<?php
/**
 * AI Central Admin Dashboard - Backend API
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

aiCentral_logMessage("Dashboard API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getStats':
            getStats();
            break;

        case 'getRequestsChart':
            getRequestsChart();
            break;

        case 'getProvidersChart':
            getProvidersChart();
            break;

        case 'getRecentRequests':
            getRecentRequests();
            break;

        case 'getTopPrograms':
            getTopPrograms();
            break;

        case 'getProviderStatus':
            getProviderStatus();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Dashboard API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get dashboard statistics
 */
function getStats() {
    $conn = ai_getDBConnection();

    // Total requests last 30 days
    $sql = "SELECT COUNT(*) as total, SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'";
    $result = $conn->query($sql);
    $current = $result->fetch_assoc();

    // Previous 30 days for comparison
    $sql = "SELECT COUNT(*) as total, SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'";
    $result = $conn->query($sql);
    $previous = $result->fetch_assoc();

    // Active users
    $sql = "SELECT COUNT(DISTINCT user_id) as total
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $conn->query($sql);
    $users = $result->fetch_assoc();

    $sql = "SELECT COUNT(DISTINCT user_id) as total
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $conn->query($sql);
    $usersPrevious = $result->fetch_assoc();

    // Average response time
    $sql = "SELECT AVG(response_time_ms) as avg_time
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'";
    $result = $conn->query($sql);
    $avgTime = $result->fetch_assoc();

    $sql = "SELECT AVG(response_time_ms) as avg_time
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'";
    $result = $conn->query($sql);
    $avgTimePrevious = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalRequests' => [
                'value' => (int)$current['total'],
                'change' => calculatePercentChange($previous['total'], $current['total'])
            ],
            'totalCost' => [
                'value' => (float)$current['cost'],
                'change' => calculatePercentChange($previous['cost'], $current['cost'])
            ],
            'activeUsers' => [
                'value' => (int)$users['total'],
                'change' => calculatePercentChange($usersPrevious['total'], $users['total'])
            ],
            'avgResponseTime' => [
                'value' => (float)$avgTime['avg_time'],
                'change' => calculatePercentChange($avgTimePrevious['avg_time'], $avgTime['avg_time'])
            ]
        ]
    ]);
}

/**
 * Get requests chart data (daily for last 30 days)
 */
function getRequestsChart() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DATE(request_timestamp) as date, COUNT(*) as count
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'
            GROUP BY DATE(request_timestamp)
            ORDER BY date ASC";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'date' => $row['date'],
            'count' => (int)$row['count']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Get providers chart data (cost breakdown)
 */
function getProvidersChart() {
    $conn = ai_getDBConnection();

    $sql = "SELECT p.provider_name, SUM(ul.total_cost_usd) as cost, COUNT(*) as count
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            WHERE ul.request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND ul.status = 'success'
            GROUP BY p.provider_id
            ORDER BY cost DESC";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'provider' => $row['provider_name'],
            'cost' => (float)$row['cost'],
            'count' => (int)$row['count']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Get recent requests
 */
function getRecentRequests() {
    $conn = ai_getDBConnection();

    $sql = "SELECT ul.usage_id, ul.user_id, ul.program_id, ul.feature_code,
                   p.provider_name, m.model_display_name, ul.input_cost_usd, ul.output_cost_usd,
                   ul.status, ul.request_timestamp
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            ORDER BY ul.request_timestamp DESC
            LIMIT 20";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['usage_id'],
            'timestamp' => $row['request_timestamp'],
            'user_id' => $row['user_id'],
            'program' => $row['program_id'],
            'feature' => $row['feature_code'],
            'provider' => $row['provider_name'],
            'model' => $row['model_display_name'],
            'cost' => (float)($row['input_cost_usd'] + $row['output_cost_usd']),
            'status' => $row['status']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Get top programs by usage
 */
function getTopPrograms() {
    $conn = ai_getDBConnection();

    $sql = "SELECT program_id, COUNT(*) as count, SUM(total_cost_usd) as cost
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'
            GROUP BY program_id
            ORDER BY count DESC
            LIMIT 10";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'program' => $row['program_id'],
            'count' => (int)$row['count'],
            'cost' => (float)$row['cost']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Get provider status
 */
function getProviderStatus() {
    $conn = ai_getDBConnection();

    $sql = "SELECT p.provider_id, p.provider_name, p.provider_code, p.is_active,
                   COUNT(ul.usage_id) as requests_today,
                   SUM(ul.total_cost_usd) as cost_today
            FROM ai_providers p
            LEFT JOIN ai_usage_log ul ON p.provider_id = ul.provider_id AND DATE(ul.request_timestamp) = CURDATE()
            GROUP BY p.provider_id
            ORDER BY p.provider_name";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['provider_id'],
            'name' => $row['provider_name'],
            'code' => $row['provider_code'],
            'isActive' => (bool)$row['is_active'],
            'requestsToday' => (int)$row['requests_today'],
            'costToday' => (float)$row['cost_today']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Calculate percent change
 */
function calculatePercentChange($old, $new) {
    if ($old == 0) return $new > 0 ? 100 : 0;
    return round((($new - $old) / $old) * 100, 1);
}

?>
