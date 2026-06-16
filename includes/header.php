<?php
/**
 * header.php
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role'])) {
    header('Location: /csi-hub/login.php');
    exit;
}

if (!function_exists('is_admin')) {
    require_once __DIR__ . '/auth.php';
}

$_hn_name     = $_SESSION['name'] ?? 'User';
$_hn_role     = is_admin() ? 'Administrator' : 'User';
$_hn_parts    = explode(' ', trim($_hn_name));
$_hn_initials = strtoupper(substr($_hn_parts[0],0,1).(isset($_hn_parts[1]) ? substr($_hn_parts[1],0,1) : ''));


if (!isset($active_page)) $active_page = '';

$nav_tabs = [
    'dashboard'    => ['icon'=>'ti-layout-dashboard','label'=>'Dashboard',    'href'=>'dashboard.php'],
    'partnerships' => ['icon'=>'ti-users',            'label'=>'Partnerships', 'href'=>'partnerships.php'],
    'companies'    => ['icon'=>'ti-building',         'label'=>'Companies',    'href'=>'companies.php'],
    'schools'      => ['icon'=>'ti-school',           'label'=>'Schools',      'href'=>'schools.php'],
    'reports'      => ['icon'=>'ti-chart-bar',        'label'=>'Reports',      'href'=>'reports.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSI Hub — <?= ucfirst($active_page ?: 'Home') ?> | Research Unlimited</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <link rel="stylesheet" href="assets/css/cards.css">
  <link rel="stylesheet" href="assets/css/modal.css">
  <link rel="stylesheet" href="assets/css/tables.css">
</head>
<body>

<nav class="topnav">

  <!-- Logo -->
  <div class="topnav-brand">
    <img src="assets/img/logo.png" alt="Research Unlimited" class="topnav-logo-img">
    <div class="ru-text">
      <span class="ru-name">Research Unlimited</span>
      <span class="ru-sub">CSI Hub</span>
    </div>
  </div>

  <!-- Nav tabs: Dashboard, Partnerships, Companies, Schools, Reports -->
  <div class="topnav-tabs">
    <?php foreach ($nav_tabs as $key => $tab): ?>
      <a href="<?= $tab['href'] ?>"
         class="topnav-tab <?= $active_page === $key ? 'active' : '' ?>">
        <i class="ti <?= $tab['icon'] ?>"></i>
        <?= $tab['label'] ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Right: bell + role badge + user + logout -->
  <div class="topnav-right">

    <button class="topnav-icon-btn" title="Notifications">
      <i class="ti ti-bell"></i>
      <span class="notif-dot"></span>
    </button>

    <?php if (is_admin()): ?>
      <span class="role-badge admin-role"><i class="ti ti-shield-check"></i> Admin</span>
    <?php else: ?>
      <span class="role-badge viewer-role"><i class="ti ti-eye"></i> User</span>
    <?php endif; ?>

    <div class="topnav-user">
      <div class="topnav-avatar"><?= $_hn_initials ?></div>
      <div class="topnav-user-info">
        <span class="topnav-user-name"><?= htmlspecialchars($_hn_name) ?></span>
        <span class="topnav-user-role"><?= htmlspecialchars($_hn_role) ?></span>
      </div>
    </div>

    <a href="logout.php" class="topnav-logout" title="Log out">
      <i class="ti ti-logout"></i>
    </a>

  </div>
</nav>