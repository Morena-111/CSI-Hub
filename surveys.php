<?php
$active_page = 'surveys';
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';

// ── DB TABLE CHECK / CREATE ───────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(255) NOT NULL,
  description TEXT,
  target_type ENUM('all','companies','schools') DEFAULT 'all',
  status      ENUM('draft','active','closed') DEFAULT 'draft',
  created_by  VARCHAR(100),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS survey_questions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  survey_id   INT NOT NULL,
  question    TEXT NOT NULL,
  type        ENUM('text','rating','yesno','multiple') DEFAULT 'text',
  options     TEXT,
  sort_order  INT DEFAULT 0,
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS survey_responses (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  survey_id   INT NOT NULL,
  question_id INT NOT NULL,
  respondent  VARCHAR(100),
  answer      TEXT,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$success = ''; $error = '';

// ── HANDLERS ─────────────────────────────────────────────────
$form = $_POST['form'] ?? '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($form === 'add_survey') {
        $title = trim($_POST['title']??'');
        $desc  = trim($_POST['description']??'');
        $target= $_POST['target_type']??'all';
        if ($title) {
            $pdo->prepare("INSERT INTO surveys (title,description,target_type,status,created_by) VALUES (?,?,?,'draft',?)")
                ->execute([$title,$desc,$target,current_user_name()]);
            $success = "Survey \"{$title}\" created.";
        } else { $error = 'Title is required.'; }
    }
    if ($form === 'update_status') {
        $pdo->prepare("UPDATE surveys SET status=? WHERE id=?")
            ->execute([$_POST['status'],(int)$_POST['survey_id']]);
        $success = 'Survey status updated.';
    }
    if ($form === 'delete_survey') {
        $pdo->prepare("DELETE FROM surveys WHERE id=?")->execute([(int)$_POST['survey_id']]);
        $success = 'Survey deleted.';
    }
    if ($form === 'add_question') {
        $sid  = (int)$_POST['survey_id'];
        $q    = trim($_POST['question']??'');
        $type = $_POST['qtype']??'text';
        $opts = trim($_POST['options']??'');
        if ($q && $sid) {
            $ord = $pdo->prepare("SELECT COUNT(*) FROM survey_questions WHERE survey_id=?");
            $ord->execute([$sid]); $ord = (int)$ord->fetchColumn();
            $pdo->prepare("INSERT INTO survey_questions (survey_id,question,type,options,sort_order) VALUES (?,?,?,?,?)")
                ->execute([$sid,$q,$type,$opts,$ord+1]);
            $success = 'Question added.';
        }
    }
    if ($form === 'delete_question') {
        $pdo->prepare("DELETE FROM survey_questions WHERE id=?")->execute([(int)$_POST['question_id']]);
        $success = 'Question removed.';
    }
}

// ── FETCH ─────────────────────────────────────────────────────
$view_id  = (int)($_GET['id']??0);
$surveys  = $pdo->query("
    SELECT s.*, COUNT(DISTINCT sq.id) AS q_count,
           COUNT(DISTINCT sr.id) AS r_count
    FROM surveys s
    LEFT JOIN survey_questions sq ON sq.survey_id=s.id
    LEFT JOIN survey_responses sr ON sr.survey_id=s.id
    GROUP BY s.id ORDER BY s.created_at DESC
")->fetchAll();

$view_survey   = null;
$view_questions= [];
$view_responses= [];
if ($view_id) {
    $vs = $pdo->prepare("SELECT * FROM surveys WHERE id=?");
    $vs->execute([$view_id]); $view_survey = $vs->fetch();
    if ($view_survey) {
        $vq = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY sort_order");
        $vq->execute([$view_id]); $view_questions = $vq->fetchAll();
        // Responses grouped by question
        foreach ($view_questions as &$vq_item) {
            $vr = $pdo->prepare("SELECT * FROM survey_responses WHERE question_id=? ORDER BY submitted_at DESC");
            $vr->execute([$vq_item['id']]);
            $vq_item['responses'] = $vr->fetchAll();
        }
        unset($vq_item);
    }
}

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <?php if($view_survey): ?>
    <a href="surveys.php" style="color:var(--text-muted)">Surveys</a>
    <span style="color:var(--border)">›</span>
    <span class="active-crumb"><?= htmlspecialchars($view_survey['title']) ?></span>
  <?php else: ?>
    <span class="active-crumb">Surveys</span>
  <?php endif; ?>
</div>

<?php if($view_survey): ?>
<!-- ══ SURVEY DETAIL VIEW ══ -->
<div class="page-header">
  <div>
    <h1><?= htmlspecialchars($view_survey['title']) ?></h1>
    <p><?= htmlspecialchars($view_survey['description']??'') ?>
       · Target: <?= ucfirst($view_survey['target_type']) ?>
       · <span class="status-badge <?= $view_survey['status'] === 'active' ? 'active' : ($view_survey['status']==='closed'?'completed':'pending') ?>">
           <?= ucfirst($view_survey['status']) ?>
         </span>
    </p>
  </div>
  <div class="page-header-right">
    <a href="surveys.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back</a>
    <button class="btn btn-primary" onclick="openModal('add-question-modal')">
      <i class="ti ti-plus"></i> Add Question
    </button>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- Questions + responses -->
<?php if(empty($view_questions)): ?>
<div class="widget" style="text-align:center;padding:40px">
  <i class="ti ti-clipboard-list" style="font-size:36px;opacity:.2;display:block;margin-bottom:8px"></i>
  <p style="color:var(--text-muted);font-size:13px">No questions yet. Add your first question.</p>
</div>
<?php else: ?>
<?php foreach($view_questions as $qi=>$vq): ?>
<div class="widget" style="margin-bottom:14px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
    <div style="display:flex;align-items:flex-start;gap:10px">
      <div style="width:26px;height:26px;border-radius:7px;background:var(--orange-soft);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0"><?= $qi+1 ?></div>
      <div>
        <div style="font-size:13.5px;font-weight:600;color:var(--text)"><?= htmlspecialchars($vq['question']) ?></div>
        <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px">
          Type: <?= ucfirst($vq['type']) ?>
          <?php if($vq['options']): ?> · Options: <?= htmlspecialchars($vq['options']) ?><?php endif; ?>
          · <?= count($vq['responses']) ?> response<?= count($vq['responses'])!=1?'s':'' ?>
        </div>
      </div>
    </div>
    <form method="POST" style="display:inline" onsubmit="return confirm('Remove this question?')">
      <input type="hidden" name="form" value="delete_question">
      <input type="hidden" name="question_id" value="<?= $vq['id'] ?>">
      <input type="hidden" name="survey_id_back" value="<?= $view_id ?>">
      <button type="submit" class="table-action-btn btn-danger-icon" title="Remove"><i class="ti ti-trash"></i></button>
    </form>
  </div>
  <?php if(!empty($vq['responses'])): ?>
  <div style="background:var(--surface);border-radius:8px;padding:12px;margin-top:8px">
    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Responses</div>
    <?php foreach($vq['responses'] as $resp): ?>
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:12.5px">
      <span style="color:var(--text-muted);flex-shrink:0;margin-right:12px"><?= htmlspecialchars($resp['respondent']??'Anonymous') ?></span>
      <span style="color:var(--text)"><?= htmlspecialchars($resp['answer']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Add question modal -->
<div class="modal-overlay" id="add-question-modal" onclick="if(event.target.id==='add-question-modal')closeModal('add-question-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-question-modal')"><i class="ti ti-x"></i></button>
    <h2>Add Question</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_question">
      <input type="hidden" name="survey_id" value="<?= $view_id ?>">
      <div class="form-group">
        <label class="form-label">Question *</label>
        <textarea class="form-input" name="question" rows="3" placeholder="Enter your question…" required></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Answer Type</label>
          <select class="form-select" name="qtype" id="qtype-sel" onchange="toggleOpts()">
            <option value="text">Text (open)</option>
            <option value="rating">Rating 1–5</option>
            <option value="yesno">Yes / No</option>
            <option value="multiple">Multiple Choice</option>
          </select>
        </div>
        <div class="form-group" id="opts-wrap" style="display:none">
          <label class="form-label">Options (comma-separated)</label>
          <input class="form-input" type="text" name="options" placeholder="Option A, Option B, Option C">
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-question-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Add Question</button>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══ SURVEYS LIST ══ -->
<div class="page-header">
  <div><h1>Surveys</h1><p>Create and manage surveys for partners and schools</p></div>
  <div class="page-header-right">
    <button class="btn btn-primary" onclick="openModal('add-modal')"><i class="ti ti-plus"></i> New Survey</button>
  </div>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div class="stats-row">
  <div class="stat-card orange">
    <div class="stat-label">Total Surveys</div>
    <div class="stat-value orange"><?= count($surveys) ?></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Active</div>
    <div class="stat-value teal"><?= count(array_filter($surveys,fn($s)=>$s['status']==='active')) ?></div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Total Responses</div>
    <div class="stat-value purple"><?= array_sum(array_column($surveys,'r_count')) ?></div>
  </div>
</div>

<?php if(empty($surveys)): ?>
<div class="widget" style="text-align:center;padding:40px">
  <i class="ti ti-clipboard-list" style="font-size:36px;opacity:.2;display:block;margin-bottom:8px"></i>
  <p style="color:var(--text-muted);font-size:13px">No surveys yet.</p>
</div>
<?php else: ?>
<div class="widget">
  <table class="data-table">
    <thead><tr><th>Title</th><th>Target</th><th>Questions</th><th>Responses</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($surveys as $sv): ?>
    <tr>
      <td><a href="surveys.php?id=<?= $sv['id'] ?>" style="font-weight:600;color:var(--orange)"><?= htmlspecialchars($sv['title']) ?></a></td>
      <td style="font-size:12px"><?= ucfirst($sv['target_type']) ?></td>
      <td style="font-weight:700;color:var(--navy)"><?= $sv['q_count'] ?></td>
      <td style="font-weight:700;color:var(--teal)"><?= $sv['r_count'] ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="form" value="update_status">
          <input type="hidden" name="survey_id" value="<?= $sv['id'] ?>">
          <select class="form-select" name="status" style="padding:4px 8px;font-size:11.5px;width:auto" onchange="this.form.submit()">
            <?php foreach(['draft','active','closed'] as $st): ?>
            <option value="<?= $st ?>" <?= $sv['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td style="font-size:12px;color:var(--text-muted)"><?= date('d M Y',strtotime($sv['created_at'])) ?></td>
      <td>
        <a href="surveys.php?id=<?= $sv['id'] ?>" class="table-action-btn" title="View"><i class="ti ti-eye"></i></a>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this survey?')">
          <input type="hidden" name="form" value="delete_survey">
          <input type="hidden" name="survey_id" value="<?= $sv['id'] ?>">
          <button type="submit" class="table-action-btn btn-danger-icon" title="Delete"><i class="ti ti-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Add survey modal -->
<div class="modal-overlay" id="add-modal" onclick="if(event.target.id==='add-modal')closeModal('add-modal')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('add-modal')"><i class="ti ti-x"></i></button>
    <h2>New Survey</h2>
    <form method="POST">
      <input type="hidden" name="form" value="add_survey">
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input class="form-input" type="text" name="title" placeholder="e.g. Partner Satisfaction Survey 2026" required>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="2" placeholder="Brief description…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Target Audience</label>
        <select class="form-select" name="target_type">
          <option value="all">All Users</option>
          <option value="companies">Partners / Companies</option>
          <option value="schools">Schools</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Create Survey</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

</main>
</div>
<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function toggleOpts() {
  const t = document.getElementById('qtype-sel').value;
  document.getElementById('opts-wrap').style.display = t==='multiple' ? 'block' : 'none';
}
</script>
<?php include 'includes/footer.php'; ?>