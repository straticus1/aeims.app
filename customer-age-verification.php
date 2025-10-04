<?php
/**
 * AEIMS Customer Age Verification
 * Credit card-based age verification for customers (18+ validation)
 */

session_start();
$config = include 'config.php';
$response = ['success' => false, 'message' => '', 'verification_id' => null];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $response = handleAgeVerification($_POST);
    } catch (Exception $e) {
        $response['message'] = 'Verification Error: ' . $e->getMessage();
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
    header("Location: customer-age-verification.php?message={$message}&type={$type}");
    exit;
}

/**
 * Handle age verification submission
 */
function handleAgeVerification($data) {
    // Validate required fields
    $required = ['email', 'card_number', 'expiry_month', 'expiry_year', 'cvv', 'billing_name', 'billing_address', 'billing_city', 'billing_state', 'billing_zip'];

    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '{$field}' is required");
        }
    }

    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Validate card number (basic format check)
    $cardNumber = preg_replace('/\s+/', '', $data['card_number']);
    if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
        throw new Exception('Invalid credit card number format');
    }

    // Validate expiry date
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');
    $expiryYear = (int)$data['expiry_year'];
    $expiryMonth = (int)$data['expiry_month'];

    if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        throw new Exception('Credit card has expired');
    }

    // Validate CVV
    if (!preg_match('/^\d{3,4}$/', $data['cvv'])) {
        throw new Exception('Invalid CVV code');
    }

    // Perform credit card validation for age verification
    $verificationResult = validateCreditCardForAge($data);

    if (!$verificationResult['success']) {
        throw new Exception($verificationResult['message']);
    }

    // Generate verification ID
    $verification_id = 'AGE-' . strtoupper(substr(uniqid(), -8));

    // Prepare verification record
    $verification = [
        'id' => $verification_id,
        'email' => sanitize($data['email']),
        'verification_type' => 'credit_card_age_verification',
        'verification_date' => date('Y-m-d H:i:s'),
        'verification_result' => $verificationResult,
        'billing_info' => [
            'name' => sanitize($data['billing_name']),
            'address' => sanitize($data['billing_address']),
            'city' => sanitize($data['billing_city']),
            'state' => sanitize($data['billing_state']),
            'zip' => sanitize($data['billing_zip'])
        ],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'card_info' => [
            'last_four' => substr($cardNumber, -4),
            'card_type' => detectCardType($cardNumber),
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear
        ]
    ];

    // Save verification record
    $saved = saveAgeVerificationRecord($verification);

    if ($saved) {
        // Update customer account age verification status
        updateCustomerAgeVerification($data['email'], $verification);

        // Send confirmation
        sendAgeVerificationConfirmation($verification);

        return [
            'success' => true,
            'message' => "Age verification completed successfully! Verification ID: {$verification_id}",
            'verification_id' => $verification_id
        ];
    }

    throw new Exception('Failed to save age verification record');
}

/**
 * Validate credit card for age verification (18+ check)
 */
function validateCreditCardForAge($data) {
    // In a production environment, this would integrate with a payment processor
    // For demo purposes, we'll simulate the validation

    $cardNumber = preg_replace('/\s+/', '', $data['card_number']);

    // Basic card validation checks
    if (!luhnCheck($cardNumber)) {
        return ['success' => false, 'message' => 'Invalid credit card number'];
    }

    // Simulate payment processor response
    // In reality, this would make an API call to Stripe, Square, etc.
    $processorResponse = simulatePaymentProcessor($data);

    if (!$processorResponse['valid']) {
        return ['success' => false, 'message' => $processorResponse['error']];
    }

    // Age verification logic
    if (!$processorResponse['age_verified']) {
        return ['success' => false, 'message' => 'Unable to verify age. Please ensure you are 18 or older and using a valid payment method.'];
    }

    return [
        'success' => true,
        'age_verified' => true,
        'verification_method' => 'credit_card',
        'processor_response' => $processorResponse
    ];
}

/**
 * Luhn algorithm for credit card validation
 */
function luhnCheck($cardNumber) {
    $sum = 0;
    $odd = strlen($cardNumber) % 2;

    for ($i = 0; $i < strlen($cardNumber); $i++) {
        $digit = (int)$cardNumber[$i];

        if (($i % 2) == $odd) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }

        $sum += $digit;
    }

    return ($sum % 10) == 0;
}

/**
 * Detect credit card type
 */
function detectCardType($cardNumber) {
    $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/'
    ];

    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $cardNumber)) {
            return $type;
        }
    }

    return 'unknown';
}

/**
 * Simulate payment processor for demo purposes
 */
function simulatePaymentProcessor($data) {
    $cardNumber = preg_replace('/\s+/', '', $data['card_number']);

    // Simulate different responses based on card number
    $lastDigit = (int)substr($cardNumber, -1);

    if ($lastDigit === 0) {
        return ['valid' => false, 'error' => 'Card declined by issuer'];
    } elseif ($lastDigit === 1) {
        return ['valid' => false, 'error' => 'Insufficient funds'];
    } elseif ($lastDigit === 2) {
        return ['valid' => true, 'age_verified' => false, 'error' => 'Unable to verify cardholder age'];
    } else {
        return [
            'valid' => true,
            'age_verified' => true,
            'cardholder_age_range' => '18+',
            'verification_confidence' => 0.95
        ];
    }
}

/**
 * Save age verification record
 */
function saveAgeVerificationRecord($verification) {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $filename = $dataDir . '/age_verifications.json';

    // Load existing verifications
    $verifications = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $verifications = json_decode($content, true) ?: [];
    }

    // Add new verification
    $verifications[] = $verification;

    // Save updated verifications
    return file_put_contents($filename, json_encode($verifications, JSON_PRETTY_PRINT));
}

/**
 * Update customer account age verification status
 */
function updateCustomerAgeVerification($email, $verification) {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];

    foreach ($accounts as $username => &$account) {
        if (strtolower($account['email']) === strtolower($email)) {
            $account['age_verification'] = [
                'verified' => $verification['verification_result']['age_verified'],
                'verification_date' => $verification['verification_date'],
                'verification_id' => $verification['id'],
                'verification_method' => 'credit_card',
                'last_four' => $verification['card_info']['last_four']
            ];
            break;
        }
    }

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * Send age verification confirmation
 */
function sendAgeVerificationConfirmation($verification) {
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $confirmationEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'age_verification_confirmation',
        'verification_id' => $verification['id'],
        'recipient_email' => $verification['email'],
        'subject' => 'AEIMS Age Verification Confirmation',
        'message' => "Your age verification has been completed successfully. Verification ID: {$verification['id']}. You now have full access to all platform features."
    ];

    file_put_contents($logDir . '/email_notifications.log', json_encode($confirmationEntry) . "\n", FILE_APPEND);
}

/**
 * Sanitize input data
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get message from URL parameters
$message = '';
$messageType = '';
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Age Verification - <?php echo $config['site']['name']; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .verification-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .verification-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .verification-header h1 {
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .security-notice {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .security-notice h3 {
            color: #0369a1;
            margin: 0 0 0.5rem 0;
        }

        .security-notice p {
            margin: 0;
            color: #0c4a6e;
            font-size: 0.875rem;
        }

        .btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #2563eb;
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .breadcrumb {
            text-align: center;
            margin-bottom: 1rem;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .card-icons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .card-icon {
            width: 32px;
            height: 20px;
            background: #f3f4f6;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">← Back to Homepage</a>
        </div>

        <!-- Header -->
        <div class="verification-header">
            <h1>🔒 Customer Age Verification</h1>
            <p>Verify that you are 18 or older to access all platform features</p>
        </div>

        <!-- Message Display -->
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Security Notice -->
        <div class="security-notice">
            <h3>🛡️ Your Security & Privacy</h3>
            <p>We use industry-standard encryption to protect your payment information. Your credit card will be used only for age verification purposes - no charges will be made to your account. We do not store your full credit card number.</p>
        </div>

        <!-- IP Address and Compliance Notice -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.2rem;">🔒</span>
                <strong style="color: #374151;">Security & Compliance Notice</strong>
            </div>
            <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">
                Your IP address <strong style="color: #1e40af;"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?></strong> will be logged as part of this age verification process for security and compliance purposes. This information helps us maintain platform security and meet regulatory requirements.
            </p>
            <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                By submitting this verification, you acknowledge that your IP address and submission details will be recorded.
            </p>
        </div>

        <!-- Age Verification Form -->
        <form method="POST" id="age-verification-form">
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>

            <h3 style="color: #374151; margin-bottom: 1rem;">Payment Information</h3>

            <div class="form-group">
                <label for="card_number">Credit Card Number *</label>
                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
                <div class="card-icons">
                    <div class="card-icon">VISA</div>
                    <div class="card-icon">MC</div>
                    <div class="card-icon">AMEX</div>
                    <div class="card-icon">DISC</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="expiry_month">Expiry Month *</label>
                    <select id="expiry_month" name="expiry_month" required>
                        <option value="">Month</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="expiry_year">Expiry Year *</label>
                    <select id="expiry_year" name="expiry_year" required>
                        <option value="">Year</option>
                        <?php for ($i = date('Y'); $i <= date('Y') + 15; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="cvv">CVV Code *</label>
                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" required>
            </div>

            <h3 style="color: #374151; margin: 2rem 0 1rem 0;">Billing Address</h3>

            <div class="form-group">
                <label for="billing_name">Full Name on Card *</label>
                <input type="text" id="billing_name" name="billing_name" required>
            </div>

            <div class="form-group">
                <label for="billing_address">Billing Address *</label>
                <input type="text" id="billing_address" name="billing_address" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="billing_city">City *</label>
                    <input type="text" id="billing_city" name="billing_city" required>
                </div>
                <div class="form-group">
                    <label for="billing_state">State *</label>
                    <input type="text" id="billing_state" name="billing_state" placeholder="CA" required>
                </div>
            </div>

            <div class="form-group">
                <label for="billing_zip">ZIP Code *</label>
                <input type="text" id="billing_zip" name="billing_zip" required>
            </div>

            <button type="submit" class="btn" id="submit-btn">
                🔒 Verify My Age (No Charge)
            </button>
        </form>
    </div>

    <script>
        // Credit card number formatting
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
            e.target.value = formattedValue;
        });

        // CVV validation
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Form submission
        document.getElementById('age-verification-form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.innerHTML = '🔄 Processing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>