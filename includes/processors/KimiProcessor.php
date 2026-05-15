<?php
/**
 * AI Central System - Kimi (Moonshot AI) Processor
 *
 * Handles API request/response formatting for Kimi (Moonshot AI) models.
 *
 * IMPORTANT: Kimi uses a 2-tier conversation flow when web search is enabled:
 * 1. First API call: User asks question -> Kimi returns tool_calls requesting web search
 * 2. Execute web search using Kimi's search API
 * 3. Second API call: Send search results back -> Kimi generates final answer
 *
 * This is different from OpenAI/Grok which handle tools server-side.
 *
 * API Documentation: https://platform.moonshot.cn/docs/api-reference
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class KimiProcessor implements BaseProcessor {

    // Store API key and endpoint for 2-tier calls
    private $apiKey;
    private $endpoint;

    /**
     * Set API key and endpoint for 2-tier calls
     * Called by AIProviderManager before making requests
     *
     * @param string $apiKey API key
     * @param string $endpoint API endpoint
     */
    public function setApiKeyAndEndpoint($apiKey, $endpoint) {
        $this->apiKey = $apiKey;
        $this->endpoint = $endpoint;
    }

    /**
     * Build Kimi API request
     *
     * Kimi uses OpenAI-compatible format:
     * - System prompt: First message with role="system"
     * - Images: messages[].content[] with type="image_url"
     * - Tools: builtin_function with name="$web_search"
     * - Max tokens: "max_tokens" field
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options
     * @return array API request body
     */
    public function buildRequest($prompt, $options) {
        aiCentral_logMessage("KimiProcessor: Building request", 'DEBUG');

        // Start with model
        $modelCode = $options['model_code'] ?? 'moonshot-v1-8k';
        $request = [
            'model' => $modelCode,
        ];

        // Add max_tokens if specified
        if (isset($options['max_tokens'])) {
            $request['max_tokens'] = (int)$options['max_tokens'];
        }

        // Add optional parameters
        if (isset($options['temperature'])) {
            $request['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['top_p'])) {
            $request['top_p'] = (float)$options['top_p'];
        }

        // Build messages array
        $messages = [];

        // System prompt (first message with role="system")
        if (!empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system']
            ];
        }

        // Check if this is a tool result continuation (2nd tier of conversation)
        if (!empty($options['tool_results'])) {
            // This is the second API call - include previous messages and tool results
            if (!empty($options['previous_messages'])) {
                $messages = array_merge($messages, $options['previous_messages']);
            }

            // Add tool result message
            $messages[] = [
                'role' => 'tool',
                'content' => json_encode($options['tool_results']),
                'tool_call_id' => $options['tool_call_id'] ?? 'unknown'
            ];

            aiCentral_logMessage("KimiProcessor: Building 2nd tier request with tool results", 'DEBUG');
        } else {
            // First tier - normal user message
            $userContent = [];

            // Add text first
            $userContent[] = [
                'type' => 'text',
                'text' => $prompt
            ];

            // Add images if present (OpenAI format: data URL)
            if (!empty($options['images']) && is_array($options['images'])) {
                foreach ($options['images'] as $image) {
                    $mediaType = $image['media_type'] ?? 'image/jpeg';
                    $base64Data = $image['data'];

                    $userContent[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:$mediaType;base64,$base64Data",
                            'detail' => 'high'
                        ]
                    ];
                }
                aiCentral_logMessage("KimiProcessor: Added " . count($options['images']) . " images", 'DEBUG');
            }

            // Add user message
            $messages[] = [
                'role' => 'user',
                'content' => $userContent
            ];
        }

        $request['messages'] = $messages;

        // Add tools if capabilities specified (only for first tier)
        if (!empty($options['capabilities']) && empty($options['tool_results'])) {
            $tools = $this->buildTools($options['capabilities']);
            if ($tools !== null) {
                $request['tools'] = $tools;
                aiCentral_logMessage("KimiProcessor: Added web_search tool", 'DEBUG');
            }
        }

        // Always disable streaming
        $request['stream'] = false;

        aiCentral_logMessage("KimiProcessor: Request built successfully", 'DEBUG');
        return $request;
    }

    /**
     * Parse Kimi API response
     *
     * Handles both direct responses and tool call responses.
     * If finish_reason is "tool_calls", automatically makes a second API call with tool results.
     * Returns combined tokens from both API calls.
     *
     * @param array $apiResponse Raw API response from first call
     * @param int $httpCode HTTP status code
     * @param array $originalRequest Original request body (for 2nd tier)
     * @return array Standardized response with combined usage
     */
    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        aiCentral_logMessage("KimiProcessor: Parsing response (HTTP $httpCode)", 'DEBUG');

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorMsg = 'Unknown error';
            $errorType = 'http_error';

            if (isset($apiResponse['error'])) {
                $errorMsg = $apiResponse['error']['message'] ?? json_encode($apiResponse['error']);
                $errorType = $apiResponse['error']['type'] ?? 'api_error';
            }

            aiCentral_logMessage("KimiProcessor: API error - $errorMsg", 'ERROR');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => $errorType,
                'raw_response_data' => $apiResponse
            ];
        }

        // Get finish reason
        $finishReason = $apiResponse['choices'][0]['finish_reason'] ?? 'unknown';

        // Check if this is a tool call response (2-tier conversation needed)
        if ($finishReason === 'tool_calls' && isset($apiResponse['choices'][0]['message']['tool_calls'])) {
            aiCentral_logMessage("KimiProcessor: Detected tool_calls - initiating 2-tier flow", 'INFO');

            // Store first call usage
            $firstCallUsage = [
                'input_tokens' => $apiResponse['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $apiResponse['usage']['completion_tokens'] ?? 0
            ];

            // Extract tool call information
            $toolCall = $apiResponse['choices'][0]['message']['tool_calls'][0];
            $toolCallId = $toolCall['id'];
            $toolName = $toolCall['function']['name'] ?? '$web_search';
            $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            aiCentral_logMessage("KimiProcessor: Tool call - $toolName with ID: $toolCallId", 'DEBUG');

            // Build second tier request
            // According to Kimi docs, we need to add the assistant message with tool_calls
            // then add a tool message with the results
            $secondRequest = [
                'model' => $originalRequest['model'] ?? 'moonshot-v1-128k',
                'messages' => $originalRequest['messages'] ?? [],
                'stream' => false
            ];

            // Add optional parameters from original request
            if (isset($originalRequest['max_tokens'])) {
                $secondRequest['max_tokens'] = $originalRequest['max_tokens'];
            }
            if (isset($originalRequest['temperature'])) {
                $secondRequest['temperature'] = $originalRequest['temperature'];
            }

            // Add the assistant's tool_calls message
            $secondRequest['messages'][] = [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [$toolCall]
            ];

            // Add the tool result message
            // Kimi's web search is automatic - we just echo back the search result
            $secondRequest['messages'][] = [
                'role' => 'tool',
                'content' => $toolCall['function']['arguments'],
                'tool_call_id' => $toolCallId
            ];

            aiCentral_logMessage("KimiProcessor: Making second API call with tool results", 'INFO');

            // Make second API call
            $secondResponse = $this->makeSecondTierCall($secondRequest);

            if (!$secondResponse['success']) {
                aiCentral_logMessage("KimiProcessor: Second tier call failed: " . $secondResponse['error'], 'ERROR');
                return $secondResponse;
            }

            // Combine usage from both calls
            $combinedUsage = [
                'input_tokens' => $firstCallUsage['input_tokens'] + ($secondResponse['usage']['prompt_tokens'] ?? 0),
                'output_tokens' => $firstCallUsage['output_tokens'] + ($secondResponse['usage']['completion_tokens'] ?? 0),
                'thinking_tokens' => 0
            ];

            // Extract tool call count for logging
            $toolCalls = [[
                'tool' => $toolName,
                'input' => $toolArguments,
                'count' => 1
            ]];

            aiCentral_logMessage("KimiProcessor: 2-tier flow complete. Combined tokens: {$combinedUsage['input_tokens']} in, {$combinedUsage['output_tokens']} out", 'INFO');

            // Return final response with combined usage
            return [
                'success' => true,
                'response' => $secondResponse['data']['choices'][0]['message']['content'] ?? '',
                'usage' => $combinedUsage,
                'tool_calls' => $toolCalls,
                'stop_reason' => $secondResponse['data']['choices'][0]['finish_reason'] ?? 'stop',
                'raw_response_data' => [
                    'first_call' => $apiResponse,
                    'second_call' => $secondResponse['data']
                ]
            ];
        }

        // Normal response (no tool calls)
        $responseText = '';
        if (isset($apiResponse['choices'][0]['message']['content'])) {
            $responseText = $apiResponse['choices'][0]['message']['content'];
        }

        // Extract usage information
        $usage = [
            'input_tokens' => $apiResponse['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $apiResponse['usage']['completion_tokens'] ?? 0,
            'thinking_tokens' => 0
        ];

        aiCentral_logMessage("KimiProcessor: Direct response parsed successfully", 'DEBUG');

        return [
            'success' => true,
            'response' => $responseText,
            'usage' => $usage,
            'tool_calls' => [],
            'stop_reason' => $finishReason,
            'raw_response_data' => $apiResponse
        ];
    }

    /**
     * Make second tier API call for tool results
     *
     * @param array $requestBody Request body for second call
     * @return array Response with 'success', 'data', 'error'
     */
    private function makeSecondTierCall($requestBody) {
        if (!$this->apiKey || !$this->endpoint) {
            return [
                'success' => false,
                'error' => 'API key or endpoint not set for second tier call'
            ];
        }

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            aiCentral_logMessage("KimiProcessor: Second tier CURL error: $curlError", 'ERROR');
            return [
                'success' => false,
                'error' => 'CURL error: ' . $curlError
            ];
        }

        $data = json_decode($response, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            aiCentral_logMessage("KimiProcessor: Second tier JSON decode error", 'ERROR');
            return [
                'success' => false,
                'error' => 'Invalid JSON response'
            ];
        }

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Unknown error';
            aiCentral_logMessage("KimiProcessor: Second tier HTTP error $httpCode: $errorMsg", 'ERROR');
            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'usage' => $data['usage'] ?? []
        ];
    }

    /**
     * Build tools array for Kimi
     *
     * Kimi tool format:
     * - type: "builtin_function"
     * - function: {name: "$web_search"}
     *
     * @param array $capabilities Capabilities to enable
     * @param string $providerCode Not used for Kimi
     * @return array|null Tools array or null
     */
    public function buildTools($capabilities, $providerCode = null) {
        $tools = [];

        // Web search tool
        if (!empty($capabilities['web_search'])) {
            $tools[] = [
                'type' => 'builtin_function',
                'function' => [
                    'name' => '$web_search'
                ]
            ];
            aiCentral_logMessage("KimiProcessor: Added $web_search builtin tool", 'DEBUG');
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Get Kimi API endpoint
     *
     * @param string $modelCode Model code
     * @param string $providerCode Not used for Kimi
     * @return string API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        return 'https://api.moonshot.ai/v1/chat/completions';
    }

    /**
     * Get Kimi API headers
     *
     * Uses Bearer token authentication like OpenAI
     *
     * @param string $apiKey API key
     * @param string $providerCode Not used for Kimi
     * @return array HTTP headers
     */
    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
    }

}
