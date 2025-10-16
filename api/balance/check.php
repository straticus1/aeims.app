<?php
/**
 * API: Check Balance
 * GET /api/balance/check.php?operator_id=xxx
 * Get customer balance and estimated talk time
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
$operatorId = $_GET['operator_id'] ?? null;
$customerId = $_SESSION['customer_id'];

if (!$operatorId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing operator_id']);
    exit;
}

// Get customer balance
$customer = $db->fetchOne("
    SELECT id, balance FROM customers WHERE id = :id
", ['id' => $customerId]);

if (!$customer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Customer not found']);
    exit;
}

// Get operator rates
$operator = $db->fetchOne("
    SELECT id, metadata FROM operators WHERE id = :id AND is_active = true
", ['id' => $operatorId]);

if (!$operator) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Operator not found']);
    exit;
}

$metadata = json_decode($operator['metadata'] ?? '{}', true);
$ratePerMinute = $metadata['rate_per_minute'] ?? 3.99;
$connectFee = $metadata['connect_fee'] ?? 0.99;
$ratePerMessage = $metadata['rate_per_message'] ?? 0.50;

// Get available free minutes
$freeMinutesResult = $db->fetchOne("
    SELECT get_available_free_minutes(:customer_id, :operator_id) as minutes
", [
    'customer_id' => $customerId,
    'operator_id' => $operatorId
]);

$freeMinutes = (int)($freeMinutesResult['minutes'] ?? 0);
$balance = (float)$customer['balance'];

// Calculate estimated talk time
$estimatedDuration = [
    'with_free_minutes' => 0,
    'with_paid_balance' => 0,
    'total_minutes' => 0
];

if ($freeMinutes > 0) {
    $estimatedDuration['with_free_minutes'] = $freeMinutes;
}

if ($balance > $connectFee) {
    $estimatedDuration['with_paid_balance'] = floor(($balance - $connectFee) / $ratePerMinute);
}

$estimatedDuration['total_minutes'] = $estimatedDuration['with_free_minutes'] + $estimatedDuration['with_paid_balance'];

// Calculate estimated text messages
$estimatedTexts = $balance > 0 ? floor($balance / $ratePerMessage) : 0;

// Check if customer can afford a call
$canAffordCall = false;
$minimumRequired = 0;

if ($freeMinutes > 0 && $balance >= $connectFee) {
    $canAffordCall = true;
} elseif ($freeMinutes === 0 && $balance >= ($ratePerMinute + $connectFee)) {
    $canAffordCall = true;
}

if (!$canAffordCall) {
    $minimumRequired = $freeMinutes > 0 ? $connectFee : ($ratePerMinute + $connectFee);
}

echo json_encode([
    'success' => true,
    'balance' => $balance,
    'free_minutes' => $freeMinutes,
    'operator_rate' => $ratePerMinute,
    'connect_fee' => $connectFee,
    'rate_per_message' => $ratePerMessage,
    'estimated_duration' => $estimatedDuration,
    'estimated_texts' => $estimatedTexts,
    'can_afford_call' => $canAffordCall,
    'minimum_required' => $minimumRequired
]);
