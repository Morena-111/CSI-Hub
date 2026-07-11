<?php
$active_page = 'programmes';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$enquiry_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['enquiry_type'] ?? 'general';
    $company = htmlspecialchars($_POST['company'] ?? '');
    $contact = htmlspecialchars($_POST['contact'] ?? '');
    $email   = htmlspecialchars($_POST['email'] ?? '');
    $message = htmlspecialchars($_POST['message'] ?? '');
    $prog    = htmlspecialchars($_POST['programme'] ?? '');
    $budget  = htmlspecialchars($_POST['budget'] ?? '');
    $subject = "CSI Hub Enquiry — {$prog} — {$company}";
    $body    = "Company: {$company}\nContact: {$contact}\nEmail: {$email}\nProgramme: {$prog}\nBudget: R{$budget}\nMessage: {$message}";
    $headers = "From: noreply@researchunlimitedsa.co.za\r\nReply-To: {$email}\r\n";
    send_email(SITE_EMAIL, $subject, $body);
    // Save to requests log
    $req_file = __DIR__.'/data/programme_requests.json';
    $reqs = file_exists($req_file) ? json_decode(file_get_contents($req_file),true)??[] : [];
    $reqs[] = [
        'company'      => $_POST['company']??'',
        'contact'      => $_POST['contact']??'',
        'email'        => $_POST['email']??'',
        'phone'        => $_POST['phone']??'',
        'programme'    => $_POST['programme']??'',
        'province'     => $_POST['province']??'',
        'schools_count'=> $_POST['schools_count']??'',
        'message'      => $_POST['message']??'',
        'date'         => date('d M Y H:i'),
        'status'       => 'pending',
    ];
    if(!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data',0755,true);
    file_put_contents($req_file, json_encode($reqs, JSON_PRETTY_PRINT));
    $enquiry_success = "Thank you! We will contact you within 2 business days.";
}

$is_company = (!is_admin() && ($_SESSION['user_type']??'') === 'company');
$linked_id  = (int)($_SESSION['linked_id'] ?? 0);

$my_partnerships = [];
if ($is_company && $linked_id) {
    $st = $pdo->prepare("
        SELECT p.*, s.name AS school_name, s.province,
               DATEDIFF(p.end_date, CURDATE()) AS days_left
        FROM partnerships p
        JOIN schools s ON s.id = p.school_id
        WHERE p.company_id = ?
        ORDER BY p.status ASC, p.start_date DESC
    ");
    $st->execute([$linked_id]);
    $my_partnerships = $st->fetchAll();
}

// Real RU programmes based on their actual services
$programmes = [
    [
        'icon'  => 'ti-school',
        'color' => '#6c5ce7',
        'bg'    => '#f0eeff',
        'title' => 'Postgraduate Success & Leadership Development Training',
        'tag'   => 'Leadership',
        'desc'  => "A structured programme equipping postgraduate students and emerging leaders with the skills, mindset and networks to succeed in academia and the professional world. Aligned to the SDGs and South Africa's national development agenda.",
        'items' => [
            'Leadership skills development',
            'Academic research & writing support',
            'Career readiness & professional coaching',
            'Networking & mentorship opportunities',
            'Postgraduate study support',
            'M&E and impact reporting',
        ],
    ],
    [
        'icon'  => 'ti-recycle',
        'color' => '#00956a',
        'bg'    => '#e6faf5',
        'title' => 'School Waste Management Program',
        'tag'   => 'Environment',
        'desc'  => "An environmental education initiative equipping schools with knowledge, tools and systems to reduce waste, recycle responsibly and build a culture of environmental stewardship. Contributes to SDG 12 (Responsible Consumption and Production).",
        'items' => [
            'Waste audit & baseline assessment',
            'Learner-led recycling clubs',
            'Teacher training on environmental education',
            'Waste reduction campaigns',
            'Reporting on waste diverted',
            'B-BBEE SED compliance documentation',
        ],
    ],
    [
        'icon'  => 'ti-bolt',
        'color' => '#f5a623',
        'bg'    => '#fffbea',
        'title' => 'Power UP: Schools for a Sustainable Future',
        'tag'   => 'Sustainability',
        'desc'  => "An energy and sustainability programme empowering schools to adopt clean energy practices, reduce their carbon footprint and teach learners about renewable energy and sustainable living. Aligned to SDG 7 (Affordable and Clean Energy).",
        'items' => [
            'Solar energy awareness & education',
            'Energy efficiency audits at schools',
            'Learner sustainability projects',
            'STEM integration with energy topics',
            'Community awareness campaigns',
            'Quarterly progress and impact reporting',
        ],
    ],
    [
        'icon'  => 'ti-briefcase',
        'color' => '#E8541A',
        'bg'    => '#fdf0ea',
        'title' => 'Youth Employability Program',
        'tag'   => 'Youth Empowerment',
        'desc'  => "A comprehensive youth development programme bridging the gap between education and employment. Builds practical, digital and interpersonal skills employers demand — contributing to SDG 8 (Decent Work and Economic Growth).",
        'items' => [
            'CV writing & job application skills',
            'Interview preparation & confidence building',
            'Digital literacy & computer skills',
            'Entrepreneurship & small business basics',
            'Workplace readiness workshops',
            'Linkage to SETA-accredited training',
        ],
    ],
    [
        'icon'  => 'ti-atom',
        'color' => '#2dbcd8',
        'bg'    => '#e8f8fc',
        'title' => 'Polymer Innovation & Circular Education Program',
        'tag'   => 'Innovation & STEM',
        'desc'  => "A cutting-edge STEM programme introducing learners to polymer science, materials innovation and circular economy thinking. Developed in partnership with industry to give South African learners exposure to real-world science — supporting SDG 4 and SDG 9.",
        'items' => [
            'Polymer science workshops for learners',
            'Circular economy & recycling education',
            'Industry site visits & exposure',
            'Hands-on science experiments',
            'Teacher upskilling in STEM subjects',
            'Innovation challenge competitions',
        ],
    ],
    [
        'icon'  => 'ti-shield-heart',
        'color' => '#e91e8c',
        'bg'    => '#fce4ec',
        'title' => 'Education Awareness: GBV Initiative',
        'tag'   => 'GBV Awareness',
        'desc'  => "A gender-based violence awareness and prevention programme delivered in schools and communities. Educates learners, educators and parents on signs of GBV, available support resources and building safer school environments — aligned to SDG 5 and SDG 16.",
        'items' => [
            'GBV awareness workshops for learners',
            'Safe space facilitation in schools',
            'Educator & parent sensitisation sessions',
            'Referral pathways to support services',
            'Reporting on reach and awareness levels',
            'Collaboration with NGOs and government',
        ],
    ],
];

include 'includes/header.php';
?>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<main class="main">

<div class="page-banner">
  <i class="ti ti-home" style="font-size:13px"></i>
  <span style="color:var(--text-muted)">Home</span>
  <span style="color:var(--border)">›</span>
  <span class="active-crumb">Programmes</span>
</div>

<?php if ($enquiry_success): ?>
<div style="background:var(--teal-soft);border:1px solid #a7e9d3;color:#054d36;border-radius:10px;
            padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:9px;font-size:13px;font-weight:500">
  <i class="ti ti-circle-check" style="font-size:18px"></i><?= htmlspecialchars($enquiry_success) ?>
</div>
<?php endif; ?>

<?php if ($is_company && !empty($my_partnerships)): ?>
<!-- ══ COMPANY USER — MY ACTIVE PROGRAMMES ══ -->
<div class="page-header">
  <div>
    <h1>My Programmes</h1>
    <p>Your active CSI programmes managed by Research Unlimited</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('enquire-modal')">
    <i class="ti ti-plus"></i> Enquire About More
  </button>
</div>

<!-- Stats -->
<?php
$active_c = count(array_filter($my_partnerships, fn($p)=>$p['status']==='active'));
$total_v  = array_sum(array_column($my_partnerships,'amount'));
$school_c = count(array_unique(array_column($my_partnerships,'school_id')));
?>
<div class="stats-row" style="margin-bottom:22px">
  <div class="stat-card orange">
    <div class="stat-label">Active Programmes</div>
    <div class="stat-value orange"><?= $active_c ?></div>
    <div class="stat-sub">Currently running</div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Schools Reached</div>
    <div class="stat-value teal"><?= $school_c ?></div>
    <div class="stat-sub">Beneficiary schools</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-label">Total Investment</div>
    <div class="stat-value" style="color:var(--gold)">R<?= number_format($total_v/1000000,2) ?>M</div>
    <div class="stat-sub">CSI committed</div>
  </div>
</div>

<!-- Horizontal partnership cards -->
<div style="display:flex;flex-direction:column;gap:14px">
<?php foreach($my_partnerships as $p):
  $s  = strtotime($p['start_date']); $e = strtotime($p['end_date']); $now = time();
  $pg = $p['status']==='completed'?100:($p['status']==='pending'?0:($now>=$e?100:($now<=$s?0:round(($now-$s)/($e-$s)*100))));
  $dl = (int)$p['days_left'];
  $sc = ['active'=>['#e6faf5','#00956a'],'pending'=>['#fffbea','#9a6700'],'completed'=>['#f1f5f9','#64748b'],'paused'=>['#fde9e9','#c53030']][$p['status']] ?? ['#f1f5f9','#64748b'];
?>
<div style="background:white;border:1px solid var(--border);border-radius:13px;
            display:flex;align-items:stretch;overflow:hidden;
            box-shadow:0 1px 8px rgba(26,31,46,.05)">
  <!-- accent -->
  <div style="width:5px;background:var(--orange);flex-shrink:0"></div>
  <!-- school + progress -->
  <div style="padding:16px 20px;flex:2;border-right:1px solid var(--border)">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px">
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">
          <?= htmlspecialchars($p['focus_area']) ?>
        </div>
        <div style="font-size:15px;font-weight:700;color:var(--text)"><?= htmlspecialchars($p['school_name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
          <i class="ti ti-map-pin" style="font-size:12px"></i> <?= htmlspecialchars($p['province']) ?>
        </div>
      </div>
      <span style="background:<?= $sc[0] ?>;color:<?= $sc[1] ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600">
        <?= ucfirst($p['status']) ?>
      </span>
    </div>
    <div style="display:flex;align-items:center;gap:10px">
      <div style="flex:1;height:6px;background:var(--border);border-radius:6px;overflow:hidden">
        <div style="height:100%;width:<?= $pg ?>%;background:<?= $pg>=100?'var(--teal)':'var(--orange)' ?>;border-radius:6px"></div>
      </div>
      <span style="font-size:11px;font-weight:700;color:var(--orange);flex-shrink:0"><?= $pg ?>%</span>
    </div>
  </div>
  <!-- dates -->
  <div style="padding:16px 18px;flex:1;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center;gap:6px">
    <div>
      <div style="font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Start</div>
      <div style="font-size:12.5px;font-weight:600;color:var(--text)"><?= date('d M Y',strtotime($p['start_date'])) ?></div>
    </div>
    <div>
      <div style="font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">End</div>
      <div style="font-size:12.5px;font-weight:600;color:<?= $dl<30&&$p['status']==='active'?'#c53030':'var(--text)' ?>">
        <?= date('d M Y',strtotime($p['end_date'])) ?>
        <?php if($dl>0&&$p['status']==='active'): ?><span style="font-size:10px;color:<?= $dl<30?'#c53030':'var(--text-muted)' ?>"> · <?= $dl ?>d left</span><?php endif; ?>
      </div>
    </div>
  </div>
  <!-- amount -->
  <div style="padding:16px 18px;flex:1;border-right:1px solid var(--border);display:flex;flex-direction:column;justify-content:center">
    <div style="font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Investment</div>
    <div style="font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:var(--orange)">R<?= number_format($p['amount']) ?></div>
  </div>
  <!-- actions -->
  <div style="padding:16px 14px;display:flex;flex-direction:column;justify-content:center;gap:8px;min-width:110px">
    <a href="partnerships.php?partner_id=<?= $linked_id ?>" class="btn btn-primary" style="font-size:11.5px;padding:6px 12px;justify-content:center">
      <i class="ti ti-eye"></i> Details
    </a>
    <a href="documents.php" class="btn btn-secondary" style="font-size:11.5px;padding:6px 12px;justify-content:center">
      <i class="ti ti-file"></i> Docs
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php else: ?>
<!-- ══ ADMIN — PROGRAMME REQUESTS ══ -->
<?php if(is_admin()):
// Load enquiry requests from data file
$req_file = __DIR__.'/data/programme_requests.json';
$requests = file_exists($req_file) ? json_decode(file_get_contents($req_file),true)??[] : [];
if(!empty($requests)): ?>
<div class="widget" style="margin-bottom:22px">
  <div class="widget-title"><i class="ti ti-inbox" style="color:var(--orange)"></i>
    Programme Requests
    <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:8px"><?= count($requests) ?> pending</span>
  </div>
  <table class="data-table">
    <thead><tr><th>Company</th><th>Contact</th><th>Programme</th><th>Province</th><th>Schools</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach(array_reverse($requests) as $req): ?>
    <tr>
      <td class="cell-name"><?= htmlspecialchars($req['company']??'—') ?></td>
      <td style="font-size:12px">
        <?= htmlspecialchars($req['contact']??'—') ?><br>
        <span style="color:var(--text-muted)"><?= htmlspecialchars($req['email']??'') ?></span>
      </td>
      <td><?= htmlspecialchars($req['programme']??'—') ?></td>
      <td style="font-size:12px"><?= htmlspecialchars($req['province']??'—') ?></td>
      <td style="font-size:12px"><?= htmlspecialchars($req['schools_count']??'—') ?></td>
      <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($req['date']??'—') ?></td>
      <td><span class="status-badge pending">Pending</span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; endif; ?>

<!-- ══ ADMIN / ALL USERS — FULL PROGRAMME CATALOGUE ══ -->
<div class="page-header">
  <div>
    <h1>Our Programmes</h1>
    <p>Research Unlimited — Research Made Easy · <a href="https://researchunlimitedsa.co.za" target="_blank" style="color:var(--orange)">researchunlimitedsa.co.za</a></p>
  </div>
  <button class="btn btn-primary" onclick="openModal('enquire-modal')">
    <i class="ti ti-send"></i> Enquire Now
  </button>
</div>

<!-- Company intro banner -->
<div style="background:linear-gradient(120deg,#0d1e3d 0%,#1a3560 60%,#0d1e3d 100%);
            border-radius:14px;padding:28px 32px;margin-bottom:28px;
            display:flex;align-items:center;gap:28px;flex-wrap:wrap">
  <div style="flex:1;min-width:260px">
    <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.45);
                text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">
      100% Black-owned &amp; Female-owned · B-BBEE Level 1
    </div>
    <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:white;
               margin-bottom:10px;line-height:1.3">
      Research Unlimited is an African education research consultancy focused on CSI, education and business research.
    </h2>
    <p style="font-size:12.5px;color:rgba(255,255,255,.55);line-height:1.7;max-width:520px">
      We design, implement and monitor high-impact CSI programmes that address critical skills gaps and societal challenges — aligned to the Sustainable Development Goals (SDGs) and the South African DBE's E³ initiative.
    </p>
  </div>
  <div style="display:flex;flex-direction:column;gap:10px;flex-shrink:0">
    <?php foreach([
      ['ti-shield-check','B-BBEE Level 1 Contributor'],
      ['ti-users','Serving undergrads, postgrads, NGOs & businesses'],
      ['ti-map-pin','All 9 provinces — nationwide reach'],
      ['ti-award','5+ years field research experience'],
    ] as [$ico,$txt]): ?>
    <div style="display:flex;align-items:center;gap:9px;font-size:12px;color:rgba(255,255,255,.75)">
      <i class="ti <?= $ico ?>" style="color:var(--orange);font-size:15px;flex-shrink:0"></i>
      <?= $txt ?>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:6px;display:flex;gap:10px">
      <a href="tel:+27680245514" style="font-size:12px;color:var(--orange);text-decoration:none;display:flex;align-items:center;gap:5px">
        <i class="ti ti-phone"></i> +27 68 024 5514
      </a>
      <a href="mailto:info@researchunlimitedsa.co.za" style="font-size:12px;color:rgba(255,255,255,.55);text-decoration:none;display:flex;align-items:center;gap:5px">
        <i class="ti ti-mail"></i> info@researchunlimitedsa.co.za
      </a>
    </div>
  </div>
</div>

<!-- PROGRAMME GRID — horizontal cards -->
<div style="display:flex;flex-direction:column;gap:16px">
  <?php foreach($programmes as $i => $prog): ?>
  <div style="background:white;border:1px solid var(--border);border-radius:13px;
              display:flex;align-items:stretch;overflow:hidden;
              box-shadow:0 1px 8px rgba(26,31,46,.05);
              transition:box-shadow .15s"
       onmouseenter="this.style.boxShadow='0 4px 20px rgba(26,31,46,.1)'"
       onmouseleave="this.style.boxShadow='0 1px 8px rgba(26,31,46,.05)'">

    <!-- Left colour block with icon -->
    <div style="width:90px;flex-shrink:0;background:<?= $prog['bg'] ?>;
                display:flex;flex-direction:column;align-items:center;
                justify-content:center;gap:8px;padding:20px 0">
      <div style="width:44px;height:44px;border-radius:12px;
                  background:<?= $prog['color'] ?>;
                  display:flex;align-items:center;justify-content:center">
        <i class="ti <?= $prog['icon'] ?>" style="font-size:20px;color:white"></i>
      </div>
      <span style="font-size:9px;font-weight:700;color:<?= $prog['color'] ?>;
                   text-transform:uppercase;letter-spacing:.05em;text-align:center;
                   padding:0 6px;line-height:1.3"><?= $prog['tag'] ?></span>
    </div>

    <!-- Description -->
    <div style="padding:18px 22px;flex:1.2;border-right:1px solid var(--border)">
      <h3 style="font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:7px">
        <?= htmlspecialchars($prog['title']) ?>
      </h3>
      <p style="font-size:12.5px;color:var(--text-muted);line-height:1.65">
        <?= htmlspecialchars($prog['desc']) ?>
      </p>
    </div>

    <!-- Feature list -->
    <div style="padding:18px 20px;flex:1;border-right:1px solid var(--border)">
      <div style="font-size:9.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;
                  letter-spacing:.06em;margin-bottom:10px">What's included</div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach($prog['items'] as $item): ?>
        <div style="display:flex;align-items:flex-start;gap:7px;font-size:12px;color:var(--text)">
          <i class="ti ti-check" style="color:<?= $prog['color'] ?>;font-size:13px;flex-shrink:0;margin-top:1px"></i>
          <?= htmlspecialchars($item) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- CTA -->
    <div style="padding:18px 16px;display:flex;flex-direction:column;
                align-items:center;justify-content:center;gap:8px;min-width:120px">
      <button class="btn btn-primary"
              style="font-size:12px;padding:8px 16px;justify-content:center;width:100%"
              onclick="document.getElementById('enquire-prog').value='<?= htmlspecialchars($prog['title'],ENT_QUOTES) ?>';openModal('enquire-modal')">
        <i class="ti ti-send"></i> Enquire
      </button>
      <div style="font-size:10.5px;color:var(--text-muted);text-align:center;line-height:1.4">
        Quoted based on scope and number of schools
      </div>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<!-- Bottom CTA banner -->
<div style="background:var(--orange-soft);border:1px solid rgba(232,84,26,.2);
            border-radius:14px;padding:24px 28px;margin-top:24px;
            display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
  <div>
    <h3 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px">
      Ready to invest in South Africa\'s future?
    </h3>
    <p style="font-size:12.5px;color:var(--text-muted)">
      Mon–Sat 8:00am–17:00pm · <a href="tel:+27680245514" style="color:var(--orange);font-weight:600">+27 68 024 5514</a> ·
      <a href="mailto:info@researchunlimitedsa.co.za" style="color:var(--orange);font-weight:600">info@researchunlimitedsa.co.za</a>
    </p>
  </div>
  <button class="btn btn-primary" style="padding:12px 24px;font-size:13.5px;flex-shrink:0"
          onclick="openModal('enquire-modal')">
    <i class="ti ti-rocket"></i> Get Started Today
  </button>
</div>

<?php endif; ?>

<!-- ══ ENQUIRY MODAL ══ -->
<div class="modal-overlay" id="enquire-modal" onclick="if(event.target.id==='enquire-modal')closeModal('enquire-modal')">
  <div class="modal" style="max-width:520px">
    <button class="modal-close" onclick="closeModal('enquire-modal')"><i class="ti ti-x"></i></button>
    <h2>Programme Enquiry</h2>
    <div class="modal-sub">Tell us about your CSI goals. We respond within 2 business days.</div>
    <form method="POST">
      <input type="hidden" name="enquiry_type" value="programme">
      <div class="form-group">
        <label class="form-label">Programme of Interest</label>
        <select class="form-select" name="programme" id="enquire-prog">
          <?php foreach($programmes as $pg): ?>
          <option value="<?= htmlspecialchars($pg['title']) ?>"><?= htmlspecialchars($pg['title']) ?></option>
          <?php endforeach; ?>
          <option value="General Enquiry">General Enquiry</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Company / Organisation *</label>
          <input class="form-input" type="text" name="company" placeholder="Your company name" required>
        </div>
        <div class="form-group">
          <label class="form-label">Contact Person *</label>
          <input class="form-input" type="text" name="contact" placeholder="Full name" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input class="form-input" type="email" name="email" placeholder="you@company.co.za" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <input class="form-input" type="tel" name="phone" placeholder="079 xxx xxxx" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Target Province(s)</label>
          <select class="form-select" name="province">
            <option value="">Select province…</option>
            <?php foreach(['Gauteng','KwaZulu-Natal','Western Cape','Eastern Cape','Limpopo','Mpumalanga','North West','Free State','Northern Cape'] as $pv): ?>
            <option><?= $pv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Number of Schools Targeted</label>
          <input class="form-input" type="text" name="schools_count" placeholder="e.g. 3 schools">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">What do you hope to achieve through this programme?</label>
        <textarea class="form-input" name="message" rows="3" placeholder="e.g. Improve STEM results in 5 rural schools, reach 2000 learners by end of 2026…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal('enquire-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-send"></i> Send Enquiry</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>

<?php include 'includes/footer.php'; ?>