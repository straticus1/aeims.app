<?php
/**
 * API: Send Message
 * POST /api/messages/send.php
 * Send text message to operator
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/DatabaseManager.php';
require_once __DIR__ . '/../../includes/TextService.php';

$db = DatabaseManager::getInstance();
$textService = new TextService($db);

$input = json_decode(file_get_contents('php://input'), true);
$operatorId = $input['operator_id'] ?? null;
$messageText = $input['message_text'] ?? '';
$messageType = $input['message_type'] ?? 'chat';

if (!$operatorId || empty($messageText)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$domain = $_SESSION['site_domain'] ?? 'aeims.app';

$result = $textService->sendCustomerToOperator([
    'customer_id' => $_SESSION['customer_id'],
    'operator_id' => $operatorId,
    'message_text' => $messageText,
    'domain' => $domain,
    'message_type' => $messageType
]);

if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code($result['error'] === 'Insufficient balance' ? 402 : 400);
}

echo json_encode($result);
