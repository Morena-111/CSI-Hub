<?php
$active_page = 'programmes';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$enquiry_success = '';

// Handle enquiry form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to   = 'info@researchunlimitedsa.co.za';
    $type = $_POST['enquiry_type'] ?? 'general';

    if ($type === 'run') {
        $company  = htmlspecialchars($_POST['company'] ?? '');
        $contact  = htmlspecialchars($_POST['contact'] ?? '');
        $email    = htmlspecialchars($_POST['email'] ?? '');
        $budget   = htmlspecialchars($_POST['budget'] ?? '');
        $schools  = htmlspecialchars($_POST['schools'] ?? '');
        $message  = htmlspecialchars($_POST['message'] ?? '');
        $subject  = "CSI Enquiry — We Run It — {$company}";
        $body     = "Company: {$company}\nContact: {$contact}\nEmail: {$email}\nBudget: R{$budget}\nSchools: {$schools}\nMessage: {$message}";
    } else {
        $company  = htmlspecialchars($_POST['company'] ?? '');
        $pkg      = htmlspecialchars($_POST['package'] ?? '');
        $email    = htmlspecialchars($_POST['email'] ?? '');
        $province = htmlspecialchars($_POST['province'] ?? '');
        $subject  = "CSI Package Request — {$pkg} — {$company}";
        $body     = "Company: {$company}\nPackage: {$pkg}\nEmail: {$email}\nProvince: {$province}";
    }

    $headers = "From: noreply@researchunlimitedsa.co.za\r\nReply-To: {$email}\r\n";
    @mail($to, $subject, $body, $headers);
    $enquiry_success = $type === 'run'
        ? "Enquiry sent! We will contact you within 2 business days."
        : "Package request sent! We will be in touch shortly.";
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
    <span class="active-crumb">Our Programmes</span>
  </div>

  <div class="page-header">
    <div>
      <h1>Our Programmes</h1>
      <p>Choose how Research Unlimited can deliver the CSI programme for your company</p>
    </div>
  </div>

  <?php if ($enquiry_success): ?>
  <div style="background:#e6faf5;border:1px solid #a7e9d3;color:#054d36;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="ti ti-circle-check" style="font-size:16px"></i> <?= htmlspecialchars($enquiry_success) ?>
  </div>
  <?php endif; ?>

  <!-- TWO OPTIONS -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px">

    <!-- Option 1: We Run It -->
    <div style="background:var(--white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden">
      <div style="background:var(--navy);padding:28px 28px 20px">
        <div style="width:52px;height:52px;background:var(--orange);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px;color:white;margin-bottom:16px">
          <i class="ti ti-rocket"></i>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:white;margin-bottom:6px">We Run It For You</h2>
        <p style="font-size:13px;color:rgba(255,255,255,.55);line-height:1.6">Research Unlimited designs, manages and reports on the full CSI programme on your behalf.</p>
      </div>
      <div style="padding:24px 28px">
        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px">
          <?php foreach([
            'Full programme design & implementation',
            'School selection & onboarding',
            'Monthly site visits & monitoring',
            'Quarterly impact reports',
            'BBBEE compliance documentation',
            'Dedicated CSI coordinator assigned',
          ] as $feat): ?>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text)">
            <i class="ti ti-circle-check" style="color:var(--teal);font-size:16px;flex-shrink:0"></i>
            <?= $feat ?>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="background:var(--orange-soft);border-radius:10px;padding:14px 16px;margin-bottom:16px">
          <div style="font-size:11px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Management Fee</div>
          <div style="font-size:18px;font-weight:700;color:var(--text)">10–15% of programme budget</div>
          <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px">Quoted based on scope and number of schools</div>
        </div>
        <?php if (can_edit()): ?>
        <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="openModal('enquire-run')">
          <i class="ti ti-send"></i> Enquire About This Option
        </button>
        <?php else: ?>
        <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="openModal('enquire-run')">
          <i class="ti ti-send"></i> Request This Programme
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Option 2: Buy / Sponsor -->
    <div style="background:var(--white);border:1.5px solid var(--border);border-radius:16px;overflow:hidden">
      <div style="background:linear-gradient(135deg,#00956a,#00c48c);padding:28px 28px 20px">
        <div style="width:52px;height:52px;background:rgba(255,255,255,.2);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px;color:white;margin-bottom:16px">
          <i class="ti ti-shopping-cart"></i>
        </div>
        <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:white;margin-bottom:6px">Buy a Programme Package</h2>
        <p style="font-size:13px;color:rgba(255,255,255,.7);line-height:1.6">Choose a ready-made CSI programme package. You fund it, we deliver it — simple and transparent.</p>
      </div>
      <div style="padding:24px 28px">

        <!-- Packages -->
        <?php foreach([
          ['Bronze','R150k – R300k','1 school · 1 focus area · Annual report','#b7791f','#fffbea'],
          ['Silver','R300k – R600k','2–3 schools · 2 focus areas · Quarterly reports','var(--text-muted)','var(--surface)'],
          ['Gold',  'R600k – R1M+', '5+ schools · Full programme · Monthly reporting','#E8541A','var(--orange-soft)'],
        ] as [$tier, $range, $desc, $col, $bg]): ?>
        <div style="background:<?= $bg ?>;border-radius:10px;padding:12px 14px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <span style="font-size:13px;font-weight:700;color:<?= $col ?>"><?= $tier ?> Package</span>
            <span style="font-size:12.5px;font-weight:700;color:var(--text)"><?= $range ?></span>
          </div>
          <div style="font-size:12px;color:var(--text-muted)"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>

        <button class="btn btn-secondary" style="width:100%;justify-content:center;margin-top:6px;border-color:var(--teal);color:var(--teal)" onclick="openModal('enquire-buy')">
          <i class="ti ti-package"></i> Choose a Package
        </button>
      </div>
    </div>
  </div>

  <!-- PROCESS STEPS -->
  <div class="widget">
    <div class="widget-title"><i class="ti ti-list-check"></i> How It Works</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:20px;padding:8px 0">
      <?php foreach([
        ['1','ti-phone','Initial Consultation','We meet to understand your CSI goals and budget'],
        ['2','ti-file-description','Programme Design','We design a tailored programme aligned to your B-BBEE goals'],
        ['3','ti-school','School Selection','We identify and onboard suitable beneficiary schools'],
        ['4','ti-rocket','Implementation','Programme launches with trained facilitators on-site'],
        ['5','ti-chart-bar','Monitoring','Monthly visits, data collection and learner tracking'],
        ['6','ti-file-analytics','Reporting','Quarterly and annual impact reports for your records'],
      ] as [$num, $icon, $title, $desc]): ?>
      <div style="text-align:center;padding:10px">
        <div style="width:44px;height:44px;background:var(--orange-soft);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:20px;color:var(--orange)">
          <i class="ti <?= $icon ?>"></i>
        </div>
        <div style="font-size:10px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Step <?= $num ?></div>
        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px"><?= $title ?></div>
        <div style="font-size:11.5px;color:var(--text-muted);line-height:1.5"><?= $desc ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</main>
</div>

<!-- ENQUIRE: WE RUN IT -->
<div class="modal-overlay" id="enquire-run" onclick="if(event.target.id==='enquire-run')closeModal('enquire-run')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('enquire-run')"><i class="ti ti-x"></i></button>
    <h2>Enquire — We Run It For You</h2>
    <div class="modal-sub">Tell us about your CSI goals and we'll put together a proposal.</div>
    <div class="form-group">
      <label class="form-label">Company Name</label>
      <input class="form-input" type="text" placeholder="Your company or foundation">
    </div>
    <div class="form-group">
      <label class="form-label">Contact Person</label>
      <input class="form-input" type="text" placeholder="Full name">
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input class="form-input" type="email" placeholder="you@company.co.za">
    </div>
    <div class="form-group">
      <label class="form-label">Estimated Budget (R)</label>
      <input class="form-input" type="text" placeholder="e.g. 500 000">
    </div>
    <div class="form-group">
      <label class="form-label">Number of Schools in Mind</label>
      <select class="form-select">
        <option>1–2 schools</option><option>3–5 schools</option><option>6–10 schools</option><option>10+ schools</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Message / Requirements</label>
      <textarea class="form-input" rows="3" placeholder="Any specific focus areas, provinces or goals?"></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeModal('enquire-run')">Cancel</button>
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-send"></i> Send Enquiry
      </button>
    </div>
  </div>
</div>

<!-- ENQUIRE: BUY PACKAGE -->
<div class="modal-overlay" id="enquire-buy" onclick="if(event.target.id==='enquire-buy')closeModal('enquire-buy')">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('enquire-buy')"><i class="ti ti-x"></i></button>
    <h2>Choose a Package</h2>
    <div class="modal-sub">Select a package that fits your budget and goals.</div>
    <div class="form-group">
      <label class="form-label">Package *</label>
      <select class="form-select">
        <option value="">Select package</option>
        <option>Bronze — R150k to R300k (1 school)</option>
        <option>Silver — R300k to R600k (2–3 schools)</option>
        <option>Gold — R600k to R1M+ (5+ schools)</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Company Name</label>
      <input class="form-input" type="text" placeholder="Your company or foundation">
    </div>
    <div class="form-group">
      <label class="form-label">Contact Email</label>
      <input class="form-input" type="email" placeholder="you@company.co.za">
    </div>
    <div class="form-group">
      <label class="form-label">Preferred Province</label>
      <select class="form-select">
        <option value="">Any province</option>
        <?php foreach(['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape','Limpopo','Mpumalanga','North West','Free State','Northern Cape'] as $p): ?>
          <option><?= $p ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" onclick="closeModal('enquire-buy')">Cancel</button>
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-package"></i> Request Package
      </button>
    </div>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>

<?php include 'includes/footer.php'; ?>