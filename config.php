<?php
/**
 * config.php — Central configuration
 */

// ── BASE URL (auto-detected) ──────────────────────────────────
// Local XAMPP:  http://localhost/csi-hub/
// Live server:  https://researchunlimitedsa.co.za/
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script = $_SERVER['SCRIPT_NAME'] ?? '';

if ($_host === 'localhost' || str_starts_with($_host, '127.')) {
    // Local development — files are in /csi-hub/ subfolder
    define('BASE_URL', '/csi-hub/');
    define('ENVIRONMENT', 'local');
} else {
    // Live server — files are in public_html root
    define('BASE_URL', '/');
    define('ENVIRONMENT', 'production');
}

// ── DATABASE ──────────────────────────────────────────────────
if (ENVIRONMENT === 'local') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'csi_hub');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // !! UPDATE THESE with your live hosting credentials !!
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'your_db_name');     // e.g. csi_hub or cpanel_csi_hub
    define('DB_USER', 'your_db_user');     // from cPanel MySQL Users
    define('DB_PASS', 'your_db_password'); // your cPanel DB password
}

// ── SITE INFO ─────────────────────────────────────────────────
define('SITE_NAME',  'CSI Hub');
define('SITE_ORG',   'Research Unlimited');
define('SITE_EMAIL', 'info@researchunlimitedsa.co.za');
define('SITE_URL',   (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_host . BASE_URL);

// ── HELPER FUNCTION ───────────────────────────────────────────
/**
 * Generate an absolute URL within the app.
 * Usage: url('dashboard.php')  →  /csi-hub/dashboard.php  (local)
 *                               →  /dashboard.php           (live)
 */
function url(string $path = ''): string {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Redirect to an internal page.
 * Usage: redirect('login.php')
 */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}