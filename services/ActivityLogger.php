<?php

namespace AEIMS\Services;

use Exception;

/**
 * Activity Logger
 * Tracks all customer spending, operator earnings, and site activity
 */
class ActivityLogger
{
    private array $activities = [];
    private array $operatorViews = [];
    private array $profileViews = [];
    private string $activitiesFile;
    private string $operatorViewsFile;
    private string $profileViewsFile;

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
        $this->activitiesFile = __DIR__ . '/../data/activity_log.json';
        $this->operatorViewsFile = __DIR__ . '/../data/operator_views.json';
        $this->profileViewsFile = __DIR__ . '/../data/profile_views.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        // Load activities
        if (file_exists($this->activitiesFile)) {
            $data = json_decode(file_get_contents($this->activitiesFile), true);
            $this->activities = $data['activities'] ?? [];
        }

        // Load operator views
        if (file_exists($this->operatorViewsFile)) {
            $data = json_decode(file_get_contents($this->operatorViewsFile), true);
            $this->operatorViews = $data['views'] ?? [];
        }

        // Load profile views
        if (file_exists($this->profileViewsFile)) {
            $data = json_decode(file_get_contents($this->profileViewsFile), true);
            $this->profileViews = $data['views'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->activitiesFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Save activities
        $activityData = [
            'activities' => $this->activities,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->activitiesFile, json_encode($activityData, JSON_PRETTY_PRINT));

        // Save operator views
        $operatorViewData = [
            'views' => $this->operatorViews,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->operatorViewsFile, json_encode($operatorViewData, JSON_PRETTY_PRINT));

        // Save profile views
        $profileViewData = [
            'views' => $this->profileViews,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->profileViewsFile, json_encode($profileViewData, JSON_PRETTY_PRINT));
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

        $this->activities[$activityId] = $activity;
        $this->saveData();

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

        $this->operatorViews[$viewId] = $view;
        $this->saveData();

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

        $this->profileViews[$viewId] = $view;
        $this->saveData();

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
        $filtered = [];
        $total = 0.0;
        $breakdown = [];

        foreach ($this->activities as $activity) {
            if ($activity['customer_id'] !== $customerId) {
                continue;
            }

            // Date filter
            if ($startDate && $activity['date'] < $startDate) continue;
            if ($endDate && $activity['date'] > $endDate) continue;

            // Operator filter
            if ($operatorId && $activity['operator_id'] !== $operatorId) continue;

            // Activity type filter
            if ($activityType && $activity['type'] !== $activityType) continue;

            $filtered[] = $activity;
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
            'activities' => $filtered,
            'total_spent' => $total,
            'breakdown' => $breakdown,
            'count' => count($filtered)
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
        $filtered = [];
        $totalEarnings = 0.0;
        $breakdown = [];

        foreach ($this->activities as $activity) {
            if ($activity['operator_id'] !== $operatorId) {
                continue;
            }

            // Date filter
            if ($startDate && $activity['date'] < $startDate) continue;
            if ($endDate && $activity['date'] > $endDate) continue;

            // Customer filter
            if ($customerId && $activity['customer_id'] !== $customerId) continue;

            // Activity type filter
            if ($activityType && $activity['type'] !== $activityType) continue;

            $filtered[] = $activity;
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
            'activities' => $filtered,
            'total_earnings' => $totalEarnings,
            'breakdown' => $breakdown,
            'count' => count($filtered)
        ];
    }

    /**
     * Get most viewed operators for a customer
     */
    public function getMostViewedOperators(string $customerId, int $limit = 10): array
    {
        $operatorCounts = [];

        foreach ($this->operatorViews as $view) {
            if ($view['customer_id'] !== $customerId) {
                continue;
            }

            $opId = $view['operator_id'];
            if (!isset($operatorCounts[$opId])) {
                $operatorCounts[$opId] = [
                    'operator_id' => $opId,
                    'view_count' => 0,
                    'last_viewed' => $view['timestamp']
                ];
            }
            $operatorCounts[$opId]['view_count']++;

            // Update last viewed if more recent
            if ($view['timestamp'] > $operatorCounts[$opId]['last_viewed']) {
                $operatorCounts[$opId]['last_viewed'] = $view['timestamp'];
            }
        }

        // Sort by view count descending
        usort($operatorCounts, function($a, $b) {
            return $b['view_count'] - $a['view_count'];
        });

        return array_slice($operatorCounts, 0, $limit);
    }

    /**
     * Get profile viewers for a customer
     */
    public function getProfileViewers(string $customerId, ?string $startDate = null, ?string $endDate = null): array
    {
        $viewers = [];

        foreach ($this->profileViews as $view) {
            if ($view['customer_id'] !== $customerId) {
                continue;
            }

            // Date filter
            if ($startDate && $view['date'] < $startDate) continue;
            if ($endDate && $view['date'] > $endDate) continue;

            $viewers[] = $view;
        }

        // Sort by most recent first
        usort($viewers, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $viewers;
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
