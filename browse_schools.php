<?php
$active_page = 'browse_schools';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Company users + admin can browse schools
if (!is_admin() && ($_SESSION['user_type']??'') === 'school') {
    redirect('dashboard.php');
}

$linked_id = (int)($_SESSION['linked_id'] ?? 0);
$search    = trim($_GET['search'] ?? '');
$province  = $_GET['province'] ?? '';
$focus     = $_GET['focus'] ?? '';

// Fetch schools with their needs and impact data
$where = "s.status = 'active'";
$params = [];
if ($search)   { $where .= " AND s.name LIKE ?";         $params[] = "%$search%"; }
if ($province) { $where .= " AND s.province = ?";         $params[] = $province; }
if ($focus)    { $where .= " AND n.focus_area LIKE ?";    $params[] = "%$focus%"; }

$schools = $pdo->prepare("
    SELECT s.*,
           COUNT(DISTINCT p.id)  AS partnership_count,
           COUNT(DISTINCT n.id)  AS needs_count,
           COALESCE(SUM(n.amount_needed - n.amount_funded),0) AS funding_gap,
           COALESCE(SUM(i.learners),0) AS total_learners,
           COALESCE(SUM(i.educators),0) AS total_educators,
           MAX(n.priority) AS top_priority
    FROM schools s
    LEFT JOIN partnerships p ON p.school_id = s.id
    LEFT JOIN school_needs n ON n.school_id = s.id AND n.status != 'fully_funded'
    LEFT JOIN impact_stats i ON i.partnership_id = p.id
    WHERE $where
    GROUP BY s.id
    ORDER BY needs_count DESC, s.name ASC
");
$schools->execute($params);
$schools = $schools->fetchAll();

$provinces = ['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape',
              'Limpopo','Mpumalanga','North West','Free State','Northern Cape'];

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Browse Schools</span>
</div>

<div class="page-header">
  <div>
    <h1>Browse Schools</h1>
    <p>Explore beneficiary schools and choose where to direct your CSI investment</p>
  </div>
  <div class="page-header-right">
    <div class="search-wrap">
      <i class="ti ti-search"></i>
      <input class="filter-input" type="text" placeholder="Search schools…"
             value="<?= htmlspecialchars($search) ?>"
             onchange="window.location='browse_schools.php?search='+this.value+'&province=<?= urlencode($province) ?>'">
    </div>
    <select class="form-select" style="width:auto" onchange="window.location='browse_schools.php?province='+this.value+'&search=<?= urlencode($search) ?>&focus=<?= urlencode($focus) ?>'">
      <option value="">All Provinces</option>
      <?php foreach($provinces as $p): ?>
      <option value="<?= $p ?>" <?= $province===$p?'selected':'' ?>><?= $p ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" style="width:auto" onchange="window.location='browse_schools.php?focus='+this.value+'&search=<?= urlencode($search) ?>&province=<?= urlencode($province) ?>'">
      <option value="">All Focus Areas</option>
      <?php foreach(['STEM','Literacy','Digital Skills','Science','Arts & Culture','Skills Development','Sports','Health & Nutrition','Infrastructure'] as $fa): ?>
      <option value="<?= $fa ?>" <?= $focus===$fa?'selected':'' ?>><?= $fa ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Stats summary -->
<div class="stats-row" style="margin-bottom:22px">
  <div class="stat-card teal">
    <div class="stat-label">Schools Available</div>
    <div class="stat-value teal"><?= count($schools) ?></div>
    <div class="stat-sub">Ready for investment</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Total Funding Gap</div>
    <div class="stat-value orange" style="font-size:20px">R<?= number_format(array_sum(array_column($schools,'funding_gap'))) ?></div>
    <div class="stat-sub">Still needed across all schools</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Learners to Reach</div>
    <div class="stat-value purple"><?= number_format(array_sum(array_column($schools,'total_learners'))) ?></div>
    <div class="stat-sub">Across all schools</div>
  </div>
</div>

<?php if(empty($schools)): ?>
<div style="text-align:center;padding:48px;background:white;border:1px solid var(--border);border-radius:14px">
  <i class="ti ti-school" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px"></i>
  <p style="color:var(--text-muted)">No schools found matching your criteria.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px">
<?php foreach($schools as $s):
  $priority_colors = ['high'=>['#fde9e9','#c53030'],'medium'=>['var(--orange-soft)','var(--orange)'],'low'=>['var(--teal-soft)','var(--teal)']];
  $pc = $priority_colors[$s['top_priority']??'medium'] ?? $priority_colors['medium'];
?>
<div style="background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden;
            box-shadow:0 2px 12px rgba(26,31,46,.05);transition:all .2s"
     onmouseenter="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 28px rgba(26,31,46,.12)'"
     onmouseleave="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(26,31,46,.05)'">

  <!-- Card header -->
  <div style="padding:18px 20px;border-bottom:1px solid var(--border)">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
      <div>
        <h3 style="font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:3px;line-height:1.3">
          <?= htmlspecialchars($s['name']) ?>
        </h3>
        <div style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:4px">
          <i class="ti ti-map-pin" style="font-size:12px"></i>
          <?= htmlspecialchars($s['province']??'') ?>
          <?php if($s['district']): ?> · <?= htmlspecialchars($s['district']) ?><?php endif; ?>
        </div>
      </div>
      <?php if($s['top_priority']): ?>
      <span style="background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;font-size:10px;font-weight:700;
                   padding:3px 9px;border-radius:10px;white-space:nowrap;flex-shrink:0">
        <?= ucfirst($s['top_priority']??'') ?> Need
      </span>
      <?php endif; ?>
    </div>

    <!-- Quick stats -->
    <div style="display:flex;gap:14px;flex-wrap:wrap">
      <div style="font-size:11.5px;color:var(--text-muted)">
        <i class="ti ti-users" style="color:var(--teal)"></i>
        <?= number_format($s['learners']??0) ?> learners
      </div>
      <div style="font-size:11.5px;color:var(--text-muted)">
        <i class="ti ti-user-check" style="color:var(--purple)"></i>
        <?= number_format($s['educators']??0) ?> educators
      </div>
      <div style="font-size:11.5px;color:var(--text-muted)">
        <i class="ti ti-heart-handshake" style="color:var(--orange)"></i>
        <?= $s['needs_count'] ?> open need<?= $s['needs_count']!=1?'s':'' ?>
      </div>
    </div>
  </div>

  <!-- Funding gap -->
  <?php if($s['funding_gap'] > 0): ?>
  <div style="padding:12px 20px;background:var(--orange-soft);border-bottom:1px solid rgba(232,84,26,.1)">
    <div style="font-size:11px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">
      Funding Needed
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--orange)">
      R<?= number_format($s['funding_gap']) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <div style="padding:14px 20px;display:flex;gap:8px">
    <a href="school_profile.php?id=<?= $s['id'] ?>" class="btn btn-secondary" style="flex:1;justify-content:center;font-size:12px">
      <i class="ti ti-eye"></i> View Profile
    </a>
    <?php if(!is_admin()): ?>
    <a href="school_profile.php?id=<?= $s['id'] ?>#needs" class="btn btn-primary" style="flex:1;justify-content:center;font-size:12px">
      <i class="ti ti-heart"></i> Fund School
    </a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include 'includes/footer.php'; ?>