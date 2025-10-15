<?php
header('Content-Type: text/plain');

echo "=== DIAGNOSTIC INFO ===\n\n";

$file = __DIR__ . '/admin-dashboard.php';

echo "File path: $file\n";
echo "File exists: " . (file_exists($file) ? "YES" : "NO") . "\n";

if (file_exists($file)) {
    echo "File size: " . filesize($file) . " bytes\n";
    echo "Is readable: " . (is_readable($file) ? "YES" : "NO") . "\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
    echo "\nFirst 500 bytes:\n";
    echo substr(file_get_contents($file), 0, 500) . "\n";
}

echo "\n\n=== PHP INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Output buffering: " . (ob_get_level() > 0 ? "ON (level " . ob_get_level() . ")" : "OFF") . "\n";
echo "Display errors: " . ini_get('display_errors') . "\n";
echo "Error reporting: " . error_reporting() . "\n";

echo "\n\n=== TEST SIMPLE OUTPUT ===\n";
echo "If you see this, PHP output works!\n";
