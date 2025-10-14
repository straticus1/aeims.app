<?php

namespace AEIMS\Services;

use Exception;

/**
 * Toy Management Service
 * Handles toy registration, billing, and interaction logging
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class ToyManager
{
    private $dataLayer;

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
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

        $this->dataLayer->saveToyRegistration($registration);
        return $registration;
    }

    public function getOperatorToys(string $operatorId): array
    {
        return $this->dataLayer->getOperatorToys($operatorId);
    }

    public function getToyRegistration(string $registrationId): ?array
    {
        return $this->dataLayer->getToyRegistration($registrationId);
    }

    public function updateToy(string $registrationId, array $updates): array
    {
        $registration = $this->dataLayer->getToyRegistration($registrationId);
        
        if (!$registration) {
            throw new Exception('Toy registration not found');
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['nickname', 'per_minute_rate', 'is_active'])) {
                $registration[$key] = $value;
            }
        }

        $registration['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveToyRegistration($registration);
        return $registration;
    }

    public function removeToy(string $registrationId): void
    {
        $registration = $this->dataLayer->getToyRegistration($registrationId);
        
        if (!$registration) {
            throw new Exception('Toy registration not found');
        }

        $registration['is_active'] = false;
        $registration['removed_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveToyRegistration($registration);
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

        $this->dataLayer->saveToyInteraction($interaction);

        $registrationId = $interactionData['toy_registration_id'];
        $registration = $this->dataLayer->getToyRegistration($registrationId);
        
        if ($registration) {
            $registration['interaction_count']++;
            $registration['total_earnings'] += $interactionData['rate_charged'] * 0.65;
            $this->dataLayer->saveToyRegistration($registration);
        }
    }

    public function getOperatorEarnings(string $operatorId): array
    {
        return $this->dataLayer->getToyEarnings($operatorId);
    }

    public function getAvailableToysForCustomer(): array
    {
        return $this->dataLayer->getActiveToys();
    }
}
