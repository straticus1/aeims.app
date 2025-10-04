<?php
/**
 * AEIMS Operator Registration Handler
 * Processes operator application form submissions
 */

session_start();
$config = include 'config.php';
$response = ['success' => false, 'message' => '', 'application_id' => null];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $response = handleOperatorApplication($_POST);
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
    header("Location: become-operator.php?message={$message}&type={$type}");
    exit;
}

/**
 * Handle operator application submission
 */
function handleOperatorApplication($data) {
    // Validate required fields
    $required = [
        'first_name', 'last_name', 'email', 'address', 'phone',
        'first_role', 'sms_support', 'hours_per_week', 'worked_competitor',
        'password', 'confirm_password', 'terms_agree', 'age_verify'
    ];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Check if email already exists
    if (emailExists($data['email'])) {
        throw new Exception('An application with this email address already exists');
    }

    // Validate password requirements
    $password = $data['password'];
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    if (!preg_match('/\d/', $password)) {
        throw new Exception('Password must include at least one number');
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        throw new Exception('Password must include at least one special character');
    }

    // Confirm passwords match
    if ($password !== $data['confirm_password']) {
        throw new Exception('Passwords do not match');
    }

    // Validate competitor information if applicable
    if ($data['worked_competitor'] === 'yes' && empty($data['competitor_platforms'])) {
        throw new Exception('Please specify which competing platforms you have worked for');
    }

    // Generate application ID
    $application_id = 'OP-' . strtoupper(substr(uniqid(), -8));

    // Prepare application data
    $application = [
        'id' => $application_id,
        'personal_info' => [
            'first_name' => sanitize($data['first_name']),
            'middle_initial' => sanitize($data['middle_initial'] ?? ''),
            'last_name' => sanitize($data['last_name']),
            'email' => sanitize($data['email']),
            'address' => sanitize($data['address']),
            'phone' => sanitize($data['phone'])
        ],
        'experience' => [
            'first_role' => sanitize($data['first_role']),
            'worked_competitor' => sanitize($data['worked_competitor']),
            'competitor_platforms' => sanitize($data['competitor_platforms'] ?? ''),
            'average_rating' => sanitize($data['average_rating'] ?? '')
        ],
        'technical' => [
            'sms_support' => sanitize($data['sms_support']),
            'hours_per_week' => sanitize($data['hours_per_week'])
        ],
        'account' => [
            'login_name' => sanitize($data['login_name']) ?: sanitize($data['email']),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ],
        'application_data' => [
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ],
        'verification' => [
            'terms_agreed' => $data['terms_agree'] === 'on',
            'age_verified' => $data['age_verify'] === 'on'
        ]
    ];

    // Save application
    $saved = saveOperatorApplication($application);

    if ($saved) {
        // Send notification emails
        sendApplicationNotification($application);

        // Redirect to identity verification step
        header("Location: identity-verification.php?application_id={$application_id}");
        exit();

        return [
            'success' => true,
            'message' => "Step 1 completed. Redirecting to identity verification...",
            'application_id' => $application_id
        ];
    }

    throw new Exception('Failed to save application');
}

/**
 * Check if email already exists in applications or accounts
 */
function emailExists($email) {
    $dataDir = __DIR__ . '/data';

    // Check operator applications
    $applicationsFile = $dataDir . '/operator_applications.json';
    if (file_exists($applicationsFile)) {
        $applications = json_decode(file_get_contents($applicationsFile), true) ?: [];
        foreach ($applications as $app) {
            if (strtolower($app['personal_info']['email']) === strtolower($email)) {
                return true;
            }
        }
    }

    // Check existing accounts
    $accountsFile = $dataDir . '/accounts.json';
    if (file_exists($accountsFile)) {
        $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
        foreach ($accounts as $account) {
            if (strtolower($account['email']) === strtolower($email)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Save operator application to JSON file
 */
function saveOperatorApplication($application) {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $filename = $dataDir . '/operator_applications.json';

    // Load existing applications
    $applications = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $applications = json_decode($content, true) ?: [];
    }

    // Add new application
    $applications[] = $application;

    // Save updated applications
    return file_put_contents($filename, json_encode($applications, JSON_PRETTY_PRINT));
}

/**
 * Send application notification emails
 */
function sendApplicationNotification($application) {
    global $config;

    // In production, implement actual email sending
    // For now, just log to a file
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Log admin notification
    $adminLogEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'operator_application',
        'application_id' => $application['id'],
        'applicant_name' => $application['personal_info']['first_name'] . ' ' . $application['personal_info']['last_name'],
        'applicant_email' => $application['personal_info']['email'],
        'experience_level' => $application['experience']['first_role'],
        'hours_per_week' => $application['technical']['hours_per_week'],
        'admin_action_required' => true
    ];

    file_put_contents($logDir . '/admin_notifications.log', json_encode($adminLogEntry) . "\n", FILE_APPEND);

    // Log applicant confirmation
    $applicantLogEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'application_confirmation',
        'application_id' => $application['id'],
        'recipient_email' => $application['personal_info']['email'],
        'subject' => 'AEIMS Operator Application Received',
        'message' => "Thank you for your interest in becoming an AEIMS operator. Your application (ID: {$application['id']}) has been received and will be reviewed within 24-48 hours."
    ];

    file_put_contents($logDir . '/email_notifications.log', json_encode($applicantLogEntry) . "\n", FILE_APPEND);

    // In production, send actual emails:
    // - Confirmation email to applicant
    // - Notification email to admin team
    // - Welcome email with next steps after approval
}

/**
 * Sanitize input data
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate temporary login credentials for approved operators
 */
function generateOperatorAccount($application) {
    // This would be called when an application is approved
    // Creates an actual user account in the accounts.json file

    $accountsFile = __DIR__ . '/data/accounts.json';
    $accounts = [];

    if (file_exists($accountsFile)) {
        $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
    }

    $username = $application['account']['login_name'];
    $newAccount = [
        'id' => 'OP' . time() . rand(100, 999),
        'name' => $application['personal_info']['first_name'] . ' ' . $application['personal_info']['last_name'],
        'email' => $application['personal_info']['email'],
        'password' => $application['account']['password_hash'], // Already hashed
        'type' => 'operator',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'application_id' => $application['id'],
        'permissions' => ['create_ads', 'manage_identities', 'view_analytics'],
        'operator_data' => [
            'experience_level' => $application['experience']['first_role'],
            'hours_per_week' => $application['technical']['hours_per_week'],
            'sms_support' => $application['technical']['sms_support'],
            'identities' => [],
            'ads' => []
        ],
        'verification_status' => [
            'identity_verified' => false,
            'verification_date' => null,
            'id_expiration_date' => null,
            'next_revalidation_date' => null,
            'verification_method' => null,
            'verification_confidence' => null,
            'verification_notes' => null
        ]
    ];

    $accounts[$username] = $newAccount;

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}
?>