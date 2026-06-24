<?php
$active_page = 'dashboard';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ── DATA ISOLATION: scope data to user's linked entity ────────
$scope_company = null;
$scope_school  = null;
if (!is_admin()) {
    if (($_SESSION['user_type']??'') === 'company' && isset($_SESSION['linked_id'])) {
        $scope_company = (int)$_SESSION['linked_id'];
    } elseif (($_SESSION['user_type']??'') === 'school' && isset($_SESSION['linked_id'])) {
        $scope_school = (int)$_SESSION['linked_id'];
    }
}

// Build WHERE clause for partnerships
$p_where  = '1=1'; $p_params = [];
if ($scope_company) { $p_where .= ' AND p.company_id=?'; $p_params[] = $scope_company; }
if ($scope_school)  { $p_where .= ' AND p.school_id=?';  $p_params[] = $scope_school; }

// ── YEAR FILTER ───────────────────────────────────────────────
$selected_year = (int)($_GET['year'] ?? date('Y'));
$years_st = $pdo->query("SELECT DISTINCT YEAR(start_date) AS yr FROM partnerships ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years_st)) $years_st = [date('Y')];

// ── STATS ─────────────────────────────────────────────────────
$stats_st = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(p.status='active')   AS active,
           SUM(p.status='pending')  AS pending,
           COALESCE(SUM(p.amount),0) AS total_value
    FROM partnerships p WHERE {$p_where}
");
$stats_st->execute($p_params); $stats = $stats_st->fetch();

$school_count  = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
$partner_count = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$doc_count     = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

// Impact totals
$impact_st = $pdo->prepare("
    SELECT COALESCE(SUM(i.learners),0) AS learners, COALESCE(SUM(i.educators),0) AS educators
    FROM impact_stats i JOIN partnerships p ON p.id=i.partnership_id WHERE {$p_where}
");
$impact_st->execute($p_params); $impact = $impact_st->fetch();

// ── QUARTERLY CHART ───────────────────────────────────────────
$qdata = [1=>0,2=>0,3=>0,4=>0];
$q_p   = array_merge([$selected_year], $p_params);
$qst   = $pdo->prepare("SELECT QUARTER(start_date) AS q, SUM(amount) AS total
    FROM partnerships p WHERE YEAR(start_date)=? AND {$p_where} GROUP BY QUARTER(start_date)");
$qst->execute($q_p);
foreach ($qst->fetchAll() as $q) $qdata[(int)$q['q']] = (float)$q['total'];
$max_q = max(array_values($qdata)) ?: 1;

// ── FOCUS AREA BREAKDOWN ──────────────────────────────────────
$focus_st = $pdo->prepare("SELECT focus_area, COUNT(*) AS cnt, SUM(amount) AS total
    FROM partnerships p WHERE YEAR(start_date)=? AND {$p_where} GROUP BY focus_area ORDER BY total DESC");
$focus_st->execute($q_p); $focus_raw = $focus_st->fetchAll();
$focus_total = array_sum(array_column($focus_raw,'total')) ?: 1;
$focus_colors = ['STEM'=>'#E8541A','Digital Skills'=>'#7c6af5','Literacy'=>'#f5a623',
                 'Arts & Culture'=>'#f06292','Science'=>'#00c48c','Other'=>'#a0aec0'];

// ── PROVINCE SPREAD ───────────────────────────────────────────
$prov_st = $pdo->prepare("SELECT s.province, COUNT(DISTINCT p.id) AS cnt, SUM(p.amount) AS total
    FROM partnerships p JOIN schools s ON s.id=p.school_id WHERE {$p_where} GROUP BY s.province ORDER BY total DESC");
$prov_st->execute($p_params); $provinces = $prov_st->fetchAll();

// ── RECENT PARTNERSHIPS ───────────────────────────────────────
$rec_st = $pdo->prepare("SELECT p.*, c.name AS company_name, s.name AS school_name
    FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id
    WHERE {$p_where} ORDER BY p.created_at DESC LIMIT 8");
$rec_st->execute($p_params); $recent = $rec_st->fetchAll();

// ── UPCOMING EVENTS ───────────────────────────────────────────
$events = $pdo->query("SELECT * FROM events WHERE status='upcoming' AND event_date>=CURDATE() ORDER BY event_date ASC LIMIT 5")->fetchAll();

// ── EXPIRING ─────────────────────────────────────────────────
$exp_st = $pdo->prepare("SELECT p.*, c.name AS company_name, s.name AS school_name
    FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id
    WHERE p.status='active' AND {$p_where}
    AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)");
$exp_st->execute($p_params); $expiring = $exp_st->fetchAll();

// ── PARTNERSHIP SUMMARY TABLE (year-filtered) ─────────────────
$sum_p = array_merge([$selected_year], $p_params);
$sum_st= $pdo->prepare("SELECT p.*, c.name AS company_name, s.name AS school_name
    FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id
    WHERE YEAR(p.start_date)=? AND {$p_where} ORDER BY p.amount DESC");
$sum_st->execute($sum_p); $summary = $sum_st->fetchAll();

function calcProg($s,$e,$st){
    if($st==='completed') return 100; if($st==='pending') return 0;
    $now=time();$s=strtotime($s);$e=strtotime($e);
    if($now>=$e) return 100; if($now<=$s) return 0;
    return round(($now-$s)/($e-$s)*100);
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h1>Dashboard</h1>
      <p>Welcome back, <?= current_user_name() ?> — <?= date('l, d F Y') ?></p>
    </div>
    <div class="page-header-right">
      <!-- Year filter -->
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--text-muted)">Year:</span>
        <?php foreach($years_st as $yr): ?>
        <a href="dashboard.php?year=<?= $yr ?>"
           style="padding:5px 12px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;
           <?= $yr==$selected_year ? 'background:var(--orange);color:white;' : 'background:var(--white);color:var(--text-muted);border:1px solid var(--border);' ?>">
          <?= $yr ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if(is_admin()): ?>
      <a href="export_report_pdf.php?year=<?= $selected_year ?>" class="btn btn-navy" target="_blank">
        <i class="ti ti-download"></i> Export PDF
      </a>
      <a href="partnerships.php" class="btn btn-primary">
        <i class="ti ti-plus"></i> New Partnership
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ROLE BADGE -->
  <?php if(is_admin()): ?>
  <div style="display:inline-flex;align-items:center;gap:6px;background:var(--orange-soft);color:var(--orange);font-size:11.5px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:20px">
    <i class="ti ti-shield-check"></i> Admin — Full Access
  </div>
  <?php else: ?>
  <div style="display:inline-flex;align-items:center;gap:6px;background:var(--teal-soft);color:#00956a;font-size:11.5px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:20px">
    <i class="ti ti-eye"></i> <?= ucfirst($_SESSION['user_type']??'User') ?> — <?= current_user_name() ?>
  </div>
  <?php endif; ?>

  <!-- EXPIRING ALERT -->
  <?php if(!empty($expiring)): ?>
  <div style="background:#fffbea;border:1px solid #f6e05e;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px">
    <i class="ti ti-alert-triangle" style="color:#b7791f;font-size:18px;flex-shrink:0"></i>
    <div>
      <strong style="color:#7b341e"><?= count($expiring) ?> partnership<?= count($expiring)!=1?'s':'' ?> expiring within 30 days:</strong>
      <?php foreach($expiring as $ep): ?>
        <span style="margin-left:8px;color:#7b341e"><?= htmlspecialchars($ep['company_name']) ?> → <?= htmlspecialchars($ep['school_name']) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- KPI CARDS -->
  <div class="stats-row">
    <div class="stat-card orange">
      <div class="stat-label">Total Partnerships</div>
      <div class="stat-value orange"><?= $stats['total'] ?></div>
      <div class="stat-sub"><?= $stats['pending'] ?> pending</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active Now</div>
      <div class="stat-value teal"><?= $stats['active'] ?></div>
      <div class="stat-sub">Currently running</div>
    </div>
    <?php if(can_view_financials()): ?>
    <div class="stat-card purple">
      <div class="stat-label">Total Value</div>
      <div class="stat-value purple">R<?= number_format($stats['total_value']/1000000,1) ?>M</div>
      <div class="stat-sub">All partnerships</div>
    </div>
    <?php endif; ?>
    <div class="stat-card gold">
      <div class="stat-label">Schools Reached</div>
      <div class="stat-value"><?= $school_count ?></div>
      <div class="stat-sub"><?= $partner_count ?> partners · <?= $doc_count ?> docs</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Learners</div>
      <div class="stat-value orange"><?= number_format($impact['learners']) ?></div>
      <div class="stat-sub"><?= number_format($impact['educators']) ?> educators</div>
    </div>
  </div>

  <!-- CHARTS ROW -->
  <div style="display:grid;grid-template-columns:3fr 2fr;gap:18px;margin-bottom:20px">

    <!-- Quarterly bar chart -->
    <div class="widget">
      <div class="widget-title"><i class="ti ti-chart-bar"></i> Funding by Quarter — <?= $selected_year ?></div>
      <div style="display:flex;align-items:flex-end;gap:18px;height:160px;padding:12px 0 0;position:relative">
        <div style="position:absolute;left:0;top:0;height:100%;display:flex;flex-direction:column;justify-content:space-between">
          <?php $top=ceil($max_q/100000)*100000; foreach([100,75,50,25,0] as $pct): $val=$top*$pct/100; ?>
            <span style="font-size:10px;color:var(--text-light)"><?= $val>0?'R'.number_format($val/1000).'k':'' ?></span>
          <?php endforeach; ?>
        </div>
        <div style="flex:1;display:flex;align-items:flex-end;gap:14px;padding-left:36px;height:100%">
          <?php foreach($qdata as $qnum=>$val): $pct=$max_q>0?round($val/$max_q*100):0; ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
            <span style="font-size:10.5px;font-weight:700;color:var(--orange)"><?= $val>0?'R'.number_format($val/1000).'k':'—' ?></span>
            <div style="width:100%;height:<?= max($pct,2) ?>%;background:<?= $val>0?'var(--orange)':'var(--border)' ?>;border-radius:4px 4px 0 0"></div>
            <span style="font-size:11px;color:var(--text-muted);margin-top:4px">Q<?= $qnum ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Focus area donut -->
    <div class="widget">
      <div class="widget-title"><i class="ti ti-chart-donut"></i> Focus Area Breakdown</div>
      <?php if(empty($focus_raw)): ?>
        <p style="color:var(--text-muted);font-size:13px">No data for <?= $selected_year ?>.</p>
      <?php else:
        $circumference=2*M_PI*45; $offset=0; $svg='';
        foreach($focus_raw as $f){
          $color=$focus_colors[$f['focus_area']]??'#a0aec0';
          $slice=($f['total']/$focus_total)*$circumference;
          $svg.="<circle cx='60' cy='60' r='45' fill='none' stroke='{$color}' stroke-width='20' stroke-dasharray='{$slice} {$circumference}' stroke-dashoffset='-{$offset}' transform='rotate(-90 60 60)'/>";
          $offset+=$slice;
        }
      ?>
      <div style="display:flex;align-items:center;gap:18px">
        <svg width="120" height="120" viewBox="0 0 120 120" style="flex-shrink:0">
          <circle cx="60" cy="60" r="45" fill="none" stroke="var(--border)" stroke-width="20"/>
          <?= $svg ?>
        </svg>
        <div style="display:flex;flex-direction:column;gap:7px">
          <?php foreach($focus_raw as $f): $pct=round($f['total']/$focus_total*100); $color=$focus_colors[$f['focus_area']]??'#a0aec0'; ?>
          <div style="display:flex;align-items:center;gap:7px;font-size:12px">
            <div style="width:9px;height:9px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></div>
            <?= htmlspecialchars($f['focus_area']) ?> — <?= $pct ?>%
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- PROVINCE GRID -->
  <?php if(!empty($provinces)): ?>
  <div class="widget" style="margin-bottom:20px">
    <div class="widget-title"><i class="ti ti-map-pin"></i> Province Spread</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px">
      <?php foreach($provinces as $prov): ?>
      <div style="background:var(--surface);border-radius:9px;padding:12px 14px">
        <div style="font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:3px"><?= htmlspecialchars($prov['province']) ?></div>
        <div style="font-size:11.5px;color:var(--text-muted)"><?= $prov['cnt'] ?> partnership<?= $prov['cnt']!=1?'s':'' ?></div>
        <?php if(can_view_financials()): ?><div style="font-size:13px;font-weight:700;color:var(--orange);margin-top:3px">R<?= number_format($prov['total']/1000) ?>k</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- BOTTOM GRID: recent partnerships + events -->
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:20px">
    <div class="widget">
      <div class="widget-title"><i class="ti ti-activity"></i> Recent Partnerships
        <a href="partnerships.php" style="margin-left:auto;font-size:11px;color:var(--orange);font-weight:500">View all →</a>
      </div>
      <?php foreach($recent as $r): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
        <div style="width:7px;height:7px;border-radius:50%;flex-shrink:0;background:<?= $r['status']==='active'?'var(--teal)':($r['status']==='pending'?'var(--gold)':'var(--text-light)') ?>"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12.5px;font-weight:600;color:var(--text)">
            <?= htmlspecialchars($r['company_name']) ?> → <?= htmlspecialchars($r['school_name']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($r['focus_area']) ?>
            <?php if(can_view_financials()): ?> · R<?= number_format($r['amount']/1000) ?>k<?php endif; ?>
          </div>
        </div>
        <span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="widget">
      <div class="widget-title"><i class="ti ti-calendar-event" style="color:var(--orange)"></i> Upcoming Events
        <a href="events.php" style="margin-left:auto;font-size:11px;color:var(--orange);font-weight:500">View all →</a>
      </div>
      <?php if(empty($events)): ?>
      <p style="color:var(--text-muted);font-size:12.5px;padding:10px 0">No upcoming events.</p>
      <?php else: foreach($events as $ev): $days=ceil((strtotime($ev['event_date'])-time())/86400); ?>
      <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--border)">
        <div style="text-align:center;min-width:34px">
          <div style="font-size:18px;font-weight:700;color:var(--orange);font-family:'Playfair Display',serif;line-height:1"><?= date('d',strtotime($ev['event_date'])) ?></div>
          <div style="font-size:9px;color:var(--text-light);text-transform:uppercase"><?= date('M',strtotime($ev['event_date'])) ?></div>
        </div>
        <div>
          <div style="font-size:12.5px;font-weight:600;color:var(--text)"><?= htmlspecialchars($ev['title']) ?></div>
          <?php if($days<=3): ?>
          <span style="font-size:10px;background:var(--orange-soft);color:var(--orange);padding:1px 6px;border-radius:10px;font-weight:600">In <?= $days ?> day<?= $days!=1?'s':'' ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- FULL PARTNERSHIP SUMMARY TABLE -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-table"></i> Partnership Summary — <?= $selected_year ?></div>
    <?php if(empty($summary)): ?>
      <p style="color:var(--text-muted);font-size:13px">No data for <?= $selected_year ?>.</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Partner</th><th>School</th><th>Focus</th>
          <?php if(can_view_financials()): ?><th>Amount</th><?php endif; ?>
          <th>Period</th><th>Progress</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($summary as $r): $prog=calcProg($r['start_date'],$r['end_date'],$r['status']); ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($r['company_name']) ?></td>
          <td><?= htmlspecialchars($r['school_name']) ?></td>
          <td><?= htmlspecialchars($r['focus_area']) ?></td>
          <?php if(can_view_financials()): ?><td style="font-weight:700;color:var(--orange)">R<?= number_format($r['amount']/1000) ?>k</td><?php endif; ?>
          <td style="font-size:12px;color:var(--text-muted)"><?= date('M Y',strtotime($r['start_date'])) ?> – <?= date('M Y',strtotime($r['end_date'])) ?></td>
          <td style="min-width:120px">
            <div style="display:flex;align-items:center;gap:7px">
              <div class="progress-bar" style="flex:1;margin:0"><div class="progress-fill" style="width:<?= $prog ?>%"></div></div>
              <span style="font-size:11px;font-weight:700;color:var(--orange)"><?= $prog ?>%</span>
            </div>
          </td>
          <td><span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>
</div>
<?php include 'includes/footer.php'; ?>