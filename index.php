<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /laundry/laundry/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM employees WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $employee = $stmt->fetch();

    if ($employee && password_verify($password, $employee['password'])) {
        $_SESSION['user'] = [
            'id' => (int)$employee['id'],
            'name' => $employee['name'],
            'email' => $employee['email'],
            'role' => $employee['role'],
        ];
        header('Location: /laundry/laundry/dashboard.php');
        exit;
    }

    setFlash('error', 'Invalid email or password.');
    header('Location: /laundry/laundry/index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laundry Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/laundry/laundry/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
  <div class="card shadow-sm w-100 mobile-card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3 text-center">Laundry System Login</h1>
      <?php if ($msg = getFlash('error')): ?>
        <div class="alert alert-danger js-auto-dismiss-flash" role="alert"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <form method="post" class="vstack gap-3">
        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control form-control-lg" required>
        </div>
        <div>
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control form-control-lg" required>
        </div>
        <button class="btn btn-primary btn-lg w-100">Login</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Create an admin account first using SQL seed script.</p>
    </div>
  </div>
</div>
<script src="/laundry/laundry/assets/js/flash-auto-dismiss.js"></script>
</body>
</html>
