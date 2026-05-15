<?php
/**
 * AI Model Comparison Tool - Backend API
 * Handles all comparison operations
 */

// SECURITY: Require ADMIN-level authentication
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

$user = auth_getAuthenticatedUser('AI');
if (!$user || !in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Model Comparison API: action=$action, user={$user['user_id']}", 'INFO');

// JSON response for all actions
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'getOriginalRequest':
            getOriginalRequest();
            break;

        case 'getAvailableModels':
            getAvailableModels();
            break;

        case 'estimateCost':
            estimateCost();
            break;

        case 'runComparison':
            runComparison();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Model Comparison API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Task 1.3: Get original request from usage_id
 */
function getOriginalRequest() {
    $conn = ai_getDBConnection();

    $usageId = (int)($_REQUEST['usageId'] ?? 0);

    if ($usageId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid usage ID']);
        return;
    }

    aiCentral_logMessage("Getting original request for usage_id=$usageId", 'DEBUG');

    $sql = "SELECT
                ul.usage_id,
                ul.user_id,
                ul.program_id,
                ul.feature_code,
                ul.page_url,
                ul.provider_id,
                ul.model_id,
                ul.input_tokens,
                ul.output_tokens,
                ul.total_tokens,
                ul.input_cost_usd,
                ul.output_cost_usd,
                ul.tool_call_cost_usd,
                ul.tool_result_cost_usd,
                ul.thinking_cost_usd,
                ul.total_cost_usd,
                ul.response_time_ms,
                ul.tool_call_count,
                ul.tool_calls_json,
                ul.prompt_text,
                ul.response_text,
                ul.prompt_to_ai,
                ul.complete_ai_response,
                ul.request_metadata,
                p.provider_name,
                p.provider_code,
                m.model_display_name,
                m.model_code
            FROM ai_usage_log ul
            JOIN ai_providers p ON ul.provider_id = p.provider_id
            JOIN ai_models m ON ul.model_id = m.model_id
            WHERE ul.usage_id = ?
            LIMIT 1";

    $result = aiCentral_executeQuery($conn, $sql, [$usageId], 'i');

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch usage record']);
        return;
    }

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Usage record not found']);
        return;
    }

    $record = $result->fetch_assoc();

    aiCentral_logMessage("Original request loaded successfully for usage_id=$usageId", 'INFO');

    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
}

/**
 * Task 1.4: Get available models grouped by provider
 */
function getAvailableModels() {
    $conn = ai_getDBConnection();

    aiCentral_logMessage("Getting available models for comparison", 'DEBUG');

    $sql = "SELECT
                m.model_id,
                m.model_code,
                m.model_display_name,
                m.model_tier,
                m.input_cost_per_million,
                m.output_cost_per_million,
                m.context_window,
                m.supports_vision,
                m.supports_function_calling,
                p.provider_id,
                p.provider_name,
                p.provider_code
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.is_active = 1
            AND p.is_active = 1
            ORDER BY p.provider_name, m.sort_order, m.model_display_name";

    $result = aiCentral_executeQuery($conn, $sql, [], '');

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch models']);
        return;
    }

    // Group models by provider
    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providerCode = $row['provider_code'];

        if (!isset($providers[$providerCode])) {
            $providers[$providerCode] = [
                'provider_id' => $row['provider_id'],
                'provider_name' => $row['provider_name'],
                'provider_code' => $row['provider_code'],
                'models' => []
            ];
        }

        $providers[$providerCode]['models'][] = [
            'model_id' => $row['model_id'],
            'model_code' => $row['model_code'],
            'model_display_name' => $row['model_display_name'],
            'model_tier' => $row['model_tier'],
            'input_cost_per_million' => $row['input_cost_per_million'],
            'output_cost_per_million' => $row['output_cost_per_million'],
            'context_window' => $row['context_window'],
            'supports_vision' => (bool)$row['supports_vision'],
            'supports_function_calling' => (bool)$row['supports_function_calling']
        ];
    }

    // Convert to indexed array
    $providersArray = array_values($providers);

    aiCentral_logMessage("Loaded " . count($providersArray) . " providers with models", 'INFO');

    echo json_encode([
        'success' => true,
        'providers' => $providersArray
    ]);
}

/**
 * Task 1.5: Estimate cost for selected models - REAL ESTIMATE
 */
function estimateCost() {
    $conn = ai_getDBConnection();

    $modelIds = $_REQUEST['modelIds'] ?? [];
    $inputTokens = (int)($_REQUEST['inputTokens'] ?? 0);
    $outputTokens = (int)($_REQUEST['outputTokens'] ?? 0);

    if (empty($modelIds)) {
        echo json_encode(['success' => false, 'error' => 'No models selected']);
        return;
    }

    if (!is_array($modelIds)) {
        $modelIds = [$modelIds];
    }

    // Validate max 4 models
    if (count($modelIds) > 4) {
        echo json_encode(['success' => false, 'error' => 'Maximum 4 models allowed']);
        return;
    }

    aiCentral_logMessage("Estimating cost for " . count($modelIds) . " models, input=$inputTokens, output=$outputTokens", 'DEBUG');

    // Build query with placeholders
    $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
    $sql = "SELECT
                model_id,
                model_display_name,
                input_cost_per_million,
                output_cost_per_million
            FROM ai_models
            WHERE model_id IN ($placeholders)
            AND is_active = 1";

    // Create types string (all integers)
    $types = str_repeat('i', count($modelIds));

    $result = aiCentral_executeQuery($conn, $sql, $modelIds, $types);

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch model pricing']);
        return;
    }

    $estimates = [];
    $totalCost = 0.0;

    while ($row = $result->fetch_assoc()) {
        // REAL estimate using actual input/output token counts from original request
        $estimatedInputCost = ($inputTokens / 1000000) * $row['input_cost_per_million'];
        $estimatedOutputCost = ($outputTokens / 1000000) * $row['output_cost_per_million'];
        $estimatedTotal = $estimatedInputCost + $estimatedOutputCost;

        $estimates[] = [
            'model_id' => $row['model_id'],
            'model_display_name' => $row['model_display_name'],
            'estimated_cost' => round($estimatedTotal, 6)
        ];

        $totalCost += $estimatedTotal;
    }

    aiCentral_logMessage("Cost estimate: $" . round($totalCost, 6) . " for " . count($estimates) . " models (input=$inputTokens, output=$outputTokens)", 'INFO');

    echo json_encode([
        'success' => true,
        'estimates' => $estimates,
        'total_cost' => round($totalCost, 6),
        'warning' => $totalCost > 0.50
    ]);
}

/**
 * Task 2.1-2.7: Run comparison across multiple models
 * Executes prompt against selected models in parallel, logs results, calculates costs
 */
function runComparison() {
    global $user;
    $conn = ai_getDBConnection();

    // Get parameters
    $modelIds = $_REQUEST['modelIds'] ?? [];
    $prompt = $_REQUEST['prompt'] ?? '';
    $originalUsageId = (int)($_REQUEST['originalUsageId'] ?? 0);
    $promptEdited = (bool)($_REQUEST['promptEdited'] ?? false);

    // NEW: Get extracted parameters from original request
    $systemPrompt = $_REQUEST['systemPrompt'] ?? null;
    $temperature = (float)($_REQUEST['temperature'] ?? 0.7);
    $maxTokens = (int)($_REQUEST['maxTokens'] ?? 4096);

    // Parse capabilities JSON if provided
    $capabilities = [];
    if (!empty($_REQUEST['capabilities'])) {
        $capabilitiesJson = $_REQUEST['capabilities'];
        $capabilities = json_decode($capabilitiesJson, true);
        if (!is_array($capabilities)) {
            $capabilities = [];
        }
    }

    aiCentral_logMessage("runComparison: systemPrompt=" . ($systemPrompt ? 'yes' : 'no') . ", capabilities=" . json_encode($capabilities) . ", temp=$temperature, maxTokens=$maxTokens", 'DEBUG');

    // Validation
    if (empty($modelIds)) {
        echo json_encode(['success' => false, 'error' => 'No models selected']);
        return;
    }

    if (!is_array($modelIds)) {
        $modelIds = [$modelIds];
    }

    if (count($modelIds) > 4) {
        echo json_encode(['success' => false, 'error' => 'Maximum 4 models allowed']);
        return;
    }

    if (empty($prompt)) {
        echo json_encode(['success' => false, 'error' => 'No prompt provided']);
        return;
    }

    // Generate comparison group ID
    $comparisonGroupId = 'comp_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);

    aiCentral_logMessage("Starting comparison: group_id=$comparisonGroupId, models=" . count($modelIds), 'INFO');

    // Load model details for all selected models
    $placeholders = implode(',', array_fill(0, count($modelIds), '?'));
    $sql = "SELECT
                m.model_id,
                m.model_code,
                m.model_display_name,
                m.processor_class,
                m.max_tokens,
                p.provider_id,
                p.provider_code,
                p.provider_name
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.model_id IN ($placeholders)
            AND m.is_active = 1
            AND p.is_active = 1";

    $types = str_repeat('i', count($modelIds));
    $result = aiCentral_executeQuery($conn, $sql, $modelIds, $types);

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No valid models found']);
        return;
    }

    $models = [];
    while ($row = $result->fetch_assoc()) {
        $models[$row['model_id']] = $row;
    }

    // Include API key constants
    require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_API.php';

    // Execute all models - using sequential execution (simpler than curl_multi for now)
    // In production, this could be optimized with parallel execution
    $results = [];

    require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/AIProviderManager.php';

    foreach ($models as $modelId => $modelData) {
        $startTime = microtime(true);

        try {
            aiCentral_logMessage("Executing model: {$modelData['model_display_name']}", 'DEBUG');

            $providerCode = $modelData['provider_code'];
            $modelCode = $modelData['model_code'];

            // Get system API key based on provider code (same as ai_functions.php)
            $apiKey = null;
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
            }

            if (!$apiKey) {
                throw new Exception("System API key not configured for provider: {$providerCode}");
            }

            // Create AI Provider Manager
            $aiManager = new AIProviderManager(
                $providerCode,
                $modelCode,
                $apiKey,
                $user['user_id'],
                'AI',
                'model_comparison'
            );

            // Prepare options with metadata and extracted parameters
            $options = [
                'max_tokens' => $maxTokens, // Use extracted value
                'temperature' => $temperature, // Use extracted value
                'run_id' => $comparisonGroupId . '_' . $modelId,
                'metadata' => [
                    'comparison_group_id' => $comparisonGroupId,
                    'original_usage_id' => $originalUsageId,
                    'prompt_edited' => $promptEdited,
                    'selected_models' => $modelIds,
                    'comparison_mode' => 'parallel',
                    'initiated_by' => $user['user_id']
                ]
            ];

            // Add system prompt if provided
            if ($systemPrompt) {
                $options['system'] = $systemPrompt;
            }

            // Add capabilities if provided
            if (!empty($capabilities)) {
                $options['capabilities'] = $capabilities;
                aiCentral_logMessage("Adding capabilities to model {$modelData['model_display_name']}: " . json_encode($capabilities), 'DEBUG');
            }

            // Make request
            $apiResult = $aiManager->makeRequest($prompt, $options);

            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // Format response type detection
            $responseType = detectResponseType($apiResult['response'] ?? '');

            // Build result
            $results[] = [
                'model_id' => $modelId,
                'model_display_name' => $modelData['model_display_name'],
                'provider_name' => $modelData['provider_name'],
                'success' => $apiResult['success'],
                'response' => $apiResult['response'],
                'response_type' => $responseType,
                'usage' => $apiResult['usage'],
                'cost' => $apiResult['cost'],
                'tool_calls' => $apiResult['tool_calls'] ?? [],
                'response_time_ms' => $responseTime,
                'error' => $apiResult['error']
            ];

            aiCentral_logMessage("Model {$modelData['model_display_name']} completed: success={$apiResult['success']}, time={$responseTime}ms", 'INFO');

        } catch (Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            aiCentral_logMessage("Model {$modelData['model_display_name']} failed: {$e->getMessage()}", 'ERROR');

            $results[] = [
                'model_id' => $modelId,
                'model_display_name' => $modelData['model_display_name'],
                'provider_name' => $modelData['provider_name'],
                'success' => false,
                'response' => null,
                'response_type' => 'text',
                'usage' => null,
                'cost' => ['total_cost' => 0],
                'tool_calls' => [],
                'response_time_ms' => $responseTime,
                'error' => 'Internal error'
            ];
        }
    }

    aiCentral_logMessage("Comparison completed: group_id=$comparisonGroupId, results=" . count($results), 'INFO');

    echo json_encode([
        'success' => true,
        'comparison_group_id' => $comparisonGroupId,
        'results' => $results
    ]);
}


/**
 * Task 2.6: Detect response type (JSON array/object/text)
 */
function detectResponseType($response) {
    if (empty($response)) {
        return 'text';
    }

    // First, try to parse as pure JSON
    $decoded = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        // Valid JSON
        if (is_array($decoded)) {
            // Check if it's an array of objects (table data)
            if (isset($decoded[0]) && is_array($decoded[0])) {
                return 'json_array';
            }
            return 'json_object';
        }
        return 'json_object';
    }

    // Try to extract JSON from markdown code blocks
    if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\]|\{[\s\S]*?\})\s*```/', $response, $matches)) {
        $extracted = trim($matches[1]);
        $decoded = json_decode($extracted, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded[0]) && is_array($decoded[0])) {
                return 'json_array';
            }
            return 'json_object';
        }
    }

    // Try to find JSON array or object in the response
    if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/', $response, $matches)) {
        $extracted = trim($matches[1]);
        $decoded = json_decode($extracted, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded[0]) && is_array($decoded[0])) {
                return 'json_array';
            }
            return 'json_object';
        }
    }

    // Not valid JSON - plain text
    return 'text';
}

?>
