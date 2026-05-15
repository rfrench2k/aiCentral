<?php
/**
 * AI Central Admin Models - Backend API
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

aiCentral_logMessage("Models API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getModels':
            getModels();
            break;

        case 'getProviders':
            getProviders();
            break;

        case 'saveModel':
            saveModel();
            break;

        case 'deleteModel':
            deleteModel();
            break;

        case 'toggleModelStatus':
            toggleModelStatus();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Models API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all models
 */
function getModels() {
    $conn = ai_getDBConnection();

    $sql = "SELECT m.*, p.provider_name, p.provider_code
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            ORDER BY p.provider_name, m.sort_order, m.model_display_name";

    $result = $conn->query($sql);

    $models = [];
    while ($row = $result->fetch_assoc()) {
        $models[] = [
            'model_id' => (int)$row['model_id'],
            'provider_id' => (int)$row['provider_id'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code'],
            'model_code' => $row['model_code'],
            'model_display_name' => $row['model_display_name'],
            'model_tier' => $row['model_tier'],
            'is_active' => (bool)$row['is_active'],
            'sort_order' => (int)$row['sort_order'],
            'max_tokens' => (int)$row['max_tokens'],
            'context_window' => (int)$row['context_window'],
            'supports_vision' => (bool)$row['supports_vision'],
            'supports_function_calling' => (bool)$row['supports_function_calling'],
            'supports_streaming' => (bool)$row['supports_streaming'],
            'input_cost_per_million' => (float)$row['input_cost_per_million'],
            'output_cost_per_million' => (float)$row['output_cost_per_million'],
            'thinking_cost_per_million' => $row['thinking_cost_per_million'] ? (float)$row['thinking_cost_per_million'] : null,
            'pricing_effective_date' => $row['pricing_effective_date'],
            'pricing_end_date' => $row['pricing_end_date'],
            'notes' => $row['notes']
        ];
    }

    echo json_encode(['success' => true, 'models' => $models]);
}

/**
 * Get providers list
 */
function getProviders() {
    $conn = ai_getDBConnection();

    $sql = "SELECT provider_id, provider_name, provider_code, is_active
            FROM ai_providers
            ORDER BY provider_name";

    $result = $conn->query($sql);

    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = [
            'provider_id' => $row['provider_id'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code'],
            'is_active' => (bool)$row['is_active']
        ];
    }

    echo json_encode(['success' => true, 'providers' => $providers]);
}

/**
 * Save model (add or update)
 */
function saveModel() {
    $conn = ai_getDBConnection();

    // Debug: Log what we received
    aiCentral_logMessage("saveModel POST data: " . json_encode($_POST), 'DEBUG');

    $modelId = $_POST['model_id'] ?? null;
    $providerId = $_POST['provider_id'] ?? '';
    $modelCode = trim($_POST['model_code'] ?? '');
    $modelName = trim($_POST['model_display_name'] ?? '');
    $modelTier = $_POST['model_tier'] ?? 'standard';
    $sortOrder = (int)($_POST['sort_order'] ?? 100);
    $maxTokens = (int)($_POST['max_tokens'] ?? 4096);
    $contextWindow = (int)($_POST['context_window'] ?? 200000);
    $supportsVision = isset($_POST['supports_vision']) ? 1 : 0;
    $supportsFunctions = isset($_POST['supports_function_calling']) ? 1 : 0;
    $supportsStreaming = isset($_POST['supports_streaming']) ? 1 : 0;
    $inputCost = (float)($_POST['input_cost_per_million'] ?? 0);
    $outputCost = (float)($_POST['output_cost_per_million'] ?? 0);
    $thinkingCost = isset($_POST['thinking_cost_per_million']) && $_POST['thinking_cost_per_million'] !== '' ? (float)$_POST['thinking_cost_per_million'] : NULL;
    $effectiveDate = $_POST['pricing_effective_date'] ?? date('Y-m-d');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    // Debug: Log the effective date specifically
    aiCentral_logMessage("Effective date received: '" . $effectiveDate . "' (length: " . strlen($effectiveDate) . ")", 'DEBUG');

    // Validation
    if (empty($providerId) || empty($modelCode) || empty($modelName)) {
        echo json_encode(['success' => false, 'error' => 'Provider, model code, and display name are required']);
        return;
    }

    if ($inputCost < 0 || $outputCost < 0) {
        echo json_encode(['success' => false, 'error' => 'Costs must be positive']);
        return;
    }

    // Validate date format (must be YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveDate)) {
        echo json_encode(['success' => false, 'error' => 'Invalid date format. Expected YYYY-MM-DD, got: ' . $effectiveDate]);
        return;
    }

    // Use prepared statements for security
    if ($modelId) {
        // Update existing model
        $sql = "UPDATE ai_models SET
                provider_id = ?,
                model_code = ?,
                model_display_name = ?,
                model_tier = ?,
                sort_order = ?,
                max_tokens = ?,
                context_window = ?,
                supports_vision = ?,
                supports_function_calling = ?,
                supports_streaming = ?,
                input_cost_per_million = ?,
                output_cost_per_million = ?,
                thinking_cost_per_million = ?,
                pricing_effective_date = ?,
                is_active = ?,
                notes = ?,
                updated_at = NOW()
                WHERE model_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            aiCentral_logMessage("SQL Prepare Error: " . $conn->error, 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Database error']);
            return;
        }

        $stmt->bind_param('isssiiiiiiiddsisi',
            $providerId,            // i
            $modelCode,             // s
            $modelName,             // s
            $modelTier,             // s
            $sortOrder,             // i
            $maxTokens,             // i
            $contextWindow,         // i
            $supportsVision,        // i
            $supportsFunctions,     // i
            $supportsStreaming,     // i
            $inputCost,             // d
            $outputCost,            // d
            $thinkingCost,          // d (NULL safe)
            $effectiveDate,         // s
            $isActive,              // i
            $notes,                 // s
            $modelId                // i
        );
    } else {
        // Insert new model
        $sql = "INSERT INTO ai_models (
                provider_id, model_code, model_display_name, model_tier,
                sort_order, max_tokens, context_window, supports_vision,
                supports_function_calling, supports_streaming,
                input_cost_per_million, output_cost_per_million, thinking_cost_per_million,
                pricing_effective_date, is_active, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            aiCentral_logMessage("SQL Prepare Error: " . $conn->error, 'ERROR');
            echo json_encode(['success' => false, 'error' => 'Database error']);
            return;
        }

        $stmt->bind_param('isssiiiiiiiddsisi',
            $providerId,            // i
            $modelCode,             // s
            $modelName,             // s
            $modelTier,             // s
            $sortOrder,             // i
            $maxTokens,             // i
            $contextWindow,         // i
            $supportsVision,        // i
            $supportsFunctions,     // i
            $supportsStreaming,     // i
            $inputCost,             // d
            $outputCost,            // d
            $thinkingCost,          // d (NULL safe)
            $effectiveDate,         // s
            $isActive,              // i
            $notes                  // s
        );
    }

    if ($stmt->execute()) {
        $id = $modelId ?: $conn->insert_id;
        aiCentral_logMessage("Model saved: ID=$id, Code=$modelCode, ThinkingCost=" . ($thinkingCost === null ? 'NULL' : $thinkingCost), 'INFO');
        $stmt->close();
        echo json_encode(['success' => true, 'model_id' => $id]);
    } else {
        $error = $stmt->error;
        aiCentral_logMessage("SQL Execute Error: " . $error, 'ERROR');
        $stmt->close();
        echo json_encode(['success' => false, 'error' => $error]);
    }
}

/**
 * Delete model
 */
function deleteModel() {
    $conn = ai_getDBConnection();

    $modelId = $_POST['model_id'] ?? 0;

    if (!$modelId) {
        echo json_encode(['success' => false, 'error' => 'Model ID required']);
        return;
    }

    // Check if model is in use
    $sql = "SELECT COUNT(*) as count FROM ai_usage_log WHERE model_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $modelId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete model - it has usage history. Deactivate instead.']);
        return;
    }

    $sql = "DELETE FROM ai_models WHERE model_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $modelId);

    if ($stmt->execute()) {
        aiCentral_logMessage("Model deleted: ID=$modelId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Toggle model active status
 */
function toggleModelStatus() {
    $conn = ai_getDBConnection();

    $modelId = $_POST['model_id'] ?? 0;

    if (!$modelId) {
        echo json_encode(['success' => false, 'error' => 'Model ID required']);
        return;
    }

    $sql = "UPDATE ai_models SET is_active = NOT is_active, updated_at = NOW() WHERE model_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $modelId);

    if ($stmt->execute()) {
        // Get new status
        $sql = "SELECT is_active FROM ai_models WHERE model_id = ?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $modelId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        $newStatus = (bool)$row['is_active'];
        $stmt2->close();

        aiCentral_logMessage("Model status toggled: ID=$modelId, Active=$newStatus", 'INFO');
        echo json_encode(['success' => true, 'is_active' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

?>
