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
        $modelCode = $options['model_code'] ?? 'claude-sonnet-5-5';
        $request = [
            'model' => $modelCode,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        // Add optional parameters. Opus 4.7 and later, Sonnet 5 and Fable reject the
        // sampling parameters (400), so they are only sent to the models that take them.
        $acceptsSampling = !preg_match('/^claude-(opus-4-[78]|opus-5|sonnet-5|haiku-5|fable|mythos)/', $modelCode);
        if ($acceptsSampling && isset($options['temperature'])) {
            $request['temperature'] = (float)$options['temperature'];
        }
        if ($acceptsSampling && isset($options['top_p'])) {
            $request['top_p'] = (float)$options['top_p'];
        }
        if ($acceptsSampling && isset($options['top_k'])) {
            $request['top_k'] = (int)$options['top_k'];
        }

        // Effort and thinking, set per feature and tier in AI Central (ai_applyTierSettings checked them against the model)
        if (!empty($options['effort'])) {
            $request['output_config']['effort'] = $options['effort'];
        }
        if (!empty($options['thinking_mode'])) {
            $request['thinking'] = ['type' => $options['thinking_mode']];
        }
        // Structured output: the answer is valid JSON matching the caller's schema (same option as the CLI processor)
        if (!empty($options['json_schema']) && is_array($options['json_schema'])) {
            $request['output_config']['format'] = ['type' => 'json_schema', 'schema' => $options['json_schema']];
        }

        // System prompt (top-level field): a string, or a list of text blocks when parts of it
        // are marked for prompt caching ([['type' => 'text', 'text' => ..., 'cache_control' => ...], ...])
        if (!empty($options['system'])) {
            $request['system'] = $options['system'];
        }

        // Prompt caching of the conversation so far (the API places the breakpoint itself);
        // 'cache' => true for the 5-minute cache, '1h' for the 1-hour cache
        if (!empty($options['cache'])) {
            $request['cache_control'] = ['type' => 'ephemeral'];
            if ($options['cache'] === '1h') {
                $request['cache_control']['ttl'] = '1h';
            }
        }

        // Fast mode (set per feature and tier; only on models that have it) and the
        // model's server-side fallback when its safety classifiers decline a request
        if (($options['speed'] ?? '') === 'fast') {
            $request['speed'] = 'fast';
        }
        if (!empty($options['fallbacks'])) {
            $request['fallbacks'] = $options['fallbacks'];
        }

        // Build messages array: earlier conversation turns first, then this turn
        [$messages, $prompt] = aiCentral_historyTurns($options, $prompt);
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

        // Source passages the reply may quote, with citations on: each part of the reply that
        // uses one comes back tied to the exact text it came from (see parseResponse 'segments')
        if (!empty($options['sources']) && is_array($options['sources'])) {
            foreach ($options['sources'] as $source) {
                $block = [
                    'type' => 'document',
                    'source' => ['type' => 'text', 'media_type' => 'text/plain', 'data' => (string)$source['text']],
                    'citations' => ['enabled' => true],
                ];
                if (!empty($source['title'])) {
                    $block['title'] = (string)$source['title'];
                }
                $content[] = $block;
            }
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

        // The calling app's own tools (run by its tool_handler; see AIProviderManager::makeRequest).
        // When streamed, inputs stream as they are written (validated before use). A caller that
        // warms the prompt cache sets eager_input_streaming itself, so warm-up and messages match.
        if (!empty($options['tools']) && is_array($options['tools'])) {
            foreach ($options['tools'] as $tool) {
                if (!empty($options['stream']) && !isset($tool['eager_input_streaming'])) {
                    $tool['eager_input_streaming'] = true;
                }
                $request['tools'][] = $tool;
            }
        }

        // Claude doesn't support top-level metadata field
        // Metadata is handled internally for logging only

        // Streamed when the caller takes the text as it arrives (options['on_text'])
        $request['stream'] = !empty($options['stream']);

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

        // Prompt-cache tokens. Anthropic reports these SEPARATELY from input_tokens:
        //   cache_read   = served from cache (~0.1x input price)
        //   cache_creation = written to cache (5-min TTL ~1.25x, 1-hour TTL ~2x)
        // Captured here so cost + the usage dashboards reflect them.
        $cc = $apiResponse['usage']['cache_creation'] ?? [];
        $usage['cache_read_tokens']     = (int)($apiResponse['usage']['cache_read_input_tokens'] ?? 0);
        $usage['cache_write_5m_tokens'] = (int)($cc['ephemeral_5m_input_tokens'] ?? 0);
        $usage['cache_write_1h_tokens'] = (int)($cc['ephemeral_1h_input_tokens'] ?? 0);
        // Older responses give only the total with no 5m/1h split — treat it as 5-min.
        $ccTotal = (int)($apiResponse['usage']['cache_creation_input_tokens'] ?? 0);
        if ($ccTotal > 0 && ($usage['cache_write_5m_tokens'] + $usage['cache_write_1h_tokens']) === 0) {
            $usage['cache_write_5m_tokens'] = $ccTotal;
        }
        $usage['cache_write_tokens'] = $usage['cache_write_5m_tokens'] + $usage['cache_write_1h_tokens'];

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

        // The reply as text segments, each with the source passages it cites (options['sources'])
        $segments = [];
        foreach ($apiResponse['content'] ?? [] as $block) {
            if (($block['type'] ?? '') !== 'text') {
                continue;
            }
            $cites = [];
            foreach ($block['citations'] ?? [] as $c) {
                if (isset($c['document_index'])) {
                    $cites[] = [
                        'source_index' => (int)$c['document_index'],
                        'cited_text' => (string)($c['cited_text'] ?? ''),
                        'start' => $c['start_char_index'] ?? null,
                        'end' => $c['end_char_index'] ?? null,
                    ];
                }
            }
            $segments[] = ['text' => $block['text'], 'citations' => $cites];
        }

        aiCentral_logMessage("ClaudeProcessor: Response parsed successfully", 'DEBUG');

        // A request the model declined (stop_reason 'refusal'), even after any fallback model
        if (($apiResponse['stop_reason'] ?? '') === 'refusal') {
            aiCentral_logMessage("ClaudeProcessor: request declined (" . ($apiResponse['stop_details']['category'] ?? 'no category') . ")", 'WARNING');
        }
        $usage['speed'] = $apiResponse['usage']['speed'] ?? null;

        return [
            'success' => true,
            'response' => $responseText,
            'usage' => $usage,
            'tool_calls' => $toolCalls,
            'stop_reason' => $apiResponse['stop_reason'] ?? 'unknown',
            'message_id' => $apiResponse['id'] ?? null,
            'content' => $apiResponse['content'] ?? [],
            'segments' => $segments,
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

    /** Takes options['sources'] as citable documents (other processors get them as prompt text). */
    public function supportsSources() {
        return true;
    }

    /**
     * Beta headers a built request needs (fast mode, server-side fallbacks).
     *
     * @param array $request Request body from buildRequest()
     * @return array HTTP header lines (empty when none)
     */
    public function betaHeaders(array $request) {
        $betas = [];
        if (!empty($request['fallbacks'])) {
            $betas[] = 'server-side-fallback-2026-07-01';
        }
        if (($request['speed'] ?? '') === 'fast') {
            $betas[] = 'fast-mode-2026-02-01';
        }
        return $betas ? ['anthropic-beta: ' . implode(',', $betas)] : [];
    }

    /**
     * The same request at standard speed: fast mode has its own, smaller rate limit,
     * so a 429 in fast mode is retried this way.
     *
     * @return array|null The request without fast mode, or null when it wasn't fast
     */
    public function withoutFastMode(array $request) {
        if (($request['speed'] ?? '') !== 'fast') {
            return null;
        }
        unset($request['speed']);
        return $request;
    }

    /**
     * Streaming: start an empty message that streamEvent() fills in.
     */
    public function streamStart() {
        return ['message' => ['id' => null, 'content' => [], 'stop_reason' => null, 'usage' => []], 'json' => [], 'error' => null];
    }

    /**
     * Streaming: apply one server-sent event (its decoded "data" JSON) to the message
     * being assembled, and pass reply text to $onText as it arrives.
     */
    public function streamEvent(array &$state, array $event, callable $onText) {
        $msg = &$state['message'];
        switch ($event['type'] ?? '') {
            case 'message_start':
                $msg = array_merge($msg, $event['message'] ?? []);
                $msg['content'] = [];
                break;
            case 'content_block_start':
                $msg['content'][$event['index']] = $event['content_block'];
                if (($event['content_block']['type'] ?? '') === 'tool_use') {
                    $state['json'][$event['index']] = '';
                }
                break;
            case 'content_block_delta':
                $i = $event['index'];
                $delta = $event['delta'] ?? [];
                switch ($delta['type'] ?? '') {
                    case 'text_delta':
                        $msg['content'][$i]['text'] = ($msg['content'][$i]['text'] ?? '') . $delta['text'];
                        $onText($delta['text']);
                        break;
                    case 'thinking_delta':
                        $msg['content'][$i]['thinking'] = ($msg['content'][$i]['thinking'] ?? '') . $delta['thinking'];
                        break;
                    case 'signature_delta':
                        $msg['content'][$i]['signature'] = $delta['signature'];
                        break;
                    case 'input_json_delta':
                        $state['json'][$i] = ($state['json'][$i] ?? '') . $delta['partial_json'];
                        break;
                    case 'citations_delta':
                        $msg['content'][$i]['citations'][] = $delta['citation'];
                        break;
                }
                break;
            case 'content_block_stop':
                $i = $event['index'];
                if (isset($state['json'][$i])) {
                    // Tool input streams unvalidated: a broken or partial JSON is kept as null for the loop to report
                    $input = json_decode($state['json'][$i] === '' ? '{}' : $state['json'][$i], true);
                    $msg['content'][$i]['input'] = is_array($input) ? $input : null;
                }
                break;
            case 'message_delta':
                $msg['stop_reason'] = $event['delta']['stop_reason'] ?? $msg['stop_reason'];
                if (isset($event['delta']['stop_details'])) {
                    $msg['stop_details'] = $event['delta']['stop_details'];
                }
                $msg['usage'] = array_merge($msg['usage'] ?? [], $event['usage'] ?? []);
                break;
            case 'error':
                $state['error'] = $event['error'] ?? ['message' => 'stream error'];
                break;
        }
    }

    /**
     * Streaming: the assembled message, in the same shape as a non-streamed response
     * (so parseResponse() handles both). A stream error comes back as an error response.
     *
     * @return array ['data' => array, 'http_code' => int]
     */
    public function streamFinish(array $state) {
        if ($state['error']) {
            return ['data' => ['error' => $state['error']], 'http_code' => 500];
        }
        $msg = $state['message'];
        ksort($msg['content']);
        $msg['content'] = array_values($msg['content']);
        return ['data' => $msg, 'http_code' => 200];
    }

    /**
     * The assistant turn to send back after the app's tools ran. Blocks go back unchanged,
     * except after a fallback (a decline part-way through): before the last fallback block
     * only text is kept, and the fallback markers themselves are dropped.
     */
    public function assistantTurn(array $content) {
        $lastFallback = null;
        foreach ($content as $i => $block) {
            if (($block['type'] ?? '') === 'fallback') {
                $lastFallback = $i;
            }
        }
        $out = [];
        foreach ($content as $i => $block) {
            $type = $block['type'] ?? '';
            if ($type === 'fallback' || ($lastFallback !== null && $i < $lastFallback && $type !== 'text')) {
                continue;
            }
            if ($type === 'tool_use') {
                $block['input'] = is_array($block['input'] ?? null) ? (object)$block['input'] : new stdClass();
            }
            $out[] = $block;
        }
        return ['role' => 'assistant', 'content' => $out];
    }
}
