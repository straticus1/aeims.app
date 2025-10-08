<?php

namespace AEIMS\Services;

use Exception;

/**
 * Payment Processor Service
 * Integrates with PayKings and Authorize.net APIs for real payment processing
 */
class PaymentProcessorService
{
    private array $config;
    private string $environment; // 'sandbox' or 'production'

    // PayKings API Configuration
    private const PAYKINGS_SANDBOX_URL = 'https://sandbox.paykings.com/api/v1/';
    private const PAYKINGS_PRODUCTION_URL = 'https://api.paykings.com/v1/';

    // Authorize.net Configuration
    private const AUTHORIZE_SANDBOX_URL = 'https://apitest.authorize.net/xml/v1/request.api';
    private const AUTHORIZE_PRODUCTION_URL = 'https://api.authorize.net/xml/v1/request.api';

    public function __construct(string $environment = 'sandbox')
    {
        $this->environment = $environment;
        $this->loadConfiguration();
    }

    private function loadConfiguration(): void
    {
        // Load configuration from environment or config file
        $this->config = [
            'paykings' => [
                'merchant_id' => $_ENV['PAYKINGS_MERCHANT_ID'] ?? 'test_merchant_123',
                'api_key' => $_ENV['PAYKINGS_API_KEY'] ?? 'test_api_key_456',
                'secret_key' => $_ENV['PAYKINGS_SECRET_KEY'] ?? 'test_secret_789',
                'phone_api_key' => $_ENV['PAYKINGS_PHONE_API_KEY'] ?? 'test_phone_key',
            ],
            'authorize' => [
                'login_id' => $_ENV['AUTHORIZE_LOGIN_ID'] ?? 'test_login_id',
                'transaction_key' => $_ENV['AUTHORIZE_TRANSACTION_KEY'] ?? 'test_transaction_key',
                'public_key' => $_ENV['AUTHORIZE_PUBLIC_KEY'] ?? 'test_public_key',
            ]
        ];
    }

    /**
     * Process payment through PayKings API
     */
    public function processPayKingsPayment(array $paymentData): array
    {
        try {
            $url = $this->environment === 'production'
                ? self::PAYKINGS_PRODUCTION_URL . 'payments'
                : self::PAYKINGS_SANDBOX_URL . 'payments';

            $payload = [
                'merchant_id' => $this->config['paykings']['merchant_id'],
                'amount' => $paymentData['amount'],
                'currency' => 'USD',
                'payment_method' => [
                    'type' => 'credit_card',
                    'card_number' => $paymentData['card_number'],
                    'expiry_month' => $paymentData['expiry_month'],
                    'expiry_year' => $paymentData['expiry_year'],
                    'cvv' => $paymentData['cvv'],
                    'cardholder_name' => $paymentData['cardholder_name']
                ],
                'billing_info' => [
                    'address' => $paymentData['billing_address'] ?? '',
                    'city' => $paymentData['billing_city'] ?? '',
                    'state' => $paymentData['billing_state'] ?? '',
                    'zip' => $paymentData['billing_zip'] ?? '',
                    'country' => $paymentData['billing_country'] ?? 'US'
                ],
                'customer_info' => [
                    'customer_id' => $paymentData['customer_id'],
                    'email' => $paymentData['customer_email'] ?? '',
                    'phone' => $paymentData['customer_phone'] ?? ''
                ],
                'transaction_id' => $paymentData['transaction_id'],
                'description' => 'AEIMS Credit Purchase',
                'webhook_url' => 'https://aeims.app/webhooks/paykings'
            ];

            $headers = [
                'Authorization: Bearer ' . $this->config['paykings']['api_key'],
                'Content-Type: application/json',
                'X-Merchant-ID: ' . $this->config['paykings']['merchant_id']
            ];

            $response = $this->makeHttpRequest($url, $payload, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'processor' => 'paykings',
                    'transaction_id' => $response['data']['transaction_id'] ?? $paymentData['transaction_id'],
                    'processor_reference' => $response['data']['paykings_id'] ?? '',
                    'amount' => $paymentData['amount'],
                    'currency' => 'USD',
                    'last4' => substr($paymentData['card_number'], -4),
                    'card_brand' => $this->detectCardBrand($paymentData['card_number']),
                    'status' => $response['data']['status'] ?? 'completed',
                    'processor_response' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'PayKings payment failed',
                    'error_code' => $response['error_code'] ?? 'UNKNOWN_ERROR',
                    'processor' => 'paykings'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'PayKings API Error: ' . $e->getMessage(),
                'processor' => 'paykings'
            ];
        }
    }

    /**
     * Process payment through Authorize.net API
     */
    public function processAuthorizeNetPayment(array $paymentData): array
    {
        try {
            $url = $this->environment === 'production'
                ? self::AUTHORIZE_PRODUCTION_URL
                : self::AUTHORIZE_SANDBOX_URL;

            // Create XML request for Authorize.net
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><createTransactionRequest></createTransactionRequest>');
            $xml->addAttribute('xmlns', 'AnetApi/xml/v1/schema/AnetApiSchema.xsd');

            // Merchant authentication
            $merchantAuth = $xml->addChild('merchantAuthentication');
            $merchantAuth->addChild('name', $this->config['authorize']['login_id']);
            $merchantAuth->addChild('transactionKey', $this->config['authorize']['transaction_key']);

            // Transaction request
            $transactionRequest = $xml->addChild('transactionRequest');
            $transactionRequest->addChild('transactionType', 'authCaptureTransaction');
            $transactionRequest->addChild('amount', $paymentData['amount']);

            // Payment details
            $payment = $transactionRequest->addChild('payment');
            $creditCard = $payment->addChild('creditCard');
            $creditCard->addChild('cardNumber', $paymentData['card_number']);
            $creditCard->addChild('expirationDate',
                str_pad($paymentData['expiry_month'], 2, '0', STR_PAD_LEFT) . $paymentData['expiry_year']);
            $creditCard->addChild('cardCode', $paymentData['cvv']);

            // Billing information
            if (!empty($paymentData['billing_address'])) {
                $billTo = $transactionRequest->addChild('billTo');
                $billTo->addChild('firstName', explode(' ', $paymentData['cardholder_name'])[0] ?? '');
                $billTo->addChild('lastName', explode(' ', $paymentData['cardholder_name'], 2)[1] ?? '');
                $billTo->addChild('address', $paymentData['billing_address'] ?? '');
                $billTo->addChild('city', $paymentData['billing_city'] ?? '');
                $billTo->addChild('state', $paymentData['billing_state'] ?? '');
                $billTo->addChild('zip', $paymentData['billing_zip'] ?? '');
                $billTo->addChild('country', $paymentData['billing_country'] ?? 'US');
            }

            // Customer information
            $customer = $transactionRequest->addChild('customer');
            $customer->addChild('id', $paymentData['customer_id']);
            $customer->addChild('email', $paymentData['customer_email'] ?? '');

            // Order information
            $order = $transactionRequest->addChild('order');
            $order->addChild('invoiceNumber', $paymentData['transaction_id']);
            $order->addChild('description', 'AEIMS Credit Purchase');

            $headers = [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($xml->asXML())
            ];

            $response = $this->makeHttpRequest($url, $xml->asXML(), $headers, false);

            // Parse XML response
            $responseXml = simplexml_load_string($response['data']);
            $resultCode = (string) $responseXml->transactionResponse->responseCode;

            if ($resultCode === '1') { // Approved
                return [
                    'success' => true,
                    'processor' => 'authorize_net',
                    'transaction_id' => $paymentData['transaction_id'],
                    'processor_reference' => (string) $responseXml->transactionResponse->transId,
                    'amount' => $paymentData['amount'],
                    'currency' => 'USD',
                    'last4' => substr($paymentData['card_number'], -4),
                    'card_brand' => $this->detectCardBrand($paymentData['card_number']),
                    'status' => 'completed',
                    'auth_code' => (string) $responseXml->transactionResponse->authCode,
                    'processor_response' => json_decode(json_encode($responseXml), true)
                ];
            } else {
                $errorText = (string) $responseXml->transactionResponse->errors->error->errorText ?? 'Transaction failed';
                return [
                    'success' => false,
                    'error' => $errorText,
                    'error_code' => (string) $responseXml->transactionResponse->errors->error->errorCode ?? 'UNKNOWN',
                    'processor' => 'authorize_net'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Authorize.net API Error: ' . $e->getMessage(),
                'processor' => 'authorize_net'
            ];
        }
    }

    /**
     * Initiate phone payment through PayKings
     */
    public function initiatePhonePayment(array $paymentData): array
    {
        try {
            $url = $this->environment === 'production'
                ? self::PAYKINGS_PRODUCTION_URL . 'phone/initiate'
                : self::PAYKINGS_SANDBOX_URL . 'phone/initiate';

            $payload = [
                'merchant_id' => $this->config['paykings']['merchant_id'],
                'customer_id' => $paymentData['customer_id'],
                'customer_phone' => $paymentData['customer_phone'],
                'amount' => $paymentData['amount'],
                'currency' => 'USD',
                'transaction_id' => $paymentData['transaction_id'],
                'callback_url' => 'https://aeims.app/webhooks/paykings-phone',
                'description' => 'AEIMS Credit Purchase - Phone Payment'
            ];

            $headers = [
                'Authorization: Bearer ' . $this->config['paykings']['phone_api_key'],
                'Content-Type: application/json'
            ];

            $response = $this->makeHttpRequest($url, $payload, $headers);

            if ($response['success']) {
                return [
                    'success' => true,
                    'phone_session_id' => $response['data']['session_id'],
                    'call_initiated' => true,
                    'estimated_call_time' => '2-3 minutes',
                    'status' => 'phone_initiated'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Phone payment initiation failed'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Phone payment error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process refund through appropriate processor
     */
    public function processRefund(array $refundData): array
    {
        $processor = $refundData['original_processor'];

        switch ($processor) {
            case 'paykings':
                return $this->processPayKingsRefund($refundData);
            case 'authorize_net':
                return $this->processAuthorizeNetRefund($refundData);
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported processor for refund: ' . $processor
                ];
        }
    }

    private function processPayKingsRefund(array $refundData): array
    {
        try {
            $url = $this->environment === 'production'
                ? self::PAYKINGS_PRODUCTION_URL . 'refunds'
                : self::PAYKINGS_SANDBOX_URL . 'refunds';

            $payload = [
                'merchant_id' => $this->config['paykings']['merchant_id'],
                'original_transaction_id' => $refundData['original_transaction_id'],
                'amount' => $refundData['amount'],
                'reason' => $refundData['reason'] ?? 'Customer request'
            ];

            $headers = [
                'Authorization: Bearer ' . $this->config['paykings']['api_key'],
                'Content-Type: application/json'
            ];

            $response = $this->makeHttpRequest($url, $payload, $headers);

            return [
                'success' => $response['success'],
                'refund_id' => $response['data']['refund_id'] ?? '',
                'processor' => 'paykings',
                'error' => $response['error'] ?? null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'PayKings refund error: ' . $e->getMessage()
            ];
        }
    }

    private function processAuthorizeNetRefund(array $refundData): array
    {
        try {
            $url = $this->environment === 'production'
                ? self::AUTHORIZE_PRODUCTION_URL
                : self::AUTHORIZE_SANDBOX_URL;

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><createTransactionRequest></createTransactionRequest>');
            $xml->addAttribute('xmlns', 'AnetApi/xml/v1/schema/AnetApiSchema.xsd');

            $merchantAuth = $xml->addChild('merchantAuthentication');
            $merchantAuth->addChild('name', $this->config['authorize']['login_id']);
            $merchantAuth->addChild('transactionKey', $this->config['authorize']['transaction_key']);

            $transactionRequest = $xml->addChild('transactionRequest');
            $transactionRequest->addChild('transactionType', 'refundTransaction');
            $transactionRequest->addChild('amount', $refundData['amount']);

            $payment = $transactionRequest->addChild('payment');
            $creditCard = $payment->addChild('creditCard');
            $creditCard->addChild('cardNumber', 'XXXX' . $refundData['last4']);
            $creditCard->addChild('expirationDate', 'XXXX');

            $transactionRequest->addChild('refTransId', $refundData['processor_reference']);

            $headers = [
                'Content-Type: application/xml',
                'Content-Length: ' . strlen($xml->asXML())
            ];

            $response = $this->makeHttpRequest($url, $xml->asXML(), $headers, false);
            $responseXml = simplexml_load_string($response['data']);
            $resultCode = (string) $responseXml->transactionResponse->responseCode;

            return [
                'success' => $resultCode === '1',
                'refund_id' => (string) $responseXml->transactionResponse->transId ?? '',
                'processor' => 'authorize_net',
                'error' => $resultCode !== '1' ? 'Refund failed' : null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Authorize.net refund error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Make HTTP request to payment processor
     */
    private function makeHttpRequest(string $url, $data, array $headers, bool $isJson = true): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $isJson ? json_encode($data) : $data,
            CURLOPT_SSL_VERIFYPEER => $this->environment === 'production',
            CURLOPT_USERAGENT => 'AEIMS Payment System v1.0'
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new Exception('HTTP Request Error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception('HTTP Error ' . $httpCode . ': ' . $response);
        }

        if ($isJson) {
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            return [
                'success' => $httpCode < 300 && ($decodedResponse['success'] ?? ($decodedResponse['status'] ?? '') === 'success'),
                'data' => $decodedResponse,
                'error' => $decodedResponse['error'] ?? $decodedResponse['message'] ?? null,
                'error_code' => $decodedResponse['error_code'] ?? null
            ];
        } else {
            return [
                'success' => $httpCode < 300,
                'data' => $response,
                'error' => $httpCode >= 300 ? 'HTTP Error ' . $httpCode : null
            ];
        }
    }

    /**
     * Detect card brand from card number
     */
    private function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\s+/', '', $cardNumber);
        $firstDigit = substr($cardNumber, 0, 1);
        $firstTwo = substr($cardNumber, 0, 2);
        $firstFour = substr($cardNumber, 0, 4);

        if ($firstDigit === '4') return 'visa';
        if (in_array($firstTwo, ['51', '52', '53', '54', '55']) ||
            (intval($firstFour) >= 2221 && intval($firstFour) <= 2720)) return 'mastercard';
        if (in_array($firstTwo, ['34', '37'])) return 'amex';
        if ($firstFour === '6011' || $firstTwo === '65') return 'discover';

        return 'unknown';
    }

    /**
     * Validate payment data
     */
    public function validatePaymentData(array $paymentData): array
    {
        $errors = [];

        if (empty($paymentData['amount']) || $paymentData['amount'] < 5) {
            $errors[] = 'Amount must be at least $5.00';
        }

        if ($paymentData['amount'] > 1000) {
            $errors[] = 'Amount cannot exceed $1,000.00';
        }

        if (empty($paymentData['card_number']) || strlen(preg_replace('/\s+/', '', $paymentData['card_number'])) < 13) {
            $errors[] = 'Invalid card number';
        }

        if (empty($paymentData['expiry_month']) || empty($paymentData['expiry_year'])) {
            $errors[] = 'Card expiry date is required';
        }

        if (empty($paymentData['cvv']) || strlen($paymentData['cvv']) < 3) {
            $errors[] = 'Invalid CVV';
        }

        if (empty($paymentData['cardholder_name'])) {
            $errors[] = 'Cardholder name is required';
        }

        return $errors;
    }
}