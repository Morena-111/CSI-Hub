<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
if (isset($_SESSION['role'])) { redirect('dashboard.php'); }

$_root_pw_file = __DIR__ . '/data/root_password.json';
$_root_pw      = file_exists($_root_pw_file)
    ? (json_decode(file_get_contents($_root_pw_file), true)['password'] ?? 'RU@admin2026')
    : 'RU@admin2026';
$admin_accounts = ['admin' => ['password' => $_root_pw, 'name' => 'Admin User']];
$admins_file = __DIR__ . '/data/admin_users.json';
if (file_exists($admins_file)) {
    $saved = json_decode(file_get_contents($admins_file), true) ?? [];
    foreach ($saved as $u => $d)
        $admin_accounts[$u] = ['password' => $d['password'], 'name' => $d['name']];
}
$data_dir     = __DIR__ . '/data';
$signups_file = $data_dir . '/pending_signups.json';
$pending      = file_exists($signups_file)
    ? json_decode(file_get_contents($signups_file), true) ?? [] : [];

$error = ''; $success = ''; $signup_errors = [];
$active_tab = $_GET['tab'] ?? 'login';

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (isset($admin_accounts[$u]) && $admin_accounts[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin'; $_SESSION['name'] = $admin_accounts[$u]['name'];
        $_SESSION['username'] = $u; $_SESSION['login_time'] = time();
        redirect('dashboard.php');
    }
    $approved = array_filter($pending, fn($v) => ($v['approved'] ?? false));
    if (isset($approved[$u]) && $approved[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role']       = 'user';
        $_SESSION['name']       = $approved[$u]['name'];
        $_SESSION['username']   = $u;
        $_SESSION['org']        = $approved[$u]['org'] ?? '';
        $_SESSION['user_type']  = $approved[$u]['user_type'] ?? 'general';
        $_SESSION['linked_id']  = $approved[$u]['linked_id'] ?? null;
        $_SESSION['login_time'] = time();
        redirect('dashboard.php');
    }
    $error = isset($pending[$u]) && !($pending[$u]['approved'] ?? false)
        ? 'Your account is pending admin approval. You will be notified by email.'
        : 'Incorrect username or password.';
    $active_tab = 'login';
}

// ── SIGNUP ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'signup') {
    $utype  = $_POST['user_type'] ?? '';
    $suser  = strtolower(trim($_POST['username'] ?? ''));
    $spass  = $_POST['password'] ?? '';
    $sconf  = $_POST['confirm_password'] ?? '';
    $semail = trim($_POST['email'] ?? '');
    $sphone = trim($_POST['phone'] ?? '');

    if ($utype === 'company') {
        $org_name = trim($_POST['company_name'] ?? '');
        $contact  = trim($_POST['contact_person'] ?? '');
        $display  = $contact ?: $org_name;
    } else {
        $org_name  = trim($_POST['school_name'] ?? '');
        $principal = trim($_POST['principal_name'] ?? '');
        $display   = $principal ?: $org_name;
    }

    if (!in_array($utype, ['company','school'])) $signup_errors[] = 'Please select your account type.';
    if (empty($org_name))   $signup_errors[] = ($utype==='company'?'Company':'School').' name is required.';
    if (empty($suser) || strlen($suser)<3) $signup_errors[] = 'Username must be at least 3 characters.';
    if (isset($admin_accounts[$suser])||isset($pending[$suser])) $signup_errors[] = 'Username already taken.';
    // Only validate email/phone if the full form was submitted (username filled in)
    if (!empty($suser)) {
        // email stored as-is, no validation blocking
        $phone_digits = preg_replace('/[^0-9]/', '', $sphone ?? '');
        if (!empty($sphone) && strlen($phone_digits) < 9) $signup_errors[] = 'Enter a valid contact number (e.g. 079 534 3798).';
    }
    if (strlen($spass)<8)   $signup_errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $spass)) $signup_errors[] = 'Password needs an uppercase letter.';
    if (!preg_match('/[0-9]/', $spass)) $signup_errors[] = 'Password needs a number.';
    if ($spass !== $sconf)  $signup_errors[] = 'Passwords do not match.';

    if (empty($signup_errors)) {
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        $record = [
            'name' => $display, 'org' => $org_name,
            'email' => $semail, 'phone' => $sphone,
            'id_number' => trim($_POST['id_number']??''),
            'password' => $spass, 'user_type' => $utype,
            'approved' => false, 'created' => date('Y-m-d H:i:s'),
        ];
        if ($utype === 'company') {
            $record['reg_number']     = trim($_POST['reg_number']??'');
            $record['contact_person'] = $contact;
            $record['csi_budget']     = trim($_POST['csi_budget']??'');
            $record['focus_areas']    = implode(',', (array)($_POST['focus_areas']??[]));
            $record['provinces']      = implode(',', (array)($_POST['provinces']??[]));
            $record['programme_pref'] = trim($_POST['programme_pref']??'');
        } else {
            $record['province']       = trim($_POST['province']??'');
            $record['district']       = trim($_POST['district']??'');
            $record['principal_name'] = $principal;
            $record['learners']       = trim($_POST['learners']??'');
            $record['educators']      = trim($_POST['educators']??'');
            $record['funding_needed'] = trim($_POST['funding_needed']??'');
            $record['challenges']     = trim($_POST['challenges']??'');
        }
        $pending[$suser] = $record;
        file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
        $success = "Request submitted! We will review and contact you at {$semail} within 2 business days.";
        $active_tab = 'login';
    } else {
        $active_tab = 'signup';
    }
}

$provinces_list = ['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape',
                   'Limpopo','Mpumalanga','North West','Free State','Northern Cape'];
$focus_list = ['STEM','Literacy','Digital Skills','Science','Arts & Culture',
               'Skills Development','Sports','Health & Nutrition','Infrastructure'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CSI Hub by Research Unlimited | Connecting Companies &amp; Schools for CSI</title>
<meta name="description" content="CSI Hub is Research Unlimited's platform connecting South African companies and schools for research-backed Corporate Social Investment — verified partners, smart matching, and real-time impact tracking.">
<meta property="og:title" content="CSI Hub | Research Unlimited">
<meta property="og:description" content="Connecting South African companies and schools for research-backed Corporate Social Investment.">
<meta property="og:image" content="assets/img/hero-photo.jpg">
<meta property="og:type" content="website">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" href="assets/img/logo.png" type="image/png">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#E8541A;--orange-h:#c94514;--orange-soft:#fdf0ea;
  --teal:#00c48c;--teal-soft:#e6faf5;--navy:#0d1e3d;--navy2:#132a52;
  --bg:#f0f2f7;--white:#fff;--border:#e2e8f0;
  --text:#1a202c;--muted:#64748b;--light:#94a3b8;
}
html,body{height:100%;font-family:'Poppins',sans-serif;color:var(--text)}
body{
  background:linear-gradient(160deg,#0d1e3d 0%,#16305c 55%,#0d1e3d 100%);
  min-height:100vh;display:flex;flex-direction:column;
}

/* ═══════════ SITE HEADER (keeps this page connected to the main site) ═══════════ */
.site-header{
  position:relative;z-index:5;width:100%;
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 34px;background:rgba(13,30,61,.55);backdrop-filter:blur(10px);
  border-bottom:1px solid rgba(255,255,255,.08);
}
.site-header .sh-brand{display:flex;align-items:center;gap:10px}
.site-header .sh-brand img{height:32px;width:auto;object-fit:contain}
.site-header .sh-brand-text{display:flex;flex-direction:column;line-height:1.15}
.site-header .sh-brand-text b{font-size:13px;color:#fff}
.site-header .sh-brand-text span{font-size:8.5px;color:#a9bcdc;text-transform:uppercase;letter-spacing:.08em}
.sh-nav{display:flex;align-items:center;gap:26px}
.sh-nav a{
  font-size:12.5px;font-weight:500;color:#dbe6f7;text-decoration:none;
  position:relative;padding:4px 0;transition:color .18s;
}
.sh-nav a::after{
  content:'';position:absolute;left:0;bottom:0;width:0;height:2px;
  background:var(--orange);transition:width .22s;
}
.sh-nav a:hover{color:#fff}
.sh-nav a:hover::after{width:100%}
.sh-cta{
  background:var(--orange);color:#fff;font-size:12.5px;font-weight:700;
  padding:9px 18px;border-radius:99px;text-decoration:none;
  box-shadow:0 4px 14px rgba(232,84,26,.35);transition:all .2s;border:none;cursor:pointer;
}
.sh-cta:hover{background:var(--orange-h);transform:translateY(-1px);box-shadow:0 6px 20px rgba(232,84,26,.45)}
@media(max-width:760px){
  .sh-nav{display:none}
  .site-header{padding:12px 18px}
}

/* ═══════════ SITE FOOTER ═══════════ */
.site-footer{
  position:relative;z-index:5;width:100%;
  padding:20px 34px;background:rgba(9,20,41,.7);backdrop-filter:blur(10px);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
  border-top:1px solid rgba(255,255,255,.08);
}
.sf-links{display:flex;gap:18px;flex-wrap:wrap}
.sf-links a{color:#b9c8e2;font-size:11.5px;text-decoration:none;transition:color .18s}
.sf-links a:hover{color:var(--orange)}
.sf-social{display:flex;gap:10px}
.sf-social a{
  width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  background:rgba(255,255,255,.08);color:#dbe6f7;font-size:13px;text-decoration:none;
  transition:all .2s;border:1px solid rgba(255,255,255,.12);
}
.sf-social a:hover{background:var(--orange);color:#fff;transform:translateY(-2px)}
.sf-copy{font-size:10.5px;color:#7f92b3}
@media(max-width:760px){
  .site-footer{flex-direction:column;align-items:flex-start;padding:16px 18px}
}
.fall-layer{position:absolute;inset:0;z-index:0;overflow:hidden;pointer-events:none}
.fall-icon{
  position:absolute;top:-8%;color:rgba(255,255,255,.16);
  animation-name:fallDown;animation-timing-function:linear;animation-iteration-count:infinite;
  will-change:transform,opacity;
}
.fall-icon.on-light{color:rgba(232,84,26,.10)}
@keyframes fallDown{
  0%{transform:translateY(0) translateX(0) rotate(0deg);opacity:0}
  8%{opacity:1}
  92%{opacity:1}
  100%{transform:translateY(112vh) translateX(var(--drift,24px)) rotate(var(--spin,90deg));opacity:0}
}

/* ═══════════ PAGE FRAME ═══════════ */
.page{
  position:relative;z-index:1;
  flex:1;display:flex;
  align-items:center;justify-content:center;
  padding:26px;
}
.frame{
  width:100%;max-width:1180px;height:calc(100vh - 168px);max-height:760px;min-height:560px;
  display:flex;background:var(--white);
  border-radius:26px;overflow:hidden;
  box-shadow:0 30px 80px rgba(0,0,0,.45);
  animation:frameIn .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes frameIn{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}

/* ═══════════ LEFT: HERO / MARKETING PANEL ═══════════ */
.hero-col{
  flex:1.2;position:relative;overflow:hidden;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:40px 44px 28px;
  color:#fff;
}
.hero-col::before{
  content:'';position:absolute;inset:0;
  background:url('assets/img/hero-photo.jpg') center/cover no-repeat;
  filter:saturate(1.05);
}
.hero-col::after{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(circle at 85% 88%,rgba(232,84,26,.35) 0%,transparent 45%),
    radial-gradient(circle at 8% 20%,rgba(0,196,140,.18) 0%,transparent 40%),
    linear-gradient(160deg,rgba(13,30,61,.95) 0%,rgba(19,42,82,.92) 45%,rgba(232,84,26,.5) 130%);
}
.hero-shapes{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:1}
.blob{position:absolute;border-radius:50%;filter:blur(2px);opacity:.35;animation:drift 14s ease-in-out infinite}
.blob1{width:220px;height:220px;background:radial-gradient(circle,var(--orange) 0%,transparent 70%);top:-60px;right:-40px;animation-delay:0s}
.blob2{width:160px;height:160px;background:radial-gradient(circle,var(--teal) 0%,transparent 70%);bottom:10%;right:20%;animation-delay:3s}
.blob3{width:130px;height:130px;background:radial-gradient(circle,#fff 0%,transparent 70%);top:35%;left:-30px;opacity:.12;animation-delay:6s}
@keyframes drift{
  0%,100%{transform:translate(0,0) scale(1)}
  33%{transform:translate(18px,-22px) scale(1.08)}
  66%{transform:translate(-14px,14px) scale(.95)}
}

.trust-chips{position:absolute;top:26px;right:30px;z-index:2;display:flex;flex-direction:column;gap:9px;align-items:flex-end}
.trust-chip{
  display:flex;align-items:center;gap:6px;
  background:rgba(13,30,61,.55);backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.22);border-radius:99px;
  padding:6px 12px 6px 9px;font-size:10.5px;font-weight:600;color:#fff;
  animation:bob 4.5s ease-in-out infinite;box-shadow:0 6px 16px rgba(0,0,0,.2);
}
.trust-chip i{font-size:13px}
.trust-chip.tc-a{color:#bff5e2}.trust-chip.tc-a i{color:var(--teal)}
.trust-chip.tc-b{color:#ffd9c4}.trust-chip.tc-b i{color:var(--orange)}
.trust-chip.tc-c{color:#dbe8ff}.trust-chip.tc-c i{color:#8fb4ff}
.trust-chip:nth-child(1){animation-delay:0s}
.trust-chip:nth-child(2){animation-delay:1.1s}
.trust-chip:nth-child(3){animation-delay:2.2s}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}

.hero-content{position:relative;z-index:2;flex:1;display:flex;flex-direction:column;justify-content:center}
.hero-brand{display:flex;align-items:center;gap:11px;margin-bottom:30px;animation:riseIn .6s .05s both}
.hero-brand img{height:38px;width:auto;object-fit:contain}
.hero-brand-name{font-size:14.5px;font-weight:700;line-height:1.2}
.hero-brand-sub{font-size:9.5px;letter-spacing:.1em;text-transform:uppercase;color:#cfe0ff;opacity:.85}

.hero-badge{
  display:inline-flex;align-items:center;gap:7px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
  padding:6px 13px;border-radius:99px;font-size:11.5px;font-weight:600;
  width:fit-content;margin-bottom:18px;backdrop-filter:blur(6px);
  animation:riseIn .6s .1s both;
}
.hero-badge .dot{width:7px;height:7px;border-radius:50%;background:var(--teal);box-shadow:0 0 0 0 rgba(0,196,140,.6);animation:pulse 2s infinite}
@keyframes pulse{
  0%{box-shadow:0 0 0 0 rgba(0,196,140,.55)}
  70%{box-shadow:0 0 0 7px rgba(0,196,140,0)}
  100%{box-shadow:0 0 0 0 rgba(0,196,140,0)}
}

.hero-title{
  font-family:'Playfair Display',serif;font-weight:700;
  font-size:clamp(26px,3.2vw,36px);line-height:1.18;margin-bottom:14px;
  animation:riseIn .6s .15s both;
}
.hero-title em{font-style:normal;color:var(--orange);position:relative}
.hero-lead{
  font-size:13.5px;line-height:1.7;color:#dce6f7;max-width:420px;margin-bottom:24px;
  animation:riseIn .6s .2s both;
}

.hero-features{list-style:none;display:flex;flex-direction:column;gap:11px;margin-bottom:26px;animation:riseIn .6s .25s both}
.hero-features li{
  display:flex;align-items:center;gap:11px;font-size:12.8px;color:#eef3fb;
  padding:6px 8px;border-radius:9px;margin-left:-8px;
  transition:background .2s,transform .2s;
}
.hero-features li:hover{background:rgba(255,255,255,.08);transform:translateX(4px)}
.hero-features li .fi-ico{
  width:26px;height:26px;border-radius:8px;flex-shrink:0;
  background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;
  color:var(--teal);font-size:14px;border:1px solid rgba(255,255,255,.18);
  transition:background .2s,color .2s,transform .2s;
}
.hero-features li:hover .fi-ico{background:var(--teal);color:#fff;transform:scale(1.1) rotate(-4deg)}

@keyframes riseIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

.hiw-strip{
  position:relative;z-index:2;display:flex;align-items:center;gap:10px;
  padding-top:20px;border-top:1px solid rgba(255,255,255,.16);
  animation:riseIn .6s .3s both;
}
.hiw-step{display:flex;align-items:center;gap:9px;padding:6px;border-radius:10px;transition:background .2s,transform .2s}
.hiw-step:hover{background:rgba(255,255,255,.07);transform:translateY(-2px)}
.hiw-num{
  width:24px;height:24px;border-radius:50%;flex-shrink:0;
  background:var(--orange);color:#fff;font-size:11.5px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}
.hiw-t{font-size:11.5px;font-weight:700;color:#fff;line-height:1.2}
.hiw-d{font-size:9.5px;color:#b9c8e2;line-height:1.3;max-width:120px}
.hiw-arrow{color:#5c729c;font-size:14px;flex-shrink:0}
@media(max-width:1100px){.hiw-d{display:none}.hiw-arrow{display:none}}

.hero-quote{
  position:relative;z-index:2;margin-top:20px;padding-left:14px;
  border-left:2px solid var(--orange);
  font-family:'Playfair Display',serif;font-style:italic;font-size:12.5px;
  color:#e7edf8;line-height:1.5;
}
.hero-quote span{display:block;margin-top:5px;font-family:'Poppins',sans-serif;font-style:normal;font-size:10.5px;color:#a9bcdc}

/* ═══════════ RIGHT: AUTH CARD (small, side-docked) ═══════════ */
.auth-col{
  width:420px;flex-shrink:0;
  display:flex;flex-direction:column;
  background:var(--white);position:relative;
}
.auth-topbar{
  padding:16px 30px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.auth-topbar-brand{font-size:12.5px;font-weight:600;color:var(--muted)}
.auth-topbar-contact{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px}
.auth-topbar-contact i{color:var(--orange);font-size:13px}
.auth-topbar-contact a{color:var(--muted);text-decoration:none}
.auth-topbar-contact a:hover{color:var(--orange)}

.auth-logo-wrap{position:relative;display:flex;align-items:center;justify-content:center}
.auth-logo-wrap::before{
  content:'';position:absolute;inset:-5px;border-radius:9px;
  background:radial-gradient(circle,rgba(232,84,26,.35) 0%,transparent 72%);
  animation:logoGlow 3s ease-in-out infinite;z-index:0;
}
@keyframes logoGlow{0%,100%{opacity:.5;transform:scale(.9)}50%{opacity:1;transform:scale(1.15)}}

.auth-body{
  flex:1;padding:26px 30px 18px;
  display:flex;flex-direction:column;overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent;
  animation:cardIn .55s .15s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}
.auth-body::-webkit-scrollbar{width:4px}
.auth-body::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}

.auth-tabs{display:flex;background:#f1f5f9;border-radius:10px;padding:4px;gap:4px;margin-bottom:24px}
.auth-tab{
  flex:1;padding:9px 8px;border:none;background:transparent;cursor:pointer;
  border-radius:7px;font-size:13px;font-weight:500;color:var(--muted);
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:all .2s;font-family:'Poppins',sans-serif;
}
.auth-tab i{font-size:15px}
.auth-tab.active{background:var(--white);color:var(--orange);font-weight:700;box-shadow:0 2px 8px rgba(26,31,46,.1)}
.auth-tab.active-teal{background:var(--white);color:var(--teal);font-weight:700;box-shadow:0 2px 8px rgba(26,31,46,.1)}

.panel{display:none;animation:fadeIn .3s ease}
.panel.on{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.auth-heading{font-family:'Playfair Display',serif;font-size:21px;font-weight:700;color:var(--text);margin-bottom:4px}
.auth-sub{font-size:12.5px;color:var(--muted);margin-bottom:20px;line-height:1.5}

.fg{margin-bottom:14px}
.fl{display:block;font-size:11px;font-weight:600;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.fl span{color:var(--orange)}
.iw{position:relative}
.iw i.ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--light);pointer-events:none;transition:color .18s}
.fi{
  width:100%;padding:11px 14px 11px 38px;
  border:1.5px solid var(--border);border-radius:9px;
  font-size:13px;color:var(--text);background:#f8fafc;
  outline:none;transition:all .18s;font-family:'Poppins',sans-serif;
}
.fi.noi{padding-left:14px}
.fi:hover{border-color:#cbd5e1}
.fi:focus{border-color:var(--orange);background:var(--white);box-shadow:0 0 0 3px var(--orange-soft)}
.fi.ft:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-soft)}
.iw:focus-within i.ico{color:var(--orange)}
select.fi{cursor:pointer}
textarea.fi{resize:vertical;min-height:68px;padding-top:11px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.pw-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--light);font-size:15px;transition:color .15s}
.pw-btn:hover{color:var(--muted)}

.type-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.type-card{
  border:2px solid var(--border);border-radius:12px;padding:15px 12px;cursor:pointer;
  background:#f8fafc;transition:all .2s;
  display:flex;flex-direction:column;align-items:center;gap:7px;text-align:center;
}
.type-card:hover{border-color:var(--orange);background:var(--orange-soft);transform:translateY(-2px)}
.type-card.sel{border-color:var(--orange);background:var(--orange-soft)}
.type-card i{font-size:26px;color:var(--light);transition:color .2s}
.type-card.sel i,.type-card:hover i{color:var(--orange)}
.type-card .tc-label{font-size:12.5px;font-weight:700;color:var(--text)}
.type-card .tc-desc{font-size:10px;color:var(--muted);line-height:1.35}

.section-label{
  font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;
  margin:16px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:6px;
}
.section-label i{font-size:13px;color:var(--orange)}

.check-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px}
.chk{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text);cursor:pointer;padding:4px 0}
.chk input{width:14px;height:14px;accent-color:var(--orange);cursor:pointer}

.al{border-radius:9px;padding:11px 14px;margin-bottom:16px;display:flex;gap:9px;font-size:12.5px;line-height:1.5;align-items:flex-start}
.al i{font-size:15px;flex-shrink:0;margin-top:1px}
.al-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}.al-err i{color:#ef4444}
.al-ok{background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36}.al-ok i{color:var(--teal)}
.errs{list-style:none;display:flex;flex-direction:column;gap:3px}
.errs li::before{content:'· ';font-weight:700}

.pw-strength{margin-top:6px}
.pw-bar{height:4px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:5px}
.pw-fill{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0}
.pw-rules{display:flex;gap:12px;flex-wrap:wrap}
.pw-rule{font-size:10.5px;color:var(--light);display:flex;align-items:center;gap:4px;transition:color .2s}
.pw-rule.ok{color:var(--teal)}.pw-rule i{font-size:11px}

.sbtn{
  width:100%;padding:12px 20px;border:none;border-radius:10px;
  font-size:14px;font-weight:700;cursor:pointer;position:relative;overflow:hidden;
  display:flex;align-items:center;justify-content:center;gap:9px;
  transition:all .2s;margin-top:8px;font-family:'Poppins',sans-serif;letter-spacing:.01em;
}
.sbtn::after{
  content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);
  transform:skewX(-20deg);transition:left .5s;
}
.sbtn:hover::after{left:130%}
.sbtn:hover{transform:translateY(-1px)}
.sbtn:active{transform:scale(.99)}
.s-orange{background:var(--orange);color:#fff;box-shadow:0 4px 14px rgba(232,84,26,.3)}
.s-orange:hover{background:var(--orange-h);box-shadow:0 6px 20px rgba(232,84,26,.4)}
.s-teal{background:var(--teal);color:#fff;box-shadow:0 4px 14px rgba(0,196,140,.25)}
.s-teal:hover{background:#00a077;box-shadow:0 6px 20px rgba(0,196,140,.35)}

.signup-scroll{
  max-height:calc(100vh - 260px);overflow-y:auto;padding-right:4px;
  scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent;
}
.signup-scroll::-webkit-scrollbar{width:4px}
.signup-scroll::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}

.swlink{text-align:center;margin-top:16px;font-size:12.5px;color:var(--muted)}
.swlink a{font-weight:700;text-decoration:none;color:var(--orange);transition:opacity .15s}
.swlink a:hover{opacity:.75}
.swlink a.t{color:var(--teal)}

.auth-foot{
  padding:14px 30px;border-top:1px solid var(--border);
  font-size:10.5px;color:var(--light);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;flex-shrink:0;
}
.auth-foot a{color:var(--orange);text-decoration:none}

.mobile-hero{display:none}
@media(max-width:980px){
  .hero-col{display:none}
  .frame{max-width:520px;height:auto;min-height:0;max-height:none}
  .auth-col{width:100%}
  .mobile-hero{
    display:block;background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,#3a2416 140%);
    color:#fff;padding:22px 24px 18px;position:relative;overflow:hidden;flex-shrink:0;
  }
  .mobile-hero .mh-inner{position:relative;z-index:2}
  .mobile-hero h1{font-family:'Playfair Display',serif;font-size:19px;line-height:1.25;margin:10px 0 6px}
  .mobile-hero h1 em{font-style:normal;color:var(--orange)}
  .mobile-hero p{font-size:11.5px;color:#dbe6f7;line-height:1.5;margin-bottom:12px}
  .mh-brand{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .mh-brand img{height:26px;width:auto;object-fit:contain}
  .mh-brand b{font-size:12px}
  .mh-chips{display:flex;gap:6px;flex-wrap:wrap}
  .mh-chips span{
    font-size:9.5px;font-weight:600;color:#fff;background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.22);padding:4px 9px;border-radius:99px;
    display:flex;align-items:center;gap:4px;
  }
  .mh-chips i{font-size:11px;color:var(--teal)}
}
@media(max-width:480px){
  .page{padding:0}
  .frame{border-radius:0;min-height:100vh}
  .auth-body{padding:22px 18px 18px}
  .auth-topbar{padding:12px 18px}
  .auth-foot{padding:12px 18px}
  .frow{grid-template-columns:1fr}
}
</style>
</head>
<body>

<header class="site-header">
  <a href="https://www.researchunlimitedsa.co.za" class="sh-brand" style="text-decoration:none">
    <img src="assets/img/logo-white.png" alt="Research Unlimited">
    <div class="sh-brand-text"><b>Research Unlimited</b><span>CSI Hub</span></div>
  </a>
  <nav class="sh-nav">
    <a href="https://www.researchunlimitedsa.co.za">Home</a>
    <a href="https://www.researchunlimitedsa.co.za/about-us">About</a>
    <a href="https://www.researchunlimitedsa.co.za/business-research">Business Research</a>
    <a href="https://www.researchunlimitedsa.co.za/blog">Blog</a>
    <a href="https://www.researchunlimitedsa.co.za/contact-us">Contact</a>
  </nav>
  <a href="#p-signup" class="sh-cta" onclick="go('signup')">Request Access</a>
</header>

<div class="page">
  <div class="frame">

    <!-- ═══ LEFT: HERO / MARKETING PANEL ═══ -->
    <div class="hero-col">
      <div class="fall-layer" id="fallHero"></div>
      <div class="hero-shapes">
        <div class="blob blob1"></div>
        <div class="blob blob2"></div>
        <div class="blob blob3"></div>
      </div>

      <div class="trust-chips">
        <div class="trust-chip tc-a"><i class="ti ti-shield-check"></i> Verified Network</div>
        <div class="trust-chip tc-b"><i class="ti ti-chart-dots"></i> Research-Backed</div>
        <div class="trust-chip tc-c"><i class="ti ti-lock"></i> POPIA-Conscious</div>
      </div>

      <div class="hero-content">
        <div class="hero-brand">
          <img src="assets/img/logo-white.png" alt="Research Unlimited">
          <div>
            <div class="hero-brand-name">Research Unlimited</div>
            <div class="hero-brand-sub">CSI Hub</div>
          </div>
        </div>

        <div class="hero-badge"><span class="dot"></span> Live CSI Matching Platform</div>

        <h1 class="hero-title">Bridging Business &amp; Education<br>Through <em>Data&#8209;Driven</em> CSI</h1>
        <p class="hero-lead">
          One trusted platform where companies and schools connect, verify each other,
          and manage Corporate Social Investment from first contact to measurable impact —
          powered by Research Unlimited's research-led approach.
        </p>

        <ul class="hero-features">
          <li><span class="fi-ico"><i class="ti ti-shield-check"></i></span> Companies &amp; schools are vetted before they ever meet</li>
          <li><span class="fi-ico"><i class="ti ti-chart-infographic"></i></span> Smart matching by focus area, budget &amp; province</li>
          <li><span class="fi-ico"><i class="ti ti-report-analytics"></i></span> Track CSI spend and impact in real time, in one place</li>
          <li><span class="fi-ico"><i class="ti ti-lock"></i></span> Your data handled securely, with POPIA in mind</li>
        </ul>
      </div>

      <div class="hiw-strip">
        <div class="hiw-step">
          <div class="hiw-num">1</div>
          <div><div class="hiw-t">Apply</div><div class="hiw-d">Tell us if you're a company or a school</div></div>
        </div>
        <div class="hiw-arrow"><i class="ti ti-arrow-right"></i></div>
        <div class="hiw-step">
          <div class="hiw-num">2</div>
          <div><div class="hiw-t">Get Verified</div><div class="hiw-d">Our team checks and approves your account</div></div>
        </div>
        <div class="hiw-arrow"><i class="ti ti-arrow-right"></i></div>
        <div class="hiw-step">
          <div class="hiw-num">3</div>
          <div><div class="hiw-t">Match &amp; Track</div><div class="hiw-d">Connect and monitor CSI impact in one place</div></div>
        </div>
      </div>

      <blockquote class="hero-quote">
        &ldquo;Research Without Limits — helping your business grow in the digital world.&rdquo;
        <span>— Research Unlimited</span>
      </blockquote>
    </div>


    <!-- ═══ RIGHT: AUTH CARD (compact, docked to the side) ═══ -->
    <div class="auth-col">
      <div class="fall-layer" id="fallAuth"></div>

      <div class="mobile-hero">
        <div class="mh-inner">
          <div class="mh-brand">
            <img src="assets/img/logo-white.png" alt="Research Unlimited">
            <b>Research Unlimited — CSI Hub</b>
          </div>
          <h1>Bridging Business &amp; Education Through <em>Data&#8209;Driven</em> CSI</h1>
          <p>One platform where companies and schools connect, verify, and manage Corporate Social Investment.</p>
          <div class="mh-chips">
            <span><i class="ti ti-shield-check"></i> Verified Network</span>
            <span><i class="ti ti-chart-dots"></i> Research-Backed</span>
            <span><i class="ti ti-lock"></i> POPIA-Conscious</span>
          </div>
        </div>
      </div>

      <div style="height:4px;background:linear-gradient(90deg,var(--orange),var(--orange-soft),var(--teal));flex-shrink:0"></div>
      <div class="auth-topbar">
        <div style="display:flex;align-items:center;gap:9px">
          <div class="auth-logo-wrap">
            <img src="assets/img/logo.png" alt="RU" style="height:28px;width:auto;object-fit:contain;border-radius:6px;position:relative;z-index:1">
          </div>
          <div style="display:flex;flex-direction:column;line-height:1.15">
            <span style="font-size:12.5px;font-weight:700;color:var(--text)">Research Unlimited</span>
            <span style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">CSI Hub</span>
          </div>
        </div>
        <span class="auth-topbar-contact">
          <i class="ti ti-phone"></i>
          <a href="tel:+27795343798">079 534 3798</a>
        </span>
      </div>

      <div class="auth-body">
        <div style="margin-bottom:18px">
          <h2 class="auth-heading">Welcome to CSI Hub</h2>
          <p class="auth-sub">Sign in to manage your CSI journey, or request access to join the platform.</p>
        </div>

        <?php if ($success): ?>
        <div class="al al-ok"><i class="ti ti-circle-check"></i><div><?= htmlspecialchars($success) ?></div></div>
        <?php endif; ?>

        <div class="auth-tabs">
          <button class="auth-tab <?= $active_tab==='login'?'active':'' ?>" id="tb-login" onclick="go('login')">
            <i class="ti ti-login"></i> Sign In
          </button>
          <button class="auth-tab <?= $active_tab==='signup'?'active-teal':'' ?>" id="tb-signup" onclick="go('signup')">
            <i class="ti ti-user-plus"></i> Request Access
          </button>
        </div>

        <!-- ── SIGN IN ── -->
        <div class="panel <?= $active_tab==='login'?'on':'' ?>" id="p-login">
          <?php if ($error): ?>
          <div class="al al-err"><i class="ti ti-alert-circle"></i><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="form" value="login">
            <div class="fg">
              <label class="fl">Username</label>
              <div class="iw"><i class="ico ti ti-user"></i>
                <input class="fi" type="text" name="username" placeholder="Enter your username"
                       autocomplete="username" required
                       value="<?= $active_tab==='login'?htmlspecialchars($_POST['username']??''):'' ?>">
              </div>
            </div>
            <div class="fg">
              <label class="fl">Password</label>
              <div class="iw"><i class="ico ti ti-lock"></i>
                <input class="fi" type="password" id="lp" name="password"
                       placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="pw-btn" onclick="tpw('lp','le')">
                  <i class="ti ti-eye" id="le"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="sbtn s-orange">
              <i class="ti ti-login"></i> Sign In to CSI Hub
            </button>
          </form>

          <div class="swlink">
            New here? <a href="#" class="t" onclick="go('signup')">Request access →</a>
          </div>
        </div>

        <!-- ── REQUEST ACCESS ── -->
        <div class="panel <?= $active_tab==='signup'?'on':'' ?>" id="p-signup">
          <?php if (!empty($signup_errors)): ?>
          <div class="al al-err">
            <i class="ti ti-alert-circle"></i>
            <ul class="errs"><?php foreach($signup_errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>

          <form method="POST">
            <input type="hidden" name="form" value="signup">
            <input type="hidden" name="user_type" id="utype-val" value="<?= htmlspecialchars($_POST['user_type']??'') ?>">

            <!-- Step 1: Type -->
            <div class="section-label"><i class="ti ti-user-check"></i> I am a…</div>
            <div class="type-cards">
              <div class="type-card <?= ($_POST['user_type']??'')==='company'?'sel':'' ?>"
                   id="tc-company" onclick="selectType('company')">
                <i class="ti ti-building"></i>
                <span class="tc-label">Company / Funder</span>
                <span class="tc-desc">I want to invest in schools through CSI</span>
              </div>
              <div class="type-card <?= ($_POST['user_type']??'')==='school'?'sel':'' ?>"
                   id="tc-school" onclick="selectType('school')">
                <i class="ti ti-school"></i>
                <span class="tc-label">School</span>
                <span class="tc-desc">My school needs CSI support and funding</span>
              </div>
            </div>

            <!-- Everything below only appears once a type is chosen, in a logical order:
                 1) Organisation details  2) Contact details  3) Login credentials  4) Submit -->
            <div id="fields-wrap" style="display:<?= !empty($_POST['user_type'])?'block':'none' ?>">

              <!-- ── STEP 2a: COMPANY FIELDS ── -->
              <div id="company-fields" style="display:<?= ($_POST['user_type']??'')==='company'?'block':'none' ?>">
                <div class="section-label"><i class="ti ti-building"></i> Company Details</div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">Company Name <span>*</span></label>
                    <div class="iw"><i class="ico ti ti-building"></i>
                      <input class="fi ft" type="text" name="company_name" placeholder="Registered name"
                             value="<?= htmlspecialchars($_POST['company_name']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">Reg. Number</label>
                    <div class="iw"><i class="ico ti ti-id"></i>
                      <input class="fi ft" type="text" name="reg_number" placeholder="2021/000000/07"
                             value="<?= htmlspecialchars($_POST['reg_number']??'') ?>">
                    </div>
                  </div>
                </div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">Contact Person <span>*</span></label>
                    <div class="iw"><i class="ico ti ti-user"></i>
                      <input class="fi ft" type="text" name="contact_person" placeholder="Full name"
                             value="<?= htmlspecialchars($_POST['contact_person']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">ID Number</label>
                    <input class="fi ft noi" type="text" name="id_number" placeholder="SA ID number"
                           value="<?= htmlspecialchars($_POST['id_number']??'') ?>" maxlength="13">
                  </div>
                </div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">CSI Budget (R)</label>
                    <div class="iw"><i class="ico ti ti-currency-rand"></i>
                      <input class="fi ft" type="text" name="csi_budget" placeholder="e.g. 500 000"
                             value="<?= htmlspecialchars($_POST['csi_budget']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">Programme Preference</label>
                    <select class="fi ft noi" name="programme_pref">
                      <option value="">Select…</option>
                      <option <?= ($_POST['programme_pref']??'')==='We Run It'?'selected':'' ?>>We Run It</option>
                      <option <?= ($_POST['programme_pref']??'')==='Bronze Package'?'selected':'' ?>>Bronze Package</option>
                      <option <?= ($_POST['programme_pref']??'')==='Silver Package'?'selected':'' ?>>Silver Package</option>
                      <option <?= ($_POST['programme_pref']??'')==='Gold Package'?'selected':'' ?>>Gold Package</option>
                      <option <?= ($_POST['programme_pref']??'')==='Not sure yet'?'selected':'' ?>>Not sure yet</option>
                    </select>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Focus Areas</label>
                  <div class="check-grid">
                    <?php foreach($focus_list as $fa): ?>
                    <label class="chk">
                      <input type="checkbox" name="focus_areas[]" value="<?= $fa ?>"
                             <?= in_array($fa, (array)($_POST['focus_areas']??[]))?'checked':'' ?>>
                      <?= $fa ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Target Provinces</label>
                  <div class="check-grid">
                    <?php foreach($provinces_list as $pv): ?>
                    <label class="chk">
                      <input type="checkbox" name="provinces[]" value="<?= $pv ?>"
                             <?= in_array($pv, (array)($_POST['provinces']??[]))?'checked':'' ?>>
                      <?= $pv ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <!-- ── STEP 2b: SCHOOL FIELDS ── -->
              <div id="school-fields" style="display:<?= ($_POST['user_type']??'')==='school'?'block':'none' ?>">
                <div class="section-label"><i class="ti ti-school"></i> School Details</div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">School Name <span>*</span></label>
                    <div class="iw"><i class="ico ti ti-school"></i>
                      <input class="fi ft" type="text" name="school_name" placeholder="Full school name"
                             value="<?= htmlspecialchars($_POST['school_name']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">Province <span>*</span></label>
                    <select class="fi ft noi" name="province">
                      <option value="">Select…</option>
                      <?php foreach($provinces_list as $pv): ?>
                      <option <?= ($_POST['province']??'')===$pv?'selected':'' ?>><?= $pv ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">District / Municipality</label>
                  <div class="iw"><i class="ico ti ti-map-pin"></i>
                    <input class="fi ft" type="text" name="district" placeholder="e.g. City of Johannesburg"
                           value="<?= htmlspecialchars($_POST['district']??'') ?>">
                  </div>
                </div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">Principal Name <span>*</span></label>
                    <div class="iw"><i class="ico ti ti-user"></i>
                      <input class="fi ft" type="text" name="principal_name" placeholder="Full name"
                             value="<?= htmlspecialchars($_POST['principal_name']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">ID Number</label>
                    <input class="fi ft noi" type="text" name="id_number" placeholder="SA ID number"
                           value="<?= htmlspecialchars($_POST['id_number']??'') ?>" maxlength="13">
                  </div>
                </div>
                <div class="frow">
                  <div class="fg">
                    <label class="fl">No. of Learners</label>
                    <div class="iw"><i class="ico ti ti-users"></i>
                      <input class="fi ft" type="number" name="learners" placeholder="e.g. 800" min="0"
                             value="<?= htmlspecialchars($_POST['learners']??'') ?>">
                    </div>
                  </div>
                  <div class="fg">
                    <label class="fl">No. of Educators</label>
                    <div class="iw"><i class="ico ti ti-user-check"></i>
                      <input class="fi ft" type="number" name="educators" placeholder="e.g. 32" min="0"
                             value="<?= htmlspecialchars($_POST['educators']??'') ?>">
                    </div>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Funding Amount Needed (R)</label>
                  <div class="iw"><i class="ico ti ti-currency-rand"></i>
                    <input class="fi ft" type="text" name="funding_needed" placeholder="e.g. 250 000"
                           value="<?= htmlspecialchars($_POST['funding_needed']??'') ?>">
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Challenges / What We Need Help With</label>
                  <textarea class="fi ft noi" name="challenges" rows="3"
                    placeholder="Describe your school's main challenges and what support you need…"><?= htmlspecialchars($_POST['challenges']??'') ?></textarea>
                </div>
              </div>

              <!-- ── STEP 3: CONTACT DETAILS (applies to both types) ── -->
              <div class="section-label"><i class="ti ti-mail"></i> Contact Details</div>
              <div class="frow">
                <div class="fg">
                  <label class="fl">Email Address <span>*</span></label>
                  <div class="iw"><i class="ico ti ti-mail"></i>
                    <input class="fi" type="email" name="email" placeholder="you@company.co.za"
                           value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
                  </div>
                </div>
                <div class="fg">
                  <label class="fl">Contact Number <span>*</span></label>
                  <div class="iw"><i class="ico ti ti-phone"></i>
                    <input class="fi" type="tel" name="phone" placeholder="079 534 3798"
                           value="<?= htmlspecialchars($_POST['phone']??'') ?>" required>
                  </div>
                </div>
              </div>

              <!-- ── STEP 4: LOGIN CREDENTIALS ── -->
              <div class="section-label"><i class="ti ti-lock"></i> Create Your Login</div>
              <div class="fg">
                <label class="fl">Choose Username <span>*</span></label>
                <div class="iw"><i class="ico ti ti-at"></i>
                  <input class="fi" type="text" name="username" placeholder="e.g. jsmith_company"
                         autocomplete="username"
                         value="<?= htmlspecialchars($_POST['username']??'') ?>">
                </div>
              </div>
              <div class="fg">
                <label class="fl">Password <span>*</span></label>
                <div class="iw"><i class="ico ti ti-lock"></i>
                  <input class="fi" type="password" id="sp" name="password"
                         placeholder="Min 8 chars, 1 uppercase, 1 number"
                         oninput="chkStr(this.value)">
                  <button type="button" class="pw-btn" onclick="tpw('sp','se')">
                    <i class="ti ti-eye" id="se"></i>
                  </button>
                </div>
                <div class="pw-strength">
                  <div class="pw-bar"><div class="pw-fill" id="pf"></div></div>
                  <div class="pw-rules">
                    <span class="pw-rule" id="r-len"><i class="ti ti-circle"></i> 8+ chars</span>
                    <span class="pw-rule" id="r-up"><i class="ti ti-circle"></i> Uppercase</span>
                    <span class="pw-rule" id="r-num"><i class="ti ti-circle"></i> Number</span>
                  </div>
                </div>
              </div>
              <div class="fg">
                <label class="fl">Confirm Password <span>*</span></label>
                <div class="iw"><i class="ico ti ti-lock-check"></i>
                  <input class="fi" type="password" name="confirm_password" placeholder="Repeat your password">
                </div>
              </div>

              <button type="submit" class="sbtn s-teal">
                <i class="ti ti-send"></i> Submit Access Request
              </button>

            </div><!-- /fields-wrap -->
          </form>

          <div class="swlink">
            Already have access? <a href="#" onclick="go('login')">Sign in →</a>
          </div>
        </div>

      </div><!-- /auth-body -->

      <div class="auth-foot">
        <span>&copy; <?= date('Y') ?> Research Unlimited</span>
        <span>
          <a href="mailto:helpdesk@researchunlimitedsa.co.za">helpdesk@researchunlimitedsa.co.za</a>
          &nbsp;·&nbsp;
          <a href="tel:+27795343798">079 534 3798</a>
        </span>
      </div>

    </div><!-- /auth-col -->

  </div><!-- /frame -->
</div><!-- /page -->

<script>
/* Falling CSI-themed icon particles */
(function(){
  const icons = ['ti-books','ti-heart-handshake','ti-coin','ti-trending-up','ti-award','ti-users','ti-gift','ti-school','ti-bulb'];

  function spawn(containerId, count, opts){
    const host = document.getElementById(containerId);
    if (!host) return;
    for (let i=0;i<count;i++){
      const el = document.createElement('i');
      const icon = icons[Math.floor(Math.random()*icons.length)];
      el.className = 'ti ' + icon + ' fall-icon' + (opts.light ? ' on-light' : '');
      const size = (Math.random()*(opts.maxSize-opts.minSize)+opts.minSize).toFixed(0);
      const left = (Math.random()*94+2).toFixed(1);
      const duration = (Math.random()*(opts.maxDur-opts.minDur)+opts.minDur).toFixed(1);
      const delay = (Math.random()*opts.maxDur*-1).toFixed(1);
      const drift = (Math.random()*80-40).toFixed(0)+'px';
      const spin = (Math.random()*180-90).toFixed(0)+'deg';
      el.style.left = left+'%';
      el.style.fontSize = size+'px';
      el.style.animationDuration = duration+'s';
      el.style.animationDelay = delay+'s';
      el.style.setProperty('--drift', drift);
      el.style.setProperty('--spin', spin);
      host.appendChild(el);
    }
  }

  spawn('fallHero', 16, {minSize:16, maxSize:30, minDur:14, maxDur:26, light:false});
  spawn('fallAuth', 7, {minSize:20, maxSize:34, minDur:16, maxDur:28, light:true});
})();

function go(tab) {
  ['login','signup'].forEach(t => {
    document.getElementById('p-'+t).classList.toggle('on', t===tab);
    const btn = document.getElementById('tb-'+t);
    btn.className = 'auth-tab' + (t===tab ? (t==='login' ? ' active' : ' active-teal') : '');
  });
}
go('<?= $active_tab ?>');

function selectType(type) {
  document.getElementById('utype-val').value = type;
  ['company','school'].forEach(t => {
    document.getElementById('tc-'+t).classList.toggle('sel', t===type);
    document.getElementById(t+'-fields').style.display = t===type ? 'block' : 'none';
  });
  document.getElementById('fields-wrap').style.display = 'block';
  document.getElementById('fields-wrap').scrollIntoView({behavior:'smooth', block:'nearest'});
}

<?php if (!empty($_POST['user_type'])): ?>
selectType('<?= htmlspecialchars($_POST['user_type']) ?>');
<?php endif; ?>

function tpw(id, eid) {
  const i = document.getElementById(id), e = document.getElementById(eid);
  i.type = i.type === 'password' ? 'text' : 'password';
  e.className = i.type === 'text' ? 'ti ti-eye-off' : 'ti ti-eye';
}

function chkStr(v) {
  const checks = {len: v.length>=8, up: /[A-Z]/.test(v), num: /[0-9]/.test(v)};
  const score = Object.values(checks).filter(Boolean).length;
  const fill = document.getElementById('pf');
  fill.style.width = (score * 33.33) + '%';
  fill.style.background = ['#ef4444','#f59e0b','#34d399','#00c48c'][score];
  Object.entries(checks).forEach(([k,ok]) => {
    const el = document.getElementById('r-'+k); if(!el) return;
    el.classList.toggle('ok', ok);
    el.querySelector('i').className = ok ? 'ti ti-circle-check' : 'ti ti-circle';
  });
}

</script>
<footer class="site-footer">
  <div class="sf-copy">&copy; <?= date('Y') ?> Research Unlimited · Sasolburg, South Africa</div>
  <div class="sf-links">
    <a href="https://www.researchunlimitedsa.co.za/privacy-policy">Privacy Policy</a>
    <a href="https://www.researchunlimitedsa.co.za/about-us">About Us</a>
    <a href="mailto:info@researchunlimitedsa.co.za">info@researchunlimitedsa.co.za</a>
    <a href="tel:+27680245514">+27 68 024 5514</a>
  </div>
  <div class="sf-social">
    <a href="https://www.facebook.com/share/1BKGPCaa57/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" title="Research Unlimited on Facebook" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
    <a href="http://www.youtube.com/@researchunlimitedsa" target="_blank" rel="noopener noreferrer" title="Research Unlimited on YouTube" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
    <a href="https://www.instagram.com/invites/contact/?igsh=18pvz6leis6mo&utm_content=kz2jbhc" target="_blank" rel="noopener noreferrer" title="Research Unlimited on Instagram" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
    <a href="https://www.linkedin.com/company/research-unlimited-pty-ltd/" target="_blank" rel="noopener noreferrer" title="Research Unlimited on LinkedIn" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
    <a href="https://www.tiktok.com/@research.unlimite?_r=1&_t=ZS-96ZqIhdD1G4" target="_blank" rel="noopener noreferrer" title="Research Unlimited on TikTok" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
  </div>
</footer>

</body>
</html>