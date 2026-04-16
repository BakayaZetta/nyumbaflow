<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Debug script started<br>";

// Check if index.php exists
if (file_exists('index.php')) {
    echo "Step 2: index.php found<br>";
} else {
    echo "Step 2: ERROR - index.php not found<br>";
}

// Try to include index.php
echo "Step 3: Attempting to run index.php...<br>";

try {
    include 'index.php';
} catch (Exception $e) {
    echo "Caught exception: " . $e->getMessage() . "<br>";
} catch (Error $e) {
    echo "Caught error: " . $e->getMessage() . "<br>";
}

echo "<br>Step 4: Debug script finished";
?>