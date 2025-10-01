<?php
/**
 * System Health Check - AEIMS Integration Status
 * Tests connectivity and integration with AEIMS and aeimsLib systems
 */

require_once 'includes/AeimsIntegration.php';
require_once 'includes/AeimsApiClient.php';
require_once 'includes/AeimsLibIntegration.php';

$config = include 'config.php';

// Initialize all integration classes
$healthReport = [
    'timestamp' => date('c'),
    'aeims' => null,
    'aeims_api' => null,
    'aeims_lib' => null,
    'overall_status' => 'unknown'
];

// Test AEIMS CLI Integration
try {
    $aeims = new AeimsIntegration();
    $healthReport['aeims'] = [
        'available' => $aeims->isAeimsAvailable(),
        'health_check' => $aeims->healthCheck(),
        'real_stats' => $aeims->getRealStats(),
        'system_status' => $aeims->getSystemStatus()
    ];
} catch (Exception $e) {
    $healthReport['aeims'] = [
        'available' => false,
        'error' => $e->getMessage()
    ];
}

// Test AEIMS API Integration
try {
    $aeimsApi = new AeimsApiClient();
    $healthReport['aeims_api'] = [
        'available' => $aeimsApi->isAvailable(),
        'system_info' => $aeimsApi->getSystemInfo(),
        'health' => $aeimsApi->health()
    ];
} catch (Exception $e) {
    $healthReport['aeims_api'] = [
        'available' => false,
        'error' => $e->getMessage()
    ];
}

// Test aeimsLib Integration
try {
    $aeimsLib = new AeimsLibIntegration();
    $healthReport['aeims_lib'] = [
        'available' => $aeimsLib->isAvailable(),
        'system_info' => $aeimsLib->getSystemInfo()
    ];
} catch (Exception $e) {
    $healthReport['aeims_lib'] = [
        'available' => false,
        'error' => $e->getMessage()
    ];
}

// Determine overall status
$aeimsOk = $healthReport['aeims']['available'] ?? false;
$apiOk = $healthReport['aeims_api']['available'] ?? false;
$libOk = $healthReport['aeims_lib']['available'] ?? false;

if ($aeimsOk && $apiOk && $libOk) {
    $healthReport['overall_status'] = 'fully_operational';
} elseif ($aeimsOk || $apiOk) {
    $healthReport['overall_status'] = 'partially_operational';
} else {
    $healthReport['overall_status'] = 'degraded';
}

// Handle JSON API request
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($healthReport, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - AEIMS Integration Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .header h1 {
            color: #ef4444;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .overall-status {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
        }

        .status-fully_operational {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 2px solid #22c55e;
        }

        .status-partially_operational {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 2px solid #fbbf24;
        }

        .status-degraded {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 2px solid #ef4444;
        }

        .systems-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .system-card {
            background: rgba(0, 0, 0, 0.4);
            padding: 30px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .system-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .system-title {
            font-size: 1.5rem;
            color: #ef4444;
        }

        .system-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-available {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-unavailable {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .system-details {
            margin-top: 20px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #a0a0a0;
        }

        .detail-value {
            color: #e0e0e0;
            font-weight: 500;
        }

        .json-output {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            overflow-x: auto;
        }

        .json-output pre {
            color: #e0e0e0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-primary {
            background: #ef4444;
            color: white;
        }

        .btn-primary:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        @media (max-width: 768px) {
            .systems-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AEIMS System Health Check</h1>
            <p>Integration status for AEIMS, API, and aeimsLib systems</p>
            <div class="overall-status status-<?= $healthReport['overall_status'] ?>">
                <?= str_replace('_', ' ', $healthReport['overall_status']) ?>
            </div>
            <div style="margin-top: 10px; color: #a0a0a0;">
                Last checked: <?= date('M j, Y g:i:s A', strtotime($healthReport['timestamp'])) ?>
            </div>
        </div>

        <div class="systems-grid">
            <!-- AEIMS CLI System -->
            <div class="system-card">
                <div class="system-header">
                    <h2 class="system-title">AEIMS CLI System</h2>
                    <div class="system-status status-<?= $healthReport['aeims']['available'] ? 'available' : 'unavailable' ?>">
                        <?= $healthReport['aeims']['available'] ? 'Available' : 'Unavailable' ?>
                    </div>
                </div>

                <?php if (isset($healthReport['aeims']['error'])): ?>
                    <div class="error-message">
                        Error: <?= htmlspecialchars($healthReport['aeims']['error']) ?>
                    </div>
                <?php else: ?>
                    <div class="system-details">
                        <div class="detail-item">
                            <span class="detail-label">CLI Available:</span>
                            <span class="detail-value"><?= $healthReport['aeims']['health_check']['aeims_available'] ? '✅ Yes' : '❌ No' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">CLI Path:</span>
                            <span class="detail-value"><?= htmlspecialchars($healthReport['aeims']['health_check']['cli_path']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sites Powered:</span>
                            <span class="detail-value"><?= $healthReport['aeims']['real_stats']['sites_powered'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">System Uptime:</span>
                            <span class="detail-value"><?= $healthReport['aeims']['real_stats']['uptime'] ?>%</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cross-site Operators:</span>
                            <span class="detail-value"><?= $healthReport['aeims']['real_stats']['cross_site_operators'] ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- AEIMS API System -->
            <div class="system-card">
                <div class="system-header">
                    <h2 class="system-title">AEIMS REST API</h2>
                    <div class="system-status status-<?= $healthReport['aeims_api']['available'] ? 'available' : 'unavailable' ?>">
                        <?= $healthReport['aeims_api']['available'] ? 'Available' : 'Unavailable' ?>
                    </div>
                </div>

                <?php if (isset($healthReport['aeims_api']['error'])): ?>
                    <div class="error-message">
                        Error: <?= htmlspecialchars($healthReport['aeims_api']['error']) ?>
                    </div>
                <?php else: ?>
                    <div class="system-details">
                        <div class="detail-item">
                            <span class="detail-label">API Available:</span>
                            <span class="detail-value"><?= $healthReport['aeims_api']['system_info']['api_available'] ? '✅ Yes' : '❌ No' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Base URL:</span>
                            <span class="detail-value"><?= htmlspecialchars($healthReport['aeims_api']['system_info']['base_url']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Has API Key:</span>
                            <span class="detail-value"><?= $healthReport['aeims_api']['system_info']['has_api_key'] ? '✅ Yes' : '❌ No' ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- aeimsLib Device Control -->
            <div class="system-card">
                <div class="system-header">
                    <h2 class="system-title">aeimsLib Device Control</h2>
                    <div class="system-status status-<?= $healthReport['aeims_lib']['available'] ? 'available' : 'unavailable' ?>">
                        <?= $healthReport['aeims_lib']['available'] ? 'Available' : 'Unavailable' ?>
                    </div>
                </div>

                <?php if (isset($healthReport['aeims_lib']['error'])): ?>
                    <div class="error-message">
                        Error: <?= htmlspecialchars($healthReport['aeims_lib']['error']) ?>
                    </div>
                <?php else: ?>
                    <div class="system-details">
                        <div class="detail-item">
                            <span class="detail-label">Library Available:</span>
                            <span class="detail-value"><?= $healthReport['aeims_lib']['system_info']['available'] ? '✅ Yes' : '❌ No' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">WebSocket Server:</span>
                            <span class="detail-value"><?= $healthReport['aeims_lib']['system_info']['websocket_status']['running'] ? '✅ Running' : '❌ Stopped' ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Connected Devices:</span>
                            <span class="detail-value"><?= $healthReport['aeims_lib']['system_info']['device_stats']['connected_devices'] ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Supported Brands:</span>
                            <span class="detail-value"><?= count($healthReport['aeims_lib']['system_info']['supported_devices']['stable']) + count($healthReport['aeims_lib']['system_info']['supported_devices']['experimental']) ?>+ brands</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="actions">
            <a href="?format=json" class="btn btn-secondary">View JSON Report</a>
            <a href="?" class="btn btn-primary">Refresh Status</a>
            <a href="admin.php" class="btn btn-secondary">Admin Panel</a>
            <a href="index.php" class="btn btn-secondary">Back to Site</a>
        </div>

        <div class="json-output">
            <h3 style="color: #ef4444; margin-bottom: 15px;">Raw Health Data</h3>
            <pre><?= json_encode($healthReport, JSON_PRETTY_PRINT) ?></pre>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            if (!window.location.search.includes('format=json')) {
                window.location.reload();
            }
        }, 30000);
        
        // Add some interactivity
        document.querySelectorAll('.system-card').forEach(card => {
            card.addEventListener('click', () => {
                card.style.transform = card.style.transform ? '' : 'scale(1.02)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 200);
            });
        });
    </script>
</body>
</html>