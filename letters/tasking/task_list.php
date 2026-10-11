<?php

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_TASK_LETTERS')) {
    die('Access Denied!');
}

$userId   = $_SESSION['user_id'];
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// Ensure CSRF Token is set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---- Automatic Status Update Engine ----
$autoUpdateSql = "
    UPDATE task_letters t
    LEFT JOIN (
        SELECT task_letter_id, COUNT(*) AS assigned_count 
        FROM task_letter_agencies 
        GROUP BY task_letter_id
    ) a ON t.id = a.task_letter_id
    LEFT JOIN (
        SELECT task_letter_id, COUNT(DISTINCT agency_id) AS reply_count 
        FROM task_replies 
        GROUP BY task_letter_id
    ) r ON t.id = r.task_letter_id
    SET t.status = CASE 
        WHEN COALESCE(r.reply_count, 0) = 0 THEN 'Pending'
        WHEN COALESCE(a.assigned_count, 0) > 0 AND COALESCE(r.reply_count, 0) >= COALESCE(a.assigned_count, 0) THEN 'Completed'
        ELSE 'Partially Replied'
    END
    WHERE t.status IS NULL OR t.status = ''
";
$conn->query($autoUpdateSql);

// ---- AJAX Handler for Quick View ----
if (isset($_GET['ajax']) && $_GET['ajax'] == 1 && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("
        SELECT t.*, u.name_with_initial AS assigner_name, d.desk_name,
               (SELECT COUNT(*) FROM task_letter_agencies WHERE task_letter_id = t.id) AS assigned_count,
               (SELECT COUNT(*) FROM task_replies WHERE task_letter_id = t.id) AS reply_count,
               (SELECT COUNT(*) FROM task_replies WHERE task_letter_id = t.id AND status = 'Accepted') AS accepted_count,
               (SELECT GROUP_CONCAT(
                    CONCAT(r.regiment_name, '|', IF(EXISTS(SELECT 1 FROM task_replies tr WHERE tr.task_letter_id = t.id AND tr.regiment_id = r.id), 'Yes', 'No'))
                    SEPARATOR '||'
                )
                FROM task_letter_agencies tla 
                JOIN regiments r ON tla.regiment_id = r.id 
                WHERE tla.task_letter_id = t.id) AS agency_reply_status
        FROM task_letters t
        LEFT JOIN users u ON t.assigned_by = u.id
        LEFT JOIN desks d ON u.desk_id = d.id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Task not found']);
    }
    $stmt->close();
    exit;
}

// ---- Fetch Active Desks for Filter Dropdown ----
$desks_result = $conn->query("SELECT id, desk_name FROM desks WHERE is_deleted = 0 AND status = 'Active' ORDER BY desk_name ASC");

// ---- Search & Filter Parameters Setup ----
$search          = trim($_GET['search'] ?? '');
$status_filter   = trim($_GET['status'] ?? '');
$priority_filter = trim($_GET['priority'] ?? '');
$desk_filter     = trim($_GET['desk'] ?? '');
$my_tasks        = isset($_GET['my_tasks']) && $_GET['my_tasks'] == '1' ? 1 : 0;
$date_from       = trim($_GET['date_from'] ?? '');
$date_to         = trim($_GET['date_to'] ?? '');

$whereConditions = [];
$queryParams     = [];
$paramTypes      = "";

// My Tasks Filter logic
if ($my_tasks === 1) {
    $whereConditions[] = "t.assigned_by = ?";
    $queryParams[]     = $userId;
    $paramTypes       .= "i";
}

if (!empty($search)) {
    $whereConditions[] = "(t.letter_ref LIKE ? OR t.title LIKE ? OR t.tsk_cdr_number LIKE ? OR t.description LIKE ? OR t.about_letter LIKE ?)";
    $searchWildcard    = '%' . $search . '%';
    $queryParams       = array_merge($queryParams, [$searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard]);
    $paramTypes       .= "sssss";
}

if (!empty($status_filter)) {
    if ($status_filter === 'overdue') {
        $whereConditions[] = "(t.request_date < CURDATE() AND t.status != 'Completed')";
    } elseif ($status_filter === 'pending') {
        $whereConditions[] = "(t.status = 'Pending' OR t.status = '' OR t.status IS NULL)";
    } elseif ($status_filter === 'partial') {
        $whereConditions[] = "t.status = 'Partially Replied'";
    } elseif ($status_filter === 'completed') {
        $whereConditions[] = "t.status = 'Completed'";
    }
}

if (!empty($priority_filter) && in_array($priority_filter, ['High', 'Medium', 'Low'])) {
    $whereConditions[] = "t.priority_status = ?";
    $queryParams[]     = $priority_filter;
    $paramTypes       .= "s";
}

if (!empty($desk_filter)) {
    $whereConditions[] = "u.desk_id = ?";
    $queryParams[]     = $desk_filter;
    $paramTypes       .= "i";
}

if (!empty($date_from)) {
    $whereConditions[] = "t.request_date >= ?";
    $queryParams[]     = $date_from;
    $paramTypes       .= "s";
}

if (!empty($date_to)) {
    $whereConditions[] = "t.request_date <= ?";
    $queryParams[]     = $date_to;
    $paramTypes       .= "s";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// ---- CSV / Excel Export Handler ----
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xlsx'])) {
    $sql = "SELECT DISTINCT t.*, u.name_with_initial AS assigner_name, d.desk_name,
                   (SELECT GROUP_CONCAT(
                        CONCAT(r.regiment_name, ' (', IF(EXISTS(SELECT 1 FROM task_replies tr WHERE tr.task_letter_id = t.id AND tr.regiment_id = r.id), 'Yes', 'No'), ')')
                        SEPARATOR ', '
                    )
                    FROM task_letter_agencies tla 
                    JOIN regiments r ON tla.regiment_id = r.id 
                    WHERE tla.task_letter_id = t.id) AS agency_reply_status
            FROM task_letters t
            LEFT JOIN users u ON t.assigned_by = u.id
            LEFT JOIN desks d ON u.desk_id = d.id
            $whereClause
            ORDER BY t.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($queryParams)) {
        $stmt->bind_param($paramTypes, ...$queryParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $headers = [
        'ID', 'Letter Reference', 'CDR Number', 'Letter Date', 'Title', 'Description',
        'Status', 'Priority', 'Request Date', 'Desk', 'Assigned By', 'Agencies (Reply Status)', 'Created At'
    ];

    $format = $_GET['export'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=tasks_export_' . date('Y-m-d_H-i-s') . '.csv');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, $headers);

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['letter_ref'],
                $row['tsk_cdr_number'],
                $row['letter_date'],
                $row['title'],
                $row['description'],
                $row['status'] ?: 'Pending',
                $row['priority_status'] ?: 'Medium',
                $row['request_date'],
                $row['desk_name'],
                $row['assigner_name'],
                $row['agency_reply_status'] ?: 'None',
                $row['created_at']
            ]);
        }
        fclose($output);
        $stmt->close();
        exit;
    }

    if ($format === 'xlsx') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename=tasks_export_' . date('Y-m-d_H-i-s') . '.xls');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        echo '<tr>';
        foreach ($headers as $h) {
            echo '<th style="font-weight:bold;">' . htmlspecialchars($h) . '</th>';
        }
        echo '</tr>';
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['letter_ref']) . '</td>';
            echo '<td>' . htmlspecialchars($row['tsk_cdr_number']) . '</td>';
            echo '<td>' . $row['letter_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['title']) . '</td>';
            echo '<td>' . htmlspecialchars($row['description']) . '</td>';
            echo '<td>' . htmlspecialchars($row['status'] ?: 'Pending') . '</td>';
            echo '<td>' . htmlspecialchars($row['priority_status'] ?: 'Medium') . '</td>';
            echo '<td>' . $row['request_date'] . '</td>';
            echo '<td>' . htmlspecialchars($row['desk_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['assigner_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['agency_reply_status'] ?: 'None') . '</td>';
            echo '<td>' . $row['created_at'] . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

// ---- Main Query & Sorting Setup ----
$sort_by    = $_GET['sort_by'] ?? 'request_date';
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
$allowed_sort = ['id', 'letter_ref', 'tsk_cdr_number', 'letter_date', 'request_date', 'title', 'priority_status', 'status', 'created_at'];
if (!in_array($sort_by, $allowed_sort)) { $sort_by = 'request_date'; }
$order_clause = "ORDER BY t.$sort_by $sort_order";

$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 15;
$allowed_limits = [10, 15, 25, 50];
if (!in_array($limit, $allowed_limits)) { $limit = 15; }
$offset = ($page - 1) * $limit;

// Count total matching records
$count_sql = "SELECT COUNT(DISTINCT t.id) as total FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id $whereClause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($queryParams)) {
    $count_stmt->bind_param($paramTypes, ...$queryParams);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Query page data
$sql = "
    SELECT DISTINCT t.*, u.name_with_initial AS assigner_name, d.desk_name,
           (SELECT COUNT(*) FROM task_letter_agencies WHERE task_letter_id = t.id) AS assigned_count,
           (SELECT COUNT(*) FROM task_replies WHERE task_letter_id = t.id) AS reply_count,
           (SELECT COUNT(*) FROM task_replies WHERE task_letter_id = t.id AND status = 'Accepted') AS accepted_count,
           (SELECT GROUP_CONCAT(
                CONCAT(r.regiment_name, '|', IF(EXISTS(SELECT 1 FROM task_replies tr WHERE tr.task_letter_id = t.id AND tr.regiment_id = r.id), 'Yes', 'No'))
                SEPARATOR '||'
            )
            FROM task_letter_agencies tla 
            JOIN regiments r ON tla.regiment_id = r.id 
            WHERE tla.task_letter_id = t.id) AS agency_reply_status
    FROM task_letters t
    LEFT JOIN users u ON t.assigned_by = u.id
    LEFT JOIN desks d ON u.desk_id = d.id
    $whereClause
    $order_clause
    LIMIT ? OFFSET ?
";

$main_stmt = $conn->prepare($sql);
$mainParamTypes = $paramTypes . "ii";
$mainQueryParams = array_merge($queryParams, [$limit, $offset]);
$main_stmt->bind_param($mainParamTypes, ...$mainQueryParams);
$main_stmt->execute();
$result = $main_stmt->get_result();

// Stats calculation
$stats = [];
$stats['total']         = $conn->query("SELECT COUNT(*) FROM task_letters")->fetch_row()[0];
$stats['pending']       = $conn->query("SELECT COUNT(*) FROM task_letters WHERE status = 'Pending' OR status = '' OR status IS NULL")->fetch_row()[0];
$stats['partial']       = $conn->query("SELECT COUNT(*) FROM task_letters WHERE status = 'Partially Replied'")->fetch_row()[0];
$stats['completed']     = $conn->query("SELECT COUNT(*) FROM task_letters WHERE status = 'Completed'")->fetch_row()[0];
$stats['overdue']       = $conn->query("SELECT COUNT(*) FROM task_letters WHERE request_date < CURDATE() AND status != 'Completed'")->fetch_row()[0];
$stats['high_priority'] = $conn->query("SELECT COUNT(*) FROM task_letters WHERE priority_status = 'High' AND status != 'Completed'")->fetch_row()[0];

// ---- Helper function to build pagination URL ----
function buildPaginationUrl($pageNum, $currentParams) {
    $params = $currentParams;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task List | IDCMS</title>
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
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); 
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
        }
        .btn-filter:hover { opacity: 0.9; transform: translateY(-2px); }

        .btn-toggle { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-muted); 
            border-radius: 10px; 
            padding: 8px 16px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            cursor: pointer; 
            text-decoration: none; 
        }
        .btn-toggle.active { background: var(--accent-soft); border-color: var(--accent); color: var(--accent); }

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
            width: 350px; 
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
        
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; min-width: 800px; }
        .custom-table thead th { border: none; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; padding: 10px 20px; }
        .custom-table tbody tr { background: var(--bg-body); transition: 0.2s; border-radius: 15px; }
        .custom-table tbody tr:hover { transform: translateX(5px); box-shadow: -5px 0 0 var(--accent); }
        .custom-table tbody td { padding: 18px 20px; border: none; vertical-align: middle; color: var(--text-main); }
        .custom-table tbody td:first-child { border-radius: 15px 0 0 15px; }
        .custom-table tbody td:last-child { border-radius: 0 15px 15px 0; }
        
        .badge-status { padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; display: inline-block; }
        .status-pending { background: rgba(255, 171, 0, 0.15); color: #FFAB00; }
        .status-partial { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        .status-completed { background: rgba(0, 200, 83, 0.15); color: #00C853; }
        
        .badge-priority { padding: 6px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; display: inline-block; }
        .priority-high { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .priority-medium { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .priority-low { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }

        .action-group { display: flex; gap: 5px; justify-content: flex-end; }
        .action-btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: var(--accent-soft); color: var(--accent); transition: 0.2s; text-decoration: none; font-size: 0.9rem; }
        .action-btn:hover { background: var(--accent); color: white; transform: translateY(-2px); }
        .task-ref { font-weight: 700; color: var(--accent); font-size: 0.95rem; text-decoration: none; }
        .task-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 25px; flex-wrap: wrap; }
        .page-link { display: flex; align-items: center; justify-content: center; min-width: 40px; height: 40px; padding: 0 12px; border-radius: 12px; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .page-link:hover, .page-link.active { background: var(--accent); border-color: var(--accent); color: white; }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        
        .top-controls-bar { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 16px; 
            padding: 15px 20px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .top-controls-bar .info-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .top-controls-bar .info-text strong {
            color: var(--text-main);
            font-weight: 700;
        }
        .top-controls-bar .right-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .page-size-selector select {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 8px;
            color: var(--text-main);
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
        }
        .page-size-selector select:focus {
            border-color: var(--accent);
        }

        .sort-link { color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .sort-link:hover, .sort-link.active { color: var(--accent); }
        
        .modal-content { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 16px; }
        .modal-header, .modal-footer { border-color: var(--border); }
        .btn-close { filter: invert(1); }
        .task-detail-item { padding: 8px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .task-detail-item:last-child { border-bottom: none; }
        .task-detail-label { font-weight: 600; color: var(--text-muted); }

        @keyframes pulseRed { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        @keyframes pulseYellow { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .due-date.due-overdue { color: var(--danger); font-weight: 700; animation: pulseRed 1.8s ease-in-out infinite; }
        .due-date.due-upcoming { color: var(--warning); font-weight: 700; animation: pulseYellow 1.8s ease-in-out infinite; }

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
            .top-controls-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .top-controls-bar .right-controls {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
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
        </div>
    </div>

    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-house"></i> Home Page</a>
        
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 25px 0 10px 15px; opacity: 0.6; letter-spacing: 1px;">Management</div>
        
        <button class="nav-btn active" onclick="toggleSubmenu('taskSub')">
            <i class="fa-solid fa-list-check"></i> Tasking Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
        </button>
        <div id="taskSub" class="submenu show">
            <a href="task_letters.php" class="sub-nav-btn">Add New Task</a>
            <a href="task_list.php" class="sub-nav-btn active-sub">View Tasking</a>
            <a href="../../letters/dashboard/task_dashboard.php" class="sub-nav-btn">Task Dashboard</a>
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
            <h4 class="fw-bold mb-0">Task Letters Management</h4>
            <p class="small --text-muted mb-0">View and manage all assigned task letters</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="fw-bold"><?= htmlspecialchars($userRank . ' ' . $userName) ?></div>
            </div>
            <form method="GET" action="task_list.php" class="search-box">
                <i class="fa-solid fa-magnifying-glass --text-muted"></i>
                <input type="text" name="search" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>">
                <?php
                foreach (['status','priority','desk','my_tasks','date_from','date_to','sort_by','sort_order','limit'] as $p) {
                    if (!empty($_GET[$p])) echo '<input type="hidden" name="'.$p.'" value="'.htmlspecialchars($_GET[$p]).'">';
                }
                ?>
            </form>
        </div>
    </header>

    <div class="content-container">
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-pill <?= empty($status_filter) ? 'active' : '' ?>" onclick="filterByStatus('')">
                <div class="stat-icon-sm" style="background: var(--accent);"><i class="fa-solid fa-envelope"></i></div>
                <div class="stat-info-sm"><h6>Total</h6><div class="number"><?= $stats['total'] ?></div></div>
            </div>
            <div class="stat-pill <?= $status_filter == 'pending' ? 'active' : '' ?>" onclick="filterByStatus('pending')">
                <div class="stat-icon-sm" style="background: var(--warning);"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info-sm"><h6>Pending</h6><div class="number"><?= $stats['pending'] ?></div></div>
            </div>
            <div class="stat-pill <?= $status_filter == 'partial' ? 'active' : '' ?>" onclick="filterByStatus('partial')">
                <div class="stat-icon-sm" style="background: var(--purple);"><i class="fa-solid fa-reply-all"></i></div>
                <div class="stat-info-sm"><h6>Partial</h6><div class="number"><?= $stats['partial'] ?></div></div>
            </div>
            <div class="stat-pill <?= $status_filter == 'completed' ? 'active' : '' ?>" onclick="filterByStatus('completed')">
                <div class="stat-icon-sm" style="background: var(--success);"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-info-sm"><h6>Completed</h6><div class="number"><?= $stats['completed'] ?></div></div>
            </div>
            <div class="stat-pill <?= $status_filter == 'overdue' ? 'active' : '' ?>" onclick="filterByStatus('overdue')">
                <div class="stat-icon-sm" style="background: var(--danger);"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div class="stat-info-sm"><h6>Overdue</h6><div class="number"><?= $stats['overdue'] ?></div></div>
            </div>
            <div class="stat-pill <?= $priority_filter == 'High' ? 'active' : '' ?>" onclick="filterByPriority('High')">
                <div class="stat-icon-sm" style="background: var(--danger);"><i class="fa-solid fa-flag"></i></div>
                <div class="stat-info-sm"><h6>High Priority</h6><div class="number"><?= $stats['high_priority'] ?></div></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label><i class="fa-regular fa-circle me-1"></i>Status</label>
                    <select name="status" class="filter-control">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="partial" <?= $status_filter == 'partial' ? 'selected' : '' ?>>Partially Replied</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="overdue" <?= $status_filter == 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-bolt me-1"></i>Priority</label>
                    <select name="priority" class="filter-control">
                        <option value="">All Priority</option>
                        <option value="High" <?= $priority_filter == 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $priority_filter == 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $priority_filter == 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-building me-1"></i>Desk</label>
                    <select name="desk" class="filter-control">
                        <option value="">All Desks</option>
                        <?php if ($desks_result && $desks_result->num_rows > 0): ?>
                            <?php while($desk_row = $desks_result->fetch_assoc()): ?>
                                <option value="<?= $desk_row['id'] ?>" <?= $desk_filter == $desk_row['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($desk_row['desk_name']) ?>
                                </option>
                            <?php endwhile; ?>
                            <?php $desks_result->data_seek(0); ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar me-1"></i>From</label>
                    <input type="date" name="date_from" class="filter-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fa-regular fa-calendar me-1"></i>To</label>
                    <input type="date" name="date_to" class="filter-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <label><i class="fa-solid fa-user-check me-1"></i>Ownership</label>
                    <a href="javascript:void(0)" onclick="toggleMyTasks()" class="btn-toggle <?= $my_tasks === 1 ? 'active' : '' ?>">
                        <i class="fa-solid <?= $my_tasks === 1 ? 'fa-user-check' : 'fa-users' ?>"></i> My Tasks Only
                    </a>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
                </div>
                <div class="filter-group" style="flex: 0 0 auto;">
                    <a href="task_list.php" class="btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
                </div>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <input type="hidden" name="my_tasks" id="my_tasks_input" value="<?= $my_tasks ?>">
                <input type="hidden" name="sort_by" value="<?= htmlspecialchars($sort_by) ?>">
                <input type="hidden" name="sort_order" value="<?= htmlspecialchars($sort_order) ?>">
                <input type="hidden" name="limit" value="<?= $limit ?>">
            </form>
        </div>

        <!-- Top Controls Bar -->
        <div class="top-controls-bar">
            <div class="info-text">
                <i class="fa-regular fa-file-lines me-1"></i>
                Showing 
                <strong><?= $total_rows > 0 ? $offset + 1 : 0 ?></strong> 
                – 
                <strong><?= min($offset + $limit, $total_rows) ?></strong> 
                of 
                <strong><?= $total_rows ?></strong> 
                entries
            </div>
            <div class="right-controls">
                <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'xlsx'])) ?>" class="btn-filter" style="background: #1e7e34; font-size: 0.85rem;">
                    <i class="fa-solid fa-file-excel me-1"></i> Excel
                </a>
                <div class="page-size-selector">
                    <span>Per page:</span>
                    <select onchange="changePageSize(this.value)">
                        <option value="10" <?= $limit==10?'selected':'' ?>>10</option>
                        <option value="15" <?= $limit==15?'selected':'' ?>>15</option>
                        <option value="25" <?= $limit==25?'selected':'' ?>>25</option>
                        <option value="50" <?= $limit==50?'selected':'' ?>>50</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-header">
                <h5><i class="fa-solid fa-list-check"></i> Task Letters</h5>
                <span class="result-count">
                    <i class="fa-regular fa-file-lines me-1"></i>
                    <?= $total_rows > 0 ? $offset + 1 : 0 ?> – <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?>
                </span>
            </div>

            <table class="custom-table">
                <thead>
                    <tr>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='tsk_cdr_number'?'active':'' ?>" data-sort="tsk_cdr_number">
                                Task CDR <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='letter_ref'?'active':'' ?>" data-sort="letter_ref">
                                Letter Ref <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='title'?'active':'' ?>" data-sort="title">
                                Title & Assignment Details <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='request_date'?'active':'' ?>" data-sort="request_date">
                                Due Date <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='priority_status'?'active':'' ?>" data-sort="priority_status">
                                Priority <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link text-uppercase" style="color:var(--text-muted); text-decoration:none;">
                                Agencies
                            </a>
                        </th>
                        <th>
                            <a href="#" class="sort-link <?= $sort_by=='status'?'active':'' ?>" data-sort="status">
                                Status <i class="fa-solid fa-sort"></i>
                            </a>
                        </th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $dueDate = $row['request_date'];
                            $isOverdue = (!empty($dueDate) && strtotime($dueDate) < time() && $row['status'] != 'Completed');
                            $daysDiff = !empty($dueDate) ? floor((strtotime($dueDate) - time()) / 86400) : null;
                            $isUpcoming = (!$isOverdue && $daysDiff !== null && $daysDiff >= 0 && $daysDiff <= 2 && $row['status'] != 'Completed');
                            
                            $daysOverdue = $isOverdue ? abs($daysDiff) : 0;
                            $priority    = $row['priority_status'] ?: 'Medium';
                            $status      = $row['status'] ?: 'Pending';
                            
                            $dueClass = '';
                            if ($isOverdue) $dueClass = 'due-overdue';
                            elseif ($isUpcoming) $dueClass = 'due-upcoming';

                            // Parse agency list for individual badges
                            $agencyItems = [];
                            if (!empty($row['agency_reply_status'])) {
                                $parts = explode('||', $row['agency_reply_status']);
                                foreach ($parts as $part) {
                                    $split = explode('|', $part, 2);
                                    if (count($split) === 2) {
                                        $agencyItems[] = ['name' => $split[0], 'replied' => $split[1] === 'Yes'];
                                    }
                                }
                            }
                        ?>
                        <tr>
                            <td>
                                <a href="#" class="task-ref" onclick="openTaskModal(<?= $row['id'] ?>); return false;">
                                    <?= htmlspecialchars($row['tsk_cdr_number'] ?: 'N/A') ?>
                                </a>
                                <div class="task-meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($row['created_at'])) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-info"><?= htmlspecialchars($row['letter_ref'] ?: 'N/A') ?></span>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['title']) ?></div>
                                <div class="task-meta">
                                    <span><i class="fa-regular fa-building text-primary"></i> <strong>Desk:</strong> <?= htmlspecialchars($row['desk_name'] ?? 'N/A') ?></span>
                                    <span><i class="fa-regular fa-user text-success"></i> <strong>By:</strong> <?= htmlspecialchars($row['assigner_name'] ?? 'N/A') ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($dueDate)): ?>
                                    <span class="due-date <?= $dueClass ?>">
                                        <?= date('d M Y', strtotime($dueDate)) ?>
                                        <?php if ($isOverdue): ?> 
                                            <span class="badge bg-danger text-white ms-1"><?= $daysOverdue ?>d overdue</span>
                                        <?php elseif ($isUpcoming): ?>
                                            <span class="badge bg-warning text-dark ms-1"><?= $daysDiff ?>d left</span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="--text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $priorityClass = 'priority-medium';
                                    if ($priority === 'High') $priorityClass = 'priority-high';
                                    elseif ($priority === 'Low') $priorityClass = 'priority-low';
                                ?>
                                <span class="badge-priority <?= $priorityClass ?>">
                                    <?= htmlspecialchars($priority) ?>
                                </span>
                            </td>
                            <!-- AGENCIES COLUMN – Individual status badges with explicit RGBA colors -->
                            <td>
                                <?php if (empty($agencyItems)): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                        <?php foreach ($agencyItems as $item): 
                                            $bgCol = $item['replied'] ? 'rgba(0, 200, 83, 0.15)' : 'rgba(255, 171, 0, 0.15)';
                                            $textCol = $item['replied'] ? '#00C853' : '#FFAB00';
                                        ?>
                                            <span class="badge" 
                                                  style="background: <?= $bgCol ?>; color: <?= $textCol ?>; font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 6px;"
                                                  title="<?= htmlspecialchars($item['name']) ?>: <?= $item['replied'] ? 'Replied' : 'Pending' ?>">
                                                <?= htmlspecialchars($item['name']) ?> 
                                                <i class="fa-solid <?= $item['replied'] ? 'fa-check' : 'fa-clock' ?> ms-1"></i>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    $statusClass = 'status-pending';
                                    if ($status === 'Partially Replied') $statusClass = 'status-partial';
                                    elseif ($status === 'Completed') $statusClass = 'status-completed';
                                ?>
                                <span class="badge-status <?= $statusClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="action-group">
                                    <?php if (hasPermission('VIEW_TASK')): ?>
                                        <a href="#" class="action-btn view" onclick="openTaskModal(<?= $row['id'] ?>); return false;" title="Quick View">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('VIEW_TASK')): ?>
                                        <a href="view_task.php?id=<?= $row['id'] ?>" class="action-btn view" title="Full View">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('REPLY_TASK')): ?>
                                        <a href="reply_task.php?task_id=<?= $row['id'] ?>" class="action-btn reply" title="Submit Reply">
                                            <i class="fa-solid fa-reply"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (hasPermission('EDIT_TASK')): ?>
                                        <a href="edit_task.php?id=<?= $row['id'] ?>" class="action-btn edit" title="Edit Task">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div style="font-size:3rem;color:var(--text-muted);opacity:0.3;margin-bottom:15px;">
                                    <i class="fa-regular fa-file-lines"></i>
                                </div>
                                <h6 class="--text-muted">No tasks found</h6>
                                <p class="small --text-muted">Try adjusting your filters or create a new task</p>
                                <a href="task_letters.php" class="btn-filter mt-3" style="display:inline-flex;">
                                    <i class="fa-solid fa-plus-circle me-2"></i>Create New Task
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_rows > $limit): 
                $total_pages = ceil($total_rows / $limit);
                $query_params = $_GET;
                $prevPage = max(1, $page - 1);
                $nextPage = min($total_pages, $page + 1);

                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);
                if ($end - $start < $range * 2) {
                    if ($start == 1) $end = min($total_pages, $start + $range * 2);
                    elseif ($end == $total_pages) $start = max(1, $end - $range * 2);
                }
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= buildPaginationUrl(1, $query_params) ?>" class="page-link" title="First"><i class="fa-solid fa-angles-left"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-angles-left"></i></span>
                <?php endif; ?>

                <?php if ($page > 1): ?>
                    <a href="<?= buildPaginationUrl($prevPage, $query_params) ?>" class="page-link" title="Previous"><i class="fa-solid fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php if ($start > 1): ?>
                    <a href="<?= buildPaginationUrl(1, $query_params) ?>" class="page-link">1</a>
                    <?php if ($start > 2): ?>
                        <span class="page-link disabled">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?= buildPaginationUrl($i, $query_params) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="page-link disabled">…</span>
                    <?php endif; ?>
                    <a href="<?= buildPaginationUrl($total_pages, $query_params) ?>" class="page-link"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= buildPaginationUrl($nextPage, $query_params) ?>" class="page-link" title="Next"><i class="fa-solid fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-chevron-right"></i></span>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= buildPaginationUrl($total_pages, $query_params) ?>" class="page-link" title="Last"><i class="fa-solid fa-angles-right"></i></a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fa-solid fa-angles-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Quick View Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskModalBody">
                <div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="modalFullViewBtn" class="btn btn-primary">Full View</a>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    const refInput = document.getElementById('letter_reference');
    if (refInput) {
        refInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\s/g, '');
        });
    }

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('show');
    }

    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        submenu.style.display = (submenu.style.display === "block") ? "none" : "block";
    }

    function filterByStatus(status) {
        const urlParams = new URLSearchParams(window.location.search);
        if (status) {
            urlParams.set('status', status);
        } else {
            urlParams.delete('status');
        }
        urlParams.set('page', '1');
        window.location.search = urlParams.toString();
    }

    function filterByPriority(priority) {
        const urlParams = new URLSearchParams(window.location.search);
        if (priority) {
            urlParams.set('priority', priority);
        } else {
            urlParams.delete('priority');
        }
        urlParams.set('page', '1');
        window.location.search = urlParams.toString();
    }

    function toggleMyTasks() {
        const myTasksInput = document.getElementById('my_tasks_input');
        const current = myTasksInput.value;
        myTasksInput.value = (current === '1') ? '0' : '1';
        document.getElementById('filterForm').submit();
    }

    function changePageSize(limit) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('limit', limit);
        urlParams.set('page', '1');
        window.location.search = urlParams.toString();
    }

    function openTaskModal(taskId) {
        const modal = new bootstrap.Modal(document.getElementById('taskModal'));
        const modalBody = document.getElementById('taskModalBody');
        const modalFullViewBtn = document.getElementById('modalFullViewBtn');
        
        modalBody.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';
        modalFullViewBtn.href = 'view_task.php?id=' + taskId;
        modal.show();

        fetch('task_list.php?ajax=1&id=' + taskId)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    modalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                    return;
                }
                // Parse agency list for individual badges in modal display with explicit colors
                let agencyHtml = '—';
                if (data.agency_reply_status) {
                    const items = data.agency_reply_status.split('||').map(p => {
                        const parts = p.split('|');
                        if (parts.length === 2) {
                            const [name, reply] = parts;
                            const bgCol = reply === 'Yes' ? 'rgba(0, 200, 83, 0.15)' : 'rgba(255, 171, 0, 0.15)';
                            const textCol = reply === 'Yes' ? '#00C853' : '#FFAB00';
                            const icon = reply === 'Yes' ? 'fa-check' : 'fa-clock';
                            return `<span class="badge" style="background: ${bgCol}; color: ${textCol}; font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; display: inline-block; margin: 2px;">${name} <i class="fa-solid ${icon} ms-1"></i></span>`;
                        }
                        return '';
                    });
                    agencyHtml = `<div class="d-flex flex-wrap gap-1">${items.join('')}</div>`;
                }
                modalBody.innerHTML = `
                    <div class="task-detail-item">
                        <span class="task-detail-label">Task CDR Number</span>
                        <span class="fw-bold">${data.tsk_cdr_number || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Letter Reference</span>
                        <span class="fw-bold text-info">${data.letter_ref || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Title</span>
                        <span class="fw-bold">${data.title || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Description</span>
                        <span>${data.description || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Desk</span>
                        <span class="fw-bold text-primary">${data.desk_name || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Assigned By</span>
                        <span class="fw-bold text-success">${data.assigner_name || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Agencies (Reply status)</span>
                        <div>${agencyHtml}</div>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Status</span>
                        <span class="badge-status status-${(data.status || 'Pending').toLowerCase().replace(' ', '-')}">${data.status || 'Pending'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Priority</span>
                        <span class="badge-priority priority-${(data.priority_status || 'Medium').toLowerCase()}">${data.priority_status || 'Medium'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Request Due Date</span>
                        <span>${data.request_date || 'N/A'}</span>
                    </div>
                    <div class="task-detail-item">
                        <span class="task-detail-label">Created At</span>
                        <span>${data.created_at || 'N/A'}</span>
                    </div>
                `;
            })
            .catch(err => {
                modalBody.innerHTML = `<div class="alert alert-danger">Failed to load task details.</div>`;
            });
    }

    document.querySelectorAll('.sort-link[data-sort]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sortBy = this.getAttribute('data-sort');
            const urlParams = new URLSearchParams(window.location.search);
            const currentSortBy = urlParams.get('sort_by');
            let currentOrder = urlParams.get('sort_order') || 'ASC';

            if (currentSortBy === sortBy) {
                currentOrder = (currentOrder.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
            } else {
                currentOrder = 'ASC';
            }

            urlParams.set('sort_by', sortBy);
            urlParams.set('sort_order', currentOrder);
            urlParams.set('page', '1');
            window.location.search = urlParams.toString();
        });
    });
</script>
</body>
</html>