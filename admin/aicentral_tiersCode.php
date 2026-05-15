<?php
/**
 * AI Central Admin Tiers - Backend API
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

aiCentral_logMessage("Tiers API: action=$action", 'INFO');

try {
    switch ($action) {
        case 'getTiers':
            getTiers();
            break;

        case 'getTierCapabilities':
            getTierCapabilities();
            break;

        case 'saveTier':
            saveTier();
            break;

        case 'deleteTier':
            deleteTier();
            break;

        case 'toggleTierStatus':
            toggleTierStatus();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage("Tiers API error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all tiers
 */
function getTiers() {
    $conn = ai_getDBConnection();

    $sql = "SELECT *
            FROM ai_tiers
            ORDER BY sort_order, tier_name";

    $result = $conn->query($sql);

    $tiers = [];
    while ($row = $result->fetch_assoc()) {
        $tiers[] = [
            'tier_id' => (int)$row['tier_id'],
            'tier_code' => $row['tier_code'],
            'tier_name' => $row['tier_name'],
            'tier_description' => $row['tier_description'],
            'daily_request_limit' => $row['daily_request_limit'] ? (int)$row['daily_request_limit'] : null,
            'monthly_request_limit' => $row['monthly_request_limit'] ? (int)$row['monthly_request_limit'] : null,
            'daily_token_limit' => $row['daily_token_limit'] ? (int)$row['daily_token_limit'] : null,
            'monthly_token_limit' => $row['monthly_token_limit'] ? (int)$row['monthly_token_limit'] : null,
            'monthly_spend_limit_usd' => $row['monthly_spend_limit_usd'] ? (float)$row['monthly_spend_limit_usd'] : null,
            'is_active' => (bool)$row['is_active'],
            'sort_order' => (int)$row['sort_order']
        ];
    }

    echo json_encode(['success' => true, 'tiers' => $tiers]);
}

/**
 * Get tier capabilities
 */
function getTierCapabilities() {
    $conn = ai_getDBConnection();

    $tierId = $_REQUEST['tier_id'] ?? 0;

    if (!$tierId) {
        echo json_encode(['success' => false, 'error' => 'Tier ID required']);
        return;
    }

    $sql = "SELECT tier_capability_id, tier_id, capability_code, is_enabled, max_uses
            FROM ai_tier_capabilities
            WHERE tier_id = ?
            ORDER BY capability_code";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tierId);
    $stmt->execute();
    $result = $stmt->get_result();

    $capabilities = [];
    while ($row = $result->fetch_assoc()) {
        $capabilities[] = [
            'tier_capability_id' => (int)$row['tier_capability_id'],
            'tier_id' => (int)$row['tier_id'],
            'capability_code' => $row['capability_code'],
            'is_enabled' => (bool)$row['is_enabled'],
            'max_uses' => $row['max_uses'] ? (int)$row['max_uses'] : null
        ];
    }

    $stmt->close();

    echo json_encode(['success' => true, 'capabilities' => $capabilities]);
}

/**
 * Save tier (add or update)
 */
function saveTier() {
    $conn = ai_getDBConnection();

    $tierId = $_POST['tier_id'] ?? null;
    $tierCode = trim($_POST['tier_code'] ?? '');
    $tierName = trim($_POST['tier_name'] ?? '');
    $tierDescription = trim($_POST['tier_description'] ?? '');
    $dailyRequestLimit = $_POST['daily_request_limit'] ?? null;
    $monthlyRequestLimit = $_POST['monthly_request_limit'] ?? null;
    $dailyTokenLimit = $_POST['daily_token_limit'] ?? null;
    $monthlyTokenLimit = $_POST['monthly_token_limit'] ?? null;
    $monthlySpendLimit = $_POST['monthly_spend_limit_usd'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $sortOrder = (int)($_POST['sort_order'] ?? 100);
    $capabilitiesJson = $_POST['capabilities'] ?? '[]';

    // Validation
    if (empty($tierCode) || empty($tierName)) {
        echo json_encode(['success' => false, 'error' => 'Tier code and tier name are required']);
        return;
    }

    // Validate capabilities is valid JSON
    $capabilitiesArray = json_decode($capabilitiesJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid capabilities format']);
        return;
    }

    // Validate capability data
    $validCodes = ['web_search', 'web_fetch', 'vision'];
    foreach ($capabilitiesArray as $cap) {
        if (!isset($cap['capability_code']) || !in_array($cap['capability_code'], $validCodes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid capability code']);
            return;
        }
        if (isset($cap['max_uses']) && $cap['max_uses'] !== null) {
            $maxUses = (int)$cap['max_uses'];
            if ($maxUses < 1 || $maxUses > 1000) {
                echo json_encode(['success' => false, 'error' => 'Max uses must be between 1 and 1000']);
                return;
            }
        }
    }

    // Convert empty strings to NULL
    $tierDescriptionValue = empty($tierDescription) ? NULL : $tierDescription;
    $dailyRequestLimitValue = ($dailyRequestLimit === '' || $dailyRequestLimit === null) ? NULL : (int)$dailyRequestLimit;
    $monthlyRequestLimitValue = ($monthlyRequestLimit === '' || $monthlyRequestLimit === null) ? NULL : (int)$monthlyRequestLimit;
    $dailyTokenLimitValue = ($dailyTokenLimit === '' || $dailyTokenLimit === null) ? NULL : (int)$dailyTokenLimit;
    $monthlyTokenLimitValue = ($monthlyTokenLimit === '' || $monthlyTokenLimit === null) ? NULL : (int)$monthlyTokenLimit;
    $monthlySpendLimitValue = ($monthlySpendLimit === '' || $monthlySpendLimit === null) ? NULL : (float)$monthlySpendLimit;

    if ($tierId) {
        // Update existing tier
        $sql = "UPDATE ai_tiers SET
                tier_code = ?, tier_name = ?, tier_description = ?,
                daily_request_limit = ?, monthly_request_limit = ?,
                daily_token_limit = ?, monthly_token_limit = ?,
                monthly_spend_limit_usd = ?, is_active = ?, sort_order = ?,
                updated_at = NOW()
                WHERE tier_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssiiiiidii',
            $tierCode, $tierName, $tierDescriptionValue,
            $dailyRequestLimitValue, $monthlyRequestLimitValue,
            $dailyTokenLimitValue, $monthlyTokenLimitValue,
            $monthlySpendLimitValue, $isActive, $sortOrder,
            $tierId
        );
    } else {
        // Check for duplicate tier_code
        $sql = "SELECT tier_id FROM ai_tiers WHERE tier_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $tierCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Tier code already exists']);
            $stmt->close();
            return;
        }
        $stmt->close();

        // Insert new tier
        $sql = "INSERT INTO ai_tiers (
                tier_code, tier_name, tier_description,
                daily_request_limit, monthly_request_limit,
                daily_token_limit, monthly_token_limit,
                monthly_spend_limit_usd, is_active, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssiiiiidi',
            $tierCode, $tierName, $tierDescriptionValue,
            $dailyRequestLimitValue, $monthlyRequestLimitValue,
            $dailyTokenLimitValue, $monthlyTokenLimitValue,
            $monthlySpendLimitValue, $isActive, $sortOrder
        );
    }

    if ($stmt->execute()) {
        $id = $tierId ?: $conn->insert_id;
        aiCentral_logMessage("Tier saved: ID=$id, Code=$tierCode", 'INFO');

        // Save capabilities
        foreach ($capabilitiesArray as $cap) {
            $capCode = $cap['capability_code'];
            $isEnabled = (int)($cap['is_enabled'] ?? 0);
            $maxUses = ($cap['max_uses'] === '' || $cap['max_uses'] === null) ? NULL : (int)$cap['max_uses'];

            // Check if capability already exists for this tier
            $sql = "SELECT tier_capability_id FROM ai_tier_capabilities
                    WHERE tier_id = ? AND capability_code = ?";
            $checkStmt = $conn->prepare($sql);
            $checkStmt->bind_param('is', $id, $capCode);
            $checkStmt->execute();
            $result = $checkStmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing capability
                $sql = "UPDATE ai_tier_capabilities
                        SET is_enabled = ?, max_uses = ?, updated_at = NOW()
                        WHERE tier_id = ? AND capability_code = ?";
                $capStmt = $conn->prepare($sql);
                $capStmt->bind_param('iiis', $isEnabled, $maxUses, $id, $capCode);
            } else {
                // Insert new capability
                $sql = "INSERT INTO ai_tier_capabilities
                        (tier_id, capability_code, is_enabled, max_uses)
                        VALUES (?, ?, ?, ?)";
                $capStmt = $conn->prepare($sql);
                $capStmt->bind_param('isii', $id, $capCode, $isEnabled, $maxUses);
            }

            $capStmt->execute();
            $capStmt->close();
            $checkStmt->close();
        }

        echo json_encode(['success' => true, 'tier_id' => $id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Delete tier
 */
function deleteTier() {
    $conn = ai_getDBConnection();

    $tierId = $_POST['tier_id'] ?? 0;

    if (!$tierId) {
        echo json_encode(['success' => false, 'error' => 'Tier ID required']);
        return;
    }

    // Check if tier is assigned to users
    // First get the tier_code for this tier_id
    $sql = "SELECT tier_code FROM ai_tiers WHERE tier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tierId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Tier not found']);
        $stmt->close();
        return;
    }
    $row = $result->fetch_assoc();
    $tierCode = $row['tier_code'];
    $stmt->close();

    // Now check if this tier_code is assigned to any users
    $sql = "SELECT COUNT(*) as count FROM users WHERE default_ai_tier = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $tierCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete tier - it is assigned to ' . $row['count'] . ' user(s). Deactivate instead.']);
        return;
    }

    $sql = "DELETE FROM ai_tiers WHERE tier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tierId);

    if ($stmt->execute()) {
        aiCentral_logMessage("Tier deleted: ID=$tierId", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

/**
 * Toggle tier active status
 */
function toggleTierStatus() {
    $conn = ai_getDBConnection();

    $tierId = $_POST['tier_id'] ?? 0;

    if (!$tierId) {
        echo json_encode(['success' => false, 'error' => 'Tier ID required']);
        return;
    }

    $sql = "UPDATE ai_tiers SET is_active = NOT is_active, updated_at = NOW() WHERE tier_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tierId);

    if ($stmt->execute()) {
        // Get new status
        $sql = "SELECT is_active FROM ai_tiers WHERE tier_id = ?";
        $stmt2 = $conn->prepare($sql);
        $stmt2->bind_param('i', $tierId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $row = $result->fetch_assoc();
        $newStatus = (bool)$row['is_active'];
        $stmt2->close();

        aiCentral_logMessage("Tier status toggled: ID=$tierId, Active=$newStatus", 'INFO');
        echo json_encode(['success' => true, 'is_active' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }

    $stmt->close();
}

?>
