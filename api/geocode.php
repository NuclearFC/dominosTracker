<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Accepts: GET ?q=42+Hartington+Street
// Returns: JSON array of up to 5 results with structured address details.
//          STORE_TOWN is appended to the query server-side so the JS
//          does not need to know it.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing search query']);
    exit;
}

// Build a ~20 mile viewbox around the store to bias results geographically.
// We use bounded=0 (soft bias) so addresses just outside the box still show.
// This is better than appending a town name, which breaks addresses in nearby
// towns (e.g. searching for a Belper address when the store is in Belper but
// STORE_TOWN was set to Derby).
$pad = 0.3; // ~15 miles in each direction
$viewbox = implode(',', [
    STORE_LNG - $pad, // left  (west)
    STORE_LAT + $pad, // top   (north)
    STORE_LNG + $pad, // right (east)
    STORE_LAT - $pad, // bottom (south)
]);

$url = 'https://nominatim.openstreetmap.org/search?'
    . http_build_query([
        'q'              => $q,
        'format'         => 'json',
        'limit'          => '5',
        'countrycodes'   => 'gb',
        'addressdetails' => '1',
        'viewbox'        => $viewbox,
        'bounded'        => '0',
        'dedupe'         => '0',
    ]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['User-Agent: ForgemillTracker/1.0 (forgemill.co.uk)'],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err || $http_code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding request failed']);
    exit;
}

$data = json_decode($response, true);

if (empty($data)) {
    echo json_encode([]);
    exit;
}

$results = array_map(function($item) {
    return [
        'lat'          => $item['lat'],
        'lng'          => $item['lon'],
        'display_name' => $item['display_name'],
        'address'      => $item['address'] ?? [],
    ];
}, $data);

echo json_encode($results);
?>
