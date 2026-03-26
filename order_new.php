<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerContact = trim($_POST['customer_contact'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $receiptNo = trim($_POST['receipt_no'] ?? '');

    if ($customerName === '' || $customerContact === '' || $amount <= 0 || !in_array($paymentMethod, ['cash', 'gcash'], true) || $receiptNo === '') {
        setFlash('error', 'Please fill out all required fields correctly.');
        header('Location: /laundry/laundry/order_new.php');
        exit;
    }

    $dupStmt = $pdo->prepare('SELECT id FROM orders WHERE receipt_no = ? LIMIT 1');
    $dupStmt->execute([$receiptNo]);
    if ($dupStmt->fetch()) {
        setFlash('error', 'Duplicate receipt number detected.');
        header('Location: /laundry/laundry/order_new.php');
        exit;
    }

    if (!isset($_FILES['receipt_photo']) || $_FILES['receipt_photo']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Receipt photo is required.');
        header('Location: /laundry/laundry/order_new.php');
        exit;
    }

    $tmp = $_FILES['receipt_photo']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        setFlash('error', 'Invalid receipt photo format. Use JPG, PNG, or WEBP.');
        header('Location: /laundry/laundry/order_new.php');
        exit;
    }

    $ext = $allowed[$mime];
    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $targetRelative = 'uploads/receipts/' . $fileName;
    $targetAbsolute = __DIR__ . '/' . $targetRelative;

    if (!move_uploaded_file($tmp, $targetAbsolute)) {
        setFlash('error', 'Failed to upload receipt photo.');
        header('Location: /laundry/laundry/order_new.php');
        exit;
    }

    $pdo->beginTransaction();
    try {
        $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE name = ? AND contact = ? LIMIT 1');
        $customerStmt->execute([$customerName, $customerContact]);
        $customer = $customerStmt->fetch();

        if ($customer) {
            $customerId = (int)$customer['id'];
        } else {
            $insertCustomer = $pdo->prepare('INSERT INTO customers (name, contact) VALUES (?, ?)');
            $insertCustomer->execute([$customerName, $customerContact]);
            $customerId = (int)$pdo->lastInsertId();
        }

        $sessionStmt = $pdo->prepare("SELECT id FROM cash_sessions WHERE employee_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $sessionStmt->execute([currentUser()['id']]);
        $openSession = $sessionStmt->fetch();
        $cashSessionId = $openSession ? (int)$openSession['id'] : null;

        $insertOrder = $pdo->prepare("
            INSERT INTO orders (customer_id, employee_id, cash_session_id, amount, payment_method, receipt_no, receipt_photo, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $insertOrder->execute([
            $customerId,
            currentUser()['id'],
            $cashSessionId,
            $amount,
            $paymentMethod,
            $receiptNo,
            $targetRelative,
        ]);

        if ($cashSessionId !== null && $paymentMethod === 'cash') {
            $sumCashStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) AS cash_sales
                FROM orders
                WHERE cash_session_id = ? AND payment_method = 'cash' AND status = 'completed'
            ");
            $sumCashStmt->execute([$cashSessionId]);
            $cashSales = (float)$sumCashStmt->fetch()['cash_sales'];

            $updateSession = $pdo->prepare("UPDATE cash_sessions SET expected_cash = opening_cash + ? WHERE id = ?");
            $updateSession->execute([$cashSales, $cashSessionId]);
        }

        $pdo->commit();
        setFlash('success', 'Order saved successfully.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        if (is_file($targetAbsolute)) {
            unlink($targetAbsolute);
        }
        setFlash('error', 'Failed to save order: ' . $e->getMessage());
    }

    header('Location: /laundry/laundry/order_new.php');
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<div class="card shadow-sm">
  <div class="card-header fw-semibold">New Order Entry</div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Customer Name</label>
        <input type="text" name="customer_name" class="form-control form-control-lg" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Contact Number</label>
        <input type="text" name="customer_contact" class="form-control form-control-lg" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Amount</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control form-control-lg" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Payment Method</label>
        <select name="payment_method" class="form-select form-select-lg" required>
          <option value="cash" selected>Cash</option>
          <option value="gcash">GCash</option>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Receipt Number</label>
        <input type="text" name="receipt_no" class="form-control form-control-lg" required>
      </div>
      <div class="col-12">
        <label class="form-label">Receipt Photo</label>
        <input type="file" name="receipt_photo" class="form-control form-control-lg" accept="image/*" capture="environment" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary btn-lg w-100 w-md-auto">Save Order</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
