<?php
$active_page = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$is_admin   = is_admin();
$user_type  = $_SESSION['user_type'] ?? 'general';
$linked_id  = (int)($_SESSION['linked_id'] ?? 0);
$is_company = (!$is_admin && $user_type === 'company');
$is_school  = (!$is_admin && $user_type === 'school');
$year_sel   = (int)($_GET['year'] ?? date('Y'));

// Initialize all dashboard variables to safe defaults
$stats           = [];
$quarters        = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
$provinces_data  = [];
$activity        = [];
$pending_signups = [];
$co_stats        = [];
$co_partnerships = [];
$co_quarters     = ['Q1'=>0,'Q2'=>0,'Q3'=>0,'Q4'=>0];
$sc_stats        = [];
$sc_needs        = [];
$sc_partners     = [];

// ── ADMIN STATS ───────────────────────────────────────────────
if ($is_admin) {
    $stats = [
        'partners'     => $pdo->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn(),
        'schools'      => $pdo->query("SELECT COUNT(*) FROM schools WHERE status='active'")->fetchColumn(),
        'active_p'     => $pdo->query("SELECT COUNT(*) FROM partnerships WHERE status='active'")->fetchColumn(),
        'pending_p'    => $pdo->query("SELECT COUNT(*) FROM partnerships WHERE status='pending'")->fetchColumn(),
        'total_value'  => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM partnerships WHERE status='active'")->fetchColumn(),
        'docs_pending' => $pdo->query("SELECT COUNT(*) FROM documents WHERE status='pending'")->fetchColumn(),
        'pledges_pend' => $pdo->query("SELECT COUNT(*) FROM need_pledges WHERE status='pending'")->fetchColumn(),
        'schools_reach'=> $pdo->query("SELECT COUNT(DISTINCT school_id) FROM partnerships")->fetchColumn(),
        'learners'     => $pdo->query("SELECT COALESCE(SUM(learners),0) FROM schools")->fetchColumn(),
        'educators'    => $pdo->query("SELECT COALESCE(SUM(educators),0) FROM schools")->fetchColumn(),
    ];

    // Quarterly data
    $quarters = [];
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $qmap = ['Q1'=>['01','03'],'Q2'=>['04','06'],'Q3'=>['07','09'],'Q4'=>['10','12']];
        $m = $qmap[$q];
        $v = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM partnerships p
            WHERE YEAR(p.start_date)=? AND MONTH(p.start_date) BETWEEN ? AND ?");
        $v->execute([$year_sel, $m[0], $m[1]]);
        $quarters[$q] = (float)$v->fetchColumn();
    }

    // Province distribution
    $provinces_data = $pdo->query("
        SELECT s.province, COUNT(DISTINCT p.id) AS cnt, COALESCE(SUM(p.amount),0) AS val
        FROM partnerships p JOIN schools s ON s.id=p.school_id
        GROUP BY s.province ORDER BY val DESC
    ")->fetchAll();

    // Recent activity
    $activity = $pdo->query("
        SELECT 'partnership' AS type, CONCAT(c.name,' → ',s.name) AS label, p.created_at AS dt
        FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id
        UNION ALL
        SELECT 'document', CONCAT(title,' by ',uploaded_by), created_at FROM documents
        UNION ALL
        SELECT 'pledge', CONCAT('Pledge R',FORMAT(amount,0),' from company ',company_id), created_at FROM need_pledges
        ORDER BY dt DESC LIMIT 8
    ")->fetchAll();

    // Pending signups
    $signups_file = __DIR__.'/data/pending_signups.json';
    $pending_signups = file_exists($signups_file)
        ? array_filter(json_decode(file_get_contents($signups_file),true)??[], fn($v)=>!($v['approved']??false))
        : [];
}

// ── COMPANY DASHBOARD ─────────────────────────────────────────
if ($is_company && $linked_id) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM partnerships WHERE company_id=? AND status='active'"); $q->execute([$linked_id]);
    $q2 = $pdo->prepare("SELECT COUNT(DISTINCT school_id) FROM partnerships WHERE company_id=?"); $q2->execute([$linked_id]);
    $q3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM partnerships WHERE company_id=?"); $q3->execute([$linked_id]);
    $q4 = $pdo->prepare("SELECT COUNT(*) FROM need_pledges WHERE company_id=? AND status='pending'"); $q4->execute([$linked_id]);
    $q5 = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE company_id=? AND status='pending'"); $q5->execute([$linked_id]);
    $co_stats = [
        'active_p'  => (int)$q->fetchColumn(),
        'schools'   => (int)$q2->fetchColumn(),
        'invested'  => (float)$q3->fetchColumn(),
        'pledges'   => (int)$q4->fetchColumn(),
        'docs_pend' => (int)$q5->fetchColumn(),
    ];
    $co_partnerships = $pdo->query("
        SELECT p.*, s.name AS school_name, s.province,
               DATEDIFF(p.end_date, CURDATE()) AS days_left
        FROM partnerships p JOIN schools s ON s.id=p.school_id
        WHERE p.company_id=$linked_id ORDER BY p.status, p.start_date DESC LIMIT 5
    ")->fetchAll();
    $co_quarters = [];
    foreach (['Q1','Q2','Q3','Q4'] as $q) {
        $qmap = ['Q1'=>['01','03'],'Q2'=>['04','06'],'Q3'=>['07','09'],'Q4'=>['10','12']];
        $m = $qmap[$q];
        $v = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM partnerships
            WHERE company_id=$linked_id AND YEAR(start_date)=$year_sel
            AND MONTH(start_date) BETWEEN {$m[0]} AND {$m[1]}")->fetchColumn();
        $co_quarters[$q] = (float)$v;
    }
}

// ── SCHOOL DASHBOARD ──────────────────────────────────────────
if ($is_school && $linked_id) {
    $s1=$pdo->prepare("SELECT COUNT(DISTINCT company_id) FROM partnerships WHERE school_id=? AND status='active'"); $s1->execute([$linked_id]);
    $s2=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM partnerships WHERE school_id=?"); $s2->execute([$linked_id]);
    $s3=$pdo->prepare("SELECT COUNT(*) FROM school_needs WHERE school_id=? AND status='open'"); $s3->execute([$linked_id]);
    $s4=$pdo->prepare("SELECT COUNT(*) FROM documents WHERE school_id=? AND status='pending'"); $s4->execute([$linked_id]);
    $s4t=$pdo->prepare("SELECT COUNT(*) FROM documents WHERE school_id=?"); $s4t->execute([$linked_id]);
    $s5=$pdo->prepare("SELECT COUNT(*) FROM need_pledges np JOIN school_needs sn ON sn.id=np.need_id WHERE sn.school_id=? AND np.status='pending'"); $s5->execute([$linked_id]);
    $s6=$pdo->prepare("SELECT * FROM school_needs WHERE school_id=? ORDER BY priority DESC, created_at DESC LIMIT 5"); $s6->execute([$linked_id]);
    $s7=$pdo->prepare("SELECT p.*, c.name AS company_name FROM partnerships p JOIN companies c ON c.id=p.company_id WHERE p.school_id=? ORDER BY p.status LIMIT 5"); $s7->execute([$linked_id]);
    $sc_stats = [
        'funders'     => (int)$s1->fetchColumn(),
        'funded'      => (float)$s2->fetchColumn(),
        'needs_open'  => (int)$s3->fetchColumn(),
        'docs_pend'   => (int)$s4->fetchColumn(),
        'docs_total'  => (int)$s4t->fetchColumn(),
        'pledges_pend'=> (int)$s5->fetchColumn(),
    ];
    $sc_needs    = $s6->fetchAll();
    $sc_partners = $s7->fetchAll();
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<!-- ══════════════ ADMIN DASHBOARD ══════════════ -->
<?php if ($is_admin): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--text)">Dashboard</h1>
    <p style="font-size:13px;color:var(--text-muted)">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> — <?= date('l, d F Y') ?></p>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:12px;color:var(--text-muted)">Year:</span>
    <?php foreach([date('Y'), date('Y')-1] as $yr): ?>
    <a href="?year=<?= $yr ?>" style="padding:6px 14px;border-radius:8px;font-size:12.5px;font-weight:700;text-decoration:none;
       background:<?= $yr==$year_sel?'var(--orange)':'var(--surface)' ?>;
       color:<?= $yr==$year_sel?'#fff':'var(--text)' ?>"><?= $yr ?></a>
    <?php endforeach; ?>
    <a href="export_report_pdf.php" class="btn btn-primary" style="font-size:12px;padding:7px 14px">
      <i class="ti ti-download"></i> Export PDF
    </a>
  </div>
</div>

<!-- Alerts -->
<?php if(!empty($pending_signups)): ?>
<div style="background:var(--gold-soft);border:1px solid #f5d67a;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px">
  <div style="display:flex;align-items:center;gap:9px;font-size:13px;color:#7a5800">
    <i class="ti ti-clock" style="font-size:17px"></i>
    <strong><?= count($pending_signups) ?> access request<?= count($pending_signups)!=1?'s':'' ?></strong> waiting for approval
  </div>
  <a href="team.php" class="btn btn-primary" style="font-size:12px;padding:6px 14px">Review →</a>
</div>
<?php endif; ?>
<?php if($stats['docs_pending']>0): ?>
<div style="background:var(--orange-soft);border:1px solid rgba(232,84,26,.2);border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px">
  <div style="display:flex;align-items:center;gap:9px;font-size:13px;color:#7a3a12">
    <i class="ti ti-file-alert" style="font-size:17px"></i>
    <strong><?= $stats['docs_pending'] ?> document<?= $stats['docs_pending']!=1?'s':'' ?></strong> pending review
  </div>
  <a href="documents.php" class="btn btn-primary" style="font-size:12px;padding:6px 14px">Review →</a>
</div>
<?php endif; ?>
<?php if($stats['pledges_pend']>0): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px">
  <div style="display:flex;align-items:center;gap:9px;font-size:13px;color:#054d36">
    <i class="ti ti-heart-handshake" style="font-size:17px"></i>
    <strong><?= $stats['pledges_pend'] ?> pledge<?= $stats['pledges_pend']!=1?'s':'' ?></strong> awaiting confirmation
  </div>
  <a href="pledge_management.php" class="btn btn-teal" style="font-size:12px;padding:6px 14px">Manage →</a>
</div>
<?php endif; ?>

<!-- Main stats -->
<div class="stats-row" style="margin-bottom:24px">
  <?php foreach([
    ['Partners',       $stats['partners'],           'var(--orange)', 'ti-building',          'Corporate funders'],
    ['Active Programmes',$stats['active_p'],          'var(--teal)',   'ti-activity',           'Currently running'],
    ['Total Investment','R'.number_format($stats['total_value']/1000000,1).'M','var(--gold)','ti-currency-rand','All partnerships'],
    ['Schools Reached', $stats['schools_reach'],      '#6c5ce7',      'ti-school',             'Beneficiary schools'],
    ['Learners',        number_format($stats['learners']), 'var(--teal)', 'ti-users',          'Total across schools'],
    ['Educators',       number_format($stats['educators']),'var(--orange)','ti-user-check',    'Total across schools'],
  ] as [$l,$v,$c,$ic,$s]): ?>
  <div class="stat-card" style="flex:1;min-width:150px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div class="stat-label"><?= $l ?></div>
      <i class="ti <?= $ic ?>" style="font-size:18px;color:<?= $c ?>;opacity:.6"></i>
    </div>
    <div class="stat-value" style="color:<?= $c ?>;font-size:22px"><?= $v ?></div>
    <div class="stat-sub"><?= $s ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Q1-Q4 Investment Chart -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="widget">
    <div class="widget-title"><i class="ti ti-chart-bar"></i> Quarterly Investment <?= $year_sel ?></div>
    <div style="display:flex;align-items:flex-end;gap:16px;height:140px;padding:8px 0">
    <?php
    $max_q = max(array_values($quarters)) ?: 1;
    foreach ($quarters as $qname => $qval):
      $pct = round($qval/$max_q*100);
      $colors = ['Q1'=>'var(--orange)','Q2'=>'var(--teal)','Q3'=>'#6c5ce7','Q4'=>'var(--gold)'];
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
      <div style="font-size:11px;font-weight:700;color:var(--text)">R<?= number_format($qval/1000) ?>k</div>
      <div style="flex:1;width:100%;display:flex;align-items:flex-end">
        <div style="width:100%;height:<?= max(6,$pct) ?>%;background:<?= $colors[$qname] ?>;border-radius:6px 6px 0 0;min-height:6px;transition:height .4s"></div>
      </div>
      <div style="font-size:11px;font-weight:700;color:<?= $colors[$qname] ?>"><?= $qname ?></div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Province grid -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-map-pin"></i> Investment by Province</div>
    <?php if(empty($provinces_data)): ?>
    <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px">No data yet.</p>
    <?php else:
    $max_prov = max(array_column($provinces_data,'val')) ?: 1;
    foreach($provinces_data as $prov): ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
        <span style="font-weight:500;color:var(--text)"><?= htmlspecialchars($prov['province']) ?></span>
        <span style="color:var(--text-muted)">R<?= number_format($prov['val']/1000) ?>k · <?= $prov['cnt'] ?> programme<?= $prov['cnt']!=1?'s':'' ?></span>
      </div>
      <div style="height:5px;background:var(--border);border-radius:5px;overflow:hidden">
        <div style="height:100%;width:<?= round($prov['val']/$max_prov*100) ?>%;background:var(--orange);border-radius:5px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Recent Activity -->
<div class="widget">
  <div class="widget-title"><i class="ti ti-activity"></i> Recent Activity</div>
  <?php if(empty($activity)): ?>
  <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px">No recent activity.</p>
  <?php else: ?>
  <?php foreach($activity as $a):
    $icons = ['partnership'=>'ti-heart-handshake','document'=>'ti-file-invoice','pledge'=>'ti-coin'];
    $colors = ['partnership'=>'var(--orange)','document'=>'var(--teal)','pledge'=>'var(--gold)'];
  ?>
  <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
    <div style="width:30px;height:30px;border-radius:8px;background:<?= $colors[$a['type']].'22' ?>;
                display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="ti <?= $icons[$a['type']] ?>" style="font-size:14px;color:<?= $colors[$a['type']] ?>"></i>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:12.5px;font-weight:500;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= htmlspecialchars($a['label']) ?>
      </div>
      <div style="font-size:11px;color:var(--text-muted)"><?= ucfirst($a['type']) ?> · <?= date('d M Y',strtotime($a['dt'])) ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ══════════════ COMPANY DASHBOARD ══════════════ -->
<?php elseif ($is_company && $linked_id): ?>
<div style="margin-bottom:24px">
  <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--text)">My CSI Dashboard</h1>
  <p style="font-size:13px;color:var(--text-muted)">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></p>
</div>

<div class="stats-row" style="margin-bottom:24px">
  <?php foreach([
    ['Active Programmes', $co_stats['active_p'],             'var(--orange)', 'ti-activity'],
    ['Schools Reached',   $co_stats['schools'],              'var(--teal)',   'ti-school'],
    ['Total Invested',    'R'.number_format($co_stats['invested']),'var(--gold)', 'ti-currency-rand'],
    ['Docs Approved',     ($co_stats['docs_total']??0) > 0 ? round((($co_stats['docs_total']-($co_stats['docs_pend']??0))/$co_stats['docs_total'])*100).'%' : '0%', 'var(--teal)', 'ti-file-check'],
  ] as [$l,$v,$c,$ic]): ?>
  <div class="stat-card" style="flex:1">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <div class="stat-label"><?= $l ?></div>
      <i class="ti <?= $ic ?>" style="font-size:18px;color:<?= $c ?>;opacity:.6"></i>
    </div>
    <div class="stat-value" style="color:<?= $c ?>"><?= $v ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Q1-Q4 for company -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px">
  <div class="widget">
    <div class="widget-title"><i class="ti ti-chart-bar"></i> My Quarterly Investment <?= $year_sel ?></div>
    <div style="display:flex;align-items:flex-end;gap:16px;height:120px;padding:8px 0">
    <?php
    $max_cq = max(array_values($co_quarters)) ?: 1;
    $qcols = ['Q1'=>'var(--orange)','Q2'=>'var(--teal)','Q3'=>'#6c5ce7','Q4'=>'var(--gold)'];
    foreach($co_quarters as $qn=>$qv): ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px">
      <div style="font-size:10px;font-weight:700;color:var(--text)">R<?= number_format($qv/1000) ?>k</div>
      <div style="flex:1;width:100%;display:flex;align-items:flex-end">
        <div style="width:100%;height:<?= max(4,round($qv/$max_cq*100)) ?>%;background:<?= $qcols[$qn] ?>;border-radius:4px 4px 0 0;min-height:4px"></div>
      </div>
      <div style="font-size:10px;font-weight:700;color:<?= $qcols[$qn] ?>"><?= $qn ?></div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Active programmes -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-activity"></i> Active Programmes</div>
    <?php if(empty($co_partnerships)): ?>
    <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px">No programmes yet. <a href="browse_schools.php" style="color:var(--orange)">Browse schools →</a></p>
    <?php else: foreach($co_partnerships as $cp):
      $prog = $cp['status']==='completed'?100:($cp['status']==='pending'?0:(time()>=strtotime($cp['end_date'])?100:round((time()-strtotime($cp['start_date']))/(strtotime($cp['end_date'])-strtotime($cp['start_date']))*100)));
    ?>
    <div style="padding:8px 0;border-bottom:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
        <div style="font-size:12.5px;font-weight:600;color:var(--text)"><?= htmlspecialchars($cp['school_name']) ?></div>
        <span class="status-badge <?= $cp['status'] ?>" style="font-size:10px"><?= ucfirst($cp['status']) ?></span>
      </div>
      <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:5px"><?= htmlspecialchars($cp['province']) ?> · R<?= number_format($cp['amount']) ?></div>
      <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden">
        <div style="height:100%;width:<?= $prog ?>%;background:<?= $prog>=100?'var(--teal)':'var(--orange)' ?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div style="display:flex;gap:12px;flex-wrap:wrap">
  <a href="schools.php" class="btn btn-primary"><i class="ti ti-school"></i> View Schools</a>
  <a href="programmes.php" class="btn btn-secondary"><i class="ti ti-activity"></i> View Programmes</a>
  <a href="documents.php" class="btn btn-secondary"><i class="ti ti-file"></i> My Documents</a>
</div>

<!-- ══════════════ SCHOOL DASHBOARD ══════════════ -->
<?php elseif ($is_school && $linked_id): ?>
<div style="margin-bottom:24px">
  <h1 style="font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--text)">My School Dashboard</h1>
  <p style="font-size:13px;color:var(--text-muted)">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></p>
</div>

<?php
// Check if compulsory survey is completed
$survey_done = false;
try {
    $sd = $pdo->prepare("SELECT COUNT(*) FROM survey_responses WHERE survey_id=10 AND respondent=?");
    $sd->execute([$_SESSION['name']??'']);
    $survey_done = (int)$sd->fetchColumn() > 0;
} catch(Exception $e) {}
if (!$survey_done): ?>
<div style="background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:11px">
    <div style="width:38px;height:38px;border-radius:9px;background:var(--orange);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="ti ti-clipboard-list" style="font-size:18px;color:white"></i>
    </div>
    <div>
      <div style="font-size:13px;font-weight:700;color:white;margin-bottom:2px">Action Required — Complete Your Needs Survey</div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">Help us understand what your school needs most. Takes 2 minutes.</div>
    </div>
  </div>
  <a href="surveys.php?take=10" class="btn" style="background:var(--orange);color:white;padding:9px 18px;font-size:12.5px;flex-shrink:0">
    <i class="ti ti-pencil"></i> Complete Survey
  </a>
</div>
<?php endif; ?>

<div class="stats-row" style="margin-bottom:24px">
  <?php foreach([
    ['Active Funders',   $sc_stats['funders'],              'var(--orange)', 'ti-building'],
    ['Total Funded',     'R'.number_format($sc_stats['funded']), 'var(--teal)', 'ti-currency-rand'],
    ['Open Needs',       $sc_stats['needs_open'],            '#6c5ce7',      'ti-heart-handshake'],
    ['Pending Pledges',  $sc_stats['pledges_pend'],          'var(--gold)',   'ti-coin'],
    ['Docs Approved',    ($sc_stats['docs_total']??0) > 0 ? round((($sc_stats['docs_total']-($sc_stats['docs_pend']??0))/$sc_stats['docs_total'])*100).'%' : '0%', 'var(--teal)', 'ti-file-check'],
  ] as [$l,$v,$c,$ic]): ?>
  <div class="stat-card" style="flex:1">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
      <div class="stat-label"><?= $l ?></div>
      <i class="ti <?= $ic ?>" style="font-size:18px;color:<?= $c ?>;opacity:.6"></i>
    </div>
    <div class="stat-value" style="color:<?= $c ?>"><?= $v ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px">
  <!-- My Needs -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-heart-handshake" style="color:var(--orange)"></i> My Funding Needs</div>
    <?php if(empty($sc_needs)): ?>
    <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px">No needs posted yet. <a href="school_needs.php" style="color:var(--orange)">Post a need →</a></p>
    <?php else: foreach($sc_needs as $n):
      $pct = $n['amount_needed']>0?min(100,round($n['amount_funded']/$n['amount_needed']*100)):0;
    ?>
    <div style="padding:8px 0;border-bottom:1px solid var(--border)">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:4px">
        <span><?= htmlspecialchars($n['title']) ?></span>
        <span style="color:var(--orange)"><?= $pct ?>%</span>
      </div>
      <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'var(--teal)':'var(--orange)' ?>;border-radius:4px"></div>
      </div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:3px">R<?= number_format($n['amount_funded']) ?> / R<?= number_format($n['amount_needed']) ?></div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- My Funders -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-building" style="color:var(--teal)"></i> My Funders</div>
    <?php if(empty($sc_partners)): ?>
    <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:20px">No funders yet.</p>
    <?php else: foreach($sc_partners as $sp): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
      <div style="width:30px;height:30px;border-radius:8px;background:var(--orange-soft);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">
        <?= strtoupper(substr($sp['company_name'],0,2)) ?>
      </div>
      <div style="flex:1">
        <div style="font-size:12.5px;font-weight:600;color:var(--text)"><?= htmlspecialchars($sp['company_name']) ?></div>
        <div style="font-size:11.5px;color:var(--text-muted)">R<?= number_format($sp['amount']) ?> · <?= ucfirst($sp['status']) ?></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div style="display:flex;gap:12px;flex-wrap:wrap">
  <a href="school_needs.php" class="btn btn-primary"><i class="ti ti-plus"></i> Post a Need</a>
  <a href="documents.php" class="btn btn-secondary"><i class="ti ti-file"></i> My Documents</a>
  <a href="programmes.php" class="btn btn-secondary"><i class="ti ti-activity"></i> Programmes</a>
</div>

<?php else: ?>
<!-- General user -->
<div style="text-align:center;padding:60px 20px">
  <i class="ti ti-layout-dashboard" style="font-size:48px;color:var(--orange);opacity:.3;display:block;margin-bottom:16px"></i>
  <h2 style="font-family:'Playfair Display',serif;font-size:24px;margin-bottom:8px">Welcome to CSI Hub</h2>
  <p style="color:var(--text-muted)">Your account is active. Contact admin to link you to a company or school profile.</p>
</div>
<?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>