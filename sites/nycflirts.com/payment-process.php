<?php
/**
 * Payment Processing Page
 * Handles the actual payment form and processing
 */

session_start();

// Check customer authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

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

    $transactionId = $_GET['transaction_id'] ?? '';
    if (!$transactionId) {
        header('Location: /payment.php');
        exit;
    }

    $transaction = $paymentManager->getTransaction($transactionId);
    if (!$transaction || $transaction['customer_id'] !== $_SESSION['customer_id']) {
        header('Location: /payment.php');
        exit;
    }

    if ($transaction['status'] !== 'pending') {
        header('Location: /payment-success.php');
        exit;
    }

    $creditPackages = $paymentManager->getCreditPackages();
    $package = $creditPackages[$transaction['package_id']];

} catch (Exception $e) {
    error_log("Payment process error: " . $e->getMessage());
    header('Location: /payment.php');
    exit;
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    try {
        // Get payment data based on method
        $paymentData = [];

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
    <title>Complete Payment - <?= htmlspecialchars($site['name']) ?></title>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .payment-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .payment-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .payment-header h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .transaction-summary {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .summary-row.total {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 0.5rem;
            margin-top: 1rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
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
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: <?= $site['theme']['primary_color'] ?>;
            font-weight: 500;
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
            border-color: <?= $site['theme']['primary_color'] ?>;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .payment-button {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .payment-button:disabled {
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

        .security-icon {
            color: #22c55e;
            margin-right: 0.5rem;
        }

        .crypto-selector {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .crypto-option {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 5px;
            padding: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .crypto-option.selected {
            border-color: <?= $site['theme']['primary_color'] ?>;
            background: rgba(239, 68, 68, 0.2);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .crypto-selector {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="/payment.php" class="back-button">← Back</a>
        </div>
    </header>

    <main class="main-content">
        <div class="payment-container">
            <div class="payment-header">
                <h1>Complete Your Payment</h1>
                <p>Enter your payment details to add credits to your account</p>
            </div>

            <div class="transaction-summary">
                <h3 style="margin-bottom: 1rem; color: <?= $site['theme']['primary_color'] ?>;">Order Summary</h3>
                <div class="summary-row">
                    <span>Package:</span>
                    <span><?= ucfirst($transaction['package_id']) ?></span>
                </div>
                <div class="summary-row">
                    <span>Credits:</span>
                    <span><?= number_format($package['credits'], 0) ?></span>
                </div>
                <?php if ($package['bonus'] > 0): ?>
                <div class="summary-row">
                    <span>Bonus Credits:</span>
                    <span><?= number_format($package['bonus'], 0) ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span>Total Credits:</span>
                    <span><?= number_format($transaction['total_credits'], 0) ?></span>
                </div>
                <div class="summary-row total">
                    <span>Amount:</span>
                    <span>$<?= number_format($transaction['amount_usd'], 2) ?></span>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="payment-form" id="payment-form">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transactionId) ?>">

                <?php if ($transaction['payment_method'] === 'stripe'): ?>
                    <div class="form-group">
                        <label for="cardholder_name">Cardholder Name</label>
                        <input type="text" id="cardholder_name" name="cardholder_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" class="form-control"
                               placeholder="1234 5678 9012 3456" maxlength="19" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_month">Expiry Month</label>
                            <select id="expiry_month" name="expiry_month" class="form-control" required>
                                <option value="">Month</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="expiry_year">Expiry Year</label>
                            <select id="expiry_year" name="expiry_year" class="form-control" required>
                                <option value="">Year</option>
                                <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="text" id="cvv" name="cvv" class="form-control"
                               placeholder="123" maxlength="4" required>
                    </div>

                <?php elseif ($transaction['payment_method'] === 'paypal'): ?>
                    <div class="form-group">
                        <label for="paypal_email">PayPal Email</label>
                        <input type="email" id="paypal_email" name="paypal_email" class="form-control"
                               placeholder="your@paypal.com" required>
                    </div>

                <?php elseif ($transaction['payment_method'] === 'crypto'): ?>
                    <div class="form-group">
                        <label>Select Cryptocurrency</label>
                        <div class="crypto-selector">
                            <div class="crypto-option selected" data-crypto="btc">
                                <div>₿</div>
                                <div>Bitcoin</div>
                            </div>
                            <div class="crypto-option" data-crypto="eth">
                                <div>Ξ</div>
                                <div>Ethereum</div>
                            </div>
                            <div class="crypto-option" data-crypto="ltc">
                                <div>Ł</div>
                                <div>Litecoin</div>
                            </div>
                            <div class="crypto-option" data-crypto="usdt">
                                <div>₮</div>
                                <div>USDT</div>
                            </div>
                        </div>
                        <input type="hidden" name="crypto_currency" value="btc" id="crypto_currency">
                    </div>

                    <div class="form-group">
                        <label for="wallet_address">Your Wallet Address</label>
                        <input type="text" id="wallet_address" name="wallet_address" class="form-control"
                               placeholder="Enter your wallet address" required>
                    </div>

                <?php elseif (in_array($transaction['payment_method'], ['venmo', 'cashapp'])): ?>
                    <div class="form-group">
                        <label for="username"><?= ucfirst($transaction['payment_method']) ?> Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                               placeholder="@username" required>
                    </div>
                <?php endif; ?>

                <button type="submit" class="payment-button">
                    Complete Payment - $<?= number_format($transaction['amount_usd'], 2) ?>
                </button>
            </form>

            <div class="secure-info">
                <span class="security-icon">🔒</span>
                Your payment information is secure and encrypted. We never store your payment details.
            </div>
        </div>
    </main>

    <script>
        // Format card number input
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.replace(/(.{4})/g, '$1 ').trim();
            if (formattedValue.length <= 19) {
                e.target.value = formattedValue;
            }
        });

        // Crypto currency selector
        document.querySelectorAll('.crypto-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.crypto-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('crypto_currency').value = this.dataset.crypto;
            });
        });

        // CVV validation
        document.getElementById('cvv')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Username formatting for P2P payments
        document.getElementById('username')?.addEventListener('input', function(e) {
            let value = e.target.value;
            if (value && !value.startsWith('@')) {
                e.target.value = '@' + value;
            }
        });
    </script>
</body>
</html>