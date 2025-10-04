<?php
/**
 * AEIMS Support Ticket Handler
 * Processes support ticket and emergency support form submissions
 */

session_start();
require_once 'auth_functions.php';

$config = include 'config.php';
$response = ['success' => false, 'message' => '', 'ticket_id' => null];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'submit_ticket':
                $response = handleTicketSubmission($_POST);
                break;

            case 'submit_emergency':
                $response = handleEmergencySubmission($_POST);
                break;

            default:
                throw new Exception('Invalid action specified');
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
}

// For AJAX requests, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// For regular form submissions, redirect back with message
if (!empty($response['message'])) {
    $message = urlencode($response['message']);
    $type = $response['success'] ? 'success' : 'error';
    header("Location: support.php?message={$message}&type={$type}");
    exit;
}

/**
 * Handle regular support ticket submission
 */
function handleTicketSubmission($data) {
    // Validate required fields
    $required = ['name', 'email', 'priority', 'category', 'subject', 'description'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Generate ticket ID
    $ticket_id = 'AEIMS-' . strtoupper(substr(uniqid(), -8));

    // Prepare ticket data
    $ticket = [
        'id' => $ticket_id,
        'name' => sanitize($data['name']),
        'email' => sanitize($data['email']),
        'company' => sanitize($data['company'] ?? ''),
        'priority' => sanitize($data['priority']),
        'category' => sanitize($data['category']),
        'subject' => sanitize($data['subject']),
        'description' => sanitize($data['description']),
        'environment' => sanitize($data['environment'] ?? ''),
        'status' => 'open',
        'created_at' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // Save ticket to file
    $saved = saveTicket($ticket, 'support');

    if ($saved) {
        // Send notification email (in production, implement actual email sending)
        sendTicketNotification($ticket);

        return [
            'success' => true,
            'message' => "Support ticket created successfully! Your ticket ID is: {$ticket_id}. We'll respond within " . getPriorityResponseTime($data['priority']) . ".",
            'ticket_id' => $ticket_id
        ];
    }

    throw new Exception('Failed to save ticket');
}

/**
 * Handle emergency support submission
 */
function handleEmergencySubmission($data) {
    // Validate required fields
    $required = ['name', 'phone', 'email', 'domain', 'issue_type', 'description', 'impact'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Generate emergency ticket ID
    $ticket_id = 'EMERGENCY-' . strtoupper(substr(uniqid(), -8));

    // Prepare emergency ticket data
    $ticket = [
        'id' => $ticket_id,
        'type' => 'emergency',
        'name' => sanitize($data['name']),
        'phone' => sanitize($data['phone']),
        'email' => sanitize($data['email']),
        'domain' => sanitize($data['domain']),
        'issue_type' => sanitize($data['issue_type']),
        'description' => sanitize($data['description']),
        'impact' => sanitize($data['impact']),
        'priority' => 'critical',
        'status' => 'emergency',
        'created_at' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // Save emergency ticket
    $saved = saveTicket($ticket, 'emergency');

    if ($saved) {
        // Send emergency notification (in production, implement immediate alerts)
        sendEmergencyNotification($ticket);

        return [
            'success' => true,
            'message' => "Emergency support request submitted! Your emergency ticket ID is: {$ticket_id}. Our emergency team has been alerted and will respond within 1 hour.",
            'ticket_id' => $ticket_id
        ];
    }

    throw new Exception('Failed to save emergency ticket');
}

/**
 * Save ticket to JSON file
 */
function saveTicket($ticket, $type = 'support') {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $filename = $dataDir . "/{$type}_tickets.json";

    // Load existing tickets
    $tickets = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $tickets = json_decode($content, true) ?: [];
    }

    // Add new ticket
    $tickets[] = $ticket;

    // Save updated tickets
    return file_put_contents($filename, json_encode($tickets, JSON_PRETTY_PRINT));
}

/**
 * Get priority response time text
 */
function getPriorityResponseTime($priority) {
    $times = [
        'critical' => '1 hour',
        'high' => '4 hours',
        'medium' => '24 hours',
        'low' => '72 hours'
    ];

    return $times[$priority] ?? '24 hours';
}

/**
 * Send ticket notification (placeholder - implement actual email in production)
 */
function sendTicketNotification($ticket) {
    // In production, implement email sending
    // For now, just log to a file
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'ticket_notification',
        'ticket_id' => $ticket['id'],
        'email' => $ticket['email'],
        'subject' => $ticket['subject'],
        'priority' => $ticket['priority']
    ];

    file_put_contents($logDir . '/notifications.log', json_encode($logEntry) . "\n", FILE_APPEND);
}

/**
 * Send emergency notification (placeholder - implement actual alerts in production)
 */
function sendEmergencyNotification($ticket) {
    // In production, implement immediate alerts (SMS, email, Slack, etc.)
    // For now, just log to a file
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'emergency_notification',
        'ticket_id' => $ticket['id'],
        'email' => $ticket['email'],
        'phone' => $ticket['phone'],
        'domain' => $ticket['domain'],
        'issue_type' => $ticket['issue_type'],
        'impact' => $ticket['impact']
    ];

    file_put_contents($logDir . '/emergency.log', json_encode($logEntry) . "\n", FILE_APPEND);
}

/**
 * Sanitize input data
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>