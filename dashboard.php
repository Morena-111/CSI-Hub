<?php
$active_page = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// ── LIVE STATS ────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(status='active')  AS active,
           SUM(status='pending') AS pending,
           COALESCE(SUM(amount),0) AS total_value
    FROM partnerships
")->fetch();

$school_count  = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
$company_count = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$doc_count     = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

$recent = $pdo->query("
    SELECT p.*, c.name AS company_name, s.name AS school_name
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

$upcoming_events = $pdo->query("
    SELECT e.*, c.name AS company_name, s.name AS school_name
    FROM events e
    LEFT JOIN companies c ON c.id=e.company_id
    LEFT JOIN schools   s ON s.id=e.school_id
    WHERE e.status='upcoming' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC LIMIT 5
")->fetchAll();

// Notifications data
$pending_approvals = 0;
$signups_file = __DIR__ . '/data/pending_signups.json';
if (file_exists($signups_file)) {
    $pending_data = json_decode(file_get_contents($signups_file), true) ?? [];
    $pending_approvals = count(array_filter($pending_data, fn($v) => !($v['approved'] ?? false)));
}

$expiring_soon = $pdo->query("
    SELECT p.*, c.name AS company_name, s.name AS school_name
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    WHERE p.status='active' AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetchAll();

$type_colors = ['Site Visit'=>'var(--orange)','Meeting'=>'var(--teal)','Deadline'=>'#e53e3e','Review'=>'var(--purple)','Other'=>'var(--text-muted)'];

// Build notifications list
$notifications = [];
foreach ($expiring_soon as $ep) {
    $days = ceil((strtotime($ep['end_date']) - time()) / 86400);
    $notifications[] = ['type'=>'warning','icon'=>'ti-clock','msg'=>"Partnership with {$ep['company_name']} expires in {$days} day".($days!=1?'s':''),'link'=>'partnerships.php'];
}
if ($pending_approvals > 0) {
    $notifications[] = ['type'=>'info','icon'=>'ti-user-check','msg'=>"{$pending_approvals} user access request".($pending_approvals!=1?'s':''). " pending approval",'link'=>'team.php'];
}
foreach ($upcoming_events as $ev) {
    $days = ceil((strtotime($ev['event_date']) - time()) / 86400);
    if ($days <= 3) {
        $notifications[] = ['type'=>'event','icon'=>'ti-calendar','msg'=>$ev['title']." in {$days} day".($days!=1?'s':''),'link'=>'events.php'];
    }
}
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Dashboard</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p>Welcome back, <?= current_user_name() ?> — <?= date('l, d F Y') ?></p>
    </div>
    <div class="page-header-right">
      <?php if(is_admin() && ($pending_approvals > 0 || !empty($expiring_soon))): ?>
        <div style="position:relative">
          <button class="topnav-icon-btn" onclick="toggleNotifPanel()" id="notif-btn" title="Notifications"
                  style="width:auto;padding:0 12px;gap:7px;font-size:12.5px;font-weight:500;color:var(--text)">
            <i class="ti ti-bell" style="font-size:16px"></i>
            <span style="background:var(--orange);color:white;font-size:10px;font-weight:700;padding:1px 5px;border-radius:10px"><?= count($notifications) ?></span>
          </button>
          <!-- Notifications dropdown -->
          <div id="notif-panel" style="display:none;position:absolute;right:0;top:44px;width:320px;background:var(--white);border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(26,31,46,.12);z-index:200">
            <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-size:12.5px;font-weight:700;color:var(--text)">
              Notifications <span style="color:var(--text-muted);font-weight:400">(<?= count($notifications) ?>)</span>
            </div>
            <?php foreach($notifications as $n): ?>
            <a href="<?= $n['link'] ?>" style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);text-decoration:none;transition:background .15s" onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background=''">
              <div style="width:30px;height:30px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px;
                <?= $n['type']==='warning' ? 'background:#fffbea;color:#b7791f' : ($n['type']==='info' ? 'background:#eef2ff;color:#4c63d2' : 'background:var(--orange-soft);color:var(--orange)') ?>">
                <i class="ti <?= $n['icon'] ?>"></i>
              </div>
              <div style="flex:1;font-size:12.5px;color:var(--text);line-height:1.4"><?= htmlspecialchars($n['msg']) ?></div>
            </a>
            <?php endforeach; ?>
            <div style="padding:10px 16px;text-align:center">
              <a href="team.php" style="font-size:12px;color:var(--orange);text-decoration:none;font-weight:500">View all →</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <?php if(can_edit()): ?>
        <button class="btn btn-primary" onclick="window.location='partnerships.php'">
          <i class="ti ti-plus"></i> New Partnership
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Role badge -->
  <?php if(is_admin()): ?>
  <div style="display:inline-flex;align-items:center;gap:6px;background:#fdf0ea;color:#E8541A;font-size:11.5px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:20px">
    <i class="ti ti-shield-check"></i> Admin — Full Access
  </div>
  <?php else: ?>
  <div style="display:inline-flex;align-items:center;gap:6px;background:#e6faf5;color:#00956a;font-size:11.5px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:20px">
    <i class="ti ti-eye"></i> User — Read Only
  </div>
  <?php endif; ?>

  <!-- EXPIRING SOON ALERT -->
  <?php if(is_admin() && !empty($expiring_soon)): ?>
  <div style="background:#fffbea;border:1px solid #f6e05e;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px">
    <i class="ti ti-alert-triangle" style="color:#b7791f;font-size:18px;flex-shrink:0"></i>
    <div>
      <strong style="color:#7b341e"><?= count($expiring_soon) ?> partnership<?= count($expiring_soon)!=1?'s':'' ?> expiring within 30 days:</strong>
      <?php foreach($expiring_soon as $ep): ?>
        <span style="margin-left:8px;color:#7b341e"><?= htmlspecialchars($ep['company_name']) ?> → <?= htmlspecialchars($ep['school_name']) ?></span>
      <?php endforeach; ?>
    </div>
    <a href="partnerships.php" style="margin-left:auto;font-size:12px;color:var(--orange);text-decoration:none;font-weight:600;white-space:nowrap">View →</a>
  </div>
  <?php endif; ?>

  <!-- STAT CARDS -->
  <div class="stats-row">
    <div class="stat-card orange">
      <div class="stat-label">Total Partnerships</div>
      <div class="stat-value orange"><?= $stats['total'] ?></div>
      <div class="stat-sub"><?= $stats['pending'] ?> pending</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active Now</div>
      <div class="stat-value teal"><?= $stats['active'] ?></div>
      <div class="stat-sub">Currently running</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Total Value</div>
      <?php if(can_view_financials()): ?>
        <div class="stat-value purple">R<?= number_format($stats['total_value']/1000000,1) ?>M</div>
        <div class="stat-sub">All partnerships</div>
      <?php else: ?>
        <div class="stat-value" style="font-size:18px;color:var(--text-muted)"><i class="ti ti-lock"></i></div>
        <div class="stat-sub">Admin only</div>
      <?php endif; ?>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Schools Reached</div>
      <div class="stat-value"><?= $school_count ?></div>
      <div class="stat-sub"><?= $company_count ?> partners · <?= $doc_count ?> docs</div>
    </div>
  </div>

  <!-- MAIN GRID -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px">

    <!-- RECENT PARTNERSHIPS -->
    <div class="widget">
      <div class="widget-title">
        <i class="ti ti-activity"></i> Recent Partnerships
        <a href="partnerships.php" style="margin-left:auto;font-size:11px;font-weight:500;color:var(--orange);text-decoration:none">View all →</a>
      </div>
      <?php if(empty($recent)): ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted)">
          <p style="font-size:13px">No partnerships yet.</p>
          <?php if(can_edit()): ?><a href="partnerships.php" class="btn btn-primary" style="margin-top:10px;font-size:12px"><i class="ti ti-plus"></i> Add first partnership</a><?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach($recent as $p): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:<?= $p['status']==='active'?'var(--teal)':($p['status']==='pending'?'var(--gold)':'var(--text-light)') ?>"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12.5px;font-weight:600;color:var(--text)">
              <?= htmlspecialchars($p['company_name']) ?>
              <span style="color:var(--text-muted);font-weight:400"> → </span>
              <?= htmlspecialchars($p['school_name']) ?>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:1px">
              <?= htmlspecialchars($p['focus_area']) ?>
              <?php if(can_view_financials()): ?> · R<?= number_format($p['amount']/1000) ?>k<?php endif; ?>
            </div>
          </div>
          <span class="status-badge <?= $p['status'] ?>" style="font-size:10px"><?= ucfirst($p['status']) ?></span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- UPCOMING EVENTS -->
    <div class="widget">
      <div class="widget-title">
        <i class="ti ti-calendar-event" style="color:var(--orange)"></i> Upcoming Events
        <a href="events.php" style="margin-left:auto;font-size:11px;font-weight:500;color:var(--orange);text-decoration:none">View all →</a>
      </div>
      <?php if(empty($upcoming_events)): ?>
        <div style="text-align:center;padding:20px;color:var(--text-muted)">
          <p style="font-size:13px">No upcoming events.</p>
          <?php if(can_edit()): ?><a href="events.php" class="btn btn-secondary" style="margin-top:8px;font-size:11.5px"><i class="ti ti-plus"></i> Add event</a><?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach($upcoming_events as $ev):
          $color = $type_colors[$ev['event_type']] ?? 'var(--text-muted)';
          $days  = ceil((strtotime($ev['event_date']) - time()) / 86400);
        ?>
        <div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <div style="text-align:center;min-width:38px;flex-shrink:0">
            <div style="font-size:18px;font-weight:700;color:<?= $color ?>;line-height:1;font-family:'Playfair Display',serif"><?= date('d',strtotime($ev['event_date'])) ?></div>
            <div style="font-size:9px;color:var(--text-light);text-transform:uppercase;font-weight:600"><?= date('M',strtotime($ev['event_date'])) ?></div>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12.5px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($ev['title']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($ev['event_type']) ?><?php if($ev['event_time']): ?> · <?= date('H:i',strtotime($ev['event_time'])) ?><?php endif; ?></div>
            <?php if($days===0): ?>
              <span style="font-size:10px;background:var(--orange-soft);color:var(--orange);padding:1px 6px;border-radius:10px;font-weight:600">Today</span>
            <?php elseif($days<=3): ?>
              <span style="font-size:10px;background:var(--gold-soft);color:#9a6700;padding:1px 6px;border-radius:10px;font-weight:600">In <?= $days ?> day<?= $days!=1?'s':'' ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

</main>
</div>

<script>
function toggleNotifPanel() {
  const p = document.getElementById('notif-panel');
  p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  const btn = document.getElementById('notif-btn');
  const panel = document.getElementById('notif-panel');
  if (panel && btn && !btn.contains(e.target) && !panel.contains(e.target)) {
    panel.style.display = 'none';
  }
});
</script>

<?php include 'includes/footer.php'; ?>