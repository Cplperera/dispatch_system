<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

// ============================================================
// FIX: Ensure required columns exist in outward_letters table
// ============================================================
function ensure_outward_columns($conn) {
    $missing = [];
    $columns = [
        'approval_status' => "ALTER TABLE outward_letters ADD COLUMN approval_status ENUM('Draft','Pending RA Approval','Approved','Rejected') DEFAULT 'Draft'",
        'is_deleted'      => "ALTER TABLE outward_letters ADD COLUMN is_deleted TINYINT(1) DEFAULT 0",
        'approved_by'     => "ALTER TABLE outward_letters ADD COLUMN approved_by INT(11) NULL",
        'approved_at'     => "ALTER TABLE outward_letters ADD COLUMN approved_at DATETIME NULL",
        'rejected_by'     => "ALTER TABLE outward_letters ADD COLUMN rejected_by INT(11) NULL",
        'rejected_at'     => "ALTER TABLE outward_letters ADD COLUMN rejected_at DATETIME NULL",
        'submitted_at'    => "ALTER TABLE outward_letters ADD COLUMN submitted_at DATETIME NULL",
        'dispatch_remarks'=> "ALTER TABLE outward_letters ADD COLUMN dispatch_remarks TEXT NULL"
    ];
    foreach ($columns as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM outward_letters LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            $missing[] = $col;
            if (!$conn->query($sql)) {
                error_log("Failed to add column $col: " . $conn->error);
            }
        }
    }
}
ensure_outward_columns($conn);
// ============================================================

// Get current user's appointment
$appointment = '';
$user_id = $_SESSION['user_id'];
$apptQuery = $conn->prepare("SELECT Appointment FROM users WHERE id = ?");
$apptQuery->bind_param("i", $user_id);
$apptQuery->execute();
$apptResult = $apptQuery->get_result();
if ($apptRow = $apptResult->fetch_assoc()) {
    $appointment = $apptRow['Appointment'];
}
$apptQuery->close();

// Helper: Time ago function
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return 'N/A';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/* ===============================
    HANDLE SINGLE ACTIONS (Approve, Reject, Resubmit, Dispatch, Submit Draft)
================================ */
$action_message = '';
$action_type = '';

// 1. Approve (RA)
if (isset($_GET['approve']) && isset($_GET['id'])) {
    if (!hasPermission('APPROVE_OUTWARD')) {
        $action_message = 'You do not have permission to approve.';
        $action_type = 'danger';
    } else {
        $id = intval($_GET['id']);
        $user_id = $_SESSION['user_id'];
        $update = $conn->prepare("UPDATE outward_letters SET approval_status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND approval_status = 'Pending RA Approval'");
        $update->bind_param("ii", $user_id, $id);
        if ($update->execute() && $update->affected_rows > 0) {
            $action_message = "Letter #$id has been APPROVED and forwarded to Dispatch Clerk.";
            $action_type = 'success';
        } else {
            $action_message = "Failed to approve. It may have already been processed.";
            $action_type = 'warning';
        }
        $update->close();
    }
}

// 2. Reject (RA)
if (isset($_GET['reject']) && isset($_GET['id'])) {
    if (!hasPermission('APPROVE_OUTWARD')) {
        $action_message = ' You do not have permission to reject.';
        $action_type = 'danger';
    } else {
        $id = intval($_GET['id']);
        $user_id = $_SESSION['user_id'];
        $update = $conn->prepare("UPDATE outward_letters SET approval_status = 'Rejected', rejected_by = ?, rejected_at = NOW() WHERE id = ? AND approval_status = 'Pending RA Approval'");
        $update->bind_param("ii", $user_id, $id);
        if ($update->execute() && $update->affected_rows > 0) {
            $action_message = "Letter #$id has been REJECTED and returned to the creator.";
            $action_type = 'danger';
        } else {
            $action_message = "Failed to reject. It may have already been processed.";
            $action_type = 'warning';
        }
        $update->close();
    }
}

// 3. Resubmit (User)
if (isset($_GET['resubmit']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    $check = $conn->prepare("SELECT created_by FROM outward_letters WHERE id = ? AND approval_status = 'Rejected'");
    $check->bind_param("i", $id);
    $check->execute();
    $res = $check->get_result();
    $row = $res->fetch_assoc();
    
    if ($row && ($row['created_by'] == $user_id || hasPermission('RESUBMIT_OUTWARD'))) {
        $update = $conn->prepare("UPDATE outward_letters SET approval_status = 'Pending RA Approval', rejected_by = NULL, rejected_at = NULL WHERE id = ?");
        $update->bind_param("i", $id);
        if ($update->execute() && $update->affected_rows > 0) {
            $action_message = "Letter #$id has been resubmitted for RA approval.";
            $action_type = 'info';
        } else {
            $action_message = "Failed to resubmit.";
            $action_type = 'warning';
        }
        $update->close();
    } else {
        $action_message = "You are not authorized to resubmit this letter.";
        $action_type = 'danger';
    }
    $check->close();
}

// 4. Dispatch (Dispatch Clerk)
if (isset($_POST['dispatch']) && isset($_POST['id'])) {
    if (!hasPermission('DISPATCH_OUTWARD')) {
        $action_message = 'You do not have permission to dispatch.';
        $action_type = 'danger';
    } else {
        $id = intval($_POST['id']);
        $handed_over_to = trim($_POST['handed_over_to']);
        $dispatch_remarks = trim($_POST['dispatch_remarks']);
        $user_id = $_SESSION['user_id'];
        
        if (empty($handed_over_to)) {
            $action_message = "Please enter the name of the person/entity the letter was handed over to.";
            $action_type = 'warning';
        } else {
            $update = $conn->prepare("UPDATE outward_letters 
                                      SET status = 'Completed', 
                                          dispatched_by = ?, 
                                          handed_over_to = ?, 
                                          handed_over_date = NOW(),
                                          dispatch_remarks = ? 
                                      WHERE id = ? AND approval_status = 'Approved' AND status != 'Completed'");
            $update->bind_param("issi", $user_id, $handed_over_to, $dispatch_remarks, $id);
            if ($update->execute() && $update->affected_rows > 0) {
                $action_message = "📬 Letter #$id has been DISPATCHED and completed.";
                $action_type = 'success';
            } else {
                $action_message = "Failed to dispatch. It may have already been dispatched or not approved.";
                $action_type = 'warning';
            }
            $update->close();
        }
    }
}

// 5. Submit Draft for Approval
if (isset($_GET['submit_draft']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $check = $conn->prepare("SELECT created_by FROM outward_letters WHERE id = ? AND approval_status = 'Draft'");
    $check->bind_param("i", $id);
    $check->execute();
    $res = $check->get_result();
    $row = $res->fetch_assoc();
    if ($row && $row['created_by'] == $user_id) {
        $update = $conn->prepare("UPDATE outward_letters SET approval_status = 'Pending RA Approval', submitted_at = NOW() WHERE id = ?");
        $update->bind_param("i", $id);
        if ($update->execute() && $update->affected_rows > 0) {
            $action_message = "Draft #$id submitted for RA approval.";
            $action_type = 'info';
        } else {
            $action_message = "Failed to submit draft.";
            $action_type = 'warning';
        }
        $update->close();
    } else {
        $action_message = "You are not the creator or the letter is not a draft.";
        $action_type = 'danger';
    }
    $check->close();
}

/* ===============================
    FETCH STATISTICS
================================ */
$stats = [
    'total' => 0,
    'draft' => 0,
    'pending_ra' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0
];
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN approval_status = 'Draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN approval_status = 'Pending RA Approval' AND status != 'Completed' THEN 1 ELSE 0 END) as pending_ra,
    SUM(CASE WHEN approval_status = 'Approved' AND status != 'Completed' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
    FROM outward_letters WHERE is_deleted = 0";
$statsRes = $conn->query($statsQuery);
if ($statsRes) {
    $stats = $statsRes->fetch_assoc();
}

/* ===============================
    FILTERS, SORTING & PAGINATION
================================ */
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$desk_filter = isset($_GET['desk']) ? (int)$_GET['desk'] : ''; // external branch filter
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$dispatch_filter = isset($_GET['dispatch_method']) ? $conn->real_escape_string($_GET['dispatch_method']) : '';
$internal_desk_filter = isset($_GET['internal_desk']) ? (int)$_GET['internal_desk'] : ''; // new internal desk filter

// Sorting
$sort = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'o.id';
$order = isset($_GET['order']) && strtoupper($_GET['order']) == 'ASC' ? 'ASC' : 'DESC';
$allowed_sort_columns = ['o.id', 'o.cdr_no', 'o.title', 'o.letter_date', 'o.created_at', 'o.approval_status'];
if (!in_array($sort, $allowed_sort_columns)) $sort = 'o.id';

// Per page
$per_page_options = [10, 15, 25, 50, 100];
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $per_page_options) ? (int)$_GET['per_page'] : 15;
$limit = $per_page;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where = " WHERE o.is_deleted = 0 ";
if (!empty($search)) {
    $where .= " AND (o.cdr_no LIKE '%$search%' OR o.title LIKE '%$search%' OR o.letter_ref LIKE '%$search%')";
}
if (!empty($desk_filter)) {
    $where .= " AND FIND_IN_SET('$desk_filter', o.receiver) > 0";
}
if (!empty($date_from) && !empty($date_to)) {
    $where .= " AND o.letter_date BETWEEN '$date_from' AND '$date_to'";
}
if (!empty($status_filter)) {
    if ($status_filter === 'Approved') {
        $where .= " AND o.approval_status = 'Approved' AND o.status != 'Completed'";
    } elseif ($status_filter === 'Completed') {
        $where .= " AND o.status = 'Completed'";
    } else {
        $where .= " AND o.approval_status = '$status_filter'";
    }
}
if (!empty($dispatch_filter)) {
    $where .= " AND o.dispatch_method = '$dispatch_filter'";
}
// Internal desk filter (uses creator's desk)
if (!empty($internal_desk_filter)) {
    $where .= " AND creator.desk_id = '$internal_desk_filter'";
}

// ===============================
// HANDLE EXPORT (CSV / EXCEL)
// ===============================
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xlsx'])) {
    // Disable error reporting to avoid corrupting the file
    error_reporting(0);
    ini_set('display_errors', 0);

    // Same query but without LIMIT, ordered by id
    $exportQuery = "SELECT o.*, 
                        (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, o.receiver)) AS branch_names,
                        creator.name_with_initial AS creator_name,
                        approver.name_with_initial AS approver_name,
                        rejecter.name_with_initial AS rejecter_name,
                        dispatcher.name_with_initial AS dispatcher_name
                    FROM outward_letters o
                    LEFT JOIN users creator ON o.created_by = creator.id
                    LEFT JOIN users approver ON o.approved_by = approver.id
                    LEFT JOIN users rejecter ON o.rejected_by = rejecter.id
                    LEFT JOIN users dispatcher ON o.dispatched_by = dispatcher.id
                    $where 
                    ORDER BY o.id DESC";

    $exportResult = $conn->query($exportQuery);
    if (!$exportResult || $exportResult->num_rows == 0) {
        die('No records to export.');
    }

    // Define column headers
    $headers = [
        'ID', 'CDR No', 'Title', 'Letter Ref', 'Letter Date',
        'Receiver(s)', 'Dispatch Method', 'Status', 'Created By', 'Created At',
        'Approved By', 'Approved At', 'Rejected By', 'Rejected At',
        'Dispatched By', 'Handed Over To', 'Handed Over Date', 'Dispatch Remarks'
    ];

    $format = $_GET['export'];

    if ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="outward_letters_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);

        while ($row = $exportResult->fetch_assoc()) {
            $data = [
                $row['id'],
                $row['cdr_no'],
                $row['title'],
                $row['letter_ref'],
                $row['letter_date'],
                $row['branch_names'] ?? $row['receiver'],
                $row['dispatch_method'] ?? 'Email',
                ($row['status'] == 'Completed' ? 'Completed' : $row['approval_status']),
                $row['creator_name'] ?? '',
                $row['created_at'],
                $row['approver_name'] ?? '',
                $row['approved_at'] ?? '',
                $row['rejecter_name'] ?? '',
                $row['rejected_at'] ?? '',
                $row['dispatcher_name'] ?? '',
                $row['handed_over_to'] ?? '',
                $row['handed_over_date'] ?? '',
                $row['dispatch_remarks'] ?? ''
            ];
            fputcsv($output, $data);
        }
        fclose($output);
        exit;
    }

    if ($format === 'xlsx') {
        // Simple Excel (XLS) using HTML table – works in Excel
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="outward_letters_' . date('Y-m-d') . '.xls"');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        // Header
        echo '<tr>';
        foreach ($headers as $h) {
            echo '<th style="font-weight:bold;">' . htmlspecialchars($h) . '</th>';
        }
        echo '</tr>';
        // Data
        while ($row = $exportResult->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['cdr_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['title']) . '</td>';
            echo '<td>' . htmlspecialchars($row['letter_ref']) . '</td>';
            echo '<td>' . $row['letter_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['branch_names'] ?? $row['receiver']) . '</td>';
            echo '<td>' . htmlspecialchars($row['dispatch_method'] ?? 'Email') . '</td>';
            echo '<td>' . ($row['status'] == 'Completed' ? 'Completed' : $row['approval_status']) . '</td>';
            echo '<td>' . htmlspecialchars($row['creator_name'] ?? '') . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '<td>' . htmlspecialchars($row['approver_name'] ?? '') . '</td>';
            echo '<td>' . $row['approved_at'] . '</td>';
            echo '<td>' . htmlspecialchars($row['rejecter_name'] ?? '') . '</td>';
            echo '<td>' . $row['rejected_at'] . '</td>';
            echo '<td>' . htmlspecialchars($row['dispatcher_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['handed_over_to'] ?? '') . '</td>';
            echo '<td>' . $row['handed_over_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['dispatch_remarks'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

$query = "SELECT o.*, 
          (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, o.receiver)) AS branch_names,
          creator.name_with_initial AS creator_name,
          approver.name_with_initial AS approver_name,
          rejecter.name_with_initial AS rejecter_name,
          dispatcher.name_with_initial AS dispatcher_name
          FROM outward_letters o
          LEFT JOIN users creator ON o.created_by = creator.id
          LEFT JOIN users approver ON o.approved_by = approver.id
          LEFT JOIN users rejecter ON o.rejected_by = rejecter.id
          LEFT JOIN users dispatcher ON o.dispatched_by = dispatcher.id
          $where 
          ORDER BY $sort $order LIMIT $offset, $limit";

$result = $conn->query($query);
$total_res = $conn->query("SELECT COUNT(o.id) as total FROM outward_letters o 
                           LEFT JOIN users creator ON o.created_by = creator.id
                           $where");
$total_rows = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$branches = $conn->query("SELECT id, branch_name FROM external_branches ORDER BY branch_name ASC");

// Fetch internal desks for filter
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE status = 'Active' AND is_deleted = 0 ORDER BY desk_name ASC");

$canApprove = hasPermission('APPROVE_OUTWARD');
$canDispatch = hasPermission('DISPATCH_OUTWARD');
$canResubmit = hasPermission('RESUBMIT_OUTWARD');

// Build query string for pagination links (exclude page & per_page)
$query_params = array_filter([
    'search' => $search,
    'desk' => $desk_filter,
    'internal_desk' => $internal_desk_filter,
    'status' => $status_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'dispatch_method' => $dispatch_filter,
    'sort' => $sort,
    'order' => $order
]);
$query_string = http_build_query($query_params);

function buildPaginationUrl($pageNum, $queryParams, $perPage) {
    $params = $queryParams;
    $params['page'] = $pageNum;
    $params['per_page'] = $perPage;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outward Registry | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; 
            --sidebar-bg: #FFFFFF; 
            --card-bg: #FFFFFF;
            --accent: #4318FF; 
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; 
            --text-muted: #707EAE; 
            --border: #DDE2EF;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
            --success: #10b981; 
            --warning: #f59e0b; 
            --danger: #ef4444; 
            --info: #3b82f6; 
            --purple: #8b5cf6;
        }

        [data-theme="dark"] {
            --bg-body: #0B1437; 
            --sidebar-bg: #111C44; 
            --card-bg: #111C44;
            --accent: #7551FF; 
            --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; 
            --text-muted: #A3AED0; 
            --border: rgba(255, 255, 255, 0.1);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 30px 15px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: 0.3s;
        }

        .brand-box { padding: 0 10px; margin-bottom: 40px; }
        .brand-name { font-weight: 800; font-size: 1.2rem; line-height: 1.2; color: var(--text-main); }

        .nav-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: 0.2s;
            margin-bottom: 5px;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .nav-btn i { width: 20px; font-size: 1.1rem; margin-right: 12px; }

        .submenu { padding-left: 20px; margin-bottom: 10px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { 
            display: block; 
            padding: 8px 15px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 0.8rem; 
            font-weight: 600; 
            border-left: 2px solid var(--border); 
            transition: 0.2s; 
        }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); background: transparent; }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            min-height: 100vh;
            background: var(--bg-body);
        }

        .top-nav {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .content-container { flex: 1; }

        .stats-row { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-bottom: 25px; 
        }

        .stat-pill { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            padding: 15px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            transition: 0.2s; 
            cursor: pointer; 
            text-decoration: none;
        }

        .stat-pill:hover { 
            transform: translateY(-3px); 
            border-color: var(--accent); 
            box-shadow: 0 10px 20px -10px var(--accent-soft); 
        }

        .stat-pill.active { border-color: var(--accent); background: var(--accent-soft); }

        .stat-icon-sm { 
            width: 40px; 
            height: 40px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.2rem; 
            color: white; 
        }

        .stat-info-sm h6 { 
            font-size: 0.65rem; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 2px; 
        }

        .stat-info-sm .number { font-size: 1.4rem; font-weight: 700; line-height: 1; color: var(--text-main); }
        
        .filter-bar { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 20px; 
            padding: 20px; 
            margin-bottom: 25px; 
        }

        .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { flex: 1 1 160px; }
        .filter-group label { 
            display: block; 
            font-size: 0.65rem; 
            text-transform: uppercase; 
            color: var(--text-muted); 
            font-weight: 600; 
            letter-spacing: 0.5px; 
            margin-bottom: 4px; 
        }

        .filter-control { 
            width: 100%; 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            padding: 8px 12px; 
            color: var(--text-main); 
            font-size: 0.85rem; 
        }
        .filter-control:focus { outline: none; border-color: var(--accent); }
        
        .btn-filter { 
            background: var(--accent); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            padding: 8px 20px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            cursor: pointer; 
            transition: 0.2s; 
            text-decoration: none;
        }
        .btn-filter:hover { opacity: 0.9; transform: translateY(-2px); color: white; }

        .btn-reset { 
            background: transparent; 
            border: 1px solid var(--border); 
            color: var(--text-muted); 
            border-radius: 10px; 
            padding: 8px 20px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none; 
        }
        .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

        .search-box { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 50px; 
            padding: 10px 20px; 
            display: flex; 
            align-items: center; 
            width: 300px; 
        }
        .search-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .search-box input { background: transparent; border: none; color: var(--text-main); font-size: 0.9rem; margin-left: 10px; width: 100%; outline: none; }

        .table-card { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            padding: 25px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            overflow-x: auto; 
        }

        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .table-header h5 { font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px; }

        .result-count { font-size: 0.8rem; color: var(--text-muted); background: var(--accent-soft); padding: 5px 12px; border-radius: 30px; white-space: nowrap; }
        
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; min-width: 900px; }
        .custom-table thead th { border: none; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; padding: 10px 20px; }
        .custom-table tbody tr { background: var(--bg-body); transition: 0.2s; border-radius: 15px; }
        .custom-table tbody tr:hover { transform: translateX(5px); box-shadow: -5px 0 0 var(--accent); }
        .custom-table tbody td { padding: 18px 20px; border: none; vertical-align: middle; color: var(--text-main); }
        .custom-table tbody td:first-child { border-radius: 15px 0 0 15px; }
        .custom-table tbody td:last-child { border-radius: 0 15px 15px 0; }
        
        .badge-ref { background: var(--accent-soft); color: var(--accent); padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.75rem; font-family: monospace; display: inline-block; }
        .badge-status { padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-status.pending-ra { background: rgba(255, 171, 0, 0.15); color: #FFAB00; }
        .badge-status.approved { background: rgba(0, 200, 83, 0.15); color: #00C853; }
        .badge-status.rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .badge-status.completed { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .badge-status.draft { background: rgba(160, 160, 160, 0.15); color: #A0A0A0; }

        .badge-method { padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .badge-method.email { background: rgba(117, 81, 255, 0.15); color: var(--accent); }
        .badge-method.hand { background: rgba(255, 165, 0, 0.15); color: #f59e0b; }

        .action-group { display: flex; gap: 5px; justify-content: flex-end; flex-wrap: wrap; align-items: center; }
        .action-btn { border: none; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 0.7rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; cursor: pointer; }
        .action-btn:hover { opacity: 0.85; transform: translateY(-2px); color: white; }
        .btn-approve { background: #00C853; color: #fff; }
        .btn-reject { background: #ef4444; color: #fff; }
        .btn-resubmit { background: #f59e0b; color: #fff; }
        .btn-dispatch { background: #3b82f6; color: #fff; }
        .btn-submit-draft { background: #8b5cf6; color: #fff; }
        .action-btn.view-edit { width: 36px; height: 36px; justify-content: center; background: var(--accent-soft); color: var(--accent); padding: 0; }
        .action-btn.view-edit:hover { background: var(--accent); color: white; }
        
        .task-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 25px; flex-wrap: wrap; }
        .page-link { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 12px; border-radius: 12px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .page-link:hover, .page-link.active { background: var(--accent); border-color: var(--accent); color: white; }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        
        .top-controls-bar { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .sort-link { color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .sort-link:hover, .sort-link.active { color: var(--accent); }
        
        .modal-content { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 16px; }
        .modal-header, .modal-footer { border-color: var(--border); }
        [data-theme="dark"] .btn-close { filter: invert(1); }
        
        .page-size-selector { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-muted); }
        .page-size-selector select { background: var(--bg-body); border: 1px solid var(--border); border-radius: 8px; padding: 4px 8px; color: var(--text-main); outline: none; }

        .mobile-menu-toggle { position: fixed; top: 20px; left: 20px; z-index: 1060; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px; cursor: pointer; display: none; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .mobile-menu-toggle { display: block; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .filter-form { flex-direction: column; }
            .search-box { width: 100%; }
            .top-nav { flex-direction: column; align-items: stretch; gap: 10px; }
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(10);
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br>
            <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-house"></i> Home Page</a>
        
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 25px 0 10px 15px; opacity: 0.6; letter-spacing: 1px;">Dispatching</div>
        
        <button class="nav-btn active" onclick="toggleSubmenu('outwardSub')">
            <i class="fa-solid fa-paper-plane"></i> Outward Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
        </button>
        <div id="outwardSub" class="submenu show">
            <a href="add_outward.php" class="sub-nav-btn">Add External</a>
            <a href="outward_list.php" class="sub-nav-btn active-sub">View External</a>
        </div>
        
        <button class="nav-btn" onclick="toggleSubmenu('internalSub')">
            <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
        </button>
        <div id="internalSub" class="submenu">
            <a href="../internal/internal_letters.php" class="sub-nav-btn">View Registry</a>
            <a href="../internal/inbox.php" class="sub-nav-btn">My Inbox</a>
        </div>
        
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-power-off"></i> Logout</a>
    </nav>
</aside>

<div class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fa-solid fa-bars"></i>
</div>

<main class="main-content">
    <header class="top-nav">
        <div>
            <h4 class="fw-bold mb-0">External Letters Registry</h4>
            <p class="small text-muted mb-0"></p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="add_outward.php" class="btn-filter"><i class="fa fa-plus me-2"></i>New Entry</a>
            <form method="GET" action="outward_list.php" class="search-box">
                <i class="fa-solid fa-magnifying-glass --text-muted"></i>
                <input type="text" name="search" placeholder="Search outward..." value="<?= htmlspecialchars($search) ?>">
                <?php
                foreach (['status','desk','internal_desk','dispatch_method','date_from','date_to','sort','order','per_page'] as $p) {
                    if (!empty($_GET[$p])) echo '<input type="hidden" name="'.$p.'" value="'.htmlspecialchars($_GET[$p]).'">';
                }
                ?>
            </form>
        </div>
    </header>

    <div class="content-container">
        <!-- Stats Cards Layout (Draft Card Removed) -->
        <div class="stats-row">
            <a href="outward_list.php" class="stat-pill <?= empty($status_filter) && !isset($_GET['status']) ? 'active' : '' ?>">
                <div class="stat-icon-sm" style="background: var(--accent);"><i class="fa-solid fa-list"></i></div>
                <div class="stat-info-sm"><h6>Total</h6><div class="number"><?= $stats['total'] ?></div></div>
            </a>
            <a href="outward_list.php?status=Pending+RA+Approval" class="stat-pill <?= $status_filter == 'Pending RA Approval' ? 'active' : '' ?>">
                <div class="stat-icon-sm" style="background: var(--warning);"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info-sm"><h6>Pending RA</h6><div class="number"><?= $stats['pending_ra'] ?></div></div>
            </a>
            <a href="outward_list.php?status=Approved" class="stat-pill <?= $status_filter == 'Approved' ? 'active' : '' ?>">
                <div class="stat-icon-sm" style="background: var(--success);"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-info-sm"><h6>Approved RA</h6><div class="number"><?= $stats['approved'] ?></div></div>
            </a>
            <a href="outward_list.php?status=Rejected" class="stat-pill <?= $status_filter == 'Rejected' ? 'active' : '' ?>">
                <div class="stat-icon-sm" style="background: var(--danger);"><i class="fa-solid fa-ban"></i></div>
                <div class="stat-info-sm"><h6>Rejected RA</h6><div class="number"><?= $stats['rejected'] ?></div></div>
            </a>
            <a href="outward_list.php?status=Completed" class="stat-pill <?= $status_filter == 'Completed' ? 'active' : '' ?>">
                <div class="stat-icon-sm" style="background: var(--info);"><i class="fa-solid fa-paper-plane"></i></div>
                <div class="stat-info-sm"><h6>Completed</h6><div class="number"><?= $stats['completed'] ?></div></div>
            </a>
        </div>

        <!-- Action Message -->
        <?php if (!empty($action_message)): ?>
            <div class="alert alert-<?= $action_type ?: 'info' ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= $action_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-control">
                        <option value="">All Status</option>
                        <option value="Draft" <?= $status_filter == 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Pending RA Approval" <?= $status_filter == 'Pending RA Approval' ? 'selected' : '' ?>>Pending RA</option>
                        <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>External Branches</label>
                    <select name="desk" class="filter-control">
                        <option value="">All External Branches</option>
                        <?php 
                        $branches->data_seek(0);
                        while($b = $branches->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>" <?= $desk_filter == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <!-- NEW: Internal Desk Filter -->
                <div class="filter-group">
                    <label>Internal Desk</label>
                    <select name="internal_desk" class="filter-control">
                        <option value="">All Desks</option>
                        <?php 
                        $desks->data_seek(0);
                        while($d = $desks->fetch_assoc()): ?>
                            <option value="<?= $d['id'] ?>" <?= $internal_desk_filter == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['desk_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Dispatch Method</label>
                    <select name="dispatch_method" class="filter-control">
                        <option value="">All</option>
                        <option value="Email" <?= $dispatch_filter == 'Email' ? 'selected' : '' ?>>Email</option>
                        <option value="By Hand" <?= $dispatch_filter == 'By Hand' ? 'selected' : '' ?>>By Hand</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="filter-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="filter-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn-filter"><i class="fa fa-filter me-1"></i>Apply</button>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <a href="outward_list.php" class="btn-reset"><i class="fa fa-times me-1"></i>Reset</a>
                </div>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                <input type="hidden" name="per_page" value="<?= $per_page ?>">
            </form>
        </div>

        <!-- Top Controls Bar -->
        <div class="top-controls-bar">
            <div class="--text-muted small">
                Showing <?= $total_rows > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> entries
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Excel button (CSV removed) -->
                <a href="?<?= $query_string ?>&export=xlsx&per_page=<?= $per_page ?>" class="btn-filter" style="background: #1e7e34;">
                    <i class="fa-solid fa-file-excel me-1"></i> Excel
                </a>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select onchange="window.location.href='?<?= $query_string ?>&per_page='+this.value">
                        <?php foreach ($per_page_options as $opt): ?>
                            <option value="<?= $opt ?>" <?= $opt == $per_page ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-header">
                <h5><i class="fa-solid fa-list-check"></i> Outward Letters</h5>
            </div>

            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 13%;">
                            <a href="?sort=o.cdr_no&order=<?= ($sort=='o.cdr_no' && $order=='ASC') ? 'DESC' : 'ASC' ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="sort-link <?= $sort=='o.cdr_no' ? 'active' : '' ?>">
                                CDR/Ref <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 20%;">
                            <a href="?sort=o.title&order=<?= ($sort=='o.title' && $order=='ASC') ? 'DESC' : 'ASC' ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="sort-link <?= $sort=='o.title' ? 'active' : '' ?>">
                                Title <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 15%;">Receiver</th>
                        <th style="width: 10%;">Method</th>
                        <th style="width: 12%;">
                            <a href="?sort=o.approval_status&order=<?= ($sort=='o.approval_status' && $order=='ASC') ? 'DESC' : 'ASC' ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="sort-link <?= $sort=='o.approval_status' ? 'active' : '' ?>">
                                Status <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 10%;">
                            <a href="?sort=o.created_at&order=<?= ($sort=='o.created_at' && $order=='ASC') ? 'DESC' : 'ASC' ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="sort-link <?= $sort=='o.created_at' ? 'active' : '' ?>">
                                Created <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th style="width: 20%;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows == 0): ?>
                        <tr><td colspan="7" class="text-center --text-muted py-5"><i class="fa-solid fa-file-lines fa-2x mb-3 d-block opacity-25"></i>No outward letters match your criteria.</td></tr>
                    <?php else: while($row = $result->fetch_assoc()): 
                        $status = $row['approval_status'];
                        $statusClass = '';
                        $statusDisplay = $row['status'] == 'Completed' ? 'Completed' : $status;
                        if ($row['status'] == 'Completed') $statusClass = 'badge-status completed';
                        elseif ($status == 'Pending RA Approval') $statusClass = 'badge-status pending-ra';
                        elseif ($status == 'Approved') $statusClass = 'badge-status approved';
                        elseif ($status == 'Rejected') $statusClass = 'badge-status rejected';
                        elseif ($status == 'Draft') $statusClass = 'badge-status draft';
                        
                        $method = $row['dispatch_method'] ?? 'Email';
                        $methodClass = $method == 'Email' ? 'badge-method email' : 'badge-method hand';
                        $methodIcon = $method == 'Email' ? 'fa-envelope' : 'fa-hand';
                    ?>
                    <tr>
                        <td>
                            <div class="fw-bold badge-ref"><?= htmlspecialchars($row['cdr_no']) ?></div>
                            <div class="small --text-muted mt-1"><?= htmlspecialchars($row['letter_ref']) ?></div>
                        </td>
                        <td>
                            <div class="fw-semibold text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($row['title']) ?>"><?= htmlspecialchars($row['title']) ?></div>
                            <div class="task-meta"><i class="fa-solid fa-calendar me-1"></i><?= date('Y-m-d', strtotime($row['letter_date'])) ?></div>
                        </td>
                        <td>
                            <div class="small" title="<?= htmlspecialchars($row['branch_names'] ?? $row['receiver']) ?>">
                                <?= htmlspecialchars($row['branch_names'] ?? $row['receiver']) ?>
                            </div>
                            <?php if ($row['status'] == 'Completed' && $row['handed_over_to']): ?>
                                <div class="task-meta"><i class="fa-solid fa-handshake me-1"></i> <?= htmlspecialchars($row['handed_over_to']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?= $methodClass ?>"><i class="fa-solid <?= $methodIcon ?> me-1"></i><?= htmlspecialchars($method) ?></span>
                        </td>
                        <td>
                            <span class="<?= $statusClass ?>">
                                <?php if ($row['status'] == 'Completed'): ?><i class="fa-solid fa-circle-check me-1"></i><?php endif; ?>
                                <?= htmlspecialchars($statusDisplay) ?>
                            </span>
                            <?php if ($status == 'Approved' && $row['status'] != 'Completed'): ?>
                                <div class="task-meta">Pending Dispatch</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small"><?= htmlspecialchars($row['creator_name'] ?? 'N/A') ?></div>
                            <div class="task-meta" title="<?= htmlspecialchars($row['created_at']) ?>"><?= time_elapsed_string($row['created_at']) ?></div>
                        </td>
                        <td>
                            <div class="action-group">
                                <a href="view_outward.php?id=<?= $row['id'] ?>" class="action-btn view-edit" title="View Details"><i class="fa-solid fa-eye"></i></a>
                                
                                <?php if ($row['status'] != 'Completed'): ?>
                                    <a href="edit_outward.php?id=<?= $row['id'] ?>" class="action-btn view-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <?php else: ?>
                                    <span class="action-btn view-edit" style="opacity:0.3;cursor:not-allowed;" title="Completed – cannot edit">
                                        <i class="fa-solid fa-lock"></i>
                                    </span>
                                <?php endif; ?>

                                <?php 
                                // PDF Preview Button – only if file exists
                                $file_path = $row['file_path'] ?? '';
                                if (!empty($file_path) && file_exists('../../' . $file_path)): ?>
                                    <a href="../../<?= htmlspecialchars($file_path) ?>" target="_blank" class="action-btn view-edit" title="Preview PDF">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($status == 'Draft' && $row['created_by'] == $_SESSION['user_id']): ?>
                                    <a href="?submit_draft=1&id=<?= $row['id'] ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="action-btn btn-submit-draft" onclick="return confirm('Submit this draft for approval?')">
                                        <i class="fa-solid fa-upload"></i> Submit
                                    </a>
                                <?php endif; ?>

                                <?php if ($canApprove && $status == 'Pending RA Approval'): ?>
                                    <a href="?approve=1&id=<?= $row['id'] ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="action-btn btn-approve" onclick="return confirm('Approve this outward letter?')">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </a>
                                    <a href="?reject=1&id=<?= $row['id'] ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="action-btn btn-reject" onclick="return confirm('Reject this outward letter?')">
                                        <i class="fa-solid fa-xmark"></i> Reject
                                    </a>
                                <?php endif; ?>

                                <?php if (($row['created_by'] == $_SESSION['user_id'] || $canResubmit) && $status == 'Rejected'): ?>
                                    <a href="?resubmit=1&id=<?= $row['id'] ?>&<?= $query_string ?>&per_page=<?= $per_page ?>" class="action-btn btn-resubmit" onclick="return confirm('Resubmit this letter for RA approval?')">
                                        <i class="fa-solid fa-rotate-right"></i> Resubmit
                                    </a>
                                <?php endif; ?>

                                <?php if ($canDispatch && $appointment == 'Dispatch Clerk' && $status == 'Approved' && $row['status'] != 'Completed'): ?>
                                    <button type="button" class="action-btn btn-dispatch" data-bs-toggle="modal" data-bs-target="#dispatchModal" data-id="<?= $row['id'] ?>">
                                        <i class="fa-solid fa-paper-plane"></i> Dispatch
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if($total_pages > 1): 
                $prevPage = max(1, $page - 1);
                $nextPage = min($total_pages, $page + 1);
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= buildPaginationUrl(1, $query_params, $per_page) ?>" class="page-link" title="First"><i class="fa-solid fa-angles-left"></i></a>
                    <a href="<?= buildPaginationUrl($prevPage, $query_params, $per_page) ?>" class="page-link" title="Previous"><i class="fa-solid fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-angles-left"></i></span>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php for($i=$start; $i<=$end; $i++): ?>
                    <a class="page-link <?= $i == $page ? 'active' : '' ?>" href="<?= buildPaginationUrl($i, $query_params, $per_page) ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= buildPaginationUrl($nextPage, $query_params, $per_page) ?>" class="page-link" title="Next"><i class="fa-solid fa-chevron-right"></i></a>
                    <a href="<?= buildPaginationUrl($total_pages, $query_params, $per_page) ?>" class="page-link" title="Last"><i class="fa-solid fa-angles-right"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                    <span class="page-link disabled"><i class="fa-solid fa-angles-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Dispatch Modal -->
<div class="modal fade" id="dispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Dispatch Outward Letter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="dispatchId" value="">
                    <div class="mb-3">
                        <label class="form-label fw-bold small --text-muted">Handed Over To <span class="text-danger">*</span></label>
                        <input type="text" name="handed_over_to" class="filter-control mt-1" placeholder="Enter name of person/entity" required>
                        <small class="--text-muted">Who is physically receiving this letter?</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small --text-muted">Remarks</label>
                        <textarea name="dispatch_remarks" class="filter-control mt-1" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                    <div class="alert alert-info border-0 rounded-3 mb-0">
                        <i class="fa-solid fa-circle-info me-2"></i> This action will mark the letter as <strong>Completed</strong>.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="dispatch" class="btn-filter"><i class="fa-solid fa-check me-2"></i>Confirm Dispatch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme initialization via local storage
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('show');
    }

    // Sidebar submenu toggle
    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        if(!sub) return;
        sub.classList.toggle('show');
    }

    // Modal ID assignment
    const dispatchModal = document.getElementById('dispatchModal');
    if (dispatchModal) {
        dispatchModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('dispatchId').value = button.getAttribute('data-id');
        });
    }

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }, 6000);
    });
</script>
</body>
</html>