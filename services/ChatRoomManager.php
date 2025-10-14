<?php

namespace AEIMS\Services;

use Exception;

/**
 * Chat Room Management Service
 * Manages private and public chat rooms with multi-user support
 */
class ChatRoomManager
{
    private string $roomsFile;
    private string $messagesFile;
    private array $rooms = [];
    private array $messages = [];

    public function __construct()
    {
        $this->roomsFile = __DIR__ . '/../data/chat_rooms.json';
        $this->messagesFile = __DIR__ . '/../data/room_messages.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->roomsFile)) {
            $this->rooms = json_decode(file_get_contents($this->roomsFile), true) ?? [];
        }

        if (file_exists($this->messagesFile)) {
            $this->messages = json_decode(file_get_contents($this->messagesFile), true) ?? [];
        }
    }

    private function saveRooms(): void
    {
        $dataDir = dirname($this->roomsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->roomsFile, json_encode($this->rooms, JSON_PRETTY_PRINT));
    }

    private function saveMessages(): void
    {
        $dataDir = dirname($this->messagesFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->messagesFile, json_encode($this->messages, JSON_PRETTY_PRINT));
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

        $this->rooms[] = $room;
        $this->saveRooms();

        return $room;
    }

    /**
     * Get room by ID
     */
    public function getRoomById(string $roomId): ?array
    {
        foreach ($this->rooms as $room) {
            if ($room['room_id'] === $roomId) {
                return $room;
            }
        }
        return null;
    }

    /**
     * Get all rooms by operator
     */
    public function getRoomsByOperator(string $operatorId): array
    {
        $operatorRooms = [];
        foreach ($this->rooms as $room) {
            if ($room['operator_id'] === $operatorId && $room['active']) {
                $operatorRooms[] = $room;
            }
        }
        return $operatorRooms;
    }

    /**
     * Get all public rooms (no PIN or free entry)
     */
    public function getAllPublicRooms(): array
    {
        $publicRooms = [];
        foreach ($this->rooms as $room) {
            if ($room['active'] && (empty($room['pin_code']) || $room['entry_fee'] == 0)) {
                $publicRooms[] = $room;
            }
        }
        return $publicRooms;
    }

    /**
     * Get all rooms (for browse/search)
     */
    public function getAllRooms(): array
    {
        $activeRooms = [];
        foreach ($this->rooms as $room) {
            if ($room['active']) {
                $activeRooms[] = $room;
            }
        }
        return $activeRooms;
    }

    /**
     * Join a room
     */
    public function joinRoom(string $roomId, string $customerId, ?string $pinCode = null): array
    {
        $roomIndex = null;
        $room = null;

        foreach ($this->rooms as $index => $r) {
            if ($r['room_id'] === $roomId) {
                $roomIndex = $index;
                $room = $r;
                break;
            }
        }

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
            $this->rooms[$roomIndex]['members'][] = [
                'customer_id' => $customerId,
                'joined_at' => date('c'),
                'last_seen' => date('c'),
                'total_time_minutes' => 0,
                'total_spent' => 0.0
            ];
            $this->rooms[$roomIndex]['stats']['total_members']++;
        } else {
            // Update last seen
            foreach ($this->rooms[$roomIndex]['members'] as $mIndex => $member) {
                if ($member['customer_id'] === $customerId) {
                    $this->rooms[$roomIndex]['members'][$mIndex]['last_seen'] = date('c');
                    break;
                }
            }
        }

        $this->rooms[$roomIndex]['current_users']++;
        $this->saveRooms();

        return [
            'success' => true,
            'room' => $this->rooms[$roomIndex],
            'entry_fee' => !$isMember ? $room['entry_fee'] : 0.0
        ];
    }

    /**
     * Leave a room
     */
    public function leaveRoom(string $roomId, string $customerId): array
    {
        $roomIndex = null;

        foreach ($this->rooms as $index => $room) {
            if ($room['room_id'] === $roomId) {
                $roomIndex = $index;
                break;
            }
        }

        if ($roomIndex === null) {
            return ['success' => false, 'message' => 'Room not found'];
        }

        if ($this->rooms[$roomIndex]['current_users'] > 0) {
            $this->rooms[$roomIndex]['current_users']--;
        }

        $this->saveRooms();

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

        $this->messages[] = $message;
        $this->saveMessages();

        // Update room stats
        foreach ($this->rooms as $index => $r) {
            if ($r['room_id'] === $roomId) {
                $this->rooms[$index]['stats']['total_messages']++;
                $this->saveRooms();
                break;
            }
        }

        return ['success' => true, 'message' => $message];
    }

    /**
     * Get room messages
     */
    public function getRoomMessages(string $roomId, int $limit = 50, int $offset = 0): array
    {
        $roomMessages = [];

        foreach ($this->messages as $message) {
            if ($message['room_id'] === $roomId) {
                $roomMessages[] = $message;
            }
        }

        // Sort by timestamp (newest first)
        usort($roomMessages, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        // Apply limit and offset
        $roomMessages = array_slice($roomMessages, $offset, $limit);

        // Reverse to show oldest first
        return array_reverse($roomMessages);
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
        foreach ($this->rooms as $rIndex => $r) {
            if ($r['room_id'] === $roomId) {
                foreach ($this->rooms[$rIndex]['members'] as $mIndex => $member) {
                    if ($member['customer_id'] === $customerId) {
                        $this->rooms[$rIndex]['members'][$mIndex]['total_time_minutes'] += $minutesElapsed;
                        $this->rooms[$rIndex]['members'][$mIndex]['total_spent'] += $amount;
                        break;
                    }
                }
                $this->rooms[$rIndex]['total_revenue'] += $amount;
                $this->rooms[$rIndex]['stats']['total_time_minutes'] += $minutesElapsed;
                $this->saveRooms();
                break;
            }
        }

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
        foreach ($this->rooms as $index => $room) {
            if ($room['room_id'] === $roomId) {
                foreach ($updates as $key => $value) {
                    if (in_array($key, ['name', 'description', 'pin_code', 'entry_fee', 'per_minute_rate', 'active'])) {
                        $this->rooms[$index][$key] = $value;
                    }
                }
                $this->saveRooms();
                return ['success' => true, 'room' => $this->rooms[$index]];
            }
        }

        return ['success' => false, 'message' => 'Room not found'];
    }

    /**
     * Delete/deactivate a room
     */
    public function deleteRoom(string $roomId): array
    {
        foreach ($this->rooms as $index => $room) {
            if ($room['room_id'] === $roomId) {
                $this->rooms[$index]['active'] = false;
                $this->saveRooms();
                return ['success' => true];
            }
        }

        return ['success' => false, 'message' => 'Room not found'];
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
