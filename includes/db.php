<?php
/**
 * db.php — Database connection
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'csi_hub');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:30px;background:#fde9e9;border:1px solid #f5c0c0;border-radius:8px;max-width:600px;margin:40px auto">
        <strong style="color:#c53030">Database Connection Failed</strong><br><br>
        <code>' . htmlspecialchars($e->getMessage()) . '</code><br><br>
        <strong>To fix this:</strong><br>
        1. Open XAMPP and start <strong>MySQL</strong><br>
        2. Go to <a href="http://localhost/phpmyadmin">phpMyAdmin</a><br>
        3. Create a database called <strong>csi_hub</strong><br>
        4. Import <strong>api/schema.sql</strong>
    </div>');
}