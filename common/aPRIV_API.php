<?php
/**
 * AI Central System - API Keys Configuration
 * Reads all API keys from environment variables.
 * Set the AICORE_* variables in your host environment.
 */

// AI Provider API Keys - from environment variables
define('ANTHROPIC_API_KEY', getenv('AICORE_ANTHROPIC_API_KEY') ?: '');
define('OPENAI_API_KEY', getenv('AICORE_OPENAI_API_KEY') ?: '');
define('KIMIK2_API_KEY', getenv('AICORE_KIMI_API_KEY') ?: '');
define('GROK_API_KEY', getenv('AICORE_GROK_API_KEY') ?: '');
define('GEMINI_API_KEY', getenv('AICORE_GEMINI_API_KEY') ?: '');

?>
