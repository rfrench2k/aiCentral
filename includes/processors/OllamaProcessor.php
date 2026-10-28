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

        // Earlier conversation turns, then the user message
        [$turns, $prompt] = aiCentral_historyTurns($options, $prompt);
        $messages = array_merge($messages, $turns);
        // User message; Ollama takes pictures as plain base64 strings beside the text
        $userMessage = [
            'role' => 'user',
            'content' => $prompt
        ];
        if (!empty($options['images']) && is_array($options['images'])) {
            $userMessage['images'] = array_column($options['images'], 'data');
            aiCentral_logMessage("OllamaProcessor: Added " . count($userMessage['images']) . " images", 'DEBUG');
        }
        $messages[] = $userMessage;

        $request = [
            'model' => $modelCode,
            'messages' => $messages,
            'stream' => false,
        ];

        // Thinking off (tier setting, allowed by ai_models.thinking_off_mode)
        if (!empty($options['thinking_mode'])) {
            $request['think'] = false;
        }

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

        // JSON format mode - check metadata flag or if prompt mentions JSON; a json_schema option is passed to Ollama as
        // the answer's exact shape (structured output)
        if (!empty($options['json_schema'])) {
            $request['format'] = $options['json_schema'];
        } elseif (!empty($options['metadata']['json_mode'])) {
            $request['format'] = 'json';
        }
        if (isset($options['max_tokens'])) {
            $request['options']['num_predict'] = (int)$options['max_tokens'];
        }

        // How long the model stays loaded on the graphics card after this request (e.g. "2m"; 0 = unload now). The
        // server default otherwise keeps it there, taking memory other jobs need.
        if (isset($options['keep_alive'])) {
            $request['keep_alive'] = $options['keep_alive'];
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
