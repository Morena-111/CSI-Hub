<?php
$active_page = 'reports';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// ── YEAR FILTER ───────────────────────────────────────────────
$selected_year = (int)($_GET['year'] ?? date('Y'));
$years = $pdo->query("SELECT DISTINCT YEAR(start_date) AS yr FROM partnerships ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [date('Y')];

// ── LIVE DATA ─────────────────────────────────────────────────
$kpi = $pdo->prepare("
    SELECT COUNT(*) AS total, SUM(status='active') AS active,
           COALESCE(SUM(amount),0) AS total_committed,
           COALESCE(SUM(CASE WHEN status IN('active','completed') THEN amount ELSE 0 END),0) AS disbursed
    FROM partnerships WHERE YEAR(start_date) = ?
"); $kpi->execute([$selected_year]); $kpi = $kpi->fetch();

// Quarterly
$qdata = [1=>0,2=>0,3=>0,4=>0];
$qrows = $pdo->prepare("SELECT QUARTER(start_date) AS q, SUM(amount) AS total FROM partnerships WHERE YEAR(start_date)=? GROUP BY QUARTER(start_date)");
$qrows->execute([$selected_year]);
foreach ($qrows->fetchAll() as $q) $qdata[(int)$q['q']] = (float)$q['total'];
$max_q = max(array_values($qdata)) ?: 1;

// Focus areas
$focus_raw = $pdo->prepare("SELECT focus_area, COUNT(*) AS cnt, SUM(amount) AS total FROM partnerships WHERE YEAR(start_date)=? GROUP BY focus_area ORDER BY total DESC");
$focus_raw->execute([$selected_year]); $focus_raw = $focus_raw->fetchAll();
$focus_total = array_sum(array_column($focus_raw,'total')) ?: 1;

$focus_colors = ['STEM'=>'#E8541A','Digital Skills'=>'#7c6af5','Literacy'=>'#f5a623','Arts & Culture'=>'#f06292','Science'=>'#00c48c','Other'=>'#a0aec0'];

// Province spread
$provinces = $pdo->prepare("SELECT s.province, COUNT(DISTINCT p.id) AS partnerships, SUM(p.amount) AS total FROM partnerships p JOIN schools s ON s.id=p.school_id WHERE YEAR(p.start_date)=? GROUP BY s.province ORDER BY total DESC");
$provinces->execute([$selected_year]); $provinces = $provinces->fetchAll();

// Summary
$summary = $pdo->prepare("SELECT p.*, c.name AS company_name, s.name AS school_name FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id WHERE YEAR(p.start_date)=? ORDER BY p.amount DESC");
$summary->execute([$selected_year]); $summary = $summary->fetchAll();

function calcProgress($start,$end,$status){
    if($status==='completed') return 100;
    if($status==='pending')   return 0;
    $now=time();$s=strtotime($start);$e=strtotime($end);
    if($now>=$e) return 100; if($now<=$s) return 0;
    return round(($now-$s)/($e-$s)*100);
}
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Reports</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Reports</h1>
      <p>Performance metrics and CSI impact analysis — <?= $selected_year ?></p>
    </div>
    <div class="page-header-right">
      <!-- YEAR FILTER -->
      <div style="display:flex;align-items:center;gap:6px">
        <span style="font-size:12px;color:var(--text-muted)">Year:</span>
        <div style="display:flex;gap:4px">
          <?php foreach($years as $yr): ?>
            <a href="reports.php?year=<?= $yr ?>"
               style="padding:6px 14px;border-radius:7px;font-size:12.5px;font-weight:600;text-decoration:none;
                      <?= $yr==$selected_year ? 'background:var(--orange);color:white;' : 'background:var(--white);color:var(--text-muted);border:1px solid var(--border);' ?>">
              <?= $yr ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php permission_btn('Export PDF', can_edit(), 'ti-download', 'btn btn-navy', "window.print()") ?>
    </div>
  </div>

  <!-- KPI CARDS -->
  <div class="stats-row">
    <div class="stat-card orange">
      <div class="stat-label">Total Committed</div>
      <?php if(can_view_financials()): ?>
        <div class="stat-value orange">R<?= number_format($kpi['total_committed']/1000000,1) ?>M</div>
        <div class="stat-sub"><?= $kpi['total'] ?> partnerships</div>
      <?php else: ?>
        <div class="stat-value" style="font-size:18px;color:var(--text-muted)"><i class="ti ti-lock"></i></div>
        <div class="stat-sub">Admin access required</div>
      <?php endif; ?>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active Partnerships</div>
      <div class="stat-value teal"><?= $kpi['active'] ?></div>
      <div class="stat-sub">of <?= $kpi['total'] ?> total</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Focus Areas</div>
      <div class="stat-value purple"><?= count($focus_raw) ?></div>
      <div class="stat-sub">Programme areas</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Provinces</div>
      <div class="stat-value"><?= count($provinces) ?></div>
      <div class="stat-sub">Geographic spread</div>
    </div>
  </div>

  <!-- CHARTS -->
  <div style="display:grid;grid-template-columns:3fr 2fr;gap:18px;margin-bottom:20px">
    <div class="widget">
      <div class="widget-title"><i class="ti ti-chart-bar"></i> Funding by Quarter — <?= $selected_year ?> (R)</div>
      <div style="display:flex;align-items:flex-end;gap:18px;height:160px;padding:12px 0 0;position:relative">
        <div style="position:absolute;left:0;top:0;height:100%;display:flex;flex-direction:column;justify-content:space-between;pointer-events:none">
          <?php $top=ceil($max_q/100000)*100000; foreach([100,75,50,25,0] as $pct): $val=$top*$pct/100; ?>
            <span style="font-size:10px;color:var(--text-light);white-space:nowrap"><?= $val>0?'R'.number_format($val/1000).'k':'' ?></span>
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
      <div style="display:flex;gap:18px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:6px;font-size:11.5px"><div style="width:10px;height:10px;border-radius:2px;background:var(--orange)"></div> Committed</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:11.5px"><div style="width:10px;height:10px;border-radius:2px;background:var(--border)"></div> No data</div>
      </div>
    </div>

    <div class="widget">
      <div class="widget-title"><i class="ti ti-chart-donut"></i> Focus Area Breakdown</div>
      <?php if(empty($focus_raw)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:20px 0">No data for <?= $selected_year ?>.</p>
      <?php else:
        $circumference=2*M_PI*45; $offset=0; $svg='';
        foreach($focus_raw as $f){
          $color=$focus_colors[$f['focus_area']]??'#a0aec0';
          $slice=($f['total']/$focus_total)*$circumference;
          $svg.="<circle cx='60' cy='60' r='45' fill='none' stroke='{$color}' stroke-width='20' stroke-dasharray='{$slice} {$circumference}' stroke-dashoffset='-{$offset}' transform='rotate(-90 60 60)'/>";
          $offset+=$slice;
        }
      ?>
      <div style="display:flex;align-items:center;gap:22px;padding:12px 0">
        <svg width="120" height="120" viewBox="0 0 120 120" style="flex-shrink:0">
          <circle cx="60" cy="60" r="45" fill="none" stroke="#e8edf5" stroke-width="20"/>
          <?= $svg ?>
          <text x="60" y="57" text-anchor="middle" font-family="Poppins" font-size="10" font-weight="700" fill="#1a1f2e">Focus</text>
          <text x="60" y="69" text-anchor="middle" font-family="Poppins" font-size="9" fill="#6b7a99">Areas</text>
        </svg>
        <div style="display:flex;flex-direction:column;gap:9px">
          <?php foreach($focus_raw as $f): $pct=round($f['total']/$focus_total*100); $color=$focus_colors[$f['focus_area']]??'#a0aec0'; ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:12px">
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0"></div>
            <?= htmlspecialchars($f['focus_area']) ?> — <?= $pct ?>%
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        <div class="stat-label">Top Focus Area</div>
        <p style="font-size:12px;color:var(--text);line-height:1.6;margin-top:4px">
          <strong><?= htmlspecialchars($focus_raw[0]['focus_area']??'—') ?></strong> leads at
          <strong><?= round(($focus_raw[0]['total']??0)/$focus_total*100) ?>%</strong> of all CSI funding.
        </p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- PROVINCE BREAKDOWN -->
  <?php if(!empty($provinces)): ?>
  <div class="widget" style="margin-bottom:20px">
    <div class="widget-title"><i class="ti ti-map-pin"></i> Geographic Spread by Province</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;padding:4px 0">
      <?php foreach($provinces as $prov): ?>
      <div style="background:var(--surface);border-radius:10px;padding:14px 16px">
        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px"><?= htmlspecialchars($prov['province']) ?></div>
        <div style="font-size:12px;color:var(--text-muted)"><?= $prov['partnerships'] ?> partnership<?= $prov['partnerships']!=1?'s':'' ?></div>
        <?php if(can_view_financials()): ?><div style="font-size:13px;font-weight:700;color:var(--orange);margin-top:4px">R<?= number_format($prov['total']/1000)?>k</div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- SUMMARY TABLE -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-table"></i> Partnership Summary — <?= $selected_year ?></div>
    <?php if(empty($summary)): ?>
      <p style="color:var(--text-muted);font-size:13px;padding:10px 0">No partnerships for <?= $selected_year ?>.</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Company</th><th>School</th><th>Focus</th>
          <?php if(can_view_financials()): ?><th>Amount</th><?php endif; ?>
          <th>Period</th><th>Progress</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($summary as $r): $prog=calcProgress($r['start_date'],$r['end_date'],$r['status']); ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($r['company_name']) ?></td>
          <td><?= htmlspecialchars($r['school_name']) ?></td>
          <td><?= htmlspecialchars($r['focus_area']) ?></td>
          <?php if(can_view_financials()): ?><td style="font-weight:700;color:var(--orange)">R<?= number_format($r['amount']/1000)?>k</td><?php endif; ?>
          <td style="font-size:12px;color:var(--text-muted)"><?= date('M Y',strtotime($r['start_date'])) ?> – <?= date('M Y',strtotime($r['end_date'])) ?></td>
          <td style="min-width:120px">
            <div style="display:flex;align-items:center;gap:8px">
              <div class="progress-bar" style="flex:1;margin-bottom:0"><div class="progress-fill" style="width:<?= $prog ?>%"></div></div>
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

<style>
@media print {
  .topnav, .sidebar, .actions-bar, .page-banner, .page-header-right { display:none !important; }
  .layout { display:block !important; }
  .main { padding:0 !important; overflow:visible !important; }
  body { overflow:visible !important; }
  .widget { break-inside:avoid; }
}
</style>