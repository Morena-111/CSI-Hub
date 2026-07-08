<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
if (!function_exists('send_app_email')) require_once __DIR__ . '/mailer.php';
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

$otp_file = $data_dir . '/reset_otps.json';
$otps     = file_exists($otp_file)
    ? json_decode(file_get_contents($otp_file), true) ?? [] : [];

$error = ''; $success = ''; $signup_errors = []; $forgot_msg = ''; $reset_error = '';
$active_tab   = $_GET['tab'] ?? 'login';
$awaiting_otp = ''; // holds username once an OTP has been sent, to show the verify step

/** Find which account bucket a username belongs to: 'root' | 'admin' | 'pending' | null */
function locate_account(string $u, array $admin_accounts, array $pending): ?string {
    if ($u === 'admin') return 'root';
    $admin_accounts_lc = array_change_key_case($admin_accounts, CASE_LOWER);
    if (isset($admin_accounts_lc[$u])) return 'admin';
    if (isset($pending[$u]) && ($pending[$u]['approved'] ?? false)) return 'pending';
    return null;
}
/** Normalize a phone number to digits only, for loose comparison */
function norm_phone(string $p): string { return preg_replace('/[^0-9]/', '', $p); }

// ── FORGOT PASSWORD — step 1: verify identity, send OTP ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'forgot_request') {
    $fu       = strtolower(trim($_POST['forgot_username'] ?? ''));
    $fcontact = trim($_POST['forgot_contact'] ?? '');
    $bucket   = locate_account($fu, $admin_accounts, $pending);
    $matched  = false;
    $send_to  = '';

    if ($bucket === 'pending') {
        $on_file_email = strtolower($pending[$fu]['email'] ?? '');
        $on_file_phone = norm_phone($pending[$fu]['phone'] ?? '');
        $entered_email = strtolower($fcontact);
        $entered_phone = norm_phone($fcontact);
        if (($on_file_email !== '' && $entered_email === $on_file_email)
            || ($on_file_phone !== '' && $entered_phone !== '' && $entered_phone === $on_file_phone)) {
            $matched = true;
            $send_to = $pending[$fu]['email'] ?: SITE_EMAIL;
        }
    } elseif ($bucket === 'root' || $bucket === 'admin') {
        // Admin accounts have no email/phone on file — recovery goes to the org inbox.
        $matched = true;
        $send_to = SITE_EMAIL;
    }

    if ($matched) {
        $otp = (string) random_int(100000, 999999);
        $otps[$fu] = ['otp' => $otp, 'expires' => time() + 600, 'tries' => 0];
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        file_put_contents($otp_file, json_encode($otps, JSON_PRETTY_PRINT));

        $subject = 'CSI Hub — Your Password Reset Code';
        $body    = "Hi,\n\nYour one-time code to reset your CSI Hub password is:\n\n{$otp}\n\n"
                 . "This code expires in 10 minutes. If you didn't request this, you can ignore this email.\n\nResearch Unlimited";
        send_app_email($send_to, $subject, $body);
    }

    // Same response either way, so no one can tell which usernames/contacts are valid
    $forgot_msg   = "If that username and contact detail match our records, we've emailed a 6-digit code to the address on file.";
    $active_tab   = 'forgot';
    $awaiting_otp = $fu;
}

// ── FORGOT PASSWORD — step 2: verify OTP + set new password ────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'reset_password') {
    $ru    = strtolower(trim($_POST['reset_username'] ?? ''));
    $rotp  = trim($_POST['otp'] ?? '');
    $rpass = $_POST['new_password'] ?? '';
    $rconf = $_POST['confirm_new_password'] ?? '';
    $active_tab = 'forgot'; $awaiting_otp = $ru;

    $rec = $otps[$ru] ?? null;
    if (!$rec || $rec['expires'] < time()) {
        $reset_error = 'This code has expired. Please request a new one.';
        $awaiting_otp = '';
    } elseif (($rec['tries'] ?? 0) >= 5) {
        $reset_error = 'Too many incorrect attempts. Please request a new code.';
        $awaiting_otp = '';
        unset($otps[$ru]); file_put_contents($otp_file, json_encode($otps, JSON_PRETTY_PRINT));
    } elseif ($rotp !== $rec['otp']) {
        $otps[$ru]['tries'] = ($rec['tries'] ?? 0) + 1;
        file_put_contents($otp_file, json_encode($otps, JSON_PRETTY_PRINT));
        $reset_error = 'That code is incorrect. Please check your email and try again.';
    } elseif (strlen($rpass) < 8 || !preg_match('/[A-Z]/', $rpass) || !preg_match('/[0-9]/', $rpass)) {
        $reset_error = 'Password must be at least 8 characters, with an uppercase letter and a number.';
    } elseif ($rpass !== $rconf) {
        $reset_error = 'Passwords do not match.';
    } else {
        $bucket = locate_account($ru, $admin_accounts, $pending);
        if ($bucket === 'root') {
            file_put_contents($_root_pw_file, json_encode(['password' => $rpass], JSON_PRETTY_PRINT));
        } elseif ($bucket === 'admin') {
            $saved = file_exists($admins_file) ? (json_decode(file_get_contents($admins_file), true) ?? []) : [];
            if (isset($saved[$ru])) { $saved[$ru]['password'] = $rpass; file_put_contents($admins_file, json_encode($saved, JSON_PRETTY_PRINT)); }
        } elseif ($bucket === 'pending') {
            $pending[$ru]['password'] = $rpass;
            file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
        }
        unset($otps[$ru]);
        file_put_contents($otp_file, json_encode($otps, JSON_PRETTY_PRINT));
        $success = 'Your password has been reset. You can now sign in with your new password.';
        $active_tab = 'login'; $awaiting_otp = '';
    }
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $u_typed = trim($_POST['username'] ?? '');
    $u = strtolower($u_typed); // accounts are stored/keyed lowercase — login matches regardless of case
    $p = $_POST['password'] ?? '';
    $admin_accounts_lc = array_change_key_case($admin_accounts, CASE_LOWER);
    if (isset($admin_accounts_lc[$u]) && $admin_accounts_lc[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role'] = 'admin'; $_SESSION['name'] = $admin_accounts_lc[$u]['name'];
        $_SESSION['username'] = $u; $_SESSION['login_time'] = time();
        redirect('dashboard.php');
    }
    $approved = array_filter($pending, fn($v) => ($v['approved'] ?? false));
    if (isset($approved[$u]) && $approved[$u]['password'] === $p) {
        session_regenerate_id(true);
        $_SESSION['role']       = 'user';
        $_SESSION['name']       = $approved[$u]['name'];
        $_SESSION['username']   = $approved[$u]['username_display'] ?? $u;
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
    $suser_typed = trim($_POST['username'] ?? '');
    $suser  = strtolower($suser_typed);
    $spass  = $_POST['password'] ?? '';
    $sconf  = $_POST['confirm_password'] ?? '';
    $semail = trim($_POST['email'] ?? '');
    $sphone = trim($_POST['phone'] ?? '');

    $contact = ''; $principal = '';
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
    $admin_accounts_lc = array_change_key_case($admin_accounts, CASE_LOWER);
    if (isset($admin_accounts_lc[$suser]) || isset($pending[$suser])) $signup_errors[] = 'This username is already taken — please choose another.';
    if (!empty($suser)) {
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
            'username_display' => $suser_typed,
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
<link rel="icon" href="assets/img/logo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --orange:#E8541A;--orange-h:#c94514;--orange-soft:#fdf0ea;
  --teal:#00c48c;--teal-soft:#e6faf5;--navy:#0d1e3d;
  --bg:#f2f4f8;--white:#fff;--border:#e5e9f0;
  --text:#1a202c;--muted:#64748b;--light:#94a3b8;
}
html,body{height:100%;font-family:'Poppins',sans-serif;color:var(--text)}
body{background:var(--bg);min-height:100vh;display:flex;flex-direction:column}

/* ═══════════ TOP SITE HEADER (light, matches dashboard) ═══════════ */
.site-header{
  background:var(--white);border-bottom:1px solid var(--border);
  padding:13px 34px;display:flex;align-items:center;justify-content:space-between;
  flex-shrink:0;position:relative;z-index:10;
}
.sh-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.sh-brand img{height:32px;width:auto;object-fit:contain;transition:transform .35s ease}
.sh-brand:hover img{transform:rotate(-6deg) scale(1.08)}
.sh-nav{display:flex;gap:26px}
.sh-nav a{color:var(--text);text-decoration:none;font-size:13.5px;font-weight:500;position:relative;padding:4px 0;transition:color .15s}
.sh-nav a::after{content:'';position:absolute;left:0;bottom:-2px;width:0;height:2px;background:var(--orange);transition:width .2s}
.sh-nav a:hover{color:var(--orange)}
.sh-nav a:hover::after{width:100%}
.sh-cta{
  background:var(--orange);color:#fff;padding:9px 20px;border-radius:8px;
  text-decoration:none;font-size:13px;font-weight:700;transition:background .15s,transform .15s;
  animation:ctaBreathe 3s ease-in-out infinite;
}
.sh-cta:hover{background:var(--orange-h);transform:translateY(-1px) scale(1.03);animation-play-state:paused;box-shadow:0 4px 14px rgba(232,84,26,.4)}
@keyframes ctaBreathe{
  0%,100%{box-shadow:0 0 0 0 rgba(232,84,26,.35)}
  50%{box-shadow:0 0 0 6px rgba(232,84,26,0)}
}
@media(max-width:900px){.sh-nav{display:none}}
@media(max-width:600px){.site-header{padding:12px 18px}.sh-cta{padding:8px 14px;font-size:12px}}

/* ═══════════ PAGE FRAME ═══════════ */
.page{flex:1;display:flex;align-items:stretch;justify-content:center;padding:34px}
.frame{
  width:100%;max-width:1080px;display:flex;background:var(--white);
  border-radius:18px;overflow:hidden;box-shadow:0 12px 40px rgba(20,30,60,.08);
  border:1px solid var(--border);
  animation:frameIn .5s cubic-bezier(.22,1,.36,1) both;
}
@keyframes frameIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

/* ═══════════ LEFT: INFO PANEL (light, minimal) ═══════════ */
.info-col{
  flex:1.05;background:linear-gradient(165deg,#fbfbfd 0%,#f6f8fc 100%);
  padding:44px 42px;display:flex;flex-direction:column;justify-content:center;
  border-right:1px solid var(--border);position:relative;overflow:hidden;
}

/* Animated network background — nodes drifting + pulsing connections,
   representing companies & schools linking up on CSI Hub */
.net-bg{position:absolute;inset:0;z-index:0;pointer-events:none}
.net-bg svg{width:100%;height:100%}
.net-line{stroke:var(--orange);stroke-width:1;opacity:.14;stroke-dasharray:4 5;animation:netFlow 6s linear infinite}
.net-line.t{stroke:var(--teal)}
.net-line.n2{animation-duration:8s;animation-delay:-2s}
.net-line.n3{animation-duration:7s;animation-delay:-4s;stroke:var(--teal)}
.net-line.n4{animation-duration:9s;animation-delay:-1s}
@keyframes netFlow{to{stroke-dashoffset:-90}}
.net-node{fill:var(--orange);opacity:.22;animation:netDrift 9s ease-in-out infinite}
.net-node.t{fill:var(--teal)}
.net-node.n2{animation-duration:11s;animation-delay:-3s}
.net-node.n3{animation-duration:8s;animation-delay:-5s}
.net-node.n4{animation-duration:10s;animation-delay:-1.5s}
.net-node.n5{animation-duration:12s;animation-delay:-6s}
@keyframes netDrift{
  0%,100%{transform:translate(0,0)}
  50%{transform:translate(10px,-14px)}
}

.info-logo{margin-bottom:22px;animation:riseIn .5s .05s both;position:relative;z-index:1}
.info-logo img{height:46px;width:auto;object-fit:contain;transition:transform .35s ease;cursor:default;animation:logoBob 4s ease-in-out infinite}
.info-logo img:hover{transform:rotate(-6deg) scale(1.1)}
@keyframes logoBob{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-3px) rotate(-2deg)}}

.info-badge{
  position:relative;z-index:1;
  display:inline-flex;align-items:center;gap:7px;background:var(--teal-soft);
  color:#046b4d;border:1px solid #bdeedc;padding:5px 12px;border-radius:99px;
  font-size:11px;font-weight:700;width:fit-content;margin-bottom:16px;
  animation:riseIn .5s .1s both;
}
.info-badge .dot{width:6px;height:6px;border-radius:50%;background:var(--teal);animation:pulse 2s infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(0,196,140,.5)}70%{box-shadow:0 0 0 6px rgba(0,196,140,0)}100%{box-shadow:0 0 0 0 rgba(0,196,140,0)}}

.info-title{
  position:relative;z-index:1;
  font-family:'Playfair Display',serif;font-weight:700;color:var(--navy);
  font-size:clamp(24px,2.6vw,32px);line-height:1.2;margin-bottom:12px;
  animation:riseIn .5s .15s both;
}
.info-title em{
  font-style:normal;background:linear-gradient(90deg,var(--orange),#ff8a50,var(--orange));
  background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;
  animation:shine 3.5s linear infinite;
}
@keyframes shine{to{background-position:-200% center}}
.info-lead{
  position:relative;z-index:1;
  font-size:13px;line-height:1.65;color:var(--muted);max-width:400px;margin-bottom:26px;
  animation:riseIn .5s .2s both;
}
@keyframes riseIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

.info-cards{position:relative;z-index:1;display:flex;flex-direction:column;gap:10px;animation:riseIn .5s .25s both;perspective:800px}
.info-card{
  position:relative;display:flex;align-items:center;gap:12px;background:var(--white);
  border:1px solid var(--border);border-left:3px solid var(--orange);
  border-radius:10px;padding:11px 14px;transition:transform .2s,box-shadow .2s,border-color .2s;
  overflow:hidden;
}
.info-card::before{
  content:'';position:absolute;inset:0;border-radius:10px;padding:1px;
  background:linear-gradient(120deg,transparent 0%,rgba(232,84,26,.5) 50%,transparent 100%);
  background-size:250% 100%;background-position:150% 0;
  -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
  mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
  -webkit-mask-composite:xor;mask-composite:exclude;
  opacity:0;transition:opacity .2s;pointer-events:none;
}
.info-card:hover::before{opacity:1;animation:borderSweep 1.1s ease forwards}
@keyframes borderSweep{from{background-position:150% 0}to{background-position:-50% 0}}
.info-card:nth-child(2){border-left-color:var(--teal)}
.info-card:nth-child(3){border-left-color:var(--navy)}
.info-card:hover{transform:translateX(4px) scale(1.015);box-shadow:0 10px 24px rgba(20,30,60,.1)}
.ic-flip{
  width:34px;height:34px;flex-shrink:0;position:relative;transform-style:preserve-3d;
  transition:transform .55s cubic-bezier(.4,.2,.2,1);
}
.info-card:hover .ic-flip{transform:rotateY(180deg)}
.ic-face{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  border-radius:9px;backface-visibility:hidden;background:var(--orange-soft);
}
.ic-face i{font-size:17px;color:var(--orange)}
.ic-back{transform:rotateY(180deg);background:var(--orange)}
.ic-back i{color:#fff;font-size:16px}
.info-card:nth-child(2) .ic-face{background:var(--teal-soft)}
.info-card:nth-child(2) .ic-face i{color:var(--teal)}
.info-card:nth-child(2) .ic-back{background:var(--teal)}
.info-card:nth-child(3) .ic-face{background:#e8ecf3}
.info-card:nth-child(3) .ic-face i{color:var(--navy)}
.info-card:nth-child(3) .ic-back{background:var(--navy)}
.info-card .ic-t{font-size:12.5px;font-weight:700;color:var(--text)}
.info-card .ic-d{font-size:10.5px;color:var(--muted)}

.info-foot{
  position:relative;z-index:1;
  margin-top:26px;font-size:10.5px;color:var(--light);
  animation:riseIn .5s .3s both;
}

/* ═══════════ RIGHT: AUTH CARD ═══════════ */
.auth-col{width:400px;flex-shrink:0;display:flex;flex-direction:column;background:var(--white)}
.auth-topbar{
  padding:15px 28px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
}
.auth-topbar img{height:34px;width:auto;object-fit:contain;transition:transform .4s cubic-bezier(.4,.2,.2,1);cursor:default}
.auth-topbar img:hover{transform:rotateY(360deg)}
.auth-topbar-contact{font-size:11px;color:var(--muted);display:flex;align-items:center;gap:5px}
.auth-topbar-contact i{color:var(--orange);font-size:13px;transition:transform .35s ease}
.auth-topbar-contact a{color:var(--muted);text-decoration:none;transition:color .15s}
.auth-topbar-contact:hover i{transform:rotate(-18deg) scale(1.15)}
.auth-topbar-contact a:hover{color:var(--orange)}

.auth-body{
  flex:1;padding:24px 28px 16px;display:flex;flex-direction:column;overflow-y:auto;
  scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent;
  animation:cardIn .5s .1s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.auth-body::-webkit-scrollbar{width:4px}
.auth-body::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}

.auth-tabs{display:flex;background:#f1f5f9;border-radius:10px;padding:4px;gap:4px;margin-bottom:22px}
.auth-tab{
  flex:1;padding:9px 8px;border:none;background:transparent;cursor:pointer;
  border-radius:7px;font-size:13px;font-weight:500;color:var(--muted);
  display:flex;align-items:center;justify-content:center;gap:6px;
  transition:all .2s;font-family:'Poppins',sans-serif;
}
.auth-tab i{transition:transform .45s cubic-bezier(.4,.2,.2,1)}
.auth-tab:hover i{transform:rotateY(360deg)}
.auth-tab.active{background:var(--white);color:var(--orange);font-weight:700;box-shadow:0 2px 8px rgba(26,31,46,.1)}
.auth-tab.active-teal{background:var(--white);color:var(--teal);font-weight:700;box-shadow:0 2px 8px rgba(26,31,46,.1)}

.panel{display:none;animation:fadeIn .3s ease}
.panel.on{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.auth-heading{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:4px}
.auth-sub{font-size:12px;color:var(--muted);margin-bottom:18px;line-height:1.5}

.fp-link{
  font-size:11px;font-weight:600;color:var(--orange);text-decoration:none;
  position:relative;padding-bottom:1px;
}
.fp-link::after{
  content:'';position:absolute;left:0;bottom:0;width:100%;height:1.5px;
  background:var(--orange);transform:scaleX(0);transform-origin:right;transition:transform .25s ease;
}
.fp-link:hover::after{transform:scaleX(1);transform-origin:left}

.fg{margin-bottom:14px;animation:fieldIn .4s ease both}
.fg:nth-of-type(1){animation-delay:.02s}
.fg:nth-of-type(2){animation-delay:.08s}
.fg:nth-of-type(3){animation-delay:.14s}
.fg:nth-of-type(4){animation-delay:.2s}
@keyframes fieldIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.fl{display:block;font-size:11px;font-weight:600;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.fl span{color:var(--orange)}
.iw{position:relative}
.iw i.ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--light);pointer-events:none;transition:color .18s}
.fi{
  width:100%;padding:11px 14px 11px 38px;border:1.5px solid var(--border);border-radius:9px;
  font-size:13px;color:var(--text);background:#f8fafc;outline:none;transition:all .18s;font-family:'Poppins',sans-serif;
}
.fi.noi{padding-left:14px}
.fi:hover{border-color:#cbd5e1}
.fi:focus{border-color:var(--orange);background:var(--white);box-shadow:0 0 0 3px var(--orange-soft)}
.fi.ft:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-soft)}
.iw:focus-within i.ico{color:var(--orange);animation:iconWiggle .4s ease}
@keyframes iconWiggle{0%,100%{transform:translateY(-50%) rotate(0)}25%{transform:translateY(-50%) rotate(-10deg)}75%{transform:translateY(-50%) rotate(10deg)}}
select.fi{cursor:pointer}
textarea.fi{resize:vertical;min-height:64px;padding-top:11px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.pw-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--light);font-size:15px;transition:color .15s}
.pw-btn i{display:inline-block;transition:transform .4s cubic-bezier(.4,.2,.2,1)}
.pw-btn:hover{color:var(--muted)}
.pw-btn:hover i{transform:rotateY(180deg) scale(1.1)}

.type-cards{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;perspective:700px}
.type-card{
  border:2px solid var(--border);border-radius:12px;padding:15px 12px;cursor:pointer;
  background:#f8fafc;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:7px;text-align:center;
}
.type-card:hover{border-color:var(--orange);background:var(--orange-soft);transform:translateY(-3px)}
.type-card.sel{border-color:var(--orange);background:var(--orange-soft)}
.type-card i{font-size:26px;color:var(--light);transition:color .2s,transform .5s cubic-bezier(.4,.2,.2,1)}
.type-card.sel i,.type-card:hover i{color:var(--orange);transform:rotateY(360deg)}
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
  width:100%;padding:12px 20px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;
  position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;gap:9px;
  transition:all .2s;margin-top:8px;font-family:'Poppins',sans-serif;letter-spacing:.01em;
}
.sbtn i{transition:transform .45s cubic-bezier(.4,.2,.2,1)}
.sbtn:hover i{transform:rotateY(360deg)}
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

.swlink{text-align:center;margin-top:16px;font-size:12.5px;color:var(--muted)}
.swlink a{font-weight:700;text-decoration:none;color:var(--orange);transition:opacity .15s;display:inline-flex;align-items:center;gap:3px}
.swlink a span.arrow{display:inline-block;transition:transform .25s ease}
.swlink a:hover{opacity:.75}
.swlink a:hover span.arrow{transform:translateX(4px)}
.swlink a.t{color:var(--teal)}

.auth-foot{
  padding:13px 28px;border-top:1px solid var(--border);font-size:10.5px;color:var(--light);
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;flex-shrink:0;
}
.auth-foot a{color:var(--orange);text-decoration:none}

/* ═══════════ SITE FOOTER (light) ═══════════ */
.site-footer{
  background:var(--white);border-top:1px solid var(--border);
  padding:16px 34px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;
  flex-shrink:0;
}
.sf-copy{font-size:11.5px;color:var(--muted)}
.sf-links{display:flex;gap:16px;flex-wrap:wrap}
.sf-links a{font-size:11.5px;color:var(--muted);text-decoration:none;transition:color .15s;position:relative}
.sf-links a::after{content:'';position:absolute;left:0;bottom:-2px;width:100%;height:1px;background:var(--orange);transform:scaleX(0);transform-origin:right;transition:transform .2s ease}
.sf-links a:hover{color:var(--orange)}
.sf-links a:hover::after{transform:scaleX(1);transform-origin:left}
.sf-social{display:flex;gap:8px;perspective:400px}
.sf-social a{
  width:30px;height:30px;border-radius:50%;background:#f1f5f9;color:var(--muted);
  display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;
  transition:background .18s,color .18s,transform .18s;
}
.sf-social a i{transition:transform .5s cubic-bezier(.4,.2,.2,1)}
.sf-social a:hover{background:var(--orange);color:#fff;transform:translateY(-3px)}
.sf-social a:hover i{transform:rotateY(360deg)}

/* ═══════════ MOBILE ═══════════ */
.mobile-info{display:none}
@media(max-width:960px){
  .info-col{display:none}
  .page{padding:18px}
  .frame{max-width:480px}
  .auth-col{width:100%}
  .mobile-info{
    display:block;background:linear-gradient(165deg,#fbfbfd 0%,#f6f8fc 100%);
    border-bottom:1px solid var(--border);padding:20px 24px;flex-shrink:0;
  }
  .mobile-info img{height:32px;margin-bottom:10px}
  .mobile-info h1{font-family:'Playfair Display',serif;font-size:17px;color:var(--navy);line-height:1.3;margin-bottom:6px}
  .mobile-info h1 em{font-style:normal;color:var(--orange)}
  .mobile-info p{font-size:11.5px;color:var(--muted);line-height:1.5}
}
@media(max-width:480px){
  .page{padding:0}
  .frame{border-radius:0}
  .auth-body{padding:20px 18px 16px}
  .auth-topbar{padding:12px 18px}
  .auth-foot{padding:12px 18px}
  .site-header{padding:12px 16px}
  .site-footer{padding:14px 16px;flex-direction:column;align-items:flex-start}
  .frow{grid-template-columns:1fr}
}
</style>
</head>
<body>

<header class="site-header">
  <a href="https://www.researchunlimitedsa.co.za" class="sh-brand">
    <img src="assets/img/logo.png" alt="Research Unlimited">
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

    <!-- ═══ LEFT: INFO PANEL (light, minimal) ═══ -->
    <div class="info-col">
      <div class="net-bg">
        <svg viewBox="0 0 460 560" preserveAspectRatio="xMidYMid slice">
          <line class="net-line" x1="60" y1="90" x2="200" y2="170"/>
          <line class="net-line t n2" x1="200" y1="170" x2="120" y2="290"/>
          <line class="net-line n3" x1="200" y1="170" x2="350" y2="120"/>
          <line class="net-line t n4" x1="120" y1="290" x2="60" y2="430"/>
          <line class="net-line n2" x1="120" y1="290" x2="280" y2="380"/>
          <line class="net-line t" x1="280" y1="380" x2="380" y2="470"/>
          <line class="net-line n3" x1="350" y1="120" x2="410" y2="260"/>
          <circle class="net-node" cx="60" cy="90" r="5"/>
          <circle class="net-node t n2" cx="200" cy="170" r="6"/>
          <circle class="net-node n3" cx="350" cy="120" r="4"/>
          <circle class="net-node t n4" cx="120" cy="290" r="6"/>
          <circle class="net-node n2" cx="280" cy="380" r="5"/>
          <circle class="net-node t n5" cx="60" cy="430" r="4"/>
          <circle class="net-node n3" cx="380" cy="470" r="5"/>
          <circle class="net-node t" cx="410" cy="260" r="4"/>
        </svg>
      </div>

      <div class="info-logo"><img src="assets/img/logo.png" alt="Research Unlimited"></div>

      <div class="info-badge"><span class="dot"></span> Live CSI Matching Platform</div>

      <h1 class="info-title">Bridging Business &amp; Education Through <em>Data&#8209;Driven</em> CSI</h1>
      <p class="info-lead">One platform where companies and schools connect, verify each other, and manage Corporate Social Investment — from first contact to measurable impact.</p>

      <div class="info-cards">
        <div class="info-card">
          <div class="ic-flip">
            <div class="ic-face ic-front"><i class="ti ti-shield-check"></i></div>
            <div class="ic-face ic-back"><i class="ti ti-check"></i></div>
          </div>
          <div><div class="ic-t">Verified Network</div><div class="ic-d">Companies &amp; schools vetted before they meet</div></div>
        </div>
        <div class="info-card">
          <div class="ic-flip">
            <div class="ic-face ic-front"><i class="ti ti-chart-infographic"></i></div>
            <div class="ic-face ic-back"><i class="ti ti-check"></i></div>
          </div>
          <div><div class="ic-t">Smart Matching</div><div class="ic-d">By focus area, budget &amp; province</div></div>
        </div>
        <div class="info-card">
          <div class="ic-flip">
            <div class="ic-face ic-front"><i class="ti ti-report-analytics"></i></div>
            <div class="ic-face ic-back"><i class="ti ti-check"></i></div>
          </div>
          <div><div class="ic-t">Real-Time Tracking</div><div class="ic-d">Monitor CSI spend and impact in one place</div></div>
        </div>
      </div>

      <div class="info-foot">Powered by Research Unlimited</div>
    </div>

    <!-- ═══ RIGHT: AUTH CARD ═══ -->
    <div class="auth-col">

      <div class="mobile-info">
        <img src="assets/img/logo.png" alt="Research Unlimited">
        <h1>Bridging Business &amp; Education Through <em>Data&#8209;Driven</em> CSI</h1>
        <p>Connect, verify, and manage Corporate Social Investment in one place.</p>
      </div>

      <div class="auth-topbar">
        <img src="assets/img/logo.png" alt="Research Unlimited">
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
              <div style="display:flex;align-items:center;justify-content:space-between">
                <label class="fl" style="margin-bottom:0">Password</label>
                <a href="#" class="fp-link" onclick="go('forgot');return false;">Forgot password?</a>
              </div>
              <div class="iw" style="margin-top:6px"><i class="ico ti ti-lock"></i>
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
            New here? <a href="#" class="t" onclick="go('signup')">Request access <span class="arrow">→</span></a>
          </div>
        </div>

        <!-- ── FORGOT PASSWORD ── -->
        <div class="panel <?= $active_tab==='forgot'?'on':'' ?>" id="p-forgot">
          <?php if ($reset_error): ?>
          <div class="al al-err"><i class="ti ti-alert-circle"></i><?= htmlspecialchars($reset_error) ?></div>
          <?php endif; ?>
          <?php if ($forgot_msg): ?>
          <div class="al al-ok"><i class="ti ti-circle-check"></i><div><?= htmlspecialchars($forgot_msg) ?></div></div>
          <?php endif; ?>

          <?php if ($awaiting_otp): ?>
          <h2 class="auth-heading">Enter Your Code</h2>
          <p class="auth-sub">Enter the 6-digit code we emailed you, along with your new password.</p>
          <form method="POST">
            <input type="hidden" name="form" value="reset_password">
            <input type="hidden" name="reset_username" value="<?= htmlspecialchars($awaiting_otp) ?>">
            <div class="fg">
              <label class="fl">6-Digit Code <span>*</span></label>
              <div class="iw"><i class="ico ti ti-key"></i>
                <input class="fi" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       placeholder="123456" required autofocus>
              </div>
            </div>
            <div class="fg">
              <label class="fl">New Password <span>*</span></label>
              <div class="iw"><i class="ico ti ti-lock"></i>
                <input class="fi" type="password" id="rp" name="new_password"
                       placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                <button type="button" class="pw-btn" onclick="tpw('rp','re')">
                  <i class="ti ti-eye" id="re"></i>
                </button>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Confirm New Password <span>*</span></label>
              <div class="iw"><i class="ico ti ti-lock-check"></i>
                <input class="fi" type="password" name="confirm_new_password" placeholder="Repeat new password" required>
              </div>
            </div>
            <button type="submit" class="sbtn s-orange">
              <i class="ti ti-lock-check"></i> Reset Password
            </button>
          </form>
          <div class="swlink">
            Didn't get a code? <a href="login.php?tab=forgot">Try again</a>
          </div>
          <?php else: ?>
          <h2 class="auth-heading">Reset Your Password</h2>
          <p class="auth-sub">Enter your username and the email or phone number on your account — we'll send you a 6-digit code.</p>
          <form method="POST">
            <input type="hidden" name="form" value="forgot_request">
            <div class="fg">
              <label class="fl">Username <span>*</span></label>
              <div class="iw"><i class="ico ti ti-user"></i>
                <input class="fi" type="text" name="forgot_username" placeholder="Enter your username" required>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Email or Phone Number on File <span>*</span></label>
              <div class="iw"><i class="ico ti ti-id-badge-2"></i>
                <input class="fi" type="text" name="forgot_contact" placeholder="you@company.co.za or 079 534 3798" required>
              </div>
            </div>
            <button type="submit" class="sbtn s-orange">
              <i class="ti ti-send"></i> Send Code
            </button>
          </form>
          <?php endif; ?>

          <div class="swlink">
            Remembered it? <a href="#" onclick="go('login')">Back to Sign In <span class="arrow">→</span></a>
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

            <div id="fields-wrap" style="display:<?= !empty($_POST['user_type'])?'block':'none' ?>">

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
            Already have access? <a href="#" onclick="go('login')">Sign in <span class="arrow">→</span></a>
          </div>
        </div>

      </div><!-- /auth-body -->

      <div class="auth-foot">
        <span>&copy; <?= date('Y') ?> Research Unlimited</span>
        <span>
          <a href="mailto:helpdesk@researchunlimitedsa.co.za">helpdesk@researchunlimitedsa.co.za</a>
        </span>
      </div>

    </div><!-- /auth-col -->

  </div><!-- /frame -->
</div><!-- /page -->

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

<script>
function go(tab) {
  ['login','signup','forgot'].forEach(t => {
    document.getElementById('p-'+t).classList.toggle('on', t===tab);
  });
  ['login','signup'].forEach(t => {
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
</body>
</html>