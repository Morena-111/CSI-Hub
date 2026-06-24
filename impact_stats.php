<?php
$active_page = 'impact';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$success = ''; $error = '';

// ── HANDLE ADD ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'add_stat' && is_admin()) {
    $pid      = (int)($_POST['partnership_id']??0);
    $date     = $_POST['report_date']??'';
    $learners = (int)($_POST['learners']??0);
    $educators= (int)($_POST['educators']??0);
    $notes    = trim($_POST['notes']??'');
    $by       = current_user_name();
    if ($pid && $date) {
        $st = $pdo->prepare("INSERT INTO impact_stats (partnership_id,report_date,learners,educators,notes,recorded_by) VALUES (?,?,?,?,?,?)");
        $st->execute([$pid,$date,$learners,$educators,$notes,$by]);
        $success = 'Impact record added successfully.';
    } else { $error = 'Please select a partnership and date.'; }
}

// ── HANDLE DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'delete_stat' && is_admin()) {
    $pdo->prepare("DELETE FROM impact_stats WHERE id=?")->execute([(int)$_POST['stat_id']]);
    $success = 'Record deleted.';
}

// ── HANDLE EDIT ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'edit_stat' && is_admin()) {
    $st = $pdo->prepare("UPDATE impact_stats SET report_date=?,learners=?,educators=?,notes=? WHERE id=?");
    $st->execute([$_POST['report_date'],(int)$_POST['learners'],(int)$_POST['educators'],trim($_POST['notes']??''),(int)$_POST['stat_id']]);
    $success = 'Record updated.';
}

// ── FETCH DATA ───────────────────────────────────────────────
$partnerships = $pdo->query("
    SELECT p.id, c.name AS company, s.name AS school, p.focus_area
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    ORDER BY c.name, s.name
")->fetchAll();

// Filter by partnership
$filter_pid = (int)($_GET['pid']??0);

$stats_sql = "
    SELECT i.*, c.name AS company, s.name AS school, p.focus_area
    FROM impact_stats i
    JOIN partnerships p ON p.id=i.partnership_id
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
";
if ($filter_pid) {
    $st = $pdo->prepare($stats_sql . " WHERE i.partnership_id=? ORDER BY i.report_date DESC");
    $st->execute([$filter_pid]);
} else {
    $st = $pdo->query($stats_sql . " ORDER BY i.report_date DESC");
}
$stats = $st->fetchAll();

// Totals
$totals = $pdo->query("SELECT SUM(learners) AS tl, SUM(educators) AS te, COUNT(*) AS tr FROM impact_stats")->fetch();

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

  <div class="page-header">
    <div>
      <h1>Learner &amp; Educator Impact</h1>
      <p>Track how many learners and educators each partnership reaches</p>
    </div>
    <div class="page-header-right">
      <?php if(is_admin()): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <i class="ti ti-plus"></i> Add Record
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if($success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>
  <?php if($error): ?>
  <div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-alert-circle"></i><?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- SUMMARY STAT CARDS -->
  <div class="stats-row" style="margin-bottom:20px">
    <div class="stat-card orange">
      <div class="stat-label">Total Learners Reached</div>
      <div class="stat-value orange"><?= number_format($totals['tl']??0) ?></div>
      <div class="stat-sub">Across all partnerships</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Total Educators Reached</div>
      <div class="stat-value teal"><?= number_format($totals['te']??0) ?></div>
      <div class="stat-sub">Across all partnerships</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Reports Captured</div>
      <div class="stat-value purple"><?= $totals['tr']??0 ?></div>
      <div class="stat-sub">Impact records logged</div>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="widget" style="padding:14px 20px;margin-bottom:16px">
    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <label style="font-size:12px;font-weight:600;color:var(--text-muted)">Filter by Partnership:</label>
      <select name="pid" class="form-select" style="min-width:280px;padding:8px 12px;font-size:13px" onchange="this.form.submit()">
        <option value="">All Partnerships</option>
        <?php foreach($partnerships as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $filter_pid==$p['id']?'selected':'' ?>>
          <?= htmlspecialchars($p['company'].' → '.$p['school']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if($filter_pid): ?>
      <a href="impact_stats.php" class="btn btn-secondary" style="font-size:12px;padding:7px 14px">
        <i class="ti ti-x"></i> Clear
      </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- DATA TABLE -->
  <div class="widget">
    <div class="widget-title">
      <i class="ti ti-chart-bar"></i> Impact Records
      <span style="margin-left:8px;font-size:11px;font-weight:400;color:var(--text-muted)"><?= count($stats) ?> record<?= count($stats)!=1?'s':'' ?></span>
    </div>
    <?php if(empty($stats)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-muted)">
      <i class="ti ti-chart-bar" style="font-size:40px;opacity:.2;display:block;margin-bottom:10px"></i>
      <p style="font-size:13px">No impact records yet.</p>
      <?php if(is_admin()): ?>
      <button class="btn btn-primary" style="margin-top:12px" onclick="openModal('add-modal')">
        <i class="ti ti-plus"></i> Add first record
      </button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Partnership</th>
          <th>Focus Area</th>
          <th>Report Date</th>
          <th style="text-align:right">Learners</th>
          <th style="text-align:right">Educators</th>
          <th>Notes</th>
          <th>Recorded By</th>
          <?php if(is_admin()): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($stats as $s): ?>
        <tr>
          <td>
            <span style="font-weight:600;color:var(--text)"><?= htmlspecialchars($s['company']) ?></span>
            <span style="color:var(--text-muted)"> → </span>
            <?= htmlspecialchars($s['school']) ?>
          </td>
          <td><span class="status-badge" style="background:#f0eeff;color:#6c5ce7;border:none"><?= htmlspecialchars($s['focus_area']) ?></span></td>
          <td><?= date('d M Y', strtotime($s['report_date'])) ?></td>
          <td style="text-align:right;font-weight:700;color:var(--orange);font-size:13px"><?= number_format($s['learners']) ?></td>
          <td style="text-align:right;font-weight:700;color:var(--teal);font-size:13px"><?= number_format($s['educators']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($s['notes']??'—') ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($s['recorded_by']??'—') ?></td>
          <?php if(is_admin()): ?>
          <td>
            <button class="table-action-btn" title="Edit"
              onclick="openEdit(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
              <i class="ti ti-pencil"></i>
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this record?')">
              <input type="hidden" name="form" value="delete_stat">
              <input type="hidden" name="stat_id" value="<?= $s['id'] ?>">
              <button type="submit" class="table-action-btn btn-danger-icon" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</main>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Impact Record</h2>
    <div class="modal-sub">Record learner and educator numbers for a partnership report.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_stat">
      <div class="form-group">
        <label class="form-label">Partnership *</label>
        <select class="form-select" name="partnership_id" required>
          <option value="">Select partnership</option>
          <?php foreach($partnerships as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['company'].' → '.$p['school']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Report Date *</label>
        <input class="form-input" type="date" name="report_date" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Learners Reached</label>
          <input class="form-input" type="number" name="learners" min="0" value="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Educators Reached</label>
          <input class="form-input" type="number" name="educators" min="0" value="0" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-input" name="notes" rows="3" placeholder="Any observations, highlights or issues from this period…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Record</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target.id==='edit-modal')closeModal('edit-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit Impact Record</h2>
    <form method="POST">
      <input type="hidden" name="form" value="edit_stat">
      <input type="hidden" name="stat_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Report Date *</label>
        <input class="form-input" type="date" name="report_date" id="edit-date" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Learners Reached</label>
          <input class="form-input" type="number" name="learners" id="edit-learners" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Educators Reached</label>
          <input class="form-input" type="number" name="educators" id="edit-educators" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-input" name="notes" id="edit-notes" rows="3"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Update Record</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openEdit(s) {
  document.getElementById('edit-id').value       = s.id;
  document.getElementById('edit-date').value     = s.report_date;
  document.getElementById('edit-learners').value = s.learners;
  document.getElementById('edit-educators').value= s.educators;
  document.getElementById('edit-notes').value    = s.notes || '';
  openModal('edit-modal');
}
</script>

<?php include 'includes/footer.php'; ?>