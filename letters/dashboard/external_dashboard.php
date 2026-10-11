<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// ─── FILTERS ──────────────────────────────────────────────────
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_approval = isset($_GET['approval_status']) ? $_GET['approval_status'] : '';
$filter_method  = isset($_GET['dispatch_method']) ? $_GET['dispatch_method'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to   = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_desk      = isset($_GET['desk_id']) ? (int)$_GET['desk_id'] : 0;

$where = "ol.is_deleted = 0";
$params = [];
$types = "";

if (!empty($filter_status)) {
    $where .= " AND ol.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($filter_approval)) {
    $where .= " AND ol.approval_status = ?";
    $params[] = $filter_approval;
    $types .= "s";
}
if (!empty($filter_method)) {
    $where .= " AND ol.dispatch_method = ?";
    $params[] = $filter_method;
    $types .= "s";
}
if (!empty($filter_date_from)) {
    $where .= " AND DATE(ol.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $where .= " AND DATE(ol.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

// ─── Desk filter helper ─────────────────────────────────────
$deskJoin = '';
$deskCondition = '';
$deskParam = null;
if ($filter_desk > 0) {
    $deskJoin = "JOIN users u ON ol.created_by = u.id";
    $deskCondition = "AND u.desk_id = ?";
    $deskParam = $filter_desk;
    // Add desk param to the main $params array for queries that use $where directly
    // but we must be careful: some queries already join users and we need to add the condition there.
    // We'll handle each query individually.
}

// We'll keep a separate copy of $params for each query because some queries may have their own additional joins and params.
// To avoid complexity, we'll rebuild the parameter array for each query.

function runFilteredQuery($conn, $sql, $params, $types) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Helper to build common WHERE with desk filter for queries that join users as "u"
function buildWhereWithDesk($where, $deskParam) {
    if ($deskParam) {
        return $where . " AND u.desk_id = ?";
    }
    return $where;
}

// ─── STATS ────────────────────────────────────────────────────
// Need to join users if desk filter is set
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN ol.status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN ol.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN ol.approval_status = 'Pending RA Approval' THEN 1 ELSE 0 END) as pending_approval,
    SUM(CASE WHEN ol.approval_status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN ol.approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
FROM outward_letters ol
";
if ($filter_desk > 0) {
    $statsSql .= "JOIN users u ON ol.created_by = u.id ";
}
$statsSql .= "WHERE $where";
if ($filter_desk > 0) {
    $statsSql .= " AND u.desk_id = ?";
}

$statsParams = $params;
$statsTypes = $types;
if ($filter_desk > 0) {
    $statsParams[] = $filter_desk;
    $statsTypes .= "i";
}
$result = runFilteredQuery($conn, $statsSql, $statsParams, $statsTypes);
$masterStats = $result->fetch_assoc();

// ─── STATUS DISTRIBUTION ────────────────────────────────────
$statusSql = "SELECT ol.status, COUNT(*) as count FROM outward_letters ol ";
if ($filter_desk > 0) {
    $statusSql .= "JOIN users u ON ol.created_by = u.id ";
}
$statusSql .= "WHERE $where";
if ($filter_desk > 0) {
    $statusSql .= " AND u.desk_id = ?";
}
$statusSql .= " GROUP BY ol.status";
$statusParams = $params;
$statusTypes = $types;
if ($filter_desk > 0) {
    $statusParams[] = $filter_desk;
    $statusTypes .= "i";
}
$result = runFilteredQuery($conn, $statusSql, $statusParams, $statusTypes);
$statusCounts = [];
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}
$statusLabels = array_keys($statusCounts);
$statusData = array_values($statusCounts);
$statusColors = [];
foreach ($statusLabels as $label) {
    if ($label == 'Pending') $statusColors[] = '#6c757d';
    elseif ($label == 'Completed') $statusColors[] = '#198754';
    else $statusColors[] = '#0d6efd';
}

// ─── APPROVAL STATUS ────────────────────────────────────────
$approvalSql = "SELECT approval_status, COUNT(*) as count FROM outward_letters ol ";
if ($filter_desk > 0) {
    $approvalSql .= "JOIN users u ON ol.created_by = u.id ";
}
$approvalSql .= "WHERE $where";
if ($filter_desk > 0) {
    $approvalSql .= " AND u.desk_id = ?";
}
$approvalSql .= " GROUP BY approval_status";
$approvalParams = $params;
$approvalTypes = $types;
if ($filter_desk > 0) {
    $approvalParams[] = $filter_desk;
    $approvalTypes .= "i";
}
$result = runFilteredQuery($conn, $approvalSql, $approvalParams, $approvalTypes);
$approvalCounts = [];
while ($row = $result->fetch_assoc()) {
    $approvalCounts[$row['approval_status']] = $row['count'];
}
$approvalLabels = ['Pending RA Approval', 'Approved', 'Rejected'];
$approvalData = [];
foreach ($approvalLabels as $label) {
    $approvalData[] = $approvalCounts[$label] ?? 0;
}
$approvalColors = ['#ffc107', '#198754', '#ef4444'];

// ─── DISPATCH METHOD ────────────────────────────────────────
$methodSql = "SELECT dispatch_method, COUNT(*) as count FROM outward_letters ol ";
if ($filter_desk > 0) {
    $methodSql .= "JOIN users u ON ol.created_by = u.id ";
}
$methodSql .= "WHERE $where";
if ($filter_desk > 0) {
    $methodSql .= " AND u.desk_id = ?";
}
$methodSql .= " GROUP BY dispatch_method";
$methodParams = $params;
$methodTypes = $types;
if ($filter_desk > 0) {
    $methodParams[] = $filter_desk;
    $methodTypes .= "i";
}
$result = runFilteredQuery($conn, $methodSql, $methodParams, $methodTypes);
$methodCounts = [];
while ($row = $result->fetch_assoc()) {
    $methodCounts[$row['dispatch_method']] = $row['count'];
}
$methodLabels = ['By Hand', 'Email','Courier','Post'];
$methodData = [];
foreach ($methodLabels as $label) {
    $methodData[] = $methodCounts[$label] ?? 0;
}
$methodColors = ['#8b5cf6', '#3b82f6'];

// ─── MONTHLY TREND ──────────────────────────────────────────
$trendSql = "SELECT 
    DATE_FORMAT(ol.created_at, '%Y-%m') as month,
    DATE_FORMAT(ol.created_at, '%b %Y') as month_label,
    COUNT(*) as total,
    SUM(CASE WHEN ol.status = 'Completed' THEN 1 ELSE 0 END) as completed
FROM outward_letters ol ";
if ($filter_desk > 0) {
    $trendSql .= "JOIN users u ON ol.created_by = u.id ";
}
$trendSql .= "WHERE $where AND ol.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";
if ($filter_desk > 0) {
    $trendSql .= " AND u.desk_id = ?";
}
$trendSql .= " GROUP BY month ORDER BY month DESC";
$trendParams = $params;
$trendTypes = $types;
if ($filter_desk > 0) {
    $trendParams[] = $filter_desk;
    $trendTypes .= "i";
}
$result = runFilteredQuery($conn, $trendSql, $trendParams, $trendTypes);
$monthlyTrend = [];
while ($row = $result->fetch_assoc()) {
    $monthlyTrend[] = $row;
}
$trendLabels = array_reverse(array_column($monthlyTrend, 'month_label'));
$trendTotal = array_reverse(array_column($monthlyTrend, 'total'));
$trendCompleted = array_reverse(array_column($monthlyTrend, 'completed'));

// ─── PEAK HOURS ─────────────────────────────────────────────
$hourSql = "SELECT HOUR(ol.created_at) as hour, COUNT(*) as count
FROM outward_letters ol ";
if ($filter_desk > 0) {
    $hourSql .= "JOIN users u ON ol.created_by = u.id ";
}
$hourSql .= "WHERE $where AND ol.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
if ($filter_desk > 0) {
    $hourSql .= " AND u.desk_id = ?";
}
$hourSql .= " GROUP BY HOUR(ol.created_at) ORDER BY hour";
$hourParams = $params;
$hourTypes = $types;
if ($filter_desk > 0) {
    $hourParams[] = $filter_desk;
    $hourTypes .= "i";
}
$result = runFilteredQuery($conn, $hourSql, $hourParams, $hourTypes);
$hourCounts = array_fill(0, 24, 0);
while ($row = $result->fetch_assoc()) {
    $hourCounts[(int)$row['hour']] = (int)$row['count'];
}
$hours = array_map(function($i) { return sprintf("%02d:00", $i); }, range(0, 23));

// ─── RECEIVER PERFORMANCE (external_branches) ──────────────
$receiverSql = "SELECT 
    eb.branch_name,
    COUNT(DISTINCT ol.id) as total,
    SUM(CASE WHEN ol.status = 'Completed' THEN 1 ELSE 0 END) as completed
FROM outward_letters ol
JOIN external_branches eb ON FIND_IN_SET(eb.id, ol.receiver) > 0
";
if ($filter_desk > 0) {
    $receiverSql .= "JOIN users u ON ol.created_by = u.id ";
}
$receiverSql .= "WHERE $where AND ol.receiver IS NOT NULL AND ol.receiver != ''";
if ($filter_desk > 0) {
    $receiverSql .= " AND u.desk_id = ?";
}
$receiverSql .= " GROUP BY eb.id ORDER BY total DESC LIMIT 10";
$receiverParams = $params;
$receiverTypes = $types;
if ($filter_desk > 0) {
    $receiverParams[] = $filter_desk;
    $receiverTypes .= "i";
}
$result = runFilteredQuery($conn, $receiverSql, $receiverParams, $receiverTypes);
$receiverStats = $result->fetch_all(MYSQLI_ASSOC);

// ─── DESK PERFORMANCE (creator's desk) ─────────────────────
$deskSql = "SELECT 
    d.id as desk_id,
    d.desk_name,
    COUNT(DISTINCT ol.id) as total_letters,
    SUM(CASE WHEN ol.status = 'Completed' THEN 1 ELSE 0 END) as completed
FROM outward_letters ol
JOIN users u ON ol.created_by = u.id
JOIN desks d ON u.desk_id = d.id
WHERE $where";
if ($filter_desk > 0) {
    $deskSql .= " AND u.desk_id = ?";
}
$deskSql .= " GROUP BY d.id ORDER BY total_letters DESC LIMIT 10";
$deskParams = $params;
$deskTypes = $types;
if ($filter_desk > 0) {
    $deskParams[] = $filter_desk;
    $deskTypes .= "i";
}
$result = runFilteredQuery($conn, $deskSql, $deskParams, $deskTypes);
$deskStats = $result->fetch_all(MYSQLI_ASSOC);

// ─── USER PERFORMANCE (individual users) ──────────────────
$userSql = "SELECT 
    u.id as user_id,
    u.name_with_initial as user_name,
    COUNT(DISTINCT ol.id) as total_letters,
    SUM(CASE WHEN ol.status = 'Completed' THEN 1 ELSE 0 END) as completed
FROM outward_letters ol
JOIN users u ON ol.created_by = u.id
WHERE $where";
if ($filter_desk > 0) {
    $userSql .= " AND u.desk_id = ?";
}
$userSql .= " GROUP BY u.id ORDER BY total_letters DESC LIMIT 10";
$userParams = $params;
$userTypes = $types;
if ($filter_desk > 0) {
    $userParams[] = $filter_desk;
    $userTypes .= "i";
}
$result = runFilteredQuery($conn, $userSql, $userParams, $userTypes);
$userStats = $result->fetch_all(MYSQLI_ASSOC);

// ─── AGING ALERTS ───────────────────────────────────────────
$agingSql = "SELECT 
    ol.id, ol.letter_ref, ol.cdr_no, ol.title,
    DATEDIFF(NOW(), ol.created_at) as days_old,
    ol.approval_status,
    u.name_with_initial as created_by_name
FROM outward_letters ol
LEFT JOIN users u ON ol.created_by = u.id
WHERE $where 
    AND ol.approval_status = 'Pending RA Approval'
    AND ol.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)";
if ($filter_desk > 0) {
    $agingSql .= " AND u.desk_id = ?";
}
$agingSql .= " ORDER BY days_old DESC LIMIT 5";
$agingParams = $params;
$agingTypes = $types;
if ($filter_desk > 0) {
    $agingParams[] = $filter_desk;
    $agingTypes .= "i";
}
$result = runFilteredQuery($conn, $agingSql, $agingParams, $agingTypes);
$agingAlerts = $result->fetch_all(MYSQLI_ASSOC);

// ─── RECENT LETTERS ─────────────────────────────────────────
$recentSql = "SELECT 
    ol.id, ol.letter_ref, ol.cdr_no, ol.title, ol.letter_date, ol.status, ol.approval_status, ol.dispatch_method,
    (SELECT GROUP_CONCAT(eb.branch_name SEPARATOR ', ') 
     FROM external_branches eb 
     WHERE FIND_IN_SET(eb.id, ol.receiver) > 0) AS receiver_names
FROM outward_letters ol
";
if ($filter_desk > 0) {
    $recentSql .= "JOIN users u ON ol.created_by = u.id ";
}
$recentSql .= "WHERE $where";
if ($filter_desk > 0) {
    $recentSql .= " AND u.desk_id = ?";
}
$recentSql .= " ORDER BY ol.created_at DESC LIMIT 10";
$recentParams = $params;
$recentTypes = $types;
if ($filter_desk > 0) {
    $recentParams[] = $filter_desk;
    $recentTypes .= "i";
}
$result = runFilteredQuery($conn, $recentSql, $recentParams, $recentTypes);
$recentLetters = $result->fetch_all(MYSQLI_ASSOC);

// ─── FILTER DROPDOWNS ──────────────────────────────────────
$statusList = [];
$statusQuery = "SELECT DISTINCT status FROM outward_letters WHERE is_deleted = 0 ORDER BY status";
$statusRes = $conn->query($statusQuery);
while ($row = $statusRes->fetch_assoc()) {
    $statusList[] = $row['status'];
}
$approvalStatuses = ['Pending RA Approval', 'Approved', 'Rejected'];
$dispatchMethods = ['By Hand', 'Email','Courier','Post'];

// ─── DESK DROPDOWN ───────────────────────────────────────────
$desks = [];
$deskQuery = "SELECT id, desk_name FROM desks WHERE status='Active' AND is_deleted=0 ORDER BY desk_name";
$deskRes = $conn->query($deskQuery);
while ($row = $deskRes->fetch_assoc()) {
    $desks[] = $row;
}

// ─── CSRF Token ─────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Letters Dashboard | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <script src="../../assets/js/chart.umd.min.js"></script>
    <style>
        /* ─── ROOT VARIABLES ────────────────────────────────── */
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
            --card-radius: 20px;
            --shadow-sm: 0 4px 20px rgba(0, 0, 0, 0.04);
            --shadow-hover: 0 12px 40px rgba(0, 0, 0, 0.08);
        }

        [data-theme="dark"] {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #7551FF;
            --accent-soft: rgba(117, 81, 255, 0.12);
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --border: rgba(255, 255, 255, 0.08);
            --shadow-sm: 0 4px 20px rgba(0, 0, 0, 0.2);
            --shadow-hover: 0 12px 40px rgba(0, 0, 0, 0.35);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Plus Jakarta Sans', system-ui, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-body); }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }

        /* ─── SIDEBAR ────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 30px 15px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: transform var(--transition-speed), background var(--transition-speed);
        }
        .brand-box { padding: 0 10px; margin-bottom: 40px; }
        .brand-name { font-weight: 800; font-size: 1.2rem; line-height: 1.2; color: var(--text-main); }
        .brand-name i { color: var(--accent); }
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
            cursor: pointer;
        }
        .nav-btn:hover, .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }
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
        .sub-nav-btn:hover, .sub-nav-btn.active-sub {
            color: var(--accent);
            border-left-color: var(--accent);
            background: transparent;
        }
        .sidebar-divider {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            margin: 25px 0 10px 15px;
            opacity: 0.5;
            letter-spacing: 1px;
        }

        /* ─── MAIN CONTENT ───────────────────────────────────── */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 30px 35px 40px;
            min-height: 100vh;
            background: var(--bg-body);
            transition: margin var(--transition-speed), background var(--transition-speed);
        }

        /* ─── TOP NAV ────────────────────────────────────────── */
        .top-nav {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 20px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            transition: background var(--transition-speed), border var(--transition-speed);
        }
        .top-nav h4 { font-weight: 700; margin: 0; }
        .top-nav h4 small { font-size: 0.75rem; font-weight: 400; color: var(--text-muted); margin-left: 10px; }
        .mobile-toggle {
            display: none;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--text-main);
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* ─── FILTER BAR ──────────────────────────────────────── */
        .filter-bar {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 18px 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 16px 20px;
            transition: background var(--transition-speed), border var(--transition-speed);
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1 1 140px;
            min-width: 120px;
        }
        .filter-group label {
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .filter-control {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--text-main);
            font-size: 0.85rem;
            width: 100%;
            transition: border-color 0.2s;
        }
        .filter-control:focus {
            outline: none;
            border-color: var(--accent);
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex: 0 0 auto;
        }
        .btn-filter {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 8px 24px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-filter:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--accent-soft);
        }
        .btn-reset {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-reset:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ─── STATS ROW ──────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.25s ease;
            cursor: default;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--accent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
            border-color: var(--accent);
        }
        .stat-card:hover::after { opacity: 1; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff; flex-shrink: 0;
        }
        .stat-icon.purple { background: var(--accent); }
        .stat-icon.yellow { background: var(--warning); }
        .stat-icon.green { background: var(--success); }
        .stat-icon.red { background: var(--danger); }
        .stat-icon.blue { background: var(--info); }
        .stat-icon.violet { background: var(--purple); }
        .stat-info h6 {
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin: 0 0 2px 0;
            font-weight: 700;
        }
        .stat-info .number {
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1.2;
            color: var(--text-main);
        }
        .stat-info .trend {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* ─── CHARTS ROW ────────────────────────────────────── */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 22px 24px 18px;
            box-shadow: var(--shadow-sm);
            transition: background var(--transition-speed), border var(--transition-speed);
        }
        .chart-card .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .chart-card .chart-header h6 {
            font-weight: 700;
            font-size: 0.85rem;
            margin: 0;
            color: var(--text-main);
        }
        .chart-card .chart-header small {
            color: var(--text-muted);
            font-size: 0.7rem;
        }
        .chart-card canvas {
            max-height: 220px;
            max-width: 100%;
        }

        /* ─── TWO-COL GRID ───────────────────────────────────── */
        .two-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        @media (max-width: 992px) {
            .two-col-grid { grid-template-columns: 1fr; }
        }

        /* ─── THREE-COL GRID for performance tables (optional) ─ */
        .three-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        @media (max-width: 1200px) {
            .three-col-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .three-col-grid { grid-template-columns: 1fr; }
        }

        /* ─── TABLES ─────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        .perf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .perf-table th {
            text-align: left;
            padding: 10px 12px;
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-bottom: 1px solid var(--border);
        }
        .perf-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
        }
        .perf-table tr:last-child td { border-bottom: none; }
        .perf-table .progress {
            height: 6px;
            background: var(--bg-body);
            border-radius: 10px;
            overflow: hidden;
            width: 80px;
        }
        .perf-table .progress-bar {
            height: 100%;
            border-radius: 10px;
            background: var(--accent);
            transition: width 0.8s ease;
        }

        /* ─── ALERT CARDS ────────────────────────────────────── */
        .alert-card {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid var(--danger);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .alert-icon {
            width: 40px; height: 40px; border-radius: 12px;
            background: var(--danger); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .cdr-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .cdr-link:hover { text-decoration: underline; }

        /* ─── RECENT ITEMS ───────────────────────────────────── */
        .recent-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg-body);
            border-radius: 14px;
            transition: 0.2s;
            border-left: 3px solid transparent;
        }
        .recent-item:hover {
            border-left-color: var(--accent);
            transform: translateX(4px);
        }
        .recent-item .info {
            flex: 1;
            min-width: 0;
        }
        .recent-item .info .title {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .recent-item .info .meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 2px;
        }
        .recent-item .info .meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .recent-item .right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
            margin-left: 14px;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.3px;
        }
        .status-pending { background: rgba(108, 117, 125, 0.15); color: #7c7d6c; }
        .status-completed { background: rgba(0, 200, 83, 0.15); color: #00C853; }

        .badge-approval {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .approval-pending { background: rgba(255, 171, 0, 0.15); color: #FFAB00; }
        .approval-approved { background: rgba(0, 200, 83, 0.15); color: #00C853; }
        .approval-rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .badge-method {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .method-byhand { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        .method-email { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }

        /* ─── EMPTY STATE ────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 3rem; opacity: 0.3; margin-bottom: 12px; }
        .empty-state h6 { font-weight: 700; color: var(--text-main); }

        /* ─── ANIMATIONS ────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stat-card { animation: fadeUp 0.5s ease forwards; }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }
        .stat-card:nth-child(5) { animation-delay: 0.25s; }
        .stat-card:nth-child(6) { animation-delay: 0.30s; }

        /* ─── RESPONSIVE ────────────────────────────────────── */
        @media (max-width: 992px) {
            .charts-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                width: 280px;
            }
            .sidebar.show { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
            .mobile-toggle { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .top-nav { flex-wrap: wrap; gap: 12px; padding: 16px 18px; }
            .top-nav h4 { font-size: 1.1rem; }
            .top-nav h4 small { display: none; }
            .stat-card { padding: 14px 16px; }
            .stat-info .number { font-size: 1.3rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1rem; }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .filter-group { min-width: 100%; }
            .filter-actions { justify-content: flex-end; }
            .recent-item { flex-wrap: wrap; gap: 8px; }
            .recent-item .right { margin-left: 0; width: 100%; justify-content: flex-start; }
            .perf-table .progress { width: 60px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-info .number { font-size: 1.1rem; }
            .stat-icon { width: 34px; height: 34px; font-size: 0.85rem; }
            .stat-info h6 { font-size: 0.5rem; }
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

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-box">
            <div class="brand-name">
                <i class="fa-solid fa-shield-halved"></i> IDCMS
            </div>
        </div>
        <nav>
            <a href="../dashboard/dashboard.php" class="nav-btn">
                <i class="fa-solid fa-house"></i> Home Page
            </a>
            <div class="sidebar-divider">Outward</div>
            <button class="nav-btn active" onclick="toggleSubmenu('outwardSub')">
                <i class="fa-solid fa-paper-plane"></i> External Letters
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="outwardSub" class="submenu show">
                <a href="../outward/add_outward.php" class="sub-nav-btn">Add Outward</a>
                <a href="../outward/outward_list.php" class="sub-nav-btn">View Outward</a>
                <a href="outward_dashboard.php" class="sub-nav-btn active-sub">Dashboard</a>
            </div>
            <a href="../../auth/logout.php" class="nav-btn mt-4" style="color:var(--danger);">
                <i class="fa-solid fa-power-off"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ═══ MAIN CONTENT ═══ -->
    <main class="main-content">

        <!-- Top Nav -->
        <header class="top-nav">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h4>
                        External Letters Dashboard
                        <small></small>
                    </h4>
                </div>
            </div>
        </header>

        <!-- ═══ FILTER BAR ═══ -->
        <form method="GET" class="filter-bar" id="filterForm">
            <div class="filter-group">
                <label for="statusFilter"><i class="fa-regular fa-circle me-1"></i> Status</label>
                <select name="status" id="statusFilter" class="filter-control">
                    <option value="">All Statuses</option>
                    <?php foreach ($statusList as $s): ?>
                        <option value="<?= $s ?>" <?= $filter_status == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="approvalFilter"><i class="fa-regular fa-check-circle me-1"></i> Approval</label>
                <select name="approval_status" id="approvalFilter" class="filter-control">
                    <option value="">All Approvals</option>
                    <?php foreach ($approvalStatuses as $a): ?>
                        <option value="<?= $a ?>" <?= $filter_approval == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="methodFilter"><i class="fa-regular fa-truck me-1"></i> Dispatch Method</label>
                <select name="dispatch_method" id="methodFilter" class="filter-control">
                    <option value="">All Methods</option>
                    <?php foreach ($dispatchMethods as $m): ?>
                        <option value="<?= $m ?>" <?= $filter_method == $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="deskFilter"><i class="fa-regular fa-building me-1"></i> Desk</label>
                <select name="desk_id" id="deskFilter" class="filter-control">
                    <option value="">All Desks</option>
                    <?php foreach ($desks as $desk): ?>
                        <option value="<?= $desk['id'] ?>" <?= $filter_desk == $desk['id'] ? 'selected' : '' ?>><?= htmlspecialchars($desk['desk_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="dateFrom"><i class="fa-regular fa-calendar me-1"></i> Date From</label>
                <input type="date" name="date_from" id="dateFrom" class="filter-control" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="filter-group">
                <label for="dateTo"><i class="fa-regular fa-calendar me-1"></i> Date To</label>
                <input type="date" name="date_to" id="dateTo" class="filter-control" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="outward_dashboard.php" class="btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
            </div>
        </form>

        <!-- ═══ STATS ROW ═══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-envelope"></i></div>
                <div class="stat-info">
                    <h6>Total Letters</h6>
                    <div class="number" id="statTotal"><?= $masterStats['total'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <h6>Pending</h6>
                    <div class="number" id="statPending"><?= $masterStats['pending'] ?></div>
                    <div class="trend">Awaiting action</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-info">
                    <h6>Completed</h6>
                    <div class="number" id="statCompleted"><?= $masterStats['completed'] ?></div>
                    <div class="trend">
                        <?php $rate = $masterStats['total'] > 0 ? round(($masterStats['completed'] / $masterStats['total']) * 100) : 0; ?>
                        <?= $rate ?>% completion
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h6>Pending Approval</h6>
                    <div class="number" id="statPendingApproval"><?= $masterStats['pending_approval'] ?></div>
                    <div class="trend">RA Review</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-check-double"></i></div>
                <div class="stat-info">
                    <h6>Approved</h6>
                    <div class="number" id="statApproved"><?= $masterStats['approved'] ?></div>
                    <div class="trend">Cleared</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon violet"><i class="fa-solid fa-times-circle"></i></div>
                <div class="stat-info">
                    <h6>Rejected</h6>
                    <div class="number" id="statRejected"><?= $masterStats['rejected'] ?></div>
                    <div class="trend">Returned</div>
                </div>
            </div>
        </div>

        <!-- ═══ CHARTS ROW ═══ -->
        <div class="charts-grid">
            <!-- Status Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-pie me-2" style="color:var(--accent);"></i>Status Distribution</h6>
                    <small><?= $masterStats['total'] ?> total</small>
                </div>
                <canvas id="statusChart"></canvas>
            </div>
            <!-- Approval Status -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-check-double me-2" style="color:var(--accent);"></i>Approval Status</h6>
                    <small>by count</small>
                </div>
                <canvas id="approvalChart"></canvas>
            </div>
            <!-- Dispatch Method -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-truck me-2" style="color:var(--accent);"></i>Dispatch Method</h6>
                    <small>by count</small>
                </div>
                <canvas id="methodChart"></canvas>
            </div>
            <!-- Monthly Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-line me-2" style="color:var(--accent);"></i>Monthly Trend</h6>
                    <small>Last 12 months</small>
                </div>
                <canvas id="trendChart" style="max-height:180px;"></canvas>
            </div>
            <!-- Peak Hours -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-clock me-2" style="color:var(--warning);"></i>Peak Activity Hours</h6>
                    <small>Last 30 days</small>
                </div>
                <canvas id="hourlyChart" style="max-height:180px;"></canvas>
            </div>
            <!-- Top Receivers -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-users me-2" style="color:var(--info);"></i>Top Receivers</h6>
                    <small>by total letters</small>
                </div>
                <canvas id="receiverChart"></canvas>
            </div>
        </div>

        <!-- ═══ THREE-COLUMN: Receiver, Desk, and User Performance ═══ -->
        <div class="three-col-grid">
            <!-- Receiver Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-user me-2" style="color:var(--purple);"></i>Receiver Performance</h6>
                    <small>Total · Completed</small>
                </div>
                <?php if (empty($receiverStats)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-user"></i></div>
                        <h6>No receiver data</h6>
                        <small>Outward letters with receivers will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Receiver Branch</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receiverStats as $row):
                                    $total = $row['total'];
                                    $completed = $row['completed'];
                                    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['branch_name']) ?></strong></td>
                                        <td><?= $total ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.75rem;min-width:36px;"><?= $progress ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desk Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-desktop me-2" style="color:var(--info);"></i>Desk Performance</h6>
                    <small>Total · Completed</small>
                </div>
                <?php if (empty($deskStats)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-building"></i></div>
                        <h6>No desk data</h6>
                        <small>Outward letters created by users of each desk will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Desk</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deskStats as $row):
                                    $total = $row['total_letters'];
                                    $completed = $row['completed'];
                                    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['desk_name']) ?></strong></td>
                                        <td><?= $total ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.75rem;min-width:36px;"><?= $progress ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-user-tie me-2" style="color:var(--success);"></i>User Performance</h6>
                    <small>Total · Completed</small>
                </div>
                <?php if (empty($userStats)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-user"></i></div>
                        <h6>No user data</h6>
                        <small>Outward letters created by each user will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userStats as $row):
                                    $total = $row['total_letters'];
                                    $completed = $row['completed'];
                                    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['user_name']) ?></strong></td>
                                        <td><?= $total ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.75rem;min-width:36px;"><?= $progress ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ AGING ALERTS ═══ -->
        <div class="chart-card" style="margin-bottom:28px;">
            <div class="chart-header">
                <h6><i class="fa-solid fa-triangle-exclamation me-2" style="color:var(--danger);"></i>Pending Approval Aging (>3 days)</h6>
                <small>Urgent attention</small>
            </div>
            <?php if (empty($agingAlerts)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-regular fa-circle-check"></i></div>
                    <h6>No aging pending approvals</h6>
                    <small>All pending approvals are within 3 days</small>
                </div>
            <?php else: ?>
                <?php foreach ($agingAlerts as $alert): ?>
                    <div class="alert-card">
                        <div class="alert-icon"><i class="fa-regular fa-clock"></i></div>
                        <div style="flex:1;">
                            <a href="../outward/view_outward.php?id=<?= $alert['id'] ?>" class="cdr-link"><?= htmlspecialchars($alert['letter_ref']) ?></a>
                            <div class="small --text-muted"><?= htmlspecialchars(substr($alert['title'], 0, 50)) ?>...</div>
                            <div class="small mt-1">
                                <span class="badge-approval approval-pending"><?= $alert['approval_status'] ?></span>
                                <span class="--text-muted ms-2"><?= $alert['days_old'] ?> days old</span>
                                <?php if (!empty($alert['created_by_name'])): ?>
                                    <span class="text-muted ms-2"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($alert['created_by_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ═══ RECENT LETTERS ═══ -->
        <div class="chart-card">
            <div class="chart-header">
                <h6><i class="fa-solid fa-list me-2" style="color:var(--accent);"></i>Recent Outward Letters</h6>
                <a href="outward_letters.php" class="text-muted small">View All</a>
            </div>
            <?php if (empty($recentLetters)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-regular fa-envelope"></i></div>
                    <h6>No recent letters</h6>
                    <small>New outward letters will appear here</small>
                </div>
            <?php else: ?>
                <div class="recent-list">
                    <?php foreach ($recentLetters as $letter):
                        $status = $letter['status'] ?: 'Pending';
                        $statusClass = 'status-pending';
                        if ($status === 'Completed') $statusClass = 'status-completed';

                        $approval = $letter['approval_status'] ?: 'Pending RA Approval';
                        $approvalClass = 'approval-pending';
                        if ($approval === 'Approved') $approvalClass = 'approval-approved';
                        elseif ($approval === 'Rejected') $approvalClass = 'approval-rejected';

                        $method = $letter['dispatch_method'] ?: 'Email';
                        $methodClass = 'method-email';
                        if ($method === 'By Hand') $methodClass = 'method-byhand';

                        $receiverNames = $letter['receiver_names'] ?? '';
                    ?>
                        <div class="recent-item">
                            <div class="info">
                                <div class="title">
                                    <a href="../outward/view_outward.php?id=<?= $letter['id'] ?>" class="cdr-link">
                                        <?= htmlspecialchars($letter['letter_ref']) ?>
                                    </a>
                                    <span style="font-weight:400;color:var(--text-muted);font-size:0.85rem;margin-left:6px;">
                                        <?= htmlspecialchars($letter['title']) ?>
                                    </span>
                                </div>
                                <div class="meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($letter['letter_date'])) ?></span>
                                    <?php if (!empty($letter['cdr_no'])): ?>
                                        <span><i class="fa-regular fa-hashtag"></i> <?= htmlspecialchars($letter['cdr_no']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($receiverNames)): ?>
                                        <span><i class="fa-regular fa-building"></i> <?= htmlspecialchars($receiverNames) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="right">
                                <span class="badge-method <?= $methodClass ?>"><?= $method ?></span>
                                <span class="badge-approval <?= $approvalClass ?>"><?= $approval ?></span>
                                <span class="badge-status <?= $statusClass ?>"><?= $status ?></span>
                                <a href="../outward/view_outward.php?id=<?= $letter['id'] ?>" style="padding:4px 12px;font-size:0.7rem;">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div style="margin-top:30px;text-align:center;font-size:0.7rem;color:var(--text-muted);opacity:0.6;border-top:1px solid var(--border);padding-top:20px;">
            IDCMS Outward Dashboard &bull; <?= date('Y') ?> &bull; Real-time insights
        </div>

    </main>

    <!-- ════════════════════════════════════════════════════════
    SCRIPTS
    ════════════════════════════════════════════════════════ -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // ─── THEME ──────────────────────────────────────────────
        const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.body.setAttribute('data-theme', savedTheme);

        // ─── SIDEBAR ────────────────────────────────────────────
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        function toggleSubmenu(id) {
            const el = document.getElementById(id);
            el.style.display = el.style.display === 'block' ? 'none' : 'block';
        }

        // ─── CHART HELPERS ──────────────────────────────────────
        function getThemeColors() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            return {
                text: isDark ? '#FFFFFF' : '#1B2559',
                muted: isDark ? '#A3AED0' : '#707EAE',
                grid: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
            };
        }
        function getChartDefaults() {
            const c = getThemeColors();
            return {
                color: c.text,
                plugins: {
                    legend: {
                        labels: {
                            color: c.text,
                            font: { size: 11, weight: '600' },
                            boxWidth: 12,
                            padding: 12,
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: c.muted, font: { size: 10 } },
                        grid: { color: c.grid, drawBorder: false },
                    },
                    y: {
                        ticks: { color: c.muted, font: { size: 10 } },
                        grid: { color: c.grid, drawBorder: false },
                        beginAtZero: true,
                    }
                }
            };
        }

        // ─── 1. STATUS CHART ────────────────────────────────────
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    data: <?= json_encode($statusData) ?>,
                    backgroundColor: <?= json_encode($statusColors) ?>,
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                ...getChartDefaults(),
                cutout: '65%',
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' }, color: getThemeColors().text }
                    }
                },
                animation: { animateRotate: true, duration: 1200 }
            }
        });

        // ─── 2. APPROVAL CHART ──────────────────────────────────
        const approvalCtx = document.getElementById('approvalChart').getContext('2d');
        new Chart(approvalCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($approvalLabels) ?>,
                datasets: [{
                    data: <?= json_encode($approvalData) ?>,
                    backgroundColor: <?= json_encode($approvalColors) ?>,
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                ...getChartDefaults(),
                cutout: '65%',
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600' }, color: getThemeColors().text }
                    }
                },
                animation: { animateRotate: true, duration: 1200 }
            }
        });

        // ─── 3. DISPATCH METHOD CHART ──────────────────────────
        const methodCtx = document.getElementById('methodChart').getContext('2d');
        new Chart(methodCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($methodLabels) ?>,
                datasets: [{
                    label: 'Letters',
                    data: <?= json_encode($methodData) ?>,
                    backgroundColor: <?= json_encode($methodColors) ?>,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.5,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1 } } },
                animation: { duration: 1000 }
            }
        });

        // ─── 4. TREND CHART ─────────────────────────────────────
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    {
                        label: 'Total',
                        data: <?= json_encode($trendTotal) ?>,
                        borderColor: '#7551FF',
                        backgroundColor: 'rgba(117, 81, 255, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#7551FF',
                        borderWidth: 2.5,
                    },
                    {
                        label: 'Completed',
                        data: <?= json_encode($trendCompleted) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#10b981',
                        borderWidth: 2.5,
                    }
                ]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: { legend: { display: true, position: 'bottom', labels: { color: getThemeColors().text } } },
                scales: {
                    x: { ...getChartDefaults().scales.x, ticks: { maxTicksLimit: 12, font: { size: 9 } } },
                    y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1, font: { size: 9 } } }
                },
                interaction: { intersect: false, mode: 'index' },
                animation: { duration: 1200 }
            }
        });

        // ─── 5. HOURLY CHART ────────────────────────────────────
        const hourCtx = document.getElementById('hourlyChart').getContext('2d');
        new Chart(hourCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($hours) ?>,
                datasets: [{
                    label: 'Submissions',
                    data: <?= json_encode($hourCounts) ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ...getChartDefaults().scales.x, ticks: { maxTicksLimit: 12, font: { size: 8 } } },
                    y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1 } }
                },
                animation: { duration: 1000 }
            }
        });

        // ─── 6. RECEIVER CHART (Bar) ───────────────────────────
        const receiverCtx = document.getElementById('receiverChart').getContext('2d');
        const receiverLabels = <?= json_encode(array_column($receiverStats, 'branch_name')) ?>;
        const receiverCounts = <?= json_encode(array_column($receiverStats, 'total')) ?>;
        const receiverColors = ['#4318FF', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#3b82f6', '#14b8a6', '#f97316', '#6366f1'];
        new Chart(receiverCtx, {
            type: 'bar',
            data: {
                labels: receiverLabels,
                datasets: [{
                    label: 'Total Letters',
                    data: receiverCounts,
                    backgroundColor: receiverColors.slice(0, receiverLabels.length),
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1 } } },
                animation: { duration: 1000 }
            }
        });

        // ─── ANIMATE STAT COUNTERS ─────────────────────────────
        function animateCounter(el, target, duration = 1000) {
            const start = 0;
            const startTime = performance.now();
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = start + (target - start) * eased;
                el.textContent = Math.round(current);
                if (progress < 1) requestAnimationFrame(update);
                else el.textContent = target;
            }
            requestAnimationFrame(update);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const stats = [
                { id: 'statTotal', val: <?= $masterStats['total'] ?> },
                { id: 'statPending', val: <?= $masterStats['pending'] ?> },
                { id: 'statCompleted', val: <?= $masterStats['completed'] ?> },
                { id: 'statPendingApproval', val: <?= $masterStats['pending_approval'] ?> },
                { id: 'statApproved', val: <?= $masterStats['approved'] ?> },
                { id: 'statRejected', val: <?= $masterStats['rejected'] ?> },
            ];
            stats.forEach(s => {
                const el = document.getElementById(s.id);
                if (el) {
                    const target = s.val;
                    el.textContent = '0';
                    setTimeout(() => animateCounter(el, target, 900), 200);
                }
            });

            // Animate progress bars
            document.querySelectorAll('.progress-bar').forEach(pb => {
                const w = pb.style.width;
                pb.style.width = '0%';
                setTimeout(() => { pb.style.width = w; }, 400);
            });
        });

        // ─── CLOSE SIDEBAR ON OUTSIDE CLICK ─────────────────────
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        console.log('Outward Dashboard loaded successfully');
    </script>
</body>
</html>