<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$type      = $_GET['type'] ?? 'full';
$year      = (int)($_GET['year'] ?? date('Y'));
$school_id = (int)($_GET['school_id'] ?? 0);

// Data isolation
$linked_id = (int)($_SESSION['linked_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? '';
if (!is_admin() && $user_type === 'school') $school_id = $linked_id;
if (!is_admin() && $user_type === 'company' && !$school_id) {
    // Company sees their own schools
    $co_sq = $pdo->prepare("SELECT DISTINCT school_id FROM partnerships WHERE company_id=?"); $co_sq->execute([$linked_id]); $co_schools = $co_sq->fetchAll();
}

// Fetch data
$where_year = $year ? "AND YEAR(p.start_date) = $year" : "";
$where_school = $school_id ? "AND p.school_id = $school_id" : "";
if (!is_admin() && $user_type === 'company') {
    $where_school .= " AND p.company_id = $linked_id";
}

$partnerships = $pdo->query("
    SELECT p.*, c.name AS company_name, s.name AS school_name,
           s.province, s.learners, s.educators
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools s ON s.id=p.school_id
    WHERE 1=1 $where_year $where_school
    ORDER BY s.name, p.start_date DESC
")->fetchAll();

$impact = $pdo->query("
    SELECT i.*, s.name AS school_name, s.province,
           c.name AS company_name, p.focus_area, p.amount
    FROM impact_stats i
    JOIN partnerships p ON p.id=i.partnership_id
    JOIN schools s ON s.id=p.school_id
    JOIN companies c ON c.id=p.company_id
    WHERE i.year=$year $where_school
    ORDER BY i.quarter, s.name
")->fetchAll();

$milestones = $pdo->query("
    SELECT m.*, s.name AS school_name, c.name AS company_name
    FROM impact_milestones m
    JOIN partnerships p ON p.id=m.partnership_id
    JOIN schools s ON s.id=p.school_id
    JOIN companies c ON c.id=p.company_id
    WHERE m.year=$year $where_school
    ORDER BY m.status, s.name
")->fetchAll();

$total_learners  = array_sum(array_column($impact,'learners'));
$total_educators = array_sum(array_column($impact,'educators'));
$total_invested  = array_sum(array_column($partnerships,'amount'));
$schools_reached = count(array_unique(array_column($partnerships,'school_name')));

$school_name_title = $school_id ? ($partnerships[0]['school_name'] ?? 'School') : 'All Schools';
$report_no = 'RPT-'.$year.'-'.str_pad(rand(1,999),3,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>CSI Impact Report <?= $year ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',sans-serif;color:#1a1f2e;background:#fff;font-size:12px}
.page{max-width:820px;margin:0 auto;padding:40px 48px}
.header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px;padding-bottom:20px;border-bottom:3px solid #E8541A}
.org-name{font-size:16px;font-weight:700;color:#0d1e3d}
.org-sub{font-size:9px;color:#6b7a99;text-transform:uppercase;letter-spacing:.08em;margin-top:2px}
.report-meta{text-align:right;font-size:10px;color:#6b7a99}
.report-meta strong{display:block;font-size:13px;color:#E8541A;font-weight:700}
h1{font-family:'Playfair Display',serif;font-size:22px;color:#0d1e3d;margin-bottom:4px}
.subtitle{font-size:11px;color:#6b7a99;margin-bottom:24px}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.kpi{background:#f5f7fb;border-radius:8px;padding:14px;border-top:3px solid #E8541A;text-align:center}
.kpi.teal{border-top-color:#00c48c}
.kpi.purple{border-top-color:#6c5ce7}
.kpi.gold{border-top-color:#f5a623}
.kpi-value{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#0d1e3d}
.kpi-label{font-size:9px;color:#6b7a99;text-transform:uppercase;letter-spacing:.06em;margin-top:3px}
h2{font-size:12px;font-weight:700;color:#0d1e3d;text-transform:uppercase;letter-spacing:.06em;margin:20px 0 10px;padding-bottom:6px;border-bottom:1px solid #e8edf5}
table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:11px}
th{background:#0d1e3d;color:#fff;padding:7px 10px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
td{padding:7px 10px;border-bottom:1px solid #e8edf5}
tr:nth-child(even){background:#f9fafb}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600}
.badge.active{background:#e6faf5;color:#00956a}
.badge.pending{background:#fffbea;color:#9a6700}
.badge.completed{background:#f1f5f9;color:#64748b}
.badge.achieved{background:#e6faf5;color:#00956a}
.badge.in_progress{background:#fdf0ea;color:#E8541A}
.bar-wrap{background:#e8edf5;border-radius:4px;height:6px;width:100px;display:inline-block;vertical-align:middle}
.bar-fill{height:100%;border-radius:4px;background:#E8541A;display:block}
.q-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px}
.q-box{background:#f5f7fb;border-radius:8px;padding:12px;text-align:center}
.q-box .q-name{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6b7a99;margin-bottom:6px}
.q-box .q-val{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#0d1e3d}
.q-box .q-sub{font-size:9px;color:#6b7a99;margin-top:2px}
.footer{text-align:center;margin-top:32px;font-size:9.5px;color:#a0aec0;padding-top:14px;border-top:1px solid #e8edf5}
.bbbee-box{background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:10px;padding:16px 20px;margin-bottom:20px;color:#fff}
.bbbee-box h3{font-size:13px;font-weight:700;margin-bottom:8px}
.bbbee-box p{font-size:11px;color:rgba(255,255,255,.65);line-height:1.6}
.bbbee-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:10px}
.bbbee-item{background:rgba(255,255,255,.08);border-radius:6px;padding:10px;text-align:center}
.bbbee-item .val{font-size:16px;font-weight:700;color:#E8541A}
.bbbee-item .lbl{font-size:9px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
@media print{.no-print{display:none!important}.page{padding:20px}}
</style>
</head>
<body>
<div class="no-print" style="background:#0d1e3d;padding:10px 24px;display:flex;align-items:center;justify-content:space-between">
  <a href="javascript:history.back()" style="color:rgba(255,255,255,.6);text-decoration:none;font-size:12px">← Back</a>
  <button onclick="window.print()" style="background:#E8541A;color:white;border:none;padding:7px 18px;border-radius:7px;cursor:pointer;font-size:12px;font-weight:600">Print / Save PDF</button>
</div>

<div class="page">
  <!-- HEADER -->
  <div class="header">
    <div>
      <div class="org-name">Research Unlimited</div>
      <div class="org-sub">CSI Hub · Research Made Easy · B-BBEE Level 1</div>
    </div>
    <div class="report-meta">
      <span>Report Reference</span>
      <strong><?= $report_no ?></strong>
      <span>Generated: <?= date('d F Y') ?></span>
    </div>
  </div>

  <h1>CSI Impact Report — <?= $year ?></h1>
  <p class="subtitle"><?= htmlspecialchars($school_name_title) ?> · Prepared by Research Unlimited for B-BBEE Socio-Economic Development Compliance</p>

  <!-- KPI GRID -->
  <div class="kpi-grid">
    <div class="kpi">
      <div class="kpi-value"><?= number_format($total_learners) ?></div>
      <div class="kpi-label">Learners Reached</div>
    </div>
    <div class="kpi teal">
      <div class="kpi-value"><?= number_format($total_educators) ?></div>
      <div class="kpi-label">Educators Reached</div>
    </div>
    <div class="kpi purple">
      <div class="kpi-value"><?= $schools_reached ?></div>
      <div class="kpi-label">Schools Reached</div>
    </div>
    <div class="kpi gold">
      <div class="kpi-value">R<?= number_format($total_invested/1000000,1) ?>M</div>
      <div class="kpi-label">Total Invested</div>
    </div>
  </div>

  <!-- B-BBEE COMPLIANCE BOX -->
  <div class="bbbee-box">
    <h3>B-BBEE Socio-Economic Development Compliance Summary</h3>
    <p>This report confirms CSI investment qualifying as Socio-Economic Development (SED) spend under the B-BBEE Codes of Good Practice. All programmes were implemented and monitored by Research Unlimited, a 100% Black-owned and Female-owned entity (B-BBEE Level 1 Contributor).</p>
    <div class="bbbee-grid">
      <div class="bbbee-item"><div class="val">R<?= number_format($total_invested) ?></div><div class="lbl">SED Qualifying Spend</div></div>
      <div class="bbbee-item"><div class="val"><?= count($partnerships) ?></div><div class="lbl">Programmes Funded</div></div>
      <div class="bbbee-item"><div class="val">Level 1</div><div class="lbl">RU B-BBEE Rating</div></div>
    </div>
  </div>

  <!-- QUARTERLY BREAKDOWN -->
  <?php
  $q_totals = ['Q1'=>['l'=>0,'e'=>0],'Q2'=>['l'=>0,'e'=>0],'Q3'=>['l'=>0,'e'=>0],'Q4'=>['l'=>0,'e'=>0]];
  foreach($impact as $r) { if(isset($q_totals[$r['quarter']])) { $q_totals[$r['quarter']]['l'] += $r['learners']; $q_totals[$r['quarter']]['e'] += $r['educators']; } }
  ?>
  <h2>Quarterly Impact Breakdown</h2>
  <div class="q-grid">
    <?php foreach($q_totals as $qn=>$qd): ?>
    <div class="q-box">
      <div class="q-name"><?= $qn ?></div>
      <div class="q-val"><?= number_format($qd['l']) ?></div>
      <div class="q-sub">Learners · <?= number_format($qd['e']) ?> Educators</div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- PARTNERSHIPS TABLE -->
  <h2>Programme Details</h2>
  <?php if(empty($partnerships)): ?>
  <p style="color:#6b7a99;font-size:11.5px">No partnerships found for the selected period.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>School</th><th>Province</th><th>Company</th><th>Focus Area</th><th>Investment</th><th>Period</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($partnerships as $p): ?>
    <tr>
      <td><strong><?= htmlspecialchars($p['school_name']) ?></strong></td>
      <td><?= htmlspecialchars($p['province']) ?></td>
      <td><?= htmlspecialchars($p['company_name']) ?></td>
      <td><?= htmlspecialchars($p['focus_area']) ?></td>
      <td><strong>R<?= number_format($p['amount']) ?></strong></td>
      <td><?= date('M Y',strtotime($p['start_date'])) ?> – <?= date('M Y',strtotime($p['end_date'])) ?></td>
      <td><span class="badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- IMPACT RECORDS -->
  <?php if(!empty($impact)): ?>
  <h2>Impact Records by School</h2>
  <table>
    <thead><tr><th>School</th><th>Quarter</th><th>Learners</th><th>Educators</th><th>Focus</th><th>Investment</th></tr></thead>
    <tbody>
    <?php foreach($impact as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['school_name']) ?></td>
      <td style="font-weight:700;color:#E8541A"><?= $r['quarter'] ?></td>
      <td style="font-weight:700"><?= number_format($r['learners']) ?></td>
      <td style="font-weight:700"><?= number_format($r['educators']) ?></td>
      <td><?= htmlspecialchars($r['focus_area']) ?></td>
      <td>R<?= number_format($r['amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- MILESTONES -->
  <?php if(!empty($milestones)): ?>
  <h2>Programme Milestones</h2>
  <table>
    <thead><tr><th>School</th><th>Milestone</th><th>Target</th><th>Achieved</th><th>Quarter</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($milestones as $m):
      $pct = $m['target_value']>0?min(100,round($m['achieved_value']/$m['target_value']*100)):0;
    ?>
    <tr>
      <td><?= htmlspecialchars($m['school_name']) ?></td>
      <td><?= htmlspecialchars($m['title']) ?></td>
      <td><?= number_format($m['target_value']) ?></td>
      <td>
        <?= number_format($m['achieved_value']) ?>
        <span class="bar-wrap"><span class="bar-fill" style="width:<?= $pct ?>%"></span></span>
        <span style="font-size:10px;color:#6b7a99"><?= $pct ?>%</span>
      </td>
      <td style="font-weight:700;color:#E8541A"><?= $m['quarter'] ?></td>
      <td><span class="badge <?= str_replace('_','-',$m['status']) ?>"><?= ucfirst(str_replace('_',' ',$m['status'])) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="footer">
    Research Unlimited · info@researchunlimitedsa.co.za · +27 68 024 5514 · researchunlimitedsa.co.za<br>
    100% Black-owned &amp; Female-owned · B-BBEE Level 1 Contributor<br>
    This report was generated by CSI Hub on <?= date('d F Y \a\t H:i') ?>
  </div>
</div>
</body></html>