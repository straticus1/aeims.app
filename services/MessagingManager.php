<?php

namespace AEIMS\Services;

use Exception;

/**
 * Messaging System Manager
 * Handles customer-operator messaging with smart billing rules
 */
class MessagingManager
{
    private array $conversations = [];
    private array $messages = [];
    private string $conversationsFile;
    private string $messagesFile;

    // Billing rates
    private const MESSAGE_RATE = 0.50;  // Standard message rate
    private const CONTENT_RATE = 0.99;  // Media/content rate
    private const OPERATOR_COMMISSION = 0.65; // 65% to operator

    public function __construct()
    {
        $this->conversationsFile = __DIR__ . '/../data/conversations.json';
        $this->messagesFile = __DIR__ . '/../data/messages.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        // Load conversations
        if (file_exists($this->conversationsFile)) {
            $data = json_decode(file_get_contents($this->conversationsFile), true);
            $this->conversations = $data['conversations'] ?? [];
        }

        // Load messages
        if (file_exists($this->messagesFile)) {
            $data = json_decode(file_get_contents($this->messagesFile), true);
            $this->messages = $data['messages'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->conversationsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Save conversations
        $conversationData = [
            'conversations' => $this->conversations,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->conversationsFile, json_encode($conversationData, JSON_PRETTY_PRINT));

        // Save messages
        $messageData = [
            'messages' => $this->messages,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->messagesFile, json_encode($messageData, JSON_PRETTY_PRINT));
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

        $this->conversations[$conversationId] = $conversation;
        $this->saveData();

        return $conversationId;
    }

    public function sendMessage(string $conversationId, string $senderId, string $content, string $senderType, array $attachments = []): array
    {
        if (!isset($this->conversations[$conversationId])) {
            throw new Exception('Conversation not found');
        }

        $conversation = &$this->conversations[$conversationId];

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

        $this->messages[$messageId] = $message;

        // Update conversation
        $conversation['last_activity'] = $now;
        $conversation['billing_info']['message_count']++;

        $this->saveData();

        return $message;
    }

    public function getConversation(string $conversationId): ?array
    {
        return $this->conversations[$conversationId] ?? null;
    }

    public function getConversationMessages(string $conversationId, int $limit = 50, int $offset = 0): array
    {
        $conversationMessages = [];

        foreach ($this->messages as $message) {
            if ($message['conversation_id'] === $conversationId) {
                $conversationMessages[] = $message;
            }
        }

        // Sort by sent_at descending (newest first)
        usort($conversationMessages, function($a, $b) {
            return strtotime($b['sent_at']) - strtotime($a['sent_at']);
        });

        return array_slice($conversationMessages, $offset, $limit);
    }

    public function getCustomerConversations(string $customerId): array
    {
        $customerConversations = [];

        foreach ($this->conversations as $conversation) {
            if ($conversation['customer_id'] === $customerId) {
                // Add last message preview
                $lastMessage = $this->getLastMessage($conversation['conversation_id']);
                $conversation['last_message'] = $lastMessage;
                $customerConversations[] = $conversation;
            }
        }

        // Sort by last activity
        usort($customerConversations, function($a, $b) {
            return strtotime($b['last_activity']) - strtotime($a['last_activity']);
        });

        return $customerConversations;
    }

    public function getOperatorConversations(string $operatorId): array
    {
        $operatorConversations = [];

        foreach ($this->conversations as $conversation) {
            if ($conversation['operator_id'] === $operatorId) {
                // Add last message preview
                $lastMessage = $this->getLastMessage($conversation['conversation_id']);
                $conversation['last_message'] = $lastMessage;
                $operatorConversations[] = $conversation;
            }
        }

        // Sort by last activity
        usort($operatorConversations, function($a, $b) {
            return strtotime($b['last_activity']) - strtotime($a['last_activity']);
        });

        return $operatorConversations;
    }

    private function getLastMessage(string $conversationId): ?array
    {
        $lastMessage = null;
        $latestTime = '';

        foreach ($this->messages as $message) {
            if ($message['conversation_id'] === $conversationId) {
                if ($message['sent_at'] > $latestTime) {
                    $latestTime = $message['sent_at'];
                    $lastMessage = $message;
                }
            }
        }

        return $lastMessage;
    }

    public function endConversation(string $conversationId): bool
    {
        if (!isset($this->conversations[$conversationId])) {
            throw new Exception('Conversation not found');
        }

        $this->conversations[$conversationId]['status'] = 'ended';
        $this->conversations[$conversationId]['ended_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return true;
    }

    public function getConversationStats(string $conversationId): array
    {
        if (!isset($this->conversations[$conversationId])) {
            throw new Exception('Conversation not found');
        }

        $conversation = $this->conversations[$conversationId];
        $messages = $this->getConversationMessages($conversationId, 1000); // Get all messages

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
        // This could be expanded to track read status per user
        // For now, just update last activity
        if (isset($this->conversations[$conversationId])) {
            $this->conversations[$conversationId]['last_read_' . $userType] = date('Y-m-d H:i:s');
            $this->saveData();
            return true;
        }
        return false;
    }

    public function getBillingRates(): array
    {
        return [
            'message_rate' => self::MESSAGE_RATE,
            'content_rate' => self::CONTENT_RATE,
            'operator_commission' => self::OPERATOR_COMMISSION
        ];
    }
}