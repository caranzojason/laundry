<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = currentUser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        if ($openingCash < 0) {
            setFlash('error', 'Opening cash cannot be negative.');
            header('Location: /laundry/laundry/cash_session.php');
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM cash_sessions WHERE employee_id = ? AND status = 'open' LIMIT 1");
        $check->execute([$userId]);
        if ($check->fetch()) {
            setFlash('error', 'You already have an open session.');
            header('Location: /laundry/laundry/cash_session.php');
            exit;
        }

        $insert = $pdo->prepare("
            INSERT INTO cash_sessions (employee_id, opening_cash, expected_cash, start_time, status)
            VALUES (?, ?, ?, NOW(), 'open')
        ");
        $insert->execute([$userId, $openingCash, $openingCash]);
        setFlash('success', 'Cash session started.');
    }

    if ($action === 'end') {
        $actualCash = (float)($_POST['actual_cash'] ?? 0);
        $sessionStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE employee_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $sessionStmt->execute([$userId]);
        $session = $sessionStmt->fetch();

        if (!$session) {
            setFlash('error', 'No open session found.');
            header('Location: /laundry/laundry/cash_session.php');
            exit;
        }

        $cashSalesStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS cash_sales
            FROM orders
            WHERE cash_session_id = ? AND payment_method = 'cash' AND status = 'completed'
        ");
        $cashSalesStmt->execute([$session['id']]);
        $cashSales = (float)$cashSalesStmt->fetch()['cash_sales'];

        $expectedCash = (float)$session['opening_cash'] + $cashSales;
        $variance = $actualCash - $expectedCash;

        $update = $pdo->prepare("
            UPDATE cash_sessions
            SET closing_cash = ?, expected_cash = ?, actual_cash = ?, variance = ?, end_time = NOW(), status = 'closed'
            WHERE id = ?
        ");
        $update->execute([$actualCash, $expectedCash, $actualCash, $variance, $session['id']]);

        setFlash('success', 'Session closed. Variance: PHP ' . number_format($variance, 2));
    }

    header('Location: /laundry/laundry/cash_session.php');
    exit;
}

$openStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE employee_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
$openStmt->execute([$userId]);
$openSession = $openStmt->fetch();

$historyStmt = $pdo->prepare("SELECT * FROM cash_sessions WHERE employee_id = ? ORDER BY id DESC LIMIT 10");
$historyStmt->execute([$userId]);
$history = $historyStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><?= $openSession ? 'End Shift' : 'Start Shift' ?></div>
      <div class="card-body">
        <?php if (!$openSession): ?>
          <form method="post" class="vstack gap-3">
            <input type="hidden" name="action" value="start">
            <div>
              <label class="form-label">Opening Cash</label>
              <input type="number" name="opening_cash" min="0" step="0.01" class="form-control form-control-lg" required>
            </div>
            <button class="btn btn-primary btn-lg">Start Session</button>
          </form>
        <?php else: ?>
          <p class="mb-1">Opening: PHP <?= number_format((float)$openSession['opening_cash'], 2) ?></p>
          <p class="mb-3 text-muted">Expected cash updates as cash orders are added.</p>
          <form method="post" class="vstack gap-3">
            <input type="hidden" name="action" value="end">
            <div>
              <label class="form-label">Actual Cash Counted</label>
              <input type="number" name="actual_cash" min="0" step="0.01" class="form-control form-control-lg" required>
            </div>
            <button class="btn btn-danger btn-lg">End Session</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Recent Sessions</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr><th>ID</th><th>Status</th><th>Opening</th><th>Expected</th><th>Actual</th><th>Variance</th></tr>
          </thead>
          <tbody>
            <?php foreach ($history as $row): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><span class="badge <?= $row['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                <td><?= number_format((float)$row['opening_cash'], 2) ?></td>
                <td><?= $row['expected_cash'] === null ? '-' : number_format((float)$row['expected_cash'], 2) ?></td>
                <td><?= $row['actual_cash'] === null ? '-' : number_format((float)$row['actual_cash'], 2) ?></td>
                <td><?= $row['variance'] === null ? '-' : number_format((float)$row['variance'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
