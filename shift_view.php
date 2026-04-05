<?php
// ============================================================
// shift_view.php — Single shift: map, delivery list, stats
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'config.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];

$shift_id = (int)($_GET['id'] ?? 0);
if ($shift_id <= 0) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Load the shift (verify it belongs to this user)
$stmt = $pdo->prepare(
    'SELECT * FROM shifts WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$shift_id, $user_id]);
$shift = $stmt->fetch();

if (!$shift) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Load deliveries in order
$stmt = $pdo->prepare(
    'SELECT * FROM deliveries WHERE shift_id = ? ORDER BY sequence ASC'
);
$stmt->execute([$shift_id]);
$deliveries = $stmt->fetchAll();

// Calculate stats
$total_miles     = shift_total_miles($deliveries);
$total_tips      = array_sum(array_column($deliveries, 'tip_amount'));
$delivery_count  = count($deliveries);
$avg_miles       = $delivery_count > 0 ? $total_miles / $delivery_count : 0.0;
$is_open         = ($shift['end_time'] === null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift <?= htmlspecialchars(date('d M Y', strtotime($shift['date'])), ENT_QUOTES, 'UTF-8') ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/tracker/assets/style.css">
    <!-- Leaflet CSS from CDN -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="/tracker/dashboard.php" class="back-link">← Dashboard</a>
        <span class="site-title"><?= SITE_NAME ?></span>
    </div>
</header>

<main>
    <!-- Shift heading -->
    <div class="container">
        <div class="shift-header">
            <div>
                <h1><?= htmlspecialchars(date('l j F Y', strtotime($shift['date'])), ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="shift-times">
                    <?= $shift['start_time'] ? htmlspecialchars(substr($shift['start_time'], 0, 5), ENT_QUOTES, 'UTF-8') : '?' ?>
                    –
                    <?php if ($is_open): ?>
                        <span class="badge badge-open">open</span>
                    <?php else: ?>
                        <?= htmlspecialchars(substr($shift['end_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?>
                        (<?= format_duration($shift['start_time'], $shift['end_time']) ?>)
                    <?php endif; ?>
                </p>
                <?php if ($shift['notes']): ?>
                    <p class="shift-notes"><?= htmlspecialchars($shift['notes'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <?php if ($is_open): ?>
            <div class="shift-actions">
                <a href="/tracker/delivery_add.php?shift_id=<?= $shift['id'] ?>" class="btn btn-primary">+ Add Delivery</a>
                <button class="btn btn-secondary" onclick="document.getElementById('close-shift-form').style.display='block'; this.style.display='none'">
                    Close Shift
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Close shift form (hidden by default) -->
        <?php if ($is_open): ?>
        <div id="close-shift-form" style="display:none" class="form-card form-card-inline">
            <form method="post" action="/tracker/shift_close.php">
                <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                <div class="form-group">
                    <label for="end_time">End time</label>
                    <input type="time" id="end_time" name="end_time" value="<?= date('H:i') ?>">
                </div>
                <div class="form-row">
                    <button type="submit" class="btn btn-primary">Save & Close</button>
                    <button type="button" class="btn btn-ghost"
                            onclick="document.getElementById('close-shift-form').style.display='none'; document.querySelector('.btn-secondary').style.display='inline-block'">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid stats-grid-sm">
            <div class="stat-card">
                <div class="stat-value"><?= $delivery_count ?></div>
                <div class="stat-label">Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= format_money($total_tips) ?></div>
                <div class="stat-label">Tips</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= format_miles($total_miles) ?></div>
                <div class="stat-label">Total miles</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= format_miles($avg_miles) ?></div>
                <div class="stat-label">Avg/delivery</div>
            </div>
        </div>
    </div>

    <!-- Full-width map -->
    <div id="shift-map"></div>

    <!-- Delivery list -->
    <div class="container">
        <h2>Deliveries</h2>

        <?php if (empty($deliveries)): ?>
            <p class="muted">No deliveries yet.
                <?php if ($is_open): ?>
                    <a href="/tracker/delivery_add.php?shift_id=<?= $shift['id'] ?>">Add the first one.</a>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <ol class="delivery-list">
                <?php foreach ($deliveries as $d): ?>
                <li class="delivery-item">
                    <div class="delivery-info">
                        <strong><?= htmlspecialchars($d['address'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="postcode"><?= htmlspecialchars($d['postcode'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($d['tip_amount'] > 0): ?>
                            <span class="tip-badge"><?= format_money($d['tip_amount']) ?> tip</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_open): ?>
                    <form method="post" action="/tracker/delivery_delete.php"
                          onsubmit="return confirm('Delete this delivery?')">
                        <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                        <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</main>

<!-- Leaflet JS from CDN -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/tracker/assets/app.js"></script>
<script>
// Pass PHP data to JavaScript
var SHIFT_MAP_DATA = {
    store: { lat: <?= STORE_LAT ?>, lng: <?= STORE_LNG ?>, name: <?= json_encode(STORE_NAME) ?> },
    deliveries: <?= json_encode(array_map(function($d) {
        return [
            'lat'     => $d['lat'] ? (float)$d['lat'] : null,
            'lng'     => $d['lng'] ? (float)$d['lng'] : null,
            'address' => $d['address'],
            'postcode'=> $d['postcode'],
            'tip'     => (float)$d['tip_amount'],
            'seq'     => (int)$d['sequence'],
        ];
    }, $deliveries)) ?>
};
initShiftMap('shift-map', SHIFT_MAP_DATA);
</script>

</body>
</html>
