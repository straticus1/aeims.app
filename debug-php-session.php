<?php
// Initialize session using SecurityManager to ensure correct session name
require_once __DIR__ . '/includes/SecurityManager.php';
$security = SecurityManager::getInstance();
$security->initializeSecureSession();

header('Content-Type: text/plain');

echo "=== PHP Session Configuration ===\n";
echo "session.save_handler: " . ini_get('session.save_handler') . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n";
echo "session.name: " . ini_get('session.name') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "\n=== Session Files ===\n";

$session_path = ini_get('session.save_path') ?: '/tmp';
echo "Looking in: $session_path\n";

if (is_dir($session_path)) {
    $files = scandir($session_path);
    $sess_files = array_filter($files, function($f) { return strpos($f, 'sess_') === 0; });
    echo "Session files found: " . count($sess_files) . "\n";
    foreach (array_slice($sess_files, 0, 10) as $file) {
        $full_path = $session_path . '/' . $file;
        echo "  - $file (" . filesize($full_path) . " bytes, modified: " . date('Y-m-d H:i:s', filemtime($full_path)) . ")\n";
        // Show first 200 chars of content
        echo "    Content: " . substr(file_get_contents($full_path), 0, 200) . "\n";
    }
}

echo "\n=== Cookies Received ===\n";
print_r($_COOKIE);

echo "\n=== Session Data ===\n";
print_r($_SESSION);
