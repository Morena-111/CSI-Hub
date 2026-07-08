<?php
/**
 * mailer.php — sends real SMTP email via PHPMailer instead of PHP's mail().
 *
 * Requires PHPMailer. Either:
 *   A) Installed via Composer:  vendor/autoload.php  (recommended), or
 *   B) Manually downloaded into: libs/PHPMailer/src/PHPMailer.php, SMTP.php, Exception.php
 *
 * If neither is found, this file falls back to PHP's built-in mail() so the
 * rest of the site (login, signup, etc.) never crashes just because the
 * mail library isn't installed yet — it just logs a note instead.
 */

// Make this file self-sufficient: it defines the SMTP_* constants it needs,
// regardless of what order the caller happened to require things in.
if (!defined('SMTP_HOST')) require_once __DIR__ . '/config.php';

$__phpmailer_available = false;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $__phpmailer_available = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
} elseif (file_exists(__DIR__ . '/libs/PHPMailer/src/Exception.php')
       && file_exists(__DIR__ . '/libs/PHPMailer/src/PHPMailer.php')
       && file_exists(__DIR__ . '/libs/PHPMailer/src/SMTP.php')) {
    require_once __DIR__ . '/libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/libs/PHPMailer/src/SMTP.php';
    $__phpmailer_available = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
}

if ($__phpmailer_available) {

    /**
     * Send an email via your configured SMTP server (PHPMailer).
     * Returns true on success, false on failure (never throws — logs instead).
     */
    function send_app_email(string $to, string $subject, string $body): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_ENCRYPTION; // 'tls' or 'ssl'
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('CSI Hub mail error (PHPMailer): ' . $mail->ErrorInfo);
            return false;
        }
    }

} else {

    /**
     * FALLBACK — PHPMailer isn't installed yet, so this uses PHP's built-in
     * mail() instead. This will silently do nothing on most local/XAMPP setups
     * (no mail server configured), but it keeps the site from crashing.
     * See mail_setup instructions to install PHPMailer for real SMTP delivery.
     */
    function send_app_email(string $to, string $subject, string $body): bool
    {
        error_log('CSI Hub mail notice: PHPMailer not installed — falling back to mail(). '
            . 'See mail_setup/ instructions to enable real SMTP delivery.');
        $from = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SITE_EMAIL') ? SITE_EMAIL : 'no-reply@localhost');
        return @mail($to, $subject, $body, 'From: ' . $from);
    }

}