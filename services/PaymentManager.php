<?php

namespace AEIMS\Services;

use Exception;

/**
 * Payment Processing Manager
 * Handles credit purchases, transactions, and payment method integration
 */
class PaymentManager
{
    private array $transactions = [];
    private array $paymentMethods = [];
    private string $transactionsFile;
    private string $paymentMethodsFile;

    // Payment packages
    private const CREDIT_PACKAGES = [
        'starter' => ['credits' => 10.00, 'price' => 9.99, 'bonus' => 0.00],
        'basic' => ['credits' => 25.00, 'price' => 19.99, 'bonus' => 5.00],
        'premium' => ['credits' => 50.00, 'price' => 39.99, 'bonus' => 15.00],
        'deluxe' => ['credits' => 100.00, 'price' => 79.99, 'bonus' => 35.00],
        'ultimate' => ['credits' => 250.00, 'price' => 199.99, 'bonus' => 100.00]
    ];

    // Supported payment methods
    private const PAYMENT_PROCESSORS = [
        'stripe' => 'Credit/Debit Card',
        'paypal' => 'PayPal',
        'crypto' => 'Cryptocurrency',
        'venmo' => 'Venmo',
        'cashapp' => 'Cash App'
    ];

    public function __construct()
    {
        $this->transactionsFile = __DIR__ . '/../data/transactions.json';
        $this->paymentMethodsFile = __DIR__ . '/../data/payment_methods.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        // Load transactions
        if (file_exists($this->transactionsFile)) {
            $data = json_decode(file_get_contents($this->transactionsFile), true);
            $this->transactions = $data['transactions'] ?? [];
        }

        // Load payment methods
        if (file_exists($this->paymentMethodsFile)) {
            $data = json_decode(file_get_contents($this->paymentMethodsFile), true);
            $this->paymentMethods = $data['payment_methods'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->transactionsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Save transactions
        $transactionData = [
            'transactions' => $this->transactions,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->transactionsFile, json_encode($transactionData, JSON_PRETTY_PRINT));

        // Save payment methods
        $paymentData = [
            'payment_methods' => $this->paymentMethods,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->paymentMethodsFile, json_encode($paymentData, JSON_PRETTY_PRINT));
    }

    public function getCreditPackages(): array
    {
        return self::CREDIT_PACKAGES;
    }

    public function getPaymentProcessors(): array
    {
        return self::PAYMENT_PROCESSORS;
    }

    public function createTransaction(string $customerId, string $packageId, string $paymentMethod, array $paymentData = []): array
    {
        if (!isset(self::CREDIT_PACKAGES[$packageId])) {
            throw new Exception('Invalid credit package');
        }

        if (!isset(self::PAYMENT_PROCESSORS[$paymentMethod])) {
            throw new Exception('Invalid payment method');
        }

        $package = self::CREDIT_PACKAGES[$packageId];
        $transactionId = 'txn_' . uniqid();

        $transaction = [
            'transaction_id' => $transactionId,
            'customer_id' => $customerId,
            'package_id' => $packageId,
            'payment_method' => $paymentMethod,
            'amount_usd' => $package['price'],
            'credits_purchased' => $package['credits'],
            'bonus_credits' => $package['bonus'],
            'total_credits' => $package['credits'] + $package['bonus'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'payment_data' => $paymentData,
            'processor_response' => null
        ];

        $this->transactions[$transactionId] = $transaction;
        $this->saveData();

        return $transaction;
    }

    public function processPayment(string $transactionId, array $processorData = []): array
    {
        if (!isset($this->transactions[$transactionId])) {
            throw new Exception('Transaction not found');
        }

        $transaction = &$this->transactions[$transactionId];

        if ($transaction['status'] !== 'pending') {
            throw new Exception('Transaction already processed');
        }

        try {
            // Simulate payment processing based on method
            $processorResult = $this->simulatePaymentProcessor(
                $transaction['payment_method'],
                $transaction['amount_usd'],
                $processorData
            );

            if ($processorResult['success']) {
                // Payment successful - add credits to customer
                $customerManager = new CustomerManager();

                $customerManager->addCredits(
                    $transaction['customer_id'],
                    $transaction['total_credits'],
                    "Credit purchase - Package: {$transaction['package_id']}"
                );

                // Update transaction
                $transaction['status'] = 'completed';
                $transaction['completed_at'] = date('Y-m-d H:i:s');
                $transaction['processor_response'] = $processorResult;

                $this->saveData();

                return [
                    'success' => true,
                    'transaction' => $transaction,
                    'message' => 'Payment processed successfully'
                ];
            } else {
                // Payment failed
                $transaction['status'] = 'failed';
                $transaction['failed_at'] = date('Y-m-d H:i:s');
                $transaction['processor_response'] = $processorResult;
                $transaction['failure_reason'] = $processorResult['error'] ?? 'Payment processing failed';

                $this->saveData();

                return [
                    'success' => false,
                    'error' => $processorResult['error'] ?? 'Payment processing failed',
                    'transaction' => $transaction
                ];
            }

        } catch (Exception $e) {
            // Processing error
            $transaction['status'] = 'error';
            $transaction['error_at'] = date('Y-m-d H:i:s');
            $transaction['error_message'] = $e->getMessage();

            $this->saveData();

            throw new Exception('Payment processing error: ' . $e->getMessage());
        }
    }

    private function simulatePaymentProcessor(string $method, float $amount, array $data): array
    {
        // Simulate different payment processors
        switch ($method) {
            case 'stripe':
                return $this->simulateStripePayment($amount, $data);
            case 'paypal':
                return $this->simulatePayPalPayment($amount, $data);
            case 'crypto':
                return $this->simulateCryptoPayment($amount, $data);
            case 'venmo':
            case 'cashapp':
                return $this->simulateP2PPayment($method, $amount, $data);
            default:
                return ['success' => false, 'error' => 'Unsupported payment method'];
        }
    }

    private function simulateStripePayment(float $amount, array $data): array
    {
        // Simulate Stripe payment processing
        $cardNumber = $data['card_number'] ?? '';
        $expiryMonth = $data['expiry_month'] ?? '';
        $expiryYear = $data['expiry_year'] ?? '';
        $cvv = $data['cvv'] ?? '';

        // Basic validation
        if (strlen($cardNumber) < 16 || !$expiryMonth || !$expiryYear || strlen($cvv) < 3) {
            return ['success' => false, 'error' => 'Invalid card details'];
        }

        // Simulate success (95% success rate for demo)
        $success = (rand(1, 100) <= 95);

        if ($success) {
            return [
                'success' => true,
                'processor' => 'stripe',
                'charge_id' => 'ch_' . uniqid(),
                'amount' => $amount,
                'currency' => 'usd',
                'last4' => substr($cardNumber, -4),
                'brand' => $this->detectCardBrand($cardNumber)
            ];
        } else {
            return ['success' => false, 'error' => 'Card declined by issuer'];
        }
    }

    private function simulatePayPalPayment(float $amount, array $data): array
    {
        $email = $data['paypal_email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid PayPal email'];
        }

        // Simulate PayPal success (98% success rate)
        $success = (rand(1, 100) <= 98);

        if ($success) {
            return [
                'success' => true,
                'processor' => 'paypal',
                'payment_id' => 'PAY-' . uniqid(),
                'amount' => $amount,
                'currency' => 'usd',
                'payer_email' => $email
            ];
        } else {
            return ['success' => false, 'error' => 'PayPal payment authorization failed'];
        }
    }

    private function simulateCryptoPayment(float $amount, array $data): array
    {
        $wallet = $data['wallet_address'] ?? '';
        $currency = $data['crypto_currency'] ?? 'btc';

        if (strlen($wallet) < 26) {
            return ['success' => false, 'error' => 'Invalid wallet address'];
        }

        // Simulate crypto conversion rates
        $rates = [
            'btc' => 45000.00,
            'eth' => 2800.00,
            'ltc' => 180.00,
            'usdt' => 1.00
        ];

        $cryptoAmount = $amount / ($rates[$currency] ?? 1);

        // Crypto payments have lower success rate due to network issues
        $success = (rand(1, 100) <= 85);

        if ($success) {
            return [
                'success' => true,
                'processor' => 'crypto',
                'transaction_hash' => hash('sha256', uniqid()),
                'amount_usd' => $amount,
                'amount_crypto' => $cryptoAmount,
                'currency' => strtoupper($currency),
                'wallet_address' => $wallet
            ];
        } else {
            return ['success' => false, 'error' => 'Cryptocurrency network congestion - please try again'];
        }
    }

    private function simulateP2PPayment(string $method, float $amount, array $data): array
    {
        $username = $data['username'] ?? '';

        if (strlen($username) < 3) {
            return ['success' => false, 'error' => "Invalid {$method} username"];
        }

        // P2P payments have high success rate
        $success = (rand(1, 100) <= 92);

        if ($success) {
            return [
                'success' => true,
                'processor' => $method,
                'payment_id' => strtoupper($method) . '-' . uniqid(),
                'amount' => $amount,
                'username' => $username
            ];
        } else {
            return ['success' => false, 'error' => ucfirst($method) . ' payment failed - insufficient funds'];
        }
    }

    private function detectCardBrand(string $cardNumber): string
    {
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwo = substr($cardNumber, 0, 2);

        if ($firstDigit === '4') return 'visa';
        if (in_array($firstTwo, ['51', '52', '53', '54', '55'])) return 'mastercard';
        if (in_array($firstTwo, ['34', '37'])) return 'amex';
        if ($firstTwo === '60') return 'discover';

        return 'unknown';
    }

    public function getTransaction(string $transactionId): ?array
    {
        return $this->transactions[$transactionId] ?? null;
    }

    public function getCustomerTransactions(string $customerId): array
    {
        $customerTransactions = [];

        foreach ($this->transactions as $transaction) {
            if ($transaction['customer_id'] === $customerId) {
                $customerTransactions[] = $transaction;
            }
        }

        // Sort by created_at descending
        usort($customerTransactions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $customerTransactions;
    }

    public function getTransactionStats(?string $customerId = null): array
    {
        $transactions = $customerId ?
                       $this->getCustomerTransactions($customerId) :
                       array_values($this->transactions);

        $totalTransactions = count($transactions);
        $completedTransactions = array_filter($transactions, fn($t) => $t['status'] === 'completed');
        $failedTransactions = array_filter($transactions, fn($t) => $t['status'] === 'failed');

        $totalRevenue = array_sum(array_map(fn($t) => $t['amount_usd'], $completedTransactions));
        $totalCredits = array_sum(array_map(fn($t) => $t['total_credits'], $completedTransactions));

        return [
            'total_transactions' => $totalTransactions,
            'completed_transactions' => count($completedTransactions),
            'failed_transactions' => count($failedTransactions),
            'success_rate' => $totalTransactions > 0 ? (count($completedTransactions) / $totalTransactions) * 100 : 0,
            'total_revenue' => $totalRevenue,
            'total_credits_sold' => $totalCredits,
            'average_transaction' => count($completedTransactions) > 0 ? $totalRevenue / count($completedTransactions) : 0
        ];
    }

    public function refundTransaction(string $transactionId, string $reason): array
    {
        if (!isset($this->transactions[$transactionId])) {
            throw new Exception('Transaction not found');
        }

        $transaction = &$this->transactions[$transactionId];

        if ($transaction['status'] !== 'completed') {
            throw new Exception('Only completed transactions can be refunded');
        }

        try {
            // Deduct credits from customer
            $customerManager = new CustomerManager();
            $customerManager->deductCredits(
                $transaction['customer_id'],
                $transaction['total_credits'],
                "Refund for transaction {$transactionId}: {$reason}"
            );

            // Update transaction
            $transaction['status'] = 'refunded';
            $transaction['refunded_at'] = date('Y-m-d H:i:s');
            $transaction['refund_reason'] = $reason;

            $this->saveData();

            return [
                'success' => true,
                'message' => 'Transaction refunded successfully',
                'transaction' => $transaction
            ];

        } catch (Exception $e) {
            throw new Exception('Refund failed: ' . $e->getMessage());
        }
    }
}