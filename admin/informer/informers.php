<?php
/**
 * ============================================================
 *  INFORMER MANAGEMENT MODULE
 * ============================================================
 *  File     : informers.php
 *  Purpose  : Manage informer records with permission-based
 *             add/edit/delete/view access control
 * ============================================================
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

/* ============================================================
   PERMISSION CODES
   ============================================================ */
define('PERM_VIEW_INFORMERS',   'VIEW_INFORMERS');
define('PERM_ADD_INFORMER',     'ADD_INFORMER');
define('PERM_EDIT_INFORMER',    'EDIT_INFORMER');
define('PERM_DELETE_INFORMER',  'DELETE_INFORMER');

$can_view   = hasPermission(PERM_VIEW_INFORMERS);
$can_add    = hasPermission(PERM_ADD_INFORMER);
$can_edit   = hasPermission(PERM_EDIT_INFORMER);
$can_delete = hasPermission(PERM_DELETE_INFORMER);

if (!$can_view) {
    die('Access Denied! You do not have permission to view informers.');
}

/* ============================================================
   ACTION 1: SOFT DELETE
   ============================================================ */
if (isset($_GET['delete'])) {
    if (!$can_delete) {
        header("Location: informers.php?status=no_permission");
        exit;
    }
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE informers SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: informers.php?status=deleted");
    exit;
}

/* ============================================================
   ACTION 2: ADD / UPDATE
   ============================================================ */
$error   = '';
$success = '';

if (isset($_POST['submit'])) {
    $id              = $_POST['id'] ?? null;
    $informer_code   = trim($_POST['informer_code']);
    $informer_name   = trim($_POST['informer_name']);
    $regiment_id     = (int)$_POST['regiment_id'];
    $category        = $_POST['category'];
    $classification  = $_POST['classification'];
    $handler_details = trim($_POST['handler_details']);
    $contact_number  = trim($_POST['contact_number']);
    $status          = $_POST['status'];

    if (!empty($id)) {
        if (!$can_edit) {
            $error = "Access Denied! You do not have permission to edit informers.";
        } else {
            $stmt = $conn->prepare(
                "UPDATE informers 
                 SET informer_code = ?, informer_name = ?, regiment_id = ?, 
                     category = ?, classification = ?, handler_details = ?, 
                     contact_number = ?, status = ? 
                 WHERE id = ?"
            );
            $stmt->bind_param(
                "ssisssssi",
                $informer_code, $informer_name, $regiment_id,
                $category, $classification, $handler_details,
                $contact_number, $status, $id
            );
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: informers.php?status=updated");
                exit;
            } else {
                $error = "Update Error: " . $conn->error;
            }
        }
    } else {
        if (!$can_add) {
            $error = "Access Denied! You do not have permission to add informers.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO informers 
                 (informer_code, informer_name, regiment_id, category, 
                  classification, handler_details, contact_number, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssisssss",
                $informer_code, $informer_name, $regiment_id,
                $category, $classification, $handler_details,
                $contact_number, $status
            );
            if ($stmt->execute()) {
                $stmt->close();
                header("Location: informers.php?status=added");
                exit;
            } else {
                $error = "Insert Error: " . $conn->error;
            }
        }
    }
}

/* ============================================================
   ACTION 3: STATUS MESSAGES
   ============================================================ */
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'updated')       $success = "Informer records updated successfully!";
    if ($_GET['status'] == 'added')         $success = "New informer registered successfully!";
    if ($_GET['status'] == 'deleted')       $success = "Informer record removed successfully!";
    if ($_GET['status'] == 'no_permission') $error   = "Access Denied! You do not have permission to perform this action.";
}

/* ============================================================
   FETCH REGIMENTS
   ============================================================ */
$regiments = [];
$res = $conn->query("SELECT id, regiment_name FROM regiments WHERE status = 'Active'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $regiments[$row['id']] = $row['regiment_name'];
    }
}

/* ============================================================
   FETCH INFORMERS
   ============================================================ */
$informers = [];
$res = $conn->query(
    "SELECT i.*, r.regiment_name 
     FROM informers i
     LEFT JOIN regiments r ON i.regiment_id = r.id
     WHERE i.is_deleted = 0
     ORDER BY i.id DESC"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $informers[] = $row;
    }
}

/* ============================================================
   EDIT MODE
   ============================================================ */
$edit = null;
if (isset($_GET['edit'])) {
    if (!$can_edit) {
        header("Location: informers.php?status=no_permission");
        exit;
    }
    $edit_id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM informers WHERE id = $edit_id AND is_deleted = 0");
    $edit = $res->fetch_assoc();
}

/* ============================================================
   HELPER — Clean cell value
   ============================================================ */
function displayCell($value, $placeholder = '—') {
    $value = trim((string)($value ?? ''));
    if ($value === '' || strtolower($value) === 'null') {
        return '<span class="cell-empty">' . $placeholder . '</span>';
    }
    return '<span class="cell-filled">' . htmlspecialchars($value) . '</span>';
}

/* ============================================================
   HELPER — Clickable Informer Code Link
   ------------------------------------------------------------
   Generates an anchor tag linking to view_informer.php?id=X
   ============================================================ */
function codeLink($id, $code) {
    $safeId   = (int)$id;
    $safeCode = htmlspecialchars($code ?? '—', ENT_QUOTES, 'UTF-8');
    return '<a href="view_informer.php?id=' . $safeId . '" class="informer-code-link" title="View informer details">' . $safeCode . '</a>';
}
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informer Management | IDCMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --accent: #3A0FD1;
            --accent-soft: rgba(67, 24, 255, 0.10);
            --text-main: #0B1437;
            --text-muted: #4A5578;
            --text-soft: #6B7594;
            --border: #D0D5E4;
            --sidebar-width: 280px;
            --placeholder-color: #8A93AD;
            --label-color: #2D3754;
            --header-color: #1E2847;
            --cell-empty-color: #9CA3B8;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #9D7BFF;
            --accent-soft: rgba(157, 123, 255, 0.16);
            --text-main: #FFFFFF;
            --text-muted: #C7D0E8;
            --text-soft: #A3AED0;
            --border: rgba(255, 255, 255, 0.16);
            --placeholder-color: #7A85A8;
            --label-color: #D6DDF0;
            --header-color: #E8EDF8;
            --cell-empty-color: #6F7B9C;
        }

        * {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            transition: 0.3s;
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.6;
            font-weight: 500;
        }

        /* ─── SIDEBAR ─── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 30px 15px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .brand-name { font-weight: 800; font-size: 1.15rem; color: var(--text-main); }
        .brand-name i { color: var(--accent); }
        .nav-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            background: transparent;
            width: 100%;
            border: none;
            text-align: left;
            cursor: pointer;
            line-height: 1.5;
        }
        .nav-btn:hover, .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .nav-btn i { width: 22px; font-size: 1.05rem; text-align: center; flex-shrink: 0; }
        .nav-section-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--text-soft);
            text-transform: uppercase;
            letter-spacing: 1.1px;
            margin: 22px 12px 8px;
        }
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn {
            display: block;
            padding: 9px 15px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 600;
            border-left: 2px solid var(--border);
            transition: 0.2s;
        }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub {
            color: var(--accent);
            border-left-color: var(--accent);
        }
        .sub-nav-btn.active-sub { font-weight: 700; }

        /* ─── MAIN ─── */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }
        .top-nav {
            position: sticky;
            top: 0;
            background: var(--bg-body);
            padding: 25px 30px;
            z-index: 90;
        }
        .top-nav h4 { font-weight: 800; color: var(--text-main); }

        /* ─── FORM ─── */
        .form-container {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 30px;
            margin: 0 30px 30px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        .form-label {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--label-color);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 12px;
            padding: 11px 15px;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .form-control::placeholder { color: var(--placeholder-color); opacity: 1; font-weight: 500; }
        .form-control:focus, .form-select:focus {
            background: var(--bg-body);
            color: var(--text-main);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        /* ─── BUTTONS ─── */
        .btn-save {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); color: white; }
        .btn-clear {
            color: var(--text-muted);
            border: none;
            background: transparent;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-clear:hover { color: var(--text-main); }

        /* ─── SECTION TITLES ─── */
        .section-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        .section-title i { margin-right: 12px; color: var(--accent); }

        /* ─── TABLE ─── */
        .preview-table-wrapper {
            border-top: 1px solid var(--border);
            margin-top: 40px;
            padding-top: 30px;
        }
        .table {
            color: var(--text-main);
            margin-bottom: 0;
            vertical-align: middle;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table thead th {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--header-color) !important;
            background: var(--bg-body) !important;
            padding: 16px 14px;
            border-bottom: 2px solid var(--border) !important;
            letter-spacing: 0.7px;
            white-space: nowrap;
            text-align: left;
            vertical-align: middle;
        }
        .table tbody td {
            font-size: 0.92rem;
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main) !important;
            font-weight: 600;
            line-height: 1.55;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover { background: var(--accent-soft) !important; }
        .table-hover tbody tr:hover td { background: transparent !important; }

        .cell-filled {
            color: var(--text-main);
            font-weight: 600;
            display: inline-block;
            line-height: 1.5;
        }
        .cell-empty {
            color: var(--cell-empty-color);
            font-weight: 500;
            font-style: italic;
            display: inline-block;
            opacity: 0.85;
        }

        .table thead th:nth-child(1) { min-width: 160px; }
        .table thead th:nth-child(2) { min-width: 150px; }
        .table thead th:nth-child(3) { min-width: 110px; }
        .table thead th:nth-child(4) { min-width: 120px; }
        .table thead th:nth-child(5) { min-width: 180px; }
        .table thead th:nth-child(6) { min-width: 90px;  }

        /* ============================================================
           INFORMER CODE — Clickable Link
           ============================================================ */
        .informer-code-link {
            color: var(--accent);
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.2px;
            line-height: 1.4;
            display: block;
            margin-bottom: 2px;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.15s ease;
        }
        .informer-code-link:hover {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 3px;
            opacity: 0.9;
        }
        .informer-code-link:active { opacity: 0.75; }
        .informer-code-link:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 4px;
        }

        .informer-name {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.83rem;
            line-height: 1.4;
            display: block;
        }

        /* ─── BADGES ─── */
        .badge-classification {
            background: var(--accent-soft);
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.8rem;
            display: inline-block;
            border: 1px solid var(--accent);
        }
        .badge-category {
            border: 1px solid var(--border);
            padding: 5px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.78rem;
            color: var(--text-main);
            display: inline-block;
            background: var(--bg-body);
        }
        .badge-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.72rem;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-status.status-active {
            background: rgba(0, 200, 100, 0.20);
            color: #009B4D;
            border: 1px solid rgba(0, 200, 100, 0.4);
        }
        [data-theme="dark"] .badge-status.status-active { color: #34E27B; }
        .badge-status.status-inactive {
            background: rgba(255, 91, 91, 0.20);
            color: #C82333;
            border: 1px solid rgba(255, 91, 91, 0.4);
        }
        [data-theme="dark"] .badge-status.status-inactive { color: #FF7A7A; }

        /* ─── ACTION BUTTONS ─── */
        .btn-edit {
            background: var(--accent-soft);
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.8rem;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-edit:hover { background: var(--accent); color: white; transform: scale(0.97); }
        .btn-delete {
            background: rgba(255, 91, 91, 0.15);
            color: #C82333;
            border: 1px solid rgba(255, 91, 91, 0.4);
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.8rem;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        [data-theme="dark"] .btn-delete { color: #FF7A7A; }
        .btn-delete:hover { background: #C82333; color: white; }

        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .stats-badge {
            background: var(--accent-soft);
            color: var(--accent);
            padding: 9px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.88rem;
        }
        .stats-badge i { margin-right: 8px; }

        /* ─── ALERTS ─── */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            font-weight: 700;
            font-size: 0.92rem;
        }
        .alert-danger { background: rgba(255, 91, 91, 0.18); color: #C82333; }
        [data-theme="dark"] .alert-danger { color: #FF7A7A; }
        .alert-success { background: rgba(0, 200, 100, 0.18); color: #009B4D; }
        [data-theme="dark"] .alert-success { color: #34E27B; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.5;
            margin-bottom: 15px;
            display: block;
            color: var(--text-soft);
        }

        /* ─── READ-ONLY NOTICE ─── */
        .readonly-notice {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .readonly-notice i { font-size: 1.3rem; flex-shrink: 0; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>
    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
        </a>
        <div class="nav-section-label">Human Intelligence</div>
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('informerSub')">
                <i class="fa-solid fa-user-secret me-2"></i> Informers
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="informerSub" class="submenu show">
                <a href="informers.php" class="sub-nav-btn active-sub">Manage Informers</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4" style="color:#FF5B5B;">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0">Informer Management</h4>
            <div class="stats-badge">
                <i class="fa-solid fa-user-secret"></i> Total Informers: <?= count($informers) ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($can_add || $can_edit): ?>
            <div class="section-title">
                <i class="fa-solid <?= $edit ? 'fa-pen-to-square' : 'fa-user-plus' ?>"></i>
                <?= $edit ? 'Update Informer Profile' : 'Register New Informer' ?>
            </div>

            <form method="post">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label">Informer Code</label>
                        <input type="text" name="informer_code" class="form-control" required
                               placeholder="e.g. INF-001"
                               value="<?= htmlspecialchars($edit['informer_code'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Informer Name</label>
                        <input type="text" name="informer_name" class="form-control" required
                               placeholder="Full name of informer"
                               value="<?= htmlspecialchars($edit['informer_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Regiment</label>
                        <select name="regiment_id" class="form-select" required>
                            <option value="">-- Select Regiment --</option>
                            <?php foreach ($regiments as $rid => $rname): ?>
                                <option value="<?= $rid ?>"
                                    <?= (isset($edit['regiment_id']) && $edit['regiment_id'] == $rid) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rname) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="Informer"       <?= (isset($edit['category']) && $edit['category'] == 'Informer')       ? 'selected' : '' ?>>Informer</option>
                            <option value="Casual Contact" <?= (isset($edit['category']) && $edit['category'] == 'Casual Contact') ? 'selected' : '' ?>>Casual Contact</option>
                            <option value="Agent"          <?= (isset($edit['category']) && $edit['category'] == 'Agent')          ? 'selected' : '' ?>>Agent</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Classification</label>
                        <select name="classification" class="form-select" required>
                            <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $c): ?>
                                <option value="<?= $c ?>"
                                    <?= (isset($edit['classification']) && $edit['classification'] == $c) ? 'selected' : '' ?>>
                                    <?= $c ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Handler Details</label>
                        <input type="text" name="handler_details" class="form-control"
                               placeholder="Assigned handler / officer name"
                               value="<?= htmlspecialchars($edit['handler_details'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control"
                               placeholder="+94 XX XXX XXXX"
                               value="<?= htmlspecialchars($edit['contact_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active"   <?= (isset($edit['status']) && $edit['status'] == 'Active')   ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= (isset($edit['status']) && $edit['status'] == 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 mt-5 text-end">
                        <?php if ($edit): ?>
                            <a href="informers.php" class="btn-clear me-3">
                                <i class="fa-solid fa-xmark me-1"></i> Cancel Edit
                            </a>
                        <?php else: ?>
                            <button type="reset" class="btn-clear me-3">
                                <i class="fa-solid fa-rotate-left me-1"></i> Clear Form
                            </button>
                        <?php endif; ?>
                        <button type="submit" name="submit" class="btn-save">
                            <i class="fa-solid <?= $edit ? 'fa-rotate' : 'fa-cloud-arrow-up' ?> me-2"></i>
                            <?= $edit ? 'Update Informer' : 'Register & Save Informer' ?>
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="readonly-notice">
                <i class="fa-solid fa-eye"></i>
                <div>
                    <strong>Read-only Access</strong> — You do not have permission to add or edit informers. Please contact your administrator.
                </div>
            </div>
        <?php endif; ?>

        <!-- ============================================================
             MASTER DATABASE TABLE
             ============================================================ -->
        <div class="preview-table-wrapper">
            <div class="section-title mb-3">
                <i class="fa-solid fa-list-check"></i> Master Database — Registered Informers
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code &amp; Name</th>
                            <th>Regiment</th>
                            <th>Classification</th>
                            <th>Category</th>
                            <th>Handler</th>
                            <th>Status</th>
                            <?php if ($can_edit || $can_delete): ?>
                                <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($informers) > 0): ?>
                            <?php foreach ($informers as $i):
                                $statusKey   = strtolower($i['status']);
                                $statusClass = ($statusKey === 'active')
                                    ? 'badge-status status-active'
                                    : 'badge-status status-inactive';
                            ?>
                                <tr>
                                    <!-- Code (clickable) & Name -->
                                    <td>
                                        <?= codeLink($i['id'], $i['informer_code']) ?>
                                        <span class="informer-name">
                                            <?= htmlspecialchars($i['informer_name'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= displayCell($i['regiment_name'] ?? null, 'N/A') ?>
                                    </td>

                                    <td>
                                        <span class="badge-classification">
                                            <?= htmlspecialchars($i['classification'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge-category">
                                            <?= htmlspecialchars($i['category'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= displayCell($i['handler_details'] ?? null, '—') ?>
                                        <?php if (!empty(trim($i['contact_number'] ?? ''))): ?>
                                            <span class="informer-name" style="margin-top:2px;">
                                                <i class="fa-solid fa-phone" style="font-size:0.7rem;"></i>
                                                <?= htmlspecialchars($i['contact_number']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="<?= $statusClass ?>">
                                            <?= htmlspecialchars($i['status'] ?? '—') ?>
                                        </span>
                                    </td>

                                    <?php if ($can_edit || $can_delete): ?>
                                        <td>
                                            <div class="action-cell">
                                                <?php if ($can_edit): ?>
                                                    <a href="?edit=<?= $i['id'] ?>" class="btn-edit" title="Edit Informer">
                                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <a href="?delete=<?= $i['id'] ?>" class="btn-delete"
                                                       onclick="return confirm('Confirm deletion of this record?')"
                                                       title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($can_edit || $can_delete) ? '7' : '6' ?>">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-user-secret"></i>
                                        <div>No informer records found in the database.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
    });
</script>

</body>
</html>