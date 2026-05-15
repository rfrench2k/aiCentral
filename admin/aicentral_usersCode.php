<?php
/**
 * AI Central Admin Users - Backend API
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

aiCentral_logMessage("Users API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getUsers':
            getUsers();
            break;

        case 'getTiers':
            getTiers();
            break;

        case 'syncUsers':
            syncUsers();
            break;

        case 'updateUserTier':
            updateUserTier();
            break;

        case 'getUserProgramTiers':
            getUserProgramTiers();
            break;

        case 'updateProgramTier':
            updateProgramTier();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Users API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all users (from auth_db.master_users)
 */
function getUsers() {
    $conn = ai_getDBConnection();

    // Read directly from auth_db.master_users and join with AI tier assignments
    $sql = "SELECT u.user_id, u.user_name as name, u.email,
                   GROUP_CONCAT(
                       DISTINCT CONCAT(p.program_id, ':', COALESCE(t.tier_code, 'none'))
                       SEPARATOR ', '
                   ) as program_tiers,
                   u.created_at, u.updated_at
            FROM auth_db.master_users u
            LEFT JOIN auth_db.user_program_permissions p ON u.email = p.email
            LEFT JOIN user_tier_assignments uta ON u.user_id = uta.user_id AND uta.program_id = p.program_id
            LEFT JOIN ai_tiers t ON uta.tier_id = t.tier_id
            WHERE u.user_id IS NOT NULL
            GROUP BY u.user_id, u.user_name, u.email, u.created_at, u.updated_at
            ORDER BY u.user_name, u.user_id";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Query failed']);
        return;
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'program_tiers' => $row['program_tiers'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get tiers list
 */
function getTiers() {
    $conn = ai_getDBConnection();

    $sql = "SELECT tier_id, tier_code, tier_name, is_active
            FROM ai_tiers
            WHERE is_active = 1
            ORDER BY sort_order";

    $result = $conn->query($sql);

    $tiers = [];
    while ($row = $result->fetch_assoc()) {
        $tiers[] = [
            'tier_code' => $row['tier_code'],
            'tier_name' => $row['tier_name']
        ];
    }

    echo json_encode(['success' => true, 'tiers' => $tiers]);
}

/**
 * Sync users from auth_db
 */
function syncUsers() {
    $conn = ai_getDBConnection();

    // Connect to auth_db using centralized connection function
    $authConn = ai_getAuthDBConnection();
    if ($authConn === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to connect to auth_db']);
        return;
    }

    // Get all users from auth_db.master_users
    $sql = "SELECT user_id, user_name, email FROM master_users WHERE user_id IS NOT NULL";
    $result = $authConn->query($sql);

    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to query auth database']);
        $authConn->close();
        return;
    }

    $synced = 0;
    $updated = 0;
    $skipped = 0;

    while ($row = $result->fetch_assoc()) {
        $userId = $row['user_id'];
        $name = $row['user_name'];
        $email = $row['email'];

        // Skip users without user_id
        if (empty($userId)) {
            $skipped++;
            continue;
        }

        // Check if user exists in aicore
        $checkSql = "SELECT user_id FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($checkSql);
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $checkResult = $stmt->get_result();
        $stmt->close();

        if ($checkResult->num_rows > 0) {
            // Update existing user
            $updateSql = "UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param('sss', $name, $email, $userId);
            $stmt->execute();
            $stmt->close();
            $updated++;
        } else {
            // Insert new user
            $insertSql = "INSERT INTO users (user_id, name, email, default_ai_tier) VALUES (?, ?, ?, 'free')";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param('sss', $userId, $name, $email);
            $stmt->execute();
            $stmt->close();
            $synced++;

            // Also assign default tier in user_tier_assignments
            $tierSql = "SELECT tier_id FROM ai_tiers WHERE tier_code = 'free' LIMIT 1";
            $tierResult = $conn->query($tierSql);
            if ($tierResult && $tierResult->num_rows > 0) {
                $tierRow = $tierResult->fetch_assoc();
                $tierId = $tierRow['tier_id'];

                // Check if tier assignment exists
                $checkTierSql = "SELECT * FROM user_tier_assignments WHERE user_id = ? AND program_id IS NULL";
                $stmt = $conn->prepare($checkTierSql);
                $stmt->bind_param('s', $userId);
                $stmt->execute();
                $tierCheckResult = $stmt->get_result();
                $stmt->close();

                if ($tierCheckResult->num_rows === 0) {
                    // Insert tier assignment
                    $assignTierSql = "INSERT INTO user_tier_assignments (user_id, tier_id, program_id, assigned_by) VALUES (?, ?, NULL, 'system')";
                    $stmt = $conn->prepare($assignTierSql);
                    $stmt->bind_param('si', $userId, $tierId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    $authConn->close();

    aiCentral_logMessage("Users synced: $synced new, $updated updated, $skipped skipped", 'INFO');
    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'updated' => $updated,
        'skipped' => $skipped
    ]);
}

/**
 * Update user tier
 */
function updateUserTier() {
    $conn = ai_getDBConnection();

    $userId = $_POST['user_id'] ?? '';
    $tierCode = $_POST['tier_code'] ?? '';

    if (empty($userId) || empty($tierCode)) {
        echo json_encode(['success' => false, 'error' => 'User ID and tier code are required']);
        return;
    }

    // Verify tier exists
    $sql = "SELECT tier_code FROM ai_tiers WHERE tier_code = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $tierCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid tier code']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Update user tier
    $sql = "UPDATE users SET default_ai_tier = ?, updated_at = NOW() WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $tierCode, $userId);

    if ($stmt->execute()) {
        aiCentral_logMessage("User tier updated: user=$userId, tier=$tierCode", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Get user's program-specific tier assignments
 */
function getUserProgramTiers() {
    $conn = ai_getDBConnection();

    $userId = $_REQUEST['user_id'] ?? '';

    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID is required']);
        return;
    }

    // Get all programs the user has access to from auth_db
    $sql = "SELECT p.program_id, p.permission_level,
                   t.tier_code as current_tier, t.tier_name
            FROM auth_db.user_program_permissions p
            INNER JOIN auth_db.master_users u ON p.email = u.email
            LEFT JOIN user_tier_assignments uta ON uta.user_id = u.user_id AND uta.program_id = p.program_id
            LEFT JOIN ai_tiers t ON uta.tier_id = t.tier_id
            WHERE u.user_id = ?
            ORDER BY p.program_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = [
            'program_id' => $row['program_id'],
            'permission_level' => $row['permission_level'],
            'current_tier' => $row['current_tier'],
            'tier_name' => $row['tier_name']
        ];
    }

    $stmt->close();

    echo json_encode(['success' => true, 'programs' => $programs]);
}

/**
 * Update tier for a specific user + program combination
 */
function updateProgramTier() {
    $conn = ai_getDBConnection();

    $userId = $_POST['user_id'] ?? '';
    $programId = $_POST['program_id'] ?? '';
    $tierCode = $_POST['tier_code'] ?? '';

    if (empty($userId) || empty($programId)) {
        echo json_encode(['success' => false, 'error' => 'User ID and Program ID are required']);
        return;
    }

    // Get tier_id from tier_code
    $tierId = null;
    if (!empty($tierCode)) {
        $sql = "SELECT tier_id FROM ai_tiers WHERE tier_code = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $tierCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $tierId = $row['tier_id'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid tier code']);
            $stmt->close();
            return;
        }
        $stmt->close();
    }

    // Check if tier assignment already exists
    $checkSql = "SELECT assignment_id FROM user_tier_assignments WHERE user_id = ? AND program_id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('ss', $userId, $programId);
    $stmt->execute();
    $checkResult = $stmt->get_result();
    $exists = $checkResult->num_rows > 0;
    $stmt->close();

    if ($exists) {
        if (empty($tierCode)) {
            // Delete the assignment
            $deleteSql = "DELETE FROM user_tier_assignments WHERE user_id = ? AND program_id = ?";
            $stmt = $conn->prepare($deleteSql);
            $stmt->bind_param('ss', $userId, $programId);
            $success = $stmt->execute();
            $stmt->close();
        } else {
            // Update existing assignment
            $updateSql = "UPDATE user_tier_assignments SET tier_id = ?, updated_at = NOW() WHERE user_id = ? AND program_id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param('iss', $tierId, $userId, $programId);
            $success = $stmt->execute();
            $stmt->close();
        }
    } else {
        if (!empty($tierCode) && $tierId) {
            // Insert new assignment
            $insertSql = "INSERT INTO user_tier_assignments (user_id, program_id, tier_id, assigned_by) VALUES (?, ?, ?, 'admin')";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param('ssi', $userId, $programId, $tierId);
            $success = $stmt->execute();
            $stmt->close();
        } else {
            $success = true; // Nothing to do
        }
    }

    if ($success) {
        aiCentral_logMessage("Program tier updated: user=$userId, program=$programId, tier=$tierCode", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

?>
