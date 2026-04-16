<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Testing Local Database Connection</h3>";

// Load config
include 'config.php';

echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";
echo "DEBUG_MODE: " . (DEBUG_MODE ? "ON" : "OFF") . "<br><br>";

// Try to connect
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!<br><br>";
    
    // Show tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "Tables in database:<br>";
    foreach ($tables as $table) {
        echo " - " . implode('', $table) . "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}
?>