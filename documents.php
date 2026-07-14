<?php
$active_page = 'documents';
require_once 'includes/auth.php';
require_once 'includes/db.php';
include 'includes/header.php';

$upload_dir = __DIR__ . '/uploads/documents/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$success_msg = '';
$error_msg   = '';

// ── HANDLE UPLOAD ────────────────────────────────────────────
// Only users (not admin) can upload — admin reviews what users submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'upload' && !is_admin()) {
    $file = $_FILES['document'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'File upload failed. Please try again.';
    } else {
        $allowed = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 10 * 1024 * 1024; // 10MB

        if (!in_array($ext, $allowed)) {
            $error_msg = 'File type not allowed. Use PDF, Word, Excel, PowerPoint or image files.';
        } elseif ($file['size'] > $max_size) {
            $error_msg = 'File too large. Maximum size is 10MB.';
        } else {
            $safe_name = uniqid('doc_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
                $pdo->prepare("INSERT INTO documents (title, description, file_name, file_type, file_size, partnership_id, company_id, school_id, category, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        trim($_POST['title']),
                        trim($_POST['description'] ?? ''),
                        $safe_name,
                        $ext,
                        $file['size'],
                        $_POST['partnership_id'] ?: null,
                        $_POST['company_id'] ?: null,
                        $_POST['school_id'] ?: null,
                        $_POST['category'],
                        $_SESSION['name'] ?? 'Admin',
                    ]);
                $success_msg = 'Document uploaded successfully!';
            } else {
                $error_msg = 'Could not save file. Check folder permissions.';
            }
        }
    }
}

// ── HANDLE DELETE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_doc' && is_admin()) {
    $doc = $pdo->prepare("SELECT file_name FROM documents WHERE id=?");
    $doc->execute([$_POST['doc_id']]);
    $row = $doc->fetch();
    if ($row) {
        @unlink($upload_dir . $row['file_name']);
        $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$_POST['doc_id']]);
        $success_msg = 'Document deleted.';
    }
}

// ── FETCH DATA ────────────────────────────────────────────────
$filter_cat = $_GET['category'] ?? '';
$search     = $_GET['search'] ?? '';

$where = [];
$params = [];
if ($filter_cat) { $where[] = 'd.category = ?'; $params[] = $filter_cat; }
if ($search)     { $where[] = '(d.title LIKE ? OR d.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

// ── DATA ISOLATION — users only see their own documents ───────
if (!is_admin()) {
    $utype = $_SESSION['user_type'] ?? 'general';
    $lid   = (int)($_SESSION['linked_id'] ?? 0);
    if ($utype === 'company' && $lid) {
        $where[] = 'd.company_id = ?'; $params[] = $lid;
    } elseif ($utype === 'school' && $lid) {
        $where[] = 'd.school_id = ?'; $params[] = $lid;
    } else {
        // General users only see their own uploads
        $where[] = 'd.uploaded_by = ?'; $params[] = $_SESSION['name'] ?? 'nobody';
    }
}

$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$docs = $pdo->prepare("
    SELECT d.*,
           p.id AS p_id, c.name AS company_name, s.name AS school_name
    FROM documents d
    LEFT JOIN partnerships p ON p.id = d.partnership_id
    LEFT JOIN companies    c ON c.id = d.company_id
    LEFT JOIN schools      s ON s.id = d.school_id
    $where_sql
    ORDER BY d.created_at DESC
");
$docs->execute($params);
$documents = $docs->fetchAll();

// For dropdowns
$companies_list   = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$schools_list     = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
$partnerships_list = $pdo->query("
    SELECT p.id, CONCAT(c.name,' → ',s.name) AS label
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools   s ON s.id=p.school_id
    ORDER BY c.name
")->fetchAll();

// Stats
$total_docs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

$file_icons = [
    'pdf'  => ['ti-file-type-pdf',  '#e53e3e'],
    'doc'  => ['ti-file-type-doc',  '#2b6cb0'],
    'docx' => ['ti-file-type-docx', '#2b6cb0'],
    'xls'  => ['ti-file-spreadsheet','#276749'],
    'xlsx' => ['ti-file-spreadsheet','#276749'],
    'ppt'  => ['ti-presentation',   '#c05621'],
    'pptx' => ['ti-presentation',   '#c05621'],
    'jpg'  => ['ti-photo',          '#6b46c1'],
    'jpeg' => ['ti-photo',          '#6b46c1'],
    'png'  => ['ti-photo',          '#6b46c1'],
];
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>

<main class="main">

  <div class="page-banner">
    <i class="ti ti-home" style="font-size:13px"></i>
    <span style="color:var(--text-muted)">Home</span>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb">Documents</span>
  </div>

  <?php if ($success_msg): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check"></i><?= htmlspecialchars($success_msg) ?>
  </div>
  <?php endif; ?>
  <?php if ($error_msg): ?>
  <div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-alert-circle"></i><?= htmlspecialchars($error_msg) ?>
  </div>
  <?php endif; ?>

  <div class="page-header">
    <div>
      <h1>Documents</h1>
      <p>MOUs, reports, proposals and programme files</p>
    </div>
    <div class="page-header-right">
      <a href="document_wizard.php" class="btn btn-primary"><i class="ti ti-upload"></i> Upload Document</a>
    </div>
  </div>

  <!-- WIZARD PROMPT — shown to non-admin users who haven't completed submission -->
  <?php if(!is_admin()): ?>
  <?php
    $wizard_done = count($_SESSION['wizard_completed'] ?? []);
    $wizard_total = 6;
    $wizard_pct = $wizard_done > 0 ? round($wizard_done/$wizard_total*100) : 0;
  ?>
  <?php if ($wizard_done < $wizard_total): ?>
  <div style="background:linear-gradient(135deg,#fff7ed,#fdf0ea);border:1px solid rgba(232,84,26,.2);
              border-radius:12px;padding:16px 20px;margin-bottom:20px;
              display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <div style="width:44px;height:44px;border-radius:50%;background:var(--orange-soft);
                border:2px solid var(--orange);display:flex;align-items:center;justify-content:center;
                font-size:20px;color:var(--orange);flex-shrink:0">
      <i class="ti ti-checklist"></i>
    </div>
    <div style="flex:1;min-width:200px">
      <div style="font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:3px">
        <?php if ($wizard_done === 0): ?>
          Complete your document submission to get started
        <?php else: ?>
          Document submission in progress — <?= $wizard_pct ?>% complete
        <?php endif; ?>
      </div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
        <?= $wizard_done ?> of <?= $wizard_total ?> documents submitted
      </div>
      <div style="height:6px;background:var(--border);border-radius:6px;overflow:hidden;max-width:280px">
        <div style="height:100%;width:<?= $wizard_pct ?>%;background:var(--orange);border-radius:6px;transition:width .4s"></div>
      </div>
    </div>
    <a href="document_wizard.php" class="btn btn-primary" style="flex-shrink:0">
      <i class="ti ti-arrow-right"></i>
      <?= $wizard_done === 0 ? 'Start Submission' : 'Continue Submission' ?>
    </a>
  </div>
  <?php else: ?>
  <div style="background:var(--teal-soft);border:1px solid #a7e9d3;border-radius:12px;
              padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
    <i class="ti ti-circle-check" style="font-size:22px;color:var(--teal);flex-shrink:0"></i>
    <div style="flex:1;font-size:13px;font-weight:600;color:#054d36">
      All required documents submitted! 🎉 Research Unlimited will be in touch soon.
    </div>
    <a href="document_wizard.php" class="btn btn-secondary" style="font-size:12px;padding:6px 14px">
      <i class="ti ti-eye"></i> View Summary
    </a>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="stats-row three">
    <div class="stat-card orange">
      <div class="stat-label">Total Documents</div>
      <div class="stat-value orange"><?= $total_docs ?></div>
      <div class="stat-sub">All files</div>
    </div>
    <div class="stat-sub">Document types</div>
    </div>
    <div class="stat-sub">Storage used</div>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="actions-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex:1">
      <select class="filter-input" name="category" onchange="this.form.submit()" style="width:160px;padding:8px 12px">
        <option value="">All Categories</option>
        <?php foreach(['MOU','Report','Proposal','Invoice','Other'] as $cat): ?>
          <option <?= $filter_cat===$cat?'selected':'' ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>
      <div class="search-wrap" style="margin-left:0">
        <i class="ti ti-search"></i>
        <input class="filter-input" name="search" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search documents…">
      </div>
      <button type="submit" class="btn btn-secondary"><i class="ti ti-search"></i> Search</button>
      <?php if ($filter_cat || $search): ?>
        <a href="documents.php" class="btn btn-secondary"><i class="ti ti-x"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- DOCUMENTS GRID -->
  <?php if (empty($documents)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
    <i class="ti ti-files" style="font-size:48px;opacity:.3;display:block;margin-bottom:12px"></i>
    <p style="font-size:14px">No documents yet.</p>
      <?php if(!is_admin()): ?>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('upload-modal')">
        <i class="ti ti-upload"></i> Upload your first document
      </button>
      <?php endif; ?>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <?php foreach ($documents as $d):
      $ext   = strtolower($d['file_type'] ?? 'pdf');
      $icon  = $file_icons[$ext][0] ?? 'ti-file';
      $color = $file_icons[$ext][1] ?? '#a0aec0';
      $size  = $d['file_size'] > 1048576 ? round($d['file_size']/1048576,1).'MB' : round($d['file_size']/1024).'KB';
    ?>
    <div class="pcard" style="padding:18px">
      <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
        <div style="width:42px;height:42px;border-radius:10px;background:<?= $color ?>18;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">
          <i class="ti <?= $icon ?>"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13.5px;font-weight:600;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($d['title']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted)"><?= strtoupper($ext) ?> · <?= $size ?></div>
        </div>
        <span class="status-badge pending" style="font-size:10px;padding:2px 8px"><?= htmlspecialchars($d['category']) ?></span>
      </div>

      <?php if ($d['description']): ?>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;line-height:1.5">
        <?= htmlspecialchars(substr($d['description'],0,80)).(strlen($d['description'])>80?'…':'') ?>
      </p>
      <?php endif; ?>

      <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:12px;display:flex;flex-direction:column;gap:3px">
        <?php if ($d['company_name']): ?>
          <div><i class="ti ti-building" style="font-size:12px"></i> <?= htmlspecialchars($d['company_name']) ?></div>
        <?php endif; ?>
        <?php if ($d['school_name']): ?>
          <div><i class="ti ti-school" style="font-size:12px"></i> <?= htmlspecialchars($d['school_name']) ?></div>
        <?php endif; ?>
        <div><i class="ti ti-calendar" style="font-size:12px"></i> <?= date('d M Y', strtotime($d['created_at'])) ?></div>
        <div><i class="ti ti-user" style="font-size:12px"></i> <?= htmlspecialchars($d['uploaded_by']) ?></div>
      </div>

      <div style="display:flex;gap:8px;padding-top:10px;border-top:1px solid var(--border)">
        <a href="uploads/documents/<?= htmlspecialchars($d['file_name']) ?>"
           target="_blank" class="btn btn-secondary" style="font-size:11.5px;padding:6px 12px;flex:1;justify-content:center">
          <i class="ti ti-eye"></i> View
        </a>
        <a href="uploads/documents/<?= htmlspecialchars($d['file_name']) ?>"
           download="<?= htmlspecialchars($d['title']) ?>.<?= $ext ?>"
           class="btn btn-secondary" style="font-size:11.5px;padding:6px 12px">
          <i class="ti ti-download"></i>
        </a>
        <?php if (is_admin()): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
          <input type="hidden" name="form" value="delete_doc">
          <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
          <button type="submit" class="btn btn-secondary" style="font-size:11.5px;padding:6px 10px;color:#c53030;border-color:#fed7d7">
            <i class="ti ti-trash"></i>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>
</div>

<!-- UPLOAD MODAL -->
<?php if(!is_admin()): ?>
<div class="modal-overlay" id="upload-modal" onclick="if(event.target.id==='upload-modal')closeModal('upload-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('upload-modal')"><i class="ti ti-x"></i></button>
    <h2>Upload Document</h2>
    <div class="modal-sub">PDF, Word, Excel, PowerPoint or image files — max 10MB.</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="form" value="upload">

      <div class="form-group">
        <label class="form-label">Document Title *</label>
        <input class="form-input" type="text" name="title" placeholder="e.g. TechCorp MOU 2026" required>
      </div>

      <div class="form-group">
        <label class="form-label">File *</label>
        <input class="form-input" type="file" name="document"
               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
               style="padding:8px 12px;cursor:pointer" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-select" name="category">
            <?php foreach(['MOU','Report','Proposal','Invoice','Other'] as $cat): ?>
              <option><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Linked Partnership</label>
          <select class="form-select" name="partnership_id">
            <option value="">None</option>
            <?php foreach ($partnerships_list as $p): ?>
              <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Linked Company</label>
          <select class="form-select" name="company_id">
            <option value="">None</option>
            <?php foreach ($companies_list as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
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
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="2"
                  placeholder="Brief description of this document"></textarea>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('upload-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-upload"></i> Upload</button>
      </div>
    </form>
  </div>
</div>
<?php endif; // end non-admin upload modal ?>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>

<!-- Document Approval Modal -->
<div class="modal-overlay" id="doc-approve-modal" onclick="if(event.target.id==='doc-approve-modal')closeModal('doc-approve-modal')">
  <div class="modal" style="max-width:440px">
    <button class="modal-close" onclick="closeModal('doc-approve-modal')"><i class="ti ti-x"></i></button>
    <h2 id="doc-modal-title">Review Document</h2>
    <div class="modal-sub" id="doc-modal-sub"></div>
    <form method="POST">
      <input type="hidden" name="doc_id" id="doc-modal-id">
      <input type="hidden" name="action" id="doc-modal-action">
      <div class="form-group">
        <label class="form-label">Note to User (optional)</label>
        <textarea class="form-input" name="admin_note" rows="2" placeholder="Reason for rejection or approval note…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('doc-approve-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="doc-modal-btn">Submit</button>
      </div>
    </form>
  </div>
</div>
<script>
function openApproveDoc(id, action, title) {
  document.getElementById('doc-modal-id').value = id;
  document.getElementById('doc-modal-action').value = action;
  document.getElementById('doc-modal-title').textContent = action==='approve' ? 'Approve Document' : 'Reject Document';
  document.getElementById('doc-modal-sub').textContent = title;
  const btn = document.getElementById('doc-modal-btn');
  btn.textContent = action==='approve' ? 'Approve' : 'Reject';
  btn.className = action==='approve' ? 'btn btn-teal' : 'btn btn-secondary';
  if(action==='reject') btn.style.color='#c53030';
  openModal('doc-approve-modal');
}
</script>
<?php include 'includes/footer.php'; ?>