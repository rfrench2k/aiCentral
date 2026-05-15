<?php
/**
 * AI Central Admin - Model Capabilities Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

header('Content-Type: application/json');

// SECURITY: Require ADMIN-level authentication
$user = auth_getAuthenticatedUser('AI');
if (!$user || !in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Model Capabilities API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getCapabilities':
            getCapabilities();
            break;

        case 'getCapability':
            getCapability();
            break;

        case 'saveCapability':
            saveCapability();
            break;

        case 'getProviders':
            getProviders();
            break;

        case 'getModels':
            getModels();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Model Capabilities API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all model capabilities
 */
function getCapabilities() {
    $conn = ai_getDBConnection();

    $sql = "SELECT mc.*, m.model_display_name, m.model_code, p.provider_name, p.provider_code
            FROM ai_model_capabilities mc
            JOIN ai_models m ON mc.model_id = m.model_id
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.is_active = 1
            ORDER BY p.provider_name, m.model_display_name, mc.capability_code";

    $result = $conn->query($sql);

    $capabilities = [];
    while ($row = $result->fetch_assoc()) {
        $capabilities[] = [
            'capability_id' => (int)$row['capability_id'],
            'model_id' => (int)$row['model_id'],
            'model_display_name' => $row['model_display_name'],
            'model_code' => $row['model_code'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code'],
            'capability_code' => $row['capability_code'],
            'capability_name' => $row['capability_name'],
            'cost_per_use' => $row['cost_per_use'] ? (float)$row['cost_per_use'] : null,
            'cost_per_1000' => $row['cost_per_1000'] ? (float)$row['cost_per_1000'] : null,
            'includes_result_tokens' => (bool)$row['includes_result_tokens'],
            'is_free' => (bool)$row['is_free'],
            'provider_tool_name' => $row['provider_tool_name'],
            'api_format_notes' => $row['api_format_notes'],
            'max_uses_default' => $row['max_uses_default'] ? (int)$row['max_uses_default'] : null,
            'is_supported' => (bool)$row['is_supported']
        ];
    }

    echo json_encode(['success' => true, 'capabilities' => $capabilities]);
}

/**
 * Get single capability
 */
function getCapability() {
    $conn = ai_getDBConnection();

    $capabilityId = $_REQUEST['capability_id'] ?? 0;

    if (!$capabilityId) {
        echo json_encode(['success' => false, 'error' => 'Capability ID required']);
        return;
    }

    $sql = "SELECT mc.*, m.model_display_name, m.model_code, p.provider_name
            FROM ai_model_capabilities mc
            JOIN ai_models m ON mc.model_id = m.model_id
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE mc.capability_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $capabilityId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Capability not found']);
        $stmt->close();
        return;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $capability = [
        'capability_id' => (int)$row['capability_id'],
        'model_id' => (int)$row['model_id'],
        'model_display_name' => $row['model_display_name'],
        'model_code' => $row['model_code'],
        'provider_name' => $row['provider_name'],
        'capability_code' => $row['capability_code'],
        'capability_name' => $row['capability_name'],
        'cost_per_use' => $row['cost_per_use'] ? (float)$row['cost_per_use'] : null,
        'cost_per_1000' => $row['cost_per_1000'] ? (float)$row['cost_per_1000'] : null,
        'includes_result_tokens' => (bool)$row['includes_result_tokens'],
        'is_free' => (bool)$row['is_free'],
        'provider_tool_name' => $row['provider_tool_name'],
        'api_format_notes' => $row['api_format_notes'],
        'max_uses_default' => $row['max_uses_default'] ? (int)$row['max_uses_default'] : null,
        'is_supported' => (bool)$row['is_supported']
    ];

    echo json_encode(['success' => true, 'capability' => $capability]);
}

/**
 * Save capability configuration
 */
function saveCapability() {
    $conn = ai_getDBConnection();

    $capabilityId = $_POST['capability_id'] ?? 0;
    $isSupported = isset($_POST['is_supported']) ? 1 : 0;
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $costPerUse = isset($_POST['cost_per_use']) && $_POST['cost_per_use'] !== '' ? (float)$_POST['cost_per_use'] : NULL;
    $costPer1000 = isset($_POST['cost_per_1000']) && $_POST['cost_per_1000'] !== '' ? (float)$_POST['cost_per_1000'] : NULL;
    $includesResultTokens = isset($_POST['includes_result_tokens']) ? 1 : 0;
    $maxUsesDefault = isset($_POST['max_uses_default']) && $_POST['max_uses_default'] !== '' ? (int)$_POST['max_uses_default'] : NULL;
    $providerToolName = trim($_POST['provider_tool_name'] ?? '');
    $apiFormatNotes = trim($_POST['api_format_notes'] ?? '');

    if (!$capabilityId) {
        echo json_encode(['success' => false, 'error' => 'Capability ID required']);
        return;
    }

    // Validation
    if ($costPerUse !== NULL && $costPerUse < 0) {
        echo json_encode(['success' => false, 'error' => 'Cost per use must be positive']);
        return;
    }

    if ($costPer1000 !== NULL && $costPer1000 < 0) {
        echo json_encode(['success' => false, 'error' => 'Cost per 1000 must be positive']);
        return;
    }

    if ($maxUsesDefault !== NULL && ($maxUsesDefault < 1 || $maxUsesDefault > 1000)) {
        echo json_encode(['success' => false, 'error' => 'Max uses default must be between 1 and 1000']);
        return;
    }

    // Convert empty strings to NULL for database
    $providerToolNameValue = empty($providerToolName) ? NULL : $providerToolName;
    $apiFormatNotesValue = empty($apiFormatNotes) ? NULL : $apiFormatNotes;

    $sql = "UPDATE ai_model_capabilities SET
            is_supported = ?,
            is_free = ?,
            cost_per_use = ?,
            cost_per_1000 = ?,
            includes_result_tokens = ?,
            max_uses_default = ?,
            provider_tool_name = ?,
            api_format_notes = ?,
            updated_at = NOW()
            WHERE capability_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiddiissi',
        $isSupported, $isFree, $costPerUse, $costPer1000,
        $includesResultTokens, $maxUsesDefault,
        $providerToolNameValue, $apiFormatNotesValue,
        $capabilityId
    );

    if ($stmt->execute()) {
        aiCentral_logMessage("Capability updated: ID=$capabilityId", 'INFO');
        echo json_encode(['success' => true, 'capability_id' => $capabilityId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Get providers for filter
 */
function getProviders() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT p.provider_id, p.provider_name, p.provider_code
            FROM ai_providers p
            JOIN ai_models m ON p.provider_id = m.provider_id
            WHERE m.is_active = 1
            ORDER BY p.provider_name";

    $result = $conn->query($sql);

    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = [
            'provider_id' => (int)$row['provider_id'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code']
        ];
    }

    echo json_encode(['success' => true, 'providers' => $providers]);
}

/**
 * Get models for filter
 */
function getModels() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT m.model_id, m.model_code, m.model_display_name, p.provider_name
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.is_active = 1
            ORDER BY p.provider_name, m.model_display_name";

    $result = $conn->query($sql);

    $models = [];
    while ($row = $result->fetch_assoc()) {
        $models[] = [
            'model_id' => (int)$row['model_id'],
            'model_code' => $row['model_code'],
            'model_display_name' => $row['model_display_name'],
            'provider_name' => $row['provider_name']
        ];
    }

    echo json_encode(['success' => true, 'models' => $models]);
}

?>
