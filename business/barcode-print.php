<?php
/**
 * Universal Footwear POS - Product Barcode Print
 * Place at: business/barcode-print.php
 *
 * Prints unique stock barcodes for one stock inward product row.
 * Default print quantity = current available stock quantity.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

if (function_exists('require_page_access')) {
    require_page_access($conn, 'stock-inward.php');
}

function bp_e($value): string
{
    if (function_exists('e')) {
        return e((string)$value);
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bp_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function bp_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function bp_table_exists(mysqli $conn, string $table): bool
{
    if (function_exists('table_exists')) {
        return (bool) table_exists($conn, $table);
    }

    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");

    return $res && mysqli_num_rows($res) > 0;
}

function bp_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }

    if ($types !== '' && $params) {
        $refs = [$stmt, $types];

        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }

        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : [];
    mysqli_stmt_close($stmt);

    return $row ?: [];
}

function bp_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }

    if ($types !== '' && $params) {
        $refs = [$stmt, $types];

        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }

        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function bp_has_print_access(mysqli $conn): bool
{
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return true;
    }

    if (!function_exists('current_role_id') || !bp_table_exists($conn, 'business_sidebar_menus') || !bp_table_exists($conn, 'business_role_sidebar_access')) {
        return true;
    }

    $businessId = bp_business_id();
    $roleId = (int) current_role_id();

    if ($businessId <= 0 || $roleId <= 0) {
        return true;
    }

    $row = bp_one($conn, "
        SELECT
            COALESCE(rsa.can_print, rsa.can_view, 0) AS can_print
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = 'stock-inward.php'
          AND sm.is_active = 1
        LIMIT 1
    ", 'ii', [$roleId, $businessId]);

    return !$row || (int)($row['can_print'] ?? 0) === 1;
}

function bp_sequence(mysqli $conn, int $businessId, ?int $branchId, string $key, string $prefix, int $padding): string
{
    $stmt = mysqli_prepare($conn, "
        SELECT sequence_id, current_number, prefix, padding_length
        FROM number_sequences
        WHERE business_id = ?
          AND ((branch_id IS NULL AND ? IS NULL) OR branch_id = ?)
          AND sequence_key = ?
        LIMIT 1
        FOR UPDATE
    ");
    mysqli_stmt_bind_param($stmt, 'iiis', $businessId, $branchId, $branchId, $key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        $zero = 0;
        $active = 1;
        $stmt = mysqli_prepare($conn, "
            INSERT INTO number_sequences
            (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'iissiii', $businessId, $branchId, $key, $prefix, $zero, $padding, $active);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $current = 0;
        $seqPrefix = $prefix;
        $seqPadding = $padding;
    } else {
        $current = (int)$row['current_number'];
        $seqPrefix = (string)($row['prefix'] ?: $prefix);
        $seqPadding = (int)($row['padding_length'] ?: $padding);
    }

    $next = $current + 1;

    $stmt = mysqli_prepare($conn, "
        UPDATE number_sequences
        SET current_number = ?, updated_at = NOW()
        WHERE business_id = ?
          AND ((branch_id IS NULL AND ? IS NULL) OR branch_id = ?)
          AND sequence_key = ?
    ");
    mysqli_stmt_bind_param($stmt, 'iiiis', $next, $businessId, $branchId, $branchId, $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $seqPrefix . '-' . str_pad((string)$next, $seqPadding, '0', STR_PAD_LEFT);
}

function bp_generate_barcode(mysqli $conn, int $businessId, int $branchId): string
{
    for ($i = 0; $i < 20; $i++) {
        $barcode = bp_sequence($conn, $businessId, null, 'stock_barcode', 'STK', 6);
        $row = bp_one($conn, "SELECT COUNT(*) AS total FROM stock_barcodes WHERE barcode_value = ?", 's', [$barcode]);

        if ((int)($row['total'] ?? 0) === 0) {
            return $barcode;
        }
    }

    return 'STK-' . date('YmdHis') . random_int(100, 999);
}

function bp_ensure_barcodes(mysqli $conn, array $item, int $requiredQty): void
{
    $businessId = (int)$item['business_id'];
    $branchId = (int)$item['branch_id'];
    $batchId = (int)$item['batch_id'];
    $stockItemId = (int)$item['stock_item_id'];

    if ($requiredQty <= 0) {
        return;
    }

    mysqli_begin_transaction($conn);

    try {
        $row = bp_one($conn, "
            SELECT COUNT(*) AS total
            FROM stock_barcodes
            WHERE business_id = ?
              AND stock_item_id = ?
              AND barcode_status = 'active'
            FOR UPDATE
        ", 'ii', [$businessId, $stockItemId]);

        $existing = (int)($row['total'] ?? 0);
        $missing = max(0, $requiredQty - $existing);

        for ($i = 0; $i < $missing; $i++) {
            $barcode = bp_generate_barcode($conn, $businessId, $branchId);
            $stmt = mysqli_prepare($conn, "
                INSERT INTO stock_barcodes
                (business_id, branch_id, batch_id, stock_item_id, barcode_value, barcode_status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            mysqli_stmt_bind_param($stmt, 'iiiis', $businessId, $branchId, $batchId, $stockItemId, $barcode);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function bp_code128_svg(string $text, int $height = 48): string
{
    $patterns = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112'
    ];

    $codes = [104];
    $checksum = 104;
    $position = 1;

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($chars as $ch) {
        $ord = ord($ch);
        if ($ord < 32 || $ord > 126) {
            $ord = 32;
        }

        $value = $ord - 32;
        $codes[] = $value;
        $checksum += $value * $position;
        $position++;
    }

    $codes[] = $checksum % 103;
    $codes[] = 106;

    $module = 1.45;
    $quiet = 10;
    $x = $quiet;
    $bars = '';

    foreach ($codes as $code) {
        $pattern = $patterns[$code] ?? $patterns[0];
        $black = true;

        for ($i = 0; $i < strlen($pattern); $i++) {
            $w = ((int)$pattern[$i]) * $module;

            if ($black) {
                $bars .= '<rect x="' . number_format($x, 2, '.', '') . '" y="0" width="' . number_format($w, 2, '.', '') . '" height="' . $height . '" fill="#000"/>';
            }

            $x += $w;
            $black = !$black;
        }
    }

    $width = $x + $quiet;

    return '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . number_format($width, 2, '.', '') . ' ' . $height . '" preserveAspectRatio="none">' . $bars . '</svg>';
}

try {
    $businessId = bp_business_id();
    $stockItemId = (int)($_GET['stock_item_id'] ?? 0);

    if ($businessId <= 0) {
        throw new RuntimeException('Business session missing. Please login again.');
    }

    if (!bp_has_print_access($conn)) {
        throw new RuntimeException('You do not have barcode print permission.');
    }

    if ($stockItemId <= 0) {
        throw new RuntimeException('Invalid product selected.');
    }

    $item = bp_one($conn, "
        SELECT
            sii.*,
            sib.batch_no,
            sib.inward_date,
            b.branch_name,
            b.floor_name,
            c.category_name,
            br.brand_name
        FROM stock_inward_items sii
        INNER JOIN stock_inward_batches sib
            ON sib.batch_id = sii.batch_id
           AND sib.business_id = sii.business_id
        INNER JOIN branches b
            ON b.branch_id = sii.branch_id
           AND b.business_id = sii.business_id
        LEFT JOIN categories c
            ON c.category_id = sii.category_id
           AND c.business_id = sii.business_id
        LEFT JOIN brands br
            ON br.brand_id = sii.brand_id
           AND br.business_id = sii.business_id
        WHERE sii.business_id = ?
          AND sii.stock_item_id = ?
          AND sii.item_status = 'active'
          AND sib.batch_status = 'active'
        LIMIT 1
    ", 'ii', [$businessId, $stockItemId]);

    if (!$item) {
        throw new RuntimeException('Product stock item not found or inactive.');
    }

    $availableQty = max(0, (int)floor((float)($item['available_qty'] ?? 0)));

    if ($availableQty <= 0) {
        throw new RuntimeException('Current stock quantity is zero. Barcode labels cannot be printed.');
    }

    // Ensure old/migrated items also have one active barcode for each current stock quantity.
    bp_ensure_barcodes($conn, $item, $availableQty);

    $requestedQty = (int)($_GET['qty'] ?? $availableQty);
    $printQty = max(1, min($requestedQty, $availableQty));

    $barcodes = bp_rows($conn, "
        SELECT barcode_id, barcode_value
        FROM stock_barcodes
        WHERE business_id = ?
          AND stock_item_id = ?
          AND barcode_status = 'active'
        ORDER BY barcode_id ASC
        LIMIT ?
    ", 'iii', [$businessId, $stockItemId, $printQty]);

    if (!$barcodes) {
        throw new RuntimeException('No active barcodes found for this product.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Barcode Print Error</title><style>body{font-family:Arial;padding:30px;background:#f8fafc;color:#0f172a}.box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;max-width:650px;margin:auto}.err{color:#dc2626;font-weight:800}</style></head><body><div class="box"><h2>Barcode Print Error</h2><p class="err">' . bp_e($e->getMessage()) . '</p><button onclick="history.back()">Go Back</button></div></body></html>';
    exit;
}

$article = trim((string)($item['article_name'] ?: $item['article_no']));
$pageTitle = 'Barcode Print - ' . $article;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= bp_e($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; color: #111827; background: #f8fafc; }
        .toolbar { position: sticky; top: 0; z-index: 10; background: #ffffff; border-bottom: 1px solid #dbe4f0; padding: 12px 16px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: center; }
        .toolbar h1 { margin: 0; font-size: 18px; font-weight: 800; }
        .toolbar p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .controls input { width: 90px; min-height: 34px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 6px 8px; font-weight: 800; }
        .btn { border: 0; border-radius: 999px; min-height: 34px; padding: 8px 13px; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-dark { background: #111827; color: #fff; }
        .sheet { padding: 12px; }
        .labels { display: grid; grid-template-columns: repeat(3, 64mm); gap: 4mm; align-items: start; justify-content: center; }
        .label { width: 64mm; height: 38mm; background: #fff; border: 1px dashed #94a3b8; border-radius: 3mm; padding: 3mm; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; page-break-inside: avoid; }
        .brand { display: flex; justify-content: space-between; gap: 2mm; font-size: 8px; font-weight: 800; text-transform: uppercase; color: #334155; }
        .product { font-size: 10px; font-weight: 800; line-height: 1.15; color: #0f172a; margin-top: 1mm; }
        .meta { font-size: 8px; line-height: 1.2; color: #334155; display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 2mm; margin-top: 1mm; }
        .barcode-wrap { margin-top: 1.5mm; }
        .barcode-svg { width: 100%; height: 13mm; display: block; }
        .barcode-no { text-align: center; font-size: 10px; letter-spacing: .08em; font-weight: 900; margin-top: .5mm; }
        .price { font-size: 11px; font-weight: 900; text-align: right; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .labels { gap: 0; grid-template-columns: repeat(3, 64mm); justify-content: start; }
            .label { border: 0.2mm solid #000; border-radius: 0; margin: 0; }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1>Barcode Label Print</h1>
            <p><?= bp_e($item['article_no']) ?> · Available Stock: <?= (int)$availableQty ?> · Printing: <?= (int)$printQty ?></p>
        </div>
        <form method="get" class="controls">
            <input type="hidden" name="stock_item_id" value="<?= (int)$stockItemId ?>">
            <label for="qty"><b>Qty</b></label>
            <input type="number" id="qty" name="qty" min="1" max="<?= (int)$availableQty ?>" value="<?= (int)$printQty ?>">
            <button type="submit" class="btn btn-dark">Update</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">Print Barcode</button>
            <button type="button" class="btn btn-dark" onclick="window.close()">Close</button>
        </form>
    </div>

    <div class="sheet">
        <div class="labels">
            <?php foreach ($barcodes as $barcodeRow): ?>
                <div class="label">
                    <div>
                        <div class="brand">
                            <span>GK FOOTWEAR</span>
                            <span><?= bp_e($item['brand_name'] ?? '') ?></span>
                        </div>
                        <div class="product"><?= bp_e($article) ?></div>
                        <div class="meta">
                            <span>Article: <b><?= bp_e($item['article_no']) ?></b></span>
                            <span>Size: <b><?= bp_e($item['size']) ?></b></span>
                            <span>Color: <b><?= bp_e($item['color'] ?: '-') ?></b></span>
                            <span>Batch: <b><?= bp_e($item['batch_no']) ?></b></span>
                        </div>
                    </div>

                    <div class="barcode-wrap">
                        <?= bp_code128_svg((string)$barcodeRow['barcode_value'], 48) ?>
                        <div class="barcode-no"><?= bp_e($barcodeRow['barcode_value']) ?></div>
                    </div>

                    <div class="price">MRP: ₹<?= number_format((float)$item['mrp_rate'], 2) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
