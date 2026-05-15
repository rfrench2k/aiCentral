<?php
/**
 * AI Central System - Configuration
 * Reads secrets from environment variables.
 * Set the AICORE_* variables in your host environment.
 */

// Encryption key for user API keys (AES-256) - from environment
define('AI_ENCRYPTION_KEY', getenv('AICORE_ENCRYPTION_KEY') ?: '');

// System settings
define('AI_DEFAULT_TEMPERATURE', 1.0);
define('AI_DEFAULT_MAX_TOKENS', 4096);
define('AI_REQUEST_TIMEOUT', 180); // seconds - increased for long portfolio analyses
define('AI_DEBUG_MODE', false); // Set to true to enable verbose logging

// Quota enforcement settings
define('QUOTA_GRACE_PERCENT', 10); // Allow 10% overage
define('QUOTA_HARD_STOP_PERCENT', 120); // Block at 120%

// Cache settings (future enhancement)
define('CACHE_ENABLED', false);
define('CACHE_TTL', 3600); // 1 hour

// API Security settings - from environment
define('AI_API_SECRET', getenv('AICORE_API_SECRET') ?: '');

// Build allowed-callers whitelist. Loopback is always allowed; extra IPs
// come from common/aPRIV_LOCAL.php (gitignored, per-deployment).
$allowedCallers = ['127.0.0.1', 'localhost', '::1'];
$privLocalFile = __DIR__ . '/../common/aPRIV_LOCAL.php';
if (file_exists($privLocalFile)) {
    require_once $privLocalFile;
    if (defined('AICORE_API_EXTRA_CALLERS') && is_array(AICORE_API_EXTRA_CALLERS)) {
        $allowedCallers = array_merge($allowedCallers, AICORE_API_EXTRA_CALLERS);
    }
}
define('ALLOWED_API_CALLERS', json_encode($allowedCallers));

?>
