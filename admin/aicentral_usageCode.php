<?php
/**
 * AI Central Admin - Usage Analysis Backend
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

// SECURITY: Require ADMIN-level authentication for admin functions
$user = auth_getAuthenticatedUser('AI');
if (!$user || !in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Usage Analysis API: action=$action", 'INFO');

// Handle CSV export separately (needs different headers)
if ($action === 'exportCSV') {
    exportCSV();
    exit;
}

// JSON response for all other actions
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'getUsers':
            getUsers();
            break;

        case 'getProviders':
            getProviders();
            break;

        case 'getModels':
            getModels();
            break;

        case 'getFeatures':
            getFeatures();
            break;

        case 'getSummary':
            getSummary();
            break;

        case 'getUsageOverTime':
            getUsageOverTime();
            break;

        case 'getCostByProvider':
            getCostByProvider();
            break;

        case 'getTopUsers':
            getTopUsers();
            break;

        case 'getTopPrograms':
            getTopPrograms();
            break;

        case 'getUsageData':
            getUsageData();
            break;

        case 'getUsageDetail':
            getUsageDetail();
            break;

        case 'getByFeatureData':
            getByFeatureData();
            break;

        case 'getByUserData':
            getByUserData();
            break;

        case 'getByModelData':
            getByModelData();
            break;

        case 'getByProgramData':
            getByProgramData();
            break;

        case 'getOutliersData':
            getOutliersData();
            break;

        case 'getTrendsData':
            getTrendsData();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Usage Analysis API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get list of users who have used AI
 */
function getUsers() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT u.user_id, u.name as user_name
            FROM ai_usage_log ul
            LEFT JOIN auth_db.master_users u ON ul.user_id = u.user_id
            ORDER BY u.name";

    $result = $conn->query($sql);
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get list of providers
 */
function getProviders() {
    $conn = ai_getDBConnection();

    $sql = "SELECT provider_id, provider_name
            FROM ai_providers
            WHERE is_active = 1
            ORDER BY provider_name";

    $result = $conn->query($sql);
    $providers = [];

    while ($row = $result->fetch_assoc()) {
        $providers[] = $row;
    }

    echo json_encode(['success' => true, 'providers' => $providers]);
}

/**
 * Get list of models
 */
function getModels() {
    $conn = ai_getDBConnection();

    $sql = "SELECT model_id, model_display_name
            FROM ai_models
            WHERE is_active = 1
            ORDER BY model_display_name";

    $result = $conn->query($sql);
    $models = [];

    while ($row = $result->fetch_assoc()) {
        $models[] = $row;
    }

    echo json_encode(['success' => true, 'models' => $models]);
}

/**
 * Get list of features
 */
function getFeatures() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT feature_code, feature_name, program_id
            FROM ai_features
            WHERE is_active = 1
            ORDER BY program_id, feature_name";

    $result = $conn->query($sql);
    $features = [];

    while ($row = $result->fetch_assoc()) {
        $features[] = $row;
    }

    echo json_encode(['success' => true, 'features' => $features]);
}

/**
 * Get summary statistics
 */
function getSummary() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                COUNT(*) as totalRequests,
                COALESCE(SUM(total_cost_usd), 0) as totalCost,
                COALESCE(SUM(total_tokens), 0) as totalTokens,
                COALESCE(AVG(response_time_ms), 0) as avgResponse
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();

    echo json_encode(['success' => true, 'summary' => $summary]);
}

/**
 * Get usage over time data
 */
function getUsageOverTime() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    // Determine date grouping based on date range
    $dateRange = $_REQUEST['dateRange'] ?? 'last7days';
    $dateFormat = '%Y-%m-%d';
    $dateLabel = 'DATE(request_timestamp)';

    if ($dateRange === 'today' || $dateRange === 'yesterday') {
        $dateFormat = '%H:00';
        $dateLabel = 'DATE_FORMAT(request_timestamp, "%H:00")';
    }

    $sql = "SELECT
                $dateLabel as date_label,
                COUNT(*) as requests
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY date_label
            ORDER BY date_label";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['date_label'];
        $requests[] = (int)$row['requests'];
    }

    echo json_encode([
        'success' => true,
        'chartData' => [
            'labels' => $labels,
            'requests' => $requests
        ]
    ]);
}

/**
 * Get cost by provider data
 */
function getCostByProvider() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                p.provider_name,
                COALESCE(SUM(ul.total_cost_usd), 0) as total_cost
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY p.provider_name
            ORDER BY total_cost DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $costs = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['provider_name'];
        $costs[] = (float)$row['total_cost'];
    }

    echo json_encode([
        'success' => true,
        'chartData' => [
            'labels' => $labels,
            'costs' => $costs
        ]
    ]);
}

/**
 * Get top users data
 */
function getTopUsers() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.user_id,
                COALESCE(u.user_name, ul.user_id) as user_name,
                COUNT(*) as requests
            FROM ai_usage_log ul
            LEFT JOIN auth_db.master_users u ON ul.user_id = u.user_id
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.user_id, user_name
            ORDER BY requests DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['user_name'];
        $requests[] = (int)$row['requests'];
    }

    echo json_encode([
        'success' => true,
        'chartData' => [
            'labels' => $labels,
            'requests' => $requests
        ]
    ]);
}

/**
 * Get top programs data
 */
function getTopPrograms() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.program_id,
                COUNT(*) as requests
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.program_id
            ORDER BY requests DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['program_id'];
        $requests[] = (int)$row['requests'];
    }

    echo json_encode([
        'success' => true,
        'chartData' => [
            'labels' => $labels,
            'requests' => $requests
        ]
    ]);
}

/**
 * Get usage data with pagination
 */
function getUsageData() {
    $conn = ai_getDBConnection();

    $page = (int)($_REQUEST['page'] ?? 1);
    $pageSize = (int)($_REQUEST['pageSize'] ?? 50);
    $offset = ($page - 1) * $pageSize;

    list($whereClause, $params, $types) = buildWhereClause();

    // Get total count
    $countSql = "SELECT COUNT(*) as total
                 FROM ai_usage_log ul
                 JOIN ai_providers p ON ul.provider_id = p.provider_id
                 JOIN ai_models m ON ul.model_id = m.model_id
                 $whereClause";

    $stmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRecords = $result->fetch_assoc()['total'];

    // Get paginated data
    $dataSql = "SELECT
                    ul.usage_id,
                    ul.user_id,
                    ul.program_id,
                    ul.feature_code,
                    ul.request_timestamp,
                    ul.total_tokens,
                    ul.tool_call_count,
                    ul.total_cost_usd,
                    ul.response_time_ms,
                    ul.status,
                    p.provider_name,
                    m.model_display_name
                FROM ai_usage_log ul
                JOIN ai_providers p ON ul.provider_id = p.provider_id
                JOIN ai_models m ON ul.model_id = m.model_id
                $whereClause
                ORDER BY ul.request_timestamp DESC
                LIMIT ? OFFSET ?";

    // Add pagination params
    $paginationParams = $params;
    $paginationParams[] = $pageSize;
    $paginationParams[] = $offset;
    $paginationTypes = $types . 'ii';

    $stmt = $conn->prepare($dataSql);
    if (!empty($paginationParams)) {
        $stmt->bind_param($paginationTypes, ...$paginationParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'totalRecords' => (int)$totalRecords,
        'records' => $records
    ]);
}

/**
 * Get detailed usage information
 */
function getUsageDetail() {
    $conn = ai_getDBConnection();

    $usageId = $_REQUEST['usageId'] ?? 0;

    $sql = "SELECT
                ul.*,
                p.provider_name,
                m.model_display_name,
                ul.complete_ai_response
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            WHERE ul.usage_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $usageId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'record' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Record not found']);
    }
}

/**
 * Export data to CSV
 */
function exportCSV() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.usage_id,
                ul.request_timestamp,
                ul.user_id,
                ul.program_id,
                ul.feature_code,
                p.provider_name,
                m.model_display_name,
                ul.input_tokens,
                ul.output_tokens,
                ul.total_tokens,
                ul.input_cost_usd,
                ul.output_cost_usd,
                ul.total_cost_usd,
                ul.response_time_ms,
                ul.status,
                ul.key_type
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            ORDER BY ul.request_timestamp DESC
            LIMIT 10000";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ai_usage_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, [
        'Usage ID',
        'Timestamp',
        'User ID',
        'Program',
        'Feature',
        'Provider',
        'Model',
        'Input Tokens',
        'Output Tokens',
        'Total Tokens',
        'Input Cost',
        'Output Cost',
        'Total Cost',
        'Response Time (ms)',
        'Status',
        'Key Type'
    ]);

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['usage_id'],
            $row['request_timestamp'],
            $row['user_id'],
            $row['program_id'],
            $row['feature_code'],
            $row['provider_name'],
            $row['model_display_name'],
            $row['input_tokens'],
            $row['output_tokens'],
            $row['total_tokens'],
            $row['input_cost_usd'],
            $row['output_cost_usd'],
            $row['total_cost_usd'],
            $row['response_time_ms'],
            $row['status'],
            $row['key_type']
        ]);
    }

    fclose($output);
}

/**
 * Build WHERE clause based on filters
 */
function buildWhereClause() {
    $conditions = [];
    $params = [];
    $types = '';

    // Date range filter
    $dateRange = $_REQUEST['dateRange'] ?? '';
    $dateFrom = $_REQUEST['dateFrom'] ?? '';
    $dateTo = $_REQUEST['dateTo'] ?? '';

    if ($dateRange === 'custom' && $dateFrom && $dateTo) {
        $conditions[] = "DATE(ul.request_timestamp) BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
        $types .= 'ss';
    } else {
        switch ($dateRange) {
            case 'today':
                $conditions[] = "DATE(ul.request_timestamp) = CURDATE()";
                break;
            case 'yesterday':
                $conditions[] = "DATE(ul.request_timestamp) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'last7days':
                $conditions[] = "ul.request_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'last30days':
                $conditions[] = "ul.request_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'thismonth':
                $conditions[] = "YEAR(ul.request_timestamp) = YEAR(CURDATE()) AND MONTH(ul.request_timestamp) = MONTH(CURDATE())";
                break;
            case 'lastmonth':
                $conditions[] = "YEAR(ul.request_timestamp) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(ul.request_timestamp) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                break;
        }
    }

    // User filter
    $user = $_REQUEST['user'] ?? '';
    if ($user) {
        $conditions[] = "ul.user_id = ?";
        $params[] = $user;
        $types .= 's';
    }

    // Program filter
    $program = $_REQUEST['program'] ?? '';
    if ($program) {
        $conditions[] = "ul.program_id = ?";
        $params[] = $program;
        $types .= 's';
    }

    // Provider filter
    $provider = $_REQUEST['provider'] ?? '';
    if ($provider) {
        $conditions[] = "ul.provider_id = ?";
        $params[] = (int)$provider;
        $types .= 'i';
    }

    // Model filter
    $model = $_REQUEST['model'] ?? '';
    if ($model) {
        $conditions[] = "ul.model_id = ?";
        $params[] = (int)$model;
        $types .= 'i';
    }

    // Feature filter
    $feature = $_REQUEST['feature'] ?? '';
    if ($feature) {
        $conditions[] = "ul.feature_code = ?";
        $params[] = $feature;
        $types .= 's';
    }

    // Status filter
    $status = $_REQUEST['status'] ?? '';
    if ($status) {
        $conditions[] = "ul.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    // Cost range filter
    $cost = $_REQUEST['cost'] ?? '';
    if ($cost) {
        $costParts = explode('-', $cost);
        if (count($costParts) === 2) {
            $minCost = (float)$costParts[0];
            $maxCost = (float)$costParts[1];
            $conditions[] = "ul.total_cost_usd BETWEEN ? AND ?";
            $params[] = $minCost;
            $params[] = $maxCost;
            $types .= 'dd';
        }
    }

    // Token range filter
    $tokens = $_REQUEST['tokens'] ?? '';
    if ($tokens) {
        $tokenParts = explode('-', $tokens);
        if (count($tokenParts) === 2) {
            $minTokens = (int)$tokenParts[0];
            $maxTokens = (int)$tokenParts[1];
            $conditions[] = "ul.total_tokens BETWEEN ? AND ?";
            $params[] = $minTokens;
            $params[] = $maxTokens;
            $types .= 'ii';
        }
    }

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    return [$whereClause, $params, $types];
}

/**
 * Get aggregated data by feature
 */
function getByFeatureData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.feature_code,
                f.feature_name,
                ul.program_id,
                COUNT(*) as total_requests,
                AVG(ul.total_tokens) as avg_tokens,
                AVG(ul.total_cost_usd) as avg_cost,
                AVG(ul.response_time_ms) as avg_response,
                SUM(ul.total_cost_usd) as total_cost
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            LEFT JOIN ai_features f ON ul.feature_code = f.feature_code
            $whereClause
            GROUP BY ul.feature_code, f.feature_name, ul.program_id
            ORDER BY total_requests DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'records' => $records
    ]);
}

/**
 * Get aggregated data by user
 */
function getByUserData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.user_id,
                COUNT(*) as total_requests,
                AVG(ul.total_tokens) as avg_tokens,
                AVG(ul.total_cost_usd) as avg_cost,
                AVG(ul.response_time_ms) as avg_response,
                SUM(ul.total_cost_usd) as total_cost,
                SUM(ul.total_tokens) as total_tokens
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.user_id
            ORDER BY total_requests DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        // Get user name from separate query to avoid GROUP BY issues
        $userStmt = $conn->prepare("SELECT user_name FROM auth_db.master_users WHERE user_id = ?");
        $userStmt->bind_param('s', $row['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult->fetch_assoc();

        $row['user_name'] = $userRow['user_name'] ?? $row['user_id'];
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'records' => $records
    ]);
}

/**
 * Get aggregated data by model
 */
function getByModelData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                m.model_id,
                m.model_display_name,
                p.provider_name,
                COUNT(*) as total_requests,
                AVG(ul.total_tokens) as avg_tokens,
                AVG(ul.total_cost_usd) as avg_cost,
                AVG(ul.response_time_ms) as avg_response,
                SUM(ul.total_cost_usd) as total_cost
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY m.model_id, m.model_display_name, p.provider_name
            ORDER BY total_requests DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'records' => $records
    ]);
}

/**
 * Get aggregated data by program
 */
function getByProgramData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $sql = "SELECT
                ul.program_id,
                COUNT(*) as total_requests,
                AVG(ul.total_tokens) as avg_tokens,
                AVG(ul.total_cost_usd) as avg_cost,
                AVG(ul.response_time_ms) as avg_response,
                SUM(ul.total_cost_usd) as total_cost,
                SUM(ul.total_tokens) as total_tokens
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.program_id
            ORDER BY total_requests DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }

    echo json_encode([
        'success' => true,
        'records' => $records
    ]);
}

/**
 * Get outliers data
 */
function getOutliersData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    $data = [];

    // High Token Users (by average)
    $sql = "SELECT
                ul.user_id,
                AVG(ul.total_tokens) as avg_tokens,
                COUNT(*) as total_requests
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.user_id
            HAVING COUNT(*) >= 3
            ORDER BY avg_tokens DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['highTokenUsers'] = [];
    while ($row = $result->fetch_assoc()) {
        // Get user name
        $userStmt = $conn->prepare("SELECT user_name FROM auth_db.master_users WHERE user_id = ?");
        $userStmt->bind_param('s', $row['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult->fetch_assoc();
        $row['user_name'] = $userRow['user_name'] ?? $row['user_id'];

        $data['highTokenUsers'][] = $row;
    }

    // High Cost Users (by average)
    $sql = "SELECT
                ul.user_id,
                AVG(ul.total_cost_usd) as avg_cost,
                COUNT(*) as total_requests
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY ul.user_id
            HAVING COUNT(*) >= 3
            ORDER BY avg_cost DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['highCostUsers'] = [];
    while ($row = $result->fetch_assoc()) {
        // Get user name
        $userStmt = $conn->prepare("SELECT user_name FROM auth_db.master_users WHERE user_id = ?");
        $userStmt->bind_param('s', $row['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult->fetch_assoc();
        $row['user_name'] = $userRow['user_name'] ?? $row['user_id'];

        $data['highCostUsers'][] = $row;
    }

    // Slow Response Users (by average)
    $whereClauseWithResponse = $whereClause;
    if (empty($whereClause)) {
        $whereClauseWithResponse = "WHERE ul.response_time_ms IS NOT NULL";
    } else {
        $whereClauseWithResponse .= " AND ul.response_time_ms IS NOT NULL";
    }

    $sql = "SELECT
                ul.user_id,
                AVG(ul.response_time_ms) as avg_response,
                COUNT(*) as total_requests
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClauseWithResponse
            GROUP BY ul.user_id
            HAVING COUNT(*) >= 3
            ORDER BY avg_response DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['slowUsers'] = [];
    while ($row = $result->fetch_assoc()) {
        // Get user name
        $userStmt = $conn->prepare("SELECT user_name FROM auth_db.master_users WHERE user_id = ?");
        $userStmt->bind_param('s', $row['user_id']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult->fetch_assoc();
        $row['user_name'] = $userRow['user_name'] ?? $row['user_id'];

        $data['slowUsers'][] = $row;
    }

    // High Token Individual Requests
    $sql = "SELECT
                ul.user_id,
                ul.feature_code,
                ul.total_tokens
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            ORDER BY ul.total_tokens DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['highTokenRequests'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['highTokenRequests'][] = $row;
    }

    // Most Expensive Individual Requests
    $sql = "SELECT
                ul.user_id,
                ul.feature_code,
                ul.total_cost_usd
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            ORDER BY ul.total_cost_usd DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['expensiveRequests'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['expensiveRequests'][] = $row;
    }

    // Slowest Individual Requests
    $sql = "SELECT
                ul.user_id,
                ul.feature_code,
                ul.response_time_ms
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause AND ul.response_time_ms IS NOT NULL
            ORDER BY ul.response_time_ms DESC
            LIMIT 10";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data['slowRequests'] = [];
    while ($row = $result->fetch_assoc()) {
        $data['slowRequests'][] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
}

/**
 * Get trends data over time
 */
function getTrendsData() {
    $conn = ai_getDBConnection();

    list($whereClause, $params, $types) = buildWhereClause();

    // Determine date grouping based on date range
    $dateRange = $_REQUEST['dateRange'] ?? 'last7days';
    $dateLabel = 'DATE(request_timestamp)';

    if ($dateRange === 'today' || $dateRange === 'yesterday') {
        $dateLabel = 'DATE_FORMAT(request_timestamp, "%H:00")';
    }

    $data = [];

    // Average Tokens Trend
    $sql = "SELECT
                $dateLabel as date_label,
                AVG(ul.total_tokens) as avg_tokens
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY date_label
            ORDER BY date_label";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $avgTokens = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['date_label'];
        $avgTokens[] = (float)$row['avg_tokens'];
    }
    $data['avgTokens'] = ['labels' => $labels, 'values' => $avgTokens];

    // Average Cost Trend
    $sql = "SELECT
                $dateLabel as date_label,
                AVG(ul.total_cost_usd) as avg_cost
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY date_label
            ORDER BY date_label";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $avgCost = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['date_label'];
        $avgCost[] = (float)$row['avg_cost'];
    }
    $data['avgCost'] = ['labels' => $labels, 'values' => $avgCost];

    // Average Response Time Trend
    $whereClauseWithResponse = $whereClause;
    if (empty($whereClause)) {
        $whereClauseWithResponse = "WHERE ul.response_time_ms IS NOT NULL";
    } else {
        $whereClauseWithResponse .= " AND ul.response_time_ms IS NOT NULL";
    }

    $sql = "SELECT
                $dateLabel as date_label,
                AVG(ul.response_time_ms) as avg_response
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClauseWithResponse
            GROUP BY date_label
            ORDER BY date_label";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $avgResponse = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['date_label'];
        $avgResponse[] = (float)$row['avg_response'];
    }
    $data['avgResponse'] = ['labels' => $labels, 'values' => $avgResponse];

    // Request Volume Trend
    $sql = "SELECT
                $dateLabel as date_label,
                COUNT(*) as request_count
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            $whereClause
            GROUP BY date_label
            ORDER BY date_label";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $labels = [];
    $requestVolume = [];
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['date_label'];
        $requestVolume[] = (int)$row['request_count'];
    }
    $data['requestVolume'] = ['labels' => $labels, 'values' => $requestVolume];

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
}
