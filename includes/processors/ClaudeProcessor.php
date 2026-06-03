<?php
/**
 * AI Central System - Claude Processor
 *
 * Handles API request/response formatting for all Claude models
 * (Haiku, Sonnet, Opus) via Anthropic's Claude API.
 *
 * API Documentation: https://docs.anthropic.com/en/api/messages
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class ClaudeProcessor implements BaseProcessor {

    /**
     * Build Claude API request
     *
     * Claude API format:
     * - System prompt: Top-level "system" field
     * - Images: messages[].content[] with type="image", source object
     * - Documents (PDF): messages[].content[] with type="document", source object
     * - Tools: tools[] array with type like "web_search_20250305"
     * - Max tokens: "max_tokens" field
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options
     * @return array API request body
     */
    public function buildRequest($prompt, $options) {
        aiCentral_logMessage("ClaudeProcessor: Building request", 'DEBUG');

        // Start with model
        $modelCode = $options['model_code'] ?? 'claude-sonnet-4-5-20250929';
        $request = [
            'model' => $modelCode,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Add optional parameters
        if (isset($options['temperature'])) {
            $request['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['top_p'])) {
            $request['top_p'] = (float)$options['top_p'];
        }
        if (isset($options['top_k'])) {
            $request['top_k'] = (int)$options['top_k'];
        }

        // System prompt (top-level field)
        if (!empty($options['system'])) {
            $request['system'] = $options['system'];
        }

        // Build messages array
        $messages = [];
        $content = [];

        // Add images if present (must come before text in Claude API)
        if (!empty($options['images']) && is_array($options['images'])) {
            foreach ($options['images'] as $image) {
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $image['media_type'] ?? 'image/jpeg',
                        'data' => $image['data']
                    ]
                ];
            }
            aiCentral_logMessage("ClaudeProcessor: Added " . count($options['images']) . " images", 'DEBUG');
        }

        // Add documents (e.g. PDFs) if present. Claude reads the PDF natively,
        // including its text layer and visual layout / scanned pages.
        if (!empty($options['documents']) && is_array($options['documents'])) {
            foreach ($options['documents'] as $doc) {
                $content[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $doc['media_type'] ?? 'application/pdf',
                        'data' => $doc['data']
                    ]
                ];
            }
            aiCentral_logMessage("ClaudeProcessor: Added " . count($options['documents']) . " documents", 'DEBUG');
        }

        // Add text prompt
        $content[] = [
            'type' => 'text',
            'text' => $prompt
        ];

        $messages[] = [
            'role' => 'user',
            'content' => $content
        ];

        $request['messages'] = $messages;

        // Add tools if capabilities specified
        if (!empty($options['capabilities'])) {
            $tools = $this->buildTools($options['capabilities']);
            if ($tools !== null) {
                $request['tools'] = $tools;
                aiCentral_logMessage("ClaudeProcessor: Added " . count($tools) . " tools", 'DEBUG');
            }
        }

        // Claude doesn't support top-level metadata field
        // Metadata is handled internally for logging only

        // Always disable streaming for now
        $request['stream'] = false;

        aiCentral_logMessage("ClaudeProcessor: Request built successfully", 'DEBUG');
        return $request;
    }

    /**
     * Parse Claude API response
     *
     * Claude response format:
     * - content[]: Array of content blocks
     * - usage: {input_tokens, output_tokens}
     * - stop_reason: Why generation stopped
     *
     * @param array $apiResponse Raw API response
     * @param int $httpCode HTTP status code
     * @return array Standardized response
     */
    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        aiCentral_logMessage("ClaudeProcessor: Parsing response (HTTP $httpCode)", 'DEBUG');

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorMsg = 'Unknown error';
            $errorType = 'http_error';

            if (isset($apiResponse['error'])) {
                $errorMsg = $apiResponse['error']['message'] ?? json_encode($apiResponse['error']);
                $errorType = $apiResponse['error']['type'] ?? 'api_error';
            }

            aiCentral_logMessage("ClaudeProcessor: API error - $errorMsg", 'ERROR');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => $errorType,
                'raw_response_data' => $apiResponse
            ];
        }

        // Extract text response from content blocks
        $responseText = '';
        if (isset($apiResponse['content']) && is_array($apiResponse['content'])) {
            foreach ($apiResponse['content'] as $block) {
                if ($block['type'] === 'text') {
                    $responseText .= $block['text'];
                }
            }
        }

        // Extract usage information
        $usage = [
            'input_tokens' => $apiResponse['usage']['input_tokens'] ?? 0,
            'output_tokens' => $apiResponse['usage']['output_tokens'] ?? 0,
            'thinking_tokens' => 0 // Claude doesn't have thinking tokens in standard responses
        ];

        // Check for thinking tokens (extended thinking models)
        if (isset($apiResponse['usage']['cache_creation_input_tokens'])) {
            // This might be thinking-related, but for now we'll track separately
        }

        // Extract tool calls if present
        $toolCalls = [];
        if (isset($apiResponse['content']) && is_array($apiResponse['content'])) {
            foreach ($apiResponse['content'] as $block) {
                if ($block['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'tool' => $block['name'] ?? 'unknown',
                        'input' => $block['input'] ?? []
                    ];
                }
            }
        }

        aiCentral_logMessage("ClaudeProcessor: Response parsed successfully", 'DEBUG');

        return [
            'success' => true,
            'response' => $responseText,
            'usage' => $usage,
            'tool_calls' => $toolCalls,
            'stop_reason' => $apiResponse['stop_reason'] ?? 'unknown',
            'raw_response_data' => $apiResponse
        ];
    }

    /**
     * Build tools array for Claude
     *
     * Claude tool format:
     * - type: "web_search_20250305" (versioned tool type)
     * - name: "web_search"
     * - max_uses: 5 (built into tool definition)
     *
     * @param array $capabilities Capabilities to enable
     * @param string $providerCode Not used for Claude
     * @return array|null Tools array or null
     */
    public function buildTools($capabilities, $providerCode = null) {
        $tools = [];

        // Web search tool
        if (!empty($capabilities['web_search'])) {
            $maxUses = (int)$capabilities['web_search'];
            $tools[] = [
                'type' => 'web_search_20250305',
                'name' => 'web_search',
                'max_uses' => $maxUses
            ];
            aiCentral_logMessage("ClaudeProcessor: Added web_search tool (max_uses: $maxUses)", 'DEBUG');
        }

        // Web fetch tool (if supported)
        if (!empty($capabilities['web_fetch'])) {
            $maxUses = (int)$capabilities['web_fetch'];
            $tools[] = [
                'type' => 'web_fetch',
                'name' => 'web_fetch',
                'max_uses' => $maxUses
            ];
            aiCentral_logMessage("ClaudeProcessor: Added web_fetch tool (max_uses: $maxUses)", 'DEBUG');
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Get Claude API endpoint
     *
     * @param string $modelCode Model code (not used, endpoint is same for all)
     * @param string $providerCode Not used for Claude
     * @return string API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        return 'https://api.anthropic.com/v1/messages';
    }

    /**
     * Get Claude API headers
     *
     * Claude requires:
     * - x-api-key: API key
     * - anthropic-version: API version
     * - content-type: application/json
     *
     * @param string $apiKey API key
     * @param string $providerCode Not used for Claude
     * @return array HTTP headers
     */
    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json'
        ];
    }
}
