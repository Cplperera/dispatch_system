<?php
/**
 * ============================================================
 *  LETTER CATEGORIES MANAGEMENT MODULE
 * ============================================================
 *  File     : category.php
 *  Purpose  : Manage letter categories with permission-based
 *             add / edit / delete / restore / view access control
 *
 *  Filters  : Active (is_deleted = 0) | Deleted (is_deleted = 1)
 *
 *  Permissions Required:
 *    - VIEW_CATEGORIES    : View category list
 *    - ADD_CATEGORY       : Create new categories
 *    - EDIT_CATEGORY      : Edit existing categories + restore
 *    - DELETE_CATEGORY    : Delete categories
 * ============================================================
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

/* ============================================================
   PERMISSION CODES CONFIGURATION
   ============================================================ */
define('PERM_VIEW_CATEGORIES',   'VIEW_CATEGORIES');
define('PERM_ADD_CATEGORY',     'ADD_CATEGORY');
define('PERM_EDIT_CATEGORY',    'EDIT_CATEGORY');
define('PERM_DELETE_CATEGORY',  'DELETE_CATEGORY');

/* ============================================================
   CHECK USER PERMISSIONS
   ============================================================ */
$can_view   = hasPermission(PERM_VIEW_CATEGORIES);
$can_add    = hasPermission(PERM_ADD_CATEGORY);
$can_edit   = hasPermission(PERM_EDIT_CATEGORY);
$can_delete = hasPermission(PERM_DELETE_CATEGORY);

/* ============================================================
   ACCESS CONTROL
   ============================================================ */
if (!$can_view) {
    die('Access Denied! You do not have permission to view categories.');
}

$error   = '';
$success = '';
$edit    = null;

/* ============================================================
   FILTER: ACTIVE or DELETED
   ------------------------------------------------------------
   $view = 'active'  → show is_deleted = 0  (default)
   $view = 'deleted' → show is_deleted = 1
   ============================================================ */
$view = isset($_GET['view']) && $_GET['view'] === 'deleted' ? 'deleted' : 'active';

/* ============================================================
   DETECT SOFT-DELETE COLUMN
   ------------------------------------------------------------
   If the column doesn't exist, fall back to Active-only view.
   ============================================================ */
$hasSoftDelete = false;
$checkCol = $conn->query("SHOW COLUMNS FROM letter_categories LIKE 'is_deleted'");
if ($checkCol && $checkCol->num_rows > 0) {
    $hasSoftDelete = true;
}
if (!$hasSoftDelete) {
    $view = 'active'; // fallback — no deleted list available
}

/* ============================================================
   ACTION 1: HANDLE DELETE (soft)
   ============================================================ */
if (isset($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: category.php?status=no_permission&view=" . $view);
        exit;
    }

    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
if (!$hasSoftDelete) {
    // Column missing — refuse delete with helpful message
    header("Location: category.php?status=column_missing&view=" . $view);
    exit;
}

$stmt = $conn->prepare("UPDATE letter_categories SET is_deleted = 1 WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param("i", $deleteId);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: category.php?status=deleted&view=" . $view);
                exit;
            } else {
                $error = "Delete Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $error = "Delete Prepare Error: " . $conn->error;
        }
    } else {
        $error = "Invalid category ID.";
    }
}

/* ============================================================
   ACTION 2: HANDLE RESTORE (is_deleted = 0)
   ------------------------------------------------------------
   Requires EDIT_CATEGORY permission (same as edit).
   ============================================================ */
if (isset($_GET['restore'])) {
    if (!$can_edit) {
        header("Location: category.php?status=no_permission&view=deleted");
        exit;
    }

    $restoreId = (int)$_GET['restore'];
    if ($restoreId > 0 && $hasSoftDelete) {
        $stmt = $conn->prepare("UPDATE letter_categories SET is_deleted = 0 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $restoreId);
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: category.php?status=restored&view=deleted");
                exit;
            } else {
                $error = "Restore Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $error = "Restore Prepare Error: " . $conn->error;
        }
    } else {
        $error = "Invalid category ID for restore.";
    }
}

/* ============================================================
   ACTION 3: HANDLE ADD / UPDATE FORM SUBMISSION
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $category = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $formView = $_POST['view'] ?? 'active';

    /* ── UPDATE MODE ── */
    if ($id > 0) {
        if (!$can_edit) {
            $error = "Access Denied! You do not have permission to edit categories.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE letter_categories 
                 SET category = ?, description = ? 
                 WHERE id = ?"
            );
            if ($stmt) {
                $stmt->bind_param("ssi", $category, $desc, $id);
                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: category.php?status=updated&view=" . $formView);
                    exit;
                } else {
                    $error = "Update Error: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = "Update Prepare Error: " . $conn->error;
            }
        }
    }
    /* ── INSERT MODE ── */
    else {
        if (!$can_add) {
            $error = "Access Denied! You do not have permission to add categories.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO letter_categories (category, description) VALUES (?, ?)"
            );
            if ($stmt) {
                $stmt->bind_param("ss", $category, $desc);
                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: category.php?status=added&view=active");
                    exit;
                } else {
                    $error = "Insert Error: " . $stmt->error;
                    $stmt->close();
                }
            } else {
                $error = "Insert Prepare Error: " . $conn->error;
            }
        }
    }
}

/* ============================================================
   ACTION 4: DISPLAY STATUS MESSAGES
   ============================================================ */
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'added':         $success = "Category added successfully!"; break;
        case 'updated':       $success = "Category updated successfully!"; break;
        case 'deleted':       $success = "Category moved to deleted list."; break;
        case 'restored':      $success = "Category restored successfully!"; break;
        case 'no_permission': $error   = "Access Denied! You do not have permission to perform this action."; break;
    }
}

/* ============================================================
   ACTION 5: LOAD EDIT DATA
   ============================================================ */
if (isset($_GET['edit'])) {
    if (!$can_edit) {
        header("Location: category.php?status=no_permission&view=" . $view);
        exit;
    }

    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare(
            "SELECT id, category, description 
             FROM letter_categories 
             WHERE id = ?"
        );
        if ($stmt) {
            $stmt->bind_param("i", $editId);
            $stmt->execute();
            $edit = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$edit) {
                $error = "Category not found.";
            }
        } else {
            $error = "Edit Prepare Error: " . $conn->error;
        }
    }
}

/* ============================================================
   FETCH COUNTS FOR TABS
   ============================================================ */
$activeCount  = 0;
$deletedCount = 0;

if ($hasSoftDelete) {
    $c1 = $conn->query("SELECT COUNT(*) AS c FROM letter_categories WHERE is_deleted = 0 OR is_deleted IS NULL");
    if ($c1) $activeCount = (int)$c1->fetch_assoc()['c'];

    $c2 = $conn->query("SELECT COUNT(*) AS c FROM letter_categories WHERE is_deleted = 1");
    if ($c2) $deletedCount = (int)$c2->fetch_assoc()['c'];
} else {
    $c1 = $conn->query("SELECT COUNT(*) AS c FROM letter_categories");
    if ($c1) $activeCount = (int)$c1->fetch_assoc()['c'];
}

/* ============================================================
   FETCH CATEGORIES (filtered)
   ============================================================ */
$sql = "SELECT id, category, description";
if ($hasSoftDelete) {
    $sql .= ", is_deleted";
}
$sql .= " FROM letter_categories";

if ($hasSoftDelete) {
    if ($view === 'deleted') {
        $sql .= " WHERE is_deleted = 1";
    } else {
        $sql .= " WHERE is_deleted = 0 OR is_deleted IS NULL";
    }
}
$sql .= " ORDER BY id DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | IDCMS</title>

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
            --card-radius: 18px;
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
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
        }
        .brand-box { display: flex; align-items: center; gap: 10px; padding: 4px 12px 22px; margin-bottom: 4px; }
        .brand-name { font-weight: 800; font-size: 1.1rem; color: var(--text-main); letter-spacing: -0.3px; }
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

        .top-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding: 4px 0; }
        .top-nav h4 { font-weight: 800; font-size: 1.35rem; color: var(--text-main); margin: 0; letter-spacing: -0.4px; }
        .top-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px; background: var(--accent-soft); color: var(--accent);
            border-radius: 20px; font-weight: 800; font-size: 0.78rem; letter-spacing: 0.3px;
        }

        /* ─── CARDS ─── */
        .content-card {
            background: var(--card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--border);
            padding: 28px 30px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            transition: box-shadow 0.2s;
        }
        .content-card:hover { box-shadow: var(--shadow-hover); }
        .content-card.edit-mode {
            border-left: 4px solid var(--accent);
            background: linear-gradient(135deg, var(--card-bg) 0%, var(--accent-soft) 200%);
        }

        .card-heading {
            display: flex; align-items: center; gap: 12px;
            font-size: 1.15rem; font-weight: 800; color: var(--text-main);
            margin-bottom: 22px; letter-spacing: -0.2px;
        }
        .card-heading i { color: var(--accent); font-size: 1.05rem; }
        .edit-badge {
            margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px;
            background: var(--accent); color: #fff;
            font-size: 0.7rem; font-weight: 800; letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ─── TABS (Active / Deleted) ─── */
        .view-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 22px;
            padding: 4px;
            background: var(--bg-body);
            border-radius: 12px;
            border: 1px solid var(--border);
            width: fit-content;
            max-width: 100%;
        }
        .view-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 9px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: 0.18s ease;
            white-space: nowrap;
            cursor: pointer;
            border: none;
            background: transparent;
            font-family: inherit;
        }
        .view-tab:hover {
            color: var(--accent);
            background: var(--accent-soft);
        }
        .view-tab.active {
            background: var(--card-bg);
            color: var(--accent);
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        [data-theme="dark"] .view-tab.active {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 12px rgba(117, 81, 255, 0.35);
        }
        .view-tab .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 22px;
            padding: 0 7px;
            border-radius: 11px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.72rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
        }
        .view-tab.active .count-badge {
            background: rgba(255, 255, 255, 0.2);
            color: inherit;
        }
        [data-theme="dark"] .view-tab.active .count-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #fff;
        }
        .view-tab.deleted-tab.active {
            background: rgba(238, 93, 80, 0.1);
            color: var(--error);
        }
        [data-theme="dark"] .view-tab.deleted-tab.active {
            background: var(--error);
            color: #fff;
        }

        /* ─── FORM ─── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px; }
        .form-field { display: flex; flex-direction: column; }
        .form-field label {
            font-size: 0.72rem; font-weight: 800; color: var(--text-muted);
            margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.6px;
        }
        .form-field input {
            width: 100%; background: var(--bg-body); border: 1px solid var(--border);
            padding: 12px 16px; border-radius: 12px; color: var(--text-main);
            font-size: 0.9rem; font-weight: 500; outline: none;
            transition: 0.18s; font-family: inherit;
        }
        .form-field input::placeholder { color: var(--text-muted); opacity: 0.6; }
        .form-field input:focus {
            border-color: var(--accent);
            background: var(--bg-body);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        /* ─── BUTTONS ─── */
        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: var(--accent); color: #fff; border: none;
            padding: 12px 28px; border-radius: 12px;
            font-weight: 800; font-size: 0.88rem;
            cursor: pointer; transition: 0.2s; font-family: inherit;
            letter-spacing: 0.2px; text-decoration: none;
        }
        .btn-primary:hover {
            opacity: 0.92; transform: translateY(-2px);
            color: #fff; box-shadow: 0 8px 20px var(--accent-soft);
        }
        .btn-cancel {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            background: transparent; color: var(--text-muted);
            border: 1px solid var(--border); padding: 12px 24px;
            border-radius: 12px; font-weight: 700; font-size: 0.88rem;
            cursor: pointer; transition: 0.2s; font-family: inherit; text-decoration: none;
        }
        .btn-cancel:hover {
            color: var(--text-main);
            border-color: var(--text-muted);
            background: var(--accent-soft);
        }

        /* ─── TABLE ─── */
        .table-wrap { overflow-x: auto; border-radius: 12px; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .data-table thead th {
            text-align: left; padding: 14px 16px;
            color: var(--text-muted); font-size: 0.72rem;
            font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.7px; border-bottom: 1px solid var(--border);
            background: transparent; white-space: nowrap;
        }
        .data-table tbody td {
            padding: 14px 16px; font-size: 0.9rem; font-weight: 600;
            color: var(--text-main); border-bottom: 1px solid var(--border);
            vertical-align: middle; line-height: 1.5;
        }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover td { background: var(--accent-soft); }

        /* Deleted rows — muted appearance */
        .data-table tbody tr.deleted-row td {
            opacity: 0.75;
        }
        .data-table tbody tr.deleted-row strong {
            text-decoration: line-through;
            text-decoration-color: var(--error);
            text-decoration-thickness: 2px;
        }

        .data-table tbody tr.editing-row td {
            background: var(--accent-soft);
            border-left: 3px solid var(--accent);
        }

        .id-badge {
            display: inline-block; background: var(--accent-soft); color: var(--accent);
            padding: 4px 12px; border-radius: 20px;
            font-size: 0.76rem; font-weight: 800;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
        }
        .deleted-row .id-badge {
            background: rgba(238, 93, 80, 0.12);
            color: var(--error);
        }

        /* ─── ACTION BUTTONS ─── */
        .actions { display: flex; gap: 6px; justify-content: flex-end; }
        .btn-icon {
            width: 34px; height: 34px; border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; transition: 0.18s;
            border: 1px solid transparent; font-size: 0.85rem;
            cursor: pointer; background: transparent; font-family: inherit;
        }
        .btn-edit {
            color: var(--accent); background: var(--accent-soft);
            border-color: var(--accent-soft);
        }
        .btn-edit:hover { background: var(--accent); color: #fff; transform: translateY(-2px); }
        .btn-delete {
            color: var(--error); background: rgba(238, 93, 80, 0.1);
            border-color: rgba(238, 93, 80, 0.15);
        }
        .btn-delete:hover { background: var(--error); color: #fff; transform: translateY(-2px); }
        .btn-restore {
            color: var(--success); background: rgba(0, 200, 100, 0.12);
            border-color: rgba(0, 200, 100, 0.2);
        }
        .btn-restore:hover { background: var(--success); color: #fff; transform: translateY(-2px); }

        /* ─── ALERTS ─── */
        .alert {
            border-radius: 12px; border: none; padding: 14px 18px;
            font-weight: 700; font-size: 0.9rem; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-danger  { background: rgba(238, 93, 80, 0.12); color: var(--error); }
        .alert-success { background: rgba(0, 200, 100, 0.12); color: var(--success); }

        /* ─── READ-ONLY NOTICE ─── */
        .readonly-notice {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px; border-radius: 12px;
            background: var(--accent-soft); color: var(--accent);
            font-weight: 700; font-size: 0.88rem; line-height: 1.5;
        }
        .readonly-notice i { font-size: 1.3rem; flex-shrink: 0; }

        /* ─── EMPTY STATE ─── */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i {
            font-size: 2.5rem; opacity: 0.35;
            margin-bottom: 14px; display: block; color: var(--accent);
        }
        .empty-state.deleted i { color: var(--error); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; padding: 20px 16px; }
            .form-row { grid-template-columns: 1fr; }
            .top-nav h4 { font-size: 1.15rem; }
            .view-tabs { width: 100%; }
            .view-tab { flex: 1; justify-content: center; padding: 10px 12px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR
     ═══════════════════════════════════════════ -->
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

        <div class="sidebar-divider">Dispatching</div>
        <div>
            <button class="nav-btn" onclick="toggleSubmenu('internalSub')" type="button">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="internalSub" class="submenu">
                <a href="../../letters/inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../../letters/inward/internal_letters.php" class="sub-nav-btn">View Internal</a>
                <a href="../../letters/inward/inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

       

        <div class="sidebar-divider">Administration</div>
        <div>
            <button class="nav-btn active" onclick="toggleSubmenu('categorySub')" type="button">
                <i class="fa-solid fa-tags"></i> Categories
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="categorySub" class="submenu show">
                <a href="category.php" class="sub-nav-btn active-sub">Manage Categories</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<!-- ═══════════════════════════════════════════
     MAIN CONTENT
     ═══════════════════════════════════════════ -->
<main class="main-content">

    <header class="top-nav">
        <h4>Letter Categories</h4>
        <div class="top-badge">
            <i class="fa-solid fa-shield-halved"></i> SECURE ACCESS
        </div>
    </header>

    <!-- ===== ALERTS ===== -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         CREATE / EDIT FORM (only in active view)
         ============================================================ -->
    <?php if ($view === 'active' && ($can_add || $can_edit)): ?>
        <div class="content-card <?= $edit ? 'edit-mode' : '' ?>">
            <div class="card-heading">
                <i class="fa-solid <?= $edit ? 'fa-pen-to-square' : 'fa-plus-circle' ?>"></i>
                <?= $edit ? 'Edit Category' : 'Create New Category' ?>

                <?php if ($edit): ?>
                    <span class="edit-badge">
                        <i class="fa-solid fa-pen"></i> Editing #<?= (int)$edit['id'] ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($edit && !$can_edit): ?>
                <div class="readonly-notice">
                    <i class="fa-solid fa-lock"></i>
                    <div><strong>Read-only</strong> — You do not have permission to edit categories.</div>
                </div>
            <?php elseif (!$edit && !$can_add): ?>
                <div class="readonly-notice">
                    <i class="fa-solid fa-lock"></i>
                    <div><strong>Read-only</strong> — You do not have permission to add categories.</div>
                </div>
            <?php else: ?>
                <form method="post" action="category.php">
                    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : '' ?>">
                    <input type="hidden" name="view" value="active">

                    <div class="form-row">
                        <div class="form-field">
                            <label>Category Name *</label>
                            <input type="text" name="category" required
                                   placeholder="Finance, Admin, etc."
                                   value="<?= htmlspecialchars($edit['category'] ?? '') ?>">
                        </div>
                        <div class="form-field">
                            <label>Description</label>
                            <input type="text" name="description"
                                   placeholder="Brief details about this category..."
                                   value="<?= htmlspecialchars($edit['description'] ?? '') ?>">
                        </div>
                    </div>

                    <div style="text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                        <?php if ($edit): ?>
                            <a href="category.php" class="btn-cancel">
                                <i class="fa-solid fa-xmark"></i> Cancel
                            </a>
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-rotate"></i> Update Category
                            </button>
                        <?php else: ?>
                            <button type="reset" class="btn-cancel">
                                <i class="fa-solid fa-rotate-left"></i> Clear Form
                            </button>
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Save Category
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         CATEGORIES TABLE CARD
         ============================================================ -->
    <div class="content-card">
        <div class="card-heading">
            <i class="fa-solid fa-list-check"></i>
            <?= $view === 'deleted' ? 'Deleted Categories' : 'Active Categories' ?>
        </div>

        <!-- ===== ACTIVE / DELETED TABS ===== -->
        <?php if ($hasSoftDelete): ?>
            <div class="view-tabs">
                <a href="category.php?view=active" class="view-tab <?= $view === 'active' ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-check"></i>
                    Active
                    <span class="count-badge"><?= $activeCount ?></span>
                </a>
                <a href="category.php?view=deleted" class="view-tab deleted-tab <?= $view === 'deleted' ? 'active' : '' ?>">
                    <i class="fa-solid fa-trash"></i>
                    Deleted
                    <span class="count-badge"><?= $deletedCount ?></span>
                </a>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <?php if (($can_edit && $view === 'active') || $can_delete || ($can_edit && $view === 'deleted')): ?>
                            <th style="width: 120px; text-align: right;">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $isEditingThis = $edit && (int)$edit['id'] === (int)$row['id'];
                            $rowDeleted    = $hasSoftDelete && isset($row['is_deleted']) && (int)$row['is_deleted'] === 1;
                        ?>
                            <tr class="<?= $isEditingThis ? 'editing-row' : '' ?> <?= $rowDeleted ? 'deleted-row' : '' ?>">
                                <td>
                                    <span class="id-badge">#<?= $row['id'] ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['category']) ?></strong>
                                </td>
                                <td style="color: var(--text-muted); font-weight: 500;">
                                    <?= htmlspecialchars($row['description'] ?: '—') ?>
                                </td>

                                <?php if ($view === 'active'): ?>
                                    <!-- ═══ ACTIVE VIEW — Edit + Delete ═══ -->
                                    <?php if ($can_edit || $can_delete): ?>
                                        <td>
                                            <div class="actions">
                                                <?php if ($can_edit): ?>
                                                    <?php if ($isEditingThis): ?>
                                                        <a href="category.php"
                                                           class="btn-icon btn-edit"
                                                           title="Cancel editing"
                                                           style="background: var(--accent); color:#fff;">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="category.php?edit=<?= (int)$row['id'] ?>"
                                                           class="btn-icon btn-edit"
                                                           title="Edit Category">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if ($can_delete): ?>
                                                    <a href="category.php?delete=<?= (int)$row['id'] ?>"
                                                       class="btn-icon btn-delete"
                                                       title="Delete Category"
                                                       onclick="return confirm('Move category &quot;<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>&quot; to deleted list?');">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <!-- ═══ DELETED VIEW — Restore ═══ -->
                                    <?php if ($can_edit): ?>
                                        <td>
                                            <div class="actions">
                                                <a href="category.php?restore=<?= (int)$row['id'] ?>"
                                                   class="btn-icon btn-restore"
                                                   title="Restore Category"
                                                   onclick="return confirm('Restore category &quot;<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>&quot;?');">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                </a>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">
                                <?php if ($view === 'deleted'): ?>
                                    <div class="empty-state deleted">
                                        <i class="fa-solid fa-trash"></i>
                                        <div>No deleted categories.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-tags"></i>
                                        <div>No active categories found.</div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

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

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });

        <?php if ($edit): ?>
            const categoryInput = document.querySelector('input[name="category"]');
            if (categoryInput) {
                categoryInput.focus();
                categoryInput.setSelectionRange(categoryInput.value.length, categoryInput.value.length);
                document.querySelector('.content-card.edit-mode')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        <?php endif; ?>
    });
</script>

</body>
</html>