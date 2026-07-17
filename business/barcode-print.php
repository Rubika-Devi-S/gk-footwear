<?php
/**
 * Universal Footwear POS - Product Barcode Print
 * Place at: business/barcode-print.php
 *
 * Prints unique stock barcodes for one stock inward product row.
 * Uses .NET Thermal Printer Service for direct label printing.
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

// ============================================
// CODE128 BARCODE SVG GENERATOR
// ============================================
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

// ============================================
// CHECK IF THERMAL PRINTER SERVICE IS RUNNING
// ============================================
function bp_check_printer_service(): array
{
    $result = [
        'running' => false,
        'message' => '',
        'port' => 17900
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:17900/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["PrintType" => "PING"]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error) {
        $result['message'] = 'Printer service not running: ' . $error;
        return $result;
    }

    if ($httpCode == 200 || $httpCode == 0) {
        $result['running'] = true;
        $result['message'] = 'Printer service is running.';
    } else {
        $result['message'] = 'Printer service returned HTTP ' . $httpCode;
    }

    return $result;
}

// ============================================
// SEND BARCODES TO .NET THERMAL PRINTER
// ============================================
function bp_send_to_thermal_printer(array $barcodes, array $item): void
{
    if (empty($barcodes)) {
        return;
    }

    $check = bp_check_printer_service();
    if (!$check['running']) {
        throw new RuntimeException(
            'Thermal printer service is not running. Please start the GK Thermal Print Service. ' .
            'Error: ' . $check['message']
        );
    }

    // Build print data for .NET service - Simplified for barcode labels
    $printData = [
        "PrintType" => "BARCODE_LABEL",
        "ShopName" => "GK FOOTWEAR",
        "Address" => "Gandhi Nagar, Krishnagiri.",
        "BillNo" => $item['batch_no'] ?? 'BATCH-' . $item['batch_id'],
        "OrderNo" => $item['article_no'] ?? '',
        "Customer" => "Stock Barcode",
        "Branch" => $item['branch_name'] ?? '',
        "Salesman" => $_SESSION['name'] ?? $_SESSION['username'] ?? 'Stock Manager',
        "PaymentStatus" => "STOCK",
        "GrandTotal" => (float)($item['mrp_rate'] ?? 0),
        "Items" => []
    ];

    foreach ($barcodes as $barcodeRow) {
        // Build product name with size and color
        $productName = ($item['article_name'] ?? $item['article_no'] ?? 'Product');
        $size = $item['size'] ?? '';
        $color = $item['color'] ?? '';
        
        // Create description with size and color
        $description = '';
        if (!empty($size)) {
            $description .= 'Size: ' . $size;
        }
        if (!empty($color)) {
            if (!empty($description)) $description .= ' | ';
            $description .= 'Color: ' . $color;
        }

        $printData['Items'][] = [
            "Name" => $productName,
            "Description" => $description,
            "Qty" => 1,
            "Rate" => (float)($item['mrp_rate'] ?? 0),
            "BarcodeValue" => $barcodeRow['barcode_value']
        ];
    }

    // Send to .NET thermal printer service with retry
    $maxRetries = 2;
    $retryDelay = 500;
    
    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        if ($attempt > 0) {
            usleep($retryDelay * 1000);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:17900/");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($printData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$error && $httpCode == 200 && strpos($response, 'PRINT_SUCCESS') !== false) {
            return;
        }
        
        if (strpos($error, 'Couldn\'t connect') !== false || strpos($error, 'Connection refused') !== false) {
            if ($attempt < $maxRetries) {
                continue;
            }
        }
        
        if ($error) {
            throw new RuntimeException(
                'Thermal printer service error (attempt ' . ($attempt + 1) . '): ' . $error . 
                '. Please ensure the GK Thermal Print Service is running.'
            );
        }
        
        if (strpos($response, 'PRINT_SUCCESS') === false) {
            throw new RuntimeException(
                'Thermal printer service returned: ' . ($response ?: 'Empty response')
            );
        }
    }
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

    bp_ensure_barcodes($conn, $item, $availableQty);

    // Load every active barcode for this stock item so the user can select
    // the first labels, last labels, or any individual combination.
    $barcodes = bp_rows($conn, "
        SELECT barcode_id, barcode_value
        FROM stock_barcodes
        WHERE business_id = ?
          AND stock_item_id = ?
          AND barcode_status = 'active'
        ORDER BY barcode_id ASC
        LIMIT ?
    ", 'iii', [$businessId, $stockItemId, $availableQty]);

    if (!$barcodes) {
        throw new RuntimeException('No active barcodes found for this product.');
    }

    $printQty = count($barcodes);
    $isPrintRequest = isset($_GET['print_thermal']) && $_GET['print_thermal'] == '1';

    if ($isPrintRequest) {
        header('Content-Type: application/json; charset=UTF-8');

        $selectedRaw = trim((string)($_GET['selected_ids'] ?? ''));
        if ($selectedRaw === '') {
            throw new RuntimeException('Please select at least one barcode label.');
        }

        $selectedIds = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $selectedRaw)),
            static fn(int $id): bool => $id > 0
        )));

        if (!$selectedIds) {
            throw new RuntimeException('The selected barcode list is invalid.');
        }

        // Filter against the already-authorized barcode rows. Never trust IDs
        // supplied by the browser without checking business and stock ownership.
        $selectedLookup = array_fill_keys($selectedIds, true);
        $selectedBarcodes = array_values(array_filter(
            $barcodes,
            static fn(array $row): bool => isset($selectedLookup[(int)$row['barcode_id']])
        ));

        if (!$selectedBarcodes) {
            throw new RuntimeException('None of the selected barcodes belong to this product.');
        }

        if (count($selectedBarcodes) !== count($selectedIds)) {
            throw new RuntimeException('One or more selected barcodes are invalid or inactive.');
        }

        bp_send_to_thermal_printer($selectedBarcodes, $item);
        echo json_encode([
            'success' => true,
            'message' => count($selectedBarcodes) . ' selected barcode label(s) sent to the thermal printer.'
        ]);
        exit;
    }

} catch (Throwable $e) {
    if (isset($_GET['print_thermal']) && $_GET['print_thermal'] == '1') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    
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
        :root {
            --label-w: 64mm;
            --label-h: 36mm;
            --label-gap: 4mm;
            --ink: #020617;
            --muted: #334155;
            --border: #94a3b8;
        }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background: #f8fafc;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #ffffff;
            border-bottom: 1px solid #dbe4f0;
            padding: 12px 16px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }
        .toolbar h1 { margin: 0; font-size: 18px; font-weight: 900; letter-spacing: -.02em; }
        .toolbar p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .controls input[type="number"] { width: 90px; min-height: 34px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 6px 8px; font-weight: 900; }
        .selection-summary { font-size: 12px; font-weight: 900; color: #1d4ed8; padding: 6px 10px; background: #eff6ff; border-radius: 999px; }
        .label-select { position: absolute; top: 2mm; left: 2mm; width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb; z-index: 2; }
        .label { position: relative; cursor: pointer; transition: box-shadow .15s ease, border-color .15s ease, opacity .15s ease; }
        .label.selected { border-color: #2563eb; box-shadow: 0 0 0 1.2mm rgba(37, 99, 235, .16); }
        .label.not-selected { opacity: .48; }
        .selection-no { position: absolute; top: 2mm; right: 2mm; border-radius: 999px; background: #e2e8f0; color: #334155; font-size: 9px; font-weight: 900; padding: 2px 6px; }
        .btn { border: 0; border-radius: 999px; min-height: 34px; padding: 8px 13px; font-weight: 900; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-dark { background: #111827; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #0f172a; }
        .btn-sm { min-height: 30px; padding: 4px 10px; font-size: 11px; }
        .sheet {
            padding: 14px;
            width: 100%;
        }
        .labels {
            display: grid;
            grid-template-columns: repeat(3, var(--label-w));
            gap: var(--label-gap);
            align-items: start;
            justify-content: center;
        }
        .label {
            width: var(--label-w);
            height: var(--label-h);
            background: #fff;
            border: 0.35mm dashed var(--border);
            border-radius: 2.8mm;
            padding: 2.2mm 3mm 1.9mm;
            display: grid;
            grid-template-rows: auto auto;
            align-content: start;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .label-head {
            min-width: 0;
        }
        .brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 2mm;
            font-size: 7.1px;
            line-height: 1.05;
            font-weight: 900;
            text-transform: uppercase;
            color: #0f172a;
            letter-spacing: .02em;
        }
        .brand span:first-child { max-width: 34mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .brand span:last-child { max-width: 18mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: right; }
        .product {
            margin-top: .65mm;
            font-size: 9.7px;
            font-weight: 900;
            line-height: 1.05;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .meta {
            margin-top: .7mm;
            font-size: 7.05px;
            line-height: 1.12;
            color: var(--muted);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .45mm 2mm;
        }
        .meta span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .meta b { color: #0f172a; font-weight: 900; }
        .barcode-wrap {
            margin-top: 1.15mm;
            min-width: 0;
        }
        .barcode-svg {
            width: 100%;
            height: 12.2mm;
            display: block;
            shape-rendering: crispEdges;
        }
        .barcode-bottom {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 2mm;
            margin-top: .65mm;
            line-height: 1;
        }
        .barcode-no {
            text-align: center;
            font-size: 9.7px;
            line-height: 1;
            letter-spacing: .07em;
            font-weight: 900;
            color: #020617;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .price {
            font-size: 7.2px;
            line-height: 1;
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
            color: #020617;
        }
        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .15);
            padding: 14px 18px;
            min-width: 260px;
            max-width: 380px;
            animation: slideIn 0.3s ease;
            margin-bottom: 10px;
        }
        .toast.success { border-left: 4px solid #16a34a; }
        .toast.error { border-left: 4px solid #dc2626; }
        .toast.warning { border-left: 4px solid #f59e0b; }
        .toast .title { font-weight: 700; font-size: 13px; color: #0f172a; }
        .toast .message { font-size: 11px; color: #64748b; margin-top: 3px; }
        .toast .close-btn { float: right; background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        .printer-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
        }
        .printer-status.online { background: #dcfce7; color: #15803d; }
        .printer-status.offline { background: #fee2e2; color: #b91c1c; }
        .printer-status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .printer-status.online .dot { background: #16a34a; }
        .printer-status.offline .dot { background: #dc2626; }

        @media screen and (max-width: 980px) {
            .labels { grid-template-columns: repeat(2, var(--label-w)); }
        }
        @media screen and (max-width: 620px) {
            .labels { grid-template-columns: 1fr; justify-items: center; }
        }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none !important; }
            .toast-container { display: none !important; }
            .label-select, .selection-no { display: none !important; }
            .label.not-selected { display: none !important; }
            .label.selected { box-shadow: none; border-color: #94a3b8; opacity: 1; }
            .sheet { padding: 0; }
            .labels {
                grid-template-columns: repeat(3, var(--label-w));
                gap: 2.5mm 3mm;
                justify-content: start;
            }
            .label {
                border: 0.25mm dashed #94a3b8;
                border-radius: 2mm;
                margin: 0;
                box-shadow: none;
            }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <h1>Barcode Label Print</h1>
            <p><?= bp_e($item['article_no']) ?> · Available Labels: <?= count($barcodes) ?> · Select only the labels you need</p>
        </div>
        <form method="get" class="controls" id="barcodeForm" onsubmit="return false;">
            <input type="hidden" name="stock_item_id" value="<?= (int)$stockItemId ?>">
            <label for="selectionCount"><b>Count</b></label>
            <input type="number" id="selectionCount" min="1" max="<?= count($barcodes) ?>" value="<?= min(5, count($barcodes)) ?>">
            <button type="button" class="btn btn-dark btn-sm" id="selectFirstBtn">First</button>
            <button type="button" class="btn btn-dark btn-sm" id="selectLastBtn">Last</button>
            <button type="button" class="btn btn-secondary btn-sm" id="selectAllBtn">Select All</button>
            <button type="button" class="btn btn-secondary btn-sm" id="clearSelectionBtn">Clear</button>
            <span class="selection-summary" id="selectionSummary">0 selected</span>
            <button type="button" class="btn btn-success" id="thermalPrintBtn">
                <i data-lucide="printer"></i> Print Selected
            </button>
            <button type="button" class="btn btn-primary" id="pdfPrintBtn">Print Selected PDF</button>
            <button type="button" class="btn btn-secondary" onclick="window.close()">Close</button>
            <span id="printerStatus" class="printer-status offline">
                <span class="dot"></span> Checking...
            </span>
        </form>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="sheet">
        <div class="labels" id="labelsContainer">
            <?php foreach ($barcodes as $index => $barcodeRow): ?>
                <div class="label not-selected" data-barcode-id="<?= (int)$barcodeRow['barcode_id'] ?>">
                    <input
                        type="checkbox"
                        class="label-select"
                        value="<?= (int)$barcodeRow['barcode_id'] ?>"
                        aria-label="Select barcode <?= bp_e($barcodeRow['barcode_value']) ?>"
                    >
                    <span class="selection-no">#<?= (int)($index + 1) ?></span>
                    <div class="label-head">
                        <div class="brand">
                            <span>GK FOOTWEAR</span>
                            <span><?= bp_e($item['brand_name'] ?? '') ?></span>
                        </div>
                        <div class="product"><?= bp_e($article) ?></div>
                        <div class="meta">
                            <span>Size: <b><?= bp_e($item['size']) ?></b></span>
                            <span>Color: <b><?= bp_e($item['color'] ?: '-') ?></b></span>
                        </div>
                    </div>

                    <div class="barcode-wrap">
                        <?= bp_code128_svg((string)$barcodeRow['barcode_value'], 50) ?>
                        <div class="barcode-bottom">
                            <div class="barcode-no"><?= bp_e($barcodeRow['barcode_value']) ?></div>
                            <div class="price">MRP: ₹<?= number_format((float)$item['mrp_rate'], 2) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>
    <script>
    (function() {
        'use strict';

        const thermalPrintBtn = document.getElementById('thermalPrintBtn');
        const selectionCount = document.getElementById('selectionCount');
        const selectFirstBtn = document.getElementById('selectFirstBtn');
        const selectLastBtn = document.getElementById('selectLastBtn');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const pdfPrintBtn = document.getElementById('pdfPrintBtn');
        const selectionSummary = document.getElementById('selectionSummary');
        const labelCards = Array.from(document.querySelectorAll('.label'));
        const labelCheckboxes = Array.from(document.querySelectorAll('.label-select'));
        const stockItemId = <?= (int)$stockItemId ?>;
        const container = document.getElementById('toastContainer');
        const printerStatus = document.getElementById('printerStatus');

        function showToast(title, message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast ' + (type || 'success');
            toast.innerHTML = `
                <button class="close-btn" onclick="this.parentElement.remove()">×</button>
                <div class="title">${title}</div>
                <div class="message">${message}</div>
            `;
            container.appendChild(toast);
            setTimeout(function() {
                if (toast.parentElement) toast.remove();
            }, 8000);
        }

        function showLoading(btn, loading) {
            if (loading) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Printing...';
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="printer"></i> Print Selected';
                if (window.lucide) window.lucide.createIcons();
            }
        }

        function updatePrinterStatus(status, message) {
            printerStatus.className = 'printer-status ' + (status ? 'online' : 'offline');
            printerStatus.innerHTML = '<span class="dot"></span> ' + (message || (status ? 'Online' : 'Offline'));
        }

        function checkPrinterStatus() {
            updatePrinterStatus(false, 'Checking...');
            
            fetch('barcode-print.php?stock_item_id=' + stockItemId + '&check_printer=1', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.running) {
                    updatePrinterStatus(true, 'Online');
                } else {
                    updatePrinterStatus(false, data.message || 'Offline');
                    showToast('⚠️ Printer Offline', 'Thermal printer service is not running. Please start the service.', 'warning');
                }
            })
            .catch(function() {
                updatePrinterStatus(false, 'Cannot connect');
            });
        }

        function updateSelectionUI() {
            let selectedCount = 0;
            labelCards.forEach(function(card) {
                const checkbox = card.querySelector('.label-select');
                const isSelected = checkbox.checked;
                card.classList.toggle('selected', isSelected);
                card.classList.toggle('not-selected', !isSelected);
                if (isSelected) selectedCount++;
            });
            selectionSummary.textContent = selectedCount + ' selected';
        }

        function selectRange(mode) {
            const requested = parseInt(selectionCount.value, 10);
            if (!Number.isFinite(requested) || requested < 1) {
                showToast('Selection Error', 'Enter a valid label count.', 'error');
                return;
            }
            const count = Math.min(requested, labelCheckboxes.length);
            labelCheckboxes.forEach(function(cb) { cb.checked = false; });
            const start = mode === 'last' ? labelCheckboxes.length - count : 0;
            for (let i = start; i < start + count; i++) {
                if (labelCheckboxes[i]) labelCheckboxes[i].checked = true;
            }
            updateSelectionUI();
        }

        function getSelectedIds() {
            return labelCheckboxes
                .filter(function(cb) { return cb.checked; })
                .map(function(cb) { return cb.value; });
        }

        selectFirstBtn.addEventListener('click', function() { selectRange('first'); });
        selectLastBtn.addEventListener('click', function() { selectRange('last'); });
        selectAllBtn.addEventListener('click', function() {
            labelCheckboxes.forEach(function(cb) { cb.checked = true; });
            updateSelectionUI();
        });
        clearSelectionBtn.addEventListener('click', function() {
            labelCheckboxes.forEach(function(cb) { cb.checked = false; });
            updateSelectionUI();
        });
        labelCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', updateSelectionUI);
        });
        labelCards.forEach(function(card) {
            card.addEventListener('click', function(event) {
                if (event.target.classList.contains('label-select')) return;
                const checkbox = card.querySelector('.label-select');
                checkbox.checked = !checkbox.checked;
                updateSelectionUI();
            });
        });
        pdfPrintBtn.addEventListener('click', function() {
            if (getSelectedIds().length === 0) {
                showToast('Nothing Selected', 'Select at least one barcode label.', 'warning');
                return;
            }
            window.print();
        });

        thermalPrintBtn.addEventListener('click', function() {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                showToast('Nothing Selected', 'Select at least one barcode label.', 'warning');
                return;
            }

            showLoading(this, true);

            const url = 'barcode-print.php?stock_item_id=' + encodeURIComponent(stockItemId)
                + '&selected_ids=' + encodeURIComponent(selectedIds.join(','))
                + '&print_thermal=1';

            fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                showLoading(thermalPrintBtn, false);
                if (data.success) {
                    showToast('✅ Print Success', data.message || 'Barcodes sent to thermal printer.', 'success');
                } else {
                    if (data.message && data.message.toLowerCase().includes('not running')) {
                        showToast('❌ Printer Offline', data.message + ' Please start the GK Thermal Print Service.', 'error');
                        updatePrinterStatus(false, 'Offline');
                    } else {
                        showToast('❌ Print Failed', data.message || 'Unable to print barcodes.', 'error');
                    }
                }
            })
            .catch(function(error) {
                showLoading(thermalPrintBtn, false);
                showToast('❌ Connection Error', 'Unable to connect to printer service. Please ensure the service is running.', 'error');
                updatePrinterStatus(false, 'Connection Error');
            });
        });

        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                thermalPrintBtn.click();
            }
        });

        selectRange('first');
        checkPrinterStatus();
        setInterval(checkPrinterStatus, 30000);
    })();
    </script>
</body>
</html>