<?php
$active_page = 'surveys';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$success = ''; $error = '';
$linked_id = (int)($_SESSION['linked_id'] ?? 0);

// CSV export for admin
if (isset($_GET['export_csv']) && is_admin()) {
    $sid = (int)$_GET['export_csv'];
    $sv  = $pdo->prepare("SELECT * FROM surveys WHERE id=?");
    $sv->execute([$sid]); $sv = $sv->fetch();
    if ($sv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="survey_results_'.$sid.'_'.date('Ymd').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['Question','Answer','Respondent','Date']);
        $rows = $pdo->prepare("
            SELECT sq.question, sr.answer, sr.respondent, sr.submitted_at
            FROM survey_responses sr
            JOIN survey_questions sq ON sq.id=sr.question_id
            WHERE sr.survey_id=? ORDER BY sr.submitted_at DESC
        ");
        $rows->execute([$sid]);
        foreach($rows->fetchAll() as $r) {
            fputcsv($out, [$r['question'],$r['answer'],$r['respondent'],date('d M Y H:i',strtotime($r['submitted_at']))]);
        }
        fclose($out); exit;
    }
}
$user_type = $_SESSION['user_type'] ?? 'general';

// Handle survey response submission
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='respond') {
    $survey_id  = (int)$_POST['survey_id'];
    $respondent = $_SESSION['name'] ?? 'Anonymous';
    $answers    = $_POST['answers'] ?? [];
    foreach ($answers as $q_id => $answer) {
        if (empty($answer)) continue;
        $pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, respondent, answer) VALUES (?,?,?,?)")
            ->execute([$survey_id, (int)$q_id, $respondent, is_array($answer) ? implode(', ', $answer) : $answer]);
    }
    $success = "Thank you! Your survey response has been submitted.";
}

// Handle create survey (admin)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='create_survey' && is_admin()) {
    $pdo->prepare("INSERT INTO surveys (title, description, target_type, status, created_by) VALUES (?,?,?,'active',?)")
        ->execute([trim($_POST['title']), trim($_POST['description']??''), $_POST['target_type']??'all', $_SESSION['name']]);
    $sid = $pdo->lastInsertId();
    $questions = array_filter(array_map('trim', $_POST['questions'] ?? []));
    $types = $_POST['qtypes'] ?? [];
    foreach ($questions as $i => $q) {
        $pdo->prepare("INSERT INTO survey_questions (survey_id, question, type, sort_order) VALUES (?,?,?,?)")
            ->execute([$sid, $q, $types[$i] ?? 'text', $i+1]);
    }
    $success = "Survey created successfully.";
}

// Fetch surveys visible to this user
$target = is_admin() ? '1=1' :
    ($user_type==='school'  ? "(s.target_type='all' OR s.target_type='schools')" :
    ($user_type==='company' ? "(s.target_type='all' OR s.target_type='companies')" : "s.target_type='all'"));

$surveys = $pdo->query("
    SELECT s.*,
           COUNT(DISTINCT sq.id) AS question_count,
           COUNT(DISTINCT sr.respondent) AS response_count
    FROM surveys s
    LEFT JOIN survey_questions sq ON sq.survey_id = s.id
    LEFT JOIN survey_responses sr ON sr.survey_id = s.id
    WHERE s.status != 'draft' AND $target
    GROUP BY s.id ORDER BY s.created_at DESC
")->fetchAll();

// Check which surveys current user already completed
$completed_ids = [];
if (!is_admin()) {
    $name = $_SESSION['name'] ?? '';
    $done = $pdo->prepare("SELECT DISTINCT survey_id FROM survey_responses WHERE respondent=?");
    $done->execute([$name]);
    $completed_ids = array_column($done->fetchAll(), 'survey_id');
}

// Fetch analytics for admin
$analytics = [];
if (is_admin()) {
    foreach ($surveys as $sv) {
        $qs = $pdo->prepare("SELECT sq.*, COUNT(sr.id) AS ans_count,
            GROUP_CONCAT(sr.answer ORDER BY sr.id SEPARATOR '|||') AS all_answers
            FROM survey_questions sq
            LEFT JOIN survey_responses sr ON sr.question_id=sq.id AND sr.survey_id=?
            WHERE sq.survey_id=? GROUP BY sq.id ORDER BY sq.sort_order");
        $qs->execute([$sv['id'], $sv['id']]);
        $analytics[$sv['id']] = $qs->fetchAll();
    }
}

$active_survey = isset($_GET['take']) ? (int)$_GET['take'] : 0;
$view_results  = isset($_GET['results']) ? (int)$_GET['results'] : 0;

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Surveys</span>
</div>

<?php if($success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;padding:11px 16px;margin-bottom:18px;display:flex;align-items:center;gap:8px;font-size:13px">
  <i class="ti ti-circle-check" style="font-size:17px"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1>Surveys</h1>
    <p>Gather insights from schools and partners to guide CSI decision-making</p>
  </div>
  <?php if(is_admin()): ?>
  <button class="btn btn-primary" onclick="openModal('create-survey-modal')">
    <i class="ti ti-plus"></i> Create Survey
  </button>
  <?php endif; ?>
</div>

<?php
// Compulsory survey banner for school users who haven't completed it
if (!is_admin() && $user_type === 'school') {
    $compulsory_id = 10;
    $already_done  = in_array($compulsory_id, $completed_ids);
    if (!$already_done): ?>
<div style="background:linear-gradient(135deg,#0d1e3d,#1a3560);border-radius:12px;padding:18px 22px;
            margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;border-radius:10px;background:var(--orange);
                display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="ti ti-clipboard-list" style="font-size:20px;color:white"></i>
    </div>
    <div>
      <div style="font-size:13.5px;font-weight:700;color:white;margin-bottom:3px">
        Action Required — Complete Your Needs Assessment Survey
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.55)">
        This survey helps Research Unlimited understand your school's needs. Takes 2 minutes.
      </div>
    </div>
  </div>
  <a href="?take=<?= $compulsory_id ?>" class="btn"
     style="background:var(--orange);color:white;padding:10px 20px;font-size:13px;flex-shrink:0">
    <i class="ti ti-pencil"></i> Complete Now
  </a>
</div>
<?php endif; } ?>

<!-- Stats -->
<div class="stats-row" style="margin-bottom:22px">
  <div class="stat-card orange">
    <div class="stat-label">Active Surveys</div>
    <div class="stat-value orange"><?= count(array_filter($surveys, fn($s)=>$s['status']==='active')) ?></div>
    <div class="stat-sub">Currently open</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Total Responses</div>
    <div class="stat-value teal"><?= array_sum(array_column($surveys,'response_count')) ?></div>
    <div class="stat-sub">Across all surveys</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-label">Completed</div>
    <div class="stat-value purple"><?= count($completed_ids) ?></div>
    <div class="stat-sub">You have completed</div>
  </div>
</div>

<!-- SURVEY CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;margin-bottom:28px">
<?php
$type_icons = [
  'needs_assessment' => ['ti-clipboard-list','var(--orange)','var(--orange-soft)'],
  'environment'      => ['ti-building','var(--teal)','var(--teal-soft)'],
  'leadership'       => ['ti-crown','#6c5ce7','#f0eeff'],
  'impact'           => ['ti-chart-bar','var(--gold)','var(--gold-soft)'],
  'community'        => ['ti-users','#2dbcd8','#e8f8fc'],
  'satisfaction'     => ['ti-star','#e91e8c','#fce4ec'],
  'custom'           => ['ti-forms','var(--text-muted)','var(--surface)'],
];
foreach ($surveys as $sv):
  $tc = $type_icons[$sv['survey_type']??'custom'] ?? $type_icons['custom'];
  $done = in_array($sv['id'], $completed_ids);
?>
<div style="background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(26,31,46,.05);transition:all .2s"
     onmouseenter="this.style.boxShadow='0 6px 24px rgba(26,31,46,.1)'"
     onmouseleave="this.style.boxShadow='0 2px 10px rgba(26,31,46,.05)'">
  <!-- Header -->
  <div style="padding:18px 20px;background:<?= $tc[2] ?>;border-bottom:1px solid rgba(0,0,0,.05)">
    <div style="display:flex;align-items:flex-start;gap:12px">
      <div style="width:40px;height:40px;border-radius:10px;background:<?= $tc[1] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="ti <?= $tc[0] ?>" style="font-size:18px;color:white"></i>
      </div>
      <div style="flex:1">
        <h3 style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:3px;line-height:1.3">
          <?= htmlspecialchars($sv['title']) ?>
        </h3>
        <div style="font-size:11px;color:var(--text-muted)">
          <?= $sv['question_count'] ?> questions · <?= $sv['response_count'] ?> response<?= $sv['response_count']!=1?'s':'' ?>
        </div>
      </div>
      <?php if($done): ?>
      <span style="background:var(--teal-soft);color:var(--teal);font-size:10px;font-weight:700;padding:3px 9px;border-radius:10px;flex-shrink:0">
        <i class="ti ti-check"></i> Done
      </span>
      <?php endif; ?>
    </div>
  </div>
  <!-- Body -->
  <div style="padding:14px 20px">
    <?php if($sv['description']): ?>
    <p style="font-size:12.5px;color:var(--text-muted);line-height:1.6;margin-bottom:12px"><?= htmlspecialchars($sv['description']) ?></p>
    <?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if(!is_admin() && !$done): ?>
      <a href="?take=<?= $sv['id'] ?>" class="btn btn-primary" style="font-size:12px;padding:7px 14px">
        <i class="ti ti-pencil"></i> Take Survey
      </a>
      <?php elseif(is_admin()): ?>
      <a href="?results=<?= $sv['id'] ?>" class="btn btn-secondary" style="font-size:12px;padding:7px 14px">
        <i class="ti ti-chart-pie"></i> View Results
      </a>
      <a href="?export_csv=<?= $sv['id'] ?>" class="btn btn-secondary" style="font-size:12px;padding:7px 14px">
        <i class="ti ti-download"></i> CSV
      </a>
      <?php else: ?>
      <span style="font-size:12px;color:var(--text-muted);padding:7px 0"><i class="ti ti-circle-check" style="color:var(--teal)"></i> Completed</span>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- TAKE SURVEY INLINE -->
<?php if ($active_survey && !is_admin()):
  $sv = null;
  foreach($surveys as $s) { if($s['id']==$active_survey){$sv=$s;break;} }
  if ($sv && !in_array($active_survey,$completed_ids)):
    $qs = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY sort_order");
    $qs->execute([$active_survey]); $qs = $qs->fetchAll();
?>
<div class="widget" style="max-width:680px">
  <div class="widget-title"><i class="ti ti-pencil" style="color:var(--orange)"></i> <?= htmlspecialchars($sv['title']) ?></div>
  <?php if($sv['description']): ?>
  <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px"><?= htmlspecialchars($sv['description']) ?></p>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="form" value="respond">
    <input type="hidden" name="survey_id" value="<?= $active_survey ?>">
    <?php foreach($qs as $i => $q): ?>
    <div class="form-group" style="margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border)">
      <label class="form-label" style="font-size:13px;font-weight:600;color:var(--text);text-transform:none;letter-spacing:0">
        <?= ($i+1) ?>. <?= htmlspecialchars($q['question']) ?>
      </label>
      <?php if($q['type']==='rating'): ?>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
        <?php for($r=1;$r<=5;$r++): ?>
        <label style="display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer">
          <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $r ?>" style="accent-color:var(--orange)">
          <span style="font-size:12px;font-weight:600;color:var(--text)"><?= $r ?></span>
        </label>
        <?php endfor; ?>
        <div style="display:flex;justify-content:space-between;width:100%;font-size:10.5px;color:var(--text-muted);margin-top:2px">
          <span>Poor</span><span>Excellent</span>
        </div>
      </div>
      <?php elseif($q['type']==='yesno'): ?>
      <div style="display:flex;gap:16px;margin-top:8px">
        <?php foreach(['Yes','No'] as $yn): ?>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
          <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $yn ?>" style="accent-color:var(--orange)">
          <?= $yn ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php elseif($q['type']==='multiple' && $q['options']): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px">
        <?php foreach(explode(',',$q['options']) as $opt): ?>
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12.5px">
          <input type="checkbox" name="answers[<?= $q['id'] ?>][]" value="<?= htmlspecialchars(trim($opt)) ?>" style="accent-color:var(--orange)">
          <?= htmlspecialchars(trim($opt)) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <textarea class="form-input" name="answers[<?= $q['id'] ?>]" rows="2" style="margin-top:8px" placeholder="Your answer…"></textarea>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="modal-actions" style="margin-top:0">
      <a href="surveys.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> Submit Responses</button>
    </div>
  </form>
</div>
<?php endif; endif; ?>

<!-- RESULTS / ANALYTICS (admin) -->
<?php if ($view_results && is_admin()):
  $sv = null;
  foreach($surveys as $s) { if($s['id']==$view_results){$sv=$s;break;} }
  if($sv && isset($analytics[$view_results])):
?>
<div class="widget" style="max-width:740px">
  <div class="widget-title"><i class="ti ti-chart-pie" style="color:var(--orange)"></i>
    Results: <?= htmlspecialchars($sv['title']) ?>
    <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px"><?= $sv['response_count'] ?> respondent<?= $sv['response_count']!=1?'s':'' ?></span>
  </div>
  <?php foreach($analytics[$view_results] as $q):
    $answers_raw = array_filter(explode('|||', $q['all_answers']??''));
    $freq = array_count_values(array_map('trim', $answers_raw));
    arsort($freq);
    $max_freq = $freq ? max($freq) : 1;
  ?>
  <div style="margin-bottom:22px;padding-bottom:22px;border-bottom:1px solid var(--border)">
    <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px">
      <?= htmlspecialchars($q['question']) ?>
      <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px"><?= $q['ans_count'] ?> answers</span>
    </div>
    <?php if(empty($answers_raw)): ?>
    <p style="font-size:12px;color:var(--text-muted)">No responses yet.</p>
    <?php elseif($q['type']==='text'): ?>
    <div style="display:flex;flex-direction:column;gap:6px">
      <?php foreach($answers_raw as $a): ?>
      <div style="background:var(--surface);border-radius:8px;padding:8px 12px;font-size:12.5px;color:var(--text)">
        "<?= htmlspecialchars($a) ?>"
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:7px">
      <?php foreach($freq as $ans => $cnt): ?>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
          <span style="font-weight:500;color:var(--text)"><?= htmlspecialchars($ans) ?></span>
          <span style="color:var(--text-muted)"><?= $cnt ?> (<?= $sv['response_count']>0?round($cnt/$sv['response_count']*100):0 ?>%)</span>
        </div>
        <div style="height:8px;background:var(--border);border-radius:8px;overflow:hidden">
          <div style="height:100%;width:<?= round($cnt/$max_freq*100) ?>%;background:var(--orange);border-radius:8px;transition:width .4s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <a href="surveys.php" class="btn btn-secondary"><i class="ti ti-arrow-left"></i> Back to Surveys</a>
</div>
<?php endif; endif; ?>

</main>
</div>

<!-- Create Survey Modal -->
<?php if(is_admin()): ?>
<div class="modal-overlay" id="create-survey-modal" onclick="if(event.target.id==='create-survey-modal')closeModal('create-survey-modal')">
  <div class="modal" style="max-width:580px">
    <button class="modal-close" onclick="closeModal('create-survey-modal')"><i class="ti ti-x"></i></button>
    <h2>Create New Survey</h2>
    <form method="POST">
      <input type="hidden" name="form" value="create_survey">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Survey Title *</label>
          <input class="form-input" type="text" name="title" placeholder="e.g. Term 2 Needs Assessment" required>
        </div>
        <div class="form-group">
          <label class="form-label">Target Audience</label>
          <select class="form-select" name="target_type">
            <option value="all">All Users</option>
            <option value="schools">Schools Only</option>
            <option value="companies">Companies Only</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" name="description" rows="2" placeholder="Brief description of what this survey covers…"></textarea>
      </div>
      <div id="questions-wrap">
        <?php for($i=0;$i<4;$i++): ?>
        <div class="form-row" style="margin-bottom:8px">
          <div class="form-group" style="flex:2">
            <label class="form-label"><?= $i===0?'Questions *':'' ?></label>
            <input class="form-input" type="text" name="questions[]" placeholder="Question <?= $i+1 ?><?= $i===0?' (required)':' (optional)' ?>" <?= $i===0?'required':'' ?>>
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label"><?= $i===0?'Type':'&nbsp;' ?></label>
            <select class="form-select" name="qtypes[]">
              <option value="text">Text</option>
              <option value="rating">Rating (1-5)</option>
              <option value="yesno">Yes / No</option>
              <option value="multiple">Multiple Choice</option>
            </select>
          </div>
        </div>
        <?php endfor; ?>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('create-survey-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-check"></i> Create Survey</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openModal(id){document.getElementById(id).classList.add('open')}
function closeModal(id){document.getElementById(id).classList.remove('open')}
</script>
<?php include 'includes/footer.php'; ?>