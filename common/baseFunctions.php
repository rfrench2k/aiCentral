<?php
/**
 * AI Central System - Base Functions
 * Logging and error handling
 */

/**
 * Log message to ai.log file
 *
 * @param string $message Message to log
 * @param string $level Log level (INFO, ERROR, WARNING, DEBUG)
 * @return void
 */
function aiCentral_logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../ai.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>
