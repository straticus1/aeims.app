<?php

namespace AEIMS\Services;

use Exception;

/**
 * Operator Management Service
 * Handles operator accounts, profiles, and verification
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class OperatorManager
{
    private $dataLayer;

    // Maximum allowed rates (per minute)
    private const MAX_CALL_RATE = 500.00;
    private const MAX_MESSAGE_RATE = 50.00;
    private const MAX_CONTENT_RATE = 100.00;

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
    }

    private function getAllOperatorsInternal(): array
    {
        // Use DataLayer searchOperators with no filters to get all
        return $this->dataLayer->searchOperators([]);
    }

    public function operatorExists(string $operatorId): bool
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        return $operator !== null;
    }

    public function getOperator(string $operatorId): ?array
    {
        return $this->dataLayer->getOperator($operatorId);
    }

    public function createOperator(array $operatorData): array
    {
        $operatorId = 'op_' . uniqid();

        $defaultRates = $this->getDefaultRates($operatorData['category'] ?? 'standard');
        $rates = isset($operatorData['rates'])
            ? $this->validateOperatorRates($operatorData['rates'])
            : $defaultRates;

        $operator = [
            'operator_id' => $operatorId,
            'id' => $operatorId,
            'username' => $operatorData['username'],
            'email' => $operatorData['email'],
            'password_hash' => isset($operatorData['password']) ? password_hash($operatorData['password'], PASSWORD_DEFAULT) : '',
            'category' => $operatorData['category'] ?? 'standard',
            'verified' => false,
            'active' => true,
            'status' => 'active',
            'commission_rate' => $this->getCommissionRate($operatorData['category'] ?? 'standard'),
            'rates' => $rates,
            'created_at' => date('Y-m-d H:i:s'),
            'profile' => $operatorData['profile'] ?? [],
            'sites' => $operatorData['sites'] ?? [],
            'domains' => $operatorData['domains'] ?? []
        ];

        $this->dataLayer->saveOperator($operator);
        return $operator;
    }

    public function updateOperator(string $operatorId, array $updates): array
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        if (!$operator) {
            throw new Exception('Operator not found');
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['username', 'email', 'category', 'verified', 'active', 'profile', 'status'])) {
                $operator[$key] = $value;
            }
        }

        if (isset($updates['category'])) {
            $operator['commission_rate'] = $this->getCommissionRate($updates['category']);
        }

        if (isset($updates['rates'])) {
            $operator['rates'] = $this->validateOperatorRates($updates['rates']);
        }

        $operator['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveOperator($operator);

        return $operator;
    }

    public function verifyOperator(string $operatorId): bool
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        if (!$operator) {
            throw new Exception('Operator not found');
        }

        $operator['verified'] = true;
        $operator['verified_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveOperator($operator);

        return true;
    }

    public function deactivateOperator(string $operatorId): bool
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        if (!$operator) {
            throw new Exception('Operator not found');
        }

        $operator['active'] = false;
        $operator['status'] = 'inactive';
        $operator['deactivated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveOperator($operator);

        return true;
    }

    public function getActiveOperators(): array
    {
        return $this->dataLayer->searchOperators(['status' => 'active']);
    }

    public function getAllOperators(): array
    {
        return $this->getAllOperatorsInternal();
    }

    public function getOperatorsByCategory(string $category): array
    {
        return $this->dataLayer->searchOperators(['category' => $category]);
    }

    private function getDefaultRates(string $category): array
    {
        $rates = [
            'standard' => [
                'call_per_minute' => 2.99,
                'message' => 0.50,
                'content' => 4.99
            ],
            'premium' => [
                'call_per_minute' => 4.99,
                'message' => 0.99,
                'content' => 9.99
            ],
            'elite' => [
                'call_per_minute' => 9.99,
                'message' => 1.99,
                'content' => 19.99
            ]
        ];

        return $rates[$category] ?? $rates['standard'];
    }

    private function getCommissionRate(string $category): float
    {
        $rates = [
            'standard' => 0.60,
            'premium' => 0.65,
            'elite' => 0.70
        ];

        return $rates[$category] ?? 0.60;
    }

    private function validateOperatorRates(array $rates): array
    {
        $validated = [];

        if (isset($rates['call_per_minute'])) {
            $validated['call_per_minute'] = min($rates['call_per_minute'], self::MAX_CALL_RATE);
        }

        if (isset($rates['message'])) {
            $validated['message'] = min($rates['message'], self::MAX_MESSAGE_RATE);
        }

        if (isset($rates['content'])) {
            $validated['content'] = min($rates['content'], self::MAX_CONTENT_RATE);
        }

        return $validated;
    }

    public function getOperatorRates(string $operatorId): array
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        if (!$operator) {
            throw new Exception('Operator not found');
        }

        return $operator['rates'] ?? $this->getDefaultRates($operator['category'] ?? 'standard');
    }

    public function updateOperatorRates(string $operatorId, array $rates): array
    {
        $operator = $this->dataLayer->getOperator($operatorId);
        if (!$operator) {
            throw new Exception('Operator not found');
        }

        $operator['rates'] = $this->validateOperatorRates($rates);
        $operator['updated_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveOperator($operator);

        return $operator['rates'];
    }
}
