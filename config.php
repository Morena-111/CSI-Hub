<?php
/**
 * config.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Force local settings even when accessed via ngrok
define('BASE_URL', '/csi-hub/');
define('ENVIRONMENT', 'local');

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'csi_hub');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site settings
define('SITE_NAME', 'CSI Hub');
define('SITE_ORG', 'Research Unlimited');
define('SITE_EMAIL', 'info@researchunlimitedsa.co.za');

define('SMTP_HOST', 'mail.researchunlimitedsa.co.za');   
define('SMTP_PORT', 465);                                 
define('SMTP_ENCRYPTION', 'ssl');                           
define('SMTP_USER', 'noreply@researchunlimitedsa.co.za');  
define('SMTP_PASS', 'buYRhs&b9ds87C');    
define('SMTP_FROM_EMAIL', 'noreply@researchunlimitedsa.co.za');
define('SMTP_FROM_NAME', 'CSI Hub — Research Unlimited');

$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';

define(
    'SITE_URL',
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_host . BASE_URL
);

/**
 * Generate URL
 */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Redirect helper
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}