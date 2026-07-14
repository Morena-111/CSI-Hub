<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
if (!is_admin() && ($_SESSION['user_type']??'') !== 'company') {
    redirect('dashboard.php');
}

$pledge_id = (int)($_GET['pledge_id'] ?? 0);
if (!$pledge_id) redirect('pledge_management.php');

$p = $pdo->prepare("
    SELECT np.*, sn.title AS need_title, sn.school_id,
           c.name AS company_name, s.name AS school_name,
           s.province, c.sector
    FROM need_pledges np
    JOIN school_needs sn ON sn.id=np.need_id
    JOIN companies c ON c.id=np.company_id
    JOIN schools s ON s.id=sn.school_id
    WHERE np.id=?
");
$p->execute([$pledge_id]); $pledge = $p->fetch();
if (!$pledge) redirect('pledge_management.php');
// Company can only view their own MOUs
if (!is_admin() && (int)$pledge['company_id'] !== (int)($_SESSION['linked_id']??0)) {
    redirect('dashboard.php');
}

$today    = date('d F Y');
$mou_no   = 'MOU-'.date('Y').'-'.str_pad($pledge_id,4,'0',STR_PAD_LEFT);
$end_date = date('d F Y', strtotime('+1 year'));
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>MOU — <?= htmlspecialchars($mou_no) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',sans-serif;color:#1a1f2e;background:#fff;padding:0}
.page{max-width:800px;margin:0 auto;padding:48px 56px}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:36px;padding-bottom:20px;border-bottom:3px solid #E8541A}
.logo-text{font-size:18px;font-weight:700;color:#0d1e3d}
.logo-sub{font-size:10px;color:#6b7a99;text-transform:uppercase;letter-spacing:.08em}
.mou-no{font-size:11px;color:#6b7a99;text-align:right}
.mou-no strong{display:block;font-size:14px;color:#E8541A;font-weight:700}
h1{font-family:'Playfair Display',serif;font-size:26px;color:#0d1e3d;text-align:center;margin-bottom:6px}
.subtitle{text-align:center;font-size:12px;color:#6b7a99;margin-bottom:32px}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
.party{background:#f5f7fb;border-radius:10px;padding:16px 20px;border-left:4px solid #E8541A}
.party.school{border-left-color:#00c48c}
.party-label{font-size:9.5px;font-weight:700;color:#6b7a99;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.party-name{font-size:15px;font-weight:700;color:#0d1e3d}
.party-detail{font-size:12px;color:#6b7a99;margin-top:3px}
.section{margin-bottom:22px}
.section h2{font-size:13px;font-weight:700;color:#0d1e3d;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #e8edf5}
.clause{font-size:12.5px;line-height:1.8;color:#1a1f2e;margin-bottom:8px;padding-left:16px;position:relative}
.clause::before{content:attr(data-n)'.';position:absolute;left:0;font-weight:700;color:#E8541A}
.amount-box{background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:10px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between}
.amount-box .label{font-size:11px;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.amount-box .value{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:#fff}
.amount-box .detail{font-size:12px;color:rgba(255,255,255,.6)}
.sigs{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:40px;padding-top:24px;border-top:2px dashed #e8edf5}
.sig-block .line{height:1px;background:#0d1e3d;margin-bottom:6px;margin-top:32px}
.sig-block .name{font-size:12.5px;font-weight:700;color:#0d1e3d}
.sig-block .role{font-size:11px;color:#6b7a99}
.footer{text-align:center;margin-top:36px;font-size:10.5px;color:#a0aec0;padding-top:16px;border-top:1px solid #e8edf5}
@media print{.no-print{display:none}.page{padding:24px}}
</style>
</head>
<body>
<div class="no-print" style="background:#0d1e3d;padding:12px 24px;display:flex;align-items:center;justify-content:space-between">
  <a href="pledge_management.php" style="color:rgba(255,255,255,.6);text-decoration:none;font-size:13px"><i>← Back</i></a>
  <button onclick="window.print()" style="background:#E8541A;color:white;border:none;padding:8px 20px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600">Print / Save PDF</button>
</div>

<div class="page">
  <div class="header">
    <div>
      <div class="logo-text">Research Unlimited</div>
      <div class="logo-sub">CSI Hub · Research Made Easy</div>
    </div>
    <div class="mou-no">
      <span>Document Reference</span>
      <strong><?= $mou_no ?></strong>
      <span style="font-size:10px">Date: <?= $today ?></span>
    </div>
  </div>

  <h1>Memorandum of Understanding</h1>
  <p class="subtitle">Corporate Social Investment Programme Agreement · Facilitated by Research Unlimited</p>

  <div class="parties">
    <div class="party">
      <div class="party-label">Funder / Corporate Partner</div>
      <div class="party-name"><?= htmlspecialchars($pledge['company_name']) ?></div>
      <div class="party-detail"><?= htmlspecialchars($pledge['sector']??'CSI Partner') ?></div>
    </div>
    <div class="party school">
      <div class="party-label">Beneficiary School</div>
      <div class="party-name"><?= htmlspecialchars($pledge['school_name']) ?></div>
      <div class="party-detail"><?= htmlspecialchars($pledge['province']) ?></div>
    </div>
  </div>

  <div class="amount-box">
    <div>
      <div class="label">Programme / Need</div>
      <div style="font-size:15px;font-weight:700;color:#fff"><?= htmlspecialchars($pledge['need_title']) ?></div>
      <div class="detail">Agreement Period: <?= $today ?> — <?= $end_date ?></div>
    </div>
    <div style="text-align:right">
      <div class="label">Pledged Amount</div>
      <div class="value">R<?= number_format($pledge['amount']) ?></div>
    </div>
  </div>

  <div class="section">
    <h2>1. Purpose</h2>
    <p class="clause" data-n="1.1">This Memorandum of Understanding (MOU) sets out the terms under which <?= htmlspecialchars($pledge['company_name']) ?> ("the Funder") will provide corporate social investment funding to <?= htmlspecialchars($pledge['school_name']) ?> ("the Beneficiary") through Research Unlimited as the implementing and monitoring agent.</p>
    <p class="clause" data-n="1.2">The funding is directed towards: <?= htmlspecialchars($pledge['need_title']) ?>.</p>
  </div>

  <div class="section">
    <h2>2. Roles and Responsibilities</h2>
    <p class="clause" data-n="2.1">The Funder agrees to disburse the pledged amount of R<?= number_format($pledge['amount']) ?> as agreed and coordinated by Research Unlimited.</p>
    <p class="clause" data-n="2.2">Research Unlimited will manage programme implementation, monitoring, site visits, and reporting on behalf of both parties.</p>
    <p class="clause" data-n="2.3">The Beneficiary School agrees to participate in all monitoring visits, data collection exercises, and to provide access to relevant records upon request.</p>
    <p class="clause" data-n="2.4">All parties agree to comply with applicable South African legislation including the Companies Act, SASA, and B-BBEE requirements.</p>
  </div>

  <div class="section">
    <h2>3. Reporting and Impact</h2>
    <p class="clause" data-n="3.1">Research Unlimited will deliver quarterly impact reports to the Funder, detailing learner and educator reach, programme milestones, and expenditure.</p>
    <p class="clause" data-n="3.2">An annual consolidated impact report will be provided for B-BBEE scorecard purposes within 30 days of year end.</p>
    <p class="clause" data-n="3.3">All beneficiary data will be handled in accordance with POPIA (Protection of Personal Information Act).</p>
  </div>

  <div class="section">
    <h2>4. Duration and Termination</h2>
    <p class="clause" data-n="4.1">This agreement is valid from <?= $today ?> to <?= $end_date ?>, unless terminated earlier by written notice from either party.</p>
    <p class="clause" data-n="4.2">Either party may terminate this agreement with 30 days' written notice. Unspent funds will be returned to the Funder.</p>
  </div>

  <div class="sigs">
    <div class="sig-block">
      <div class="line"></div>
      <div class="name"><?= htmlspecialchars($pledge['company_name']) ?></div>
      <div class="role">Authorised Signatory · Funder</div>
      <div class="role" style="margin-top:4px">Date: ___________________</div>
    </div>
    <div class="sig-block">
      <div class="line"></div>
      <div class="name">Research Unlimited</div>
      <div class="role">Authorised Signatory · Implementing Agent</div>
      <div class="role" style="margin-top:4px">Date: ___________________</div>
    </div>
  </div>

  <div class="footer">
    Research Unlimited · info@researchunlimitedsa.co.za · +27 68 024 5514 · researchunlimitedsa.co.za<br>
    100% Black-owned &amp; Female-owned · B-BBEE Level 1 Contributor · This document was generated by CSI Hub
  </div>
</div>
</body></html>