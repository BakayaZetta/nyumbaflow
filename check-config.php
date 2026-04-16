<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Checking config.php</h3>";

// Check if config.php exists
if (file_exists('config.php')) {
    echo "✅ config.php found<br><br>";
    
    // Show contents of config.php
    echo "<b>Contents of config.php:</b><br>";
    echo "<pre>" . htmlspecialchars(file_get_contents('config.php')) . "</pre><br>";
    
    // Try to include it
    echo "<b>Attempting to load config.php:</b><br>";
    include 'config.php';
    
    // Check which constants are defined
    echo "<br><b>Checking defined constants:</b><br>";
    $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DEBUG_MODE'];
    foreach ($constants as $const) {
        if (defined($const)) {
            if ($const == 'DB_PASS') {
                echo "✅ " . $const . " = [HIDDEN]<br>";
            } else {
                echo "✅ " . $const . " = " . constant($const) . "<br>";
            }
        } else {
            echo "❌ " . $const . " is NOT defined<br>";
        }
    }
    
} else {
    echo "❌ config.php NOT found!<br>";
}
?>