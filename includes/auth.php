<?php
// ============================================================
// includes/auth.php — Session authentication check
//
// Include this at the top of every protected page:
//   require 'includes/auth.php';
//
// If the user isn't logged in, they're sent to login.php.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    // Not logged in — redirect to login page
    header('Location: /tracker/login.php');
    exit;
}
?>
