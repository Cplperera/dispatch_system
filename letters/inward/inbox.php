<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS')) {
    die("Access Denied!");
}

$userId = $_SESSION['user_id'];

/* ============================================================
   FETCH INBOX DATA
   ------------------------------------------------------------
   FILTER LOGIC:
   • Show letters forwarded to me
   • UNLESS I have already forwarded that letter onward
   • If the letter comes back to me later, it will show again.
   ============================================================ */
$stmt = $conn->prepare("
    SELECT 
        lf.id AS forward_id, lf.seen, lf.forwarded_at, lf.comments AS current_remark,
        il.id AS letter_id, il.letter_title, il.cdr_number, il.letter_reference, 
        il.letter_date, il.priority_status, il.attachment, il.status AS doc_status,
        il.security_classification, il.is_under_final_disposal,
        u.name_with_initial AS sender_name, u.rank AS sender_rank, 
        u.appointment AS sender_appointment, u.work_place AS sender_work_place
    FROM internal_letters_forwarding lf
    JOIN internal_letters il ON lf.letter_id = il.id
    JOIN users u ON lf.forwarded_by = u.id
    WHERE lf.forwarded_to = ?
      AND NOT EXISTS (
          SELECT 1 
          FROM internal_letters_forwarding lf2
          WHERE lf2.letter_id = lf.letter_id
            AND lf2.forwarded_by = ?
            AND lf2.forwarded_at > lf.forwarded_at
      )
    ORDER BY lf.seen ASC, lf.forwarded_at DESC
");

if (!$stmt) {
    die("SQL Error (Main Query): " . $conn->error);
}

$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$newCount = 0;
$rows = [];

while ($r = $result->fetch_assoc()) {
    if (!$r['seen']) $newCount++;

    $lId = $r['letter_id'];

    $hStmt = $conn->prepare("
        SELECT 
            h.comments, 
            h.forwarded_at,
            sender.rank AS sender_rank,
            sender.name_with_initial AS sender_name,
            receiver.rank AS receiver_rank,
            receiver.name_with_initial AS receiver_name
        FROM internal_letters_forwarding h
        JOIN users sender ON h.forwarded_by = sender.id
        JOIN users receiver ON h.forwarded_to = receiver.id
        WHERE h.letter_id = ? 
        ORDER BY h.forwarded_at ASC
    ");

    if ($hStmt) {
        $hStmt->bind_param("i", $lId);
        $hStmt->execute();
        $r['history'] = $hStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $r['history'] = [];
    }

    $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Inbox | IDCMS</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">

    <style>
        /* ============================================================
           ROOT VARIABLES
           ============================================================ */
        :root {
            --bg-body: #F0F2F5;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --accent: #4318FF;
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #0B1437;
            --text-muted: #4A5578;
            --border: #E2E6F0;
            --error: #EE5D50;
            --success: #00C864;
            --warning: #F59E0B;
            --sidebar-width: 260px;
            --card-radius: 16px;
            --shadow: 0 4px 24px rgba(112, 144, 176, 0.08);
        }
        [data-theme="dark"] {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #7551FF;
            --accent-soft: rgba(117, 81, 255, 0.14);
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --border: rgba(255, 255, 255, 0.08);
            --error: #EE5D50;
            --success: #00C864;
            --warning: #F59E0B;
            --shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Noto Sans Sinhala', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
            line-height: 1.6;
            font-size: 0.92rem;
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 26px 14px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 12px 22px;
            margin-bottom: 4px;
        }
        .brand-name {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--text-main);
            letter-spacing: -0.3px;
        }
        .brand-name i {
            color: var(--accent);
            font-size: 1.3rem;
        }

        .sidebar-divider {
            font-size: 0.66rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 22px 12px 8px;
            opacity: 0.85;
        }

        .nav-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: 0.18s ease;
            margin-bottom: 3px;
            background: transparent;
            width: 100%;
            border: none;
            text-align: left;
            cursor: pointer;
            line-height: 1.3;
            font-family: inherit;
        }
        .nav-btn i:first-child {
            font-size: 0.95rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .nav-btn:hover,
        .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .submenu {
            padding-left: 22px;
            margin-left: 12px;
            display: none;
            border-left: 2px solid var(--border);
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .submenu.show { display: block; }

        .sub-nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            border-radius: 8px;
            transition: 0.15s;
            margin-bottom: 1px;
        }
        .sub-nav-btn:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .sub-nav-btn.active-sub {
            color: var(--accent);
            font-weight: 700;
            background: var(--accent-soft);
        }

        .sidebar-badge {
            background: var(--error);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
            margin-left: auto;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            min-width: 22px;
            text-align: center;
            line-height: 1.3;
        }

        .logout-btn {
            margin-top: auto;
            color: #FF5B5B !important;
            font-weight: 700;
        }
        .logout-btn:hover {
            background: rgba(255, 91, 91, 0.1) !important;
            color: #FF5B5B !important;
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 24px 32px 40px;
            display: flex;
            flex-direction: column;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 22px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .top-nav h4 {
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.4px;
        }
        .top-nav .subtitle {
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 3px;
        }

        .filter-input {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 500;
            font-size: 0.88rem;
            outline: none;
            transition: 0.18s;
            font-family: inherit;
            min-width: 280px;
        }
        .filter-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .filter-input::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        /* ============================================================
           TABLE CONTAINER
           ============================================================ */
        .table-container {
            background: var(--card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--border);
            padding: 22px 26px;
            box-shadow: var(--shadow);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }
        .table-header h6 {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--accent);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .inbox-count {
            background: var(--accent-soft);
            color: var(--accent);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
        }

        /* ============================================================
           LETTER TABLE
           ============================================================ */
        .letter-table-wrap {
            overflow-x: auto;
        }
        .letter-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            font-size: 0.88rem;
        }
        .letter-table thead th {
            text-align: left;
            padding: 10px 15px;
            color: var(--text-muted);
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border: none;
            background: transparent;
            white-space: nowrap;
        }

        .letter-table tbody tr td {
            background: var(--bg-body);
            padding: 14px 15px;
            vertical-align: middle;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            font-weight: 600;
            transition: background 0.15s;
            position: relative;
        }
        .letter-table tbody tr td:first-child {
            border-left: 1px solid var(--border);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .letter-table tbody tr td:last-child {
            border-right: 1px solid var(--border);
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        .letter-table tbody tr:hover td {
            background: var(--accent-soft);
        }

        /* Priority bar on the left of the row */
        .letter-table tbody tr {
            border-left: none;
        }
        .letter-table tbody tr td.priority-cell {
            border-left: 4px solid var(--priority-color, var(--accent));
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }

        /* Unread row highlight */
        .letter-table tbody tr.unread-row td {
            background: var(--accent-soft);
            border-color: var(--accent);
        }
        .letter-table tbody tr.unread-row td.priority-cell {
            border-left-color: var(--priority-color, var(--accent));
        }

        /* Unread indicator dot */
        .unread-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
            box-shadow: 0 0 0 3px var(--accent-soft);
        }

        /* ============================================================
           CELL CONTENT
           ============================================================ */
        .cdr-pill {
            background: var(--accent-soft);
            color: var(--accent);
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 800;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.78rem;
            display: inline-block;
            letter-spacing: 0.2px;
        }
        .ref-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            padding-left: 2px;
            font-family: 'JetBrains Mono', 'Courier New', monospace;
        }

        .subject-title {
            font-weight: 800;
            color: var(--text-main);
            font-size: 0.88rem;
            line-height: 1.4;
            margin-bottom: 6px;
        }
        .badges-row {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .mini-badge {
            font-size: 0.65rem;
            padding: 3px 9px;
            font-weight: 800;
            text-transform: uppercase;
            border-radius: 6px;
            letter-spacing: 0.4px;
            line-height: 1.3;
            display: inline-block;
        }
        .bg-maroon { background-color: #800000 !important; color: #ffffff !important; }

        .sender-name {
            font-weight: 800;
            color: var(--accent);
            font-size: 0.82rem;
            line-height: 1.3;
        }
        .sender-meta {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 600;
            margin-top: 2px;
        }

        .date-main {
            font-weight: 800;
            font-size: 0.82rem;
            color: var(--text-main);
        }
        .date-sub {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Preview button */
        .preview-btn {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-soft);
            border: 1px solid var(--accent-soft);
            color: var(--accent);
            transition: 0.18s;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
        }
        .preview-btn:hover {
            background: var(--accent);
            color: #fff;
            transform: translateY(-2px);
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.3;
            color: var(--accent);
            display: block;
            margin-bottom: 14px;
        }
        .empty-state .empty-title {
            font-weight: 800;
            color: var(--text-main);
            font-size: 1.1rem;
            margin-bottom: 6px;
        }
        .empty-state .empty-sub {
            font-weight: 500;
            font-size: 0.88rem;
        }

        /* ============================================================
           MODAL
           ============================================================ */
        .modal-xl { max-width: 98vw; }
        .modal-content {
            height: 95vh;
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        }
        .pdf-viewer-pane {
            flex: 7.2;
            background: #2c2c2c;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .minutes-pane {
            flex: 2.8;
            background: var(--sidebar-bg);
            border-left: 1px solid var(--border);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .timeline-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 25px 20px;
        }
        .timeline-container::-webkit-scrollbar { width: 5px; }
        .timeline-container::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }

        .trail-card {
            position: relative;
            padding-left: 45px;
            margin-bottom: 30px;
            border-left: 2px dashed var(--border);
            margin-left: 15px;
        }
        .trail-number {
            position: absolute;
            left: -16px;
            top: 0;
            width: 30px;
            height: 30px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.75rem;
            border: 4px solid var(--sidebar-bg);
            z-index: 5;
        }
        .trail-user {
            color: var(--accent);
            font-size: 0.82rem;
            display: block;
            margin-bottom: 4px;
            font-weight: 800;
        }
        .trail-direction {
            color: var(--text-muted);
            font-size: 0.76rem;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        .trail-direction i {
            font-size: 0.68rem;
            color: var(--accent);
        }
        .trail-date {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .trail-comment-box {
            background: var(--bg-body);
            border: 1px solid var(--border);
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            color: var(--text-main);
            line-height: 1.55;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            font-weight: 500;
        }
        .trail-comment-box i {
            color: var(--accent);
            margin-right: 6px;
            font-size: 0.7rem;
        }

        .btn-disabled {
            opacity: 0.55;
            pointer-events: none;
            cursor: not-allowed;
            filter: grayscale(0.5);
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px 16px;
            }
            .top-nav h4 { font-size: 1.15rem; }
            .filter-input { min-width: 100%; }
            .letter-table thead th,
            .letter-table tbody td { padding: 10px 12px; }
            .modal-content { flex-direction: column; height: 95vh; }
            .pdf-viewer-pane { flex: 1; min-height: 50vh; }
            .minutes-pane { flex: 1; min-height: 40vh; }
        }
    </style>
</head>
<body>

<!-- ============================================================
     SIDEBAR
     ============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>

    <nav style="display:flex; flex-direction:column; flex:1;">
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>

        <div class="sidebar-divider">Dispatching</div>
        <div>
            <button class="nav-btn active" onclick="toggleSubmenu('internalSub')" type="button">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
            </button>
            <div id="internalSub" class="submenu show">
                <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="inbox.php" class="sub-nav-btn active-sub">
                    My Inbox
                    <span id="sidebarBadge" class="sidebar-badge <?= ($newCount == 0) ? 'd-none' : '' ?>"><?= $newCount ?></span>
                </a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<main class="main-content">

    <header class="top-nav">
        <div>
            <h4>Personal Inbox</h4>
            <p class="subtitle">Incoming correspondence for your attention</p>
        </div>
        <input type="text" id="tableSearch" class="filter-input" placeholder="🔍 Search incoming letters...">
    </header>

    <section>
        <div class="table-container">
            <div class="table-header">
                <h6><i class="fa-solid fa-inbox"></i> Received Mail History</h6>
                <span class="inbox-count">
                    <i class="fa-solid fa-envelope" style="margin-right:4px;"></i>
                    <?= count($rows) ?> item<?= count($rows) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <div class="empty-title">Your inbox is empty</div>
                    <div class="empty-sub">Letters forwarded to you will appear here</div>
                </div>
            <?php else: ?>
                <div class="letter-table-wrap">
                    <table class="letter-table" id="inboxTable">
                        <thead>
                            <tr>
                                <th style="width: 15%">CDR &amp; Ref</th>
                                <th style="width: 35%">Subject &amp; Priority</th>
                                <th style="width: 20%">Originator Details</th>
                                <th style="width: 15%">Date Received</th>
                                <th class="text-end" style="width: 15%; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row):
                                $priority = $row['priority_status'] ?? 'Normal';
                                $pClass = ($priority == 'Urgent') ? 'bg-danger' : (($priority == 'Immediately') ? 'bg-maroon' : 'bg-success');
                                $pBorder = ($priority == 'Urgent') ? '#dc3545' : (($priority == 'Immediately') ? '#800000' : '#198754');
                                $isUnread = !$row['seen'];
                            ?>
                                <tr class="letter-row <?= $isUnread ? 'unread-row' : '' ?>"
                                    id="row-<?= $row['forward_id'] ?>"
                                    style="--priority-color: <?= $pBorder ?>;">
                                    <!-- CDR & Ref (with priority bar on left) -->
                                    <td class="priority-cell">
                                        <div class="cdr-pill mb-1">
                                            <?php if ($isUnread): ?>
                                                <span class="unread-dot"></span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($row['cdr_number']) ?>
                                        </div>
                                        <div class="ref-text">
                                            <?= htmlspecialchars($row['letter_reference'] ?: 'NO-REF') ?>
                                        </div>
                                    </td>

                                    <!-- Subject & Priority -->
                                    <td>
                                        <div class="subject-title">
                                            <?= htmlspecialchars($row['letter_title']) ?>
                                        </div>
                                        <div class="badges-row">
                                            <span class="mini-badge text-white <?= $pClass ?>">
                                                <?= htmlspecialchars($priority) ?>
                                            </span>
                                            <span class="mini-badge bg-secondary text-white">
                                                <?= htmlspecialchars($row['security_classification']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Originator -->
                                    <td>
                                        <div class="sender-name">
                                            <?= htmlspecialchars($row['sender_rank']) ?> <?= htmlspecialchars($row['sender_name']) ?>
                                        </div>
                                        <div class="sender-meta">
                                            <?= htmlspecialchars($row['sender_appointment']) ?> | <?= htmlspecialchars($row['sender_work_place']) ?>
                                        </div>
                                    </td>

                                    <!-- Date -->
                                    <td>
                                        <div class="date-main">
                                            <?= date('d M Y', strtotime($row['forwarded_at'])) ?>
                                        </div>
                                        <div class="date-sub">
                                            <i class="fa-regular fa-clock" style="margin-right:3px;"></i>
                                            <?= date('h:i A', strtotime($row['forwarded_at'])) ?>
                                        </div>
                                    </td>

                                    <!-- Action -->
                                    <td style="text-align: right;">
                                        <button class="preview-btn preview-btn-trigger"
                                                data-info='<?= htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8') ?>'
                                                title="Open Letter">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- ============================================================
     PREVIEW MODAL
     ============================================================ -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content d-flex flex-row">
            <!-- PDF Viewer -->
            <div class="pdf-viewer-pane">
                <div class="p-2 bg-dark d-flex justify-content-between align-items-center border-bottom border-secondary">
                    <span id="mTitle" class="text-white small fw-bold ms-2"></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div id="pdfContainer" class="flex-grow-1"></div>
            </div>

            <!-- Minutes Pane -->
            <div class="minutes-pane">
                <div class="p-3 border-bottom d-flex align-items-center" style="background: var(--bg-body);">
                    <i class="fa-solid fa-comments" style="color: var(--accent); margin-right: 8px;"></i>
                    <h6 class="mb-0 fw-bold" style="color: var(--accent); font-size: 0.85rem; letter-spacing: 0.3px;">
                        MINUTE HISTORY
                    </h6>
                </div>
                <div class="timeline-container" id="mHistory"></div>
                <div class="p-3 border-top mt-auto" style="background: var(--bg-body);">
                    <a id="mForward" href="#" class="btn btn-primary btn-sm w-100 rounded-3 mb-2" style="font-weight: 700;">
                        <i class="fa-solid fa-paper-plane me-1"></i> Forward / Reply
                    </a>
                    <?php if (hasPermission('FINAL_DISPOSAL')): ?>
                        <a id="mDisposal" href="#" class="btn btn-success btn-sm w-100 rounded-3" style="font-weight: 700;">
                            <i class="fa-solid fa-box-archive me-1"></i> Final Disposal
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    /* ─── THEME ─── */
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    /* ─── SIDEBAR ─── */
    function toggleSubmenu(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('show');
    }

    /* ─── SEARCH ─── */
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const v = this.value.toLowerCase().trim();
            document.querySelectorAll('.letter-row').forEach(r => {
                r.style.display = r.innerText.toLowerCase().includes(v) ? '' : 'none';
            });
        });
    }

    /* ─── HTML escape ─── */
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /* ─── PREVIEW BUTTONS ─── */
    document.querySelectorAll('.preview-btn-trigger').forEach(btn => {
        btn.addEventListener('click', function () {
            const data = JSON.parse(this.dataset.info);
            const rowId = data.forward_id;
            const rowElement = document.getElementById('row-' + rowId);

            document.getElementById('mTitle').innerText = `${data.cdr_number} | ${data.letter_title}`;

            /* Check if finalized */
            const isFinalized = (data.doc_status === 'Completed') || (data.is_under_final_disposal == 1);
            const forwardBtn = document.getElementById('mForward');
            const disposalBtn = document.getElementById('mDisposal');

            if (isFinalized) {
                forwardBtn.removeAttribute('href');
                forwardBtn.classList.add('btn-disabled');
                forwardBtn.title = 'This letter is completed or under final disposal. No further actions allowed.';
                if (disposalBtn) {
                    disposalBtn.removeAttribute('href');
                    disposalBtn.classList.add('btn-disabled');
                    disposalBtn.title = 'This letter is already completed or under final disposal.';
                }
            } else {
                forwardBtn.href = `forward_letter.php?letter_id=${data.letter_id}`;
                forwardBtn.classList.remove('btn-disabled');
                forwardBtn.title = '';
                if (disposalBtn) {
                    disposalBtn.href = `../Final_Disposal/forward_final_disposal.php?letter_id=${data.letter_id}`;
                    disposalBtn.classList.remove('btn-disabled');
                    disposalBtn.title = '';
                }
            }

            /* PDF / Attachment */
            const container = document.getElementById('pdfContainer');
            container.innerHTML = data.attachment
                ? `<iframe src="../../uploads/letters/${data.attachment}#view=FitH" width="100%" height="100%" style="border:none;"></iframe>`
                : `<div class="h-100 d-flex align-items-center justify-content-center text-white-50" style="font-weight:600;">
                       <div class="text-center">
                           <i class="fa-solid fa-file-slash" style="font-size:3rem; opacity:0.3; display:block; margin-bottom:14px;"></i>
                           No Attachment Available
                       </div>
                   </div>`;

            /* Timeline */
            const timeline = document.getElementById('mHistory');
            timeline.innerHTML = '';

            if (data.history && data.history.length > 0) {
                data.history.forEach((h, index) => {
                    const formattedDate = new Date(h.forwarded_at).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    timeline.innerHTML += `
                        <div class="trail-card">
                            <div class="trail-number">${index + 1}</div>
                            <div class="trail-user">
                                ${escapeHtml(h.sender_rank)} ${escapeHtml(h.sender_name)}
                            </div>
                            <div class="trail-direction">
                                <i class="fa-solid fa-arrow-right"></i>
                                To: ${escapeHtml(h.receiver_rank)} ${escapeHtml(h.receiver_name)}
                            </div>
                            <div class="trail-date">
                                <i class="fa-regular fa-calendar"></i>
                                ${formattedDate}
                            </div>
                            <div class="trail-comment-box">
                                <i class="fa-regular fa-message"></i>
                                ${escapeHtml(h.comments) || 'Document forwarded without additional comments.'}
                            </div>
                        </div>`;
                });
            } else {
                timeline.innerHTML = `
                    <div class="text-center p-5" style="opacity:0.5;">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size:2rem; display:block; margin-bottom:10px;"></i>
                        <p style="font-weight:700;">No trail recorded.</p>
                    </div>`;
            }

            /* Mark as seen */
            if (rowElement && rowElement.classList.contains('unread-row')) {
                fetch('mark_as_seen.php?id=' + rowId)
                    .then(() => {
                        rowElement.classList.remove('unread-row');
                        /* Remove unread dot if present */
                        const dot = rowElement.querySelector('.unread-dot');
                        if (dot) dot.remove();
                        /* Update sidebar badge */
                        const badge = document.getElementById('sidebarBadge');
                        if (badge) {
                            let count = parseInt(badge.innerText);
                            if (count > 1) {
                                badge.innerText = count - 1;
                            } else {
                                badge.classList.add('d-none');
                            }
                        }
                    })
                    .catch(() => { /* silent */ });
            }

            new bootstrap.Modal(document.getElementById('previewModal')).show();
        });
    });
</script>
</body>
</html>