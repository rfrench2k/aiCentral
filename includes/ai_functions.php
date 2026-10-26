<?php
/**
 * AI Central System - High-Level Functions
 * Main functions that applications call for AI functionality
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_db_functions.php';
require_once __DIR__ . '/AIProviderManager.php';
require_once __DIR__ . '/KeyManager.php';
require_once __DIR__ . '/../common/common_ai.php';
require_once __DIR__ . '/../common/aPRIV_API.php';

/**
 * Main function for making AI requests
 * This is what apps call
 *
 * @param array $params [
 *   'user_id' => string (required),
 *   'program_id' => string (required),
 *   'feature_code' => string (required),
 *   'prompt' => string (required),
 *   'options' => array (optional: max_tokens, temperature, system, images, run_id, metadata,
 *                json_schema: a JSON Schema array; on Claude models the response is then valid JSON matching it,
 *                history: earlier turns of a conversation, oldest first, as [['role' => 'user'|'assistant', 'content' => string], ...];
 *                         'prompt' is then the user's newest message,
 *                system: a string, or (Claude) a list of text blocks, some with 'cache_control' for prompt caching,
 *                cache: true (5-minute) or '1h' to prompt-cache the conversation (Claude),
 *                on_text: callable(string $text) — the reply is streamed and passed in pieces as it arrives,
 *                tools: the app's own tool definitions (Claude format: name, description, input_schema),
 *                tool_handler: callable(string $name, array $input): string|array — runs those tools; the request
 *                              continues with their results (one usage-log row per round),
 *                on_tool: callable(string $name, array $input) — told before each tool runs,
 *                max_tool_rounds: int (default 4),
 *                fallbacks: false to turn off the model's server-side fallback,
 *                sources: [['title' => ..., 'text' => ...], ...] passages the reply may quote; on Claude models the
 *                         result's 'segments' tie each part of the reply to the exact source text it came from.
 *                Model, max tokens, effort, thinking and fast mode come from the feature's tier settings.)
 * ]
 * @return array ['success' => bool, 'response' => string, 'model' => model_code used, 'usage' => array, 'cost' => array,
 *                'message_id' => string, 'stop_reason' => string, 'segments' => array, 'error' => string]
 */
function ai_makeRequest($params) {
    // Validate required parameters
    if (!isset($params['user_id']) || !isset($params['program_id']) || !isset($params['feature_code']) || !isset($params['prompt'])) {
        return ['success' => false, 'error' => 'Missing required parameters'];
    }

    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $prompt = $params['prompt'];
    $options = $params['options'] ?? [];

    aiCentral_logMessage("AI request from user: $userId, program: $programId, feature: $featureCode", 'INFO');

    try {
        // Get feature configuration
        $featureConfig = ai_getFeatureConfig($programId, $featureCode);
        if (!$featureConfig) {
            return ['success' => false, 'error' => 'Feature not found: ' . $featureCode];
        }

        // Check if program uses passthrough mode
        $programConfig = ai_getProgramConfig($programId);
        if ($programConfig && $programConfig['passthrough_mode']) {
            // Passthrough mode: skip user registration/tier checks, use program's tier
            return ai_makeRequestPassthrough($params, $featureConfig, $programConfig);
        }

        // Check user's feature preferences
        $userPref = ai_getUserFeaturePreference($userId, $programId, $featureCode);

        if ($userPref && $userPref['use_user_key'] && $userPref['user_key_id']) {
            // User wants to use their own key
            return ai_makeRequestWithUserKey($params, $userPref);
        } else {
            // Use system key
            return ai_makeRequestWithSystemKey($params, $featureConfig, $userPref);
        }

    } catch (Exception $e) {
        aiCentral_logMessage("AI request error: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Make request with user's own API key
 */
function ai_makeRequestWithUserKey($params, $userPref) {
    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $prompt = $params['prompt'];
    $options = $params['options'] ?? [];

    // Get user's key
    $userKey = ai_getUserApiKey($userPref['user_key_id']);
    if (!$userKey) {
        return ['success' => false, 'error' => 'User API key not found'];
    }

    // Decrypt key
    $keyManager = new KeyManager();
    $apiKey = $keyManager->decryptKey($userKey['encrypted_api_key'], $userKey['encryption_iv']);

    if (!$apiKey) {
        return ['success' => false, 'error' => 'Failed to decrypt API key'];
    }

    // Get provider
    $provider = ai_getProviderByCode($userPref['preferred_provider']);
    if (!$provider) {
        return ['success' => false, 'error' => 'Provider not found'];
    }

    // Get model
    $model = ai_getModelByCodeAndProvider($userPref['preferred_model'], $provider['provider_code']);
    if (!$model) {
        return ['success' => false, 'error' => 'Model not found'];
    }

    // The feature's settings for the user's tier: max output tokens, effort, thinking
    $featureConfig = ai_getFeatureConfig($programId, $featureCode);
    $tier = ai_getUserTier($userId, $programId);
    if ($featureConfig && $tier) {
        ai_applyTierSettings($options, ai_getFeatureTierRow($featureConfig['feature_id'], $tier['tier_id']), $model);
    }

    // Make request
    $manager = new AIProviderManager(
        $provider['provider_code'],
        $model['model_code'],
        $apiKey,
        $userId,
        $programId,
        $featureCode
    );

    $result = $manager->makeRequest($prompt, $options);

    // Update last used - use prepared statement for security
    $conn = ai_getDBConnection();
    $keyId = $userKey['key_id'];
    $stmt = $conn->prepare("UPDATE user_api_keys SET last_used_at = NOW() WHERE key_id = ?");
    $stmt->bind_param('i', $keyId);
    $stmt->execute();
    $stmt->close();

    return $result;
}

/**
 * The model a feature uses for a tier: the feature's default_model_<tier> (with the tier row's
 * provider), else its default_model. When the same model_code exists under several providers
 * (e.g. an API row and a CLI row), the provider decides which row is used.
 *
 * @return array ['model' => ai_models row] or ['error' => string]
 */
function ai_pickTierModel($featureConfig, $tier, $tierRow) {
    $tierCode = $tier['tier_code'];
    $modelProvider = $featureConfig['default_provider'] ?? null;
    $tierModelKey = "default_model_" . $tierCode;

    if (!empty($featureConfig[$tierModelKey])) {
        $modelCode = $featureConfig[$tierModelKey];
        if (!empty($tierRow['provider_code'])) $modelProvider = $tierRow['provider_code'];
        aiCentral_logMessage("Using tier-specific model for tier $tierCode: $modelCode", 'INFO');
    } elseif (!empty($featureConfig['default_model'])) {
        $modelCode = $featureConfig['default_model'];
        aiCentral_logMessage("No tier-specific model for tier $tierCode, using feature default: $modelCode", 'INFO');
    } else {
        return ['error' => "Feature '{$featureConfig['feature_code']}' is not configured with a model for tier '$tierCode'. Please contact administrator."];
    }

    $model = ai_getModelByCodeAndProvider($modelCode, $modelProvider);
    if (!$model) {
        return ['error' => "Model '$modelCode' not found in database. Please contact administrator."];
    }
    return ['model' => $model];
}

/**
 * What a feature will run on for this user (system key), so apps never keep their own model lists.
 *
 * @return array ['success' => bool, 'model' => model_code, 'model_name' => display name,
 *                'context_window' => int tokens, 'error' => string]
 */
function ai_getFeatureModelInfo($userId, $programId, $featureCode) {
    $featureConfig = ai_getFeatureConfig($programId, $featureCode);
    if (!$featureConfig) {
        return ['success' => false, 'error' => 'Feature not found: ' . $featureCode];
    }
    // Passthrough programs run on the program's tier, not the user's
    $programConfig = ai_getProgramConfig($programId);
    if ($programConfig && $programConfig['passthrough_mode']) {
        $result = aiCentral_executeQuery(ai_getDBConnection(), "SELECT * FROM ai_tiers WHERE tier_id = ?", [$programConfig['passthrough_tier_id']], 'i');
        $tier = $result ? $result->fetch_assoc() : null;
    } else {
        $tier = ai_getUserTier($userId, $programId);
    }
    if (!$tier) {
        return ['success' => false, 'error' => 'No tier assigned to user.'];
    }
    $picked = ai_pickTierModel($featureConfig, $tier, ai_getFeatureTierRow($featureConfig['feature_id'], $tier['tier_id']));
    if (!empty($picked['error'])) {
        return ['success' => false, 'error' => $picked['error']];
    }
    return [
        'success' => true,
        'model' => $picked['model']['model_code'],
        'model_name' => $picked['model']['model_display_name'],
        'context_window' => (int)$picked['model']['context_window']
    ];
}

/**
 * Make request with system API key
 */
function ai_makeRequestWithSystemKey($params, $featureConfig, $userPref) {
    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $prompt = $params['prompt'];
    $options = $params['options'] ?? [];

    // Check quota (only for system keys)
    $quotaCheck = ai_checkQuota($userId, $programId);
    if (!$quotaCheck['allowed']) {
        return ['success' => false, 'error' => $quotaCheck['message']];
    }

    // Get user's tier to select appropriate model
    $tier = ai_getUserTier($userId, $programId);
    if (!$tier) {
        return ['success' => false, 'error' => 'No tier assigned to user. Please contact administrator.'];
    }
    $tierCode = $tier['tier_code'];

    // Determine model based on tier + feature configuration ONLY
    // User preferences are IGNORED when using system keys
    $tierRow = ai_getFeatureTierRow($featureConfig['feature_id'], $tier['tier_id']);
    $picked = ai_pickTierModel($featureConfig, $tier, $tierRow);
    if (!empty($picked['error'])) {
        return ['success' => false, 'error' => $picked['error']];
    }
    $model = $picked['model'];
    $modelCode = $model['model_code'];

    // Get provider FROM THE MODEL - provider is determined by the model, not separately
    $provider = ai_getProviderById($model['provider_id']);
    if (!$provider) {
        return ['success' => false, 'error' => "Provider not found for model '$modelCode'. Please contact administrator."];
    }

    // Check if user's tier allows this model (tier already retrieved above)
    $allowedModels = ai_getModelsForTier($tier['tier_id']);
    $modelAllowed = false;
    foreach ($allowedModels as $allowedModel) {
        if ($allowedModel['model_id'] == $model['model_id']) {
            $modelAllowed = true;
            break;
        }
    }
    // A feature can have its own permission for a model its tier does not allow
    if (!$modelAllowed && ai_featureAllowsModel($featureConfig['feature_id'], $tier['tier_id'], $model['model_id'])) {
        $modelAllowed = true;
    }

    if (!$modelAllowed) {
        return ['success' => false, 'error' => 'Your tier does not allow access to this model. Please upgrade or use your own API key.'];
    }

    // Check and configure capabilities (tools) from feature+tier configuration
    $featureTierCaps = ai_getFeatureTierCapabilities($featureConfig['feature_id'], $tier['tier_id']);

    if (!empty($featureTierCaps)) {
        aiCentral_logMessage("Feature has capabilities configured for tier {$tier['tier_code']}: " . implode(', ', array_keys($featureTierCaps)), 'INFO');

        // Get model's supported capabilities
        $modelCaps = ai_getModelCapabilities($model['model_id']);

        // Build capability limits array
        $capabilityLimits = [];

        foreach ($featureTierCaps as $capCode => $capConfig) {
            // Check if model supports this capability
            if (!isset($modelCaps[$capCode]) || !$modelCaps[$capCode]['is_supported']) {
                aiCentral_logMessage("Capability $capCode not supported by model {$model['model_code']}, skipping", 'INFO');
                continue;
            }

            // Get the limit for this capability (from feature+tier config)
            $maxUses = $capConfig['max_uses'] ?? null;
            $capabilityLimits[$capCode] = $maxUses;

            aiCentral_logMessage("Capability $capCode enabled with limit: " . ($maxUses ?? 'unlimited'), 'INFO');
        }

        // Add capabilities to options
        if (!empty($capabilityLimits)) {
            $options['capabilities'] = $capabilityLimits;
        }
    } else {
        aiCentral_logMessage("No capabilities configured for feature {$featureConfig['feature_code']} on tier {$tier['tier_code']}", 'INFO');
    }

    // The feature's tier settings: max output tokens, effort, thinking
    ai_applyTierSettings($options, $tierRow, $model);


    // Get system API key from config based on provider
    $apiKey = null;
    $providerCode = $provider['provider_code'];

    if ($providerCode === 'claude') {
        $apiKey = ANTHROPIC_API_KEY;
    } elseif ($providerCode === 'openai') {
        $apiKey = OPENAI_API_KEY;
    } elseif ($providerCode === 'kimi') {
        $apiKey = KIMIK2_API_KEY;
    } elseif ($providerCode === 'grok') {
        $apiKey = GROK_API_KEY;
    } elseif ($providerCode === 'gemini') {
        $apiKey = GEMINI_API_KEY;
    } elseif ($providerCode === 'ollama') {
        $apiKey = 'ollama-no-key'; // Ollama doesn't require an API key
    } elseif ($providerCode === 'claude_cli') {
        $apiKey = 'cli-no-key'; // Claude Code CLI uses local OAuth credentials, not an API key
    }

    if (!$apiKey) {
        return ['success' => false, 'error' => "System API key not configured for provider '{$provider['provider_name']}'. Please contact administrator."];
    }

    // Make request
    $manager = new AIProviderManager(
        $provider['provider_code'],
        $model['model_code'],
        $apiKey,
        $userId,
        $programId,
        $featureCode
    );

    return $manager->makeRequest($prompt, $options);
}

/**
 * Make request in passthrough mode (no user registration required)
 * Uses the program's passthrough_tier_id for model selection.
 * Skips per-user quota checks and user feature preferences.
 */
function ai_makeRequestPassthrough($params, $featureConfig, $programConfig) {
    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $prompt = $params['prompt'];
    $options = $params['options'] ?? [];

    aiCentral_logMessage("Passthrough mode for program $programId, user $userId", 'INFO');

    if ($budgetError = ai_checkProgramBudget($programConfig)) {
        return ['success' => false, 'error' => $budgetError];
    }

    // Get tier from program config
    $tierId = $programConfig['passthrough_tier_id'];
    $conn = ai_getDBConnection();
    $sql = "SELECT * FROM ai_tiers WHERE tier_id = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$tierId], 'i');
    $tier = $result ? $result->fetch_assoc() : null;

    if (!$tier) {
        return ['success' => false, 'error' => 'Passthrough tier not configured for program ' . $programId];
    }

    $tierCode = $tier['tier_code'];

    // Determine model based on tier + feature configuration
    $tierRow = ai_getFeatureTierRow($featureConfig['feature_id'], $tier['tier_id']);
    $modelProvider = $featureConfig['default_provider'] ?? null;
    $tierModelKey = "default_model_" . $tierCode;

    if (isset($featureConfig[$tierModelKey]) && !empty($featureConfig[$tierModelKey])) {
        $modelCode = $featureConfig[$tierModelKey];
        if (!empty($tierRow['provider_code'])) $modelProvider = $tierRow['provider_code'];
    } elseif (isset($featureConfig['default_model']) && !empty($featureConfig['default_model'])) {
        $modelCode = $featureConfig['default_model'];
    } else {
        return ['success' => false, 'error' => "Feature '$featureCode' has no model configured for tier '$tierCode'."];
    }

    // Get model. Use feature's default_provider for disambiguation when the
    // same model_code exists under multiple providers.
    $model = ai_getModelByCodeAndProvider($modelCode, $modelProvider);
    if (!$model) {
        return ['success' => false, 'error' => "Model '$modelCode' not found."];
    }

    // Get provider from the model
    $provider = ai_getProviderById($model['provider_id']);
    if (!$provider) {
        return ['success' => false, 'error' => "Provider not found for model '$modelCode'."];
    }

    // The feature's tier settings: max output tokens, effort, thinking
    ai_applyTierSettings($options, $tierRow, $model);


    // Get system API key
    $apiKey = null;
    $providerCode = $provider['provider_code'];

    if ($providerCode === 'claude') {
        $apiKey = ANTHROPIC_API_KEY;
    } elseif ($providerCode === 'openai') {
        $apiKey = OPENAI_API_KEY;
    } elseif ($providerCode === 'kimi') {
        $apiKey = KIMIK2_API_KEY;
    } elseif ($providerCode === 'grok') {
        $apiKey = GROK_API_KEY;
    } elseif ($providerCode === 'gemini') {
        $apiKey = GEMINI_API_KEY;
    } elseif ($providerCode === 'ollama') {
        $apiKey = 'ollama-no-key'; // Ollama doesn't require an API key
    } elseif ($providerCode === 'claude_cli') {
        $apiKey = 'cli-no-key'; // Claude Code CLI uses local OAuth credentials, not an API key
    }

    if (!$apiKey) {
        return ['success' => false, 'error' => "System API key not configured for provider '{$provider['provider_name']}'."];
    }

    // Make request
    $manager = new AIProviderManager(
        $provider['provider_code'],
        $model['model_code'],
        $apiKey,
        $userId,
        $programId,
        $featureCode
    );

    return $manager->makeRequest($prompt, $options);
}

/**
 * Dollars spent through AI Central this calendar month: by a program, or by one user in it.
 */
function ai_monthSpend($programId, $userId = null) {
    $conn = ai_getDBConnection();
    $sql = "SELECT COALESCE(SUM(total_cost_usd), 0) AS spent FROM ai_usage_log
            WHERE program_id = ? AND request_timestamp >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    if ($userId === null) {
        $result = aiCentral_executeQuery($conn, $sql, [$programId], 's');
    } else {
        $result = aiCentral_executeQuery($conn, $sql . " AND user_id = ?", [$programId, $userId], 'ss');
    }
    return $result ? (float)$result->fetch_assoc()['spent'] : 0.0;
}

/**
 * The program's monthly budget (programs.monthly_spend_limit_usd; NULL = none), for every request
 * paid with the system keys, passthrough included.
 *
 * @return string|null Error message when the budget is used up, else null
 */
function ai_checkProgramBudget($programConfig) {
    $limit = $programConfig['monthly_spend_limit_usd'] ?? null;
    if ($limit === null || $limit === '') {
        return null;
    }
    $spent = ai_monthSpend($programConfig['program_id']);
    if ($spent >= (float)$limit) {
        aiCentral_logMessage("Program {$programConfig['program_id']} reached its monthly AI budget: \$" . round($spent, 2) . " of \$$limit", 'WARNING');
        return 'The monthly AI budget for this app has been reached. Please try again next month or ask the administrator.';
    }
    return null;
}

/**
 * Check if user is within quota
 */
function ai_checkQuota($userId, $programId) {
    // The program's own monthly budget comes first: it applies on every tier
    $programConfig = ai_getProgramConfig($programId);
    if ($programConfig && ($budgetError = ai_checkProgramBudget($programConfig))) {
        return ['allowed' => false, 'message' => $budgetError];
    }

    // Get user's tier
    $tier = ai_getUserTier($userId, $programId);
    if (!$tier) {
        return ['allowed' => false, 'message' => 'No tier assigned'];
    }

    // If unlimited tier, allow
    if ($tier['tier_code'] === 'unlimited') {
        return ['allowed' => true];
    }

    $conn = ai_getDBConnection();

    // Check daily request limit
    if ($tier['daily_request_limit']) {
        $sql = "SELECT COUNT(*) as count FROM ai_usage_log
                WHERE user_id = ? AND program_id = ? AND DATE(request_timestamp) = CURDATE() AND status = 'success'";
        $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId], 'ss');
        $row = $result->fetch_assoc();
        $dailyCount = $row['count'];

        $limit = $tier['daily_request_limit'];
        $graceLimit = $limit * (1 + QUOTA_GRACE_PERCENT / 100);
        $hardLimit = $limit * (QUOTA_HARD_STOP_PERCENT / 100);

        if ($dailyCount >= $hardLimit) {
            return ['allowed' => false, 'message' => 'Daily request limit exceeded. Please upgrade your tier or use your own API key.'];
        } elseif ($dailyCount >= $graceLimit) {
            aiCentral_logMessage("User $userId approaching daily limit: $dailyCount / $limit", 'WARNING');
        }
    }

    // Check monthly request limit
    if ($tier['monthly_request_limit']) {
        $sql = "SELECT COUNT(*) as count FROM ai_usage_log
                WHERE user_id = ? AND program_id = ? AND YEAR(request_timestamp) = YEAR(CURDATE()) AND MONTH(request_timestamp) = MONTH(CURDATE()) AND status = 'success'";
        $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId], 'ss');
        $row = $result->fetch_assoc();
        $monthlyCount = $row['count'];

        $limit = $tier['monthly_request_limit'];
        $hardLimit = $limit * (QUOTA_HARD_STOP_PERCENT / 100);

        if ($monthlyCount >= $hardLimit) {
            return ['allowed' => false, 'message' => 'Monthly request limit exceeded. Please upgrade your tier or use your own API key.'];
        }
    }

    // Check monthly spend limit (this user, this program)
    if ($tier['monthly_spend_limit_usd'] !== null && $tier['monthly_spend_limit_usd'] !== '') {
        $limit = (float)$tier['monthly_spend_limit_usd'];
        $hardLimit = $limit * (QUOTA_HARD_STOP_PERCENT / 100);
        if (ai_monthSpend($programId, $userId) >= $hardLimit) {
            return ['allowed' => false, 'message' => 'Monthly AI spending limit reached. Please upgrade your tier or use your own API key.'];
        }
    }

    return ['allowed' => true];
}

/**
 * Get available models for user
 */
function ai_getAvailableModels($userId, $programId) {
    $tier = ai_getUserTier($userId, $programId);
    if (!$tier) {
        return [];
    }

    return ai_getModelsForTier($tier['tier_id']);
}

/**
 * Test if an API key works
 */
function ai_testKey($provider, $model, $apiKey) {
    $keyManager = new KeyManager();
    return $keyManager->testKey($provider, $model, $apiKey);
}

/**
 * Generate embeddings via AI Central
 *
 * Routes embedding requests through the configured provider (OpenAI or Ollama).
 * Both providers use OpenAI-compatible /v1/embeddings API format.
 *
 * @param array $params [
 *   'user_id' => string (required),
 *   'program_id' => string (required),
 *   'feature_code' => string (required),
 *   'input' => string|array (required - text or array of texts to embed),
 *   'options' => array (optional: dimensions, run_id, metadata)
 * ]
 * @return array ['success' => bool, 'embeddings' => array, 'model' => model_code used, 'usage' => array, 'cost' => array, 'error' => string|null]
 */
function ai_generateEmbedding($params) {
    if (!isset($params['user_id']) || !isset($params['program_id']) || !isset($params['feature_code']) || !isset($params['input'])) {
        return ['success' => false, 'error' => 'Missing required parameters (user_id, program_id, feature_code, input)'];
    }

    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $input = $params['input'];
    $options = $params['options'] ?? [];

    aiCentral_logMessage("Embedding request from user: $userId, program: $programId, feature: $featureCode", 'INFO');

    try {
        // Get feature configuration
        $featureConfig = ai_getFeatureConfig($programId, $featureCode);
        if (!$featureConfig) {
            return ['success' => false, 'error' => 'Feature not found: ' . $featureCode];
        }

        // Check if program uses passthrough mode
        $programConfig = ai_getProgramConfig($programId);
        $isPassthrough = $programConfig && $programConfig['passthrough_mode'];

        // Determine model
        $modelCode = null;

        if ($isPassthrough) {
            if ($budgetError = ai_checkProgramBudget($programConfig)) {
                return ['success' => false, 'error' => $budgetError];
            }
            // Passthrough: use program's tier for model selection
            $tierId = $programConfig['passthrough_tier_id'];
            $conn = ai_getDBConnection();
            $sql = "SELECT * FROM ai_tiers WHERE tier_id = ?";
            $result = aiCentral_executeQuery($conn, $sql, [$tierId], 'i');
            $tier = $result ? $result->fetch_assoc() : null;
            $tierCode = $tier ? $tier['tier_code'] : 'free';
        } else {
            // Normal: use user's tier
            $tier = ai_getUserTier($userId, $programId);
            if (!$tier) {
                return ['success' => false, 'error' => 'No tier assigned to user'];
            }
            $tierCode = $tier['tier_code'];

            // Check quota
            $quotaCheck = ai_checkQuota($userId, $programId);
            if (!$quotaCheck['allowed']) {
                return ['success' => false, 'error' => $quotaCheck['message']];
            }
        }

        // Resolve model from feature config + tier
        $tierRow = !empty($tier['tier_id']) ? ai_getFeatureTierRow($featureConfig['feature_id'], $tier['tier_id']) : null;
        $modelProvider = $featureConfig['default_provider'] ?? null;
        $tierModelKey = "default_model_" . $tierCode;
        if (isset($featureConfig[$tierModelKey]) && !empty($featureConfig[$tierModelKey])) {
            $modelCode = $featureConfig[$tierModelKey];
            if (!empty($tierRow['provider_code'])) $modelProvider = $tierRow['provider_code'];
        } elseif (isset($featureConfig['default_model']) && !empty($featureConfig['default_model'])) {
            $modelCode = $featureConfig['default_model'];
        } else {
            return ['success' => false, 'error' => "No embedding model configured for feature '$featureCode' tier '$tierCode'"];
        }

        // Get model details
        $model = ai_getModelByCodeAndProvider($modelCode, $modelProvider);
        if (!$model) {
            return ['success' => false, 'error' => "Model '$modelCode' not found"];
        }

        // Get provider
        $provider = ai_getProviderById($model['provider_id']);
        if (!$provider) {
            return ['success' => false, 'error' => "Provider not found for model '$modelCode'"];
        }

        // Get API key
        $apiKey = null;
        $providerCode = $provider['provider_code'];

        if ($providerCode === 'openai') {
            $apiKey = OPENAI_API_KEY;
        } elseif ($providerCode === 'ollama') {
            $apiKey = 'ollama'; // Ollama uses "ollama" as dummy key for OpenAI-compat endpoint
        }

        if (!$apiKey) {
            return ['success' => false, 'error' => "No API key configured for provider '$providerCode'"];
        }

        // Make embedding request via AIProviderManager
        $manager = new AIProviderManager(
            $providerCode,
            $modelCode,
            $apiKey,
            $userId,
            $programId,
            $featureCode
        );

        return $manager->makeEmbeddingRequest($input, $options);

    } catch (Exception $e) {
        aiCentral_logMessage("Embedding request error: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Make an audio transcription request via the OpenAI transcription API.
 *
 * Uses AI Central for API key management and usage logging, same as
 * ai_makeRequest() does for chat completions. The model is the feature's
 * default_model; its price is ai_models.input_cost_per_million, which for
 * audio models holds USD per 1,000 minutes of audio.
 *
 * @param array $params Required keys: user_id, program_id, feature_code, audio_file_path
 *                      Optional: audio_mime (default 'audio/webm'), audio_filename, language (default 'en'; null = detect),
 *                      timeout (seconds, default 30; long recordings need more)
 * @return array ['success' => bool, 'text' => string, 'cost' => array, 'error' => string|null]
 */
function ai_makeTranscriptionRequest($params) {
    if (!isset($params['user_id']) || !isset($params['program_id']) || !isset($params['feature_code']) || !isset($params['audio_file_path'])) {
        return ['success' => false, 'error' => 'Missing required parameters (user_id, program_id, feature_code, audio_file_path)'];
    }

    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $audioFilePath = $params['audio_file_path'];
    $audioMime = $params['audio_mime'] ?? 'audio/webm';
    $audioFilename = $params['audio_filename'] ?? 'recording.webm';
    $language = array_key_exists('language', $params) ? $params['language'] : 'en';
    $timeout = (int)($params['timeout'] ?? 30);
    $model = null;

    aiCentral_logMessage("Transcription request from user: $userId, program: $programId, feature: $featureCode", 'INFO');

    $startTime = microtime(true);

    try {
        $setup = ai_audioFeatureSetup($userId, $programId, $featureCode);
        if (!empty($setup['error'])) {
            return ['success' => false, 'error' => $setup['error']];
        }
        $model = $setup['model'];
        $modelCode = $model['model_code'];
        $apiKey = $setup['api_key'];

        // whisper models take a single 'language'; newer transcription models take a 'languages' list
        $fields = [
            'file' => new CURLFile($audioFilePath, $audioMime, $audioFilename),
            'model' => $modelCode
        ];
        if (strpos($modelCode, 'whisper') === 0) {
            if ($language) $fields['language'] = $language;
            $fields['response_format'] = 'json';
        } elseif ($language) {
            $fields['languages[]'] = $language;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey
            ]
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        if ($curlError) {
            aiCentral_logMessage("Transcription cURL error ($modelCode): $curlError", 'ERROR');
            ai_logAudioUsage($userId, $programId, $featureCode, $model, ['type' => 'transcription'], 0, $responseTime, 'failed', "cURL error: $curlError");
            return ['success' => false, 'error' => 'Network error. Please try again.'];
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown API error';
            aiCentral_logMessage("Transcription API error ($modelCode, $httpCode): $errorMsg", 'ERROR');
            ai_logAudioUsage($userId, $programId, $featureCode, $model, ['type' => 'transcription'], 0, $responseTime, 'failed', "API error ($httpCode): $errorMsg");
            return ['success' => false, 'error' => 'Transcription service unavailable.'];
        }

        $result = json_decode($response, true);
        $transcribedText = $result['text'] ?? '';

        // Cost per minute from the model's price. Use the audio length the API reports;
        // otherwise estimate it from the file size (~16KB/sec for webm/opus).
        $audioSize = filesize($audioFilePath) ?: 0;
        $seconds = (float)($result['usage']['seconds'] ?? $result['duration'] ?? 0);
        if ($seconds <= 0) $seconds = max(1, $audioSize / 16000);
        $costPerMinute = (float)$model['input_cost_per_million'] / 1000;
        $estimatedCost = round(($seconds / 60) * $costPerMinute, 6);

        // Log success to AI Central
        ai_logAudioUsage($userId, $programId, $featureCode, $model, ['type' => 'transcription', 'audio_size_bytes' => $audioSize, 'seconds' => $seconds], $estimatedCost, $responseTime, 'success', null);

        aiCentral_logMessage("Transcription success ($modelCode): " . strlen($transcribedText) . " chars, cost ~\$$estimatedCost, user: $userId", 'INFO');

        return [
            'success' => true,
            'text' => $transcribedText,
            'cost' => ['total_cost' => $estimatedCost],
            'responseTime' => $responseTime,
            'error' => null
        ];

    } catch (Exception $e) {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        aiCentral_logMessage("Transcription exception: " . $e->getMessage(), 'ERROR');
        ai_logAudioUsage($userId, $programId, $featureCode, $model, ['type' => 'transcription'], 0, $responseTime, 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Text to speech via the OpenAI speech API, with AI Central's key and usage log.
 * The model is the feature's default_model; its input_cost_per_million is USD per 1M characters.
 *
 * @param array $params Required keys: user_id, program_id, feature_code, text
 *                      Optional: voice (default 'alloy'), format (mp3|opus|aac|flac|wav|pcm, default 'mp3'),
 *                      instructions (how to speak; gpt-4o-mini-tts and later), metadata
 * @return array ['success' => bool, 'audio' => binary string, 'mime' => string, 'cost' => array, 'error' => string|null]
 */
function ai_makeSpeechRequest($params) {
    if (!isset($params['user_id']) || !isset($params['program_id']) || !isset($params['feature_code']) || !isset($params['text'])) {
        return ['success' => false, 'error' => 'Missing required parameters (user_id, program_id, feature_code, text)'];
    }

    $userId = $params['user_id'];
    $programId = $params['program_id'];
    $featureCode = $params['feature_code'];
    $text = (string)$params['text'];
    $format = $params['format'] ?? 'mp3';
    $model = null;
    $details = array_merge((array)($params['metadata'] ?? []), ['type' => 'speech', 'characters' => mb_strlen($text)]);

    aiCentral_logMessage("Speech request from user: $userId, program: $programId, feature: $featureCode", 'INFO');
    $startTime = microtime(true);

    try {
        $setup = ai_audioFeatureSetup($userId, $programId, $featureCode);
        if (!empty($setup['error'])) {
            return ['success' => false, 'error' => $setup['error']];
        }
        $model = $setup['model'];

        $body = ['model' => $model['model_code'], 'input' => $text, 'voice' => $params['voice'] ?? 'alloy', 'response_format' => $format];
        if (!empty($params['instructions'])) {
            $body['instructions'] = $params['instructions'];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/speech',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $setup['api_key'],
                'Content-Type: application/json'
            ]
        ]);
        $audio = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: 'audio/mpeg';
        $curlError = curl_error($curl);
        curl_close($curl);

        $responseTime = (int)((microtime(true) - $startTime) * 1000);

        if ($curlError || $httpCode !== 200) {
            $errorData = $curlError ? null : json_decode($audio, true);
            $errorMsg = $curlError ? "cURL error: $curlError" : "API error ($httpCode): " . ($errorData['error']['message'] ?? 'Unknown API error');
            aiCentral_logMessage("Speech {$model['model_code']}: $errorMsg", 'ERROR');
            ai_logAudioUsage($userId, $programId, $featureCode, $model, $details, 0, $responseTime, 'failed', $errorMsg);
            return ['success' => false, 'error' => 'Speech service unavailable.'];
        }

        $cost = round($details['characters'] * (float)$model['input_cost_per_million'] / 1000000, 6);
        ai_logAudioUsage($userId, $programId, $featureCode, $model, $details, $cost, $responseTime, 'success', null);

        return ['success' => true, 'audio' => $audio, 'mime' => $mime, 'cost' => ['total_cost' => $cost], 'responseTime' => $responseTime, 'error' => null];

    } catch (Exception $e) {
        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        aiCentral_logMessage("Speech exception: " . $e->getMessage(), 'ERROR');
        ai_logAudioUsage($userId, $programId, $featureCode, $model, $details, 0, $responseTime, 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * The model and key an audio feature (transcription, speech) runs on, after the quota check.
 * The model is the feature's default_model; audio is OpenAI only.
 *
 * @return array ['model' => ai_models row, 'api_key' => string] or ['error' => string]
 */
function ai_audioFeatureSetup($userId, $programId, $featureCode) {
    $featureConfig = ai_getFeatureConfig($programId, $featureCode);
    if (!$featureConfig) {
        return ['error' => 'Feature not found: ' . $featureCode];
    }
    if (empty($featureConfig['default_model'])) {
        return ['error' => "Feature '$featureCode' has no audio model configured."];
    }
    $model = ai_getModelByCodeAndProvider($featureConfig['default_model'], $featureConfig['default_provider'] ?? null);
    if (!$model) {
        return ['error' => "Model '{$featureConfig['default_model']}' not found in database."];
    }

    $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
    if (empty($apiKey)) {
        return ['error' => 'OpenAI API key not configured'];
    }

    // Check quota (normal mode) or the program's budget (passthrough)
    $programConfig = ai_getProgramConfig($programId);
    if (!$programConfig || !$programConfig['passthrough_mode']) {
        $quotaCheck = ai_checkQuota($userId, $programId);
        if (!$quotaCheck['allowed']) {
            return ['error' => $quotaCheck['message']];
        }
    } elseif ($budgetError = ai_checkProgramBudget($programConfig)) {
        return ['error' => $budgetError];
    }
    return ['model' => $model, 'api_key' => $apiKey];
}

/**
 * Log an audio request (transcription or speech) to ai_usage_log.
 * Simplified version of AIProviderManager::logUsage; the cost goes in input_cost_usd.
 * $model is the ai_models row used (null when the request failed before a model was chosen);
 * $details goes to request_metadata (type, audio size, characters, ...).
 */
function ai_logAudioUsage($userId, $programId, $featureCode, $model, array $details, $cost, $responseTime, $status, $error) {
    if (!$model) {
        aiCentral_logMessage("Audio request $status before a model was chosen: $error", 'ERROR');
        return;
    }
    $conn = ai_getDBConnection();
    if (!$conn) return;

    try {
        $sql = "INSERT INTO ai_usage_log (
            user_id, program_id, feature_code, provider_id, model_id,
            key_type, input_tokens, output_tokens, thinking_tokens,
            input_cost_usd, output_cost_usd, thinking_cost_usd,
            response_time_ms, status, error_message, request_metadata
        ) VALUES (?, ?, ?, ?, ?,
            'system', 0, 0, 0,
            ?, 0, 0,
            ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $providerId = $model['provider_id'] ?? null;
            $modelId = $model['model_id'] ?? null;
            $metadata = json_encode($details);
            $stmt->bind_param('sssiidisss',
                $userId, $programId, $featureCode,
                $providerId, $modelId,
                $cost,
                $responseTime, $status, $error, $metadata
            );
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        aiCentral_logMessage("Failed to log audio usage: " . $e->getMessage(), 'ERROR');
    }
}


?>
