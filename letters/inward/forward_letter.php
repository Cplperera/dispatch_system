<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('FORWARD_INTERNAL_LETTER')) { die("Access Denied!"); }
if (!isset($_GET['letter_id']) || !is_numeric($_GET['letter_id'])) { die("Invalid ID."); }

$letter_id = intval($_GET['letter_id']);

// Fetch letter details
$stmt = $conn->prepare("SELECT id, cdr_number, letter_title, letter_date FROM internal_letters WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $letter_id);
$stmt->execute();
$letter = $stmt->get_result()->fetch_assoc();
if (!$letter) { die("Letter not found."); }

// Fetch workplace ENUM values dynamically
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'work_place'");
$row = $res->fetch_assoc();
preg_match("/^enum\(\'(.*)\'\)$/", $row['Type'], $matches);
$workplaces = explode("','", $matches[1]);

// Fetch desks into an array
$desksResult = $conn->query("SELECT id, desk_name FROM desks WHERE is_deleted = 0 AND status = 'Active' ORDER BY desk_name ASC");
$desks = [];
while ($d = $desksResult->fetch_assoc()) {
    $desks[] = $d;
}

// Fetch active users with LEFT JOIN on desks table to get desk_name
$usersQuery = "SELECT u.id, u.username, u.name_with_initial, u.rank, u.appointment, u.section, u.work_place, u.desk_id, u.user_category AS category, d.desk_name 
               FROM users u 
               LEFT JOIN desks d ON u.desk_id = d.id 
               WHERE u.status='Active' AND u.is_deleted = 0 
               ORDER BY u.section ASC, u.name_with_initial ASC";

$usersResult = $conn->query($usersQuery);

if (!$usersResult) {
    die("Database Query Failed: " . $conn->error);
}

$users = [];
$appointments = [];

while ($u = $usersResult->fetch_assoc()) {
    $u['category'] = !empty($u['category']) ? $u['category'] : 'ORs';
    if (!empty($u['appointment']) && !in_array($u['appointment'], $appointments)) {
        $appointments[] = $u['appointment'];
    }
    $users[] = $u;
}
sort($appointments);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forward Internal Letter | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <script>
        (function() {
            const saved = localStorage.getItem('dispatch_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
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
            --placeholder-color: #A3AED0;
            --danger: #FF5B5B; 
            --success: #00C864; 
            --warning: #FFB600;
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
            --placeholder-color: #707EAE;
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            min-height: 100vh; 
            font-family: "Segoe UI", system-ui, sans-serif; 
            margin: 0;
            transition: background 0.3s, color 0.3s;
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
        }
        .brand-box { margin-bottom: 1.5rem; padding: 0 0.5rem; }
        .brand-name { font-weight: 800; font-size: 1.25rem; color: var(--text-main); }
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
            background: transparent; 
            width: 100%; 
            border: none; 
            text-align: left; 
            cursor: pointer;
        }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        
        .submenu { padding-left: 20px; display: none; }
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
        .sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
        .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            display: flex; 
            flex-direction: column; 
            height: 100vh;
            overflow: hidden;
        }
        .top-nav { 
            background: var(--bg-body); 
            padding: 20px 30px; 
            z-index: 90; 
            flex-shrink: 0;
            border-bottom: 1px solid var(--border);
        }

        .section-title { 
            font-size: 1.25rem; 
            font-weight: 800; 
            color: var(--text-main); 
            display: flex; 
            align-items: center; 
        }
        .section-title i { margin-right: 12px; color: var(--accent); }

        .stats-badge { 
            background: var(--accent-soft); 
            color: var(--accent); 
            padding: 8px 16px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.85rem; 
        }

        .form-control, .form-select { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 12px; 
            padding: 8px 12px; 
            font-size: 0.85rem; 
        }
        .form-control::placeholder { color: var(--placeholder-color); opacity: 0.8; }
        .form-control:focus, .form-select:focus { 
            background: var(--bg-body); 
            color: var(--text-main); 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px var(--accent-soft); 
        }

        .kanban-wrapper {
            padding: 20px 30px;
            flex: 1;
            display: flex;
            gap: 20px;
            min-height: 0;
        }

        .panel { 
            background: var(--card-bg); 
            border-radius: 16px; 
            border: 1px solid var(--border); 
            display: flex; 
            flex-direction: column; 
            padding: 20px; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        .panel-left { flex: 1.3; }
        .panel-right { flex: 1; }

        .panel-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 12px; 
            flex-shrink: 0; 
        }
        .panel-title { font-weight: 800; font-size: 0.95rem; color: var(--text-main); }
        .panel-title .badge { 
            background: var(--accent-soft); 
            color: var(--accent); 
            font-size: 0.75rem; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-weight: 700;
        }

        .filter-row { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
            margin-bottom: 12px; 
            flex-shrink: 0; 
        }
        .filter-row .filter-input { 
            flex: 1; 
            min-width: 90px; 
        }

        .card-list { 
            flex: 1; 
            overflow-y: auto; 
            padding-right: 4px; 
            display: flex; 
            flex-direction: column; 
            gap: 6px; 
        }
        .card-list::-webkit-scrollbar { width: 4px; }
        .card-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }

        .section-header-tag { 
            font-size: 0.7rem; 
            font-weight: 800; 
            color: var(--accent); 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            padding: 6px 0 2px 0; 
            border-bottom: 1px solid var(--border); 
            margin-top: 8px; 
        }
        .section-header-tag:first-of-type { margin-top: 0; }

        .user-card { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            padding: 8px 12px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            transition: 0.2s; 
            flex-shrink: 0; 
            cursor: pointer; 
            position: relative;
        }

        .user-card.card-officer {
            border-left: 5px solid #7551FF !important; 
            border-color: rgba(117, 81, 255, 0.3);
            background: rgba(117, 81, 255, 0.03);
        }

        .user-card.card-ors {
            border-left: 5px solid #00C864 !important; 
            border-color: rgba(0, 200, 100, 0.3);
            background: rgba(0, 200, 100, 0.03);
        }

        .user-card.card-officer:hover { 
            border-color: #7551FF; 
            background: rgba(117, 81, 255, 0.1); 
        }

        .user-card.card-ors:hover { 
            border-color: #00C864; 
            background: rgba(0, 200, 100, 0.1); 
        }

        .user-card.card-officer .user-icon {
            background: rgba(117, 81, 255, 0.15);
            color: #7551FF;
            border-color: rgba(117, 81, 255, 0.3);
        }

        .user-card.card-ors .user-icon {
            background: rgba(0, 200, 100, 0.15);
            color: #00C864;
            border-color: rgba(0, 200, 100, 0.3);
        }

        .user-icon { 
            width: 36px; 
            height: 36px; 
            border-radius: 8px; 
            border: 1px solid var(--border);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            font-size: 0.85rem; 
            flex-shrink: 0; 
        }

        .user-card .info { flex: 1; min-width: 0; }
        .user-card .info .name { font-weight: 700; font-size: 0.82rem; display: block; line-height: 1.2; color: var(--text-main); }
        .user-card .info .meta { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
        .user-card .info .badges { display: flex; gap: 4px; align-items: center; margin-top: 3px; flex-wrap: wrap; }
        
        .badge-cat { font-size: 0.65rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; }
        .badge-cat.officer { background: rgba(117, 81, 255, 0.2); color: #7551FF; }
        .badge-cat.ors { background: rgba(0, 200, 100, 0.2); color: #00C864; }
        
        .badge-uname { font-size: 0.68rem; font-weight: 600; color: var(--text-muted); background: var(--card-bg); border: 1px solid var(--border); padding: 0px 5px; border-radius: 4px; }
        
        .user-card .action-btn { 
            background: transparent; 
            border: none; 
            font-size: 0.95rem; 
            cursor: pointer; 
            padding: 2px 6px; 
            color: var(--text-muted); 
            transition: 0.2s; 
        }
        .user-card .action-btn:hover { color: var(--accent); transform: scale(1.15); }
        .user-card .action-btn.add { color: var(--success); }
        .user-card .action-btn.remove { color: var(--danger); }

        .dispatch-notes { flex-shrink: 0; margin-top: 12px; }
        .dispatch-notes textarea { 
            width: 100%; 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 10px; 
            padding: 10px; 
            font-size: 0.85rem; 
            resize: none; 
            height: 65px; 
            outline: none; 
        }
        .dispatch-notes textarea:focus { border-color: var(--accent); }

        .btn-save { 
            background: var(--accent); 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 10px; 
            font-weight: 700; 
            font-size: 0.85rem;
            transition: 0.3s; 
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-1px); }

        .btn-outline-accent { 
            background: transparent; 
            border: 1px solid var(--accent); 
            color: var(--accent); 
            padding: 4px 10px; 
            border-radius: 8px; 
            font-weight: 700; 
            font-size: 0.75rem; 
            transition: 0.2s; 
            cursor: pointer;
        }
        .btn-outline-accent:hover { background: var(--accent-soft); }

        .stats-line { font-size: 0.75rem; color: var(--text-muted); padding: 2px 0 6px 0; flex-shrink: 0; }
        .stats-line strong { color: var(--text-main); }

        .template-manager { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 8px; }
        .template-manager select { flex: 1; min-width: 120px; font-size: 0.75rem; }

        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 6px; }
        .toast-msg { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-left: 4px solid var(--accent); 
            padding: 10px 16px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); 
            font-size: 0.8rem; 
            color: var(--text-main);
            animation: slideIn 0.2s ease;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .empty-state { color: var(--text-muted); text-align: center; padding: 30px 10px; font-size: 0.8rem; }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 8px; opacity: 0.4; }

        .receipt-modal .modal-content { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 16px; }
        .receipt-list { max-height: 250px; overflow-y: auto; }

        .receipt-list, #receiptMinutes {
            background-color: var(--bg-body) !important;
            color: var(--text-main) !important;
            border-color: var(--border) !important;
        }

        .receipt-list .small, .receipt-list div {
            color: var(--text-main) !important;
        }

        /* ============================================================
           REDIRECT OVERLAY — shows during 2s countdown
           ============================================================ */
        .redirect-overlay {
            position: fixed;
            inset: 0;
            background: rgba(11, 20, 55, 0.85);
            backdrop-filter: blur(8px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        [data-theme="light"] .redirect-overlay {
            background: rgba(240, 242, 245, 0.85);
        }
        .redirect-overlay.show { display: flex; }

        .redirect-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px 50px;
            text-align: center;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: scaleIn 0.4s ease;
        }
        @keyframes scaleIn {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .redirect-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #00C864, #059669);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
            box-shadow: 0 12px 30px rgba(0, 200, 100, 0.35);
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1);     box-shadow: 0 12px 30px rgba(0,200,100,0.35); }
            50%      { transform: scale(1.05);  box-shadow: 0 16px 40px rgba(0,200,100,0.5); }
        }

        .redirect-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 8px;
            letter-spacing: -0.3px;
        }

        .redirect-sub {
            color: var(--text-muted);
            font-size: 0.92rem;
            margin: 0 0 22px;
            font-weight: 500;
        }

        .redirect-progress {
            width: 100%;
            height: 5px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .redirect-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #00C864, #7551FF);
            border-radius: 3px;
            width: 0%;
            animation: progressFill 2s linear forwards;
        }
        @keyframes progressFill {
            from { width: 0%; }
            to   { width: 100%; }
        }

        .redirect-countdown {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: 1px;
        }
        .redirect-countdown small {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            display: block;
            letter-spacing: 0;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS</div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Dispatching</div>
        
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text me-2"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="internalSub" class="submenu show">
                <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="internal_letters.php" class="sub-nav-btn active-sub">View Registry</a>
                <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="section-title">
                <i class="fa-solid fa-paper-plane"></i> Forward Internal Letter
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="stats-badge font-monospace">
                    <i class="fa-solid fa-hashtag me-1"></i><?= htmlspecialchars($letter['cdr_number']) ?>
                </div>
                <a href="internal_letters.php" class="btn btn-outline-secondary btn-sm rounded-3"><i class="fa-solid fa-xmark me-1"></i>Cancel</a>
            </div>
        </div>
    </header>

    <div id="statusMessage" class="px-4 mt-2"></div>

    <div class="kanban-wrapper">
        <!-- LEFT PANEL: Available Officers -->
        <div class="panel panel-left">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-users me-2 text-primary"></i>Available Recipients <span class="badge" id="availableCount">0</span></span>
                <button type="button" id="addAllBtn" class="btn-outline-accent"><i class="fa-solid fa-plus me-1"></i> Add All</button>
            </div>
            <div class="filter-row">
                <select id="filterCategory" class="form-select filter-input">
                    <option value="">Category (All)</option>
                    <option value="Officer">Officer</option>
                    <option value="ORs">ORs</option>
                </select>
                <select id="filterWP" class="form-select filter-input">
                    <option value="">Unit / Work Place</option>
                    <?php foreach($workplaces as $wp): ?>
                        <option value="<?= htmlspecialchars($wp) ?>"><?= htmlspecialchars($wp) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterSection" class="form-select filter-input"><option value="">Section</option></select>
                <select id="filterDesk" class="form-select filter-input">
                    <option value="">Desk</option>
                    <?php foreach($desks as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['desk_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterAppointment" class="form-select filter-input">
                    <option value="">Appointment (All)</option>
                    <?php foreach($appointments as $appt): ?>
                        <option value="<?= htmlspecialchars($appt) ?>"><?= htmlspecialchars($appt) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="userSearch" class="form-control filter-input" placeholder="🔍 Search officer/user/desk..." style="flex: 1.5;">
            </div>
            <div class="card-list" id="availableList"></div>
        </div>

        <!-- RIGHT PANEL: Selected Recipients -->
        <div class="panel panel-right">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-user-check me-2 text-primary"></i>Selected Recipients <span class="badge" id="selectedCount">0</span></span>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="clearAllBtn" class="btn-outline-accent" title="Clear All"><i class="fa-solid fa-trash-can"></i></button>
                    <span class="--text-muted small">|</span>
                    <span id="undoHint" class="--text-muted" style="font-size:0.7rem;">Ctrl+Z</span>
                </div>
            </div>
            <div class="stats-line" id="statsLine">0 selected</div>
            <div class="card-list" id="selectedList">
                <div class="empty-state" id="emptySelected">
                    <i class="fa-regular fa-hand-pointer"></i>
                    Click <strong>＋</strong> or double‑click a user to add.
                </div>
            </div>

            <!-- Notes & Template Manager -->
            <div class="dispatch-notes">
                <div class="template-manager">
                    <select id="templateSelect" class="form-select"><option value="">-- Minutes Template --</option></select>
                    <button type="button" id="saveTemplateBtn" class="btn-outline-accent"><i class="fa-regular fa-floppy-disk"></i> Save</button>
                    <button type="button" id="deleteTemplateBtn" class="btn-outline-accent text-danger border-danger"><i class="fa-regular fa-trash-can"></i></button>
                </div>
                <textarea id="dispatchMinutes" class="form-control" placeholder="Enter forwarding instructions or minute notes..."></textarea>
                <button type="button" id="submitBtn" class="btn-save mt-2">
                    <i class="fa-solid fa-paper-plane"></i> Dispatch & Forward
                </button>
            </div>
        </div>
    </div>
</main>

<div class="toast-container" id="toastContainer"></div>

<!-- ============================================================
     REDIRECT OVERLAY — Shows for 2 seconds after success
     ============================================================ -->
<div class="redirect-overlay" id="redirectOverlay">
    <div class="redirect-card">
        <div class="redirect-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <h3 class="redirect-title">Forward Successful!</h3>
        <p class="redirect-sub">Letter dispatched to <strong id="redirectCount">0</strong> recipient(s).<br>Redirecting you to Add Internal Letter page...</p>

        <div class="redirect-progress">
            <div class="redirect-progress-bar"></div>
        </div>

        <div class="redirect-countdown">
            <span id="countdownNum">2</span>
            <small>seconds</small>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
// --- Helper Function ---
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    if(!submenu) return;
    const icon = submenu.previousElementSibling ? submenu.previousElementSibling.querySelector('.fa-chevron-down') : null;
    
    if (submenu.style.display === "block" || submenu.classList.contains('show')) {
        submenu.style.display = "none";
        submenu.classList.remove('show');
        if(icon) icon.style.transform = "rotate(0deg)";
    } else {
        submenu.style.display = "block";
        submenu.classList.add('show');
        if(icon) icon.style.transform = "rotate(180deg)";
    }
}

// --- DATA INITIALIZATION ---
const userData = <?= json_encode($users) ?>;
const sections = [...new Set(userData.map(u => u.section).filter(Boolean))].sort();
const sectionSelect = document.getElementById('filterSection');
sections.forEach(sec => { 
    const o = document.createElement('option'); 
    o.value = sec; 
    o.textContent = sec; 
    sectionSelect.appendChild(o); 
});

// --- DOM References ---
const availableList = document.getElementById('availableList');
const selectedList = document.getElementById('selectedList');
const fCategory = document.getElementById('filterCategory');
const fWP = document.getElementById('filterWP');
const fSection = document.getElementById('filterSection');
const fDesk = document.getElementById('filterDesk');
const fAppointment = document.getElementById('filterAppointment');
const fSearch = document.getElementById('userSearch');
const availableCount = document.getElementById('availableCount');
const selectedCount = document.getElementById('selectedCount');
const statsLine = document.getElementById('statsLine');
const dispatchMinutes = document.getElementById('dispatchMinutes');
const submitBtn = document.getElementById('submitBtn');
const toastContainer = document.getElementById('toastContainer');
const templateSelect = document.getElementById('templateSelect');
const saveTemplateBtn = document.getElementById('saveTemplateBtn');
const deleteTemplateBtn = document.getElementById('deleteTemplateBtn');

// --- State Variables ---
let selectedIds = new Set();
let history = [];
let historyIndex = -1;
const MAX_HISTORY = 20;

// --- Template Storage ---
const TEMPLATE_KEY = 'dispatch_templates';
function getTemplates() {
    try { return JSON.parse(localStorage.getItem(TEMPLATE_KEY)) || []; } catch(e) { return []; }
}
function saveTemplates(templates) {
    localStorage.setItem(TEMPLATE_KEY, JSON.stringify(templates));
}
function loadTemplateDropdown() {
    const templates = getTemplates();
    templateSelect.innerHTML = '<option value="">-- Minutes Template --</option>';
    templates.forEach((t, idx) => {
        const opt = document.createElement('option');
        opt.value = idx;
        opt.textContent = t.name;
        templateSelect.appendChild(opt);
    });
}
function loadTemplateContent(idx) {
    const templates = getTemplates();
    if (templates[idx]) {
        dispatchMinutes.value = templates[idx].content;
        dispatchMinutes.focus();
        showToast('Template loaded: ' + templates[idx].name);
    }
}
function saveNewTemplate() {
    const content = dispatchMinutes.value.trim();
    if (!content) { showToast('Minutes are empty – nothing to save.'); return; }
    const name = prompt('Enter a name for this template:', 'Standard Forward');
    if (!name) return;
    const templates = getTemplates();
    templates.push({ name: name.trim(), content: content });
    saveTemplates(templates);
    loadTemplateDropdown();
    showToast('Template saved!');
}
function deleteSelectedTemplate() {
    const idx = templateSelect.value;
    if (idx === '') { showToast('Select a template to delete.'); return; }
    const templates = getTemplates();
    if (!confirm('Delete template "' + templates[parseInt(idx)].name + '"?')) return;
    templates.splice(parseInt(idx), 1);
    saveTemplates(templates);
    loadTemplateDropdown();
    showToast('Template deleted.');
}

function pushHistory() {
    const snapshot = Array.from(selectedIds);
    history = history.slice(0, historyIndex + 1);
    history.push(snapshot);
    if (history.length > MAX_HISTORY) history.shift();
    historyIndex = history.length - 1;
}

function undo() {
    if (historyIndex <= 0) { showToast('Nothing to undo'); return; }
    historyIndex--;
    const snapshot = history[historyIndex];
    selectedIds = new Set(snapshot);
    renderAll();
    saveDraft();
    showToast('Undo successful');
}

function showToast(msg) {
    const div = document.createElement('div');
    div.className = 'toast-msg';
    div.textContent = msg;
    toastContainer.appendChild(div);
    setTimeout(() => { if (div.parentNode) div.remove(); }, 2500);
}

// --- Filter Persistence ---
const FILTER_KEY = 'kanban_filters';
function saveFilters() {
    localStorage.setItem(FILTER_KEY, JSON.stringify({
        cat: fCategory.value, wp: fWP.value, section: fSection.value, desk: fDesk.value, appt: fAppointment.value, search: fSearch.value
    }));
}
function loadFilters() {
    try {
        const data = JSON.parse(localStorage.getItem(FILTER_KEY));
        if (data) {
            if (data.cat) fCategory.value = data.cat;
            if (data.wp) fWP.value = data.wp;
            if (data.section) fSection.value = data.section;
            if (data.desk) fDesk.value = data.desk;
            if (data.appt) fAppointment.value = data.appt;
            if (data.search) fSearch.value = data.search;
        }
    } catch(e) {}
}

// --- Rendering Logic ---
function getAvailableUsers() {
    const search = fSearch.value.toLowerCase().trim();
    return userData.filter(u => {
        if (selectedIds.has(u.id.toString())) return false;
        if (fCategory.value && u.category !== fCategory.value) return false;
        if (fWP.value && u.work_place !== fWP.value) return false;
        if (fSection.value && u.section !== fSection.value) return false;
        if (fDesk.value && String(u.desk_id) !== String(fDesk.value)) return false;
        if (fAppointment.value && u.appointment !== fAppointment.value) return false;
        if (search) {
            const haystack = (u.rank + ' ' + u.name_with_initial + ' ' + (u.username||'') + ' ' + (u.appointment||'') + ' ' + u.work_place + ' ' + u.section + ' ' + (u.desk_name||'')).toLowerCase();
            if (!haystack.includes(search)) return false;
        }
        return true;
    });
}

function renderAvailable() {
    const available = getAvailableUsers();
    availableCount.textContent = available.length;
    if (available.length === 0) {
        availableList.innerHTML = `<div class="empty-state"><i class="fa-solid fa-user-slash"></i>No matching recipients</div>`;
        return;
    }
    const groups = {};
    available.forEach(u => {
        const key = u.section || 'Other';
        if (!groups[key]) groups[key] = [];
        groups[key].push(u);
    });
    let html = '';
    for (const [section, users] of Object.entries(groups).sort((a,b) => a[0].localeCompare(b[0]))) {
        html += `<div class="section-header-tag">${escapeHtml(section)}</div>`;
        users.forEach(u => {
            const initial = escapeHtml(u.name_with_initial.charAt(0).toUpperCase());
            const apptStr = u.appointment ? ` - ${escapeHtml(u.appointment)}` : '';
            const catClass = u.category === 'Officer' ? 'officer' : 'ors';
            const cardClass = u.category === 'Officer' ? 'card-officer' : 'card-ors';
            const deskStr = u.desk_name ? ` • 💻 ${escapeHtml(u.desk_name)}` : '';
            html += `
                <div class="user-card ${cardClass}" data-id="${u.id}">
                    <div class="user-icon">${initial}</div>
                    <div class="info">
                        <span class="name">${escapeHtml(u.rank)} ${escapeHtml(u.name_with_initial)}${apptStr}</span>
                        <div class="meta">${escapeHtml(u.work_place)} • ${escapeHtml(u.section)}${deskStr}</div>
                        <div class="badges">
                            <span class="badge-cat ${catClass}">${escapeHtml(u.category)}</span>
                            <span class="badge-uname">@${escapeHtml(u.username)}</span>
                        </div>
                    </div>
                    <button type="button" class="action-btn add" title="Add"><i class="fa-solid fa-plus-circle"></i></button>
                </div>
            `;
        });
    }
    availableList.innerHTML = html;
}

function renderSelected() {
    const selectedUsers = userData.filter(u => selectedIds.has(u.id.toString()));
    selectedCount.textContent = selectedUsers.length;
    
    const officers = selectedUsers.filter(u => u.category === 'Officer').length;
    const ors = selectedUsers.length - officers;
    statsLine.innerHTML = `<strong>${selectedUsers.length}</strong> selected <span class="--text-muted">(</span>${officers} Officers, ${ors} ORs<span class="text-muted">)</span>`;

    if (selectedUsers.length === 0) {
        selectedList.innerHTML = `<div class="empty-state" id="emptySelected"><i class="fa-regular fa-hand-pointer"></i>Click <strong>＋</strong> or double‑click to add.</div>`;
        return;
    }
    let html = '';
    selectedUsers.forEach(u => {
        const initial = escapeHtml(u.name_with_initial.charAt(0).toUpperCase());
        const apptStr = u.appointment ? ` - ${escapeHtml(u.appointment)}` : '';
        const catClass = u.category === 'Officer' ? 'officer' : 'ors';
        const cardClass = u.category === 'Officer' ? 'card-officer' : 'card-ors';
        const deskStr = u.desk_name ? ` • 💻 ${escapeHtml(u.desk_name)}` : '';
        html += `
            <div class="user-card ${cardClass}" data-id="${u.id}">
                <div class="user-icon">${initial}</div>
                <div class="info">
                    <span class="name">${escapeHtml(u.rank)} ${escapeHtml(u.name_with_initial)}${apptStr}</span>
                    <div class="meta">${escapeHtml(u.work_place)} • ${escapeHtml(u.section)}${deskStr}</div>
                    <div class="badges">
                        <span class="badge-cat ${catClass}">${escapeHtml(u.category)}</span>
                        <span class="badge-uname">@${escapeHtml(u.username)}</span>
                    </div>
                </div>
                <button type="button" class="action-btn remove" data-id="${u.id}" title="Remove"><i class="fa-solid fa-minus-circle"></i></button>
            </div>
        `;
    });
    selectedList.innerHTML = html;
    
    selectedList.querySelectorAll('.remove').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.dataset.id;
            if (selectedIds.has(id)) {
                selectedIds.delete(id);
                pushHistory();
                renderAll();
                saveDraft();
            }
        });
    });
}

function renderAll() {
    renderAvailable();
    renderSelected();
}

function addUser(id) {
    if (selectedIds.has(id)) return;
    selectedIds.add(id);
    pushHistory();
    renderAll();
    saveDraft();
}

function removeUser(id) {
    if (!selectedIds.has(id)) return;
    selectedIds.delete(id);
    pushHistory();
    renderAll();
    saveDraft();
}

// --- Interaction Handlers ---
availableList.addEventListener('click', function(e) {
    const btn = e.target.closest('.action-btn.add');
    if (!btn) return;
    const card = btn.closest('.user-card');
    if (card) addUser(card.dataset.id);
});

availableList.addEventListener('dblclick', function(e) {
    const card = e.target.closest('.user-card');
    if (card) addUser(card.dataset.id);
});

selectedList.addEventListener('dblclick', function(e) {
    const card = e.target.closest('.user-card');
    if (card) removeUser(card.dataset.id);
});

document.getElementById('addAllBtn').addEventListener('click', function() {
    const visible = availableList.querySelectorAll('.user-card');
    let added = 0;
    visible.forEach(card => {
        const id = card.dataset.id;
        if (!selectedIds.has(id)) { selectedIds.add(id); added++; }
    });
    if (added > 0) {
        pushHistory();
        renderAll();
        saveDraft();
        showToast(`Added ${added} recipients`);
    }
});

document.getElementById('clearAllBtn').addEventListener('click', function() {
    if (selectedIds.size === 0) return;
    if (!confirm('Remove all selected recipients?')) return;
    selectedIds.clear();
    pushHistory();
    renderAll();
    saveDraft();
    showToast('Cleared all selected');
});

[fCategory, fWP, fSection, fDesk, fAppointment, fSearch].forEach(el => {
    el.addEventListener('input', function() {
        renderAvailable();
        saveFilters();
    });
});

templateSelect.addEventListener('change', function() {
    if (this.value !== '') loadTemplateContent(parseInt(this.value));
});
saveTemplateBtn.addEventListener('click', saveNewTemplate);
deleteTemplateBtn.addEventListener('click', deleteSelectedTemplate);

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); undo(); }
    if (e.ctrlKey && e.key === 'a') { e.preventDefault(); document.getElementById('addAllBtn').click(); }
    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); document.getElementById('submitBtn').click(); }
});

// --- Draft Auto-save ---
const DRAFT_KEY = 'kanban_v3_draft_' + <?= $letter_id ?>;
function saveDraft() {
    const selected = Array.from(selectedIds);
    localStorage.setItem(DRAFT_KEY, JSON.stringify({
        selected: selected,
        comments: dispatchMinutes.value
    }));
}
function loadDraft() {
    try {
        const data = JSON.parse(localStorage.getItem(DRAFT_KEY));
        if (data && data.selected) {
            data.selected.forEach(id => selectedIds.add(id));
            renderAll();
            dispatchMinutes.value = data.comments || '';
            pushHistory();
        } else {
            pushHistory();
        }
    } catch(e) { pushHistory(); }
}

loadFilters();
loadDraft();
setInterval(saveDraft, 10000);
loadTemplateDropdown();

/* ============================================================
   PROCESS SUBMISSION — with 2s auto-redirect
   ============================================================ */
document.getElementById('submitBtn').addEventListener('click', function() {
    const count = selectedIds.size;
    if (count === 0) { alert('Please add at least one recipient.'); return; }
    if (count > 50 && !confirm(`You are dispatching to ${count} users. Are you sure?`)) return;

    const formData = new FormData();
    formData.append('letter_id', '<?= $letter_id ?>');
    formData.append('comments', dispatchMinutes.value);
    selectedIds.forEach(id => {
        formData.append('forward_users[]', id);
    });

    const btn = this;
    const msg = document.getElementById('statusMessage');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> DISPATCHING…';

    fetch('forward_letter_process.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Clear draft
                localStorage.removeItem(DRAFT_KEY);

                // Show redirect overlay
                showRedirectOverlay(count);

                // Start 2s countdown
                let secondsLeft = 2;
                const countdownEl = document.getElementById('countdownNum');
                countdownEl.textContent = secondsLeft;

                const interval = setInterval(() => {
                    secondsLeft--;
                    if (secondsLeft >= 0) {
                        countdownEl.textContent = secondsLeft;
                    }
                }, 1000);

                // Redirect after 2 seconds
                setTimeout(() => {
                    clearInterval(interval);
                    window.location.href = 'add_internal_letter.php';
                }, 2000);

            } else {
                msg.innerHTML = `<div class="alert alert-danger py-2 small rounded-3">${data.error || 'Server error occurred.'}</div>`;
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Dispatch & Forward';
            }
        })
        .catch(() => {
            msg.innerHTML = `<div class="alert alert-danger py-2 small rounded-3">Network error.</div>`;
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Dispatch & Forward';
        });
});

/* ============================================================
   REDIRECT OVERLAY — Show countdown card
   ============================================================ */
function showRedirectOverlay(recipientCount) {
    document.getElementById('redirectCount').textContent = recipientCount;
    const overlay = document.getElementById('redirectOverlay');
    overlay.classList.add('show');

    // Restart progress bar animation
    const bar = overlay.querySelector('.redirect-progress-bar');
    if (bar) {
        bar.style.animation = 'none';
        void bar.offsetWidth; // reflow
        bar.style.animation = 'progressFill 2s linear forwards';
    }
}

renderAll();
</script>
</body>
</html>