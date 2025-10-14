<?php
/**
 * Operator Request Manager
 * Handles operator-initiated chat/call requests to customers
 */

namespace AEIMS\Services;

class OperatorRequestManager {
    private $requestsFile;
    private $notificationManager;

    public function __construct() {
        $this->requestsFile = __DIR__ . '/../data/operator_requests.json';
        $this->notificationManager = new NotificationManager();
        $this->ensureDataFile();
    }

    private function ensureDataFile() {
        if (!file_exists($this->requestsFile)) {
            file_put_contents($this->requestsFile, json_encode([]));
        }
    }

    private function loadRequests() {
        $data = file_get_contents($this->requestsFile);
        $requests = json_decode($data, true);
        return $requests ?: [];
    }

    private function saveRequests($requests) {
        file_put_contents($this->requestsFile, json_encode($requests, JSON_PRETTY_PRINT));
    }

    /**
     * Create a new operator request (chat or call)
     */
    public function createRequest($operatorId, $customerId, $type, $message = '', $duration = null, $price = null) {
        $requests = $this->loadRequests();

        // Check for duplicate pending requests
        foreach ($requests as $request) {
            if ($request['operator_id'] === $operatorId &&
                $request['customer_id'] === $customerId &&
                $request['type'] === $type &&
                $request['status'] === 'pending') {
                throw new \Exception('You already have a pending ' . $type . ' request with this customer');
            }
        }

        $requestId = uniqid('req_', true);
        $newRequest = [
            'request_id' => $requestId,
            'operator_id' => $operatorId,
            'customer_id' => $customerId,
            'type' => $type, // 'chat' or 'call'
            'message' => $message,
            'duration' => $duration, // For calls: duration in minutes
            'price' => $price, // Price if specified
            'status' => 'pending', // pending, accepted, declined, expired, cancelled
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'responded_at' => null
        ];

        $requests[] = $newRequest;
        $this->saveRequests($requests);

        // Send notification to customer
        $typeLabel = $type === 'call' ? '📞 Call Request' : '💬 Chat Request';
        $this->notificationManager->createNotification(
            $customerId,
            'operator_request',
            $typeLabel,
            'An operator wants to ' . $type . ' with you!',
            '/operator-requests.php?request_id=' . $requestId
        );

        return $newRequest;
    }

    /**
     * Get all requests for a customer
     */
    public function getCustomerRequests($customerId, $status = null) {
        $requests = $this->loadRequests();

        $filtered = array_filter($requests, function($req) use ($customerId, $status) {
            if ($req['customer_id'] !== $customerId) {
                return false;
            }
            if ($status && $req['status'] !== $status) {
                return false;
            }
            return true;
        });

        // Sort by created_at descending
        usort($filtered, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_values($filtered);
    }

    /**
     * Get all requests sent by an operator
     */
    public function getOperatorRequests($operatorId, $status = null) {
        $requests = $this->loadRequests();

        $filtered = array_filter($requests, function($req) use ($operatorId, $status) {
            if ($req['operator_id'] !== $operatorId) {
                return false;
            }
            if ($status && $req['status'] !== $status) {
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
     * Get a single request by ID
     */
    public function getRequest($requestId) {
        $requests = $this->loadRequests();

        foreach ($requests as $request) {
            if ($request['request_id'] === $requestId) {
                return $request;
            }
        }

        return null;
    }

    /**
     * Accept a request
     */
    public function acceptRequest($requestId, $customerId) {
        $requests = $this->loadRequests();
        $found = false;

        foreach ($requests as &$request) {
            if ($request['request_id'] === $requestId) {
                // Verify the customer is the recipient
                if ($request['customer_id'] !== $customerId) {
                    throw new \Exception('Unauthorized');
                }

                if ($request['status'] !== 'pending') {
                    throw new \Exception('Request is no longer pending');
                }

                $request['status'] = 'accepted';
                $request['responded_at'] = date('Y-m-d H:i:s');
                $found = true;

                // Notify operator
                $typeLabel = $request['type'] === 'call' ? '📞 Call' : '💬 Chat';
                $this->notificationManager->createNotification(
                    $request['operator_id'],
                    'request_accepted',
                    $typeLabel . ' Request Accepted!',
                    'Your ' . $request['type'] . ' request was accepted!',
                    '/agents/operator-messages.php'
                );

                break;
            }
        }

        if (!$found) {
            throw new \Exception('Request not found');
        }

        $this->saveRequests($requests);
        return $request;
    }

    /**
     * Decline a request
     */
    public function declineRequest($requestId, $customerId) {
        $requests = $this->loadRequests();
        $found = false;

        foreach ($requests as &$request) {
            if ($request['request_id'] === $requestId) {
                // Verify the customer is the recipient
                if ($request['customer_id'] !== $customerId) {
                    throw new \Exception('Unauthorized');
                }

                if ($request['status'] !== 'pending') {
                    throw new \Exception('Request is no longer pending');
                }

                $request['status'] = 'declined';
                $request['responded_at'] = date('Y-m-d H:i:s');
                $found = true;

                // Notify operator
                $this->notificationManager->createNotification(
                    $request['operator_id'],
                    'request_declined',
                    'Request Declined',
                    'Your ' . $request['type'] . ' request was declined.',
                    '/agents/operator-requests.php'
                );

                break;
            }
        }

        if (!$found) {
            throw new \Exception('Request not found');
        }

        $this->saveRequests($requests);
        return $request;
    }

    /**
     * Cancel a request (by operator)
     */
    public function cancelRequest($requestId, $operatorId) {
        $requests = $this->loadRequests();
        $found = false;

        foreach ($requests as &$request) {
            if ($request['request_id'] === $requestId) {
                // Verify the operator is the sender
                if ($request['operator_id'] !== $operatorId) {
                    throw new \Exception('Unauthorized');
                }

                if ($request['status'] !== 'pending') {
                    throw new \Exception('Request is no longer pending');
                }

                $request['status'] = 'cancelled';
                $request['responded_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception('Request not found');
        }

        $this->saveRequests($requests);
        return $request;
    }

    /**
     * Expire old pending requests
     */
    public function expireOldRequests() {
        $requests = $this->loadRequests();
        $now = time();
        $expired = 0;

        foreach ($requests as &$request) {
            if ($request['status'] === 'pending' &&
                strtotime($request['expires_at']) < $now) {
                $request['status'] = 'expired';
                $expired++;
            }
        }

        if ($expired > 0) {
            $this->saveRequests($requests);
        }

        return $expired;
    }

    /**
     * Get pending request count for customer
     */
    public function getPendingCount($customerId) {
        $requests = $this->getCustomerRequests($customerId, 'pending');
        return count($requests);
    }
}
