<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Why a proxy? Browsers can mangle User-Agent headers, and
// Nominatim requires a proper descriptive User-Agent.
// All geocoding must go through here, never direct from JS.
//
// Usage: GET /tracker/api/geocode.php?q=42+Hartington+Street+Derby
// Returns: JSON with lat/lng, or an error message.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = trim($_GET['q'] ?? '');
if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

// Build the Nominatim URL
$url = 'https://nominatim.openstreetmap.org/search?'
    . http_build_query([
        'q'            => $query,
        'format'       => 'json',
        'limit'        => '1',
        'countrycodes' => 'gb',
    ]);

// Make the request using cURL (available on all cPanel hosts)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        // Nominatim requires a real, descriptive User-Agent
        'User-Agent: ForgemillTracker/1.0 (forgemill.co.uk)',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding request failed']);
    exit;
}

if ($http_code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Geocoding service returned error ' . $http_code]);
    exit;
}

$data = json_decode($response, true);

if (empty($data)) {
    echo json_encode(['error' => 'Address not found']);
    exit;
}

// Return just what we need
$result = $data[0];
echo json_encode([
    'lat'         => $result['lat'],
    'lng'         => $result['lon'],
    'display_name'=> $result['display_name'],
]);
?>
