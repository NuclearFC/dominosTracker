<?php
// ============================================================
// delivery_delete.php — Delete a delivery (POST only)
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /tracker/dashboard.php');
    exit;
}

$delivery_id = (int)($_POST['delivery_id'] ?? 0);
$shift_id    = (int)($_POST['shift_id'] ?? 0);

if ($delivery_id <= 0 || $shift_id <= 0) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Verify the delivery's shift belongs to this user and is still open
$stmt = $pdo->prepare(
    'SELECT s.id FROM shifts s
     JOIN deliveries d ON d.shift_id = s.id
     WHERE d.id = ? AND s.id = ? AND s.user_id = ? AND s.end_time IS NULL
     LIMIT 1'
);
$stmt->execute([$delivery_id, $shift_id, $user_id]);

if (!$stmt->fetch()) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Delete the delivery
$stmt = $pdo->prepare('DELETE FROM deliveries WHERE id = ?');
$stmt->execute([$delivery_id]);

// Re-sequence remaining deliveries so there are no gaps (1, 2, 3, ...)
$stmt = $pdo->prepare('SELECT id FROM deliveries WHERE shift_id = ? ORDER BY sequence ASC');
$stmt->execute([$shift_id]);
$remaining = $stmt->fetchAll(PDO::FETCH_COLUMN);

$update = $pdo->prepare('UPDATE deliveries SET sequence = ? WHERE id = ?');
foreach ($remaining as $i => $did) {
    $update->execute([$i + 1, $did]);
}

header('Location: /tracker/shift_view.php?id=' . $shift_id);
exit;
?>
