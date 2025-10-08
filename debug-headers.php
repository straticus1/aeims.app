<?php
// Debug script to check headers reaching the application
header('Content-Type: application/json');

$debug_info = [
    'host_header' => $_SERVER['HTTP_HOST'] ?? 'NOT_SET',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT_SET',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'NOT_SET',
    'all_headers' => []
];

foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $debug_info['all_headers'][$key] = $value;
    }
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>