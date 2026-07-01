<?php
$active_page = 'documents';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ── WIZARD STEPS DEFINITION ───────────────────────────────────
$steps = [
    1 => [
        'title'       => 'Company Registration',
        'description' => 'Official company or organisation registration document issued by CIPC or relevant authority.',
        'icon'        => 'ti-building',
        'color'       => 'var(--orange)',
        'bg'          => 'var(--orange-soft)',
        'category'    => 'Company Registration',
        'required'    => true,
        'fields'      => [
            ['name'=>'reg_number',   'label'=>'Company Registration Number', 'type'=>'text',   'placeholder'=>'e.g. 2021/123456/07', 'required'=>true],
            ['name'=>'reg_date',     'label'=>'Date of Registration',        'type'=>'date',   'placeholder'=>'',                    'required'=>true],
            ['name'=>'company_name', 'label'=>'Registered Company Name',     'type'=>'text',   'placeholder'=>'Full legal name',     'required'=>true],
        ],
        'accept'      => '.pdf,.jpg,.jpeg,.png',
        'hint'        => 'Accepted: PDF, JPG, PNG (max 10MB)',
    ],
    2 => [
        'title'       => 'Tax Clearance Certificate',
        'description' => 'Valid Tax Clearance Certificate or Tax Compliance Status (TCS) pin from SARS.',
        'icon'        => 'ti-receipt-tax',
        'color'       => '#6c5ce7',
        'bg'          => '#f0eeff',
        'category'    => 'Tax Clearance',
        'required'    => true,
        'fields'      => [
            ['name'=>'tax_number',   'label'=>'Tax Reference Number',   'type'=>'text',  'placeholder'=>'e.g. 1234567890', 'required'=>true],
            ['name'=>'tax_pin',      'label'=>'TCS Pin (if applicable)','type'=>'text',  'placeholder'=>'e.g. 1234567890123', 'required'=>false],
            ['name'=>'tax_expiry',   'label'=>'Certificate Expiry Date','type'=>'date',  'placeholder'=>'',  'required'=>true],
        ],
        'accept'      => '.pdf,.jpg,.jpeg,.png',
        'hint'        => 'Must be valid and not expired. Accepted: PDF, JPG, PNG',
    ],
    3 => [
        'title'       => 'B-BBEE Certificate',
        'description' => 'Broad-Based Black Economic Empowerment certificate or sworn affidavit (for EME/QSE).',
        'icon'        => 'ti-certificate',
        'color'       => 'var(--teal)',
        'bg'          => 'var(--teal-soft)',
        'category'    => 'B-BBEE Certificate',
        'required'    => true,
        'fields'      => [
            ['name'=>'bbee_level',   'label'=>'B-BBEE Level',           'type'=>'select','options'=>['Level 1','Level 2','Level 3','Level 4','Level 5','Level 6','Level 7','Level 8','Non-Compliant','EME','QSE'], 'required'=>true],
            ['name'=>'bbee_issuer',  'label'=>'Issuing Agency / Auditor','type'=>'text', 'placeholder'=>'e.g. Empowerlogic', 'required'=>false],
            ['name'=>'bbee_expiry',  'label'=>'Certificate Expiry Date', 'type'=>'date', 'placeholder'=>'', 'required'=>true],
        ],
        'accept'      => '.pdf,.jpg,.jpeg,.png',
        'hint'        => 'Accepted: PDF, JPG, PNG. Affidavit accepted for EME/QSE.',
    ],
    4 => [
        'title'       => 'Proof of Bank Account',
        'description' => 'Official bank confirmation letter or stamped bank statement not older than 3 months.',
        'icon'        => 'ti-building-bank',
        'color'       => '#f5a623',
        'bg'          => '#fffbea',
        'category'    => 'Bank Details',
        'required'    => true,
        'fields'      => [
            ['name'=>'bank_name',    'label'=>'Bank Name',           'type'=>'select','options'=>['ABSA','FNB','Nedbank','Standard Bank','Capitec','African Bank','Bidvest Bank','Other'], 'required'=>true],
            ['name'=>'account_name', 'label'=>'Account Holder Name', 'type'=>'text',  'placeholder'=>'As per bank records',  'required'=>true],
            ['name'=>'account_no',   'label'=>'Account Number',      'type'=>'text',  'placeholder'=>'e.g. 1234567890',     'required'=>true],
            ['name'=>'branch_code',  'label'=>'Branch Code',         'type'=>'text',  'placeholder'=>'e.g. 632005',         'required'=>false],
        ],
        'accept'      => '.pdf,.jpg,.jpeg,.png',
        'hint'        => 'Accepted: PDF, JPG, PNG. Must be dated within last 3 months.',
    ],
    5 => [
        'title'       => 'CSI Programme Proposal',
        'description' => 'Your proposed CSI programme, including objectives, target schools, budget breakdown, and expected impact.',
        'icon'        => 'ti-file-description',
        'color'       => '#2dbcd8',
        'bg'          => '#e8f8fc',
        'category'    => 'Programme Proposal',
        'required'    => true,
        'fields'      => [
            ['name'=>'proposal_title',  'label'=>'Proposal Title',         'type'=>'text',     'placeholder'=>'e.g. STEM Education Initiative 2026', 'required'=>true],
            ['name'=>'target_schools',  'label'=>'Target School(s)',        'type'=>'text',     'placeholder'=>'School name(s)', 'required'=>true],
            ['name'=>'proposed_budget', 'label'=>'Proposed Budget (R)',     'type'=>'number',   'placeholder'=>'e.g. 500000',   'required'=>true],
            ['name'=>'focus_area',      'label'=>'Focus Area',              'type'=>'select',   'options'=>['STEM','Literacy','Digital Skills','Arts & Culture','Science','Skills Development','Sports','Health & Nutrition','Infrastructure','Other'], 'required'=>true],
            ['name'=>'proposal_notes',  'label'=>'Brief Description',       'type'=>'textarea', 'placeholder'=>'Summarise your programme objectives and expected outcomes…', 'required'=>false],
        ],
        'accept'      => '.pdf,.doc,.docx,.ppt,.pptx',
        'hint'        => 'Accepted: PDF, Word, PowerPoint (max 10MB)',
    ],
    6 => [
        'title'       => 'Signed MOU / Agreement',
        'description' => 'Signed Memorandum of Understanding between your organisation and Research Unlimited.',
        'icon'        => 'ti-file-check',
        'color'       => '#00956a',
        'bg'          => 'var(--teal-soft)',
        'category'    => 'MOU',
        'required'    => false,
        'fields'      => [
            ['name'=>'mou_date',       'label'=>'Date Signed',            'type'=>'date', 'placeholder'=>'',                'required'=>false],
            ['name'=>'mou_signatory',  'label'=>'Signed By (Name)',       'type'=>'text', 'placeholder'=>'Authorised signatory full name', 'required'=>false],
            ['name'=>'mou_position',   'label'=>'Position / Title',       'type'=>'text', 'placeholder'=>'e.g. CEO, CFO',   'required'=>false],
        ],
        'accept'      => '.pdf',
        'hint'        => 'Optional at this stage. Accepted: PDF only.',
    ],
];

$total_steps    = count($steps);
$required_steps = count(array_filter($steps, fn($s) => $s['required']));

// ── HANDLE UPLOAD ─────────────────────────────────────────────
$upload_success = '';
$upload_error   = '';
$completed      = $_SESSION['wizard_completed'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form']??'') === 'wizard_upload') {
    $step_num = (int)($_POST['step_num'] ?? 0);
    $step     = $steps[$step_num] ?? null;

    if ($step && isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $orig     = basename($_FILES['doc_file']['name']);
        $ext      = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $safe     = date('Ymd_His') . '_step' . $step_num . '_' . preg_replace('/[^a-z0-9._-]/i', '_', $orig);
        $size     = $_FILES['doc_file']['size'];

        if ($size > 10 * 1024 * 1024) {
            $upload_error = 'File too large. Maximum size is 10MB.';
        } else {
            // Collect metadata fields
            $meta = [];
            foreach ($step['fields'] as $f) {
                $meta[$f['name']] = trim($_POST[$f['name']] ?? '');
            }
            $meta_json = json_encode($meta);

            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $safe)) {
                // Save to documents table
                $pdo->prepare("
                    INSERT INTO documents (title, file_name, category, uploaded_by, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $step['title'],
                    $safe,
                    $step['category'],
                    $_SESSION['name'] ?? 'User',
                    $meta_json
                ]);

                // Mark step as complete in session
                $completed[$step_num] = [
                    'file'     => $orig,
                    'uploaded' => date('d M Y H:i'),
                    'meta'     => $meta,
                ];
                $_SESSION['wizard_completed'] = $completed;
                $upload_success = $step['title'] . ' uploaded successfully.';
            } else {
                $upload_error = 'Upload failed. Please try again.';
            }
        }
    } elseif (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK && $_FILES['doc_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_error = 'Upload error. Please try again.';
    }
}

// Handle reset
if (isset($_GET['reset'])) {
    $_SESSION['wizard_completed'] = [];
    header('Location: document_wizard.php'); exit;
}

$completed      = $_SESSION['wizard_completed'] ?? [];
$done_count     = count($completed);
$req_done       = count(array_filter($steps, fn($s,$k) => $s['required'] && isset($completed[$k]), ARRAY_FILTER_USE_BOTH));
$pct            = round($done_count / $total_steps * 100);
$all_required   = ($req_done >= $required_steps);
$all_done       = ($done_count >= $total_steps);

// Find current active step
$current_step = 1;
foreach ($steps as $num => $s) {
    if (!isset($completed[$num])) { $current_step = $num; break; }
}
if ($done_count >= $total_steps) $current_step = 0; // all done

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <a href="documents.php" style="color:var(--text-muted)">Documents</a>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Document Submission</span>
</div>

<div class="page-header">
  <div>
    <h1>Document Submission</h1>
    <p>Complete all required documents to register your organisation on CSI Hub</p>
  </div>
  <div class="page-header-right">
    <?php if ($done_count > 0): ?>
    <a href="document_wizard.php?reset=1" class="btn btn-secondary"
       onclick="return confirm('Reset all progress? This will not delete uploaded files.')">
      <i class="ti ti-refresh"></i> Reset Progress
    </a>
    <?php endif; ?>
    <a href="documents.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back to Documents</a>
  </div>
</div>

<?php if ($upload_success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:9px;font-size:13px;font-weight:500">
  <i class="ti ti-circle-check" style="font-size:18px"></i><?= htmlspecialchars($upload_success) ?>
</div>
<?php endif; ?>
<?php if ($upload_error): ?>
<div style="background:#fde9e9;border:1px solid #f5c0c0;color:#7a1f1f;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:9px;font-size:13px">
  <i class="ti ti-alert-circle" style="font-size:18px"></i><?= htmlspecialchars($upload_error) ?>
</div>
<?php endif; ?>

<!-- ══ PROGRESS BAR ══ -->
<div class="widget" style="margin-bottom:20px;padding:20px 24px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <div>
      <span style="font-size:14px;font-weight:700;color:var(--text)">Submission Progress</span>
      <span style="font-size:12.5px;color:var(--text-muted);margin-left:10px">
        <?= $done_count ?> of <?= $total_steps ?> documents uploaded
        <?php if ($req_done < $required_steps): ?>
          · <span style="color:var(--orange)"><?= $required_steps - $req_done ?> required remaining</span>
        <?php endif; ?>
      </span>
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;
                color:<?= $pct >= 100 ? 'var(--teal)' : ($pct >= 60 ? 'var(--orange)' : 'var(--text)') ?>">
      <?= $pct ?>%
    </div>
  </div>
  <!-- Progress bar track -->
  <div style="height:10px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:14px">
    <div style="height:100%;width:<?= $pct ?>%;border-radius:10px;transition:width .5s ease;
                background:<?= $pct >= 100 ? 'var(--teal)' : 'linear-gradient(90deg,var(--orange),var(--orange-mid))' ?>">
    </div>
  </div>
  <!-- Step indicators -->
  <div style="display:flex;gap:6px">
    <?php foreach ($steps as $num => $s): ?>
    <div style="flex:1;text-align:center" title="<?= htmlspecialchars($s['title']) ?>">
      <div style="height:4px;border-radius:4px;background:<?= isset($completed[$num]) ? 'var(--teal)' : ($num === $current_step ? 'var(--orange)' : 'var(--border)') ?>"></div>
      <div style="font-size:9px;margin-top:4px;color:<?= isset($completed[$num]) ? 'var(--teal)' : ($num === $current_step ? 'var(--orange)' : 'var(--text-light)') ?>;font-weight:<?= $num === $current_step ? '700' : '400' ?>">
        <?= $s['required'] ? 'Step '.$num : 'Step '.$num.'*' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="font-size:10.5px;color:var(--text-muted);margin-top:8px">* Optional step</div>
</div>

<?php if ($all_done): ?>
<!-- ══ COMPLETION SCREEN ══ -->
<div style="text-align:center;padding:48px 32px;background:white;border:1px solid var(--border);border-radius:16px;margin-bottom:20px;box-shadow:0 4px 24px rgba(0,196,140,.1)">
  <!-- Celebration icon -->
  <div style="width:80px;height:80px;border-radius:50%;background:var(--teal-soft);
              display:flex;align-items:center;justify-content:center;margin:0 auto 16px;
              border:3px solid var(--teal)">
    <i class="ti ti-trophy" style="font-size:36px;color:var(--teal)"></i>
  </div>
  <div style="font-size:11px;font-weight:700;color:var(--teal);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">
    100% Complete
  </div>
  <h2 style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--text);margin-bottom:10px">
    Congratulations! 🎉
  </h2>
  <p style="font-size:14px;color:var(--text-muted);line-height:1.7;max-width:480px;margin:0 auto 24px">
    You have successfully uploaded all required documents.<br>
    The Research Unlimited team will review your submission and contact you within <strong>2–3 business days</strong>.
  </p>
  <div style="display:inline-flex;align-items:center;gap:8px;background:var(--teal-soft);
              border:1px solid #a7e9d3;border-radius:10px;padding:12px 24px;
              font-size:13px;font-weight:600;color:#054d36">
    <i class="ti ti-mail-check" style="font-size:16px"></i>
    A confirmation has been recorded. Reference your uploaded documents in the Documents section.
  </div>
  <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">
    <a href="documents.php" class="btn btn-primary"><i class="ti ti-file-invoice"></i> View All Documents</a>
    <a href="dashboard.php" class="btn btn-secondary"><i class="ti ti-layout-dashboard"></i> Go to Dashboard</a>
  </div>
</div>

<?php elseif ($all_required && !$all_done): ?>
<!-- ══ REQUIRED DONE — OPTIONAL REMAINING ══ -->
<div style="background:#fffbea;border:1px solid #f6e05e;border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-size:13px">
  <i class="ti ti-star" style="color:#b7791f;font-size:18px;flex-shrink:0"></i>
  <div>
    <strong style="color:#7b341e">All required documents submitted!</strong>
    <span style="color:#7b341e;margin-left:6px">You may also upload the optional MOU document below.</span>
  </div>
</div>
<?php endif; ?>

<!-- ══ STEPS LIST ══ -->
<?php if (!$all_done): ?>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($steps as $num => $step):
    $is_done    = isset($completed[$num]);
    $is_current = ($num === $current_step);
    $is_locked  = !$is_done && !$is_current;
    $prev_done  = ($num === 1) || isset($completed[$num - 1]);
    // Can only do this step if previous required step is done
    $can_do = !$is_locked || $prev_done;
  ?>
  <div style="background:white;border:<?= $is_current ? '2px solid var(--orange)' : '1px solid var(--border)' ?>;
              border-radius:14px;overflow:hidden;
              opacity:<?= ($is_locked && !$can_do) ? '.5' : '1' ?>;
              box-shadow:<?= $is_current ? '0 4px 20px rgba(232,84,26,.1)' : '0 1px 4px rgba(26,31,46,.04)' ?>">

    <!-- Step header -->
    <div style="display:flex;align-items:center;gap:14px;padding:16px 20px;
                border-bottom:<?= ($is_current && !$is_done) ? '1px solid var(--border)' : 'none' ?>;
                background:<?= $is_done ? 'var(--teal-soft)' : ($is_current ? 'white' : 'var(--surface)') ?>">

      <!-- Number / check -->
      <div style="width:40px;height:40px;border-radius:50%;flex-shrink:0;
                  background:<?= $is_done ? 'var(--teal)' : ($is_current ? $step['bg'] : 'var(--border)') ?>;
                  display:flex;align-items:center;justify-content:center;font-size:16px;
                  color:<?= $is_done ? 'white' : ($is_current ? $step['color'] : 'var(--text-muted)') ?>">
        <?php if ($is_done): ?>
          <i class="ti ti-check" style="font-size:18px"></i>
        <?php else: ?>
          <i class="ti <?= $step['icon'] ?>"></i>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
          <span style="font-size:13.5px;font-weight:700;color:<?= $is_done ? '#054d36' : 'var(--text)' ?>">
            <?= htmlspecialchars($step['title']) ?>
          </span>
          <?php if (!$step['required']): ?>
          <span style="font-size:10px;background:#f1f5f9;color:var(--text-muted);padding:2px 7px;border-radius:8px;font-weight:600">Optional</span>
          <?php else: ?>
          <span style="font-size:10px;background:var(--orange-soft);color:var(--orange);padding:2px 7px;border-radius:8px;font-weight:600">Required</span>
          <?php endif; ?>
          <?php if ($is_done): ?>
          <span style="font-size:10.5px;color:#00956a;font-weight:600">
            ✓ Uploaded <?= htmlspecialchars($completed[$num]['uploaded']) ?>
          </span>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--text-muted);line-height:1.4">
          <?= htmlspecialchars($step['description']) ?>
        </div>
      </div>

      <!-- Toggle button for completed steps -->
      <?php if ($is_done): ?>
      <button onclick="toggleReupload(<?= $num ?>)"
              style="font-size:11.5px;color:var(--orange);background:none;border:1px solid rgba(232,84,26,.25);border-radius:7px;padding:5px 12px;cursor:pointer;white-space:nowrap;font-weight:600">
        <i class="ti ti-refresh" style="font-size:12px"></i> Re-upload
      </button>
      <?php elseif ($is_locked && !$can_do): ?>
      <div style="font-size:11.5px;color:var(--text-muted);display:flex;align-items:center;gap:5px">
        <i class="ti ti-lock" style="font-size:13px"></i> Complete previous step first
      </div>
      <?php endif; ?>
    </div>

    <!-- Upload form — shown for current step OR re-upload -->
    <?php if ($is_current || $is_done): ?>
    <div id="upload-form-<?= $num ?>" style="<?= $is_done ? 'display:none' : 'display:block' ?>;padding:20px">

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="form" value="wizard_upload">
        <input type="hidden" name="step_num" value="<?= $num ?>">

        <!-- Metadata fields -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:16px">
          <?php foreach ($step['fields'] as $f): ?>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">
              <?= htmlspecialchars($f['label']) ?>
              <?php if ($f['required']): ?><span style="color:var(--orange)"> *</span><?php endif; ?>
            </label>
            <?php if ($f['type'] === 'select'): ?>
            <select class="form-select" name="<?= $f['name'] ?>" <?= $f['required'] ? 'required' : '' ?>>
              <option value="">Select…</option>
              <?php foreach ($f['options'] as $opt): ?>
              <option value="<?= $opt ?>" <?= ($completed[$num]['meta'][$f['name']]??'') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
              <?php endforeach; ?>
            </select>
            <?php elseif ($f['type'] === 'textarea'): ?>
            <textarea class="form-input" name="<?= $f['name'] ?>" rows="2"
                      placeholder="<?= htmlspecialchars($f['placeholder']) ?>"
                      <?= $f['required'] ? 'required' : '' ?>><?= htmlspecialchars($completed[$num]['meta'][$f['name']]??'') ?></textarea>
            <?php else: ?>
            <input class="form-input" type="<?= $f['type'] ?>" name="<?= $f['name'] ?>"
                   placeholder="<?= htmlspecialchars($f['placeholder']??'') ?>"
                   value="<?= htmlspecialchars($completed[$num]['meta'][$f['name']]??'') ?>"
                   <?= $f['required'] ? 'required' : '' ?>>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- File upload zone -->
        <div style="border:2px dashed <?= $is_current ? 'var(--orange)' : 'var(--border)' ?>;border-radius:10px;
                    padding:20px;text-align:center;background:<?= $is_current ? 'var(--orange-soft)' : 'var(--surface)' ?>;
                    margin-bottom:14px;position:relative;cursor:pointer"
             onclick="document.getElementById('file-<?= $num ?>').click()"
             id="drop-<?= $num ?>">
          <i class="ti ti-cloud-upload" style="font-size:28px;color:<?= $is_current ? 'var(--orange)' : 'var(--text-muted)' ?>;display:block;margin-bottom:6px"></i>
          <div style="font-size:13px;font-weight:600;color:<?= $is_current ? 'var(--orange)' : 'var(--text)' ?>">
            Click to choose file or drag & drop here
          </div>
          <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($step['hint']) ?></div>
          <div id="file-name-<?= $num ?>" style="font-size:12px;color:var(--teal);margin-top:8px;font-weight:600"></div>
          <input type="file" id="file-<?= $num ?>" name="doc_file"
                 accept="<?= htmlspecialchars($step['accept']) ?>"
                 style="position:absolute;inset:0;opacity:0;cursor:pointer"
                 onchange="showFileName(<?= $num ?>, this)"
                 <?= ($step['required'] && !$is_done) ? 'required' : '' ?>>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
          <?php if ($is_done): ?>
          <div style="font-size:12px;color:var(--text-muted)">
            Previously uploaded: <strong><?= htmlspecialchars($completed[$num]['file']) ?></strong>
          </div>
          <?php else: ?>
          <div style="font-size:12px;color:var(--text-muted)">
            Step <?= $num ?> of <?= $total_steps ?>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary" style="min-width:160px;justify-content:center">
            <i class="ti ti-upload"></i>
            <?= $is_done ? 'Re-upload Document' : 'Upload & Continue' ?>
          </button>
        </div>

      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ SUMMARY ══ -->
<?php if ($done_count > 0): ?>
<div class="widget" style="margin-top:20px">
  <div class="widget-title"><i class="ti ti-list-check" style="color:var(--teal)"></i> Submission Summary</div>
  <table class="data-table">
    <thead>
      <tr><th>Step</th><th>Document</th><th>Category</th><th>File Uploaded</th><th>Date</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($steps as $num => $step): ?>
      <tr>
        <td style="font-size:12px;color:var(--text-muted)">Step <?= $num ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($step['title']) ?></td>
        <td style="font-size:12px"><?= htmlspecialchars($step['category']) ?></td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?= isset($completed[$num]) ? htmlspecialchars($completed[$num]['file']) : '—' ?>
        </td>
        <td style="font-size:12px;color:var(--text-muted)">
          <?= isset($completed[$num]) ? htmlspecialchars($completed[$num]['uploaded']) : '—' ?>
        </td>
        <td>
          <?php if (isset($completed[$num])): ?>
            <span class="status-badge active"><i class="ti ti-check" style="font-size:11px"></i> Uploaded</span>
          <?php elseif ($step['required']): ?>
            <span class="status-badge pending">Required</span>
          <?php else: ?>
            <span class="status-badge" style="background:var(--surface);color:var(--text-muted)">Optional</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

</main>
</div>

<script>
function showFileName(num, input) {
  const div = document.getElementById('file-name-' + num);
  if (input.files && input.files[0]) {
    const size = (input.files[0].size / 1024 / 1024).toFixed(2);
    div.textContent = '📎 ' + input.files[0].name + ' (' + size + ' MB)';
  }
}
function toggleReupload(num) {
  const form = document.getElementById('upload-form-' + num);
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
// Drag and drop visual feedback
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('[id^="drop-"]').forEach(zone => {
    zone.addEventListener('dragover', function(e) {
      e.preventDefault();
      this.style.borderColor = 'var(--teal)';
      this.style.background = 'var(--teal-soft)';
    });
    zone.addEventListener('dragleave', function() {
      this.style.borderColor = '';
      this.style.background = '';
    });
    zone.addEventListener('drop', function(e) {
      e.preventDefault();
      this.style.borderColor = '';
      this.style.background = '';
    });
  });
});
</script>

<?php include 'includes/footer.php'; ?>