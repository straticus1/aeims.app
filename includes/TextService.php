<?php
/**
 * TextService - Manages text messages (SMS/Chat) with billing
 *
 * Billing Model:
 * - Customer → Operator: Customer pays operator's per-text rate
 * - Operator → Customer: FREE (no charge)
 */

class TextService {
    private $db;
    private $twilioAccountSid;
    private $twilioAuthToken;
    private $twilioPhoneNumber;

    public function __construct($db) {
        $this->db = $db;

        // Twilio configuration (for SMS)
        $this->twilioAccountSid = getenv('TWILIO_ACCOUNT_SID') ?: '';
        $this->twilioAuthToken = getenv('TWILIO_AUTH_TOKEN') ?: '';
        $this->twilioPhoneNumber = getenv('TWILIO_PHONE_NUMBER') ?: '';
    }

    /**
     * Send text message from customer to operator
     * Customer is charged operator's per-text rate
     *
     * @param array $params {
     *   customer_id: int,
     *   operator_id: int,
     *   message_text: string,
     *   domain: string,
     *   message_type: string ('sms' or 'chat')
     * }
     * @return array {success: bool, message_id: string}
     */
    public function sendCustomerToOperator($params) {
        $customerId = $params['customer_id'];
        $operatorId = $params['operator_id'];
        $messageText = $params['message_text'];
        $domain = $params['domain'] ?? 'aeims.app';
        $messageType = $params['message_type'] ?? 'chat';

        // Get operator data and rate
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

        $metadata = json_decode($operator['metadata'] ?? '{}', true);
        $ratePerMessage = $metadata['rate_per_message'] ?? 0.50; // Default $0.50/text

        // Check customer balance
        $customer = $this->db->fetchOne("
            SELECT id, balance FROM customers WHERE id = :id
        ", ['id' => $customerId]);

        if (!$customer) {
            return ['success' => false, 'error' => 'Customer not found'];
        }

        if ($customer['balance'] < $ratePerMessage) {
            return [
                'success' => false,
                'error' => 'Insufficient balance',
                'required' => $ratePerMessage,
                'available' => $customer['balance']
            ];
        }

        // Generate message ID
        $messageId = 'msg_' . time() . '_' . bin2hex(random_bytes(8));

        // Insert message record
        try {
            $this->db->execute("
                INSERT INTO messages (
                    message_id, sender_id, sender_type, recipient_id, recipient_type,
                    domain, message_text, message_type, direction, status
                ) VALUES (
                    :message_id, :sender_id, 'customer', :recipient_id, 'operator',
                    :domain, :message_text, :message_type, 'inbound', 'sent'
                )
            ", [
                'message_id' => $messageId,
                'sender_id' => $customerId,
                'recipient_id' => $operatorId,
                'domain' => $domain,
                'message_text' => $messageText,
                'message_type' => $messageType
            ]);

            $messageDbId = $this->db->lastInsertId();

        } catch (Exception $e) {
            error_log("TextService: Failed to save message: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save message'];
        }

        // Create transaction (customer is charged)
        $operatorAmount = round($ratePerMessage * 0.80, 2); // 80% to operator
        $platformAmount = round($ratePerMessage * 0.20, 2); // 20% to platform

        try {
            $this->db->execute("
                INSERT INTO transactions (
                    message_id, customer_id, operator_id, domain,
                    transaction_type, amount, operator_amount, platform_amount,
                    message_count, rate_per_message, status, description
                ) VALUES (
                    :message_id, :customer_id, :operator_id, :domain,
                    'message', :amount, :operator_amount, :platform_amount,
                    1, :rate_per_message, 'completed',
                    'Text message charge'
                )
            ", [
                'message_id' => $messageDbId,
                'customer_id' => $customerId,
                'operator_id' => $operatorId,
                'domain' => $domain,
                'amount' => $ratePerMessage,
                'operator_amount' => $operatorAmount,
                'platform_amount' => $platformAmount,
                'rate_per_message' => $ratePerMessage
            ]);

            // Deduct from customer balance
            $this->db->execute("
                UPDATE customers
                SET balance = balance - :amount
                WHERE id = :id
            ", [
                'amount' => $ratePerMessage,
                'id' => $customerId
            ]);

        } catch (Exception $e) {
            error_log("TextService: Failed to create transaction: " . $e->getMessage());
        }

        // Send actual SMS if using Twilio
        if ($messageType === 'sms' && $this->isTwilioConfigured()) {
            $operatorPhone = $metadata['phone'] ?? null;
            if ($operatorPhone) {
                $this->sendViaTwilio($operatorPhone, $messageText, $messageId);
            }
        }

        // TODO: Send real-time notification to operator via WebSocket

        return [
            'success' => true,
            'message_id' => $messageId,
            'charged' => $ratePerMessage,
            'balance_remaining' => $customer['balance'] - $ratePerMessage
        ];
    }

    /**
     * Send text message from operator to customer
     * FREE - No charge to anyone
     *
     * @param array $params {
     *   operator_id: int,
     *   customer_id: int,
     *   message_text: string,
     *   domain: string,
     *   message_type: string
     * }
     * @return array {success: bool, message_id: string}
     */
    public function sendOperatorToCustomer($params) {
        $operatorId = $params['operator_id'];
        $customerId = $params['customer_id'];
        $messageText = $params['message_text'];
        $domain = $params['domain'] ?? 'aeims.app';
        $messageType = $params['message_type'] ?? 'chat';

        // Verify operator exists and is active
        $operator = $this->db->fetchOne("
            SELECT id FROM operators WHERE id = :id AND is_active = true
        ", ['id' => $operatorId]);

        if (!$operator) {
            return ['success' => false, 'error' => 'Operator not found or inactive'];
        }

        // Generate message ID
        $messageId = 'msg_' . time() . '_' . bin2hex(random_bytes(8));

        // Insert message record (NO BILLING - it's free)
        try {
            $this->db->execute("
                INSERT INTO messages (
                    message_id, sender_id, sender_type, recipient_id, recipient_type,
                    domain, message_text, message_type, direction, status
                ) VALUES (
                    :message_id, :sender_id, 'operator', :recipient_id, 'customer',
                    :domain, :message_text, :message_type, 'outbound', 'sent'
                )
            ", [
                'message_id' => $messageId,
                'sender_id' => $operatorId,
                'recipient_id' => $customerId,
                'domain' => $domain,
                'message_text' => $messageText,
                'message_type' => $messageType
            ]);

        } catch (Exception $e) {
            error_log("TextService: Failed to save message: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to save message'];
        }

        // Send actual SMS if using Twilio
        if ($messageType === 'sms' && $this->isTwilioConfigured()) {
            $customer = $this->db->fetchOne("
                SELECT phone FROM customers WHERE id = :id
            ", ['id' => $customerId]);

            if ($customer && $customer['phone']) {
                $this->sendViaTwilio($customer['phone'], $messageText, $messageId);
            }
        }

        // TODO: Send real-time notification to customer via WebSocket

        return [
            'success' => true,
            'message_id' => $messageId,
            'charged' => 0.00, // FREE for operator responses
            'note' => 'Operator messages are free'
        ];
    }

    /**
     * Get message history between operator and customer
     */
    public function getConversation($operatorId, $customerId, $limit = 50) {
        return $this->db->fetchAll("
            SELECT
                message_id,
                sender_type,
                message_text,
                message_type,
                status,
                created_at
            FROM messages
            WHERE
                (sender_id = :operator_id AND sender_type = 'operator' AND recipient_id = :customer_id)
                OR
                (sender_id = :customer_id AND sender_type = 'customer' AND recipient_id = :operator_id)
            ORDER BY created_at DESC
            LIMIT :limit
        ", [
            'operator_id' => $operatorId,
            'customer_id' => $customerId,
            'limit' => $limit
        ]);
    }

    /**
     * Get unread message count for operator
     */
    public function getUnreadCount($operatorId) {
        $result = $this->db->fetchOne("
            SELECT COUNT(*) as count
            FROM messages
            WHERE recipient_id = :operator_id
            AND recipient_type = 'operator'
            AND status IN ('sent', 'delivered')
            AND read_at IS NULL
        ", ['operator_id' => $operatorId]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead($operatorId, $messageIds) {
        if (empty($messageIds)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

        $stmt = $this->db->prepare("
            UPDATE messages
            SET status = 'read', read_at = CURRENT_TIMESTAMP
            WHERE recipient_id = ?
            AND recipient_type = 'operator'
            AND message_id IN ($placeholders)
        ");

        $params = array_merge([$operatorId], $messageIds);
        return $stmt->execute($params);
    }

    /**
     * Check if Twilio is configured
     */
    private function isTwilioConfigured() {
        return !empty($this->twilioAccountSid) &&
               !empty($this->twilioAuthToken) &&
               !empty($this->twilioPhoneNumber);
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio($toPhone, $messageText, $messageId) {
        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioAccountSid}/Messages.json";

            $data = [
                'From' => $this->twilioPhoneNumber,
                'To' => $toPhone,
                'Body' => $messageText,
                'StatusCallback' => getenv('TWILIO_STATUS_CALLBACK_URL')
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_USERPWD => "{$this->twilioAccountSid}:{$this->twilioAuthToken}",
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($response, true);
                $twilioSid = $result['sid'] ?? null;

                // Update message with Twilio SID
                if ($twilioSid) {
                    $this->db->execute("
                        UPDATE messages
                        SET metadata = jsonb_set(
                            COALESCE(metadata, '{}')::jsonb,
                            '{twilio_sid}',
                            :twilio_sid::jsonb
                        )
                        WHERE message_id = :message_id
                    ", [
                        'twilio_sid' => json_encode($twilioSid),
                        'message_id' => $messageId
                    ]);
                }

                return true;
            }

            error_log("TextService Twilio Error: HTTP $httpCode - $response");
            return false;

        } catch (Exception $e) {
            error_log("TextService Twilio Exception: " . $e->getMessage());
            return false;
        }
    }
}
