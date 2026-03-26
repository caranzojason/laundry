<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /laundry/laundry/index.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        $_SESSION['flash_error'] = 'Admin access required.';
        header('Location: /laundry/laundry/dashboard.php');
        exit;
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION["flash_{$type}"] = $message;
}

function getFlash(string $type): ?string
{
    $key = "flash_{$type}";
    if (!isset($_SESSION[$key])) {
        return null;
    }
    $message = $_SESSION[$key];
    unset($_SESSION[$key]);
    return $message;
}
