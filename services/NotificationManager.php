<?php

namespace AEIMS\Services;

/**
 * Notification Management Service
 * Handles real-time notifications for customers
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class NotificationManager
{
    private $dataLayer;

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
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

        $this->dataLayer->saveNotification($notification);
        return $notification;
    }

    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications(string $userId): array
    {
        return $this->dataLayer->searchNotifications(['user_id' => $userId, 'read' => false]);
    }

    /**
     * Get all notifications for a user
     */
    public function getAllNotifications(string $userId, int $limit = 50): array
    {
        return $this->dataLayer->searchNotifications(['user_id' => $userId], $limit);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): bool
    {
        $notification = $this->dataLayer->getNotification($notificationId);
        if (!$notification) {
            return false;
        }

        $notification['read'] = true;
        $this->dataLayer->saveNotification($notification);
        return true;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(string $userId): int
    {
        return $this->dataLayer->markAllNotificationsRead($userId);
    }

    /**
     * Clear all notifications for a user
     */
    public function clearAll(string $userId): int
    {
        return $this->dataLayer->clearUserNotifications($userId);
    }

    /**
     * Delete a specific notification
     */
    public function deleteNotification(string $notificationId): bool
    {
        return $this->dataLayer->deleteNotification($notificationId);
    }

    /**
     * Get count of unread notifications
     */
    public function getUnreadCount(string $userId): int
    {
        return $this->dataLayer->getUnreadNotificationCount($userId);
    }

    /**
     * Clean up old notifications (older than 30 days)
     */
    public function cleanupOldNotifications(): int
    {
        return $this->dataLayer->cleanupOldNotifications(30);
    }
}
