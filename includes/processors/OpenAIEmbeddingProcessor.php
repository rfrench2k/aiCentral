<?php
/**
 * AI Central System - OpenAI Embedding Processor
 *
 * Handles embedding generation via OpenAI-compatible /v1/embeddings API.
 * Works with both OpenAI (text-embedding-3-small) and Ollama (nomic-embed-text)
 * since Ollama exposes an OpenAI-compatible endpoint at /v1/embeddings.
 *
 * API Documentation:
 * - OpenAI: https://platform.openai.com/docs/api-reference/embeddings
 * - Ollama: https://github.com/ollama/ollama/blob/main/docs/openai.md
 *
 * @version 1.0.0
 * @date 2026-03-02
 */

require_once __DIR__ . '/../../common/common_ai.php';

class OpenAIEmbeddingProcessor {

    /**
     * Build embedding API request
     *
     * @param string|array $input Single text or array of texts to embed
     * @param array $options [model_code, provider_code, dimensions]
     * @return array API request body
     */
    public function buildEmbeddingRequest($input, array $options = []): array {
        $modelCode = $options['model_code'] ?? 'text-embedding-3-small';
        $providerCode = $options['provider_code'] ?? 'openai';

        $request = [
            'model' => $modelCode,
            'input' => $input,
        ];

        // Only OpenAI supports the dimensions parameter; Ollama ignores it
        if ($providerCode === 'openai' && !empty($options['dimensions'])) {
            $request['dimensions'] = (int)$options['dimensions'];
        }

        return $request;
    }

    /**
     * Parse embedding API response
     *
     * @param array $apiResponse Raw API response
     * @param int $httpCode HTTP status code
     * @return array Standardized response
     */
    public function parseEmbeddingResponse(array $apiResponse, int $httpCode): array {
        if ($httpCode !== 200) {
            $errorMsg = $apiResponse['error']['message'] ?? $apiResponse['error'] ?? "HTTP $httpCode error";
            return [
                'success' => false,
                'error' => $errorMsg,
                'embeddings' => [],
                'usage' => ['prompt_tokens' => 0, 'total_tokens' => 0],
            ];
        }

        if (!isset($apiResponse['data']) || !is_array($apiResponse['data'])) {
            return [
                'success' => false,
                'error' => 'Invalid response format - no data array',
                'embeddings' => [],
                'usage' => ['prompt_tokens' => 0, 'total_tokens' => 0],
            ];
        }

        // Sort by index to ensure correct ordering for batch requests
        $data = $apiResponse['data'];
        usort($data, fn($a, $b) => ($a['index'] ?? 0) - ($b['index'] ?? 0));

        $embeddings = array_map(fn($d) => $d['embedding'], $data);

        $usage = [
            'prompt_tokens' => $apiResponse['usage']['prompt_tokens'] ?? 0,
            'total_tokens' => $apiResponse['usage']['total_tokens'] ?? 0,
        ];

        return [
            'success' => true,
            'error' => null,
            'embeddings' => $embeddings,
            'usage' => $usage,
        ];
    }

    /**
     * Get embedding API endpoint
     *
     * @param string $providerCode Provider code (openai, ollama)
     * @return string API endpoint URL
     */
    public function getEmbeddingEndpoint(string $providerCode = 'openai'): string {
        if ($providerCode === 'ollama') {
            $baseUrl = getenv('AICORE_OLLAMA_URL') ?: 'http://localhost:11434';
            return rtrim($baseUrl, '/') . '/v1/embeddings';
        }

        return 'https://api.openai.com/v1/embeddings';
    }

    /**
     * Get API headers
     *
     * @param string $apiKey API key
     * @param string $providerCode Provider code
     * @return array HTTP headers
     */
    public function getEmbeddingHeaders(string $apiKey, string $providerCode = 'openai'): array {
        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }
}
