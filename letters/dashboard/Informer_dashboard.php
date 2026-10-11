<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ACCESS_INFORMER_DASHBOARD')) {
    die('Access Denied!');
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// ─── FILTERS ──────────────────────────────────────────────────
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$filter_class    = isset($_GET['classification']) ? $_GET['classification'] : '';
$filter_status   = isset($_GET['status']) ? $_GET['status'] : '';
$filter_regiment = isset($_GET['regiment']) ? intval($_GET['regiment']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to   = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search          = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base WHERE for informers (is_deleted = 0)
$where = "i.is_deleted = 0";
$params = [];
$types = "";

if (!empty($filter_category)) {
    $where .= " AND i.category = ?";
    $params[] = $filter_category;
    $types .= "s";
}
if (!empty($filter_class)) {
    $where .= " AND i.classification = ?";
    $params[] = $filter_class;
    $types .= "s";
}
if (!empty($filter_status)) {
    $where .= " AND i.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if ($filter_regiment > 0) {
    $where .= " AND i.regiment_id = ?";
    $params[] = $filter_regiment;
    $types .= "i";
}
if (!empty($filter_date_from)) {
    $where .= " AND DATE(i.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $where .= " AND DATE(i.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}
if (!empty($search)) {
    $where .= " AND (i.informer_code LIKE ? OR i.informer_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

function runFilteredQuery($conn, $sql, $params, $types) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// ─── STATS ────────────────────────────────────────────────────
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN i.status = 'Active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN i.status = 'Inactive' THEN 1 ELSE 0 END) as inactive
FROM informers i
WHERE $where";
$result = runFilteredQuery($conn, $statsSql, $params, $types);
$masterStats = $result->fetch_assoc();

$lettersSql = "SELECT COUNT(DISTINCT il.id) as total_letters
    FROM internal_letters il
    JOIN informers i ON il.Informer_id = i.id
    WHERE $where AND il.is_deleted = 0";
$result = runFilteredQuery($conn, $lettersSql, $params, $types);
$lettersStats = $result->fetch_assoc();
$masterStats['total_letters'] = $lettersStats['total_letters'] ?? 0;

// ─── CATEGORY DISTRIBUTION ──────────────────────────────────
$catSql = "SELECT category, COUNT(*) as count FROM informers i WHERE $where GROUP BY category";
$result = runFilteredQuery($conn, $catSql, $params, $types);
$catCounts = [];
while ($row = $result->fetch_assoc()) {
    $catCounts[$row['category']] = $row['count'];
}
$catLabels = ['Informer', 'Casual Contact', 'Agent'];
$catData = [];
foreach ($catLabels as $label) {
    $catData[] = $catCounts[$label] ?? 0;
}
$catColors = ['#10b981', '#f59e0b', '#ef4444'];

// ─── CLASSIFICATION DISTRIBUTION ────────────────────────────
$classSql = "SELECT classification, COUNT(*) as count FROM informers i WHERE $where GROUP BY classification";
$result = runFilteredQuery($conn, $classSql, $params, $types);
$classCounts = [];
while ($row = $result->fetch_assoc()) {
    $classCounts[$row['classification']] = $row['count'];
}
$classLabels = ['A', 'B', 'C', 'D', 'E', 'F'];
$classData = [];
foreach ($classLabels as $label) {
    $classData[] = $classCounts[$label] ?? 0;
}
$classColors = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];

// ─── REGIMENT DISTRIBUTION ──────────────────────────────────
$regSql = "SELECT r.regiment_name, COUNT(i.id) as count 
    FROM informers i
    JOIN regiments r ON i.regiment_id = r.id
    WHERE $where
    GROUP BY i.regiment_id
    ORDER BY count DESC LIMIT 10";
$result = runFilteredQuery($conn, $regSql, $params, $types);
$regStats = $result->fetch_all(MYSQLI_ASSOC);
$regLabels = array_column($regStats, 'regiment_name');
$regData = array_column($regStats, 'count');

// ─── STATUS DISTRIBUTION ────────────────────────────────────
$statusSql = "SELECT status, COUNT(*) as count FROM informers i WHERE $where GROUP BY status";
$result = runFilteredQuery($conn, $statusSql, $params, $types);
$statusCounts = [];
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}
$statusLabels = ['Active', 'Inactive'];
$statusData = [];
foreach ($statusLabels as $label) {
    $statusData[] = $statusCounts[$label] ?? 0;
}
$statusColors = ['#10b981', '#ef4444'];

// ─── TOP INFORMERS BY LETTERS ───────────────────────────────
$topSql = "SELECT i.id, i.informer_code, i.informer_name, COUNT(il.id) as letter_count
    FROM informers i
    LEFT JOIN internal_letters il ON il.Informer_id = i.id AND il.is_deleted = 0
    WHERE $where
    GROUP BY i.id
    ORDER BY letter_count DESC
    LIMIT 5";
$result = runFilteredQuery($conn, $topSql, $params, $types);
$topInformers = $result->fetch_all(MYSQLI_ASSOC);

// ─── RECENT INFORMERS ────────────────────────────────────────
$recentSql = "SELECT id, informer_code, informer_name, category, classification, status, created_at
    FROM informers i
    WHERE $where
    ORDER BY created_at DESC
    LIMIT 5";
$result = runFilteredQuery($conn, $recentSql, $params, $types);
$recentInformers = $result->fetch_all(MYSQLI_ASSOC);

// ─── INFORMER BRANCH PERFORMANCE (SIMPLIFIED) ──────────────
$branchSql = "SELECT 
    i.informer_code,
    eb.branch_name,
    COUNT(DISTINCT ol.id) as total_letters
    FROM informers i
    JOIN internal_letters il ON il.Informer_id = i.id
    JOIN outward_internal oi ON oi.internal_id = il.id
    JOIN outward_letters ol ON oi.outward_id = ol.id
    JOIN external_branches eb ON FIND_IN_SET(eb.id, ol.receiver) > 0
    WHERE $where AND il.is_deleted = 0 AND ol.is_deleted = 0
    GROUP BY i.id, eb.id
    ORDER BY i.informer_code, eb.branch_name";
$result = runFilteredQuery($conn, $branchSql, $params, $types);
$rows = $result->fetch_all(MYSQLI_ASSOC);

// Build pivot array
$informerData = [];
$allBranches = [];
foreach ($rows as $row) {
    $code = $row['informer_code'];
    $branch = $row['branch_name'];
    $total = (int)$row['total_letters'];
    
    if (!isset($informerData[$code])) {
        $informerData[$code] = ['branches' => []];
    }
    $informerData[$code]['branches'][$branch] = $total;
    if (!in_array($branch, $allBranches)) {
        $allBranches[] = $branch;
    }
}
sort($allBranches);

// ─── FULL REGISTRY ───────────────────────────────────────────
$registrySql = "SELECT i.id, i.informer_code, i.informer_name, i.category, i.classification, i.status, 
    r.regiment_name, i.handler_details, i.contact_number,
    COUNT(il.id) as letter_count
    FROM informers i
    LEFT JOIN regiments r ON i.regiment_id = r.id
    LEFT JOIN internal_letters il ON il.Informer_id = i.id AND il.is_deleted = 0
    WHERE $where
    GROUP BY i.id
    ORDER BY i.created_at DESC";
$result = runFilteredQuery($conn, $registrySql, $params, $types);
$registry = $result->fetch_all(MYSQLI_ASSOC);

// ─── FILTER DROPDOWNS ───────────────────────────────────────
$categories = ['Informer', 'Casual Contact', 'Agent'];
$classifications = ['A', 'B', 'C', 'D', 'E', 'F'];
$statuses = ['Active', 'Inactive'];
$regiments = $conn->query("SELECT id, regiment_name FROM regiments WHERE status = 'Active' ORDER BY regiment_name");

// ─── CSRF Token ──────────────────────────────────────────────
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
    <title>Informer Dashboard | IDCMS</title>
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
        .stat-icon.green { background: var(--success); }
        .stat-icon.red { background: var(--danger); }
        .stat-icon.blue { background: var(--info); }
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

        /* ─── THREE-COL GRID for performance tables ─────────── */
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

        .badge-category {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-agent { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-informer { background: rgba(16,185,129,0.15); color: #10b981; }
        .badge-contact { background: rgba(245,158,11,0.15); color: #f59e0b; }

        .badge-class {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 700;
            background: var(--accent-soft);
            color: var(--accent);
        }

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

        /* ─── RESPONSIVE ────────────────────────────────────── */
        @media (max-width: 992px) {
            .charts-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
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
            <div class="sidebar-divider">Informer</div>
            <button class="nav-btn active" onclick="toggleSubmenu('informerSub')">
                <i class="fa-solid fa-user-secret"></i> Informer Unit
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="informerSub" class="submenu show">
                <a href="../../admin/informer/informers.php" class="sub-nav-btn">Add Informer</a>
               
                <a href="informer_dashboard.php" class="sub-nav-btn active-sub">Dashboard</a>
            </div>
            <a href="../../auth/logout.php" class="nav-btn mt-4" style="color:var(--danger);">
                <i class="fa-solid fa-power-off"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ═══ MAIN CONTENT ═══ -->
    <main class="main-content">

        <!-- Top Nav — theme toggle button removed -->
        <header class="top-nav">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h4>
                        Human Intelligence Dashboard
                        <small>Real‑time informer analytics</small>
                    </h4>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted"><?= htmlspecialchars($userName) ?></span>
            </div>
        </header>

        <!-- ═══ FILTER BAR ═══ -->
        <form method="GET" class="filter-bar" id="filterForm">
            <div class="filter-group">
                <label for="categoryFilter"><i class="fa-solid fa-tag me-1"></i> Category</label>
                <select name="category" id="categoryFilter" class="filter-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $filter_category == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="classFilter"><i class="fa-solid fa-layer-group me-1"></i> Classification</label>
                <select name="classification" id="classFilter" class="filter-control">
                    <option value="">All Classes</option>
                    <?php foreach ($classifications as $cls): ?>
                        <option value="<?= $cls ?>" <?= $filter_class == $cls ? 'selected' : '' ?>>Class <?= $cls ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="statusFilter"><i class="fa-solid fa-circle me-1"></i> Status</label>
                <select name="status" id="statusFilter" class="filter-control">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $filter_status == $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="regimentFilter"><i class="fa-solid fa-building me-1"></i> Regiment</label>
                <select name="regiment" id="regimentFilter" class="filter-control">
                    <option value="0">All Regiments</option>
                    <?php while ($r = $regiments->fetch_assoc()): ?>
                        <option value="<?= $r['id'] ?>" <?= $filter_regiment == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['regiment_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="dateFrom"><i class="fa-solid fa-calendar me-1"></i> Date From</label>
                <input type="date" name="date_from" id="dateFrom" class="filter-control" value="<?= htmlspecialchars($filter_date_from) ?>">
            </div>
            <div class="filter-group">
                <label for="dateTo"><i class="fa-solid fa-calendar me-1"></i> Date To</label>
                <input type="date" name="date_to" id="dateTo" class="filter-control" value="<?= htmlspecialchars($filter_date_to) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="informer_dashboard.php" class="btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
            </div>
        </form>

        <!-- ═══ STATS ROW ═══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info">
                    <h6>Total Informers</h6>
                    <div class="number" id="statTotal"><?= $masterStats['total'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                <div class="stat-info">
                    <h6>Active</h6>
                    <div class="number" id="statActive"><?= $masterStats['active'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-user-slash"></i></div>
                <div class="stat-info">
                    <h6>Inactive</h6>
                    <div class="number" id="statInactive"><?= $masterStats['inactive'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-file-lines"></i></div>
                <div class="stat-info">
                    <h6>Total Letters</h6>
                    <div class="number" id="statLetters"><?= $masterStats['total_letters'] ?></div>
                    <div class="trend">Linked to informers</div>
                </div>
            </div>
        </div>

        <!-- ═══ CHARTS ROW ═══ -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-pie me-2" style="color:var(--accent);"></i>Category Distribution</h6>
                    <small><?= $masterStats['total'] ?> total</small>
                </div>
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-bar me-2" style="color:var(--accent);"></i>Classification Breakdown</h6>
                    <small>by class</small>
                </div>
                <canvas id="classChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-building me-2" style="color:var(--accent);"></i>Regiment Distribution</h6>
                    <small>by regiment</small>
                </div>
                <canvas id="regimentChart"></canvas>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-circle me-2" style="color:var(--accent);"></i>Status Distribution</h6>
                    <small>active vs inactive</small>
                </div>
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- ═══ THREE-COLUMN PERFORMANCE TABLES ═══ -->
        <div class="three-col-grid">
            <!-- Top Informers by Letters -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-trophy me-2" style="color:var(--warning);"></i>Top Informers</h6>
                    <small>by letter count</small>
                </div>
                <?php if (empty($topInformers)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-solid fa-user"></i></div>
                        <h6>No data</h6>
                        <small>Informers with letters will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Letters</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topInformers as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['informer_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['informer_name']) ?></td>
                                        <td><?= $row['letter_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Informers -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-clock me-2" style="color:var(--info);"></i>Recent Informers</h6>
                    <small>last added</small>
                </div>
                <?php if (empty($recentInformers)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-solid fa-user"></i></div>
                        <h6>No recent informers</h6>
                        <small>New informers will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentInformers as $row):
                                    $catClass = 'badge-informer';
                                    if ($row['category'] == 'Agent') $catClass = 'badge-agent';
                                    elseif ($row['category'] == 'Casual Contact') $catClass = 'badge-contact';
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['informer_code']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['informer_name']) ?></td>
                                        <td><span class="badge-category <?= $catClass ?>"><?= $row['category'] ?></span></td>
                                        <td><span class="badge-class">Class <?= $row['classification'] ?></span></td>
                                        <td>
                                            <?php if ($row['status'] == 'Active'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informer Branch Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-globe me-2" style="color:var(--purple);"></i>Informer Branch Performance</h6>
                    <small>Outward letters per branch</small>
                </div>
                <?php if (empty($informerData)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-solid fa-building"></i></div>
                        <h6>No outward branch data</h6>
                        <small>Informer-linked outward letters to external branches will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Informer</th>
                                    <?php foreach ($allBranches as $branch): ?>
                                        <th><?= htmlspecialchars($branch) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($informerData as $code => $data): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($code) ?></strong></td>
                                        <?php foreach ($allBranches as $branch): ?>
                                            <td>
                                                <?= $data['branches'][$branch] ?? 0 ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ FULL INFORMER REGISTRY ═══ -->
        <div class="chart-card">
            <div class="chart-header">
                <h6><i class="fa-solid fa-list me-2" style="color:var(--accent);"></i>Informer Registry</h6>
                <form method="GET" class="d-flex gap-2">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>">
                    <input type="hidden" name="classification" value="<?= htmlspecialchars($filter_class) ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                    <input type="hidden" name="regiment" value="<?= $filter_regiment ?>">
                    <input type="hidden" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                    <input type="hidden" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                    <div class="search-box d-flex align-items-center" style="background:var(--bg-body); border:1px solid var(--border); border-radius:50px; padding:4px 12px;">
                        <i class="fa-solid fa-magnifying-glass text-muted"></i>
                        <input type="text" name="search" class="filter-control" style="border:none; background:transparent; padding:6px 10px;" placeholder="Search by code or name..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn-filter" style="padding:6px 16px;"><i class="fa-solid fa-search"></i></button>
                </form>
            </div>
            <?php if (empty($registry)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-solid fa-user"></i></div>
                    <h6>No informers found</h6>
                    <small>Adjust your filters or add a new informer</small>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Class</th>
                                <th>Regiment</th>
                                <th>Handler</th>
                                <th>Status</th>
                                <th>Letters</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registry as $row):
                                $catClass = 'badge-informer';
                                if ($row['category'] == 'Agent') $catClass = 'badge-agent';
                                elseif ($row['category'] == 'Casual Contact') $catClass = 'badge-contact';
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['informer_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['informer_name']) ?></td>
                                    <td><span class="badge-category <?= $catClass ?>"><?= $row['category'] ?></span></td>
                                    <td><span class="badge-class">Class <?= $row['classification'] ?></span></td>
                                    <td><?= htmlspecialchars($row['regiment_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['handler_details'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'Active'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $row['letter_count'] ?></td>
                                    <td>
                                        <a href="view_informer.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fa-solid fa-eye"></i></a>
                                        <a href="edit_informer.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div style="margin-top:30px;text-align:center;font-size:0.7rem;color:var(--text-muted);opacity:0.6;border-top:1px solid var(--border);padding-top:20px;">
            IDCMS Informer Dashboard &bull; <?= date('Y') ?> &bull; Intelligence insights
        </div>

    </main>

    <!-- ════════════════════════════════════════════════════════
    SCRIPTS
    ════════════════════════════════════════════════════════ -->
    <script src="../../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // ─── THEME (load from localStorage only — no toggle) ───
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

        // ─── CHARTS INIT ────────────────────────────────────────
        let charts = {};

        function initCharts() {
            const defaults = getChartDefaults();

            // 1. Category (doughnut)
            const catCtx = document.getElementById('categoryChart').getContext('2d');
            charts.category = new Chart(catCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($catLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($catData) ?>,
                        backgroundColor: <?= json_encode($catColors) ?>,
                        borderWidth: 0,
                        hoverOffset: 8,
                    }]
                },
                options: {
                    ...defaults,
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

            // 2. Classification (bar)
            const classCtx = document.getElementById('classChart').getContext('2d');
            charts.classification = new Chart(classCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($classLabels) ?>,
                    datasets: [{
                        label: 'Informers',
                        data: <?= json_encode($classData) ?>,
                        backgroundColor: <?= json_encode($classColors) ?>,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    ...defaults,
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { ...defaults.scales.y, ticks: { stepSize: 1 } } },
                    animation: { duration: 1000 }
                }
            });

            // 3. Regiment (bar)
            const regCtx = document.getElementById('regimentChart').getContext('2d');
            charts.regiment = new Chart(regCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($regLabels) ?>,
                    datasets: [{
                        label: 'Informers',
                        data: <?= json_encode($regData) ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    ...defaults,
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { ...defaults.scales.y, ticks: { stepSize: 1 } } },
                    animation: { duration: 1000 }
                }
            });

            // 4. Status (doughnut)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            charts.status = new Chart(statusCtx, {
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
                    ...defaults,
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
        }

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
            initCharts();

            const stats = [
                { id: 'statTotal', val: <?= $masterStats['total'] ?> },
                { id: 'statActive', val: <?= $masterStats['active'] ?> },
                { id: 'statInactive', val: <?= $masterStats['inactive'] ?> },
                { id: 'statLetters', val: <?= $masterStats['total_letters'] ?> },
            ];
            stats.forEach(s => {
                const el = document.getElementById(s.id);
                if (el) {
                    const target = s.val;
                    el.textContent = '0';
                    setTimeout(() => animateCounter(el, target, 900), 200);
                }
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

        console.log('Informer Dashboard loaded successfully');
    </script>
</body>
</html>