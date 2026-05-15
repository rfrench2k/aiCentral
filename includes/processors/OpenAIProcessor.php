<?php
/**
 * AI Central System - OpenAI Processor
 *
 * Handles API request/response formatting for OpenAI-compatible APIs:
 * - OpenAI (GPT models)
 * - Kimi (Moonshot AI)
 * - Grok (xAI)
 *
 * All three use OpenAI's chat completion API format with slight variations.
 *
 * API Documentation:
 * - OpenAI: https://platform.openai.com/docs/api-reference/chat
 * - Kimi: https://platform.moonshot.cn/docs/api-reference
 * - Grok: https://docs.x.ai/api
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class OpenAIProcessor implements BaseProcessor {

    /**
     * Build OpenAI-compatible API request
     *
     * OpenAI format:
     * - System prompt: First message with role="system"
     * - Images: messages[].content[] with type="image_url"
     * - Tools: Varies by provider (OpenAI, Kimi, Grok)
     * - Max tokens: "max_tokens" field
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options
     * @return array API request body
     */
    public function buildRequest($prompt, $options) {
        $providerCode = $options['provider_code'] ?? 'openai';
        aiCentral_logMessage("OpenAIProcessor: Building request for provider: $providerCode", 'DEBUG');

        // Start with model
        $modelCode = $options['model_code'] ?? 'gpt-4o';
        $request = [
            'model' => $modelCode,
        ];

        // Add max_tokens if specified
        // Max tokens parameter varies by API endpoint and model
        if (isset($options['max_tokens'])) {
            $maxTokens = (int)$options['max_tokens'];

            if ($providerCode === 'openai') {
                // OpenAI /v1/responses API uses max_output_tokens
                $request['max_output_tokens'] = $maxTokens;
            } elseif (strpos($modelCode, 'gpt-5') !== false ||
                      strpos($modelCode, 'o1-') !== false ||
                      strpos($modelCode, 'o3-') !== false) {
                // GPT-5+ and o1/o3 models use max_completion_tokens (for chat/completions)
                $request['max_completion_tokens'] = $maxTokens;
            } else {
                // Other providers and older OpenAI models use max_tokens
                $request['max_tokens'] = $maxTokens;
            }
        }

        // Add optional parameters
        if (isset($options['temperature'])) {
            $request['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['top_p'])) {
            $request['top_p'] = (float)$options['top_p'];
        }

        // OpenAI uses different format: instructions + input (for /v1/responses endpoint)
        // Other providers (Kimi, Grok) use messages array (for /v1/chat/completions endpoint)

        if ($providerCode === 'openai') {
            // OpenAI /v1/responses format
            // System prompt goes in "instructions"
            $instructions = $options['system'] ?? '';
            if (!empty($options['capabilities']['web_search'])) {
                $maxSearches = $options['capabilities']['web_search'];
                $searchLimit = "\n\nIMPORTANT: ONLY search $maxSearches times maximum. Do not exceed this limit.";
                $instructions .= $searchLimit;
                aiCentral_logMessage("OpenAIProcessor: Added web_search limit ($maxSearches) to instructions for OpenAI", 'DEBUG');
            }

            if (!empty($instructions)) {
                $request['instructions'] = $instructions;
            }

            // User prompt goes in "input"
            $request['input'] = $prompt;

            aiCentral_logMessage("OpenAIProcessor: Using /v1/responses format (instructions + input)", 'DEBUG');

        } else {
            // Kimi/Grok use standard messages array format
            $messages = [];

            // System prompt
            if (!empty($options['system'])) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $options['system']
                ];
            }

            // Build user message content
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
                            'detail' => 'high' // Use high detail for vision
                        ]
                    ];
                }
                aiCentral_logMessage("OpenAIProcessor: Added " . count($options['images']) . " images", 'DEBUG');
            }

            // Add user message
            $messages[] = [
                'role' => 'user',
                'content' => $userContent
            ];

            $request['messages'] = $messages;
        }

        // Add tools if capabilities specified
        if (!empty($options['capabilities'])) {
            $tools = $this->buildTools($options['capabilities'], $providerCode);
            if ($tools !== null) {
                $request['tools'] = $tools;
                aiCentral_logMessage("OpenAIProcessor: Added tools for $providerCode", 'DEBUG');
            }
        }

        // Always disable streaming for now
        $request['stream'] = false;

        aiCentral_logMessage("OpenAIProcessor: Request built successfully for $providerCode", 'DEBUG');
        return $request;
    }

    /**
     * Parse OpenAI-compatible API response
     *
     * OpenAI response format:
     * - choices[0].message.content: Response text
     * - usage: {prompt_tokens, completion_tokens}
     *
     * @param array $apiResponse Raw API response
     * @param int $httpCode HTTP status code
     * @return array Standardized response
     */
    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        aiCentral_logMessage("OpenAIProcessor: Parsing response (HTTP $httpCode)", 'DEBUG');

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorMsg = 'Unknown error';
            $errorType = 'http_error';

            if (isset($apiResponse['error'])) {
                $errorMsg = $apiResponse['error']['message'] ?? json_encode($apiResponse['error']);
                $errorType = $apiResponse['error']['type'] ?? 'api_error';
            }

            aiCentral_logMessage("OpenAIProcessor: API error - $errorMsg", 'ERROR');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => $errorType,
                'raw_response_data' => $apiResponse
            ];
        }

        // Detect response format: /v1/responses uses 'output', /v1/chat/completions uses 'choices'
        $isResponsesAPI = isset($apiResponse['output']);

        if ($isResponsesAPI) {
            // NEW /v1/responses API format
            $responseText = '';
            $toolCalls = [];

            // Parse output array - contains message, web_search_call, tool_call, and tool_result entries
            foreach ($apiResponse['output'] as $outputItem) {
                $type = $outputItem['type'] ?? '';

                if ($type === 'message' || $type === 'response.message') {
                    // Extract text from content array
                    if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                        foreach ($outputItem['content'] as $contentItem) {
                            if ($contentItem['type'] === 'output_text') {
                                $responseText .= $contentItem['text'];
                            }
                        }
                    }
                } elseif ($type === 'web_search_call') {
                    // Track web search calls
                    $toolCalls[] = [
                        'tool' => 'web_search',
                        'input' => $outputItem['action'] ?? []
                    ];
                } elseif ($type === 'tool_call') {
                    // Track other tool calls
                    $toolCalls[] = [
                        'tool' => $outputItem['tool_name'] ?? 'unknown',
                        'input' => $outputItem['parameters'] ?? []
                    ];
                }
            }

            // Usage from /v1/responses format
            $usage = [
                'input_tokens' => $apiResponse['usage']['input_tokens'] ?? 0,
                'output_tokens' => $apiResponse['usage']['output_tokens'] ?? 0,
                'thinking_tokens' => 0
            ];

            $finishReason = 'stop'; // /v1/responses doesn't provide finish_reason

        } else {
            // OLD /v1/chat/completions API format
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

            // Check for reasoning tokens (o1 models)
            if (isset($apiResponse['usage']['completion_tokens_details']['reasoning_tokens'])) {
                $usage['thinking_tokens'] = $apiResponse['usage']['completion_tokens_details']['reasoning_tokens'];
            }

            // Extract tool calls if present
            $toolCalls = [];
            if (isset($apiResponse['choices'][0]['message']['tool_calls'])) {
                foreach ($apiResponse['choices'][0]['message']['tool_calls'] as $toolCall) {
                    $toolCalls[] = [
                        'tool' => $toolCall['function']['name'] ?? 'unknown',
                        'input' => json_decode($toolCall['function']['arguments'] ?? '{}', true)
                    ];
                }
            }

            // Get finish reason
            $finishReason = $apiResponse['choices'][0]['finish_reason'] ?? 'unknown';
        }

        aiCentral_logMessage("OpenAIProcessor: Response parsed successfully (response length: " . strlen($responseText) . ", tool_calls: " . count($toolCalls) . ")", 'DEBUG');

        return [
            'success' => true,
            'response' => $responseText,
            'usage' => $usage,
            'tool_calls' => $toolCalls,
            'stop_reason' => $finishReason,
            'raw_response_data' => $apiResponse
        ];
    }

    /**
     * Build tools array for OpenAI-compatible APIs
     *
     * Tool formats vary by provider:
     * - OpenAI: {type: "web_search"} or {type: "function", function: {...}}
     * - Kimi: {type: "builtin_function", function: {name: "$web_search"}}
     * - Grok: No tools needed (automatic web search, FREE)
     *
     * @param array $capabilities Capabilities to enable
     * @param string $providerCode Provider code (openai, kimi, grok)
     * @return array|null Tools array or null
     */
    public function buildTools($capabilities, $providerCode = null) {
        // Grok has automatic web search (no tool definition needed)
        if ($providerCode === 'grok') {
            aiCentral_logMessage("OpenAIProcessor: Grok uses automatic web search (no tools needed)", 'DEBUG');
            return null;
        }

        $tools = [];

        // Web search tool
        if (!empty($capabilities['web_search'])) {
            if ($providerCode === 'kimi') {
                // Kimi format: builtin_function with $web_search
                $tools[] = [
                    'type' => 'builtin_function',
                    'function' => [
                        'name' => '$web_search'
                    ]
                ];
                aiCentral_logMessage("OpenAIProcessor: Added Kimi web_search tool", 'DEBUG');
            } else {
                // OpenAI format: web_search type
                $tools[] = [
                    'type' => 'web_search'
                ];
                aiCentral_logMessage("OpenAIProcessor: Added OpenAI web_search tool", 'DEBUG');
            }
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Get API endpoint for provider
     *
     * Different endpoint for each provider:
     * - OpenAI: https://api.openai.com/v1/chat/completions
     * - Kimi: https://api.moonshot.ai/v1/chat/completions
     * - Grok: https://api.x.ai/v1/chat/completions
     *
     * @param string $modelCode Model code
     * @param string $providerCode Provider code (openai, kimi, grok)
     * @return string API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        switch ($providerCode) {
            case 'kimi':
                return 'https://api.moonshot.ai/v1/chat/completions';
            case 'grok':
                return 'https://api.x.ai/v1/chat/completions';
            case 'openai':
                return 'https://api.openai.com/v1/responses';
            default:
                return 'https://api.openai.com/v1/chat/completions';
        }
    }

    /**
     * Get API headers for provider
     *
     * All use Bearer token authentication
     *
     * @param string $apiKey API key
     * @param string $providerCode Provider code
     * @return array HTTP headers
     */
    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
    }
}
