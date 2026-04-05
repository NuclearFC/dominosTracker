<?php
// ============================================================
// dashboard.php — Overview: recent shifts and summary stats
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'config.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];

// ----------------------------------------------------------
// Fetch recent shifts (last 10)
// ----------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id, date, start_time, end_time, notes
     FROM shifts
     WHERE user_id = ?
     ORDER BY date DESC, start_time DESC
     LIMIT 10'
);
$stmt->execute([$user_id]);
$recent_shifts = $stmt->fetchAll();

// ----------------------------------------------------------
// Attach delivery counts, total tips, and total miles to each shift
// ----------------------------------------------------------
foreach ($recent_shifts as &$shift) {
    $stmt2 = $pdo->prepare(
        'SELECT sequence, lat, lng, tip_amount FROM deliveries WHERE shift_id = ? ORDER BY sequence'
    );
    $stmt2->execute([$shift['id']]);
    $deliveries = $stmt2->fetchAll();

    $shift['delivery_count'] = count($deliveries);
    $shift['total_tips']     = array_sum(array_column($deliveries, 'tip_amount'));
    $shift['total_miles']    = shift_total_miles($deliveries);
}
unset($shift); // Break the reference

// ----------------------------------------------------------
// All-time summary stats
// ----------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total_shifts FROM shifts WHERE user_id = ?'
);
$stmt->execute([$user_id]);
$total_shifts = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total_deliveries FROM deliveries d
     JOIN shifts s ON d.shift_id = s.id
     WHERE s.user_id = ?'
);
$stmt->execute([$user_id]);
$total_deliveries = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT SUM(d.tip_amount) FROM deliveries d
     JOIN shifts s ON d.shift_id = s.id
     WHERE s.user_id = ?'
);
$stmt->execute([$user_id]);
$total_tips = (float)$stmt->fetchColumn();

// Average deliveries per shift
$avg_deliveries = $total_shifts > 0 ? round($total_deliveries / $total_shifts, 1) : 0;

// Most frequent postcode
$stmt = $pdo->prepare(
    'SELECT d.postcode, COUNT(*) AS cnt
     FROM deliveries d
     JOIN shifts s ON d.shift_id = s.id
     WHERE s.user_id = ? AND d.postcode != ""
     GROUP BY d.postcode
     ORDER BY cnt DESC
     LIMIT 1'
);
$stmt->execute([$user_id]);
$top_postcode_row = $stmt->fetch();
$top_postcode = $top_postcode_row ? $top_postcode_row['postcode'] : '—';

// Average miles per shift (calculate across all shifts)
$stmt = $pdo->prepare(
    'SELECT id FROM shifts WHERE user_id = ?'
);
$stmt->execute([$user_id]);
$all_shift_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$total_miles_all = 0.0;
foreach ($all_shift_ids as $sid) {
    $stmt2 = $pdo->prepare(
        'SELECT lat, lng FROM deliveries WHERE shift_id = ? ORDER BY sequence'
    );
    $stmt2->execute([$sid]);
    $total_miles_all += shift_total_miles($stmt2->fetchAll());
}
$avg_miles = $total_shifts > 0 ? $total_miles_all / $total_shifts : 0.0;

// Check if there's an open (not yet closed) shift
$stmt = $pdo->prepare(
    'SELECT id FROM shifts WHERE user_id = ? AND end_time IS NULL ORDER BY date DESC, start_time DESC LIMIT 1'
);
$stmt->execute([$user_id]);
$open_shift = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/tracker/assets/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <span class="site-title"><?= SITE_NAME ?></span>
        <a href="/tracker/logout.php" class="btn btn-sm btn-ghost">Log out</a>
    </div>
</header>

<main class="container">

    <!-- Open shift banner -->
    <?php if ($open_shift): ?>
    <div class="alert alert-info">
        You have an open shift.
        <a href="/tracker/shift_view.php?id=<?= $open_shift['id'] ?>">View shift</a>
        &nbsp;|&nbsp;
        <a href="/tracker/delivery_add.php?shift_id=<?= $open_shift['id'] ?>">Add delivery</a>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="action-row">
        <a href="/tracker/shift_new.php" class="btn btn-primary">+ New Shift</a>
    </div>

    <!-- Stats grid -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $total_shifts ?></div>
            <div class="stat-label">Shifts</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_deliveries ?></div>
            <div class="stat-label">Deliveries</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= format_money($total_tips) ?></div>
            <div class="stat-label">Total tips</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $avg_deliveries ?></div>
            <div class="stat-label">Avg deliveries/shift</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= format_miles($avg_miles) ?></div>
            <div class="stat-label">Avg miles/shift</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= htmlspecialchars($top_postcode, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="stat-label">Top postcode</div>
        </div>
    </section>

    <!-- Recent shifts -->
    <section>
        <h2>Recent Shifts</h2>

        <?php if (empty($recent_shifts)): ?>
            <p class="muted">No shifts logged yet. Start one above!</p>
        <?php else: ?>
            <div class="shift-list">
                <?php foreach ($recent_shifts as $s): ?>
                <a href="/tracker/shift_view.php?id=<?= $s['id'] ?>" class="shift-card">
                    <div class="shift-date"><?= htmlspecialchars(date('D j M Y', strtotime($s['date'])), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="shift-meta">
                        <?= $s['start_time'] ? htmlspecialchars(substr($s['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') : '?' ?>
                        –
                        <?= $s['end_time'] ? htmlspecialchars(substr($s['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') : '<span class="badge badge-open">open</span>' ?>
                    </div>
                    <div class="shift-stats">
                        <span><?= $s['delivery_count'] ?> deliveries</span>
                        <span><?= format_money($s['total_tips']) ?> tips</span>
                        <span><?= format_miles($s['total_miles']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
