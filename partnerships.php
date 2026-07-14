<?php
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
$active_page = 'partnerships';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// PARTNER FILTER MODE
$filter_partner_id = (int)($_GET['partner_id'] ?? 0);
$viewing_partner    = null;

if ($filter_partner_id) {
    if (!is_admin() && ($_SESSION['user_type']??'') === 'company'
        && isset($_SESSION['linked_id']) && (int)$_SESSION['linked_id'] !== $filter_partner_id) {
        redirect('partnerships.php'); 
    }
    $pst = $pdo->prepare("SELECT * FROM companies WHERE id=?");
    $pst->execute([$filter_partner_id]);
    $viewing_partner = $pst->fetch();
    if (!$viewing_partner) { redirect('partnerships.php');  }
}

// HANDLE ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_partnership' && can_edit()) {
    $pdo->prepare("INSERT INTO partnerships (company_id, school_id, amount, focus_area, start_date, end_date, description, status) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            $_POST['company_id'], $_POST['school_id'],
            str_replace(['R','k','K',',',' '], ['','000','000','',''], $_POST['amount']),
            $_POST['focus_area'], $_POST['start_date'], $_POST['end_date'],
            trim($_POST['description'] ?? ''), $_POST['status'] ?? 'pending',
        ]);
    header('Location: partnerships.php?success=added'); exit;
}

// HANDLE DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_partnership' && is_admin()) {
    $pdo->prepare("DELETE FROM partnerships WHERE id = ?")->execute([$_POST['partnership_id']]);
    header('Location: partnerships.php?success=deleted'); exit;
}

// HANDLE STATUS UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_status' && can_edit()) {
    $pdo->prepare("UPDATE partnerships SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['partnership_id']]);
    header('Location: partnerships.php?success=updated'); exit;
}

// FETCH DATA
$pw_sql = "
    SELECT p.*, c.name AS company_name, c.sector,
           s.name AS school_name, s.province, s.location
    FROM partnerships p
    JOIN companies c ON c.id = p.company_id
    JOIN schools   s ON s.id = p.school_id
";
$pw_params = [];
if ($filter_partner_id) {
    $pw_sql .= " WHERE p.company_id = ?";
    $pw_params[] = $filter_partner_id;
}
$pw_sql .= " ORDER BY s.name, p.created_at DESC";
$pwst = $pdo->prepare($pw_sql);
$pwst->execute($pw_params);
$partnerships = $pwst->fetchAll();

// Impact stats for the partner being viewed
$viewing_impact = ['learners'=>0,'educators'=>0];
if ($filter_partner_id) {
    $ist = $pdo->prepare("
        SELECT COALESCE(SUM(i.learners),0) AS learners, COALESCE(SUM(i.educators),0) AS educators
        FROM impact_stats i JOIN partnerships p ON p.id=i.partnership_id
        WHERE p.company_id=?
    ");
    $ist->execute([$filter_partner_id]);
    $viewing_impact = $ist->fetch();
}

$companies_list = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$schools_list   = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();

// Stats
$total    = count($partnerships);
$active   = count(array_filter($partnerships, fn($p) => $p['status']==='active'));
$in_prog  = count(array_filter($partnerships, fn($p) => in_array($p['status'], ['active','pending'])));
$total_val = array_sum(array_column($partnerships,'amount'));

// Count unique schools across partnerships
$school_ids = array_unique(array_column($partnerships, 'school_id'));
$school_count = count($school_ids);

$success = $_GET['success'] ?? '';

function calcProgress($start, $end, $status) {
    if ($status === 'completed') return 100;
    if ($status === 'pending')   return 0;
    $now = time(); $s = strtotime($start); $e = strtotime($end);
    if ($now >= $e) return 100;
    if ($now <= $s) return 0;
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
    <?php if ($viewing_partner): ?>
      <a href="partnerships.php" style="color:var(--text-muted)">Partnerships</a>
      <span style="color:var(--border)">›</span>
      <span class="active-crumb"><?= htmlspecialchars($viewing_partner['name']) ?></span>
    <?php else: ?>
      <span class="active-crumb">Partnerships</span>
    <?php endif; ?>
  </div>

  <?php if ($viewing_partner): ?>
  <!-- PARTNER INFO BANNER — replaces the old partner_detail.php header -->
  <div class="widget" style="margin-bottom:18px">
    <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div style="width:48px;height:48px;border-radius:12px;background:var(--orange-soft);
                  color:var(--orange);display:flex;align-items:center;justify-content:center;
                  font-size:18px;font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($viewing_partner['name'],0,2)) ?>
      </div>
      <div style="flex:1;min-width:200px">
        <h2 style="font-family:'Playfair Display',serif;font-size:19px;font-weight:700;color:var(--text);margin-bottom:3px">
          <?= htmlspecialchars($viewing_partner['name']) ?>
        </h2>
        <p style="font-size:12.5px;color:var(--text-muted)">
          <?= htmlspecialchars($viewing_partner['sector']??'') ?>
          <?php if($viewing_partner['since_year']): ?> · Since <?= htmlspecialchars($viewing_partner['since_year']) ?><?php endif; ?>
          · <span class="status-badge <?= $viewing_partner['status'] ?>"><?= ucfirst($viewing_partner['status']) ?></span>
        </p>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div style="text-align:center;padding:8px 14px;background:var(--teal-soft);border-radius:9px">
          <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:var(--teal)"><?= number_format($viewing_impact['learners']) ?></div>
          <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Learners</div>
        </div>
        <div style="text-align:center;padding:8px 14px;background:var(--purple-soft);border-radius:9px">
          <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:var(--purple)"><?= number_format($viewing_impact['educators']) ?></div>
          <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase">Educators</div>
        </div>
      </div>
      <a href="partnerships.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> All Partnerships</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i>
    <?= $success==='added' ? 'Partnership added!' : ($success==='deleted' ? 'Partnership deleted.' : 'Partnership updated!') ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h1><?= $viewing_partner ? 'Projects' : 'Partnerships' ?></h1>
      <p><?= $viewing_partner
            ? 'All projects for ' . htmlspecialchars($viewing_partner['name']) . ', grouped by school'
            : 'List of partners in the CSI programme' ?></p>
    </div>
    <?php permission_btn('Add Partnership', can_edit(), 'ti-plus', 'btn btn-primary', "openModal('add-modal')") ?>
  </div>

  <!-- STATS -->
  <div class="stats-row" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card orange">
      <div class="stat-label">List of Partners</div>
      <div class="stat-value orange"><?= $total ?></div>
      <div class="stat-sub">All partnerships</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">In Progress</div>
      <div class="stat-value teal"><?= $in_prog ?></div>
      <div class="stat-sub">Active &amp; pending</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Cost Committed</div>
      <?php if (can_view_financials()): ?>
        <div class="stat-value">R<?= number_format($total_val/1000000,1) ?>M</div>
        <div class="stat-sub">All partnerships</div>
      <?php else: ?>
        <div class="stat-value" style="font-size:18px;color:var(--text-muted)"><i class="ti ti-lock"></i></div>
        <div class="stat-sub">Admin access required</div>
      <?php endif; ?>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Schools</div>
      <div class="stat-value"><?= $school_count ?></div>
      <div class="stat-sub">Schools reached</div>
    </div>
  </div>

  <!-- FILTERS & SEARCH -->
  <div class="actions-bar">
    <div style="position:relative">
      <button class="btn btn-secondary" onclick="toggleFilter('p-filter')" id="p-filter-btn">
        <i class="ti ti-filter"></i> Filter
      </button>
      <div id="p-filter" style="display:none;position:absolute;top:42px;left:0;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:16px;min-width:260px;box-shadow:0 8px 24px rgba(26,31,46,.1);z-index:100">
        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Filter by Status</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
          <?php foreach(['all'=>'All','active'=>'Active','pending'=>'Pending','completed'=>'Completed','paused'=>'Paused'] as $val=>$lbl): ?>
          <button onclick="filterByStatus('<?= $val ?>')" class="btn btn-secondary" style="font-size:11.5px;padding:4px 10px"><?= $lbl ?></button>
          <?php endforeach; ?>
        </div>
        <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px">Filter by Focus Area</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach(['STEM','Digital Skills','Literacy','Arts & Culture','Science','Other'] as $fa): ?>
          <button onclick="filterByFocus('<?= $fa ?>')" class="btn btn-secondary" style="font-size:11.5px;padding:4px 10px"><?= $fa ?></button>
          <?php endforeach; ?>
        </div>
        <button onclick="clearFilters()" class="btn btn-secondary" style="margin-top:12px;width:100%;justify-content:center;color:var(--orange)">
          <i class="ti ti-x"></i> Clear Filters
        </button>
      </div>
    </div>
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input class="filter-input" id="search-input" placeholder="Search company, school, focus…"
             oninput="filterTable('search-input','p-tbody')">
    </div>
  </div>

  <!-- TABLE -->
  <table class="data-table">
    <thead>
      <tr>
        <?php if (!$viewing_partner): ?><th>Company</th><?php endif; ?>
        <th>Industry</th>
        <th>Focus</th>
        <th>Schools</th>
        <?php if (can_view_financials()): ?><th>Cost Committed</th><?php endif; ?>
        <th>Period</th>
        <th>Progress</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="p-tbody">
      <?php foreach ($partnerships as $p):
        $progress = calcProgress($p['start_date'], $p['end_date'], $p['status']);
      ?>
      <tr>
        <?php if (!$viewing_partner): ?>
        <td>
          <span class="cell-name"><?= htmlspecialchars($p['company_name']) ?></span>
        </td>
        <?php endif; ?>
        <td style="color:var(--text-muted);font-size:12.5px"><?= htmlspecialchars($p['sector'] ?? '—') ?></td>
        <td><?= htmlspecialchars($p['focus_area']) ?></td>
        <td style="text-align:center">
          <span style="font-weight:600;color:var(--teal)">
            <a href="school_profile.php?id=<?= $p['school_id'] ?>" style="color:var(--teal);text-decoration:none">
              <?= htmlspecialchars($p['school_name']) ?>
            </a>
          </span>
        </td>
        <?php if (can_view_financials()): ?>
          <td style="font-weight:700;color:var(--orange)">R<?= number_format($p['amount']/1000)?>k</td>
        <?php endif; ?>
        <td style="font-size:12px;color:var(--text-muted)">
          <?= date('M Y', strtotime($p['start_date'])) ?> –<br>
          <?= date('M Y', strtotime($p['end_date'])) ?>
        </td>
        <td style="min-width:110px">
          <div style="display:flex;align-items:center;gap:7px">
            <div class="progress-bar" style="flex:1;margin-bottom:0">
              <div class="progress-fill" style="width:<?= $progress ?>%"></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:var(--orange)"><?= $progress ?>%</span>
          </div>
        </td>
        <td><span class="status-badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
        <td>
          <?= action_btn('ti-pencil','Edit Status', can_edit(), "openStatusModal({$p['id']},'{$p['status']}')") ?>
          <?= action_btn('ti-trash', 'Delete',      is_admin(), "deletePartnership({$p['id']})", 'btn-danger-icon') ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($partnerships)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:30px">No partnerships yet. Click Add Partnership to get started.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

</main>
</div>

<!-- ADD MODAL -->
<?php if (can_edit()): ?>
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>New Partnership</h2>
    <div class="modal-sub">Link a company with a school and define the CSI programme details.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_partnership">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Company *</label>
          <select class="form-select" name="company_id" required>
            <option value="">Select company</option>
            <?php foreach ($companies_list as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">School *</label>
          <select class="form-select" name="school_id" required>
            <option value="">Select school</option>
            <?php foreach ($schools_list as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cost (R) *</label>
          <input class="form-input" type="text" name="amount" placeholder="e.g. 500000" required>
        </div>
        <div class="form-group">
          <label class="form-label">Focus Area *</label>
          <select class="form-select" name="focus_area" required>
            <?php foreach(['STEM','Digital Skills','Literacy','Arts & Culture','Science','Other'] as $f): ?>
              <option><?= $f ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Start Date *</label>
          <input class="form-input" type="date" name="start_date" required>
        </div>
        <div class="form-group">
          <label class="form-label">End Date *</label>
          <input class="form-input" type="date" name="end_date" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description / Deliverables</label>
        <textarea class="form-input" name="description" rows="3" placeholder="What does this partnership deliver?"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="paused">Paused</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Partnership</button>
      </div>
    </form>
  </div>
</div>

<!-- STATUS UPDATE MODAL -->
<div class="modal-overlay" id="status-modal" onclick="if(event.target.id==='status-modal')closeModal('status-modal')">
  <div class="modal" style="max-width:360px">
    <button class="modal-close" onclick="closeModal('status-modal')"><i class="ti ti-x"></i></button>
    <h2>Update Status</h2>
    <form method="POST">
      <input type="hidden" name="form" value="update_status">
      <input type="hidden" name="partnership_id" id="status-id">
      <div class="form-group">
        <label class="form-label">New Status</label>
        <select class="form-select" name="status" id="status-select">
          <option value="pending">Pending</option>
          <option value="active">Active</option>
          <option value="paused">Paused</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('status-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Update</button>
      </div>
    </form>
  </div>
</div>

<form method="POST" id="delete-form">
  <input type="hidden" name="form" value="delete_partnership">
  <input type="hidden" name="partnership_id" id="delete-id">
</form>
<?php endif; ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openStatusModal(id, status) {
  document.getElementById('status-id').value     = id;
  document.getElementById('status-select').value = status;
  openModal('status-modal');
}
function deletePartnership(id) {
  if (confirm('Delete this partnership? This cannot be undone.')) {
    document.getElementById('delete-id').value = id;
    document.getElementById('delete-form').submit();
  }
}
function filterTable(inputId, tbodyId) {
  const q = document.getElementById(inputId).value.toLowerCase();
  document.querySelectorAll('#'+tbodyId+' tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
function toggleFilter(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function filterByStatus(status) {
  document.querySelectorAll('#p-tbody tr').forEach(r => {
    r.style.display = status === 'all' || r.textContent.toLowerCase().includes(status) ? '' : 'none';
  });
  document.getElementById('p-filter').style.display = 'none';
}
function filterByFocus(focus) {
  document.querySelectorAll('#p-tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(focus.toLowerCase()) ? '' : 'none';
  });
  document.getElementById('p-filter').style.display = 'none';
}
function clearFilters() {
  document.querySelectorAll('#p-tbody tr').forEach(r => r.style.display = '');
  document.getElementById('search-input').value = '';
  document.getElementById('p-filter').style.display = 'none';
}
document.addEventListener('click', function(e) {
  const btn = document.getElementById('p-filter-btn');
  const panel = document.getElementById('p-filter');
  if (panel && btn && !btn.contains(e.target) && !panel.contains(e.target)) {
    panel.style.display = 'none';
  }
});
</script>

<?php
// Show pledge status for company users
if (!is_admin() && ($_SESSION['user_type']??'')==='company' && $linked_id): 
    $my_pledges = $pdo->prepare("
        SELECT np.*, sn.title AS need_title, sn.amount_needed,
               s.name AS school_name, m.file_name AS mou_file
        FROM need_pledges np
        JOIN school_needs sn ON sn.id=np.need_id
        JOIN schools s ON s.id=sn.school_id
        LEFT JOIN mous m ON m.pledge_id=np.id
        WHERE np.company_id=?
        ORDER BY np.created_at DESC
    ");
    $my_pledges->execute([$linked_id]);
    $my_pledges = $my_pledges->fetchAll();
    if (!empty($my_pledges)): ?>
<div class="widget" style="margin-top:24px">
  <div class="widget-title"><i class="ti ti-heart-handshake" style="color:var(--orange)"></i> My Funding Pledges</div>
  <table class="data-table">
    <thead><tr><th>School</th><th>Need</th><th>Amount</th><th>Status</th><th>MOU</th></tr></thead>
    <tbody>
    <?php foreach($my_pledges as $pl):
      $sc = ['pending'=>['#fffbea','#9a6700'],'confirmed'=>['var(--teal-soft)','#00956a'],'declined'=>['#fde9e9','#c53030']][$pl['status']]??['var(--surface)','var(--text-muted)'];
    ?>
    <tr>
      <td class="cell-name"><?= htmlspecialchars($pl['school_name']) ?></td>
      <td style="font-size:12.5px"><?= htmlspecialchars($pl['need_title']) ?></td>
      <td style="font-weight:700;color:var(--orange)">R<?= number_format($pl['amount']) ?></td>
      <td>
        <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600">
          <?= ucfirst($pl['status']) ?>
        </span>
        <?php if($pl['status']==='pending'): ?>
        <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px">Awaiting Research Unlimited confirmation</div>
        <?php endif; ?>
      </td>
      <td>
        <?php if($pl['status']==='confirmed' && $pl['mou_file']): ?>
        <a href="mou_generate.php?pledge_id=<?= $pl['id'] ?>" class="btn btn-secondary" style="font-size:11px;padding:4px 10px">
          <i class="ti ti-file-certificate"></i> View MOU
        </a>
        <?php else: ?>
        <span style="font-size:11.5px;color:var(--text-muted)">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; endif; ?>

<?php include 'includes/footer.php'; ?>