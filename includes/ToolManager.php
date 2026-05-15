<?php
/**
 * AI Central System - Tool Manager
 * Handles AI provider tool/capability configuration and usage tracking
 * Supports web_search, web_fetch, vision across different providers
 */

class ToolManager {
    private $providerCode;
    private $modelCode;
    private $modelId;

    /**
     * Constructor
     *
     * @param string $providerCode Provider code (claude, openai, gemini, etc)
     * @param string $modelCode Model code
     * @param int $modelId Model ID from database
     */
    public function __construct($providerCode, $modelCode, $modelId) {
        $this->providerCode = $providerCode;
        $this->modelCode = $modelCode;
        $this->modelId = $modelId;
    }

    /**
     * Build tools array for API request based on provider format
     *
     * @param array $capabilities Array of capability codes to enable (e.g. ['web_search', 'web_fetch'])
     * @param array $limits Array of max_uses limits keyed by capability code
     * @return array|null Tools array in provider-specific format, or null if provider doesn't support tools
     */
    public function buildToolsArray($capabilities, $limits = []) {
        if (empty($capabilities)) {
            return null;
        }

        switch ($this->providerCode) {
            case 'claude':
            case 'claude_cli':
                return $this->buildClaudeTools($capabilities, $limits);

            case 'openai':
                return $this->buildOpenAITools($capabilities, $limits);

            case 'gemini':
                return $this->buildGeminiTools($capabilities, $limits);

            case 'kimi':
                return $this->buildKimiTools($capabilities, $limits);

            case 'grok':
            default:
                // Provider doesn't support tools yet
                return null;
        }
    }

    /**
     * Build Claude-specific tools array
     * Format: tools array with type, name, and max_uses
     */
    private function buildClaudeTools($capabilities, $limits) {
        $tools = [];

        foreach ($capabilities as $capabilityCode) {
            $maxUses = $limits[$capabilityCode] ?? null;

            switch ($capabilityCode) {
                case 'web_search':
                    $tool = [
                        'type' => 'web_search_20250305',
                        'name' => 'web_search'
                    ];
                    if ($maxUses !== null) {
                        $tool['max_uses'] = (int)$maxUses;
                    }
                    $tools[] = $tool;
                    break;

                case 'web_fetch':
                    $tool = [
                        'type' => 'web_fetch',
                        'name' => 'web_fetch'
                    ];
                    if ($maxUses !== null) {
                        $tool['max_uses'] = (int)$maxUses;
                    }
                    $tools[] = $tool;
                    break;

                // Vision is handled separately via images parameter, not tools
                case 'vision':
                    // Skip - vision handled via images in message content
                    break;
            }
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Build OpenAI-specific tools array
     * Format: tools array with type (max_uses goes in prompt)
     */
    private function buildOpenAITools($capabilities, $limits) {
        $tools = [];

        foreach ($capabilities as $capabilityCode) {
            switch ($capabilityCode) {
                case 'web_search':
                    $tools[] = [
                        'type' => 'web_search'
                    ];
                    break;

                // Vision handled separately, web_fetch not supported
                case 'web_fetch':
                case 'vision':
                    // Skip
                    break;
            }
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Build Gemini-specific tools array
     * Format: Google Search Grounding
     */
    private function buildGeminiTools($capabilities, $limits) {
        $tools = [];

        foreach ($capabilities as $capabilityCode) {
            switch ($capabilityCode) {
                case 'web_search':
                    // Gemini uses google_search_retrieval tool
                    $tools[] = [
                        'google_search_retrieval' => []
                    ];
                    break;

                // Vision handled separately
                case 'web_fetch':
                case 'vision':
                    // Skip
                    break;
            }
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Build Kimi (Moonshot)-specific tools array
     * Format: builtin_function with $web_search
     */
    private function buildKimiTools($capabilities, $limits) {
        $tools = [];

        foreach ($capabilities as $capabilityCode) {
            switch ($capabilityCode) {
                case 'web_search':
                    // Kimi uses builtin_function with $web_search
                    $tools[] = [
                        'type' => 'builtin_function',
                        'function' => [
                            'name' => '$web_search'
                        ]
                    ];
                    break;

                // Vision and web_fetch not supported
                case 'web_fetch':
                case 'vision':
                    // Skip
                    break;
            }
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Append tool usage instructions to prompt (for providers like OpenAI)
     *
     * @param string $prompt Original prompt
     * @param array $capabilities Array of capability codes
     * @param array $limits Array of max_uses limits keyed by capability code
     * @return string Modified prompt with tool instructions
     */
    public function appendPromptInstructions($prompt, $capabilities, $limits) {
        if (empty($capabilities)) {
            return $prompt;
        }

        // OpenAI requires max_uses in prompt text
        if ($this->providerCode === 'openai') {
            $instructions = [];

            foreach ($capabilities as $capabilityCode) {
                $maxUses = $limits[$capabilityCode] ?? null;

                switch ($capabilityCode) {
                    case 'web_search':
                        if ($maxUses !== null) {
                            $instructions[] = "Use no more than $maxUses web searches to answer this question.";
                        }
                        break;
                }
            }

            if (!empty($instructions)) {
                $prompt = implode(' ', $instructions) . "\n\n" . $prompt;
            }
        }

        return $prompt;
    }

    /**
     * Parse tool usage from API response
     *
     * @param array $responseData Decoded API response
     * @param string $providerCode Provider code
     * @return array Array of tool calls: [['type' => 'web_search', 'count' => 3], ...]
     */
    public function parseToolUsage($responseData, $providerCode) {
        $toolCalls = [];

        switch ($providerCode) {
            case 'claude':
            case 'claude_cli':
                $toolCalls = $this->parseClaudeToolUsage($responseData);
                break;

            case 'openai':
                $toolCalls = $this->parseOpenAIToolUsage($responseData);
                break;

            case 'gemini':
                $toolCalls = $this->parseGeminiToolUsage($responseData);
                break;

            case 'kimi':
                $toolCalls = $this->parseKimiToolUsage($responseData);
                break;

            default:
                // No tool usage for other providers
                break;
        }

        return $toolCalls;
    }

    /**
     * Parse Claude tool usage from response
     */
    private function parseClaudeToolUsage($responseData) {
        $toolCalls = [];

        // Claude returns tool usage in content blocks
        if (isset($responseData['content']) && is_array($responseData['content'])) {
            $toolCounts = [];

            foreach ($responseData['content'] as $block) {
                // Check for both 'tool_use' (custom tools) and 'server_tool_use' (server-side tools like web_search)
                if (isset($block['type']) && ($block['type'] === 'tool_use' || $block['type'] === 'server_tool_use')) {
                    $toolType = $block['name'] ?? 'unknown';

                    // Normalize tool type names
                    if ($toolType === 'web_search' || $toolType === 'web_search_20250305') {
                        $toolType = 'web_search';
                    }

                    if (!isset($toolCounts[$toolType])) {
                        $toolCounts[$toolType] = 0;
                    }
                    $toolCounts[$toolType]++;
                }
            }

            foreach ($toolCounts as $type => $count) {
                $toolCalls[] = [
                    'type' => $type,
                    'count' => $count
                ];
            }
        }

        return $toolCalls;
    }

    /**
     * Parse OpenAI tool usage from response
     * Supports both /v1/responses (output array) and /v1/chat/completions (choices array)
     */
    private function parseOpenAIToolUsage($responseData) {
        $toolCalls = [];
        $toolCounts = [];

        // NEW /v1/responses API format - output array with web_search_call entries
        if (isset($responseData['output']) && is_array($responseData['output'])) {
            foreach ($responseData['output'] as $outputItem) {
                $type = $outputItem['type'] ?? '';

                if ($type === 'web_search_call') {
                    $toolType = 'web_search';
                    if (!isset($toolCounts[$toolType])) {
                        $toolCounts[$toolType] = 0;
                    }
                    $toolCounts[$toolType]++;
                } elseif ($type === 'tool_call') {
                    $toolType = $outputItem['tool_name'] ?? 'unknown';
                    if (!isset($toolCounts[$toolType])) {
                        $toolCounts[$toolType] = 0;
                    }
                    $toolCounts[$toolType]++;
                }
            }
        }
        // OLD /v1/chat/completions format - choices array with tool_calls
        elseif (isset($responseData['choices'][0]['message']['tool_calls']) && is_array($responseData['choices'][0]['message']['tool_calls'])) {
            foreach ($responseData['choices'][0]['message']['tool_calls'] as $toolCall) {
                $toolType = $toolCall['function']['name'] ?? 'unknown';

                if (!isset($toolCounts[$toolType])) {
                    $toolCounts[$toolType] = 0;
                }
                $toolCounts[$toolType]++;
            }
        }

        // Convert counts to standardized format
        foreach ($toolCounts as $type => $count) {
            $toolCalls[] = [
                'type' => $type,
                'count' => $count
            ];
        }

        return $toolCalls;
    }

    /**
     * Parse Gemini tool usage from response
     */
    private function parseGeminiToolUsage($responseData) {
        $toolCalls = [];

        // Gemini search grounding details
        if (isset($responseData['candidates'][0]['groundingMetadata']['webSearchQueries'])) {
            $searchCount = count($responseData['candidates'][0]['groundingMetadata']['webSearchQueries']);
            if ($searchCount > 0) {
                $toolCalls[] = [
                    'type' => 'web_search',
                    'count' => $searchCount
                ];
            }
        }

        return $toolCalls;
    }

    /**
     * Parse Kimi (Moonshot) tool usage from response
     */
    private function parseKimiToolUsage($responseData) {
        $toolCalls = [];

        // Kimi returns tool calls similar to OpenAI format
        if (isset($responseData['choices'][0]['message']['tool_calls']) && is_array($responseData['choices'][0]['message']['tool_calls'])) {
            $toolCounts = [];

            foreach ($responseData['choices'][0]['message']['tool_calls'] as $toolCall) {
                $toolType = $toolCall['function']['name'] ?? 'unknown';

                // Normalize Kimi's $web_search to web_search
                if ($toolType === '$web_search') {
                    $toolType = 'web_search';
                }

                if (!isset($toolCounts[$toolType])) {
                    $toolCounts[$toolType] = 0;
                }
                $toolCounts[$toolType]++;
            }

            foreach ($toolCounts as $type => $count) {
                $toolCalls[] = [
                    'type' => $type,
                    'count' => $count
                ];
            }
        }

        return $toolCalls;
    }

    /**
     * Calculate tool costs based on usage
     *
     * @param array $toolCalls Array of tool calls from parseToolUsage()
     * @return float Total cost in USD
     */
    public function calculateToolCosts($toolCalls) {
        if (empty($toolCalls)) {
            return 0.0;
        }

        $totalCost = 0.0;
        $conn = ai_getDBConnection();

        foreach ($toolCalls as $toolCall) {
            $capabilityCode = $toolCall['type'];
            $count = $toolCall['count'];

            // Get cost per use from database
            $stmt = $conn->prepare("SELECT cost_per_use FROM ai_model_capabilities WHERE model_id = ? AND capability_code = ?");
            $stmt->bind_param('is', $this->modelId, $capabilityCode);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $costPerUse = (float)$row['cost_per_use'];
                $totalCost += ($costPerUse * $count);
            }

            $stmt->close();
        }

        // Note: Connection uses static variable pattern, do not close
        return $totalCost;
    }
}
