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

    if ($amount <= 0 || !in_array($paymentMethod, ['cash', 'gcash'], true) || $receiptNo === '') {
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

$extra_scripts = <<<'HTML'
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js"></script>
<script src="/laundry/laundry/assets/js/receipt_scan.js"></script>
<script>
document.getElementById('order_form_clear').addEventListener('click', function () {
  var r = document.getElementById('receipt_no');
  var n = document.getElementById('customer_name');
  var c = document.getElementById('customer_contact');
  var a = document.getElementById('amount');
  var p = document.getElementById('receipt_photo');
  var pm = document.querySelector('#order_form select[name="payment_method"]');
  var st = document.getElementById('receipt_scan_status');
  if (r) r.value = '';
  if (n) n.value = '';
  if (c) c.value = '';
  if (a) a.value = '';
  if (p) p.value = '';
  if (pm) pm.value = 'cash';
  if (st) {
    st.classList.add('d-none');
    st.textContent = '';
    st.innerHTML = '';
    st.classList.remove('alert-success', 'alert-warning', 'alert-info');
  }
});
</script>
HTML;
?>

<div class="card shadow-sm">
  <div class="card-body">
    <form id="order_form" method="post" enctype="multipart/form-data" class="row g-3">
      <div class="col-12">
        <label class="form-label">Receipt Photo</label>
        <input type="file" id="receipt_photo" name="receipt_photo" class="form-control form-control-lg" accept="image/*" capture="environment" required>
      </div>
      <div class="col-12 col-md-6 d-none" aria-hidden="true">
        <label class="form-label" for="receipt_template">Receipt layout</label>
        <select id="receipt_template" class="form-select form-select-lg">
          <option value="auto" selected>Auto-detect (main vs backup)</option>
          <option value="main">Main invoice (SERVICE INVOICE)</option>
          <option value="backup">Backup receipt (SERVICE RECEIPT)</option>
        </select>
      </div>
      <div class="col-12 d-flex align-items-end">
        <div id="receipt_scan_status" class="alert alert-secondary py-2 small mb-0 w-100 d-none" role="status"></div>
      </div>
      <div class="col-12">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <label class="form-label mb-0 text-nowrap" for="receipt_no">Receipt Number</label>
          <input type="text" id="receipt_no" name="receipt_no" class="form-control form-control-sm receipt-no-input" required autocomplete="off">
        </div>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="customer_name">Customer Name <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" id="customer_name" name="customer_name" class="form-control form-control-lg" autocomplete="off">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="customer_contact">Contact Number <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" id="customer_contact" name="customer_contact" class="form-control form-control-lg" autocomplete="off">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label" for="amount">Amount</label>
        <input type="number" id="amount" step="0.01" min="0.01" name="amount" class="form-control form-control-lg" required>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label">Payment Method</label>
        <select name="payment_method" class="form-select form-select-lg" required>
          <option value="cash" selected>Cash</option>
          <option value="gcash">GCash</option>
        </select>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary btn-lg">Save Order</button>
        <button type="button" id="order_form_clear" class="btn btn-outline-secondary btn-lg">Clear</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
