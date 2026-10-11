<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// 1. Check permission
if (!hasPermission('VIEW_INTERNAL_LETTERS')) { 
    die('Access Denied');
}

$canIssue = true; 

// ==========================================
// AJAX ENDPOINT: GET AVAILABLE FILES FOR INLINE PICKER
// ==========================================
if (isset($_GET['get_available_files'])) {
    header('Content-Type: application/json');
    try {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 5;
        $search = isset($_GET['search_value']) ? trim($_GET['search_value']) : '';
        $filter_desk = isset($_GET['filter_desk']) ? intval($_GET['filter_desk']) : 0; // NEW

        $baseQuery = "
            FROM physical_file_records f
            LEFT JOIN desks d ON f.desk_id = d.id
            WHERE f.status = 'ACTIVE' 
            AND f.is_deleted = 0 
            AND f.id NOT IN (SELECT file_id FROM file_issuances WHERE status = 'Issued')
        ";
        
        $params = [];
        $types = '';

        if (!empty($search)) {
            $baseQuery .= " AND (f.file_reference LIKE ? OR f.file_name LIKE ? OR f.volume LIKE ? OR f.rack_no LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= 'ssss';
        }
        
        // NEW: Desk Filter
        if ($filter_desk > 0) {
            $baseQuery .= " AND f.desk_id = ?";
            $params[] = $filter_desk;
            $types .= 'i';
        }

        $totalStmt = $conn->query("SELECT COUNT(*) as cnt FROM physical_file_records WHERE status = 'ACTIVE' AND is_deleted = 0");
        $totalRecords = $totalStmt->fetch_assoc()['cnt'];

        $countQuery = "SELECT COUNT(*) as cnt " . $baseQuery;
        $countStmt = $conn->prepare($countQuery);
        if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $filteredRecords = $countStmt->get_result()->fetch_assoc()['cnt'];

        $dataQuery = "
            SELECT f.id, f.file_reference, f.file_name, f.volume, f.rack_no, f.row_no, d.desk_name
            " . $baseQuery . "
            ORDER BY f.file_reference ASC
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

// ==========================================
// AJAX ENDPOINT: GET ISSUANCES FOR MAIN TABLE
// ==========================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    try {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search_value']) ? trim($_GET['search_value']) : '';
        $filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

        $baseQuery = "
            FROM file_issuances fi
            JOIN physical_file_records f ON fi.file_id = f.id
            JOIN users u_to ON fi.issued_to = u_to.id
            JOIN users u_by ON fi.issued_by = u_by.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = '';

        if (!empty($search)) {
            $baseQuery .= " AND (f.file_reference LIKE ? OR f.file_name LIKE ? OR u_to.name_with_initial LIKE ? OR u_to.username LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
            $types .= 'ssss';
        }
        if (!empty($filter_status)) {
            $baseQuery .= " AND fi.status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }

        $totalStmt = $conn->query("SELECT COUNT(*) as cnt FROM file_issuances");
        $totalRecords = $totalStmt->fetch_assoc()['cnt'];

        $countQuery = "SELECT COUNT(*) as cnt " . $baseQuery;
        $countStmt = $conn->prepare($countQuery);
        if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $filteredRecords = $countStmt->get_result()->fetch_assoc()['cnt'];

        $dataQuery = "
            SELECT fi.id, fi.issue_date, fi.due_date, fi.return_date, fi.status, fi.remarks,
                   f.file_reference, f.file_name,
                   u_to.name_with_initial as issued_to_name, u_to.service_number as issued_to_service,
                   u_by.name_with_initial as issued_by_name
            " . $baseQuery . "
            ORDER BY fi.id DESC
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

// ==========================================
// AJAX ENDPOINT: BULK ISSUE FILES
// ==========================================
if (isset($_POST['issue_files_bulk'])) {
    header('Content-Type: application/json');
    if (!$canIssue) {
        echo json_encode(['success' => false, 'message' => 'Access Denied']);
        exit;
    }

    $file_ids_string = $_POST['file_ids_string'] ?? '';
    $file_ids = array_filter(explode(',', $file_ids_string)); // Remove empty values
    
    $issued_to = intval($_POST['issued_to']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $remarks = trim($_POST['remarks']);
    $issued_by = $_SESSION['user_id'];

    if (empty($file_ids) || empty($issued_to)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one file and a user.']);
        exit;
    }

    $successCount = 0;
    $errors = [];

    // Use a transaction for safety
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO file_issuances (file_id, issued_to, issued_by, due_date, remarks) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($file_ids as $file_id) {
            $file_id = intval($file_id);
            
            // Check if already issued (double check safety)
            $check = $conn->prepare("SELECT id FROM file_issuances WHERE file_id = ? AND status = 'Issued'");
            $check->bind_param("i", $file_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $errors[] = "File ID $file_id is already issued.";
                continue;
            }

            $stmt->bind_param("iiiss", $file_id, $issued_to, $issued_by, $due_date, $remarks);
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errors[] = "Failed to issue File ID $file_id: " . $conn->error;
            }
        }

        if (empty($errors)) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => "$successCount file(s) issued successfully."]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Errors occurred: ' . implode(' ', $errors)]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================
// AJAX ENDPOINT: RETURN A FILE
// ==========================================
if (isset($_POST['return_file'])) {
    header('Content-Type: application/json');
    
    $issuance_id = intval($_POST['issuance_id']);
    $return_remarks = trim($_POST['return_remarks'] ?? '');
    
    $stmt = $conn->prepare("UPDATE file_issuances SET status = 'Returned', return_date = CURRENT_TIMESTAMP, remarks = CONCAT(IFNULL(remarks, ''), ' | Returned: ', ?) WHERE id = ?");
    $stmt->bind_param("si", $return_remarks, $issuance_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

// ==========================================
// FETCH DATA FOR DROPDOWNS & STATS
// ==========================================
$totalIssued = $conn->query("SELECT COUNT(*) as cnt FROM file_issuances WHERE status = 'Issued'")->fetch_assoc()['cnt'];
$totalReturned = $conn->query("SELECT COUNT(*) as cnt FROM file_issuances WHERE status = 'Returned'")->fetch_assoc()['cnt'];
$overdue = $conn->query("SELECT COUNT(*) as cnt FROM file_issuances WHERE status = 'Issued' AND due_date < CURDATE()")->fetch_assoc()['cnt'];

$users = $conn->query("SELECT id, name_with_initial, service_number FROM users WHERE status = 'Active' ORDER BY name_with_initial ASC");

// NEW: Fetch desks for the filter dropdown
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE status = 'Active' AND is_deleted = 0 ORDER BY desk_name");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue File | IDCMS</title>
    
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
            display: flex; min-height: 100vh; font-family: 'Iskoola Pota', 'Noto Sans Sinhala', "Segoe UI", sans-serif; transition: 0.3s;
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
        
        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px;
            padding: 15px 20px; display: flex; align-items: center; gap: 15px;
        }
        .stat-icon {
            width: 40px; height: 40px; border-radius: 10px; display: flex;
            align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .stat-info { display: flex; flex-direction: column; }
        .stat-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }

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
        
        .badge-issued { background: #f59e0b20; color: #f59e0b; padding: 4px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        .badge-returned { background: #10b98120; color: #10b981; padding: 4px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        .badge-overdue { background: #ef444420; color: #ef4444; padding: 4px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }

        .btn-primary { background: var(--accent); border: none; border-radius: 12px; padding: 8px 20px; font-weight: 600; transition: 0.2s; }
        .btn-primary:hover { background: var(--accent); opacity: 0.9; transform: translateY(-1px); }
        .btn-outline-secondary { border-color: var(--border); color: var(--text-muted); border-radius: 10px; }
        .btn-outline-secondary:hover { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

        .label { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; font-weight: 600; }
        
        [data-theme="dark"] .form-control, [data-theme="dark"] .form-select {
            background-color: #1a1f3a !important; color: #ffffff !important; border-color: rgba(255, 255, 255, 0.15);
        }
        /* FIX: Placeholder color for dark mode */
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder {
            color: #8898aa !important;
            opacity: 1;
        }
        [data-theme="dark"] .form-control:focus, [data-theme="dark"] .form-select:focus {
            background-color: #1a1f3a !important; color: #ffffff !important; border-color: var(--accent);
        }
        
        /* Queue styles */
        .selected-file-item {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--accent-soft); padding: 5px 10px; border-radius: 8px;
            margin-bottom: 5px; font-size: 0.8rem; color: var(--text-main);
        }
        .selected-file-item .remove-btn { color: #ef4444; cursor: pointer; border: none; background: none; }
        .queue-container { max-height: 150px; overflow-y: auto; border: 1px dashed var(--border); border-radius: 8px; padding: 10px; }
        
        .modal-content { background-color: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; color: var(--text-main); }
        .modal-header { border-bottom: 1px solid var(--border); }
        .modal-footer { border-top: 1px solid var(--border); }
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
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
                <a href="file_list.php" class="sub-nav-btn">File List</a>
                <a href="issue_file.php" class="sub-nav-btn active">Issue & Returned File</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <div class="container-fluid">
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0"><i class="fa-solid fa-share-square me-2 text-primary"></i>File Issuance Registry</h4>
                <p class="--text-muted small mb-0">Track files issued to staff and their return status</p>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4 no-print">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-clock"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">CURRENTLY ISSUED</span>
                        <span class="stat-value"><?= $totalIssued ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;"><i class="fa-solid fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">OVERDUE</span>
                        <span class="stat-value"><?= $overdue ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-check-circle"></i></div>
                    <div class="stat-info">
                        <span class="stat-label">RETURNED</span>
                        <span class="stat-value"><?= $totalReturned ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP CONTAINER: BULK ISSUE FORM + INLINE FILE PICKER -->
        <div class="card-box mb-4">
            <h5 class="fw-bold mb-4"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Issue Files (Bulk Mode)</h5>
            
            <div class="row">
                <!-- LEFT SIDE: FORM & QUEUE -->
                <div class="col-md-5 border-end" style="border-color: var(--border) !important;">
                    <form id="issueForm">
                        <div class="mb-3">
                            <div class="label">Selected Files Queue *</div>
                            <div class="queue-container" id="selectedFilesQueue">
                                <div class="--text-muted small text-center py-3">No files selected. Use the panel on the right to add files.</div>
                            </div>
                            <!-- Hidden input to store comma-separated IDs -->
                            <input type="hidden" name="file_ids_string" id="file_ids_string">
                        </div>
                        
                        <div class="mb-3">
                            <div class="label">Issue To (User) *</div>
                            <select name="issued_to" class="form-select" required>
                                <option value="">-- Choose User --</option>
                                <?php while($u = $users->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name_with_initial']) ?> (<?= htmlspecialchars($u['service_number']) ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="label">Due Date</div>
                                <input type="date" name="due_date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="label">Remarks</div>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary w-100" id="saveIssueBtn">
                            <i class="fa-solid fa-paper-plane me-2"></i>Issue Selected Files
                        </button>
                    </form>
                </div>

                <!-- RIGHT SIDE: INLINE FILE PICKER -->
                <div class="col-md-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="label mb-0"><i class="fa-solid fa-list me-1"></i>Available Files (Click "Add" to queue)</div>
                        <!-- NEW: Desk Filter Dropdown -->
                        <select id="availableDeskFilter" class="form-select form-select-sm" style="width: auto; background-color: #1a1f3a; color: white; border-color: rgba(255,255,255,0.15);">
                            <option value="">All Desks</option>
                            <?php while($d = $desks->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['desk_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <table id="availableFilesTable" class="table table-hover table-sm align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Name</th>
                                <th>Loc</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BOTTOM CONTAINER: ISSUED FILES LIST -->
        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="fa-solid fa-list me-2"></i>Issued Files Log</h6>
            </div>
            <div class="table-responsive">
                <table id="issueTable" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>File Reference</th>
                            <th>Issued To</th>
                            <th>Issued By</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- RETURN FILE MODAL (POPUP) -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Return File Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="returnForm">
                    <input type="hidden" name="issuance_id" id="return_issuance_id">
                    <p>Are you sure you want to mark this file as returned?</p>
                    <div class="mb-3">
                        <div class="label">Return Remarks / Condition</div>
                        <textarea name="return_remarks" id="return_remarks" rows="3" class="form-control" placeholder="e.g., Returned in good condition..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveReturnBtn">Confirm Return</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Array to hold queued files
        let selectedFiles = [];

        // ==========================================
        // 1. INITIALIZE INLINE FILE PICKER
        // ==========================================
        let pickerTable = $('#availableFilesTable').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ajax: {
                url: window.location.href,
                type: 'GET',
                data: function(d) {
                    d.get_available_files = 1;
                    d.search_value = d.search.value;
                    d.filter_desk = $('#availableDeskFilter').val(); // NEW: Send desk filter
                }
            },
            columns: [
                { data: 'file_reference', class: 'fw-bold text-primary' },
                { data: 'file_name', defaultContent: '-' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        return `${escapeHtml(row.volume)} / ${escapeHtml(row.rack_no)}`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        // Disable Add button if already in queue
                        let isAdded = selectedFiles.some(f => f.id == row.id);
                        if (isAdded) {
                            return `<button class="btn btn-secondary btn-sm" disabled><i class="fa-solid fa-check"></i> Added</button>`;
                        }
                        return `<button class="btn btn-primary btn-sm add-file-btn" 
                                        data-id="${row.id}" 
                                        data-ref="${escapeHtml(row.file_reference)}">
                                    <i class="fa-solid fa-plus"></i> Add
                                </button>`;
                    }
                }
            ],
            order: [[0, 'asc']],
            pageLength: 5,
            lengthMenu: [5, 10, 25],
            language: {
                emptyTable: "No available files to issue",
                info: "Showing _START_ to _END_ of _TOTAL_ available files",
                search: "Search:",
                paginate: { first: "First", last: "Last", next: "Next", previous: "Previous" }
            }
        });

        // NEW: Reload picker table when desk filter changes
        $('#availableDeskFilter').on('change', function() {
            pickerTable.ajax.reload();
        });

        // ==========================================
        // 2. QUEUE MANAGEMENT LOGIC
        // ==========================================
        
        // Function to render the queue on the left
        function renderQueue() {
            const queueContainer = $('#selectedFilesQueue');
            queueContainer.empty();
            
            if (selectedFiles.length === 0) {
                queueContainer.html('<div class="--text-muted small text-center py-3">No files selected. Use the panel on the right to add files.</div>');
                $('#file_ids_string').val('');
                return;
            }

            selectedFiles.forEach((file, index) => {
                queueContainer.append(`
                    <div class="selected-file-item">
                        <span><i class="fa-solid fa-file me-1"></i> ${file.ref}</span>
                        <button type="button" class="remove-btn" data-id="${file.id}" title="Remove"><i class="fa-solid fa-times"></i></button>
                    </div>
                `);
            });

            // Update hidden input with comma-separated IDs
            $('#file_ids_string').val(selectedFiles.map(f => f.id).join(','));
        }

        // Handle "Add" button click in the picker
        $('#availableFilesTable').on('click', '.add-file-btn', function() {
            const id = $(this).data('id');
            const ref = $(this).data('ref');
            
            // Add to array
            selectedFiles.push({ id: id, ref: ref });
            
            // Re-render queue and reload picker table to update button states
            renderQueue();
            pickerTable.ajax.reload(null, false); // false = keep current page
        });

        // Handle "Remove" button click in the queue
        $('#selectedFilesQueue').on('click', '.remove-btn', function() {
            const id = $(this).data('id');
            
            // Remove from array
            selectedFiles = selectedFiles.filter(f => f.id != id);
            
            // Re-render queue and reload picker table
            renderQueue();
            pickerTable.ajax.reload(null, false);
        });

        // ==========================================
        // 3. INITIALIZE MAIN ISSUED FILES TABLE
        // ==========================================
        let table = $('#issueTable').DataTable({
            processing: true,
            serverSide: true,
            searching: true,
            ajax: {
                url: window.location.href,
                type: 'GET',
                data: function(d) {
                    d.ajax = 1;
                    d.search_value = d.search.value; 
                }
            },
            columns: [
                { 
                    data: 'file_reference',
                    render: function(data, type, row) {
                        return `<span class="fw-bold text-primary">${escapeHtml(data)}</span><br><small class="--text-muted">${escapeHtml(row.file_name || '-')}</small>`;
                    }
                },
                { 
                    data: 'issued_to_name',
                    render: function(data, type, row) {
                        return `<span class="fw-bold">${escapeHtml(data)}</span><br><small class="--text-muted">${escapeHtml(row.issued_to_service)}</small>`;
                    }
                },
                { data: 'issued_by_name', defaultContent: 'Unknown' },
                { 
                    data: 'issue_date',
                    render: function(data) {
                        return new Date(data).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    }
                },
                { 
                    data: 'due_date',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        let isOverdue = false;
                        if (data === 'Issued' && row.due_date) {
                            let due = new Date(row.due_date);
                            let today = new Date();
                            today.setHours(0,0,0,0);
                            if (due < today) isOverdue = true;
                        }

                        if (data === 'Returned') return '<span class="badge-returned">RETURNED</span>';
                        if (isOverdue) return '<span class="badge-overdue">OVERDUE</span>';
                        return '<span class="badge-issued">ISSUED</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (row.status === 'Issued') {
                            return `<button class="btn btn-outline-primary btn-sm return-btn" data-id="${row.id}" title="Return File"><i class="fa-solid fa-undo"></i> Return</button>`;
                        }
                        return `<span class="--text-muted small">Returned on ${new Date(row.return_date).toLocaleDateString()}</span>`;
                    }
                }
            ],
            order: [[3, 'desc']],
            pageLength: 10,
            language: {
                emptyTable: "No file issuance records found",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                lengthMenu: "Per page: _MENU_",
                search: "Search:",
                paginate: { first: "First", last: "Last", next: "Next", previous: "Previous" }
            }
        });

        // ==========================================
        // 4. BULK ISSUE FORM SUBMIT
        // ==========================================
        $('#saveIssueBtn').on('click', function() {
            const fileIdsStr = $('#file_ids_string').val();
            if (!fileIdsStr) {
                alert('Please add at least one file to the queue.');
                return;
            }

            const issuedTo = $('select[name="issued_to"]').val();
            if (!issuedTo) {
                alert('Please select a user to issue the files to.');
                return;
            }

            const formData = $('#issueForm').serialize() + '&issue_files_bulk=1';
            $.ajax({
                url: 'issue_file.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Clear everything
                        $('#issueForm')[0].reset();
                        selectedFiles = [];
                        renderQueue();
                        pickerTable.ajax.reload();
                        table.ajax.reload();
                        alert(response.message);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('A network error occurred while issuing the files.');
                }
            });
        });

        // ==========================================
        // 5. RETURN FILE MODAL LOGIC
        // ==========================================
        $('#issueTable').on('click', '.return-btn', function() {
            const id = $(this).data('id');
            $('#return_issuance_id').val(id);
            $('#return_remarks').val(''); // Clear previous remarks
            $('#returnModal').modal('show');
        });

        $('#saveReturnBtn').on('click', function() {
            const formData = $('#returnForm').serialize() + '&return_file=1';
            $.ajax({
                url: 'issue_file.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#returnModal').modal('hide');
                        pickerTable.ajax.reload();
                        table.ajax.reload();
                        alert('File returned successfully!');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('A network error occurred while returning the file.');
                }
            });
        });

        // Theme persistence
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