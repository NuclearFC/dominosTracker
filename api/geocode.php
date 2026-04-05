<?php
// ============================================================
// api/geocode.php — Server-side proxy for Nominatim geocoding
//
// Strategy:
//   1. Search with the full query as typed.
//   2. If the query starts with a house number, also run a
//      secondary search without it (street-only). This handles
//      addresses where OSM has the road but not the individual
//      house number mapped.
//   3. Merge both result sets, deduplicate by place_id, then
//      sort by distance from the store so nearby results always
//      rank above distant ones with the same street name.
//   4. Re-attach the queried house number to road-level results
//      so the display still reads "107 Belper Road".
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Sanitise — strip commas (Nominatim treats them as field separators)
$q = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $_GET['q'] ?? '')));
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query']);
    exit;
}

// Extract leading house number if present, e.g. "107 Belper Road" → "107" + "Belper Road"
$house_number = '';
$street_only  = '';
if (preg_match('/^(\d+[a-z]?)\s+(.+)/i', $q, $m)) {
    $house_number = $m[1];
    $street_only  = trim($m[2]);
}

// ---- Nominatim request helper --------------------------------
function nominatim_search($query) {
    $pad     = 0.3;
    $viewbox = implode(',', [
        STORE_LNG - $pad,
        STORE_LAT + $pad,
        STORE_LNG + $pad,
        STORE_LAT - $pad,
    ]);

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $query,
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
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['User-Agent: ForgemillTracker/1.0 (forgemill.co.uk)'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $ok   = !curl_error($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);

    if (!$ok) return [];
    return json_decode($resp, true) ?: [];
}

// ---- Run searches --------------------------------------------
$primary   = nominatim_search($q);
$secondary = $house_number ? nominatim_search($street_only) : [];

// Merge and deduplicate by place_id
$seen = [];
$all  = [];
foreach (array_merge($primary, $secondary) as $item) {
    $id = $item['place_id'] ?? null;
    if ($id && isset($seen[$id])) continue;
    if ($id) $seen[$id] = true;
    $all[] = $item;
}

if (empty($all)) {
    echo json_encode([]);
    exit;
}

// ---- Sort by distance from store -----------------------------
usort($all, function($a, $b) {
    $da = ($a['lat'] - STORE_LAT) ** 2 + ($a['lon'] - STORE_LNG) ** 2;
    $db = ($b['lat'] - STORE_LAT) ** 2 + ($b['lon'] - STORE_LNG) ** 2;
    return $da <=> $db;
});

// ---- Build response ------------------------------------------
$results = array_map(function($item) use ($house_number) {
    return [
        'lat'          => $item['lat'],
        'lng'          => $item['lon'],
        'display_name' => $item['display_name'],
        'address'      => $item['address'] ?? [],
        // Passed back so JS can prepend it onto road-level results
        'house_number' => $house_number,
    ];
}, array_slice($all, 0, 5));

echo json_encode($results);
?>
