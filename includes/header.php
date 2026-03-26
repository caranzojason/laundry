<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$user = currentUser();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laundry MVP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/laundry/laundry/assets/css/style.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/laundry/laundry/dashboard.php">Laundry MVP</a>
    <?php if ($user): ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link" href="/laundry/laundry/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="/laundry/laundry/order_new.php">New Order</a></li>
          <li class="nav-item"><a class="nav-link" href="/laundry/laundry/cash_session.php">Cash Session</a></li>
          <li class="nav-item"><a class="nav-link" href="/laundry/laundry/reports.php">Reports</a></li>
        </ul>
        <span class="navbar-text me-3">
          <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)
        </span>
        <a class="btn btn-sm btn-outline-light" href="/laundry/laundry/logout.php">Logout</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
<main class="container py-3 py-md-4">
  <?php if ($msg = getFlash('success')): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($msg = getFlash('error')): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
