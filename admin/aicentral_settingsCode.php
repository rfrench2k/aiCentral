<?php
/**
 * AI Central Admin Settings - Backend API
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

header('Content-Type: application/json');

// SECURITY: Require ADMIN-level authentication for admin functions
$user = auth_getAuthenticatedUser('AI');
if (!$user || !in_array($user['user_level'], ['ADMIN', 'SUPERADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Settings API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getSettings':
            getSettings();
            break;

        case 'updateSetting':
            updateSetting();
            break;

        case 'updateAllSettings':
            updateAllSettings();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Settings API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all system settings
 */
function getSettings() {
    $conn = ai_getDBConnection();

    $sql = "SELECT *
            FROM ai_settings
            WHERE setting_category = 'system'
            ORDER BY setting_key";

    $result = $conn->query($sql);

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[] = [
            'setting_id' => $row['setting_id'],
            'setting_key' => $row['setting_key'],
            'setting_value' => $row['setting_value'],
            'setting_type' => $row['setting_type'],
            'setting_category' => $row['setting_category'],
            'setting_scope_id' => $row['setting_scope_id']
        ];
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
}

/**
 * Update single setting
 */
function updateSetting() {
    $conn = ai_getDBConnection();

    $settingId = $_POST['setting_id'] ?? 0;
    $settingValue = $_POST['setting_value'] ?? '';

    if (!$settingId) {
        echo json_encode(['success' => false, 'error' => 'Setting ID required']);
        return;
    }

    $sql = "UPDATE ai_settings SET setting_value = ?, updated_at = NOW() WHERE setting_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $settingValue, $settingId);

    if ($stmt->execute()) {
        aiCentral_logMessage("Setting updated: ID=$settingId, Value=$settingValue", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Update all settings
 */
function updateAllSettings() {
    $conn = ai_getDBConnection();

    $settings = json_decode($_POST['settings'] ?? '[]', true);

    if (!is_array($settings) || count($settings) === 0) {
        echo json_encode(['success' => false, 'error' => 'No settings provided']);
        return;
    }

    $conn->begin_transaction();

    try {
        foreach ($settings as $setting) {
            $settingId = $setting['setting_id'] ?? 0;
            $settingValue = $setting['setting_value'] ?? '';

            if (!$settingId) continue;

            $sql = "UPDATE ai_settings SET setting_value = ?, updated_at = NOW() WHERE setting_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $settingValue, $settingId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        aiCentral_logMessage("All settings updated: " . count($settings) . " settings", 'INFO');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Internal error']);
    }
}

?>
