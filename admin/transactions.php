<?php
/**
 * Admin Transaction Management Panel
 * Visibility into all transactions, chargebacks, refunds, and statistics
 */

session_start();

require_once __DIR__ . '/../includes/auth_functions.php';
requireAdmin();

require_once __DIR__ . '/../services/PaymentManager.php';
require_once __DIR__ . '/../services/CustomerManager.php';

$paymentManager = new \AEIMS\Services\PaymentManager();
$customerManager = new \AEIMS\Services\CustomerManager();

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_chargeback') {
            $chargeback = $paymentManager->createChargeback(
                $_POST['transaction_id'],
                $_POST['reason'],
                (float)$_POST['amount'],
                ['notes' => $_POST['notes'] ?? '']
            );
            $message = "Chargeback created: {$chargeback['chargeback_id']}";
        } elseif ($action === 'resolve_chargeback') {
            $chargeback = $paymentManager->resolveChargeback(
                $_POST['chargeback_id'],
                $_POST['resolution'],
                $_POST['notes'] ?? ''
            );
            $message = "Chargeback resolved: {$_POST['resolution']}";
        } elseif ($action === 'refund_transaction') {
            $transaction = $paymentManager->refundTransaction(
                $_POST['transaction_id'],
                $_POST['reason'] ?? ''
            );
            $message = "Transaction refunded: {$transaction['transaction_id']}";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get filters
$dateRange = $_GET['date_range'] ?? 'week';
$status = $_GET['status'] ?? null;

// Get data
$stats = $paymentManager->getTransactionStats();
$chargebacks = $paymentManager->getAllChargebacks();
$pendingChargebacks = array_filter($chargebacks, fn($cb) => $cb['status'] === 'pending');

$processors = $paymentManager->getPaymentProcessors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - AEIMS Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 2rem;
        }

        .header {
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            color: #e74c3c;
            margin-bottom: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .nav-link {
            color: #3498db;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 5px;
            transition: all 0.3s;
        }

        .nav-link:hover {
            background: rgba(52, 152, 219, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #aaa;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            color: #e74c3c;
            font-weight: bold;
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section h2 {
            color: #e74c3c;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th {
            background: rgba(231, 76, 60, 0.2);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #fff;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status.completed { background: rgba(46, 204, 113, 0.3); color: #2ecc71; }
        .status.pending { background: rgba(241, 196, 15, 0.3); color: #f1c40f; }
        .status.failed { background: rgba(231, 76, 60, 0.3); color: #e74c3c; }
        .status.chargeback { background: rgba(155, 89, 182, 0.3); color: #9b59b6; }
        .status.refunded { background: rgba(52, 152, 219, 0.3); color: #3498db; }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 0.25rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid rgba(46, 204, 113, 0.5);
            color: #2ecc71;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1a1a2e;
            padding: 2rem;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #aaa;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: #fff;
            font-family: inherit;
        }

        .processor-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .processor-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .processor-card strong {
            color: #e74c3c;
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>💳 Transaction Management</h1>
        <div class="nav-links">
            <a href="/admin-dashboard.php" class="nav-link">← Dashboard</a>
            <a href="#" class="nav-link">Transactions</a>
            <a href="#chargebacks" class="nav-link">Chargebacks</a>
            <a href="#stats" class="nav-link">Statistics</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Transactions</h3>
            <div class="value"><?= $stats['total_transactions'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Revenue</h3>
            <div class="value">$<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending Chargebacks</h3>
            <div class="value"><?= count($pendingChargebacks) ?></div>
        </div>
        <div class="stat-card">
            <h3>Refunded Amount</h3>
            <div class="value">$<?= number_format($stats['refunded_amount'] ?? 0, 2) ?></div>
        </div>
    </div>

    <div class="section">
        <h2>Payment Processors</h2>
        <div class="processor-list">
            <?php foreach ($processors as $key => $name): ?>
                <div class="processor-card">
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <small><?= $key ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section" id="chargebacks">
        <h2>Recent Chargebacks</h2>
        <?php if (empty($chargebacks)): ?>
            <p style="color: #aaa;">No chargebacks recorded.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Chargeback ID</th>
                        <th>Transaction ID</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($chargebacks, 0, 20) as $cb): ?>
                        <tr>
                            <td><?= htmlspecialchars($cb['chargeback_id']) ?></td>
                            <td><?= htmlspecialchars($cb['transaction_id']) ?></td>
                            <td>$<?= number_format($cb['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($cb['reason']) ?></td>
                            <td><span class="status <?= $cb['status'] ?>"><?= ucfirst($cb['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($cb['created_at'])) ?></td>
                            <td>
                                <?php if ($cb['status'] === 'pending'): ?>
                                    <button class="btn btn-primary" onclick="resolveChargeback('<?= $cb['chargeback_id'] ?>')">Resolve</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="section" id="stats">
        <h2>Transaction Statistics</h2>
        <table>
            <tr>
                <td><strong>Successful Transactions</strong></td>
                <td><?= $stats['successful_count'] ?? 0 ?></td>
                <td>$<?= number_format($stats['successful_amount'] ?? 0, 2) ?></td>
            </tr>
            <tr>
                <td><strong>Failed Transactions</strong></td>
                <td><?= $stats['failed_count'] ?? 0 ?></td>
                <td>$<?= number_format($stats['failed_amount'] ?? 0, 2) ?></td>
            </tr>
            <tr>
                <td><strong>Chargebacks</strong></td>
                <td><?= $stats['chargeback_count'] ?? 0 ?></td>
                <td>$<?= number_format($stats['chargeback_amount'] ?? 0, 2) ?></td>
            </tr>
            <tr>
                <td><strong>Refunds</strong></td>
                <td><?= $stats['refund_count'] ?? 0 ?></td>
                <td>$<?= number_format($stats['refund_amount'] ?? 0, 2) ?></td>
            </tr>
        </table>
    </div>

    <!-- Resolve Chargeback Modal -->
    <div id="resolveModal" class="modal">
        <div class="modal-content">
            <h2>Resolve Chargeback</h2>
            <form method="POST">
                <input type="hidden" name="action" value="resolve_chargeback">
                <input type="hidden" name="chargeback_id" id="resolve_chargeback_id">

                <div class="form-group">
                    <label>Resolution</label>
                    <select name="resolution" required>
                        <option value="won">Won (No action needed)</option>
                        <option value="lost">Lost (Deduct credits)</option>
                        <option value="partial">Partial (Manual adjustment)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Add resolution notes..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Resolution</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function resolveChargeback(chargebackId) {
            document.getElementById('resolve_chargeback_id').value = chargebackId;
            document.getElementById('resolveModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('resolveModal').classList.remove('active');
        }
    </script>
</body>
</html>
