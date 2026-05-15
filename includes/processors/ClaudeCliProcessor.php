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
 * Environment:
 *   AICORE_CLAUDE_CLI_PATH   Path to the `claude` executable. Default 'claude'.
 *   AICORE_CLAUDE_CLI_HOME   Directory the CLI reads credentials from. If unset,
 *                            the calling process's HOME/USERPROFILE is used.
 *                            On IIS/IUSR hosts where the service account has no
 *                            home, set this to a writable directory with the
 *                            Claude credentials so the CLI can authenticate.
 *   AICORE_CLAUDE_CLI_TIMEOUT  Max seconds to wait for the CLI lock. Default 180.
 */

require_once __DIR__ . '/BaseProcessor.php';
require_once __DIR__ . '/../../common/common_ai.php';

class ClaudeCliProcessor implements BaseProcessor {

    /**
     * Build a CLI-shaped request from prompt+options.
     *
     * Returned array is opaque to AIProviderManager and gets handed back to
     * executeLocal() verbatim.
     */
    public function buildRequest($prompt, $options) {
        return [
            'prompt'        => $prompt,
            'model'         => $options['model_code']    ?? '',
            'system'        => $options['system']        ?? null,
            'max_tokens'    => $options['max_tokens']    ?? null,
            'json_schema'   => $options['json_schema']   ?? null,
            'allowed_tools' => $options['allowed_tools'] ?? ['Read'],
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
        $cliPath  = getenv('AICORE_CLAUDE_CLI_PATH') ?: 'claude';
        $cliHome  = getenv('AICORE_CLAUDE_CLI_HOME') ?: null;
        $timeout  = (int)(getenv('AICORE_CLAUDE_CLI_TIMEOUT') ?: 180);
        if ($timeout < 10) $timeout = 180;

        // On Windows, prefer the raw claude.exe over the claude.cmd wrapper.
        // Service-account IIS pools (ApplicationPoolIdentity) don't always
        // wire up a console correctly for cmd.exe batch scripts, which causes
        // claude.cmd subprocesses to hang silently. Going straight to the .exe
        // avoids cmd.exe entirely. Honors AICORE_CLAUDE_CLI_PATH if set to
        // a direct .exe path; only auto-resolves when path is the bare
        // string 'claude' or ends in 'claude.cmd'/'claude.ps1'.
        if (DIRECTORY_SEPARATOR === '\\') {
            $needsResolve = ($cliPath === 'claude')
                || preg_match('/\\\\claude(\\.cmd|\\.ps1)?$/i', $cliPath);
            if ($needsResolve) {
                $candidate = 'C:\\npm-global\\node_modules\\@anthropic-ai\\claude-code\\bin\\claude.exe';
                if (is_file($candidate)) {
                    $cliPath = $candidate;
                }
            }
        }

        // Temp directory for the prompt file + concurrency lock. On Windows,
        // sys_get_temp_dir() can return a per-profile path (e.g.
        // C:\Users\<pool>\AppData\Local\Temp) when an IIS pool has
        // LoadUserProfile=True. Different callers (IIS IUSR vs scheduled task
        // SYSTEM) then get different temp dirs and the cross-process lock is
        // useless. Pin to a system-wide location that all callers can reach.
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = 'C:\\Windows\\Temp\\aicore_claude_cli';
        } else {
            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . 'aicore_claude_cli';
        }
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $promptFile = $tempDir . DIRECTORY_SEPARATOR . 'prompt_' . time() . '_' . bin2hex(random_bytes(4)) . '.txt';
        $promptText = (string)($body['prompt'] ?? '');
        if (!empty($body['system'])) {
            // Prepend a system block so it is part of the same conversation turn.
            $promptText = "SYSTEM:\n" . $body['system'] . "\n\nUSER:\n" . $promptText;
        }
        file_put_contents($promptFile, $promptText);

        // Build the command. escapeshellarg handles spaces in model codes and
        // schema JSON; for the JSON schema we additionally need to survive cmd.exe
        // quoting on Windows, so we wrap and escape inner double quotes ourselves.
        $cmd  = '"' . $cliPath . '"';
        $cmd .= ' --print';
        $cmd .= ' --output-format json';
        $cmd .= ' --dangerously-skip-permissions';
        $cmd .= ' --no-session-persistence';
        if (!empty($body['allowed_tools']) && is_array($body['allowed_tools'])) {
            $cmd .= ' --allowed-tools ' . escapeshellarg(implode(',', $body['allowed_tools']));
        }
        $cmd .= ' --model ' . escapeshellarg($modelCode);
        // Note: --max-tokens was removed from the claude CLI in 2.1.142
        // (auto-update on 2026-05-14). The CLI now uses the model's default
        // output cap, which is plenty for this hub's use cases. If a hard
        // dollar cap is needed, use --max-budget-usd instead.
        // if (!empty($body['max_tokens'])) {
        //     $cmd .= ' --max-tokens ' . (int)$body['max_tokens'];
        // }
        if (!empty($body['json_schema']) && is_array($body['json_schema'])) {
            $schemaJson = json_encode($body['json_schema'], JSON_UNESCAPED_SLASHES);
            $cmd .= ' --json-schema "' . str_replace('"', '\\"', $schemaJson) . '"';
        }
        // The prompt is written to the subprocess's stdin via the pipe below.
        // Previously this line appended ' < promptFile' to the command, but the
        // combination of proc_open's stdin pipe AND a shell '<' redirect is
        // racy on Windows — cmd.exe can win or lose the redirect, and when it
        // loses, claude reads EOF from the closed pipe and silently exits 0.

        // Concurrent claude CLI invocations silently produce empty output.
        // Serialize with a file lock so the worker and the UI can both queue.
        $lockFile = $tempDir . DIRECTORY_SEPARATOR . 'claude_cli.lock';
        $lockHandle = fopen($lockFile, 'c');
        if ($lockHandle === false) {
            @unlink($promptFile);
            return ['data' => null, 'http_code' => 500, 'error' => 'Could not open CLI lock file'];
        }
        $deadline = time() + $timeout;
        $locked = false;
        while (time() < $deadline) {
            if (flock($lockHandle, LOCK_EX | LOCK_NB)) { $locked = true; break; }
            usleep(500000);
        }
        if (!$locked) {
            fclose($lockHandle);
            @unlink($promptFile);
            return ['data' => null, 'http_code' => 504, 'error' => "Timed out (${timeout}s) waiting for Claude CLI lock"];
        }

        // Point the CLI at a credentials directory if AICORE_CLAUDE_CLI_HOME is
        // set. Necessary on IIS where the service account (IUSR) has no home.
        $envBackup = [];
        if ($cliHome !== null && $cliHome !== '') {
            foreach (['HOME', 'USERPROFILE', 'APPDATA'] as $k) {
                $envBackup[$k] = getenv($k);
                putenv("$k=$cliHome");
            }
        }

        // Provide ANTHROPIC_API_KEY as a fallback auth method. Claude CLI prefers
        // OAuth (subscription, zero per-token cost) when the credentials file is
        // readable, and falls back to ANTHROPIC_API_KEY (API billing) only when
        // OAuth isn't available — which is the case when PHP runs under IIS as
        // a service account that can't read the Administrator's keychain. The
        // SYSTEM-owned scheduled task continues to use OAuth.
        $apiKey = getenv('AICORE_ANTHROPIC_API_KEY') ?: '';
        if ($apiKey !== '' && !defined('ANTHROPIC_API_KEY') /* false-positive guard */) {
            $envBackup['ANTHROPIC_API_KEY'] = getenv('ANTHROPIC_API_KEY');
            putenv("ANTHROPIC_API_KEY=$apiKey");
        }

        $stdout = '';
        $stderr = '';
        $exitCode = -1;
        try {
            // Use proc_open's 'file' descriptor type for stdin — opens the
            // prompt file as the subprocess's stdin handle directly. This
            // bypasses cmd.exe redirect AND avoids the pipe-write timing
            // that hung under IIS service-account contexts. The prompt was
            // already written to $promptFile above.
            $descriptors = [
                0 => ['file', $promptFile, 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = proc_open($cmd, $descriptors, $pipes);
            if (!is_resource($proc)) {
                throw new Exception('proc_open failed');
            }
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);
        } catch (Throwable $e) {
            return ['data' => null, 'http_code' => 500, 'error' => 'Claude CLI launch failed: ' . $e->getMessage()];
        } finally {
            foreach ($envBackup as $k => $old) {
                if ($old === false) putenv($k); else putenv("$k=$old");
            }
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($promptFile);
            $this->cleanupOldTempFiles($tempDir);
        }

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

    private function cleanupOldTempFiles(string $dir): void {
        $cutoff = time() - 3600;
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'prompt_*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < $cutoff) @unlink($f);
        }
    }
}
