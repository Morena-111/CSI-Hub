<?php
/**
 * sidebar.php
 * Place in: C:\xampp\htdocs\csi-hub\includes\sidebar.php
 */

// Determine user type label
$_sb_role     = $_SESSION['role'] ?? 'user';
$_sb_name     = $_SESSION['name'] ?? 'User';
$_sb_utype    = $_SESSION['user_type'] ?? 'general'; // 'company', 'school', 'general'
$_sb_initials = current_user_initials();

$_sb_platform = is_admin() ? 'Coordination Platform' : (
    $_sb_utype === 'company' ? 'Partner Platform' : (
    $_sb_utype === 'school'  ? 'School Platform'  : 'User Platform'
));

// Badge counts
try {
    $badge_docs   = $pdo->query("SELECT COUNT(*) FROM documents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $badge_events = $pdo->query("SELECT COUNT(*) FROM events WHERE status='upcoming' AND event_date >= CURDATE()")->fetchColumn();
    $badge_pending = 0;
    $sp = __DIR__ . '/../data/pending_signups.json';
    if (file_exists($sp)) {
        $pd = json_decode(file_get_contents($sp), true) ?? [];
        $badge_pending = count(array_filter($pd, fn($v) => !($v['approved']??false)));
    }
} catch(Exception $e) {
    $badge_docs = $badge_events = $badge_pending = 0;
}

$nav = [];

// ── OVERVIEW ────────────────────────────────────────────────
$nav['OVERVIEW'] = [
    ['key'=>'dashboard', 'icon'=>'ti-layout-dashboard', 'label'=>'Dashboard',    'href'=>'dashboard.php', 'badge'=>null],
    ['key'=>'impact',    'icon'=>'ti-heart-rate-monitor','label'=>'Impact Stats', 'href'=>'impact_stats.php','badge'=>null],
];

// ── MANAGEMENT ──────────────────────────────────────────────
$nav['MANAGEMENT'] = [
    ['key'=>'partners',  'icon'=>'ti-users',        'label'=>'Partners',   'href'=>'partners.php',  'badge'=>null],
    ['key'=>'schools',   'icon'=>'ti-school',        'label'=>'Schools',    'href'=>'schools.php',   'badge'=>null],
    ['key'=>'documents', 'icon'=>'ti-file-invoice',  'label'=>'Documents',  'href'=>'documents.php', 'badge'=>(int)$badge_docs ?: null],
    ['key'=>'doc_wizard', 'icon'=>'ti-checklist',      'label'=>'Submit Docs', 'href'=>'document_wizard.php', 'badge'=>null],
    ['key'=>'events',    'icon'=>'ti-calendar',      'label'=>'Events',     'href'=>'events.php',    'badge'=>(int)$badge_events ?: null],
    ['key'=>'programmes','icon'=>'ti-topology-star-3','label'=>'Programmes', 'href'=>'programmes.php','badge'=>null],
];

// ── ADMIN (admin only) ───────────────────────────────────────
if (is_admin()) {
    $nav['ADMIN'] = [
        ['key'=>'surveys',  'icon'=>'ti-clipboard-list', 'label'=>'Surveys',   'href'=>'surveys.php',  'badge'=>null],
        ['key'=>'team',     'icon'=>'ti-users-group',    'label'=>'Team',      'href'=>'team.php',     'badge'=>(int)$badge_pending ?: null],
        ['key'=>'settings', 'icon'=>'ti-settings',       'label'=>'Settings',  'href'=>'settings.php', 'badge'=>null],
    ];
}
?>
<aside class="sidebar">

  <!-- Brand — text only, logo lives in header -->
  <div class="sidebar-brand">
    <div class="sidebar-brand-text">
      <span class="sidebar-brand-name">CSI Hub</span>
      <span class="sidebar-brand-sub"><?= htmlspecialchars($_sb_platform) ?></span>
    </div>
  </div>

  <!-- Nav sections -->
  <nav class="sidebar-nav">
    <?php foreach ($nav as $section => $items): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= $section ?></div>
      <?php foreach ($items as $item): ?>
      <a href="<?= $item['href'] ?>"
         class="sidebar-item <?= ($active_page ?? '') === $item['key'] ? 'active' : '' ?>">
        <i class="ti <?= $item['icon'] ?>"></i>
        <span><?= $item['label'] ?></span>
        <?php if ($item['badge']): ?>
        <span class="sidebar-badge"><?= $item['badge'] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <!-- Help / Contact card — different content for admin vs users -->
  <div class="sidebar-help">
    <?php if (is_admin()): ?>
    <div class="sidebar-help-title">
      <i class="ti ti-headset"></i> Admin Support
    </div>
    <a href="tel:+27795343798" class="sidebar-help-item">
      <i class="ti ti-phone"></i>
      <span>079 534 3798</span>
    </a>
    <a href="mailto:lwandile@researchunlimited.co.za" class="sidebar-help-item">
      <i class="ti ti-mail"></i>
      <span>lwandile@researchunlimited.co.za</span>
    </a>
    <a href="mailto:it@researchunlimited.co.za" class="sidebar-help-item">
      <i class="ti ti-device-laptop"></i>
      <span>IT Team Support</span>
    </a>
    <?php else: ?>
    <div class="sidebar-help-title">
      <i class="ti ti-lifebuoy"></i> Need Help?
    </div>
    <a href="tel:+27795343798" class="sidebar-help-item">
      <i class="ti ti-phone"></i>
      <span>079 534 3798</span>
    </a>
    <a href="mailto:helpdesk@researchunlimited.co.za" class="sidebar-help-item">
      <i class="ti ti-mail"></i>
      <span>helpdesk@researchunlimited.co.za</span>
    </a>
    <?php endif; ?>
  </div>

  <!-- Help card handles sign out via header icon only -->

</aside>