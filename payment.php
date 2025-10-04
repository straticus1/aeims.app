<?php
/**
 * Centralized Payment Hub
 * Handles payments for all AEIMS sites with PayKings and Authorize.net integration
 */

session_start();

// Load AEIMS integration
require_once 'includes/AeimsIntegration.php';

try {
    $aeims = new AeimsIntegration();

    // Check if user is authenticated
    if (!isset($_SESSION['customer_id'])) {
        // Redirect to login with return URL
        $currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('Location: /login.php?return_url=' . urlencode($currentUrl));
        exit;
    }

    // Get parameters
    $sourceSite = $_GET['site'] ?? 'aeims.app';
    $returnUrl = $_GET['return_url'] ?? "https://{$sourceSite}/dashboard.php";
    $preselectedAmount = $_GET['amount'] ?? '';
    $preselectedMethod = $_GET['method'] ?? '';

    // Get customer data
    $customer = $aeims->getCustomer($_SESSION['customer_id']);
    if (!$customer) {
        header('Location: /login.php');
        exit;
    }

    // Get site information for branding
    $siteInfo = $aeims->getSite($sourceSite);
    if (!$siteInfo) {
        $siteInfo = [
            'name' => 'AEIMS',
            'theme' => [
                'primary_color' => '#ef4444',
                'secondary_color' => '#dc2626',
                'accent_color' => '#f97316',
                'text_color' => '#ffffff',
                'font_family' => "'Inter', sans-serif",
                'favicon_url' => '/assets/favicon.png'
            ]
        ];
    }

} catch (Exception $e) {
    error_log("Payment hub error: " . $e->getMessage());
    header('Location: /error.php');
    exit;
}

// Enhanced credit packages with flexible amounts
$creditPackages = [
    'custom' => ['min' => 5.00, 'max' => 1000.00, 'rate' => 1.0],
    'quick' => ['credits' => 10.00, 'price' => 9.99, 'bonus' => 0.00],
    'standard' => ['credits' => 25.00, 'price' => 19.99, 'bonus' => 5.00],
    'premium' => ['credits' => 50.00, 'price' => 39.99, 'bonus' => 15.00],
    'deluxe' => ['credits' => 100.00, 'price' => 79.99, 'bonus' => 35.00],
    'ultimate' => ['credits' => 250.00, 'price' => 199.99, 'bonus' => 100.00]
];

// Payment processors with real API integration
$paymentProcessors = [
    'paykings' => [
        'name' => 'Credit/Debit Card (PayKings)',
        'icon' => '💳',
        'priority' => 1,
        'phone_support' => true
    ],
    'authorize' => [
        'name' => 'Credit/Debit Card (Authorize.net)',
        'icon' => '💳',
        'priority' => 2,
        'phone_support' => false
    ],
    'crypto' => [
        'name' => 'Cryptocurrency',
        'icon' => '₿',
        'priority' => 3,
        'phone_support' => false
    ],
    'paypal' => [
        'name' => 'PayPal',
        'icon' => '🅿️',
        'priority' => 4,
        'phone_support' => false
    ]
];

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'process_payment') {
            $amount = floatval($_POST['amount'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? '';
            $sourcesite = $_POST['source_site'] ?? $sourceSite;

            if ($amount < 5.00 || $amount > 1000.00) {
                throw new Exception('Amount must be between $5.00 and $1,000.00');
            }

            if (!isset($paymentProcessors[$paymentMethod])) {
                throw new Exception('Invalid payment method');
            }

            // Create transaction ID
            $transactionId = 'txn_' . uniqid();

            // Store transaction in session for processing
            $_SESSION['pending_transaction'] = [
                'transaction_id' => $transactionId,
                'customer_id' => $_SESSION['customer_id'],
                'amount' => $amount,
                'credits' => $amount, // 1:1 ratio for custom amounts
                'payment_method' => $paymentMethod,
                'source_site' => $sourcesite,
                'return_url' => $returnUrl,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Redirect to payment processor
            header('Location: /payment-process.php?method=' . $paymentMethod);
            exit;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Credits - AEIMS Payment Hub</title>
    <link rel="icon" href="<?= htmlspecialchars($siteInfo['theme']['favicon_url']) ?>">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= $siteInfo['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: <?= $siteInfo['theme']['text_color'] ?>;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: <?= $siteInfo['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .site-badge {
            background: linear-gradient(45deg, <?= $siteInfo['theme']['primary_color'] ?>, <?= $siteInfo['theme']['secondary_color'] ?>);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            margin-left: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $siteInfo['theme']['primary_color'] ?>, <?= $siteInfo['theme']['secondary_color'] ?>);
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-weight: 500;
        }

        .main-content {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .payment-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .payment-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $siteInfo['theme']['primary_color'] ?>, <?= $siteInfo['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .source-info {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            margin-bottom: 2rem;
            text-align: center;
        }

        .payment-form {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            color: <?= $siteInfo['theme']['primary_color'] ?>;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .amount-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .amount-option {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .amount-option:hover,
        .amount-option.selected {
            border-color: <?= $siteInfo['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.2);
        }

        .amount-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: <?= $siteInfo['theme']['primary_color'] ?>;
        }

        .amount-credits {
            font-size: 0.9rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }

        .custom-amount {
            margin-top: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: <?= $siteInfo['theme']['primary_color'] ?>;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .method-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .method-card:hover,
        .method-card.selected {
            border-color: <?= $siteInfo['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.2);
        }

        .method-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .phone-badge {
            background: #22c55e;
            color: white;
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            margin-top: 0.5rem;
        }

        .submit-button {
            background: linear-gradient(45deg, <?= $siteInfo['theme']['primary_color'] ?>, <?= $siteInfo['theme']['secondary_color'] ?>);
            color: white;
            border: none;
            padding: 1rem 3rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 2rem;
        }

        .submit-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .submit-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .security-info {
            text-align: center;
            margin-top: 2rem;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: <?= $siteInfo['theme']['primary_color'] ?>;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .payment-header h1 {
                font-size: 2rem;
            }

            .amount-options {
                grid-template-columns: repeat(2, 1fr);
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div style="display: flex; align-items: center;">
                <a href="/" class="logo">AEIMS</a>
                <div class="site-badge"><?= htmlspecialchars($siteInfo['name']) ?></div>
            </div>

            <div class="user-info">
                <div class="credits-display">
                    Balance: $<?= number_format($customer['billing']['credits'], 2) ?>
                </div>
                <span>Welcome, <?= htmlspecialchars($customer['username']) ?></span>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="payment-header">
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="back-link">← Back to <?= htmlspecialchars($siteInfo['name']) ?></a>
            <h1>Add Credits</h1>
            <p>Add credits to your account - usable across all AEIMS sites</p>
        </div>

        <div class="source-info">
            🔗 Credits will be added to your universal AEIMS account<br>
            💰 Balance is shared across all sites: <?= htmlspecialchars($sourceSite) ?> and others
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="payment-form" id="payment-form">
            <input type="hidden" name="action" value="process_payment">
            <input type="hidden" name="source_site" value="<?= htmlspecialchars($sourceSite) ?>">
            <input type="hidden" name="amount" id="selected-amount">
            <input type="hidden" name="payment_method" id="selected-method">

            <div class="form-section">
                <h3>1. Select Amount</h3>
                <div class="amount-options">
                    <?php foreach ($creditPackages as $packageId => $package): ?>
                        <?php if ($packageId === 'custom') continue; ?>
                        <div class="amount-option" data-amount="<?= $package['price'] ?>" onclick="selectAmount(<?= $package['price'] ?>)">
                            <div class="amount-value">$<?= number_format($package['price'], 0) ?></div>
                            <div class="amount-credits">
                                <?= number_format($package['credits'] + $package['bonus'], 0) ?> Credits
                                <?php if ($package['bonus'] > 0): ?>
                                    <br><small style="color: #22c55e;">+<?= $package['bonus'] ?> Bonus</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="custom-amount">
                    <label for="custom_amount" style="display: block; margin-bottom: 0.5rem; color: <?= $siteInfo['theme']['primary_color'] ?>;">
                        Custom Amount ($5 - $1,000)
                    </label>
                    <input type="number" id="custom_amount" class="form-control"
                           placeholder="Enter amount..." min="5" max="1000" step="0.01"
                           value="<?= htmlspecialchars($preselectedAmount) ?>"
                           onchange="selectCustomAmount(this.value)">
                </div>
            </div>

            <div class="form-section">
                <h3>2. Select Payment Method</h3>
                <div class="payment-methods">
                    <?php foreach ($paymentProcessors as $methodId => $processor): ?>
                        <div class="method-card <?= $preselectedMethod === $methodId ? 'selected' : '' ?>"
                             data-method="<?= $methodId ?>" onclick="selectMethod('<?= $methodId ?>')">
                            <div class="method-icon"><?= $processor['icon'] ?></div>
                            <div><?= htmlspecialchars($processor['name']) ?></div>
                            <?php if ($processor['phone_support']): ?>
                                <div class="phone-badge">📞 Phone Support</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="submit-button" id="submit-btn" disabled>
                Select Amount and Payment Method
            </button>
        </form>

        <div class="security-info">
            <span style="color: #22c55e;">🔒</span>
            Your payment is secured with bank-level encryption.<br>
            Credits are instantly available across all AEIMS sites.
        </div>
    </main>

    <script>
        let selectedAmount = <?= json_encode($preselectedAmount) ?> || 0;
        let selectedMethod = <?= json_encode($preselectedMethod) ?> || '';

        function selectAmount(amount) {
            selectedAmount = amount;

            // Clear custom amount
            document.getElementById('custom_amount').value = '';

            // Update UI
            document.querySelectorAll('.amount-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`[data-amount="${amount}"]`).classList.add('selected');

            // Update form
            document.getElementById('selected-amount').value = amount;
            updateSubmitButton();
        }

        function selectCustomAmount(amount) {
            amount = parseFloat(amount);
            if (amount >= 5 && amount <= 1000) {
                selectedAmount = amount;

                // Clear preset selections
                document.querySelectorAll('.amount-option').forEach(option => {
                    option.classList.remove('selected');
                });

                // Update form
                document.getElementById('selected-amount').value = amount;
                updateSubmitButton();
            }
        }

        function selectMethod(method) {
            selectedMethod = method;

            // Update UI
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-method="${method}"]`).classList.add('selected');

            // Update form
            document.getElementById('selected-method').value = method;
            updateSubmitButton();
        }

        function updateSubmitButton() {
            const button = document.getElementById('submit-btn');

            if (selectedAmount >= 5 && selectedMethod) {
                button.disabled = false;
                button.textContent = `Add $${selectedAmount} Credits via ${selectedMethod.toUpperCase()}`;
            } else {
                button.disabled = true;
                button.textContent = 'Select Amount and Payment Method';
            }
        }

        // Initialize if preselected values exist
        if (selectedAmount >= 5) {
            if (selectedAmount in [10, 20, 40, 80, 200]) {
                selectAmount(selectedAmount);
            } else {
                document.getElementById('custom_amount').value = selectedAmount;
                selectCustomAmount(selectedAmount);
            }
        }

        if (selectedMethod) {
            selectMethod(selectedMethod);
        }
    </script>
</body>
</html>