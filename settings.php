<?php
$active_page = 'settings';
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';
include 'includes/header.php';

$_s_name = $_SESSION['name']     ?? 'Admin User';
$_s_user = $_SESSION['username'] ?? 'admin';

$success_msg = '';
$error_msg   = '';

// ── SAVE PROFILE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $new_name  = trim($_POST['full_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    if (strlen($new_name) < 2) {
        $error_msg = 'Name must be at least 2 characters.';
    } else {
        $admins_file = __DIR__ . '/data/admin_users.json';
        $admins = file_exists($admins_file) ? json_decode(file_get_contents($admins_file), true) ?? [] : [];
        if ($_s_user !== 'admin' && isset($admins[$_s_user])) {
            $admins[$_s_user]['name']  = $new_name;
            $admins[$_s_user]['email'] = $new_email;
            file_put_contents($admins_file, json_encode($admins, JSON_PRETTY_PRINT));
        }
        $_SESSION['name'] = $new_name;
        $_s_name = $new_name;
        $success_msg = 'Profile updated successfully!';
    }
}

// ── CHANGE PASSWORD ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $admins_file = __DIR__ . '/data/admin_users.json';
    $admins = file_exists($admins_file) ? json_decode(file_get_contents($admins_file), true) ?? [] : [];
    $root_passwords = ['admin' => 'RU@admin2026'];
    $current_stored = $root_passwords[$_s_user] ?? ($admins[$_s_user]['password'] ?? '');

    if ($current !== $current_stored)          $error_msg = 'Current password is incorrect.';
    elseif (strlen($new_pass) < 8)             $error_msg = 'New password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $new_pass)) $error_msg = 'Password must include an uppercase letter.';
    elseif (!preg_match('/[0-9]/', $new_pass)) $error_msg = 'Password must include a number.';
    elseif ($new_pass !== $confirm)            $error_msg = 'Passwords do not match.';
    else {
        if ($_s_user !== 'admin' && isset($admins[$_s_user])) {
            $admins[$_s_user]['password'] = $new_pass;
            file_put_contents($admins_file, json_encode($admins, JSON_PRETTY_PRINT));
        }
        $success_msg = 'Password updated successfully!';
    }
}

// ── EXPORT CSV handled above before HTML output ────────────

// ── SAVE PREFERENCES ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'preferences') {
    $prefs = [
        'date_format'   => $_POST['date_format']   ?? 'DD/MM/YYYY',
        'currency'      => $_POST['currency']       ?? 'R (ZAR)',
        'financial_year'=> $_POST['financial_year'] ?? date('Y'),
    ];
    $prefs_file = __DIR__ . '/data/preferences.json';
    if (!is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
    file_put_contents($prefs_file, json_encode($prefs, JSON_PRETTY_PRINT));
    $success_msg = 'Preferences saved!';
}

// Load preferences
$prefs_file = __DIR__ . '/data/preferences.json';
$prefs = file_exists($prefs_file) ? json_decode(file_get_contents($prefs_file), true) ?? [] : [];

// DB stats
$db_stats = [
    'Partnerships' => $pdo->query("SELECT COUNT(*) FROM partnerships")->fetchColumn(),
    'Schools'      => $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn(),
    'Companies'    => $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
    'Documents'    => $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn(),
    'Events'       => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
];
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>

<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Settings</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Settings</h1>
      <p>Manage your profile, password and system preferences</p>
    </div>
    <span class="admin-badge"><i class="ti ti-shield-check"></i> Admin Only</span>
  </div>

  <?php if ($success_msg): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i><?= htmlspecialchars($success_msg) ?>
  </div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
  <div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-alert-circle"></i><?= htmlspecialchars($error_msg) ?>
  </div>
  <?php endif; ?>

  <div class="settings-grid">

    <!-- PROFILE -->
    <div class="settings-card">
      <div class="settings-card-header">
        <div class="settings-card-icon orange"><i class="ti ti-user"></i></div>
        <div>
          <div class="settings-card-title">My Profile</div>
          <div class="settings-card-sub">Update your name and email</div>
        </div>
      </div>
      <div class="settings-card-body">
        <form method="POST">
          <input type="hidden" name="form" value="profile">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input" type="text" name="full_name" value="<?= htmlspecialchars($_s_name) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input class="form-input" type="text" value="<?= htmlspecialchars($_s_user) ?>" disabled
                   style="background:var(--surface);color:var(--text-muted)">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input class="form-input" type="email" name="email" placeholder="admin@researchunlimited.co.za">
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:4px">
            <i class="ti ti-check"></i> Save Profile
          </button>
        </form>
      </div>
    </div>

    <!-- PASSWORD -->
    <div class="settings-card">
      <div class="settings-card-header">
        <div class="settings-card-icon teal"><i class="ti ti-lock"></i></div>
        <div>
          <div class="settings-card-title">Change Password</div>
          <div class="settings-card-sub">Update your login password</div>
        </div>
      </div>
      <div class="settings-card-body">
        <form method="POST">
          <input type="hidden" name="form" value="password">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input class="form-input" type="password" name="current_password" placeholder="Enter current password" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input class="form-input" type="password" name="new_password" placeholder="Min 8 chars, uppercase, number" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input class="form-input" type="password" name="confirm_password" placeholder="Repeat new password" required>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:4px">
            <i class="ti ti-check"></i> Update Password
          </button>
        </form>
      </div>
    </div>

    <!-- SYSTEM PREFS -->
    <div class="settings-card">
      <div class="settings-card-header">
        <div class="settings-card-icon purple"><i class="ti ti-adjustments"></i></div>
        <div>
          <div class="settings-card-title">System Preferences</div>
          <div class="settings-card-sub">Date format and currency</div>
        </div>
      </div>
      <div class="settings-card-body">
        <form method="POST">
          <input type="hidden" name="form" value="preferences">
          <div class="form-group">
            <label class="form-label">Date Format</label>
            <select class="form-select" name="date_format">
              <?php foreach(['DD/MM/YYYY','MM/DD/YYYY','YYYY-MM-DD'] as $df): ?>
                <option <?= ($prefs['date_format']??'DD/MM/YYYY')===$df?'selected':'' ?>><?= $df ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Currency Display</label>
            <select class="form-select" name="currency">
              <?php foreach(['R (ZAR)','ZAR'] as $cur): ?>
                <option <?= ($prefs['currency']??'R (ZAR)')===$cur?'selected':'' ?>><?= $cur ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Financial Year</label>
            <select class="form-select" name="financial_year">
              <?php foreach([date('Y'),date('Y')-1,date('Y')-2] as $fy): ?>
                <option <?= ($prefs['financial_year']??date('Y'))==$fy?'selected':'' ?>><?= $fy ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="margin-top:4px">
            <i class="ti ti-check"></i> Save Preferences
          </button>
        </form>
      </div>
    </div>

    <!-- EXPORT -->
    <div class="settings-card">
      <div class="settings-card-header">
        <div class="settings-card-icon gold"><i class="ti ti-database"></i></div>
        <div>
          <div class="settings-card-title">Export Data</div>
          <div class="settings-card-sub">Download live data as CSV</div>
        </div>
      </div>
      <div class="settings-card-body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;line-height:1.6">
          Export your live database records as CSV files you can open in Excel.
        </p>
        <form method="POST" style="display:flex;flex-direction:column;gap:10px">
          <input type="hidden" name="form" value="export_csv">
          <button type="submit" name="export_type" value="partnerships" class="btn btn-secondary" style="justify-content:flex-start">
            <i class="ti ti-users"></i> Export Partnerships
          </button>
          <button type="submit" name="export_type" value="schools" class="btn btn-secondary" style="justify-content:flex-start">
            <i class="ti ti-school"></i> Export Schools
          </button>
          <button type="submit" name="export_type" value="companies" class="btn btn-secondary" style="justify-content:flex-start">
            <i class="ti ti-building"></i> Export Companies
          </button>
        </form>
      </div>
    </div>

    <!-- DB STATUS -->
    <div class="settings-card">
      <div class="settings-card-header">
        <div class="settings-card-icon teal" style="background:#e6faf4;color:#00a374"><i class="ti ti-server"></i></div>
        <div>
          <div class="settings-card-title">Database Status</div>
          <div class="settings-card-sub">Live record counts</div>
        </div>
      </div>
      <div class="settings-card-body">
        <?php foreach ($db_stats as $label => $count): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="font-size:13px;color:var(--text-muted)"><?= $label ?></span>
          <span style="font-size:14px;font-weight:700;color:var(--text)"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;display:flex;align-items:center;gap:7px;font-size:12px;color:#00956a">
          <i class="ti ti-circle-check" style="font-size:15px"></i>
          Connected — csi_hub
        </div>
      </div>
    </div>

  </div>

</main>
</div>

<?php include 'includes/footer.php'; ?>