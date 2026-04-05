<?php
// ============================================================
// shift_close.php — Mark a shift as finished (sets end_time)
// Accepts POST only.
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tracker/dashboard.php');
    exit;
}

$shift_id = (int)($_POST['shift_id'] ?? 0);
$end_time = trim($_POST['end_time'] ?? '');

if ($shift_id <= 0) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Verify the shift belongs to this user before updating
$stmt = $pdo->prepare('SELECT id FROM shifts WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$shift_id, $user_id]);

if (!$stmt->fetch()) {
    header('Location: /tracker/dashboard.php');
    exit;
}

$stmt = $pdo->prepare('UPDATE shifts SET end_time = ? WHERE id = ?');
$stmt->execute([$end_time ?: date('H:i:s'), $shift_id]);

header('Location: /tracker/shift_view.php?id=' . $shift_id);
exit;
?>
