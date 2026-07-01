<?php
if (!function_exists('redirect')) require_once __DIR__ . '/config.php';
/**
 * approve_user.php
 */
require_once 'includes/auth.php';
require_admin_role();
require_once 'includes/db.php';

$signups_file = __DIR__ . '/data/pending_signups.json';
$pending = file_exists($signups_file)
    ? json_decode(file_get_contents($signups_file), true) ?? []
    : [];

$username  = trim($_POST['username'] ?? '');
$action    = $_POST['action'] ?? '';
$user_type = $_POST['user_type'] ?? 'general';   // 'company', 'school', 'general'
$linked_id = (int)($_POST['linked_id'] ?? 0);     // FK to companies.id or schools.id

if ($username && isset($pending[$username])) {
    if ($action === 'approve') {
        $pending[$username]['approved']   = true;
        $pending[$username]['user_type']  = $user_type;
        $pending[$username]['linked_id']  = $linked_id ?: null;
        $pending[$username]['approved_at']= date('Y-m-d H:i:s');
        $pending[$username]['approved_by']= $_SESSION['name'] ?? 'Admin';
    } elseif ($action === 'reject') {
        unset($pending[$username]);
    }
    file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
}

redirect('team.php');