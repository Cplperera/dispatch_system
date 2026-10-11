<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INFORMERS')) {
    die('Access Denied!');
}

$informerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($informerId <= 0) {
    die('Invalid informer ID.');
}

// Fetch informer details
$sql = "SELECT i.*, r.regiment_name 
        FROM informers i
        LEFT JOIN regiments r ON i.regiment_id = r.id
        WHERE i.id = ? AND i.is_deleted = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $informerId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    die('Informer not found.');
}
$informer = $result->fetch_assoc();
$stmt->close();

// Fetch internal letters linked to this informer
$internalSql = "SELECT il.*, lc.category as category_name, 
                (SELECT COUNT(*) FROM internal_letters_forwarding WHERE letter_id = il.id) as forward_count
                FROM internal_letters il
                LEFT JOIN letter_categories lc ON il.category_id = lc.id
                WHERE il.Informer_id = ? AND il.is_deleted = 0
                ORDER BY il.created_at DESC";
$stmt = $conn->prepare($internalSql);
$stmt->bind_param("i", $informerId);
$stmt->execute();
$internalLetters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For each internal letter, fetch outward letters via outward_internal
$outwardMap = [];
if (!empty($internalLetters)) {
    $internalIds = array_column($internalLetters, 'id');
    $placeholders = implode(',', array_fill(0, count($internalIds), '?'));
    $outwardSql = "SELECT oi.internal_id, ol.*, eb.branch_name as receiver_branch
                   FROM outward_internal oi
                   JOIN outward_letters ol ON oi.outward_id = ol.id
                   LEFT JOIN external_branches eb ON FIND_IN_SET(eb.id, ol.receiver) > 0
                   WHERE oi.internal_id IN ($placeholders) AND ol.is_deleted = 0
                   ORDER BY ol.created_at DESC";
    $stmt = $conn->prepare($outwardSql);
    $stmt->bind_param(str_repeat('i', count($internalIds)), ...$internalIds);
    $stmt->execute();
    $outwardRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Group by internal_id
    foreach ($outwardRows as $row) {
        $internalId = $row['internal_id'];
        if (!isset($outwardMap[$internalId])) {
            $outwardMap[$internalId] = [];
        }
        unset($row['internal_id']);
        $outwardMap[$internalId][] = $row;
    }
}

// Fetch task letters linked to any internal letter of this informer
$taskMap = [];
if (!empty($internalLetters)) {
    $internalIds = array_column($internalLetters, 'id');
    $placeholders = implode(',', array_fill(0, count($internalIds), '?'));
    $taskSql = "SELECT til.internal_letter_id, tl.* 
                FROM task_internal_links til
                JOIN task_letters tl ON til.task_id = tl.id
                WHERE til.internal_letter_id IN ($placeholders)
                ORDER BY tl.created_at DESC";
    $stmt = $conn->prepare($taskSql);
    $stmt->bind_param(str_repeat('i', count($internalIds)), ...$internalIds);
    $stmt->execute();
    $taskRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($taskRows as $row) {
        $internalId = $row['internal_letter_id'];
        if (!isset($taskMap[$internalId])) {
            $taskMap[$internalId] = [];
        }
        unset($row['internal_letter_id']);
        $taskMap[$internalId][] = $row;
    }
}

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
    <title>Informer Details | IDCMS</title>
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

        /* ─── CARDS ───────────────────────────────────────────── */
        .detail-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--card-radius);
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 28px;
            transition: background var(--transition-speed), border var(--transition-speed);
        }
        .detail-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }
        .detail-card .card-header h5 {
            font-weight: 700;
            margin: 0;
        }
        .detail-card .card-header small {
            color: var(--text-muted);
            font-size: 0.7rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 30px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-item .label {
            font-size: 0.65rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .detail-item .value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .status-inactive { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

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

        .badge-internal-status {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-not-forwarded { background: rgba(108,117,125,0.15); color: #6c757d; }
        .status-forwarded { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .status-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .status-completed { background: rgba(16,185,129,0.15); color: #10b981; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            border: none;
            font-weight: 700;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-back:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--accent-soft);
            color: #fff;
        }

        /* ─── RESPONSIVE ────────────────────────────────────── */
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
            .top-nav { flex-wrap: wrap; gap: 12px; padding: 16px 18px; }
            .top-nav h4 { font-size: 1.1rem; }
            .top-nav h4 small { display: none; }
            .detail-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .detail-grid { grid-template-columns: 1fr; }
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
            <a href="../../letters/dashboard/dashboard.php" class="nav-btn">
                <i class="fa-solid fa-house"></i> Home Page
            </a>
            <div class="sidebar-divider">Informer</div>
            <button class="nav-btn active" onclick="toggleSubmenu('informerSub')">
                <i class="fa-solid fa-user-secret"></i> Informer Unity
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="informerSub" class="submenu show">
                <a href="informers.php" class="sub-nav-btn">Add Informer</a>
                <a href="informers.php" class="sub-nav-btn active-sub">View Informer</a>
                <a href="../../letters/dashboard/informer_dashboard.php" class="sub-nav-btn">Informer Dashboard</a>
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
                        Informer Details
                        <small><?= htmlspecialchars($informer['informer_code']) ?></small>
                    </h4>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="informers.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </header>

        <!-- ═══ INFORMER DETAILS ═══ -->
        <div class="detail-card">
            <div class="card-header">
                <h5><i class="fa-solid fa-user-secret me-2" style="color:var(--accent);"></i>Basic Information</h5>
                <small>Last updated: <?= date('d M Y H:i', strtotime($informer['updated_at'])) ?></small>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="label">Informer Code</span>
                    <span class="value"><?= htmlspecialchars($informer['informer_code']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Full Name</span>
                    <span class="value"><?= htmlspecialchars($informer['informer_name']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Category</span>
                    <span class="value">
                        <?php
                            $catClass = 'badge-informer';
                            if ($informer['category'] == 'Agent') $catClass = 'badge-agent';
                            elseif ($informer['category'] == 'Casual Contact') $catClass = 'badge-contact';
                        ?>
                        <span class="badge-category <?= $catClass ?>"><?= $informer['category'] ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="label">Classification</span>
                    <span class="value"><span class="badge-class">Class <?= $informer['classification'] ?></span></span>
                </div>
                <div class="detail-item">
                    <span class="label">Regiment</span>
                    <span class="value"><?= htmlspecialchars($informer['regiment_name'] ?? '-') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Status</span>
                    <span class="value">
                        <?php if ($informer['status'] == 'Active'): ?>
                            <span class="badge-status status-active">Active</span>
                        <?php else: ?>
                            <span class="badge-status status-inactive">Inactive</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="label">Handler Details</span>
                    <span class="value"><?= htmlspecialchars($informer['handler_details'] ?? '-') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Contact Number</span>
                    <span class="value"><?= htmlspecialchars($informer['contact_number'] ?? '-') ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Created At</span>
                    <span class="value"><?= date('d M Y H:i', strtotime($informer['created_at'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Updated At</span>
                    <span class="value"><?= date('d M Y H:i', strtotime($informer['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- ═══ INTERNAL LETTERS ═══ -->
        <div class="detail-card">
            <div class="card-header">
                <h5><i class="fa-solid fa-envelope me-2" style="color:var(--info);"></i>Internal Letters</h5>
                <small><?= count($internalLetters) ?> linked</small>
            </div>
            <?php if (empty($internalLetters)): ?>
                <div class="empty-state text-center py-4" style="color:var(--text-muted);">
                    <i class="fa-solid fa-envelope" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:10px;"></i>
                    <h6>No internal letters linked to this informer.</h6>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="perf-table">
                        <thead>
                            <tr>
                                <th>CDR No.</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Security</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Forwarded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internalLetters as $il):
                                $statusClass = 'status-not-forwarded';
                                if ($il['status'] == 'Forwarded') $statusClass = 'status-forwarded';
                                elseif ($il['status'] == 'pending') $statusClass = 'status-pending';
                                elseif ($il['status'] == 'completed') $statusClass = 'status-completed';
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($il['cdr_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($il['letter_title']) ?></td>
                                    <td><?= htmlspecialchars($il['category_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($il['security_classification']) ?></td>
                                    <td><?= htmlspecialchars($il['priority_status']) ?></td>
                                    <td><span class="badge-internal-status <?= $statusClass ?>"><?= $il['status'] ?></span></td>
                                    <td><?= $il['forward_count'] ?></td>
                                    <td>
                                        <a href="../../letters/inward/internal_letter_details.php?id=<?= $il['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fa-solid fa-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ═══ OUTWARD LETTERS PER INTERNAL LETTER ═══ -->
        <?php if (!empty($outwardMap)): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h5><i class="fa-solid fa-paper-plane me-2" style="color:var(--success);"></i>Outward Letters (by Internal Letter)</h5>
                    <small><?= count($outwardMap) ?> internal letters with outward links</small>
                </div>
                <?php foreach ($outwardMap as $internalId => $outwardList): 
                    $internalTitle = '';
                    foreach ($internalLetters as $il) {
                        if ($il['id'] == $internalId) {
                            $internalTitle = $il['cdr_number'] . ' - ' . $il['letter_title'];
                            break;
                        }
                    }
                ?>
                    <div style="margin-bottom:20px;">
                        <h6 style="color:var(--text-muted);font-weight:600;font-size:0.85rem;border-bottom:1px solid var(--border);padding-bottom:8px;">
                            <i class="fa-solid fa-file-lines me-2"></i><?= htmlspecialchars($internalTitle) ?>
                            <small style="font-weight:400;font-size:0.7rem;">(<?= count($outwardList) ?> outward letters)</small>
                        </h6>
                        <div class="table-wrap">
                            <table class="perf-table">
                                <thead>
                                    <tr>
                                        <th>Ref.</th>
                                        <th>Title</th>
                                        <th>Receiver</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($outwardList as $ol): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($ol['letter_ref']) ?></strong></td>
                                            <td><?= htmlspecialchars($ol['title']) ?></td>
                                            <td><?= htmlspecialchars($ol['receiver_branch'] ?? $ol['receiver'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($ol['dispatch_method']) ?></td>
                                            <td><?= htmlspecialchars($ol['status']) ?></td>
                                            <td>
                                                <a href="../../letters/outward/view_outward.php?id=<?= $ol['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fa-solid fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ═══ TASK LETTERS PER INTERNAL LETTER ═══ -->
        <?php if (!empty($taskMap)): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h5><i class="fa-solid fa-tasks me-2" style="color:var(--purple);"></i>Task Letters (by Internal Letter)</h5>
                    <small><?= count($taskMap) ?> internal letters with task links</small>
                </div>
                <?php foreach ($taskMap as $internalId => $taskList): 
                    $internalTitle = '';
                    foreach ($internalLetters as $il) {
                        if ($il['id'] == $internalId) {
                            $internalTitle = $il['cdr_number'] . ' - ' . $il['letter_title'];
                            break;
                        }
                    }
                ?>
                    <div style="margin-bottom:20px;">
                        <h6 style="color:var(--text-muted);font-weight:600;font-size:0.85rem;border-bottom:1px solid var(--border);padding-bottom:8px;">
                            <i class="fa-solid fa-file-lines me-2"></i><?= htmlspecialchars($internalTitle) ?>
                            <small style="font-weight:400;font-size:0.7rem;">(<?= count($taskList) ?> task letters)</small>
                        </h6>
                        <div class="table-wrap">
                            <table class="perf-table">
                                <thead>
                                    <tr>
                                        <th>Ref.</th>
                                        <th>Title</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taskList as $tl): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($tl['letter_ref']) ?></strong></td>
                                            <td><?= htmlspecialchars($tl['title']) ?></td>
                                            <td><?= htmlspecialchars($tl['priority_status']) ?></td>
                                            <td><?= htmlspecialchars($tl['status']) ?></td>
                                            <td>
                                                <a href="../../letters/tasking/view_task.php?id=<?= $tl['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fa-solid fa-eye"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ═══ FOOTER ═══ -->
        <div style="margin-top:30px;text-align:center;font-size:0.7rem;color:var(--text-muted);opacity:0.6;border-top:1px solid var(--border);padding-top:20px;">
            IDCMS Informer Details &bull; <?= date('Y') ?>
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

        console.log('Informer Details loaded successfully');
    </script>
</body>
</html>