<?php
$active_page = 'team';
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';
include 'includes/header.php';

$data_dir     = __DIR__ . '/data';
$admins_file  = $data_dir . '/admin_users.json';
$signups_file = $data_dir . '/pending_signups.json';

$saved_admins = file_exists($admins_file)  ? json_decode(file_get_contents($admins_file),  true) ?? [] : [];
$pending      = file_exists($signups_file) ? json_decode(file_get_contents($signups_file), true) ?? [] : [];
$unapproved   = array_filter($pending, fn($v) => !($v['approved'] ?? false));
$approved     = array_filter($pending, fn($v) => ($v['approved'] ?? false));

$team = array_merge(
    [['name'=>'Admin User','username'=>'admin','role'=>'Administrator','email'=>'admin@researchunlimited.co.za','status'=>'active']],
    array_values($saved_admins)
);

$add_error   = '';
$add_success = '';

// ── HANDLE ADD MEMBER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_member') {
    $new_name  = trim($_POST['new_name'] ?? '');
    $new_user  = strtolower(trim($_POST['new_username'] ?? ''));
    $new_email = trim($_POST['new_email'] ?? '');
    $new_pass  = $_POST['new_password'] ?? '';
    $new_role  = $_POST['new_role'] ?? 'Administrator';

    if (strlen($new_name) < 2)   $add_error = 'Full name is required.';
    elseif (strlen($new_user) < 3) $add_error = 'Username must be at least 3 characters.';
    elseif ($new_user === 'admin' || isset($saved_admins[$new_user])) $add_error = 'Username already exists.';
    elseif (strlen($new_pass) < 6) $add_error = 'Password must be at least 6 characters.';
    else {
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        $saved_admins[$new_user] = [
            'name'     => $new_name,
            'username' => $new_user,
            'email'    => $new_email,
            'password' => $new_pass,
            'role'     => $new_role,
            'status'   => 'active',
            'created'  => date('Y-m-d H:i:s'),
        ];
        file_put_contents($admins_file, json_encode($saved_admins, JSON_PRETTY_PRINT));
        $add_success = "Member \"{$new_name}\" added! They can now log in via the Admin portal.";
        $team = array_merge(
            [['name'=>'Admin User','username'=>'admin','role'=>'Administrator','email'=>'admin@researchunlimited.co.za','status'=>'active']],
            array_values($saved_admins)
        );
    }
}

// ── HANDLE REMOVE MEMBER ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'remove_member') {
    $del_user = $_POST['del_username'] ?? '';
    if ($del_user !== 'admin' && isset($saved_admins[$del_user])) {
        unset($saved_admins[$del_user]);
        file_put_contents($admins_file, json_encode($saved_admins, JSON_PRETTY_PRINT));
        $team = array_merge(
            [['name'=>'Admin User','username'=>'admin','role'=>'Administrator','email'=>'admin@researchunlimited.co.za','status'=>'active']],
            array_values($saved_admins)
        );
    }
}

// ── HANDLE EDIT TEAM MEMBER ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_member') {
    $edit_user  = $_POST['edit_username'] ?? '';
    $edit_name  = trim($_POST['edit_name'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_role  = $_POST['edit_role'] ?? 'Administrator';
    $edit_pass  = $_POST['edit_password'] ?? '';

    if ($edit_user !== 'admin' && isset($saved_admins[$edit_user]) && strlen($edit_name) >= 2) {
        $saved_admins[$edit_user]['name']  = $edit_name;
        $saved_admins[$edit_user]['email'] = $edit_email;
        $saved_admins[$edit_user]['role']  = $edit_role;
        if (strlen($edit_pass) >= 6) {
            $saved_admins[$edit_user]['password'] = $edit_pass;
        }
        file_put_contents($admins_file, json_encode($saved_admins, JSON_PRETTY_PRINT));
        $add_success = "Member \"{$edit_name}\" updated.";
        $team = array_merge(
            [['name'=>'Admin User','username'=>'admin','role'=>'Administrator','email'=>'admin@researchunlimited.co.za','status'=>'active']],
            array_values($saved_admins)
        );
    }
}

// ── HANDLE EDIT APPROVED USER ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'edit_user') {
    $edit_uname = $_POST['edit_uname'] ?? '';
    $edit_name  = trim($_POST['edit_uname_name'] ?? '');
    $edit_org   = trim($_POST['edit_uname_org'] ?? '');
    $edit_email = trim($_POST['edit_uname_email'] ?? '');
    $edit_pass  = $_POST['edit_uname_password'] ?? '';

    if (isset($pending[$edit_uname]) && strlen($edit_name) >= 2) {
        $pending[$edit_uname]['name']  = $edit_name;
        $pending[$edit_uname]['org']   = $edit_org;
        $pending[$edit_uname]['email'] = $edit_email;
        if (strlen($edit_pass) >= 6) {
            $pending[$edit_uname]['password'] = $edit_pass;
        }
        file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
        $add_success = "User \"{$edit_name}\" updated.";
        $approved = array_filter($pending, fn($v) => ($v['approved'] ?? false));
        $unapproved = array_filter($pending, fn($v) => !($v['approved'] ?? false));
    }
}

// ── HANDLE REMOVE APPROVED USER ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'remove_user') {
    $del_uname = $_POST['del_uname'] ?? '';
    if (isset($pending[$del_uname])) {
        unset($pending[$del_uname]);
        file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
        $add_success = "User account removed.";
        $approved = array_filter($pending, fn($v) => ($v['approved'] ?? false));
        $unapproved = array_filter($pending, fn($v) => !($v['approved'] ?? false));
    }
}
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>

<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Team</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Team</h1>
      <p>Manage staff accounts and access levels</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <span class="admin-badge"><i class="ti ti-shield-check"></i> Admin Only</span>
      <button class="btn btn-primary" onclick="openModal('add-modal')">
        <i class="ti ti-user-plus"></i> Add Member
      </button>
    </div>
  </div>

  <?php if ($add_success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i><?= htmlspecialchars($add_success) ?>
  </div>
  <?php endif; ?>
  <?php if ($add_error): ?>
  <div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-alert-circle"></i><?= htmlspecialchars($add_error) ?>
  </div>
  <?php endif; ?>

  <div class="stats-row three">
    <div class="stat-card orange">
      <div class="stat-label">Total Members</div>
      <div class="stat-value orange"><?= count($team) ?></div>
      <div class="stat-sub">Admin accounts</div>
    </div>
    <div class="stat-card teal">
      <div class="stat-label">Active</div>
      <div class="stat-value teal"><?= count(array_filter($team, fn($m) => $m['status']==='active')) ?></div>
      <div class="stat-sub">Currently active</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Pending Users</div>
      <div class="stat-value"><?= count($unapproved) ?></div>
      <div class="stat-sub">Awaiting approval</div>
    </div>
  </div>

  <!-- ADMIN TEAM TABLE -->
  <div class="widget" style="margin-bottom:24px">
    <div class="widget-title"><i class="ti ti-shield-check"></i> Admin Team</div>
    <table class="data-table">
      <thead>
        <tr><th>Member</th><th>Username</th><th>Role</th><th>Email</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($team as $m):
          $parts    = explode(' ', $m['name']);
          $initials = strtoupper(substr($parts[0],0,1).substr($parts[1]??'',0,1));
          $is_me    = ($m['username'] === ($_SESSION['username'] ?? ''));
          $is_root  = ($m['username'] === 'admin');
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="topnav-avatar" style="width:32px;height:32px;font-size:12px"><?= $initials ?></div>
              <div>
                <span class="cell-name"><?= htmlspecialchars($m['name']) ?></span>
                <?php if ($is_me): ?>
                  <span style="font-size:10px;background:var(--orange-soft);color:var(--orange);padding:1px 6px;border-radius:4px;margin-left:4px">You</span>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="font-family:monospace;font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($m['username']) ?></td>
          <td><span class="status-badge active"><?= htmlspecialchars($m['role']) ?></span></td>
          <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($m['email']) ?></td>
          <td><span class="status-badge <?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
          <td>
            <?php if (!$is_root): ?>
              <button class="table-action-btn" title="Edit" onclick="openEditMember(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">
                <i class="ti ti-pencil"></i>
              </button>
            <?php endif; ?>
            <?php if (!$is_root && !$is_me): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars($m['name']) ?> from the team?')">
                <input type="hidden" name="form" value="remove_member">
                <input type="hidden" name="del_username" value="<?= htmlspecialchars($m['username']) ?>">
                <button type="submit" class="table-action-btn btn-danger-icon" title="Remove">
                  <i class="ti ti-trash"></i>
                </button>
              </form>
            <?php endif; ?>
            <?php if ($is_root): ?>
              <span style="font-size:12px;color:var(--text-light)">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- PENDING USER REQUESTS -->
  <?php if (!empty($unapproved)): ?>
  <div class="widget" style="margin-bottom:24px">
    <div class="widget-title" style="color:#b7791f">
      <i class="ti ti-clock"></i> Pending Access Requests (<?= count($unapproved) ?>)
      <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px">
        Review each request carefully before approving
      </span>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px">
      <?php foreach ($unapproved as $uname => $u):
        $is_co = ($u['user_type']??'') === 'company';
        $is_sc = ($u['user_type']??'') === 'school';
        $type_color = $is_co ? 'var(--orange)' : ($is_sc ? 'var(--teal)' : 'var(--text-muted)');
        $type_bg    = $is_co ? 'var(--orange-soft)' : ($is_sc ? 'var(--teal-soft)' : 'var(--surface)');
        $type_icon  = $is_co ? 'ti-building' : ($is_sc ? 'ti-school' : 'ti-user');
        $type_label = $is_co ? 'Company' : ($is_sc ? 'School' : 'General');
      ?>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <!-- Card header -->
        <div style="display:flex;align-items:center;gap:12px;padding:14px 18px;
                    background:white;border-bottom:1px solid var(--border)">
          <div style="width:38px;height:38px;border-radius:10px;background:<?= $type_bg ?>;
                      display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="ti <?= $type_icon ?>" style="font-size:17px;color:<?= $type_color ?>"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="font-size:14px;font-weight:700;color:var(--text)">
                <?= htmlspecialchars($u['name'] ?? $uname) ?>
              </span>
              <span style="background:<?= $type_bg ?>;color:<?= $type_color ?>;
                           font-size:10px;font-weight:700;padding:2px 9px;border-radius:10px">
                <?= $type_label ?>
              </span>
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
              @<?= htmlspecialchars($uname) ?> · Requested <?= htmlspecialchars($u['created'] ?? '—') ?>
            </div>
          </div>
          <!-- Action buttons in header -->
          <div style="display:flex;gap:8px;flex-shrink:0">
            <button class="btn btn-teal" style="font-size:12px;padding:7px 16px"
                    onclick="openApprove('<?= htmlspecialchars($uname,ENT_QUOTES) ?>',
                             '<?= htmlspecialchars(addslashes($u['name']??$uname),ENT_QUOTES) ?>',
                             '<?= htmlspecialchars($u['user_type']??'general',ENT_QUOTES) ?>')">
              <i class="ti ti-check"></i> Approve
            </button>
            <form method="POST" action="approve_user.php" style="display:inline"
                  onsubmit="return confirm('Reject this request from <?= htmlspecialchars(addslashes($u['name']??$uname)) ?>?')">
              <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="btn btn-secondary"
                      style="font-size:12px;padding:7px 14px;color:#c53030;border-color:#fed7d7">
                <i class="ti ti-x"></i> Reject
              </button>
            </form>
          </div>
        </div>

        <!-- Card body: all their info in a clean grid -->
        <div style="padding:14px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">

          <?php if ($is_co): ?>
          <!-- COMPANY INFO -->
          <?php foreach([
            ['ti-building','Organisation',$u['org']??'—'],
            ['ti-id','Reg Number',$u['reg_number']??'—'],
            ['ti-user','Contact Person',$u['contact_person']??'—'],
            ['ti-fingerprint','ID Number',$u['id_number']??'—'],
            ['ti-phone','Phone',$u['phone']??'—'],
            ['ti-mail','Email',$u['email']??'—'],
            ['ti-currency-rand','CSI Budget','R '.($u['csi_budget']??'Not specified')],
            ['ti-checkbox','Programme Pref',$u['programme_pref']??'Not specified'],
          ] as [$ic,$lbl,$val]): ?>
          <div style="display:flex;align-items:flex-start;gap:8px">
            <i class="ti <?= $ic ?>" style="font-size:14px;color:var(--orange);flex-shrink:0;margin-top:2px"></i>
            <div>
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em"><?= $lbl ?></div>
              <div style="font-size:12.5px;color:var(--text);font-weight:500"><?= htmlspecialchars($val) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(!empty($u['focus_areas'])): ?>
          <div style="display:flex;align-items:flex-start;gap:8px;grid-column:span 2">
            <i class="ti ti-target" style="font-size:14px;color:var(--orange);flex-shrink:0;margin-top:2px"></i>
            <div>
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Focus Areas</div>
              <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:3px">
                <?php foreach(explode(',',$u['focus_areas']) as $fa): ?>
                <span style="background:var(--orange-soft);color:var(--orange);font-size:11px;padding:2px 8px;border-radius:10px"><?= htmlspecialchars(trim($fa)) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php if(!empty($u['provinces'])): ?>
          <div style="display:flex;align-items:flex-start;gap:8px;grid-column:span 2">
            <i class="ti ti-map-pin" style="font-size:14px;color:var(--orange);flex-shrink:0;margin-top:2px"></i>
            <div>
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Target Provinces</div>
              <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:3px">
                <?php foreach(explode(',',$u['provinces']) as $pv): ?>
                <span style="background:var(--purple-soft);color:var(--purple);font-size:11px;padding:2px 8px;border-radius:10px"><?= htmlspecialchars(trim($pv)) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php elseif ($is_sc): ?>
          <!-- SCHOOL INFO -->
          <?php foreach([
            ['ti-school','School Name',$u['org']??'—'],
            ['ti-map-pin','Province',$u['province']??'—'],
            ['ti-topology-star','District',$u['district']??'—'],
            ['ti-user','Principal Name',$u['principal_name']??'—'],
            ['ti-fingerprint','ID Number',$u['id_number']??'—'],
            ['ti-phone','Contact Number',$u['phone']??'—'],
            ['ti-mail','Email',$u['email']??'—'],
            ['ti-users','No. of Learners',($u['learners']??'—').' learners'],
            ['ti-user-check','No. of Educators',($u['educators']??'—').' educators'],
            ['ti-currency-rand','Funding Needed','R '.($u['funding_needed']??'Not specified')],
          ] as [$ic,$lbl,$val]): ?>
          <div style="display:flex;align-items:flex-start;gap:8px">
            <i class="ti <?= $ic ?>" style="font-size:14px;color:var(--teal);flex-shrink:0;margin-top:2px"></i>
            <div>
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em"><?= $lbl ?></div>
              <div style="font-size:12.5px;color:var(--text);font-weight:500"><?= htmlspecialchars($val) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(!empty($u['challenges'])): ?>
          <div style="display:flex;align-items:flex-start;gap:8px;grid-column:1/-1">
            <i class="ti ti-alert-triangle" style="font-size:14px;color:var(--teal);flex-shrink:0;margin-top:2px"></i>
            <div style="flex:1">
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Challenges / Needs</div>
              <div style="font-size:12.5px;color:var(--text);margin-top:3px;line-height:1.6;
                          background:white;padding:8px 12px;border-radius:8px;border:1px solid var(--border)">
                <?= htmlspecialchars($u['challenges']) ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php else: ?>
          <!-- GENERAL USER -->
          <?php foreach([
            ['ti-user','Name',$u['name']??'—'],
            ['ti-mail','Email',$u['email']??'—'],
            ['ti-building','Organisation',$u['org']??'—'],
          ] as [$ic,$lbl,$val]): ?>
          <div style="display:flex;align-items:flex-start;gap:8px">
            <i class="ti <?= $ic ?>" style="font-size:14px;color:var(--text-muted);flex-shrink:0;margin-top:2px"></i>
            <div>
              <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em"><?= $lbl ?></div>
              <div style="font-size:12.5px;color:var(--text);font-weight:500"><?= htmlspecialchars($val) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- APPROVED USERS -->
  <?php if (!empty($approved)): ?>
  <div class="widget">
    <div class="widget-title" style="color:#00956a"><i class="ti ti-eye"></i> Approved Users (<?= count($approved) ?>)</div>
    <table class="data-table">
      <thead><tr><th>Name</th><th>Username</th><th>Organisation</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($approved as $uname => $u): ?>
        <tr>
          <td class="cell-name"><?= htmlspecialchars($u['name']) ?></td>
          <td style="font-family:monospace;font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($uname) ?></td>
          <td><?= htmlspecialchars($u['org'] ?? '—') ?></td>
          <td style="font-size:12.5px;color:var(--text-muted)"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
          <td><span class="status-badge active">Approved</span></td>
          <td>
            <button class="table-action-btn" title="Edit"
                    onclick="openEditUser('<?= htmlspecialchars($uname, ENT_QUOTES) ?>', <?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
              <i class="ti ti-pencil"></i>
            </button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($u['name'])) ?> account?')">
              <input type="hidden" name="form" value="remove_user">
              <input type="hidden" name="del_uname" value="<?= htmlspecialchars($uname) ?>">
              <button type="submit" class="table-action-btn btn-danger-icon" title="Remove">
                <i class="ti ti-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</main>
</div>

<!-- ADD MEMBER MODAL -->
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Team Member</h2>
    <div class="modal-sub">Create a new admin account. They can log in via the Admin portal immediately.</div>
    <form method="POST">
      <input type="hidden" name="form" value="add_member">
      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <div style="position:relative">
          <i class="ti ti-user" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:15px"></i>
          <input class="form-input" style="padding-left:34px" type="text" name="new_name" placeholder="e.g. Lerato Sithole" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username *</label>
          <div style="position:relative">
            <i class="ti ti-at" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:15px"></i>
            <input class="form-input" style="padding-left:34px" type="text" name="new_username" placeholder="e.g. lerato" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select class="form-select" name="new_role">
            <option value="Administrator">Administrator</option>
            <option value="CSI Coordinator">CSI Coordinator</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <div style="position:relative">
          <i class="ti ti-mail" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:15px"></i>
          <input class="form-input" style="padding-left:34px" type="email" name="new_email" placeholder="lerato@researchunlimited.co.za">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password *</label>
        <div style="position:relative">
          <i class="ti ti-lock" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:15px"></i>
          <input class="form-input" style="padding-left:34px;padding-right:38px" type="password" id="new-pw" name="new_password" placeholder="Min. 6 characters" required>
          <button type="button" onclick="togglePw()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-light);font-size:15px">
            <i class="ti ti-eye" id="pw-eye"></i>
          </button>
        </div>
        <p style="font-size:11px;color:var(--text-muted);margin-top:5px">
          <i class="ti ti-info-circle"></i> Share this password with the member so they can log in via Admin portal.
        </p>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-user-plus"></i> Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT TEAM MEMBER MODAL -->
<div class="modal-overlay" id="edit-member-modal" onclick="if(event.target.id==='edit-member-modal')closeModal('edit-member-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-member-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit Team Member</h2>
    <div class="modal-sub">Update this admin's details. Leave password blank to keep it unchanged.</div>
    <form method="POST">
      <input type="hidden" name="form" value="edit_member">
      <input type="hidden" name="edit_username" id="em-username">

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input class="form-input" type="text" name="edit_name" id="em-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" type="text" id="em-username-display" disabled
                 style="background:var(--surface);color:var(--text-muted)">
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select class="form-select" name="edit_role" id="em-role">
            <option value="Administrator">Administrator</option>
            <option value="CSI Coordinator">CSI Coordinator</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-input" type="email" name="edit_email" id="em-email">
      </div>
      <div class="form-group">
        <label class="form-label">New Password (optional)</label>
        <input class="form-input" type="password" name="edit_password" placeholder="Leave blank to keep current password">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-member-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT APPROVED USER MODAL -->
<div class="modal-overlay" id="edit-user-modal" onclick="if(event.target.id==='edit-user-modal')closeModal('edit-user-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('edit-user-modal')"><i class="ti ti-x"></i></button>
    <h2>Edit User Account</h2>
    <div class="modal-sub">Update this user's details. Leave password blank to keep it unchanged.</div>
    <form method="POST">
      <input type="hidden" name="form" value="edit_user">
      <input type="hidden" name="edit_uname" id="eu-uname">

      <div class="form-group">
        <label class="form-label">Full Name *</label>
        <input class="form-input" type="text" name="edit_uname_name" id="eu-name" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" type="text" id="eu-uname-display" disabled
                 style="background:var(--surface);color:var(--text-muted)">
        </div>
        <div class="form-group">
          <label class="form-label">Organisation</label>
          <input class="form-input" type="text" name="edit_uname_org" id="eu-org">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input class="form-input" type="email" name="edit_uname_email" id="eu-email">
      </div>
      <div class="form-group">
        <label class="form-label">New Password (optional)</label>
        <input class="form-input" type="password" name="edit_uname_password" placeholder="Leave blank to keep current password">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-user-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- APPROVE USER MODAL -->
<div class="modal-overlay" id="approve-modal" onclick="if(event.target.id==='approve-modal')closeModal('approve-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('approve-modal')"><i class="ti ti-x"></i></button>
    <h2>Approve User Access</h2>
    <div class="modal-sub">Set this user's type so they only see relevant data.</div>
    <form method="POST" action="approve_user.php">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="username" id="approve-uname">
      <div class="form-group">
        <label class="form-label">User</label>
        <input class="form-input" type="text" id="approve-name-display" disabled style="background:var(--surface)">
      </div>
      <div class="form-group">
        <label class="form-label">User Type *</label>
        <select class="form-select" name="user_type" id="approve-type" onchange="loadLinked(this.value)">
          <option value="general">General (view all data)</option>
          <option value="company">Partner / Company</option>
          <option value="school">School</option>
        </select>
      </div>
      <div class="form-group" id="linked-wrap" style="display:none">
        <label class="form-label">Link to specific record</label>
        <select class="form-select" name="linked_id" id="linked-select">
          <option value="">Loading…</option>
        </select>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
          This user will only see data for the selected <?= '<?= document.getElementById("approve-type")?.value === "school" ? "school" : "partner" ?>' ?>.
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('approve-modal')">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="ti ti-check"></i> Approve Access</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function togglePw() {
  const pw = document.getElementById('new-pw');
  const eye = document.getElementById('pw-eye');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  eye.className = pw.type === 'text' ? 'ti ti-eye-off' : 'ti ti-eye';
}
function openEditMember(m) {
  document.getElementById('em-username').value = m.username;
  document.getElementById('em-username-display').value = m.username;
  document.getElementById('em-name').value = m.name;
  document.getElementById('em-email').value = m.email || '';
  document.getElementById('em-role').value = m.role || 'Administrator';
  openModal('edit-member-modal');
}
function openEditUser(uname, u) {
  document.getElementById('eu-uname').value = uname;
  document.getElementById('eu-uname-display').value = uname;
  document.getElementById('eu-name').value = u.name || '';
  document.getElementById('eu-org').value = u.org || '';
  document.getElementById('eu-email').value = u.email || '';
  openModal('edit-user-modal');
}
function openApprove(uname, displayName, preType) {
  document.getElementById('approve-uname').value = uname;
  document.getElementById('approve-name-display').value = displayName + ' (@' + uname + ')';
  const typeSelect = document.getElementById('approve-type');
  typeSelect.value = preType || 'general';
  if (preType && preType !== 'general') {
    loadLinked(preType);
  } else {
    document.getElementById('linked-wrap').style.display = 'none';
  }
  openModal('approve-modal');
}
function loadLinked(type) {
  const wrap = document.getElementById('linked-wrap');
  const sel  = document.getElementById('linked-select');
  if (type === 'general') { wrap.style.display='none'; return; }
  wrap.style.display = 'block';
  sel.innerHTML = '<option value="">Loading…</option>';
  fetch('get_linked_options.php?type=' + type)
    .then(r => r.json())
    .then(data => {
      sel.innerHTML = '<option value="">-- No specific link (sees all) --</option>';
      data.forEach(item => {
        sel.innerHTML += '<option value="'+item.id+'">'+item.name+'</option>';
      });
    })
    .catch(() => { sel.innerHTML = '<option value="">Error loading options</option>'; });
}
</script>

<?php include 'includes/footer.php'; ?>