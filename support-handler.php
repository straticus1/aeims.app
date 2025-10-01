<?php
/**
 * AEIMS Support Ticket Handler
 * Processes support ticket submissions and emergency requests
 */

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
$config = [
    'support_email' => 'support@aeims.app',
    'emergency_email' => 'emergency@aeims.app',
    'admin_email' => 'coleman.ryan@gmail.com',
    'subject_prefix' => '[AEIMS Support]',
    'emergency_prefix' => '[AEIMS EMERGENCY]',
    'from_email' => 'noreply@aeims.app',
    'from_name' => 'AEIMS Support System',
    'max_message_length' => 5000,
    'required_fields' => [
        'ticket' => ['name', 'email', 'priority', 'category', 'subject', 'description'],
        'emergency' => ['name', 'phone', 'email', 'domain', 'issue_type', 'description', 'impact']
    ]
];

/**
 * Generate unique ticket ID
 */
function generateTicketId($type = 'ticket') {
    $prefix = $type === 'emergency' ? 'EMRG' : 'TICK';
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Validate and sanitize input data
 */
function validateInput($data, $type) {
    global $config;

    $errors = [];
    $sanitized = [];

    // Check required fields
    foreach ($config['required_fields'][$type] as $field) {
        if (empty($data[$field])) {
            $errors[] = "Field '{$field}' is required";
        }
    }

    // Sanitize and validate individual fields
    if (!empty($data['name'])) {
        $sanitized['name'] = trim(strip_tags($data['name']));
        if (strlen($sanitized['name']) < 2 || strlen($sanitized['name']) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters';
        }
    }

    if (!empty($data['email'])) {
        $sanitized['email'] = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
        if (!$sanitized['email']) {
            $errors[] = 'Invalid email address';
        }
    }

    if (!empty($data['phone'])) {
        $sanitized['phone'] = preg_replace('/[^\d\+\-\(\)\s]/', '', $data['phone']);
        if (strlen($sanitized['phone']) < 10) {
            $errors[] = 'Invalid phone number';
        }
    }

    // Sanitize other fields
    $textFields = ['company', 'domain', 'subject', 'description', 'impact', 'environment'];
    foreach ($textFields as $field) {
        if (!empty($data[$field])) {
            $sanitized[$field] = trim(strip_tags($data[$field]));
            if ($field === 'description' || $field === 'impact') {
                if (strlen($sanitized[$field]) > $config['max_message_length']) {
                    $errors[] = ucfirst($field) . " must be less than {$config['max_message_length']} characters";
                }
            }
        }
    }

    // Validate select fields
    $selectFields = ['priority', 'category', 'issue_type'];
    foreach ($selectFields as $field) {
        if (!empty($data[$field])) {
            $sanitized[$field] = trim(strip_tags($data[$field]));
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $sanitized
    ];
}

/**
 * Generate email content for support ticket
 */
function generateTicketEmail($data, $ticketId) {
    $content = "AEIMS Support Ticket\n";
    $content .= "==================\n\n";

    $content .= "Ticket ID: {$ticketId}\n";
    $content .= "Priority: " . strtoupper($data['priority']) . "\n";
    $content .= "Category: {$data['category']}\n\n";

    $content .= "Contact Information:\n";
    $content .= "Name: {$data['name']}\n";
    $content .= "Email: {$data['email']}\n";

    if (!empty($data['company'])) {
        $content .= "Company/Domain: {$data['company']}\n";
    }

    $content .= "\nSubject: {$data['subject']}\n\n";

    $content .= "Description:\n";
    $content .= str_repeat("-", 40) . "\n";
    $content .= $data['description'] . "\n";
    $content .= str_repeat("-", 40) . "\n\n";

    if (!empty($data['environment'])) {
        $content .= "Environment Information:\n";
        $content .= $data['environment'] . "\n\n";
    }

    $content .= "Expected Response Time: " . getResponseTime($data['priority']) . "\n";
    $content .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";
    $content .= "IP Address: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . "\n";

    return $content;
}

/**
 * Generate email content for emergency request
 */
function generateEmergencyEmail($data, $ticketId) {
    $content = "🚨 AEIMS EMERGENCY REQUEST 🚨\n";
    $content .= "============================\n\n";

    $content .= "Emergency Ticket ID: {$ticketId}\n";
    $content .= "Issue Type: " . strtoupper($data['issue_type']) . "\n";
    $content .= "IMMEDIATE RESPONSE REQUIRED\n\n";

    $content .= "Contact Information:\n";
    $content .= "Name: {$data['name']}\n";
    $content .= "Email: {$data['email']}\n";
    $content .= "Phone: {$data['phone']}\n";
    $content .= "Affected Domain(s): {$data['domain']}\n\n";

    $content .= "Critical Issue Description:\n";
    $content .= str_repeat("-", 40) . "\n";
    $content .= $data['description'] . "\n";
    $content .= str_repeat("-", 40) . "\n\n";

    $content .= "Business Impact:\n";
    $content .= str_repeat("-", 40) . "\n";
    $content .= $data['impact'] . "\n";
    $content .= str_repeat("-", 40) . "\n\n";

    $content .= "⚠️ TARGET RESPONSE TIME: 1 HOUR\n";
    $content .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";
    $content .= "IP Address: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . "\n";

    return $content;
}

/**
 * Get response time based on priority
 */
function getResponseTime($priority) {
    $times = [
        'critical' => '1 hour',
        'high' => '4 hours',
        'medium' => '24 hours',
        'low' => '72 hours'
    ];
    return $times[$priority] ?? '24 hours';
}

/**
 * Send email notification
 */
function sendNotification($data, $ticketId, $type) {
    global $config;

    if ($type === 'emergency') {
        $subject = $config['emergency_prefix'] . ' ' . $ticketId . ' - ' . $data['issue_type'];
        $message = generateEmergencyEmail($data, $ticketId);
        $recipient = $config['emergency_email'];
    } else {
        $subject = $config['subject_prefix'] . ' ' . $ticketId . ' - ' . $data['priority'];
        $message = generateTicketEmail($data, $ticketId);
        $recipient = $config['support_email'];
    }

    $headers = [
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: AEIMS Support System',
        'X-Priority: ' . ($type === 'emergency' ? '1' : '3')
    ];

    // Send to support team
    $success = mail($recipient, $subject, $message, implode("\r\n", $headers));

    // Also send to admin for emergencies or critical tickets
    if (($type === 'emergency' || $data['priority'] === 'critical') && $success) {
        mail($config['admin_email'], $subject, $message, implode("\r\n", $headers));
    }

    return $success;
}

/**
 * Log ticket submission
 */
function logTicket($data, $ticketId, $type, $success) {
    $logEntry = [
        'ticket_id' => $ticketId,
        'type' => $type,
        'timestamp' => date('c'),
        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'email' => $data['email'] ?? 'unknown',
        'priority' => $data['priority'] ?? $data['issue_type'] ?? 'unknown',
        'success' => $success
    ];

    $logFile = __DIR__ . '/logs/support-tickets.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Store ticket in database (simple file-based for now)
 */
function storeTicket($data, $ticketId, $type) {
    $ticketData = [
        'id' => $ticketId,
        'type' => $type,
        'status' => 'open',
        'priority' => $data['priority'] ?? 'critical',
        'category' => $data['category'] ?? $data['issue_type'] ?? 'emergency',
        'subject' => $data['subject'] ?? 'Emergency: ' . $data['issue_type'],
        'description' => $data['description'],
        'customer' => [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? $data['domain'] ?? null
        ],
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'assigned_to' => null,
        'internal_notes' => []
    ];

    $ticketsFile = __DIR__ . '/data/tickets.json';
    $ticketsDir = dirname($ticketsFile);

    if (!is_dir($ticketsDir)) {
        mkdir($ticketsDir, 0755, true);
    }

    $tickets = [];
    if (file_exists($ticketsFile)) {
        $tickets = json_decode(file_get_contents($ticketsFile), true) ?? [];
    }

    $tickets[$ticketId] = $ticketData;

    file_put_contents($ticketsFile, json_encode($tickets, JSON_PRETTY_PRINT));

    return true;
}

/**
 * Rate limiting for support requests
 */
function checkSupportRateLimit() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $rateLimitFile = __DIR__ . '/logs/support-rate-limit.json';

    if (!file_exists($rateLimitFile)) {
        file_put_contents($rateLimitFile, json_encode([]));
    }

    $rateLimitData = json_decode(file_get_contents($rateLimitFile), true);
    $now = time();
    $timeWindow = 3600; // 1 hour
    $maxRequests = 10; // Max 10 support requests per hour per IP

    // Clean old entries
    $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });

    // Count requests from this IP
    $ipRequests = array_filter($rateLimitData, function($timestamp, $key) use ($ip) {
        return strpos($key, $ip) === 0;
    }, ARRAY_FILTER_USE_BOTH);

    if (count($ipRequests) >= $maxRequests) {
        return false;
    }

    // Add current request
    $rateLimitData[$ip . '_' . $now] = $now;
    file_put_contents($rateLimitFile, json_encode($rateLimitData));

    return true;
}

// Main processing
try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Rate limiting
    if (!checkSupportRateLimit()) {
        throw new Exception('Too many support requests. Please try again later.', 429);
    }

    // Get and decode JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if ($data === null) {
        $data = $_POST;
    }

    // Determine request type
    $type = $data['type'] ?? 'ticket';
    if (!in_array($type, ['ticket', 'emergency'])) {
        throw new Exception('Invalid request type', 400);
    }

    // Validate input
    $validation = validateInput($data, $type);

    if (!$validation['valid']) {
        throw new Exception('Validation failed: ' . implode(', ', $validation['errors']), 400);
    }

    // Generate ticket ID
    $ticketId = generateTicketId($type);

    // Store ticket
    storeTicket($validation['data'], $ticketId, $type);

    // Send notification email
    $emailSent = sendNotification($validation['data'], $ticketId, $type);

    if (!$emailSent) {
        throw new Exception('Failed to send notification email', 500);
    }

    // Log successful submission
    logTicket($validation['data'], $ticketId, $type, true);

    // Return success response
    echo json_encode([
        'success' => true,
        'ticket_id' => $ticketId,
        'message' => $type === 'emergency'
            ? 'Emergency request submitted successfully! Our emergency team has been notified and will contact you within 1 hour.'
            : 'Support ticket submitted successfully! Expected response time: ' . getResponseTime($validation['data']['priority']) . '.',
        'response_time' => $type === 'emergency' ? '1 hour' : getResponseTime($validation['data']['priority']),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    // Log failed submission
    if (isset($validation['data']) && isset($ticketId)) {
        logTicket($validation['data'], $ticketId ?? 'FAILED', $type ?? 'unknown', false);
    }

    // Return error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>