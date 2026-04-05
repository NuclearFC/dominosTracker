<?php
// ============================================================
// logout.php — Destroys the session and redirects to login
// ============================================================

session_start();
session_destroy();

header('Location: /tracker/login.php');
exit;
?>
