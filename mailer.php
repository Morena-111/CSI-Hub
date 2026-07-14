<?php
/**
 * mailer.php
 */

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
    function send_app_email(string $to, string $subject, string $body): bool
    {
        error_log('CSI Hub mail notice: PHPMailer not installed — falling back to mail(). '
            . 'See mail_setup/ instructions to enable real SMTP delivery.');
        $from = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SITE_EMAIL') ? SITE_EMAIL : 'no-reply@localhost');
        return @mail($to, $subject, $body, 'From: ' . $from);
    }

}