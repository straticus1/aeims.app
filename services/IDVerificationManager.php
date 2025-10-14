<?php

namespace AEIMS\Services;

use Exception;

/**
 * ID Verification Manager
 * Handles operator identity verification with override codes
 */
class IDVerificationManager
{
    private array $verifications = [];
    private array $overrideCodes = [];
    private string $verificationsFile;
    private string $overrideCodesFile;

    // Verification statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_OVERRIDE = 'override'; // Bypassed with code

    public function __construct()
    {
        $this->verificationsFile = __DIR__ . '/../data/id_verifications.json';
        $this->overrideCodesFile = __DIR__ . '/../data/verification_codes.json';
        $this->loadData();
    }

    private function loadData(): void
    {
        // Load verifications
        if (file_exists($this->verificationsFile)) {
            $data = json_decode(file_get_contents($this->verificationsFile), true);
            $this->verifications = $data['verifications'] ?? [];
        }

        // Load override codes
        if (file_exists($this->overrideCodesFile)) {
            $data = json_decode(file_get_contents($this->overrideCodesFile), true);
            $this->overrideCodes = $data['codes'] ?? [];
        }
    }

    private function saveData(): void
    {
        $dataDir = dirname($this->verificationsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Save verifications
        $verificationData = [
            'verifications' => $this->verifications,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->verificationsFile, json_encode($verificationData, JSON_PRETTY_PRINT));

        // Save override codes
        $codeData = [
            'codes' => $this->overrideCodes,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        file_put_contents($this->overrideCodesFile, json_encode($codeData, JSON_PRETTY_PRINT));
    }

    /**
     * Create a new verification record
     */
    public function createVerification(
        string $operatorId,
        array $documents = [],
        string $notes = ''
    ): string {
        $verificationId = 'verify_' . uniqid();

        $verification = [
            'verification_id' => $verificationId,
            'operator_id' => $operatorId,
            'status' => self::STATUS_PENDING,
            'documents' => $documents,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null
        ];

        $this->verifications[$verificationId] = $verification;
        $this->saveData();

        return $verificationId;
    }

    /**
     * Verify operator with an override code
     */
    public function verifyWithCode(string $operatorId, string $code): bool
    {
        // Check if code exists and is valid
        if (!isset($this->overrideCodes[$code])) {
            throw new Exception('Invalid verification code');
        }

        $codeData = $this->overrideCodes[$code];

        // Check if code is already used
        if ($codeData['used']) {
            throw new Exception('Verification code has already been used');
        }

        // Check if code is expired
        if (isset($codeData['expires_at'])) {
            if (strtotime($codeData['expires_at']) < time()) {
                throw new Exception('Verification code has expired');
            }
        }

        // Mark code as used
        $this->overrideCodes[$code]['used'] = true;
        $this->overrideCodes[$code]['used_by'] = $operatorId;
        $this->overrideCodes[$code]['used_at'] = date('Y-m-d H:i:s');

        // Create verification record with override status
        $verificationId = 'verify_' . uniqid();
        $this->verifications[$verificationId] = [
            'verification_id' => $verificationId,
            'operator_id' => $operatorId,
            'status' => self::STATUS_OVERRIDE,
            'verification_code' => $code,
            'documents' => [],
            'notes' => 'Verified with override code: ' . $code,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => 'system_override',
            'reviewed_at' => date('Y-m-d H:i:s')
        ];

        $this->saveData();

        return true;
    }

    /**
     * Approve a verification
     */
    public function approveVerification(string $verificationId, string $reviewerId, string $notes = ''): bool
    {
        if (!isset($this->verifications[$verificationId])) {
            throw new Exception('Verification not found');
        }

        $this->verifications[$verificationId]['status'] = self::STATUS_APPROVED;
        $this->verifications[$verificationId]['reviewed_by'] = $reviewerId;
        $this->verifications[$verificationId]['reviewed_at'] = date('Y-m-d H:i:s');
        $this->verifications[$verificationId]['updated_at'] = date('Y-m-d H:i:s');

        if ($notes) {
            $this->verifications[$verificationId]['notes'] .= "\n[APPROVED] " . $notes;
        }

        $this->saveData();

        return true;
    }

    /**
     * Reject a verification
     */
    public function rejectVerification(string $verificationId, string $reviewerId, string $reason): bool
    {
        if (!isset($this->verifications[$verificationId])) {
            throw new Exception('Verification not found');
        }

        $this->verifications[$verificationId]['status'] = self::STATUS_REJECTED;
        $this->verifications[$verificationId]['reviewed_by'] = $reviewerId;
        $this->verifications[$verificationId]['reviewed_at'] = date('Y-m-d H:i:s');
        $this->verifications[$verificationId]['updated_at'] = date('Y-m-d H:i:s');
        $this->verifications[$verificationId]['rejection_reason'] = $reason;

        $this->saveData();

        return true;
    }

    /**
     * Check if operator is verified
     */
    public function isOperatorVerified(string $operatorId): bool
    {
        foreach ($this->verifications as $verification) {
            if ($verification['operator_id'] === $operatorId &&
                ($verification['status'] === self::STATUS_APPROVED ||
                 $verification['status'] === self::STATUS_OVERRIDE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get operator verification status
     */
    public function getOperatorVerification(string $operatorId): ?array
    {
        // Return the most recent verification
        $operatorVerifications = [];

        foreach ($this->verifications as $verification) {
            if ($verification['operator_id'] === $operatorId) {
                $operatorVerifications[] = $verification;
            }
        }

        if (empty($operatorVerifications)) {
            return null;
        }

        // Sort by created_at descending
        usort($operatorVerifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $operatorVerifications[0];
    }

    /**
     * Generate a new override code
     */
    public function generateOverrideCode(
        string $generatedBy,
        ?string $expiresAt = null,
        string $notes = ''
    ): string {
        // Generate a unique code
        $code = 'VERIFY-' . strtoupper(substr(uniqid(), -8));

        $this->overrideCodes[$code] = [
            'code' => $code,
            'generated_by' => $generatedBy,
            'generated_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'used' => false,
            'used_by' => null,
            'used_at' => null,
            'notes' => $notes
        ];

        $this->saveData();

        return $code;
    }

    /**
     * Get all override codes
     */
    public function getOverrideCodes(): array
    {
        return $this->overrideCodes;
    }

    /**
     * Get all pending verifications
     */
    public function getPendingVerifications(): array
    {
        $pending = [];

        foreach ($this->verifications as $verification) {
            if ($verification['status'] === self::STATUS_PENDING) {
                $pending[] = $verification;
            }
        }

        // Sort by created_at descending (newest first)
        usort($pending, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return $pending;
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStats(): array
    {
        $stats = [
            'total' => count($this->verifications),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'override' => 0
        ];

        foreach ($this->verifications as $verification) {
            switch ($verification['status']) {
                case self::STATUS_PENDING:
                    $stats['pending']++;
                    break;
                case self::STATUS_APPROVED:
                    $stats['approved']++;
                    break;
                case self::STATUS_REJECTED:
                    $stats['rejected']++;
                    break;
                case self::STATUS_OVERRIDE:
                    $stats['override']++;
                    break;
            }
        }

        return $stats;
    }
}
