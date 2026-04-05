<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Accepts: ?q=42+Hartington+Street+Belper
// Returns: JSON array of up to 5 results with structured address details
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

// Append the store town so results are biased to the right area
$query = $q . ', ' . STORE_TOWN;

$url = 'https://nominatim.openstreetmap.org/search?'
    . http_build_query([
        'q'              => $query,
        'format'         => 'json',
        'limit'          => '6',
        'countrycodes'   => 'gb',
        'addressdetails' => '1',   // Returns structured address so we can extract postcode
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
$err       = curl_error($ch);
curl_close($ch);

if ($err || $http_code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding request failed']);
    exit;
}

$data = json_decode($response, true);

if (empty($data)) {
    echo json_encode(['results' => []]);
    exit;
}

// Build a clean result for each match
$results = [];
foreach ($data as $r) {
    $addr = $r['address'] ?? [];

    // Build a short street address from the structured parts
    $parts = array_filter([
        $addr['house_number'] ?? '',
        $addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? '',
    ]);
    $street = implode(' ', $parts);

    // Postcode direct from address details
    $postcode = strtoupper(trim($addr['postcode'] ?? ''));

    // Fallback label if we couldn't build a street string
    $label = $street ?: $r['display_name'];

    $results[] = [
        'label'    => $label,           // Short name shown in dropdown
        'postcode' => $postcode,        // Auto-fills the postcode field
        'lat'      => $r['lat'],
        'lng'      => $r['lon'],
        'full'     => $r['display_name'], // Full address shown below the label
    ];
}

echo json_encode(['results' => $results]);
?>
