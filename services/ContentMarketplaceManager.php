<?php
/**
 * Content Marketplace Manager
 * Handles operator content items (pictures, videos, etc.) for sale or free
 */

namespace AEIMS\Services;

class ContentMarketplaceManager {
    private $itemsFile;
    private $purchasesFile;
    private $notificationManager;

    public function __construct() {
        $this->itemsFile = __DIR__ . '/../data/content_items.json';
        $this->purchasesFile = __DIR__ . '/../data/content_purchases.json';
        $this->notificationManager = new NotificationManager();
        $this->ensureDataFiles();
    }

    private function ensureDataFiles() {
        if (!file_exists($this->itemsFile)) {
            file_put_contents($this->itemsFile, json_encode([]));
        }
        if (!file_exists($this->purchasesFile)) {
            file_put_contents($this->purchasesFile, json_encode([]));
        }
    }

    private function loadItems() {
        $data = file_get_contents($this->itemsFile);
        $items = json_decode($data, true);
        return $items ?: [];
    }

    private function saveItems($items) {
        file_put_contents($this->itemsFile, json_encode($items, JSON_PRETTY_PRINT));
    }

    private function loadPurchases() {
        $data = file_get_contents($this->purchasesFile);
        $purchases = json_decode($data, true);
        return $purchases ?: [];
    }

    private function savePurchases($purchases) {
        file_put_contents($this->purchasesFile, json_encode($purchases, JSON_PRETTY_PRINT));
    }

    /**
     * Create a new content item
     */
    public function createItem($operatorId, $type, $title, $description, $price, $fileUrl, $thumbnailUrl = null, $tags = []) {
        $items = $this->loadItems();

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

        $items[] = $newItem;
        $this->saveItems($items);

        return $newItem;
    }

    /**
     * Get all items by operator
     */
    public function getOperatorItems($operatorId, $status = null) {
        $items = $this->loadItems();

        $filtered = array_filter($items, function($item) use ($operatorId, $status) {
            if ($item['operator_id'] !== $operatorId) {
                return false;
            }
            if ($status && $item['status'] !== $status) {
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
     * Get all active items (for marketplace browsing)
     */
    public function getAllActiveItems($operatorId = null, $type = null) {
        $items = $this->loadItems();

        $filtered = array_filter($items, function($item) use ($operatorId, $type) {
            if ($item['status'] !== 'active') {
                return false;
            }
            if ($operatorId && $item['operator_id'] !== $operatorId) {
                return false;
            }
            if ($type && $item['type'] !== $type) {
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
     * Get a single item
     */
    public function getItem($itemId) {
        $items = $this->loadItems();

        foreach ($items as $item) {
            if ($item['item_id'] === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Update item
     */
    public function updateItem($itemId, $operatorId, $updates) {
        $items = $this->loadItems();
        $found = false;

        foreach ($items as &$item) {
            if ($item['item_id'] === $itemId) {
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
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Item not found');
        }

        $this->saveItems($items);
        return $item;
    }

    /**
     * Increment view count
     */
    public function incrementViews($itemId) {
        $items = $this->loadItems();

        foreach ($items as &$item) {
            if ($item['item_id'] === $itemId) {
                $item['views']++;
                $this->saveItems($items);
                return $item;
            }
        }
    }

    /**
     * Purchase/access an item
     */
    public function purchaseItem($itemId, $customerId, $paymentAmount = 0.00) {
        $items = $this->loadItems();
        $purchases = $this->loadPurchases();

        // Check if already purchased
        foreach ($purchases as $purchase) {
            if ($purchase['item_id'] === $itemId && $purchase['customer_id'] === $customerId) {
                return $purchase; // Already owns it
            }
        }

        // Get item
        $item = null;
        foreach ($items as &$i) {
            if ($i['item_id'] === $itemId) {
                $item = &$i;
                break;
            }
        }

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

        $purchases[] = $purchase;
        $this->savePurchases($purchases);

        // Update item stats
        $item['purchases']++;
        $item['earnings'] += $item['price'];
        $this->saveItems($items);

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
        $purchases = $this->loadPurchases();

        foreach ($purchases as $purchase) {
            if ($purchase['item_id'] === $itemId && $purchase['customer_id'] === $customerId) {
                return true;
            }
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
        $purchases = $this->loadPurchases();

        $filtered = array_filter($purchases, function($purchase) use ($customerId) {
            return $purchase['customer_id'] === $customerId;
        });

        usort($filtered, function($a, $b) {
            return strtotime($b['purchased_at']) - strtotime($a['purchased_at']);
        });

        return array_values($filtered);
    }

    /**
     * Get operator's sales
     */
    public function getOperatorSales($operatorId) {
        $purchases = $this->loadPurchases();

        $filtered = array_filter($purchases, function($purchase) use ($operatorId) {
            return $purchase['operator_id'] === $operatorId;
        });

        usort($filtered, function($a, $b) {
            return strtotime($b['purchased_at']) - strtotime($a['purchased_at']);
        });

        return array_values($filtered);
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
        $items = $this->loadItems();
        $found = false;

        foreach ($items as &$item) {
            if ($item['item_id'] === $itemId) {
                // Verify ownership
                if ($item['operator_id'] !== $operatorId) {
                    throw new \Exception('Unauthorized');
                }

                $item['status'] = 'removed';
                $item['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Item not found');
        }

        $this->saveItems($items);
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
