<?php
/**
 * AI Central Admin Features - Backend API
 * Per-Feature-Per-Tier Configuration
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

aiCentral_logMessage("Features API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getFeatures':
            getFeatures();
            break;

        case 'getFeature':
            getFeature();
            break;

        case 'getAllTiers':
            getAllTiers();
            break;

        case 'getFeatureTierConfig':
            getFeatureTierConfig();
            break;

        case 'saveFeature':
            saveFeature();
            break;

        case 'deleteFeature':
            deleteFeature();
            break;

        case 'toggleFeatureStatus':
            toggleFeatureStatus();
            break;

        case 'getModels':
            getModels();
            break;

        case 'getProviders':
            getProviders();
            break;

        case 'getPrograms':
            getPrograms();
            break;

        case 'getCapabilities':
            getCapabilities();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Features API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all features
 */
function getFeatures() {
    $conn = ai_getDBConnection();

    $sql = "SELECT *
            FROM ai_features
            ORDER BY program_id, sort_order, feature_name";

    $result = $conn->query($sql);

    $features = [];
    while ($row = $result->fetch_assoc()) {
        $featureId = (int)$row['feature_id'];

        // Get capability summary for this feature (all tiers vs some tiers)
        $capSummary = [];
        $capSql = "SELECT
                    ftc.capability_code,
                    COUNT(CASE WHEN ftc.is_enabled = 1 THEN 1 END) as enabled_count,
                    COUNT(*) as total_tiers,
                    GROUP_CONCAT(CASE WHEN ftc.is_enabled = 1 THEN t.tier_name END SEPARATOR ', ') as tier_names
                FROM ai_feature_tier_capabilities ftc
                JOIN ai_tiers t ON ftc.tier_id = t.tier_id
                WHERE ftc.feature_id = ?
                GROUP BY ftc.capability_code";
        $capStmt = $conn->prepare($capSql);
        $capStmt->bind_param('i', $featureId);
        $capStmt->execute();
        $capResult = $capStmt->get_result();

        while ($capRow = $capResult->fetch_assoc()) {
            $capSummary[$capRow['capability_code']] = [
                'enabled_count' => (int)$capRow['enabled_count'],
                'total_tiers' => (int)$capRow['total_tiers'],
                'tier_names' => explode(', ', $capRow['tier_names'])
            ];
        }
        $capStmt->close();

        $features[] = [
            'feature_id' => $featureId,
            'program_id' => $row['program_id'],
            'feature_code' => $row['feature_code'],
            'feature_name' => $row['feature_name'],
            'feature_description' => $row['feature_description'],
            'feature_type' => $row['feature_type'],
            'default_provider' => $row['default_provider'],
            'default_model' => $row['default_model'],
            'default_model_free' => $row['default_model_free'],
            'default_model_basic' => $row['default_model_basic'],
            'default_model_pro' => $row['default_model_pro'],
            'default_model_unlimited' => $row['default_model_unlimited'],
            'supports_vision' => (bool)$row['supports_vision'],
            'supports_streaming' => (bool)$row['supports_streaming'],
            'supports_file_upload' => (bool)$row['supports_file_upload'],
            'gear_icon_visible' => (bool)$row['gear_icon_visible'],
            'max_input_tokens' => (int)$row['max_input_tokens'],
            'is_active' => (bool)$row['is_active'],
            'sort_order' => (int)$row['sort_order'],
            'tier_capability_summary' => $capSummary
        ];
    }

    echo json_encode(['success' => true, 'features' => $features]);
}

/**
 * Get single feature with all tier configurations
 */
function getFeature() {
    $conn = ai_getDBConnection();

    $featureId = $_REQUEST['feature_id'] ?? 0;

    if (!$featureId) {
        echo json_encode(['success' => false, 'error' => 'Feature ID required']);
        return;
    }

    // Get feature details
    $sql = "SELECT * FROM ai_features WHERE feature_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $featureId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Feature not found']);
        $stmt->close();
        return;
    }

    $feature = $result->fetch_assoc();
    $stmt->close();

    // Get all tiers
    $sql = "SELECT tier_id, tier_code, tier_name FROM ai_tiers ORDER BY sort_order";
    $result = $conn->query($sql);
    $tiers = [];
    while ($row = $result->fetch_assoc()) {
        $tiers[] = $row;
    }

    // Get tier configurations
    $tierConfigs = [];
    foreach ($tiers as $tier) {
        $tierId = $tier['tier_id'];

        // Get max_output_tokens and model_code for this tier
        $sql = "SELECT max_output_tokens, model_code FROM ai_feature_tier_config
                WHERE feature_id = ? AND tier_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $featureId, $tierId);
        $stmt->execute();
        $result = $stmt->get_result();

        $maxOutputTokens = 4096; // Default
        $modelCode = null;
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $maxOutputTokens = (int)$row['max_output_tokens'];
            $modelCode = $row['model_code'];
        }
        $stmt->close();

        // Get capabilities for this tier
        $sql = "SELECT capability_code, is_enabled, max_uses
                FROM ai_feature_tier_capabilities
                WHERE feature_id = ? AND tier_id = ?
                ORDER BY capability_code";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $featureId, $tierId);
        $stmt->execute();
        $result = $stmt->get_result();

        $capabilities = [];
        while ($row = $result->fetch_assoc()) {
            $capabilities[] = [
                'capability_code' => $row['capability_code'],
                'is_enabled' => (bool)$row['is_enabled'],
                'max_uses' => $row['max_uses'] ? (int)$row['max_uses'] : null
            ];
        }
        $stmt->close();

        $tierConfigs[$tier['tier_code']] = [
            'tier_id' => (int)$tierId,
            'tier_code' => $tier['tier_code'],
            'tier_name' => $tier['tier_name'],
            'model_code' => $modelCode,
            'max_output_tokens' => $maxOutputTokens,
            'capabilities' => $capabilities
        ];
    }

    echo json_encode([
        'success' => true,
        'feature' => [
            'feature_id' => (int)$feature['feature_id'],
            'program_id' => $feature['program_id'],
            'feature_code' => $feature['feature_code'],
            'feature_name' => $feature['feature_name'],
            'feature_description' => $feature['feature_description'],
            'feature_type' => $feature['feature_type'],
            'default_provider' => $feature['default_provider'],
            'default_model' => $feature['default_model'],
            'max_input_tokens' => (int)$feature['max_input_tokens'],
            'is_active' => (bool)$feature['is_active'],
            'sort_order' => (int)$feature['sort_order'],
            'tier_configs' => $tierConfigs
        ]
    ]);
}

/**
 * Get all tiers
 */
function getAllTiers() {
    $conn = ai_getDBConnection();

    $sql = "SELECT tier_id, tier_code, tier_name, sort_order
            FROM ai_tiers
            ORDER BY sort_order";

    $result = $conn->query($sql);

    $tiers = [];
    while ($row = $result->fetch_assoc()) {
        $tiers[] = [
            'tier_id' => (int)$row['tier_id'],
            'tier_code' => $row['tier_code'],
            'tier_name' => $row['tier_name'],
            'sort_order' => (int)$row['sort_order']
        ];
    }

    echo json_encode(['success' => true, 'tiers' => $tiers]);
}

/**
 * Get feature tier configuration for specific tier
 */
function getFeatureTierConfig() {
    $conn = ai_getDBConnection();

    $featureId = $_REQUEST['feature_id'] ?? 0;
    $tierId = $_REQUEST['tier_id'] ?? 0;

    if (!$featureId || !$tierId) {
        echo json_encode(['success' => false, 'error' => 'Feature ID and Tier ID required']);
        return;
    }

    // Get max_output_tokens
    $sql = "SELECT max_output_tokens FROM ai_feature_tier_config
            WHERE feature_id = ? AND tier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $featureId, $tierId);
    $stmt->execute();
    $result = $stmt->get_result();

    $maxOutputTokens = null;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $maxOutputTokens = (int)$row['max_output_tokens'];
    }
    $stmt->close();

    // Get capabilities
    $sql = "SELECT capability_code, is_enabled, max_uses
            FROM ai_feature_tier_capabilities
            WHERE feature_id = ? AND tier_id = ?
            ORDER BY capability_code";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $featureId, $tierId);
    $stmt->execute();
    $result = $stmt->get_result();

    $capabilities = [];
    while ($row = $result->fetch_assoc()) {
        $capabilities[] = [
            'capability_code' => $row['capability_code'],
            'is_enabled' => (bool)$row['is_enabled'],
            'max_uses' => $row['max_uses'] ? (int)$row['max_uses'] : null
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'config' => [
            'max_output_tokens' => $maxOutputTokens,
            'capabilities' => $capabilities
        ]
    ]);
}

/**
 * Save feature and all tier configurations
 */
function saveFeature() {
    $conn = ai_getDBConnection();

    $featureId = $_POST['feature_id'] ?? null;
    $programId = trim($_POST['program_id'] ?? '');
    $featureCode = trim($_POST['feature_code'] ?? '');
    $featureName = trim($_POST['feature_name'] ?? '');
    $featureDescription = trim($_POST['feature_description'] ?? '');
    $featureType = trim($_POST['feature_type'] ?? '');
    $defaultModel = trim($_POST['default_model'] ?? '');
    $maxInputTokens = (int)($_POST['max_input_tokens'] ?? 128000);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 100);
    $tierConfigsJson = $_POST['tier_configs'] ?? '{}';

    // Validation
    if (empty($programId) || empty($featureCode) || empty($featureName)) {
        echo json_encode(['success' => false, 'error' => 'Program ID, feature code, and feature name are required']);
        return;
    }

    // Validate max_input_tokens - must be one of the standard values
    $validInputTokens = [4000, 8000, 16000, 32000, 64000, 128000, 200000, 400000, 1000000, 2000000];
    if (!in_array($maxInputTokens, $validInputTokens)) {
        echo json_encode(['success' => false, 'error' => 'Max input tokens must be a standard value (4K, 8K, 16K, 32K, 64K, 128K, 200K, 400K, 1M, or 2M)']);
        return;
    }

    // Parse tier configs
    $tierConfigs = json_decode($tierConfigsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid tier configurations format']);
        return;
    }

    $featureDescriptionValue = empty($featureDescription) ? NULL : $featureDescription;
    $featureTypeValue = empty($featureType) ? NULL : $featureType;
    $defaultModelValue = empty($defaultModel) ? NULL : $defaultModel;

    // Derive default_provider from the selected model's provider
    $defaultProviderValue = NULL;
    if ($defaultModelValue) {
        $provSql = "SELECT p.provider_code FROM ai_models m
                    JOIN ai_providers p ON m.provider_id = p.provider_id
                    WHERE m.model_code = ? AND m.is_active = 1 LIMIT 1";
        $provStmt = $conn->prepare($provSql);
        $provStmt->bind_param('s', $defaultModelValue);
        $provStmt->execute();
        $provResult = $provStmt->get_result();
        if ($provRow = $provResult->fetch_assoc()) {
            $defaultProviderValue = $provRow['provider_code'];
        }
        $provStmt->close();
    }

    if ($featureId) {
        // Update existing feature
        $sql = "UPDATE ai_features SET
                program_id = ?, feature_code = ?, feature_name = ?,
                feature_description = ?, feature_type = ?, default_model = ?, default_provider = ?,
                max_input_tokens = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                WHERE feature_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssssiiii',
            $programId, $featureCode, $featureName, $featureDescriptionValue,
            $featureTypeValue, $defaultModelValue, $defaultProviderValue,
            $maxInputTokens, $isActive, $sortOrder, $featureId
        );
    } else {
        // Check for duplicate feature_code
        $sql = "SELECT feature_id FROM ai_features WHERE feature_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $featureCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Feature code already exists']);
            $stmt->close();
            return;
        }
        $stmt->close();

        // Insert new feature
        $sql = "INSERT INTO ai_features (
                    program_id, feature_code, feature_name, feature_description,
                    feature_type, default_model, default_provider, max_input_tokens, is_active, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssssiii',
            $programId, $featureCode, $featureName, $featureDescriptionValue,
            $featureTypeValue, $defaultModelValue, $defaultProviderValue, $maxInputTokens, $isActive, $sortOrder
        );
    }

    if ($stmt->execute()) {
        $id = $featureId ?: $conn->insert_id;
        aiCentral_logMessage("Feature saved: ID=$id, Code=$featureCode", 'INFO');

        // Save tier configurations
        foreach ($tierConfigs as $tierCode => $tierConfig) {
            $tierId = (int)$tierConfig['tier_id'];
            $modelCode = trim($tierConfig['model_code'] ?? '');
            $maxOutputTokens = (int)$tierConfig['max_output_tokens'];
            $capabilities = $tierConfig['capabilities'] ?? [];

            // Convert empty model_code to NULL
            $modelCodeValue = empty($modelCode) ? NULL : $modelCode;

            // Validate max_output_tokens - must be one of the standard values
            $validOutputTokens = [1024, 2048, 4096, 8192, 16384, 32768, 65536, 128000];
            if (!in_array($maxOutputTokens, $validOutputTokens)) {
                echo json_encode(['success' => false, 'error' => "Max output tokens for {$tierCode} must be a standard value (1024, 2048, 4096, 8192, 16384, 32768, 65536, or 128000)"]);
                return;
            }

            // Save/update tier config
            $sql = "INSERT INTO ai_feature_tier_config (feature_id, tier_id, max_output_tokens, model_code)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE max_output_tokens = ?, model_code = ?, updated_at = NOW()";
            $configStmt = $conn->prepare($sql);
            $configStmt->bind_param('iiisis', $id, $tierId, $maxOutputTokens, $modelCodeValue, $maxOutputTokens, $modelCodeValue);
            $configStmt->execute();
            $configStmt->close();

            // Sync tier model to ai_features.default_model_{tierCode} column
            // This keeps the feature table in sync for passthrough mode lookups
            $tierModelCol = "default_model_" . $tierCode;
            $validTierCols = ['default_model_free', 'default_model_basic', 'default_model_pro', 'default_model_unlimited'];
            if (in_array($tierModelCol, $validTierCols)) {
                $syncSql = "UPDATE ai_features SET $tierModelCol = ?, updated_at = NOW() WHERE feature_id = ?";
                $syncStmt = $conn->prepare($syncSql);
                $syncStmt->bind_param('si', $modelCodeValue, $id);
                $syncStmt->execute();
                $syncStmt->close();
            }

            // Save capabilities
            foreach ($capabilities as $cap) {
                $capCode = $cap['capability_code'];
                $isEnabled = (int)($cap['is_enabled'] ?? 0);
                $maxUses = ($cap['max_uses'] === '' || $cap['max_uses'] === null) ? NULL : (int)$cap['max_uses'];

                // Validate max_uses
                if ($maxUses !== null && ($maxUses < 1 || $maxUses > 1000)) {
                    echo json_encode(['success' => false, 'error' => "Max uses for {$capCode} must be between 1 and 1,000"]);
                    return;
                }

                // Save/update capability
                $sql = "INSERT INTO ai_feature_tier_capabilities
                        (feature_id, tier_id, capability_code, is_enabled, max_uses)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE is_enabled = ?, max_uses = ?, updated_at = NOW()";
                $capStmt = $conn->prepare($sql);
                $capStmt->bind_param('iisiiii', $id, $tierId, $capCode, $isEnabled, $maxUses, $isEnabled, $maxUses);
                $capStmt->execute();
                $capStmt->close();
            }
        }

        echo json_encode(['success' => true, 'feature_id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Delete feature (cascade deletes tier configs and capabilities)
 */
function deleteFeature() {
    $conn = ai_getDBConnection();

    $featureId = $_POST['feature_id'] ?? 0;

    if (!$featureId) {
        echo json_encode(['success' => false, 'error' => 'Feature ID required']);
        return;
    }

    $sql = "DELETE FROM ai_features WHERE feature_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $featureId);

    if ($stmt->execute()) {
        aiCentral_logMessage("Feature deleted: ID=$featureId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Toggle feature active status
 */
function toggleFeatureStatus() {
    $conn = ai_getDBConnection();

    $featureId = $_POST['feature_id'] ?? 0;

    if (!$featureId) {
        echo json_encode(['success' => false, 'error' => 'Feature ID required']);
        return;
    }

    $sql = "UPDATE ai_features SET is_active = NOT is_active, updated_at = NOW() WHERE feature_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $featureId);

    if ($stmt->execute()) {
        // Get new status
        $sql = "SELECT is_active FROM ai_features WHERE feature_id = ?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $featureId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        $newStatus = (bool)$row['is_active'];
        $stmt2->close();

        aiCentral_logMessage("Feature status toggled: ID=$featureId, Active=$newStatus", 'INFO');
        echo json_encode(['success' => true, 'is_active' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Get all available models with provider information
 */
function getModels() {
    $conn = ai_getDBConnection();

    $sql = "SELECT m.model_id, m.model_code, m.model_display_name, m.provider_id, p.provider_code
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
            'model_name' => $row['model_display_name'],
            'provider_id' => (int)$row['provider_id'],
            'provider_code' => $row['provider_code']
        ];
    }

    echo json_encode(['success' => true, 'models' => $models]);
}

/**
 * Get all active providers
 */
function getProviders() {
    $conn = ai_getDBConnection();

    $sql = "SELECT provider_id, provider_code, provider_name
            FROM ai_providers
            WHERE is_active = 1
            ORDER BY provider_name";

    $result = $conn->query($sql);

    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = [
            'provider_id' => (int)$row['provider_id'],
            'provider_code' => $row['provider_code'],
            'provider_name' => $row['provider_name']
        ];
    }

    echo json_encode(['success' => true, 'providers' => $providers]);
}

/**
 * Get unique program list from features
 */
function getPrograms() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT program_id
            FROM ai_features
            ORDER BY program_id";

    $result = $conn->query($sql);

    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row['program_id'];
    }

    echo json_encode(['success' => true, 'programs' => $programs]);
}

/**
 * Get all distinct capabilities
 */
function getCapabilities() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT capability_code
            FROM ai_feature_tier_capabilities
            ORDER BY capability_code";

    $result = $conn->query($sql);

    $capabilities = [];
    while ($row = $result->fetch_assoc()) {
        $capCode = $row['capability_code'];

        // Get capability display info from lookups if available
        $lookupSql = "SELECT LOOKUP_DESC FROM admin_lookups
                      WHERE LOOKUP_NAME = 'capability' AND LOOKUP_VALUE = ?";
        $stmt = $conn->prepare($lookupSql);
        $stmt->bind_param('s', $capCode);
        $stmt->execute();
        $lookupResult = $stmt->get_result();

        $displayName = ucwords(str_replace('_', ' ', $capCode));
        $description = '';

        if ($lookupResult->num_rows > 0) {
            $lookupRow = $lookupResult->fetch_assoc();
            $description = $lookupRow['LOOKUP_DESC'];
        } else {
            // Fallback descriptions
            switch($capCode) {
                case 'web_search':
                    $description = 'Allow AI to search the web';
                    break;
                case 'web_fetch':
                    $description = 'Fetch specific URLs';
                    break;
                case 'vision':
                    $description = 'Image analysis';
                    break;
                default:
                    $description = $displayName;
            }
        }
        $stmt->close();

        $capabilities[] = [
            'capability_code' => $capCode,
            'name' => $displayName,
            'description' => $description
        ];
    }

    echo json_encode(['success' => true, 'capabilities' => $capabilities]);
}

?>
