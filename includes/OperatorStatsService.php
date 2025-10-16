<?php
/**
 * Operator Stats Service - Real database-backed statistics
 */

class OperatorStatsService {
    private $db;

    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }

    /**
     * Get comprehensive operator statistics
     */
    public function getOperatorStats($operatorId, $domain = null) {
        if (!$this->db->isEnabled() || !$this->db->isAvailable()) {
            return $this->getMockStats();
        }

        try {
            $stats = [
                'calls_today' => $this->getCallsToday($operatorId, $domain),
                'texts_today' => $this->getTextsToday($operatorId, $domain),
                'chat_sessions' => $this->getChatSessionsToday($operatorId, $domain),
                'earnings_today' => $this->getEarningsToday($operatorId, $domain),
                'earnings_week' => $this->getEarningsWeek($operatorId, $domain),
                'earnings_month' => $this->getEarningsMonth($operatorId, $domain),
                'rating' => $this->getAverageRating($operatorId, $domain),
                'total_customers' => $this->getTotalCustomers($operatorId, $domain),
                'repeat_customers' => $this->getRepeatCustomers($operatorId, $domain)
            ];

            return $stats;
        } catch (Exception $e) {
            error_log("OperatorStatsService: Error getting stats: " . $e->getMessage());
            return $this->getMockStats();
        }
    }

    /**
     * Get number of calls today
     */
    private function getCallsToday($operatorId, $domain) {
        try {
            // Check if calls table exists
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'calls'
                )
            ");

            if (!$tableExists['exists']) {
                return 0;
            }

            $query = "
                SELECT COUNT(*) as count
                FROM calls
                WHERE operator_id = :operator_id
                AND created_at >= CURRENT_DATE
            ";
            $params = ['operator_id' => $operatorId];

            if ($domain) {
                $query .= " AND domain = :domain";
                $params['domain'] = $domain;
            }

            $result = $this->db->fetchOne($query, $params);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get number of text messages today
     */
    private function getTextsToday($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'messages'
                )
            ");

            if (!$tableExists['exists']) {
                return 0;
            }

            $query = "
                SELECT COUNT(*) as count
                FROM messages
                WHERE sender_id = :operator_id
                AND sender_type = 'operator'
                AND created_at >= CURRENT_DATE
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get number of chat sessions today
     */
    private function getChatSessionsToday($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'chat_sessions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0;
            }

            $query = "
                SELECT COUNT(*) as count
                FROM chat_sessions
                WHERE operator_id = :operator_id
                AND created_at >= CURRENT_DATE
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get earnings today
     */
    private function getEarningsToday($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'transactions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0.00;
            }

            $query = "
                SELECT COALESCE(SUM(operator_amount), 0) as total
                FROM transactions
                WHERE operator_id = :operator_id
                AND status = 'completed'
                AND created_at >= CURRENT_DATE
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return round((float)($result['total'] ?? 0), 2);
        } catch (Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get earnings this week
     */
    private function getEarningsWeek($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'transactions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0.00;
            }

            $query = "
                SELECT COALESCE(SUM(operator_amount), 0) as total
                FROM transactions
                WHERE operator_id = :operator_id
                AND status = 'completed'
                AND created_at >= DATE_TRUNC('week', CURRENT_DATE)
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return round((float)($result['total'] ?? 0), 2);
        } catch (Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get earnings this month
     */
    private function getEarningsMonth($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'transactions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0.00;
            }

            $query = "
                SELECT COALESCE(SUM(operator_amount), 0) as total
                FROM transactions
                WHERE operator_id = :operator_id
                AND status = 'completed'
                AND created_at >= DATE_TRUNC('month', CURRENT_DATE)
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return round((float)($result['total'] ?? 0), 2);
        } catch (Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get average rating
     */
    private function getAverageRating($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'operator_ratings'
                )
            ");

            if (!$tableExists['exists']) {
                return 0.0;
            }

            $query = "
                SELECT COALESCE(AVG(rating), 0) as avg_rating
                FROM operator_ratings
                WHERE operator_id = :operator_id
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return round((float)($result['avg_rating'] ?? 0), 1);
        } catch (Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get total customers
     */
    private function getTotalCustomers($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'operator_customer_interactions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0;
            }

            $query = "
                SELECT COUNT(DISTINCT customer_id) as count
                FROM operator_customer_interactions
                WHERE operator_id = :operator_id
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get repeat customers (customers with 2+ interactions)
     */
    private function getRepeatCustomers($operatorId, $domain) {
        try {
            $tableExists = $this->db->fetchOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_name = 'operator_customer_interactions'
                )
            ");

            if (!$tableExists['exists']) {
                return 0;
            }

            $query = "
                SELECT COUNT(*) as count
                FROM (
                    SELECT customer_id
                    FROM operator_customer_interactions
                    WHERE operator_id = :operator_id
                    GROUP BY customer_id
                    HAVING COUNT(*) >= 2
                ) repeat_cust
            ";
            $params = ['operator_id' => $operatorId];

            $result = $this->db->fetchOne($query, $params);
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Fallback mock stats when database unavailable or tables don't exist
     */
    private function getMockStats() {
        return [
            'calls_today' => 0,
            'texts_today' => 0,
            'chat_sessions' => 0,
            'earnings_today' => 0.00,
            'earnings_week' => 0.00,
            'earnings_month' => 0.00,
            'rating' => 0.0,
            'total_customers' => 0,
            'repeat_customers' => 0
        ];
    }
}
