<?php
$active_page = 'partners';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$id = (int)($_GET['id']??0);
if (!$id) { header('Location: /csi-hub/partners.php'); exit; }

// page (schools need to see who their partners are; admin sees all).
if (!is_admin() && ($_SESSION['user_type']??'') === 'company'
    && isset($_SESSION['linked_id']) && (int)$_SESSION['linked_id'] !== $id) {
    header('Location: /csi-hub/partners.php'); exit;
}

$partner = $pdo->prepare("SELECT * FROM companies WHERE id=?");
$partner->execute([$id]); $partner = $partner->fetch();
if (!$partner) { header('Location: /csi-hub/partners.php'); exit; }

// All projects this partner has, grouped by school
$projects = $pdo->prepare("
    SELECT p.*, s.name AS school_name, s.province, s.location,
           DATEDIFF(p.end_date,CURDATE()) AS days_left
    FROM partnerships p
    JOIN schools s ON s.id=p.school_id
    WHERE p.company_id=?
    ORDER BY s.name, p.start_date DESC
");
$projects->execute([$id]); $projects = $projects->fetchAll();

// Group by school
$by_school = [];
foreach ($projects as $p) {
    $by_school[$p['school_name']][] = $p;
}

// Totals
$total_invested = array_sum(array_column($projects,'amount'));
$active_count   = count(array_filter($projects, fn($p)=>$p['status']==='active'));
$school_count   = count($by_school);

// Impact stats for this partner
$impact = $pdo->prepare("
    SELECT SUM(i.learners) AS learners, SUM(i.educators) AS educators, COUNT(*) AS reports
    FROM impact_stats i
    JOIN partnerships p ON p.id=i.partnership_id
    WHERE p.company_id=?
");
$impact->execute([$id]); $impact = $impact->fetch();

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <a href="partners.php" style="color:var(--text-muted)">Partners</a>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb"><?= htmlspecialchars($partner['name']) ?></span>
  </div>

  <div class="page-header">
    <div style="display:flex;align-items:center;gap:16px">
      <div style="width:52px;height:52px;border-radius:13px;background:var(--orange-soft);
                  color:var(--orange);display:flex;align-items:center;justify-content:center;
                  font-size:20px;font-weight:700">
        <?= strtoupper(substr($partner['name'],0,2)) ?>
      </div>
      <div>
        <h1><?= htmlspecialchars($partner['name']) ?></h1>
        <p><?= htmlspecialchars($partner['sector']??'') ?>
          <?php if($partner['since_year']): ?> · Since <?= htmlspecialchars($partner['since_year']) ?><?php endif; ?>
          · <span class="status-badge <?= $partner['status'] ?>"><?= ucfirst($partner['status']) ?></span>
        </p>
      </div>
    </div>
    <div class="page-header-right">
      <a href="partners.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>
      <?php if(is_admin()): ?>
      <a href="partnerships.php" class="btn btn-primary"><i class="ti ti-plus"></i> Add Project</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- PARTNER STATS -->
  <div class="stats-row">
    <div class="stat-card orange">
      <div class="stat-label">Total Projects</div>
      <div class="stat-value orange"><?= count($projects) ?></div>
      <div class="stat-sub"><?= $active_count ?> active</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Schools Reached</div>
      <div class="stat-value teal"><?= $school_count ?></div>
      <div class="stat-sub">Beneficiary schools</div>
    </div>
    <?php if(can_view_financials()): ?>
    <div class="stat-card purple">
      <div class="stat-label">Total Invested</div>
      <div class="stat-value purple">R<?= number_format($total_invested/1000000,2) ?>M</div>
      <div class="stat-sub">Across all projects</div>
    </div>
    <?php endif; ?>
    <div class="stat-card gold">
      <div class="stat-label">Learners Reached</div>
      <div class="stat-value"><?= number_format($impact['learners']??0) ?></div>
      <div class="stat-sub"><?= number_format($impact['educators']??0) ?> educators</div>
    </div>
  </div>

  <!-- PROJECTS GROUPED BY SCHOOL -->
  <?php if(empty($by_school)): ?>
  <div class="widget" style="text-align:center;padding:40px">
    <i class="ti ti-clipboard-x" style="font-size:36px;opacity:.2;display:block;margin-bottom:8px"></i>
    <p style="color:var(--text-muted);font-size:13px">No projects yet for this partner.</p>
  </div>
  <?php else: ?>
  <?php foreach($by_school as $school_name => $school_projects): ?>
  <div class="widget" style="margin-bottom:16px">
    <div class="widget-title" style="margin-bottom:12px">
      <i class="ti ti-school" style="color:var(--teal)"></i>
      <span><?= htmlspecialchars($school_name) ?></span>
      <span style="margin-left:6px;font-size:11px;font-weight:400;color:var(--text-muted)">
        <?= htmlspecialchars($school_projects[0]['province']??'') ?>
        <?php if($school_projects[0]['location']??''): ?>
          · <?= htmlspecialchars($school_projects[0]['location']) ?>
        <?php endif; ?>
      </span>
      <span style="margin-left:auto;font-size:11.5px;font-weight:400;color:var(--text-muted)">
        <?= count($school_projects) ?> project<?= count($school_projects)!=1?'s':'' ?>
      </span>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Focus Area</th>
          <?php if(can_view_financials()): ?><th>Amount</th><?php endif; ?>
          <th>Period</th>
          <th>Progress</th>
          <th>Days Left</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($school_projects as $proj):
          $start = strtotime($proj['start_date']);
          $end   = strtotime($proj['end_date']);
          $now   = time();
          $prog  = $proj['status']==='completed' ? 100 :
                   ($proj['status']==='pending'   ? 0   :
                   ($now>=$end ? 100 : ($now<=$start ? 0 :
                   round(($now-$start)/($end-$start)*100))));
        ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($proj['focus_area']) ?></td>
          <?php if(can_view_financials()): ?>
          <td style="font-weight:700;color:var(--orange)">R<?= number_format($proj['amount']/1000) ?>k</td>
          <?php endif; ?>
          <td style="font-size:12px;color:var(--text-muted)">
            <?= date('d M Y',strtotime($proj['start_date'])) ?> –
            <?= date('d M Y',strtotime($proj['end_date'])) ?>
          </td>
          <td style="min-width:120px">
            <div style="display:flex;align-items:center;gap:8px">
              <div class="progress-bar" style="flex:1;margin:0">
                <div class="progress-fill" style="width:<?= $prog ?>%"></div>
              </div>
              <span style="font-size:11px;font-weight:700;color:var(--orange)"><?= $prog ?>%</span>
            </div>
          </td>
          <td>
            <?php $dl = (int)$proj['days_left']; ?>
            <?php if($proj['status']==='completed'): ?>
              <span style="font-size:12px;color:var(--text-muted)">Completed</span>
            <?php elseif($dl<0): ?>
              <span style="font-size:11px;background:#fde9e9;color:#c53030;padding:2px 7px;border-radius:10px;font-weight:600">Overdue</span>
            <?php elseif($dl<=7): ?>
              <span style="font-size:11px;background:var(--gold-soft);color:#9a6700;padding:2px 7px;border-radius:10px;font-weight:600"><?= $dl ?>d left</span>
            <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted)"><?= $dl ?> days</span>
            <?php endif; ?>
          </td>
          <td><span class="status-badge <?= $proj['status'] ?>"><?= ucfirst($proj['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>