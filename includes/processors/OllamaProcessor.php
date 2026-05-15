<?php
/**
 * AI Central System - Ollama Processor
 *
 * Handles API request/response formatting for Ollama (local LLM server).
 * Supports Llama, Mistral, and other models served by Ollama.
 *
 * API Documentation: https://github.com/ollama/ollama/blob/main/docs/api.md
 *
 * Key differences from cloud providers:
 * - No API key required
 * - Self-hosted endpoint (configurable via OLLAMA_URL env var)
 * - Uses /api/chat format for chat completions
 * - Supports format: "json" for structured output
 * - Token stats use prompt_eval_count / eval_count
 *
 * @version 1.0.0
 * @date 2026-02-16
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class OllamaProcessor implements BaseProcessor {

    /**
     * Build Ollama API request
     *
     * Ollama /api/chat format:
     * - model: Model name (e.g., 'llama3.1:8b')
     * - messages: Array of {role, content} objects
     * - stream: false for non-streaming
     * - format: "json" for JSON output
     * - options: {temperature, top_p, top_k}
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options
     * @return array API request body
     */
    public function buildRequest($prompt, $options) {
        aiCentral_logMessage("OllamaProcessor: Building request", 'DEBUG');

        $modelCode = $options['model_code'] ?? 'llama3.1:8b';

        $messages = [];

        // System prompt as system message
        if (!empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system']
            ];
        }

        // User message
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $request = [
            'model' => $modelCode,
            'messages' => $messages,
            'stream' => false,
        ];

        // Generation options
        $genOptions = [];
        if (isset($options['temperature'])) {
            $genOptions['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['top_p'])) {
            $genOptions['top_p'] = (float)$options['top_p'];
        }
        if (isset($options['top_k'])) {
            $genOptions['top_k'] = (int)$options['top_k'];
        }
        if (!empty($genOptions)) {
            $request['options'] = $genOptions;
        }

        // JSON format mode - check metadata flag or if prompt mentions JSON
        if (!empty($options['metadata']['json_mode'])) {
            $request['format'] = 'json';
        }

        aiCentral_logMessage("OllamaProcessor: Request built successfully for model $modelCode", 'DEBUG');
        return $request;
    }

    /**
     * Parse Ollama API response
     *
     * Ollama /api/chat response:
     * - message.content: Response text
     * - prompt_eval_count: Input tokens
     * - eval_count: Output tokens
     * - done: Boolean completion flag
     *
     * @param array $apiResponse Raw API response
     * @param int $httpCode HTTP status code
     * @return array Standardized response
     */
    public function parseResponse($apiResponse, $httpCode, $originalRequest = null) {
        aiCentral_logMessage("OllamaProcessor: Parsing response (HTTP $httpCode)", 'DEBUG');

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorMsg = $apiResponse['error'] ?? "HTTP $httpCode error";
            aiCentral_logMessage("OllamaProcessor: API error - $errorMsg", 'ERROR');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => 'http_error',
                'raw_response_data' => $apiResponse
            ];
        }

        // Extract response text
        $responseText = $apiResponse['message']['content'] ?? '';

        // Extract usage (Ollama uses different field names)
        $usage = [
            'input_tokens' => $apiResponse['prompt_eval_count'] ?? 0,
            'output_tokens' => $apiResponse['eval_count'] ?? 0,
            'thinking_tokens' => 0
        ];

        aiCentral_logMessage("OllamaProcessor: Response parsed (length: " . strlen($responseText) . ")", 'DEBUG');

        return [
            'success' => true,
            'response' => $responseText,
            'usage' => $usage,
            'tool_calls' => [],
            'stop_reason' => ($apiResponse['done'] ?? false) ? 'stop' : 'unknown',
            'raw_response_data' => $apiResponse
        ];
    }

    /**
     * Ollama doesn't support tools/capabilities
     */
    public function buildTools($capabilities, $providerCode = null) {
        return null;
    }

    /**
     * Get Ollama API endpoint
     *
     * Uses AICORE_OLLAMA_URL env var, defaults to local Ollama.
     *
     * @param string $modelCode Model code (not used)
     * @param string $providerCode Not used
     * @return string API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        $baseUrl = getenv('AICORE_OLLAMA_URL') ?: 'http://localhost:11434';
        return rtrim($baseUrl, '/') . '/api/chat';
    }

    /**
     * Get Ollama API headers
     *
     * Ollama doesn't require authentication.
     *
     * @param string $apiKey Not used (Ollama has no auth)
     * @param string $providerCode Not used
     * @return array HTTP headers
     */
    public function getHeaders($apiKey, $providerCode = null) {
        return [
            'Content-Type: application/json'
        ];
    }
}
