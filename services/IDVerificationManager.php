<?php

namespace AEIMS\Services;

use Exception;

/**
 * ID Verification Manager  
 * Handles operator identity verification with override codes
 * UPDATED: Now uses DataLayer for PostgreSQL/JSON abstraction
 */
class IDVerificationManager
{
    private $dataLayer;

    // Verification statuses
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_OVERRIDE = 'override';

    public function __construct()
    {
        require_once __DIR__ . '/../includes/DataLayer.php';
        $this->dataLayer = getDataLayer();
    }

    public function createVerification(string $operatorId, array $documents = [], string $notes = ''): string
    {
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

        $this->dataLayer->saveVerification($verification);
        return $verificationId;
    }

    public function verifyWithCode(string $operatorId, string $code): bool
    {
        $codeData = $this->dataLayer->getVerificationCode($code);
        
        if (!$codeData) {
            throw new Exception('Invalid verification code');
        }

        if ($codeData['used']) {
            throw new Exception('Verification code has already been used');
        }

        if (isset($codeData['expires_at']) && strtotime($codeData['expires_at']) < time()) {
            throw new Exception('Verification code has expired');
        }

        // Mark code as used
        $codeData['used'] = true;
        $codeData['used_by'] = $operatorId;
        $codeData['used_at'] = date('Y-m-d H:i:s');
        $this->dataLayer->saveVerificationCode($codeData);

        // Create verification record
        $verificationId = 'verify_' . uniqid();
        $verification = [
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

        $this->dataLayer->saveVerification($verification);
        return true;
    }

    public function approveVerification(string $verificationId, string $reviewerId, string $notes = ''): bool
    {
        $verification = $this->dataLayer->getVerification($verificationId);
        if (!$verification) {
            throw new Exception('Verification not found');
        }

        $verification['status'] = self::STATUS_APPROVED;
        $verification['reviewed_by'] = $reviewerId;
        $verification['reviewed_at'] = date('Y-m-d H:i:s');
        $verification['updated_at'] = date('Y-m-d H:i:s');

        if ($notes) {
            $verification['notes'] .= "\n[APPROVED] " . $notes;
        }

        $this->dataLayer->saveVerification($verification);
        return true;
    }

    public function rejectVerification(string $verificationId, string $reviewerId, string $reason): bool
    {
        $verification = $this->dataLayer->getVerification($verificationId);
        if (!$verification) {
            throw new Exception('Verification not found');
        }

        $verification['status'] = self::STATUS_REJECTED;
        $verification['reviewed_by'] = $reviewerId;
        $verification['reviewed_at'] = date('Y-m-d H:i:s');
        $verification['updated_at'] = date('Y-m-d H:i:s');
        $verification['rejection_reason'] = $reason;

        $this->dataLayer->saveVerification($verification);
        return true;
    }

    public function isOperatorVerified(string $operatorId): bool
    {
        return $this->dataLayer->isOperatorVerified($operatorId);
    }

    public function getOperatorVerification(string $operatorId): ?array
    {
        return $this->dataLayer->getOperatorVerification($operatorId);
    }

    public function generateOverrideCode(string $generatedBy, ?string $expiresAt = null, string $notes = ''): string
    {
        $code = 'VERIFY-' . strtoupper(substr(uniqid(), -8));

        $codeData = [
            'code' => $code,
            'generated_by' => $generatedBy,
            'generated_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'used' => false,
            'used_by' => null,
            'used_at' => null,
            'notes' => $notes
        ];

        $this->dataLayer->saveVerificationCode($codeData);
        return $code;
    }

    public function getOverrideCodes(): array
    {
        return $this->dataLayer->getAllVerificationCodes();
    }

    public function getPendingVerifications(): array
    {
        return $this->dataLayer->searchVerifications(['status' => self::STATUS_PENDING]);
    }

    public function getVerificationStats(): array
    {
        return $this->dataLayer->getVerificationStats();
    }
}
