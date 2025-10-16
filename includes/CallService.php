<?php
/**
 * CallService - Manages voice calls via Asterisk integration
 * Handles call initiation, billing, and tracking
 */

class CallService {
    private $db;
    private $asteriskAdapterUrl;
    private $apiKey;

    public function __construct($db) {
        $this->db = $db;

        // Service discovery URLs (ECS internal DNS)
        $this->asteriskAdapterUrl = getenv('ASTERISK_ADAPTER_URL') ?:
            'http://aeims-asterisk-adapter.aeims-cluster.local:8080';

        $this->apiKey = getenv('AEIMS_API_KEY') ?: '';
    }

    /**
     * Initiate a bridged call between customer and operator
     *
     * Flow:
     * 1. Validate customer balance
     * 2. Get operator phone from metadata
     * 3. Create call record in database
     * 4. Tell Asterisk to originate call to customer
     * 5. When customer answers, Asterisk calls operator
     * 6. Bridge both calls together
     *
     * @param array $params {
     *   customer_id: int,
     *   customer_phone: string (E.164 format),
     *   operator_id: int,
     *   domain: string,
     *   rate_per_minute: float (optional, uses operator's default)
     * }
     * @return array {success: bool, call_id: string, message: string}
     */
    public function initiateBridgedCall($params) {
        $customerId = $params['customer_id'];
        $customerPhone = $this->normalizePhone($params['customer_phone']);
        $operatorId = $params['operator_id'];
        $domain = $params['domain'] ?? 'aeims.app';

        // Get operator data
        $operator = $this->db->fetchOne("
            SELECT id, display_name, metadata
            FROM operators
            WHERE id = :id AND is_active = true
        ", ['id' => $operatorId]);

        if (!$operator) {
            return [
                'success' => false,
                'error' => 'Operator not found or inactive'
            ];
        }

        // Get operator phone from metadata
        $metadata = json_decode($operator['metadata'] ?? '{}', true);
        $operatorPhone = $metadata['phone'] ?? $metadata['contact_phone'] ?? null;

        if (!$operatorPhone) {
            return [
                'success' => false,
                'error' => 'Operator phone number not configured'
            ];
        }

        $operatorPhone = $this->normalizePhone($operatorPhone);

        // Get rate per minute (from params or operator's default)
        $ratePerMinute = $params['rate_per_minute'] ??
            $metadata['rate_per_minute'] ??
            3.99; // Default rate

        // Validate customer has sufficient balance (at least 1 minute)
        $customer = $this->db->fetchOne("
            SELECT id, balance
            FROM customers
            WHERE id = :id
        ", ['id' => $customerId]);

        if (!$customer) {
            return [
                'success' => false,
                'error' => 'Customer not found'
            ];
        }

        if ($customer['balance'] < $ratePerMinute) {
            return [
                'success' => false,
                'error' => 'Insufficient balance',
                'required' => $ratePerMinute,
                'available' => $customer['balance']
            ];
        }

        // Generate unique call ID
        $callId = 'call_' . time() . '_' . bin2hex(random_bytes(8));

        // Insert call record
        try {
            $this->db->execute("
                INSERT INTO calls (
                    call_id, operator_id, customer_id, domain,
                    direction, call_type, status
                ) VALUES (
                    :call_id, :operator_id, :customer_id, :domain,
                    'outbound', 'bridged', 'initiated'
                )
            ", [
                'call_id' => $callId,
                'operator_id' => $operatorId,
                'customer_id' => $customerId,
                'domain' => $domain
            ]);
        } catch (Exception $e) {
            error_log("CallService: Failed to create call record: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create call record'
            ];
        }

        // Originate call via Asterisk
        $asteriskResponse = $this->originateCallInAsterisk([
            'call_id' => $callId,
            'customer_phone' => $customerPhone,
            'operator_phone' => $operatorPhone,
            'operator_id' => $operatorId,
            'customer_id' => $customerId,
            'rate_per_minute' => $ratePerMinute,
            'operator_name' => $operator['display_name'],
            'domain' => $domain
        ]);

        if (!$asteriskResponse['success']) {
            // Mark call as failed
            $this->db->execute("
                UPDATE calls SET status = 'failed' WHERE call_id = :call_id
            ", ['call_id' => $callId]);

            return [
                'success' => false,
                'error' => 'Failed to initiate call',
                'asterisk_error' => $asteriskResponse['error'] ?? 'Unknown error'
            ];
        }

        return [
            'success' => true,
            'call_id' => $callId,
            'message' => 'Call initiated successfully',
            'operator' => $operator['display_name'],
            'rate_per_minute' => $ratePerMinute
        ];
    }

    /**
     * Originate call in Asterisk via adapter API
     */
    private function originateCallInAsterisk($params) {
        $endpoint = "{$this->asteriskAdapterUrl}/call/originate";

        $payload = [
            'endpoint' => "PJSIP/provider/{$params['customer_phone']}",
            'callerid' => "{$params['operator_name']} <{$params['operator_phone']}>",
            'variables' => [
                'CALL_ID' => $params['call_id'],
                'CUSTOMER_ID' => (string)$params['customer_id'],
                'OPERATOR_ID' => (string)$params['operator_id'],
                'OPERATOR_PHONE' => $params['operator_phone'],
                'RATE_PER_MINUTE' => (string)$params['rate_per_minute'],
                'DOMAIN' => $params['domain']
            ],
            'account_id' => "customer_{$params['customer_id']}",
            'operator_id' => (string)$params['operator_id'],
            'timeout' => 30
        ];

        $response = $this->httpPost($endpoint, $payload);

        if (!$response) {
            return [
                'success' => false,
                'error' => 'Failed to connect to Asterisk adapter'
            ];
        }

        return [
            'success' => isset($response['ok']) && $response['ok'] === true,
            'channel_id' => $response['channel_id'] ?? null,
            'error' => $response['message'] ?? null
        ];
    }

    /**
     * Get call status
     */
    public function getCallStatus($callId) {
        return $this->db->fetchOne("
            SELECT
                call_id,
                status,
                duration_seconds,
                created_at,
                answered_at,
                ended_at,
                operator_id,
                customer_id
            FROM calls
            WHERE call_id = :call_id
        ", ['call_id' => $callId]);
    }

    /**
     * Get active calls for an operator
     */
    public function getOperatorActiveCalls($operatorId) {
        return $this->db->fetchAll("
            SELECT
                call_id,
                customer_id,
                status,
                created_at,
                duration_seconds
            FROM calls
            WHERE operator_id = :operator_id
            AND status IN ('initiated', 'ringing', 'answered')
            ORDER BY created_at DESC
        ", ['operator_id' => $operatorId]);
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalizePhone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add +1 for US numbers if not present
        if (strlen($phone) == 10) {
            $phone = '1' . $phone;
        }

        return '+' . $phone;
    }

    /**
     * HTTP POST helper with authentication
     */
    private function httpPost($url, $data) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'X-Service: aeims-app'
            ],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("CallService HTTP Error: $error");
            return null;
        }

        if ($httpCode >= 400) {
            error_log("CallService HTTP $httpCode: $response");
            return null;
        }

        return json_decode($response, true);
    }
}
