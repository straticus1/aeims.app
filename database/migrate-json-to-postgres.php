<?php
/**
 * Migrate JSON data to PostgreSQL
 * This script safely migrates all existing JSON data to the database
 */

require_once __DIR__ . '/../load-env.php';
require_once __DIR__ . '/../includes/DatabaseManager.php';
require_once __DIR__ . '/../includes/DataLayer.php';

echo "==============================================\n";
echo "AEIMS JSON to PostgreSQL Migration\n";
echo "==============================================\n\n";

$db = DatabaseManager::getInstance();
$dataLayer = getDataLayer();

if (!$db->isAvailable()) {
    die("ERROR: Database is not available. Check your connection settings.\n");
}

echo "✓ Database connection successful\n\n";

// Track migration stats
$stats = [
    'customers' => ['total' => 0, 'migrated' => 0, 'errors' => 0],
    'operators' => ['total' => 0, 'migrated' => 0, 'errors' => 0],
    'sites' => ['total' => 0, 'migrated' => 0, 'errors' => 0],
];

// ==============================================================================
// 1. Migrate Sites
// ==============================================================================
echo "1. Migrating Sites...\n";
$sitesFile = __DIR__ . '/../data/sites.json';
if (file_exists($sitesFile)) {
    $sitesData = json_decode(file_get_contents($sitesFile), true);
    $sites = $sitesData['sites'] ?? [];
    $stats['sites']['total'] = count($sites);

    foreach ($sites as $site) {
        try {
            // Check if site already exists
            $existing = $db->fetchOne("SELECT site_id FROM sites WHERE domain = :domain", ['domain' => $site['domain']]);
            if ($existing) {
                echo "   - Skipping {$site['domain']} (already exists)\n";
                continue;
            }

            // Insert site
            $db->insert('sites', [
                'site_id' => $site['site_id'],
                'domain' => $site['domain'],
                'name' => $site['name'],
                'description' => $site['description'] ?? null,
                'template' => $site['template'] ?? 'default',
                'active' => $site['active'] ?? true,
                'categories' => json_encode($site['categories'] ?? []),
                'theme' => json_encode($site['theme'] ?? []),
                'features' => json_encode($site['features'] ?? []),
                'billing_config' => json_encode($site['billing_config'] ?? []),
                'metadata' => json_encode($site['metadata'] ?? [])
            ]);

            $stats['sites']['migrated']++;
            echo "   ✓ Migrated site: {$site['name']}\n";
        } catch (Exception $e) {
            $stats['sites']['errors']++;
            echo "   ✗ Error migrating site {$site['name']}: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   - No sites.json file found\n";
}
echo "\n";

// ==============================================================================
// 2. Migrate Customers
// ==============================================================================
echo "2. Migrating Customers...\n";
$customersFile = __DIR__ . '/../data/customers.json';
if (file_exists($customersFile)) {
    $customersData = json_decode(file_get_contents($customersFile), true);
    $customers = $customersData['customers'] ?? [];
    $stats['customers']['total'] = count($customers);

    foreach ($customers as $customerId => $customer) {
        try {
            // Check if customer already exists
            $existing = $db->fetchOne("SELECT customer_id FROM customers WHERE username = :username",
                ['username' => $customer['username']]);
            if ($existing) {
                echo "   - Skipping {$customer['username']} (already exists)\n";
                continue;
            }

            // Insert customer - generate UUID if needed
            $customerUuid = $customer['customer_id'] ?? $customerId;
            // If not a valid UUID, use the key as-is (sites use string IDs)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $customerUuid)) {
                // Keep the existing ID - PostgreSQL will use it as varchar
                $customerUuid = $customerId;
            }
            $db->insert('customers', [
                'customer_id' => $customerUuid,
                'username' => $customer['username'],
                'email' => $customer['email'],
                'password_hash' => $customer['password_hash'],
                'display_name' => $customer['profile']['display_name'] ?? $customer['username'],
                'bio' => $customer['profile']['bio'] ?? null,
                'avatar_url' => $customer['profile']['avatar_url'] ?? null,
                'age_verified' => $customer['profile']['age_verified'] ?? false,
                'active' => $customer['active'] ?? true,
                'verified' => $customer['verified'] ?? false,
                'registration_ip' => $customer['registration_ip'] ?? null,
                'created_at' => $customer['created_at'] ?? date('Y-m-d H:i:s'),
                'credits' => $customer['billing']['credits'] ?? 0.00,
                'total_spent' => $customer['billing']['total_spent'] ?? 0.00,
                'preferences' => json_encode($customer['profile']['preferences'] ?? []),
                'metadata' => json_encode($customer['metadata'] ?? [])
            ]);

            // Insert customer-site relationships
            $sites = $customer['sites'] ?? [$customer['site_domain']];
            foreach ($sites as $siteDomain) {
                try {
                    $db->insert('customer_sites', [
                        'customer_id' => $customerUuid,
                        'site_id' => $siteDomain
                    ]);
                } catch (Exception $e) {
                    // Ignore duplicate errors
                }
            }

            $stats['customers']['migrated']++;
            echo "   ✓ Migrated customer: {$customer['username']}\n";
        } catch (Exception $e) {
            $stats['customers']['errors']++;
            echo "   ✗ Error migrating customer {$customer['username']}: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   - No customers.json file found\n";
}
echo "\n";

// ==============================================================================
// 3. Migrate Operators
// ==============================================================================
echo "3. Migrating Operators...\n";
$operatorsFile = __DIR__ . '/../data/operators.json';
if (file_exists($operatorsFile)) {
    $operatorsData = json_decode(file_get_contents($operatorsFile), true);
    $operators = $operatorsData['operators'] ?? [];
    $stats['operators']['total'] = count($operators);

    foreach ($operators as $operatorId => $operator) {
        try {
            // Generate username from name or use email
            $username = isset($operator['username']) ? $operator['username'] :
                        (isset($operator['name']) ? strtolower(str_replace(' ', '', $operator['name'])) :
                        explode('@', $operator['email'])[0]);

            // Check if operator already exists
            $existing = $db->fetchOne("SELECT operator_id FROM operators WHERE email = :email",
                ['email' => $operator['email']]);
            if ($existing) {
                echo "   - Skipping {$username} (already exists)\n";
                continue;
            }

            // Insert operator
            $operatorUuid = $operator['id'] ?? "op_" . $operatorId;

            // Handle boolean fields - check status field
            $active = ($operator['status'] ?? 'active') === 'active';
            $available = ($operator['settings']['availability']['status'] ?? 'offline') === 'online';

            $db->insert('operators', [
                'operator_id' => $operatorUuid,
                'username' => $username,
                'email' => $operator['email'],
                'password_hash' => $operator['password_hash'],
                'display_name' => $operator['name'] ?? $username,
                'bio' => $operator['profile']['bio'] ?? null,
                'age' => $operator['profile']['age'] ?? null,
                'location' => $operator['profile']['location'] ?? null,
                'avatar_url' => $operator['profile']['avatar_url'] ?? null,
                'active' => $active,
                'available' => $available,
                'created_at' => $operator['created_at'] ?? date('Y-m-d H:i:s'),
                'total_earned' => $operator['earnings']['lifetime_total'] ?? 0.00,
                'pending_payout' => $operator['earnings']['pending'] ?? 0.00,
                'metadata' => json_encode($operator ?? [])
            ]);

            $stats['operators']['migrated']++;
            echo "   ✓ Migrated operator: {$username}\n";
        } catch (Exception $e) {
            $stats['operators']['errors']++;
            $username = $operator['name'] ?? $operatorId;
            echo "   ✗ Error migrating operator {$username}: " . $e->getMessage() . "\n";
        }
    }
} else {
    echo "   - No operators.json file found\n";
}
echo "\n";

// ==============================================================================
// Migration Summary
// ==============================================================================
echo "==============================================\n";
echo "Migration Summary\n";
echo "==============================================\n\n";

foreach ($stats as $type => $data) {
    echo ucfirst($type) . ":\n";
    echo "   Total: {$data['total']}\n";
    echo "   Migrated: {$data['migrated']}\n";
    echo "   Errors: {$data['errors']}\n";
    echo "   Success Rate: " . ($data['total'] > 0 ? round(($data['migrated'] / $data['total']) * 100, 1) : 0) . "%\n\n";
}

$totalMigrated = $stats['customers']['migrated'] + $stats['operators']['migrated'] + $stats['sites']['migrated'];
$totalErrors = $stats['customers']['errors'] + $stats['operators']['errors'] + $stats['sites']['errors'];

echo "Overall:\n";
echo "   Total Records Migrated: $totalMigrated\n";
echo "   Total Errors: $totalErrors\n\n";

if ($totalErrors === 0) {
    echo "✓ Migration completed successfully!\n\n";
    echo "Next steps:\n";
    echo "1. Verify data in PostgreSQL\n";
    echo "2. Test authentication and core features\n";
    echo "3. Enable DUAL_WRITE=false in .env to use PostgreSQL as primary\n";
} else {
    echo "⚠ Migration completed with errors. Review the errors above.\n\n";
}
