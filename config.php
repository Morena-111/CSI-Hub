<?php
/**
 * config.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);


define('BASE_URL', '/csi-hub/');
define('ENVIRONMENT', 'local');


define('DB_HOST', 'localhost');
define('DB_NAME', 'csi_hub');
define('DB_USER', 'root');
define('DB_PASS', '');


define('SITE_NAME', 'CSI Hub');
define('SITE_ORG', 'Research Unlimited');
define('SITE_EMAIL', 'info@researchunlimitedsa.co.za');
define('ADMIN_EMAIL', 'lwandile@researchunlimited.co.za');
define('HELP_EMAIL',  'helpdesk@researchunlimitedsa.co.za');
define('HELP_PHONE',  '079 534 3798');

define('SMTP_HOST',       'mail.researchunlimitedsa.co.za');
define('SMTP_PORT',       465);
define('SMTP_ENCRYPTION', 'ssl');
define('SMTP_USER',       'noreply@researchunlimitedsa.co.za');
define('SMTP_PASS',       'buYRhs&b9ds87C');
define('SMTP_FROM_EMAIL', 'noreply@researchunlimitedsa.co.za');
define('SMTP_FROM_NAME',  'CSI Hub — Research Unlimited');

// ── SMS settings (BulkSMS — sign up at bulksms.com when ready) ──
define('SMS_USERNAME', 'PASTE_YOUR_BULKSMS_USERNAME_HERE');
define('SMS_PASSWORD', 'PASTE_YOUR_BULKSMS_PASSWORD_HERE');

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

/**
 * Send email via SMTP using PHPMailer if available, fallback to mail()
 */
function send_email(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    // Try PHPMailer first (if installed via composer)
    $mailer_path = __DIR__ . '/vendor/autoload.php';
    if (file_exists($mailer_path)) {
        require_once $mailer_path;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            if ($replyTo) $mail->addReplyTo($replyTo);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br(htmlspecialchars($body));
            $mail->AltBody = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('PHPMailer error: ' . $e->getMessage());
        }
    }

    // Fallback to PHP mail()
    $headers  = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . ($replyTo ?: SMTP_FROM_EMAIL) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}