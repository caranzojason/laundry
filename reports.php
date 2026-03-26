<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$salesStmt = $pdo->prepare("
    SELECT
      DATE(created_at) AS day,
      COUNT(*) AS orders_count,
      COALESCE(SUM(amount), 0) AS total_sales,
      COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END), 0) AS cash_sales,
      COALESCE(SUM(CASE WHEN payment_method = 'gcash' THEN amount ELSE 0 END), 0) AS gcash_sales
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'
    GROUP BY DATE(created_at)
    ORDER BY day DESC
");
$salesStmt->execute([$from, $to]);
$daily = $salesStmt->fetchAll();

$varianceStmt = $pdo->prepare("
    SELECT cs.*, e.name AS employee_name
    FROM cash_sessions cs
    INNER JOIN employees e ON e.id = cs.employee_id
    WHERE DATE(cs.start_time) BETWEEN ? AND ?
    ORDER BY cs.id DESC
");
$varianceStmt->execute([$from, $to]);
$sessionRows = $varianceStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-6 col-md-4">
        <label class="form-label">From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label">To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
      </div>
      <div class="col-12 col-md-4">
        <button class="btn btn-primary w-100">Apply Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header fw-semibold">Daily Sales</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Date</th><th>Orders</th><th>Total</th><th>Cash</th><th>GCash</th></tr></thead>
      <tbody>
      <?php foreach ($daily as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['day']) ?></td>
          <td><?= (int)$r['orders_count'] ?></td>
          <td><?= number_format((float)$r['total_sales'], 2) ?></td>
          <td><?= number_format((float)$r['cash_sales'], 2) ?></td>
          <td><?= number_format((float)$r['gcash_sales'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header fw-semibold">Cash Variance by Session</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>ID</th><th>Employee</th><th>Status</th><th>Expected</th><th>Actual</th><th>Variance</th></tr></thead>
      <tbody>
      <?php foreach ($sessionRows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['employee_name']) ?></td>
          <td><?= htmlspecialchars($r['status']) ?></td>
          <td><?= $r['expected_cash'] === null ? '-' : number_format((float)$r['expected_cash'], 2) ?></td>
          <td><?= $r['actual_cash'] === null ? '-' : number_format((float)$r['actual_cash'], 2) ?></td>
          <td><?= $r['variance'] === null ? '-' : number_format((float)$r['variance'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
