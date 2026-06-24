<?php
$active_page = '';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$success = ''; $error = '';
$uname   = $_SESSION['username'] ?? 'admin';
$utype   = $_SESSION['user_type'] ?? 'general';

// Load current user data
$admins_file = __DIR__ . '/data/admin_users.json';
$signups_file= __DIR__ . '/data/pending_signups.json';
$admins  = file_exists($admins_file)  ? json_decode(file_get_contents($admins_file),  true) ?? [] : [];
$pending = file_exists($signups_file) ? json_decode(file_get_contents($signups_file), true) ?? [] : [];

$is_root = ($uname === 'admin');
$current = $is_root
    ? ['name'=>'Admin User','email'=>'admin@researchunlimitedsa.co.za','org'=>'Research Unlimited','user_type'=>'general']
    : ($admins[$uname] ?? $pending[$uname] ?? []);

// ── SAVE PROFILE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='profile') {
    $new_name  = trim($_POST['full_name']??'');
    $new_email = trim($_POST['email']??'');
    $new_org   = trim($_POST['organisation']??'');
    if (strlen($new_name)<2) { $error = 'Name must be at least 2 characters.'; }
    else {
        if (!$is_root) {
            if (isset($admins[$uname])) {
                $admins[$uname]['name']  = $new_name;
                $admins[$uname]['email'] = $new_email;
                file_put_contents($admins_file, json_encode($admins,JSON_PRETTY_PRINT));
            } elseif (isset($pending[$uname])) {
                $pending[$uname]['name']  = $new_name;
                $pending[$uname]['email'] = $new_email;
                $pending[$uname]['org']   = $new_org;
                file_put_contents($signups_file, json_encode($pending,JSON_PRETTY_PRINT));
            }
        }
        $_SESSION['name'] = $new_name;
        $current['name']  = $new_name;
        $current['email'] = $new_email;
        $success = 'Profile updated.';
    }
}

// ── CHANGE PASSWORD ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='password') {
    $cur  = $_POST['current_password']??'';
    $new  = $_POST['new_password']??'';
    $conf = $_POST['confirm_password']??'';
    $root_pw_file   = __DIR__ . '/data/root_password.json';
    $stored = $is_root
        ? (file_exists($root_pw_file) ? (json_decode(file_get_contents($root_pw_file),true)['password']??'RU@admin2026') : 'RU@admin2026')
        : ($admins[$uname]['password'] ?? $pending[$uname]['password'] ?? '');
    if ($cur !== $stored)              $error = 'Current password is incorrect.';
    elseif (strlen($new)<8)            $error = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/',$new)) $error = 'Needs an uppercase letter.';
    elseif (!preg_match('/[0-9]/',$new)) $error = 'Needs a number.';
    elseif ($new !== $conf)            $error = 'Passwords do not match.';
    else {
        if (!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data',0755,true);
        if ($is_root) {
            file_put_contents($root_pw_file, json_encode(['password'=>$new],JSON_PRETTY_PRINT));
        } elseif (isset($admins[$uname])) {
            $admins[$uname]['password'] = $new;
            file_put_contents($admins_file, json_encode($admins,JSON_PRETTY_PRINT));
        } elseif (isset($pending[$uname])) {
            $pending[$uname]['password'] = $new;
            file_put_contents($signups_file, json_encode($pending,JSON_PRETTY_PRINT));
        }
        $success = 'Password updated.';
    }
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">My Profile</span>
</div>

<div class="page-header">
  <div>
    <h1>My Profile</h1>
    <p>Edit your name, contact email and password</p>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-alert-circle"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;max-width:860px">

  <!-- PROFILE INFO -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-user"></i> Personal Info</div>
    <form method="POST">
      <input type="hidden" name="form" value="profile">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input class="form-input" type="text" name="full_name" value="<?= htmlspecialchars($current['name']??'') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input class="form-input" type="text" value="<?= htmlspecialchars($uname) ?>" disabled style="background:var(--surface);color:var(--text-muted)">
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-input" type="email" name="email" value="<?= htmlspecialchars($current['email']??'') ?>" placeholder="your@email.co.za">
      </div>
      <?php if(isset($current['org'])): ?>
      <div class="form-group">
        <label class="form-label">Organisation</label>
        <input class="form-input" type="text" name="organisation" value="<?= htmlspecialchars($current['org']??'') ?>" placeholder="Your company/school">
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Role / Type</label>
        <input class="form-input" type="text" value="<?= ucfirst($utype) ?> — <?= is_admin()?'Administrator':'User' ?>" disabled style="background:var(--surface);color:var(--text-muted)">
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Profile</button>
    </form>
  </div>

  <!-- CHANGE PASSWORD -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-lock"></i> Change Password</div>
    <form method="POST">
      <input type="hidden" name="form" value="password">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input class="form-input" type="password" name="current_password" placeholder="Enter current password" required>
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input class="form-input" type="password" name="new_password" id="np" placeholder="Min 8 chars, uppercase, number" required oninput="chkPw(this.value)">
        <div style="height:4px;background:var(--border);border-radius:2px;margin-top:6px;overflow:hidden">
          <div id="pw-bar" style="height:100%;border-radius:2px;transition:width .3s,background .3s;width:0"></div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input class="form-input" type="password" name="confirm_password" placeholder="Repeat new password" required>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-lock-check"></i> Update Password</button>
    </form>
  </div>

</div>

</main>
</div>
<script>
function chkPw(v){
  const s=[v.length>=8,/[A-Z]/.test(v),/[a-z]/.test(v),/[0-9]/.test(v),/[\W_]/.test(v)].filter(Boolean).length;
  const b=document.getElementById('pw-bar');
  b.style.width=(s*20)+'%';
  b.style.background=['#ef4444','#ef4444','#f59e0b','#34d399','#00c48c'][s];
}
</script>
<?php include 'includes/footer.php'; ?>