<?php
/**
 * search.php
 */
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$like   = "%{$q}%";
$results = [];

// ── PARTNERS ─────────────────────────────────────────────────
$st = $pdo->prepare("SELECT id, name, status FROM companies WHERE name LIKE ? LIMIT 5");
$st->execute([$like]);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['name'],
        'type'  => 'Partner — ' . ucfirst($r['status']),
        'url'   => 'partner_detail.php?id=' . $r['id'],
        'icon'  => 'ti-users',
        'color' => '#E8541A',
        'bg'    => '#fdf0ea',
    ];
}

// ── SCHOOLS ──────────────────────────────────────────────────
$st = $pdo->prepare("SELECT id, name, province FROM schools WHERE name LIKE ? OR province LIKE ? LIMIT 5");
$st->execute([$like, $like]);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['name'],
        'type'  => 'School — ' . ($r['province'] ?? ''),
        'url'   => 'schools.php?search=' . urlencode($r['name']),
        'icon'  => 'ti-school',
        'color' => '#00c48c',
        'bg'    => '#e6faf5',
    ];
}

// ── PARTNERSHIPS ─────────────────────────────────────────────
$st = $pdo->prepare("
    SELECT p.id, c.name AS co, s.name AS sc, p.focus_area, p.status
    FROM partnerships p
    JOIN companies c ON c.id=p.company_id
    JOIN schools s ON s.id=p.school_id
    WHERE c.name LIKE ? OR s.name LIKE ? OR p.focus_area LIKE ?
    LIMIT 5
");
$st->execute([$like, $like, $like]);
foreach ($st->fetchAll() as $r) {
    $results[] = [
        'name'  => $r['co'] . ' → ' . $r['sc'],
        'type'  => 'Partnership — ' . $r['focus_area'],
        'url'   => 'partnerships.php',
        'icon'  => 'ti-activity',
        'color' => '#6c5ce7',
        'bg'    => '#f0eeff',
    ];
}

// ── DOCUMENTS ────────────────────────────────────────────────
$st = $pdo->prepare("SELECT id, title, category FROM documents WHERE title LIKE ? OR category LIKE ? LIMIT 4");
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
$st = $pdo->prepare("SELECT id, title, event_date, event_type FROM events WHERE title LIKE ? LIMIT 3");
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

echo json_encode(array_slice($results, 0, 12));