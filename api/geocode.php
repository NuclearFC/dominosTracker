<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Accepts: ?address=42+Hartington+Street&postcode=DE1+3GU
// Returns: JSON array of up to 5 results for the user to pick from
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
// Helper: make a Nominatim request, return decoded array
// ----------------------------------------------------------
function nominatim_request(array $params): array {
    $url = 'https://nominatim.openstreetmap.org/search?'
        . http_build_query(array_merge($params, [
            'format'       => 'json',
            'limit'        => '5',
            'countrycodes' => 'gb',
        ]));

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

    if ($err || $http_code !== 200) return [];
    $data = json_decode($response, true);
    return is_array($data) ? $data : [];
}

// ----------------------------------------------------------
// Attempt 1: structured street + postcode
// ----------------------------------------------------------
$results = [];

if ($address !== '' && $postcode !== '') {
    $results = nominatim_request([
        'street'     => $address,
        'postalcode' => $postcode,
    ]);
}

// ----------------------------------------------------------
// Attempt 2: postcode only
// ----------------------------------------------------------
if (empty($results) && $postcode !== '') {
    $results = nominatim_request(['postalcode' => $postcode]);
}

// ----------------------------------------------------------
// Attempt 3: free-text fallback
// ----------------------------------------------------------
if (empty($results)) {
    $results = nominatim_request(['q' => trim("$address $postcode")]);
}

if (empty($results)) {
    echo json_encode(['error' => 'No results found — try adjusting the address or postcode']);
    exit;
}

// Return all results so the user can pick the right one
$output = array_map(function($r) {
    return [
        'lat'          => $r['lat'],
        'lng'          => $r['lon'],
        'display_name' => $r['display_name'],
    ];
}, $results);

echo json_encode(['results' => $output]);
?>
