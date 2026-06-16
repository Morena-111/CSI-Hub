<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['role'])) { header('Location: /csi-hub/dashboard.php'); exit; }

$admin_accounts = ['admin' => ['password'=>'RU@admin2026','name'=>'Admin User']];
$admins_file = __DIR__ . '/data/admin_users.json';
if (file_exists($admins_file)) {
    $saved = json_decode(file_get_contents($admins_file), true) ?? [];
    foreach ($saved as $u => $d) $admin_accounts[$u] = ['password'=>$d['password'],'name'=>$d['name']];
}
$data_dir     = __DIR__ . '/data';
$signups_file = $data_dir . '/pending_signups.json';
$pending      = file_exists($signups_file) ? json_decode(file_get_contents($signups_file), true) ?? [] : [];

$error = ''; $success = ''; $signup_errors = [];
$active_tab = $_GET['tab'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'login') {
    $u = trim($_POST['username']??''); $p = $_POST['password']??'';
    if (isset($admin_accounts[$u]) && $admin_accounts[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role']='admin'; $_SESSION['name']=$admin_accounts[$u]['name'];
        $_SESSION['username']=$u; $_SESSION['login_time']=time();
        header('Location: /csi-hub/dashboard.php'); exit;
    }
    $approved = array_filter($pending, fn($v) => ($v['approved']??false));
    if (isset($approved[$u]) && $approved[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role']='user'; $_SESSION['name']=$approved[$u]['name'];
        $_SESSION['username']=$u; $_SESSION['org']=$approved[$u]['org']??'';
        $_SESSION['login_time']=time();
        header('Location: /csi-hub/dashboard.php'); exit;
    }
    $error = isset($pending[$u]) && !($pending[$u]['approved']??false)
        ? 'Your account is pending approval. Contact info@researchunlimitedsa.co.za.'
        : 'Incorrect username or password.';
    $active_tab = 'login';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'signup') {
    $sname=$_POST['full_name']??''; $sorg=$_POST['organisation']??'';
    $semail=$_POST['email']??''; $suser=strtolower(trim($_POST['username']??''));
    $spass=$_POST['password']??''; $sconfirm=$_POST['confirm_password']??'';
    if (strlen($sname)<3)  $signup_errors[]='Full name must be at least 3 characters.';
    if (empty($sorg))      $signup_errors[]='Organisation is required.';
    if (!filter_var($semail,FILTER_VALIDATE_EMAIL)) $signup_errors[]='Enter a valid email address.';
    if (strlen($suser)<3)  $signup_errors[]='Username must be at least 3 characters.';
    if (isset($admin_accounts[$suser])||isset($pending[$suser])) $signup_errors[]='Username already taken.';
    if (strlen($spass)<8)              $signup_errors[]='Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/',$spass)) $signup_errors[]='Needs an uppercase letter.';
    if (!preg_match('/[a-z]/',$spass)) $signup_errors[]='Needs a lowercase letter.';
    if (!preg_match('/[0-9]/',$spass)) $signup_errors[]='Needs a number.';
    if (!preg_match('/[\W_]/',$spass)) $signup_errors[]='Needs a special character.';
    if ($spass!==$sconfirm)            $signup_errors[]='Passwords do not match.';
    if (empty($signup_errors)) {
        if (!is_dir($data_dir)) mkdir($data_dir,0755,true);
        $pending[$suser]=['name'=>$sname,'org'=>$sorg,'email'=>$semail,'password'=>$spass,
                          'role'=>'user','approved'=>false,'created'=>date('Y-m-d H:i:s')];
        file_put_contents($signups_file,json_encode($pending,JSON_PRETTY_PRINT));
        $success="Request sent! An admin will approve your account and contact you at {$semail}.";
    }
    $active_tab='signup';
}
$error_get = htmlspecialchars($_GET['error']??'');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CSI Hub | Research Unlimited</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Fraunces:ital,wght@0,600;0,700;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#E8541A; --orange-soft:#fdf0ea;
  --teal:#00c48c;   --teal-soft:#e6faf5;
  --navy:#1a1f2e;
  --purple:#6c5ce7;
  --bg:#f6f7fb;     --border:#e8edf5;
  --text:#1a1f2e;   --muted:#6b7a99;  --light:#a0aec0;
}
*{font-family:'Inter',sans-serif}
html,body{height:100%}
body{min-height:100vh;background:var(--bg);display:flex;flex-direction:column;position:relative}
.topbar,.main,.foot{position:relative;z-index:1}

/* geometric background pattern */
.geo-bg{
  position:fixed;inset:0;z-index:0;pointer-events:none;
  overflow:hidden;
}

/* ── TOP BAR — matches dashboard topnav ── */
.topbar{
  background:white;border-bottom:1px solid var(--border);
  padding:14px 32px;
  display:flex;align-items:center;justify-content:space-between;
}
.topbar-brand{display:flex;align-items:center;gap:12px;text-decoration:none}
.topbar-brand img{height:34px;width:auto;object-fit:contain}
.topbar-brand-text{display:flex;flex-direction:column;line-height:1.25}
.topbar-name{font-size:14px;font-weight:700;color:var(--text)}
.topbar-sub{font-size:11px;color:var(--muted)}


/* ── MAIN AREA ── */
.main{
  flex:1;display:flex;align-items:center;justify-content:center;
  padding:24px;min-height:0;
}

.layout{
  display:flex;align-items:center;gap:64px;
  max-width:1040px;width:100%;
}

/* ── LEFT: mascot + welcome ── */
.intro{
  flex:1;display:flex;flex-direction:column;align-items:center;text-align:center;
}
.mascot-img{
  width:200px;height:auto;object-fit:contain;
  margin-bottom:8px;
  filter:drop-shadow(0 16px 32px rgba(26,31,46,.12));
  animation:bob 4s ease-in-out infinite;
}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

.welcome-bubble{
  background:white;border:1px solid var(--border);
  border-radius:14px;padding:12px 20px;
  font-size:13px;color:var(--muted);
  margin-bottom:28px;
  box-shadow:0 4px 14px rgba(26,31,46,.04);
}
.welcome-bubble strong{color:var(--text)}
.welcome-bubble .o{color:var(--orange);font-weight:700}

.intro-h1{
  font-family:'Fraunces',serif;
  font-size:36px;font-weight:700;color:var(--text);
  line-height:1.25;margin-bottom:10px;
}
.intro-h1 em{
  font-style:italic;color:var(--orange);
}


/* ── RIGHT: login card ── */
.card-col{width:380px;flex-shrink:0}
.card{
  background:white;border:1px solid var(--border);border-radius:16px;
  padding:28px 30px;
  box-shadow:0 4px 24px rgba(26,31,46,.06);
  max-height:calc(100vh - 130px);
  overflow-y:auto;
}

.card-title{font-family:'Fraunces',serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:3px}
.card-sub{font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.5}

/* tabs — match dashboard's pill style */
.tabs{display:flex;background:var(--bg);border-radius:10px;padding:4px;gap:4px;margin-bottom:16px}
.tab{
  flex:1;padding:8px 6px;border:none;background:transparent;cursor:pointer;
  border-radius:7px;font-size:12.5px;font-weight:500;color:var(--muted);
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:all .15s;
}
.tab i{font-size:14px}
.tab.on{background:white;color:var(--orange);font-weight:700;box-shadow:0 1px 4px rgba(26,31,46,.08)}
.tab.tt.on{color:var(--teal)}

.panel{display:none}.panel.on{display:block}

.fg{margin-bottom:12px}
.fl{display:block;font-size:11px;font-weight:600;color:var(--text);margin-bottom:6px}
.iw{position:relative}
.iw i.ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--light)}
.fi{
  width:100%;padding:9px 12px 9px 38px;
  border:1.5px solid var(--border);border-radius:9px;
  font-size:13px;color:var(--text);background:var(--bg);outline:none;
  transition:all .15s;
}
.fi:focus{border-color:var(--orange);background:white;box-shadow:0 0 0 3px var(--orange-soft)}
.fi.ft:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-soft)}
.pw-btn{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--light);font-size:15px}
.pw-btn:hover{color:var(--muted)}

.al{border-radius:9px;padding:10px 13px;margin-bottom:14px;display:flex;gap:8px;font-size:12px;line-height:1.5;align-items:flex-start}
.al i{font-size:14px;flex-shrink:0;margin-top:1px}
.al-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.al-err i{color:#ef4444}
.al-ok{background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36}.al-ok i{color:var(--teal)}
.al-info{background:var(--orange-soft);border:1px solid #fbd5be;color:#9a3412}
.al-info i{color:var(--orange)}.al-info a{color:var(--orange);font-weight:700;text-decoration:none}
.errs{list-style:none;display:flex;flex-direction:column;gap:2px}
.errs li::before{content:'• '}

.sbar-wrap{margin-top:6px}
.sbar{height:4px;border-radius:2px;background:var(--border);overflow:hidden;margin-bottom:4px}
.sfill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0}
.srules{display:flex;flex-wrap:wrap;gap:5px}
.srule{font-size:10px;color:var(--light);display:flex;align-items:center;gap:2px}
.srule.ok{color:var(--teal)}.srule i{font-size:10px}

.sbtn{
  width:100%;padding:11px;border:none;border-radius:9px;
  font-size:13.5px;font-weight:700;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:all .15s;margin-top:4px;
}
.sbtn:hover{transform:translateY(-1px)}
.s-orange{background:var(--orange);color:white}
.s-orange:hover{background:#c94514}
.s-teal{background:var(--teal);color:white}
.s-teal:hover{background:#00a077}

.frow{display:grid;grid-template-columns:1fr 1fr;gap:10px}

.swlink{text-align:center;margin-top:14px;font-size:12px;color:var(--muted)}
.swlink a{font-weight:700;text-decoration:none}
.swlink a.o{color:var(--orange)}
.swlink a.t{color:var(--teal)}

/* footer */
.foot{
  text-align:center;font-size:11.5px;color:var(--light);
  padding:18px 0;border-top:1px solid var(--border);background:white;
}
.foot a{color:var(--orange);text-decoration:none}

@media(max-width:900px){
  .layout{flex-direction:column;gap:32px}
  .card-col{width:100%;max-width:380px}
}
</style>
</head>
<body>

<!-- GEOMETRIC BACKGROUND -->
<svg class="geo-bg" preserveAspectRatio="none" viewBox="0 0 1440 900" xmlns="http://www.w3.org/2000/svg">

  <!-- ═══ TOP-LEFT — orange chevrons ═══ -->
  <g stroke="#E8541A" stroke-width="2" fill="none" opacity="0.19">
    <polyline points="0,40  80,110 160,40  240,110 320,40  400,110"/>
    <polyline points="0,90  80,160 160,90  240,160 320,90  400,160"/>
    <polyline points="0,140 80,210 160,140 240,210 320,140 400,210"/>
    <polyline points="0,190 80,260 160,190 240,260 320,190 400,260"/>
    <line x1="80"  y1="110" x2="80"  y2="260"/>
    <line x1="160" y1="40"  x2="160" y2="210"/>
    <line x1="240" y1="110" x2="240" y2="260"/>
    <line x1="320" y1="40"  x2="320" y2="210"/>
  </g>

  <!-- ═══ TOP-RIGHT — teal/gold chevrons ═══ -->
  <g stroke="#00c48c" stroke-width="2" fill="none" opacity="0.19">
    <polyline points="1100,0 1180,70 1260,0 1340,70 1420,0"/>
    <polyline points="1100,50 1180,120 1260,50 1340,120 1420,50"/>
    <polyline points="1100,100 1180,170 1260,100 1340,170 1420,100"/>
    <polyline points="1100,150 1180,220 1260,150 1340,220 1420,150"/>
    <line x1="1180" y1="70"  x2="1180" y2="220"/>
    <line x1="1260" y1="0"   x2="1260" y2="170"/>
    <line x1="1340" y1="70"  x2="1340" y2="220"/>
  </g>
  <g stroke="#f5a623" stroke-width="1.5" fill="none" opacity="0.17">
    <polyline points="1100,200 1180,270 1260,200 1340,270 1420,200"/>
    <line x1="1180" y1="270" x2="1180" y2="320"/>
  </g>

  <!-- ═══ BOTTOM-LEFT — teal chevrons ═══ -->
  <g stroke="#00c48c" stroke-width="2" fill="none" opacity="0.17">
    <polyline points="0,750 80,820 160,750 240,820 320,750"/>
    <polyline points="0,800 80,870 160,800 240,870 320,800"/>
    <polyline points="0,850 80,920 160,850 240,920 320,850"/>
    <line x1="80"  y1="820" x2="80"  y2="920"/>
    <line x1="160" y1="750" x2="160" y2="850"/>
    <line x1="240" y1="820" x2="240" y2="920"/>
  </g>

  <!-- ═══ BOTTOM-RIGHT — orange chevrons ═══ -->
  <g stroke="#E8541A" stroke-width="2" fill="none" opacity="0.17">
    <polyline points="1100,780 1180,850 1260,780 1340,850 1420,780"/>
    <polyline points="1100,830 1180,900 1260,830 1340,900 1420,830"/>
    <line x1="1180" y1="850" x2="1180" y2="900"/>
    <line x1="1260" y1="780" x2="1260" y2="900"/>
    <line x1="1340" y1="850" x2="1340" y2="900"/>
  </g>

  <!-- ═══ OUTLINE CIRCLES — all 4 corners ═══ -->
  <circle cx="60"   cy="40"  r="160" fill="none" stroke="#E8541A" stroke-width="1.5" opacity="0.15"/>
  <circle cx="60"   cy="40"  r="230" fill="none" stroke="#f5a623" stroke-width="1"   opacity="0.12"/>

  <circle cx="1380" cy="60"  r="180" fill="none" stroke="#f5a623" stroke-width="1.5" opacity="0.17"/>
  <circle cx="1380" cy="60"  r="260" fill="none" stroke="#E8541A" stroke-width="1"   opacity="0.13"/>

  <circle cx="40"   cy="860" r="200" fill="none" stroke="#00c48c" stroke-width="1.5" opacity="0.17"/>
  <circle cx="40"   cy="860" r="280" fill="none" stroke="#2dbcd8" stroke-width="1"   opacity="0.13"/>

  <circle cx="1400" cy="880" r="170" fill="none" stroke="#E8541A" stroke-width="1.5" opacity="0.15"/>
  <circle cx="1400" cy="880" r="240" fill="none" stroke="#00c48c" stroke-width="1"   opacity="0.12"/>

  <!-- ═══ DIAGONAL ACCENT LINES ═══ -->
  <line x1="900" y1="0"   x2="1440" y2="500" stroke="#E8541A" stroke-width="1" opacity="0.15"/>
  <line x1="0"   y1="400" x2="500"  y2="900" stroke="#00c48c" stroke-width="1" opacity="0.15"/>
  <line x1="500" y1="0"   x2="900"  y2="350" stroke="#2dbcd8" stroke-width="1" opacity="0.13"/>
  <line x1="600" y1="900" x2="1000" y2="550" stroke="#f5a623" stroke-width="1" opacity="0.13"/>

  <!-- ═══ SCATTERED ROTATED SQUARES ═══ -->
  <rect x="200"  y="60"  width="10" height="10" fill="#E8541A" opacity="0.22" transform="rotate(15 205 65)"/>
  <rect x="700"  y="780" width="14" height="14" fill="#00c48c" opacity="0.19" transform="rotate(-10 707 787)"/>
  <rect x="1050" y="650" width="8"  height="8"  fill="#f5a623" opacity="0.25" transform="rotate(25 1054 654)"/>
  <rect x="380"  y="820" width="10" height="10" fill="#2dbcd8" opacity="0.21" transform="rotate(20 385 825)"/>
  <rect x="640"  y="120" width="12" height="12" fill="#E8541A" opacity="0.19" transform="rotate(-18 646 126)"/>
  <rect x="1200" y="450" width="9"  height="9"  fill="#00c48c" opacity="0.22" transform="rotate(12 1204 454)"/>
  <rect x="120"  y="500" width="11" height="11" fill="#f5a623" opacity="0.20" transform="rotate(-22 125 505)"/>
  <rect x="950"  y="200" width="8"  height="8"  fill="#2dbcd8" opacity="0.23" transform="rotate(30 954 204)"/>


  <!-- ═══ CENTRE-LEFT extra chevrons ═══ -->
  <g stroke="#f5a623" stroke-width="1.5" fill="none" opacity=".09">
    <polyline points="0,420 70,480 140,420 210,480 280,420"/>
    <polyline points="0,465 70,525 140,465 210,525 280,465"/>
    <line x1="70"  y1="480" x2="70"  y2="525"/>
    <line x1="140" y1="420" x2="140" y2="465"/>
  </g>

  <!-- ═══ CENTRE-RIGHT extra chevrons ═══ -->
  <g stroke="#2dbcd8" stroke-width="1.5" fill="none" opacity=".09">
    <polyline points="1180,400 1250,460 1320,400 1390,460 1440,400"/>
    <polyline points="1180,445 1250,505 1320,445 1390,505 1440,445"/>
    <line x1="1250" y1="460" x2="1250" y2="505"/>
    <line x1="1320" y1="400" x2="1320" y2="445"/>
  </g>

  <!-- mid-page outline circles -->
  <circle cx="720" cy="450" r="320" fill="none" stroke="#E8541A" stroke-width="1" opacity=".05"/>
  <circle cx="720" cy="450" r="220" fill="none" stroke="#00c48c" stroke-width="1" opacity=".05"/>

  <!-- extra scattered squares centre area -->
  <rect x="500" y="430" width="10" height="10" fill="#f5a623" opacity=".14" transform="rotate(18 505 435)"/>
  <rect x="850" y="500" width="9"  height="9"  fill="#E8541A" opacity=".14" transform="rotate(-15 854 504)"/>
  <rect x="300" y="300" width="8"  height="8"  fill="#2dbcd8" opacity=".15" transform="rotate(22 304 304)"/>
  <rect x="1000" y="350" width="11" height="11" fill="#00c48c" opacity=".13" transform="rotate(-20 1005 355)"/>
</svg>

<!-- TOPBAR -->
<div class="topbar">
  <a class="topbar-brand" href="#">
    <img src="assets/img/logo.png" alt="Research Unlimited">
    <div class="topbar-brand-text">
      <span class="topbar-name">Research Unlimited</span>
      <span class="topbar-sub">CSI Hub</span>
    </div>
  </a>
</div>

<!-- MAIN -->
<div class="main">
  <div class="layout">

    <!-- LEFT: mascot + intro -->
    <div class="intro">
      <img class="mascot-img" src="assets/img/mascot.png" alt="RU Mascot">

      <div class="welcome-bubble">
        <span class="o">Hey there! 👋</span> — <strong>Welcome to CSI Hub.</strong>
      </div>

    </div>

    <!-- RIGHT: login card -->
    <div class="card-col">
      <div class="card">

        <?php if ($error_get): ?>
        <div class="al al-err"><i class="ti ti-alert-circle"></i><?= $error_get ?></div>
        <?php endif; ?>

        <h2 class="card-title">Sign In</h2>
        <p class="card-sub">Access your CSI Hub account.</p>

        <div class="tabs">
          <button class="tab <?= $active_tab==='login'?'on':'' ?>" id="tb-login" onclick="go('login')">
            <i class="ti ti-login"></i> Sign In
          </button>
          <button class="tab tt <?= $active_tab==='signup'?'on':'' ?>" id="tb-signup" onclick="go('signup')">
            <i class="ti ti-user-plus"></i> Request Access
          </button>
        </div>

        <!-- SIGN IN -->
        <div class="panel <?= $active_tab==='login'?'on':'' ?>" id="p-login">

          <?php if ($error && $active_tab==='login'): ?>
          <div class="al al-err"><i class="ti ti-alert-circle"></i><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="form" value="login">
            <div class="fg">
              <label class="fl">Username</label>
              <div class="iw">
                <i class="ico ti ti-user"></i>
                <input class="fi" type="text" name="username"
                       value="<?= $active_tab==='login'?htmlspecialchars($_POST['username']??''):'' ?>"
                       placeholder="Enter your username" autocomplete="username" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Password</label>
              <div class="iw">
                <i class="ico ti ti-lock"></i>
                <input class="fi" type="password" id="lp" name="password"
                       placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="pw-btn" onclick="tpw('lp','le')">
                  <i class="ti ti-eye" id="le"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="sbtn s-orange">
              <i class="ti ti-login"></i> Sign In
            </button>
          </form>

          <div class="swlink">
            Don't have access? <a href="#" class="t" onclick="go('signup')">Request access →</a>
          </div>
        </div>

        <!-- REQUEST ACCESS -->
        <div class="panel <?= $active_tab==='signup'?'on':'' ?>" id="p-signup">

          <?php if ($success): ?>
          <div class="al al-ok"><i class="ti ti-circle-check"></i><div><?= htmlspecialchars($success) ?></div></div>
          <?php elseif (!empty($signup_errors)): ?>
          <div class="al al-err">
            <i class="ti ti-alert-circle"></i>
            <ul class="errs"><?php foreach($signup_errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <?php if (!$success): ?>
          <form method="POST">
            <input type="hidden" name="form" value="signup">
            <div class="fg">
              <label class="fl">Full Name *</label>
              <div class="iw"><i class="ico ti ti-user"></i>
                <input class="fi ft" type="text" name="full_name" placeholder="Your name"
                       value="<?= htmlspecialchars($_POST['full_name']??'') ?>" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Organisation *</label>
              <div class="iw"><i class="ico ti ti-building"></i>
                <input class="fi ft" type="text" name="organisation" placeholder="Company/School"
                       value="<?= htmlspecialchars($_POST['organisation']??'') ?>" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Email Address *</label>
              <div class="iw"><i class="ico ti ti-mail"></i>
                <input class="fi ft" type="email" name="email" placeholder="you@organisation.co.za"
                       value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Username *</label>
              <div class="iw"><i class="ico ti ti-at"></i>
                <input class="fi ft" type="text" name="username" placeholder="e.g. jsmith"
                       value="<?= htmlspecialchars($_POST['username']??'') ?>" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Password *</label>
              <div class="iw"><i class="ico ti ti-lock"></i>
                <input class="fi ft" type="password" id="sp" name="password"
                       placeholder="Create a password" oninput="chkStr(this.value)" required>
                <button type="button" class="pw-btn" onclick="tpw('sp','se')">
                  <i class="ti ti-eye" id="se"></i>
                </button>
              </div>
              <div class="sbar-wrap">
                <div class="sbar"><div class="sfill" id="sf"></div></div>
                <div class="srules">
                  <span class="srule" id="r-len"><i class="ti ti-circle"></i> 8+</span>
                  <span class="srule" id="r-up"><i class="ti ti-circle"></i> A-Z</span>
                  <span class="srule" id="r-lo"><i class="ti ti-circle"></i> a-z</span>
                  <span class="srule" id="r-num"><i class="ti ti-circle"></i> 0-9</span>
                  <span class="srule" id="r-sym"><i class="ti ti-circle"></i> @!#</span>
                </div>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Confirm Password *</label>
              <div class="iw"><i class="ico ti ti-lock-check"></i>
                <input class="fi ft" type="password" id="sc" name="confirm_password" placeholder="Repeat password" required>
                <button type="button" class="pw-btn" onclick="tpw('sc','sce')">
                  <i class="ti ti-eye" id="sce"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="sbtn s-teal">
              <i class="ti ti-send"></i> Submit Request
            </button>
          </form>
          <?php endif; ?>

          <div class="swlink">
            Already have access? <a href="#" class="o" onclick="go('login')">Sign in →</a>
          </div>
        </div>

      </div><!-- .card -->
    </div>

  </div><!-- .layout -->
</div><!-- .main -->

<!-- FOOTER -->
<div class="foot">
  &copy; <?= date('Y') ?> Research Unlimited &mdash;
  <a href="mailto:info@researchunlimitedsa.co.za">info@researchunlimitedsa.co.za</a>
  &nbsp;|&nbsp; Internal system — authorised access only
</div>

<script>
function go(n){
  ['login','signup'].forEach(t=>{
    document.getElementById('p-'+t).classList.toggle('on',t===n);
    document.getElementById('tb-'+t).classList.toggle('on',t===n);
  });
}
go('<?= $active_tab ?>');
function tpw(id,eid){
  const i=document.getElementById(id),e=document.getElementById(eid);
  i.type=i.type==='password'?'text':'password';
  e.className=i.type==='text'?'ti ti-eye-off':'ti ti-eye';
}
function chkStr(v){
  const r={len:v.length>=8,up:/[A-Z]/.test(v),lo:/[a-z]/.test(v),num:/[0-9]/.test(v),sym:/[\W_]/.test(v)};
  const s=Object.values(r).filter(Boolean).length;
  const f=document.getElementById('sf');
  f.style.width=(s*20)+'%';
  f.style.background=['#ef4444','#ef4444','#f59e0b','#34d399','#00c48c'][s];
  const m={len:'r-len',up:'r-up',lo:'r-lo',num:'r-num',sym:'r-sym'};
  Object.entries(r).forEach(([k,ok])=>{
    const el=document.getElementById(m[k]);if(!el)return;
    el.classList.toggle('ok',ok);
    el.querySelector('i').className=ok?'ti ti-circle-check':'ti ti-circle';
  });
}
</script>
</body>
</html>