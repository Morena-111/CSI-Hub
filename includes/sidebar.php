<?php
/**
 * sidebar.php — Live counts from DB
 */

$badge_counts = ['partnerships'=>0,'schools'=>0,'companies'=>0,'documents'=>0,'events'=>0];
if (isset($pdo)) {
    try {
        $badge_counts['partnerships'] = $pdo->query("SELECT COUNT(*) FROM partnerships WHERE status='active'")->fetchColumn();
        $badge_counts['schools']      = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
        $badge_counts['companies']    = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
        $badge_counts['documents']    = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $badge_counts['events']       = $pdo->query("SELECT COUNT(*) FROM events WHERE status='upcoming' AND event_date >= CURDATE()")->fetchColumn();
    } catch (Exception $e) {}
}

$_sb_name     = $_SESSION['name'] ?? 'User';
$_sb_role     = is_admin() ? 'Administrator' : 'User';
$_sb_parts    = explode(' ', trim($_sb_name));
$_sb_initials = strtoupper(substr($_sb_parts[0],0,1).(isset($_sb_parts[1]) ? substr($_sb_parts[1],0,1) : ''));

$sidebar_items = [
    'overview' => [
        ['key'=>'partnerships','icon'=>'ti-users',   'label'=>'Partnerships','href'=>'partnerships.php','badge'=>$badge_counts['partnerships'],'style'=>'orange','admin_only'=>false],
        ['key'=>'schools',     'icon'=>'ti-school',  'label'=>'Schools',     'href'=>'schools.php',    'badge'=>$badge_counts['schools'],      'style'=>'',      'admin_only'=>false],
        ['key'=>'companies',   'icon'=>'ti-building','label'=>'Companies',   'href'=>'companies.php',  'badge'=>$badge_counts['companies'],    'style'=>'',      'admin_only'=>false],
    ],
    'management' => [
        ['key'=>'programmes','icon'=>'ti-topology-star-3','label'=>'Our Programmes','href'=>'programmes.php','badge'=>null,'style'=>'','admin_only'=>false],
        ['key'=>'reports',   'icon'=>'ti-chart-pie',      'label'=>'Reports',       'href'=>'reports.php',  'badge'=>null,'style'=>'','admin_only'=>false],
        ['key'=>'dashboard', 'icon'=>'ti-layout-dashboard','label'=>'Dashboard',    'href'=>'dashboard.php','badge'=>null,'style'=>'','admin_only'=>false],
        ['key'=>'documents', 'icon'=>'ti-file-invoice',   'label'=>'Documents',     'href'=>'documents.php','badge'=>$badge_counts['documents'],'style'=>'','admin_only'=>false],
        ['key'=>'events',    'icon'=>'ti-calendar',       'label'=>'Events',        'href'=>'events.php',   'badge'=>$badge_counts['events'],   'style'=>'','admin_only'=>false],
    ],
    'admin' => [
        ['key'=>'settings','icon'=>'ti-settings',   'label'=>'Settings','href'=>'settings.php','badge'=>null,'style'=>'','admin_only'=>true],
        ['key'=>'team',    'icon'=>'ti-users-group','label'=>'Team',    'href'=>'team.php',    'badge'=>null,'style'=>'','admin_only'=>true],
    ],
];
?>

<aside class="sidebar">

  <div class="sidebar-logo-area">
    <div class="s-title">CSI Hub</div>
    <div class="s-sub">Coordination Platform</div>
  </div>

  <?php foreach ($sidebar_items as $section_label => $items): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= ucfirst($section_label) ?></div>
      <?php foreach ($items as $item):
        $locked = $item['admin_only'] && !is_admin();
      ?>
        <?php if ($locked): ?>
          <a href="#" class="sidebar-item sidebar-item-locked"
             onclick="event.preventDefault(); showAccessDenied();"
             title="Administrator access required">
            <i class="ti <?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
            <i class="ti ti-lock sidebar-lock-icon"></i>
          </a>
        <?php else: ?>
          <a href="<?= $item['href'] ?>"
             class="sidebar-item <?= ($active_page ?? '') === $item['key'] ? 'active' : '' ?>">
            <i class="ti <?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
            <?php if ($item['badge']): ?>
              <span class="sidebar-badge <?= $item['style'] ?>"><?= $item['badge'] ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="sidebar-footer">
    <div class="sidebar-footer-info">
      <div class="sidebar-footer-avatar"><?= $_sb_initials ?></div>
      <div>
        <div class="sidebar-footer-name"><?= htmlspecialchars($_sb_name) ?></div>
        <div class="sidebar-footer-role"><?= htmlspecialchars($_sb_role) ?></div>
      </div>
    </div>
  </div>

</aside>