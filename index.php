<?php
/**
 * index.php — Entry point
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['role'])) {
    header('Location: /csi-hub/dashboard.php');
} else {
    header('Location: /csi-hub/login.php');
}
exit;