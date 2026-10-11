<?php
/**
 * ============================================================
 *  DESK MANAGEMENT DASHBOARD (AJAX + Permission-based)
 * ============================================================
 *  File     : desks.php
 *  Purpose  : List, add, edit, restore desks
 *             with in-place AJAX restore (no page reload)
 *
 *  Permissions Required:
 *    - VIEW_DESKS   : View desk list
 *    - ADD_DESK     : Create new desks
 *    - EDIT_DESK    : Edit existing desks + restore
 *    - DELETE_DESK  : Soft-delete desks
 * ============================================================
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

define('PERM_VIEW_DESKS',   'VIEW_DESKS');
define('PERM_ADD_DESK',     'ADD_DESK');
define('PERM_EDIT_DESK',    'EDIT_DESK');
define('PERM_DELETE_DESK',  'DELETE_DESK');

$can_view   = hasPermission(PERM_VIEW_DESKS);
$can_add    = hasPermission(PERM_ADD_DESK);
$can_edit   = hasPermission(PERM_EDIT_DESK);
$can_delete = hasPermission(PERM_DELETE_DESK);

if (!$can_view) {
    die('Access Denied! You do not have permission to view desks.');
}

$error = $success = '';

/* ============================================================
   DETECT SOFT-DELETE COLUMN
   ============================================================ */
$hasSoftDelete = false;
$checkCol = $conn->query("SHOW COLUMNS FROM desks LIKE 'is_deleted'");
if ($checkCol && $checkCol->num_rows > 0) {
    $hasSoftDelete = true;
}

/* ============================================================
   AJAX DETECTION
   ============================================================ */
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

/* ============================================================
   HELPER — Send JSON response and exit
   ============================================================ */
function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ============================================================
   HELPER — Recount stats for AJAX response
   ============================================================ */
function getStats($conn, $hasSoftDelete) {
    $stats = ['total' => 0, 'active' => 0, 'deleted' => 0, 'users' => 0];

    if ($hasSoftDelete) {
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
    } else {
        $r = $conn->query("SELECT COUNT(*) AS c FROM desks");
        if ($r) {
            $stats['active'] = (int)$r->fetch_assoc()['c'];
            $stats['total']  = $stats['active'];
        }
    }

    $r = $conn->query("SELECT COUNT(*) AS c FROM users WHERE is_deleted = 0 AND desk_id IS NOT NULL AND desk_id > 0");
    if ($r) $stats['users'] = (int)$r->fetch_assoc()['c'];

    return $stats;
}

/* ============================================================
   HANDLE SOFT DELETE (inline / AJAX)
   ============================================================ */
if (isset($_GET['delete'])) {
    if (!$can_delete) {
        if ($isAjax) jsonResponse(['success' => false, 'error' => 'Access Denied! DELETE_DESK permission required.']);
        header("Location: desks.php?status=no_permission");
        exit;
    }

    if (!$hasSoftDelete) {
        if ($isAjax) jsonResponse(['success' => false, 'error' => 'Database column is_deleted missing.']);
        header("Location: desks.php?status=column_missing");
        exit;
    }

    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare("UPDATE desks SET is_deleted = 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $deleteId);
            if ($stmt->execute()) {
                $stmt->close();

                if ($isAjax) {
                    jsonResponse([
                        'success' => true,
                        'id'      => $deleteId,
                        'message' => 'Desk moved to deleted list.',
                        'stats'   => getStats($conn, $hasSoftDelete),
                    ]);
                }

                header("Location: desks.php?status=deleted");
                exit;
            } else {
                $error = "Delete Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $error = "Delete Prepare Error: " . $conn->error;
        }
    }

    if ($isAjax) jsonResponse(['success' => false, 'error' => $error ?: 'Unknown error']);
}

/* ============================================================
   HANDLE RESTORE (inline / AJAX)
   ============================================================ */
if (isset($_GET['restore'])) {
    if (!$can_edit) {
        if ($isAjax) jsonResponse(['success' => false, 'error' => 'Access Denied! EDIT_DESK permission required.']);
        header("Location: desks.php?status=no_permission");
        exit;
    }

    $restoreId = (int)$_GET['restore'];
    if ($restoreId > 0 && $hasSoftDelete) {
        $stmt = $conn->prepare("UPDATE desks SET is_deleted = 0 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $restoreId);
            if ($stmt->execute()) {
                $stmt->close();

                if ($isAjax) {
                    jsonResponse([
                        'success' => true,
                        'id'      => $restoreId,
                        'message' => 'Desk restored successfully!',
                        'stats'   => getStats($conn, $hasSoftDelete),
                    ]);
                }

                header("Location: desks.php?status=restored");
                exit;
            } else {
                $error = "Restore Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $error = "Restore Prepare Error: " . $conn->error;
        }
    }

    if ($isAjax) jsonResponse(['success' => false, 'error' => $error ?: 'Unknown error']);
}

/* ============================================================
   HANDLE ADD DESK
   ============================================================ */
if (isset($_POST['submit'])) {
    if (!$can_add) {
        $error = "Access Denied! You do not have permission to add desks.";
    } else {
        $desk_name   = trim($_POST['desk_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = $_POST['status'] ?? 'Active';

        if ($desk_name === '') {
            $error = "Desk name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO desks (desk_name, description, status) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $desk_name, $description, $status);
                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: desks.php?status=added");
                    exit;
                } else {
                    $error = "Insert Error: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = "Prepare Error: " . $conn->error;
            }
        }
    }
}

/* ============================================================
   STATUS MESSAGES
   ============================================================ */
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'added':          $success = "Desk added successfully!"; break;
        case 'updated':        $success = "Desk updated successfully!"; break;
        case 'deleted':        $success = "Desk moved to deleted list."; break;
        case 'restored':       $success = "Desk restored successfully!"; break;
        case 'no_permission':  $error   = "Access Denied! You do not have permission to perform this action."; break;
        case 'column_missing': $error   = "⚠️ Database column missing. Run: <code>ALTER TABLE desks ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;</code>"; break;
    }
}

/* ============================================================
   FETCH ALL DESKS (Active + Deleted combined)
   ============================================================ */
$desks = [];
$sql = "SELECT d.*, 
               (SELECT COUNT(*) FROM users u 
                WHERE u.desk_id = d.id AND u.is_deleted = 0) AS user_count
        FROM desks d
        ORDER BY d.is_deleted ASC, d.id DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $desks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

/* ============================================================
   STATS
   ============================================================ */
$stats         = getStats($conn, $hasSoftDelete);
$totalDesks    = $stats['total'];
$activeCount   = $stats['active'];
$deletedCount  = $stats['deleted'];
$assignedUsers = $stats['users'];
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desk Management | IDCMS</title>
    
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-body: #F0F2F5;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --accent: #4318FF;
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #0B1437;
            --text-muted: #4A5578;
            --border: #E2E6F0;
            --error: #EE5D50;
            --success: #00C864;
            --warning: #F59E0B;
            --sidebar-width: 260px;
            --card-radius: 16px;
            --shadow: 0 4px 24px rgba(112, 144, 176, 0.08);
            --shadow-hover: 0 8px 32px rgba(112, 144, 176, 0.14);
        }
        [data-theme="dark"] {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #7551FF;
            --accent-soft: rgba(117, 81, 255, 0.14);
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --border: rgba(255, 255, 255, 0.08);
            --error: #EE5D50;
            --success: #00C864;
            --warning: #F59E0B;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
            --shadow-hover: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Noto Sans Sinhala', 'Plus Jakarta Sans', 'Iskoola Pota', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
            line-height: 1.6;
            font-size: 0.92rem;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 26px 14px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .brand-box { display:flex; align-items:center; gap:10px; padding:4px 12px 22px; margin-bottom:4px; }
        .brand-name { font-weight:800; font-size:1.1rem; color:var(--text-main); letter-spacing:-0.3px; }
        .brand-name i { color: var(--accent); font-size: 1.3rem; }
        .sidebar-divider {
            font-size: 0.66rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1.2px;
            margin: 22px 12px 8px; opacity: 0.85;
        }
        .nav-btn {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; color: var(--text-muted); text-decoration: none;
            border-radius: 10px; font-weight: 600; font-size: 0.88rem;
            transition: 0.18s ease; margin-bottom: 3px;
            background: transparent; width: 100%; border: none;
            text-align: left; cursor: pointer; font-family: inherit;
            line-height: 1.3;
        }
        .nav-btn i:first-child { font-size: 0.95rem; width: 18px; text-align: center; flex-shrink: 0; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .submenu { padding-left: 22px; margin-left: 12px; display: none; border-left: 2px solid var(--border); padding-top: 4px; padding-bottom: 4px; }
        .submenu.show { display: block; }
        .sub-nav-btn {
            display: block; padding: 8px 12px; color: var(--text-muted);
            text-decoration: none; font-size: 0.84rem; font-weight: 600;
            border-radius: 8px; transition: 0.15s; margin-bottom: 1px;
        }
        .sub-nav-btn:hover { background: var(--accent-soft); color: var(--accent); }
        .sub-nav-btn.active-sub { color: var(--accent); font-weight: 700; }
        .logout-btn { margin-top: auto; color: #FF5B5B !important; font-weight: 700; }
        .logout-btn:hover { background: rgba(255, 91, 91, 0.1) !important; color: #FF5B5B !important; }

        /* ─── MAIN ─── */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 24px 32px 40px;
            display: flex;
            flex-direction: column;
        }

        .top-nav {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 22px; padding: 4px 0;
            gap: 16px; flex-wrap: wrap;
        }
        .top-nav h4 { font-weight: 800; font-size: 1.35rem; color: var(--text-main); margin: 0; letter-spacing: -0.4px; }
        .top-nav .subtitle { color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-top: 3px; }

        .btn-add-toggle {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 22px;
            background: var(--accent); color: #fff;
            border: none; border-radius: 12px;
            font-weight: 800; font-size: 0.85rem;
            text-decoration: none; transition: 0.2s;
            letter-spacing: 0.2px; cursor: pointer;
            font-family: inherit;
        }
        .btn-add-toggle:hover {
            opacity: 0.92; transform: translateY(-2px);
            color: #fff; box-shadow: 0 8px 20px var(--accent-soft);
        }
        .btn-add-toggle.active i.chev { transform: rotate(180deg); }
        .btn-add-toggle i.chev { transition: transform 0.25s; font-size: 0.75rem; }

        /* ─── STAT CARDS ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: var(--card-radius); padding: 18px 20px;
            display: flex; align-items: center; gap: 14px;
            transition: 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            border-color: var(--accent);
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: #fff; flex-shrink: 0;
        }
        .stat-icon.purple { background: linear-gradient(135deg, #7551FF, #4318FF); }
        .stat-icon.green  { background: linear-gradient(135deg, #10B981, #059669); }
        .stat-icon.red    { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .stat-icon.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-info { display: flex; flex-direction: column; }
        .stat-info .num { font-size: 1.6rem; font-weight: 800; color: var(--text-main); line-height: 1.1; transition: color 0.3s; }
        .stat-info .lbl {
            font-size: 0.68rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.8px; margin-top: 3px;
        }

        /* ─── ADD FORM CARD ─── */
        .add-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-left: 4px solid var(--accent);
            border-radius: var(--card-radius);
            padding: 26px 28px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
            display: none;
            animation: slideDown 0.3s ease;
        }
        .add-card.open { display: block; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .add-card-heading {
            display: flex; align-items: center; gap: 12px;
            font-size: 1.05rem; font-weight: 800; color: var(--text-main);
            margin-bottom: 20px; padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
            letter-spacing: -0.2px;
        }
        .add-card-heading i { color: var(--accent); font-size: 1rem; }

        .add-form-row {
            display: grid;
            grid-template-columns: 1.2fr 1.5fr 0.8fr auto;
            gap: 16px;
            align-items: end;
        }
        .form-field { display: flex; flex-direction: column; }
        .form-label {
            font-size: 0.7rem; font-weight: 800; color: var(--text-muted);
            margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.7px;
        }
        .form-label .req { color: var(--error); margin-left: 2px; }
        .form-control, .form-select {
            background: var(--bg-body); border: 1px solid var(--border);
            color: var(--text-main); border-radius: 12px;
            padding: 11px 16px; font-size: 0.9rem;
            font-weight: 500; transition: 0.18s;
            font-family: inherit; width: 100%; outline: none;
        }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.55; font-weight: 500; }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .form-actions { display: flex; gap: 10px; }
        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: var(--accent); color: #fff; border: none;
            padding: 11px 24px; border-radius: 12px;
            font-weight: 800; font-size: 0.88rem;
            cursor: pointer; transition: 0.2s;
            font-family: inherit; letter-spacing: 0.2px;
            white-space: nowrap;
        }
        .btn-primary:hover { opacity: 0.92; transform: translateY(-2px); color: #fff; }
        .btn-cancel {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            background: transparent; color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 11px 20px; border-radius: 12px;
            font-weight: 700; font-size: 0.88rem;
            cursor: pointer; transition: 0.2s;
            font-family: inherit; white-space: nowrap;
        }
        .btn-cancel:hover { color: var(--text-main); border-color: var(--text-muted); background: var(--accent-soft); }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            overflow: hidden;
        }
        .desk-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .desk-table thead th {
            text-align: left; padding: 14px 16px;
            color: var(--text-muted); font-size: 0.68rem;
            font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.8px; border-bottom: 1px solid var(--border);
            background: var(--bg-body); white-space: nowrap;
        }
        .desk-table tbody td {
            padding: 14px 16px; color: var(--text-main);
            font-weight: 600; border-bottom: 1px solid var(--border);
            vertical-align: middle; line-height: 1.5;
        }
        .desk-table tbody tr:last-child td { border-bottom: none; }
        .desk-table tbody tr {
            transition: background 0.15s, opacity 0.4s, transform 0.4s;
        }
        .desk-table tbody tr:hover { background: var(--accent-soft); }

        /* Row removal animation */
        .desk-table tbody tr.removing {
            opacity: 0;
            transform: translateX(-30px);
            background: rgba(0, 200, 100, 0.1);
        }

        .desk-cell { display: flex; align-items: center; gap: 12px; }
        .desk-avatar {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: #fff; flex-shrink: 0;
        }
        .av-purple { background: linear-gradient(135deg, #7551FF, #4318FF); }
        .av-green  { background: linear-gradient(135deg, #10B981, #059669); }
        .av-orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .av-blue   { background: linear-gradient(135deg, #3B82F6, #1D4ED8); }
        .av-pink   { background: linear-gradient(135deg, #EC4899, #BE185D); }
        .av-red    { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .av-cyan   { background: linear-gradient(135deg, #06B6D4, #0891B2); }
        .av-teal   { background: linear-gradient(135deg, #14B8A6, #0F766E); }

        .desk-info { display: flex; flex-direction: column; line-height: 1.35; }
        .desk-info .name { font-weight: 800; color: var(--text-main); font-size: 0.92rem; }
        .desk-info .id-tag {
            font-size: 0.72rem; color: var(--text-muted);
            font-weight: 700; font-family: 'JetBrains Mono', 'Courier New', monospace;
        }
        .desc-text { color: var(--text-muted); font-weight: 500; font-size: 0.85rem; }

        .user-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 8px;
            background: var(--accent-soft); color: var(--accent);
            font-size: 0.78rem; font-weight: 800; white-space: nowrap;
        }
        .user-badge i { font-size: 0.72rem; }
        .user-badge.empty { background: rgba(163, 174, 208, 0.12); color: var(--text-muted); }

        .status-badge {
            display: inline-block; padding: 5px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.4px; white-space: nowrap;
        }
        .status-active {
            background: rgba(0, 200, 100, 0.15); color: #00B85C;
            border: 1px solid rgba(0, 200, 100, 0.3);
        }
        [data-theme="dark"] .status-active { color: #34E27B; }
        .status-inactive {
            background: rgba(238, 93, 80, 0.12); color: #DC3545;
            border: 1px solid rgba(238, 93, 80, 0.25);
        }
        [data-theme="dark"] .status-inactive { color: #FF7A7A; }

        .deleted-row td { opacity: 0.75; }
        .deleted-row .desk-info .name {
            text-decoration: line-through;
            text-decoration-color: var(--error);
            text-decoration-thickness: 2px;
        }

        .actions-cell { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }
        .btn-icon {
            width: 34px; height: 34px; border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: 0.18s;
            border: 1px solid transparent; font-size: 0.85rem;
            cursor: pointer; background: transparent; font-family: inherit;
            position: relative;
        }
        .btn-icon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-icon-edit { color: var(--accent); background: var(--accent-soft); border-color: var(--accent-soft); }
        .btn-icon-edit:hover { background: var(--accent); color: #fff; transform: translateY(-2px); }
        .btn-icon-delete { color: var(--error); background: rgba(238, 93, 80, 0.1); border-color: rgba(238, 93, 80, 0.15); }
        .btn-icon-delete:hover { background: var(--error); color: #fff; transform: translateY(-2px); }
        .btn-icon-restore { color: var(--success); background: rgba(0, 200, 100, 0.12); border-color: rgba(0, 200, 100, 0.2); }
        .btn-icon-restore:hover { background: var(--success); color: #fff; transform: translateY(-2px); }

        /* Loading spinner inside button */
        .btn-icon .spinner {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        .btn-icon.loading .icon-normal { display: none; }
        .btn-icon.loading .spinner { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i {
            font-size: 2.5rem; opacity: 0.35; color: var(--accent);
            display: block; margin-bottom: 14px;
        }
        .empty-state .empty-title { font-weight: 800; color: var(--text-main); font-size: 1rem; margin-bottom: 6px; }
        .empty-state .empty-sub { font-weight: 500; font-size: 0.85rem; }
        .empty-state a {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 14px; padding: 9px 20px;
            background: var(--accent); color: #fff;
            border-radius: 10px; font-weight: 800; font-size: 0.85rem;
            text-decoration: none; transition: 0.2s;
        }
        .empty-state a:hover { opacity: 0.92; transform: translateY(-2px); color: #fff; }

        .alert {
            border-radius: 12px; padding: 14px 18px;
            font-weight: 700; font-size: 0.9rem;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
            border: none;
        }
        .alert-danger  { background: rgba(238, 93, 80, 0.12); color: var(--error); }
        .alert-success { background: rgba(0, 200, 100, 0.12); color: var(--success); }

        /* ─── TOAST NOTIFICATIONS ─── */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-left: 4px solid var(--success);
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--text-main);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 280px;
            pointer-events: auto;
            animation: toastIn 0.3s ease;
        }
        .toast.toast-error { border-left-color: var(--error); }
        .toast.toast-success { border-left-color: var(--success); }
        .toast .toast-icon {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; flex-shrink: 0;
        }
        .toast.toast-success .toast-icon { background: rgba(0, 200, 100, 0.15); color: var(--success); }
        .toast.toast-error   .toast-icon { background: rgba(238, 93, 80, 0.15); color: var(--error); }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(40px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateX(0); }
            to   { opacity: 0; transform: translateX(40px); }
        }
        .toast.removing { animation: toastOut 0.3s ease forwards; }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .add-form-row { grid-template-columns: 1fr 1fr; }
            .add-form-row .form-actions { grid-column: 1 / -1; justify-content: flex-end; }
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 20px 16px; }
            .top-nav h4 { font-size: 1.15rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .add-form-row { grid-template-columns: 1fr; }
            .desk-table { font-size: 0.8rem; }
            .desk-table thead th, .desk-table tbody td { padding: 10px 12px; }
            .toast-container { left: 16px; right: 16px; top: 16px; }
            .toast { min-width: 0; }
        }
        @media (max-width: 500px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>

    <nav style="display:flex; flex-direction:column; flex:1;">
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>

        <div class="sidebar-divider">System Admin</div>
        <div>
            <a href="../users/users.php" class="nav-btn">
                <i class="fa-solid fa-users-gear"></i> User Management
            </a>
            <button class="nav-btn active" onclick="toggleSubmenu('deskSub')" type="button">
                <i class="fa-solid fa-desktop"></i> Desk Settings
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="deskSub" class="submenu show">
                <a href="desks.php" class="sub-nav-btn active-sub">View Desks</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">

    <header class="top-nav">
        <div>
            <h4>Desk Management Dashboard</h4>
            <p class="subtitle">Overview of all system desks and their assignments</p>
        </div>

        <?php if ($can_add): ?>
            <button type="button" class="btn-add-toggle" id="toggleAddBtn" onclick="toggleAddForm()">
                <i class="fa-solid fa-plus"></i>
                <span id="toggleAddText">Add New Desk</span>
                <i class="fa-solid fa-chevron-down chev"></i>
            </button>
        <?php endif; ?>
    </header>

    <!-- Server-side alerts (fallback) -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= $error ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($can_add): ?>
        <div class="add-card" id="addCard">
            <div class="add-card-heading">
                <i class="fa-solid fa-plus-circle"></i>
                Create New Desk
            </div>

            <form method="post" action="desks.php" autocomplete="off">
                <div class="add-form-row">
                    <div class="form-field">
                        <label class="form-label">Desk Name <span class="req">*</span></label>
                        <input type="text" name="desk_name" class="form-control"
                               placeholder="Enter desk name..." required maxlength="100">
                    </div>

                    <div class="form-field">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control"
                               placeholder="Enter description..." maxlength="255">
                    </div>

                    <div class="form-field">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" selected>Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="closeAddForm()">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </button>
                        <button type="submit" name="submit" class="btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Desk
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-desktop"></i></div>
            <div class="stat-info">
                <span class="num" id="statTotal"><?= $totalDesks ?></span>
                <span class="lbl">Total Desks</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-info">
                <span class="num" id="statActive"><?= $activeCount ?></span>
                <span class="lbl">Active Desks</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fa-solid fa-trash"></i></div>
            <div class="stat-info">
                <span class="num" id="statDeleted"><?= $deletedCount ?></span>
                <span class="lbl">Deleted Desks</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fa-solid fa-user-tie"></i></div>
            <div class="stat-info">
                <span class="num" id="statUsers"><?= $assignedUsers ?></span>
                <span class="lbl">Assigned Users</span>
            </div>
        </div>
    </div>

    <!-- DESK TABLE -->
    <div class="table-card">
        <div style="overflow-x:auto;">
            <table class="desk-table" id="deskTable">
                <thead>
                    <tr>
                        <th style="min-width:220px;">Desk</th>
                        <th style="min-width:280px;">Description</th>
                        <th>Assigned Users</th>
                        <th>Status</th>
                        <?php if ($can_edit || $can_delete): ?>
                            <th style="text-align:right;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($desks)): ?>
                        <?php
                        $palette = ['av-purple', 'av-green', 'av-orange', 'av-blue', 'av-pink', 'av-red', 'av-cyan', 'av-teal'];
                        $i = 0;
                        foreach ($desks as $d):
                            $avClass = $palette[$i % count($palette)];
                            $i++;
                            $statusKey = strtolower($d['status'] ?? 'inactive');
                            $statusClass = $statusKey === 'active' ? 'status-active' : 'status-inactive';
                            $rowDeleted = $hasSoftDelete && isset($d['is_deleted']) && (int)$d['is_deleted'] === 1;
                        ?>
                            <tr data-desk-id="<?= (int)$d['id'] ?>" class="<?= $rowDeleted ? 'deleted-row' : '' ?>">
                                <td>
                                    <div class="desk-cell">
                                        <div class="desk-avatar <?= $avClass ?>">
                                            <i class="fa-solid fa-desktop"></i>
                                        </div>
                                        <div class="desk-info">
                                            <span class="name"><?= htmlspecialchars($d['desk_name'] ?? '—') ?></span>
                                            <span class="id-tag">#<?= (int)$d['id'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="desc-text">
                                        <?= htmlspecialchars($d['description'] ?: '—') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$d['user_count'] > 0): ?>
                                        <span class="user-badge">
                                            <i class="fa-solid fa-users"></i>
                                            <?= (int)$d['user_count'] ?> user<?= (int)$d['user_count'] !== 1 ? 's' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="user-badge empty">
                                            <i class="fa-solid fa-user-slash"></i>
                                            No users
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($d['status'] ?? 'Inactive') ?>
                                    </span>
                                </td>

                                <?php if ($can_edit || $can_delete): ?>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if (!$rowDeleted): ?>
                                                <!-- ═══ ACTIVE ROW ═══ -->
                                                <?php if ($can_edit): ?>
                                                    <a href="edit_desk.php?id=<?= (int)$d['id'] ?>"
                                                       class="btn-icon btn-icon-edit" title="Edit Desk">
                                                        <i class="fa-solid fa-pen-to-square icon-normal"></i>
                                                        <span class="spinner"></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button type="button"
                                                            class="btn-icon btn-icon-delete js-delete-btn"
                                                            data-id="<?= (int)$d['id'] ?>"
                                                            data-name="<?= htmlspecialchars($d['desk_name'] ?? '', ENT_QUOTES) ?>"
                                                            title="Soft Delete Desk">
                                                        <i class="fa-solid fa-trash icon-normal"></i>
                                                        <span class="spinner"></span>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- ═══ DELETED ROW ═══ -->
                                                <?php if ($can_edit): ?>
                                                    <button type="button"
                                                            class="btn-icon btn-icon-restore js-restore-btn"
                                                            data-id="<?= (int)$d['id'] ?>"
                                                            data-name="<?= htmlspecialchars($d['desk_name'] ?? '', ENT_QUOTES) ?>"
                                                            title="Restore Desk">
                                                        <i class="fa-solid fa-rotate-left icon-normal"></i>
                                                        <span class="spinner"></span>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="<?= ($can_edit || $can_delete) ? '5' : '4' ?>">
                                <div class="empty-state">
                                    <i class="fa-solid fa-desktop"></i>
                                    <div class="empty-title">No desks yet</div>
                                    <div class="empty-sub">Create your first desk to get started</div>
                                    <?php if ($can_add): ?>
                                        <a href="javascript:void(0)" onclick="toggleAddForm(true)">
                                            <i class="fa-solid fa-plus"></i> Add First Desk
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    /* ─── THEME ─── */
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    /* ─── SIDEBAR SUBMENU ─── */
    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        if (!submenu) return;
        const icon = submenu.previousElementSibling
            ? submenu.previousElementSibling.querySelector('.fa-chevron-down')
            : null;

        if (submenu.style.display === "block" || submenu.classList.contains('show')) {
            submenu.style.display = "none";
            submenu.classList.remove('show');
            if (icon) icon.style.transform = "rotate(0deg)";
        } else {
            submenu.style.display = "block";
            submenu.classList.add('show');
            if (icon) icon.style.transform = "rotate(180deg)";
        }
    }

    /* ─── INLINE ADD FORM TOGGLE ─── */
    function toggleAddForm(forceOpen = false) {
        const card = document.getElementById('addCard');
        if (!card) return;

        const btn   = document.getElementById('toggleAddBtn');
        const label = document.getElementById('toggleAddText');
        const isOpen = card.classList.contains('open');
        const shouldOpen = forceOpen === true || !isOpen;

        if (shouldOpen) {
            card.classList.add('open');
            if (btn) btn.classList.add('active');
            if (label) label.textContent = 'Close Form';
            setTimeout(() => {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const nameInput = card.querySelector('input[name="desk_name"]');
                if (nameInput) nameInput.focus();
            }, 100);
        } else {
            closeAddForm();
        }
    }

    function closeAddForm() {
        const card = document.getElementById('addCard');
        if (!card) return;

        const btn   = document.getElementById('toggleAddBtn');
        const label = document.getElementById('toggleAddText');

        card.classList.remove('open');
        if (btn) btn.classList.remove('active');
        if (label) label.textContent = 'Add New Desk';

        card.querySelectorAll('input[type="text"], select').forEach(el => {
            if (el.name === 'status') el.value = 'Active';
            else if (el.name === 'desk_name' || el.name === 'description') el.value = '';
        });
    }

    /* ============================================================
       TOAST NOTIFICATION
       ============================================================ */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML =
            '<div class="toast-icon">' +
                '<i class="fa-solid ' + (type === 'success' ? 'fa-check' : 'fa-exclamation') + '"></i>' +
            '</div>' +
            '<div style="flex:1;">' + message + '</div>';

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    /* ============================================================
       UPDATE STATS
       ============================================================ */
    function updateStats(stats) {
        if (!stats) return;

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = val;
                el.style.color = 'var(--accent)';
                setTimeout(() => el.style.color = '', 600);
            }
        };

        setVal('statTotal',   stats.total);
        setVal('statActive',  stats.active);
        setVal('statDeleted', stats.deleted);
        setVal('statUsers',   stats.users);
    }

    /* ============================================================
       AJAX DELETE
       ============================================================ */
    async function doDelete(btn) {
        const id   = btn.dataset.id;
        const name = btn.dataset.name || 'this desk';

        if (!confirm('Move desk "' + name + '" to deleted list?')) return;

        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const url = 'ajax_delete_desk.php?action=delete&id=' + encodeURIComponent(id);
            const resp = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const rawText = await resp.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                showToast('Server returned invalid response.', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
                return;
            }

            if (data.success) {
                // Replace the row with a deleted version (soft-delete visual)
                const row = btn.closest('tr');
                if (row) {
                    row.classList.add('deleted-row');
                    row.querySelector('td:last-child').innerHTML = 
                        '<div class="actions-cell">' +
                            '<button type="button" class="btn-icon btn-icon-restore js-restore-btn" ' +
                                    'data-id="' + id + '" ' +
                                    'data-name="' + (name.replace(/"/g, '&quot;')) + '" ' +
                                    'title="Restore Desk">' +
                                '<i class="fa-solid fa-rotate-left icon-normal"></i>' +
                                '<span class="spinner"></span>' +
                            '</button>' +
                        '</div>';
                }

                updateStats(data.stats);
                showToast(data.message || 'Desk deleted successfully.', 'success');
            } else {
                showToast(data.error || 'Delete failed.', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        } catch (err) {
            showToast('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    /* ============================================================
       AJAX RESTORE
       ============================================================ */
    async function doRestore(btn) {
        const id   = btn.dataset.id;
        const name = btn.dataset.name || 'this desk';

        if (!confirm('Restore desk "' + name + '"?')) return;

        btn.disabled = true;
        btn.classList.add('loading');

        try {
            const url = 'ajax_delete_desk.php?action=restore&id=' + encodeURIComponent(id);
            const resp = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const rawText = await resp.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                showToast('Server returned invalid response.', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
                return;
            }

            if (data.success) {
                // Replace the row with an active version
                const row = btn.closest('tr');
                if (row) {
                    row.classList.remove('deleted-row');
                    row.querySelector('td:last-child').innerHTML = 
                        '<div class="actions-cell">' +
                            '<?php if ($can_edit): ?>' +
                                '<a href="edit_desk.php?id=' + id + '" class="btn-icon btn-icon-edit" title="Edit Desk">' +
                                    '<i class="fa-solid fa-pen-to-square icon-normal"></i>' +
                                    '<span class="spinner"></span>' +
                                '</a>' +
                            '<?php endif; ?>' +
                            '<?php if ($can_delete): ?>' +
                                '<button type="button" class="btn-icon btn-icon-delete js-delete-btn" ' +
                                        'data-id="' + id + '" ' +
                                        'data-name="' + (name.replace(/"/g, '&quot;')) + '" ' +
                                        'title="Soft Delete Desk">' +
                                    '<i class="fa-solid fa-trash icon-normal"></i>' +
                                    '<span class="spinner"></span>' +
                                '</button>' +
                            '<?php endif; ?>' +
                        '</div>';
                }

                updateStats(data.stats);
                showToast(data.message || 'Desk restored successfully.', 'success');
            } else {
                showToast(data.error || 'Restore failed.', 'error');
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        } catch (err) {
            showToast('Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.classList.remove('loading');
        }
    }

    /* ============================================================
       EVENT DELEGATION — Delete + Restore
       ============================================================ */
    document.addEventListener('click', function (e) {
        const delBtn = e.target.closest('.js-delete-btn');
        if (delBtn) {
            e.preventDefault();
            doDelete(delBtn);
            return;
        }

        const resBtn = e.target.closest('.js-restore-btn');
        if (resBtn) {
            e.preventDefault();
            doRestore(resBtn);
            return;
        }
    });

    /* ─── AUTO-CLOSE ALERTS ─── */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
</script>

</body>
</html>