<?php
/**
 * AI Central API - Assign User Tier
 * Called by Auth system when users are created or updated
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/aPRIV_DB_AI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/common/common_ai.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ai/includes/config.php';

header('Content-Type: application/json');

// Security validation
function validateApiRequest() {
    $allowedCallers = json_decode(ALLOWED_API_CALLERS, true);
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

    // Check IP whitelist
    if (!in_array($clientIP, $allowedCallers)) {
        aiCentral_logMessage("API access denied from IP: $clientIP", 'WARNING');
        return ['valid' => false, 'error' => 'Access denied'];
    }

    // Check API secret (header only; constant-time compare)
    $apiSecret = $_SERVER['HTTP_X_API_SECRET'] ?? '';
    if (!is_string($apiSecret) || $apiSecret === '' || !hash_equals(AI_API_SECRET, $apiSecret)) {
        aiCentral_logMessage("API access denied - invalid secret from IP: $clientIP", 'WARNING');
        return ['valid' => false, 'error' => 'Invalid API credentials'];
    }

    return ['valid' => true];
}

$action = $_REQUEST['action'] ?? '';

aiCentral_logMessage("Tier Assignment API: action=$action from IP={$_SERVER['REMOTE_ADDR']}", 'INFO');

// Validate request
$validation = validateApiRequest();
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'error' => $validation['error']]);
    exit;
}

try {
    switch ($action) {
        case 'assignUserTier':
            assignUserTier();
            break;

        case 'updateUserTier':
            updateUserTier();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Tier Assignment API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Assign tier to new user
 */
function assignUserTier() {
    $conn = ai_getDBConnection();

    $userId = trim($_POST['user_id'] ?? '');
    $programId = trim($_POST['program_id'] ?? '');
    $appLevel = trim($_POST['app_level'] ?? '');

    // Validation
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'user_id is required']);
        return;
    }

    if (empty($programId)) {
        echo json_encode(['success' => false, 'error' => 'program_id is required']);
        return;
    }

    if (empty($appLevel)) {
        echo json_encode(['success' => false, 'error' => 'app_level is required']);
        return;
    }

    aiCentral_logMessage("Assigning tier: user_id=$userId, program_id=$programId, app_level=$appLevel", 'INFO');

    // Map app_level to ai_tier_code
    $sql = "SELECT ai_tier_code FROM app_level_ai_mapping WHERE app_level = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$appLevel], 's');

    if (!$result || $result->num_rows === 0) {
        // Default to free tier if mapping not found
        aiCentral_logMessage("No mapping found for app_level=$appLevel, defaulting to free", 'WARNING');
        $aiTierCode = 'free';
    } else {
        $row = $result->fetch_assoc();
        $aiTierCode = $row['ai_tier_code'];
    }

    // Get tier_id from ai_tiers
    $sql = "SELECT tier_id FROM ai_tiers WHERE tier_code = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$aiTierCode], 's');

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => "AI tier not found: $aiTierCode"]);
        return;
    }

    $row = $result->fetch_assoc();
    $tierId = $row['tier_id'];

    // Check if assignment already exists
    $sql = "SELECT assignment_id FROM user_tier_assignments
            WHERE user_id = ? AND program_id = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$userId, $programId], 'ss');

    if ($result && $result->num_rows > 0) {
        // Update existing assignment
        $assignedBy = 'system';
        $sql = "UPDATE user_tier_assignments
                SET tier_id = ?, assigned_by = ?, assigned_at = NOW()
                WHERE user_id = ? AND program_id = ?";
        $affectedRows = aiCentral_executeUpdate($conn, $sql, [$tierId, $assignedBy, $userId, $programId], 'isss');

        if ($affectedRows !== false) {
            aiCentral_logMessage("Updated tier assignment: user_id=$userId, program_id=$programId, tier_id=$tierId", 'INFO');
            echo json_encode([
                'success' => true,
                'message' => 'Tier assignment updated',
                'tier_code' => $aiTierCode,
                'tier_id' => $tierId
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update tier assignment']);
        }
    } else {
        // Insert new assignment
        $assignedBy = 'system';
        $sql = "INSERT INTO user_tier_assignments (user_id, tier_id, program_id, assigned_by, assigned_at)
                VALUES (?, ?, ?, ?, NOW())";
        $affectedRows = aiCentral_executeUpdate($conn, $sql, [$userId, $tierId, $programId, $assignedBy], 'siss');

        if ($affectedRows !== false) {
            aiCentral_logMessage("Created tier assignment: user_id=$userId, program_id=$programId, tier_id=$tierId", 'INFO');
            echo json_encode([
                'success' => true,
                'message' => 'Tier assignment created',
                'tier_code' => $aiTierCode,
                'tier_id' => $tierId
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create tier assignment']);
        }
    }
}

/**
 * Update user tier (when permission level changes)
 */
function updateUserTier() {
    $conn = ai_getDBConnection();

    $userId = trim($_POST['user_id'] ?? '');
    $programId = trim($_POST['program_id'] ?? '');
    $appLevel = trim($_POST['app_level'] ?? '');

    // Validation
    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'user_id is required']);
        return;
    }

    if (empty($programId)) {
        echo json_encode(['success' => false, 'error' => 'program_id is required']);
        return;
    }

    if (empty($appLevel)) {
        echo json_encode(['success' => false, 'error' => 'app_level is required']);
        return;
    }

    aiCentral_logMessage("Updating tier: user_id=$userId, program_id=$programId, app_level=$appLevel", 'INFO');

    // Map app_level to ai_tier_code
    $sql = "SELECT ai_tier_code FROM app_level_ai_mapping WHERE app_level = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$appLevel], 's');

    if (!$result || $result->num_rows === 0) {
        // Default to free tier if mapping not found
        aiCentral_logMessage("No mapping found for app_level=$appLevel, defaulting to free", 'WARNING');
        $aiTierCode = 'free';
    } else {
        $row = $result->fetch_assoc();
        $aiTierCode = $row['ai_tier_code'];
    }

    // Get tier_id from ai_tiers
    $sql = "SELECT tier_id FROM ai_tiers WHERE tier_code = ?";
    $result = aiCentral_executeQuery($conn, $sql, [$aiTierCode], 's');

    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => "AI tier not found: $aiTierCode"]);
        return;
    }

    $row = $result->fetch_assoc();
    $tierId = $row['tier_id'];

    // Update tier assignment
    $assignedBy = 'system';
    $sql = "UPDATE user_tier_assignments
            SET tier_id = ?, assigned_by = ?, assigned_at = NOW()
            WHERE user_id = ? AND program_id = ?";
    $affectedRows = aiCentral_executeUpdate($conn, $sql, [$tierId, $assignedBy, $userId, $programId], 'isss');

    if ($affectedRows !== false) {
        if ($affectedRows > 0) {
            aiCentral_logMessage("Updated tier assignment: user_id=$userId, program_id=$programId, tier_id=$tierId", 'INFO');
            echo json_encode([
                'success' => true,
                'message' => 'Tier assignment updated',
                'tier_code' => $aiTierCode,
                'tier_id' => $tierId
            ]);
        } else {
            // No rows updated - assignment doesn't exist, create it
            $sql = "INSERT INTO user_tier_assignments (user_id, tier_id, program_id, assigned_by, assigned_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $affectedRows = aiCentral_executeUpdate($conn, $sql, [$userId, $tierId, $programId, $assignedBy], 'siss');

            if ($affectedRows !== false) {
                aiCentral_logMessage("Created tier assignment (update fallback): user_id=$userId, program_id=$programId, tier_id=$tierId", 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => 'Tier assignment created',
                    'tier_code' => $aiTierCode,
                    'tier_id' => $tierId
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create tier assignment']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update tier assignment']);
    }
}

?>
