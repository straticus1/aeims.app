<?php
/**
 * AEIMS Contact Form Handler
 * Processes contact form submissions and sends email notifications
 */

// CORS headers for AJAX requests
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
    'recipient_email' => 'coleman.ryan@gmail.com',
    'subject_prefix' => '[AEIMS License Inquiry]',
    'from_email' => 'noreply@aeims.app',
    'from_name' => 'AEIMS Contact Form',
    'max_message_length' => 2000,
    'required_fields' => ['name', 'email', 'message']
];

/**
 * Validate and sanitize input data
 */
function validateInput($data) {
    global $config;

    $errors = [];
    $sanitized = [];

    // Check required fields
    foreach ($config['required_fields'] as $field) {
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

    if (!empty($data['company'])) {
        $sanitized['company'] = trim(strip_tags($data['company']));
    }

    if (!empty($data['users'])) {
        $sanitized['users'] = trim(strip_tags($data['users']));
    }

    if (!empty($data['domains'])) {
        $sanitized['domains'] = trim(strip_tags($data['domains']));
    }

    if (!empty($data['message'])) {
        $sanitized['message'] = trim(strip_tags($data['message']));
        if (strlen($sanitized['message']) > $config['max_message_length']) {
            $errors[] = "Message must be less than {$config['max_message_length']} characters";
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $sanitized
    ];
}

/**
 * Generate email content
 */
function generateEmailContent($data) {
    $content = "New AEIMS License Inquiry\n";
    $content .= "========================\n\n";

    $content .= "Contact Information:\n";
    $content .= "Name: {$data['name']}\n";
    $content .= "Email: {$data['email']}\n";

    if (!empty($data['company'])) {
        $content .= "Company: {$data['company']}\n";
    }

    $content .= "\nRequirements:\n";

    if (!empty($data['users'])) {
        $content .= "Estimated Users: {$data['users']}\n";
    }

    if (!empty($data['domains'])) {
        $content .= "Number of Domains: {$data['domains']}\n";
    }

    $content .= "\nMessage:\n";
    $content .= str_repeat("-", 40) . "\n";
    $content .= $data['message'] . "\n";
    $content .= str_repeat("-", 40) . "\n\n";

    $content .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";
    $content .= "IP Address: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . "\n";
    $content .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";

    return $content;
}

/**
 * Send email notification
 */
function sendNotification($data) {
    global $config;

    $subject = $config['subject_prefix'] . ' ' . $data['name'];
    $message = generateEmailContent($data);

    $headers = [
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: AEIMS Contact Form'
    ];

    return mail($config['recipient_email'], $subject, $message, implode("\r\n", $headers));
}

/**
 * Log submission for security/analytics
 */
function logSubmission($data, $success) {
    $logEntry = [
        'timestamp' => date('c'),
        'ip' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'email' => $data['email'] ?? 'unknown',
        'success' => $success,
        'users' => $data['users'] ?? null,
        'domains' => $data['domains'] ?? null
    ];

    $logFile = __DIR__ . '/logs/contact-submissions.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Rate limiting (simple implementation)
 */
function checkRateLimit() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $rateLimitFile = __DIR__ . '/logs/rate-limit.json';

    if (!file_exists($rateLimitFile)) {
        file_put_contents($rateLimitFile, json_encode([]));
    }

    $rateLimitData = json_decode(file_get_contents($rateLimitFile), true);
    $now = time();
    $timeWindow = 3600; // 1 hour
    $maxRequests = 5; // Max 5 submissions per hour per IP

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
    if (!checkRateLimit()) {
        throw new Exception('Too many requests. Please try again later.', 429);
    }

    // Get and decode JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Fallback to $_POST if JSON decode fails
    if ($data === null) {
        $data = $_POST;
    }

    // Validate input
    $validation = validateInput($data);

    if (!$validation['valid']) {
        throw new Exception('Validation failed: ' . implode(', ', $validation['errors']), 400);
    }

    // Send notification email
    $emailSent = sendNotification($validation['data']);

    if (!$emailSent) {
        throw new Exception('Failed to send notification email', 500);
    }

    // Log successful submission
    logSubmission($validation['data'], true);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your inquiry! We will get back to you within 24 hours.',
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    // Log failed submission
    if (isset($validation['data'])) {
        logSubmission($validation['data'], false);
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