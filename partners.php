<?php
$active_page = 'partners';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$success = ''; $error = '';

// ── HANDLERS (admin only) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && is_admin()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'add_partner') {
        $name  = trim($_POST['name']??'');
        $prog  = trim($_POST['programme']??'');
        $focus = trim($_POST['focus_area']??'');
        $status= $_POST['status']??'active';
        if ($name) {
            $pdo->prepare("INSERT INTO companies (name,sector,since_year,status) VALUES (?,?,?,?)")
                ->execute([$name,$prog,$focus,$status]);
            $success = "Partner \"{$name}\" added.";
        } else { $error = 'Partner name is required.'; }
    }
    if ($form === 'edit_partner') {
        $pdo->prepare("UPDATE companies SET name=?,sector=?,since_year=?,status=? WHERE id=?")
            ->execute([trim($_POST['name']??''),trim($_POST['programme']??''),trim($_POST['focus_area']??''),$_POST['status']??'active',(int)$_POST['partner_id']]);
        $success = 'Partner updated.';
    }
    if ($form === 'delete_partner') {
        $pdo->prepare("DELETE FROM companies WHERE id=?")->execute([(int)$_POST['partner_id']]);
        $success = 'Partner removed.';
    }
}

// ── FETCH ─────────────────────────────────────────────────────
$search = trim($_GET['q']??'');
$filter_status = $_GET['status']??'';

$where = '1=1';
$params = [];
if ($search) { $where .= ' AND c.name LIKE ?'; $params[] = "%{$search}%"; }
if ($filter_status) { $where .= ' AND c.status=?'; $params[] = $filter_status; }

// Data isolation:
// - Company users: see ONLY their own partner record
// - School users: see ALL partners (they need to find/identify partners,
//   but their own school data isolation happens in schools.php)
// - Admin: sees everything
if (!is_admin() && ($_SESSION['user_type']??'') === 'company' && isset($_SESSION['linked_id'])) {
    $where .= ' AND c.id=?'; $params[] = (int)$_SESSION['linked_id'];
}

$st = $pdo->prepare("
    SELECT c.*,
           COUNT(DISTINCT p.id)     AS total_partnerships,
           COUNT(DISTINCT p.school_id) AS schools_count,
           SUM(CASE WHEN p.status='active' THEN 1 ELSE 0 END) AS active_count,
           COALESCE(SUM(p.amount),0) AS total_invested
    FROM companies c
    LEFT JOIN partnerships p ON p.company_id=c.id
    WHERE {$where}
    GROUP BY c.id ORDER BY c.name ASC
");
$st->execute($params);
$partners = $st->fetchAll();

$totals = [
    'total'    => count($partners),
    'active'   => count(array_filter($partners, fn($p) => $p['status']==='active')),
    'invested' => array_sum(array_column($partners,'total_invested')),
    'schools'  => array_sum(array_column($partners,'schools_count')),
];

$statuses   = ['active','inactive','pending'];
$programmes = ['Education','STEM','Literacy','Digital Skills','Arts & Culture','Science','Skills Development','Other'];
$focus_years= array_map(fn($y)=>(string)$y, range(date('Y'),2010));

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Partners</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Partners</h1>
      <p>Corporate partners investing in CSI programmes</p>
    </div>
    <div class="page-header-right">
      <!-- Search -->
      <div class="search-wrap">
        <i class="ti ti-search"></i>
        <input class="filter-input" id="partner-search" placeholder="Search partners…"
               value="<?= htmlspecialchars($search) ?>"
               oninput="filterPartners()">
      </div>
      <!-- Status filter -->
      <select class="form-select" style="width:auto;padding:8px 12px;font-size:13px"
              onchange="window.location='partners.php?status='+this.value+'&q=<?= urlencode($search) ?>'">
        <option value="" <?= !$filter_status?'selected':'' ?>>All Status</option>
        <?php foreach($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if(is_admin()): ?>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <i class="ti ti-plus"></i> Add Partner
      </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if($success): ?>
  <div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
  </div>
  <?php endif; ?>

  <!-- STAT CARDS -->
  <div class="stats-row">
    <div class="stat-card orange">
      <div class="stat-label">Total Partners</div>
      <div class="stat-value orange"><?= $totals['total'] ?></div>
      <div class="stat-sub">Corporate funders</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active</div>
      <div class="stat-value teal"><?= $totals['active'] ?></div>
      <div class="stat-sub">Currently engaged</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Schools Funded</div>
      <div class="stat-value purple"><?= $totals['schools'] ?></div>
      <div class="stat-sub">Beneficiary schools</div>
    </div>
    <?php if(can_view_financials()): ?>
    <div class="stat-card gold">
      <div class="stat-label">Total Invested</div>
      <div class="stat-value">R<?= number_format($totals['invested']/1000000,1) ?>M</div>
      <div class="stat-sub">Across all partners</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- PARTNERS TABLE -->
  <div class="widget">
    <div class="widget-title">
      <i class="ti ti-users"></i> Partner Directory
      <span style="margin-left:8px;font-size:11px;font-weight:400;color:var(--text-muted)" id="partner-count">
        <?= count($partners) ?> partner<?= count($partners)!=1?'s':'' ?>
      </span>
    </div>
    <?php if(empty($partners)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-muted)">
      <i class="ti ti-users" style="font-size:36px;opacity:.2;display:block;margin-bottom:8px"></i>
      <p>No partners found.</p>
    </div>
    <?php else: ?>
    <table class="data-table" id="partners-table">
      <thead>
        <tr>
          <th>Partner Name</th>
          <th>Programme</th>
          <th>Focus Year</th>
          <th>Projects</th>
          <th>Schools</th>
          <?php if(can_view_financials()): ?><th>Total Invested</th><?php endif; ?>
          <th>Status</th>
          <?php if(is_admin()): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="partners-tbody">
        <?php foreach($partners as $c): ?>
        <tr>
          <td>
            <a href="partnerships.php?partner_id=<?= $c['id'] ?>"
               style="font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
              <div style="width:32px;height:32px;border-radius:8px;background:var(--orange-soft);
                          color:var(--orange);display:flex;align-items:center;justify-content:center;
                          font-size:12px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($c['name'],0,2)) ?>
              </div>
              <?= htmlspecialchars($c['name']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($c['sector']??'—') ?></td>
          <td><?= htmlspecialchars($c['since_year']??'—') ?></td>
          <td style="font-weight:700;color:var(--orange)"><?= $c['total_partnerships'] ?></td>
          <td><?= $c['schools_count'] ?></td>
          <?php if(can_view_financials()): ?>
          <td style="font-weight:700">R<?= number_format($c['total_invested']/1000) ?>k</td>
          <?php endif; ?>
          <td><span class="status-badge <?= $c['status'] ?>"><?= ucfirst($c['status']) ?></span></td>
          <?php if(is_admin()): ?>
          <td>
            <button class="table-action-btn" title="Edit"
                    onclick="openEdit(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)">
              <i class="ti ti-pencil"></i>
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($c['name'])) ?>?')">
              <input type="hidden" name="form" value="delete_partner">
              <input type="hidden" name="partner_id" value="<?= $c['id'] ?>">
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
<?php if(is_admin()): ?>
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Partner</h2>
    <div class="modal-sub">Add a new corporate partner to CSI Hub.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_partner">
      <div class="form-group">
        <label class="form-label">Partner / Company Name *</label>
        <input class="form-input" type="text" name="name" placeholder="e.g. Sasol Foundation" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Programme</label>
          <select class="form-select" name="programme">
            <option value="">Select programme</option>
            <?php foreach($programmes as $p): ?><option><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Focus Year</label>
          <select class="form-select" name="focus_area">
            <option value="">Select year</option>
            <?php foreach($focus_years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach($statuses as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add Partner</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target.id==='edit-modal')closeModal('edit-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit Partner</h2>
    <form method="POST">
      <input type="hidden" name="form" value="edit_partner">
      <input type="hidden" name="partner_id" id="edit-id">
      <div class="form-group">
        <label class="form-label">Partner Name *</label>
        <input class="form-input" type="text" name="name" id="edit-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Programme</label>
          <select class="form-select" name="programme" id="edit-prog">
            <option value="">Select programme</option>
            <?php foreach($programmes as $p): ?><option><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Focus Year</label>
          <select class="form-select" name="focus_area" id="edit-focus">
            <option value="">Select year</option>
            <?php foreach($focus_years as $y): ?><option><?= $y ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="edit-status">
          <?php foreach($statuses as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
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
function openEdit(c) {
  document.getElementById('edit-id').value     = c.id;
  document.getElementById('edit-name').value   = c.name;
  document.getElementById('edit-prog').value   = c.sector||'';
  document.getElementById('edit-focus').value  = c.since_year||'';
  document.getElementById('edit-status').value = c.status||'active';
  openModal('edit-modal');
}
function filterPartners() {
  const q = document.getElementById('partner-search').value.toLowerCase();
  let shown = 0;
  document.querySelectorAll('#partners-tbody tr').forEach(r => {
    const show = r.textContent.toLowerCase().includes(q);
    r.style.display = show ? '' : 'none';
    if (show) shown++;
  });
  document.getElementById('partner-count').textContent = shown+' partner'+(shown!==1?'s':'');
}
</script>
<?php include 'includes/footer.php'; ?>