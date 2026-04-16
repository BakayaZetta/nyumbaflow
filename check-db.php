<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Checking Database Configuration</h3>";

// Check if db_config.php exists
if (file_exists('db_config.php')) {
    echo "✅ db_config.php found<br><br>";
    
    // Show what's in db_config.php (without passwords fully visible)
    $config_content = file_get_contents('db_config.php');
    echo "<b>Contents of db_config.php:</b><br>";
    echo "<pre>" . htmlspecialchars($config_content) . "</pre><br>";
    
    // Now try to include it and test the connection
    include 'db_config.php';
    
    // Check what variables were defined
    echo "<b>Variables found:</b><br>";
    if (isset($pdo)) {
        echo "✅ \$pdo variable exists<br>";
        if ($pdo) {
            echo "✅ PDO connection object exists<br>";
        } else {
            echo "❌ \$pdo is null or false<br>";
        }
    } else {
        echo "❌ \$pdo variable NOT found<br>";
    }
    
    if (isset($conn)) {
        echo "✅ \$conn variable exists<br>";
    }
    
    if (isset($db)) {
        echo "✅ \$db variable exists<br>";
    }
    
} else {
    echo "❌ db_config.php NOT found!<br>";
}
?>