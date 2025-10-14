<?php

namespace AEIMS\Services;

use Exception;

/**
 * Chat Room Management Service
 * Manages private and public chat rooms with multi-user support
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class ChatRoomManager
{
    private $dataLayer;

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
    }

    /**
     * Create a new chat room
     */
    public function createRoom(
        string $operatorId,
        string $roomName,
        string $description,
        ?string $pinCode = null,
        float $entryFee = 0.0,
        float $perMinuteRate = 0.0
    ): array {
        $roomId = 'room_' . bin2hex(random_bytes(8));

        $room = [
            'room_id' => $roomId,
            'operator_id' => $operatorId,
            'name' => $roomName,
            'description' => $description,
            'pin_code' => $pinCode,
            'entry_fee' => $entryFee,
            'per_minute_rate' => $perMinuteRate,
            'active' => true,
            'created_at' => date('c'),
            'members' => [],
            'current_users' => 0,
            'total_revenue' => 0.0,
            'stats' => [
                'total_members' => 0,
                'total_messages' => 0,
                'total_time_minutes' => 0
            ]
        ];

        $this->dataLayer->saveChatRoom($room);
        return $room;
    }

    /**
     * Get room by ID
     */
    public function getRoomById(string $roomId): ?array
    {
        return $this->dataLayer->getChatRoom($roomId);
    }

    /**
     * Get all rooms by operator
     */
    public function getRoomsByOperator(string $operatorId): array
    {
        return $this->dataLayer->searchChatRooms(['operator_id' => $operatorId, 'active' => true]);
    }

    /**
     * Get all public rooms (no PIN or free entry)
     */
    public function getAllPublicRooms(): array
    {
        return $this->dataLayer->searchChatRooms(['active' => true, 'is_public' => true]);
    }

    /**
     * Get all rooms (for browse/search)
     */
    public function getAllRooms(): array
    {
        return $this->dataLayer->searchChatRooms(['active' => true]);
    }

    /**
     * Join a room
     */
    public function joinRoom(string $roomId, string $customerId, ?string $pinCode = null): array
    {
        $room = $this->dataLayer->getChatRoom($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        if (!$room['active']) {
            return ['success' => false, 'message' => 'Room is not active'];
        }

        // Check PIN if required
        if (!empty($room['pin_code']) && $room['pin_code'] !== $pinCode) {
            return ['success' => false, 'message' => 'Invalid PIN code'];
        }

        // Check if already a member
        $isMember = false;
        foreach ($room['members'] as $member) {
            if ($member['customer_id'] === $customerId) {
                $isMember = true;
                break;
            }
        }

        if (!$isMember) {
            // Add as new member
            $room['members'][] = [
                'customer_id' => $customerId,
                'joined_at' => date('c'),
                'last_seen' => date('c'),
                'total_time_minutes' => 0,
                'total_spent' => 0.0
            ];
            $room['stats']['total_members']++;
        } else {
            // Update last seen
            foreach ($room['members'] as $mIndex => $member) {
                if ($member['customer_id'] === $customerId) {
                    $room['members'][$mIndex]['last_seen'] = date('c');
                    break;
                }
            }
        }

        $room['current_users']++;
        $this->dataLayer->saveChatRoom($room);

        return [
            'success' => true,
            'room' => $room,
            'entry_fee' => !$isMember ? $room['entry_fee'] : 0.0
        ];
    }

    /**
     * Leave a room
     */
    public function leaveRoom(string $roomId, string $customerId): array
    {
        $room = $this->dataLayer->getChatRoom($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        if ($room['current_users'] > 0) {
            $room['current_users']--;
        }

        $this->dataLayer->saveChatRoom($room);

        return ['success' => true];
    }

    /**
     * Send a message to a room
     */
    public function sendMessage(
        string $roomId,
        string $senderId,
        string $senderType,
        string $content
    ): array {
        $room = $this->getRoomById($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        $message = [
            'message_id' => 'msg_' . bin2hex(random_bytes(8)),
            'room_id' => $roomId,
            'sender_id' => $senderId,
            'sender_type' => $senderType, // 'customer' or 'operator'
            'content' => $content,
            'timestamp' => date('c'),
            'read_by' => []
        ];

        $this->dataLayer->saveRoomMessage($message);

        // Update room stats
        $room['stats']['total_messages']++;
        $this->dataLayer->saveChatRoom($room);

        return ['success' => true, 'message' => $message];
    }

    /**
     * Get room messages
     */
    public function getRoomMessages(string $roomId, int $limit = 50, int $offset = 0): array
    {
        return $this->dataLayer->getRoomMessages($roomId, $limit, $offset);
    }

    /**
     * Calculate and charge room fees
     */
    public function chargeRoomFees(string $roomId, string $customerId, int $minutesElapsed): array
    {
        $room = $this->getRoomById($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found', 'amount' => 0.0];
        }

        $amount = $room['per_minute_rate'] * $minutesElapsed;

        // Update member stats
        foreach ($room['members'] as $mIndex => $member) {
            if ($member['customer_id'] === $customerId) {
                $room['members'][$mIndex]['total_time_minutes'] += $minutesElapsed;
                $room['members'][$mIndex]['total_spent'] += $amount;
                break;
            }
        }
        $room['total_revenue'] += $amount;
        $room['stats']['total_time_minutes'] += $minutesElapsed;
        $this->dataLayer->saveChatRoom($room);

        return [
            'success' => true,
            'amount' => $amount,
            'minutes' => $minutesElapsed
        ];
    }

    /**
     * Update room details
     */
    public function updateRoom(string $roomId, array $updates): array
    {
        $room = $this->dataLayer->getChatRoom($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['name', 'description', 'pin_code', 'entry_fee', 'per_minute_rate', 'active'])) {
                $room[$key] = $value;
            }
        }
        $this->dataLayer->saveChatRoom($room);

        return ['success' => true, 'room' => $room];
    }

    /**
     * Delete/deactivate a room
     */
    public function deleteRoom(string $roomId): array
    {
        $room = $this->dataLayer->getChatRoom($roomId);

        if (!$room) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        $room['active'] = false;
        $this->dataLayer->saveChatRoom($room);

        return ['success' => true];
    }

    /**
     * Get room member count
     */
    public function getRoomMemberCount(string $roomId): int
    {
        $room = $this->getRoomById($roomId);
        return $room ? count($room['members']) : 0;
    }

    /**
     * Check if customer is in room
     */
    public function isCustomerInRoom(string $roomId, string $customerId): bool
    {
        $room = $this->getRoomById($roomId);

        if (!$room) {
            return false;
        }

        foreach ($room['members'] as $member) {
            if ($member['customer_id'] === $customerId) {
                return true;
            }
        }

        return false;
    }
}
