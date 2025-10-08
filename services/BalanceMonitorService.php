<?php

namespace AEIMS\Services;

use Exception;

/**
 * Balance Monitor Service
 * Monitors customer balance during calls and triggers warnings/actions
 */
class BalanceMonitorService
{
    private array $activeCalls = [];
    private string $callsFile;
    private const LOW_BALANCE_THRESHOLD = 2.00; // $2.00 threshold
    private const CRITICAL_BALANCE_THRESHOLD = 0.50; // $0.50 critical threshold

    public function __construct()
    {
        $this->callsFile = __DIR__ . '/../data/active_calls.json';
        $this->loadActiveCalls();
    }

    private function loadActiveCalls(): void
    {
        if (file_exists($this->callsFile)) {
            $data = json_decode(file_get_contents($this->callsFile), true);
            $this->activeCalls = $data['active_calls'] ?? [];
        }
    }

    private function saveActiveCalls(): void
    {
        $dataDir = dirname($this->callsFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $data = [
            'active_calls' => $this->activeCalls,
            'last_updated' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->callsFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Start monitoring a call
     */
    public function startCallMonitoring(array $callData): string
    {
        $callId = 'call_' . uniqid();

        $call = [
            'call_id' => $callId,
            'customer_id' => $callData['customer_id'],
            'operator_id' => $callData['operator_id'],
            'rate_per_minute' => $callData['rate_per_minute'],
            'start_time' => date('Y-m-d H:i:s'),
            'initial_balance' => $callData['initial_balance'],
            'current_balance' => $callData['initial_balance'],
            'total_charges' => 0.00,
            'warnings_sent' => [],
            'status' => 'active',
            'last_billing_update' => time(),
            'billing_interval' => 60, // Bill every 60 seconds
            'customer_phone' => $callData['customer_phone'] ?? '',
            'low_balance_warned' => false,
            'critical_balance_warned' => false
        ];

        $this->activeCalls[$callId] = $call;
        $this->saveActiveCalls();

        return $callId;
    }

    /**
     * Update call billing and check balance
     */
    public function updateCallBilling(string $callId): array
    {
        if (!isset($this->activeCalls[$callId])) {
            throw new Exception('Call not found');
        }

        $call = &$this->activeCalls[$callId];
        $currentTime = time();
        $lastBillingTime = $call['last_billing_update'];

        // Calculate elapsed time since last billing
        $elapsedSeconds = $currentTime - $lastBillingTime;

        if ($elapsedSeconds >= $call['billing_interval']) {
            // Calculate charge for elapsed time
            $elapsedMinutes = $elapsedSeconds / 60;
            $charge = $elapsedMinutes * $call['rate_per_minute'];

            // Update balances
            $call['total_charges'] += $charge;
            $call['current_balance'] -= $charge;
            $call['last_billing_update'] = $currentTime;

            // Check balance thresholds
            $balanceStatus = $this->checkBalanceThresholds($callId);

            $this->saveActiveCalls();

            return [
                'call_id' => $callId,
                'charge_applied' => $charge,
                'current_balance' => $call['current_balance'],
                'total_charges' => $call['total_charges'],
                'balance_status' => $balanceStatus,
                'call_duration_minutes' => ($currentTime - strtotime($call['start_time'])) / 60
            ];
        }

        return [
            'call_id' => $callId,
            'charge_applied' => 0,
            'current_balance' => $call['current_balance'],
            'total_charges' => $call['total_charges'],
            'balance_status' => 'sufficient',
            'call_duration_minutes' => ($currentTime - strtotime($call['start_time'])) / 60
        ];
    }

    /**
     * Check balance thresholds and trigger warnings
     */
    private function checkBalanceThresholds(string $callId): string
    {
        $call = &$this->activeCalls[$callId];
        $balance = $call['current_balance'];

        if ($balance <= 0) {
            // Insufficient funds - call should be terminated
            $call['status'] = 'terminated_insufficient_funds';
            $this->triggerCallTermination($callId, 'insufficient_funds');
            return 'terminated';
        }

        if ($balance <= self::CRITICAL_BALANCE_THRESHOLD && !$call['critical_balance_warned']) {
            // Critical balance warning
            $call['critical_balance_warned'] = true;
            $call['warnings_sent'][] = [
                'type' => 'critical_balance',
                'balance' => $balance,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->triggerCriticalBalanceWarning($callId);
            return 'critical';
        }

        if ($balance <= self::LOW_BALANCE_THRESHOLD && !$call['low_balance_warned']) {
            // Low balance warning
            $call['low_balance_warned'] = true;
            $call['warnings_sent'][] = [
                'type' => 'low_balance',
                'balance' => $balance,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            $this->triggerLowBalanceWarning($callId);
            return 'low';
        }

        return 'sufficient';
    }

    /**
     * Trigger low balance warning
     */
    private function triggerLowBalanceWarning(string $callId): void
    {
        $call = $this->activeCalls[$callId];

        // Log warning
        error_log("Low balance warning for call {$callId}: Customer {$call['customer_id']} has ${$call['current_balance']} remaining");

        // Here you would integrate with your call system to play the warning message
        // Example: "Your balance is running low. Press 1 to add funds to your account."

        // In a real system, this would trigger:
        // 1. Audio message during call
        // 2. WebSocket notification to frontend
        // 3. SMS/email notification
        // 4. Pause call for payment if user presses 1

        $this->sendBalanceWarningToCall($callId, 'low_balance');
    }

    /**
     * Trigger critical balance warning
     */
    private function triggerCriticalBalanceWarning(string $callId): void
    {
        $call = $this->activeCalls[$callId];

        error_log("Critical balance warning for call {$callId}: Customer {$call['customer_id']} has ${$call['current_balance']} remaining");

        // Critical warning message
        $this->sendBalanceWarningToCall($callId, 'critical_balance');
    }

    /**
     * Trigger call termination due to insufficient funds
     */
    private function triggerCallTermination(string $callId, string $reason): void
    {
        $call = $this->activeCalls[$callId];

        error_log("Terminating call {$callId}: {$reason}");

        // Terminate the call
        $this->endCall($callId, $reason);

        // Send termination notice
        $this->sendCallTerminationNotice($callId, $reason);
    }

    /**
     * Send balance warning to active call
     */
    private function sendBalanceWarningToCall(string $callId, string $warningType): void
    {
        $call = $this->activeCalls[$callId];

        $messages = [
            'low_balance' => "Your account balance is running low. You have $" .
                           number_format($call['current_balance'], 2) .
                           " remaining. Press 1 to add funds or the call will end when your balance reaches zero.",
            'critical_balance' => "Critical balance warning! You have $" .
                                number_format($call['current_balance'], 2) .
                                " remaining. Press 1 now to add funds or your call will end shortly."
        ];

        // In a real system, this would:
        // 1. Play audio message during call
        // 2. Send WebSocket notification
        // 3. Trigger DTMF detection for "1" press

        $this->logBalanceEvent($callId, $warningType, $messages[$warningType]);
    }

    /**
     * Handle user pressing 1 for payment during call
     */
    public function handlePaymentRequest(string $callId, string $customerPhone): array
    {
        if (!isset($this->activeCalls[$callId])) {
            throw new Exception('Call not found');
        }

        $call = $this->activeCalls[$callId];

        // Pause call billing
        $this->pauseCallBilling($callId);

        // Initiate phone payment through PayKings
        $paymentProcessorService = new PaymentProcessorService();

        $paymentData = [
            'customer_id' => $call['customer_id'],
            'customer_phone' => $customerPhone,
            'amount' => 20.00, // Default $20 quick payment
            'transaction_id' => 'phone_' . uniqid()
        ];

        $phonePaymentResult = $paymentProcessorService->initiatePhonePayment($paymentData);

        if ($phonePaymentResult['success']) {
            return [
                'success' => true,
                'message' => 'Phone payment initiated. Please follow the instructions.',
                'estimated_time' => $phonePaymentResult['estimated_call_time'],
                'session_id' => $phonePaymentResult['phone_session_id']
            ];
        } else {
            return [
                'success' => false,
                'error' => $phonePaymentResult['error']
            ];
        }
    }

    /**
     * Resume call after successful payment
     */
    public function resumeCallAfterPayment(string $callId, float $amountAdded): void
    {
        if (!isset($this->activeCalls[$callId])) {
            throw new Exception('Call not found');
        }

        $call = &$this->activeCalls[$callId];

        // Add funds to call balance
        $call['current_balance'] += $amountAdded;
        $call['status'] = 'active';

        // Reset warning flags if balance is now sufficient
        if ($call['current_balance'] > self::LOW_BALANCE_THRESHOLD) {
            $call['low_balance_warned'] = false;
        }
        if ($call['current_balance'] > self::CRITICAL_BALANCE_THRESHOLD) {
            $call['critical_balance_warned'] = false;
        }

        // Resume billing
        $call['last_billing_update'] = time();

        $this->saveActiveCalls();

        $this->logBalanceEvent($callId, 'payment_added', "Added ${amountAdded} to call balance");
    }

    /**
     * Pause call billing
     */
    public function pauseCallBilling(string $callId): void
    {
        if (isset($this->activeCalls[$callId])) {
            $this->activeCalls[$callId]['status'] = 'paused_for_payment';
            $this->saveActiveCalls();
        }
    }

    /**
     * End call and calculate final charges
     */
    public function endCall(string $callId, string $reason = 'normal'): array
    {
        if (!isset($this->activeCalls[$callId])) {
            throw new Exception('Call not found');
        }

        $call = $this->activeCalls[$callId];

        // Calculate final billing
        $finalUpdate = $this->updateCallBilling($callId);

        $call = &$this->activeCalls[$callId];
        $call['status'] = 'ended';
        $call['end_time'] = date('Y-m-d H:i:s');
        $call['end_reason'] = $reason;

        $callSummary = [
            'call_id' => $callId,
            'duration_minutes' => (time() - strtotime($call['start_time'])) / 60,
            'total_charges' => $call['total_charges'],
            'final_balance' => $call['current_balance'],
            'end_reason' => $reason
        ];

        // Move to call history
        $this->archiveCall($callId);

        return $callSummary;
    }

    /**
     * Get active call status
     */
    public function getCallStatus(string $callId): ?array
    {
        return $this->activeCalls[$callId] ?? null;
    }

    /**
     * Get all active calls
     */
    public function getActiveCalls(): array
    {
        return array_values($this->activeCalls);
    }

    /**
     * Archive completed call
     */
    private function archiveCall(string $callId): void
    {
        $call = $this->activeCalls[$callId];
        unset($this->activeCalls[$callId]);

        // Save to call history (you could save to a separate file)
        $historyFile = __DIR__ . '/../data/call_history.json';
        $history = [];

        if (file_exists($historyFile)) {
            $data = json_decode(file_get_contents($historyFile), true);
            $history = $data['calls'] ?? [];
        }

        $history[$callId] = $call;

        file_put_contents($historyFile, json_encode([
            'calls' => $history,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));

        $this->saveActiveCalls();
    }

    /**
     * Log balance-related events
     */
    private function logBalanceEvent(string $callId, string $eventType, string $message): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'call_id' => $callId,
            'event_type' => $eventType,
            'message' => $message,
            'current_balance' => $this->activeCalls[$callId]['current_balance'] ?? 0
        ];

        error_log("Balance Monitor: " . json_encode($logEntry));
    }

    /**
     * Send call termination notice
     */
    private function sendCallTerminationNotice(string $callId, string $reason): void
    {
        // In a real system, this would send notifications
        // via WebSocket, email, SMS, etc.

        $this->logBalanceEvent($callId, 'call_terminated', "Call terminated: {$reason}");
    }

    /**
     * Monitor all active calls (run this in a background process)
     */
    public function monitorAllCalls(): array
    {
        $updates = [];

        foreach ($this->activeCalls as $callId => $call) {
            if ($call['status'] === 'active') {
                $update = $this->updateCallBilling($callId);
                if ($update['charge_applied'] > 0 || $update['balance_status'] !== 'sufficient') {
                    $updates[$callId] = $update;
                }
            }
        }

        return $updates;
    }
}