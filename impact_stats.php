<?php
$active_page = 'impact';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$year_sel    = (int)($_GET['year']    ?? date('Y'));
$quarter_sel = $_GET['quarter'] ?? '';
$school_sel  = (int)($_GET['school_id'] ?? 0);
$linked_id   = (int)($_SESSION['linked_id'] ?? 0);
$is_school   = (!is_admin() && ($_SESSION['user_type']??'')==='school');
$is_company  = (!is_admin() && ($_SESSION['user_type']??'')==='company');

// Data isolation
if ($is_school && $linked_id) $school_sel = $linked_id;

// Handle adding impact record
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='add_impact' && (is_admin() || $is_school)) {
    $pdo->prepare("INSERT INTO impact_stats (partnership_id, report_date, learners, educators, notes, recorded_by, quarter, year, schools_reached, programmes_count)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $_POST['partnership_id'], $_POST['report_date'],
            (int)$_POST['learners'], (int)$_POST['educators'],
            trim($_POST['notes']??''), $_SESSION['name'],
            $_POST['quarter']??null, $_POST['year']??date('Y'),
            (int)($_POST['schools_reached']??0),
            (int)($_POST['programmes_count']??0),
        ]);
    header('Location: impact_stats.php?year='.$year_sel.'&success=1'); exit;
}

// Handle milestone
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='add_milestone' && is_admin()) {
    $pdo->prepare("INSERT INTO impact_milestones (partnership_id,school_id,title,description,target_value,milestone_type,status,due_date,quarter,year)
        VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $_POST['partnership_id'], $_POST['school_id'],
            trim($_POST['title']), trim($_POST['description']??''),
            (int)$_POST['target_value'], $_POST['milestone_type']??'other',
            'not_started', $_POST['due_date']??null,
            $_POST['quarter']??null, $year_sel
        ]);
    header('Location: impact_stats.php?year='.$year_sel.'&success=1'); exit;
}

// Build filters
$where = ['1=1']; $params = [];
if ($year_sel)    { $where[] = 'i.year = ?';               $params[] = $year_sel; }
if ($quarter_sel) { $where[] = 'i.quarter = ?';             $params[] = $quarter_sel; }
if ($school_sel)  { $where[] = 's.id = ?';                  $params[] = $school_sel; }
if ($is_company && $linked_id) { $where[] = 'p.company_id = ?'; $params[] = $linked_id; }

$where_sql = implode(' AND ', $where);

// Main impact data
$impact_rows = $pdo->prepare("
    SELECT i.*, p.focus_area, p.amount, p.status AS p_status,
           s.name AS school_name, s.province,
           c.name AS company_name
    FROM impact_stats i
    JOIN partnerships p ON p.id = i.partnership_id
    JOIN schools s ON s.id = p.school_id
    JOIN companies c ON c.id = p.company_id
    WHERE $where_sql
    ORDER BY i.year DESC, i.quarter DESC, i.report_date DESC
");
$impact_rows->execute($params);
$impact_rows = $impact_rows->fetchAll();

// Totals
$totals = [
    'learners'   => array_sum(array_column($impact_rows,'learners')),
    'educators'  => array_sum(array_column($impact_rows,'educators')),
    'schools'    => count(array_unique(array_column($impact_rows,'school_name'))),
    'programmes' => count(array_unique(array_column($impact_rows,'partnership_id'))),
    'invested'   => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM partnerships WHERE status='active'")->fetchColumn(),
];

// Q1-Q4 breakdown
$q_data = [];
foreach(['Q1','Q2','Q3','Q4'] as $q) {
    $rows = array_filter($impact_rows, fn($r)=>$r['quarter']===$q);
    $q_data[$q] = [
        'learners'  => array_sum(array_column(array_values($rows),'learners')),
        'educators' => array_sum(array_column(array_values($rows),'educators')),
        'count'     => count($rows),
    ];
}

// Milestones
$milestones_q = ['1=1']; $m_params = [];
if ($school_sel) { $milestones_q[] = 'm.school_id = ?'; $m_params[] = $school_sel; }
if ($year_sel)   { $milestones_q[] = 'm.year = ?';      $m_params[] = $year_sel; }
$milestones = $pdo->prepare("
    SELECT m.*, s.name AS school_name, c.name AS company_name, p.focus_area
    FROM impact_milestones m
    JOIN partnerships p ON p.id=m.partnership_id
    JOIN schools s ON s.id=m.school_id
    JOIN companies c ON c.id=p.company_id
    WHERE ".implode(' AND ',$milestones_q)."
    ORDER BY m.status, m.due_date ASC
");
$milestones->execute($m_params);
$milestones = $milestones->fetchAll();

// Schools list
$schools_list   = $pdo->query("SELECT id,name FROM schools ORDER BY name")->fetchAll();
$partners_list  = $pdo->query("SELECT p.id, c.name AS cname, s.name AS sname FROM partnerships p JOIN companies c ON c.id=p.company_id JOIN schools s ON s.id=p.school_id ORDER BY s.name")->fetchAll();
$success = isset($_GET['success']);

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Impact Stats</span>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:11px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check" style="font-size:17px"></i> Impact record saved successfully.
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1>Impact Statistics</h1>
    <p>Beneficiaries reached, milestones achieved and programme outcomes — across all partnerships</p>
  </div>
  <div class="page-header-right">
    <?php if(is_admin() || $is_school): ?>
    <button class="btn btn-primary" onclick="openModal('add-impact-modal')">
      <i class="ti ti-plus"></i> Add Impact Record
    </button>
    <?php endif; ?>
    <?php if(is_admin()): ?>
    <button class="btn btn-secondary" onclick="openModal('add-milestone-modal')">
      <i class="ti ti-flag"></i> Add Milestone
    </button>
    <a href="export_report_pdf.php?type=impact&year=<?= $year_sel ?>" class="btn btn-secondary">
      <i class="ti ti-download"></i> Export PDF
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;background:white;padding:14px 18px;border-radius:12px;border:1px solid var(--border)">
  <div style="font-size:12px;font-weight:700;color:var(--text-muted);display:flex;align-items:center;gap:6px;margin-right:4px">
    <i class="ti ti-filter"></i> FILTER:
  </div>
  <div style="display:flex;gap:6px">
    <?php foreach([date('Y'),date('Y')-1] as $yr): ?>
    <a href="?year=<?= $yr ?>&quarter=<?= $quarter_sel ?>&school_id=<?= $school_sel ?>"
       style="padding:5px 13px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;
              background:<?= $yr==$year_sel?'var(--orange)':'var(--surface)' ?>;
              color:<?= $yr==$year_sel?'#fff':'var(--text)' ?>"><?= $yr ?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:6px">
    <?php foreach([''=>'All Quarters','Q1'=>'Q1','Q2'=>'Q2','Q3'=>'Q3','Q4'=>'Q4'] as $qv=>$ql): ?>
    <a href="?year=<?= $year_sel ?>&quarter=<?= $qv ?>&school_id=<?= $school_sel ?>"
       style="padding:5px 13px;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;
              background:<?= $qv===$quarter_sel?'var(--navy)':'var(--surface)' ?>;
              color:<?= $qv===$quarter_sel?'#fff':'var(--text)' ?>"><?= $ql ?></a>
    <?php endforeach; ?>
  </div>
  <?php if(!$is_school): ?>
  <select class="form-select" style="width:auto;font-size:12px"
          onchange="window.location='?year=<?= $year_sel ?>&quarter=<?= $quarter_sel ?>&school_id='+this.value">
    <option value="0">All Schools</option>
    <?php foreach($schools_list as $sl): ?>
    <option value="<?= $sl['id'] ?>" <?= $sl['id']==$school_sel?'selected':'' ?>><?= htmlspecialchars($sl['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
</div>

<!-- TOTALS -->
<div class="stats-row" style="margin-bottom:24px">
  <?php foreach([
    ['Learners Reached',   number_format($totals['learners']),   'var(--orange)', 'ti-users'],
    ['Educators Reached',  number_format($totals['educators']),  'var(--teal)',   'ti-user-check'],
    ['Schools Reached',    $totals['schools'],                   '#6c5ce7',      'ti-school'],
    ['Programmes Tracked', $totals['programmes'],                'var(--gold)',   'ti-activity'],
    ['Total Invested',     'R'.number_format((float)$totals['invested']/1000000,1).'M', 'var(--orange)', 'ti-currency-rand'],
  ] as [$l,$v,$c,$ic]): ?>
  <div class="stat-card" style="flex:1">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div class="stat-label"><?= $l ?></div>
      <i class="ti <?= $ic ?>" style="font-size:18px;color:<?= $c ?>;opacity:.6"></i>
    </div>
    <div class="stat-value" style="color:<?= $c ?>"><?= $v ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Q1-Q4 breakdown -->
<div class="widget" style="margin-bottom:22px">
  <div class="widget-title"><i class="ti ti-chart-bar"></i> Quarterly Breakdown — <?= $year_sel ?></div>
  <div style="overflow-x:auto">
  <table class="data-table">
    <thead>
      <tr>
        <th>Quarter</th><th>Learners Reached</th><th>Educators Reached</th><th>Records</th><th>Bar</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $max_q = max(array_column($q_data,'learners'))?:1;
    $qcols = ['Q1'=>'var(--orange)','Q2'=>'var(--teal)','Q3'=>'#6c5ce7','Q4'=>'var(--gold)'];
    foreach($q_data as $qn=>$qd): ?>
    <tr>
      <td><span style="font-weight:700;color:<?= $qcols[$qn] ?>"><?= $qn ?></span></td>
      <td style="font-weight:600"><?= number_format($qd['learners']) ?></td>
      <td style="font-weight:600"><?= number_format($qd['educators']) ?></td>
      <td><?= $qd['count'] ?></td>
      <td style="width:200px">
        <div style="height:10px;background:var(--border);border-radius:5px;overflow:hidden;width:100%">
          <div style="height:100%;width:<?= $qd['learners']?round($qd['learners']/$max_q*100):0 ?>%;background:<?= $qcols[$qn] ?>;border-radius:5px;transition:width .4s"></div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- MILESTONES -->
<?php if(!empty($milestones)): ?>
<div class="widget" style="margin-bottom:22px">
  <div class="widget-title"><i class="ti ti-flag" style="color:var(--orange)"></i> Programme Milestones</div>
  <div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach($milestones as $m):
    $pct = $m['target_value']>0?min(100,round($m['achieved_value']/$m['target_value']*100)):0;
    $sc  = ['not_started'=>['var(--surface)','var(--text-muted)'],'in_progress'=>['var(--orange-soft)','var(--orange)'],'achieved'=>['var(--teal-soft)','var(--teal)'],'exceeded'=>['#f0fff4','#00956a']][$m['status']]??['var(--surface)','var(--text-muted)'];
  ?>
  <div style="border:1px solid var(--border);border-radius:10px;padding:14px;display:flex;align-items:center;gap:14px">
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
        <span style="font-size:13px;font-weight:700;color:var(--text)"><?= htmlspecialchars($m['title']) ?></span>
        <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px">
          <?= ucfirst(str_replace('_',' ',$m['status'])) ?>
        </span>
        <?php if($m['quarter']): ?><span style="font-size:10px;color:var(--text-muted)"><?= $m['quarter'] ?></span><?php endif; ?>
      </div>
      <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:8px">
        <?= htmlspecialchars($m['school_name']) ?> · <?= htmlspecialchars($m['company_name']) ?> · <?= htmlspecialchars($m['focus_area']) ?>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <div style="flex:1;height:6px;background:var(--border);border-radius:6px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $sc[1] ?>;border-radius:6px"></div>
        </div>
        <span style="font-size:11px;font-weight:700;color:<?= $sc[1] ?>"><?= $pct ?>%</span>
        <span style="font-size:11px;color:var(--text-muted)"><?= number_format($m['achieved_value']) ?>/<?= number_format($m['target_value']) ?></span>
      </div>
    </div>
    <?php if($m['due_date']): ?>
    <div style="text-align:center;flex-shrink:0">
      <div style="font-size:9.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Due</div>
      <div style="font-size:12px;font-weight:600;color:var(--text)"><?= date('d M Y',strtotime($m['due_date'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- IMPACT RECORDS TABLE -->
<div class="widget">
  <div class="widget-title"><i class="ti ti-list"></i> Impact Records
    <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px"><?= count($impact_rows) ?> records</span>
  </div>
  <?php if(empty($impact_rows)): ?>
  <p style="font-size:12.5px;color:var(--text-muted);text-align:center;padding:24px">No impact records for the selected filters.</p>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>School</th><th>Company</th><th>Focus</th><th>Quarter</th><th>Learners</th><th>Educators</th><th>Investment</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php foreach($impact_rows as $r): ?>
    <tr>
      <td class="cell-name"><?= htmlspecialchars($r['school_name']) ?><br><small style="color:var(--text-muted)"><?= htmlspecialchars($r['province']) ?></small></td>
      <td style="font-size:12.5px"><?= htmlspecialchars($r['company_name']) ?></td>
      <td style="font-size:12px"><?= htmlspecialchars($r['focus_area']) ?></td>
      <td><span style="font-weight:700;color:var(--orange)"><?= $r['quarter']??'—' ?></span></td>
      <td style="font-weight:700;color:var(--orange)"><?= number_format($r['learners']) ?></td>
      <td style="font-weight:700;color:var(--teal)"><?= number_format($r['educators']) ?></td>
      <td>R<?= number_format($r['amount']) ?></td>
      <td><span class="status-badge <?= $r['p_status'] ?>"><?= ucfirst($r['p_status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</main>
</div>

<!-- Add Impact Modal -->
<?php if(is_admin() || $is_school): ?>
<div class="modal-overlay" id="add-impact-modal" onclick="if(event.target.id==='add-impact-modal')closeModal('add-impact-modal')">
  <div class="modal" style="max-width:520px">
    <button class="modal-close" onclick="closeModal('add-impact-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Impact Record</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_impact">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Partnership *</label>
          <select class="form-select" name="partnership_id" required>
            <option value="">Select…</option>
            <?php foreach($partners_list as $pl): ?>
            <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['cname'].' → '.$pl['sname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Report Date *</label>
          <input class="form-input" type="date" name="report_date" value="<?= date('Y-m-d') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Quarter</label>
          <select class="form-select" name="quarter">
            <option value="">Select…</option>
            <?php foreach(['Q1','Q2','Q3','Q4'] as $q): ?><option><?= $q ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Year</label>
          <input class="form-input" type="number" name="year" value="<?= date('Y') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Learners Reached *</label>
          <input class="form-input" type="number" name="learners" min="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Educators Reached *</label>
          <input class="form-input" type="number" name="educators" min="0" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Schools Reached</label>
          <input class="form-input" type="number" name="schools_reached" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Programmes Count</label>
          <input class="form-input" type="number" name="programmes_count" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-input" name="notes" rows="2" placeholder="Key observations from this reporting period…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-impact-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Record</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Add Milestone Modal -->
<?php if(is_admin()): ?>
<div class="modal-overlay" id="add-milestone-modal" onclick="if(event.target.id==='add-milestone-modal')closeModal('add-milestone-modal')">
  <div class="modal" style="max-width:500px">
    <button class="modal-close" onclick="closeModal('add-milestone-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Programme Milestone</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_milestone">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Partnership *</label>
          <select class="form-select" name="partnership_id" required onchange="setSchool(this)">
            <option value="">Select…</option>
            <?php foreach($partners_list as $pl): ?>
            <option value="<?= $pl['id'] ?>" data-school="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['cname'].' → '.$pl['sname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">School *</label>
          <select class="form-select" name="school_id" id="milestone-school" required>
            <option value="">Select partnership first</option>
            <?php foreach($schools_list as $sl): ?><option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Milestone Title *</label>
        <input class="form-input" type="text" name="title" placeholder="e.g. 500 learners complete STEM module" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Type</label>
          <select class="form-select" name="milestone_type">
            <?php foreach(['learners','educators','schools','funding','programmes','other'] as $mt): ?>
            <option value="<?= $mt ?>"><?= ucfirst($mt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Target Value</label>
          <input class="form-input" type="number" name="target_value" min="0" placeholder="e.g. 500">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Quarter</label>
          <select class="form-select" name="quarter">
            <?php foreach(['Q1','Q2','Q3','Q4'] as $q): ?><option><?= $q ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Due Date</label>
          <input class="form-input" type="date" name="due_date">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="2" placeholder="What does achieving this milestone mean?"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-milestone-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-flag"></i> Add Milestone</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function setSchool(sel){
  const opt = sel.options[sel.selectedIndex];
  const sid = opt.getAttribute('data-school');
  if(sid) document.getElementById('milestone-school').value = sid;
}
</script>
<?php include 'includes/footer.php'; ?>