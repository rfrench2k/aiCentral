<?php
/**
 * AI Database Credentials - Backend API
 * Handles CRUD operations for encrypted database credentials
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/KeyManager.php';

// Check authentication
$user = auth_checkProgramAccess('ai', 'ADMIN'); // Require ADMIN level

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';

    // Get database connection
    $conn = ai_getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    $keyManager = new KeyManager();

    switch ($action) {

        case 'getCredentials':
            // Get all database credentials
            $sql = "SELECT credential_id, database_name, permission_level, db_host, db_username,
                           is_active, created_at, updated_at
                    FROM ai_database_credentials
                    ORDER BY database_name, permission_level";

            $result = $conn->query($sql);
            $credentials = [];

            while ($row = $result->fetch_assoc()) {
                $credentials[] = [
                    'credential_id' => $row['credential_id'],
                    'database_name' => $row['database_name'],
                    'permission_level' => $row['permission_level'],
                    'db_host' => $row['db_host'],
                    'db_username' => $row['db_username'],
                    'is_active' => (bool)$row['is_active'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }

            echo json_encode([
                'success' => true,
                'credentials' => $credentials
            ]);
            break;

        case 'addCredential':
            $databaseName = trim($_POST['databaseName'] ?? '');
            $permissionLevel = $_POST['permissionLevel'] ?? '';
            $dbHost = trim($_POST['dbHost'] ?? 'localhost');
            $dbUsername = trim($_POST['dbUsername'] ?? '');
            $dbPassword = $_POST['dbPassword'] ?? '';

            // Validation
            if (empty($databaseName) || empty($permissionLevel) || empty($dbHost) || empty($dbUsername) || empty($dbPassword)) {
                throw new Exception('All fields are required');
            }

            if (!in_array($permissionLevel, ['readonly', 'readwrite', 'full'])) {
                throw new Exception('Invalid permission level');
            }

            // Check if credential already exists
            $sql = "SELECT credential_id FROM ai_database_credentials
                    WHERE database_name = ? AND permission_level = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $databaseName, $permissionLevel);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Credential already exists for {$databaseName} with {$permissionLevel} access");
            }

            // Encrypt password
            $encrypted = $keyManager->encryptKey($dbPassword);

            // Insert credential
            $sql = "INSERT INTO ai_database_credentials
                    (database_name, permission_level, db_host, db_username, encrypted_password, encryption_iv)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssss',
                $databaseName,
                $permissionLevel,
                $dbHost,
                $dbUsername,
                $encrypted['encrypted'],
                $encrypted['iv']
            );

            if ($stmt->execute()) {
                aiCentral_logMessage("Added database credential: {$databaseName}/{$permissionLevel} by user={$user['user_id']}", 'INFO');

                echo json_encode([
                    'success' => true,
                    'message' => 'Database credential added successfully',
                    'credential_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception('Failed to insert credential: ' . $stmt->error);
            }
            break;

        case 'updateCredential':
            $credentialId = intval($_POST['credentialId'] ?? 0);
            $databaseName = trim($_POST['databaseName'] ?? '');
            $permissionLevel = $_POST['permissionLevel'] ?? '';
            $dbHost = trim($_POST['dbHost'] ?? 'localhost');
            $dbUsername = trim($_POST['dbUsername'] ?? '');
            $dbPassword = $_POST['dbPassword'] ?? '';

            if ($credentialId <= 0) {
                throw new Exception('Invalid credential ID');
            }

            // Validation
            if (empty($databaseName) || empty($permissionLevel) || empty($dbHost) || empty($dbUsername)) {
                throw new Exception('All fields except password are required');
            }

            if (!in_array($permissionLevel, ['readonly', 'readwrite', 'full'])) {
                throw new Exception('Invalid permission level');
            }

            // If password provided, re-encrypt it
            if (!empty($dbPassword)) {
                $encrypted = $keyManager->encryptKey($dbPassword);

                $sql = "UPDATE ai_database_credentials
                        SET database_name = ?, permission_level = ?, db_host = ?,
                            db_username = ?, encrypted_password = ?, encryption_iv = ?
                        WHERE credential_id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssssi',
                    $databaseName,
                    $permissionLevel,
                    $dbHost,
                    $dbUsername,
                    $encrypted['encrypted'],
                    $encrypted['iv'],
                    $credentialId
                );
            } else {
                // Update without changing password
                $sql = "UPDATE ai_database_credentials
                        SET database_name = ?, permission_level = ?, db_host = ?, db_username = ?
                        WHERE credential_id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssi',
                    $databaseName,
                    $permissionLevel,
                    $dbHost,
                    $dbUsername,
                    $credentialId
                );
            }

            if ($stmt->execute()) {
                aiCentral_logMessage("Updated database credential ID={$credentialId} by user={$user['user_id']}", 'INFO');

                echo json_encode([
                    'success' => true,
                    'message' => 'Database credential updated successfully'
                ]);
            } else {
                throw new Exception('Failed to update credential: ' . $stmt->error);
            }
            break;

        case 'deleteCredential':
            $credentialId = intval($_POST['credentialId'] ?? 0);

            if ($credentialId <= 0) {
                throw new Exception('Invalid credential ID');
            }

            // Soft delete (set is_active = 0)
            $sql = "UPDATE ai_database_credentials SET is_active = 0 WHERE credential_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $credentialId);

            if ($stmt->execute()) {
                aiCentral_logMessage("Deleted (soft) database credential ID={$credentialId} by user={$user['user_id']}", 'INFO');

                echo json_encode([
                    'success' => true,
                    'message' => 'Database credential deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete credential: ' . $stmt->error);
            }
            break;

        case 'testConnection':
            $credentialId = intval($_POST['credentialId'] ?? 0);

            if ($credentialId <= 0) {
                throw new Exception('Invalid credential ID');
            }

            // Get credential
            $sql = "SELECT database_name, db_host, db_username, encrypted_password, encryption_iv
                    FROM ai_database_credentials
                    WHERE credential_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $credentialId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$credential = $result->fetch_assoc()) {
                throw new Exception('Credential not found');
            }

            // Decrypt password
            $dbPassword = $keyManager->decryptKey(
                $credential['encrypted_password'],
                $credential['encryption_iv']
            );

            if ($dbPassword === false) {
                throw new Exception('Failed to decrypt password');
            }

            // Test connection
            $testConn = @new mysqli(
                $credential['db_host'],
                $credential['db_username'],
                $dbPassword,
                $credential['database_name']
            );

            if ($testConn->connect_error) {
                $errorMsg = $testConn->connect_error;
                aiCentral_logMessage("Database connection test failed for credential ID={$credentialId}: {$errorMsg}", 'WARNING');

                echo json_encode([
                    'success' => false,
                    'message' => 'Connection failed',
                    'error' => $errorMsg,
                    'test_success' => false
                ]);
            } else {
                // Test a simple query
                $testResult = $testConn->query("SELECT 1 as test");

                if ($testResult) {
                    $testConn->close();

                    aiCentral_logMessage("Database connection test successful for credential ID={$credentialId}", 'INFO');

                    echo json_encode([
                        'success' => true,
                        'message' => 'Connection successful!',
                        'test_success' => true,
                        'database' => $credential['database_name'],
                        'host' => $credential['db_host'],
                        'username' => $credential['db_username']
                    ]);
                } else {
                    $testConn->close();
                    throw new Exception('Connected but query failed: ' . $testConn->error);
                }
            }
            break;

        default:
            throw new Exception('Invalid action');
    }

    $conn->close();

} catch (Exception $e) {
    aiCentral_logMessage("aiDatabaseCredentialsCode error: " . $e->getMessage(), 'ERROR');

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
