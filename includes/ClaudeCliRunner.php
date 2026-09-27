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
 *      (free) must report authMethod "claude.ai". If it doesn't, nothing runs.
 *   2. The login lives in the owner's Windows profile, so the CLI must run as
 *      the owner. A caller that can read that login (scheduled tasks running as
 *      the owner, e.g. "Movies - DNA Regen") runs the CLI directly. A caller
 *      that can't (IIS web requests) queues the job in aicore.claude_cli_jobs
 *      and waits; the "AI Central - CLI Runner" scheduled task, running as the
 *      owner, executes it (scripts/claudeCliRunner.php).
 *
 * Environment (optional):
 *   AICORE_CLAUDE_CLI_PATH   Path to the `claude` executable. Default: the npm
 *                            global claude.exe, else 'claude'.
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
function aiCentral_claudeCliCanRunHere(): bool {
    $profile = getenv('USERPROFILE') ?: getenv('HOME');
    return $profile !== false && $profile !== ''
        && is_readable($profile . DIRECTORY_SEPARATOR . '.claude' . DIRECTORY_SEPARATOR . '.credentials.json');
}

/** This process's environment with every API-related variable removed. */
function aiCentral_claudeCliEnv(): array {
    $env = getenv();
    foreach (array_keys($env) as $k) {
        if (in_array(strtoupper($k), AICORE_CLI_BLOCKED_ENV, true)) {
            unset($env[$k]);
        }
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
    } elseif (($status['authMethod'] ?? '') !== 'claude.ai') {
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
