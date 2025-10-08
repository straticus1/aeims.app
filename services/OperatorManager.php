<?php

namespace AEIMS\Services;

use Exception;

/**
 * Operator Management Service
 * Handles operator accounts, profiles, and verification
 */
class OperatorManager
{
    private array $operators = [];
    private string $dataFile;

    // Maximum allowed rates (per minute)
    private const MAX_CALL_RATE = 500.00; // $500/min = $30,000/hr
    private const MAX_MESSAGE_RATE = 50.00; // $50/message max
    private const MAX_CONTENT_RATE = 100.00; // $100/content max

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/operators.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        if (file_exists($this->dataFile)) {
            $data = json_decode(file_get_contents($this->dataFile), true);
            $this->operators = $data['operators'] ?? [];
        } else {
            // Initialize with demo operators
            $this->operators = [
                'op1' => [
                    'operator_id' => 'op1',
                    'username' => 'SexyKitten',
                    'email' => 'kitten@example.com',
                    'category' => 'premium',
                    'verified' => true,
                    'active' => true,
                    'commission_rate' => 0.65,
                    'created_at' => '2024-01-01 00:00:00',
                    'profile' => [
                        'bio' => 'Experienced cam model specializing in interactive shows',
                        'age' => 25,
                        'location' => 'US',
                        'languages' => ['English'],
                        'specialties' => ['interactive_toys', 'role_play', 'fetish']
                    ]
                ],
                'op2' => [
                    'operator_id' => 'op2',
                    'username' => 'TechGoddess',
                    'email' => 'goddess@example.com',
                    'category' => 'elite',
                    'verified' => true,
                    'active' => true,
                    'commission_rate' => 0.70,
                    'created_at' => '2024-01-01 00:00:00',
                    'profile' => [
                        'bio' => 'Elite performer with cutting-edge interactive technology',
                        'age' => 28,
                        'location' => 'US',
                        'languages' => ['English', 'Spanish'],
                        'specialties' => ['tech_shows', 'interactive_toys', 'vr_experience']
                    ]
                ]
            ];
            $this->saveData();
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->dataFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'operators' => $this->operators
        ];

        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function operatorExists(string $operatorId): bool
    {
        return isset($this->operators[$operatorId]);
    }

    public function getOperator(string $operatorId): ?array
    {
        return $this->operators[$operatorId] ?? null;
    }

    public function createOperator(array $operatorData): array
    {
        $operatorId = 'op_' . uniqid();

        // Initialize default rates based on category
        $defaultRates = $this->getDefaultRates($operatorData['category'] ?? 'standard');

        // Validate and set custom rates if provided
        $rates = isset($operatorData['rates'])
            ? $this->validateOperatorRates($operatorData['rates'])
            : $defaultRates;

        $operator = [
            'operator_id' => $operatorId,
            'username' => $operatorData['username'],
            'email' => $operatorData['email'],
            'category' => $operatorData['category'] ?? 'standard',
            'verified' => false,
            'active' => true,
            'commission_rate' => $this->getCommissionRate($operatorData['category'] ?? 'standard'),
            'rates' => $rates,
            'created_at' => date('Y-m-d H:i:s'),
            'profile' => $operatorData['profile'] ?? []
        ];

        $this->operators[$operatorId] = $operator;
        $this->saveData();

        return $operator;
    }

    public function updateOperator(string $operatorId, array $updates): array
    {
        if (!isset($this->operators[$operatorId])) {
            throw new Exception('Operator not found');
        }

        foreach ($updates as $key => $value) {
            if (in_array($key, ['username', 'email', 'category', 'verified', 'active', 'profile'])) {
                $this->operators[$operatorId][$key] = $value;
            }
        }

        // Update commission rate based on category
        if (isset($updates['category'])) {
            $this->operators[$operatorId]['commission_rate'] = $this->getCommissionRate($updates['category']);
        }

        // Validate and update rates
        if (isset($updates['rates'])) {
            $this->operators[$operatorId]['rates'] = $this->validateOperatorRates($updates['rates']);
        }

        $this->operators[$operatorId]['updated_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return $this->operators[$operatorId];
    }

    public function getAllOperators(): array
    {
        return array_values($this->operators);
    }

    public function getActiveOperators(): array
    {
        return array_filter($this->operators, fn($op) => $op['active'] && $op['verified']);
    }

    private function getCommissionRate(string $category): float
    {
        $rates = [
            'standard' => 0.60,
            'premium' => 0.65,
            'vip' => 0.70,
            'elite' => 0.75
        ];

        return $rates[$category] ?? 0.60;
    }

    public function verifyOperator(string $operatorId): bool
    {
        if (!isset($this->operators[$operatorId])) {
            throw new Exception('Operator not found');
        }

        $this->operators[$operatorId]['verified'] = true;
        $this->operators[$operatorId]['verified_at'] = date('Y-m-d H:i:s');
        $this->saveData();

        return true;
    }

    public function getOperatorsByCategory(string $category): array
    {
        return array_filter($this->operators, fn($op) => $op['category'] === $category);
    }

    /**
     * Validate operator rates against maximum limits
     */
    public function validateOperatorRates(array $rates): array
    {
        $validatedRates = [];

        // Validate call rate (per minute)
        if (isset($rates['call_rate'])) {
            $callRate = floatval($rates['call_rate']);
            if ($callRate > self::MAX_CALL_RATE) {
                throw new Exception("Call rate cannot exceed $" . number_format(self::MAX_CALL_RATE, 2) . "/minute");
            }
            if ($callRate < 0) {
                throw new Exception("Call rate cannot be negative");
            }
            $validatedRates['call_rate'] = $callRate;
        }

        // Validate message rate
        if (isset($rates['message_rate'])) {
            $messageRate = floatval($rates['message_rate']);
            if ($messageRate > self::MAX_MESSAGE_RATE) {
                throw new Exception("Message rate cannot exceed $" . number_format(self::MAX_MESSAGE_RATE, 2) . "/message");
            }
            if ($messageRate < 0) {
                throw new Exception("Message rate cannot be negative");
            }
            $validatedRates['message_rate'] = $messageRate;
        }

        // Validate content rate
        if (isset($rates['content_rate'])) {
            $contentRate = floatval($rates['content_rate']);
            if ($contentRate > self::MAX_CONTENT_RATE) {
                throw new Exception("Content rate cannot exceed $" . number_format(self::MAX_CONTENT_RATE, 2) . "/content");
            }
            if ($contentRate < 0) {
                throw new Exception("Content rate cannot be negative");
            }
            $validatedRates['content_rate'] = $contentRate;
        }

        // Validate toy rates if provided
        if (isset($rates['toy_rates']) && is_array($rates['toy_rates'])) {
            $validatedToyRates = [];
            foreach ($rates['toy_rates'] as $toyId => $toyRate) {
                $rate = floatval($toyRate);
                if ($rate > self::MAX_CALL_RATE) {
                    throw new Exception("Toy rate for {$toyId} cannot exceed $" . number_format(self::MAX_CALL_RATE, 2) . "/minute");
                }
                if ($rate < 0) {
                    throw new Exception("Toy rate for {$toyId} cannot be negative");
                }
                $validatedToyRates[$toyId] = $rate;
            }
            $validatedRates['toy_rates'] = $validatedToyRates;
        }

        return $validatedRates;
    }

    /**
     * Get default rates based on operator category
     */
    public function getDefaultRates(string $category): array
    {
        $baseRates = [
            'standard' => [
                'call_rate' => 3.99,  // $3.99/min
                'message_rate' => 0.99, // $0.99/message
                'content_rate' => 4.99, // $4.99/content
                'toy_rates' => []
            ],
            'premium' => [
                'call_rate' => 6.99,  // $6.99/min
                'message_rate' => 1.49, // $1.49/message
                'content_rate' => 7.99, // $7.99/content
                'toy_rates' => []
            ],
            'vip' => [
                'call_rate' => 9.99,  // $9.99/min
                'message_rate' => 2.49, // $2.49/message
                'content_rate' => 12.99, // $12.99/content
                'toy_rates' => []
            ],
            'elite' => [
                'call_rate' => 19.99, // $19.99/min
                'message_rate' => 4.99, // $4.99/message
                'content_rate' => 24.99, // $24.99/content
                'toy_rates' => []
            ]
        ];

        return $baseRates[$category] ?? $baseRates['standard'];
    }

    /**
     * Check if operator rate is within limits for real-time validation
     */
    public function isRateValid(string $rateType, float $rate): bool
    {
        switch ($rateType) {
            case 'call_rate':
                return $rate <= self::MAX_CALL_RATE && $rate >= 0;
            case 'message_rate':
                return $rate <= self::MAX_MESSAGE_RATE && $rate >= 0;
            case 'content_rate':
                return $rate <= self::MAX_CONTENT_RATE && $rate >= 0;
            default:
                return false;
        }
    }

    /**
     * Get maximum allowed rates
     */
    public function getMaxRates(): array
    {
        return [
            'call_rate' => self::MAX_CALL_RATE,
            'message_rate' => self::MAX_MESSAGE_RATE,
            'content_rate' => self::MAX_CONTENT_RATE
        ];
    }

    /**
     * Update existing operators to include rates if missing
     */
    public function migrateOperatorRates(): void
    {
        $updated = false;

        foreach ($this->operators as $operatorId => &$operator) {
            if (!isset($operator['rates'])) {
                $operator['rates'] = $this->getDefaultRates($operator['category'] ?? 'standard');
                $updated = true;
            }
        }

        if ($updated) {
            $this->saveData();
        }
    }
}