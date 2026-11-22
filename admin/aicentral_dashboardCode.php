<?php
/**
 * AI Central Admin Dashboard - Backend API
 */

/** A model has a free-tier limit (ai_models.limit_* set). */
const PROVIDER_LIMITED_SQL = "(lm.limit_requests_day IS NOT NULL OR lm.limit_audio_sec_hour IS NOT NULL OR lm.limit_audio_sec_day IS NOT NULL OR lm.limit_tokens_day IS NOT NULL)";

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
    $sql = "SELECT COUNT(*) as total, " . aiCentral_costSplitSql() . "
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'" . aiCentral_paidOnlySql() . "";
    $result = $conn->query($sql);
    $current = $result->fetch_assoc();

    // Previous 30 days for comparison
    $sql = "SELECT COUNT(*) as total, " . aiCentral_costSplitSql() . "
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'" . aiCentral_paidOnlySql() . "";
    $result = $conn->query($sql);
    $previous = $result->fetch_assoc();

    // Active users
    $sql = "SELECT COUNT(DISTINCT user_id) as total
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . aiCentral_paidOnlySql() . "";
    $result = $conn->query($sql);
    $users = $result->fetch_assoc();

    $sql = "SELECT COUNT(DISTINCT user_id) as total
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)" . aiCentral_paidOnlySql() . "";
    $result = $conn->query($sql);
    $usersPrevious = $result->fetch_assoc();

    // Average response time
    $sql = "SELECT AVG(response_time_ms) as avg_time
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'" . aiCentral_paidOnlySql() . "";
    $result = $conn->query($sql);
    $avgTime = $result->fetch_assoc();

    $sql = "SELECT AVG(response_time_ms) as avg_time
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            AND request_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'" . aiCentral_paidOnlySql() . "";
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
                'value' => (float)$current['api_cost'],
                'change' => calculatePercentChange($previous['api_cost'], $current['api_cost'])
            ],
            'cliCost' => [
                'value' => (float)$current['cli_cost'],
                'requests' => (int)$current['cli_requests']
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
            AND status = 'success'" . aiCentral_paidOnlySql() . "
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
            AND ul.status = 'success'" . aiCentral_paidOnlySql('ul') . "
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
            WHERE 1=1" . aiCentral_paidOnlySql('ul') . "
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

    $sql = "SELECT program_id, COUNT(*) as count, " . aiCentral_costSplitSql() . "
            FROM ai_usage_log
            WHERE request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'success'" . aiCentral_paidOnlySql() . "
            GROUP BY program_id
            ORDER BY count DESC
            LIMIT 10";

    $result = $conn->query($sql);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'program' => $row['program_id'],
            'count' => (int)$row['count'],
            'apiCost' => (float)$row['api_cost'],
            'cliCost' => (float)$row['cli_cost'],
            'apiRequests' => (int)$row['api_requests'],
            'cliRequests' => (int)$row['cli_requests']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Get provider status
 */
function getProviderStatus() {
    $conn = ai_getDBConnection();

    // Paid only leaves out free providers, except ones with free-tier limits (their use is shown against the limits)
    $paidOrLimited = (($_REQUEST['paid_only'] ?? '') === '1')
        ? " AND (EXISTS (SELECT 1 FROM ai_models pm WHERE pm.provider_id = p.provider_id AND (pm.input_cost_per_million > 0 OR pm.output_cost_per_million > 0))
                 OR EXISTS (SELECT 1 FROM ai_models lm WHERE lm.provider_id = p.provider_id AND " . PROVIDER_LIMITED_SQL . "))"
        : '';
    $sql = "SELECT p.provider_id, p.provider_name, p.provider_code, p.is_active,
                   COUNT(ul.usage_id) as requests_today,
                   SUM(ul.total_cost_usd) as cost_today
            FROM ai_providers p
            LEFT JOIN ai_usage_log ul ON p.provider_id = ul.provider_id AND DATE(ul.request_timestamp) = CURDATE()" . aiCentral_paidOnlySql('ul') . "
            WHERE 1=1" . $paidOrLimited . "
            GROUP BY p.provider_id
            ORDER BY p.provider_name";

    $result = $conn->query($sql);
    $limits = getModelLimitUse($conn);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['provider_id'],
            'name' => $row['provider_name'],
            'code' => $row['provider_code'],
            'isActive' => (bool)$row['is_active'],
            'requestsToday' => (int)$row['requests_today'],
            'costToday' => (float)$row['cost_today'],
            'limits' => $limits[(int)$row['provider_id']] ?? []
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);
}

/**
 * Use of every model with free-tier limits that a feature is set to or that had requests in the last 24 hours, per provider: requests and tokens in the last 24 hours, audio seconds in the
 * last hour and 24 hours (each recording counted at least audio_min_sec, as the provider counts it). Every request
 * through AI Central counts, paid only or not; requests made with the key outside AI Central are not seen.
 * @return array provider_id => [ ['model', 'requestsDay', 'limitRequestsDay', 'audioSecHour', 'limitAudioSecHour', ...], ... ]
 */
function getModelLimitUse($conn) {
    $sql = "SELECT lm.provider_id, lm.model_display_name, lm.limit_requests_day, lm.limit_audio_sec_hour, lm.limit_audio_sec_day,
                   lm.limit_tokens_day, COALESCE(lm.audio_min_sec, 0) AS min_sec,
                   COUNT(ul.usage_id) AS requests_day,
                   COALESCE(SUM(ul.total_tokens), 0) AS tokens_day,
                   COALESCE(SUM(CASE WHEN ul.usage_id IS NOT NULL
                       THEN GREATEST(COALESCE(lm.audio_min_sec, 0), COALESCE(JSON_EXTRACT(ul.request_metadata, '$.seconds'), 0)) END), 0) AS audio_day,
                   COALESCE(SUM(CASE WHEN ul.request_timestamp >= NOW() - INTERVAL 1 HOUR
                       THEN GREATEST(COALESCE(lm.audio_min_sec, 0), COALESCE(JSON_EXTRACT(ul.request_metadata, '$.seconds'), 0)) END), 0) AS audio_hour
              FROM ai_models lm
              LEFT JOIN ai_usage_log ul ON ul.model_id = lm.model_id AND ul.request_timestamp >= NOW() - INTERVAL 24 HOUR
             WHERE " . PROVIDER_LIMITED_SQL . "
             GROUP BY lm.model_id
            HAVING requests_day > 0
                OR EXISTS (SELECT 1 FROM ai_features f JOIN ai_providers fp ON fp.provider_code = f.default_provider
                            WHERE f.is_active = 1 AND f.default_model = lm.model_code AND fp.provider_id = lm.provider_id)
             ORDER BY lm.model_display_name";
    $result = $conn->query($sql);
    $out = [];
    while ($result && $row = $result->fetch_assoc()) {
        $out[(int)$row['provider_id']][] = [
            'model' => $row['model_display_name'],
            'requestsDay' => (int)$row['requests_day'],
            'limitRequestsDay' => $row['limit_requests_day'] !== null ? (int)$row['limit_requests_day'] : null,
            'tokensDay' => (int)$row['tokens_day'],
            'limitTokensDay' => $row['limit_tokens_day'] !== null ? (int)$row['limit_tokens_day'] : null,
            'audioSecHour' => (float)$row['audio_hour'],
            'limitAudioSecHour' => $row['limit_audio_sec_hour'] !== null ? (int)$row['limit_audio_sec_hour'] : null,
            'audioSecDay' => (float)$row['audio_day'],
            'limitAudioSecDay' => $row['limit_audio_sec_day'] !== null ? (int)$row['limit_audio_sec_day'] : null,
        ];
    }
    return $out;
}

/**
 * Calculate percent change
 */
function calculatePercentChange($old, $new) {
    if ($old == 0) return $new > 0 ? 100 : 0;
    return round((($new - $old) / $old) * 100, 1);
}

?>
