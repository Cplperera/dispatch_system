<?php
/*
 * ============================================================
 * TASK DASHBOARD – Advanced Command Center with Filters
 * ============================================================
 * Features:
 *   • Real-time stats with animated counters
 *   • Interactive charts (Chart.js)
 *   • Desk & Date Range filters
 *   • Agency reply performance
 *   • Desk performance (total, pending, partial)
 *   • Recent task activity feed
 *   • Dark/Light theme sync (using localStorage)
 *   • Fully responsive – fixed overflow issues
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_TASK_LETTERS')) {
    die('Access Denied!');
}

$userId = $_SESSION['user_id'];
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// ─── AUTO-UPDATE STATUS ENGINE ──────────────────────────────
$autoUpdateSql = "
    UPDATE task_letters t
    LEFT JOIN (
        SELECT task_letter_id, COUNT(*) AS assigned_count 
        FROM task_letter_agencies 
        GROUP BY task_letter_id
    ) a ON t.id = a.task_letter_id
    LEFT JOIN (
        SELECT task_letter_id, COUNT(DISTINCT regiment_id) AS reply_count 
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

// ─── FILTERS ──────────────────────────────────────────────────
$filter_desk = isset($_GET['desk']) ? intval($_GET['desk']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause for task-based queries
$taskWhere = "1=1";
$taskParams = [];
$taskTypes = "";

if ($filter_desk > 0) {
    $taskWhere .= " AND u.desk_id = ?";
    $taskParams[] = $filter_desk;
    $taskTypes .= "i";
}
if (!empty($filter_date_from)) {
    $taskWhere .= " AND DATE(t.created_at) >= ?";
    $taskParams[] = $filter_date_from;
    $taskTypes .= "s";
}
if (!empty($filter_date_to)) {
    $taskWhere .= " AND DATE(t.created_at) <= ?";
    $taskParams[] = $filter_date_to;
    $taskTypes .= "s";
}

// ─── STATS ────────────────────────────────────────────────────
$statsSql = "SELECT COUNT(*) as total FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['total'] = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as pending FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND (t.status = 'Pending' OR t.status = '' OR t.status IS NULL)";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['pending'] = (int) $stmt->get_result()->fetch_assoc()['pending'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as partial FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.status = 'Partially Replied'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['partial'] = (int) $stmt->get_result()->fetch_assoc()['partial'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as completed FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.status = 'Completed'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['completed'] = (int) $stmt->get_result()->fetch_assoc()['completed'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as overdue FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.request_date < CURDATE() AND t.status != 'Completed'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['overdue'] = (int) $stmt->get_result()->fetch_assoc()['overdue'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as high FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.priority_status = 'High' AND t.status != 'Completed'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['high_priority'] = (int) $stmt->get_result()->fetch_assoc()['high'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as medium FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.priority_status = 'Medium'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['medium_priority'] = (int) $stmt->get_result()->fetch_assoc()['medium'];
$stmt->close();

$statsSql = "SELECT COUNT(*) as low FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND t.priority_status = 'Low'";
$stmt = $conn->prepare($statsSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$stats['low_priority'] = (int) $stmt->get_result()->fetch_assoc()['low'];
$stmt->close();

// ─── TASKS OVER TIME (last 30 days) ──────────────────────────
$timeData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sql = "SELECT COUNT(*) as count FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE $taskWhere AND DATE(t.created_at) = '$date'";
    $stmt = $conn->prepare($sql);
    if (!empty($taskParams)) {
        $stmt->bind_param($taskTypes, ...$taskParams);
    }
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    $timeData[] = ['date' => date('d M', strtotime($date)), 'count' => $count];
}

// ─── STATUS DISTRIBUTION ─────────────────────────────────────
$statusLabels = ['Pending', 'Partially Replied', 'Completed'];
$statusCounts = [$stats['pending'], $stats['partial'], $stats['completed']];
$statusColors = ['#FFAB00', '#8B5CF6', '#00C853'];

// ─── PRIORITY DISTRIBUTION ──────────────────────────────────
$priorityLabels = ['High', 'Medium', 'Low'];
$priorityCounts = [$stats['high_priority'], $stats['medium_priority'], $stats['low_priority']];
$priorityColors = ['#ef4444', '#f59e0b', '#3b82f6'];

// ─── AGENCY PERFORMANCE ──────────────────────────────────────
$agencySql = "
    SELECT 
        r.id AS regiment_id,
        r.regiment_name,
        COUNT(DISTINCT tla.task_letter_id) AS assigned_tasks,
        COUNT(DISTINCT tr.task_letter_id) AS replied_tasks,
        ROUND(IFNULL(COUNT(DISTINCT tr.task_letter_id) / NULLIF(COUNT(DISTINCT tla.task_letter_id), 0) * 100, 0)) AS reply_rate
    FROM regiments r
    LEFT JOIN task_letter_agencies tla ON r.id = tla.regiment_id
    LEFT JOIN task_letters t ON tla.task_letter_id = t.id
    LEFT JOIN users u ON t.assigned_by = u.id
    LEFT JOIN task_replies tr ON tla.task_letter_id = tr.task_letter_id AND r.id = tr.regiment_id
    WHERE r.status = 'Active' AND $taskWhere
    GROUP BY r.id, r.regiment_name
    HAVING assigned_tasks > 0
    ORDER BY reply_rate DESC, assigned_tasks DESC
    LIMIT 10
";
$stmt = $conn->prepare($agencySql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$agencyPerf = $stmt->get_result();
$stmt->close();

// ─── DESK PERFORMANCE ────────────────────────────────────────
$deskWhere = "1=1";
$deskParams = [];
$deskTypes = "";
if ($filter_desk > 0) {
    $deskWhere .= " AND d.id = ?";
    $deskParams[] = $filter_desk;
    $deskTypes .= "i";
}
if (!empty($filter_date_from)) {
    $deskWhere .= " AND DATE(t.created_at) >= ?";
    $deskParams[] = $filter_date_from;
    $deskTypes .= "s";
}
if (!empty($filter_date_to)) {
    $deskWhere .= " AND DATE(t.created_at) <= ?";
    $deskParams[] = $filter_date_to;
    $deskTypes .= "s";
}

$deskSql = "
    SELECT 
        d.id AS desk_id,
        d.desk_name,
        COUNT(t.id) AS total_tasks,
        SUM(CASE WHEN t.status = 'Pending' OR t.status = '' OR t.status IS NULL THEN 1 ELSE 0 END) AS pending_tasks,
        SUM(CASE WHEN t.status = 'Partially Replied' THEN 1 ELSE 0 END) AS partial_tasks,
        SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks
    FROM desks d
    LEFT JOIN users u ON u.desk_id = d.id
    LEFT JOIN task_letters t ON t.assigned_by = u.id
    WHERE d.is_deleted = 0 AND d.status = 'Active' AND $deskWhere
    GROUP BY d.id, d.desk_name
    HAVING total_tasks > 0
    ORDER BY total_tasks DESC
";
$stmt = $conn->prepare($deskSql);
if (!empty($deskParams)) {
    $stmt->bind_param($deskTypes, ...$deskParams);
}
$stmt->execute();
$deskPerf = $stmt->get_result();
$stmt->close();

// ─── OVERDUE BREAKDOWN ──────────────────────────────────────
$overdueSql = "
    SELECT 
        CASE 
            WHEN DATEDIFF(CURDATE(), t.request_date) <= 7 THEN '1-7 days'
            WHEN DATEDIFF(CURDATE(), t.request_date) <= 14 THEN '8-14 days'
            WHEN DATEDIFF(CURDATE(), t.request_date) <= 30 THEN '15-30 days'
            ELSE '30+ days'
        END AS overdue_range,
        COUNT(*) AS count
    FROM task_letters t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE $taskWhere AND t.request_date < CURDATE() AND t.status != 'Completed'
    GROUP BY overdue_range
    ORDER BY MIN(DATEDIFF(CURDATE(), t.request_date))
";
$stmt = $conn->prepare($overdueSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$overdueResult = $stmt->get_result();
$stmt->close();
$overdueLabels = [];
$overdueCounts = [];
while ($row = $overdueResult->fetch_assoc()) {
    $overdueLabels[] = $row['overdue_range'];
    $overdueCounts[] = (int) $row['count'];
}

// ─── REPLY ACTIVITY (last 7 days) ──────────────────────────
$replyActivity = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sql = "SELECT COUNT(*) as count FROM task_replies tr 
            JOIN task_letters t ON tr.task_letter_id = t.id 
            LEFT JOIN users u ON t.assigned_by = u.id 
            WHERE $taskWhere AND DATE(tr.replied_at) = '$date'";
    $stmt = $conn->prepare($sql);
    if (!empty($taskParams)) {
        $stmt->bind_param($taskTypes, ...$taskParams);
    }
    $stmt->execute();
    $count = (int) $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    $replyActivity[] = ['date' => date('d M', strtotime($date)), 'count' => $count];
}

// ─── RECENT TASKS ────────────────────────────────────────────
$recentSql = "
    SELECT 
        t.id,
        t.tsk_cdr_number,
        t.letter_ref,
        t.title,
        t.status,
        t.priority_status,
        t.request_date,
        t.created_at,
        u.name_with_initial AS assigner_name,
        (SELECT COUNT(*) FROM task_letter_agencies WHERE task_letter_id = t.id) AS agency_count,
        (SELECT COUNT(*) FROM task_replies WHERE task_letter_id = t.id) AS reply_count
    FROM task_letters t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE $taskWhere
    ORDER BY t.created_at DESC
    LIMIT 8
";
$stmt = $conn->prepare($recentSql);
if (!empty($taskParams)) {
    $stmt->bind_param($taskTypes, ...$taskParams);
}
$stmt->execute();
$recentTasks = $stmt->get_result();
$stmt->close();

// ─── DESK LIST FOR FILTER DROPDOWN ─────────────────────────
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE is_deleted = 0 AND status = 'Active' ORDER BY desk_name");

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
    <title>Task Dashboard | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <script src="../../assets/js/chart.umd.min.js"></script>
    </script>
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

        /* ─── RESET & BASE ──────────────────────────────────── */
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

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-body);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

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

        .brand-box {
            padding: 0 10px;
            margin-bottom: 40px;
        }
        .brand-name {
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1.2;
            color: var(--text-main);
        }
        .brand-name i {
            color: var(--accent);
        }

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
        .nav-btn:hover,
        .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .nav-btn i {
            width: 20px;
            font-size: 1.1rem;
            margin-right: 12px;
        }

        .submenu {
            padding-left: 20px;
            margin-bottom: 10px;
            display: none;
        }
        .submenu.show {
            display: block;
        }
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
        .sub-nav-btn:hover,
        .sub-nav-btn.active-sub {
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
            max-width: 100%;
            overflow-x: hidden;
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
            flex-wrap: wrap;
            gap: 12px;
        }
        .top-nav h4 {
            font-weight: 700;
            margin: 0;
            font-size: 1.2rem;
        }
        .top-nav h4 small {
            font-size: 0.75rem;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 10px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-badge .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }
        .user-badge .name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .user-badge .rank {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

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
            flex: 1 1 160px;
            min-width: 140px;
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
        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            flex-shrink: 0;
        }
        .stat-icon.purple {
            background: var(--accent);
        }
        .stat-icon.yellow {
            background: var(--warning);
        }
        .stat-icon.violet {
            background: var(--purple);
        }
        .stat-icon.green {
            background: var(--success);
        }
        .stat-icon.red {
            background: var(--danger);
        }
        .stat-icon.blue {
            background: var(--info);
        }

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
        .stat-info .trend .up {
            color: var(--success);
        }
        .stat-info .trend .down {
            color: var(--danger);
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
            overflow: hidden;
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

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        /* ─── OVERDUE BREAKDOWN ─────────────────────────────── */
        .overdue-bars {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            height: 120px;
            padding-top: 10px;
            flex-wrap: wrap;
        }
        .overdue-bar-wrap {
            flex: 1;
            min-width: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .overdue-bar {
            width: 100%;
            max-width: 50px;
            border-radius: 8px 8px 0 0;
            background: var(--danger);
            min-height: 8px;
            transition: height 0.6s ease;
        }
        .overdue-bar-label {
            font-size: 0.6rem;
            color: var(--text-muted);
            text-align: center;
            font-weight: 600;
        }
        .overdue-bar-count {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* ─── TABLE WRAP ────────────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -4px;
        }
        .perf-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 500px;
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
        .perf-table tr:last-child td {
            border-bottom: none;
        }
        .perf-table .progress {
            height: 6px;
            background: var(--bg-body);
            border-radius: 10px;
            overflow: hidden;
            width: 100px;
            min-width: 40px;
        }
        .perf-table .progress-bar {
            height: 100%;
            border-radius: 10px;
            background: var(--accent);
            transition: width 0.8s ease;
        }

        /* ─── RECENT TASKS ───────────────────────────────────── */
        .recent-task-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .recent-task-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg-body);
            border-radius: 14px;
            transition: 0.2s;
            border-left: 3px solid transparent;
            flex-wrap: wrap;
            gap: 8px;
        }
        .recent-task-item:hover {
            border-left-color: var(--accent);
            transform: translateX(4px);
        }
        .recent-task-item .task-info {
            flex: 1;
            min-width: 0;
        }
        .recent-task-item .task-info .title {
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .recent-task-item .task-info .meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 2px;
        }
        .recent-task-item .task-info .meta span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .recent-task-item .task-right {
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
        .status-pending {
            background: rgba(255, 171, 0, 0.15);
            color: #FFAB00;
        }
        .status-partial {
            background: rgba(139, 92, 246, 0.15);
            color: #8b5cf6;
        }
        .status-completed {
            background: rgba(0, 200, 83, 0.15);
            color: #00C853;
        }

        .badge-priority {
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .priority-high {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }
        .priority-medium {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        .priority-low {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .task-ref-link {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .task-ref-link:hover {
            text-decoration: underline;
        }

        /* ─── EMPTY STATE ───────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        .empty-state .icon {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 12px;
        }
        .empty-state h6 {
            font-weight: 700;
            color: var(--text-main);
        }

        /* animation for counters */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .stat-card {
            animation: fadeUp 0.5s ease forwards;
        }
        .stat-card:nth-child(1) {
            animation-delay: 0.05s;
        }
        .stat-card:nth-child(2) {
            animation-delay: 0.10s;
        }
        .stat-card:nth-child(3) {
            animation-delay: 0.15s;
        }
        .stat-card:nth-child(4) {
            animation-delay: 0.20s;
        }
        .stat-card:nth-child(5) {
            animation-delay: 0.25s;
        }
        .stat-card:nth-child(6) {
            animation-delay: 0.30s;
        }

        /* two-col grid for agency + desk */
        .two-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        @media (max-width: 992px) {
            .two-col-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Fix for date picker icon in dark mode */
        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(10);
        }

        /* ─── RESPONSIVE ────────────────────────────────────── */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                width: 280px;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
            .mobile-toggle {
                display: block;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .top-nav {
                padding: 16px 18px;
            }
            .top-nav h4 {
                font-size: 1.1rem;
            }
            .top-nav h4 small {
                display: none;
            }
            .stat-card {
                padding: 14px 16px;
            }
            .stat-info .number {
                font-size: 1.3rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .recent-task-item {
                flex-wrap: wrap;
                gap: 8px;
            }
            .recent-task-item .task-right {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }
            .chart-card {
                padding: 16px;
            }
            .perf-table .progress {
                width: 60px;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .filter-group {
                min-width: 100%;
            }
            .filter-actions {
                justify-content: flex-end;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .stat-card {
                padding: 12px 14px;
            }
            .stat-info .number {
                font-size: 1.1rem;
            }
            .stat-icon {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }
            .stat-info h6 {
                font-size: 0.5rem;
            }
        }
    </style>
</head>
<body>

    <!-- ════════════════════════════════════════════════════════
    SIDEBAR
    ════════════════════════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar">
        <div class="brand-box">
            <div class="brand-name">
                <i class="fa-solid fa-shield-halved"></i> IDCMS
            </div>
        </div>

        <nav>
            <a href="../../letters/dashboard/dashboard.php" class="nav-btn">
                <i class="fa-solid fa-house"></i> Home Page
            </a>

            <div class="sidebar-divider">Management</div>

            <button class="nav-btn active" onclick="toggleSubmenu('taskSub')">
                <i class="fa-solid fa-list-check"></i> Tasking Letters
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="taskSub" class="submenu show">
                <a href="../tasking/task_letters.php" class="sub-nav-btn">Add New Task</a>
                <a href="../tasking/task_list.php" class="sub-nav-btn">View Tasking</a>
                <a href="task_dashboard.php" class="sub-nav-btn active-sub">Dashboard</a>
            </div>

            <a href="../../auth/logout.php" class="nav-btn mt-4" style="color:var(--danger);">
                <i class="fa-solid fa-power-off"></i> Logout
            </a>
        </nav>
    </aside>

    <!-- ════════════════════════════════════════════════════════
    MAIN CONTENT
    ════════════════════════════════════════════════════════ -->
    <main class="main-content">

        <!-- Top Nav -->
        <header class="top-nav">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h4>
                        Task Letters Dashboard
                        <small></small>
                    </h4>
                </div>
            </div>
        </header>

        <!-- ═══ FILTER BAR ═══ -->
        <form method="GET" class="filter-bar" id="filterForm">
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
                <a href="task_dashboard.php" class="btn-reset"><i class="fa-solid fa-undo"></i> Reset</a>
            </div>
        </form>

        <!-- ═══ STATS ROW ═══ -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-envelope"></i></div>
                <div class="stat-info">
                    <h6>Total Tasks</h6>
                    <div class="number" id="statTotal"><?= $stats['total'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-info">
                    <h6>Pending</h6>
                    <div class="number" id="statPending"><?= $stats['pending'] ?></div>
                    <div class="trend"><?= $stats['pending'] > 0 ? 'Awaiting reply' : '✅ All replied' ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon violet"><i class="fa-solid fa-reply-all"></i></div>
                <div class="stat-info">
                    <h6>Partially Replied</h6>
                    <div class="number" id="statPartial"><?= $stats['partial'] ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div>
                <div class="stat-info">
                    <h6>Completed</h6>
                    <div class="number" id="statCompleted"><?= $stats['completed'] ?></div>
                    <div class="trend">
                        <?php
                        $completionRate = $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100) : 0;
                        echo $completionRate . '% completion';
                        ?>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h6>Overdue</h6>
                    <div class="number" id="statOverdue"><?= $stats['overdue'] ?></div>
                    <div class="trend <?= $stats['overdue'] > 0 ? 'down' : '' ?>">
                        <?= $stats['overdue'] > 0 ? 'Needs attention' : 'On track' ?>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-flag"></i></div>
                <div class="stat-info">
                    <h6>High Priority</h6>
                    <div class="number" id="statHigh"><?= $stats['high_priority'] ?></div>
                    <div class="trend"><?= $stats['high_priority'] > 0 ? 'Urgent' : 'No urgent tasks' ?></div>
                </div>
            </div>
        </div>

        <!-- ═══ CHARTS ROW ═══ -->
        <div class="charts-grid">

            <!-- Status Distribution (Donut) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-pie me-2" style="color:var(--accent);"></i>Status Distribution</h6>
                    <small><?= $stats['total'] ?> total</small>
                </div>
                <canvas id="statusChart"></canvas>
            </div>

            <!-- Priority Distribution (Bar) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-bar me-2" style="color:var(--accent);"></i>Priority Breakdown</h6>
                    <small>by count</small>
                </div>
                <canvas id="priorityChart"></canvas>
            </div>

            <!-- Tasks Over Time (Line) -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-chart-line me-2" style="color:var(--accent);"></i>Task Creation Trend</h6>
                    <small>Last 30 days</small>
                </div>
                <canvas id="trendChart" style="max-height:180px;"></canvas>
            </div>

            <!-- Overdue Breakdown (Custom Bars) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-clock me-2" style="color:var(--danger);"></i>Overdue Breakdown</h6>
                    <small><?= $stats['overdue'] ?> overdue</small>
                </div>
                <?php if (empty($overdueLabels)): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-circle-check"></i></div>
                        <h6>No overdue tasks</h6>
                        <small>All tasks are on schedule 🎉</small>
                    </div>
                <?php else: ?>
                    <div class="overdue-bars">
                        <?php
                        $maxOverdue = max($overdueCounts) ?: 1;
                        foreach ($overdueLabels as $idx => $label):
                            $height = max(8, ($overdueCounts[$idx] / $maxOverdue) * 100);
                        ?>
                            <div class="overdue-bar-wrap">
                                <div class="overdue-bar-count"><?= $overdueCounts[$idx] ?></div>
                                <div class="overdue-bar" style="height:<?= $height ?>px;"></div>
                                <div class="overdue-bar-label"><?= $label ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reply Activity (Line) -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-reply me-2" style="color:var(--success);"></i>Reply Activity</h6>
                    <small>Last 7 days</small>
                </div>
                <canvas id="replyChart" style="max-height:140px;"></canvas>
            </div>

        </div>

        <!-- ═══ TWO-COLUMN: AGENCY + DESK PERFORMANCE ═══ -->
        <div class="two-col-grid">

            <!-- Agency Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-building me-2" style="color:var(--purple);"></i>Agency Performance</h6>
                    <small>Reply rate</small>
                </div>
                <?php if ($agencyPerf->num_rows === 0): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-building"></i></div>
                        <h6>No agency data</h6>
                        <small>Assign tasks to see performance</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Agency</th>
                                    <th>Assigned</th>
                                    <th>Replied</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $agencyPerf->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['regiment_name']) ?></strong></td>
                                        <td><?= $row['assigned_tasks'] ?></td>
                                        <td><?= $row['replied_tasks'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $row['reply_rate'] ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.75rem;min-width:36px;">
                                                    <?= $row['reply_rate'] ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desk Performance -->
            <div class="chart-card">
                <div class="chart-header">
                    <h6><i class="fa-solid fa-desktop me-2" style="color:var(--info);"></i>Desk Performance</h6>
                    <small>Total · Pending · Partial</small>
                </div>
                <?php if ($deskPerf->num_rows === 0): ?>
                    <div class="empty-state">
                        <div class="icon"><i class="fa-regular fa-building"></i></div>
                        <h6>No desk data</h6>
                        <small>Tasks assigned to desks will appear here</small>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Desk</th>
                                    <th>Total</th>
                                    <th>Pending</th>
                                    <th>Partial</th>
                                    <th>Completed</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $deskPerf->fetch_assoc()):
                                    $total = $row['total_tasks'];
                                    $completed = $row['completed_tasks'];
                                    $progress = $total > 0 ? round(($completed / $total) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['desk_name']) ?></strong></td>
                                        <td><?= $total ?></td>
                                        <td><?= $row['pending_tasks'] ?></td>
                                        <td><?= $row['partial_tasks'] ?></td>
                                        <td><?= $completed ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress">
                                                    <div class="progress-bar" style="width:<?= $progress ?>%;"></div>
                                                </div>
                                                <span style="font-weight:700;font-size:0.75rem;min-width:36px;">
                                                    <?= $progress ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- ═══ RECENT TASKS ═══ -->
        <div class="chart-card">
            <div class="chart-header">
                <h6><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent);"></i>Recent Tasks</h6>
                <small>Latest activity</small>
            </div>
            <?php if ($recentTasks->num_rows === 0): ?>
                <div class="empty-state">
                    <div class="icon"><i class="fa-regular fa-file-lines"></i></div>
                    <h6>No tasks yet</h6>
                    <small>Create your first task letter</small>
                </div>
            <?php else: ?>
                <div class="recent-task-list">
                    <?php while ($task = $recentTasks->fetch_assoc()):
                        $status = $task['status'] ?: 'Pending';
                        $statusClass = 'status-pending';
                        if ($status === 'Partially Replied') $statusClass = 'status-partial';
                        elseif ($status === 'Completed') $statusClass = 'status-completed';

                        $priority = $task['priority_status'] ?: 'Medium';
                        $priorityClass = 'priority-medium';
                        if ($priority === 'High') $priorityClass = 'priority-high';
                        elseif ($priority === 'Low') $priorityClass = 'priority-low';

                        $isOverdue = (!empty($task['request_date']) && strtotime($task['request_date']) < time() && $status !== 'Completed');
                    ?>
                        <div class="recent-task-item">
                            <div class="task-info">
                                <div class="title">
                                    <a href="../tasking/view_task.php?id=<?= $task['id'] ?>" class="task-ref-link" target="_blank">
                                        <?= htmlspecialchars($task['tsk_cdr_number'] ?: 'N/A') ?>
                                    </a>
                                    <span style="font-weight:400;color:var(--text-muted);font-size:0.85rem;margin-left:6px;">
                                        <?= htmlspecialchars($task['title']) ?>
                                    </span>
                                </div>
                                <div class="meta">
                                    <span><i class="fa-regular fa-user"></i> <?= htmlspecialchars($task['assigner_name'] ?? 'N/A') ?></span>
                                    <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y', strtotime($task['created_at'])) ?></span>
                                    <span><i class="fa-regular fa-building"></i> <?= $task['agency_count'] ?> agency(ies)</span>
                                    <span><i class="fa-regular fa-reply"></i> <?= $task['reply_count'] ?> reply(ies)</span>
                                    <?php if ($isOverdue): ?>
                                        <span style="color:var(--danger);font-weight:700;">
                                            <i class="fa-solid fa-triangle-exclamation"></i> Overdue
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="task-right">
                                <span class="badge-priority <?= $priorityClass ?>"><?= $priority ?></span>
                                <span class="badge-status <?= $statusClass ?>"><?= $status ?></span>
                                <a href="../tasking/view_task.php?id=<?= $task['id'] ?>" class="quick-btn" style="padding:4px 12px;font-size:0.7rem;">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div style="margin-top:30px;text-align:center;font-size:0.7rem;color:var(--text-muted);opacity:0.6;border-top:1px solid var(--border);padding-top:20px;">
            IDCMS Task Dashboard &bull; <?= date('Y') ?> &bull; Real-time insights
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

        // ─── CHART.JS HELPERS ──────────────────────────────────
        function getThemeColors() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            return {
                text: isDark ? '#FFFFFF' : '#1B2559',
                muted: isDark ? '#A3AED0' : '#707EAE',
                grid: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                cardBg: isDark ? '#111C44' : '#FFFFFF',
            };
        }

        function getChartDefaults() {
            const c = getThemeColors();
            return {
                color: c.text,
                borderColor: c.text,
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

        // ─── 1. STATUS CHART (Donut) ──────────────────────────
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: <?= json_encode($statusColors) ?>,
                borderWidth: 0,
                hoverOffset: 8,
            }]
        };
        new Chart(statusCtx, {
            type: 'doughnut',
            data: statusData,
            options: {
                ...getChartDefaults(),
                cutout: '65%',
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 14,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '600' },
                            color: getThemeColors().text,
                        }
                    }
                },
                animation: { animateRotate: true, duration: 1200 }
            }
        });

        // ─── 2. PRIORITY CHART (Bar) ──────────────────────────
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($priorityLabels) ?>,
                datasets: [{
                    label: 'Tasks',
                    data: <?= json_encode($priorityCounts) ?>,
                    backgroundColor: <?= json_encode($priorityColors) ?>,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.5,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        ...getChartDefaults().scales.y,
                        ticks: { stepSize: 1 }
                    }
                },
                animation: { duration: 1000 }
            }
        });

        // ─── 3. TREND CHART (Line) ────────────────────────────
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendLabels = <?= json_encode(array_column($timeData, 'date')) ?>;
        const trendCounts = <?= json_encode(array_column($timeData, 'count')) ?>;
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Tasks Created',
                    data: trendCounts,
                    borderColor: '#7551FF',
                    backgroundColor: 'rgba(117, 81, 255, 0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#7551FF',
                    borderWidth: 2.5,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ...getChartDefaults().scales.x,
                        ticks: { maxTicksLimit: 15, font: { size: 9 } },
                    },
                    y: {
                        ...getChartDefaults().scales.y,
                        ticks: { stepSize: 1, font: { size: 9 } },
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                animation: { duration: 1200 }
            }
        });

        // ─── 4. REPLY ACTIVITY (Line) ─────────────────────────
        const replyCtx = document.getElementById('replyChart').getContext('2d');
        const replyLabels = <?= json_encode(array_column($replyActivity, 'date')) ?>;
        const replyCounts = <?= json_encode(array_column($replyActivity, 'count')) ?>;
        new Chart(replyCtx, {
            type: 'line',
            data: {
                labels: replyLabels,
                datasets: [{
                    label: 'Replies',
                    data: replyCounts,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#10b981',
                    borderWidth: 2.5,
                }]
            },
            options: {
                ...getChartDefaults(),
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        ...getChartDefaults().scales.x,
                        ticks: { font: { size: 9 } },
                    },
                    y: {
                        ...getChartDefaults().scales.y,
                        ticks: { stepSize: 1, font: { size: 9 } },
                    }
                },
                animation: { duration: 1000 }
            }
        });

        // ─── ANIMATE STAT COUNTERS ─────────────────────────────
        function animateCounter(el, target, duration = 1000) {
            const start = 0;
            const startTime = performance.now();
            const isFloat = target % 1 !== 0;

            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = start + (target - start) * eased;
                el.textContent = isFloat ? current.toFixed(1) : Math.round(current);
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    el.textContent = isFloat ? target.toFixed(1) : target;
                }
            }
            requestAnimationFrame(update);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const stats = [
                { id: 'statTotal', val: <?= $stats['total'] ?> },
                { id: 'statPending', val: <?= $stats['pending'] ?> },
                { id: 'statPartial', val: <?= $stats['partial'] ?> },
                { id: 'statCompleted', val: <?= $stats['completed'] ?> },
                { id: 'statOverdue', val: <?= $stats['overdue'] ?> },
                { id: 'statHigh', val: <?= $stats['high_priority'] ?> },
            ];
            stats.forEach(s => {
                const el = document.getElementById(s.id);
                if (el) {
                    const target = s.val;
                    el.textContent = '0';
                    setTimeout(() => animateCounter(el, target, 900), 200);
                }
            });

            // Animate overdue bars
            document.querySelectorAll('.overdue-bar').forEach((bar, i) => {
                const height = parseFloat(bar.style.height) || 0;
                bar.style.height = '4px';
                setTimeout(() => {
                    bar.style.height = height + 'px';
                }, 300 + i * 100);
            });

            // Animate progress bars
            document.querySelectorAll('.progress-bar').forEach(pb => {
                const w = pb.style.width;
                pb.style.width = '0%';
                setTimeout(() => {
                    pb.style.width = w;
                }, 400);
            });
        });

        // ─── CLOSE SIDEBAR ON OUTSIDE CLICK (mobile) ──────────
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('show') &&
                    !sidebar.contains(e.target) &&
                    !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        console.log('Task Dashboard loaded successfully');
    </script>

</body>
</html>