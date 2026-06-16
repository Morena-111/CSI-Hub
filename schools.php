<?php
$active_page = 'schools';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// ── HANDLE ADD ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_school' && can_edit()) {
    $pdo->prepare("INSERT INTO schools (name, location, province, district, school_type, funding_requested, funding_granted, status) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            trim($_POST['name']),
            trim($_POST['location']),
            $_POST['province'],
            $_POST['district'] ?? '',
            $_POST['school_type'] ?? 'Secondary',
            (float)str_replace([',','R',' '],'',$_POST['funding_requested'] ?? 0),
            (float)str_replace([',','R',' '],'',$_POST['funding_granted'] ?? 0),
            $_POST['status'] ?? 'active',
        ]);
    header('Location: schools.php?success=added'); exit;
}

// ── HANDLE DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_school' && is_admin()) {
    $pdo->prepare("DELETE FROM schools WHERE id=?")->execute([$_POST['school_id']]);
    header('Location: schools.php?success=deleted'); exit;
}

// ── HANDLE EDIT ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_school' && can_edit()) {
    $pdo->prepare("UPDATE schools SET name=?, location=?, province=?, district=?, school_type=?, funding_requested=?, funding_granted=?, status=? WHERE id=?")
        ->execute([
            trim($_POST['name']),
            trim($_POST['location']),
            $_POST['province'],
            $_POST['district'] ?? '',
            $_POST['school_type'] ?? 'Secondary',
            (float)str_replace([',','R',' '],'',$_POST['funding_requested'] ?? 0),
            (float)str_replace([',','R',' '],'',$_POST['funding_granted'] ?? 0),
            $_POST['status'],
            $_POST['school_id'],
        ]);
    header('Location: schools.php?success=updated'); exit;
}

// ── FETCH DATA ───────────────────────────────────────────────
$filter_province = $_GET['province'] ?? '';
$filter_status   = $_GET['status']   ?? '';
$search          = $_GET['q']        ?? '';

$where = []; $params = [];
if ($filter_province) { $where[] = 's.province = ?'; $params[] = $filter_province; }
if ($filter_status)   { $where[] = 's.status = ?';   $params[] = $filter_status; }
if ($search)          { $where[] = 's.name LIKE ?';   $params[] = "%$search%"; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$schools = $pdo->prepare("
    SELECT s.*, COUNT(p.id) AS partnership_count
    FROM schools s
    LEFT JOIN partnerships p ON p.school_id = s.id
    $wsql
    GROUP BY s.id ORDER BY s.name
");
$schools->execute($params);
$schools = $schools->fetchAll();

$all_schools  = $pdo->query("SELECT COUNT(*) FROM schools")->fetchColumn();
$active_count = $pdo->query("SELECT COUNT(*) FROM schools WHERE status='active'")->fetchColumn();
$provinces_q  = $pdo->query("SELECT COUNT(DISTINCT province) FROM schools")->fetchColumn();

$districts = [
    'Ekurhuleni Metro','City of Johannesburg Metro','City of Tshwane Metro',
    'Sedibeng District','West Rand District','eThekwini Metro','uMgungundlovu District',
    'City of Cape Town Metro','Cape Winelands District','Buffalo City Metro',
    'Nelson Mandela Bay Metro','OR Tambo District','Polokwane Local','Mopani District',
    'Ehlanzeni District','Gert Sibande District','Bojanala Platinum District',
    'Fezile Dabi District','John Taolo Gaetsewe District','Other'
];

$success = $_GET['success'] ?? '';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Schools</span>
  </div>

  <?php if ($success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i>
    <?= $success==='added'?'School added!':(($success==='deleted')?'School deleted.':'School updated!') ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div><h1>Schools</h1><p>Beneficiary schools enrolled in the CSI programme</p></div>
    <?php permission_btn('Add School', can_edit(), 'ti-plus', 'btn btn-primary', "openModal('add-modal')") ?>
  </div>

  <div class="stats-row three">
    <div class="stat-card orange">
      <div class="stat-label">Total Schools</div>
      <div class="stat-value orange"><?= $all_schools ?></div>
      <div class="stat-sub">Enrolled in programme</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active</div>
      <div class="stat-value teal"><?= $active_count ?></div>
      <div class="stat-sub">Currently funded</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">Provinces</div>
      <div class="stat-value"><?= $provinces_q ?></div>
      <div class="stat-sub">Covered</div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="actions-bar">
    <div style="position:relative">
      <button class="btn btn-secondary" onclick="toggleFilter('s-filter')" id="s-filter-btn">
        <i class="ti ti-filter"></i> Filter
        <?php if ($filter_province || $filter_status): ?>
          <span style="background:var(--orange);color:white;font-size:10px;padding:1px 5px;border-radius:8px">●</span>
        <?php endif; ?>
      </button>
      <div id="s-filter" style="display:none;position:absolute;top:42px;left:0;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:16px;min-width:280px;box-shadow:0 8px 24px rgba(26,31,46,.1);z-index:100">
        <form method="GET">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Province</div>
          <select name="province" class="form-select" style="margin-bottom:12px">
            <option value="">All Provinces</option>
            <?php foreach(['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape','Limpopo','Mpumalanga','North West','Free State','Northern Cape'] as $p): ?>
              <option <?= $filter_province===$p?'selected':'' ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Status</div>
          <select name="status" class="form-select" style="margin-bottom:12px">
            <option value="">All Statuses</option>
            <?php foreach(['active'=>'Active','pending'=>'Pending','completed'=>'Completed','inactive'=>'Inactive'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $filter_status===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;font-size:12px"><i class="ti ti-check"></i> Apply</button>
            <a href="schools.php" class="btn btn-secondary" style="flex:1;justify-content:center;font-size:12px"><i class="ti ti-x"></i> Clear</a>
          </div>
        </form>
      </div>
    </div>
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input class="filter-input" id="school-search" placeholder="Search schools…"
             oninput="filterTable('school-search','schools-tbody')">
    </div>
  </div>

  <?php if ($filter_province || $filter_status): ?>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px">
    Showing <?= count($schools) ?> result<?= count($schools)!=1?'s':'' ?>
    <?= $filter_province ? " in <strong>{$filter_province}</strong>" : '' ?>
    <?= $filter_status   ? " with status <strong>".ucfirst($filter_status)."</strong>" : '' ?>
    — <a href="schools.php" style="color:var(--orange)">Clear filters</a>
  </div>
  <?php endif; ?>

  <table class="data-table">
    <thead>
      <tr>
        <th>School Name</th>
        <th>Province</th>
        <th>Municipality / District</th>
        <th>Partnerships</th>
        <?php if (can_view_financials()): ?>
          <th>Funding Requested</th>
          <th>Funding Granted</th>
        <?php endif; ?>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="schools-tbody">
      <?php foreach ($schools as $s): ?>
      <tr>
        <td>
          <span class="cell-name"><?= htmlspecialchars($s['name']) ?></span>
          <span class="cell-sub"><?= htmlspecialchars($s['location'] ?? '') ?></span>
        </td>
        <td><?= htmlspecialchars($s['province']) ?></td>
        <td style="color:var(--text-muted);font-size:12.5px"><?= htmlspecialchars($s['district'] ?? '—') ?></td>
        <td style="text-align:center;font-weight:600"><?= $s['partnership_count'] ?></td>
        <?php if (can_view_financials()): ?>
          <td style="color:var(--text-muted);font-weight:600">
            <?= $s['funding_requested'] > 0 ? 'R'.number_format($s['funding_requested']/1000).'k' : '—' ?>
          </td>
          <td style="color:var(--teal);font-weight:700">
            <?= $s['funding_granted'] > 0 ? 'R'.number_format($s['funding_granted']/1000).'k' : '—' ?>
          </td>
        <?php endif; ?>
        <td><span class="status-badge <?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
        <td>
          <?= action_btn('ti-pencil','Edit',   can_edit(),  "openEdit({$s['id']},".htmlspecialchars(json_encode($s), ENT_QUOTES).")") ?>
          <?= action_btn('ti-trash', 'Delete', is_admin(), "deleteSchool({$s['id']},".htmlspecialchars(json_encode($s['name']), ENT_QUOTES).")", 'btn-danger-icon') ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($schools)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px">No schools found.</td></tr>
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
    <h2>Add School</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_school">
      <div class="form-group">
        <label class="form-label">School Name *</label>
        <input class="form-input" type="text" name="name" placeholder="e.g. Diepsloot Secondary School" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Location</label>
          <input class="form-input" type="text" name="location" placeholder="e.g. Soweto, Gauteng">
        </div>
        <div class="form-group">
          <label class="form-label">Province *</label>
          <select class="form-select" name="province" required>
            <option value="">Select province</option>
            <?php foreach(['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape','Limpopo','Mpumalanga','North West','Free State','Northern Cape'] as $p): ?>
              <option><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Municipality / District</label>
          <select class="form-select" name="district">
            <option value="">Select district</option>
            <?php foreach ($districts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">School Type</label>
          <select class="form-select" name="school_type">
            <?php foreach(['Primary','Secondary','Combined','Technical','STEM','Other'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Funding Requested (R)</label>
          <input class="form-input" type="text" name="funding_requested" placeholder="e.g. 500000">
        </div>
        <div class="form-group">
          <label class="form-label">Funding Granted (R)</label>
          <input class="form-input" type="text" name="funding_granted" placeholder="e.g. 450000">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add School</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target.id==='edit-modal')closeModal('edit-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit School</h2>
    <form method="POST">
      <input type="hidden" name="form" value="edit_school">
      <input type="hidden" name="school_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">School Name *</label>
        <input class="form-input" type="text" name="name" id="edit-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Location</label>
          <input class="form-input" type="text" name="location" id="edit-location">
        </div>
        <div class="form-group">
          <label class="form-label">Province *</label>
          <select class="form-select" name="province" id="edit-province">
            <?php foreach(['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape','Limpopo','Mpumalanga','North West','Free State','Northern Cape'] as $p): ?>
              <option><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Municipality / District</label>
          <select class="form-select" name="district" id="edit-district">
            <option value="">Select district</option>
            <?php foreach ($districts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">School Type</label>
          <select class="form-select" name="school_type" id="edit-type">
            <?php foreach(['Primary','Secondary','Combined','Technical','STEM','Other'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Funding Requested (R)</label>
          <input class="form-input" type="text" name="funding_requested" id="edit-freq">
        </div>
        <div class="form-group">
          <label class="form-label">Funding Granted (R)</label>
          <input class="form-input" type="text" name="funding_granted" id="edit-fgrant">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="edit-status">
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<form method="POST" id="delete-form">
  <input type="hidden" name="form" value="delete_school">
  <input type="hidden" name="school_id" id="delete-id">
</form>
<?php endif; ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function toggleFilter(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function openEdit(id, data) {
  document.getElementById('edit-id').value       = id;
  document.getElementById('edit-name').value     = data.name;
  document.getElementById('edit-location').value = data.location || '';
  document.getElementById('edit-province').value = data.province;
  document.getElementById('edit-district').value = data.district || '';
  document.getElementById('edit-type').value     = data.school_type;
  document.getElementById('edit-freq').value     = data.funding_requested || '';
  document.getElementById('edit-fgrant').value   = data.funding_granted || '';
  document.getElementById('edit-status').value   = data.status;
  openModal('edit-modal');
}
function deleteSchool(id, name) {
  if (confirm('Delete "' + name + '"?\nThis will also remove linked partnerships.')) {
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
document.addEventListener('click', function(e) {
  const btn = document.getElementById('s-filter-btn');
  const panel = document.getElementById('s-filter');
  if (panel && btn && !btn.contains(e.target) && !panel.contains(e.target))
    panel.style.display = 'none';
});
</script>

<?php include 'includes/footer.php'; ?>