<?php
/**
 * AI Central System - AI Provider Manager (Refactored)
 *
 * Now uses the Processor Pattern for provider API abstraction.
 * Each provider's API format is handled by a dedicated processor class.
 *
 * Version 2.0 - Processor Pattern Implementation
 * Date: 2025-11-20
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../common/common_ai.php';
require_once __DIR__ . '/../common/aPRIV_DB_AI.php';
require_once __DIR__ . '/processors/BaseProcessor.php';
require_once __DIR__ . '/ToolManager.php'; // Still used for parseToolUsage

class AIProviderManager {
    private $provider;
    private $model;
    private $apiKey;
    private $userId;
    private $programId;
    private $featureCode;
    private $providerDetails;
    private $modelDetails;

    /**
     * Constructor
     *
     * @param string $provider Provider code (claude, openai, kimi, grok, gemini)
     * @param string $model Model code
     * @param string $apiKey API key to use
     * @param string $userId User ID making the request
     * @param string $programId Program ID (the calling app's program code)
     * @param string $featureCode Feature code (e.g., item_analysis, chat_assistant)
     */
    public function __construct($provider, $model, $apiKey, $userId, $programId, $featureCode) {
        $this->provider = $provider;
        $this->model = $model;
        $this->apiKey = $apiKey;
        $this->userId = $userId;
        $this->programId = $programId;
        $this->featureCode = $featureCode;

        $this->loadProviderDetails();
        $this->loadModelDetails();
    }

    /**
     * Load provider details from database
     */
    private function loadProviderDetails() {
        $conn = ai_getDBConnection();

        $sql = "SELECT * FROM ai_providers WHERE provider_code = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $providerCode = $this->provider;
        $stmt->bind_param('s', $providerCode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Provider not found or inactive: " . $this->provider);
        }

        $this->providerDetails = $result->fetch_assoc();
        $stmt->close();
    }

    /**
     * Load model details from database. The same model_code can exist under
     * multiple providers (e.g. an Anthropic API row and a claude_cli
     * row); disambiguate using the constructor's provider
     * argument so we route to the correct processor class.
     */
    private function loadModelDetails() {
        $conn = ai_getDBConnection();

        $sql = "SELECT m.* FROM ai_models m
                JOIN ai_providers p ON m.provider_id = p.provider_id
                WHERE m.model_code = ? AND p.provider_code = ? AND m.is_active = 1
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $modelCode = $this->model;
        $providerCode = $this->provider;
        $stmt->bind_param('ss', $modelCode, $providerCode);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Fall back to model_code-only lookup so single-provider models still work
            $sql = "SELECT * FROM ai_models WHERE model_code = ? AND is_active = 1";
            $stmt->close();
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $modelCode);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Model not found or inactive: " . $this->model);
            }
        }

        $this->modelDetails = $result->fetch_assoc();
        $stmt->close();
    }

    /**
     * Make API request using processor pattern
     *
     * @param string $prompt User prompt
     * @param array $options Options (max_tokens, temperature, system, images, capabilities, etc), plus:
     *   - 'on_text' (callable): stream the reply; called with each piece of text as it arrives
     *   - 'tools' (array): the app's own tool definitions (provider format)
     *   - 'tool_handler' (callable): fn(string $name, array $input): string|array, runs those tools;
     *       the request continues with the results until the model is done (one usage row per round)
     *   - 'on_tool' (callable): fn(string $name, array $input), told when a tool is about to run
     *   - 'max_tool_rounds' (int): rounds of tool use allowed (default 4)
     *   - 'cache' (true|'1h'): prompt-cache the conversation; 'system' may also be a list of
     *       blocks with their own cache_control
     *   - 'fallbacks' (false): turn off the model's server-side fallback (on by default where the model has one)
     *   - 'sources' (array): [['title' => ..., 'text' => ...], ...] passages the reply may quote; on Claude they are
     *       cited, and 'segments' says which part of the reply came from which source
     * @return array ['success' => bool, 'response' => string, 'usage' => array, 'cost' => array, 'tool_calls' => array,
     *                'tool_cost' => float, 'message_id' => string, 'stop_reason' => string, 'error' => string,
     *                'segments' => [['text' => string, 'citations' => [['source_index', 'cited_text', 'start', 'end'], ...]], ...]]
     */
    public function makeRequest($prompt, $options = []) {
        $startTime = microtime(true);
        $onText = (isset($options['on_text']) && is_callable($options['on_text'])) ? $options['on_text'] : null;
        $toolHandler = (isset($options['tool_handler']) && is_callable($options['tool_handler'])) ? $options['tool_handler'] : null;
        $onTool = (isset($options['on_tool']) && is_callable($options['on_tool'])) ? $options['on_tool'] : null;
        unset($options['on_text'], $options['tool_handler'], $options['on_tool']);   // never part of a request or log

        try {
            // Get processor class from model details
            $processorClass = $this->modelDetails['processor_class'] ?? 'OpenAIProcessor';
            $processorFile = __DIR__ . '/processors/' . $processorClass . '.php';

            // Load processor class if not already loaded
            if (!class_exists($processorClass)) {
                if (!file_exists($processorFile)) {
                    throw new Exception("Processor file not found: $processorFile");
                }
                require_once $processorFile;
            }

            aiCentral_logMessage("Using processor: $processorClass for model: {$this->model}", 'DEBUG');

            // Instantiate processor
            $processor = new $processorClass();

            // For KimiProcessor, set API key and endpoint for 2-tier calls
            if ($processorClass === 'KimiProcessor' && method_exists($processor, 'setApiKeyAndEndpoint')) {
                $endpoint = $processor->getEndpoint($this->model, $this->provider);
                $processor->setApiKeyAndEndpoint($this->apiKey, $endpoint);
            }

            // Add model and provider info to options for processor
            $options['model_code'] = $this->model;
            $options['provider_code'] = $this->provider;

            // The model's server-side fallback (ai_models.fallback_mode), unless the caller turned it off.
            // Not on a cache warm-up (max_tokens 0), which produces no answer to fall back for.
            if (!array_key_exists('fallbacks', $options) && !empty($this->modelDetails['fallback_mode'])
                && (!isset($options['max_tokens']) || (int)$options['max_tokens'] > 0)) {
                $options['fallbacks'] = $this->modelDetails['fallback_mode'];
            }
            if (($options['fallbacks'] ?? null) === false) {
                unset($options['fallbacks']);
            }

            // Source passages: citable documents where the processor supports them, else plain prompt text
            if (!empty($options['sources']) && !method_exists($processor, 'supportsSources')) {
                $sourceText = '';
                foreach ($options['sources'] as $source) {
                    $sourceText .= '<source title="' . htmlspecialchars((string)($source['title'] ?? ''), ENT_QUOTES) . "\">\n" . $source['text'] . "\n</source>\n\n";
                }
                $prompt = $sourceText . $prompt;
                unset($options['sources']);
            }

            // Stream when the caller takes text as it arrives and the processor can stream
            // (not on a cache warm-up: max_tokens 0 has no text, and the API refuses to stream it)
            $streaming = $onText && method_exists($processor, 'streamEvent')
                && (!isset($options['max_tokens']) || (int)$options['max_tokens'] > 0);
            if ($streaming) {
                $options['stream'] = true;
            }

            // Build request using processor
            $requestBody = $processor->buildRequest($prompt, $options);
            $endpoint = $processor->getEndpoint($this->model, $this->provider);
            aiCentral_logMessage("API Endpoint: $endpoint", 'DEBUG');

            $maxRounds = ($toolHandler && method_exists($processor, 'assistantTurn')) ? max(0, (int)($options['max_tool_rounds'] ?? 4)) : 0;
            $responseText = '';
            $usageTotal = ['input_tokens' => 0, 'output_tokens' => 0, 'thinking_tokens' => 0, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0];
            $costTotal = ['input_cost' => 0.0, 'output_cost' => 0.0, 'tool_cost' => 0.0, 'thinking_cost' => 0.0, 'total_cost' => 0.0];
            $allToolCalls = [];
            $segments = [];
            $messageId = null;
            $result = null;

            for ($round = 0; ; $round++) {
                $roundStart = microtime(true);
                [$apiResponse, $requestBody] = $this->sendRequest($processor, $endpoint, $requestBody, $streaming ? $onText : null);

                // Parse response using processor
                // Pass original request for processors that need it (e.g., KimiProcessor for 2-tier calls)
                $result = $processor->parseResponse($apiResponse['data'], $apiResponse['http_code'], $requestBody);

                // Add raw request to result for logging
                $result['raw_request'] = json_encode($requestBody, JSON_PRETTY_PRINT);
                $loggedPrompt = $round === 0 ? $prompt : "[tool results, round $round] " . $prompt;

                if (!$result['success']) {
                    // Log failure - include raw request and response
                    $this->logUsage(
                        0,
                        0,
                        ['input_cost' => 0, 'output_cost' => 0, 'thinking_cost' => 0, 'tool_cost' => 0, 'total_cost' => 0],
                        (int)((microtime(true) - $roundStart) * 1000),
                        'failed',
                        $result['error'],
                        $options['run_id'] ?? null,
                        $loggedPrompt,
                        null,
                        $options['metadata'] ?? null,
                        $result['raw_request'],
                        [],
                        0.0,
                        0,
                        $result['raw_response_data']
                    );

                    return [
                        'success' => false,
                        'response' => $responseText !== '' ? $responseText : null,
                        'usage' => null,
                        'cost' => 0,
                        'tool_calls' => [],
                        'tool_cost' => 0.0,
                        'error' => $result['error']
                    ];
                }

                $round_ = $this->recordSuccess($result, $loggedPrompt, $options, $requestBody, (int)((microtime(true) - $roundStart) * 1000));
                $responseText .= $result['response'];
                $messageId = $messageId ?? ($result['message_id'] ?? null);
                foreach ($usageTotal as $k => $v) {
                    $usageTotal[$k] = $v + (int)($result['usage'][$k] ?? 0);
                }
                foreach ($costTotal as $k => $v) {
                    $costTotal[$k] = $v + (float)($round_['cost'][$k] ?? 0);
                }
                $allToolCalls = array_merge($allToolCalls, $round_['tool_calls']);
                $segments = array_merge($segments, $result['segments'] ?? [['text' => (string)$result['response'], 'citations' => []]]);

                // The app's tools: run what the model asked for, then let it continue
                if ($round < $maxRounds && ($result['stop_reason'] ?? '') === 'tool_use') {
                    $toolResults = [];
                    foreach ($result['content'] ?? [] as $block) {
                        if (($block['type'] ?? '') !== 'tool_use') {
                            continue;
                        }
                        $input = $block['input'] ?? null;
                        if (!is_array($input)) {
                            $toolResults[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'], 'is_error' => true,
                                'content' => 'INVALID_JSON: the tool input could not be read. Call the tool again with valid JSON.'];
                            continue;
                        }
                        try {
                            if ($onTool) {
                                $onTool($block['name'], $input);
                            }
                            $output = $toolHandler($block['name'], $input);
                            $toolResults[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'],
                                'content' => is_string($output) ? $output : json_encode($output)];
                        } catch (\Throwable $e) {
                            aiCentral_logMessage("Tool {$block['name']} failed: " . $e->getMessage(), 'ERROR');
                            $toolResults[] = ['type' => 'tool_result', 'tool_use_id' => $block['id'], 'is_error' => true,
                                'content' => 'The tool failed: ' . $e->getMessage()];
                        }
                    }
                    $requestBody['messages'][] = $processor->assistantTurn($result['content']);
                    $requestBody['messages'][] = ['role' => 'user', 'content' => $toolResults];
                    if ($responseText !== '' && !preg_match('/\s$/', $responseText)) {
                        $responseText .= ' ';
                        if ($streaming) {
                            $onText(' ');
                        }
                    }
                    continue;
                }
                break;
            }

            $costTotal = array_map(fn($v) => round($v, 6), $costTotal);
            return [
                'success' => true,
                'response' => $responseText,
                'model' => $this->model,
                'usage' => array_merge($result['usage'], $usageTotal),
                'cost' => $costTotal,
                'tool_calls' => $allToolCalls,
                'tool_cost' => $costTotal['tool_cost'],
                'thinking_cost' => $costTotal['thinking_cost'],
                'message_id' => $messageId,
                'segments' => $segments,
                'stop_reason' => $result['stop_reason'] ?? null,
                'responseTime' => (int)((microtime(true) - $startTime) * 1000),
                'error' => null
            ];
        } catch (Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            aiCentral_logMessage("Exception in makeRequest: " . $e->getMessage(), 'ERROR');

            $this->logUsage(
                0,
                0,
                ['input_cost' => 0, 'output_cost' => 0, 'thinking_cost' => 0, 'tool_cost' => 0, 'total_cost' => 0],
                $responseTime,
                'failed',
                $e->getMessage(),
                $options['run_id'] ?? null,
                $prompt,
                null,
                $options['metadata'] ?? null,
                null,
                [],
                0.0,
                0,
                null
            );

            return [
                'success' => false,
                'response' => null,
                'usage' => null,
                'cost' => 0,
                'tool_calls' => [],
                'tool_cost' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send one built request: run locally (CLI processor), stream, or plain HTTP.
     * A rate-limited fast-mode request (429) is sent again at standard speed.
     *
     * @return array [apiResponse ['data', 'http_code', 'error'], the request as sent]
     */
    private function sendRequest($processor, $endpoint, array $requestBody, ?callable $onText) {
        // 'local://' endpoints are handled by the processor directly (e.g. ClaudeCliProcessor
        // runs a subprocess); everything else goes over HTTP.
        if (strpos($endpoint, 'local://') === 0 && method_exists($processor, 'executeLocal')) {
            $apiResponse = $processor->executeLocal($requestBody, $this->model);
            // Surface the detailed CLI error to the log; parseResponse() collapses
            // it to a generic "non-200 or non-array response" which is useless
            // for diagnosing CLI auth / subprocess issues.
            if (!empty($apiResponse['error'])) {
                aiCentral_logMessage("CLI executeLocal error (http={$apiResponse['http_code']}): " . $apiResponse['error'], 'ERROR');
            }
            return [$apiResponse, $requestBody];
        }

        $send = function (array $body) use ($processor, $endpoint, $onText) {
            $headers = $processor->getHeaders($this->apiKey, $this->provider);
            if (method_exists($processor, 'betaHeaders')) {
                $headers = array_merge($headers, $processor->betaHeaders($body));
            }
            return $onText ? $this->callAPIStream($processor, $endpoint, $body, $headers, $onText)
                           : $this->callAPI($endpoint, $body, $headers);
        };

        $apiResponse = $send($requestBody);
        if ($apiResponse['http_code'] === 429 && method_exists($processor, 'withoutFastMode')) {
            $standard = $processor->withoutFastMode($requestBody);
            if ($standard !== null) {
                aiCentral_logMessage("Fast mode rate-limited for {$this->model}; sending at standard speed", 'WARNING');
                $requestBody = $standard;
                $apiResponse = $send($requestBody);
            }
        }
        return [$apiResponse, $requestBody];
    }

    /**
     * Cost and usage log for one successful API call.
     *
     * @return array ['cost' => array, 'tool_calls' => array]
     */
    private function recordSuccess(array $result, $prompt, array $options, array $requestBody, $responseTime) {
        // Parse tool usage from response
        $toolCalls = [];
        $toolCallsForCostCalc = [];

        if (isset($result['raw_response_data'])) {
            $toolManager = new ToolManager(
                $this->provider,
                $this->model,
                $this->modelDetails['model_id']
            );
            $toolCalls = $toolManager->parseToolUsage($result['raw_response_data'], $this->provider);

            // Convert tool calls format for cost calculation
            foreach ($toolCalls as $toolCall) {
                $toolCallsForCostCalc[$toolCall['type']] = $toolCall['count'];
            }
        }

        // Extract thinking tokens
        $thinkingTokens = $result['usage']['thinking_tokens'] ?? 0;
        $speed = $result['usage']['speed'] ?? ($requestBody['speed'] ?? null);

        // Cost: when the processor reports its own cost (e.g. ClaudeCliProcessor
        // reads total_cost_usd straight from the CLI's JSON), trust that over the
        // token-x-rate calculation. The CLI's cost reflects the real charge to
        // the Claude Code subscription / $200 credit pool, and the ai_models
        // rows for claude_cli intentionally have $0 per-token pricing.
        if (isset($result['cli_total_cost_usd']) && $result['cli_total_cost_usd'] > 0) {
            $cliTotal = (float)$result['cli_total_cost_usd'];
            $inT = (int)$result['usage']['input_tokens'];
            $outT = (int)$result['usage']['output_tokens'];
            $sumT = max(1, $inT + $outT);
            $costBreakdown = [
                'input_cost_usd'     => $cliTotal * ($inT  / $sumT),
                'output_cost_usd'    => $cliTotal * ($outT / $sumT),
                'tool_call_cost_usd' => 0.0,
                'thinking_cost_usd'  => 0.0,
                'total_cost_usd'     => $cliTotal,
            ];
        } else {
            // Standard path: compute from this model row's per-million pricing,
            // including prompt-cache read/write tokens and fast mode.
            $costBreakdown = aiCentral_calculateRequestCost(
                $this->model,
                $result['usage']['input_tokens'],
                $result['usage']['output_tokens'],
                $toolCallsForCostCalc,
                0, // tool_result_tokens
                $thinkingTokens,
                $result['usage']['cache_read_tokens']     ?? 0,
                $result['usage']['cache_write_5m_tokens']  ?? 0,
                $result['usage']['cache_write_1h_tokens']  ?? 0,
                $this->modelDetails['model_id'],
                $speed
            );
        }

        // Format cost for return/logging
        $cost = [
            'input_cost' => $costBreakdown['input_cost_usd'],
            'output_cost' => $costBreakdown['output_cost_usd'],
            'tool_cost' => $costBreakdown['tool_call_cost_usd'],
            'thinking_cost' => $costBreakdown['thinking_cost_usd'],
            'total_cost' => $costBreakdown['total_cost_usd']
        ];

        // Fast mode is recorded with the call
        $metadata = $options['metadata'] ?? null;
        if ($speed && $speed !== 'standard') {
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?: ['note' => $metadata];
            }
            $metadata = array_merge((array)$metadata, ['speed' => $speed]);
        }

        // Log usage
        $this->logUsage(
            $result['usage']['input_tokens'],
            $result['usage']['output_tokens'],
            $cost,
            $responseTime,
            'success',
            null,
            $options['run_id'] ?? null,
            $prompt,
            $result['response'],
            $metadata,
            $result['raw_request'],
            $toolCalls,
            $cost['tool_cost'],
            $thinkingTokens,
            $result['raw_response_data'],
            $result['usage']['cache_read_tokens'] ?? 0,
            $result['usage']['cache_write_tokens'] ?? 0
        );

        return ['cost' => $cost, 'tool_calls' => $toolCalls];
    }

    /**
     * Streamed API call: server-sent events go to the processor (streamEvent), which passes
     * reply text to $onText as it arrives and assembles the full message.
     *
     * @return array ['data' => array, 'http_code' => int, 'error' => string|null] like callAPI()
     */
    private function callAPIStream($processor, $endpoint, $body, $headers, callable $onText) {
        $state = $processor->streamStart();
        $buffer = '';
        $errorBody = '';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, AI_REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$errorBody, &$state, $processor, $onText) {
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                $errorBody .= $chunk;          // an error reply is plain JSON, not events
                return strlen($chunk);
            }
            $buffer .= str_replace("\r\n", "\n", $chunk);
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                foreach (explode("\n", $event) as $line) {
                    if (strncmp($line, 'data:', 5) === 0) {
                        $data = json_decode(trim(substr($line, 5)), true);
                        if (is_array($data)) {
                            $processor->streamEvent($state, $data, $onText);
                        }
                    }
                }
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            aiCentral_logMessage("CURL error (stream): $curlError", 'ERROR');
            return ['data' => ['error' => ['message' => 'Curl error: ' . $curlError]], 'http_code' => 0, 'error' => $curlError];
        }
        if ($httpCode !== 200) {
            $data = json_decode($errorBody, true);
            return ['data' => is_array($data) ? $data : ['error' => ['message' => "HTTP $httpCode"]], 'http_code' => $httpCode, 'error' => null];
        }
        $finished = $processor->streamFinish($state);
        return ['data' => $finished['data'], 'http_code' => $finished['http_code'], 'error' => null];
    }

    /**
     * Generic API caller using CURL
     *
     * Makes HTTP POST request to any API endpoint with given body and headers.
     *
     * @param string $endpoint Full API endpoint URL
     * @param array $body Request body (will be JSON encoded)
     * @param array $headers HTTP headers
     * @return array ['data' => array, 'http_code' => int, 'error' => string|null]
     */
    private function callAPI($endpoint, $body, $headers) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, AI_REQUEST_TIMEOUT);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            aiCentral_logMessage("CURL error: $curlError", 'ERROR');
            return [
                'data' => ['error' => ['message' => 'Curl error: ' . $curlError]],
                'http_code' => 0,
                'error' => $curlError
            ];
        }

        $data = json_decode($response, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            aiCentral_logMessage("JSON decode error: " . json_last_error_msg(), 'ERROR');
            return [
                'data' => ['error' => ['message' => 'Invalid JSON response']],
                'http_code' => $httpCode,
                'error' => 'Invalid JSON'
            ];
        }

        return [
            'data' => $data,
            'http_code' => $httpCode,
            'error' => null
        ];
    }

    /**
     * Log usage to database
     */
    private function logUsage($inputTokens, $outputTokens, $cost, $responseTime, $status, $error, $runId, $promptText, $responseText, $metadata, $rawRequest, $toolCalls = [], $toolCost = 0.0, $thinkingTokens = 0, $rawResponse = null, $cacheReadTokens = 0, $cacheWriteTokens = 0) {
      try {
        $conn = ai_getDBConnection();

        $sql = "INSERT INTO ai_usage_log (
            user_id, program_id, feature_code, provider_id, model_id,
            key_type, input_tokens, output_tokens, thinking_tokens, input_cost_usd, output_cost_usd, thinking_cost_usd,
            response_time_ms, run_id, status, error_message, prompt_text, response_text, prompt_to_ai, complete_ai_response, request_metadata,
            tool_calls_json, tool_call_count, tool_call_cost_usd, cache_read_tokens, cache_write_tokens
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $userId = $this->userId;
        $programId = $this->programId;
        $featureCode = $this->featureCode;
        $providerId = $this->providerDetails['provider_id'];
        $modelId = $this->modelDetails['model_id'];
        $keyType = 'system';
        $inputCost = $cost['input_cost'] ?? 0;
        $outputCost = $cost['output_cost'] ?? 0;
        $thinkingCost = $cost['thinking_cost'] ?? 0;
        $metadataJson = $metadata ? json_encode($metadata) : null;
        $toolCallsJson = !empty($toolCalls) ? json_encode($toolCalls) : null;
        $completeResponse = $rawResponse ? json_encode($rawResponse, JSON_PRETTY_PRINT) : null;

        // Calculate total tool call count
        $toolCallCount = 0;
        if (!empty($toolCalls)) {
            foreach ($toolCalls as $toolCall) {
                $toolCallCount += $toolCall['count'] ?? 0;
            }
        }

        // Strip invalid UTF-8 from text columns so emails with malformed bytes
        // (truncated multi-byte chars, mixed encodings) don't trigger MySQL
        // "Incorrect string value" errors and abort the insert.
        $promptText      = $promptText      === null ? null : mb_convert_encoding($promptText,      'UTF-8', 'UTF-8');
        $responseText    = $responseText    === null ? null : mb_convert_encoding($responseText,    'UTF-8', 'UTF-8');
        $rawRequest      = $rawRequest      === null ? null : mb_convert_encoding($rawRequest,      'UTF-8', 'UTF-8');
        $completeResponse = $completeResponse === null ? null : mb_convert_encoding($completeResponse, 'UTF-8', 'UTF-8');

        // prompt_text / response_text are TEXT columns (65,535-byte cap). Large
        // model output (e.g. an event-dense PDF extraction) can exceed that, so
        // truncate the logged copy here; otherwise the usage-log INSERT fails and
        // aborts an otherwise-successful request. (prompt_to_ai / complete_ai_response
        // are MEDIUMTEXT and don't need this.)
        if ($promptText !== null && strlen($promptText) > 65000) {
            $promptText = mb_strcut($promptText, 0, 65000, 'UTF-8') . ' …[truncated]';
        }
        if ($responseText !== null && strlen($responseText) > 65000) {
            $responseText = mb_strcut($responseText, 0, 65000, 'UTF-8') . ' …[truncated]';
        }

        $stmt->bind_param(
            // run_id (pos 14) is varchar(50), not int -- bind it as a string so a
            // non-numeric run_id isn't silently cast to 0.
            'sssiisiiidddisssssssssidii',
            $userId,
            $programId,
            $featureCode,
            $providerId,
            $modelId,
            $keyType,
            $inputTokens,
            $outputTokens,
            $thinkingTokens,
            $inputCost,
            $outputCost,
            $thinkingCost,
            $responseTime,
            $runId,
            $status,
            $error,
            $promptText,
            $responseText,
            $rawRequest,
            $completeResponse,
            $metadataJson,
            $toolCallsJson,
            $toolCallCount,
            $toolCost,
            $cacheReadTokens,
            $cacheWriteTokens
        );

        if (!$stmt->execute()) {
            aiCentral_logMessage("Failed to log usage: " . $stmt->error, 'ERROR');
        }
        $stmt->close();
      } catch (\Throwable $e) {
        // Best-effort: usage logging must NEVER fail an otherwise-successful AI
        // request (e.g. a member not yet synced into aicore.users). Log and move on.
        aiCentral_logMessage("Usage logging skipped: " . $e->getMessage(), 'WARNING');
      }
    }

    /**
     * Make embedding API request
     *
     * Generates vector embeddings for one or more texts using the configured
     * embedding model. Uses OpenAIEmbeddingProcessor for both OpenAI and Ollama
     * providers (Ollama exposes an OpenAI-compatible /v1/embeddings endpoint).
     *
     * @param string|array $input Single text or array of texts to embed
     * @param array $options Options (dimensions, metadata)
     * @return array ['success' => bool, 'embeddings' => array, 'usage' => array, 'cost' => array, 'error' => string|null]
     */
    public function makeEmbeddingRequest($input, array $options = []) {
        $startTime = microtime(true);

        try {
            // Load embedding processor
            $processorFile = __DIR__ . '/processors/OpenAIEmbeddingProcessor.php';
            if (!class_exists('OpenAIEmbeddingProcessor')) {
                require_once $processorFile;
            }

            $processor = new OpenAIEmbeddingProcessor();

            // Build request
            $options['model_code'] = $this->model;
            $options['provider_code'] = $this->provider;
            $requestBody = $processor->buildEmbeddingRequest($input, $options);

            // Get endpoint and headers
            $endpoint = $processor->getEmbeddingEndpoint($this->provider);
            $headers = $processor->getEmbeddingHeaders($this->apiKey, $this->provider);

            aiCentral_logMessage("Embedding API Endpoint: $endpoint, model: {$this->model}", 'DEBUG');

            // Make API call (reuse callAPI from parent). Network errors, rate limits and server
            // errors are retried with a growing wait (large indexing runs hit them).
            for ($attempt = 1; ; $attempt++) {
                $apiResponse = $this->callAPI($endpoint, $requestBody, $headers);
                $code = $apiResponse['http_code'];
                if ($code === 200 || $attempt >= 4 || ($code !== 0 && $code !== 429 && $code < 500)) {
                    break;
                }
                aiCentral_logMessage("Embedding request HTTP $code, retrying (attempt $attempt)", 'WARNING');
                sleep(2 ** $attempt);
            }

            // Parse response
            $result = $processor->parseEmbeddingResponse((array)$apiResponse['data'], $apiResponse['http_code']);

            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            if ($result['success']) {
                // Calculate cost (embeddings have input cost only, no output cost)
                $costBreakdown = aiCentral_calculateRequestCost(
                    $this->model,
                    $result['usage']['prompt_tokens'],
                    0, // no output tokens for embeddings
                    [],
                    0,
                    0,
                    0, 0, 0,
                    $this->modelDetails['model_id']
                );

                $cost = [
                    'input_cost' => $costBreakdown['input_cost_usd'],
                    'output_cost' => 0,
                    'total_cost' => $costBreakdown['input_cost_usd'],
                ];

                // Log usage
                $inputCount = is_array($input) ? count($input) : 1;
                $promptText = is_array($input)
                    ? "[Batch of $inputCount texts]"
                    : (strlen($input) > 500 ? substr($input, 0, 500) . '...' : $input);

                $this->logUsage(
                    $result['usage']['prompt_tokens'],
                    0,
                    ['input_cost' => $cost['input_cost'], 'output_cost' => 0, 'thinking_cost' => 0, 'tool_cost' => 0, 'total_cost' => $cost['total_cost']],
                    $responseTime,
                    'success',
                    null,
                    $options['run_id'] ?? null,
                    $promptText,
                    "[Embedding vector(s)]",
                    $options['metadata'] ?? null,
                    json_encode($requestBody, JSON_PRETTY_PRINT),
                    [],
                    0.0,
                    0,
                    null
                );

                return [
                    'success' => true,
                    'embeddings' => $result['embeddings'],
                    'model' => $this->model,
                    'usage' => $result['usage'],
                    'cost' => $cost,
                    'error' => null,
                ];
            } else {
                $this->logUsage(
                    0, 0,
                    ['input_cost' => 0, 'output_cost' => 0, 'thinking_cost' => 0, 'tool_cost' => 0, 'total_cost' => 0],
                    $responseTime,
                    'failed',
                    $result['error'],
                    $options['run_id'] ?? null,
                    is_array($input) ? "[Batch of " . count($input) . " texts]" : substr($input, 0, 500),
                    null,
                    $options['metadata'] ?? null,
                    json_encode($requestBody, JSON_PRETTY_PRINT),
                    [],
                    0.0,
                    0,
                    null
                );

                return [
                    'success' => false,
                    'embeddings' => [],
                    'usage' => null,
                    'cost' => 0,
                    'error' => $result['error'],
                ];
            }
        } catch (Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            aiCentral_logMessage("Exception in makeEmbeddingRequest: " . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'embeddings' => [],
                'usage' => null,
                'cost' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
