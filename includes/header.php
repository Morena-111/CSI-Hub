<?php
if (!function_exists('redirect')) require_once __DIR__ . '/../config.php';
/**
 * header.php
 * Place in: C:\xampp\htdocs\csi-hub\includes\header.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role'])) { redirect('login.php');  }
if (!function_exists('is_admin')) { require_once __DIR__ . '/auth.php'; }

$_hn_name     = $_SESSION['name'] ?? 'User';
$_hn_initials = current_user_initials();
$_hn_utype    = $_SESSION['user_type'] ?? 'general';

// ── NOTIFICATIONS (all types) ─────────────────────────────────
$_notifs = [];
try {
    // Expiring partnerships
    $exp = $pdo->query("
        SELECT p.end_date, c.name AS co, s.name AS sc,
               DATEDIFF(p.end_date,CURDATE()) AS days
        FROM partnerships p
        JOIN companies c ON c.id=p.company_id
        JOIN schools   s ON s.id=p.school_id
        WHERE p.status='active'
          AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)
        ORDER BY p.end_date ASC LIMIT 10
    ")->fetchAll();
    foreach ($exp as $e) {
        $_notifs[] = [
            'icon'=>'ti-clock','color'=>'#b7791f','bg'=>'#fffbea',
            'text'=>"{$e['co']} → {$e['sc']} expires in {$e['days']} day".($e['days']!=1?'s':''),
            'link'=>'partnerships.php','time'=>'Partnership'
        ];
    }
    // New partnerships (last 7 days)
    $new_p = $pdo->query("
        SELECT p.created_at, c.name AS co, s.name AS sc
        FROM partnerships p
        JOIN companies c ON c.id=p.company_id
        JOIN schools   s ON s.id=p.school_id
        WHERE p.created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
        ORDER BY p.created_at DESC LIMIT 5
    ")->fetchAll();
    foreach ($new_p as $n) {
        $_notifs[] = [
            'icon'=>'ti-plus','color'=>'#00956a','bg'=>'#e6faf5',
            'text'=>"New partnership: {$n['co']} → {$n['sc']}",
            'link'=>'partnerships.php','time'=>date('d M', strtotime($n['created_at']))
        ];
    }
    // New documents (last 7 days)
    $new_d = $pdo->query("
        SELECT title, uploaded_by, created_at FROM documents
        WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
        ORDER BY created_at DESC LIMIT 5
    ")->fetchAll();
    foreach ($new_d as $d) {
        $_notifs[] = [
            'icon'=>'ti-file-invoice','color'=>'#6c5ce7','bg'=>'#f0eeff',
            'text'=>"Document uploaded: {$d['title']} by {$d['uploaded_by']}",
            'link'=>'documents.php','time'=>date('d M', strtotime($d['created_at']))
        ];
    }
    // Upcoming events (next 3 days)
    $ev = $pdo->query("
        SELECT title, event_date FROM events
        WHERE status='upcoming'
          AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)
        ORDER BY event_date ASC LIMIT 5
    ")->fetchAll();
    foreach ($ev as $e) {
        $days = ceil((strtotime($e['event_date'])-time())/86400);
        $_notifs[] = [
            'icon'=>'ti-calendar','color'=>'#E8541A','bg'=>'#fdf0ea',
            'text'=>"Event: {$e['title']} in {$days} day".($days!=1?'s':''),
            'link'=>'events.php','time'=>date('d M', strtotime($e['event_date']))
        ];
    }
    // Pending approvals (admin only)
    if (is_admin()) {
        $sp = __DIR__ . '/../data/pending_signups.json';
        if (file_exists($sp)) {
            $pd = json_decode(file_get_contents($sp), true) ?? [];
            $pu = array_filter($pd, fn($v) => !($v['approved']??false));
            if (count($pu) > 0) {
                $_notifs[] = [
                    'icon'=>'ti-user-check','color'=>'#4c63d2','bg'=>'#eef2ff',
                    'text'=>count($pu).' user access request'.(count($pu)!=1?'s':'').' awaiting approval',
                    'link'=>'team.php','time'=>'Admin'
                ];
            }
        }
    }
    // New impact stats (last 7 days)
    $ni = $pdo->query("
        SELECT i.created_at, c.name AS co, s.name AS sc
        FROM impact_stats i
        JOIN partnerships p ON p.id=i.partnership_id
        JOIN companies c ON c.id=p.company_id
        JOIN schools   s ON s.id=p.school_id
        WHERE i.created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
        ORDER BY i.created_at DESC LIMIT 3
    ")->fetchAll();
    foreach ($ni as $n) {
        $_notifs[] = [
            'icon'=>'ti-heart-rate-monitor','color'=>'#00c48c','bg'=>'#e6faf5',
            'text'=>"Impact record added: {$n['co']} → {$n['sc']}",
            'link'=>'impact_stats.php','time'=>date('d M', strtotime($n['created_at']))
        ];
    }
} catch(Exception $e) { $_notifs = []; }

$_notif_count = count($_notifs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CSI Hub | Research Unlimited</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
<!-- assets/css/main.css and modal.css intentionally NOT loaded here.
     All styling lives in the inline <style> block below to guarantee
     it always wins and never conflicts with old/cached CSS files. -->
<style>
/* ══════════════════════════════════════════════════
   CSI Hub — Core Styles (embedded in header.php)
   Consistent with original dashboard look
══════════════════════════════════════════════════ */
:root{
  --orange:#E8541A; --orange-h:#c94514; --orange-soft:#fdf0ea;
  --teal:#00c48c;   --teal-h:#00a077;   --teal-soft:#e6faf5;
  --navy:#0d1e3d;   --navy2:#162848;
  --purple:#6c5ce7; --purple-soft:#f0eeff;
  --gold:#f5a623;   --gold-soft:#fffbea;
  --white:#ffffff;  --surface:#f6f7fb;  --bg:#f0f2f7;
  --border:#e8edf5; --text:#1a1f2e;
  --text-muted:#6b7a99; --text-light:#a0aec0;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);overflow:hidden}
img{max-width:100%}
a{text-decoration:none;color:inherit}

/* ══════════════════════════════════════════════
   TOP NAV — Real platform feel, humanised
   Inspired by established CSI/enterprise tools:
   clean white, warm navy left zone, orange accent
══════════════════════════════════════════════ */
.topnav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  height:68px;
  background:var(--white);
  display:flex;align-items:stretch;
  box-shadow:0 1px 0 #e2e8f0, 0 2px 8px rgba(13,30,61,.06);
}
/* No pseudo-element gradients — clean and real */

/* LEFT ZONE — plain white, matches the rest of the header */
.topnav-left{
  width:240px;flex-shrink:0;
  display:flex;align-items:center;
  padding:0 18px;
  background:#ffffff;
  border-right:1px solid #e2e8f0;
  position:relative;z-index:2;
}
.topnav-logo-link{
  display:flex;align-items:center;gap:10px;
  text-decoration:none;
  padding:4px 0;
  transition:opacity .15s;
}
.topnav-logo-link:hover{opacity:.85}
.topnav-logo{
  height:36px;width:auto;
  object-fit:contain;display:block;
  flex-shrink:0;
  border-radius:6px;
}
.topnav-logo-text{display:flex;flex-direction:column;line-height:1.2;min-width:0}
.topnav-logo-name{
  font-size:13px;font-weight:700;
  color:#0d1e3d;
  white-space:nowrap;letter-spacing:-.01em;
}
.topnav-logo-tag{
  font-size:9px;color:var(--orange);
  text-transform:uppercase;letter-spacing:.08em;
  white-space:nowrap;margin-top:1px;
}

/* Centre: search */
.topnav-centre{
  flex:1;display:flex;align-items:center;gap:20px;
  justify-content:flex-start;gap:16px;padding:0 24px;
  position:relative;z-index:2;
}
.topnav-search-wrap{
  position:relative;flex:1;max-width:340px;
}
.topnav-search-icon{
  position:absolute;left:11px;top:50%;
  transform:translateY(-50%);
  font-size:13px;color:var(--text-light);
  pointer-events:none;
}
.topnav-search{
  width:100%;padding:9px 14px 9px 36px;
  border:1.5px solid var(--border);
  border-radius:9px;
  font-size:12.5px;font-family:'Poppins',sans-serif;
  color:var(--text);
  background:var(--surface);
  outline:none;transition:all .2s;
}
.topnav-search::placeholder{color:var(--text-light)}
.topnav-search:focus{
  border-color:var(--orange);
  background:var(--white);
  box-shadow:0 0 0 3px var(--orange-soft);
}
.topnav-search-results{
  display:none;
  position:absolute;top:calc(100% + 8px);left:0;right:0;
  min-width:280px;
  background:var(--white);
  border:1px solid var(--border);
  border-radius:12px;
  box-shadow:0 8px 32px rgba(13,30,61,.2);
  z-index:300;max-height:320px;overflow-y:auto;
}
.topnav-search-results.visible{display:block}
.search-result-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 14px;border-bottom:1px solid var(--border);
  cursor:pointer;transition:background .12s;text-decoration:none;
}
.search-result-item:last-child{border-bottom:none}
.search-result-item:hover{background:var(--surface)}
.search-result-icon{
  width:28px;height:28px;border-radius:7px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:13px;
}
.search-result-text{flex:1;min-width:0}
.search-result-name{font-size:12.5px;font-weight:600;color:var(--text)}
.search-result-type{font-size:11px;color:var(--text-muted)}
.search-result-empty{padding:18px;text-align:center;font-size:12.5px;color:var(--text-muted)}

/* Right section */
.topnav-right{
  display:flex;align-items:center;gap:6px;
  padding:0 20px;
  flex-shrink:0;
  position:relative;z-index:2;
}

/* M&E link — THE hero element of the header.
   This is the company's core revenue driver, so it must be
   the single most eye-catching thing in the entire topbar. */
/* ── M&E BUTTON — clean, professional, branded ── */
.topnav-me-link{
  display:inline-flex;align-items:center;gap:11px;
  text-decoration:none;
  background:#0d1e3d;
  border-radius:10px;
  padding:10px 20px;
  border:1.5px solid #E8541A;
  box-shadow:0 3px 14px rgba(13,30,61,.25);
  transition:all .2s;
  flex-shrink:0;
}
.topnav-me-link:hover{
  transform:translateY(-1px);
  box-shadow:0 6px 22px rgba(13,30,61,.35);
}
.me-icon{
  width:32px;height:32px;border-radius:8px;
  background:rgba(232,84,26,.15);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.me-icon i{font-size:17px;color:#E8541A}
.me-words{display:flex;flex-direction:column;line-height:1.3}
.me-title{
  font-size:13px;font-weight:700;
  color:#ffffff;white-space:nowrap;
}
.me-desc{
  font-size:9px;color:rgba(255,255,255,.5);
  font-weight:500;text-transform:uppercase;
  letter-spacing:.06em;white-space:nowrap;
  margin-top:1px;
}
.me-chip{
  background:#E8541A;color:#fff;
  font-size:10px;font-weight:700;
  padding:3px 7px;border-radius:5px;
  letter-spacing:.04em;flex-shrink:0;
}

/* Divider */
.topnav-divider{
  width:1px;height:24px;
  background:#dce3ef;
  margin:0 4px;flex-shrink:0;
}

/* Icon buttons (bell, signout) */
.topnav-icon-btn{
  width:38px;height:38px;border-radius:9px;
  border:none;background:transparent;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:17px;color:#64748b;
  transition:all .15s;position:relative;text-decoration:none;
}
.topnav-icon-btn:hover{
  background:#f1f5f9;
  color:#0d1e3d;
}

/* Notification badge */
.topnav-badge{
  position:absolute;top:4px;right:4px;
  min-width:16px;height:16px;border-radius:8px;
  background:var(--orange);color:#fff;
  font-size:9px;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  padding:0 3px;
  border:1.5px solid var(--white);
}

/* User btn — avatar + name */
.topnav-user-btn{
  display:flex;align-items:center;gap:9px;
  padding:5px 10px 5px 5px;
  border-radius:10px;
  text-decoration:none;
  transition:background .15s;
  cursor:pointer;
  margin:0 2px;
}
.topnav-user-btn:hover{background:var(--surface)}
.topnav-avatar{
  width:36px;height:36px;border-radius:50%;flex-shrink:0;
  background:#E8541A;
  color:#ffffff !important;
  font-size:13px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  letter-spacing:.05em;
  box-shadow:0 2px 8px rgba(232,84,26,.4);
  text-transform:uppercase;
  font-family:'Poppins',sans-serif;
  line-height:1;
}
.topnav-user-name{
  font-size:12.5px;font-weight:600;
  color:var(--text);
  white-space:nowrap;
  max-width:120px;
  overflow:hidden;text-overflow:ellipsis;
}

/* Sign out button */
.topnav-signout-btn{
  width:34px;height:34px;border-radius:7px;
  display:flex;align-items:center;justify-content:center;
  font-size:16px;color:#94a3b8;
  transition:all .15s;text-decoration:none;
  margin-left:2px;
}
.topnav-signout-btn:hover{
  background:#fef2f2;
  color:#dc2626;
}

/* Notification panel */
.topnav-notif-wrap{position:relative}
.notif-panel{
  display:none;
  position:absolute;top:calc(100% + 10px);right:0;
  width:340px;
  background:var(--white);
  border:1px solid var(--border);
  border-radius:14px;
  box-shadow:0 12px 40px rgba(13,30,61,.18);
  z-index:300;overflow:hidden;
}
.notif-panel.open{display:block}
.notif-panel-head{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 16px;
  border-bottom:1px solid var(--border);
  font-size:13px;font-weight:700;color:var(--text);
  background:var(--surface);
}
.notif-count{font-size:11px;font-weight:400;color:var(--text-muted)}
.notif-list{max-height:340px;overflow-y:auto}
.notif-item{
  display:flex;align-items:flex-start;gap:10px;
  padding:11px 16px;border-bottom:1px solid var(--border);
  transition:background .12s;text-decoration:none;
}
.notif-item:hover{background:var(--surface)}
.notif-icon{
  width:30px;height:30px;border-radius:8px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:14px;
}
.notif-body{flex:1;min-width:0}
.notif-text{font-size:12px;color:var(--text);line-height:1.45}
.notif-time{font-size:10.5px;color:var(--text-muted);margin-top:2px}
.notif-empty{text-align:center;padding:28px 16px;color:var(--text-muted)}
.notif-empty i{font-size:28px;opacity:.25;display:block;margin-bottom:8px}
.notif-empty p{font-size:12.5px}
.notif-panel-foot{
  padding:10px 16px;text-align:center;
  font-size:12px;border-top:1px solid var(--border);
  background:var(--surface);
}
.notif-panel-foot a{color:var(--orange);font-weight:600}

/* ── SIDEBAR ─────────────────────────────────────
   Fixed left, starts below topnav (top:56px).
   240px wide. White bg with right border.
──────────────────────────────────────────────── */
.sidebar{
  width:260px;flex-shrink:0;
  background:var(--white);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  position:fixed;top:68px;left:0;
  height:calc(100vh - 68px);
  overflow-y:auto;z-index:50;
  /* thin, unobtrusive scrollbar instead of default thick one */
  scrollbar-width:thin;
  scrollbar-color:var(--border) transparent;
}
.sidebar::-webkit-scrollbar{width:5px}
.sidebar::-webkit-scrollbar-track{background:transparent}
.sidebar::-webkit-scrollbar-thumb{
  background:var(--border);
  border-radius:10px;
}
.sidebar::-webkit-scrollbar-thumb:hover{background:var(--text-light)}

/* Sidebar brand — text only (logo lives in header) */
.sidebar-brand{
  display:flex;align-items:center;
  padding:18px 16px 15px;
  border-bottom:1px solid var(--border);
}
.sidebar-brand-text{display:flex;flex-direction:column;line-height:1.3;min-width:0}
.sidebar-brand-name{
  font-size:14px;font-weight:700;color:var(--text);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.sidebar-brand-sub{
  font-size:9.5px;color:var(--orange);
  text-transform:uppercase;letter-spacing:.08em;margin-top:1px;
}

/* Nav sections */
.sidebar-nav{flex:1;padding:6px 0}
.sidebar-section{margin-bottom:2px}
.sidebar-section-label{
  display:block;
  font-size:9.5px;font-weight:700;
  color:var(--text-light);
  text-transform:uppercase;letter-spacing:.1em;
  padding:14px 16px 5px;
}
.sidebar-item{
  display:flex;align-items:center;gap:10px;
  padding:9px 16px;
  font-size:13px;font-weight:500;
  color:var(--text-muted);
  transition:all .12s;
  text-decoration:none;position:relative;
}
.sidebar-item i{font-size:16px;flex-shrink:0}
.sidebar-item span{flex:1}
.sidebar-item:hover{background:var(--surface);color:var(--text)}
.sidebar-item.active{
  background:var(--orange-soft);
  color:var(--orange);
  font-weight:700;
}
.sidebar-item.active::after{
  content:'';position:absolute;right:0;top:0;bottom:0;
  width:3px;background:var(--orange);border-radius:2px 0 0 2px;
}
.sidebar-badge{
  background:var(--orange);color:#fff;
  font-size:9px;font-weight:700;
  padding:2px 6px;border-radius:10px;
  flex-shrink:0;
}

/* Sidebar help / contact card — sits above the user block,
   styled like the support widgets on big SaaS platforms
   (Intercom, Zendesk-style sidebar contact card) */
.sidebar-help{
  margin:10px 12px 6px;
  padding:13px 14px;
  background:linear-gradient(135deg,#fff7ed 0%,var(--orange-soft) 100%);
  border:1px solid rgba(232,84,26,.18);
  border-radius:12px;
}
.sidebar-help-title{
  display:flex;align-items:center;gap:7px;
  font-size:11px;font-weight:700;color:var(--orange);
  text-transform:uppercase;letter-spacing:.04em;
  margin-bottom:9px;
}
.sidebar-help-title i{font-size:14px}
.sidebar-help-item{
  display:flex;align-items:center;gap:8px;
  font-size:11.5px;font-weight:500;color:#7c4a2d;
  padding:5px 0;
  text-decoration:none;
  transition:color .15s;
}
.sidebar-help-item:hover{color:var(--orange)}
.sidebar-help-item i{font-size:13px;flex-shrink:0;color:var(--orange);opacity:.75}
.sidebar-help-item span{
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}

/* Sidebar sign out bar — bottom */
.sidebar-signout-bar{
  padding:12px;
  border-top:1px solid var(--border);
  margin-top:auto;
}
.sidebar-signout-btn{
  display:flex;align-items:center;gap:9px;
  padding:9px 14px;border-radius:9px;
  font-size:12.5px;font-weight:600;
  color:var(--text-muted);text-decoration:none;
  transition:all .15s;
  width:100%;
}
.sidebar-signout-btn i{font-size:16px;color:var(--text-muted)}
.sidebar-signout-btn:hover{
  background:#fde9e9;color:#c53030;
}
.sidebar-signout-btn:hover i{color:#c53030}

/* ── LAYOUT ──────────────────────────────────────
   .layout wraps sidebar + main content.
   Sidebar is fixed so main needs left margin.
──────────────────────────────────────────────── */
.layout{
  display:flex;
  height:calc(100vh - 68px);
  margin-top:68px;
  overflow:hidden;
}
.main{
  flex:1;
  min-width:0;
  padding:28px 32px;
  margin-left:260px;
  height:100%;
  overflow-y:auto;
  scrollbar-width:thin;
  scrollbar-color:var(--border) transparent;
}
.main::-webkit-scrollbar{width:6px}
.main::-webkit-scrollbar-track{background:transparent}
.main::-webkit-scrollbar-thumb{
  background:var(--border);
  border-radius:10px;
}
.main::-webkit-scrollbar-thumb:hover{background:var(--text-light)}

/* ── PAGE STRUCTURE ──────────────────────────── */
.page-banner{
  display:flex;align-items:center;gap:6px;
  font-size:12px;color:var(--text-muted);
  margin-bottom:16px;
}
.page-banner i{font-size:13px}
.active-crumb{color:var(--orange);font-weight:600}
.page-header{
  display:flex;align-items:flex-start;
  justify-content:space-between;
  margin-bottom:22px;flex-wrap:wrap;gap:12px;
}
.page-header h1{
  font-family:'Playfair Display',serif;
  font-size:26px;font-weight:700;
  color:var(--text);margin-bottom:3px;
  line-height:1.2;
}
.page-header p{font-size:13px;color:var(--text-muted);line-height:1.5}
.page-header-right{
  display:flex;align-items:center;
  gap:10px;flex-wrap:wrap;
}

/* ── BUTTONS ─────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;gap:7px;
  padding:8px 16px;border-radius:9px;
  border:none;cursor:pointer;
  font-size:13px;font-weight:600;
  font-family:'Poppins',sans-serif;
  transition:all .15s;white-space:nowrap;
  line-height:1;
}
.btn:hover{transform:translateY(-1px)}
.btn:active{transform:scale(.98)}
.btn i{font-size:15px}
.btn-primary  {background:var(--orange);color:#fff}
.btn-primary:hover{background:var(--orange-h)}
.btn-secondary{background:var(--white);color:var(--text);border:1.5px solid var(--border)}
.btn-secondary:hover{background:var(--surface)}
.btn-teal {background:var(--teal);color:#fff}
.btn-teal:hover{background:var(--teal-h)}
.btn-navy {background:var(--navy);color:#fff}
.btn-navy:hover{background:var(--navy2)}
.btn-locked{opacity:.5;cursor:not-allowed}
.btn-locked:hover{transform:none}

/* ── STAT CARDS ──────────────────────────────── */
.stats-row{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(175px,1fr));
  gap:14px;margin-bottom:22px;
}
.stat-card{
  background:var(--white);border:1px solid var(--border);
  border-radius:12px;padding:16px 18px;
  border-left:4px solid var(--border);
}
.stat-card.orange{border-left-color:var(--orange)}
.stat-card.teal  {border-left-color:var(--teal)}
.stat-card.purple{border-left-color:var(--purple)}
.stat-card.gold  {border-left-color:var(--gold)}
.stat-label{
  font-size:10.5px;font-weight:700;color:var(--text-muted);
  text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;
}
.stat-value{
  font-family:'Playfair Display',serif;
  font-size:28px;font-weight:700;color:var(--text);
  line-height:1;margin-bottom:4px;
}
.stat-value.orange{color:var(--orange)}
.stat-value.teal  {color:var(--teal)}
.stat-value.purple{color:var(--purple)}
.stat-sub{font-size:11.5px;color:var(--text-muted)}

/* ── WIDGETS ─────────────────────────────────── */
.widget{
  background:var(--white);border:1px solid var(--border);
  border-radius:12px;padding:18px 20px;margin-bottom:18px;
}
.widget-title{
  display:flex;align-items:center;gap:8px;
  font-size:13px;font-weight:700;color:var(--text);
  margin-bottom:14px;
}
.widget-title i{font-size:16px;color:var(--orange)}

/* ── DATA TABLES ─────────────────────────────── */
.data-table{width:100%;border-collapse:collapse}
.data-table th{
  background:var(--surface);color:var(--text-muted);
  font-size:10.5px;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;
  padding:9px 12px;text-align:left;
  border-bottom:1px solid var(--border);
}
.data-table td{
  padding:10px 12px;
  border-bottom:1px solid var(--border);
  font-size:13px;color:var(--text);
}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:var(--surface)}
.cell-name{font-weight:600}

/* ── STATUS BADGES ───────────────────────────── */
.status-badge{
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 10px;border-radius:20px;
  font-size:11px;font-weight:600;
}
.status-badge.active   {background:var(--teal-soft);color:#00956a}
.status-badge.pending  {background:var(--gold-soft);color:#9a6700}
.status-badge.completed{background:var(--surface);color:var(--text-muted)}
.status-badge.paused,
.status-badge.inactive {background:#fde9e9;color:#c53030}
.status-badge.draft    {background:var(--surface);color:var(--text-muted)}
.status-badge.closed   {background:var(--surface);color:var(--text-light)}

/* ── TABLE ACTION BUTTONS ────────────────────── */
.table-action-btn{
  width:30px;height:30px;border-radius:7px;
  border:1px solid var(--border);background:var(--white);
  cursor:pointer;font-size:14px;color:var(--text-muted);
  display:inline-flex;align-items:center;justify-content:center;
  transition:all .12s;
}
.table-action-btn:hover{background:var(--surface);color:var(--text)}
.btn-danger-icon:hover{background:#fde9e9;color:#c53030;border-color:#f5c0c0}

/* ── FORMS ───────────────────────────────────── */
.form-group {margin-bottom:14px}
.form-row   {display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-label {
  display:block;font-size:11px;font-weight:600;
  color:var(--text);margin-bottom:6px;
}
.form-input,.form-select{
  width:100%;padding:10px 12px;
  border:1.5px solid var(--border);border-radius:9px;
  font-size:13px;font-family:'Poppins',sans-serif;
  color:var(--text);background:var(--surface);outline:none;
  transition:all .15s;
}
.form-input:focus,.form-select:focus{
  border-color:var(--orange);background:var(--white);
  box-shadow:0 0 0 3px var(--orange-soft);
}
textarea.form-input{resize:vertical;min-height:80px}
.filter-input{
  padding:8px 12px 8px 34px;
  border:1.5px solid var(--border);border-radius:9px;
  font-size:13px;font-family:'Poppins',sans-serif;
  color:var(--text);background:var(--white);outline:none;
  width:220px;transition:all .15s;
}
.filter-input:focus{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-soft)}
.search-wrap{position:relative;display:inline-block}
.search-wrap>i{
  position:absolute;left:10px;top:50%;
  transform:translateY(-50%);
  color:var(--text-light);font-size:15px;pointer-events:none;
}

/* ── PROGRESS ────────────────────────────────── */
.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-bottom:4px}
.progress-fill{height:100%;background:var(--orange);border-radius:3px;transition:width .3s}

/* ── CARDS GRID ──────────────────────────────── */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.pcard{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:18px 20px;transition:all .15s}
.pcard:hover{box-shadow:0 4px 16px rgba(26,31,46,.08);transform:translateY(-2px)}

/* ── ADMIN BADGE ─────────────────────────────── */
.admin-badge{display:inline-flex;align-items:center;gap:5px;background:var(--orange-soft);color:var(--orange);font-size:11.5px;font-weight:600;padding:4px 12px;border-radius:20px}

/* ── TOAST ───────────────────────────────────── */
.toast{
  position:fixed;bottom:24px;right:24px;z-index:9999;
  background:var(--navy);color:#fff;
  padding:12px 18px;border-radius:10px;
  display:none;align-items:center;gap:10px;
  font-size:13px;font-weight:500;
  box-shadow:0 8px 24px rgba(0,0,0,.2);
}
.toast.show{display:flex}

/* ── SETTINGS PAGE ───────────────────────────── */
.settings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.settings-card{background:var(--white);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.settings-card-header{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border)}
.settings-card-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.settings-card-icon.orange{background:var(--orange-soft);color:var(--orange)}
.settings-card-icon.teal  {background:var(--teal-soft);  color:var(--teal)}
.settings-card-icon.purple{background:var(--purple-soft);color:var(--purple)}
.settings-card-icon.gold  {background:var(--gold-soft);  color:var(--gold)}
.settings-card-title{font-size:14px;font-weight:700;color:var(--text)}
.settings-card-sub  {font-size:12px;color:var(--text-muted);margin-top:2px}
.settings-card-body {padding:18px 20px}

/* ── MODAL ───────────────────────────────────── */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(13,30,61,.45);
  z-index:1000;align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.open{display:flex}
.modal{
  background:var(--white);border-radius:16px;
  padding:28px 30px;width:100%;max-width:480px;
  max-height:90vh;overflow-y:auto;
  position:relative;
  box-shadow:0 20px 60px rgba(13,30,61,.22);
  animation:mpop .18s ease;
}
@keyframes mpop{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal h2{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:4px}
.modal-sub{font-size:12.5px;color:var(--text-muted);margin-bottom:20px;line-height:1.5}
.modal-close{
  position:absolute;top:16px;right:16px;
  background:var(--surface);border:none;border-radius:8px;
  width:32px;height:32px;display:flex;align-items:center;justify-content:center;
  cursor:pointer;color:var(--text-muted);font-size:16px;transition:all .15s;
}
.modal-close:hover{background:var(--border);color:var(--text)}
.modal-actions{
  display:flex;justify-content:flex-end;gap:10px;
  margin-top:20px;padding-top:16px;border-top:1px solid var(--border);
}
</style>
</head>
<body>

<!-- TOP NAV -->
<nav class="topnav">

  <!-- LEFT: Logo icon + crisp text wordmark -->
  <div class="topnav-left">
    <a href="dashboard.php" class="topnav-logo-link">
      <img src="assets/img/logo.png" alt="Research Unlimited" class="topnav-logo">
      <div class="topnav-logo-text">
        <span class="topnav-logo-name">Research Unlimited</span>
        <span class="topnav-logo-tag">Research Made Easy</span>
      </div>
    </a>
  </div>

  <!-- CENTRE: Creative M&E pill + search -->
  <div class="topnav-centre">

    <a href="programmes.php" class="topnav-me-link">
      <div class="me-icon"><i class="ti ti-activity"></i></div>
      <div class="me-words">
        <span class="me-title">Monitoring &amp; Evaluation</span>
        <span class="me-desc">Our Core Service</span>
      </div>
      <div class="me-chip">M&amp;E</div>
    </a>

    <!-- Search — compact, integrated feel -->
    <div class="topnav-search-wrap">
      <i class="ti ti-search topnav-search-icon"></i>
      <input type="text" class="topnav-search" id="global-search"
        placeholder="Search partners, schools, docs…"
        autocomplete="off"
        oninput="globalSearch(this.value)"
        onfocus="showResults()"
        onblur="setTimeout(hideResults,200)">
      <div class="topnav-search-results" id="search-results"></div>
    </div>

  </div>

  <!-- RIGHT: bell + avatar (no name, no signout here) -->
  <div class="topnav-right">
    <div class="topnav-divider"></div>


    <!-- Notification bell -->
    <div class="topnav-notif-wrap" id="notif-wrap">
      <button class="topnav-icon-btn" id="notif-btn" onclick="toggleNotif()" title="Notifications">
        <i class="ti ti-bell"></i>
        <?php if ($_notif_count > 0): ?>
        <span class="topnav-badge"><?= $_notif_count ?></span>
        <?php endif; ?>
      </button>
      <div class="notif-panel" id="notif-panel">
        <div class="notif-panel-head">
          <span>Notifications</span>
          <span class="notif-count"><?= $_notif_count ?> update<?= $_notif_count!=1?'s':'' ?></span>
        </div>
        <?php if (empty($_notifs)): ?>
        <div class="notif-empty">
          <i class="ti ti-bell-off"></i>
          <p>No new updates</p>
        </div>
        <?php else: ?>
        <div class="notif-list">
          <?php foreach ($_notifs as $n): ?>
          <a href="<?= $n['link'] ?>" class="notif-item">
            <div class="notif-icon" style="background:<?= $n['bg'] ?>;color:<?= $n['color'] ?>">
              <i class="ti <?= $n['icon'] ?>"></i>
            </div>
            <div class="notif-body">
              <div class="notif-text"><?= htmlspecialchars($n['text']) ?></div>
              <div class="notif-time"><?= htmlspecialchars($n['time']) ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="notif-panel-foot">
          <a href="dashboard.php">View all →</a>
        </div>
      </div>
    </div>

    <!-- User avatar → profile -->
    <a href="profile.php" class="topnav-user-btn" title="My Profile">
      <div class="topnav-avatar">
        <?php
          // Compute initials fresh here as fallback
          $_av_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'U';
          $_av_parts = explode(' ', trim($_av_name));
          $_av_init = '';
          foreach(array_slice($_av_parts,0,2) as $p) $_av_init .= strtoupper(substr($p,0,1));
          echo htmlspecialchars($_av_init ?: 'U');
        ?>
      </div>
    </a>

    <!-- Sign out -->
    <a href="logout.php" class="topnav-signout-btn" title="Sign out">
      <i class="ti ti-logout"></i>
    </a>

  </div>

</nav>
<script>
// Base path — works on both localhost/csi-hub/ and live root /
const BASE_PATH = '<?= defined("BASE_URL") ? BASE_URL : "/csi-hub/" ?>';
function toggleNotif() {
  const p = document.getElementById('notif-panel');
  p.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notif-panel').classList.remove('open');
  }
  // Close search if clicking outside
  const sw = document.querySelector('.topnav-search-wrap');
  if (sw && !sw.contains(e.target)) hideResults();
});

// ── GLOBAL SEARCH ───────────────────────────────────────────
let _st = null;
function globalSearch(q) {
  clearTimeout(_st);
  const box = document.getElementById('search-results');
  if (!q || q.length < 2) {
    box.innerHTML='';
    box.classList.remove('visible');
    return;
  }
  // Show loading state immediately
  box.innerHTML = '<div class="search-result-empty" style="color:var(--text-muted)"><i class="ti ti-loader" style="font-size:16px;display:block;margin-bottom:6px"></i>Searching…</div>';
  box.classList.add('visible');

  _st = setTimeout(() => {
    fetch(BASE_PATH + 'search.php?q=' + encodeURIComponent(q), {
      credentials: 'same-origin'
    })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        if (!data.length) {
          box.innerHTML = '<div class="search-result-empty">No results found for "<strong>' + escHtml(q) + '</strong>"</div>';
        } else {
          box.innerHTML = data.map(r => `
            <a href="${BASE_PATH + r.url}" class="search-result-item">
              <div class="search-result-icon" style="background:${r.bg};color:${r.color}">
                <i class="ti ${r.icon}"></i>
              </div>
              <div class="search-result-text">
                <div class="search-result-name">${escHtml(r.name)}</div>
                <div class="search-result-type">${escHtml(r.type)}</div>
              </div>
            </a>`).join('');
        }
        box.classList.add('visible');
      })
      .catch(err => {
        box.innerHTML = '<div class="search-result-empty" style="color:#c53030"><i class="ti ti-alert-circle" style="font-size:14px"></i> Search unavailable</div>';
        box.classList.add('visible');
        console.error('Search error:', err);
      });
  }, 250);
}
function showResults() {
  const q = document.getElementById('global-search').value;
  if (q && q.length >= 2) document.getElementById('search-results').classList.add('visible');
}
function hideResults() {
  document.getElementById('search-results').classList.remove('visible');
}
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>