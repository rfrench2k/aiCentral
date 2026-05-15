<?php
/**
 * AI Central System - Database Functions
 * CRUD operations for all AI system tables
 */

require_once __DIR__ . '/../common/aPRIV_DB_AI.php';
require_once __DIR__ . '/../common/common_ai.php';

/**
 * Get program configuration including passthrough settings
 * @param string $programId Program ID
 * @return array|null Program row or null
 */
function ai_getProgramConfig($programId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM programs WHERE program_id = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$programId], 's');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get provider by ID
 */
function ai_getProviderById($providerId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM ai_providers WHERE provider_id = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$providerId], 'i');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get provider by code
 */
function ai_getProviderByCode($code) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM ai_providers WHERE provider_code = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$code], 's');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get model by ID
 */
function ai_getModelById($modelId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM ai_models WHERE model_id = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$modelId], 'i');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get model by code
 */
function ai_getModelByCode($modelCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM ai_models WHERE model_code = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$modelCode], 's');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Look up a model by (model_code, provider_code) when the same model_code
 * exists under multiple providers (e.g. claude-opus-4-6 is both an Anthropic
 * API row and a claude_cli row). Falls back to ai_getModelByCode() if the
 * provider_code is empty or no matching row is found.
 */
function ai_getModelByCodeAndProvider($modelCode, $providerCode) {
    if (empty($providerCode)) {
        return ai_getModelByCode($modelCode);
    }
    $conn = ai_getDBConnection();
    $sql = "SELECT m.* FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.model_code = ? AND p.provider_code = ? AND m.is_active = 1
            LIMIT 1";
    $result = aiCentral_executeQuery($conn, $sql, [$modelCode, $providerCode], 'ss');
    $row = $result ? $result->fetch_assoc() : null;
    return $row ?: ai_getModelByCode($modelCode);
}

/**
 * Get user's tier
 */
function ai_getUserTier($userId, $programId = null) {
    $conn = ai_getDBConnection();

    if ($programId) {
        $sql = "SELECT t.* FROM ai_tiers t
                JOIN user_tier_assignments uta ON t.tier_id = uta.tier_id
                WHERE uta.user_id = ? AND (uta.program_id = ? OR uta.program_id IS NULL)
                ORDER BY uta.program_id DESC LIMIT 1";
        $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId], 'ss');
    } else {
        $sql = "SELECT t.* FROM ai_tiers t
                JOIN user_tier_assignments uta ON t.tier_id = uta.tier_id
                WHERE uta.user_id = ? AND uta.program_id IS NULL LIMIT 1";
        $result = aiCentral_executeQuery($conn, $sql, [$userId], 's');
    }

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    // Default to free tier
    $sql = "SELECT * FROM ai_tiers WHERE tier_code = 'free' LIMIT 1";
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get user's feature preference
 */
function ai_getUserFeaturePreference($userId, $programId, $featureCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM user_feature_preferences
            WHERE user_id = ? AND program_id = ? AND feature_code = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId, $featureCode], 'sss');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Save user feature preference
 */
function ai_saveUserFeaturePreference($data) {
    $conn = ai_getDBConnection();
    $sql = "INSERT INTO user_feature_preferences
            (user_id, program_id, feature_code, use_user_key, user_key_id, preferred_provider, preferred_model)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            use_user_key = VALUES(use_user_key),
            user_key_id = VALUES(user_key_id),
            preferred_provider = VALUES(preferred_provider),
            preferred_model = VALUES(preferred_model),
            updated_at = CURRENT_TIMESTAMP";

    $userId = $data['user_id'];
    $programId = $data['program_id'];
    $featureCode = $data['feature_code'];
    $useUserKey = $data['use_user_key'] ?? 0;
    $userKeyId = $data['user_key_id'] ?? null;
    $preferredProvider = $data['preferred_provider'] ?? null;
    $preferredModel = $data['preferred_model'] ?? null;

    return aiCentral_executeUpdate($conn, $sql, [
        $userId, $programId, $featureCode, $useUserKey, $userKeyId, $preferredProvider, $preferredModel
    ], 'sssssss');
}

/**
 * Get user's API key
 */
function ai_getUserApiKey($keyId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM user_api_keys WHERE key_id = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$keyId], 'i');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get user's default key for provider
 */
function ai_getUserDefaultKeyForProvider($userId, $providerCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT uk.* FROM user_api_keys uk
            JOIN ai_providers p ON uk.provider_id = p.provider_id
            WHERE uk.user_id = ? AND p.provider_code = ? AND uk.is_active = 1 AND uk.is_default = 1
            LIMIT 1";
    $result = aiCentral_executeQuery($conn, $sql, [$userId, $providerCode], 'ss');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Save user API key
 */
function ai_saveUserApiKey($data) {
    $conn = ai_getDBConnection();
    $sql = "INSERT INTO user_api_keys
            (user_id, provider_id, key_name, encrypted_api_key, encryption_iv, key_prefix, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $userId = $data['user_id'];
    $providerId = $data['provider_id'];
    $keyName = $data['key_name'];
    $encryptedKey = $data['encrypted_api_key'];
    $iv = $data['encryption_iv'];
    $keyPrefix = $data['key_prefix'];
    $isDefault = $data['is_default'] ?? 0;

    return aiCentral_executeUpdate($conn, $sql, [
        $userId, $providerId, $keyName, $encryptedKey, $iv, $keyPrefix, $isDefault
    ], 'sisssi');
}

/**
 * Delete user API key
 */
function ai_deleteUserApiKey($keyId, $userId) {
    $conn = ai_getDBConnection();
    $sql = "DELETE FROM user_api_keys WHERE key_id = ? AND user_id = ?";
    return aiCentral_executeUpdate($conn, $sql, [$keyId, $userId], 'is');
}

/**
 * Get feature config
 */
function ai_getFeatureConfig($programId, $featureCode) {
    $conn = ai_getDBConnection();
    if (!$conn) {
        aiCentral_logMessage("Database connection failed in ai_getFeatureConfig", 'ERROR');
        return null;
    }

    // Verify connection is alive
    if (!$conn->ping()) {
        aiCentral_logMessage("Database connection ping failed in ai_getFeatureConfig", 'ERROR');
        return null;
    }

    aiCentral_logMessage("Executing feature lookup for program=$programId, feature=$featureCode", 'DEBUG');

    $sql = "SELECT * FROM ai_features WHERE program_id = ? AND feature_code = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$programId, $featureCode], 'ss');

    if (!$result) {
        aiCentral_logMessage("Query returned false for program=$programId, feature=$featureCode", 'ERROR');
        return null;
    }

    $featureData = $result->fetch_assoc();
    if (!$featureData) {
        aiCentral_logMessage("No feature data found for program=$programId, feature=$featureCode", 'ERROR');
    } else {
        aiCentral_logMessage("Feature found: " . json_encode($featureData), 'DEBUG');

        // Parse JSON capabilities
        if (!empty($featureData['required_capabilities'])) {
            $featureData['required_capabilities'] = json_decode($featureData['required_capabilities'], true);
        } else {
            $featureData['required_capabilities'] = [];
        }
    }

    return $featureData;
}

/**
 * Get chat type config
 */
function ai_getChatTypeConfig($programId, $chatTypeCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM chat_types WHERE program_id = ? AND chat_type_code = ? AND is_active = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$programId, $chatTypeCode], 'ss');
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get system setting
 */
function ai_getSetting($category, $scopeId, $key) {
    $conn = ai_getDBConnection();
    $sql = "SELECT setting_value, setting_type FROM ai_settings
            WHERE setting_category = ? AND setting_scope_id <=> ? AND setting_key = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$category, $scopeId, $key], 'sss');

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $value = $row['setting_value'];
        $type = $row['setting_type'];

        if ($type === 'boolean') {
            return (bool)$value;
        } elseif ($type === 'integer') {
            return (int)$value;
        } elseif ($type === 'decimal') {
            return (float)$value;
        } elseif ($type === 'json') {
            return json_decode($value, true);
        }

        return $value;
    }

    return null;
}

/**
 * Save system setting
 */
function ai_saveSetting($category, $scopeId, $key, $value, $type = 'string') {
    $conn = ai_getDBConnection();
    $sql = "INSERT INTO ai_settings (setting_category, setting_scope_id, setting_key, setting_value, setting_type)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";

    if ($type === 'json') {
        $value = json_encode($value);
    }

    return aiCentral_executeUpdate($conn, $sql, [$category, $scopeId, $key, $value, $type], 'sssss');
}

/**
 * Get models for tier
 */
function ai_getModelsForTier($tierId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT m.* FROM ai_models m
            JOIN tier_model_permissions tmp ON m.model_id = tmp.model_id
            WHERE tmp.tier_id = ? AND tmp.is_allowed = 1 AND m.is_active = 1
            ORDER BY m.sort_order";
    $result = aiCentral_executeQuery($conn, $sql, [$tierId], 'i');

    $models = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $models[] = $row;
        }
    }

    return $models;
}

/**
 * ============================================================================
 * CAPABILITY MANAGEMENT FUNCTIONS
 * ============================================================================
 */

/**
 * Get all capabilities for a specific model
 *
 * @param int $modelId Model ID
 * @return array Array of capabilities with cost and support info
 */
function ai_getModelCapabilities($modelId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT capability_code, capability_name, cost_per_use, max_uses_default, is_supported
            FROM ai_model_capabilities
            WHERE model_id = ? AND is_supported = 1
            ORDER BY capability_code";
    $result = aiCentral_executeQuery($conn, $sql, [$modelId], 'i');

    $capabilities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $capabilities[$row['capability_code']] = $row;
        }
    }

    return $capabilities;
}

/**
 * Get enabled capabilities for a specific feature and tier combination
 *
 * @param int $featureId Feature ID
 * @param int $tierId Tier ID
 * @return array Array of enabled capabilities with limits
 */
function ai_getFeatureTierCapabilities($featureId, $tierId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT capability_code, is_enabled, max_uses
            FROM ai_feature_tier_capabilities
            WHERE feature_id = ? AND tier_id = ? AND is_enabled = 1
            ORDER BY capability_code";
    $result = aiCentral_executeQuery($conn, $sql, [$featureId, $tierId], 'ii');

    $capabilities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $capabilities[$row['capability_code']] = $row;
        }
    }

    return $capabilities;
}

/**
 * Get enabled capabilities for a specific tier (global fallback)
 *
 * @param int $tierId Tier ID
 * @return array Array of enabled capabilities with limits
 */
function ai_getTierCapabilities($tierId) {
    $conn = ai_getDBConnection();
    $sql = "SELECT capability_code, is_enabled, max_uses
            FROM ai_tier_capabilities
            WHERE tier_id = ? AND is_enabled = 1
            ORDER BY capability_code";
    $result = aiCentral_executeQuery($conn, $sql, [$tierId], 'i');

    $capabilities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $capabilities[$row['capability_code']] = $row;
        }
    }

    return $capabilities;
}

/**
 * Check if a tier allows a specific capability
 *
 * @param int $tierId Tier ID
 * @param string $capabilityCode Capability code (web_search, web_fetch, vision)
 * @return bool True if allowed, false otherwise
 */
function ai_checkCapabilityAllowed($tierId, $capabilityCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT is_enabled FROM ai_tier_capabilities
            WHERE tier_id = ? AND capability_code = ? AND is_enabled = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$tierId, $capabilityCode], 'is');

    return $result && $result->num_rows > 0;
}

/**
 * Get cost per use for a capability on a specific model
 *
 * @param int $modelId Model ID
 * @param string $capabilityCode Capability code
 * @return float Cost per use in USD, or 0 if not found
 */
function ai_getCapabilityCost($modelId, $capabilityCode) {
    $conn = ai_getDBConnection();
    $sql = "SELECT cost_per_use FROM ai_model_capabilities
            WHERE model_id = ? AND capability_code = ? AND is_supported = 1";
    $result = aiCentral_executeQuery($conn, $sql, [$modelId, $capabilityCode], 'is');

    if ($result && $row = $result->fetch_assoc()) {
        return (float)$row['cost_per_use'];
    }

    return 0.0;
}

/**
 * Update feature capabilities (for admin UI)
 *
 * @param string $programId Program ID
 * @param string $featureCode Feature code
 * @param array $capabilities Array of capability codes to require
 * @param int $maxOutputTokens Max output tokens
 * @return bool Success
 */
function ai_updateFeatureCapabilities($programId, $featureCode, $capabilities, $maxOutputTokens = null) {
    $conn = ai_getDBConnection();

    $capabilitiesJson = json_encode($capabilities);
    $sql = "UPDATE ai_features SET required_capabilities = ?";
    $types = 's';
    $params = [$capabilitiesJson];

    if ($maxOutputTokens !== null) {
        $sql .= ", max_output_tokens = ?";
        $types .= 'i';
        $params[] = $maxOutputTokens;
    }

    $sql .= " WHERE program_id = ? AND feature_code = ?";
    $types .= 'ss';
    $params[] = $programId;
    $params[] = $featureCode;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        aiCentral_logMessage("Failed to prepare statement: " . $conn->error, 'ERROR');
        return false;
    }

    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * Update tier capability (for admin UI)
 *
 * @param int $tierId Tier ID
 * @param string $capabilityCode Capability code
 * @param bool $enabled Whether capability is enabled
 * @param int|null $maxUses Max uses limit (NULL = unlimited)
 * @return bool Success
 */
function ai_updateTierCapability($tierId, $capabilityCode, $enabled, $maxUses) {
    $conn = ai_getDBConnection();

    $isEnabled = $enabled ? 1 : 0;

    $sql = "INSERT INTO ai_tier_capabilities (tier_id, capability_code, is_enabled, max_uses)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), max_uses = VALUES(max_uses)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        aiCentral_logMessage("Failed to prepare statement: " . $conn->error, 'ERROR');
        return false;
    }

    $stmt->bind_param('isii', $tierId, $capabilityCode, $isEnabled, $maxUses);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

?>
