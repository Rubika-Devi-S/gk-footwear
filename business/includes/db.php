<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');


$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

$isLocalhost = in_array($serverName, ['localhost', '127.0.0.1'], true)
    || str_contains($serverName, '.test')
    || str_contains($serverName, '.local');

if ($isLocalhost) {
    // Local WAMP/XAMPP connecting to remote Hostinger database
    $dbHost = 'auth-db1740.hstgr.io';
    $dbPort = 3306;
} else {
    // Live Hostinger server
    $dbHost = 'localhost';
    $dbPort = 3306;
}

$dbName = 'u966043993_footwear';
$dbUser = 'u966043993_footwear';
$dbPass = '1E:dnF1Xh0';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('PDO Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if (!$conn) {
    die('MySQLi Database connection failed: ' . htmlspecialchars(mysqli_connect_error(), ENT_QUOTES, 'UTF-8'));
}

mysqli_set_charset($conn, 'utf8mb4');
?>