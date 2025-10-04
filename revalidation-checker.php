<?php
/**
 * AEIMS Revalidation Checker
 * Automated system to check for expired/expiring ID verifications
 * Can be run as a cron job or called manually
 */

$config = include 'config.php';

/**
 * Check all operator accounts for revalidation requirements
 */
function checkAllRevalidations() {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return ['error' => 'No accounts file found'];
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];
    $results = [
        'total_checked' => 0,
        'expired' => [],
        'expiring_soon' => [],
        'notifications_sent' => 0
    ];

    foreach ($accounts as $username => $account) {
        if ($account['type'] !== 'operator') {
            continue;
        }

        $results['total_checked']++;
        $verificationStatus = $account['verification_status'] ?? null;

        if (!$verificationStatus || !$verificationStatus['identity_verified']) {
            continue;
        }

        $revalidationResult = checkRevalidationStatus($verificationStatus, $account);

        if ($revalidationResult['status'] === 'expired') {
            $results['expired'][] = [
                'username' => $username,
                'name' => $account['name'],
                'email' => $account['email'],
                'expired_date' => $revalidationResult['expired_date'],
                'days_overdue' => $revalidationResult['days_overdue']
            ];

            // Send urgent notification
            sendRevalidationNotification($account, 'expired', $revalidationResult);
            $results['notifications_sent']++;

        } elseif ($revalidationResult['status'] === 'expiring_soon') {
            $results['expiring_soon'][] = [
                'username' => $username,
                'name' => $account['name'],
                'email' => $account['email'],
                'expiring_date' => $revalidationResult['expiring_date'],
                'days_remaining' => $revalidationResult['days_remaining']
            ];

            // Send reminder notification
            sendRevalidationNotification($account, 'expiring_soon', $revalidationResult);
            $results['notifications_sent']++;
        }
    }

    return $results;
}

/**
 * Check individual revalidation status
 */
function checkRevalidationStatus($verificationStatus, $account) {
    if (!$verificationStatus['next_revalidation_date']) {
        return ['status' => 'no_expiration'];
    }

    $revalidationDate = new DateTime($verificationStatus['next_revalidation_date']);
    $today = new DateTime();
    $interval = $today->diff($revalidationDate);

    if ($revalidationDate <= $today) {
        // Expired
        return [
            'status' => 'expired',
            'expired_date' => $verificationStatus['next_revalidation_date'],
            'days_overdue' => $interval->days
        ];
    } elseif ($interval->days <= 30) {
        // Expiring within 30 days
        return [
            'status' => 'expiring_soon',
            'expiring_date' => $verificationStatus['next_revalidation_date'],
            'days_remaining' => $interval->days
        ];
    }

    return ['status' => 'valid'];
}

/**
 * Send revalidation notifications
 */
function sendRevalidationNotification($account, $type, $details) {
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $notification = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'revalidation_notification',
        'urgency' => $type === 'expired' ? 'high' : 'medium',
        'recipient_name' => $account['name'],
        'recipient_email' => $account['email'],
        'notification_type' => $type,
        'details' => $details
    ];

    if ($type === 'expired') {
        $notification['subject'] = 'URGENT: AEIMS Identity Verification Expired';
        $notification['message'] = "Your AEIMS operator identity verification has expired as of {$details['expired_date']}. " .
                                  "Your operator account has been suspended until you complete revalidation. " .
                                  "Please log in immediately and submit new verification documents.";

        // Update account status to suspended
        updateAccountStatus($account['email'], 'suspended');

    } elseif ($type === 'expiring_soon') {
        $notification['subject'] = 'AEIMS Identity Verification Expiring Soon';
        $notification['message'] = "Your AEIMS operator identity verification will expire on {$details['expiring_date']} " .
                                  "({$details['days_remaining']} days from now). " .
                                  "Please log in and submit new verification documents before this date to avoid account suspension.";
    }

    // Log notification for admin review
    file_put_contents($logDir . '/revalidation_notifications.log', json_encode($notification) . "\n", FILE_APPEND);

    // In production, send actual email notifications here
    logEmail($notification);

    return true;
}

/**
 * Update account status based on verification expiration
 */
function updateAccountStatus($email, $newStatus) {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];

    foreach ($accounts as $username => &$account) {
        if (strtolower($account['email']) === strtolower($email)) {
            $account['status'] = $newStatus;
            $account['status_updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * Log email for sending (in production, integrate with actual email service)
 */
function logEmail($notification) {
    $logDir = __DIR__ . '/data';
    $emailLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'to' => $notification['recipient_email'],
        'subject' => $notification['subject'],
        'message' => $notification['message'],
        'urgency' => $notification['urgency'],
        'type' => 'revalidation_notification'
    ];

    file_put_contents($logDir . '/email_queue.log', json_encode($emailLog) . "\n", FILE_APPEND);
}

/**
 * Generate revalidation report
 */
function generateRevalidationReport() {
    $results = checkAllRevalidations();

    $report = [
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_operators_checked' => $results['total_checked'],
            'expired_verifications' => count($results['expired']),
            'expiring_soon' => count($results['expiring_soon']),
            'notifications_sent' => $results['notifications_sent']
        ],
        'expired_operators' => $results['expired'],
        'expiring_soon_operators' => $results['expiring_soon']
    ];

    // Save report
    $reportsDir = __DIR__ . '/data/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }

    $reportFile = $reportsDir . '/revalidation_report_' . date('Y-m-d') . '.json';
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

    return $report;
}

/**
 * CLI interface for running checks
 */
if (php_sapi_name() === 'cli') {
    echo "AEIMS Revalidation Checker\n";
    echo "=========================\n\n";

    $report = generateRevalidationReport();

    echo "Revalidation Check Complete:\n";
    echo "- Total operators checked: {$report['summary']['total_operators_checked']}\n";
    echo "- Expired verifications: {$report['summary']['expired_verifications']}\n";
    echo "- Expiring soon: {$report['summary']['expiring_soon']}\n";
    echo "- Notifications sent: {$report['summary']['notifications_sent']}\n\n";

    if (!empty($report['expired_operators'])) {
        echo "EXPIRED VERIFICATIONS (URGENT):\n";
        foreach ($report['expired_operators'] as $operator) {
            echo "- {$operator['name']} ({$operator['email']}) - {$operator['days_overdue']} days overdue\n";
        }
        echo "\n";
    }

    if (!empty($report['expiring_soon_operators'])) {
        echo "EXPIRING SOON:\n";
        foreach ($report['expiring_soon_operators'] as $operator) {
            echo "- {$operator['name']} ({$operator['email']}) - {$operator['days_remaining']} days remaining\n";
        }
        echo "\n";
    }

    echo "Report saved to: data/reports/revalidation_report_" . date('Y-m-d') . ".json\n";
}

/**
 * Web interface for manual checks
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    header('Content-Type: application/json');
    echo json_encode(generateRevalidationReport());
    exit;
}

// Return functions for inclusion in other files
return [
    'checkAllRevalidations' => 'checkAllRevalidations',
    'checkRevalidationStatus' => 'checkRevalidationStatus',
    'generateRevalidationReport' => 'generateRevalidationReport'
];
?>