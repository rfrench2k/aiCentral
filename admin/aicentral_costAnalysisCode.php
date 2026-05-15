<?php
/**
 * AI Central Admin Cost Analysis - Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

header('Content-Type: application/json');

// SECURITY: Require ADMIN-level authentication for admin functions
$user = auth_getAuthenticatedUser('AI');
if (!$user || !in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Cost Analysis API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getCostData':
            getCostData();
            break;

        case 'getUserBreakdown':
            getUserBreakdown();
            break;

        case 'getPassthroughPrograms':
            getPassthroughPrograms();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Cost Analysis API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get cost analysis data
 */
function getCostData() {
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
            WHERE DATE(request_timestamp) BETWEEN ? AND ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();

    // Cost by provider
    $sql = "SELECT
                p.provider_code,
                COUNT(*) as requests,
                SUM(l.input_tokens) as input_tokens,
                SUM(l.output_tokens) as output_tokens,
                SUM(l.total_cost_usd) as cost
            FROM ai_usage_log l
            JOIN ai_providers p ON l.provider_id = p.provider_id
            WHERE DATE(l.request_timestamp) BETWEEN ? AND ?
            GROUP BY p.provider_code
            ORDER BY cost DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byProvider = [];
    while ($row = $result->fetch_assoc()) {
        $byProvider[] = $row;
    }
    $stmt->close();

    // Cost by model
    $sql = "SELECT
                m.model_code,
                COUNT(*) as requests,
                SUM(l.input_tokens) as input_tokens,
                SUM(l.output_tokens) as output_tokens,
                SUM(l.total_cost_usd) as cost
            FROM ai_usage_log l
            JOIN ai_models m ON l.model_id = m.model_id
            WHERE DATE(l.request_timestamp) BETWEEN ? AND ?
            GROUP BY m.model_code
            ORDER BY cost DESC
            LIMIT 20";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byModel = [];
    while ($row = $result->fetch_assoc()) {
        $byModel[] = $row;
    }
    $stmt->close();

    // Cost by program
    $sql = "SELECT
                program_id,
                COUNT(*) as requests,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY program_id
            ORDER BY cost DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byProgram = [];
    while ($row = $result->fetch_assoc()) {
        $byProgram[] = $row;
    }
    $stmt->close();

    // Cost by feature
    $sql = "SELECT
                feature_code,
                COUNT(*) as requests,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE DATE(request_timestamp) BETWEEN ? AND ?
              AND feature_code IS NOT NULL
            GROUP BY feature_code
            ORDER BY cost DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byFeature = [];
    while ($row = $result->fetch_assoc()) {
        $byFeature[] = $row;
    }
    $stmt->close();

    // Cost by user (top 10)
    $sql = "SELECT
                user_id,
                COUNT(*) as requests,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY user_id
            ORDER BY cost DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $byUser = [];
    while ($row = $result->fetch_assoc()) {
        $byUser[] = $row;
    }
    $stmt->close();

    // Daily trend
    $sql = "SELECT
                DATE(request_timestamp) as date,
                COUNT(*) as requests,
                SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY DATE(request_timestamp)
            ORDER BY date";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $dailyTrend = [];
    while ($row = $result->fetch_assoc()) {
        $dailyTrend[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_requests' => (int)$summary['total_requests'],
            'total_input_tokens' => (int)$summary['total_input_tokens'],
            'total_output_tokens' => (int)$summary['total_output_tokens'],
            'total_cost' => (float)$summary['total_cost'],
            'avg_cost_per_request' => $summary['total_requests'] > 0 ? (float)$summary['total_cost'] / (int)$summary['total_requests'] : 0
        ],
        'byProvider' => $byProvider,
        'byModel' => $byModel,
        'byProgram' => $byProgram,
        'byFeature' => $byFeature,
        'byUser' => $byUser,
        'dailyTrend' => $dailyTrend,
        'dateRange' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

/**
 * Get passthrough-mode programs for the program filter dropdown
 */
function getPassthroughPrograms() {
    $conn = ai_getDBConnection();

    $sql = "SELECT program_id, program_name FROM programs WHERE passthrough_mode = 1 AND is_active = 1 ORDER BY program_name";
    $result = $conn->query($sql);

    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }

    echo json_encode(['success' => true, 'programs' => $programs]);
}

/**
 * Get per-user breakdown for a passthrough program
 */
function getUserBreakdown() {
    $conn = ai_getDBConnection();

    $programId = $_REQUEST['program_id'] ?? '';
    $startDate = $_REQUEST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_REQUEST['end_date'] ?? date('Y-m-d');

    if (empty($programId)) {
        echo json_encode(['success' => false, 'error' => 'program_id is required']);
        return;
    }

    // Verify this is a passthrough program
    $stmt = $conn->prepare("SELECT passthrough_mode FROM programs WHERE program_id = ?");
    $stmt->bind_param('s', $programId);
    $stmt->execute();
    $prog = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prog || !$prog['passthrough_mode']) {
        echo json_encode(['success' => false, 'error' => 'Not a passthrough program']);
        return;
    }

    // Per-user summary
    $sql = "SELECT
                user_id,
                COUNT(*) as requests,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(total_cost_usd) as cost,
                MAX(request_timestamp) as last_active
            FROM ai_usage_log
            WHERE program_id = ?
              AND DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY user_id
            ORDER BY cost DESC
            LIMIT 50";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $programId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    // Per-feature summary for this program
    $sql = "SELECT
                feature_code,
                COUNT(*) as requests,
                SUM(total_cost_usd) as cost,
                COUNT(DISTINCT user_id) as unique_users
            FROM ai_usage_log
            WHERE program_id = ?
              AND DATE(request_timestamp) BETWEEN ? AND ?
            GROUP BY feature_code
            ORDER BY cost DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $programId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $features = [];
    while ($row = $result->fetch_assoc()) {
        $features[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'program_id' => $programId,
        'users' => $users,
        'features' => $features,
        'dateRange' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

?>
