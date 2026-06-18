<?php
$active_page = 'schools';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ── DATA ISOLATION ────────────────────────────────────────────
$scope_school = null;
if (!is_admin()) {
    if (($_SESSION['user_type']??'') === 'company') {
        header('Location: /csi-hub/partners.php'); exit;
    }
    if (($_SESSION['user_type']??'') === 'school' && isset($_SESSION['linked_id'])) {
        $scope_school = (int)$_SESSION['linked_id'];
    }
}

$success = ''; $error = '';

// ── HANDLERS (admin only) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && is_admin()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'add_school') {
        $name     = trim($_POST['name']??'');
        $location = trim($_POST['location']??'');
        $province = $_POST['province']??'';
        $type     = $_POST['school_type']??'Public';
        $status   = $_POST['status']??'active';
        $fund_req = (float)str_replace(',','',$_POST['funding_requested']??0);
        $fund_grt = (float)str_replace(',','',$_POST['funding_granted']??0);
        $district = trim($_POST['district']??'');
        if ($name) {
            $pdo->prepare("INSERT INTO schools (name,location,province,school_type,status,funding_requested,funding_granted,district) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$name,$location,$province,$type,$status,$fund_req,$fund_grt,$district]);
            $success = "School \"{$name}\" added.";
        } else { $error = 'School name is required.'; }
    }
    if ($form === 'edit_school') {
        $pdo->prepare("UPDATE schools SET name=?,location=?,province=?,school_type=?,status=?,funding_requested=?,funding_granted=?,district=? WHERE id=?")
            ->execute([
                trim($_POST['name']??''), trim($_POST['location']??''),
                $_POST['province']??'', $_POST['school_type']??'Public',
                $_POST['status']??'active',
                (float)str_replace(',','',$_POST['funding_requested']??0),
                (float)str_replace(',','',$_POST['funding_granted']??0),
                trim($_POST['district']??''),
                (int)$_POST['school_id']
            ]);
        $success = 'School updated.';
    }
    if ($form === 'delete_school') {
        $pdo->prepare("DELETE FROM schools WHERE id=?")->execute([(int)$_POST['school_id']]);
        $success = 'School removed.';
    }
}

// ── FETCH ─────────────────────────────────────────────────────
$search         = trim($_GET['search']??'');
$filter_province= $_GET['province']??'';
$filter_status  = $_GET['status']??'';

$where  = '1=1'; $params = [];

// Scope to single school if user is linked
if ($scope_school) {
    $where .= ' AND s.id=?'; $params[] = $scope_school;
} else {
    if ($search)          { $where .= ' AND s.name LIKE ?';     $params[] = "%{$search}%"; }
    if ($filter_province) { $where .= ' AND s.province=?';      $params[] = $filter_province; }
    if ($filter_status)   { $where .= ' AND s.status=?';        $params[] = $filter_status; }
}

$schools_st = $pdo->prepare("
    SELECT s.*,
           COUNT(DISTINCT p.id) AS partnership_count,
           COUNT(DISTINCT p.company_id) AS partner_count,
           SUM(CASE WHEN p.status='active' THEN 1 ELSE 0 END) AS active_partnerships
    FROM schools s
    LEFT JOIN partnerships p ON p.school_id=s.id
    WHERE {$where}
    GROUP BY s.id ORDER BY s.name ASC
");
$schools_st->execute($params);
$schools = $schools_st->fetchAll();

$provinces = ['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape',
              'Limpopo','Mpumalanga','North West','Free State','Northern Cape'];
$statuses  = ['active','inactive','pending'];
$types     = ['Public','Private','Independent','LSEN'];

$districts = [
    'City of Johannesburg','City of Tshwane','Ekurhuleni','Sedibeng','West Rand',
    'eThekwini','uMgungundlovu','uThukela','Zululand','King Cetshwayo',
    'City of Cape Town','Cape Winelands','Overberg','Garden Route','Central Karoo',
    'Buffalo City','Nelson Mandela Bay','OR Tambo','Joe Gqabi','Amathole',
    'Capricorn','Mopani','Sekhukhune','Vhembe','Waterberg',
    'Gert Sibande','Ehlanzeni','Nkangala',
    'Bojanala','Dr Kenneth Kaunda','Dr Ruth Segomotsi Mompati','Ngaka Modiri Molema',
    'Fezile Dabi','Lejweleputswa','Mangaung','Thabo Mofutsanyana','Xhariep',
    'Frances Baard','John Taolo Gaetsewe','Namakwa','Pixley ka Seme','ZF Mgcawu',
];

include 'includes/header.php';
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

<!-- PAGE HEADER -->
<div class="page-header">
  <div>
    <h1><?= $scope_school ? htmlspecialchars($schools[0]['name']??'My School') : 'Schools' ?></h1>
    <p><?= $scope_school ? 'Your school overview and partners list' : 'Beneficiary schools in the CSI programme' ?></p>
  </div>
  <div class="page-header-right">
    <?php if(!$scope_school): ?>
    <!-- Search + filters — admin/general view only -->
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input class="filter-input" id="school-search" placeholder="Search schools…"
             value="<?= htmlspecialchars($search) ?>"
             oninput="filterTable('school-search','school-tbody')">
    </div>
    <select class="form-select" style="width:auto;padding:8px 12px;font-size:13px"
            onchange="window.location='schools.php?province='+this.value+'&status=<?= urlencode($filter_status) ?>&search=<?= urlencode($search) ?>'">
      <option value="">All Provinces</option>
      <?php foreach($provinces as $pv): ?>
      <option value="<?= $pv ?>" <?= $filter_province===$pv?'selected':'' ?>><?= $pv ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:auto;padding:8px 12px;font-size:13px"
            onchange="window.location='schools.php?status='+this.value+'&province=<?= urlencode($filter_province) ?>&search=<?= urlencode($search) ?>'">
      <option value="">All Status</option>
      <?php foreach($statuses as $sv): ?>
      <option value="<?= $sv ?>" <?= $filter_status===$sv?'selected':'' ?>><?= ucfirst($sv) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?php if(is_admin()): ?>
    <button class="btn btn-primary" onclick="openModal('add-modal')">
      <i class="ti ti-plus"></i> Add School
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-alert-circle"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if($scope_school && !empty($schools)):
  // ════════════════════════════════════════════
  // SCHOOL USER VIEW — their school only
  // Shows: school info card + partners list only
  // ════════════════════════════════════════════
  $sch = $schools[0];

  // Fetch this school's partners
  $partners_st = $pdo->prepare("
      SELECT p.*, c.name AS partner_name, c.sector AS programme,
             p.focus_area, p.status, p.start_date, p.end_date, p.amount,
             DATEDIFF(p.end_date,CURDATE()) AS days_left
      FROM partnerships p
      JOIN companies c ON c.id=p.company_id
      WHERE p.school_id=?
      ORDER BY p.status ASC, p.start_date DESC
  ");
  $partners_st->execute([$scope_school]);
  $school_partners = $partners_st->fetchAll();

  // Impact for this school
  $impact_st = $pdo->prepare("
      SELECT COALESCE(SUM(i.learners),0) AS learners, COALESCE(SUM(i.educators),0) AS educators
      FROM impact_stats i JOIN partnerships p ON p.id=i.partnership_id
      WHERE p.school_id=?
  ");
  $impact_st->execute([$scope_school]); $impact = $impact_st->fetch();
?>

<!-- School info card -->
<div class="widget" style="margin-bottom:18px">
  <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
    <div style="width:52px;height:52px;border-radius:13px;background:var(--teal-soft);color:var(--teal);
                display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
      <i class="ti ti-school"></i>
    </div>
    <div style="flex:1;min-width:200px">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--text);margin-bottom:4px">
        <?= htmlspecialchars($sch['name']) ?>
      </h2>
      <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12.5px;color:var(--text-muted)">
        <?php if($sch['province']): ?><span><i class="ti ti-map-pin" style="font-size:13px"></i> <?= htmlspecialchars($sch['province']) ?></span><?php endif; ?>
        <?php if($sch['location']): ?><span><i class="ti ti-building" style="font-size:13px"></i> <?= htmlspecialchars($sch['location']) ?></span><?php endif; ?>
        <?php if($sch['district']): ?><span><i class="ti ti-topology-star" style="font-size:13px"></i> <?= htmlspecialchars($sch['district']) ?></span><?php endif; ?>
        <span class="status-badge <?= $sch['status'] ?>"><?= ucfirst($sch['status']) ?></span>
      </div>
    </div>
    <div style="display:flex;gap:14px;flex-wrap:wrap">
      <div style="text-align:center;padding:10px 16px;background:var(--orange-soft);border-radius:10px">
        <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--orange)"><?= count($school_partners) ?></div>
        <div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Partners</div>
      </div>
      <div style="text-align:center;padding:10px 16px;background:var(--teal-soft);border-radius:10px">
        <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--teal)"><?= number_format($impact['learners']) ?></div>
        <div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Learners</div>
      </div>
      <div style="text-align:center;padding:10px 16px;background:var(--purple-soft);border-radius:10px">
        <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--purple)"><?= number_format($impact['educators']) ?></div>
        <div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Educators</div>
      </div>
    </div>
  </div>
</div>

<!-- Partners list — school users only see this, NO other school data -->
<div class="widget">
  <div class="widget-title">
    <i class="ti ti-users" style="color:var(--orange)"></i>
    Partners investing in <?= htmlspecialchars($sch['name']) ?>
    <span style="margin-left:6px;font-size:11px;font-weight:400;color:var(--text-muted)">
      <?= count($school_partners) ?> partner<?= count($school_partners)!=1?'s':'' ?>
    </span>
  </div>
  <?php if(empty($school_partners)): ?>
  <div style="text-align:center;padding:32px;color:var(--text-muted)">
    <i class="ti ti-users" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px"></i>
    <p style="font-size:13px">No partners linked to your school yet.</p>
  </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Partner</th>
        <th>Programme</th>
        <th>Focus Area</th>
        <th>Period</th>
        <th>Progress</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($school_partners as $sp):
        $s = strtotime($sp['start_date']); $e = strtotime($sp['end_date']); $now = time();
        $prog = $sp['status']==='completed' ? 100 :
                ($sp['status']==='pending'   ? 0   :
                ($now>=$e ? 100 : ($now<=$s ? 0 : round(($now-$s)/($e-$s)*100))));
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:9px">
            <div style="width:30px;height:30px;border-radius:7px;background:var(--orange-soft);
                        color:var(--orange);display:flex;align-items:center;justify-content:center;
                        font-size:11px;font-weight:700;flex-shrink:0">
              <?= strtoupper(substr($sp['partner_name'],0,2)) ?>
            </div>
            <span style="font-weight:600"><?= htmlspecialchars($sp['partner_name']) ?></span>
          </div>
        </td>
        <td style="font-size:12.5px;color:var(--text-muted)"><?= htmlspecialchars($sp['programme']??'—') ?></td>
        <td>
          <span style="background:var(--purple-soft);color:var(--purple);padding:3px 9px;border-radius:12px;font-size:11.5px;font-weight:600">
            <?= htmlspecialchars($sp['focus_area']) ?>
          </span>
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?= date('d M Y',strtotime($sp['start_date'])) ?> – <?= date('d M Y',strtotime($sp['end_date'])) ?>
        </td>
        <td style="min-width:110px">
          <div style="display:flex;align-items:center;gap:7px">
            <div class="progress-bar" style="flex:1;margin:0">
              <div class="progress-fill" style="width:<?= $prog ?>%"></div>
            </div>
            <span style="font-size:11px;font-weight:700;color:var(--orange)"><?= $prog ?>%</span>
          </div>
        </td>
        <td><span class="status-badge <?= $sp['status'] ?>"><?= ucfirst($sp['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php else:
  // ════════════════════════════════════════════
  // ADMIN / GENERAL VIEW — full school directory
  // ════════════════════════════════════════════
?>

<!-- Stats -->
<div class="stats-row">
  <div class="stat-card teal">
    <div class="stat-label">Total Schools</div>
    <div class="stat-value teal"><?= count($schools) ?></div>
    <div class="stat-sub">In programme</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Active</div>
    <div class="stat-value orange"><?= count(array_filter($schools,fn($s)=>$s['status']==='active')) ?></div>
    <div class="stat-sub">Currently engaged</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Total Partnerships</div>
    <div class="stat-value purple"><?= array_sum(array_column($schools,'partnership_count')) ?></div>
    <div class="stat-sub">Across all schools</div>
  </div>
</div>

<!-- Table -->
<div class="widget">
  <div class="widget-title">
    <i class="ti ti-school"></i> School Directory
    <span style="margin-left:8px;font-size:11px;font-weight:400;color:var(--text-muted)"
          id="school-count"><?= count($schools) ?> school<?= count($schools)!=1?'s':'' ?></span>
  </div>
  <?php if(empty($schools)): ?>
  <div style="text-align:center;padding:40px;color:var(--text-muted)">
    <i class="ti ti-school" style="font-size:36px;opacity:.2;display:block;margin-bottom:8px"></i>
    <p style="font-size:13px">No schools found.</p>
  </div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>School Name</th>
        <th>Province</th>
        <th>District</th>
        <th>Type</th>
        <th>Partners</th>
        <th>Active Projects</th>
        <?php if(can_view_financials()): ?>
        <th>Funding Req.</th>
        <th>Funding Granted</th>
        <?php endif; ?>
        <th>Status</th>
        <?php if(is_admin()): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody id="school-tbody">
      <?php foreach($schools as $s): ?>
      <tr>
        <td class="cell-name"><?= htmlspecialchars($s['name']) ?></td>
        <td><?= htmlspecialchars($s['province']??'—') ?></td>
        <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($s['district']??'—') ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($s['school_type']??'—') ?></td>
        <td style="font-weight:700;color:var(--orange)"><?= $s['partner_count'] ?></td>
        <td style="font-weight:700;color:var(--teal)"><?= $s['active_partnerships'] ?></td>
        <?php if(can_view_financials()): ?>
        <td>R<?= number_format($s['funding_requested']??0) ?></td>
        <td style="font-weight:600;color:var(--teal)">R<?= number_format($s['funding_granted']??0) ?></td>
        <?php endif; ?>
        <td><span class="status-badge <?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
        <?php if(is_admin()): ?>
        <td>
          <button class="table-action-btn" title="Edit"
                  onclick="openEdit(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)">
            <i class="ti ti-pencil"></i>
          </button>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($s['name'])) ?>?')">
            <input type="hidden" name="form" value="delete_school">
            <input type="hidden" name="school_id" value="<?= $s['id'] ?>">
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

<?php endif; ?>

</main>
</div>

<!-- ADD SCHOOL MODAL -->
<?php if(is_admin()): ?>
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal" style="max-width:540px">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add School</h2>
    <div class="modal-sub">Add a new beneficiary school to the CSI programme.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_school">
      <div class="form-group">
        <label class="form-label">School Name *</label>
        <input class="form-input" type="text" name="name" placeholder="e.g. Diepsloot Secondary School" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Province</label>
          <select class="form-select" name="province">
            <option value="">Select province</option>
            <?php foreach($provinces as $p): ?><option><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">District / Municipality</label>
          <select class="form-select" name="district">
            <option value="">Select district</option>
            <?php foreach($districts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Town / Location</label>
          <input class="form-input" type="text" name="location" placeholder="e.g. Diepsloot, Johannesburg">
        </div>
        <div class="form-group">
          <label class="form-label">School Type</label>
          <select class="form-select" name="school_type">
            <?php foreach($types as $t): ?><option><?= $t ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Funding Requested (R)</label>
          <input class="form-input" type="number" name="funding_requested" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Funding Granted (R)</label>
          <input class="form-input" type="number" name="funding_granted" min="0" value="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach($statuses as $sv): ?><option value="<?= $sv ?>"><?= ucfirst($sv) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add School</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT SCHOOL MODAL -->
<div class="modal-overlay" id="edit-modal" onclick="if(event.target.id==='edit-modal')closeModal('edit-modal')">
  <div class="modal" style="max-width:540px">
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
          <label class="form-label">Province</label>
          <select class="form-select" name="province" id="edit-province">
            <option value="">Select province</option>
            <?php foreach($provinces as $p): ?><option><?= $p ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">District / Municipality</label>
          <select class="form-select" name="district" id="edit-district">
            <option value="">Select district</option>
            <?php foreach($districts as $d): ?><option><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Town / Location</label>
          <input class="form-input" type="text" name="location" id="edit-location">
        </div>
        <div class="form-group">
          <label class="form-label">School Type</label>
          <select class="form-select" name="school_type" id="edit-type">
            <?php foreach($types as $t): ?><option><?= $t ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Funding Requested (R)</label>
          <input class="form-input" type="number" name="funding_requested" id="edit-freq" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Funding Granted (R)</label>
          <input class="form-input" type="number" name="funding_granted" id="edit-fgrt" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" id="edit-status">
          <?php foreach($statuses as $sv): ?><option value="<?= $sv ?>"><?= ucfirst($sv) ?></option><?php endforeach; ?>
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
function filterTable(inputId, tbodyId) {
  const q = document.getElementById(inputId).value.toLowerCase();
  let shown = 0;
  document.querySelectorAll('#'+tbodyId+' tr').forEach(r => {
    const show = r.textContent.toLowerCase().includes(q);
    r.style.display = show ? '' : 'none';
    if(show) shown++;
  });
  const cnt = document.getElementById('school-count');
  if(cnt) cnt.textContent = shown+' school'+(shown!==1?'s':'');
}
function openEdit(s) {
  document.getElementById('edit-id').value       = s.id;
  document.getElementById('edit-name').value     = s.name;
  document.getElementById('edit-province').value = s.province||'';
  document.getElementById('edit-district').value = s.district||'';
  document.getElementById('edit-location').value = s.location||'';
  document.getElementById('edit-type').value     = s.school_type||'Public';
  document.getElementById('edit-freq').value     = s.funding_requested||0;
  document.getElementById('edit-fgrt').value     = s.funding_granted||0;
  document.getElementById('edit-status').value   = s.status||'active';
  openModal('edit-modal');
}
</script>

<?php include 'includes/footer.php'; ?>