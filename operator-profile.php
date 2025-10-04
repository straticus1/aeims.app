<?php
/**
 * AEIMS Operator Profile - Verification Status Interface
 * Allows PSOs to check their validation status and revalidation requirements
 */

require_once 'auth_functions.php';
requireLogin();

$config = include 'config.php';
$user = getCurrentUser();
$userInfo = getUserInfo();

// Get operator verification status
$verificationStatus = getOperatorVerificationStatus($userInfo['email']);
$revalidationRequired = checkRevalidationRequired($verificationStatus);

function getOperatorVerificationStatus($email) {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return null;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];

    foreach ($accounts as $account) {
        if (strtolower($account['email']) === strtolower($email)) {
            return $account['verification_status'] ?? null;
        }
    }

    return null;
}

function checkRevalidationRequired($verificationStatus) {
    if (!$verificationStatus || !$verificationStatus['next_revalidation_date']) {
        return false;
    }

    $revalidationDate = new DateTime($verificationStatus['next_revalidation_date']);
    $today = new DateTime();
    $daysUntilRevalidation = $today->diff($revalidationDate)->days;

    // Require revalidation if within 30 days or past due
    return $revalidationDate <= $today->modify('+30 days');
}

function getVerificationBadge($status) {
    if (!$status) {
        return ['class' => 'badge-pending', 'icon' => '⏳', 'text' => 'Pending Verification'];
    }

    if ($status['identity_verified']) {
        return ['class' => 'badge-verified', 'icon' => '✅', 'text' => 'Identity Verified'];
    } else {
        return ['class' => 'badge-failed', 'icon' => '❌', 'text' => 'Verification Failed'];
    }
}

function getRevalidationAlert($verificationStatus) {
    if (!$verificationStatus || !$verificationStatus['next_revalidation_date']) {
        return null;
    }

    $revalidationDate = new DateTime($verificationStatus['next_revalidation_date']);
    $today = new DateTime();
    $daysUntilRevalidation = $today->diff($revalidationDate)->days;

    if ($revalidationDate <= $today) {
        return ['type' => 'error', 'message' => 'Your ID verification has expired. Please resubmit your verification documents immediately.'];
    } elseif ($daysUntilRevalidation <= 30) {
        return ['type' => 'warning', 'message' => "Your ID verification expires in {$daysUntilRevalidation} days. Please prepare to resubmit your verification documents."];
    }

    return null;
}

$badge = getVerificationBadge($verificationStatus);
$alert = getRevalidationAlert($verificationStatus);

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
    <title>Operator Profile - <?php echo $config['site']['name']; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }

        .profile-email {
            opacity: 0.9;
            font-size: 1rem;
        }

        .verification-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .verification-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .badge-verified {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .verification-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .detail-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 500;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .nav-link {
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 0;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .nav-link:hover {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .nav-breadcrumb {
            margin-bottom: 2rem;
            color: #6b7280;
        }

        .nav-breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .nav-breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="dashboard-body">
    <div class="profile-container">
        <!-- Breadcrumb -->
        <div class="nav-breadcrumb">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <h1 class="profile-name"><?php echo htmlspecialchars($userInfo['name']); ?></h1>
            <div class="profile-email"><?php echo htmlspecialchars($userInfo['email']); ?></div>
        </div>

        <!-- Revalidation Alert -->
        <?php if ($alert): ?>
        <div class="alert alert-<?php echo $alert['type']; ?>">
            <span><?php echo $alert['type'] === 'error' ? '⚠️' : '💡'; ?></span>
            <span><?php echo htmlspecialchars($alert['message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Verification Status Card -->
        <div class="verification-card">
            <div class="verification-title">
                🛡️ Identity Verification Status
            </div>

            <div class="verification-badge <?php echo $badge['class']; ?>">
                <span><?php echo $badge['icon']; ?></span>
                <span><?php echo $badge['text']; ?></span>
            </div>

            <?php if ($verificationStatus): ?>
            <div class="verification-details">
                <div class="detail-item">
                    <div class="detail-label">Verification Date</div>
                    <div class="detail-value">
                        <?php echo $verificationStatus['verification_date'] ?
                              date('M j, Y g:i A', strtotime($verificationStatus['verification_date'])) :
                              'Not verified'; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Verification Method</div>
                    <div class="detail-value">
                        <?php echo $verificationStatus['verification_method'] ?
                              ucfirst(str_replace('_', ' ', $verificationStatus['verification_method'])) :
                              'None'; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Verification Confidence</div>
                    <div class="detail-value">
                        <?php if ($verificationStatus['verification_confidence']): ?>
                            <?php echo round($verificationStatus['verification_confidence'] * 100, 1); ?>%
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">ID Expiration Date</div>
                    <div class="detail-value">
                        <?php echo $verificationStatus['id_expiration_date'] ?
                              date('M j, Y', strtotime($verificationStatus['id_expiration_date'])) :
                              'Not available'; ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Next Revalidation</div>
                    <div class="detail-value">
                        <?php if ($verificationStatus['next_revalidation_date']): ?>
                            <?php
                            $revalidationDate = new DateTime($verificationStatus['next_revalidation_date']);
                            $today = new DateTime();
                            $daysUntil = $today->diff($revalidationDate)->days;

                            echo date('M j, Y', strtotime($verificationStatus['next_revalidation_date']));
                            if ($revalidationDate > $today) {
                                echo " ({$daysUntil} days)";
                            } else {
                                echo " (Overdue)";
                            }
                            ?>
                        <?php else: ?>
                            Not required
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($verificationStatus['verification_notes']): ?>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <div class="detail-label">Verification Notes</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($verificationStatus['verification_notes']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="margin-top: 1.5rem; padding: 1rem; background: #f1f5f9; border-radius: 8px; color: #64748b;">
                No verification information available. Please complete the operator verification process.
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="actions">
                <?php if (!$verificationStatus || !$verificationStatus['identity_verified'] || $revalidationRequired): ?>
                <a href="identity-verification.php" class="btn btn-primary">
                    <?php echo $verificationStatus && $verificationStatus['identity_verified'] ? 'Resubmit Verification' : 'Complete Verification'; ?>
                </a>
                <?php endif; ?>

                <a href="dashboard.php" class="btn btn-outline">Return to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>