<?php
/**
 * get_linked_options.php
 */
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';

$type = $_GET['type'] ?? 'company';
header('Content-Type: application/json');

if ($type === 'company') {
    $rows = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();
} elseif ($type === 'school') {
    $rows = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
} else {
    $rows = [];
}

echo json_encode($rows);