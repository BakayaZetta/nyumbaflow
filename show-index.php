<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Contents of index.php (first 50 lines):</h3>";
echo "<pre>";

$lines = file('index.php');
$lineCount = 0;
foreach ($lines as $line) {
    echo htmlspecialchars($line);
    $lineCount++;
    if ($lineCount >= 50) {
        echo "\n... (truncated after 50 lines)";
        break;
    }
}

echo "</pre>";

echo "<h3>Also check for composer.json:</h3>";
if (file_exists('composer.json')) {
    echo "composer.json EXISTS<br>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents('composer.json'));
    echo "</pre>";
} else {
    echo "composer.json does NOT exist<br>";
}

echo "<h3>Files in this directory:</h3>";
$files = scandir('.');
echo "<ul>";
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li>" . htmlspecialchars($file) . "</li>";
    }
}
echo "</ul>";
?>