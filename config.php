<?php
// ============================================================
// config.php — Database credentials and app-wide constants
// IMPORTANT: Block direct browser access to this file via
// .htaccess (already configured). Never commit real passwords.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'fd10forg_deliveryTracker');
define('DB_USER', 'fd10forg_sam');
define('DB_PASS', '^pOcK3ars12345');

// ----------------------------------------------------------
// Store location — find your exact branch on Google Maps,
// right-click the pin and copy the coordinates.
// ----------------------------------------------------------


define('STORE_LAT', 53.023493);
define('STORE_LNG', -1.480351);
define('STORE_NAME', "Domino's Belper");

// OS Places API key — get a free one at https://osdatahub.os.uk/
// Free tier: 250,000 lookups/month (more than enough for a delivery tracker)
define('OSPLACES_API_KEY', '');

define('SITE_NAME', 'ForgemillTracker');
?>
