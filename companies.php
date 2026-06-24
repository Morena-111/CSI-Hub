<?php
$active_page = 'companies';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

// ── HANDLE ADD ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_company' && can_edit()) {
    $name = trim($_POST['name']);
    $initials = strtoupper(substr(explode(' ',$name)[0],0,1).substr(explode(' ',$name)[1]??'',0,1));
    $pdo->prepare("INSERT INTO companies (name, sector, initials, status, since_year) VALUES (?,?,?,?,?)")
        ->execute([$name, $_POST['programme'] ?? '', $_POST['programme'] ?? '', $initials, $_POST['status'], $_POST['focus_year'] ?: null]);
    header('Location: companies.php?success=added'); exit;
}

// ── HANDLE DELETE ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_company' && is_admin()) {
    $pdo->prepare("DELETE FROM companies WHERE id=?")->execute([$_POST['company_id']]);
    header('Location: companies.php?success=deleted'); exit;
}

// ── HANDLE EDIT ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_company' && can_edit()) {
    $pdo->prepare("UPDATE companies SET name=?, sector=?, status=?, since_year=? WHERE id=?")
        ->execute([trim($_POST['name']), $_POST['programme'] ?? '', $_POST['programme'] ?? '', $_POST['status'], $_POST['focus_year'] ?: null, $_POST['company_id']]);
    header('Location: companies.php?success=updated'); exit;
}

// ── FETCH ─────────────────────────────────────────────────────
$filter_status = $_GET['status'] ?? '';
$search        = $_GET['q']      ?? '';

$where = []; $params = [];
if ($filter_status) { $where[] = 'c.status = ?'; $params[] = $filter_status; }
if ($search)        { $where[] = 'c.name LIKE ?'; $params[] = "%$search%"; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$companies = $pdo->prepare("
    SELECT c.*,
           COUNT(DISTINCT p.school_id) AS schools_funded,
           COUNT(p.id)                 AS partnership_count,
           COALESCE(SUM(p.amount),0)   AS total_committed
    FROM companies c
    LEFT JOIN partnerships p ON p.company_id = c.id
    $wsql
    GROUP BY c.id ORDER BY c.name
");
$companies->execute($params);
$companies = $companies->fetchAll();

$logo_colors = [
    'Technology'        => 'background:var(--navy);color:white',
    'Financial Services'=> 'background:#fff8ec;color:#9a6700',
    'Energy'            => 'background:#f0eeff;color:#7c6af5',
    'Mining'            => 'background:#fde9f1;color:#f06292',
    'Retail'            => 'background:#e6faf5;color:#00956a',
    'Healthcare'        => 'background:#fdf0ea;color:#E8541A',
];

$programmes_list = ['STEM Education','Digital Skills','Literacy & Numeracy','Arts & Culture','Science & Technology','Leadership Development','Sports & Recreation','Other'];
$sectors_list    = ['Technology','Financial Services','Energy','Mining','Retail','Healthcare','Other'];
$focus_years     = range(date('Y'), 2020);

$total_co    = count($companies);
$active_co   = count(array_filter($companies, fn($c) => $c['status']==='active'));
$total_schs  = array_sum(array_column($companies,'schools_funded'));
$success     = $_GET['success'] ?? '';
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Companies</span>
  </div>

  <?php if ($success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i>
    <?= $success==='added'?'Company added!':(($success==='deleted')?'Company deleted.':'Company updated!') ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div><h1>Companies</h1><p>Corporate partners invested in our CSI programme</p></div>
    <?php permission_btn('Add Company', can_edit(), 'ti-plus', 'btn btn-primary', "openModal('add-modal')") ?>
  </div>

  <div class="stats-row three">
    <div class="stat-card orange">
      <div class="stat-label">Companies</div>
      <div class="stat-value orange"><?= $total_co ?></div>
      <div class="stat-sub">Corporate funders</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active</div>
      <div class="stat-value teal"><?= $active_co ?></div>
      <div class="stat-sub">Currently engaged</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Schools Funded</div>
      <div class="stat-value"><?= $total_schs ?></div>
      <div class="stat-sub">Across all companies</div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="actions-bar">
    <div style="position:relative">
      <button class="btn btn-secondary" onclick="toggleFilter('c-filter')" id="c-filter-btn">
        <i class="ti ti-filter"></i> Filter
        <?php if ($filter_status): ?><span style="background:var(--orange);color:white;font-size:10px;padding:1px 5px;border-radius:8px">●</span><?php endif; ?>
      </button>
      <div id="c-filter" style="display:none;position:absolute;top:42px;left:0;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:16px;min-width:220px;box-shadow:0 8px 24px rgba(26,31,46,.1);z-index:100">
        <form method="GET">
          <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px">Status</div>
          <select name="status" class="form-select" style="margin-bottom:12px">
            <option value="">All</option>
            <?php foreach(['active'=>'Active','pending'=>'Pending','inactive'=>'Inactive'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $filter_status===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;font-size:12px"><i class="ti ti-check"></i> Apply</button>
            <a href="companies.php" class="btn btn-secondary" style="flex:1;justify-content:center;font-size:12px"><i class="ti ti-x"></i> Clear</a>
          </div>
        </form>
      </div>
    </div>
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input class="filter-input" id="co-search" placeholder="Search companies…" oninput="filterCards()">
    </div>
  </div>

  <div class="cards-grid" id="cards-grid">
    <?php foreach ($companies as $c):
      $style    = $logo_colors[$c['sector']] ?? 'background:var(--surface);color:var(--text)';
      $initials = $c['initials'] ?: strtoupper(substr($c['name'],0,2));
    ?>
    <div class="pcard" data-search="<?= htmlspecialchars(strtolower($c['name'].' '.$c['programme'].' '.$c['programme'])) ?>">
      <div class="pcard-head">
        <div class="pcard-company">
          <div class="company-logo" style="<?= htmlspecialchars($style) ?>"><?= htmlspecialchars($initials) ?></div>
          <div>
            <div class="pcard-name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="pcard-school" style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($c['sector'] ?? '') ?></div>
          </div>
        </div>
        <span class="status-badge <?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12.5px;margin-top:10px">
        <div>
          <div class="stat-label">Schools</div>
          <strong style="color:var(--teal)"><?= $c['schools_funded'] ?> school<?= $c['schools_funded']!=1?'s':'' ?></strong>
        </div>
        <div>
          <div class="stat-label">Cost Committed</div>
          <?php if (can_view_financials()): ?>
            <strong style="color:var(--orange)"><?= $c['total_committed']>0?'R'.number_format($c['total_committed']/1000).'k':'—' ?></strong>
          <?php else: ?>
            <strong style="color:var(--text-muted)"><i class="ti ti-lock" style="font-size:11px"></i> Hidden</strong>
          <?php endif; ?>
        </div>
        <div>
          <div class="stat-label">Programmes</div>
          <strong style="font-size:12px"><?= htmlspecialchars($c['sector'] ?? '—') ?></strong>
        </div>
        <div>
          <div class="stat-label">Focus Area</div>
          <strong><?= $c['since_year'] ?? '—' ?></strong>
        </div>
      </div>

      <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
        <?php permission_btn('Edit', can_edit(), 'ti-pencil', 'btn btn-secondary', "openEdit({$c['id']},".htmlspecialchars(json_encode($c), ENT_QUOTES).")") ?>
        <?php if (is_admin()): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
            <input type="hidden" name="form" value="delete_company">
            <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-secondary" style="color:#c53030;border-color:#fed7d7;font-size:11.5px;padding:6px 12px">
              <i class="ti ti-trash"></i> Delete
            </button>
          </form>
        <?php endif; ?>
        <?php if (can_edit()): ?>
          <button class="btn btn-primary" style="font-size:11.5px;padding:6px 12px;margin-left:auto" onclick="window.location='partnerships.php'">
            <i class="ti ti-plus"></i> Add Partnership
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($companies)): ?>
      <p style="color:var(--text-muted);font-size:13px">No companies found.</p>
    <?php endif; ?>
  </div>

</main>
</div>

<!-- ADD MODAL -->
<?php if (can_edit()): ?>
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Company</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_company">
      <div class="form-group">
        <label class="form-label">Company Name *</label>
        <input class="form-input" type="text" name="name" placeholder="e.g. Standard Bank Foundation" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Programmes</label>
          <select class="form-select" name="programme">
            <option value="">Select programme</option>
            <?php foreach($programmes_list as $pr): ?><option><?= $pr ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Focus Area</label>
          <select class="form-select" name="focus_area">
            <option value="">Select year</option>
            <?php foreach($focus_years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
          </select>
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
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add Company</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target.id==='edit-modal')closeModal('edit-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit Company</h2>
    <form method="POST">
      <input type="hidden" name="form" value="edit_company">
      <input type="hidden" name="company_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Company Name *</label>
        <input class="form-input" type="text" name="name" id="edit-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Programmes</label>
          <select class="form-select" name="programme" id="edit-programme">
            <option value="">Select programme</option>
            <?php foreach($programmes_list as $pr): ?><option><?= $pr ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Focus Area</label>
          <select class="form-select" name="focus_area" id="edit-focus">
            <option value="">Select year</option>
            <?php foreach($focus_years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="edit-status">
          <option value="active">Active</option>
          <option value="pending">Pending</option>
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
<?php endif; ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function toggleFilter(id) {
  const el = document.getElementById(id);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function openEdit(id, data) {
  document.getElementById('edit-id').value        = id;
  document.getElementById('edit-name').value      = data.name;
  document.getElementById('edit-sector').value    = data.sector    || '';
  document.getElementById('edit-focus').value     = data.since_year || '';
  document.getElementById('edit-programme').value = data.sector || '';
  document.getElementById('edit-status').value    = data.status;
  openModal('edit-modal');
}
function filterCards() {
  const q = document.getElementById('co-search').value.toLowerCase();
  document.querySelectorAll('#cards-grid .pcard').forEach(c => {
    c.style.display = (c.dataset.search || '').includes(q) ? '' : 'none';
  });
}
document.addEventListener('click', function(e) {
  const btn = document.getElementById('c-filter-btn');
  const panel = document.getElementById('c-filter');
  if (panel && btn && !btn.contains(e.target) && !panel.contains(e.target))
    panel.style.display = 'none';
});
</script>

<?php include 'includes/footer.php'; ?>