<?php
require_once __DIR__ . '/baseFunctions.php';

/**
 * AI Central System - Common Functions
 * Database helper functions
 */

// aiCentral_logMessage() is now in baseFunctions.php

/**
 * Execute prepared statement with error handling
 * IMPORTANT: Always assign values to variables first, then bind
 * NEVER use inline expressions like $user['id'] ?? '' in bind_param()
 *
 * @param mysqli $conn Database connection
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind
 * @param string $types Types string (s=string, i=integer, d=double, b=blob)
 * @return mysqli_result|bool Query result or false on error
 */
function aiCentral_executeQuery($conn, $sql, $params = [], $types = '') {
    aiCentral_logMessage("Query: $sql | Params: " . json_encode($params) . " | Types: $types", 'DEBUG');

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        aiCentral_logMessage("Prepare failed: " . $conn->error, 'ERROR');
        return false;
    }

    if (!empty($params)) {
        // Count parameters
        $paramCount = count($params);
        $typeCount = strlen($types);

        if ($paramCount !== $typeCount) {
            aiCentral_logMessage("Parameter count mismatch: $paramCount params vs $typeCount types", 'ERROR');
            return false;
        }

        // Bind parameters
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        aiCentral_logMessage("Execute failed: " . $stmt->error, 'ERROR');
        return false;
    }

    $result = $stmt->get_result();

    if ($result === false) {
        aiCentral_logMessage("get_result() failed: " . $stmt->error . " (May be MySQLnd not available)", 'ERROR');
        $stmt->close();
        return false;
    }

    aiCentral_logMessage("Query successful, rows returned: " . $result->num_rows, 'DEBUG');
    $stmt->close();

    return $result;
}

/**
 * Execute non-SELECT query (INSERT, UPDATE, DELETE)
 *
 * @param mysqli $conn Database connection
 * @param string $sql SQL query with ? placeholders
 * @param array $params Parameters to bind
 * @param string $types Types string
 * @return int|bool Number of affected rows or false on error
 */
function aiCentral_executeUpdate($conn, $sql, $params = [], $types = '') {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        aiCentral_logMessage("Prepare failed: " . $conn->error, 'ERROR');
        return false;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        aiCentral_logMessage("Execute failed: " . $stmt->error, 'ERROR');
        return false;
    }

    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    return $affectedRows;
}

/**
 * Get last insert ID
 *
 * @param mysqli $conn Database connection
 * @return int Last insert ID
 */
function aiCentral_getLastInsertId($conn) {
    return $conn->insert_id;
}

// aiCentral_getAuthenticatedUser() wrapper function removed - auth now handled at top of file

/**
 * Calculate complete cost for an AI request
 *
 * @param string $modelCode Model code (e.g., 'claude-sonnet-4-5-20250929')
 * @param int $inputTokens Number of input tokens
 * @param int $outputTokens Number of output tokens
 * @param array $toolCalls Tool usage counts ['web_search' => 2, 'vision' => 1]
 * @param int $toolResultTokens Tokens in tool results (OpenAI dual billing)
 * @param int $thinkingTokens Extended thinking tokens (Claude Opus)
 * @return array Cost breakdown with all components
 */
function aiCentral_calculateRequestCost($modelCode, $inputTokens, $outputTokens, $toolCalls = [], $toolResultTokens = 0, $thinkingTokens = 0, $cacheReadTokens = 0, $cacheWrite5mTokens = 0, $cacheWrite1hTokens = 0) {
    $conn = ai_getDBConnection();

    // Get model pricing
    $sql = "SELECT input_cost_per_million, output_cost_per_million, thinking_cost_per_million, model_id
            FROM ai_models
            WHERE model_code = ? AND is_active = 1
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $modelCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        aiCentral_logMessage("Model not found for cost calculation: $modelCode", 'WARNING');
        return [
            'input_cost_usd' => 0.0,
            'output_cost_usd' => 0.0,
            'tool_call_cost_usd' => 0.0,
            'tool_result_cost_usd' => 0.0,
            'thinking_cost_usd' => 0.0,
            'total_cost_usd' => 0.0
        ];
    }

    $model = $result->fetch_assoc();
    $stmt->close();

    // Calculate token costs
    $inputCost = ($inputTokens / 1000000) * $model['input_cost_per_million'];

    // Prompt-cache costs, priced off the input rate: reads ~0.1x, 5-minute writes
    // ~1.25x, 1-hour writes ~2x. Folded into the input cost so per-row and summary
    // totals reflect the true charge.
    $inPerM = $model['input_cost_per_million'];
    $cacheCost = (($cacheReadTokens    / 1000000) * $inPerM * 0.10)
               + (($cacheWrite5mTokens / 1000000) * $inPerM * 1.25)
               + (($cacheWrite1hTokens / 1000000) * $inPerM * 2.00);
    $inputCost += $cacheCost;
    $outputCost = ($outputTokens / 1000000) * $model['output_cost_per_million'];
    $thinkingCost = $thinkingTokens > 0 ? ($thinkingTokens / 1000000) * ($model['thinking_cost_per_million'] ?? 0) : 0.0;
    $toolResultCost = $toolResultTokens > 0 ? ($toolResultTokens / 1000000) * $model['input_cost_per_million'] : 0.0;

    // Calculate tool call costs
    $toolCost = 0.0;
    if (!empty($toolCalls)) {
        foreach ($toolCalls as $capabilityCode => $count) {
            if ($count <= 0) continue;

            // Look up capability pricing for this model
            $capSql = "SELECT cost_per_use, cost_per_1000, is_free
                      FROM ai_model_capabilities
                      WHERE model_id = ? AND capability_code = ?
                      LIMIT 1";
            $capStmt = $conn->prepare($capSql);
            $capStmt->bind_param('is', $model['model_id'], $capabilityCode);
            $capStmt->execute();
            $capResult = $capStmt->get_result();

            if ($capResult->num_rows > 0) {
                $cap = $capResult->fetch_assoc();

                if ($cap['is_free']) {
                    // Free tool - no cost
                    continue;
                } elseif ($cap['cost_per_use'] > 0) {
                    // Per-use pricing (e.g., $0.01 per search)
                    $toolCost += $count * $cap['cost_per_use'];
                } elseif ($cap['cost_per_1000'] > 0) {
                    // Per-1000 pricing (e.g., $10 per 1000 searches)
                    $toolCost += ($count / 1000) * $cap['cost_per_1000'];
                }
            }

            $capStmt->close();
        }
    }

    $totalCost = $inputCost + $outputCost + $toolCost + $toolResultCost + $thinkingCost;

    return [
        'input_cost_usd' => round($inputCost, 6),
        'output_cost_usd' => round($outputCost, 6),
        'tool_call_cost_usd' => round($toolCost, 6),
        'tool_result_cost_usd' => round($toolResultCost, 6),
        'thinking_cost_usd' => round($thinkingCost, 6),
        'cache_cost_usd' => round($cacheCost, 6),
        'total_cost_usd' => round($totalCost, 6)
    ];
}

/**
 * Get lookup values from admin_lookups table
 * @param string|null $lookupName Optional - filter by lookup name (e.g., 'feature_type')
 * @return array Array of lookup values
 */
function aiCentral_getLookups($lookupName = null) {
    $conn = ai_getDBConnection();

    if ($lookupName) {
        $sql = "SELECT * FROM admin_lookups
                WHERE LOOKUP_NAME = ?
                ORDER BY LOOKUP_ORDER, LOOKUP_VALUE";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $lookupName);
    } else {
        $sql = "SELECT * FROM admin_lookups
                ORDER BY LOOKUP_NAME, LOOKUP_ORDER, LOOKUP_VALUE";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $lookups = [];
    while ($row = $result->fetch_assoc()) {
        $lookups[] = [
            'id' => $row['ID'],
            'lookup_name' => $row['LOOKUP_NAME'],
            'lookup_value' => $row['LOOKUP_VALUE'],
            'lookup_order' => $row['LOOKUP_ORDER'],
            'lookup_desc' => $row['LOOKUP_DESC']
        ];
    }

    $stmt->close();
    return $lookups;
}

?>
