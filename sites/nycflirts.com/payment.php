<?php
/**
 * Credit Purchase Interface - Redirect to Centralized Payment Hub
 * Redirects customers to the centralized AEIMS payment system
 */

session_start();

// Check customer authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

// Get any pre-selected parameters
$preselectedPackage = $_GET['package'] ?? '';
$preselectedMethod = $_GET['method'] ?? '';
$preselectedAmount = $_GET['amount'] ?? '';

// Build centralized payment URL
$paymentHubUrl = 'https://aeims.app/payment.php';
$params = [
    'site' => 'flirts.nyc',
    'return_url' => 'https://flirts.nyc/dashboard.php'
];

if ($preselectedAmount) {
    $params['amount'] = $preselectedAmount;
}

if ($preselectedMethod) {
    $params['method'] = $preselectedMethod;
}

$redirectUrl = $paymentHubUrl . '?' . http_build_query($params);

// Redirect to centralized payment hub
header('Location: ' . $redirectUrl);
exit;

// Load services
require_once '../../services/SiteManager.php';
require_once '../../services/CustomerManager.php';
require_once '../../services/PaymentManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $paymentManager = new \AEIMS\Services\PaymentManager();

    $site = $siteManager->getSite('flirts.nyc');
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);

    if (!$site || !$site['active'] || !$customer) {
        header('Location: /dashboard.php');
        exit;
    }

    $creditPackages = $paymentManager->getCreditPackages();
    $paymentProcessors = $paymentManager->getPaymentProcessors();

} catch (Exception $e) {
    error_log("Payment page error: " . $e->getMessage());
    header('Location: /dashboard.php');
    exit;
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_transaction') {
            $packageId = $_POST['package_id'] ?? '';
            $paymentMethod = $_POST['payment_method'] ?? '';

            if (!$packageId || !$paymentMethod) {
                throw new Exception('Package and payment method are required');
            }

            // Create transaction
            $transaction = $paymentManager->createTransaction(
                $_SESSION['customer_id'],
                $packageId,
                $paymentMethod
            );

            // Redirect to payment processing
            header('Location: /payment-process.php?transaction_id=' . $transaction['transaction_id']);
            exit;

        } elseif ($_POST['action'] === 'process_payment') {
            $transactionId = $_POST['transaction_id'] ?? '';

            if (!$transactionId) {
                throw new Exception('Transaction ID is required');
            }

            // Get payment data based on method
            $paymentData = [];
            $transaction = $paymentManager->getTransaction($transactionId);

            if (!$transaction || $transaction['customer_id'] !== $_SESSION['customer_id']) {
                throw new Exception('Invalid transaction');
            }

            switch ($transaction['payment_method']) {
                case 'stripe':
                    $paymentData = [
                        'card_number' => $_POST['card_number'] ?? '',
                        'expiry_month' => $_POST['expiry_month'] ?? '',
                        'expiry_year' => $_POST['expiry_year'] ?? '',
                        'cvv' => $_POST['cvv'] ?? '',
                        'cardholder_name' => $_POST['cardholder_name'] ?? ''
                    ];
                    break;
                case 'paypal':
                    $paymentData = [
                        'paypal_email' => $_POST['paypal_email'] ?? ''
                    ];
                    break;
                case 'crypto':
                    $paymentData = [
                        'wallet_address' => $_POST['wallet_address'] ?? '',
                        'crypto_currency' => $_POST['crypto_currency'] ?? 'btc'
                    ];
                    break;
                case 'venmo':
                case 'cashapp':
                    $paymentData = [
                        'username' => $_POST['username'] ?? ''
                    ];
                    break;
            }

            // Process payment
            $result = $paymentManager->processPayment($transactionId, $paymentData);

            if ($result['success']) {
                // Refresh customer data
                $customer = $customerManager->getCustomer($_SESSION['customer_id']);

                $_SESSION['payment_success'] = [
                    'transaction_id' => $transactionId,
                    'credits_added' => $result['transaction']['total_credits'],
                    'new_balance' => $customer['billing']['credits']
                ];

                header('Location: /payment-success.php');
                exit;
            } else {
                $error = $result['error'];
            }
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$selectedPackage = $_GET['package'] ?? '';
$selectedMethod = $_GET['method'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Credits - <?= htmlspecialchars($site['name']) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site['theme']['favicon_url']) ?>">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: <?= $site['theme']['font_family'] ?>;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d1b3d 100%);
            color: <?= $site['theme']['text_color'] ?>;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .credits-display {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            padding: 0.5rem 1rem;
            border-radius: 15px;
        }

        .back-button {
            background: transparent;
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: <?= $site['theme']['primary_color'] ?>;
            color: white;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .package-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .package-card:hover {
            transform: translateY(-5px);
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }

        .package-card.selected {
            border-color: <?= $site['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.1);
        }

        .package-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .package-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-transform: uppercase;
        }

        .package-price {
            font-size: 2rem;
            font-weight: bold;
            color: #22c55e;
            margin: 1rem 0;
        }

        .package-credits {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .bonus-credits {
            color: <?= $site['theme']['accent_color'] ?>;
            font-weight: 500;
        }

        .best-value {
            position: absolute;
            top: -10px;
            right: -10px;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .payment-methods {
            margin-top: 3rem;
        }

        .payment-methods h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .method-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .method-card:hover,
        .method-card.selected {
            border-color: <?= $site['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.1);
        }

        .method-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .purchase-button {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            border: none;
            padding: 1rem 3rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            margin: 2rem auto;
            min-width: 200px;
        }

        .purchase-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .purchase-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .secure-info {
            text-align: center;
            margin-top: 2rem;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .secure-info .security-icon {
            color: #22c55e;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .packages-grid {
                grid-template-columns: 1fr;
            }

            .methods-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .main-content {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

        <div class="user-info">
            <div class="credits-display">
                Credits: $<?= number_format($customer['billing']['credits'], 2) ?>
            </div>
            <a href="/dashboard.php" class="back-button">← Back</a>
        </div>
    </header>

    <main class="main-content">
        <div class="page-header">
            <h1>Add Credits</h1>
            <p>Choose a credit package to continue enjoying your conversations</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form id="payment-form" method="POST">
            <input type="hidden" name="action" value="create_transaction">
            <input type="hidden" name="package_id" id="selected-package">
            <input type="hidden" name="payment_method" id="selected-method">

            <div class="packages-grid">
                <?php foreach ($creditPackages as $packageId => $package): ?>
                    <div class="package-card" data-package="<?= $packageId ?>" onclick="selectPackage('<?= $packageId ?>')">
                        <?php if ($packageId === 'deluxe'): ?>
                            <div class="best-value">BEST VALUE</div>
                        <?php endif; ?>

                        <div class="package-header">
                            <div class="package-name"><?= ucfirst($packageId) ?></div>
                            <div class="package-price">$<?= number_format($package['price'], 2) ?></div>
                        </div>

                        <div class="package-credits">
                            <?= number_format($package['credits'], 0) ?> Credits
                            <?php if ($package['bonus'] > 0): ?>
                                <div class="bonus-credits">+ <?= number_format($package['bonus'], 0) ?> Bonus Credits</div>
                            <?php endif; ?>
                        </div>

                        <div style="text-align: center; margin-top: 1rem;">
                            <strong>Total: <?= number_format($package['credits'] + $package['bonus'], 0) ?> Credits</strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="payment-methods">
                <h2>Choose Payment Method</h2>

                <div class="methods-grid">
                    <?php foreach ($paymentProcessors as $methodId => $methodName): ?>
                        <div class="method-card" data-method="<?= $methodId ?>" onclick="selectMethod('<?= $methodId ?>')">
                            <div class="method-icon">
                                <?php
                                $icons = [
                                    'stripe' => '💳',
                                    'paypal' => '🅿️',
                                    'crypto' => '₿',
                                    'venmo' => '📱',
                                    'cashapp' => '💵'
                                ];
                                echo $icons[$methodId] ?? '💰';
                                ?>
                            </div>
                            <div><?= htmlspecialchars($methodName) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="purchase-button" id="purchase-btn" disabled>
                    Select Package & Payment Method
                </button>
            </div>
        </form>

        <div class="secure-info">
            <span class="security-icon">🔒</span>
            Your payment information is secure and encrypted. We never store your payment details.
        </div>
    </main>

    <script>
        let selectedPackage = '<?= $selectedPackage ?>';
        let selectedMethod = '<?= $selectedMethod ?>';

        function selectPackage(packageId) {
            selectedPackage = packageId;

            // Update UI
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-package="${packageId}"]`).classList.add('selected');

            // Update form
            document.getElementById('selected-package').value = packageId;

            updatePurchaseButton();
        }

        function selectMethod(methodId) {
            selectedMethod = methodId;

            // Update UI
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`[data-method="${methodId}"]`).classList.add('selected');

            // Update form
            document.getElementById('selected-method').value = methodId;

            updatePurchaseButton();
        }

        function updatePurchaseButton() {
            const button = document.getElementById('purchase-btn');

            if (selectedPackage && selectedMethod) {
                button.disabled = false;
                button.textContent = 'Continue to Payment';
            } else {
                button.disabled = true;
                button.textContent = 'Select Package & Payment Method';
            }
        }

        // Initialize if package/method are pre-selected
        if (selectedPackage) {
            selectPackage(selectedPackage);
        }
        if (selectedMethod) {
            selectMethod(selectedMethod);
        }
    </script>
</body>
</html>