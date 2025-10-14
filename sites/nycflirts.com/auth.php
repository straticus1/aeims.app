<?php
/**
 * Flirts NYC - Customer Authentication
 * SECURITY UPDATED: Session fixation, CSRF, rate limiting, strong passwords
 */

// Load security manager
require_once __DIR__ . '/../../includes/SecurityManager.php';
require_once __DIR__ . '/../../includes/DatabaseManager.php';

$security = SecurityManager::getInstance();
$db = DatabaseManager::getInstance();

// Initialize secure session
$security->initializeSecureSession();
$security->applySecurityHeaders();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username && $password) {
                // SECURITY FIX: Rate limiting
                $ip = $_SERVER['REMOTE_ADDR'];
                if (!$security->checkRateLimit($ip, 'customer_login', 5, 300)) {
                    $_SESSION['auth_message'] = 'Too many login attempts. Please try again in 5 minutes.';
                    $_SESSION['auth_message_type'] = 'error';
                    break;
                }

                // Load customer data with safe file operations
                $customersFile = __DIR__ . '/../../data/customers.json';
                if (file_exists($customersFile)) {
                    $data = $security->safeJSONRead($customersFile);
                    $customers = $data['customers'] ?? [];

                    // Find customer by username
                    $foundCustomer = null;
                    foreach ($customers as $customerId => $customer) {
                        if ($customer['username'] === $username) {
                            $foundCustomer = $customer;
                            $foundCustomer['id'] = $customerId;
                            break;
                        }
                    }

                    if ($foundCustomer && password_verify($password, $foundCustomer['password_hash'])) {
                        // Check if customer is authorized for this site
                        if (in_array('nycflirts.com', $foundCustomer['sites'] ?? [])) {
                            // SECURITY FIX: Regenerate session to prevent session fixation
                            $security->regenerateSessionOnLogin();

                            // Reset rate limit on success
                            $security->resetRateLimit($ip, 'customer_login');

                            $_SESSION['customer_id'] = $foundCustomer['id'];
                            $_SESSION['customer_username'] = $foundCustomer['username'];
                            $_SESSION['customer_email'] = $foundCustomer['email'];
                            header('Location: /dashboard.php');
                            exit;
                        } else {
                            $_SESSION['auth_message'] = 'Account not authorized for this site';
                            $_SESSION['auth_message_type'] = 'error';
                        }
                    } else {
                        $_SESSION['auth_message'] = 'Invalid username or password';
                        $_SESSION['auth_message_type'] = 'error';
                    }
                } else {
                    $_SESSION['auth_message'] = 'Authentication system unavailable';
                    $_SESSION['auth_message_type'] = 'error';
                }
            } else {
                $_SESSION['auth_message'] = 'Username and password are required';
                $_SESSION['auth_message_type'] = 'error';
            }
            break;

        case 'signup':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($username && $email && $password && $confirmPassword) {
                if ($password !== $confirmPassword) {
                    $_SESSION['auth_message'] = 'Passwords do not match';
                    $_SESSION['auth_message_type'] = 'error';
                }
                // SECURITY FIX: Enforce strong passwords (10+ chars with complexity)
                else {
                    $passwordValidation = $security->validatePassword($password);
                    if (!$passwordValidation['valid']) {
                        $_SESSION['auth_message'] = 'Password requirements not met: ' . implode(', ', $passwordValidation['errors']);
                        $_SESSION['auth_message_type'] = 'error';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['auth_message'] = 'Please enter a valid email address';
                        $_SESSION['auth_message_type'] = 'error';
                    } else {
                        // Load and update customer data with safe file operations
                        $customersFile = __DIR__ . '/../../data/customers.json';
                        if (file_exists($customersFile)) {
                            $data = $security->safeJSONRead($customersFile);
                            $customers = $data['customers'] ?? [];

                            // Check if username already exists
                            $usernameExists = false;
                            foreach ($customers as $customer) {
                                if ($customer['username'] === $username) {
                                    $usernameExists = true;
                                    break;
                                }
                            }

                            if ($usernameExists) {
                                $_SESSION['auth_message'] = 'Username already taken';
                                $_SESSION['auth_message_type'] = 'error';
                            } else {
                                // Create new customer
                                $customerId = 'cust_' . uniqid();
                                $customers[$customerId] = [
                                    'customer_id' => $customerId,
                                    'username' => $username,
                                    'email' => $email,
                                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                                    'sites' => ['nycflirts.com'],
                                    'active' => true,
                                    'verified' => true,
                                    'created_at' => date('Y-m-d H:i:s')
                                ];

                                $data['customers'] = $customers;

                                // SECURITY FIX: Use safe file write
                                $security->safeJSONWrite($customersFile, $data);

                                // SECURITY FIX: Regenerate session after signup
                                $security->regenerateSessionOnLogin();

                                // Log them in
                                $_SESSION['customer_id'] = $customerId;
                                $_SESSION['customer_username'] = $username;
                                $_SESSION['customer_email'] = $email;
                                $_SESSION['auth_message'] = 'Registration successful! Welcome to NYC Flirts';
                                $_SESSION['auth_message_type'] = 'success';
                                header('Location: /dashboard.php');
                                exit;
                            }
                        } else {
                            $_SESSION['auth_message'] = 'Registration system unavailable';
                            $_SESSION['auth_message_type'] = 'error';
                        }
                    }
                }
            } else {
                $_SESSION['auth_message'] = 'All fields are required';
                $_SESSION['auth_message_type'] = 'error';
            }
            break;
    }
}

// Handle logout
if ($action === 'logout') {
    session_destroy();
    header('Location: /');
    exit;
}

// Redirect back to homepage
header('Location: /');
exit;
