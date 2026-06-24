<?php
/**
 * index.php — Entry point
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (isset($_SESSION['role'])) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}