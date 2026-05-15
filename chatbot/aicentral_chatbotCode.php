<?php
/**
 * Universal Chatbot - Backend API
 * Handles all chatbot operations using AI Central System
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/ai_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

// SECURITY: Use session-based authentication (NOT cookies or REQUEST params)
$user = auth_getAuthenticatedUser('AI');
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}
$userId = $user['user_id'];  // Legacy users: username string, new users: email
$programId = $user['program_id'];

aiCentral_logMessage("Chatbot API: action=$action, user=$userId, program=$programId", 'INFO');

try {
    switch ($action) {
        case 'getConversations':
            getConversations($userId, $programId);
            break;

        case 'createConversation':
            createConversation($userId, $programId);
            break;

        case 'getMessages':
            getMessages($_REQUEST['conversation_id'] ?? 0, $userId);
            break;

        case 'sendMessage':
            sendMessage($userId, $programId, $_REQUEST);
            break;

        case 'renameConversation':
            renameConversation($_REQUEST['conversation_id'] ?? 0, $_REQUEST['title'] ?? '', $userId);
            break;

        case 'deleteConversation':
            deleteConversation($_REQUEST['conversation_id'] ?? 0, $userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Chatbot API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Verify the conversation belongs to the calling user.
 * Returns true if owned, false otherwise (and emits an error response).
 */
function chatbot_assertConversationOwner($conn, $conversationId, $userId) {
    $convId = (int)$conversationId;
    $sql = "SELECT user_id FROM chat_conversations WHERE conversation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row || $row['user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Conversation not found or access denied']);
        return false;
    }
    return true;
}

/**
 * Get conversations for user
 */
function getConversations($userId, $programId) {
    $conn = ai_getDBConnection();

    $sql = "SELECT conversation_id, program_id, chat_type_code, conversation_title,
                   started_at, last_message_at, message_count, is_archived
            FROM chat_conversations
            WHERE user_id = ? AND program_id = ? AND is_archived = 0
            ORDER BY last_message_at DESC
            LIMIT 50";

    $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId], 'ss');

    $conversations = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
    }

    echo json_encode(['success' => true, 'conversations' => $conversations]);
}

/**
 * Create new conversation
 */
function createConversation($userId, $programId) {
    $conn = ai_getDBConnection();

    $chatTypeCode = $_REQUEST['chat_type_code'] ?? 'general_assistant';

    $sql = "INSERT INTO chat_conversations (user_id, program_id, chat_type_code, conversation_title)
            VALUES (?, ?, ?, 'New Chat')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $userId, $programId, $chatTypeCode);
    $stmt->execute();
    $conversationId = $conn->insert_id;
    $stmt->close();

    aiCentral_logMessage("Created conversation $conversationId for user $userId", 'INFO');

    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
}

/**
 * Get messages for conversation
 */
function getMessages($conversationId, $userId) {
    $conn = ai_getDBConnection();

    if (!$conversationId) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    if (!chatbot_assertConversationOwner($conn, $conversationId, $userId)) {
        return;
    }

    $sql = "SELECT message_id, conversation_id, message_role, message_text,
                   created_at, usage_log_id, page_context
            FROM chat_messages
            WHERE conversation_id = ?
            ORDER BY created_at ASC";

    $result = aiCentral_executeQuery($conn, $sql, [$conversationId], 'i');

    $messages = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
    }

    echo json_encode(['success' => true, 'messages' => $messages]);
}

/**
 * Send message and get AI response
 */
function sendMessage($userId, $programId, $params) {
    $conn = ai_getDBConnection();

    $conversationId = $params['conversation_id'] ?? 0;
    $message = $params['message'] ?? '';
    $chatTypeCode = $params['chat_type_code'] ?? 'general_assistant';
    $pageContext = $params['page_context'] ?? '';
    $pageData = $params['page_data'] ?? null;

    if (!$conversationId || !$message) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID and message required']);
        return;
    }

    if (!chatbot_assertConversationOwner($conn, $conversationId, $userId)) {
        return;
    }

    // Get chat type config
    $chatConfig = ai_getChatTypeConfig($programId, $chatTypeCode);
    if (!$chatConfig) {
        echo json_encode(['success' => false, 'error' => 'Chat type not found']);
        return;
    }

    // Get conversation history
    $sql = "SELECT message_role, message_text FROM chat_messages
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT ?";

    $maxHistory = $chatConfig['max_history_messages'] ?? 20;
    $stmt = $conn->prepare($sql);
    $convId = (int)$conversationId;
    $stmt->bind_param('ii', $convId, $maxHistory);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'role' => $row['message_role'],
            'content' => $row['message_text']
        ];
    }
    $history = array_reverse($history); // Oldest first
    $stmt->close();

    // Build system prompt
    $systemPrompt = $chatConfig['system_prompt'];

    // Add page context if provided
    if ($pageContext) {
        $systemPrompt .= "\n\nCurrent Page: $pageContext";
    }

    if ($pageData) {
        $systemPrompt .= "\n\nPage Data:\n" . json_encode($pageData, JSON_PRETTY_PRINT);
    }

    // Build conversation for AI Central
    $conversationMessages = [];
    foreach ($history as $msg) {
        $conversationMessages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
    $conversationMessages[] = [
        'role' => 'user',
        'content' => $message
    ];

    // Prepare full prompt with history
    $fullPrompt = "";
    foreach ($conversationMessages as $msg) {
        $fullPrompt .= $msg['role'] . ": " . $msg['content'] . "\n\n";
    }

    // Call AI Central System
    $aiResult = ai_makeRequest([
        'user_id' => $userId,
        'program_id' => $programId,
        'feature_code' => 'chatbot_' . $chatTypeCode,
        'prompt' => $fullPrompt,
        'options' => [
            'system' => $systemPrompt,
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'metadata' => [
                'conversation_id' => $conversationId,
                'page_context' => $pageContext,
                'has_history' => count($history) > 0
            ]
        ]
    ]);

    if (!$aiResult['success']) {
        echo json_encode(['success' => false, 'error' => $aiResult['error']]);
        return;
    }

    $aiResponse = $aiResult['response'];
    $usageLogId = null; // Would get from ai_makeRequest if we returned it

    // Save user message
    $pageContextJson = $pageContext ? json_encode(['context' => $pageContext, 'data' => $pageData]) : null;
    $sql = "INSERT INTO chat_messages (conversation_id, message_role, message_text, page_context)
            VALUES (?, 'user', ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $convId, $message, $pageContextJson);
    $stmt->execute();
    $stmt->close();

    // Save assistant response
    $sql = "INSERT INTO chat_messages (conversation_id, message_role, message_text, usage_log_id)
            VALUES (?, 'assistant', ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isi', $convId, $aiResponse, $usageLogId);
    $stmt->execute();
    $messageId = $conn->insert_id;
    $stmt->close();

    // Update conversation
    $sql = "UPDATE chat_conversations
            SET last_message_at = NOW(), message_count = message_count + 2
            WHERE conversation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $convId);
    $stmt->execute();
    $stmt->close();

    // Auto-title if first message
    if (count($history) == 0) {
        $autoTitle = substr($message, 0, 50);
        if (strlen($message) > 50) $autoTitle .= '...';

        $sql = "UPDATE chat_conversations SET conversation_title = ? WHERE conversation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $autoTitle, $convId);
        $stmt->execute();
        $stmt->close();
    }

    aiCentral_logMessage("Chatbot response sent. Cost: $" . number_format($aiResult['cost']['total_cost'], 6), 'INFO');

    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'response' => $aiResponse,
        'usage' => $aiResult['usage'],
        'cost' => $aiResult['cost']
    ]);
}

/**
 * Rename conversation
 */
function renameConversation($conversationId, $title, $userId) {
    $conn = ai_getDBConnection();

    if (!$conversationId || !$title) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID and title required']);
        return;
    }

    if (!chatbot_assertConversationOwner($conn, $conversationId, $userId)) {
        return;
    }

    $sql = "UPDATE chat_conversations SET conversation_title = ? WHERE conversation_id = ?";
    $stmt = $conn->prepare($sql);
    $convId = (int)$conversationId;
    $stmt->bind_param('si', $title, $convId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'affected' => $affected]);
}

/**
 * Delete conversation (archive)
 */
function deleteConversation($conversationId, $userId) {
    $conn = ai_getDBConnection();

    if (!$conversationId) {
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $sql = "UPDATE chat_conversations SET is_archived = 1 WHERE conversation_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $convId = (int)$conversationId;
    $stmt->bind_param('is', $convId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode(['success' => true, 'affected' => $affected]);
}

?>
