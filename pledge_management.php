<?php
$active_page = 'pledge_management';
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';

$success = ''; $error = '';

// Handle confirm/decline
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $pid    = (int)$_POST['pledge_id'];
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');

    if ($action === 'confirm') {
        $pdo->prepare("UPDATE need_pledges SET status='confirmed', admin_note=?, confirmed_at=NOW() WHERE id=?")->execute([$note, $pid]);

        // Get full pledge details
        $pd = $pdo->prepare("
            SELECT np.*, sn.school_id, sn.title AS need_title, sn.focus_area,
                   c.name AS company_name, s.name AS school_name,
                   c.id AS cid, s.id AS sid
            FROM need_pledges np
            JOIN school_needs sn ON sn.id=np.need_id
            JOIN companies c ON c.id=np.company_id
            JOIN schools s ON s.id=sn.school_id
            WHERE np.id=?
        ");
        $pd->execute([$pid]); $p = $pd->fetch();

        if ($p) {
            // ── AUTO-CREATE PARTNERSHIP ───────────────────────────
            $exists = $pdo->prepare("SELECT id FROM partnerships WHERE company_id=? AND school_id=?");
            $exists->execute([$p['cid'], $p['sid']]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare("
                    INSERT INTO partnerships
                    (company_id, school_id, focus_area, amount, start_date, end_date, status, notes, created_at)
                    VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', ?, NOW())
                ")->execute([
                    $p['cid'], $p['sid'],
                    $p['focus_area'] ?? $p['need_title'],
                    $p['amount'],
                    "Auto-created from pledge #{$pid} — {$p['need_title']}"
                ]);
            } else {
                $pdo->prepare("UPDATE partnerships SET amount=amount+?, status='active' WHERE company_id=? AND school_id=?")
                    ->execute([$p['amount'], $p['cid'], $p['sid']]);
            }

            // Update school funding_granted
            $pdo->prepare("UPDATE schools SET funding_granted=COALESCE(funding_granted,0)+? WHERE id=?")
                ->execute([$p['amount'], $p['sid']]);

            // ── MOU RECORD ────────────────────────────────────────
            $fname = preg_replace('/[^A-Za-z0-9_.]/', '_',
                'MOU_'.$p['company_name'].'_'.$p['school_name'].'_'.date('Ymd').'.pdf');
            $pdo->prepare("INSERT INTO mous (pledge_id,company_id,school_id,need_id,amount,file_name,status) VALUES (?,?,?,?,?,?,'draft')")
                ->execute([$pid, $p['cid'], $p['sid'], $p['need_id'], $p['amount'], $fname]);

            // ── EMAIL NOTIFICATION ────────────────────────────────
            send_email(
                ADMIN_EMAIL,
                "Pledge Confirmed — " . $p['need_title'],
                "Pledge of R{$p['amount']} confirmed.\nCompany: {$p['company_name']}\nSchool: {$p['school_name']}\nNote: {$note}\n\nA partnership record has been automatically created and MOU generated."
            );
        }
        $success = "Pledge confirmed, partnership created and MOU generated.";
    } elseif ($action === 'decline') {
        $pdo->prepare("UPDATE need_pledges SET status='declined', admin_note=? WHERE id=?")->execute([$note, $pid]);
        // Reverse the funded amount
        $__ps = $pdo->prepare("SELECT * FROM need_pledges WHERE id=?");
        $__ps->execute([$pid]);
        $pledge = $__ps->fetch() ?: null;
        if ($pledge) {
            $pdo->prepare("UPDATE school_needs SET amount_funded = GREATEST(0, amount_funded - ?) WHERE id=?")->execute([$pledge['amount'], $pledge['need_id']]);
        }
        $success = "Pledge declined.";
    }
}

// Fetch all pledges with full details
$pledges = $pdo->query("
    SELECT np.*, 
           sn.title AS need_title, sn.amount_needed, sn.amount_funded, sn.school_id,
           c.name AS company_name,
           s.name AS school_name, s.province
    FROM need_pledges np
    JOIN school_needs sn ON sn.id = np.need_id
    JOIN companies c ON c.id = np.company_id
    JOIN schools s ON s.id = sn.school_id
    ORDER BY np.status ASC, np.created_at DESC
")->fetchAll();

$pending_count   = count(array_filter($pledges, fn($p)=>$p['status']==='pending'));
$confirmed_count = count(array_filter($pledges, fn($p)=>$p['status']==='confirmed'));
$total_pledged   = array_sum(array_column(array_filter($pledges, fn($p)=>$p['status']==='confirmed'), 'amount'));

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Pledge Management</span>
</div>

<div class="page-header">
  <div>
    <h1>Pledge Management</h1>
    <p>Review, confirm or decline funding pledges from corporate partners</p>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check" style="font-size:17px"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-row" style="margin-bottom:22px">
  <div class="stat-card orange">
    <div class="stat-label">Pending Review</div>
    <div class="stat-value orange"><?= $pending_count ?></div>
    <div class="stat-sub">Awaiting your decision</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Confirmed</div>
    <div class="stat-value teal"><?= $confirmed_count ?></div>
    <div class="stat-sub">Active pledges</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Total Confirmed Value</div>
    <div class="stat-value" style="color:var(--gold);font-size:20px">R<?= number_format($total_pledged) ?></div>
    <div class="stat-sub">Committed funding</div>
  </div>
</div>

<?php if(empty($pledges)): ?>
<div style="background:white;border:1.5px dashed var(--border);border-radius:14px;padding:48px;text-align:center">
  <i class="ti ti-heart-handshake" style="font-size:40px;opacity:.2;display:block;margin-bottom:12px"></i>
  <p style="color:var(--text-muted)">No pledges yet. They will appear here when companies fund school needs.</p>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach($pledges as $p):
  $sc = ['pending'=>['#fffbea','#9a6700'],'confirmed'=>['var(--teal-soft)','#00956a'],'declined'=>['#fde9e9','#c53030']][$p['status']]??['var(--surface)','var(--text-muted)'];
?>
<div style="background:white;border:1px solid var(--border);border-radius:13px;overflow:hidden;box-shadow:0 1px 8px rgba(26,31,46,.05)">
  <div style="display:flex;align-items:stretch">
    <!-- Status band -->
    <div style="width:5px;background:<?= $sc[1] ?>;flex-shrink:0"></div>

    <!-- Main info -->
    <div style="padding:16px 20px;flex:1;border-right:1px solid var(--border)">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px">
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:3px">
            <?= htmlspecialchars($p['need_title']) ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted)">
            <i class="ti ti-building" style="color:var(--orange)"></i> <?= htmlspecialchars($p['company_name']) ?>
            &nbsp;→&nbsp;
            <i class="ti ti-school" style="color:var(--teal)"></i> <?= htmlspecialchars($p['school_name']) ?>
            (<?= htmlspecialchars($p['province']) ?>)
          </div>
        </div>
        <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;font-size:11px;font-weight:700;padding:3px 11px;border-radius:20px;white-space:nowrap;flex-shrink:0">
          <?= ucfirst($p['status']) ?>
        </span>
      </div>
      <?php if($p['message']): ?>
      <div style="font-size:12px;color:var(--text-muted);font-style:italic;margin-top:6px;padding:8px 12px;background:var(--surface);border-radius:8px">
        "<?= htmlspecialchars($p['message']) ?>"
      </div>
      <?php endif; ?>
      <?php if($p['admin_note']): ?>
      <div style="font-size:11.5px;color:#00956a;margin-top:6px"><i class="ti ti-note"></i> Admin note: <?= htmlspecialchars($p['admin_note']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Amount -->
    <div style="padding:16px 18px;flex-shrink:0;display:flex;flex-direction:column;justify-content:center;border-right:1px solid var(--border);min-width:130px">
      <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Pledge Amount</div>
      <div style="font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:var(--orange)">R<?= number_format($p['amount']) ?></div>
      <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px"><?= date('d M Y',strtotime($p['created_at'])) ?></div>
    </div>

    <!-- Actions (only for pending) -->
    <?php if($p['status']==='pending'): ?>
    <div style="padding:16px 14px;display:flex;flex-direction:column;justify-content:center;gap:8px;min-width:140px">
      <button class="btn btn-teal" style="font-size:12px;padding:7px 12px;justify-content:center"
              onclick="openConfirm(<?= $p['id'] ?>,'confirm','<?= htmlspecialchars(addslashes($p['company_name'])) ?>', <?= $p['amount'] ?>)">
        <i class="ti ti-check"></i> Confirm
      </button>
      <button class="btn btn-secondary" style="font-size:12px;padding:7px 12px;justify-content:center;color:#c53030;border-color:#fed7d7"
              onclick="openConfirm(<?= $p['id'] ?>,'decline','<?= htmlspecialchars(addslashes($p['company_name'])) ?>', <?= $p['amount'] ?>)">
        <i class="ti ti-x"></i> Decline
      </button>
    </div>
    <?php elseif($p['status']==='confirmed'): ?>
    <div style="padding:16px 14px;display:flex;flex-direction:column;justify-content:center;gap:8px;min-width:140px">
      <a href="mou_generate.php?pledge_id=<?= $p['id'] ?>" class="btn btn-secondary" style="font-size:12px;padding:7px 12px;justify-content:center">
        <i class="ti ti-file-certificate"></i> View MOU
      </a>
    </div>
    <?php else: ?>
    <div style="padding:16px 14px;min-width:140px"></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</main>
</div>

<!-- Confirm/Decline Modal -->
<div class="modal-overlay" id="action-modal" onclick="if(event.target.id==='action-modal')closeModal('action-modal')">
  <div class="modal" style="max-width:440px">
    <button class="modal-close" onclick="closeModal('action-modal')"><i class="ti ti-x"></i></button>
    <h2 id="modal-title">Confirm Pledge</h2>
    <div class="modal-sub" id="modal-sub"></div>
    <form method="POST">
      <input type="hidden" name="pledge_id" id="modal-pledge-id">
      <input type="hidden" name="action" id="modal-action">
      <div class="form-group">
        <label class="form-label">Admin Note (optional)</label>
        <textarea class="form-input" name="admin_note" rows="2" placeholder="Internal note about this decision…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('action-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-btn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
function openConfirm(id, action, company, amount) {
  document.getElementById('modal-pledge-id').value = id;
  document.getElementById('modal-action').value = action;
  const btn = document.getElementById('modal-btn');
  if (action === 'confirm') {
    document.getElementById('modal-title').textContent = 'Confirm Pledge';
    document.getElementById('modal-sub').textContent = 'Confirm R'+amount.toLocaleString()+' pledge from '+company+'? This will create an MOU record.';
    btn.className = 'btn btn-teal';
    btn.textContent = 'Yes, Confirm';
  } else {
    document.getElementById('modal-title').textContent = 'Decline Pledge';
    document.getElementById('modal-sub').textContent = 'Decline R'+amount.toLocaleString()+' pledge from '+company+'? This cannot be undone.';
    btn.className = 'btn btn-secondary';
    btn.style.color = '#c53030';
    btn.textContent = 'Yes, Decline';
  }
  openModal('action-modal');
}
</script>
<?php include 'includes/footer.php'; ?>