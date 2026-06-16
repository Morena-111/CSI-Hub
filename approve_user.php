<?php
/**
 * approve_user.php
 */
require_once 'includes/auth.php';
require_admin_role();

$signups_file = __DIR__ . '/data/pending_signups.json';
$pending = file_exists($signups_file) ? json_decode(file_get_contents($signups_file), true) ?? [] : [];

$username = trim($_POST['username'] ?? '');
$action   = $_POST['action'] ?? '';

if ($username && isset($pending[$username])) {
    if ($action === 'approve') {
        $pending[$username]['approved'] = true;
    } elseif ($action === 'reject') {
        unset($pending[$username]);
    }
    file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
}

header('Location: /csi-hub/team.php');
exit;