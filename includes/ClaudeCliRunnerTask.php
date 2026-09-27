<?php
/**
 * AI Central - CLI Runner (scheduled task "AI Central - CLI Runner", runs as the owner)
 *
 * Runs the Claude CLI jobs that web requests queue in aicore.claude_cli_jobs
 * (see ClaudeCliRunner.php). It runs as the owner's Windows account, so the CLI
 * uses the owner's Claude subscription login, never the API.
 *
 * Runs for about 55 minutes, then exits; the task starts it again (at startup
 * and every 5 minutes, never two at once).
 *
 *   php-win.exe ClaudeCliRunnerTask.php          run the loop
 *   php.exe ClaudeCliRunnerTask.php --check      free check only: can this account run the CLI?
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ClaudeCliRunner.php';

if (!aiCentral_claudeCliCanRunHere()) {
    aiCentral_logMessage('CLI Runner: this account has no Claude login in its profile; not running', 'ERROR');
    fwrite(STDERR, "This account has no Claude login in its profile.\n");
    exit(1);
}

if (in_array('--check', $argv, true)) {
    $problem = aiCentral_claudeCliSubscriptionProblem();
    echo $problem === null ? "OK: Claude CLI uses the claude.ai subscription login\n" : "NOT OK: $problem\n";
    exit($problem === null ? 0 : 1);
}

$conn = ai_getDBConnection();
if (!$conn) {
    aiCentral_logMessage('CLI Runner: no aicore connection', 'ERROR');
    exit(1);
}

// Jobs left 'running' by a runner that died: fail them so their callers stop waiting.
$conn->query("UPDATE claude_cli_jobs SET status = 'error', error = 'CLI Runner restarted while running', finished_at = NOW()
              WHERE status = 'running' AND started_at < NOW() - INTERVAL 15 MINUTE");

$stopAt = time() + 55 * 60;
$lastCleanup = 0;
while (time() < $stopAt) {
    if (time() - $lastCleanup > 3600) {
        // Prompts/answers are cleared by the caller; drop old rows entirely.
        $conn->query("DELETE FROM claude_cli_jobs WHERE created_at < NOW() - INTERVAL 7 DAY");
        $conn->query("UPDATE claude_cli_jobs SET status = 'error', error = 'nobody ran it', prompt = ''
                      WHERE status = 'pending' AND created_at < NOW() - INTERVAL 15 MINUTE");
        $lastCleanup = time();
    }

    // Claim the oldest pending job.
    $conn->begin_transaction();
    $res = $conn->query("SELECT job_id, caller, cli_args, prompt FROM claude_cli_jobs
                         WHERE status = 'pending' ORDER BY job_id LIMIT 1 FOR UPDATE SKIP LOCKED");
    $job = $res ? $res->fetch_assoc() : null;
    if (!$job) {
        $conn->commit();
        sleep(1);
        continue;
    }
    $jobId = (int)$job['job_id'];
    $conn->query("UPDATE claude_cli_jobs SET status = 'running', started_at = NOW() WHERE job_id = $jobId");
    $conn->commit();

    aiCentral_logMessage("CLI Runner: running job $jobId ({$job['caller']})", 'INFO');
    $r = aiCentral_claudeCliRun((string)$job['cli_args'], (string)$job['prompt'], (string)$job['caller']);

    $status = $r['error'] === null ? 'done' : 'error';
    $stmt = $conn->prepare("UPDATE claude_cli_jobs SET status = ?, stdout = ?, stderr = ?, exit_code = ?, error = ?, finished_at = NOW()
                            WHERE job_id = ?");
    $stderr = substr($r['stderr'], -60000);
    $exitCode = (int)$r['exit_code'];
    $error = $r['error'] === null ? null : substr($r['error'], 0, 500);
    $stmt->bind_param('sssisi', $status, $r['stdout'], $stderr, $exitCode, $error, $jobId);
    $stmt->execute();
    $stmt->close();
    aiCentral_logMessage("CLI Runner: job $jobId $status (exit {$r['exit_code']})", $status === 'done' ? 'INFO' : 'ERROR');
}
