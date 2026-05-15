<?php
/**
 * AI Central Admin - Lookups Management Backend
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getLookups':
            getLookups();
            break;

        case 'getLookupCategories':
            getLookupCategories();
            break;

        case 'saveLookup':
            saveLookup();
            break;

        case 'deleteLookup':
            deleteLookup();
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    aiCentral_logMessage('Lookups API Error: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => 'Internal error']);
}

/**
 * Get all lookups or filter by category
 */
function getLookups() {
    $category = $_GET['category'] ?? null;
    $lookups = aiCentral_getLookups($category);
    echo json_encode(['success' => true, 'lookups' => $lookups]);
}

/**
 * Get distinct lookup categories
 */
function getLookupCategories() {
    $conn = ai_getDBConnection();

    $sql = "SELECT DISTINCT LOOKUP_NAME as category,
            COUNT(*) as count
            FROM admin_lookups
            GROUP BY LOOKUP_NAME
            ORDER BY LOOKUP_NAME";

    $result = $conn->query($sql);
    $categories = [];

    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category' => $row['category'],
            'count' => $row['count']
        ];
    }

    echo json_encode(['success' => true, 'categories' => $categories]);
}

/**
 * Save (insert or update) a lookup
 */
function saveLookup() {
    $conn = ai_getDBConnection();

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $lookupName = trim($_POST['lookup_name'] ?? '');
    $lookupValue = trim($_POST['lookup_value'] ?? '');
    $lookupOrder = isset($_POST['lookup_order']) && $_POST['lookup_order'] !== '' ? (int)$_POST['lookup_order'] : null;
    $lookupDesc = trim($_POST['lookup_desc'] ?? '');

    // Validation
    if (empty($lookupName)) {
        echo json_encode(['success' => false, 'error' => 'Category name is required']);
        return;
    }

    if (empty($lookupValue)) {
        echo json_encode(['success' => false, 'error' => 'Lookup value is required']);
        return;
    }

    if ($id) {
        // Update existing
        $sql = "UPDATE admin_lookups SET
                LOOKUP_NAME = ?,
                LOOKUP_VALUE = ?,
                LOOKUP_ORDER = ?,
                LOOKUP_DESC = ?
                WHERE ID = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssisi', $lookupName, $lookupValue, $lookupOrder, $lookupDesc, $id);

        if ($stmt->execute()) {
            aiCentral_logMessage("Lookup updated: ID=$id, Category=$lookupName, Value=$lookupValue", 'INFO');
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            throw new Exception('Failed to update lookup: ' . $stmt->error);
        }

        $stmt->close();
    } else {
        // Insert new
        $sql = "INSERT INTO admin_lookups (LOOKUP_NAME, LOOKUP_VALUE, LOOKUP_ORDER, LOOKUP_DESC)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssis', $lookupName, $lookupValue, $lookupOrder, $lookupDesc);

        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            aiCentral_logMessage("Lookup created: ID=$newId, Category=$lookupName, Value=$lookupValue", 'INFO');
            echo json_encode(['success' => true, 'id' => $newId]);
        } else {
            throw new Exception('Failed to create lookup: ' . $stmt->error);
        }

        $stmt->close();
    }
}

/**
 * Delete a lookup
 */
function deleteLookup() {
    $conn = ai_getDBConnection();

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid lookup ID']);
        return;
    }

    $sql = "DELETE FROM admin_lookups WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        aiCentral_logMessage("Lookup deleted: ID=$id", 'INFO');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete lookup: ' . $stmt->error);
    }

    $stmt->close();
}
?>
