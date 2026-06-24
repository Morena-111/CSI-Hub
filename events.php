<?php
$active_page = 'events';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$success_msg = '';
$error_msg   = '';

// ── HANDLE ADD ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_event' && can_edit()) {
    $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location, event_type, partnership_id, company_id, school_id, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            trim($_POST['title']),
            trim($_POST['description'] ?? ''),
            $_POST['event_date'],
            $_POST['event_time'] ?: null,
            trim($_POST['location'] ?? ''),
            $_POST['event_type'],
            $_POST['partnership_id'] ?: null,
            $_POST['company_id'] ?: null,
            $_POST['school_id'] ?: null,
            'upcoming',
            $_SESSION['name'] ?? 'Admin',
        ]);
    header('Location: events.php?success=added'); exit;
}

// ── HANDLE STATUS UPDATE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_event' && can_edit()) {
    $pdo->prepare("UPDATE events SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['event_id']]);
    header('Location: events.php?success=updated'); exit;
}

// ── HANDLE DELETE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_event' && is_admin()) {
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$_POST['event_id']]);
    header('Location: events.php?success=deleted'); exit;
}

// ── FETCH DATA ────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'upcoming';

$status_filter = $filter === 'all' ? '' : "WHERE e.status = 'upcoming'";
if ($filter === 'completed') $status_filter = "WHERE e.status = 'completed'";
if ($filter === 'past')      $status_filter = "WHERE e.event_date < CURDATE()";

$events = $pdo->query("
    SELECT e.*,
           c.name AS company_name,
           s.name AS school_name
    FROM events e
    LEFT JOIN companies c ON c.id = e.company_id
    LEFT JOIN schools   s ON s.id = e.school_id
    $status_filter
    ORDER BY e.event_date ASC, e.event_time ASC
")->fetchAll();

// For dropdowns
$companies_list    = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$schools_list      = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
$partnerships_list = $pdo->query("
    SELECT p.id, CONCAT(c.name,' → ',s.name) AS label
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    ORDER BY c.name
")->fetchAll();

// Stats
$upcoming_count   = $pdo->query("SELECT COUNT(*) FROM events WHERE status='upcoming' AND event_date >= CURDATE()")->fetchColumn();
$this_week_count  = $pdo->query("SELECT COUNT(*) FROM events WHERE status='upcoming' AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$completed_count  = $pdo->query("SELECT COUNT(*) FROM events WHERE status='completed'")->fetchColumn();

$type_colors = [
    'Site Visit' => ['var(--orange)', 'var(--orange-soft)'],
    'Meeting'    => ['var(--teal)',   'var(--teal-soft)'],
    'Deadline'   => ['#e53e3e',       '#fff5f5'],
    'Review'     => ['var(--purple)', 'var(--purple-soft)'],
    'Other'      => ['var(--text-muted)', 'var(--surface)'],
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
    <span class="active-crumb">Events</span>
  </div>

  <?php if ($success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i>
    <?= $success==='added' ? 'Event added!' : ($success==='deleted' ? 'Event deleted.' : 'Event updated!') ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h1>Events</h1>
      <p>Site visits, meetings, deadlines and programme reviews</p>
    </div>
    <?php permission_btn('Add Event', can_edit(), 'ti-plus', 'btn btn-primary', "openModal('add-modal')") ?>
  </div>

  <!-- STATS -->
  <div class="stats-row three">
    <div class="stat-card orange">
      <div class="stat-label">Upcoming</div>
      <div class="stat-value orange"><?= $upcoming_count ?></div>
      <div class="stat-sub">Scheduled events</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">This Week</div>
      <div class="stat-value teal"><?= $this_week_count ?></div>
      <div class="stat-sub">Next 7 days</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Completed</div>
      <div class="stat-value"><?= $completed_count ?></div>
      <div class="stat-sub">All time</div>
    </div>
  </div>

  <!-- FILTER TABS -->
  <div style="display:flex;gap:8px;margin-bottom:20px">
    <?php foreach(['upcoming'=>'Upcoming','all'=>'All Events','completed'=>'Completed'] as $val=>$label): ?>
      <a href="events.php?filter=<?= $val ?>"
         style="padding:7px 16px;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none;
                <?= $filter===$val ? 'background:var(--orange-soft);color:var(--orange);' : 'background:var(--white);color:var(--text-muted);border:1px solid var(--border);' ?>">
        <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- EVENTS LIST -->
  <?php if (empty($events)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
    <i class="ti ti-calendar-off" style="font-size:48px;opacity:.3;display:block;margin-bottom:12px"></i>
    <p style="font-size:14px">No <?= $filter === 'upcoming' ? 'upcoming' : '' ?> events.</p>
    <?php if (can_edit()): ?>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('add-modal')">
        <i class="ti ti-plus"></i> Add an event
      </button>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <!-- Group by month -->
  <?php
  $grouped = [];
  foreach ($events as $ev) {
      $month = date('F Y', strtotime($ev['event_date']));
      $grouped[$month][] = $ev;
  }
  ?>

  <?php foreach ($grouped as $month => $month_events): ?>
  <div style="margin-bottom:28px">
    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border)">
      <?= $month ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach ($month_events as $ev):
        $colors = $type_colors[$ev['event_type']] ?? $type_colors['Other'];
        $is_today    = date('Y-m-d') === $ev['event_date'];
        $is_overdue  = $ev['event_date'] < date('Y-m-d') && $ev['status'] === 'upcoming';
        $days_away   = (strtotime($ev['event_date']) - strtotime(date('Y-m-d'))) / 86400;
      ?>
      <div style="background:var(--white);border:1px solid var(--border);border-radius:12px;padding:16px 18px;display:flex;align-items:flex-start;gap:16px;
                  <?= $is_today ? 'border-left:3px solid var(--orange);' : '' ?>
                  <?= $is_overdue ? 'opacity:.7;' : '' ?>">

        <!-- Date block -->
        <div style="text-align:center;min-width:46px;flex-shrink:0">
          <div style="font-size:22px;font-weight:700;color:<?= $colors[0] ?>;line-height:1;font-family:'Playfair Display',serif">
            <?= date('d', strtotime($ev['event_date'])) ?>
          </div>
          <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;font-weight:600;margin-top:2px">
            <?= date('M', strtotime($ev['event_date'])) ?>
          </div>
        </div>

        <!-- Content -->
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap">
            <span style="font-size:14px;font-weight:600;color:var(--text)"><?= htmlspecialchars($ev['title']) ?></span>
            <span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:12px;background:<?= $colors[1] ?>;color:<?= $colors[0] ?>">
              <?= htmlspecialchars($ev['event_type']) ?>
            </span>
            <?php if ($is_today): ?>
              <span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:12px;background:var(--orange-soft);color:var(--orange)">Today</span>
            <?php elseif ($is_overdue): ?>
              <span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:12px;background:#fff5f5;color:#c53030">Overdue</span>
            <?php elseif ($days_away <= 7 && $days_away > 0): ?>
              <span style="font-size:10.5px;font-weight:600;padding:2px 9px;border-radius:12px;background:var(--gold-soft);color:#9a6700">In <?= (int)$days_away ?> day<?= $days_away!=1?'s':'' ?></span>
            <?php endif; ?>
            <span class="status-badge <?= $ev['status'] === 'completed' ? 'completed' : ($is_overdue ? 'paused' : 'active') ?>" style="font-size:10px">
              <?= ucfirst($ev['status']) ?>
            </span>
          </div>

          <?php if ($ev['description']): ?>
            <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:6px;line-height:1.5">
              <?= htmlspecialchars($ev['description']) ?>
            </p>
          <?php endif; ?>

          <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:11.5px;color:var(--text-muted)">
            <?php if ($ev['event_time']): ?>
              <span><i class="ti ti-clock" style="font-size:12px"></i> <?= date('H:i', strtotime($ev['event_time'])) ?></span>
            <?php endif; ?>
            <?php if ($ev['location']): ?>
              <span><i class="ti ti-map-pin" style="font-size:12px"></i> <?= htmlspecialchars($ev['location']) ?></span>
            <?php endif; ?>
            <?php if ($ev['company_name']): ?>
              <span><i class="ti ti-building" style="font-size:12px"></i> <?= htmlspecialchars($ev['company_name']) ?></span>
            <?php endif; ?>
            <?php if ($ev['school_name']): ?>
              <span><i class="ti ti-school" style="font-size:12px"></i> <?= htmlspecialchars($ev['school_name']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:6px;flex-shrink:0">
          <?php if (can_edit() && $ev['status'] === 'upcoming'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="form" value="update_event">
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
              <input type="hidden" name="status" value="completed">
              <button type="submit" class="table-action-btn" title="Mark as completed" style="color:var(--teal)">
                <i class="ti ti-circle-check"></i>
              </button>
            </form>
          <?php endif; ?>
          <?php if (is_admin()): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this event?')">
              <input type="hidden" name="form" value="delete_event">
              <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
              <button type="submit" class="table-action-btn btn-danger-icon" title="Delete event">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</main>
</div>

<!-- ADD EVENT MODAL -->
<?php if (can_edit()): ?>
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Event</h2>
    <div class="modal-sub">Schedule a site visit, meeting, deadline or review.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_event">

      <div class="form-group">
        <label class="form-label">Event Title *</label>
        <input class="form-input" type="text" name="title"
               placeholder="e.g. Site Visit — Diepsloot Secondary" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input class="form-input" type="date" name="event_date"
                 min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Time</label>
          <input class="form-input" type="time" name="event_time">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Event Type</label>
          <select class="form-select" name="event_type">
            <?php foreach(['Site Visit','Meeting','Deadline','Review','Other'] as $t): ?>
              <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input class="form-input" type="text" name="location"
                 placeholder="e.g. School hall or Virtual">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Linked Partnership</label>
          <select class="form-select" name="partnership_id">
            <option value="">None</option>
            <?php foreach ($partnerships_list as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Linked Company</label>
          <select class="form-select" name="company_id">
            <option value="">None</option>
            <?php foreach ($companies_list as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Linked School</label>
        <select class="form-select" name="school_id">
          <option value="">None</option>
          <?php foreach ($schools_list as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="2"
                  placeholder="What is this event about?"></textarea>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add Event</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>

<?php include 'includes/footer.php'; ?>