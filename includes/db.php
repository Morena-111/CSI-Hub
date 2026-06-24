<?php
/**
 * db.php — Database connection
 */

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#7a1f1f">
            <h2>Service Temporarily Unavailable</h2>
            <p>Please contact info@researchunlimitedsa.co.za</p>
        </div>');
    } else {
        die('<pre style="color:red;padding:20px">Database connection failed: ' . $e->getMessage() . '</pre>');
    }
}