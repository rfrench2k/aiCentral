<?php
/**
 * AI Central System - Claude Code CLI runner (the ONE place that starts `claude`)
 *
 * Every claude_cli call in AI Central (ClaudeCliProcessor, AIDeepInsights) goes
 * through aiCentral_claudeCliRun(). Rules it enforces:
 *
 *   1. The CLI runs ONLY on the owner's Claude subscription login (Max plan),
 *      NEVER the Anthropic API. Every API-key / API-routing variable is removed
 *      from the subprocess environment, and before running, `claude auth status`
 *      (free) must report authMethod "claude.ai" (or "oauth_token", the setup-token
 *      subscription token). If it doesn't, nothing runs.
 *   2. Every caller runs the CLI directly, no waiting. Scheduled tasks running
 *      as the owner use the login in his Windows profile. Callers without it
 *      (IIS web requests) use the subscription token from `claude setup-token`
 *      (AICORE_CLAUDE_OAUTH_TOKEN), with their own CLI config folder
 *      (AICORE_CLI_WEB_CONFIG_DIR). Only when neither is there is the job queued
 *      in aicore.claude_cli_jobs for the "AI Central - CLI Runner" task.
 *
 * Environment:
 *   AICORE_CLAUDE_OAUTH_TOKEN  The owner's subscription token (claude setup-token)
 *                              for callers without his profile, e.g. IIS.
 *   AICORE_CLAUDE_CLI_PATH     (optional) Path to the `claude` executable. Default:
 *                              the npm global claude.exe, else 'claude'.
 */

require_once __DIR__ . '/../common/common_ai.php';
require_once __DIR__ . '/../common/aPRIV_DB_AI.php';

// Variables that make the CLI (or anything it starts) use the API instead of
// the subscription login. Removed from every CLI subprocess.
const AICORE_CLI_BLOCKED_ENV = [
    'ANTHROPIC_API_KEY', 'ANTHROPIC_AUTH_TOKEN', 'ANTHROPIC_BASE_URL',
    'CLAUDE_CODE_USE_BEDROCK', 'CLAUDE_CODE_USE_VERTEX', 'CLAUDE_CODE_USE_FOUNDRY',
    'AICORE_ANTHROPIC_API_KEY', 'AICORE_OPENAI_API_KEY', 'AICORE_GEMINI_API_KEY',
    'AICORE_GROK_API_KEY', 'AICORE_KIMI_API_KEY',
];

// The CLI's own config folder for callers without the owner's profile (IIS): it must be writable by them.
const AICORE_CLI_WEB_CONFIG_DIR = 'C:\\ProgramData\\AICentral\\claude-web';

/** Path to claude.exe (skips the .cmd/.ps1 wrappers, which hang under IIS). */
function aiCentral_claudeCliPath(): string {
    $cliPath = getenv('AICORE_CLAUDE_CLI_PATH') ?: 'claude';
    if (DIRECTORY_SEPARATOR === '\\'
        && ($cliPath === 'claude' || preg_match('/\\\\claude(\\.cmd|\\.ps1)?$/i', $cliPath))) {
        $candidate = 'C:\\npm-global\\node_modules\\@anthropic-ai\\claude-code\\bin\\claude.exe';
        if (is_file($candidate)) {
            $cliPath = $candidate;
        }
    }
    return $cliPath;
}

/** True when this process runs as a user whose own profile holds the Claude login. */
function aiCentral_claudeCliOwnLogin(): bool {
    $profile = getenv('USERPROFILE') ?: getenv('HOME');
    return $profile !== false && $profile !== ''
        && is_readable($profile . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . '.credentials.json');
}

/** The owner's subscription token (claude setup-token), or null when it is not set. */
function aiCentral_claudeCliToken(): ?string {
    $token = trim((string)getenv('AICORE_CLAUDE_OAUTH_TOKEN'));
    return $token !== '' ? $token : null;
}

/** True when this process can run the CLI itself: its own login, or the subscription token. */
function aiCentral_claudeCliCanRunHere(): bool {
    return aiCentral_claudeCliOwnLogin() || aiCentral_claudeCliToken() !== null;
}

/**
 * This process's environment with every API-related variable removed. A caller
 * without its own login gets the subscription token and its own config folder.
 */
function aiCentral_claudeCliEnv(): array {
    $env = getenv();
    foreach (array_keys($env) as $k) {
        if (in_array(strtoupper($k), AICORE_CLI_BLOCKED_ENV, true) || strtoupper($k) === 'AICORE_CLAUDE_OAUTH_TOKEN') {
            unset($env[$k]);
        }
    }
    if (!aiCentral_claudeCliOwnLogin() && aiCentral_claudeCliToken() !== null) {
        if (!is_dir(AICORE_CLI_WEB_CONFIG_DIR)) {
            @mkdir(AICORE_CLI_WEB_CONFIG_DIR, 0755, true);
        }
        $env['CLAUDE_CODE_OAUTH_TOKEN'] = aiCentral_claudeCliToken();
        $env['CLAUDE_CONFIG_DIR'] = AICORE_CLI_WEB_CONFIG_DIR;
    }
    return $env;
}

/**
 * Free check that the CLI will use the subscription login. Cached for 10 minutes
 * per process. Returns null when OK, or the reason it is not safe to run.
 */
function aiCentral_claudeCliSubscriptionProblem(): ?string {
    static $checkedAt = 0, $problem = null;
    if ($checkedAt > time() - 600) {
        return $problem;
    }
    $out = [];
    $proc = proc_open('"' . aiCentral_claudeCliPath() . '" auth status',
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, aiCentral_claudeCliEnv());
    if (!is_resource($proc)) {
        return 'could not run claude auth status';
    }
    fclose($pipes[0]);
    $json = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $status = json_decode((string)$json, true);
    if (!is_array($status)) {
        $problem = 'claude auth status returned no JSON';
    } elseif (empty($status['loggedIn'])) {
        $problem = 'Claude CLI is not logged in';
    } elseif (!in_array($status['authMethod'] ?? '', ['claude.ai', 'oauth_token'], true)     // oauth_token = claude setup-token (subscription)
              || ($status['apiProvider'] ?? 'firstParty') !== 'firstParty') {
        $problem = 'Claude CLI login is "' . ($status['authMethod'] ?? '?') . '", not the claude.ai subscription';
    } else {
        $problem = null;
    }
    $checkedAt = time();
    if ($problem !== null) {
        aiCentral_logMessage("Claude CLI refused: $problem", 'ERROR');
    }
    return $problem;
}

/**
 * Run the Claude CLI with the given arguments (everything after the executable)
 * and the prompt on stdin.
 *
 * @return array ['stdout' => string, 'stderr' => string, 'exit_code' => int, 'error' => string|null]
 */
function aiCentral_claudeCliRun(string $cliArgs, string $promptText, string $caller = '', int $timeout = 540): array {
    if (!aiCentral_claudeCliCanRunHere()) {
        return aiCentral_claudeCliQueueAndWait($cliArgs, $promptText, $caller, $timeout);
    }

    $problem = aiCentral_claudeCliSubscriptionProblem();
    if ($problem !== null) {
        return ['stdout' => '', 'stderr' => '', 'exit_code' => -1, 'error' => "Claude CLI not run (subscription only): $problem"];
    }

    // Prompt file + a cross-process lock in a system-wide temp dir: concurrent
    // claude CLI runs silently produce empty output, so they are serialized.
    $tempDir = DIRECTORY_SEPARATOR === '\\' ? 'C:\\Windows\\Temp\\aicore_claude_cli'
        : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . 'aicore_claude_cli';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    $promptFile = $tempDir . DIRECTORY_SEPARATOR . 'prompt_' . time() . '_' . bin2hex(random_bytes(4)) . '.txt';
    file_put_contents($promptFile, $promptText);

    $lockHandle = fopen($tempDir . DIRECTORY_SEPARATOR . 'claude_cli.lock', 'c');
    if ($lockHandle === false) {
        @unlink($promptFile);
        return ['stdout' => '', 'stderr' => '', 'exit_code' => -1, 'error' => 'Could not open CLI lock file'];
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
        return ['stdout' => '', 'stderr' => '', 'exit_code' => -1, 'error' => "Timed out ({$timeout}s) waiting for Claude CLI lock"];
    }

    $stdout = '';
    $stderr = '';
    $exitCode = -1;
    try {
        // stdin is the prompt file opened directly (no cmd.exe '<' redirect,
        // which is racy on Windows). The environment has no API variables.
        $proc = proc_open('"' . aiCentral_claudeCliPath() . '" ' . $cliArgs,
            [0 => ['file', $promptFile, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes, null, aiCentral_claudeCliEnv());
        if (!is_resource($proc)) {
            throw new Exception('proc_open failed');
        }
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
    } catch (Throwable $e) {
        return ['stdout' => '', 'stderr' => '', 'exit_code' => -1, 'error' => 'Claude CLI launch failed: ' . $e->getMessage()];
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($promptFile);
        $cutoff = time() - 3600;
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'prompt_*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < $cutoff) @unlink($f);
        }
    }

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $exitCode, 'error' => null];
}

/** Queue the job for the CLI Runner task (runs as the owner) and wait for it. */
function aiCentral_claudeCliQueueAndWait(string $cliArgs, string $promptText, string $caller, int $timeout): array {
    $conn = ai_getDBConnection();
    if (!$conn) {
        return ['stdout' => '', 'stderr' => '', 'exit_code' => -1, 'error' => 'Claude CLI queue: no aicore connection'];
    }
    $stmt = $conn->prepare("INSERT INTO claude_cli_jobs (caller, cli_args, prompt) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $caller, $cliArgs, $promptText);
    $stmt->execute();
    $jobId = (int)$conn->insert_id;
    $stmt->close();
    aiCentral_logMessage("Claude CLI job $jobId queued for the CLI Runner ($caller)", 'DEBUG');

    $deadline = time() + $timeout;
    while (time() < $deadline) {
        usleep(500000);
        $res = $conn->query("SELECT status, stdout, stderr, exit_code, error FROM claude_cli_jobs WHERE job_id = $jobId");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row && ($row['status'] === 'done' || $row['status'] === 'error')) {
            // The result was handed back: don't keep the prompt/answer around.
            $conn->query("UPDATE claude_cli_jobs SET prompt = '', stdout = NULL, stderr = NULL WHERE job_id = $jobId");
            return [
                'stdout'    => (string)$row['stdout'],
                'stderr'    => (string)$row['stderr'],
                'exit_code' => (int)$row['exit_code'],
                'error'     => $row['status'] === 'error' ? ($row['error'] ?: 'Claude CLI Runner error') : null,
            ];
        }
    }
    $conn->query("UPDATE claude_cli_jobs SET status = 'error', error = 'caller gave up waiting', prompt = '' WHERE job_id = $jobId AND status = 'pending'");
    return ['stdout' => '', 'stderr' => '', 'exit_code' => -1,
            'error' => "Claude CLI Runner did not finish job $jobId within {$timeout}s (is the \"AI Central - CLI Runner\" task running?)"];
}
