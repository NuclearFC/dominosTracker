<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Accepts: ?address=42+Hartington+Street&postcode=DE1+3GU
//
// Strategy:
//   1. Try structured query (street + postcode) — most precise
//   2. Fall back to postcode only — catches edge cases
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$address  = trim($_GET['address'] ?? '');
$postcode = trim($_GET['postcode'] ?? '');

if ($address === '' && $postcode === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter an address or postcode']);
    exit;
}

// ----------------------------------------------------------
// Helper: make a single Nominatim request and return the
// decoded JSON array (may be empty if nothing found).
// ----------------------------------------------------------
function nominatim_request(array $params): array {
    $url = 'https://nominatim.openstreetmap.org/search?'
        . http_build_query(array_merge($params, [
            'format'       => 'json',
            'limit'        => '1',
            'countrycodes' => 'gb',
            'addressdetails' => '0',
        ]));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ForgemillTracker/1.0 (forgemill.co.uk)',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err       = curl_error($ch);
    curl_close($ch);

    if ($err || $http_code !== 200) return [];

    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

// ----------------------------------------------------------
// Attempt 1: structured query — street + postcode
// Nominatim handles these much better when separated out.
// ----------------------------------------------------------
$result = [];

if ($address !== '' && $postcode !== '') {
    $data = nominatim_request([
        'street'     => $address,
        'postalcode' => $postcode,
    ]);
    if (!empty($data)) $result = $data[0];
}

// ----------------------------------------------------------
// Attempt 2: postcode only — reliable fallback for UK
// ----------------------------------------------------------
if (empty($result) && $postcode !== '') {
    $data = nominatim_request(['postalcode' => $postcode]);
    if (!empty($data)) $result = $data[0];
}

// ----------------------------------------------------------
// Attempt 3: free-text fallback using whatever we have
// ----------------------------------------------------------
if (empty($result)) {
    $q = trim("$address $postcode");
    $data = nominatim_request(['q' => $q]);
    if (!empty($data)) $result = $data[0];
}

if (empty($result)) {
    echo json_encode(['error' => 'Address not found — try adjusting the street name or postcode']);
    exit;
}

echo json_encode([
    'lat'          => $result['lat'],
    'lng'          => $result['lon'],
    'display_name' => $result['display_name'],
]);
?>
