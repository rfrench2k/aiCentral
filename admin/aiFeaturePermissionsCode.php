<?php
/**
 * AI Feature Permissions - Backend API
 * Handles CRUD operations for feature permissions
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/auth/includes/auth_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';

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

    switch ($action) {

        case 'getFeatures':
            // Get all features using claude_cli provider
            $sql = "SELECT f.feature_id, f.feature_code, f.feature_name, f.program_id, f.default_provider
                    FROM ai_features f
                    WHERE f.default_provider = 'claude_cli' AND f.is_active = 1
                    ORDER BY f.program_id, f.feature_name";

            $result = $conn->query($sql);
            $features = [];

            while ($row = $result->fetch_assoc()) {
                $features[] = $row;
            }

            echo json_encode([
                'success' => true,
                'features' => $features
            ]);
            break;

        case 'getFeaturePermissions':
            $featureId = intval($_POST['featureId'] ?? 0);

            if ($featureId <= 0) {
                throw new Exception('Invalid feature ID');
            }

            // Get all permissions for this feature
            $sql = "SELECT permission_type, is_enabled
                    FROM ai_feature_permissions
                    WHERE feature_id = ?
                    ORDER BY permission_type";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $featureId);
            $stmt->execute();
            $result = $stmt->get_result();

            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[$row['permission_type']] = (bool)$row['is_enabled'];
            }

            echo json_encode([
                'success' => true,
                'permissions' => $permissions
            ]);
            break;

        case 'saveFeaturePermissions':
            $featureId = intval($_POST['featureId'] ?? 0);
            $permissions = json_decode($_POST['permissions'] ?? '{}', true);

            if ($featureId <= 0) {
                throw new Exception('Invalid feature ID');
            }

            if (!is_array($permissions)) {
                throw new Exception('Invalid permissions data');
            }

            // Update each permission
            $sql = "UPDATE ai_feature_permissions
                    SET is_enabled = ?
                    WHERE feature_id = ? AND permission_type = ?";

            $stmt = $conn->prepare($sql);
            $updatedCount = 0;

            foreach ($permissions as $permType => $isEnabled) {
                $enabled = $isEnabled ? 1 : 0;
                $stmt->bind_param('iis', $enabled, $featureId, $permType);
                $stmt->execute();
                $updatedCount++;
            }

            aiCentral_logMessage("Updated {$updatedCount} permissions for feature_id={$featureId} by user={$user['user_id']}", 'INFO');

            echo json_encode([
                'success' => true,
                'message' => "Updated {$updatedCount} permissions successfully"
            ]);
            break;

        case 'previewPrompt':
            $featureId = intval($_POST['featureId'] ?? 0);

            if ($featureId <= 0) {
                throw new Exception('Invalid feature ID');
            }

            // Get feature details
            $sql = "SELECT feature_code, program_id FROM ai_features WHERE feature_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $featureId);
            $stmt->execute();
            $result = $stmt->get_result();
            $feature = $result->fetch_assoc();

            if (!$feature) {
                throw new Exception('Feature not found');
            }

            // Get permissions
            $sql = "SELECT permission_type, is_enabled FROM ai_feature_permissions WHERE feature_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $featureId);
            $stmt->execute();
            $result = $stmt->get_result();

            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[$row['permission_type']] = (bool)$row['is_enabled'];
            }

            // Build sample prompt (simplified version of what AIDeepInsights builds)
            $prompt = "SYSTEM PROMPT PREVIEW\n";
            $prompt .= "====================\n\n";
            $prompt .= "Feature: {$feature['feature_code']}\n";
            $prompt .= "Program: {$feature['program_id']}\n\n";

            $prompt .= "CRITICAL SECURITY RULE:\n";
            $prompt .= "THE LOGGED-IN USER IS: {user_id}\n";
            $prompt .= "IF a table has a 'user_id' column, YOU MUST filter by: WHERE user_id = '{user_id}'\n\n";

            $prompt .= "PERMISSIONS:\n";
            $prompt .= "Database Read: " . ($permissions['allow_db_read'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "Database Write: " . ($permissions['allow_db_write'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "Database Delete: " . ($permissions['allow_db_delete'] ? '⚠ ALLOWED - CAUTION' : '✗ DENIED') . "\n";
            $prompt .= "Temporary Tables: " . ($permissions['allow_temp_tables'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "Web Search: " . ($permissions['allow_web_search'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "Web Fetch: " . ($permissions['allow_web_fetch'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "File Read: " . ($permissions['allow_file_read'] ? '✓ ALLOWED' : '✗ DENIED') . "\n";
            $prompt .= "File Write: " . ($permissions['allow_file_write'] ? '✓ ALLOWED' : '✗ DENIED') . "\n\n";

            $prompt .= "ALLOWED CLAUDE CLI TOOLS:\n";
            $tools = [];
            if ($permissions['allow_db_read']) $tools[] = 'Bash';
            if ($permissions['allow_web_search']) $tools[] = 'WebSearch';
            if ($permissions['allow_web_fetch']) $tools[] = 'WebFetch';
            if ($permissions['allow_file_read']) $tools = array_merge($tools, ['Read', 'Grep', 'Glob']);
            if ($permissions['allow_file_write']) $tools = array_merge($tools, ['Write', 'Edit']);

            if (empty($tools)) {
                $prompt .= "⚠ WARNING: No permissions enabled! Claude CLI will have minimal access.\n";
                $tools[] = 'Read'; // Fallback
            }

            $prompt .= "--allowed-tools " . implode(',', $tools) . "\n\n";

            $prompt .= "This is a preview. Actual prompt includes database credentials, conversation history, and detailed instructions.";

            echo json_encode([
                'success' => true,
                'prompt' => $prompt
            ]);
            break;

        case 'previewTools':
            $permissions = json_decode($_POST['permissions'] ?? '{}', true);

            if (!is_array($permissions)) {
                throw new Exception('Invalid permissions data');
            }

            $tools = [];
            if (!empty($permissions['allow_db_read'])) $tools[] = 'Bash';
            if (!empty($permissions['allow_web_search'])) $tools[] = 'WebSearch';
            if (!empty($permissions['allow_web_fetch'])) $tools[] = 'WebFetch';
            if (!empty($permissions['allow_file_read'])) $tools = array_merge($tools, ['Read', 'Grep', 'Glob']);
            if (!empty($permissions['allow_file_write'])) $tools = array_merge($tools, ['Write', 'Edit']);

            if (empty($tools)) {
                $tools[] = 'Read'; // Fallback
                $warning = 'WARNING: No permissions enabled. Minimal access with Read tool only.';
            } else {
                $warning = null;
            }

            echo json_encode([
                'success' => true,
                'tools' => $tools,
                'toolString' => implode(', ', $tools),
                'warning' => $warning
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

    $conn->close();

} catch (Exception $e) {
    aiCentral_logMessage("aiFeaturePermissionsCode error: " . $e->getMessage(), 'ERROR');

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
