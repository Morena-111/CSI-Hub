<?php
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
$user_type = $_POST['user_type'] ?? 'general';
$linked_id = (int)($_POST['linked_id'] ?? 0);

if ($username && isset($pending[$username])) {
    if ($action === 'approve') {
        $u = $pending[$username];

        // AUTO-CREATE COMPANY RECORD (only for company users)
        if ($user_type === 'company' && !$linked_id) {
            $stmt = $pdo->prepare("
                INSERT INTO companies (name, sector, status, created_at)
                VALUES (?, 'CSI Partner', 'active', NOW())
            ");
            $stmt->execute([$u['org'] ?? $username]);
            $linked_id = (int)$pdo->lastInsertId();

            // Update company with extra signup info if we got a real insert
            if ($linked_id) {
                $pdo->prepare("UPDATE companies SET sector=?, status='active' WHERE id=?")
                    ->execute([
                        !empty($u['focus_areas']) ? $u['focus_areas'] : 'CSI Partner',
                        $linked_id
                    ]);
            }
        }

        if ($user_type === 'school' && !$linked_id) {
            $funding = (float)str_replace([',','R',' '], '', $u['funding_needed'] ?? 0);
            $stmt = $pdo->prepare("
                INSERT INTO schools
                (name, province, district, status, learners, educators, funding_requested, created_at)
                VALUES (?, ?, ?, 'active', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $u['org']       ?? $username,
                $u['province']  ?? '',
                $u['district']  ?? '',
                (int)($u['learners']  ?? 0),
                (int)($u['educators'] ?? 0),
                $funding,
            ]);
            $linked_id = (int)$pdo->lastInsertId();

            // Auto-create school need from challenges
            if ($linked_id && !empty($u['challenges']) && $funding > 0) {
                try {
                    $pdo->prepare("
                        INSERT INTO school_needs
                        (school_id, title, description, amount_needed, priority, status)
                        VALUES (?, ?, ?, ?, 'high', 'open')
                    ")->execute([
                        $linked_id,
                        'Funding Request — ' . ($u['org'] ?? $username),
                        $u['challenges'],
                        $funding,
                    ]);
                } catch (Exception $e) {}
            }
        }

        $pending[$username]['approved']    = true;
        $pending[$username]['user_type']   = $user_type;
        $pending[$username]['linked_id']   = $linked_id ?: null;
        $pending[$username]['approved_at'] = date('Y-m-d H:i:s');
        $pending[$username]['approved_by'] = $_SESSION['name'] ?? 'Admin';

        if (!empty($u['email'])) {
            $msg = "Dear {$u['name']},

"
                 . "Your CSI Hub access request has been approved!

"
                 . "You can now log in at: " . SITE_URL . "login.php
"
                 . "Username: {$username}

"
                 . "If you have any questions, contact us:
"
                 . "Email: " . HELP_EMAIL . "
"
                 . "Phone: " . HELP_PHONE . "

"
                 . "Research Unlimited — Research Made Easy";
            send_email($u['email'], "CSI Hub — Your Access Has Been Approved", $msg);
        }

    } elseif ($action === 'reject') {
        unset($pending[$username]);
    }

    file_put_contents($signups_file, json_encode($pending, JSON_PRETTY_PRINT));
}

header('Location: team.php');
exit;