<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$today = date('Y-m-d');

$salesStmt = $pdo->prepare("
    SELECT
      COALESCE(SUM(amount), 0) AS total_sales,
      COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) AS cash_sales,
      COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount ELSE 0 END), 0) AS gcash_sales,
      COUNT(*) AS orders_count
    FROM orders
    WHERE DATE(created_at) = ? AND status = 'completed'
");
$salesStmt->execute([$today]);
$sales = $salesStmt->fetch();

$dupStmt = $pdo->prepare("
    SELECT receipt_no, COUNT(*) AS c
    FROM orders
    WHERE DATE(created_at) = ?
    GROUP BY receipt_no
    HAVING c > 1
");
$dupStmt->execute([$today]);
$duplicateReceipts = $dupStmt->fetchAll();

$openSessionStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE employee_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
$openSessionStmt->execute([currentUser()['id']]);
$openSession = $openSessionStmt->fetch();

$variance = $openSession['variance'] ?? null;

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Today Sales</small><div class="h5 mb-0">PHP <?= number_format((float)$sales['total_sales'], 2) ?></div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Cash</small><div class="h5 mb-0">PHP <?= number_format((float)$sales['cash_sales'], 2) ?></div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">GCash</small><div class="h5 mb-0">PHP <?= number_format((float)$sales['gcash_sales'], 2) ?></div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100"><div class="card-body"><small class="text-muted">Orders</small><div class="h5 mb-0"><?= (int)$sales['orders_count'] ?></div></div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold">Duplicate Receipt Alerts (Today)</div>
      <div class="card-body">
        <?php if (!$duplicateReceipts): ?>
          <p class="mb-0 text-success">No duplicate receipts found.</p>
        <?php else: ?>
          <ul class="mb-0">
            <?php foreach ($duplicateReceipts as $dup): ?>
              <li>Receipt <strong><?= htmlspecialchars($dup['receipt_no']) ?></strong> appears <?= (int)$dup['c'] ?> times.</li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold">Cash Session Status</div>
      <div class="card-body">
        <?php if ($openSession): ?>
          <p class="mb-1">Current session is <span class="badge bg-success">OPEN</span></p>
          <p class="mb-1">Opening: PHP <?= number_format((float)$openSession['opening_cash'], 2) ?></p>
          <p class="mb-1">Expected: PHP <?= number_format((float)($openSession['expected_cash'] ?? 0), 2) ?></p>
          <p class="mb-0">Variance: <?= $variance === null ? '-' : 'PHP ' . number_format((float)$variance, 2) ?></p>
        <?php else: ?>
          <p class="mb-0 text-muted">No open cash session. Start one in Cash Session page.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
