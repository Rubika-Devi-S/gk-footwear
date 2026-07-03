<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'dashboard.php');

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section p-3 p-lg-3">

<?php
$businessId = current_business_id();

function scalar_count(mysqli $conn, string $sql, int $businessId) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($stmt);
    $value = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0;
    mysqli_stmt_close($stmt);
    return $value;
}

$totalBranches = scalar_count($conn, "SELECT COUNT(*) FROM branches WHERE business_id = ?", $businessId);
$totalUsers = scalar_count($conn, "SELECT COUNT(*) FROM users WHERE business_id = ?", $businessId);
$totalBills = scalar_count($conn, "SELECT COUNT(*) FROM bills WHERE business_id = ?", $businessId);
$totalStock = scalar_count($conn, "SELECT COALESCE(SUM(available_qty),0) FROM stock_inward_items WHERE business_id = ?", $businessId);

$recentBills = [];
if (table_exists($conn, 'bills')) {
    $stmt = mysqli_prepare($conn, "
        SELECT bill_no, customer_name, net_amount, payment_status, bill_date
        FROM bills
        WHERE business_id = ?
        ORDER BY bill_id DESC
        LIMIT 8
    ");
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($rs)) {
        $recentBills[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="page-head-card mb-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1">Dashboard</h1>
            <p class="text-muted-custom mb-0 small">GK Footwear business POS overview.</p>
        </div>
        <a href="bill-create.php" class="btn brand-gradient rounded-4 fw-bold btn-sm px-3">Create Bill</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="kpi-card">
            <div class="kpi-icon text-white" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="store"></i></div>
            <div><div class="kpi-label">Branches / Firms</div><p class="kpi-value"><?= (int)$totalBranches ?></p><p class="kpi-sub">Firm-wise stock control</p></div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="kpi-card">
            <div class="kpi-icon bg-success-subtle text-success"><i data-lucide="users"></i></div>
            <div><div class="kpi-label">Users</div><p class="kpi-value"><?= (int)$totalUsers ?></p><p class="kpi-sub">Role-based access</p></div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="kpi-card">
            <div class="kpi-icon bg-warning-subtle text-warning"><i data-lucide="boxes"></i></div>
            <div><div class="kpi-label">Available Stock</div><p class="kpi-value"><?= (int)$totalStock ?></p><p class="kpi-sub">Current footwear stock</p></div>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="kpi-card">
            <div class="kpi-icon text-white" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);"><i data-lucide="receipt"></i></div>
            <div><div class="kpi-label">Bills</div><p class="kpi-value"><?= (int)$totalBills ?></p><p class="kpi-sub">Bill of Supply</p></div>
        </article>
    </div>
</div>

<section class="card-ui overflow-hidden">
    <div class="p-3 p-lg-4 d-flex justify-content-between">
        <div>
            <h2 class="fw-bold fs-6 mb-1">Recent Bills</h2>
            <p class="text-muted-custom small mb-0">Latest billing activity.</p>
        </div>
    </div>

    <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3">
        <table class="table">
            <thead><tr><th>Bill No</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (!$recentBills): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No bills created yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentBills as $bill): ?>
                <tr>
                    <td><?= e($bill['bill_no']) ?></td>
                    <td><?= e($bill['customer_name']) ?></td>
                    <td><?= money_inr($bill['net_amount']) ?></td>
                    <td><?= e(ucfirst($bill['payment_status'])) ?></td>
                    <td><?= e(date('d-m-Y', strtotime($bill['bill_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-md-none px-3 pb-3 d-grid gap-3">
        <?php if (!$recentBills): ?>
            <div class="mobile-data-card text-center text-muted">No bills created yet.</div>
        <?php endif; ?>
        <?php foreach ($recentBills as $bill): ?>
            <div class="mobile-data-card">
                <div class="d-flex justify-content-between gap-2">
                    <div><div class="fw-bold"><?= e($bill['bill_no']) ?></div><small class="text-muted-custom"><?= e($bill['customer_name']) ?></small></div>
                    <div class="fw-bold"><?= money_inr($bill['net_amount']) ?></div>
                </div>
                <div class="small text-muted-custom mt-2"><?= e(ucfirst($bill['payment_status'])) ?> · <?= e(date('d-m-Y', strtotime($bill['bill_date']))) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
