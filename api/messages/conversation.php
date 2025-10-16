<?php
/**
 * API: Get Conversation
 * GET /api/messages/conversation.php?operator_id=xxx&limit=50
 * Get message history with operator
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

$operatorId = $_GET['operator_id'] ?? null;
$limit = min((int)($_GET['limit'] ?? 50), 100); // Max 100 messages

if (!$operatorId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing operator_id']);
    exit;
}

$messages = $textService->getConversation(
    $operatorId,
    $_SESSION['customer_id'],
    $limit
);

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
