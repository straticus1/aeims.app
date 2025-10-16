<?php
/**
 * API: Call Status
 * GET /api/calls/status.php?call_id=xxx
 * Get real-time call status and duration
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/DatabaseManager.php';

$db = DatabaseManager::getInstance();
$callId = $_GET['call_id'] ?? '';

if (!$callId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing call_id']);
    exit;
}

$call = $db->fetchOne("
    SELECT
        call_id,
        status,
        duration_seconds,
        created_at,
        answered_at,
        ended_at,
        operator_id,
        customer_id,
        free_minutes_used,
        connect_fee_charged,
        is_free_minutes_call
    FROM calls
    WHERE call_id = :call_id
    AND customer_id = :customer_id
", [
    'call_id' => $callId,
    'customer_id' => $_SESSION['customer_id']
]);

if (!$call) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Call not found']);
    exit;
}

// Calculate current duration if call is active
$durationSeconds = (int)$call['duration_seconds'];
if (in_array($call['status'], ['answered', 'ringing']) && $call['answered_at']) {
    $answeredTime = strtotime($call['answered_at']);
    $durationSeconds = time() - $answeredTime;
}

// Get operator rate
$operator = $db->fetchOne("
    SELECT metadata FROM operators WHERE id = :id
", ['operator_id' => $call['operator_id']]);

$metadata = json_decode($operator['metadata'] ?? '{}', true);
$ratePerMinute = $metadata['rate_per_minute'] ?? 3.99;

// Calculate estimated charges
$minutes = ceil($durationSeconds / 60);
$freeMinutesUsed = (int)$call['free_minutes_used'];
$paidMinutes = max(0, $minutes - $freeMinutesUsed);
$estimatedCharges = round($paidMinutes * $ratePerMinute, 2);

// Add connect fee if it was charged
if ($call['connect_fee_charged']) {
    $estimatedCharges += (float)$call['connect_fee_charged'];
}

echo json_encode([
    'success' => true,
    'call_id' => $call['call_id'],
    'status' => $call['status'],
    'duration_seconds' => $durationSeconds,
    'duration_minutes' => $minutes,
    'operator_id' => $call['operator_id'],
    'rate_per_minute' => $ratePerMinute,
    'free_minutes_used' => $freeMinutesUsed,
    'paid_minutes' => $paidMinutes,
    'estimated_charges' => $estimatedCharges,
    'is_active' => in_array($call['status'], ['initiated', 'ringing', 'answered']),
    'created_at' => $call['created_at'],
    'answered_at' => $call['answered_at'],
    'ended_at' => $call['ended_at']
]);
