<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS')) {
    die('Access Denied!');
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// ─── FILTERS ──────────────────────────────────────────────────
$filter_regiment = isset($_GET['regiment']) ? intval($_GET['regiment']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to   = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_desk      = isset($_GET['desk']) ? intval($_GET['desk']) : 0;

// Base WHERE for letters (is_deleted = 0)
$where = "il.is_deleted = 0";
$params = [];
$types = "";

if ($filter_regiment > 0) {
    $where .= " AND il.regiment_id = ?";
    $params[] = $filter_regiment;
    $types .= "i";
}
if (!empty($filter_date_from)) {
    $where .= " AND DATE(il.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}
if (!empty($filter_date_to)) {
    $where .= " AND DATE(il.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

// ─── DESK FILTER SUBQUERY ───────────────────────────────────
$deskFilterSubquery = "";
$deskFilterParams = [];
$deskFilterTypes = "";
if ($filter_desk > 0) {
    $deskFilterSubquery = " AND il.id IN (
        SELECT DISTINCT f.letter_id 
        FROM internal_letters_forwarding f
        JOIN users u ON f.forwarded_to = u.id
        WHERE u.desk_id = ?
    )";
    $deskFilterParams[] = $filter_desk;
    $deskFilterTypes .= "i";
}
// Merge all params
$allParams = array_merge($params, $deskFilterParams);
$allTypes = $types . $deskFilterTypes;

// Helper to run prepared statements with all filters
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
    SUM(CASE WHEN il.status = 'Not Forwarded' THEN 1 ELSE 0 END) as not_forwarded,
    SUM(CASE WHEN il.status = 'Forwarded' THEN 1 ELSE 0 END) as forwarded,
    SUM(CASE WHEN il.status = 'pending' AND (ifd.status IS NULL OR ifd.status != 'pending') THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN il.status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN ifd.status = 'pending' THEN 1 ELSE 0 END) as under_final
FROM internal_letters il
LEFT JOIN internal_letters_final_disposal ifd ON il.id = ifd.letter_id
WHERE $where $deskFilterSubquery";

$result = runFilteredQuery($conn, $statsSql, $allParams, $allTypes);
$masterStats = $result->fetch_assoc();

// My Inbox
$inboxSql = "SELECT COUNT(*) as inbox_count FROM internal_letters_forwarding WHERE forwarded_to = ? AND seen = 0";
$stmt = $conn->prepare($inboxSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$inboxCount = $stmt->get_result()->fetch_assoc()['inbox_count'];
$stmt->close();

// ─── STATUS DISTRIBUTION ─────────────────────────────────────
$statusSql = "SELECT il.status, COUNT(*) as count FROM internal_letters il WHERE $where $deskFilterSubquery GROUP BY il.status";
$result = runFilteredQuery($conn, $statusSql, $allParams, $allTypes);
$statusCounts = [];
while ($row = $result->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}
$statusLabels = ['Not Forwarded', 'Forwarded', 'pending', 'completed'];
$statusData = [];
foreach ($statusLabels as $label) {
    $statusData[] = $statusCounts[$label] ?? 0;
}
$statusColors = ['#6c757d', '#0d6efd', '#ffc107', '#198754'];

// ─── PRIORITY DISTRIBUTION ──────────────────────────────────
$prioritySql = "SELECT priority_status, COUNT(*) as count FROM internal_letters il WHERE $where $deskFilterSubquery GROUP BY priority_status";
$result = runFilteredQuery($conn, $prioritySql, $allParams, $allTypes);
$priorityCounts = [];
while ($row = $result->fetch_assoc()) {
    $priorityCounts[$row['priority_status']] = $row['count'];
}
$priorityLabels = ['Normal', 'Urgent', 'Immediately'];
$priorityData = [];
foreach ($priorityLabels as $label) {
    $priorityData[] = $priorityCounts[$label] ?? 0;
}
$priorityColors = ['#10b981', '#ef4444', '#800000'];

// ─── SECURITY CLASSIFICATION ────────────────────────────────
$securitySql = "SELECT security_classification, COUNT(*) as total, 
    SUM(CASE WHEN il.status != 'completed' THEN 1 ELSE 0 END) as active
    FROM internal_letters il WHERE $where $deskFilterSubquery
    GROUP BY security_classification
    ORDER BY FIELD(security_classification, 'Top Secret', 'Secret', 'Confidential', 'Restricted', 'Public')";
$result = runFilteredQuery($conn, $securitySql, $allParams, $allTypes);
$securityStats = [];
while ($row = $result->fetch_assoc()) {
    $securityStats[] = $row;
}
$securityLabels = array_column($securityStats, 'security_classification');
$securityActive = array_column($securityStats, 'active');
$securityTotal = array_column($securityStats, 'total');

// ─── MONTHLY TREND (last 12 months) ─────────────────────────
$trendSql = "SELECT 
    DATE_FORMAT(il.created_at, '%Y-%m') as month,
    DATE_FORMAT(il.created_at, '%b %Y') as month_label,
    COUNT(*) as total,
    SUM(CASE WHEN il.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM internal_letters il WHERE $where $deskFilterSubquery AND il.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month DESC";
$result = runFilteredQuery($conn, $trendSql, $allParams, $allTypes);
$monthlyTrend = [];
while ($row = $result->fetch_assoc()) {
    $monthlyTrend[] = $row;
}
$trendLabels = array_reverse(array_column($monthlyTrend, 'month_label'));
$trendTotal = array_reverse(array_column($monthlyTrend, 'total'));
$trendCompleted = array_reverse(array_column($monthlyTrend, 'completed'));

// ─── PEAK HOURS (last 30 days) ─────────────────────────────
$hourSql = "SELECT HOUR(il.created_at) as hour, COUNT(*) as count
    FROM internal_letters il WHERE $where $deskFilterSubquery AND il.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(il.created_at) ORDER BY hour";
$result = runFilteredQuery($conn, $hourSql, $allParams, $allTypes);
$hourCounts = array_fill(0, 24, 0);
while ($row = $result->fetch_assoc()) {
    $hourCounts[(int)$row['hour']] = (int)$row['count'];
}
$hours = array_map(function($i) { return sprintf("%02d:00", $i); }, range(0, 23));

// ─── CATEGORY DISTRIBUTION ──────────────────────────────────
$catSql = "SELECT 
    COALESCE(lc.category, 'Uncategorized') as category,
    COUNT(il.id) as count
    FROM letter_categories lc
    LEFT JOIN internal_letters il ON lc.id = il.category_id AND il.is_deleted = 0
    WHERE lc.status = 'Active' OR lc.id IS NULL
    GROUP BY lc.id ORDER BY count DESC LIMIT 10";
$catResult = $conn->query($catSql);
$categories = [];
$categoryCounts = [];
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row['category'];
    $categoryCounts[] = $row['count'];
}

// ─── REGIMENT PERFORMANCE ────────────────────────────────────
$regSql = "SELECT 
    r.regiment_name,
    COALESCE(f.total_letters, 0) as total_letters,
    COALESCE(f.completed, 0) as completed
    FROM regiments r
    LEFT JOIN (
        SELECT regiment_id, 
            COUNT(*) as total_letters,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM internal_letters il
        WHERE $where $deskFilterSubquery
        GROUP BY regiment_id
    ) f ON r.id = f.regiment_id
    WHERE r.status = 'Active' AND f.total_letters > 0
    ORDER BY f.total_letters DESC
    LIMIT 10";
$result = runFilteredQuery($conn, $regSql, $allParams, $allTypes);
$regimentStats = $result->fetch_all(MYSQLI_ASSOC);

// ─── DESK PERFORMANCE ───────────────────────────────────────
$deskSql = "SELECT 
    d.id as desk_id,
    d.desk_name,
    COUNT(DISTINCT f.letter_id) as total_letters,
    COUNT(DISTINCT CASE WHEN il.status NOT IN ('completed') AND il.is_under_final_disposal = 0 THEN f.letter_id END) as in_progress,
    COUNT(DISTINCT CASE WHEN il.is_under_final_disposal = 1 THEN f.letter_id END) as final_disposal,
    COUNT(DISTINCT CASE WHEN il.status = 'completed' THEN f.letter_id END) as completed
    FROM desks d
    LEFT JOIN users u ON u.desk_id = d.id
    LEFT JOIN internal_letters_forwarding f ON f.forwarded_to = u.id
    LEFT JOIN internal_letters il ON f.letter_id = il.id AND $where $deskFilterSubquery
    WHERE d.is_deleted = 0 AND d.status = 'Active'
    GROUP BY d.id
    HAVING total_letters > 0
    ORDER BY total_letters DESC
    LIMIT 10";
$result = runFilteredQuery($conn, $deskSql, $allParams, $allTypes);
$deskStats = $result->fetch_all(MYSQLI_ASSOC);

// ─── AGING ALERTS (high priority >3 days) ──────────────────
$agingSql = "SELECT 
    il.id, il.cdr_number, il.letter_title, il.priority_status,
    DATEDIFF(NOW(), il.created_at) as days_old,
    u.name_with_initial as last_forwarded_to
    FROM internal_letters il
    LEFT JOIN internal_letters_forwarding lf ON il.id = lf.letter_id
    LEFT JOIN users u ON lf.forwarded_to = u.id
    WHERE $where $deskFilterSubquery AND il.status != 'completed'
        AND il.priority_status IN ('Urgent', 'Immediately')
        AND il.created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
    GROUP BY il.id
    ORDER BY days_old DESC
    LIMIT 5";
$result = runFilteredQuery($conn, $agingSql, $allParams, $allTypes);
$agingAlerts = $result->fetch_all(MYSQLI_ASSOC);

// ─── RECENT INTERNAL LETTERS ────────────────────────────────
$recentSql = "SELECT id, cdr_number, letter_title, letter_date, status, priority_status
    FROM internal_letters il WHERE $where $deskFilterSubquery
    ORDER BY created_at DESC LIMIT 10";
$result = runFilteredQuery($conn, $recentSql, $allParams, $allTypes);
$recentLetters = $result->fetch_all(MYSQLI_ASSOC);

// ─── REGIMENTS FOR FILTER DROPDOWN ──────────────────────────
$regiments = $conn->query("SELECT id, regiment_name FROM regiments WHERE status = 'Active' ORDER BY regiment_name");

// ─── DESKS FOR FILTER DROPDOWN ──────────────────────────────
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE is_deleted = 0 AND status = 'Active' ORDER BY desk_name");

// ─── CSRF Token ──────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Letters Dashboard | IDCMS</title>

    <!-- ============================================================
         FONTS — Sinhala rendering support
         ============================================================ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

            /* ── Font stacks ── */
            --font-sinhala: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', sans-serif;
            --font-english: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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

        /* ─── GLOBAL RESET — Sinhala-aware ─── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            /* Noto Sans Sinhala FIRST for proper Sinhala rendering */
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            /* Better font rendering quality */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-feature-settings: "kern" 1, "liga" 1;
            font-kerning: normal;
        }

        /* Sinhala text class for explicit usage */
        .sinhala-text {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', sans-serif;
            line-height: 1.75;
            letter-spacing: 0.015em;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            line-height: 1.6;
            letter-spacing: 0.01em;
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
        .brand-name {
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1.3;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }
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
            margin-bottom: 5px;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            cursor: pointer;
            line-height: 1.5;
        }
        .nav-btn:hover, .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .nav-btn i { width: 20px; font-size: 1.1rem; margin-right: 12px; text-align: center; flex-shrink: 0; }
        .submenu { padding-left: 20px; margin-bottom: 10px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn {
            display: block;
            padding: 8px 15px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border-left: 2px solid var(--border);
            transition: 0.2s;
            line-height: 1.5;
        }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub {
            color: var(--accent);
            border-left-color: var(--accent);
            background: transparent;
        }
        .sidebar-divider {
            font-size: 0.68rem;
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
        .top-nav h4 { font-weight: 800; margin: 0; line-height: 1.4; }
        .top-nav h4 small { font-size: 0.78rem; font-weight: 500; color: var(--text-muted); margin-left: 10px; }
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
            font-size: 0.68rem;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 0.6px;
        }
        .filter-control {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 14px;
            color: var(--text-main);
            font-size: 0.88rem;
            width: 100%;
            transition: border-color 0.2s;
            font-family: inherit;
            line-height: 1.5;
        }
        .filter-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
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
            padding: 9px 24px;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
            font-family: inherit;
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
            padding: 9px 20px;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            font-family: inherit;
        }
        .btn-reset:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ─── STATS ROW ──────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-icon.blue { background: var(--info); }
        .stat-icon.violet { background: var(--purple); }
        .stat-icon.green { background: var(--success); }
        .stat-icon.red { background: var(--danger); }
        .stat-info h6 {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            margin: 0 0 2px 0;
            font-weight: 800;
        }
        .stat-info .number {
            font-size: 1.7rem;
            font-weight: 800;
            line-height: 1.2;
            color: var(--text-main);
        }
        .stat-info .trend {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            line-height: 1.4;
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
            flex-wrap: wrap;
            gap: 8px;
        }
        .chart-card .chart-header h6 {
            font-weight: 800;
            font-size: 0.92rem;
            margin: 0;
            color: var(--text-main);
            line-height: 1.5;
        }
        .chart-card .chart-header small {
            color: var(--text-muted);
            font-size: 0.75rem;
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

        /* ─── TABLES ─────────────────────────────────────────── */
        .table-wrap { overflow-x: auto; }
        .perf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .perf-table th {
            text-align: left;
            padding: 10px 12px;
            color: var(--text-muted);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 800;
            border-bottom: 1px solid var(--border);
        }
        .perf-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            line-height: 1.55;
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
            flex-shrink: 0;
        }
        .cdr-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 700;
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
            font-weight: 700;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.5;
        }
        .recent-item .info .meta {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 3px;
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
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.4px;
            line-height: 1.3;
        }
        .status-not-forwarded { background: rgba(108, 117, 125, 0.15); color: #6c757d; }
        .status-forwarded { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .status-pending { background: rgba(255, 171, 0, 0.15); color: #FFAB00; }
        .status-completed { background: rgba(0, 200, 83, 0.15); color: #00C853; }

        .badge-priority {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.3;
        }
        .priority-urgent { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .priority-immediate { background: rgba(128, 0, 0, 0.15); color: #800000; }
        .priority-normal { background: rgba(16, 185, 129, 0.15); color: #10b981; }

        /* ─── EMPTY STATE ────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon { font-size: 3rem; opacity: 0.3; margin-bottom: 12px; }
        .empty-state h6 { font-weight: 800; color: var(--text-main); line-height: 1.5; }

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
            .two-col-grid { grid-template-columns: 1fr; }
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
            .stat-info h6 { font-size: 0.55rem; }
        }

        /* ─── DATE INPUT CALENDAR ICON ─── */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
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
            <div class="sidebar-divider">Correspondence</div>
            <button class="nav-btn active" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="internalSub" class="submenu show">
                <a href="../inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="../inward/inbox.php" class="sub-nav-btn">My Inbox</a>
                <a href="internal_dashboard.php" class="sub-nav-btn active-sub">Dashboard</a>
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
                        Internal Letters Dashboard
                        <small></small>
                    </h4>
                </div>
            </div>
        </header>

        <!-- ═══ FILTER BAR ═══ -->
        <form method="GET" class="filter-bar" id="filterForm">
            <div class="filter-group">
                <label for="regimentFilter"><i class="fa-regular fa-building me-1"></i> Regiment</label>
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
                <label for="deskFilter"><i class="fa-regular fa-building me-1"></i> Desk</label>
                <select name="desk" id="deskFilter" class="filter-control">
                    <option value="0">All Desks</option>
                    <?php while ($d = $desks->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>" <?= $filter_desk == $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['desk_name']) ?>
                        </option>
                    <?php endwhile; ?>
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
                <a href="internal_dashboard.php" class="btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
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
                    <h6>Not Forwarded</h6>
                    <div class="number" id="statNotForwarded"><?= $masterStats['not_forwarded'] ?></div>
                    <div class="trend">Awaiting initial action</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
                <div class="stat-info">
                    <h6>Forwarded</h6>
                    <div class="number" id="statForwarded"><?= $masterStats['forwarded'] ?></div>
                    <div class="trend">In circulation</div>
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
                <div class="stat-icon violet"><i class="fa-solid fa-inbox"></i></div>
                <div class="stat-info">
                    <h6>My Inbox</h6>
                    <div class="number" id="statInbox"><?= $inboxCount ?></div>
                    <div class="trend"><?= $inboxCount > 0 ? 'Unread forwards' : 'Inbox clear' ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-box-archive"></i></div>
                <div class="stat-info">
                    <h6>Under Final Disposal</h6>
                    <div class="number" id="statFinal"><?= $masterStats['under_final'] ?></div>
                    <div class="trend"><?= $masterStats['under_final'] > 0 ? 'Pending disposal' : 'All disposed' ?></div>
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
            <!-- Priority Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-bar me-2" style="color:var(--accent);"></i>Priority Breakdown</h6>
                    <small>by count</small>
                </div>
                <canvas id="priorityChart"></canvas>
            </div>
            <!-- Monthly Trend -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-line me-2" style="color:var(--accent);"></i>Monthly Trend</h6>
                    <small>Last 12 months</small>
                </div>
                <canvas id="trendChart" style="max-height:180px;"></canvas>
            </div>
            <!-- Security Classification -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-lock me-2" style="color:var(--purple);"></i>Security Classification</h6>
                    <small>Active vs Total</small>
                </div>
                <canvas id="securityChart"></canvas>
            </div>
            <!-- Peak Hours -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-clock me-2" style="color:var(--warning);"></i>Peak Activity Hours</h6>
                    <small>Last 30 days</small>
                </div>
                <canvas id="hourlyChart" style="max-height:180px;"></canvas>
            </div>
            <!-- Category Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-folder-open me-2" style="color:var(--info);"></i>Top Categories</h6>
                    <small>Distribution</small>
                </div>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- ═══ TWO-COLUMN: REGIMENT + DESK PERFORMANCE ═══ -->
        <div class="two-col-grid">
            <!-- Regiment Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-building me-2" style="color:var(--purple);"></i>Regiment Performance</h6>
                    <small>Total · Completed</small>
                </div>
                <?php if (empty($regimentStats)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-building"></i></div>
                        <h6>No regiment data</h6>
                        <small>Letters assigned to regiments will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Regiment</th>
                                    <th>Total</th>
                                    <th>Completed</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($regimentStats as $row):
                                    $total = $row['total_letters'];
                                    $completed = $row['completed'];
                                    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['regiment_name']) ?></strong></td>
                                        <td><?= $total ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.78rem;min-width:36px;"><?= $progress ?>%</span>
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
                    <small>Total · In Progress · Final Disposal</small>
                </div>
                <?php if (empty($deskStats)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-building"></i></div>
                        <h6>No desk data</h6>
                        <small>Letters forwarded to desks will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Desk</th>
                                    <th>Total</th>
                                    <th>In Progress</th>
                                    <th>Final Disposal</th>
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
                                        <td><?= $row['in_progress'] ?></td>
                                        <td><?= $row['final_disposal'] ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.78rem;min-width:36px;"><?= $progress ?>%</span>
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
                <h6><i class="fa-solid fa-triangle-exclamation me-2" style="color:var(--danger);"></i>High Priority Aging (&gt;3 days)</h6>
                <small>Urgent attention</small>
            </div>
            <?php if (empty($agingAlerts)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-regular fa-circle-check"></i></div>
                    <h6>No aging high-priority letters</h6>
                    <small>All urgent letters are on track</small>
                </div>
            <?php else: ?>
                <?php foreach ($agingAlerts as $alert): ?>
                    <div class="alert-card">
                        <div class="alert-icon"><i class="fa-regular fa-clock"></i></div>
                        <div style="flex:1;">
                            <a href="../inward/internal_letter_details.php?id=<?= $alert['id'] ?>" class="cdr-link"><?= htmlspecialchars($alert['cdr_number']) ?></a>
                            <div class="small" style="color:var(--text-muted);"><?= htmlspecialchars(substr($alert['letter_title'], 0, 50)) ?>...</div>
                            <div class="small mt-1">
                                <span class="badge-priority priority-<?= strtolower($alert['priority_status']) ?>"><?= $alert['priority_status'] ?></span>
                                <span style="color:var(--text-muted);" class="ms-2"><?= $alert['days_old'] ?> days old</span>
                                <?php if (!empty($alert['last_forwarded_to'])): ?>
                                    <span style="color:var(--text-muted);" class="ms-2"><i class="fa-regular fa-user"></i> <?= htmlspecialchars($alert['last_forwarded_to']) ?></span>
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
                <h6><i class="fa-solid fa-list me-2" style="color:var(--accent);"></i>Recent Letters</h6>
                <a href="internal_letters.php" style="color:var(--text-muted);font-size:0.78rem;">View All</a>
            </div>
            <?php if (empty($recentLetters)): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-regular fa-envelope"></i></div>
                    <h6>No recent letters</h6>
                    <small>New letters will appear here</small>
                </div>
            <?php else: ?>
                <div class="recent-list">
                    <?php foreach ($recentLetters as $letter):
                        $status = $letter['status'] ?: 'Not Forwarded';
                        $statusClass = 'status-not-forwarded';
                        if ($status === 'Forwarded') $statusClass = 'status-forwarded';
                        elseif ($status === 'pending') $statusClass = 'status-pending';
                        elseif ($status === 'completed') $statusClass = 'status-completed';

                        $priority = $letter['priority_status'] ?: 'Normal';
                        $priorityClass = 'priority-normal';
                        if ($priority === 'Urgent') $priorityClass = 'priority-urgent';
                        elseif ($priority === 'Immediately') $priorityClass = 'priority-immediate';
                    ?>
                        <div class="recent-item">
                            <div class="info">
                                <div class="title">
                                    <a href="../inward/internal_letter_details.php?id=<?= $letter['id'] ?>" class="cdr-link">
                                        <?= htmlspecialchars($letter['cdr_number']) ?>
                                    </a>
                                    <span style="font-weight:500;color:var(--text-muted);font-size:0.88rem;margin-left:6px;">
                                        <?= htmlspecialchars($letter['letter_title']) ?>
                                    </span>
                                </div>
                                <div class="meta">
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($letter['letter_date'])) ?></span>
                                </div>
                            </div>
                            <div class="right">
                                <span class="badge-priority <?= $priorityClass ?>"><?= $priority ?></span>
                                <span class="badge-status <?= $statusClass ?>"><?= $status ?></span>
                                <a href="../inward/internal_letter_details.php?id=<?= $letter['id'] ?>" style="padding:4px 12px;font-size:0.75rem;">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div style="margin-top:30px;text-align:center;font-size:0.75rem;color:var(--text-muted);opacity:0.7;border-top:1px solid var(--border);padding-top:20px;">
            IDCMS Internal Dashboard &bull; <?= date('Y') ?> &bull; Real-time insights
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
                            font: { size: 11, weight: '600', family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" },
                            boxWidth: 12,
                            padding: 12,
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: c.muted, font: { size: 10, family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" } },
                        grid: { color: c.grid, drawBorder: false },
                    },
                    y: {
                        ticks: { color: c.muted, font: { size: 10, family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" } },
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
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 11, weight: '600', family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" }, color: getThemeColors().text }
                    }
                },
                animation: { animateRotate: true, duration: 1200 }
            }
        });

        // ─── 2. PRIORITY CHART ──────────────────────────────────
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($priorityLabels) ?>,
                datasets: [{
                    label: 'Letters',
                    data: <?= json_encode($priorityData) ?>,
                    backgroundColor: <?= json_encode($priorityColors) ?>,
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

        // ─── 3. TREND CHART ─────────────────────────────────────
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
                plugins: { legend: { display: true, position: 'bottom', labels: { color: getThemeColors().text, font: { family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" } } } },
                scales: {
                    x: { ...getChartDefaults().scales.x, ticks: { maxTicksLimit: 12, font: { size: 9 } } },
                    y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1, font: { size: 9 } } }
                },
                interaction: { intersect: false, mode: 'index' },
                animation: { duration: 1200 }
            }
        });

        // ─── 4. SECURITY CHART ──────────────────────────────────
        const secCtx = document.getElementById('securityChart').getContext('2d');
        new Chart(secCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($securityLabels) ?>,
                datasets: [
                    { label: 'Active', data: <?= json_encode($securityActive) ?>, backgroundColor: '#4318FF', borderRadius: 6 },
                    { label: 'Total', data: <?= json_encode($securityTotal) ?>, backgroundColor: 'rgba(67, 24, 255, 0.3)', borderRadius: 6 }
                ]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: { legend: { display: true, position: 'bottom', labels: { color: getThemeColors().text, font: { family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" } } } },
                scales: { y: { ...getChartDefaults().scales.y, ticks: { stepSize: 1 } } },
                animation: { duration: 1000 }
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

        // ─── 6. CATEGORY CHART ──────────────────────────────────
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($categories) ?>,
                datasets: [{
                    data: <?= json_encode($categoryCounts) ?>,
                    backgroundColor: ['#4318FF', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#3b82f6', '#14b8a6', '#f97316', '#6366f1'],
                    borderWidth: 0,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyle: 'circle', font: { size: 10, weight: '600', family: "'Noto Sans Sinhala', 'Plus Jakarta Sans', sans-serif" }, color: getThemeColors().text }
                    }
                },
                cutout: '60%',
                animation: { animateRotate: true, duration: 1200 }
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
                { id: 'statNotForwarded', val: <?= $masterStats['not_forwarded'] ?> },
                { id: 'statForwarded', val: <?= $masterStats['forwarded'] ?> },
                { id: 'statCompleted', val: <?= $masterStats['completed'] ?> },
                { id: 'statInbox', val: <?= $inboxCount ?> },
                { id: 'statFinal', val: <?= $masterStats['under_final'] ?> },
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

        console.log('Internal Dashboard loaded successfully');
    </script>
</body>
</html>