<?php

namespace AEIMS\Services;

use Exception;

/**
 * Activity Logger
 * Tracks all customer spending, operator earnings, and site activity
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class ActivityLogger
{
    private $dataLayer;

    // Activity types
    const TYPE_MESSAGE = 'message';
    const TYPE_CALL = 'call';
    const TYPE_VIDEO = 'video';
    const TYPE_CAM = 'cam';
    const TYPE_CHAT = 'chat';
    const TYPE_TOY_CONTROL = 'toy_control';
    const TYPE_CONTENT = 'content';
    const TYPE_TIP = 'tip';
    const TYPE_CREDIT_PURCHASE = 'credit_purchase';
    const TYPE_OPERATOR_VIEW = 'operator_view';
    const TYPE_PROFILE_VIEW = 'profile_view';

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
    }

    /**
     * Log spending activity
     */
    public function logSpending(
        string $customerId,
        string $operatorId,
        string $activityType,
        float $amount,
        array $metadata = []
    ): string {
        $activityId = 'act_' . uniqid();

        $activity = [
            'activity_id' => $activityId,
            'customer_id' => $customerId,
            'operator_id' => $operatorId,
            'type' => $activityType,
            'amount' => $amount,
            'operator_earnings' => $amount * 0.65, // 65% commission
            'platform_fee' => $amount * 0.35,
            'timestamp' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'metadata' => $metadata
        ];

        $this->dataLayer->saveActivity($activity);
        return $activityId;
    }

    /**
     * Log operator view (customer viewing operator profile)
     */
    public function logOperatorView(string $customerId, string $operatorId, string $siteDomain): string
    {
        $viewId = 'view_' . uniqid();

        $view = [
            'view_id' => $viewId,
            'customer_id' => $customerId,
            'operator_id' => $operatorId,
            'site_domain' => $siteDomain,
            'timestamp' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s')
        ];

        $this->dataLayer->saveOperatorView($view);
        return $viewId;
    }

    /**
     * Log profile view (operator viewing customer profile)
     */
    public function logProfileView(string $operatorId, string $customerId, string $siteDomain): string
    {
        $viewId = 'pview_' . uniqid();

        $view = [
            'view_id' => $viewId,
            'operator_id' => $operatorId,
            'customer_id' => $customerId,
            'site_domain' => $siteDomain,
            'timestamp' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s')
        ];

        $this->dataLayer->saveProfileView($view);
        return $viewId;
    }

    /**
     * Get customer spending breakdown
     */
    public function getCustomerSpending(
        string $customerId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $operatorId = null,
        ?string $activityType = null
    ): array {
        $activities = $this->dataLayer->getCustomerActivities($customerId, $startDate, $endDate, $operatorId, $activityType);

        $total = 0.0;
        $breakdown = [];

        foreach ($activities as $activity) {
            $total += $activity['amount'];

            // Build breakdown by type
            if (!isset($breakdown[$activity['type']])) {
                $breakdown[$activity['type']] = [
                    'count' => 0,
                    'total' => 0.0
                ];
            }
            $breakdown[$activity['type']]['count']++;
            $breakdown[$activity['type']]['total'] += $activity['amount'];
        }

        return [
            'activities' => $activities,
            'total_spent' => $total,
            'breakdown' => $breakdown,
            'count' => count($activities)
        ];
    }

    /**
     * Get operator earnings
     */
    public function getOperatorEarnings(
        string $operatorId,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $customerId = null,
        ?string $activityType = null
    ): array {
        $activities = $this->dataLayer->getOperatorActivities($operatorId, $startDate, $endDate, $customerId, $activityType);

        $totalEarnings = 0.0;
        $breakdown = [];

        foreach ($activities as $activity) {
            $totalEarnings += $activity['operator_earnings'];

            // Build breakdown by type
            if (!isset($breakdown[$activity['type']])) {
                $breakdown[$activity['type']] = [
                    'count' => 0,
                    'total_earnings' => 0.0,
                    'total_revenue' => 0.0
                ];
            }
            $breakdown[$activity['type']]['count']++;
            $breakdown[$activity['type']]['total_earnings'] += $activity['operator_earnings'];
            $breakdown[$activity['type']]['total_revenue'] += $activity['amount'];
        }

        return [
            'activities' => $activities,
            'total_earnings' => $totalEarnings,
            'breakdown' => $breakdown,
            'count' => count($activities)
        ];
    }

    /**
     * Get most viewed operators for a customer
     */
    public function getMostViewedOperators(string $customerId, int $limit = 10): array
    {
        return $this->dataLayer->getMostViewedOperators($customerId, $limit);
    }

    /**
     * Get profile viewers for a customer
     */
    public function getProfileViewers(string $customerId, ?string $startDate = null, ?string $endDate = null): array
    {
        return $this->dataLayer->getProfileViewers($customerId, $startDate, $endDate);
    }

    /**
     * Get date range presets
     */
    public static function getDateRangePreset(string $preset): array
    {
        $now = new \DateTime();
        $today = $now->format('Y-m-d');

        switch ($preset) {
            case 'today':
                return ['start' => $today, 'end' => $today];

            case 'yesterday':
                $yesterday = (clone $now)->modify('-1 day');
                $date = $yesterday->format('Y-m-d');
                return ['start' => $date, 'end' => $date];

            case 'week':
                $weekStart = (clone $now)->modify('-6 days')->format('Y-m-d');
                return ['start' => $weekStart, 'end' => $today];

            case 'bi-weekly':
                $biWeekStart = (clone $now)->modify('-13 days')->format('Y-m-d');
                return ['start' => $biWeekStart, 'end' => $today];

            case 'monthly':
                $monthStart = (clone $now)->modify('first day of this month')->format('Y-m-d');
                return ['start' => $monthStart, 'end' => $today];

            case 'quarterly':
                $quarterMonth = ceil($now->format('n') / 3) * 3 - 2;
                $quarterStart = $now->setDate((int)$now->format('Y'), $quarterMonth, 1)->format('Y-m-d');
                return ['start' => $quarterStart, 'end' => $today];

            case 'half-year':
                $halfYearStart = (clone $now)->modify('-6 months')->format('Y-m-d');
                return ['start' => $halfYearStart, 'end' => $today];

            case 'half-year-plus-3':
                $nineMoStart = (clone $now)->modify('-9 months')->format('Y-m-d');
                return ['start' => $nineMoStart, 'end' => $today];

            case 'yearly':
                $yearStart = (clone $now)->modify('first day of january this year')->format('Y-m-d');
                return ['start' => $yearStart, 'end' => $today];

            default:
                return ['start' => $today, 'end' => $today];
        }
    }

    /**
     * Get activity summary for a date range
     */
    public function getActivitySummary(string $customerId, string $startDate, string $endDate): array
    {
        $spending = $this->getCustomerSpending($customerId, $startDate, $endDate);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'total_spent' => $spending['total_spent'],
            'total_activities' => $spending['count'],
            'breakdown' => $spending['breakdown'],
            'daily_average' => $this->calculateDailyAverage($spending['activities'])
        ];
    }

    private function calculateDailyAverage(array $activities): float
    {
        if (empty($activities)) {
            return 0.0;
        }

        $dates = array_unique(array_column($activities, 'date'));
        $dayCount = count($dates);

        if ($dayCount === 0) {
            return 0.0;
        }

        $total = array_sum(array_column($activities, 'amount'));
        return $total / $dayCount;
    }
}
