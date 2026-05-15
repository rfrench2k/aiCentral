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
 *   'options' => array (optional: max_tokens, temperature, system, images, run_id, metadata)
 * ]
 * @return array ['success' => bool, 'response' => string, 'usage' => array, 'cost' => float, 'error' => string]
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
    $model = ai_getModelByCode($userPref['preferred_model']);
    if (!$model) {
        return ['success' => false, 'error' => 'Model not found'];
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
    $tierModelKey = "default_model_" . $tierCode;

    if (isset($featureConfig[$tierModelKey]) && !empty($featureConfig[$tierModelKey])) {
        $modelCode = $featureConfig[$tierModelKey];
        aiCentral_logMessage("Using tier-specific model for tier $tierCode: $modelCode", 'INFO');
    } elseif (isset($featureConfig['default_model']) && !empty($featureConfig['default_model'])) {
        $modelCode = $featureConfig['default_model'];
        aiCentral_logMessage("No tier-specific model for tier $tierCode, using feature default: $modelCode", 'INFO');
    } else {
        return ['success' => false, 'error' => "Feature '$featureCode' is not configured with a model for tier '$tierCode'. Please contact administrator."];
    }

    // Get model from database. When the feature pins a default_provider
    // (e.g. provider_code='claude_cli'), use that to disambiguate — the same
    // model_code can exist under multiple providers (one paid via API, one
    // zero-cost via CLI), and we need to pick the right one.
    $model = ai_getModelByCodeAndProvider($modelCode, $featureConfig['default_provider'] ?? null);
    if (!$model) {
        return ['success' => false, 'error' => "Model '$modelCode' not found in database. Please contact administrator."];
    }

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

    // Apply max_output_tokens from feature config if not overridden in options
    if (!isset($options['max_tokens']) && isset($featureConfig['max_output_tokens'])) {
        $options['max_tokens'] = $featureConfig['max_output_tokens'];
        aiCentral_logMessage("Using feature max_output_tokens: {$featureConfig['max_output_tokens']}", 'INFO');
    }

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
    $tierModelKey = "default_model_" . $tierCode;

    if (isset($featureConfig[$tierModelKey]) && !empty($featureConfig[$tierModelKey])) {
        $modelCode = $featureConfig[$tierModelKey];
    } elseif (isset($featureConfig['default_model']) && !empty($featureConfig['default_model'])) {
        $modelCode = $featureConfig['default_model'];
    } else {
        return ['success' => false, 'error' => "Feature '$featureCode' has no model configured for tier '$tierCode'."];
    }

    // Get model. Use feature's default_provider for disambiguation when the
    // same model_code exists under multiple providers.
    $model = ai_getModelByCodeAndProvider($modelCode, $featureConfig['default_provider'] ?? null);
    if (!$model) {
        return ['success' => false, 'error' => "Model '$modelCode' not found."];
    }

    // Get provider from the model
    $provider = ai_getProviderById($model['provider_id']);
    if (!$provider) {
        return ['success' => false, 'error' => "Provider not found for model '$modelCode'."];
    }

    // Apply max_output_tokens from feature config if not overridden
    if (!isset($options['max_tokens']) && isset($featureConfig['max_output_tokens'])) {
        $options['max_tokens'] = $featureConfig['max_output_tokens'];
    }

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
 * Check if user is within quota
 */
function ai_checkQuota($userId, $programId) {
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
 * @return array ['success' => bool, 'embeddings' => array, 'usage' => array, 'cost' => array, 'error' => string|null]
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
        $tierModelKey = "default_model_" . $tierCode;
        if (isset($featureConfig[$tierModelKey]) && !empty($featureConfig[$tierModelKey])) {
            $modelCode = $featureConfig[$tierModelKey];
        } elseif (isset($featureConfig['default_model']) && !empty($featureConfig['default_model'])) {
            $modelCode = $featureConfig['default_model'];
        } else {
            return ['success' => false, 'error' => "No embedding model configured for feature '$featureCode' tier '$tierCode'"];
        }

        // Get model details
        $model = ai_getModelByCode($modelCode);
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
 * Make an audio transcription request via OpenAI Whisper API.
 *
 * Uses AI Central for API key management and usage logging, same as
 * ai_makeRequest() does for chat completions.
 *
 * @param array $params Required keys: user_id, program_id, feature_code, audio_file_path
 *                      Optional: audio_mime (default 'audio/webm'), audio_filename, language (default 'en')
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
    $language = $params['language'] ?? 'en';

    aiCentral_logMessage("Transcription request from user: $userId, program: $programId, feature: $featureCode", 'INFO');

    $startTime = microtime(true);

    try {
        // Get feature configuration
        $featureConfig = ai_getFeatureConfig($programId, $featureCode);
        if (!$featureConfig) {
            return ['success' => false, 'error' => 'Feature not found: ' . $featureCode];
        }

        // Get API key — Whisper is OpenAI only
        $apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured'];
        }

        // Check quota for passthrough / normal mode
        $programConfig = ai_getProgramConfig($programId);
        if (!$programConfig || !$programConfig['passthrough_mode']) {
            $quotaCheck = ai_checkQuota($userId, $programId);
            if (!$quotaCheck['allowed']) {
                return ['success' => false, 'error' => $quotaCheck['message']];
            }
        }

        // Make Whisper API call
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($audioFilePath, $audioMime, $audioFilename),
                'model' => 'whisper-1',
                'language' => $language,
                'response_format' => 'json'
            ],
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
            aiCentral_logMessage("Whisper cURL error: $curlError", 'ERROR');
            ai_logTranscriptionUsage($userId, $programId, $featureCode, 0, 0, $responseTime, 'failed', "cURL error: $curlError");
            return ['success' => false, 'error' => 'Network error. Please try again.'];
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? 'Unknown API error';
            aiCentral_logMessage("Whisper API error ($httpCode): $errorMsg", 'ERROR');
            ai_logTranscriptionUsage($userId, $programId, $featureCode, 0, 0, $responseTime, 'failed', "API error ($httpCode): $errorMsg");
            return ['success' => false, 'error' => 'Transcription service unavailable.'];
        }

        $result = json_decode($response, true);
        $transcribedText = $result['text'] ?? '';

        // Whisper pricing: $0.006 per minute of audio. Estimate from file size (~16KB/sec for webm/opus).
        $audioSize = filesize($audioFilePath) ?: 0;
        $estimatedSeconds = max(1, $audioSize / 16000);
        $estimatedMinutes = $estimatedSeconds / 60;
        $estimatedCost = round($estimatedMinutes * 0.006, 6);

        // Log success to AI Central
        ai_logTranscriptionUsage($userId, $programId, $featureCode, $audioSize, $estimatedCost, $responseTime, 'success', null);

        aiCentral_logMessage("Transcription success: " . strlen($transcribedText) . " chars, cost ~\$$estimatedCost, user: $userId", 'INFO');

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
        ai_logTranscriptionUsage($userId, $programId, $featureCode, 0, 0, $responseTime, 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Log transcription usage to ai_usage_log.
 * Simplified version of AIProviderManager::logUsage for audio transcription.
 */
function ai_logTranscriptionUsage($userId, $programId, $featureCode, $audioSize, $cost, $responseTime, $status, $error) {
    $conn = ai_getDBConnection();
    if (!$conn) return;

    try {
        $sql = "INSERT INTO ai_usage_log (
            user_id, program_id, feature_code, provider_id, model_id,
            key_type, input_tokens, output_tokens, thinking_tokens,
            input_cost_usd, output_cost_usd, thinking_cost_usd,
            response_time_ms, status, error_message, request_metadata
        ) VALUES (?, ?, ?,
            (SELECT provider_id FROM ai_providers WHERE provider_code = 'openai' LIMIT 1),
            (SELECT model_id FROM ai_models WHERE model_code = 'whisper-1' LIMIT 1),
            'system', 0, 0, 0,
            ?, 0, 0,
            ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $metadata = json_encode(['audio_size_bytes' => $audioSize, 'type' => 'transcription']);
            $stmt->bind_param('sssdisss',
                $userId, $programId, $featureCode,
                $cost,
                $responseTime, $status, $error, $metadata
            );
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        aiCentral_logMessage("Failed to log transcription usage: " . $e->getMessage(), 'ERROR');
    }
}

?>
