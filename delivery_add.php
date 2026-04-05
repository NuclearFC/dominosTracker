<?php
// ============================================================
// delivery_add.php — Add a delivery to an open shift
// ============================================================

require 'includes/auth.php';
require_once 'includes/db.php';
require_once 'config.php';

$pdo     = db();
$user_id = $_SESSION['user_id'];

$shift_id = (int)($_GET['shift_id'] ?? $_POST['shift_id'] ?? 0);
if ($shift_id <= 0) {
    header('Location: /tracker/dashboard.php');
    exit;
}

// Verify shift belongs to this user and is still open
$stmt = $pdo->prepare(
    'SELECT id, date FROM shifts WHERE id = ? AND user_id = ? AND end_time IS NULL LIMIT 1'
);
$stmt->execute([$shift_id, $user_id]);
$shift = $stmt->fetch();

if (!$shift) {
    // Either doesn't exist, doesn't belong to user, or is already closed
    header('Location: /tracker/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address    = trim($_POST['address'] ?? '');
    $postcode   = strtoupper(trim($_POST['postcode'] ?? ''));
    $lat        = $_POST['lat'] ?? '';
    $lng        = $_POST['lng'] ?? '';
    $tip_amount = trim($_POST['tip_amount'] ?? '');

    if ($address === '') {
        $error = 'Please enter an address.';
    } elseif ($postcode === '') {
        $error = 'Please enter a postcode.';
    } else {
        // Get the next sequence number
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sequence), 0) + 1 FROM deliveries WHERE shift_id = ?');
        $stmt->execute([$shift_id]);
        $next_seq = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO deliveries (shift_id, sequence, address, postcode, lat, lng, tip_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $shift_id,
            $next_seq,
            $address,
            $postcode,
            ($lat !== '' && is_numeric($lat)) ? (float)$lat : null,
            ($lng !== '' && is_numeric($lng)) ? (float)$lng : null,
            ($tip_amount !== '' && is_numeric($tip_amount)) ? (float)$tip_amount : null,
        ]);

        header('Location: /tracker/shift_view.php?id=' . $shift_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Delivery — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/tracker/assets/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="/tracker/shift_view.php?id=<?= $shift_id ?>" class="back-link">← Shift</a>
        <span class="site-title"><?= SITE_NAME ?></span>
    </div>
</header>

<main class="container">
    <h1>Add Delivery</h1>
    <p class="muted">Shift: <?= htmlspecialchars(date('D j M', strtotime($shift['date'])), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/tracker/delivery_add.php" class="form-card" id="delivery-form">
        <input type="hidden" name="shift_id" value="<?= $shift_id ?>">
        <!-- Hidden lat/lng — filled in by JS after geocoding -->
        <input type="hidden" name="lat" id="lat" value="">
        <input type="hidden" name="lng" id="lng" value="">

        <div class="form-group">
            <label for="address">Street address</label>
            <input type="text" id="address" name="address"
                   value="<?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="e.g. 42 Hartington Street"
                   autocomplete="off" required>
        </div>

        <div class="form-group">
            <label for="postcode">Postcode</label>
            <input type="text" id="postcode" name="postcode"
                   value="<?= htmlspecialchars($_POST['postcode'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="e.g. DE1 3GU"
                   autocomplete="off" required
                   style="text-transform: uppercase">
        </div>

        <!-- Find on map button -->
        <button type="button" class="btn btn-secondary btn-full" id="geocode-btn">
            Find on map
        </button>

        <div id="geocode-status" class="geocode-status" style="display:none"></div>

        <!-- Preview map (shown after geocoding) -->
        <div id="preview-map" style="display:none"></div>

        <div class="form-group" style="margin-top: 1rem">
            <label for="tip_amount">Tip <span class="label-hint">(optional, in £)</span></label>
            <input type="number" id="tip_amount" name="tip_amount"
                   value="<?= htmlspecialchars($_POST['tip_amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="0.00" step="0.01" min="0">
        </div>

        <button type="submit" class="btn btn-primary btn-full">Save Delivery</button>
    </form>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="/tracker/assets/app.js"></script>
<script>
var STORE = { lat: <?= STORE_LAT ?>, lng: <?= STORE_LNG ?> };
var STORE_TOWN = <?= json_encode(STORE_TOWN) ?>;
initDeliveryForm('geocode-btn', 'address', 'postcode', 'lat', 'lng', 'preview-map', 'geocode-status', STORE, STORE_TOWN);
</script>

</body>
</html>
