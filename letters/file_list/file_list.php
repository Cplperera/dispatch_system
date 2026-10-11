<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// 1. Check if user has permission to view the file list
if (!hasPermission('VIEW_FILE_RECORDS')) {
    die('Access Denied');
}

$canDelete = hasPermission('DELETE_FILE_RECORD'); 
$canEdit = hasPermission('EDIT_FILE_RECORD'); 

// ==========================================
// AJAX ENDPOINT: FETCH SINGLE RECORD FOR EDIT
// ==========================================
if (isset($_GET['get_record'])) {
    header('Content-Type: application/json');
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $stmt = $conn->prepare("SELECT * FROM physical_file_records WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Record not found']);
    }
    exit;
}

// ==========================================
// AJAX ENDPOINT: UPDATE RECORD
// ==========================================
if (isset($_POST['update_record'])) {
    header('Content-Type: application/json');
    
    if (!$canEdit) {
        echo json_encode(['success' => false, 'message' => 'Access Denied']);
        exit;
    }

    $id = intval($_POST['id']);
    $file_reference = trim($_POST['file_reference']);
    $file_name      = trim($_POST['file_name']);
    $volume         = trim($_POST['volume']);
    $rack_no        = trim($_POST['rack_no']);
    $row_no         = trim($_POST['row_no']);
    $about_file     = trim($_POST['about_file']);
    $desk_id        = !empty($_POST['desk_id']) ? (int)$_POST['desk_id'] : null;
    $status         = trim($_POST['status']);
    
    $editor_id      = $_SESSION['user_id']; 

    if (empty($file_reference) || empty($volume) || empty($rack_no) || empty($row_no)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    $updateStmt = $conn->prepare("
        UPDATE physical_file_records 
        SET file_reference = ?, file_name = ?, volume = ?, rack_no = ?, row_no = ?, about_file = ?, desk_id = ?, status = ?, 
            edited_by = ?, edited_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $updateStmt->bind_param("ssssssisii", $file_reference, $file_name, $volume, $rack_no, $row_no, $about_file, $desk_id, $status, $editor_id, $id);

    if ($updateStmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

// AJAX endpoint for DataTables
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    try {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search_value']) ? trim($_GET['search_value']) : '';
        $orderColumn = isset($_GET['order'][0]['column']) ? intval($_GET['order'][0]['column']) : 0;
        $orderDir = isset($_GET['order'][0]['dir']) ? $_GET['order'][0]['dir'] : 'desc';

        $filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $filter_desk = isset($_GET['desk']) ? intval($_GET['desk']) : 0;
        $filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        $filter_my_files = isset($_GET['my_files']) ? intval($_GET['my_files']) : 0;

        $columns = [
            0 => 'file_reference', 1 => 'file_name', 2 => 'volume', 3 => 'rack_no', 4 => 'row_no',
            5 => 'desk_name', 6 => 'f.status', 7 => 'username', 8 => 'f.created_at', 9 => 'u2.username', 10 => 'f.id'
        ];
        $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'f.created_at';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $baseQuery = "
            FROM physical_file_records f
            LEFT JOIN users u ON f.created_by = u.id
            LEFT JOIN users u2 ON f.edited_by = u2.id
            LEFT JOIN desks d ON f.desk_id = d.id
            WHERE f.is_deleted = 0
        ";
        
        $params = [];
        $types = '';

        if (!empty($search)) {
            $baseQuery .= " AND (f.file_reference LIKE ? OR f.file_name LIKE ? OR f.volume LIKE ? OR f.rack_no LIKE ? OR f.row_no LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= 'sssss';
        }
        if (!empty($filter_status)) { $baseQuery .= " AND f.status = ?"; $params[] = $filter_status; $types .= 's'; }
        if (!empty($filter_desk)) { $baseQuery .= " AND f.desk_id = ?"; $params[] = $filter_desk; $types .= 'i'; }
        if ($filter_my_files && isset($_SESSION['user_id'])) { $baseQuery .= " AND f.created_by = ?"; $params[] = $_SESSION['user_id']; $types .= 'i'; }
        
        if (!empty($filter_date_from) && !empty($filter_date_to)) {
            $baseQuery .= " AND DATE(f.created_at) BETWEEN ? AND ?";
            $params[] = $filter_date_from; $params[] = $filter_date_to; $types .= 'ss';
        } elseif (!empty($filter_date_from)) {
            $baseQuery .= " AND DATE(f.created_at) >= ?"; $params[] = $filter_date_from; $types .= 's';
        } elseif (!empty($filter_date_to)) {
            $baseQuery .= " AND DATE(f.created_at) <= ?"; $params[] = $filter_date_to; $types .= 's';
        }

        $totalStmt = $conn->query("SELECT COUNT(*) as cnt FROM physical_file_records WHERE is_deleted = 0");
        $totalRecords = $totalStmt->fetch_assoc()['cnt'];

        $countQuery = "SELECT COUNT(*) as cnt " . $baseQuery;
        $countStmt = $conn->prepare($countQuery);
        if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $filteredRecords = $countStmt->get_result()->fetch_assoc()['cnt'];

        $dataQuery = "
            SELECT f.id, f.file_reference, f.file_name, f.volume, f.rack_no, f.row_no, f.status, f.created_at, f.edited_at,
                   u.username, u2.username as editor_name, d.desk_name
            " . $baseQuery . "
            ORDER BY $orderBy $orderDir
            LIMIT ? OFFSET ?
        ";
        $dataStmt = $conn->prepare($dataQuery);
        $params[] = $length; $params[] = $start; $types .= 'ii';
        $dataStmt->bind_param($types, ...$params);
        $dataStmt->execute();
        $data = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $data]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Fetch desks for dropdown
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE status = 'Active' AND is_deleted = 0 ORDER BY desk_name");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-File Records | IDCMS</title>
    
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
            color-scheme: dark; 
        }
        body {
            background-color: var(--bg-body); color: var(--text-main);
            display: flex; min-height: 100vh; font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', "Segoe UI", sans-serif; transition: 0.3s;
        }
        .sidebar {
            width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border);
            padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100;
        }
        .brand-name { font-weight: 700; font-size: 1.2rem; color: var(--accent); }
        .nav-btn {
            display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted);
            text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
            transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; cursor: pointer;
        }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn {
            display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none;
            font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border);
        }
        .sub-nav-btn:hover, .sub-nav-btn.active { color: var(--accent); border-left-color: var(--accent); }
        .main-content {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            display: flex; flex-direction: column; padding: 30px;
        }
        .card-box {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: 20px; padding: 25px; margin-bottom: 20px;
        }
        .table { color: var(--text-main); border-color: var(--border); }
        .table thead th {
            border-bottom: 2px solid var(--border); color: var(--text-muted);
            text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px;
        }
        .table td, .table th { border-top: 1px solid var(--border); vertical-align: middle; }
        .badge-active { background: #10b98120; color: #10b981; padding: 4px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        .badge-archived { background: #ef444420; color: #ef4444; padding: 4px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        
        .btn-primary { background: var(--accent); border: none; border-radius: 12px; padding: 8px 20px; font-weight: 600; transition: 0.2s; }
        .btn-primary:hover { background: var(--accent); opacity: 0.9; transform: translateY(-1px); }
        .btn-outline-secondary { border-color: var(--border); color: var(--text-muted); border-radius: 10px; }
        .btn-outline-secondary:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

        /* === CUSTOM EXCEL BUTTON STYLE === */
        .btn-excel {
            background-color: #107c41;
            border: 1px solid #107c41;
            color: #ffffff;
            border-radius: 8px;
            padding: 6px 16px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-excel:hover {
            background-color: #0b5e31;
            border-color: #0b5e31;
            color: #ffffff;
            transform: translateY(-1px);
        }
        .btn-excel i { color: #ffffff; }

        .success-message {
            background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px; padding: 12px 20px; color: #10b981; margin-bottom: 20px;
        }
        .filter-bar { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .action-btns .btn { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 6px; }

        /* === DATATABLES TOOLBAR CUSTOM STYLES === */
        .length-wrapper select {
            background-color: #1a1f3a;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 4px 10px;
            width: auto;
            display: inline-block;
            margin-left: 5px;
            font-size: 0.8rem;
        }
        .length-wrapper { font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; }
        .excel-btn-wrapper { margin-right: 15px; }

        /* === DARK MODE CONTRAST ISSUES === */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #1a1f3a !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.15);
        }
        [data-theme="dark"] .form-control::placeholder {
            color: #8898aa !important;
            opacity: 1;
        }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #1a1f3a !important;
            color: #ffffff !important;
            border-color: var(--accent);
        }
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        [data-theme="dark"] .form-check-label {
            color: #cbd5e0 !important;
        }
        [data-theme="dark"] .label {
            color: #cbd5e0 !important;
            opacity: 0.9;
        }

        /* === MODAL DARK MODE FIXES === */
        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            color: var(--text-main);
        }
        .modal-header { border-bottom: 1px solid var(--border); }
        .modal-footer { border-top: 1px solid var(--border); }
        .modal-title { color: var(--text-main); }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        
        .label { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS</div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">E-FILES OPERATIONS</div>
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('recordRoomSub')">
                <i class="fa-solid fa-folder-open me-2"></i> Record Room
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="recordRoomSub" class="submenu show">
                <a href="add_file.php" class="sub-nav-btn">Add File</a>
                <a href="file_list.php" class="sub-nav-btn active">File List</a>
                <a href="issue_file.php" class="sub-nav-btn">Issue & Returned File</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">E-File Records</h2>
                <p class="--text-muted small">Manage and track file locations</p>
            </div>
            <!-- Excel button is now injected dynamically into the DataTables toolbar -->
            <div id="exportExcelBtnSource" style="display:none;">
                <button class="btn btn-excel" id="exportExcelBtn">
                    <i class="fa-solid fa-file-excel me-2"></i>Excel
                </button>
            </div>
        </div>

        <div class="success-message" id="successMessage" style="display:none;">
            <i class="fa-regular fa-circle-check me-2"></i>File record updated successfully!
        </div>

        <div class="filter-bar">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input type="text" id="searchBox" class="form-control form-control-sm" placeholder="File ref, name, volume, rack...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select id="statusFilter" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="ACTIVE">Active</option>
                        <option value="ARCHIVED">Archived</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desk</label>
                    <select id="deskFilter" class="form-select form-select-sm">
                        <option value="">All Desks</option>
                        <?php while($desk = $desks->fetch_assoc()): ?>
                            <option value="<?= $desk['id'] ?>"><?= htmlspecialchars($desk['desk_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Created Date Range</label>
                    <div class="input-group input-group-sm">
                        <input type="date" id="dateFrom" class="form-control" placeholder="From">
                        <input type="date" id="dateTo" class="form-control" placeholder="To">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="myFilesFilter">
                        <label class="form-check-label small" for="myFilesFilter">My Files Only</label>
                    </div>
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button id="applyFilters" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <button id="resetFilters" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fa-solid fa-rotate-left"></i></button>
                </div>
            </div>
        </div>

        <div class="card-box">
            <div class="table-responsive">
                <table id="fileTable" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>File Reference</th>
                            <th>File Name</th>
                            <th>Volume</th>
                            <th>Rack No</th>
                            <th>Row No</th>
                            <th>Desk</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created Date</th>
                            <th>Edited By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- EDIT MODAL POPUP -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit File Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="label">File Reference *</div>
                            <input type="text" name="file_reference" id="edit_file_reference" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">File Name</div>
                            <input type="text" name="file_name" id="edit_file_name" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">Volume *</div>
                            <input type="text" name="volume" id="edit_volume" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">Rack No *</div>
                            <input type="text" name="rack_no" id="edit_rack_no" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">Row No *</div>
                            <input type="text" name="row_no" id="edit_row_no" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">Desk (Optional)</div>
                            <select name="desk_id" id="edit_desk_id" class="form-select">
                                <option value="">Select Desk</option>
                                <?php 
                                // Reset pointer and fetch desks again for the modal
                                $desks->data_seek(0);
                                while ($desk = $desks->fetch_assoc()): ?>
                                    <option value="<?= $desk['id'] ?>"><?= htmlspecialchars($desk['desk_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="label">Status</div>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="ACTIVE">Active</option>
                                <option value="ARCHIVED">Archived</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="label">About File (Optional)</div>
                            <textarea name="about_file" id="edit_about_file" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEditBtn"><i class="fa-regular fa-floppy-disk me-2"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap5.min.js"></script>

<script>
    const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
    const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;

    $(document).ready(function() {
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        let table;

        function initTable() {
            table = $('#fileTable').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                // Custom DOM: Moves 'i' (info) and 'l' (length) to a top toolbar
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"--text-muted small"i><"d-flex align-items-center gap-2"<"excel-btn-wrapper"><"length-wrapper"l>>>rtp',
                ajax: {
                    url: window.location.href,
                    type: 'GET',
                    data: function(d) {
                        d.ajax = 1;
                        d.status = $('#statusFilter').val();
                        d.desk = $('#deskFilter').val();
                        d.date_from = $('#dateFrom').val();
                        d.date_to = $('#dateTo').val();
                        d.search_value = $('#searchBox').val();
                        d.my_files = $('#myFilesFilter').is(':checked') ? 1 : 0;
                    }
                },
                columns: [
                    {
                        data: 'file_reference',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                return '<a href="file_details.php?id=' + row.id + '" class="text-primary text-decoration-none fw-bold">' + escapeHtml(data) + '</a>';
                            }
                            return data;
                        }
                    },
                    { data: 'file_name', defaultContent: '-' },
                    { data: 'volume' },
                    { data: 'rack_no' },
                    { data: 'row_no' },
                    { data: 'desk_name', defaultContent: '-' },
                    {
                        data: 'status',
                        render: function(data) {
                            return data === 'ACTIVE' ? '<span class="badge-active">ACTIVE</span>' : '<span class="badge-archived">ARCHIVED</span>';
                        }
                    },
                    { data: 'username', defaultContent: 'Unknown' },
                    {
                        data: 'created_at',
                        render: function(data) {
                            if (!data) return '-';
                            return new Date(data).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        }
                    },
                    { 
                        data: 'editor_name', 
                        defaultContent: '-',
                        render: function(data, type, row) {
                            if (data && row.edited_at) {
                                let date = new Date(row.edited_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                                return `<span class="fw-bold">${escapeHtml(data)}</span><br><small class="--text-muted" style="font-size:0.65rem;">${date}</small>`;
                            }
                            return data || '-';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            let deleteBtn = CAN_DELETE 
                                ? `<button class="btn btn-outline-danger delete-btn" data-id="${row.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>`
                                : '';
                            
                            let editBtn = CAN_EDIT
                                ? `<button class="btn btn-outline-primary edit-btn" data-id="${row.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>`
                                : '';
                                
                            return `
                                <div class="d-flex gap-1 action-btns">
                                    <a href="file_details.php?id=${row.id}" class="btn btn-outline-info" title="View"><i class="fa-solid fa-eye"></i></a>
                                    ${editBtn}
                                    ${deleteBtn}
                                </div>
                            `;
                        }
                    }
                ],
                order: [[8, 'desc']],
                pageLength: 15,
                language: {
                    emptyTable: "No file records found",
                    info: "Showing _START_ - _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 - 0 of 0 entries",
                    lengthMenu: "Per page: _MENU_",
                    paginate: { first: "First", last: "Last", next: "Next", previous: "Previous" }
                },
                initComplete: function() {
                    // Move the Excel button into the DataTables generated toolbar
                    $('#exportExcelBtnSource').contents().appendTo('.excel-btn-wrapper');
                }
            });
        }

        function reloadTable() {
            if (table) table.ajax.reload();
            else initTable();
        }

        $('#applyFilters').on('click', function() { reloadTable(); });

        $('#resetFilters').on('click', function() {
            $('#searchBox').val(''); $('#statusFilter').val(''); $('#deskFilter').val('');
            $('#dateFrom').val(''); $('#dateTo').val(''); $('#myFilesFilter').prop('checked', false);
            reloadTable();
        });

        let searchTimeout;
        $('#searchBox').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() { reloadTable(); }, 500);
        });

        // ==========================================
        // EDIT MODAL LOGIC
        // ==========================================
        $('#fileTable').on('click', '.edit-btn', function() {
            const id = $(this).data('id');
            
            $.ajax({
                url: 'file_list.php?get_record=1&id=' + id,
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    $('#edit_id').val(data.id);
                    $('#edit_file_reference').val(data.file_reference);
                    $('#edit_file_name').val(data.file_name);
                    $('#edit_volume').val(data.volume);
                    $('#edit_rack_no').val(data.rack_no);
                    $('#edit_row_no').val(data.row_no);
                    $('#edit_desk_id').val(data.desk_id || '');
                    $('#edit_status').val(data.status);
                    $('#edit_about_file').val(data.about_file || '');
                    
                    $('#editModal').modal('show');
                },
                error: function() {
                    alert('Error fetching record details.');
                }
            });
        });

        // Save changes from modal
        $('#saveEditBtn').on('click', function() {
            const formData = $('#editForm').serialize() + '&update_record=1';
            
            $.ajax({
                url: 'file_list.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editModal').modal('hide');
                        $('#successMessage').fadeIn().delay(3000).fadeOut();
                        reloadTable();
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('A network error occurred while saving.');
                }
            });
        });

        // ==========================================
        // DELETE LOGIC
        // ==========================================
        $('#fileTable').on('click', '.delete-btn', function() {
            const id = $(this).data('id');
            if (confirm('Are you sure you want to delete this file record? This action cannot be undone.')) {
                $.ajax({
                    url: 'delete_file_record.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            reloadTable();
                        } else {
                            alert('Error: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('A network error occurred. Please check the console for details.');
                    }
                });
            }
        });

        // Export buttons
        function getExportParams() {
            return new URLSearchParams({
                status: $('#statusFilter').val(), desk: $('#deskFilter').val(),
                date_from: $('#dateFrom').val(), date_to: $('#dateTo').val(),
                search_value: $('#searchBox').val(), my_files: $('#myFilesFilter').is(':checked') ? 1 : 0
            }).toString();
        }

        // Since we moved the button, we use event delegation on the document
        $(document).on('click', '#exportExcelBtn', function() { 
            window.location.href = 'export_file_records_excel.php?' + getExportParams(); 
        });

        initTable();

        (function() {
            const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    });

    function toggleSubmenu(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('show');
    }
</script>
</body>
</html>