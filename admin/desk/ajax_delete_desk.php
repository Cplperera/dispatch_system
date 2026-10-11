<?php
/**
 * ============================================================
 *  AJAX DELETE ENDPOINT — Desk
 * ============================================================
 *  File     : ajax_delete_desk.php
 *  Purpose  : Handle AJAX delete/restore for desks
 *  Returns  : JSON
 *
 *  Query params:
 *    ?action=delete&id=5
 *    ?action=restore&id=5
 * ============================================================
 */

/* ============================================================
   CLEAN JSON OUTPUT — no HTML pollution
   ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ============================================================
   SHUTDOWN HANDLER — always return JSON, never HTML
   ============================================================ */
function sendJson($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Fatal server error: ' . $err['message'],
        ]);
    }
});

/* ============================================================
   OUTPUT BUFFER — discard any accidental output from includes
   ============================================================ */
ob_start();

/* ============================================================
   BOOTSTRAP
   ============================================================ */
try {
    require_once '../../includes/auth.php';
    require_once '../../config/db.php';
    checkSessionTimeout();
} catch (Throwable $t) {
    ob_end_clean();
    sendJson(['success' => false, 'error' => 'Bootstrap failed: ' . $t->getMessage()]);
}

/* Discard any output from auth.php / db.php (warnings, notices, etc.) */
ob_end_clean();

/* ============================================================
   PERMISSIONS
   ============================================================ */
define('PERM_EDIT_DESK',   'EDIT_DESK');
define('PERM_DELETE_DESK', 'DELETE_DESK');

$can_edit   = hasPermission(PERM_EDIT_DESK);
$can_delete = hasPermission(PERM_DELETE_DESK);

/* ============================================================
   READ REQUEST
   ============================================================ */
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id     = isset($_GET['id'])     ? (int)$_GET['id']       : 0;

if ($id <= 0) {
    sendJson(['success' => false, 'error' => 'Invalid desk ID.'], 400);
}

/* ============================================================
   CHECK COLUMN EXISTS
   ============================================================ */
$hasSoftDelete = false;
$colCheck = $conn->query("SHOW COLUMNS FROM desks LIKE 'is_deleted'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasSoftDelete = true;
}

if (!$hasSoftDelete) {
    sendJson([
        'success' => false,
        'error'   => 'Database column is_deleted missing. Run: ALTER TABLE desks ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;',
    ]);
}

/* ============================================================
   HELPER — Get updated stats
   ============================================================ */
function getStats($conn) {
    $stats = ['total' => 0, 'active' => 0, 'deleted' => 0, 'users' => 0];

    $r = $conn->query("SELECT 
        SUM(CASE WHEN is_deleted = 0 OR is_deleted IS NULL THEN 1 ELSE 0 END) AS a,
        SUM(CASE WHEN is_deleted = 1 THEN 1 ELSE 0 END) AS d
        FROM desks");
    if ($r) {
        $row = $r->fetch_assoc();
        $stats['active']  = (int)$row['a'];
        $stats['deleted'] = (int)$row['d'];
        $stats['total']   = $stats['active'] + $stats['deleted'];
    }

    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE is_deleted = 0 AND desk_id IS NOT NULL AND desk_id > 0");
    if ($r) $stats['users'] = (int)$r->fetch_assoc()['c'];

    return $stats;
}

/* ============================================================
   HANDLE ACTION
   ============================================================ */
switch ($action) {

    /* ─────────────────────────────────────────────────────
       DELETE (soft)
       ───────────────────────────────────────────────────── */
    case 'delete':
        if (!$can_delete) {
            sendJson(['success' => false, 'error' => 'Access Denied! DELETE_DESK permission required.'], 403);
        }

        $stmt = $conn->prepare("UPDATE desks SET is_deleted = 1 WHERE id = ?");
        if (!$stmt) {
            sendJson(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            sendJson(['success' => false, 'error' => 'Execute failed: ' . $err]);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected == 0) {
            sendJson(['success' => false, 'error' => 'Desk not found or already deleted.']);
        }

        sendJson([
            'success' => true,
            'id'      => $id,
            'message' => 'Desk moved to deleted list.',
            'stats'   => getStats($conn),
        ]);
        break;

    /* ─────────────────────────────────────────────────────
       RESTORE
       ───────────────────────────────────────────────────── */
    case 'restore':
        if (!$can_edit) {
            sendJson(['success' => false, 'error' => 'Access Denied! EDIT_DESK permission required.'], 403);
        }

        $stmt = $conn->prepare("UPDATE desks SET is_deleted = 0 WHERE id = ?");
        if (!$stmt) {
            sendJson(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            sendJson(['success' => false, 'error' => 'Execute failed: ' . $err]);
        }

        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected == 0) {
            sendJson(['success' => false, 'error' => 'Desk not found or already active.']);
        }

        sendJson([
            'success' => true,
            'id'      => $id,
            'message' => 'Desk restored successfully!',
            'stats'   => getStats($conn),
        ]);
        break;

    /* ─────────────────────────────────────────────────────
       UNKNOWN ACTION
       ───────────────────────────────────────────────────── */
    default:
        sendJson(['success' => false, 'error' => 'Invalid action. Use ?action=delete or ?action=restore'], 400);
}