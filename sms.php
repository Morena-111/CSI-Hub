<?php
/**
 * sms.php
 */

if (!defined('SMS_USERNAME')) require_once __DIR__ . '/config.php';

function send_app_sms(string $to, string $message): bool
{
    if (!defined('SMS_USERNAME') || !defined('SMS_PASSWORD')
        || SMS_USERNAME === '' || SMS_PASSWORD === ''
        || SMS_USERNAME === 'PASTE_YOUR_BULKSMS_USERNAME_HERE') {
        error_log('CSI Hub SMS notice: SMS_USERNAME/SMS_PASSWORD not configured yet — skipping SMS send. '
            . 'See sms.php header for setup steps.');
        return false;
    }

    $digits = preg_replace('/[^0-9]/', '', $to);
    if (substr($digits, 0, 1) === '0') $digits = '27' . substr($digits, 1);
    elseif (substr($digits, 0, 2) !== '27') $digits = '27' . $digits;

    $payload = json_encode([
        'to'      => $digits,
        'body'    => $message,
    ]);

    $ch = curl_init('https://api.bulksms.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => SMS_USERNAME . ':' . SMS_PASSWORD,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('CSI Hub SMS error (connection): ' . $curlErr);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log("CSI Hub SMS error (HTTP {$status}): " . $response);
        return false;
    }
    return true;
}