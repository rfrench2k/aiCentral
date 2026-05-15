<?php
/**
 * AI Central System - Gemini Processor
 *
 * Handles API request/response formatting for Google Gemini models.
 *
 * Gemini uses a significantly different API structure than OpenAI/Claude:
 * - System prompt: systemInstruction.parts[].text
 * - Images: contents[].parts[] with inline_data
 * - Tools: tools[{google_search: {}}]
 * - Config: generationConfig object
 *
 * API Documentation: https://ai.google.dev/api/rest/v1beta/models/generateContent
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class GeminiProcessor implements BaseProcessor {

    /**
     * Build Gemini API request
     *
     * Gemini format is quite different from OpenAI/Claude:
     * - contents[] array with parts[]
     * - systemInstruction for system prompt
     * - generationConfig for parameters
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options
     * @return array API request body
     */
    public function buildRequest($prompt, $options) {
        aiCentral_logMessage("GeminiProcessor: Building request", 'DEBUG');

        $request = [];

        // System instruction (separate from contents)
        if (!empty($options['system'])) {
            $request['systemInstruction'] = [
                'parts' => [
                    ['text' => $options['system']]
                ]
            ];
        }

        // Build contents array
        $parts = [];

        // Add text prompt
        $parts[] = [
            'text' => $prompt
        ];

        // Add images if present (inline_data format)
        if (!empty($options['images']) && is_array($options['images'])) {
            foreach ($options['images'] as $image) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image['media_type'] ?? 'image/jpeg',
                        'data' => $image['data']
                    ]
                ];
            }
            aiCentral_logMessage("GeminiProcessor: Added " . count($options['images']) . " images", 'DEBUG');
        }

        $request['contents'] = [
            [
                'role' => 'user',
                'parts' => $parts
            ]
        ];

        // Generation config
        $generationConfig = [];

        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['top_p'])) {
            $generationConfig['topP'] = (float)$options['top_p'];
        }
        if (isset($options['top_k'])) {
            $generationConfig['topK'] = (int)$options['top_k'];
        }
        if (isset($options['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int)$options['max_tokens'];
        }

        if (!empty($generationConfig)) {
            $request['generationConfig'] = $generationConfig;
        }

        // Add tools if capabilities specified
        if (!empty($options['capabilities'])) {
            $tools = $this->buildTools($options['capabilities']);
            if ($tools !== null) {
                $request['tools'] = $tools;
                aiCentral_logMessage("GeminiProcessor: Added tools", 'DEBUG');
            }
        }

        aiCentral_logMessage("GeminiProcessor: Request built successfully", 'DEBUG');
        return $request;
    }

    /**
     * Parse Gemini API response
     *
     * Gemini response format:
     * - candidates[0].content.parts[].text: Response text
     * - usageMetadata: Token usage
     *
     * @param array $apiResponse Raw API response
     * @param int $httpCode HTTP status code
     * @return array Standardized response
     */
    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        aiCentral_logMessage("GeminiProcessor: Parsing response (HTTP $httpCode)", 'DEBUG');

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorMsg = 'Unknown error';
            $errorType = 'http_error';

            if (isset($apiResponse['error'])) {
                $errorMsg = $apiResponse['error']['message'] ?? json_encode($apiResponse['error']);
                $errorType = $apiResponse['error']['code'] ?? 'api_error';
            }

            aiCentral_logMessage("GeminiProcessor: API error - $errorMsg", 'ERROR');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => $errorType,
                'raw_response_data' => $apiResponse
            ];
        }

        // Extract text response from candidates
        $responseText = '';
        if (isset($apiResponse['candidates'][0]['content']['parts'])) {
            foreach ($apiResponse['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $responseText .= $part['text'];
                }
            }
        }

        // Extract usage information
        $usage = [
            'input_tokens' => $apiResponse['usageMetadata']['promptTokenCount'] ?? 0,
            'output_tokens' => $apiResponse['usageMetadata']['candidatesTokenCount'] ?? 0,
            'thinking_tokens' => 0 // Gemini doesn't have thinking tokens
        ];

        // Extract tool calls if present (function calls)
        $toolCalls = [];
        if (isset($apiResponse['candidates'][0]['content']['parts'])) {
            foreach ($apiResponse['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['functionCall'])) {
                    $toolCalls[] = [
                        'tool' => $part['functionCall']['name'] ?? 'unknown',
                        'input' => $part['functionCall']['args'] ?? []
                    ];
                }
            }
        }

        // Get finish reason
        $finishReason = $apiResponse['candidates'][0]['finishReason'] ?? 'unknown';

        aiCentral_logMessage("GeminiProcessor: Response parsed successfully", 'DEBUG');

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
     * Build tools array for Gemini
     *
     * Gemini tool format:
     * - google_search: {} (for web search) - Updated API as of 2025
     *
     * @param array $capabilities Capabilities to enable
     * @param string $providerCode Not used for Gemini
     * @return array|null Tools array or null
     */
    public function buildTools($capabilities, $providerCode = null) {
        $tools = [];

        // Web search tool
        if (!empty($capabilities['web_search'])) {
            $tools[] = [
                'google_search' => (object)[] // Empty object
            ];
            aiCentral_logMessage("GeminiProcessor: Added google_search tool", 'DEBUG');
        }

        return empty($tools) ? null : $tools;
    }

    /**
     * Get Gemini API endpoint
     *
     * Gemini endpoint includes the model in the URL
     * Format: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
     *
     * @param string $modelCode Model code (e.g., "gemini-2.5-pro")
     * @param string $providerCode Not used for Gemini
     * @return string API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        return "https://generativelanguage.googleapis.com/v1beta/models/$modelCode:generateContent";
    }

    /**
     * Get Gemini API headers
     *
     * Gemini uses x-goog-api-key header
     *
     * @param string $apiKey API key
     * @param string $providerCode Not used for Gemini
     * @return array HTTP headers
     */
    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'x-goog-api-key: ' . $apiKey,
            'Content-Type: application/json'
        ];
    }
}
