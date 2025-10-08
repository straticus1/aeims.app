#!/usr/bin/env php
<?php
/**
 * AEIMS Log Rotation Script
 * To be run daily via cron: 0 2 * * * /var/www/aeims/scripts/rotate-logs.php
 */

require_once dirname(__DIR__) . '/includes/Logger.php';

echo "Starting AEIMS log rotation...\n";

try {
    AeimsLogger::rotateLogs();
    echo "Log rotation completed successfully.\n";

    // Log the rotation event
    aeims_log_system('log_rotation', 'Daily log rotation completed');

} catch (Exception $e) {
    echo "Error during log rotation: " . $e->getMessage() . "\n";
    aeims_log_error('Log rotation failed', $e);
    exit(1);
}

echo "Done.\n";
?>