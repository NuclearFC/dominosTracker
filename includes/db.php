<?php
// ============================================================
// includes/db.php — PDO database connection (singleton)
//
// Usage: $pdo = db();
// Returns the same PDO instance every time (only connects once).
// ============================================================

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null; // Static means this persists between calls

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return rows as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                   // Use real prepared statements
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
?>
