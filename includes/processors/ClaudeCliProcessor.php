<?php
/**
 * AI Central System - Claude Code CLI Processor
 *
 * Executes the Claude Code CLI (`claude`) as a local subprocess and returns
 * structured JSON output. Implements BaseProcessor like every other provider
 * processor, plus an `executeLocal()` method that AIProviderManager dispatches
 * to when the model's endpoint scheme is `local://` instead of HTTP.
 *
 * This processor is generic — it knows nothing about any specific feature or
 * caller. Apps that want to use it route through ai_makeRequest() with a
 * feature configured to a claude_cli model.
 *
 * The CLI itself is started by aiCentral_claudeCliRun() (../ClaudeCliRunner.php):
 * subscription login only, never the API; web requests go through the
 * "AI Central - CLI Runner" task, which runs as the owner.
 *
 * Images / PDFs (options['images'] = [['media_type', 'data' (base64)], ...]): the CLI takes no attachments, so each
 * is written to a temporary file and the prompt asks Claude to open it with the Read tool (always allowed then);
 * the files are deleted when the run ends.
 *
 * Environment:
 *   AICORE_CLAUDE_CLI_PATH   Path to the `claude` executable. Default 'claude'.
 *   AICORE_CLAUDE_CLI_TIMEOUT  Max seconds to wait for the CLI lock. Default 180.
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';
require_once __DIR__ . '/../ClaudeCliRunner.php';

class ClaudeCliProcessor implements BaseProcessor {

    /**
     * Build a CLI-shaped request from prompt+options.
     *
     * Returned array is opaque to AIProviderManager and gets handed back to
     * executeLocal() verbatim.
     */
    public function buildRequest($prompt, $options) {
        // The CLI takes one turn, so earlier conversation turns are written out ahead of the new message.
        [$turns, $prompt] = aiCentral_historyTurns($options, $prompt);
        if ($turns) {
            $transcript = "Conversation so far:\n\n";
            foreach ($turns as $turn) {
                $transcript .= ($turn['role'] === 'assistant' ? 'Assistant' : 'User') . ': ' . $turn['content'] . "\n\n";
            }
            $prompt = $transcript . "New message from the user:\n" . $prompt;
        }
        return [
            'prompt'        => $prompt,
            'model'         => $options['model_code']    ?? '',
            'system'        => $options['system']        ?? null,
            'max_tokens'    => $options['max_tokens']    ?? null,
            'json_schema'   => $options['json_schema']   ?? null,
            'effort'        => $options['effort']        ?? null,
            'allowed_tools' => $options['allowed_tools'] ?? ['Read'],
            'images'        => $options['images']        ?? [],
        ];
    }

    /**
     * Parse the JSON the CLI prints on stdout into our standard response shape.
     *
     * The CLI's --output-format=json emits roughly:
     *   { type, subtype, result, structured_output?, usage:{input_tokens,
     *     output_tokens, cache_creation_input_tokens, cache_read_input_tokens},
     *     total_cost_usd, modelUsage }
     */
    public function parseResponse($apiResponse, $httpCode, $body = null) {
        $rawRequest = $body ? json_encode($body, JSON_PRETTY_PRINT) : null;

        if ($httpCode !== 200 || !is_array($apiResponse)) {
            $err = is_array($apiResponse) && isset($apiResponse['error'])
                ? (is_array($apiResponse['error']) ? ($apiResponse['error']['message'] ?? 'CLI error') : $apiResponse['error'])
                : 'Claude CLI returned non-200 or non-array response';
            return [
                'success'           => false,
                'error'             => $err,
                'error_type'        => 'cli_error',
                'raw_request'       => $rawRequest,
                'raw_response_data' => $apiResponse,
            ];
        }

        // Prefer the schema-enforced payload when a json_schema was supplied;
        // fall back to the plain `result` text otherwise. Callers that want the
        // raw structured payload can also read it from raw_response_data.
        $responseText = '';
        if (isset($apiResponse['structured_output'])) {
            $responseText = json_encode($apiResponse['structured_output'], JSON_UNESCAPED_SLASHES);
        } elseif (isset($apiResponse['result']) && is_string($apiResponse['result'])) {
            $responseText = $apiResponse['result'];
        }

        $usage = $apiResponse['usage'] ?? [];
        // The CLI reports its own cost (the dollar amount drawn from the
        // Claude Code subscription / credit pool). Surface it so AIProviderManager
        // can log the real cost instead of the token-x-price calculation, which
        // for claude_cli ai_models rows is $0.
        $cliCost = isset($apiResponse['total_cost_usd']) ? (float)$apiResponse['total_cost_usd'] : 0.0;
        return [
            'success'             => true,
            'response'            => $responseText,
            'usage'               => [
                'input_tokens'    => (int)($usage['input_tokens']  ?? 0),
                'output_tokens'   => (int)($usage['output_tokens'] ?? 0),
                'thinking_tokens' => 0,
            ],
            'tool_calls'          => [],
            'cli_total_cost_usd'  => $cliCost,
            'raw_response_data'   => $apiResponse,
            'raw_request'         => $rawRequest,
        ];
    }

    /**
     * CLI handles its own tool wiring via --allowed-tools, so there is nothing
     * to translate from the standard capabilities map.
     */
    public function buildTools($capabilities, $providerCode = null) {
        return null;
    }

    /**
     * AIProviderManager checks for the 'local://' scheme to skip HTTP and call
     * executeLocal() instead. The value after 'local://' is opaque.
     */
    public function getEndpoint($modelCode, $providerCode = null) {
        return 'local://claude';
    }

    public function getHeaders($apiKey, $providerCode = null) {
        return [];
    }

    /**
     * Run the claude CLI as a subprocess and return the parsed JSON in the
     * same envelope shape as AIProviderManager::callAPI would for an HTTP call.
     *
     * @param array  $body       Output of buildRequest()
     * @param string $modelCode  Model code to pass to --model
     * @return array ['data' => array|null, 'http_code' => int, 'error' => string|null]
     */
    public function executeLocal(array $body, string $modelCode): array {
        $timeout  = (int)(getenv('AICORE_CLAUDE_CLI_TIMEOUT') ?: 180);
        if ($timeout < 10) $timeout = 180;

        $promptText = (string)($body['prompt'] ?? '');
        [$files, $fileNote] = $this->writeAttachments((array)($body['images'] ?? []));
        if ($fileNote !== '') {
            $promptText = $fileNote . "\n\n" . $promptText;
            if (!in_array('Read', (array)$body['allowed_tools'], true)) $body['allowed_tools'][] = 'Read';
        }
        if (!empty($body['system'])) {
            // Prepend a system block so it is part of the same conversation turn.
            $promptText = "SYSTEM:\n" . $body['system'] . "\n\nUSER:\n" . $promptText;
        }

        // The arguments, one list item each: aiCentral_claudeCliRun() passes them
        // without a shell, so nothing here needs quoting or escaping.
        $args = ['--print', '--output-format', 'json', '--dangerously-skip-permissions', '--no-session-persistence'];
        if (!empty($body['allowed_tools']) && is_array($body['allowed_tools'])) {
            array_push($args, '--allowed-tools', implode(',', $body['allowed_tools']));
        }
        array_push($args, '--model', $modelCode);
        if (!empty($body['effort'])) {
            array_push($args, '--effort', (string)$body['effort']);
        }
        // Note: --max-tokens was removed from the claude CLI in 2.1.142
        // (auto-update on 2026-05-14). The CLI now uses the model's default
        // output cap, which is plenty for this hub's use cases. If a hard
        // dollar cap is needed, use --max-budget-usd instead.
        if (!empty($body['json_schema']) && is_array($body['json_schema'])) {
            array_push($args, '--json-schema', json_encode($body['json_schema'], JSON_UNESCAPED_SLASHES));
        }

        // Subscription login only, never the API; runs as the owner (directly,
        // or through the CLI Runner task when called from a web request).
        $r = aiCentral_claudeCliRun($args, $promptText, 'ClaudeCliProcessor ' . $modelCode, max($timeout, 540));
        foreach ($files as $f) @unlink($f);
        if ($r['error'] !== null) {
            return ['data' => null, 'http_code' => 500, 'error' => $r['error']];
        }
        $stdout   = $r['stdout'];
        $stderr   = $r['stderr'];
        $exitCode = $r['exit_code'];

        if ($stdout === false || trim((string)$stdout) === '') {
            $tail = substr(trim((string)$stderr), -400);
            return ['data' => null, 'http_code' => 500, 'error' => "Claude CLI empty output (exit=$exitCode, stderr=" . ($tail ?: '<empty>') . ')'];
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            return ['data' => ['error' => ['message' => 'CLI returned non-JSON output']], 'http_code' => 500, 'error' => 'non-JSON CLI output'];
        }

        return ['data' => $decoded, 'http_code' => 200, 'error' => null];
    }

    /**
     * Writes the images / PDFs to temporary files for the CLI to Read. Returns [paths, the line for the prompt].
     * Leftovers older than an hour (a run that died) are removed here too.
     */
    private function writeAttachments(array $images): array {
        if (!$images) return [[], ''];
        $dir = (DIRECTORY_SEPARATOR === '\\' ? 'C:\\Windows\\Temp' : rtrim(sys_get_temp_dir(), '/')) . DIRECTORY_SEPARATOR . 'aicore_claude_cli_files';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'att_*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 3600) @unlink($old);
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        $files = [];
        foreach ($images as $img) {
            $data = base64_decode((string)($img['data'] ?? ''), true);
            if ($data === false || $data === '') continue;
            $path = $dir . DIRECTORY_SEPARATOR . 'att_' . bin2hex(random_bytes(8)) . '.' . ($ext[$img['media_type'] ?? ''] ?? 'jpg');
            if (file_put_contents($path, $data) !== false) $files[] = $path;
        }
        if (!$files) return [[], ''];
        $note = (count($files) === 1 ? 'One file is attached' : count($files) . ' files are attached, in this order')
              . '. Open each with the Read tool and look at it before answering:' . "\n- " . implode("\n- ", $files);
        return [$files, $note];
    }
}
