<?php
/**
 * Content Marketplace Manager
 * Handles operator content items (pictures, videos, etc.) for sale or free
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */

namespace AEIMS\Services;

class ContentMarketplaceManager {
    private $dataLayer;
    private $notificationManager;

    public function __construct() {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
        $this->notificationManager = new NotificationManager();
    }

    /**
     * Create a new content item
     */
    public function createItem($operatorId, $type, $title, $description, $price, $fileUrl, $thumbnailUrl = null, $tags = []) {
        $itemId = uniqid('item_', true);
        $newItem = [
            'item_id' => $itemId,
            'operator_id' => $operatorId,
            'type' => $type, // photo, video, audio, document
            'title' => $title,
            'description' => $description,
            'price' => (float)$price, // 0.00 for free items
            'file_url' => $fileUrl,
            'thumbnail_url' => $thumbnailUrl ?: '/assets/images/content/placeholder.jpg',
            'tags' => $tags,
            'status' => 'active', // active, inactive, removed
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'views' => 0,
            'purchases' => 0,
            'earnings' => 0.00
        ];

        $this->dataLayer->saveContentItem($newItem);
        return $newItem;
    }

    /**
     * Get all items by operator
     */
    public function getOperatorItems($operatorId, $status = null) {
        $filters = ['operator_id' => $operatorId];
        if ($status) {
            $filters['status'] = $status;
        }
        return $this->dataLayer->searchContentItems($filters);
    }

    /**
     * Get all active items (for marketplace browsing)
     */
    public function getAllActiveItems($operatorId = null, $type = null) {
        $filters = ['status' => 'active'];
        if ($operatorId) {
            $filters['operator_id'] = $operatorId;
        }
        if ($type) {
            $filters['type'] = $type;
        }
        return $this->dataLayer->searchContentItems($filters);
    }

    /**
     * Get a single item
     */
    public function getItem($itemId) {
        return $this->dataLayer->getContentItem($itemId);
    }

    /**
     * Update item
     */
    public function updateItem($itemId, $operatorId, $updates) {
        $item = $this->dataLayer->getContentItem($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Verify ownership
        if ($item['operator_id'] !== $operatorId) {
            throw new \Exception('Unauthorized');
        }

        // Update allowed fields
        $allowedFields = ['title', 'description', 'price', 'thumbnail_url', 'tags', 'status'];
        foreach ($allowedFields as $field) {
            if (isset($updates[$field])) {
                $item[$field] = $updates[$field];
            }
        }

        $item['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveContentItem($item);

        return $item;
    }

    /**
     * Increment view count
     */
    public function incrementViews($itemId) {
        $item = $this->dataLayer->getContentItem($itemId);

        if ($item) {
            $item['views']++;
            $this->dataLayer->saveContentItem($item);
            return $item;
        }
        return null;
    }

    /**
     * Purchase/access an item
     */
    public function purchaseItem($itemId, $customerId, $paymentAmount = 0.00) {
        // Check if already purchased
        if ($this->dataLayer->hasContentPurchase($customerId, $itemId)) {
            return $this->dataLayer->getContentPurchase($customerId, $itemId);
        }

        // Get item
        $item = $this->dataLayer->getContentItem($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        if ($item['status'] !== 'active') {
            throw new \Exception('Item is not available');
        }

        // Verify price
        if ($item['price'] > 0 && $paymentAmount < $item['price']) {
            throw new \Exception('Insufficient payment');
        }

        // Create purchase record
        $purchaseId = uniqid('purchase_', true);
        $purchase = [
            'purchase_id' => $purchaseId,
            'item_id' => $itemId,
            'customer_id' => $customerId,
            'operator_id' => $item['operator_id'],
            'price_paid' => $item['price'],
            'purchased_at' => date('Y-m-d H:i:s'),
            'access_granted' => true
        ];

        $this->dataLayer->saveContentPurchase($purchase);

        // Update item stats
        $item['purchases']++;
        $item['earnings'] += $item['price'];
        $this->dataLayer->saveContentItem($item);

        // Notify operator of purchase
        if ($item['price'] > 0) {
            $this->notificationManager->createNotification(
                $item['operator_id'],
                'content_purchase',
                '💰 Content Purchased!',
                'Someone purchased your "' . $item['title'] . '" for $' . number_format($item['price'], 2),
                '/agents/content-marketplace.php'
            );
        }

        return $purchase;
    }

    /**
     * Check if customer owns item
     */
    public function customerOwnsItem($customerId, $itemId) {
        // Check purchases
        if ($this->dataLayer->hasContentPurchase($customerId, $itemId)) {
            return true;
        }

        // Check if item is free
        $item = $this->getItem($itemId);
        if ($item && $item['price'] == 0) {
            return true;
        }

        return false;
    }

    /**
     * Get customer's purchased items
     */
    public function getCustomerPurchases($customerId) {
        return $this->dataLayer->getCustomerContentPurchases($customerId);
    }

    /**
     * Get operator's sales
     */
    public function getOperatorSales($operatorId) {
        return $this->dataLayer->getOperatorContentSales($operatorId);
    }

    /**
     * Get operator total earnings from content
     */
    public function getOperatorEarnings($operatorId) {
        $items = $this->getOperatorItems($operatorId);
        $totalEarnings = 0;

        foreach ($items as $item) {
            $totalEarnings += $item['earnings'];
        }

        return $totalEarnings;
    }

    /**
     * Delete item
     */
    public function deleteItem($itemId, $operatorId) {
        $item = $this->dataLayer->getContentItem($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Verify ownership
        if ($item['operator_id'] !== $operatorId) {
            throw new \Exception('Unauthorized');
        }

        $item['status'] = 'removed';
        $item['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveContentItem($item);

        return true;
    }

    /**
     * Send content item to customer
     */
    public function sendItemToCustomer($itemId, $customerId, $operatorId, $message = '') {
        $item = $this->getItem($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        if ($item['operator_id'] !== $operatorId) {
            throw new \Exception('Unauthorized');
        }

        // Create notification with item access
        $this->notificationManager->createNotification(
            $customerId,
            'content_gift',
            '🎁 Content Gift from Operator!',
            $message ?: 'You received: "' . $item['title'] . '"',
            '/content-marketplace.php?item_id=' . $itemId
        );

        // Grant access (create free purchase)
        $this->purchaseItem($itemId, $customerId, 0);

        return true;
    }
}
