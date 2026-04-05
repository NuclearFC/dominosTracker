<?php
// ============================================================
// includes/helpers.php — Utility functions
// ============================================================

/**
 * Haversine formula — calculates the distance in miles between
 * two points on Earth given their latitude/longitude coordinates.
 *
 * @param float $lat1  Latitude of point A
 * @param float $lng1  Longitude of point A
 * @param float $lat2  Latitude of point B
 * @param float $lng2  Longitude of point B
 * @return float Distance in miles
 */
function haversine_miles(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earth_radius_miles = 3958.8;

    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);

    $a = sin($dlat / 2) * sin($dlat / 2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dlng / 2) * sin($dlng / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius_miles * $c;
}

/**
 * Calculates the total route distance for a shift:
 * store → delivery 1 → delivery 2 → ... → last delivery → store
 *
 * @param array $deliveries  Array of delivery rows, each with lat/lng
 * @return float Total distance in miles
 */
function shift_total_miles(array $deliveries): float {
    require_once __DIR__ . '/../config.php';

    if (empty($deliveries)) {
        return 0.0;
    }

    $total = 0.0;
    $prev_lat = STORE_LAT;
    $prev_lng = STORE_LNG;

    foreach ($deliveries as $d) {
        if ($d['lat'] && $d['lng']) {
            $total += haversine_miles($prev_lat, $prev_lng, (float)$d['lat'], (float)$d['lng']);
            $prev_lat = (float)$d['lat'];
            $prev_lng = (float)$d['lng'];
        }
    }

    // Return trip back to store from last delivery
    $total += haversine_miles($prev_lat, $prev_lng, STORE_LAT, STORE_LNG);

    return $total;
}

/**
 * Formats a decimal miles value for display, e.g. "3.4 mi"
 */
function format_miles(float $miles): string {
    return number_format($miles, 1) . ' mi';
}

/**
 * Formats a decimal money value for display, e.g. "£4.50"
 */
function format_money(float $amount): string {
    return '£' . number_format($amount, 2);
}

/**
 * Formats a shift duration given start and end TIME strings.
 * Returns something like "4h 30m" or "—" if times are missing.
 */
function format_duration(?string $start, ?string $end): string {
    if (!$start || !$end) return '—';

    $s = strtotime($start);
    $e = strtotime($end);

    if ($e <= $s) return '—';

    $diff = $e - $s;
    $hours = intdiv($diff, 3600);
    $mins  = intdiv($diff % 3600, 60);

    if ($hours > 0) {
        return "{$hours}h {$mins}m";
    }
    return "{$mins}m";
}

/**
 * Safe HTML output — always use this when printing user-supplied
 * data to prevent XSS attacks.
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
