<?php
/**
 * Operator Profile Page
 * Displays detailed operator profile with categories, pricing, and services
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
require_once '../../services/OperatorManager.php';
require_once '../../services/ToyManager.php';

$operatorId = $_GET['id'] ?? '';

if (!$operatorId) {
    header('Location: /dashboard.php');
    exit;
}

try {
    $siteManager = new \AEIMS\Services\SiteManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $toyManager = new \AEIMS\Services\ToyManager();

    $site = $siteManager->getSite('flirts.nyc');
    $customer = $customerManager->getCustomer($_SESSION['customer_id']);
    $operator = $operatorManager->getOperator($operatorId);
    $operatorToys = $toyManager->getOperatorToys($operatorId);

    if (!$site || !$site['active'] || !$customer || !$operator) {
        header('Location: /dashboard.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Operator profile error: " . $e->getMessage());
    header('Location: /dashboard.php');
    exit;
}

// Get operator's active categories based on site categories
$operatorCategories = [];
foreach ($site['categories'] as $key => $category) {
    if ($category['active'] && in_array($key, $operator['profile']['specialties'] ?? [])) {
        $operatorCategories[$key] = $category['name'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($operator['username']) ?> - <?= htmlspecialchars($site['name']) ?></title>
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
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(239, 68, 68, 0.3);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: <?= $site['theme']['primary_color'] ?>;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            color: <?= $site['theme']['text_color'] ?>;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: <?= $site['theme']['primary_color'] ?>;
        }

        .main-content {
            margin-top: 80px;
            padding: 2rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .profile-header {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
            backdrop-filter: blur(10px);
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['accent_color'] ?>);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: white;
        }

        .profile-info h1 {
            font-size: 2.5rem;
            color: <?= $site['theme']['primary_color'] ?>;
            margin-bottom: 0.5rem;
        }

        .profile-stats {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-badge {
            background: rgba(239, 68, 68, 0.2);
            color: <?= $site['theme']['primary_color'] ?>;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .online-status {
            background: #22c55e;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .main-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
        }

        .section-card h2 {
            color: <?= $site['theme']['primary_color'] ?>;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .category-badge {
            background: linear-gradient(45deg, <?= $site['theme']['primary_color'] ?>, <?= $site['theme']['secondary_color'] ?>);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .toys-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .toy-card {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .toy-card h4 {
            color: <?= $site['theme']['accent_color'] ?>;
            margin-bottom: 0.5rem;
        }

        .toy-price {
            color: #22c55e;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(10px);
            text-align: center;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
            width: 100%;
            margin-bottom: 1rem;
            font-size: 1rem;
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
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .pricing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .pricing-table th,
        .pricing-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pricing-table th {
            color: <?= $site['theme']['primary_color'] ?>;
            font-weight: 600;
        }

        .price-amount {
            color: #22c55e;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 1rem;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="nav-container">
            <a href="/dashboard.php" class="logo"><?= htmlspecialchars($site['name']) ?></a>

            <div class="nav-links">
                <a href="/dashboard.php">← Back to Browse</a>
                <span>Credits: $<?= number_format($customer['billing']['credits'], 2) ?></span>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(substr($operator['username'], 0, 2)) ?>
            </div>

            <div class="profile-info">
                <h1><?= htmlspecialchars($operator['username']) ?></h1>
                <p><?= htmlspecialchars($operator['profile']['bio']) ?></p>

                <div class="profile-stats">
                    <span class="stat-badge"><?= ucfirst($operator['category']) ?> Model</span>
                    <span class="stat-badge">Age: <?= $operator['profile']['age'] ?></span>
                    <span class="stat-badge"><?= $operator['profile']['location'] ?></span>
                    <?php foreach ($operator['profile']['languages'] as $lang): ?>
                        <span class="stat-badge"><?= htmlspecialchars($lang) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="online-status">
                <div class="status-dot"></div>
                Online Now
            </div>
        </div>

        <div class="content-grid">
            <div class="main-section">
                <div class="section-card">
                    <h2>Specializes In</h2>
                    <p>Explore the categories this model excels in:</p>

                    <div class="categories-grid">
                        <?php foreach ($operatorCategories as $key => $name): ?>
                            <div class="category-badge">
                                <?= htmlspecialchars($name) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($operatorToys)): ?>
                <div class="section-card">
                    <h2>Interactive Toys</h2>
                    <p>Control <?= htmlspecialchars($operator['username']) ?>'s interactive toys during your session:</p>

                    <div class="toys-grid">
                        <?php foreach ($operatorToys as $toy): ?>
                            <div class="toy-card">
                                <h4><?= htmlspecialchars($toy['nickname'] ?? $toy['toy_id']) ?></h4>
                                <p class="toy-price">$<?= number_format($toy['per_minute_rate'], 2) ?>/min</p>
                                <p><small>Interactive control available</small></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="section-card">
                    <h2>Pricing & Services</h2>
                    <table class="pricing-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Rate</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Text Chat</td>
                                <td class="price-amount">$<?= number_format($site['billing']['message_rates']['standard'], 2) ?>/msg</td>
                                <td>Private messaging</td>
                            </tr>
                            <tr>
                                <td>Voice Call</td>
                                <td class="price-amount">$<?= number_format($site['billing']['call_rates']['standard'], 2) ?>/min</td>
                                <td>Private voice conversation</td>
                            </tr>
                            <tr>
                                <td>Video Call</td>
                                <td class="price-amount">$<?= number_format($site['billing']['call_rates']['premium'], 2) ?>/min</td>
                                <td>Private video session</td>
                            </tr>
                            <tr>
                                <td>Custom Content</td>
                                <td class="price-amount">$<?= number_format($site['billing']['content_rates']['custom'], 2) ?>+</td>
                                <td>Personalized photos/videos</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sidebar">
                <div class="action-card">
                    <h3 style="color: <?= $site['theme']['primary_color'] ?>; margin-bottom: 1rem;">Start Session</h3>

                    <a href="/chat.php?operator_id=<?= $operator['operator_id'] ?>" class="btn btn-primary">
                        💬 Start Chat
                    </a>

                    <button class="btn btn-secondary" onclick="startVoiceCall('<?= $operator['operator_id'] ?>')">
                        📞 Voice Call
                    </button>

                    <button class="btn btn-secondary" onclick="startVideoCall('<?= $operator['operator_id'] ?>')">
                        📹 Video Call
                    </button>

                    <?php if (!empty($operatorToys)): ?>
                    <button class="btn btn-primary" onclick="startToySession('<?= $operator['operator_id'] ?>')">
                        🎮 Control Toys
                    </button>
                    <?php endif; ?>
                </div>

                <div class="action-card">
                    <h3 style="color: <?= $site['theme']['primary_color'] ?>; margin-bottom: 1rem;">Your Credits</h3>
                    <p style="font-size: 1.5rem; color: #22c55e; margin-bottom: 1rem;">
                        $<?= number_format($customer['billing']['credits'], 2) ?>
                    </p>

                    <button class="btn btn-secondary" onclick="addCredits()">
                        💳 Add Credits
                    </button>
                </div>

                <div class="action-card">
                    <h3 style="color: <?= $site['theme']['primary_color'] ?>; margin-bottom: 1rem;">Quick Actions</h3>

                    <button class="btn btn-secondary" onclick="sendTip('<?= $operator['operator_id'] ?>')">
                        💝 Send Tip
                    </button>

                    <button class="btn btn-secondary" onclick="requestCustom('<?= $operator['operator_id'] ?>')">
                        🎨 Request Custom
                    </button>

                    <button class="btn btn-secondary" onclick="addToFavorites('<?= $operator['operator_id'] ?>')">
                        ⭐ Add to Favorites
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        const customerCredits = <?= $customer['billing']['credits'] ?>;
        const operatorId = '<?= $operator['operator_id'] ?>';

        function checkCredits(requiredAmount, serviceName) {
            if (customerCredits < requiredAmount) {
                alert(`Insufficient credits for ${serviceName}! You need $${requiredAmount.toFixed(2)} but only have $${customerCredits.toFixed(2)}.`);
                return false;
            }
            return true;
        }

        function startChat(operatorId) {
            if (!checkCredits(<?= $site['billing']['message_rates']['standard'] ?>, 'chat')) return;
            alert('Chat system integration coming soon!');
        }

        function startVoiceCall(operatorId) {
            if (!checkCredits(<?= $site['billing']['call_rates']['standard'] ?>, 'voice call')) return;
            alert('Voice call system integration coming soon!');
        }

        function startVideoCall(operatorId) {
            if (!checkCredits(<?= $site['billing']['call_rates']['premium'] ?>, 'video call')) return;
            alert('Video call system integration coming soon!');
        }

        function startToySession(operatorId) {
            if (!checkCredits(2.00, 'toy control')) return;
            alert('Interactive toy control coming soon!');
        }

        function addCredits() {
            alert('Payment system integration coming soon!');
        }

        function sendTip(operatorId) {
            const tip = prompt('Enter tip amount:');
            if (tip && parseFloat(tip) > 0) {
                if (!checkCredits(parseFloat(tip), 'tip')) return;
                alert(`Tip system integration coming soon! You wanted to tip $${tip}`);
            }
        }

        function requestCustom(operatorId) {
            if (!checkCredits(<?= $site['billing']['content_rates']['custom'] ?>, 'custom content')) return;
            alert('Custom content request system coming soon!');
        }

        function addToFavorites(operatorId) {
            alert('Favorites system integration coming soon!');
        }
    </script>
</body>
</html>