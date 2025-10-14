<?php
// Simplest possible test
echo "TEST 1: This file is loading<br>";

echo "TEST 2: Checking if includes directory exists...<br>";
if (is_dir(__DIR__ . '/includes')) {
    echo "✅ includes/ directory exists<br>";

    echo "TEST 3: Checking if OperatorAuth.php exists...<br>";
    if (file_exists(__DIR__ . '/includes/OperatorAuth.php')) {
        echo "✅ OperatorAuth.php file exists<br>";
        echo "File size: " . filesize(__DIR__ . '/includes/OperatorAuth.php') . " bytes<br>";
    } else {
        echo "❌ OperatorAuth.php NOT found<br>";
    }
} else {
    echo "❌ includes/ directory NOT found<br>";
    echo "Directory contents of " . __DIR__ . ":<br>";
    echo "<pre>" . print_r(scandir(__DIR__), true) . "</pre>";
}

echo "<br>TEST 4: Checking config.php...<br>";
if (file_exists(__DIR__ . '/../config.php')) {
    echo "✅ config.php exists<br>";
} else {
    echo "❌ config.php NOT found<br>";
}

echo "<br>If you see this, the PHP file is working!";
?>
