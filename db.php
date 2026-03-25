<?php
// Cấu hình kết nối DB. Cập nhật thông tin cho môi trường của bạn.
$dbHost = '127.0.0.1';
$dbName = 'vlxd';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    http_response_code(500);
    die('Không thể kết nối cơ sở dữ liệu: ' . htmlspecialchars($e->getMessage()));
}
