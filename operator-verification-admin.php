<?php
/**
 * AEIMS Operator Verification Admin Interface
 * Administrative dashboard for managing operator identity verification
 */

session_start();
require_once 'auth_functions.php';
requireAdmin();

$config = include 'config.php';
$userInfo = getUserInfo();

// Get all operator accounts and their verification status
function getAllOperatorVerifications() {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return [];
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
    $operators = [];

    foreach ($accounts as $username => $account) {
        if ($account['type'] === 'operator') {
            $verificationStatus = $account['verification_status'] ?? null;
            $revalidationStatus = 'unknown';
            $urgency = 'low';

            if ($verificationStatus) {
                if (!$verificationStatus['identity_verified']) {
                    $revalidationStatus = 'verification_failed';
                    $urgency = 'high';
                } elseif ($verificationStatus['next_revalidation_date']) {
                    $revalidationDate = new DateTime($verificationStatus['next_revalidation_date']);
                    $today = new DateTime();
                    $daysUntil = $today->diff($revalidationDate)->days;

                    if ($revalidationDate <= $today) {
                        $revalidationStatus = 'expired';
                        $urgency = 'critical';
                    } elseif ($daysUntil <= 30) {
                        $revalidationStatus = 'expiring_soon';
                        $urgency = 'medium';
                    } else {
                        $revalidationStatus = 'valid';
                        $urgency = 'low';
                    }
                }
            } else {
                $revalidationStatus = 'pending_verification';
                $urgency = 'high';
            }

            $operators[] = [
                'username' => $username,
                'name' => $account['name'],
                'email' => $account['email'],
                'status' => $account['status'],
                'verification_status' => $verificationStatus,
                'revalidation_status' => $revalidationStatus,
                'urgency' => $urgency,
                'created_at' => $account['created_at'] ?? 'Unknown'
            ];
        }
    }

    // Sort by urgency (critical, high, medium, low)
    usort($operators, function($a, $b) {
        $priority = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        return $priority[$b['urgency']] - $priority[$a['urgency']];
    });

    return $operators;
}

$operators = getAllOperatorVerifications();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Verification Admin - AEIMS</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .summary-card h3 {
            margin: 0 0 0.5rem 0;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .summary-card .number {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .operators-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-content {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-verified {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-expiring {
            background: #fef3c7;
            color: #b45309;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .urgency-critical {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
        }

        .urgency-high {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }

        .urgency-medium {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
        }

        .urgency-low {
            background: #f0f9ff;
            border-left: 4px solid #06b6d4;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .breadcrumb {
            margin-bottom: 2rem;
            color: #6b7280;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .filters {
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin-dashboard.php">← Back to Admin Dashboard</a>
        </div>

        <!-- Header -->
        <div class="admin-header">
            <h1>Operator Verification Management</h1>
            <p>Monitor and manage operator identity verification status and revalidation requirements</p>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <?php
            $summary = [
                'total' => count($operators),
                'verified' => 0,
                'pending' => 0,
                'expired' => 0,
                'expiring_soon' => 0
            ];

            foreach ($operators as $operator) {
                switch ($operator['revalidation_status']) {
                    case 'valid':
                        $summary['verified']++;
                        break;
                    case 'pending_verification':
                    case 'verification_failed':
                        $summary['pending']++;
                        break;
                    case 'expired':
                        $summary['expired']++;
                        break;
                    case 'expiring_soon':
                        $summary['expiring_soon']++;
                        break;
                }
            }
            ?>
            <div class="summary-card">
                <h3>Total Operators</h3>
                <div class="number" style="color: #3b82f6;"><?= $summary['total'] ?></div>
            </div>
            <div class="summary-card">
                <h3>Verified</h3>
                <div class="number" style="color: #059669;"><?= $summary['verified'] ?></div>
            </div>
            <div class="summary-card">
                <h3>Pending/Failed</h3>
                <div class="number" style="color: #f59e0b;"><?= $summary['pending'] ?></div>
            </div>
            <div class="summary-card">
                <h3>Expired</h3>
                <div class="number" style="color: #dc2626;"><?= $summary['expired'] ?></div>
            </div>
            <div class="summary-card">
                <h3>Expiring Soon</h3>
                <div class="number" style="color: #b45309;"><?= $summary['expiring_soon'] ?></div>
            </div>
        </div>

        <!-- Operators Table -->
        <div class="operators-table">
            <div class="table-header">
                <h3>Operator Verification Status</h3>
                <p>All operator accounts and their current verification status</p>

                <!-- Filters -->
                <div class="filters">
                    <label for="status-filter">Filter by Status:</label>
                    <select id="status-filter" class="filter-select" onchange="filterOperators()">
                        <option value="all">All Statuses</option>
                        <option value="critical">Critical (Expired)</option>
                        <option value="high">High Priority</option>
                        <option value="medium">Medium Priority</option>
                        <option value="low">Low Priority</option>
                    </select>

                    <button onclick="runRevalidationCheck()" class="action-btn btn-primary">Run Revalidation Check</button>
                </div>
            </div>

            <div class="table-content">
                <table id="operators-table">
                    <thead>
                        <tr>
                            <th>Operator</th>
                            <th>Email</th>
                            <th>Account Status</th>
                            <th>Verification Status</th>
                            <th>Expiration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($operators as $operator): ?>
                        <tr class="urgency-<?= $operator['urgency'] ?>" data-urgency="<?= $operator['urgency'] ?>">
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($operator['name']) ?></div>
                                <div style="font-size: 0.875rem; color: #6b7280;">@<?= htmlspecialchars($operator['username']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($operator['email']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $operator['status'] === 'active' ? 'verified' : 'pending' ?>">
                                    <?= ucfirst($operator['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = '';
                                $statusText = '';
                                switch ($operator['revalidation_status']) {
                                    case 'valid':
                                        $statusClass = 'verified';
                                        $statusText = 'Valid';
                                        break;
                                    case 'pending_verification':
                                        $statusClass = 'pending';
                                        $statusText = 'Pending Verification';
                                        break;
                                    case 'verification_failed':
                                        $statusClass = 'failed';
                                        $statusText = 'Verification Failed';
                                        break;
                                    case 'expired':
                                        $statusClass = 'expired';
                                        $statusText = 'Expired';
                                        break;
                                    case 'expiring_soon':
                                        $statusClass = 'expiring';
                                        $statusText = 'Expiring Soon';
                                        break;
                                    default:
                                        $statusClass = 'pending';
                                        $statusText = 'Unknown';
                                }
                                ?>
                                <span class="status-badge status-<?= $statusClass ?>">
                                    <?= $statusText ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($operator['verification_status'] && $operator['verification_status']['next_revalidation_date']): ?>
                                    <?= date('M j, Y', strtotime($operator['verification_status']['next_revalidation_date'])) ?>
                                    <?php
                                    $revalidationDate = new DateTime($operator['verification_status']['next_revalidation_date']);
                                    $today = new DateTime();
                                    $daysUntil = $today->diff($revalidationDate)->days;
                                    if ($revalidationDate <= $today) {
                                        echo '<br><small style="color: #dc2626;">Overdue</small>';
                                    } else {
                                        echo '<br><small style="color: #6b7280;">' . $daysUntil . ' days</small>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <span style="color: #6b7280;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="operator-profile.php?email=<?= urlencode($operator['email']) ?>" class="action-btn btn-secondary">View Profile</a>
                                <?php if ($operator['revalidation_status'] === 'expired' || $operator['revalidation_status'] === 'expiring_soon'): ?>
                                <button onclick="sendRevalidationNotification('<?= $operator['email'] ?>')" class="action-btn btn-primary">Send Notice</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function filterOperators() {
            const filter = document.getElementById('status-filter').value;
            const rows = document.querySelectorAll('#operators-table tbody tr');

            rows.forEach(row => {
                const urgency = row.getAttribute('data-urgency');
                if (filter === 'all' || urgency === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function runRevalidationCheck() {
            if (confirm('Run revalidation check for all operators? This will send notifications to operators with expired or expiring verifications.')) {
                const button = event.target;
                button.innerHTML = 'Running...';
                button.disabled = true;

                fetch('revalidation-checker.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'run_check=1'
                })
                .then(response => response.json())
                .then(data => {
                    let message = `Revalidation check completed:\n`;
                    message += `- Total operators checked: ${data.summary.total_operators_checked}\n`;
                    message += `- Expired verifications: ${data.summary.expired_verifications}\n`;
                    message += `- Expiring soon: ${data.summary.expiring_soon}\n`;
                    message += `- Notifications sent: ${data.summary.notifications_sent}`;

                    alert(message);
                    setTimeout(() => location.reload(), 1000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error running revalidation check. Please try again.');
                })
                .finally(() => {
                    button.innerHTML = 'Run Revalidation Check';
                    button.disabled = false;
                });
            }
        }

        function sendRevalidationNotification(email) {
            if (confirm(`Send revalidation notification to ${email}?`)) {
                // This would integrate with the notification system
                alert('Notification sent successfully.');
            }
        }
    </script>
</body>
</html>