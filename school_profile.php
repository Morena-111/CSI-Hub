<?php
$active_page = 'browse_schools';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('browse_schools.php');

// Get school
$st = $pdo->prepare("SELECT * FROM schools WHERE id=?");
$st->execute([$id]); $school = $st->fetch();
if (!$school) redirect('browse_schools.php');

// School users can only view their own
if (!is_admin() && ($_SESSION['user_type']??'')==='school' && (int)($_SESSION['linked_id']??0) !== $id) {
    redirect('dashboard.php');
}

$success = ''; $error = '';
$linked_company = (int)($_SESSION['linked_id'] ?? 0);

// Handle pledge
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='pledge') {
    $need_id = (int)$_POST['need_id'];
    $amount  = (float)str_replace(',','',$_POST['pledge_amount']??0);
    $msg     = trim($_POST['pledge_message']??'');
    if ($amount > 0 && $need_id && $linked_company) {
        $pdo->prepare("INSERT INTO need_pledges (need_id,company_id,amount,message) VALUES (?,?,?,?)")
            ->execute([$need_id,$linked_company,$amount,$msg]);
        $pdo->prepare("UPDATE school_needs SET amount_funded=amount_funded+? WHERE id=?")->execute([$amount,$need_id]);
        $pdo->prepare("UPDATE school_needs SET status=CASE
            WHEN amount_funded>=amount_needed THEN 'fully_funded'
            WHEN amount_funded>0 THEN 'partially_funded'
            ELSE 'open' END WHERE id=?")->execute([$need_id]);
        // Email notify admin
        send_email(ADMIN_EMAIL, "New Pledge — ".$school['name'], "Company ID {$linked_company} pledged R{$amount} to need ID {$need_id} at ".$school['name']);
        $success = "Pledge of R".number_format($amount)." submitted! Research Unlimited will confirm within 2 business days.";
    } else {
        $error = 'Invalid pledge amount.';
    }
}

$needs = $pdo->prepare("SELECT * FROM school_needs WHERE school_id=? ORDER BY priority DESC, created_at DESC");
$needs->execute([$id]); $needs = $needs->fetchAll();

// Fetch partnerships (funders)
$partners = $pdo->prepare("
    SELECT p.*, c.name AS company_name, c.sector
    FROM partnerships p JOIN companies c ON c.id=p.company_id
    WHERE p.school_id=? ORDER BY p.status, p.start_date DESC
");
$partners->execute([$id]); $partners = $partners->fetchAll();

// Fetch impact
$impact = $pdo->prepare("
    SELECT COALESCE(SUM(i.learners),0) AS learners,
           COALESCE(SUM(i.educators),0) AS educators,
           COUNT(DISTINCT i.partnership_id) AS programmes
    FROM impact_stats i JOIN partnerships p ON p.id=i.partnership_id WHERE p.school_id=?
");
$impact->execute([$id]); $impact = $impact->fetch();

// Fetch documents
$docs = $pdo->prepare("SELECT * FROM documents WHERE school_id=? AND status='approved' ORDER BY created_at DESC LIMIT 5");
$docs->execute([$id]); $docs = $docs->fetchAll();

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home"></i>
  <span style="color:var(--text-muted)">Home</span> <span style="color:var(--border)">›</span>
  <a href="browse_schools.php" style="color:var(--text-muted)">Browse Schools</a>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb"><?= htmlspecialchars($school['name']) ?></span>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check" style="font-size:18px"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-alert-circle" style="font-size:18px"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- SCHOOL HERO -->
<div style="background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:16px;padding:28px 32px;margin-bottom:22px;position:relative;overflow:hidden">
  <div style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(232,84,26,.08)"></div>
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;position:relative;z-index:1">
    <div>
      <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">
        <?= htmlspecialchars($school['school_type']??'School') ?> · <?= htmlspecialchars($school['province']??'') ?>
      </div>
      <h1 style="font-family:'Playfair Display',serif;font-size:26px;color:white;margin-bottom:6px">
        <?= htmlspecialchars($school['name']) ?>
      </h1>
      <?php if($school['district']): ?>
      <div style="font-size:13px;color:rgba(255,255,255,.5)">
        <i class="ti ti-map-pin"></i> <?= htmlspecialchars($school['district']) ?>
        <?php if($school['location']): ?>, <?= htmlspecialchars($school['location']) ?><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <?php foreach([
        ['learners'  , number_format($school['learners']??0), 'Learners'   , 'var(--orange)'],
        ['educators' , number_format($school['educators']??0),'Educators'  , 'var(--teal)'],
        ['programmes', $impact['programmes'], 'Programmes', '#a78bfa'],
      ] as [$k,$v,$l,$c]): ?>
      <div style="text-align:center;background:rgba(255,255,255,.07);border-radius:10px;padding:12px 18px">
        <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:<?= $c ?>"><?= $v ?></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.06em;margin-top:2px"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px">

<!-- LEFT COLUMN -->
<div>
  <!-- NEEDS / FUND THIS SCHOOL -->
  <div class="widget" id="needs">
    <div class="widget-title"><i class="ti ti-heart-handshake" style="color:var(--orange)"></i>
      Funding Needs
      <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:6px"><?= count($needs) ?> need<?= count($needs)!=1?'s':'' ?></span>
    </div>
    <?php if(empty($needs)): ?>
    <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px">No needs posted yet.</p>
    <?php else: ?>
    <?php foreach($needs as $n):
      $pct = $n['amount_needed']>0 ? min(100,round($n['amount_funded']/$n['amount_needed']*100)) : 0;
      $sc  = ['open'=>['var(--orange-soft)','var(--orange)'],'partially_funded'=>['var(--teal-soft)','var(--teal)'],'fully_funded'=>['#f0fff4','#00956a'],'closed'=>['var(--surface)','var(--text-muted)']][$n['status']]??['var(--surface)','var(--text-muted)'];
    ?>
    <div style="border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div>
          <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:3px"><?= htmlspecialchars($n['title']) ?></div>
          <?php if($n['description']): ?>
          <div style="font-size:12px;color:var(--text-muted);line-height:1.5"><?= htmlspecialchars($n['description']) ?></div>
          <?php endif; ?>
        </div>
        <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;font-size:10.5px;font-weight:600;padding:3px 10px;border-radius:12px;white-space:nowrap;flex-shrink:0;margin-left:10px">
          <?= ucfirst(str_replace('_',' ',$n['status'])) ?>
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="flex:1;height:6px;background:var(--border);border-radius:6px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>=100?'var(--teal)':'var(--orange)' ?>;border-radius:6px"></div>
        </div>
        <span style="font-size:11px;font-weight:700;color:var(--orange)"><?= $pct ?>%</span>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:12px;color:var(--text-muted)">
          R<?= number_format($n['amount_funded']) ?> of R<?= number_format($n['amount_needed']) ?>
        </span>
        <?php if(!is_admin() && ($_SESSION['user_type']??'')==='company' && $n['status']!=='fully_funded'): ?>
        <button class="btn btn-primary" style="font-size:12px;padding:6px 14px"
                onclick="openPledge(<?= $n['id'] ?>,'<?= htmlspecialchars(addslashes($n['title'])) ?>',<?= $n['amount_needed']-$n['amount_funded'] ?>)">
          <i class="ti ti-heart"></i> Fund This
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- CURRENT PARTNERS/FUNDERS -->
  <?php if(!empty($partners)): ?>
  <div class="widget">
    <div class="widget-title"><i class="ti ti-users" style="color:var(--teal)"></i> Current Partners</div>
    <?php foreach($partners as $p):
      $prog = $p['status']==='completed'?100:($p['status']==='pending'?0:(time()>=strtotime($p['end_date'])?100:round((time()-strtotime($p['start_date']))/(strtotime($p['end_date'])-strtotime($p['start_date']))*100)));
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
      <div style="width:36px;height:36px;border-radius:9px;background:var(--orange-soft);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">
        <?= strtoupper(substr($p['company_name'],0,2)) ?>
      </div>
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= htmlspecialchars($p['company_name']) ?></div>
        <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars($p['focus_area']) ?> · R<?= number_format($p['amount']) ?></div>
        <div style="height:4px;background:var(--border);border-radius:4px;overflow:hidden;margin-top:5px">
          <div style="height:100%;width:<?= $prog ?>%;background:var(--teal);border-radius:4px"></div>
        </div>
      </div>
      <span class="status-badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- RIGHT COLUMN -->
<div>
  <!-- School info card -->
  <div class="widget" style="margin-bottom:16px">
    <div class="widget-title"><i class="ti ti-info-circle"></i> School Info</div>
    <?php
    $school_info_rows = [
        ['Province',      $school['province']     ?? '—'],
        ['District',      $school['district']     ?? '—'],
        ['Type',          $school['school_type']  ?? '—'],
        ['Learners',      number_format($school['learners']  ?? 0)],
        ['Educators',     number_format($school['educators'] ?? 0)],
        ['Funding Req.',  'R' . number_format($school['funding_requested'] ?? 0)],
        ['Funded So Far', 'R' . number_format($school['funding_granted']   ?? 0)],
    ];
    foreach ($school_info_rows as [$l, $v]): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:12.5px">
      <span style="color:var(--text-muted)"><?= $l ?></span>
      <span style="font-weight:600;color:var(--text)"><?= htmlspecialchars((string)$v) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Approved docs -->
  <?php if(!empty($docs)): ?>
  <div class="widget">
    <div class="widget-title"><i class="ti ti-file-check" style="color:var(--teal)"></i> Documents</div>
    <?php foreach($docs as $d): ?>
    <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border);font-size:12px">
      <i class="ti ti-file-invoice" style="color:var(--orange)"></i>
      <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($d['title']) ?></span>
      <a href="uploads/documents/<?= htmlspecialchars($d['file_name']) ?>" target="_blank" style="color:var(--orange)">View</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div><!-- grid -->

</main>
</div>

<!-- Pledge Modal -->
<?php if(!is_admin() && ($_SESSION['user_type']??'')==='company'): ?>
<div class="modal-overlay" id="pledge-modal" onclick="if(event.target.id==='pledge-modal')closeModal('pledge-modal')">
  <div class="modal" style="max-width:440px">
    <button class="modal-close" onclick="closeModal('pledge-modal')"><i class="ti ti-x"></i></button>
    <h2>Fund This Need</h2>
    <div class="modal-sub" id="pledge-title"></div>
    <form method="POST">
      <input type="hidden" name="form" value="pledge">
      <input type="hidden" name="need_id" id="pledge-need-id">
      <div class="form-group">
        <label class="form-label">Pledge Amount (R) *</label>
        <input class="form-input" type="number" name="pledge_amount" id="pledge-max" min="1000" required>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">Max needed: <span id="pledge-gap"></span></div>
      </div>
      <div class="form-group">
        <label class="form-label">Message to School</label>
        <textarea class="form-input" name="pledge_message" rows="2" placeholder="Optional message…"></textarea>
      </div>
      <div style="background:var(--orange-soft);border-radius:8px;padding:10px 14px;font-size:12px;color:#7a3a12;margin-bottom:14px">
        <i class="ti ti-info-circle"></i> Research Unlimited will confirm your pledge and manage all logistics.
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('pledge-modal')">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="ti ti-heart"></i> Submit Pledge</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function openPledge(id,title,gap){
  document.getElementById('pledge-need-id').value=id;
  document.getElementById('pledge-title').textContent='Pledging to: '+title;
  document.getElementById('pledge-gap').textContent='R'+gap.toLocaleString();
  openModal('pledge-modal');
}
</script>
<?php include 'includes/footer.php'; ?>