<?php

namespace AEIMS\Services;

/**
 * Notification Management Service
 * Handles real-time notifications for customers
 */
class NotificationManager
{
    private string $dataFile;
    private array $notifications = [];

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/notifications.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $this->notifications = json_decode(file_get_contents($this->dataFile), true) ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($this->dataFile, json_encode($this->notifications, JSON_PRETTY_PRINT));
    }

    /**
     * Create a new notification
     */
    public function createNotification(
        string $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null
    ): array {
        $notification = [
            'notification_id' => 'notif_' . bin2hex(random_bytes(8)),
            'user_id' => $userId,
            'type' => $type, // chat, room_invite, mail, message_sent, system
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'read' => false,
            'created_at' => date('c'),
            'timestamp' => time()
        ];

        $this->notifications[] = $notification;
        $this->saveData();

        return $notification;
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications(string $userId): array
    {
        $unread = [];

        foreach ($this->notifications as $notification) {
            if ($notification['user_id'] === $userId && !$notification['read']) {
                $unread[] = $notification;
            }
        }

        // Sort by timestamp (newest first)
        usort($unread, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $unread;
    }

    /**
     * Get all notifications for a user
     */
    public function getAllNotifications(string $userId, int $limit = 50): array
    {
        $userNotifications = [];

        foreach ($this->notifications as $notification) {
            if ($notification['user_id'] === $userId) {
                $userNotifications[] = $notification;
            }
        }

        // Sort by timestamp (newest first)
        usort($userNotifications, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return array_slice($userNotifications, 0, $limit);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): bool
    {
        foreach ($this->notifications as $index => $notification) {
            if ($notification['notification_id'] === $notificationId) {
                $this->notifications[$index]['read'] = true;
                $this->saveData();
                return true;
            }
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(string $userId): int
    {
        $count = 0;

        foreach ($this->notifications as $index => $notification) {
            if ($notification['user_id'] === $userId && !$notification['read']) {
                $this->notifications[$index]['read'] = true;
                $count++;
            }
        }

        if ($count > 0) {
            $this->saveData();
        }

        return $count;
    }

    /**
     * Clear all notifications for a user
     */
    public function clearAll(string $userId): int
    {
        $originalCount = count($this->notifications);

        $this->notifications = array_filter($this->notifications, function ($notification) use ($userId) {
            return $notification['user_id'] !== $userId;
        });

        $this->notifications = array_values($this->notifications); // Re-index array

        $removedCount = $originalCount - count($this->notifications);

        if ($removedCount > 0) {
            $this->saveData();
        }

        return $removedCount;
    }

    /**
     * Delete a specific notification
     */
    public function deleteNotification(string $notificationId): bool
    {
        $originalCount = count($this->notifications);

        $this->notifications = array_filter($this->notifications, function ($notification) use ($notificationId) {
            return $notification['notification_id'] !== $notificationId;
        });

        $this->notifications = array_values($this->notifications);

        if (count($this->notifications) < $originalCount) {
            $this->saveData();
            return true;
        }

        return false;
    }

    /**
     * Get count of unread notifications
     */
    public function getUnreadCount(string $userId): int
    {
        $count = 0;

        foreach ($this->notifications as $notification) {
            if ($notification['user_id'] === $userId && !$notification['read']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clean up old notifications (older than 30 days)
     */
    public function cleanupOldNotifications(): int
    {
        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
        $originalCount = count($this->notifications);

        $this->notifications = array_filter($this->notifications, function ($notification) use ($thirtyDaysAgo) {
            return $notification['timestamp'] >= $thirtyDaysAgo;
        });

        $this->notifications = array_values($this->notifications);

        $removedCount = $originalCount - count($this->notifications);

        if ($removedCount > 0) {
            $this->saveData();
        }

        return $removedCount;
    }
}
