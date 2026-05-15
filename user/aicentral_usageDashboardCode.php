<?php
/**
 * AI Central User Usage Dashboard - Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

// SECURITY: Use session-based authentication (NOT cookies)
$user = auth_getAuthenticatedUser('AI');
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}
$userId = $user['user_id'];  // Legacy users: username string, new users: email

aiCentral_logMessage("User Usage Dashboard: action=$action, user=$userId", 'INFO');

try {
    switch ($action) {
        case 'getUsageData':
            getUsageData($userId);
            break;

        case 'getTierInfo':
            getTierInfo($userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("User Usage Dashboard error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get user's tier information
 */
function getTierInfo($userId) {
    $conn = ai_getDBConnection();

    // Get user's tier
    $sql = "SELECT u.default_ai_tier, t.tier_name, t.tier_description,
                   t.daily_request_limit, t.monthly_request_limit,
                   t.daily_token_limit, t.monthly_token_limit,
                   t.monthly_spend_limit_usd
            FROM users u
            LEFT JOIN ai_tiers t ON u.default_ai_tier = t.tier_code
            WHERE u.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tierInfo = $result->fetch_assoc();
    $stmt->close();

    // Get current month usage
    $startOfMonth = date('Y-m-01');
    $sql = "SELECT
                COUNT(*) as monthly_requests,
                SUM(total_tokens) as monthly_tokens,
                SUM(total_cost_usd) as monthly_cost
            FROM ai_usage_log
            WHERE user_id = ? AND DATE(request_timestamp) >= ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $userId, $startOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $usage = $result->fetch_assoc();
    $stmt->close();

    // Get today's usage
    $today = date('Y-m-d');
    $sql = "SELECT
                COUNT(*) as daily_requests,
                SUM(total_tokens) as daily_tokens
            FROM ai_usage_log
            WHERE user_id = ? AND DATE(request_timestamp) = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $dailyUsage = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'tier' => [
            'tier_code' => $tierInfo['default_ai_tier'],
            'tier_name' => $tierInfo['tier_name'],
            'tier_description' => $tierInfo['tier_description'],
            'daily_request_limit' => $tierInfo['daily_request_limit'] ? (int)$tierInfo['daily_request_limit'] : null,
            'monthly_request_limit' => $tierInfo['monthly_request_limit'] ? (int)$tierInfo['monthly_request_limit'] : null,
            'daily_token_limit' => $tierInfo['daily_token_limit'] ? (int)$tierInfo['daily_token_limit'] : null,
            'monthly_token_limit' => $tierInfo['monthly_token_limit'] ? (int)$tierInfo['monthly_token_limit'] : null,
            'monthly_spend_limit_usd' => $tierInfo['monthly_spend_limit_usd'] ? (float)$tierInfo['monthly_spend_limit_usd'] : null
        ],
        'usage' => [
            'monthly_requests' => (int)$usage['monthly_requests'],
            'monthly_tokens' => (int)$usage['monthly_tokens'],
            'monthly_cost' => (float)$usage['monthly_cost'],
            'daily_requests' => (int)$dailyUsage['daily_requests'],
            'daily_tokens' => (int)$dailyUsage['daily_tokens']
        ]
    ]);
}

/**
 * Get user's usage data
 */
function getUsageData($userId) {
    $conn = ai_getDBConnection();

    $startDate = $_REQUEST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_REQUEST['end_date'] ?? date('Y-m-d');

    // Overall summary
    $sql = "SELECT
                COUNT(*) as total_requests,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(total_cost_usd) as total_cost
            FROM ai_usage_log
            WHERE user_id = ? AND DATE(request_timestamp) BETWEEN ? AND ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Usage by feature
    $sql = "SELECT
                feature_code,
                COUNT(*) as requests,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE user_id = ? AND DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY feature_code
            ORDER BY requests DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byFeature = [];
    while ($row = $result->fetch_assoc()) {
        $byFeature[] = $row;
    }
    $stmt->close();

    // Usage by provider
    $sql = "SELECT
                p.provider_name,
                COUNT(*) as requests,
                SUM(l.total_cost_usd) as cost
            FROM ai_usage_log l
            JOIN ai_providers p ON l.provider_id = p.provider_id
            WHERE l.user_id = ? AND DATE(l.request_timestamp) BETWEEN ? AND ?
            GROUP BY p.provider_name
            ORDER BY requests DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $userId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byProvider = [];
    while ($row = $result->fetch_assoc()) {
        $byProvider[] = $row;
    }
    $stmt->close();

    // Recent activity
    $sql = "SELECT
                l.request_timestamp,
                l.feature_code,
                l.program_id,
                p.provider_name,
                m.model_display_name,
                l.total_tokens,
                l.total_cost_usd,
                l.status
            FROM ai_usage_log l
            JOIN ai_providers p ON l.provider_id = p.provider_id
            JOIN ai_models m ON l.model_id = m.model_id
            WHERE l.user_id = ?
            ORDER BY l.request_timestamp DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentActivity = [];
    while ($row = $result->fetch_assoc()) {
        $recentActivity[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_requests' => (int)$summary['total_requests'],
            'total_input_tokens' => (int)$summary['total_input_tokens'],
            'total_output_tokens' => (int)$summary['total_output_tokens'],
            'total_cost' => (float)$summary['total_cost']
        ],
        'byFeature' => $byFeature,
        'byProvider' => $byProvider,
        'recentActivity' => $recentActivity,
        'dateRange' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

?>
