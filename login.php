<?php
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['role'])) { redirect('dashboard.php');  }

$_root_pw_file = __DIR__ . '/data/root_password.json';
$_root_pw      = file_exists($_root_pw_file)
    ? (json_decode(file_get_contents($_root_pw_file), true)['password'] ?? 'RU@admin2026')
    : 'RU@admin2026';
$admin_accounts = ['admin' => ['password' => $_root_pw, 'name' => 'Admin User']];

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
        redirect('dashboard.php'); 
    }
    $approved = array_filter($pending, fn($v) => ($v['approved']??false));
    if (isset($approved[$u]) && $approved[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role']      = 'user';
        $_SESSION['name']      = $approved[$u]['name'];
        $_SESSION['username']  = $u;
        $_SESSION['org']       = $approved[$u]['org'] ?? '';
        $_SESSION['user_type'] = $approved[$u]['user_type'] ?? 'general';
        $_SESSION['linked_id'] = $approved[$u]['linked_id'] ?? null;
        $_SESSION['login_time']= time();
        redirect('dashboard.php'); 
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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#E8541A; --orange-h:#c94514; --orange-soft:#fdf0ea;
  --teal:#00c48c;   --teal-h:#00a077;   --teal-soft:#e6faf5;
  --navy:#1a1f2e;
  --bg:#f6f7fb;     --border:#e8edf5;
  --text:#1a1f2e;   --muted:#6b7a99;  --light:#a0aec0;
}
*{font-family:'Poppins',sans-serif}
html,body{height:100%}
body{min-height:100vh;background:var(--bg);display:flex;flex-direction:column;position:relative}

/* ── CIRCUIT BOARD BACKGROUND (your PNG) ── */
.circuit-bg{
  position:fixed;inset:0;z-index:0;
  background-image:url('assets/img/circuit-bg.png');
  background-size:cover;
  background-position:center;
  background-repeat:no-repeat;
  opacity:.35;
  pointer-events:none;
}
.topbar,.main,.foot{position:relative;z-index:1}

/* ── TOP BAR ── */
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

/* ── RIGHT: login card ── */
.card-col{width:380px;flex-shrink:0}
.card{
  background:white;border:1px solid var(--border);border-radius:16px;
  padding:28px 30px;
  box-shadow:0 4px 24px rgba(26,31,46,.06);
  max-height:calc(100vh - 130px);
  overflow-y:auto;
}

.card-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:3px}
.card-sub{font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.5}

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

<!-- circuit board background -->
<div class="circuit-bg"></div>

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