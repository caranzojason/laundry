<?php
declare(strict_types=1);

$host = '127.0.0.1';
$port = 3306;
$dbName = 'laundry_mvp';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
