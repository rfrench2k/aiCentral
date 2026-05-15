<?php
/**
 * AI Central — Local-only site configuration template.
 *
 * Copy this file to aPRIV_LOCAL.php and fill in values for your environment.
 * aPRIV_LOCAL.php is gitignored — your actual values stay local.
 *
 * This file holds local definitions that vary by deployment but do not
 * warrant an environment variable (e.g. IP whitelists).
 */

// IPs (in addition to 127.0.0.1, localhost, ::1) allowed to call the
// AI Central API endpoints. Add any servers that integrate with /ai/api/*.
define('AICORE_API_EXTRA_CALLERS', []);
