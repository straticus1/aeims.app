<?php
/**
 * Server-Sent Events (SSE) Notification Stream
 * Real-time notifications for logged-in customers
 */

session_start();

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once __DIR__ . '/../../services/NotificationManager.php';

$notificationManager = new \AEIMS\Services\NotificationManager();
$customerId = $_SESSION['customer_id'];

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent script timeout
set_time_limit(0);
ini_set('max_execution_time', '0');

// Track last sent notification timestamp
$lastTimestamp = time();

// Send initial connection message
echo "data: " . json_encode(['type' => 'connected', 'message' => 'Notification stream connected']) . "\n\n";
flush();

// Keep connection alive and send notifications
while (true) {
    // Check if connection is still alive
    if (connection_aborted()) {
        break;
    }

    // Get unread notifications
    $notifications = $notificationManager->getUnreadNotifications($customerId);

    // Send new notifications
    foreach ($notifications as $notification) {
        if ($notification['timestamp'] > $lastTimestamp) {
            $data = [
                'notification_id' => $notification['notification_id'],
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'link' => $notification['link'],
                'timestamp' => $notification['created_at']
            ];

            echo "data: " . json_encode($data) . "\n\n";
            flush();

            $lastTimestamp = $notification['timestamp'];
        }
    }

    // Send heartbeat every 30 seconds
    echo ": heartbeat\n\n";
    flush();

    // Wait 2 seconds before next poll
    sleep(2);
}
