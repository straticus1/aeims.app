<?php

namespace AEIMS\Services;

use Exception;

/**
 * Messaging System Manager
 * Handles customer-operator messaging with smart billing rules
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class MessagingManager
{
    private $dataLayer;

    // Billing rates
    private const MESSAGE_RATE = 0.50;  // Standard message rate
    private const CONTENT_RATE = 0.99;  // Media/content rate
    private const OPERATOR_COMMISSION = 0.65; // 65% to operator

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
    }

    public function startConversation(string $customerId, string $operatorId, string $siteDomain): string
    {
        $conversationId = 'conv_' . uniqid();

        $conversation = [
            'conversation_id' => $conversationId,
            'customer_id' => $customerId,
            'operator_id' => $operatorId,
            'site_domain' => $siteDomain,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'last_activity' => date('Y-m-d H:i:s'),
            'billing_info' => [
                'customer_free_messages' => 0, // Free messages earned from operator replies
                'total_customer_paid' => 0.00,
                'total_operator_earned' => 0.00,
                'message_count' => 0
            ]
        ];

        $this->dataLayer->saveConversation($conversation);
        return $conversationId;
    }

    public function sendMessage(string $conversationId, string $senderId, string $content, string $senderType, array $attachments = []): array
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            throw new Exception('Conversation not found');
        }

        if ($conversation['status'] !== 'active') {
            throw new Exception('Conversation is not active');
        }

        $messageId = 'msg_' . uniqid();
        $now = date('Y-m-d H:i:s');
        $cost = 0.00;
        $wasCharged = false;
        $usedFreeMessage = false;

        // Apply billing rules
        if ($senderType === 'customer') {
            // Check for content attachments (costs $0.99)
            if (!empty($attachments)) {
                $cost = self::CONTENT_RATE;
                $wasCharged = true;
            }
            // Check if customer has free messages from operator replies
            elseif ($conversation['billing_info']['customer_free_messages'] > 0) {
                $conversation['billing_info']['customer_free_messages']--;
                $usedFreeMessage = true;
            }
            // Regular paid message
            else {
                $cost = self::MESSAGE_RATE;
                $wasCharged = true;
            }

            // Process payment if needed
            if ($wasCharged && $cost > 0) {
                $customerManager = new CustomerManager();
                $customerManager->deductCredits($senderId, $cost, 'Message to operator');

                // Update conversation billing
                $conversation['billing_info']['total_customer_paid'] += $cost;
                $operatorEarning = $cost * self::OPERATOR_COMMISSION;
                $conversation['billing_info']['total_operator_earned'] += $operatorEarning;

                // Log activity
                $activityLogger = new ActivityLogger();
                $activityType = !empty($attachments) ? ActivityLogger::TYPE_CONTENT : ActivityLogger::TYPE_MESSAGE;
                $activityLogger->logSpending(
                    $senderId,
                    $conversation['operator_id'],
                    $activityType,
                    $cost,
                    [
                        'conversation_id' => $conversationId,
                        'message_id' => $messageId,
                        'has_attachments' => !empty($attachments)
                    ]
                );
            }
        }
        elseif ($senderType === 'operator') {
            // Operator replies are always free and give customer 1 free message
            $conversation['billing_info']['customer_free_messages']++;
        }

        // Create message
        $message = [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'content' => $content,
            'attachments' => $attachments,
            'sent_at' => $now,
            'billing' => [
                'cost' => $cost,
                'was_charged' => $wasCharged,
                'used_free_message' => $usedFreeMessage,
                'free_messages_remaining' => $conversation['billing_info']['customer_free_messages']
            ]
        ];

        $this->dataLayer->saveMessage($message);

        // Update conversation
        $conversation['last_activity'] = $now;
        $conversation['billing_info']['message_count']++;
        $this->dataLayer->saveConversation($conversation);

        return $message;
    }

    public function getConversation(string $conversationId): ?array
    {
        return $this->dataLayer->getConversation($conversationId);
    }

    public function getConversationMessages(string $conversationId, int $limit = 50, int $offset = 0): array
    {
        return $this->dataLayer->getConversationMessages($conversationId, $limit, $offset);
    }

    public function getCustomerConversations(string $customerId): array
    {
        $conversations = $this->dataLayer->getCustomerConversations($customerId);

        // Add last message preview to each conversation
        foreach ($conversations as &$conversation) {
            $messages = $this->dataLayer->getConversationMessages($conversation['conversation_id'], 1, 0);
            $conversation['last_message'] = !empty($messages) ? $messages[0] : null;
        }

        return $conversations;
    }

    public function getOperatorConversations(string $operatorId): array
    {
        $conversations = $this->dataLayer->getOperatorConversations($operatorId);

        // Add last message preview to each conversation
        foreach ($conversations as &$conversation) {
            $messages = $this->dataLayer->getConversationMessages($conversation['conversation_id'], 1, 0);
            $conversation['last_message'] = !empty($messages) ? $messages[0] : null;
        }

        return $conversations;
    }

    public function endConversation(string $conversationId): bool
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            throw new Exception('Conversation not found');
        }

        $conversation['status'] = 'ended';
        $conversation['ended_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveConversation($conversation);

        return true;
    }

    public function getConversationStats(string $conversationId): array
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            throw new Exception('Conversation not found');
        }

        $messages = $this->dataLayer->getConversationMessages($conversationId, 10000, 0); // Get all messages

        $customerMessages = array_filter($messages, fn($m) => $m['sender_type'] === 'customer');
        $operatorMessages = array_filter($messages, fn($m) => $m['sender_type'] === 'operator');

        return [
            'total_messages' => count($messages),
            'customer_messages' => count($customerMessages),
            'operator_messages' => count($operatorMessages),
            'total_customer_paid' => $conversation['billing_info']['total_customer_paid'],
            'total_operator_earned' => $conversation['billing_info']['total_operator_earned'],
            'customer_free_messages_remaining' => $conversation['billing_info']['customer_free_messages'],
            'duration' => $this->calculateConversationDuration($conversation),
            'status' => $conversation['status']
        ];
    }

    private function calculateConversationDuration(array $conversation): string
    {
        $start = strtotime($conversation['created_at']);
        $end = isset($conversation['ended_at']) ?
               strtotime($conversation['ended_at']) :
               strtotime($conversation['last_activity']);

        $duration = $end - $start;
        $minutes = floor($duration / 60);
        $hours = floor($minutes / 60);

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes % 60);
        } else {
            return sprintf('%dm', $minutes);
        }
    }

    public function markAsRead(string $conversationId, string $userId, string $userType): bool
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            return false;
        }

        $conversation['last_read_' . $userType] = date('Y-m-d H:i:s');
        $this->dataLayer->saveConversation($conversation);
        return true;
    }

    public function getBillingRates(): array
    {
        return [
            'message_rate' => self::MESSAGE_RATE,
            'content_rate' => self::CONTENT_RATE,
            'operator_commission' => self::OPERATOR_COMMISSION
        ];
    }

    /**
     * Send a paid operator message
     * Charges the customer and gives operator earnings
     */
    public function sendPaidOperatorMessage(string $conversationId, string $operatorId, string $content, float $rate = 1.99): array
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            throw new Exception('Conversation not found');
        }

        if ($conversation['status'] !== 'active') {
            throw new Exception('Conversation is not active');
        }

        if ($conversation['operator_id'] !== $operatorId) {
            throw new Exception('Operator not authorized for this conversation');
        }

        $messageId = 'msg_' . uniqid();
        $now = date('Y-m-d H:i:s');

        // Charge customer
        $customerManager = new CustomerManager();
        $customerManager->deductCredits($conversation['customer_id'], $rate, 'Paid operator message');

        // Update billing
        $operatorEarning = $rate * self::OPERATOR_COMMISSION;
        $conversation['billing_info']['total_customer_paid'] += $rate;
        $conversation['billing_info']['total_operator_earned'] += $operatorEarning;

        // Log activity
        $activityLogger = new ActivityLogger();
        $activityLogger->logSpending(
            $conversation['customer_id'],
            $operatorId,
            'paid_operator_message',
            $rate,
            [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'message_type' => 'paid'
            ]
        );

        // Create message
        $message = [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $operatorId,
            'sender_type' => 'operator',
            'message_type' => 'paid',
            'content' => $content,
            'attachments' => [],
            'sent_at' => $now,
            'billing' => [
                'cost' => $rate,
                'operator_earned' => $operatorEarning,
                'was_charged' => true
            ]
        ];

        $this->dataLayer->saveMessage($message);

        // Update conversation
        $conversation['last_activity'] = $now;
        $conversation['billing_info']['message_count']++;
        $this->dataLayer->saveConversation($conversation);

        return $message;
    }

    /**
     * Send a marketing message (free for customer)
     */
    public function sendMarketingMessage(string $conversationId, string $operatorId, string $content): array
    {
        $conversation = $this->dataLayer->getConversation($conversationId);
        if (!$conversation) {
            throw new Exception('Conversation not found');
        }

        if ($conversation['status'] !== 'active') {
            throw new Exception('Conversation is not active');
        }

        if ($conversation['operator_id'] !== $operatorId) {
            throw new Exception('Operator not authorized for this conversation');
        }

        $messageId = 'msg_' . uniqid();
        $now = date('Y-m-d H:i:s');

        // Marketing messages are free - no billing

        // Create message
        $message = [
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_id' => $operatorId,
            'sender_type' => 'operator',
            'message_type' => 'marketing',
            'content' => $content,
            'attachments' => [],
            'sent_at' => $now,
            'billing' => [
                'cost' => 0.00,
                'was_charged' => false
            ]
        ];

        $this->dataLayer->saveMessage($message);

        // Update conversation
        $conversation['last_activity'] = $now;
        $conversation['billing_info']['message_count']++;
        $this->dataLayer->saveConversation($conversation);

        return $message;
    }

    /**
     * Bulk send marketing message to all customers for an operator
     */
    public function sendBulkMarketingMessage(string $operatorId, string $content, string $siteDomain): array
    {
        $sentCount = 0;
        $failedCount = 0;
        $results = [];

        // Get all conversations for this operator
        $operatorConversations = $this->getOperatorConversations($operatorId);

        foreach ($operatorConversations as $conversation) {
            // Only send to active conversations on this site
            if ($conversation['status'] === 'active' && $conversation['site_domain'] === $siteDomain) {
                try {
                    $this->sendMarketingMessage($conversation['conversation_id'], $operatorId, $content);
                    $sentCount++;
                    $results[] = [
                        'customer_id' => $conversation['customer_id'],
                        'status' => 'sent'
                    ];
                } catch (Exception $e) {
                    $failedCount++;
                    $results[] = [
                        'customer_id' => $conversation['customer_id'],
                        'status' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'total_conversations' => count($operatorConversations),
            'results' => $results
        ];
    }
}
