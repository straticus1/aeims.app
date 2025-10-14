#!/usr/bin/env php
<?php
/**
 * AEIMS Authentication Testing CLI Utility
 * Usage: php test-auth.php [command] [options]
 */

// Ensure we're running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Colors for CLI output
function color($text, $color) {
    $colors = [
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'cyan' => "\033[0;36m",
        'reset' => "\033[0m"
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function success($msg) {
    echo color("✓ ", 'green') . $msg . "\n";
}

function error($msg) {
    echo color("✗ ", 'red') . $msg . "\n";
}

function info($msg) {
    echo color("ℹ ", 'blue') . $msg . "\n";
}

function warning($msg) {
    echo color("⚠ ", 'yellow') . $msg . "\n";
}

function section_header($msg) {
    echo "\n" . color("=== $msg ===", 'cyan') . "\n\n";
}

// Parse command line arguments
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'test':
        section_header("Testing Authentication");

        $site = $argv[2] ?? 'flirts.nyc';
        $username = $argv[3] ?? '';
        $password = $argv[4] ?? '';

        if (empty($username) || empty($password)) {
            error("Usage: php test-auth.php test [site] [username] [password]");
            exit(1);
        }

        testLogin($site, $username, $password);
        break;

    case 'list':
        section_header("List All Customers");
        listCustomers();
        break;

    case 'verify':
        section_header("Verify Password");

        $username = $argv[2] ?? '';
        $password = $argv[3] ?? '';

        if (empty($username) || empty($password)) {
            error("Usage: php test-auth.php verify [username] [password]");
            exit(1);
        }

        verifyPassword($username, $password);
        break;

    case 'create':
        section_header("Create New Customer");

        $username = $argv[2] ?? '';
        $email = $argv[3] ?? '';
        $password = $argv[4] ?? '';
        $site = $argv[5] ?? 'flirts.nyc';

        if (empty($username) || empty($email) || empty($password)) {
            error("Usage: php test-auth.php create [username] [email] [password] [site]");
            exit(1);
        }

        createCustomer($username, $email, $password, $site);
        break;

    case 'curl':
        section_header("cURL Test Authentication");

        $site = $argv[2] ?? 'flirts.nyc';
        $username = $argv[3] ?? '';
        $password = $argv[4] ?? '';

        if (empty($username) || empty($password)) {
            error("Usage: php test-auth.php curl [site] [username] [password]");
            exit(1);
        }

        curlTest($site, $username, $password);
        break;

    case 'help':
    default:
        showHelp();
        break;
}

function testLogin($site, $username, $password) {
    info("Testing login for: $username on $site");

    // Load CustomerAuth
    require_once __DIR__ . '/includes/CustomerAuth.php';

    // Start session for testing
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new CustomerAuth($site);
    $result = $auth->authenticate($username, $password);

    if ($result['success']) {
        success("Authentication successful!");

        if (isset($_SESSION['customer_id'])) {
            success("Session created - customer_id: " . $_SESSION['customer_id']);
        } else {
            error("Session NOT created - customer_id missing!");
        }

        if (isset($_SESSION['customer_data'])) {
            success("Customer data stored in session");
            echo "  Username: " . ($_SESSION['customer_data']['username'] ?? 'N/A') . "\n";
            echo "  Email: " . ($_SESSION['customer_data']['email'] ?? 'N/A') . "\n";
        } else {
            error("Customer data NOT in session!");
        }

        info("Redirect: " . ($result['redirect'] ?? 'none'));
    } else {
        error("Authentication failed: " . ($result['message'] ?? 'Unknown error'));
    }
}

function listCustomers() {
    $dataFile = __DIR__ . '/data/customers.json';

    if (!file_exists($dataFile)) {
        error("Customers file not found: $dataFile");
        return;
    }

    $data = json_decode(file_get_contents($dataFile), true);
    $customers = $data['customers'] ?? [];

    if (empty($customers)) {
        warning("No customers found");
        return;
    }

    info("Found " . count($customers) . " customers:\n");

    foreach ($customers as $customerId => $customer) {
        echo color($customer['username'], 'cyan') . " (" . $customerId . ")\n";
        echo "  Email: " . ($customer['email'] ?? 'N/A') . "\n";
        echo "  Active: " . (($customer['active'] ?? true) ? 'Yes' : 'No') . "\n";
        echo "  Site: " . ($customer['site_domain'] ?? 'N/A') . "\n";
        echo "  Created: " . ($customer['created_at'] ?? 'N/A') . "\n";

        if (isset($customer['password_plaintext_for_testing'])) {
            warning("  Test password: " . $customer['password_plaintext_for_testing']);
        }

        echo "\n";
    }
}

function verifyPassword($username, $password) {
    info("Verifying password for: $username");

    $dataFile = __DIR__ . '/data/customers.json';
    $data = json_decode(file_get_contents($dataFile), true);
    $customers = $data['customers'] ?? [];

    $customer = null;
    foreach ($customers as $cust) {
        if (($cust['username'] ?? '') === $username || ($cust['email'] ?? '') === $username) {
            $customer = $cust;
            break;
        }
    }

    if (!$customer) {
        error("Customer not found: $username");
        return;
    }

    success("Customer found: " . $customer['customer_id']);
    info("Password hash: " . $customer['password_hash']);

    if (password_verify($password, $customer['password_hash'])) {
        success("Password is CORRECT!");
    } else {
        error("Password is INCORRECT!");

        if (isset($customer['password_plaintext_for_testing'])) {
            info("Expected password: " . $customer['password_plaintext_for_testing']);
        }
    }
}

function createCustomer($username, $email, $password, $site) {
    info("Creating customer: $username");

    $dataFile = __DIR__ . '/data/customers.json';
    $data = json_decode(file_get_contents($dataFile), true);
    $customers = $data['customers'] ?? [];

    // Check if exists
    foreach ($customers as $cust) {
        if (($cust['username'] ?? '') === $username) {
            error("Username already exists: $username");
            return;
        }
        if (($cust['email'] ?? '') === $email) {
            error("Email already exists: $email");
            return;
        }
    }

    $customerId = 'cust_' . bin2hex(random_bytes(8));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $customer = [
        'customer_id' => $customerId,
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
        'password_plaintext_for_testing' => $password,
        'site_domain' => $site,
        'active' => true,
        'verified' => false,
        'created_at' => date('c'),
        'billing' => [
            'credits' => 10.00
        ],
        'profile' => [
            'display_name' => $username
        ]
    ];

    $customers[$customerId] = $customer;
    $data['customers'] = $customers;

    if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT))) {
        success("Customer created successfully!");
        success("Customer ID: $customerId");
        success("Username: $username");
        success("Email: $email");
        success("Password: $password");
        success("Site: $site");
    } else {
        error("Failed to save customer data");
    }
}

function curlTest($site, $username, $password) {
    info("Testing authentication via cURL");
    info("Site: https://$site");
    info("Username: $username");

    $url = "https://$site/auth.php";
    $postData = http_build_query([
        'action' => 'login',
        'username' => $username,
        'password' => $password
    ]);

    $cmd = sprintf(
        'curl -s -i -c /tmp/test_cookies.txt -b /tmp/test_cookies.txt -X POST %s -d %s',
        escapeshellarg($url),
        escapeshellarg($postData)
    );

    info("Running command:");
    echo "  $cmd\n\n";

    $output = shell_exec($cmd . ' 2>&1');

    // Parse response
    $lines = explode("\n", $output);
    $statusCode = '';
    $location = '';
    $setCookie = '';

    foreach ($lines as $line) {
        if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $line, $matches)) {
            $statusCode = $matches[1];
        }
        if (preg_match('/^Location: (.+)/', $line, $matches)) {
            $location = trim($matches[1]);
        }
        if (preg_match('/^Set-Cookie: (.+)/', $line, $matches)) {
            $setCookie = trim($matches[1]);
        }
    }

    echo "\n";

    if ($statusCode) {
        if ($statusCode == '302' || $statusCode == '303') {
            success("Status Code: $statusCode (Redirect)");
        } else if ($statusCode == '200') {
            warning("Status Code: $statusCode (OK - check if redirect expected)");
        } else {
            error("Status Code: $statusCode");
        }
    }

    if ($location) {
        if (strpos($location, 'dashboard') !== false) {
            success("Location: $location (Dashboard redirect - GOOD!)");
        } else if (strpos($location, 'login') !== false || strpos($location, '/') === 0 && strlen($location) == 1) {
            error("Location: $location (Failed login - redirected to home/login)");
        } else {
            info("Location: $location");
        }
    } else {
        warning("No Location header (may not be redirecting)");
    }

    if ($setCookie) {
        if (strpos($setCookie, 'PHPSESSID') !== false) {
            success("Cookie Set: Session created");
        } else {
            info("Cookie Set: $setCookie");
        }
    } else {
        warning("No session cookie set");
    }

    echo "\n";
    info("Full response:");
    echo "─────────────────────────────────────────\n";
    echo $output;
    echo "\n─────────────────────────────────────────\n";
}

function showHelp() {
    echo color("AEIMS Authentication Testing CLI", 'cyan') . "\n\n";
    echo "Usage: php test-auth.php [command] [options]\n\n";
    echo color("Commands:", 'yellow') . "\n\n";

    echo "  " . color("test", 'green') . " [site] [username] [password]\n";
    echo "    Test authentication locally using PHP session\n";
    echo "    Example: php test-auth.php test flirts.nyc flirtyuser password123\n\n";

    echo "  " . color("curl", 'green') . " [site] [username] [password]\n";
    echo "    Test authentication via cURL (simulates real HTTP request)\n";
    echo "    Example: php test-auth.php curl flirts.nyc flirtyuser password123\n\n";

    echo "  " . color("list", 'green') . "\n";
    echo "    List all customers in the system\n";
    echo "    Example: php test-auth.php list\n\n";

    echo "  " . color("verify", 'green') . " [username] [password]\n";
    echo "    Verify if a password matches the stored hash\n";
    echo "    Example: php test-auth.php verify flirtyuser password123\n\n";

    echo "  " . color("create", 'green') . " [username] [email] [password] [site]\n";
    echo "    Create a new customer account\n";
    echo "    Example: php test-auth.php create testuser test@example.com pass123 flirts.nyc\n\n";

    echo "  " . color("help", 'green') . "\n";
    echo "    Show this help message\n\n";
}
