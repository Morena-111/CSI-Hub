<?php
$active_page = 'school_needs';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS school_needs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  school_id     INT NOT NULL,
  title         VARCHAR(255) NOT NULL,
  description   TEXT,
  focus_area    VARCHAR(100),
  amount_needed DECIMAL(15,2) DEFAULT 0,
  amount_funded DECIMAL(15,2) DEFAULT 0,
  status        ENUM('open','partially_funded','fully_funded','closed') DEFAULT 'open',
  priority      ENUM('high','medium','low') DEFAULT 'medium',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS need_pledges (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  need_id     INT NOT NULL,
  company_id  INT NOT NULL,
  amount      DECIMAL(15,2) DEFAULT 0,
  message     TEXT,
  status      ENUM('pending','confirmed','declined') DEFAULT 'pending',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$success = ''; $error = '';
$is_school  = (!is_admin() && ($_SESSION['user_type']??'') === 'school');
$is_company = (!is_admin() && ($_SESSION['user_type']??'') === 'company');
$linked_id  = (int)($_SESSION['linked_id'] ?? 0);

// Handle post need (schools only)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='post_need' && ($is_school||is_admin())) {
    $school_id = $is_school ? $linked_id : (int)$_POST['school_id'];
    $title     = trim($_POST['title']??'');
    $desc      = trim($_POST['description']??'');
    $focus     = $_POST['focus_area']??'';
    $amount    = (float)str_replace(',','',$_POST['amount_needed']??0);
    $priority  = $_POST['priority']??'medium';
    if ($title && $school_id) {
        $pdo->prepare("INSERT INTO school_needs (school_id,title,description,focus_area,amount_needed,priority) VALUES (?,?,?,?,?,?)")
            ->execute([$school_id,$title,$desc,$focus,$amount,$priority]);
        $success = 'Need posted successfully. Companies can now view and fund it.';
    } else { $error = 'Title is required.'; }
}

// Handle pledge (companies only)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='pledge' && $is_company) {
    $need_id = (int)$_POST['need_id'];
    $amount  = (float)str_replace(',','',$_POST['pledge_amount']??0);
    $msg     = trim($_POST['pledge_message']??'');
    if ($need_id && $amount > 0) {
        $pdo->prepare("INSERT INTO need_pledges (need_id,company_id,amount,message) VALUES (?,?,?,?)")
            ->execute([$need_id,$linked_id,$amount,$msg]);
        // Update amount funded
        $pdo->prepare("UPDATE school_needs SET amount_funded=amount_funded+? WHERE id=?")->execute([$amount,$need_id]);
        $pdo->prepare("UPDATE school_needs SET status=CASE
            WHEN amount_funded>=amount_needed THEN 'fully_funded'
            WHEN amount_funded>0 THEN 'partially_funded'
            ELSE 'open' END WHERE id=?")->execute([$need_id]);
        $success = 'Pledge submitted! Research Unlimited will confirm and contact you.';
    } else { $error = 'Please enter a valid pledge amount.'; }
}

// Fetch needs
if ($is_school && $linked_id) {
    // School sees own needs only
    $needs_st = $pdo->prepare("
        SELECT n.*, s.name AS school_name,
               COUNT(p.id) AS pledge_count
        FROM school_needs n
        JOIN schools s ON s.id=n.school_id
        LEFT JOIN need_pledges p ON p.need_id=n.id
        WHERE n.school_id=?
        GROUP BY n.id ORDER BY n.created_at DESC
    ");
    $needs_st->execute([$linked_id]);
} else {
    // Admin/company sees all open needs
    $needs_st = $pdo->query("
        SELECT n.*, s.name AS school_name, s.province,
               COUNT(p.id) AS pledge_count
        FROM school_needs n
        JOIN schools s ON s.id=n.school_id
        LEFT JOIN need_pledges p ON p.need_id=n.id
        GROUP BY n.id ORDER BY n.priority DESC, n.created_at DESC
    ");
}
$needs = $needs_st->fetchAll();

$schools_list = $pdo->query("SELECT id,name FROM schools ORDER BY name")->fetchAll();
$focus_list   = ['STEM','Literacy','Digital Skills','Science','Arts & Culture','Skills Development','Sports','Health & Nutrition','Infrastructure','Other'];

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">School Needs</span>
</div>

<?php if(!is_admin() && ($_SESSION['user_type']??'')==='school'): ?>
<div style="background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:12px;padding:16px 20px;
            margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:11px">
    <div style="width:38px;height:38px;border-radius:9px;background:var(--teal);
                display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="ti ti-clipboard-list" style="font-size:18px;color:white"></i>
    </div>
    <div>
      <div style="font-size:13px;font-weight:700;color:white;margin-bottom:2px">
        Post needs based on your surveys
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.5)">
        Complete your Needs Assessment survey first to identify your school's priorities, then post them here.
      </div>
    </div>
  </div>
  <a href="surveys.php" class="btn" style="background:var(--teal);color:white;padding:9px 18px;font-size:12.5px;flex-shrink:0">
    <i class="ti ti-forms"></i> Go to Surveys
  </a>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1><?= $is_school ? 'My School\'s Needs' : ($is_company ? 'Schools Seeking Funding' : 'School Needs Board') ?></h1>
    <p><?= $is_school
      ? 'Post your school\'s needs so companies can fund them'
      : ($is_company ? 'Browse and pledge funding to schools in need'
      : 'All school funding requests across the platform') ?></p>
  </div>
  <div class="page-header-right">
    <?php if($is_school||is_admin()): ?>
    <button class="btn btn-primary" onclick="openModal('post-need-modal')">
      <i class="ti ti-plus"></i> Post a Need
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:11px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500">
  <i class="ti ti-circle-check" style="font-size:17px"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if($error): ?>
<div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:10px;padding:11px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-alert-circle" style="font-size:17px"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if(empty($needs)): ?>
<div style="background:white;border:1.5px dashed var(--border);border-radius:14px;padding:48px;text-align:center">
  <i class="ti ti-clipboard-list" style="font-size:40px;color:var(--teal);opacity:.3;display:block;margin-bottom:12px"></i>
  <h3 style="font-family:'Playfair Display',serif;font-size:20px;color:var(--text);margin-bottom:6px">
    <?= $is_school ? 'No needs posted yet' : 'No school needs available' ?>
  </h3>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px">
    <?= $is_school ? 'Post your first need and companies will be able to fund it.' : 'Schools will post their funding needs here.' ?>
  </p>
  <?php if($is_school): ?>
  <button class="btn btn-primary" onclick="openModal('post-need-modal')"><i class="ti ti-plus"></i> Post a Need</button>
  <?php endif; ?>
</div>
<?php else: ?>

<!-- Stats row -->
<?php
$open_count   = count(array_filter($needs, fn($n)=>$n['status']==='open'));
$funded_count = count(array_filter($needs, fn($n)=>$n['status']==='fully_funded'));
$total_needed = array_sum(array_column($needs,'amount_needed'));
$total_funded = array_sum(array_column($needs,'amount_funded'));
?>
<div class="stats-row" style="margin-bottom:20px">
  <div class="stat-card orange">
    <div class="stat-label">Open Needs</div>
    <div class="stat-value orange"><?= $open_count ?></div>
    <div class="stat-sub">Awaiting funding</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Fully Funded</div>
    <div class="stat-value teal"><?= $funded_count ?></div>
    <div class="stat-sub">Goals reached</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Total Requested</div>
    <div class="stat-value" style="font-size:20px;color:var(--gold)">R<?= number_format($total_needed) ?></div>
    <div class="stat-sub">Across all needs</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Total Pledged</div>
    <div class="stat-value purple" style="font-size:20px">R<?= number_format($total_funded) ?></div>
    <div class="stat-sub">Committed so far</div>
  </div>
</div>

<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach($needs as $n):
  $pct = $n['amount_needed']>0 ? min(100,round($n['amount_funded']/$n['amount_needed']*100)) : 0;
  $status_map = [
    'open'             => ['var(--orange-soft)','var(--orange)','Open'],
    'partially_funded' => ['var(--teal-soft)','var(--teal)','Partially Funded'],
    'fully_funded'     => ['#f0fff4','#00956a','Fully Funded ✓'],
    'closed'           => ['var(--surface)','var(--text-muted)','Closed'],
  ];
  $sm = $status_map[$n['status']] ?? $status_map['open'];
  $priority_color = ['high'=>'#c53030','medium'=>'#9a6700','low'=>'#00956a'][$n['priority']] ?? 'var(--text-muted)';
?>
<div style="background:white;border:1px solid var(--border);border-radius:13px;overflow:hidden;
            box-shadow:0 1px 8px rgba(26,31,46,.05)">

  <!-- Top bar: priority indicator -->
  <div style="height:3px;background:<?= $priority_color ?>"></div>

  <div style="display:flex;align-items:stretch">
    <!-- Main content -->
    <div style="padding:16px 20px;flex:1;border-right:1px solid var(--border)">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
        <div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <span style="font-size:10px;font-weight:700;color:var(--purple);
                         background:var(--purple-soft);padding:2px 8px;border-radius:10px">
              <?= htmlspecialchars($n['focus_area']) ?>
            </span>
            <span style="font-size:10px;font-weight:700;color:<?= $priority_color ?>">
              <?= ucfirst($n['priority']) ?> Priority
            </span>
          </div>
          <h3 style="font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:3px">
            <?= htmlspecialchars($n['title']) ?>
          </h3>
          <div style="font-size:12px;color:var(--text-muted)">
            <i class="ti ti-school" style="font-size:12px"></i>
            <?= htmlspecialchars($n['school_name']) ?>
            <?php if(!empty($n['province'])): ?> · <?= htmlspecialchars($n['province']) ?><?php endif; ?>
          </div>
        </div>
        <span style="background:<?= $sm[0] ?>;color:<?= $sm[1] ?>;padding:3px 11px;border-radius:20px;
                     font-size:11px;font-weight:600;white-space:nowrap;flex-shrink:0">
          <?= $sm[2] ?>
        </span>
      </div>
      <?php if($n['description']): ?>
      <p style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin-bottom:10px">
        <?= htmlspecialchars($n['description']) ?>
      </p>
      <?php endif; ?>
      <!-- Funding progress -->
      <div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:5px">
          <span>R<?= number_format($n['amount_funded']) ?> pledged</span>
          <span style="font-weight:700;color:<?= $pct>=100?'var(--teal)':'var(--orange)' ?>"><?= $pct ?>%</span>
        </div>
        <div style="height:7px;background:var(--border);border-radius:7px;overflow:hidden">
          <?php $bar_bg = $pct>=100 ? 'var(--teal)' : 'linear-gradient(90deg,var(--orange),var(--orange-mid))'; ?>
          <div style="height:100%;width:<?= $pct ?>%;border-radius:7px;background:<?= $bar_bg ?>;transition:width .4s"></div>
                      transition:width .4s"></div>
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
          Goal: <strong style="color:var(--text)">R<?= number_format($n['amount_needed']) ?></strong>
          · <?= $n['pledge_count'] ?> pledge<?= $n['pledge_count']!=1?'s':'' ?>
          · Posted <?= date('d M Y',strtotime($n['created_at'])) ?>
        </div>
      </div>
    </div>

    <!-- Action panel -->
    <div style="padding:16px 14px;display:flex;flex-direction:column;justify-content:center;
                gap:8px;min-width:130px;align-items:center">
      <?php if($is_company && $n['status']!=='fully_funded'): ?>
      <button class="btn btn-teal" style="font-size:12px;padding:8px 16px;width:100%;justify-content:center"
              onclick="openPledge(<?= $n['id'] ?>,'<?= htmlspecialchars(addslashes($n['title'])) ?>')">
        <i class="ti ti-heart"></i> Fund This
      </button>
      <div style="font-size:10.5px;color:var(--text-muted);text-align:center">
        R<?= number_format($n['amount_needed']-$n['amount_funded']) ?> still needed
      </div>
      <?php elseif($is_school): ?>
      <span style="font-size:12px;color:var(--text-muted);text-align:center">
        <?= $n['pledge_count'] ?> pledge<?= $n['pledge_count']!=1?'s':'' ?>
      </span>
      <?php elseif(is_admin()): ?>
      <a href="schools.php" class="btn btn-secondary" style="font-size:11.5px;padding:6px 12px">
        <i class="ti ti-eye"></i> View School
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>

<!-- POST NEED MODAL -->
<?php if($is_school||is_admin()): ?>
<div class="modal-overlay" id="post-need-modal" onclick="if(event.target.id==='post-need-modal')closeModal('post-need-modal')">
  <div class="modal" style="max-width:540px">
    <button class="modal-close" onclick="closeModal('post-need-modal')"><i class="ti ti-x"></i></button>
    <h2>Post a School Need</h2>
    <div class="modal-sub">Describe what your school needs — companies will be able to fund it directly.</div>
    <form method="POST">
      <input type="hidden" name="form" value="post_need">
      <?php if(is_admin()): ?>
      <div class="form-group">
        <label class="form-label">School *</label>
        <select class="form-select" name="school_id" required>
          <option value="">Select school…</option>
          <?php foreach($schools_list as $sl): ?>
          <option value="<?= $sl['id'] ?>"><?= htmlspecialchars($sl['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Need Title *</label>
        <input class="form-input" type="text" name="title" placeholder="e.g. Science laboratory equipment" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Focus Area</label>
          <select class="form-select" name="focus_area">
            <?php foreach($focus_list as $f): ?><option><?= $f ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priority</label>
          <select class="form-select" name="priority">
            <option value="high">High — urgent</option>
            <option value="medium" selected>Medium</option>
            <option value="low">Low — nice to have</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Amount Needed (R) *</label>
        <input class="form-input" type="number" name="amount_needed" min="0" placeholder="e.g. 150000" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="3"
          placeholder="Describe what you need, why it's important, and how it will help your learners…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('post-need-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> Post Need</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- PLEDGE MODAL -->
<?php if($is_company): ?>
<div class="modal-overlay" id="pledge-modal" onclick="if(event.target.id==='pledge-modal')closeModal('pledge-modal')">
  <div class="modal" style="max-width:440px">
    <button class="modal-close" onclick="closeModal('pledge-modal')"><i class="ti ti-x"></i></button>
    <h2>Fund This Need</h2>
    <div class="modal-sub" id="pledge-need-title"></div>
    <form method="POST">
      <input type="hidden" name="form" value="pledge">
      <input type="hidden" name="need_id" id="pledge-need-id">
      <div class="form-group">
        <label class="form-label">Pledge Amount (R) *</label>
        <input class="form-input" type="number" name="pledge_amount" min="1000" placeholder="e.g. 100000" required>
      </div>
      <div class="form-group">
        <label class="form-label">Message to School (optional)</label>
        <textarea class="form-input" name="pledge_message" rows="2"
          placeholder="e.g. We are happy to support your STEM programme…"></textarea>
      </div>
      <div style="background:var(--orange-soft);border-radius:8px;padding:10px 14px;font-size:12px;color:#7a3a12;margin-bottom:14px">
        <i class="ti ti-info-circle"></i> Research Unlimited will confirm your pledge and handle all logistics.
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
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openPledge(id, title) {
  document.getElementById('pledge-need-id').value = id;
  document.getElementById('pledge-need-title').textContent = 'Pledging to: ' + title;
  openModal('pledge-modal');
}
</script>

<?php include 'includes/footer.php'; ?>