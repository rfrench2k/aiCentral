<?php
/**
 * AI Settings - User Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/KeyManager.php';

header('Content-Type: application/json');

// Handle JSON requests
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if ($jsonData) {
        $_POST = array_merge($_POST, $jsonData);
        $_REQUEST = array_merge($_REQUEST, $jsonData);
    }
}

$action = $_REQUEST['action'] ?? '';

// SECURITY: Use session-based authentication (NOT cookies)
$user = auth_getAuthenticatedUser('AI');
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}
$userId = $user['user_id'];  // Legacy users: username string, new users: email

aiCentral_logMessage("User Settings API: action=$action, user_id=$userId", 'INFO');

try {
    switch ($action) {
        case 'getUserTierInfo':
            getUserTierInfo($userId);
            break;

        case 'getUserKeys':
            getUserKeys($userId);
            break;

        case 'saveUserKey':
            saveUserKey($userId);
            break;

        case 'deleteUserKey':
            deleteUserKey($userId);
            break;

        case 'getUserUsage':
        case 'getUsageSummary':
            getUserUsage($userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("User Settings API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get user's tier information across all programs
 */
function getUserTierInfo($userId) {
    $conn = ai_getDBConnection();

    $sql = "SELECT uta.program_id, t.tier_code, t.tier_name, t.tier_description,
                   t.daily_request_limit, t.monthly_request_limit,
                   t.daily_token_limit, t.monthly_token_limit,
                   t.monthly_spend_limit_usd
            FROM user_tier_assignments uta
            JOIN ai_tiers t ON uta.tier_id = t.tier_id
            WHERE uta.user_id = ?
            ORDER BY uta.program_id";

    $result = aiCentral_executeQuery($conn, $sql, [$userId], 's');

    $tiers = [];
    while ($row = $result->fetch_assoc()) {
        $tiers[] = [
            'program_id' => $row['program_id'],
            'tier_code' => $row['tier_code'],
            'tier_name' => $row['tier_name'],
            'tier_description' => $row['tier_description'],
            'daily_request_limit' => $row['daily_request_limit'] ? (int)$row['daily_request_limit'] : null,
            'monthly_request_limit' => $row['monthly_request_limit'] ? (int)$row['monthly_request_limit'] : null,
            'daily_token_limit' => $row['daily_token_limit'] ? (int)$row['daily_token_limit'] : null,
            'monthly_token_limit' => $row['monthly_token_limit'] ? (int)$row['monthly_token_limit'] : null,
            'monthly_spend_limit_usd' => $row['monthly_spend_limit_usd'] ? (float)$row['monthly_spend_limit_usd'] : null
        ];
    }

    echo json_encode(['success' => true, 'tiers' => $tiers]);
}

/**
 * Get user's API keys
 */
function getUserKeys($userId) {
    $conn = ai_getDBConnection();

    $sql = "SELECT k.key_id, p.provider_code, p.provider_name, k.key_name, k.last_used_at
            FROM user_api_keys k
            JOIN ai_providers p ON k.provider_id = p.provider_id
            WHERE k.user_id = ? AND k.is_active = 1
            ORDER BY k.created_at DESC";

    $result = aiCentral_executeQuery($conn, $sql, [$userId], 's');

    $keys = [];
    while ($row = $result->fetch_assoc()) {
        $keys[] = [
            'key_id' => (int)$row['key_id'],
            'provider_code' => $row['provider_code'],
            'provider_name' => $row['provider_name'],
            'key_label' => $row['key_name'],
            'last_used_at' => $row['last_used_at']
        ];
    }

    echo json_encode(['success' => true, 'keys' => $keys]);
}

/**
 * Save user's API key
 */
function saveUserKey($userId) {
    $conn = ai_getDBConnection();

    $providerCode = trim($_POST['provider'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $keyLabel = trim($_POST['key_label'] ?? '');

    if (empty($providerCode) || empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Provider and API key are required']);
        return;
    }

    // Get provider_id from provider_code
    $sql = "SELECT provider_id FROM ai_providers WHERE provider_code = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$providerCode], 's');

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid provider']);
        return;
    }

    $row = $result->fetch_assoc();
    $providerId = $row['provider_id'];

    // Encrypt the API key
    $keyManager = new KeyManager();

    try {
        $encrypted = $keyManager->encryptKey($apiKey);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to encrypt API key']);
        return;
    }

    // Insert into database
    $encryptedApiKey = $encrypted['encrypted'];
    $encryptionIv = $encrypted['iv'];
    $keyName = empty($keyLabel) ? NULL : $keyLabel;

    $sql = "INSERT INTO user_api_keys (user_id, provider_id, encrypted_api_key, encryption_iv, key_name)
            VALUES (?, ?, ?, ?, ?)";

    $affectedRows = aiCentral_executeUpdate($conn, $sql,
        [$userId, $providerId, $encryptedApiKey, $encryptionIv, $keyName],
        'sisss'
    );

    if ($affectedRows !== false) {
        aiCentral_logMessage("User API key saved: user_id=$userId, provider=$providerCode", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save API key']);
    }
}

/**
 * Delete user's API key
 */
function deleteUserKey($userId) {
    $conn = ai_getDBConnection();

    $keyId = (int)($_POST['key_id'] ?? 0);

    if (!$keyId) {
        echo json_encode(['success' => false, 'error' => 'Key ID required']);
        return;
    }

    // Verify ownership
    $sql = "UPDATE user_api_keys SET is_active = 0 WHERE key_id = ? AND user_id = ?";
    $affectedRows = aiCentral_executeUpdate($conn, $sql, [$keyId, $userId], 'is');

    if ($affectedRows !== false) {
        aiCentral_logMessage("User API key deleted: user_id=$userId, key_id=$keyId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete API key']);
    }
}

/**
 * Get user's usage statistics
 */
function getUserUsage($userId) {
    $conn = ai_getDBConnection();

    // Get total usage (all time)
    $sql = "SELECT COUNT(*) as total_requests, SUM(total_cost_usd) as total_cost
            FROM ai_usage_log
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalUsage = $result->fetch_assoc();
    $stmt->close();

    // Get today's usage
    $sql = "SELECT COUNT(*) as requests_today
            FROM ai_usage_log
            WHERE user_id = ? AND DATE(request_timestamp) = CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $todayUsage = $result->fetch_assoc();
    $stmt->close();

    // Get this month's usage
    $sql = "SELECT COUNT(*) as requests_month
            FROM ai_usage_log
            WHERE user_id = ? AND YEAR(request_timestamp) = YEAR(CURDATE()) AND MONTH(request_timestamp) = MONTH(CURDATE())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthUsage = $result->fetch_assoc();
    $stmt->close();

    // Get recent requests
    $sql = "SELECT ul.request_timestamp as created_at, ul.program_id, ul.feature_code,
                   p.provider_name as provider, m.model_display_name as model,
                   ul.total_tokens, ul.total_cost_usd as estimated_cost
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            WHERE ul.user_id = ?
            ORDER BY ul.request_timestamp DESC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $recent[] = [
            'created_at' => $row['created_at'],
            'program_id' => $row['program_id'],
            'feature_code' => $row['feature_code'],
            'provider' => $row['provider'],
            'model' => $row['model'],
            'total_tokens' => (int)$row['total_tokens'],
            'estimated_cost' => number_format((float)$row['estimated_cost'], 4)
        ];
    }
    $stmt->close();

    // Get daily usage for chart (last 30 days)
    $sql = "SELECT DATE(request_timestamp) as date, COUNT(*) as count
            FROM ai_usage_log
            WHERE user_id = ? AND request_timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(request_timestamp)
            ORDER BY date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $chartData = [];
    while ($row = $result->fetch_assoc()) {
        $chartData[] = [
            'date' => $row['date'],
            'count' => (int)$row['count']
        ];
    }
    $stmt->close();

    $stats = [
        'total_requests' => (int)$totalUsage['total_requests'],
        'total_cost' => number_format((float)$totalUsage['total_cost'], 2),
        'today_requests' => (int)$todayUsage['requests_today'],
        'month_requests' => (int)$monthUsage['requests_month']
    ];

    echo json_encode(['success' => true, 'stats' => $stats, 'recent' => $recent, 'chart' => $chartData]);
}

?>
