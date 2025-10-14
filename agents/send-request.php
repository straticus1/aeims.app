<?php
/**
 * Operator Send Request Interface
 * Allows operators to send chat/call requests to customers
 */

session_start();

if (!isset($_SESSION['operator_id'])) {
    header('Location: /agents/login.php');
    exit;
}

require_once '../services/OperatorManager.php';
require_once '../services/CustomerManager.php';
require_once '../services/OperatorRequestManager.php';

try {
    $operatorManager = new \AEIMS\Services\OperatorManager();
    $customerManager = new \AEIMS\Services\CustomerManager();
    $requestManager = new \AEIMS\Services\OperatorRequestManager();

    $operator = $operatorManager->getOperator($_SESSION['operator_id']);

    if (!$operator) {
        session_destroy();
        header('Location: /agents/login.php');
        exit;
    }

    // Handle request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
        $customerId = $_POST['customer_id'];
        $requestType = $_POST['request_type']; // chat or call
        $message = trim($_POST['message']);
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : null;
        $price = isset($_POST['price']) ? (float)$_POST['price'] : null;

        try {
            $request = $requestManager->createRequest(
                $_SESSION['operator_id'],
                $customerId,
                $requestType,
                $message,
                $duration,
                $price
            );

            $_SESSION['request_success'] = 'Request sent successfully!';
            header('Location: /agents/send-request.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['request_error'] = $e->getMessage();
        }
    }

    // Get list of recent customers (from conversations)
    $conversationsFile = __DIR__ . '/../data/conversations.json';
    $customers = [];

    if (file_exists($conversationsFile)) {
        $conversations = json_decode(file_get_contents($conversationsFile), true);
        $customerIds = [];

        foreach ($conversations as $conv) {
            if ($conv['operator_id'] === $_SESSION['operator_id']) {
                if (!in_array($conv['customer_id'], $customerIds)) {
                    $customerIds[] = $conv['customer_id'];
                    $customer = $customerManager->getCustomer($conv['customer_id']);
                    if ($customer) {
                        $customers[] = $customer;
                    }
                }
            }
        }
    }

    // Get operator's sent requests
    $sentRequests = $requestManager->getOperatorRequests($_SESSION['operator_id']);

} catch (Exception $e) {
    error_log("Send request error: " . $e->getMessage());
    $_SESSION['request_error'] = "An error occurred. Please try again.";
}

$requestSuccess = $_SESSION['request_success'] ?? null;
$requestError = $_SESSION['request_error'] ?? null;
unset($_SESSION['request_success'], $_SESSION['request_error']);

$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostname = preg_replace('/^www\./', '', $hostname);
$hostname = preg_replace('/:\d+$/', '', $hostname);
$operatorDisplayName = $operator['profile']['display_names'][$hostname] ?? $operator['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Request - Operator Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            min-height: 100vh;
        }

        .header {
            background: rgba(0, 0, 0, 0.9);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .operator-badge {
            background: linear-gradient(45deg, #667eea, #764ba2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #667eea;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #fca5a5;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card h2 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #9ca3af;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.15);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .radio-group {
            display: flex;
            gap: 1rem;
        }

        .radio-option {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 10px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .radio-option input[type="radio"] {
            display: none;
        }

        .radio-option input[type="radio"]:checked + label {
            color: #667eea;
        }

        .radio-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.2);
        }

        .customer-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .customer-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .customer-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .customer-item.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.2);
        }

        .customer-item input[type="radio"] {
            margin-right: 0.5rem;
        }

        .request-history {
            margin-top: 2rem;
        }

        .request-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .request-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.3);
            color: #fbbf24;
        }

        .status-accepted {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
        }

        .status-declined {
            background: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .status-expired {
            background: rgba(107, 114, 128, 0.3);
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Operator Dashboard</div>

        <div class="header-actions">
            <div class="operator-badge">
                <?= htmlspecialchars($operatorDisplayName) ?>
            </div>
            <a href="/agents/dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="/agents/operator-messages.php" class="btn btn-secondary">Messages</a>
            <a href="/agents/logout.php" class="btn btn-secondary">Logout</a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">📤 Send Chat/Call Request</h1>

        <?php if ($requestSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($requestSuccess) ?></div>
        <?php endif; ?>

        <?php if ($requestError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($requestError) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <h2>Send New Request</h2>

                <form method="POST" id="requestForm">
                    <div class="form-group">
                        <label>Request Type</label>
                        <div class="radio-group">
                            <div class="radio-option" onclick="selectRequestType('chat', this)">
                                <input type="radio" name="request_type" value="chat" id="type_chat" checked>
                                <label for="type_chat">
                                    <div style="font-size: 2rem; text-align: center;">💬</div>
                                    <div style="text-align: center; margin-top: 0.5rem;">Chat Request</div>
                                </label>
                            </div>
                            <div class="radio-option" onclick="selectRequestType('call', this)">
                                <input type="radio" name="request_type" value="call" id="type_call">
                                <label for="type_call">
                                    <div style="font-size: 2rem; text-align: center;">📞</div>
                                    <div style="text-align: center; margin-top: 0.5rem;">Call Request</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Select Customer</label>
                        <?php if (empty($customers)): ?>
                            <p style="color: #9ca3af;">No customers available. Start chatting with customers first!</p>
                        <?php else: ?>
                            <div class="customer-list">
                                <?php foreach ($customers as $customer): ?>
                                    <div class="customer-item" onclick="selectCustomer('<?= htmlspecialchars($customer['customer_id']) ?>', this)">
                                        <input type="radio" name="customer_id" value="<?= htmlspecialchars($customer['customer_id']) ?>" required>
                                        <?= htmlspecialchars($customer['username']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Personal Message</label>
                        <textarea name="message" class="form-control" placeholder="Add a personal message..." required></textarea>
                    </div>

                    <div class="form-group" id="durationGroup" style="display:none;">
                        <label>Call Duration (minutes)</label>
                        <input type="number" name="duration" class="form-control" min="5" max="120" value="15">
                    </div>

                    <div class="form-group" id="priceGroup" style="display:none;">
                        <label>Price ($)</label>
                        <input type="number" name="price" class="form-control" min="0" step="0.01" value="9.99">
                    </div>

                    <button type="submit" name="send_request" class="btn btn-primary" style="width: 100%;">
                        Send Request
                    </button>
                </form>
            </div>

            <div class="card">
                <h2>Recent Requests</h2>

                <div class="request-history">
                    <?php if (empty($sentRequests)): ?>
                        <p style="color: #9ca3af; text-align: center;">No requests sent yet.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($sentRequests, 0, 10) as $request): ?>
                            <?php
                            $customer = $customerManager->getCustomer($request['customer_id']);
                            $customerName = $customer['username'] ?? 'Unknown';
                            ?>
                            <div class="request-item">
                                <div>
                                    <div style="font-weight: 600;">
                                        <?= $request['type'] === 'call' ? '📞' : '💬' ?>
                                        <?= htmlspecialchars($customerName) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #9ca3af;">
                                        <?= date('M j, g:i A', strtotime($request['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="request-status status-<?= htmlspecialchars($request['status']) ?>">
                                    <?= ucfirst($request['status']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectRequestType(type, element) {
            // Remove selected class from all options
            document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');

            // Check the radio button
            document.getElementById('type_' + type).checked = true;

            // Show/hide duration and price fields for calls
            const durationGroup = document.getElementById('durationGroup');
            const priceGroup = document.getElementById('priceGroup');

            if (type === 'call') {
                durationGroup.style.display = 'block';
                priceGroup.style.display = 'block';
            } else {
                durationGroup.style.display = 'none';
                priceGroup.style.display = 'none';
            }
        }

        function selectCustomer(customerId, element) {
            // Remove selected class from all customers
            document.querySelectorAll('.customer-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');

            // Check the radio button
            element.querySelector('input[type="radio"]').checked = true;
        }

        // Initialize with chat selected
        document.addEventListener('DOMContentLoaded', function() {
            selectRequestType('chat', document.querySelector('.radio-option'));
        });
    </script>
</body>
</html>
