<?php
// ============================================================
// index.php — Entry point
// Sends logged-in users to the dashboard, others to login.
// ============================================================

session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: /tracker/dashboard.php');
} else {
    header('Location: /tracker/login.php');
}
exit;
?>
