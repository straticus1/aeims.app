<?php
/**
 * AEIMS Account Manager CLI
 * Command-line tool for managing user accounts, migrations, and system administration
 *
 * Usage:
 *   php cli/account-manager.php user:create --username=admin --email=admin@aeims.app --role=admin
 *   php cli/account-manager.php user:list --role=operator
 *   php cli/account-manager.php user:lock --username=badactor
 *   php cli/account-manager.php user:unlock --username=gooduser
 *   php cli/account-manager.php user:delete --username=inactive --confirm
 *   php cli/account-manager.php user:reset-password --username=forgotuser
 *   php cli/account-manager.php migrate:json-to-db --file=data/accounts.json
 *   php cli/account-manager.php db:health
 */

// Bootstrap
require_once __DIR__ . '/../includes/DatabaseManager.php';
require_once __DIR__ . '/../includes/SecurityManager.php';

class AccountManagerCLI {
    private $db;
    private $security;
    private $command;
    private $args = [];

    public function __construct($argv) {
        $this->db = DatabaseManager::getInstance();
        $this->security = SecurityManager::getInstance();

        // Parse command and arguments
        $this->command = $argv[1] ?? 'help';

        // Parse --key=value arguments
        for ($i = 2; $i < count($argv); $i++) {
            if (str starts_with($argv[$i], '--')) {
                $parts = explode('=', substr($argv[$i], 2), 2);
                $this->args[$parts[0]] = $parts[1] ?? true;
            }
        }
    }

    public function run() {
        $this->printHeader();

        try {
            switch ($this->command) {
                case 'user:create':
                    return $this->createUser();
                case 'user:list':
                    return $this->listUsers();
                case 'user:show':
                    return $this->showUser();
                case 'user:update':
                    return $this->updateUser();
                case 'user:lock':
                    return $this->lockUser();
                case 'user:unlock':
                    return $this->unlockUser();
                case 'user:delete':
                    return $this->deleteUser();
                case 'user:reset-password':
                    return $this->resetPassword();
                case 'migrate:json-to-db':
                    return $this->migrateJSONToDB();
                case 'db:health':
                    return $this->dbHealth();
                case 'db:init':
                    return $this->dbInit();
                case 'help':
                default:
                    return $this->showHelp();
            }
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    private function createUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');
        $email = $this->args['email'] ?? $this->prompt('Email');
        $role = $this->args['role'] ?? $this->prompt('Role (customer/operator/admin)', 'customer');
        $password = $this->args['password'] ?? $this->promptPassword('Password (leave empty for random)');

        if (empty($password)) {
            $password = bin2hex(random_bytes(8));
            $this->info("Generated password: $password");
        }

        // Validate password
        $validation = $this->security->validatePassword($password);
        if (!$validation['valid']) {
            $this->error("Password not strong enough:");
            foreach ($validation['errors'] as $error) {
                $this->error("  - $error");
            }
            return 1;
        }

        // Create user
        $userId = $this->db->createUser($username, $email, $password, $role);

        if ($userId) {
            $this->success("✓ User created successfully!");
            $this->info("  ID: $userId");
            $this->info("  Username: $username");
            $this->info("  Email: $email");
            $this->info("  Role: $role");
            return 0;
        } else {
            $this->error("Failed to create user");
            return 1;
        }
    }

    private function listUsers() {
        $role = $this->args['role'] ?? null;
        $status = $this->args['status'] ?? null;
        $limit = $this->args['limit'] ?? 100;

        $this->info("Listing users...");
        if ($role) $this->info("  Role filter: $role");
        if ($status) $this->info("  Status filter: $status");
        echo "\n";

        $users = $this->db->listUsers($role, $status, $limit);

        if (empty($users)) {
            $this->warning("No users found");
            return 0;
        }

        // Print table
        $this->printTable(
            ['ID', 'Username', 'Email', 'Role', 'Status', 'Created', 'Last Login'],
            array_map(function($user) {
                return [
                    $user['id'],
                    $user['username'],
                    $user['email'],
                    $user['role'],
                    $user['status'],
                    substr($user['created_at'], 0, 10),
                    $user['last_login'] ? substr($user['last_login'], 0, 10) : 'Never'
                ];
            }, $users)
        );

        $this->info("\nTotal: " . count($users) . " users");
        return 0;
    }

    private function showUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');

        $user = $this->db->getUserByUsername($username);

        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        $this->success("User Details:");
        echo "\n";
        foreach ($user as $key => $value) {
            if ($key === 'password_hash') continue;
            $this->info(sprintf("  %-20s: %s", $key, $value ?? 'N/A'));
        }

        return 0;
    }

    private function updateUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');

        $user = $this->db->getUserByUsername($username);
        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        $updates = [];

        if (isset($this->args['email'])) {
            $updates['email'] = $this->args['email'];
        }

        if (isset($this->args['role'])) {
            $updates['role'] = $this->args['role'];
        }

        if (isset($this->args['status'])) {
            $updates['status'] = $this->args['status'];
        }

        if (empty($updates)) {
            $this->error("No updates specified. Use --email, --role, or --status");
            return 1;
        }

        $rowsAffected = $this->db->updateUser($user['id'], $updates);

        if ($rowsAffected > 0) {
            $this->success("✓ User updated successfully!");
            foreach ($updates as $key => $value) {
                $this->info("  $key: $value");
            }
            return 0;
        } else {
            $this->error("Failed to update user");
            return 1;
        }
    }

    private function lockUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');
        $reason = $this->args['reason'] ?? $this->prompt('Reason (optional)', '');

        $user = $this->db->getUserByUsername($username);
        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        if ($user['status'] === 'locked') {
            $this->warning("User is already locked");
            return 0;
        }

        $rowsAffected = $this->db->lockUser($username, $reason);

        if ($rowsAffected > 0) {
            $this->success("✓ User locked successfully!");
            if ($reason) {
                $this->info("  Reason: $reason");
            }
            return 0;
        } else {
            $this->error("Failed to lock user");
            return 1;
        }
    }

    private function unlockUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');

        $user = $this->db->getUserByUsername($username);
        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        if ($user['status'] !== 'locked') {
            $this->warning("User is not locked (status: {$user['status']})");
            return 0;
        }

        $rowsAffected = $this->db->unlockUser($username);

        if ($rowsAffected > 0) {
            $this->success("✓ User unlocked successfully!");
            return 0;
        } else {
            $this->error("Failed to unlock user");
            return 1;
        }
    }

    private function deleteUser() {
        $username = $this->args['username'] ?? $this->prompt('Username');
        $confirm = isset($this->args['confirm']);

        $user = $this->db->getUserByUsername($username);
        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        if (!$confirm) {
            $this->warning("This will soft-delete the user account.");
            $response = $this->prompt("Type 'yes' to confirm");
            if (strtolower($response) !== 'yes') {
                $this->info("Cancelled");
                return 0;
            }
        }

        $rowsAffected = $this->db->deleteUser($user['id']);

        if ($rowsAffected > 0) {
            $this->success("✓ User deleted successfully!");
            $this->info("  Username: $username");
            return 0;
        } else {
            $this->error("Failed to delete user");
            return 1;
        }
    }

    private function resetPassword() {
        $username = $this->args['username'] ?? $this->prompt('Username');
        $password = $this->args['password'] ?? $this->promptPassword('New password (leave empty for random)');

        $user = $this->db->getUserByUsername($username);
        if (!$user) {
            $this->error("User not found: $username");
            return 1;
        }

        if (empty($password)) {
            $password = bin2hex(random_bytes(8));
            $this->info("Generated password: $password");
        }

        // Validate password
        $validation = $this->security->validatePassword($password);
        if (!$validation['valid']) {
            $this->error("Password not strong enough:");
            foreach ($validation['errors'] as $error) {
                $this->error("  - $error");
            }
            return 1;
        }

        $rowsAffected = $this->db->updateUser($user['id'], ['password' => $password]);

        if ($rowsAffected > 0) {
            $this->success("✓ Password reset successfully!");
            $this->info("  Username: $username");
            $this->info("  New password: $password");
            return 0;
        } else {
            $this->error("Failed to reset password");
            return 1;
        }
    }

    private function migrateJSONToDB() {
        $file = $this->args['file'] ?? __DIR__ . '/../data/accounts.json';

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $this->info("Migrating users from JSON to database...");
        $this->info("  Source: $file");
        echo "\n";

        $result = $this->db->migrateJSONUsers($file);

        $this->success("✓ Migration completed!");
        $this->info("  Migrated: {$result['migrated']} users");

        if (!empty($result['errors'])) {
            $this->warning("\nErrors encountered:");
            foreach ($result['errors'] as $error) {
                $this->warning("  - $error");
            }
        }

        return empty($result['errors']) ? 0 : 1;
    }

    private function dbHealth() {
        $this->info("Checking database health...\n");

        $health = $this->db->healthCheck();

        if ($health['status'] === 'healthy') {
            $this->success("✓ Database is healthy!");
            $this->info("  Database: " . $health['database']);
            $this->info("  Version: " . substr($health['version'], 0, 50));
            $this->info("  Tables initialized: " . ($health['tables'] ? 'Yes' : 'No'));
            return 0;
        } else {
            $this->error("✗ Database is unhealthy!");
            $this->error("  Error: " . $health['error']);
            return 1;
        }
    }

    private function dbInit() {
        $this->info("Initializing database schema...\n");

        try {
            $this->db->initializeSchema();
            $this->success("✓ Database schema initialized successfully!");
            return 0;
        } catch (Exception $e) {
            $this->error("Failed to initialize schema: " . $e->getMessage());
            return 1;
        }
    }

    private function showHelp() {
        echo "\nAEIMS Account Manager CLI\n\n";
        echo "Usage: php cli/account-manager.php <command> [options]\n\n";
        echo "Commands:\n";
        echo "  user:create           Create a new user account\n";
        echo "  user:list             List all user accounts\n";
        echo "  user:show             Show user details\n";
        echo "  user:update           Update user account\n";
        echo "  user:lock             Lock a user account\n";
        echo "  user:unlock           Unlock a user account\n";
        echo "  user:delete           Delete a user account (soft delete)\n";
        echo "  user:reset-password   Reset user password\n";
        echo "  migrate:json-to-db    Migrate users from JSON to database\n";
        echo "  db:health             Check database health\n";
        echo "  db:init               Initialize database schema\n";
        echo "  help                  Show this help message\n\n";
        echo "Options:\n";
        echo "  --username=<value>    Username\n";
        echo "  --email=<value>       Email address\n";
        echo "  --password=<value>    Password\n";
        echo "  --role=<value>        User role (customer/operator/admin)\n";
        echo "  --status=<value>      User status (active/locked/deleted)\n";
        echo "  --reason=<value>      Reason for action\n";
        echo "  --file=<value>        File path\n";
        echo "  --confirm             Skip confirmation prompts\n\n";
        echo "Examples:\n";
        echo "  php cli/account-manager.php user:create --username=admin --email=admin@aeims.app --role=admin\n";
        echo "  php cli/account-manager.php user:list --role=operator --status=active\n";
        echo "  php cli/account-manager.php user:lock --username=badactor --reason=\"Spam\"\n";
        echo "  php cli/account-manager.php migrate:json-to-db --file=data/accounts.json\n\n";
        return 0;
    }

    // Helper methods
    private function printHeader() {
        echo "\n";
        echo "╔════════════════════════════════════════╗\n";
        echo "║   AEIMS Account Manager CLI v1.0       ║\n";
        echo "╚════════════════════════════════════════╝\n";
        echo "\n";
    }

    private function prompt($message, $default = null) {
        echo "$message" . ($default ? " [$default]" : "") . ": ";
        $input = trim(fgets(STDIN));
        return $input ?: $default;
    }

    private function promptPassword($message) {
        echo "$message: ";
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $password;
    }

    private function success($message) {
        echo "\033[0;32m$message\033[0m\n";
    }

    private function error($message) {
        echo "\033[0;31m$message\033[0m\n";
    }

    private function warning($message) {
        echo "\033[0;33m$message\033[0m\n";
    }

    private function info($message) {
        echo "$message\n";
    }

    private function printTable($headers, $rows) {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }

        // Print header
        $separator = '+';
        foreach ($widths as $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
        }

        echo "$separator\n";
        echo '|';
        foreach ($headers as $i => $header) {
            echo ' ' . str_pad($header, $widths[$i]) . ' |';
        }
        echo "\n$separator\n";

        // Print rows
        foreach ($rows as $row) {
            echo '|';
            foreach ($row as $i => $cell) {
                echo ' ' . str_pad($cell, $widths[$i]) . ' |';
            }
            echo "\n";
        }

        echo "$separator\n";
    }
}

// Run CLI
if (php_sapi_name() === 'cli') {
    $cli = new AccountManagerCLI($argv);
    exit($cli->run());
} else {
    die('This script must be run from the command line');
}
