<?php
// ============================================================
// api/geocode.php — UK address search proxy
//
// Primary:  OS Places API (Ordnance Survey) — authoritative UK
//           address database, every house, exact coordinates.
//           Requires a free API key in config.php.
//
// Fallback: Nominatim (OpenStreetMap) — used if no OS key is
//           configured. Less complete for UK house numbers.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$q = trim(preg_replace('/\s+/', ' ', str_replace(',', ' ', $_GET['q'] ?? '')));
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query']);
    exit;
}

// ============================================================
// OS Places API
// ============================================================
if (defined('OSPLACES_API_KEY') && OSPLACES_API_KEY !== '') {

    $url = 'https://api.os.uk/search/places/v1/find?' . http_build_query([
        'query'      => $q,
        'key'        => OSPLACES_API_KEY,
        'maxresults' => 6,
        'dataset'    => 'DPA',   // Delivery Point Address — most accurate for houses
        'srs'        => 'WGS84', // Return lat/lng directly
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['User-Agent: ForgemillTracker/1.0 (forgemill.co.uk)'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp     = curl_exec($ch);
    $http_code= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if (!$curl_err && $http_code === 200) {
        $data = json_decode($resp, true);
        $hits = $data['results'] ?? [];

        if (!empty($hits)) {
            $results = array_map(function($hit) {
                $dpa = $hit['DPA'];

                // Build a readable street line
                $parts = array_filter([
                    $dpa['SUB_BUILDING_NAME']  ?? '',
                    $dpa['BUILDING_NAME']       ?? '',
                    $dpa['BUILDING_NUMBER']     ?? '',
                    $dpa['THOROUGHFARE_NAME']   ?? $dpa['DEPENDENT_THOROUGHFARE_NAME'] ?? '',
                ]);
                $street = implode(' ', $parts) ?: ($dpa['ADDRESS'] ?? '');

                // Locality line
                $loc_parts = array_filter([
                    $dpa['DEPENDENT_LOCALITY'] ?? '',
                    $dpa['POST_TOWN']          ?? '',
                    $dpa['POSTCODE']           ?? '',
                ]);

                return [
                    'lat'          => (float)($dpa['LAT'] ?? 0),
                    'lng'          => (float)($dpa['LNG'] ?? 0),
                    'display_name' => $dpa['ADDRESS'] ?? '',
                    'address'      => [
                        'house_number' => $dpa['BUILDING_NUMBER'] ?? '',
                        'road'         => $dpa['THOROUGHFARE_NAME'] ?? '',
                        'suburb'       => $dpa['DEPENDENT_LOCALITY'] ?? '',
                        'city'         => $dpa['POST_TOWN'] ?? '',
                        'postcode'     => $dpa['POSTCODE'] ?? '',
                    ],
                    'house_number' => '',
                ];
            }, $hits);

            echo json_encode(array_values($results));
            exit;
        }
    }
    // Fall through to Nominatim if OS Places returned nothing or errored
}

// ============================================================
// Nominatim fallback
// ============================================================
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

// Extract house number for fallback search
$house_number = '';
$street_only  = '';
if (preg_match('/^(\d+[a-z]?)\s+(.+)/i', $q, $m)) {
    $house_number = $m[1];
    $street_only  = trim($m[2]);
}

$all = nominatim_search($q);
if ($house_number) {
    $all = array_merge($all, nominatim_search($street_only));
}

// Deduplicate by place_id
$seen = [];
$deduped = [];
foreach ($all as $item) {
    $id = $item['place_id'] ?? null;
    if ($id && isset($seen[$id])) continue;
    if ($id) $seen[$id] = true;
    $deduped[] = $item;
}

if (empty($deduped)) {
    echo json_encode([]);
    exit;
}

// Sort by distance from store
usort($deduped, function($a, $b) {
    $da = ($a['lat'] - STORE_LAT) ** 2 + ($a['lon'] - STORE_LNG) ** 2;
    $db = ($b['lat'] - STORE_LAT) ** 2 + ($b['lon'] - STORE_LNG) ** 2;
    return $da <=> $db;
});

$results = array_map(function($item) use ($house_number) {
    return [
        'lat'          => $item['lat'],
        'lng'          => $item['lon'],
        'display_name' => $item['display_name'],
        'address'      => $item['address'] ?? [],
        'house_number' => $house_number,
    ];
}, array_slice($deduped, 0, 5));

echo json_encode($results);
?>
