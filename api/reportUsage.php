<?php
/**
 * AI Central API - Report usage from an outside client
 *
 * A client that calls a provider itself (e.g. a desktop app with its own provider key) reports each call here, so it
 * shows in the usage log and the dashboard's free-tier bars. Counts only: never the words or the audio.
 *
 *   POST /ai/api/reportUsage.php
 *   Authorization: Bearer <client key>        (ai_client_keys; made with admin/clientKeyCreate.php)
 *   {"feature": "dictation_stt", "provider": "groq", "model": "whisper-large-v3",
 *    "seconds": 4.2, "input_tokens": 0, "output_tokens": 0, "response_ms": 640, "status": "success", "error": null}
 *
 * The key gives the program and the user; the feature must be one of that program's features and the model one of
 * AI Central's models. The cost is worked out from the model's price (free-tier models cost 0).
 * Answers {"success": true} or {"success": false, "error": "..."}.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/ai_functions.php';

header('Content-Type: application/json');

function reportFail(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') reportFail('POST only', 405);

$owner = ai_clientKeyOwner($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!$owner) reportFail('Invalid key', 401);

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) reportFail('JSON body required');

$featureCode = (string) ($in['feature'] ?? '');
if ($featureCode === '' || !ai_getFeatureConfig($owner['program_id'], $featureCode)) reportFail('Unknown feature');

$model = ai_getModelByCodeAndProvider((string) ($in['model'] ?? ''), (string) ($in['provider'] ?? ''));
if (!$model) reportFail('Unknown model');

$seconds = max(0.0, (float) ($in['seconds'] ?? 0));
$inTokens = max(0, (int) ($in['input_tokens'] ?? 0));
$outTokens = max(0, (int) ($in['output_tokens'] ?? 0));
$ms = max(0, (int) ($in['response_ms'] ?? 0));
$status = ($in['status'] ?? '') === 'success' ? 'success' : 'failed';
$error = $status === 'failed' ? mb_substr((string) ($in['error'] ?? ''), 0, 500) : null;

// audio models: price is USD per 1,000 minutes of audio; text models: per million tokens
$audio = $seconds > 0 && $inTokens === 0 && $outTokens === 0;
$inCost = $audio ? ($seconds / 60) * (float) $model['input_cost_per_million'] / 1000 : $inTokens * (float) $model['input_cost_per_million'] / 1000000;
$outCost = $audio ? 0 : $outTokens * (float) $model['output_cost_per_million'] / 1000000;

$details = ['type' => $audio ? 'transcription' : 'chat', 'reported' => true];
if ($seconds > 0) $details['seconds'] = round($seconds, 3);

$id = ai_logUsageRow($owner['user_id'], $owner['program_id'], $featureCode, $model, $inTokens, $outTokens, $details,
                     round($inCost, 6), round($outCost, 6), $ms, $status, $error);
if (!$id) reportFail('Not logged', 500);
echo json_encode(['success' => true]);
