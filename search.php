<?php
/**
 * search.php — Global search API
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$like    = "%{$q}%";
$results = [];

// ── DATA ISOLATION SCOPE ───────────────────────────────────────
$scope_company = null;
$scope_school  = null;
if (!is_admin()) {
    if (($_SESSION['user_type']??'') === 'company' && isset($_SESSION['linked_id'])) {
        $scope_company = (int)$_SESSION['linked_id'];
    } elseif (($_SESSION['user_type']??'') === 'school' && isset($_SESSION['linked_id'])) {
        $scope_school = (int)$_SESSION['linked_id'];
    }
}

// ── PARTNERS ─────────────────────────────────────────────────
// Company users only see themselves. School/admin see all (schools
// need to find/identify partners; admin sees everything).
if (!$scope_school) {
    $sql = "SELECT id, name, status FROM companies WHERE name LIKE ?";
    $params = [$like];
    if ($scope_company) { $sql .= " AND id=?"; $params[] = $scope_company; }
    $sql .= " LIMIT 6";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $results[] = [
            'name'  => $r['name'],
            'type'  => 'Partner — ' . ucfirst($r['status']),
            'url'   => 'partnerships.php?partner_id=' . $r['id'],
            'icon'  => 'ti-users',
            'color' => '#E8541A',
            'bg'    => '#fdf0ea',
        ];
    }
}

// ── SCHOOLS ──────────────────────────────────────────────────
// School users only see themselves. Company/admin see all.
if (!$scope_company) {
    $sql = "SELECT id, name, province FROM schools WHERE (name LIKE ? OR province LIKE ?)";
    $params = [$like, $like];
    if ($scope_school) { $sql .= " AND id=?"; $params[] = $scope_school; }
    $sql .= " LIMIT 6";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $results[] = [
            'name'  => $r['name'],
            'type'  => 'School — ' . ($r['province'] ?? ''),
            'url'   => $scope_school ? 'schools.php' : ('schools.php?search=' . urlencode($r['name'])),
            'icon'  => 'ti-school',
            'color' => '#00c48c',
            'bg'    => '#e6faf5',
        ];
    }
}

// ── PARTNERSHIPS ─────────────────────────────────────────────
$sql = "
    SELECT p.id, c.name AS co, s.name AS sc, p.focus_area, p.status
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools s ON s.id=p.school_id
    WHERE (c.name LIKE ? OR s.name LIKE ? OR p.focus_area LIKE ?)
";
$params = [$like, $like, $like];
if ($scope_company) { $sql .= " AND p.company_id=?"; $params[] = $scope_company; }
if ($scope_school)  { $sql .= " AND p.school_id=?";  $params[] = $scope_school; }
$sql .= " LIMIT 6";
$st = $pdo->prepare($sql);
$st->execute($params);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['co'] . ' → ' . $r['sc'],
        'type'  => 'Partnership — ' . $r['focus_area'] . ' · ' . ucfirst($r['status']),
        'url'   => 'partnerships.php',
        'icon'  => 'ti-activity',
        'color' => '#6c5ce7',
        'bg'    => '#f0eeff',
    ];
}

// ── DOCUMENTS ────────────────────────────────────────────────
$st = $pdo->prepare("SELECT id, title, category FROM documents WHERE title LIKE ? OR category LIKE ? LIMIT 5");
$st->execute([$like, $like]);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['title'],
        'type'  => 'Document — ' . ($r['category'] ?? ''),
        'url'   => 'documents.php',
        'icon'  => 'ti-file-invoice',
        'color' => '#f5a623',
        'bg'    => '#fffbea',
    ];
}

// ── EVENTS ───────────────────────────────────────────────────
$st = $pdo->prepare("SELECT id, title, event_date, event_type FROM events WHERE title LIKE ? LIMIT 4");
$st->execute([$like]);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['title'],
        'type'  => 'Event — ' . date('d M Y', strtotime($r['event_date'])),
        'url'   => 'events.php',
        'icon'  => 'ti-calendar',
        'color' => '#2dbcd8',
        'bg'    => '#e8f8fc',
    ];
}

// ── SURVEYS (admin only) ──────────────────────────────────────
if (is_admin()) {
    try {
        $st = $pdo->prepare("SELECT id, title, status FROM surveys WHERE title LIKE ? LIMIT 4");
        $st->execute([$like]);
        foreach ($st->fetchAll() as $r) {
            $results[] = [
                'name'  => $r['title'],
                'type'  => 'Survey — ' . ucfirst($r['status']),
                'url'   => 'surveys.php?id=' . $r['id'],
                'icon'  => 'ti-clipboard-list',
                'color' => '#5b21b6',
                'bg'    => '#f3eaff',
            ];
        }
    } catch (Exception $e) { /* surveys table may not exist yet */ }
}

// ── TEAM MEMBERS (admin only) ─────────────────────────────────
if (is_admin()) {
    $admins_file = __DIR__ . '/data/admin_users.json';
    $admins = file_exists($admins_file) ? json_decode(file_get_contents($admins_file), true) ?? [] : [];
    $qlower = mb_strtolower($q);
    foreach ($admins as $uname => $a) {
        $name = $a['name'] ?? $uname;
        if (mb_stripos($name, $q) !== false || mb_stripos($uname, $q) !== false) {
            $results[] = [
                'name'  => $name,
                'type'  => 'Team Member — ' . ($a['role'] ?? 'Administrator'),
                'url'   => 'team.php',
                'icon'  => 'ti-user-circle',
                'color' => '#0d1e3d',
                'bg'    => '#eef1f6',
            ];
        }
    }
    // Approved users too
    $signups_file = __DIR__ . '/data/pending_signups.json';
    $pend = file_exists($signups_file) ? json_decode(file_get_contents($signups_file), true) ?? [] : [];
    foreach ($pend as $uname => $u) {
        if (!($u['approved']??false)) continue;
        $name = $u['name'] ?? $uname;
        if (mb_stripos($name, $q) !== false || mb_stripos($uname, $q) !== false || mb_stripos($u['org']??'', $q) !== false) {
            $results[] = [
                'name'  => $name,
                'type'  => 'User — ' . ($u['org'] ?? ucfirst($u['user_type']??'General')),
                'url'   => 'team.php',
                'icon'  => 'ti-user',
                'color' => '#00956a',
                'bg'    => '#e6faf5',
            ];
        }
    }
}

// ── PROGRAMMES (static keyword match) ─────────────────────────
$programme_pages = [
    ['name'=>'We Run It For You', 'kw'=>['run','manage','programme design'], 'icon'=>'ti-rocket'],
    ['name'=>'Buy a Programme Package', 'kw'=>['buy','package','bronze','silver','gold'], 'icon'=>'ti-package'],
];
foreach ($programme_pages as $pp) {
    foreach ($pp['kw'] as $kw) {
        if (stripos($kw, $q) !== false || stripos($q, $kw) !== false) {
            $results[] = [
                'name'  => $pp['name'],
                'type'  => 'Programme Option',
                'url'   => 'programmes.php',
                'icon'  => $pp['icon'],
                'color' => '#E8541A',
                'bg'    => '#fdf0ea',
            ];
            break;
        }
    }
}

// Deduplicate by name+url combo
$seen = [];
$unique = [];
foreach ($results as $r) {
    $key = $r['name'] . '|' . $r['url'];
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $unique[] = $r;
}

echo json_encode(array_slice($unique, 0, 14));