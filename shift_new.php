<?php
// ============================================================
// shift_new.php — Start a new shift
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'config.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date       = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    // Basic validation
    if ($date === '') {
        $error = 'Please enter a date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = 'Invalid date format.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO shifts (user_id, date, start_time, notes) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $user_id,
            $date,
            $start_time ?: null,
            $notes ?: null,
        ]);

        $new_id = $pdo->lastInsertId();
        header('Location: /tracker/shift_view.php?id=' . $new_id);
        exit;
    }
}

// Default date to today, time to now
$default_date = date('Y-m-d');
$default_time = date('H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Shift — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/tracker/assets/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="/tracker/dashboard.php" class="back-link">← Dashboard</a>
        <span class="site-title"><?= SITE_NAME ?></span>
    </div>
</header>

<main class="container">
    <h1>New Shift</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/tracker/shift_new.php" class="form-card">
        <div class="form-group">
            <label for="date">Date</label>
            <input type="date" id="date" name="date"
                   value="<?= htmlspecialchars($_POST['date'] ?? $default_date, ENT_QUOTES, 'UTF-8') ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="start_time">Start time <span class="label-hint">(optional)</span></label>
            <input type="time" id="start_time" name="start_time"
                   value="<?= htmlspecialchars($_POST['start_time'] ?? $default_time, ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="notes">Notes <span class="label-hint">(optional)</span></label>
            <textarea id="notes" name="notes" rows="3"
                      placeholder="e.g. busy Friday night"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Start Shift</button>
    </form>
</main>

</body>
</html>
