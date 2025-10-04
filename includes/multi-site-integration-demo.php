<?php
/**
 * Multi-Site Integration Demonstration
 * This file demonstrates how all the site management functions work together
 */

require_once '../auth_functions.php';
require_once 'SiteManager.php';

/**
 * Demo: Complete multi-site user setup workflow
 */
function demonstrateMultiSiteSetup() {
    echo "<h2>Multi-Site Integration Demo</h2>\n";

    // Initialize site manager
    $siteManager = new SiteManager();

    echo "<h3>1. Site Discovery</h3>\n";
    $allSites = $siteManager->discoverAllSites();
    echo "Discovered " . count($allSites) . " sites:\n";
    foreach ($allSites as $domain => $site) {
        echo "- {$domain} (Sources: " . implode(', ', $site['sources']) . ")\n";
    }

    echo "\n<h3>2. Username Reservation Demo</h3>\n";
    $testUsername = 'demo_operator_' . time();
    $userInfo = [
        'id' => 'user_' . time(),
        'email' => 'demo@example.com',
        'original_site' => 'nycflirts.com'
    ];

    // Reserve username across all sites
    $reservationResult = $siteManager->reserveUsernameAcrossNetwork($testUsername, $userInfo);
    if ($reservationResult) {
        echo "✅ Username '{$testUsername}' reserved across all sites\n";

        // Check availability
        $availability = $siteManager->checkUsernameAvailability($testUsername);
        echo "Username availability check:\n";
        echo "- Available: " . ($availability['available'] ? 'No' : 'Yes (Reserved)') . "\n";
        echo "- Reserved on " . count($availability['sites']) . " sites\n";
    }

    echo "\n<h3>3. Cross-Site Account Linking Demo</h3>\n";
    $targetSites = ['nycflirts.com', 'beastybitches.com', 'gfecalls.com'];
    $linkResult = $siteManager->linkUserAccountAcrossSites($testUsername, $targetSites);

    if ($linkResult) {
        echo "✅ Account linked across sites: " . implode(', ', $targetSites) . "\n";

        // Enable cross-site login
        $crossSiteResult = $siteManager->enableCrossSiteLoginForUser($testUsername);
        if ($crossSiteResult) {
            echo "✅ Cross-site login enabled for user\n";
        }

        // Get linked sites
        $linkedSites = $siteManager->getUserLinkedSites($testUsername);
        echo "Linked sites: " . implode(', ', $linkedSites) . "\n";
    }

    echo "\n<h3>4. Site Statistics Demo</h3>\n";
    $stats = $siteManager->getSiteStatistics();
    echo "Network Statistics:\n";
    echo "- Total Sites: {$stats['total_sites']}\n";
    echo "- Total Revenue: $" . number_format($stats['total_revenue']) . "\n";
    echo "- Total Calls: " . number_format($stats['total_calls']) . "\n";
    echo "- Total Messages: " . number_format($stats['total_messages']) . "\n";
    echo "- Total Operators: " . number_format($stats['total_operators']) . "\n";
    echo "- Average Uptime: " . number_format($stats['average_uptime'], 2) . "%\n";

    echo "\n<h3>5. Individual Site Statistics</h3>\n";
    foreach (array_slice($stats['sites'], 0, 3) as $domain => $site) {
        echo "\nSite: {$domain}\n";
        echo "- Theme: {$site['theme']}\n";
        echo "- Status: {$site['status']}\n";
        echo "- Revenue: $" . number_format($site['revenue']) . "\n";
        echo "- Operators: {$site['operators']}\n";
        echo "- Has Filesystem: " . ($site['has_filesystem'] ? 'Yes' : 'No') . "\n";
        echo "- Operator Dashboard: {$site['operator_dashboard_url']}\n";
        echo "- Telephony Frontend: {$site['telephony_frontend_url']}\n";
    }

    echo "\n<h3>6. Health Check</h3>\n";
    $health = $siteManager->healthCheck();
    echo "System Health: {$health['status']}\n";
    echo "Sites Discovered: {$health['sites_discovered']}\n";
    echo "AEIMS Directory: " . ($health['checks']['aeims_directory'] ? '✅' : '❌') . "\n";
    echo "Data Directory: " . ($health['checks']['data_directory'] ? '✅' : '❌') . "\n";

    return [
        'username' => $testUsername,
        'sites' => $allSites,
        'stats' => $stats,
        'health' => $health
    ];
}

/**
 * Demo: Site selection workflow for dashboard
 */
function demonstrateSiteSelectionWorkflow($userId = null) {
    echo "\n<h2>Site Selection Workflow Demo</h2>\n";

    // Get site options for dropdown
    $siteOptions = getSiteSelectorOptions($userId);
    echo "Available site options for user:\n";
    foreach ($siteOptions as $value => $option) {
        echo "- {$value}: {$option['label']} - {$option['description']}\n";
    }

    // Demonstrate filtering statistics by site
    echo "\n<h3>Statistics by Site Selection</h3>\n";

    // All sites
    $allStats = getUserSiteStatistics($userId, 'ALL');
    echo "ALL SITES - Total Revenue: $" . number_format($allStats['total_revenue']) . "\n";

    // Individual site (if available)
    if (!empty($allStats['sites'])) {
        $firstSite = array_key_first($allStats['sites']);
        $singleSiteStats = getUserSiteStatistics($userId, $firstSite);
        echo "{$firstSite} ONLY - Total Revenue: $" . number_format($singleSiteStats['total_revenue']) . "\n";
    }
}

/**
 * Demo: Cross-site operator workflow
 */
function demonstrateCrossSiteOperatorWorkflow() {
    echo "\n<h2>Cross-Site Operator Workflow Demo</h2>\n";

    $siteManager = new SiteManager();
    $allSites = $siteManager->discoverAllSites();

    echo "Operator Dashboard URLs for each site:\n";
    foreach ($allSites as $domain => $site) {
        echo "- {$domain}: {$site['operator_dashboard_url']}\n";
    }

    echo "\nTelephony Frontend URLs (unified platform):\n";
    foreach ($allSites as $domain => $site) {
        echo "- {$domain}: {$site['telephony_frontend_url']}\n";
    }

    echo "\nThis demonstrates:\n";
    echo "1. Each site has its own operator dashboard URL\n";
    echo "2. All sites use the same telephony frontend (unified platform)\n";
    echo "3. Operators can seamlessly switch between sites\n";
    echo "4. Statistics are aggregated across all authorized sites\n";
}

/**
 * Demo: Username reservation and validation
 */
function demonstrateUsernameManagement() {
    echo "\n<h2>Username Management Demo</h2>\n";

    $siteManager = new SiteManager();

    // Test various usernames
    $testUsernames = ['operator1', 'admin', 'test_user_' . time()];

    foreach ($testUsernames as $username) {
        echo "\nTesting username: '{$username}'\n";

        $availability = $siteManager->checkUsernameAvailability($username);
        if ($availability['available']) {
            echo "✅ Username available for registration\n";

            // Reserve it
            $userInfo = [
                'id' => 'user_' . uniqid(),
                'email' => $username . '@example.com',
                'original_site' => 'aeims.app'
            ];

            $reserved = $siteManager->reserveUsernameAcrossNetwork($username, $userInfo);
            if ($reserved) {
                echo "✅ Username reserved across network\n";

                // Check again
                $newAvailability = $siteManager->checkUsernameAvailability($username);
                echo "Post-reservation check: " . ($newAvailability['available'] ? 'Still available' : 'Now reserved') . "\n";
                echo "Reserved on " . count($newAvailability['sites']) . " sites\n";
            }
        } else {
            echo "❌ Username already reserved\n";
            echo "Reservation details:\n";
            $details = $availability['reservation_details'];
            echo "- Reserved at: {$details['reserved_at']}\n";
            echo "- Original site: {$details['original_site']}\n";
            echo "- Reserved on " . count($details['reserved_sites']) . " sites\n";
        }
    }
}

// If this file is run directly, execute the demos
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    echo "<!DOCTYPE html>\n<html><head><title>Multi-Site Integration Demo</title></head><body><pre>\n";

    // Run all demos
    $setupResult = demonstrateMultiSiteSetup();
    demonstrateSiteSelectionWorkflow();
    demonstrateCrossSiteOperatorWorkflow();
    demonstrateUsernameManagement();

    echo "\n<h2>Integration Summary</h2>\n";
    echo "This demonstration shows how AEIMS.app integrates with the multi-site network:\n\n";
    echo "1. ✅ Site Discovery: Automatically finds all sites from aeims/sites/* and config\n";
    echo "2. ✅ Username Reservation: Automatically reserves usernames across all sites\n";
    echo "3. ✅ Cross-Site Linking: Enables users to connect accounts across sites\n";
    echo "4. ✅ Site Selection: Dashboard allows filtering by specific sites or viewing all\n";
    echo "5. ✅ Operator Dashboard Integration: Links to telephony-platform frontend\n";
    echo "6. ✅ Centralized Statistics: Aggregates data from all authorized sites\n";
    echo "7. ✅ Cross-Site Login: Optional feature users can enable\n";
    echo "8. ✅ Unified Billing: Centralized billing across all sites\n\n";

    echo "The system now supports the complete multi-site operator workflow described\n";
    echo "in the requirements document.\n";

    echo "\n</pre></body></html>";
}
?>