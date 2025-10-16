<?php
/**
 * API: Initiate Call
 * POST /api/calls/initiate.php
 * Customer initiates call to operator
 */

header('Content-Type: application/json');
session_start();

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/DatabaseManager.php';
require_once __DIR__ . '/../../includes/CallService.php';

$db = DatabaseManager::getInstance();
$callService = new CallService($db);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$operatorId = $input['operator_id'] ?? null;
$customerPhone = $input['customer_phone'] ?? null;

if (!$operatorId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing operator_id']);
    exit;
}

$customerId = $_SESSION['customer_id'];

// Get customer data
$customer = $db->fetchOne("
    SELECT id, phone, balance FROM customers WHERE id = :id
", ['id' => $customerId]);

if (!$customer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Customer not found']);
    exit;
}

// Use customer's saved phone if not provided
if (!$customerPhone) {
    $customerPhone = $customer['phone'];
}

if (!$customerPhone) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Phone number required. Please update your profile.'
    ]);
    exit;
}

// Get operator data
$operator = $db->fetchOne("
    SELECT id, display_name, metadata FROM operators WHERE id = :id AND is_active = true
", ['id' => $operatorId]);

if (!$operator) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Operator not found or unavailable']);
    exit;
}

$metadata = json_decode($operator['metadata'] ?? '{}', true);
$ratePerMinute = $metadata['rate_per_minute'] ?? 3.99;
$connectFee = $metadata['connect_fee'] ?? 0.99;

// Check for available free minutes
$freeMinutesResult = $db->fetchOne("
    SELECT get_available_free_minutes(:customer_id, :operator_id) as minutes
", [
    'customer_id' => $customerId,
    'operator_id' => $operatorId
]);

$freeMinutes = (int)($freeMinutesResult['minutes'] ?? 0);

// Calculate estimated duration
$balance = (float)$customer['balance'];
$hasSufficientFunds = false;
$estimatedMinutes = 0;

if ($freeMinutes > 0) {
    // Has free minutes - only need connect fee
    if ($balance >= $connectFee) {
        $hasSufficientFunds = true;
        $estimatedMinutes = $freeMinutes; // Can talk for all free minutes
    }
} else {
    // No free minutes - need balance for at least 1 minute + connect fee
    $minimumRequired = $ratePerMinute + $connectFee;
    if ($balance >= $minimumRequired) {
        $hasSufficientFunds = true;
        $estimatedMinutes = floor(($balance - $connectFee) / $ratePerMinute);
    }
}

if (!$hasSufficientFunds) {
    $requiredAmount = $freeMinutes > 0 ? $connectFee : ($ratePerMinute + $connectFee);

    http_response_code(402);
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient balance',
        'balance' => $balance,
        'required' => $requiredAmount,
        'free_minutes' => $freeMinutes,
        'needs_funds' => true
    ]);
    exit;
}

// Get domain from session or default
$domain = $_SESSION['site_domain'] ?? 'aeims.app';

// Initiate the call
$result = $callService->initiateBridgedCall([
    'customer_id' => $customerId,
    'customer_phone' => $customerPhone,
    'operator_id' => $operatorId,
    'domain' => $domain,
    'rate_per_minute' => $ratePerMinute
]);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode($result);
    exit;
}

// If using free minutes, charge connect fee immediately
$connectFeeCharged = 0;
if ($freeMinutes > 0) {
    try {
        // Deduct connect fee
        $db->execute("
            UPDATE customers SET balance = balance - :fee WHERE id = :id
        ", ['fee' => $connectFee, 'id' => $customerId]);

        // Record connect fee transaction
        $db->execute("
            INSERT INTO transactions (
                customer_id, operator_id, domain, transaction_type,
                amount, operator_amount, platform_amount,
                is_connect_fee, status, description
            ) VALUES (
                :customer_id, :operator_id, :domain, 'connect_fee',
                :amount, :operator_amount, :platform_amount,
                true, 'completed', 'Call connect fee'
            )
        ", [
            'customer_id' => $customerId,
            'operator_id' => $operatorId,
            'domain' => $domain,
            'amount' => $connectFee,
            'operator_amount' => round($connectFee * 0.80, 2),
            'platform_amount' => round($connectFee * 0.20, 2)
        ]);

        // Mark call as using free minutes
        $db->execute("
            UPDATE calls
            SET is_free_minutes_call = true,
                connect_fee_charged = :fee
            WHERE call_id = :call_id
        ", [
            'fee' => $connectFee,
            'call_id' => $result['call_id']
        ]);

        $connectFeeCharged = $connectFee;
    } catch (Exception $e) {
        error_log("Failed to charge connect fee: " . $e->getMessage());
    }
}

// Success response
echo json_encode([
    'success' => true,
    'call_id' => $result['call_id'],
    'operator' => $operator['display_name'],
    'rate_per_minute' => $ratePerMinute,
    'connect_fee' => $connectFee,
    'connect_fee_charged' => $connectFeeCharged,
    'free_minutes' => $freeMinutes,
    'estimated_duration_minutes' => $estimatedMinutes,
    'balance_remaining' => $balance - $connectFeeCharged,
    'message' => 'Call initiated successfully. You will receive a call shortly.'
]);
