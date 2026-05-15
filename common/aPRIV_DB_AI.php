<?php
/**
 * AI Central System - Database Connection
 * Connects to aicore database
 *
 * CRITICAL: This file MUST NOT create global variables that conflict with app databases!
 * ALWAYS use ai_getDBConnection() function - NEVER use global $conn
 */

/**
 * Get database connection to aicore database
 * @return mysqli|false Database connection or false on failure
 */
function ai_getDBConnection() {
    static $ai_conn = null;

    if ($ai_conn !== null) {
        // Verify connection is still alive
        try {
            if (!$ai_conn->ping()) {
                aiCentral_logMessage("DB connection lost (ping failed), reconnecting to aicore", 'WARNING');
                $ai_conn->close();
                $ai_conn = null;
            } else {
                return $ai_conn;
            }
        } catch (Exception $e) {
            aiCentral_logMessage("DB connection check error: " . $e->getMessage(), 'ERROR');
            $ai_conn = null;
        }
    }

    $host = getenv('AICORE_DB_HOST');
    $dbname = getenv('AICORE_DB_NAME');
    $username = getenv('AICORE_DB_USER');
    $password = getenv('AICORE_DB_PASS');

    if (!$host || !$username || !$password || !$dbname) {
        aiCentral_logMessage("AICORE_DB_* environment variables not set", 'ERROR');
        return false;
    }

    try {
        $ai_conn = new mysqli($host, $username, $password, $dbname);

        if ($ai_conn->connect_error) {
            aiCentral_logMessage("AI Database connection error: " . $ai_conn->connect_error, 'ERROR');
            return false;
        }

        $ai_conn->set_charset("utf8mb4");
        aiCentral_logMessage("Successfully connected to aicore database", 'INFO');

        return $ai_conn;

    } catch (Exception $e) {
        aiCentral_logMessage("AI Database connection exception: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Get database connection to auth_db database
 * @return mysqli|false Database connection or false on failure
 */
function ai_getAuthDBConnection() {
    static $auth_conn = null;

    if ($auth_conn !== null) {
        // Verify connection is still alive
        try {
            if (!$auth_conn->ping()) {
                aiCentral_logMessage("Auth DB connection lost (ping failed), reconnecting to auth_db", 'WARNING');
                $auth_conn->close();
                $auth_conn = null;
            } else {
                return $auth_conn;
            }
        } catch (Exception $e) {
            aiCentral_logMessage("Auth DB connection check error: " . $e->getMessage(), 'ERROR');
            $auth_conn = null;
        }
    }

    $host = getenv('AICORE_AUTH_DB_HOST');
    $dbname = getenv('AICORE_AUTH_DB_NAME');
    $username = getenv('AICORE_AUTH_DB_USER');
    $password = getenv('AICORE_AUTH_DB_PASS');

    if (!$host || !$username || !$password || !$dbname) {
        aiCentral_logMessage("AICORE_AUTH_DB_* environment variables not set", 'ERROR');
        return false;
    }

    try {
        $auth_conn = new mysqli($host, $username, $password, $dbname);

        if ($auth_conn->connect_error) {
            aiCentral_logMessage("Auth Database connection error: " . $auth_conn->connect_error, 'ERROR');
            return false;
        }

        $auth_conn->set_charset("utf8mb4");
        aiCentral_logMessage("Successfully connected to auth_db database", 'INFO');

        return $auth_conn;

    } catch (Exception $e) {
        aiCentral_logMessage("Auth Database connection exception: " . $e->getMessage(), 'ERROR');
        return false;
    }
}
?>
