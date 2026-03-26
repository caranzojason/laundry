<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if ($name === '' || $email === '' || $password === '') {
        $message = 'All fields are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO employees (name, email, password, role) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $hash, $role === 'staff' ? 'staff' : 'admin']);
            $message = 'User created successfully. You can now login.';
        } catch (Throwable $e) {
            $message = 'Failed: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="card mx-auto" style="max-width: 520px;">
    <div class="card-body">
      <h1 class="h5 mb-3">Create Admin/Staff User</h1>
      <?php if ($message !== ''): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <form method="post" class="vstack gap-2">
        <input class="form-control" name="name" placeholder="Name" required>
        <input class="form-control" type="email" name="email" placeholder="Email" required>
        <input class="form-control" type="password" name="password" placeholder="Password" required>
        <select class="form-select" name="role">
          <option value="admin">admin</option>
          <option value="staff">staff</option>
        </select>
        <button class="btn btn-primary">Create User</button>
      </form>
      <a class="btn btn-link px-0 mt-2" href="/laundry/laundry/index.php">Go to Login</a>
    </div>
  </div>
</div>
</body>
</html>
