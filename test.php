<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>1. PHP is working</h2>";

// Test config loads
require_once 'config.php';
echo "<h2>2. config.php loaded OK</h2>";

// Test DB connection
require_once 'includes/db.php';
try {
    $pdo = db();
    echo "<h2>3. Database connected OK</h2>";
    // Check tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . implode(', ', $tables) . "</p>";
} catch (Exception $e) {
    echo "<h2>3. DATABASE ERROR:</h2><pre>" . $e->getMessage() . "</pre>";
}
?>
