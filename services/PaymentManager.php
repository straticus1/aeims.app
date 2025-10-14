<?php

namespace AEIMS\Services;

use Exception;

/**
 * Payment Processing Manager
 * Handles credit purchases, transactions, and payment method integration
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class PaymentManager
{
    private $dataLayer;

    private const CREDIT_PACKAGES = [
        'starter' => ['credits' => 10.00, 'price' => 9.99, 'bonus' => 0.00],
        'basic' => ['credits' => 25.00, 'price' => 19.99, 'bonus' => 5.00],
        'premium' => ['credits' => 50.00, 'price' => 39.99, 'bonus' => 15.00],
        'deluxe' => ['credits' => 100.00, 'price' => 79.99, 'bonus' => 35.00],
        'ultimate' => ['credits' => 250.00, 'price' => 199.99, 'bonus' => 100.00]
    ];

    private const PAYMENT_PROCESSORS = [
        'stripe' => 'Stripe (Credit/Debit Card)',
        'authorizenet' => 'Authorize.Net',
        'paykings' => 'PayKings (High-Risk Processing)',
        'paypal' => 'PayPal',
        'crypto' => 'Cryptocurrency',
        'venmo' => 'Venmo',
        'cashapp' => 'Cash App'
    ];

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
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

        $this->dataLayer->saveTransaction($transaction);
        return $transaction;
    }

    public function processPayment(string $transactionId, array $processorData = []): array
    {
        $transaction = $this->dataLayer->getTransaction($transactionId);
        
        if (!$transaction) {
            throw new Exception('Transaction not found');
        }

        if ($transaction['status'] !== 'pending') {
            throw new Exception('Transaction already processed');
        }

        try {
            $processorResult = $this->simulatePaymentProcessor(
                $transaction['payment_method'],
                $transaction['amount_usd'],
                $processorData
            );

            if ($processorResult['success']) {
                $customerManager = new CustomerManager();
                $customerManager->addCredits($transaction['customer_id'], $transaction['total_credits'], 'Credit purchase');

                $transaction['status'] = 'completed';
                $transaction['processor_response'] = $processorResult;
                $transaction['completed_at'] = date('Y-m-d H:i:s');
            } else {
                $transaction['status'] = 'failed';
                $transaction['processor_response'] = $processorResult;
                $transaction['failed_at'] = date('Y-m-d H:i:s');
            }

            $this->dataLayer->saveTransaction($transaction);
            return $transaction;
        } catch (Exception $e) {
            $transaction['status'] = 'error';
            $transaction['error_message'] = $e->getMessage();
            $transaction['failed_at'] = date('Y-m-d H:i:s');
            $this->dataLayer->saveTransaction($transaction);
            throw $e;
        }
    }

    private function simulatePaymentProcessor(string $method, float $amount, array $data): array
    {
        return ['success' => true, 'transaction_id' => 'sim_' . uniqid(), 'timestamp' => time()];
    }

    public function getTransaction(string $transactionId): ?array
    {
        return $this->dataLayer->getTransaction($transactionId);
    }

    public function getCustomerTransactions(string $customerId): array
    {
        return $this->dataLayer->getCustomerTransactions($customerId);
    }

    public function refundTransaction(string $transactionId, string $reason = ''): array
    {
        $transaction = $this->dataLayer->getTransaction($transactionId);

        if (!$transaction) {
            throw new Exception('Transaction not found');
        }

        if ($transaction['status'] !== 'completed') {
            throw new Exception('Only completed transactions can be refunded');
        }

        $customerManager = new CustomerManager();
        $customerManager->deductCredits($transaction['customer_id'], $transaction['total_credits'], 'Refund: ' . $reason);

        $transaction['status'] = 'refunded';
        $transaction['refund_reason'] = $reason;
        $transaction['refunded_at'] = date('Y-m-d H:i:s');

        $this->dataLayer->saveTransaction($transaction);
        return $transaction;
    }

    public function createChargeback(string $transactionId, string $reason, float $amount, array $metadata = []): array
    {
        $transaction = $this->dataLayer->getTransaction($transactionId);

        if (!$transaction) {
            throw new Exception('Transaction not found');
        }

        $chargebackId = 'cb_' . uniqid();
        $chargeback = [
            'chargeback_id' => $chargebackId,
            'transaction_id' => $transactionId,
            'customer_id' => $transaction['customer_id'],
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'metadata' => $metadata
        ];

        $this->dataLayer->saveChargeback($chargeback);

        $transaction['chargeback_id'] = $chargebackId;
        $transaction['status'] = 'chargeback';
        $this->dataLayer->saveTransaction($transaction);

        return $chargeback;
    }

    public function resolveChargeback(string $chargebackId, string $resolution, string $notes = ''): array
    {
        $chargeback = $this->dataLayer->getChargeback($chargebackId);

        if (!$chargeback) {
            throw new Exception('Chargeback not found');
        }

        $chargeback['status'] = $resolution; // 'won', 'lost', 'partial'
        $chargeback['resolution_notes'] = $notes;
        $chargeback['resolved_at'] = date('Y-m-d H:i:s');

        if ($resolution === 'lost') {
            $customerManager = new CustomerManager();
            $customerManager->deductCredits($chargeback['customer_id'], $chargeback['amount'], 'Chargeback lost');
        }

        $this->dataLayer->saveChargeback($chargeback);
        return $chargeback;
    }

    public function getAllChargebacks(?string $status = null): array
    {
        return $this->dataLayer->searchChargebacks($status ? ['status' => $status] : []);
    }

    public function getTransactionStats(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->dataLayer->getTransactionStats($startDate, $endDate);
    }
}
