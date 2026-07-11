<?php
require_once __DIR__ . '/config.php';
if (!function_exists('send_app_email')) require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/includes/db.php';
/**
 * notify_expiring.php
 */

define('CRON_SECRET', 'RU-CSI-2026-notify-secret');
define('NOTIFY_EMAIL', 'info@researchunlimitedsa.co.za');
define('FROM_EMAIL',   'noreply@researchunlimitedsa.co.za');
define('SITE_URL',     'http://localhost/csi-hub');

// ── SECURITY — only run from CLI or with correct secret key ──
$is_cli = (php_sapi_name() === 'cli');
$is_web = isset($_GET['run']) && ($_GET['key'] ?? '') === CRON_SECRET;

if (!$is_cli && !$is_web) {
    http_response_code(403);
    exit('Access denied. Use CLI or provide valid key.');
}

// ── DATABASE ─────────────────────────────────────────────────
$db_config = require __DIR__ . '/includes/db.php';
// db.php returns $pdo — we rely on it being included

require_once __DIR__ . '/includes/db.php';

// ── FETCH EXPIRING PARTNERSHIPS ───────────────────────────────
$expiring_30 = $pdo->query("
    SELECT p.*, c.name AS company_name, s.name AS school_name,
           s.province, p.end_date,
           DATEDIFF(p.end_date, CURDATE()) AS days_left
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    WHERE p.status = 'active'
      AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY p.end_date ASC
")->fetchAll();

$expiring_7 = array_filter($expiring_30, fn($p) => $p['days_left'] <= 7);

if (empty($expiring_30)) {
    echo date('[Y-m-d H:i:s]') . " No expiring partnerships found. Nothing to send.\n";
    exit;
}

// ── BUILD EMAIL ───────────────────────────────────────────────
$count   = count($expiring_30);
$urgent  = count($expiring_7);
$subject = "CSI Hub — {$count} Partnership".($count!=1?'s':'')." Expiring Within 30 Days";

if ($urgent > 0) {
    $subject = "⚠️ URGENT — {$urgent} Partnership".($urgent!=1?'s':'')." Expiring This Week | CSI Hub";
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #1a1f2e; background: #f6f7fb; margin:0; padding:0; }
  .wrap { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; }
  .header { background: #0d1e3d; padding: 28px 32px; }
  .header h1 { color: white; font-size: 20px; margin: 0 0 4px; }
  .header p  { color: rgba(255,255,255,.5); font-size: 12px; margin: 0; }
  .bar { height: 4px; background: linear-gradient(90deg,#E8541A,#f5a623,#00c48c); }
  .body { padding: 28px 32px; }
  .intro { font-size: 14px; line-height: 1.7; color: #444; margin-bottom: 20px; }
  .urgent-box { background: #fffbea; border: 1px solid #f6e05e; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #7b341e; }
  table { width: 100%; border-collapse: collapse; margin: 16px 0; }
  th { background: #f6f7fb; color: #6b7a99; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; padding: 8px 10px; text-align: left; }
  td { padding: 10px; border-bottom: 1px solid #f1f4f9; font-size: 13px; }
  .urgent-row { background: #fff8f0; }
  .days-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
  .days-urgent { background: #fde9e9; color: #c53030; }
  .days-soon { background: #fffbea; color: #9a6700; }
  .days-ok { background: #e6faf5; color: #00956a; }
  .cta { text-align: center; margin: 24px 0; }
  .cta a { background: #E8541A; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; }
  .footer { background: #f6f7fb; padding: 16px 32px; text-align: center; font-size: 11px; color: #a0aec0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>Research Unlimited — CSI Hub</h1>
    <p>Partnership Expiry Notification — <?= date('d F Y') ?></p>
  </div>
  <div class="bar"></div>
  <div class="body">
    <p class="intro">
      This is an automated notification from CSI Hub.
      <strong><?= $count ?> active partnership<?= $count!=1?'s':'' ?></strong>
      will expire within the next <strong>30 days</strong>. Please review and take action where needed.
    </p>

    <?php if ($urgent > 0): ?>
    <div class="urgent-box">
      ⚠️ <strong><?= $urgent ?> partnership<?= $urgent!=1?'s':'' ?></strong>
      expire<?= $urgent===1?'s':'' ?> within the next <strong>7 days</strong> — immediate attention required.
    </div>
    <?php endif; ?>

    <table>
      <thead>
        <tr><th>Company</th><th>School</th><th>End Date</th><th>Days Left</th></tr>
      </thead>
      <tbody>
        <?php foreach($expiring_30 as $p):
          $days = (int)$p['days_left'];
          $cls  = $days<=7 ? 'days-urgent' : ($days<=14 ? 'days-soon' : 'days-ok');
          $row  = $days<=7 ? 'urgent-row' : '';
        ?>
        <tr class="<?= $row ?>">
          <td><?= htmlspecialchars($p['company_name']) ?></td>
          <td><?= htmlspecialchars($p['school_name']) ?></td>
          <td><?= date('d M Y', strtotime($p['end_date'])) ?></td>
          <td><span class="days-badge <?= $cls ?>"><?= $days ?> day<?= $days!=1?'s':'' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="cta">
      <a href="<?= SITE_URL ?>/partnerships.php">View Partnerships in CSI Hub →</a>
    </div>

    <p style="font-size:12px;color:#a0aec0;line-height:1.6">
      This email is sent automatically each day at 08:00 by CSI Hub.
      To stop receiving these, disable the cron job or Windows Task Scheduler task.
    </p>
  </div>
  <div class="footer">
    Research Unlimited &middot; researchunlimitedsa.co.za &middot; info@researchunlimitedsa.co.za<br>
    CSI Hub — Internal Platform
  </div>
</div>
</body>
</html>
<?php
$html_body = ob_get_clean();

// ── SEND EMAIL ────────────────────────────────────────────────
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: CSI Hub <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: " . NOTIFY_EMAIL . "\r\n";
$headers .= "X-Mailer: CSI-Hub-Notifier/1.0\r\n";

$sent = send_app_email(NOTIFY_EMAIL, $subject, $html_body);

// ── LOG RESULT ────────────────────────────────────────────────
$log_file = __DIR__ . '/data/notify_log.txt';
$status   = $sent ? 'OK' : 'FAILED';
$log_line = date('[Y-m-d H:i:s]') . " [{$status}] Sent to " . NOTIFY_EMAIL
          . " — {$count} expiring, {$urgent} urgent\n";

if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
file_put_contents($log_file, $log_line, FILE_APPEND);
echo $log_line;