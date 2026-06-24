<?php
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
/**
 * logout.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
redirect('login.php');