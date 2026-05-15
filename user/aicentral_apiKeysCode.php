<?php
/**
 * AI Central User API Keys - Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/KeyManager.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

// SECURITY: Use session-based authentication (NOT cookies)
$user = auth_getAuthenticatedUser('AI');
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}
$userId = $user['user_id'];  // Legacy users: username string, new users: email

aiCentral_logMessage("User API Keys: action=$action, user=$userId", 'INFO');

try {
    switch ($action) {
        case 'getKeys':
            getKeys($userId);
            break;

        case 'getProviders':
            getProviders();
            break;

        case 'saveKey':
            saveKey($userId);
            break;

        case 'deleteKey':
            deleteKey($userId);
            break;

        case 'toggleKeyStatus':
            toggleKeyStatus($userId);
            break;

        case 'setDefaultKey':
            setDefaultKey($userId);
            break;

        case 'testKey':
            testKey($userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("User API Keys error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get user's API keys
 */
function getKeys($userId) {
    $conn = ai_getDBConnection();

    $sql = "SELECT k.key_id, k.provider_id, k.key_name, k.key_prefix,
                   k.is_active, k.is_default, k.last_used_at,
                   k.last_test_status, k.last_test_error, k.created_at,
                   p.provider_name, p.provider_code
            FROM user_api_keys k
            JOIN ai_providers p ON k.provider_id = p.provider_id
            WHERE k.user_id = ?
            ORDER BY k.is_default DESC, k.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $keys = [];
    while ($row = $result->fetch_assoc()) {
        $keys[] = [
            'key_id' => $row['key_id'],
            'provider_id' => $row['provider_id'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code'],
            'key_name' => $row['key_name'],
            'key_prefix' => $row['key_prefix'],
            'is_active' => (bool)$row['is_active'],
            'is_default' => (bool)$row['is_default'],
            'last_used_at' => $row['last_used_at'],
            'last_test_status' => $row['last_test_status'],
            'last_test_error' => $row['last_test_error'],
            'created_at' => $row['created_at']
        ];
    }

    $stmt->close();
    echo json_encode(['success' => true, 'keys' => $keys]);
}

/**
 * Get providers list
 */
function getProviders() {
    $conn = ai_getDBConnection();

    $sql = "SELECT provider_id, provider_name, provider_code, is_active
            FROM ai_providers
            WHERE is_active = 1
            ORDER BY provider_name";

    $result = $conn->query($sql);

    $providers = [];
    while ($row = $result->fetch_assoc()) {
        $providers[] = [
            'provider_id' => $row['provider_id'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code']
        ];
    }

    echo json_encode(['success' => true, 'providers' => $providers]);
}

/**
 * Save API key
 */
function saveKey($userId) {
    $conn = ai_getDBConnection();

    $keyId = $_POST['key_id'] ?? null;
    $providerId = $_POST['provider_id'] ?? '';
    $keyName = trim($_POST['key_name'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    $testKey = isset($_POST['test_key']) ? true : false;

    // Validation
    if (empty($providerId) || empty($keyName) || empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'Provider, key name, and API key are required']);
        return;
    }

    // Test key if requested
    if ($testKey) {
        // Get provider_code and a test model for this provider
        $sql = "SELECT p.provider_code, m.model_code
                FROM ai_providers p
                JOIN ai_models m ON p.provider_id = m.provider_id
                WHERE p.provider_id = ? AND m.is_active = 1
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $providerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'No active models found for this provider']);
            $stmt->close();
            return;
        }
        $providerData = $result->fetch_assoc();
        $providerCode = $providerData['provider_code'];
        $modelCode = $providerData['model_code'];
        $stmt->close();

        $keyManager = new KeyManager();
        $testResult = $keyManager->testKey($providerCode, $modelCode, $apiKey);
        if (!$testResult['success']) {
            echo json_encode([
                'success' => false,
                'error' => 'API key test failed: ' . ($testResult['error'] ?? 'Unknown error')
            ]);
            return;
        }
    }

    // Encrypt the API key
    $keyManager = new KeyManager();
    $encrypted = $keyManager->encryptKey($apiKey);

    // Extract key prefix for display
    $keyPrefix = substr($apiKey, 0, min(10, strlen($apiKey)));

    if ($keyId) {
        // Update existing key - NOT ALLOWED for security
        echo json_encode(['success' => false, 'error' => 'Updating keys is not allowed. Please delete and create a new one.']);
        return;
    } else {
        // If setting as default, unset other defaults for this provider
        if ($isDefault) {
            $sql = "UPDATE user_api_keys SET is_default = 0
                    WHERE user_id = ? AND provider_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $userId, $providerId);
            $stmt->execute();
            $stmt->close();
        }

        // Insert new key
        $sql = "INSERT INTO user_api_keys (
                user_id, provider_id, key_name, encrypted_api_key,
                encryption_iv, key_prefix, is_default, last_test_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'success')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sissssi',
            $userId, $providerId, $keyName,
            $encrypted['encrypted'], $encrypted['iv'],
            $keyPrefix, $isDefault
        );
    }

    if ($stmt->execute()) {
        $id = $keyId ?: $conn->insert_id;
        aiCentral_logMessage("User API key saved: user=$userId, key_id=$id", 'INFO');
        echo json_encode(['success' => true, 'key_id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Delete API key
 */
function deleteKey($userId) {
    $conn = ai_getDBConnection();

    $keyId = $_POST['key_id'] ?? 0;

    if (!$keyId) {
        echo json_encode(['success' => false, 'error' => 'Key ID required']);
        return;
    }

    // Verify ownership
    $sql = "SELECT key_id FROM user_api_keys WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Key not found or access denied']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Delete key
    $sql = "DELETE FROM user_api_keys WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);

    if ($stmt->execute()) {
        aiCentral_logMessage("User API key deleted: user=$userId, key_id=$keyId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Toggle key active status
 */
function toggleKeyStatus($userId) {
    $conn = ai_getDBConnection();

    $keyId = $_POST['key_id'] ?? 0;

    if (!$keyId) {
        echo json_encode(['success' => false, 'error' => 'Key ID required']);
        return;
    }

    // Verify ownership
    $sql = "SELECT key_id FROM user_api_keys WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Key not found or access denied']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Toggle status
    $sql = "UPDATE user_api_keys SET is_active = NOT is_active, updated_at = NOW()
            WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);

    if ($stmt->execute()) {
        // Get new status
        $sql = "SELECT is_active FROM user_api_keys WHERE key_id = ?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $keyId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        $newStatus = (bool)$row['is_active'];
        $stmt2->close();

        aiCentral_logMessage("User API key toggled: user=$userId, key_id=$keyId, active=$newStatus", 'INFO');
        echo json_encode(['success' => true, 'is_active' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Set key as default for provider
 */
function setDefaultKey($userId) {
    $conn = ai_getDBConnection();

    $keyId = $_POST['key_id'] ?? 0;

    if (!$keyId) {
        echo json_encode(['success' => false, 'error' => 'Key ID required']);
        return;
    }

    // Verify ownership and get provider_id
    $sql = "SELECT provider_id FROM user_api_keys WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Key not found or access denied']);
        $stmt->close();
        return;
    }
    $row = $result->fetch_assoc();
    $providerId = $row['provider_id'];
    $stmt->close();

    // Unset all defaults for this provider
    $sql = "UPDATE user_api_keys SET is_default = 0
            WHERE user_id = ? AND provider_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $userId, $providerId);
    $stmt->execute();
    $stmt->close();

    // Set this key as default
    $sql = "UPDATE user_api_keys SET is_default = 1, updated_at = NOW()
            WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);

    if ($stmt->execute()) {
        aiCentral_logMessage("User API key set as default: user=$userId, key_id=$keyId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Test API key
 */
function testKey($userId) {
    $conn = ai_getDBConnection();

    $keyId = $_POST['key_id'] ?? 0;

    if (!$keyId) {
        echo json_encode(['success' => false, 'error' => 'Key ID required']);
        return;
    }

    // Verify ownership and get key details
    $sql = "SELECT provider_id, encrypted_api_key, encryption_iv
            FROM user_api_keys
            WHERE key_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $keyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Key not found or access denied']);
        $stmt->close();
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    // Get provider_code and model_code for testing
    $sql2 = "SELECT p.provider_code, m.model_code
            FROM ai_providers p
            JOIN ai_models m ON p.provider_id = m.provider_id
            WHERE p.provider_id = ? AND m.is_active = 1
            LIMIT 1";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param('i', $row['provider_id']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    if ($result2->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'No active models found for this provider']);
        $stmt2->close();
        return;
    }
    $providerData = $result2->fetch_assoc();
    $providerCode = $providerData['provider_code'];
    $modelCode = $providerData['model_code'];
    $stmt2->close();

    // Decrypt key
    $keyManager = new KeyManager();
    $apiKey = $keyManager->decryptKey($row['encrypted_api_key'], $row['encryption_iv']);

    // Test key
    $testResult = $keyManager->testKey($providerCode, $modelCode, $apiKey);

    // Update test status
    $status = $testResult['success'] ? 'success' : 'failed';
    $error = $testResult['success'] ? null : ($testResult['error'] ?? 'Unknown error');

    $sql = "UPDATE user_api_keys
            SET last_test_status = ?, last_test_error = ?, updated_at = NOW()
            WHERE key_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $status, $error, $keyId);
    $stmt->execute();
    $stmt->close();

    aiCentral_logMessage("User API key tested: user=$userId, key_id=$keyId, status=$status", 'INFO');
    echo json_encode($testResult);
}

?>
