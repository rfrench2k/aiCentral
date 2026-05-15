<?php
/**
 * AI Central System - Base Processor Interface
 *
 * This interface defines the contract that all AI provider processors must implement.
 * Each processor handles the specific API format for one or more AI providers.
 *
 * Processors are responsible for:
 * 1. Building API requests in provider-specific format
 * 2. Parsing API responses into standard format
 * 3. Handling tool/capability formatting
 * 4. Providing endpoint URLs and headers
 *
 * @version 1.0.0
 * @date 2025-11-20
 */

interface BaseProcessor {

    /**
     * Build API request in provider-specific format
     *
     * Transforms internal prompt and options into the exact JSON structure
     * required by the provider's API.
     *
     * @param string $prompt User's text prompt
     * @param array $options Configuration options:
     *   - 'system' (string): System prompt/instructions
     *   - 'max_tokens' (int): Maximum tokens to generate
     *   - 'temperature' (float): Temperature (0-1)
     *   - 'top_p' (float): Top-p sampling parameter
     *   - 'top_k' (int): Top-k sampling parameter
     *   - 'images' (array): Array of images, each with:
     *       - 'media_type' (string): 'image/jpeg' or 'image/png'
     *       - 'data' (string): Base64-encoded image data
     *   - 'capabilities' (array): Enabled capabilities:
     *       - 'web_search' (int|null): Max uses for web search
     *       - 'web_fetch' (int|null): Max uses for web fetch
     *       - 'vision' (int|null): Max uses for vision
     *   - 'metadata' (array): Custom metadata
     *   - 'model_code' (string): Model code being used
     *   - 'provider_code' (string): Provider code (for multi-provider processors)
     *
     * @return array API request body as associative array (ready for json_encode)
     *
     * @throws Exception If required options are missing or invalid
     */
    public function buildRequest($prompt, $options);

    /**
     * Parse API response into standard format
     *
     * Transforms provider-specific API response into our standard format.
     * Must handle both success and error responses.
     *
     * @param array $apiResponse Raw API response (decoded JSON as array)
     * @param int $httpCode HTTP status code from API call
     *
     * @return array Standardized response:
     *   On success:
     *   - 'success' (bool): true
     *   - 'response' (string): Generated text response
     *   - 'usage' (array):
     *       - 'input_tokens' (int): Tokens in prompt
     *       - 'output_tokens' (int): Tokens in response
     *       - 'thinking_tokens' (int): Thinking/reasoning tokens (if applicable)
     *   - 'tool_calls' (array): Array of tool calls made (if any)
     *   - 'raw_request' (string): Original request JSON (for logging)
     *   - 'raw_response_data' (array): Original response data (for logging)
     *
     *   On error:
     *   - 'success' (bool): false
     *   - 'error' (string): Error message
     *   - 'error_type' (string): Error type/code (if available)
     *   - 'raw_request' (string): Original request JSON (for logging)
     *   - 'raw_response_data' (array): Original response data (for logging)
     */
    public function parseResponse($apiResponse, $httpCode);

    /**
     * Build tools/capabilities array in provider-specific format
     *
     * Transforms our internal capabilities format into the provider's
     * tool/function/capability format.
     *
     * @param array $capabilities Capabilities to enable:
     *   - 'web_search' (int|null): Max uses for web search
     *   - 'web_fetch' (int|null): Max uses for web fetch
     *   - 'vision' (int|null): Max uses for vision
     * @param string $providerCode Provider code (for multi-provider processors)
     *
     * @return array|null Tools array in provider format, or null if no tools needed
     */
    public function buildTools($capabilities, $providerCode = null);

    /**
     * Get API endpoint URL for this provider
     *
     * @param string $modelCode Model code being used
     * @param string $providerCode Provider code (for multi-provider processors)
     *
     * @return string Full API endpoint URL
     */
    public function getEndpoint($modelCode, $providerCode = null);

    /**
     * Get HTTP headers for API request
     *
     * @param string $apiKey API key to use
     * @param string $providerCode Provider code (for multi-provider processors)
     *
     * @return array HTTP headers as associative array
     */
    public function getHeaders($apiKey, $providerCode = null);
}
