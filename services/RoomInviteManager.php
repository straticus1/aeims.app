<?php
/**
 * Room Invite Manager
 * Handles operator invitations to private chat rooms with free time
 */

namespace AEIMS\Services;

class RoomInviteManager {
    private $invitesFile;
    private $notificationManager;

    public function __construct() {
        $this->invitesFile = __DIR__ . '/../data/room_invites.json';
        $this->notificationManager = new NotificationManager();
        $this->ensureDataFile();
    }

    private function ensureDataFile() {
        if (!file_exists($this->invitesFile)) {
            file_put_contents($this->invitesFile, json_encode([]));
        }
    }

    private function loadInvites() {
        $data = file_get_contents($this->invitesFile);
        $invites = json_decode($data, true);
        return $invites ?: [];
    }

    private function saveInvites($invites) {
        file_put_contents($this->invitesFile, json_encode($invites, JSON_PRETTY_PRINT));
    }

    /**
     * Create a room invite with free time
     */
    public function createInvite($operatorId, $customerId, $roomId, $freeMinutes, $message = '') {
        $invites = $this->loadInvites();

        // Check for duplicate pending invites
        foreach ($invites as $invite) {
            if ($invite['operator_id'] === $operatorId &&
                $invite['customer_id'] === $customerId &&
                $invite['room_id'] === $roomId &&
                $invite['status'] === 'pending') {
                throw new \Exception('You already have a pending invite for this customer to this room');
            }
        }

        $inviteId = uniqid('inv_', true);
        $newInvite = [
            'invite_id' => $inviteId,
            'operator_id' => $operatorId,
            'customer_id' => $customerId,
            'room_id' => $roomId,
            'free_minutes' => (int)$freeMinutes,
            'message' => $message,
            'status' => 'pending', // pending, accepted, declined, expired, used
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'responded_at' => null,
            'used_at' => null,
            'minutes_used' => 0
        ];

        $invites[] = $newInvite;
        $this->saveInvites($invites);

        // Send notification to customer
        $this->notificationManager->createNotification(
            $customerId,
            'room_invite',
            '🎁 Free Room Invite!',
            'You\'ve been invited to a private room with ' . $freeMinutes . ' minutes free!',
            '/room-invites.php?invite_id=' . $inviteId
        );

        return $newInvite;
    }

    /**
     * Get all invites for a customer
     */
    public function getCustomerInvites($customerId, $status = null) {
        $invites = $this->loadInvites();

        $filtered = array_filter($invites, function($inv) use ($customerId, $status) {
            if ($inv['customer_id'] !== $customerId) {
                return false;
            }
            if ($status && $inv['status'] !== $status) {
                return false;
            }
            return true;
        });

        usort($filtered, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_values($filtered);
    }

    /**
     * Get all invites sent by an operator
     */
    public function getOperatorInvites($operatorId, $status = null) {
        $invites = $this->loadInvites();

        $filtered = array_filter($invites, function($inv) use ($operatorId, $status) {
            if ($inv['operator_id'] !== $operatorId) {
                return false;
            }
            if ($status && $inv['status'] !== $status) {
                return false;
            }
            return true;
        });

        usort($filtered, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_values($filtered);
    }

    /**
     * Get a single invite by ID
     */
    public function getInvite($inviteId) {
        $invites = $this->loadInvites();

        foreach ($invites as $invite) {
            if ($invite['invite_id'] === $inviteId) {
                return $invite;
            }
        }

        return null;
    }

    /**
     * Accept an invite
     */
    public function acceptInvite($inviteId, $customerId) {
        $invites = $this->loadInvites();
        $found = false;

        foreach ($invites as &$invite) {
            if ($invite['invite_id'] === $inviteId) {
                // Verify the customer is the recipient
                if ($invite['customer_id'] !== $customerId) {
                    throw new \Exception('Unauthorized');
                }

                if ($invite['status'] !== 'pending') {
                    throw new \Exception('Invite is no longer pending');
                }

                $invite['status'] = 'accepted';
                $invite['responded_at'] = date('Y-m-d H:i:s');
                $found = true;

                // Notify operator
                $this->notificationManager->createNotification(
                    $invite['operator_id'],
                    'invite_accepted',
                    '🎉 Room Invite Accepted!',
                    'Your room invite was accepted!',
                    '/agents/create-room.php'
                );

                break;
            }
        }

        if (!$found) {
            throw new \Exception('Invite not found');
        }

        $this->saveInvites($invites);
        return $invite;
    }

    /**
     * Decline an invite
     */
    public function declineInvite($inviteId, $customerId) {
        $invites = $this->loadInvites();
        $found = false;

        foreach ($invites as &$invite) {
            if ($invite['invite_id'] === $inviteId) {
                // Verify the customer is the recipient
                if ($invite['customer_id'] !== $customerId) {
                    throw new \Exception('Unauthorized');
                }

                if ($invite['status'] !== 'pending') {
                    throw new \Exception('Invite is no longer pending');
                }

                $invite['status'] = 'declined';
                $invite['responded_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Invite not found');
        }

        $this->saveInvites($invites);
        return $invite;
    }

    /**
     * Use free time from invite
     */
    public function useFreeTime($inviteId, $minutesUsed) {
        $invites = $this->loadInvites();
        $found = false;

        foreach ($invites as &$invite) {
            if ($invite['invite_id'] === $inviteId) {
                if ($invite['status'] !== 'accepted') {
                    throw new \Exception('Invite must be accepted to use free time');
                }

                $remainingMinutes = $invite['free_minutes'] - $invite['minutes_used'];

                if ($minutesUsed > $remainingMinutes) {
                    throw new \Exception('Not enough free time remaining');
                }

                $invite['minutes_used'] += $minutesUsed;

                if (!$invite['used_at']) {
                    $invite['used_at'] = date('Y-m-d H:i:s');
                }

                // Mark as used if all free time consumed
                if ($invite['minutes_used'] >= $invite['free_minutes']) {
                    $invite['status'] = 'used';
                }

                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Invite not found');
        }

        $this->saveInvites($invites);
        return $invite;
    }

    /**
     * Get remaining free minutes for an invite
     */
    public function getRemainingMinutes($inviteId) {
        $invite = $this->getInvite($inviteId);

        if (!$invite) {
            return 0;
        }

        if ($invite['status'] !== 'accepted') {
            return 0;
        }

        return max(0, $invite['free_minutes'] - $invite['minutes_used']);
    }

    /**
     * Expire old pending invites
     */
    public function expireOldInvites() {
        $invites = $this->loadInvites();
        $now = time();
        $expired = 0;

        foreach ($invites as &$invite) {
            if ($invite['status'] === 'pending' &&
                strtotime($invite['expires_at']) < $now) {
                $invite['status'] = 'expired';
                $expired++;
            }
        }

        if ($expired > 0) {
            $this->saveInvites($invites);
        }

        return $expired;
    }

    /**
     * Get pending invite count for customer
     */
    public function getPendingCount($customerId) {
        $invites = $this->getCustomerInvites($customerId, 'pending');
        return count($invites);
    }

    /**
     * Check if customer has active invite for a room
     */
    public function hasActiveInvite($customerId, $roomId) {
        $invites = $this->loadInvites();

        foreach ($invites as $invite) {
            if ($invite['customer_id'] === $customerId &&
                $invite['room_id'] === $roomId &&
                $invite['status'] === 'accepted' &&
                $invite['minutes_used'] < $invite['free_minutes']) {
                return $invite;
            }
        }

        return null;
    }
}
