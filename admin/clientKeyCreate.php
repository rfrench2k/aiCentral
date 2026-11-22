<?php
/**
 * Makes a key for an outside client to report its usage (api/reportUsage.php). Command line only.
 *   php admin/clientKeyCreate.php <program_id> <user_id> "<name>"
 * Prints the key once; only its sha256 is kept (ai_client_keys). Turn a key off: UPDATE ai_client_keys SET is_active = 0.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/includes/ai_functions.php';

[, $programId, $userId, $name] = $argv + [null, '', '', ''];
if ($programId === '' || $userId === '' || $name === '') { fwrite(STDERR, "php clientKeyCreate.php <program_id> <user_id> \"<name>\"\n"); exit(1); }
if (!ai_getProgramConfig($programId)) { fwrite(STDERR, "No program $programId\n"); exit(1); }

$key = 'aic_' . bin2hex(random_bytes(24));
$hash = hash('sha256', $key);
$conn = ai_getDBConnection();
$stmt = $conn->prepare("INSERT INTO ai_client_keys (program_id, user_id, key_name, key_hash) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $programId, $userId, $name, $hash);
$stmt->execute();
echo $key, "\n";
