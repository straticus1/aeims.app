<?php
/**
 * Payment Success Page
 * Shows confirmation after successful credit purchase
 */

session_start();

// Check customer authentication
if (!isset($_SESSION['customer_id'])) {
    header('Location: /');
    exit;
}

// Check for payment success data
if (!isset($_SESSION['payment_success'])) {
    header('Location: /dashboard.php');
    exit;
}

$paymentSuccess = $_SESSION['payment_success'];
unset($_SESSION['payment_success']); // Clear the session data

// Load services
require_once '../../services/SiteManager.php';
require_once '../../services/CustomerManager.php';

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();

    $site = $siteManager->getSite('flirts.nyc');
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);

    if (!$site || !$site['active'] || !$customer) {
        header('Location: /dashboard.php');
        exit;
    }

} catch (Exception $e) {
    error_log("Payment success error: " . $e->getMessage());
    header('Location: /dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - <?= htmlspecialchars($site['name']) ?></title>
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
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 4rem 2rem;
            text-align: center;
        }

        .success-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem 2rem;
            border: 1px solid rgba(34, 197, 94, 0.3);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-content {
            position: relative;
            z-index: 1;
        }

        .success-icon {
            font-size: 4rem;
            color: #22c55e;
            margin-bottom: 1rem;
            animation: bounce 1s ease-in-out infinite alternate;
        }

        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-10px); }
        }

        .success-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #22c55e, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .success-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #d1d5db;
        }

        .payment-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .detail-row:not(:last-child) {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-label {
            color: #9ca3af;
        }

        .detail-value {
            font-weight: bold;
            color: #22c55e;
        }

        .detail-value.credits {
            font-size: 1.2rem;
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
        }

        .btn-secondary {
            background: transparent;
            color: <?= $site['theme']['primary_color'] ?>;
            border: 1px solid <?= $site['theme']['primary_color'] ?>;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .transaction-info {
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #22c55e;
            animation: confetti-fall 3s linear infinite;
        }

        .confetti:nth-child(odd) {
            background: <?= $site['theme']['primary_color'] ?>;
            animation-delay: -1s;
        }

        .confetti:nth-child(even) {
            animation-delay: -2s;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1rem;
            }

            .success-container {
                padding: 2rem 1rem;
            }

            .success-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

        <div class="user-info">
            <div class="credits-display">
                Credits: $<?= number_format($paymentSuccess['new_balance'], 2) ?>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="success-container">
            <!-- Confetti animation -->
            <?php for ($i = 0; $i < 20; $i++): ?>
                <div class="confetti" style="left: <?= rand(0, 100) ?>%; animation-delay: <?= rand(-3000, 0) ?>ms;"></div>
            <?php endfor; ?>

            <div class="success-content">
                <div class="success-icon">🎉</div>
                <h1 class="success-title">Payment Successful!</h1>
                <p class="success-message">
                    Your credits have been added to your account. Start chatting with our amazing models!
                </p>

                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Transaction ID:</span>
                        <span class="detail-value"><?= htmlspecialchars($paymentSuccess['transaction_id']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Credits Added:</span>
                        <span class="detail-value credits"><?= number_format($paymentSuccess['credits_added']) ?> Credits</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">New Balance:</span>
                        <span class="detail-value credits">$<?= number_format($paymentSuccess['new_balance'], 2) ?></span>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="/dashboard.php" class="btn btn-primary">
                        🔥 Start Chatting
                    </a>
                    <a href="/payment.php" class="btn btn-secondary">
                        💰 Buy More Credits
                    </a>
                </div>

                <div class="transaction-info">
                    <p>A confirmation email has been sent to your registered email address.</p>
                    <p>Transaction completed at <?= date('M j, Y \a\t g:i A') ?></p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Add some interactive confetti on click
        document.addEventListener('click', function(e) {
            for (let i = 0; i < 5; i++) {
                createConfetti(e.clientX, e.clientY);
            }
        });

        function createConfetti(x, y) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.left = x + 'px';
            confetti.style.top = y + 'px';
            confetti.style.width = '6px';
            confetti.style.height = '6px';
            confetti.style.backgroundColor = Math.random() > 0.5 ? '#22c55e' : '<?= $site['theme']['primary_color'] ?>';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '9999';

            document.body.appendChild(confetti);

            const angle = Math.random() * 360;
            const velocity = Math.random() * 300 + 100;
            const gravity = 500;
            const life = Math.random() * 1000 + 1000;

            let vx = Math.cos(angle * Math.PI / 180) * velocity;
            let vy = Math.sin(angle * Math.PI / 180) * velocity;

            let startTime = Date.now();

            function animate() {
                const elapsed = Date.now() - startTime;
                const progress = elapsed / life;

                if (progress >= 1) {
                    confetti.remove();
                    return;
                }

                const newX = x + vx * elapsed / 1000;
                const newY = y + vy * elapsed / 1000 + 0.5 * gravity * Math.pow(elapsed / 1000, 2);

                confetti.style.left = newX + 'px';
                confetti.style.top = newY + 'px';
                confetti.style.opacity = 1 - progress;
                confetti.style.transform = `rotate(${elapsed / 10}deg)`;

                requestAnimationFrame(animate);
            }

            animate();
        }

        // Auto-redirect to dashboard after 10 seconds
        setTimeout(function() {
            const buttons = document.querySelector('.action-buttons');
            const notice = document.createElement('p');
            notice.style.color = '#9ca3af';
            notice.style.fontSize = '0.9rem';
            notice.style.marginTop = '1rem';
            notice.textContent = 'Redirecting to dashboard in 5 seconds...';
            buttons.parentNode.insertBefore(notice, buttons.nextSibling);

            setTimeout(function() {
                window.location.href = '/dashboard.php';
            }, 5000);
        }, 5000);
    </script>
</body>
</html>