<?php

namespace AEIMS\Services;

use Exception;

/**
 * Toy Management Service
 * Handles toy registration, billing, and interaction logging
 */
class ToyManager
{
    private array $toyRegistrations = [];
    private array $interactions = [];
    private string $dataFile;

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/toy_registrations.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $this->toyRegistrations = $data['registrations'] ?? [];
            $this->interactions = $data['interactions'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'registrations' => $this->toyRegistrations,
            'interactions' => $this->interactions
        ];

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function registerToy(array $toyData): array
    {
        $registrationId = 'reg_' . uniqid();

        $registration = [
            'registration_id' => $registrationId,
            'operator_id' => $toyData['operator_id'],
            'toy_id' => $toyData['toy_id'],
            'device_id' => $toyData['device_id'],
            'nickname' => $toyData['nickname'] ?? null,
            'per_minute_rate' => $toyData['per_minute_rate'],
            'is_active' => $toyData['is_active'] ?? true,
            'registered_at' => $toyData['registered_at'],
            'total_earnings' => 0,
            'interaction_count' => 0
        ];

        $this->toyRegistrations[$registrationId] = $registration;
        $this->saveData();

        return $registration;
    }

    public function getOperatorToys(string $operatorId): array
    {
        return array_filter($this->toyRegistrations, function($toy) use ($operatorId) {
            return $toy['operator_id'] === $operatorId;
        });
    }

    public function getToyRegistration(string $registrationId): ?array
    {
        return $this->toyRegistrations[$registrationId] ?? null;
    }

    public function updateToy(string $registrationId, array $updates): array
    {
        if (!isset($this->toyRegistrations[$registrationId])) {
            throw new Exception('Toy registration not found');
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['nickname', 'per_minute_rate', 'is_active'])) {
                $this->toyRegistrations[$registrationId][$key] = $value;
            }
        }

        $this->toyRegistrations[$registrationId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return $this->toyRegistrations[$registrationId];
    }

    public function removeToy(string $registrationId): void
    {
        if (!isset($this->toyRegistrations[$registrationId])) {
            throw new Exception('Toy registration not found');
        }

        unset($this->toyRegistrations[$registrationId]);
        $this->saveData();
    }

    public function logInteraction(array $interactionData): void
    {
        $interactionId = 'int_' . uniqid();

        $interaction = [
            'interaction_id' => $interactionId,
            'toy_registration_id' => $interactionData['toy_registration_id'],
            'customer_id' => $interactionData['customer_id'],
            'operator_id' => $interactionData['operator_id'],
            'command' => $interactionData['command'],
            'params' => $interactionData['params'],
            'rate_charged' => $interactionData['rate_charged'],
            'timestamp' => $interactionData['timestamp']
        ];

        $this->interactions[] = $interaction;

        // Update toy statistics
        $registrationId = $interactionData['toy_registration_id'];
        if (isset($this->toyRegistrations[$registrationId])) {
            $this->toyRegistrations[$registrationId]['interaction_count']++;
            $this->toyRegistrations[$registrationId]['total_earnings'] += $interactionData['rate_charged'] * 0.65; // 65% to operator
        }

        $this->saveData();
    }

    public function getOperatorEarnings(string $operatorId): array
    {
        $operatorToys = $this->getOperatorToys($operatorId);
        $totalEarnings = 0;
        $totalInteractions = 0;

        foreach ($operatorToys as $toy) {
            $totalEarnings += $toy['total_earnings'];
            $totalInteractions += $toy['interaction_count'];
        }

        // Get recent interactions for this operator
        $recentInteractions = array_filter($this->interactions, function($interaction) use ($operatorId) {
            return $interaction['operator_id'] === $operatorId &&
                   strtotime($interaction['timestamp']) > strtotime('-30 days');
        });

        return [
            'operator_id' => $operatorId,
            'total_toys' => count($operatorToys),
            'active_toys' => count(array_filter($operatorToys, fn($toy) => $toy['is_active'])),
            'total_earnings' => $totalEarnings,
            'total_interactions' => $totalInteractions,
            'recent_interactions' => count($recentInteractions),
            'average_rate' => $totalInteractions > 0 ? $totalEarnings / $totalInteractions : 0,
            'toys_breakdown' => $operatorToys
        ];
    }

    public function getAvailableToysForCustomer(): array
    {
        $activeToys = array_filter($this->toyRegistrations, fn($toy) => $toy['is_active']);

        $result = [];
        foreach ($activeToys as $toy) {
            $result[] = [
                'registration_id' => $toy['registration_id'],
                'toy_id' => $toy['toy_id'],
                'nickname' => $toy['nickname'],
                'operator_id' => $toy['operator_id'],
                'per_minute_rate' => $toy['per_minute_rate'],
                'interaction_count' => $toy['interaction_count']
            ];
        }

        return $result;
    }
}