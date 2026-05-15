<?php
/**
 * AI Central User Preferences - Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

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

aiCentral_logMessage("User Preferences: action=$action, user=$userId", 'INFO');

try {
    switch ($action) {
        case 'getPreferences':
            getPreferences($userId);
            break;

        case 'getFeatures':
            getFeatures();
            break;

        case 'getModels':
            getModels();
            break;

        case 'updatePreferences':
            updatePreferences($userId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("User Preferences error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get user preferences
 */
function getPreferences($userId) {
    $conn = ai_getDBConnection();

    // Get user-specific preferences from ai_settings
    $sql = "SELECT setting_id, setting_key, setting_value, setting_type
            FROM ai_settings
            WHERE setting_category = 'user' AND setting_scope_id = ?
            ORDER BY setting_key";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $preferences = [];
    while ($row = $result->fetch_assoc()) {
        $preferences[$row['setting_key']] = [
            'setting_id' => $row['setting_id'],
            'value' => $row['setting_value'],
            'type' => $row['setting_type']
        ];
    }
    $stmt->close();

    // If no preferences exist, create defaults
    if (empty($preferences)) {
        $defaults = [
            'preferred_chatbot_model' => ['value' => '', 'type' => 'string'],
            'preferred_analysis_model' => ['value' => '', 'type' => 'string'],
            'enable_streaming' => ['value' => '1', 'type' => 'boolean'],
            'chatbot_retention_days' => ['value' => '90', 'type' => 'integer']
        ];

        foreach ($defaults as $key => $data) {
            $sql = "INSERT INTO ai_settings (setting_category, setting_scope_id, setting_key, setting_value, setting_type)
                    VALUES ('user', ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $userId, $key, $data['value'], $data['type']);
            $stmt->execute();
            $preferences[$key] = [
                'setting_id' => $conn->insert_id,
                'value' => $data['value'],
                'type' => $data['type']
            ];
            $stmt->close();
        }
    }

    echo json_encode(['success' => true, 'preferences' => $preferences]);
}

/**
 * Get active features
 */
function getFeatures() {
    $conn = ai_getDBConnection();

    $sql = "SELECT feature_code, feature_name, program_id
            FROM ai_features
            WHERE is_active = 1
            ORDER BY program_id, feature_name";

    $result = $conn->query($sql);

    $features = [];
    while ($row = $result->fetch_assoc()) {
        $features[] = [
            'feature_code' => $row['feature_code'],
            'feature_name' => $row['feature_name'],
            'program_id' => $row['program_id']
        ];
    }

    echo json_encode(['success' => true, 'features' => $features]);
}

/**
 * Get active models
 */
function getModels() {
    $conn = ai_getDBConnection();

    $sql = "SELECT m.model_id, m.model_code, m.model_display_name,
                   p.provider_name, p.provider_code
            FROM ai_models m
            JOIN ai_providers p ON m.provider_id = p.provider_id
            WHERE m.is_active = 1
            ORDER BY p.provider_name, m.model_display_name";

    $result = $conn->query($sql);

    $models = [];
    while ($row = $result->fetch_assoc()) {
        $models[] = [
            'model_id' => $row['model_id'],
            'model_code' => $row['model_code'],
            'model_display_name' => $row['model_display_name'],
            'provider_name' => $row['provider_name'],
            'provider_code' => $row['provider_code']
        ];
    }

    echo json_encode(['success' => true, 'models' => $models]);
}

/**
 * Update user preferences
 */
function updatePreferences($userId) {
    $conn = ai_getDBConnection();

    $preferences = json_decode($_POST['preferences'] ?? '{}', true);

    if (!is_array($preferences) || count($preferences) === 0) {
        echo json_encode(['success' => false, 'error' => 'No preferences provided']);
        return;
    }

    $conn->begin_transaction();

    try {
        foreach ($preferences as $key => $value) {
            // Check if preference exists
            $sql = "SELECT setting_id FROM ai_settings
                    WHERE setting_category = 'user' AND setting_scope_id = ? AND setting_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $userId, $key);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();

            if ($result->num_rows > 0) {
                // Update existing
                $sql = "UPDATE ai_settings SET setting_value = ?, updated_at = NOW()
                        WHERE setting_category = 'user' AND setting_scope_id = ? AND setting_key = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sss', $value, $userId, $key);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insert new (shouldn't happen, but handle it)
                $type = 'string';
                $sql = "INSERT INTO ai_settings (setting_category, setting_scope_id, setting_key, setting_value, setting_type)
                        VALUES ('user', ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssss', $userId, $key, $value, $type);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        aiCentral_logMessage("User preferences updated: user=$userId, count=" . count($preferences), 'INFO');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Internal error']);
    }
}

?>
