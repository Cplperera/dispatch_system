<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Permission check – adjust to your needs
if (!hasPermission('VIEW_TASK_LETTERS')) {
    die('Access Denied!');
}

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$task_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

/* ==========================
   FETCH TASK DETAILS
========================== */
$stmt = $conn->prepare("
    SELECT t.*, u.name_with_initial, u.id as assigned_by_id
    FROM task_letters t
    LEFT JOIN users u ON t.assigned_by = u.id
    WHERE t.id = ?
");
if (!$stmt) die("DB Error (Task): " . $conn->error);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
if (!$task) die("Task not found");

/* ==========================
   FETCH AGENCIES (assigned regiments)
========================== */
$agencies = $conn->prepare("
    SELECT r.id as regiment_id, r.regiment_name, tla.assigned_at
    FROM task_letter_agencies tla
    JOIN regiments r ON tla.regiment_id = r.id
    WHERE tla.task_letter_id = ?
    ORDER BY r.regiment_name
");
if (!$agencies) die("DB Error (Agencies): " . $conn->error);
$agencies->bind_param("i", $task_id);
$agencies->execute();
$agency_result = $agencies->get_result();
$total_agencies = $agency_result->num_rows;

/* ==========================
   FETCH ALL REPLIES (multiple per regiment allowed)
========================== */
$replies = $conn->prepare("
    SELECT tr.regiment_id, tr.status as reply_status, tr.replied_at,
           il.letter_reference, il.id as internal_letter_id,
           r.regiment_name
    FROM task_replies tr
    JOIN regiments r ON tr.regiment_id = r.id
    LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id
    WHERE tr.task_letter_id = ?
    ORDER BY tr.replied_at DESC
");
if (!$replies) die("DB Error (Replies): " . $conn->error);
$replies->bind_param("i", $task_id);
$replies->execute();
$replies_result = $replies->get_result();

// Build a map of regiment_id => array of replies
$reply_map = [];
while ($row = $replies_result->fetch_assoc()) {
    $rid = $row['regiment_id'];
    if (!isset($reply_map[$rid])) {
        $reply_map[$rid] = [];
    }
    $reply_map[$rid][] = $row;
}
$replies_result->data_seek(0); // reset for later use if needed

// Count distinct regiments that have replied
$replied_count = count($reply_map);
// Count total replies (including multiples)
$total_replies = array_sum(array_map('count', $reply_map));

/* ==========================
   DERIVE TASK STATUS
========================== */
if ($replied_count == 0) {
    $derived_status = 'Pending';
} elseif ($replied_count < $total_agencies) {
    $derived_status = 'Partially Replied';
} else {
    $derived_status = 'Completed';
}

/* ==========================
   FETCH LINKED INTERNAL LETTERS (original requests)
========================== */
$internals = $conn->prepare("
    SELECT il.id, il.letter_reference, il.letter_title, il.status as letter_status
    FROM task_internal_links til
    JOIN internal_letters il ON til.internal_letter_id = il.id
    WHERE til.task_id = ?
");
if (!$internals) die("DB Error (Internals): " . $conn->error);
$internals->bind_param("i", $task_id);
$internals->execute();
$internal_result = $internals->get_result();

/* ==========================
   FETCH ACTIVITY LOG
========================== */
$activity = $conn->prepare("
    (SELECT 
        'forward' as type,
        ilf.forwarded_at as event_time,
        CONCAT('Forwarded to ', u_to.rank, ' ', u_to.name_with_initial) as description,
        u_from.name_with_initial as actor,
        il.letter_reference as ref
     FROM internal_letters_forwarding ilf
     JOIN internal_letters il ON ilf.letter_id = il.id
     JOIN users u_from ON ilf.forwarded_by = u_from.id
     JOIN users u_to ON ilf.forwarded_to = u_to.id
     WHERE il.id IN (SELECT internal_letter_id FROM task_internal_links WHERE task_id = ?)
    )
    UNION ALL
    (SELECT 
        'reply' as type,
        tr.replied_at as event_time,
        CONCAT('Reply from ', r.regiment_name, ' (', tr.status, ')') as description,
        'System' as actor,
        COALESCE(il.letter_reference, 'N/A') as ref
     FROM task_replies tr
     JOIN regiments r ON tr.regiment_id = r.id
     LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id
     WHERE tr.task_letter_id = ?
    )
    ORDER BY event_time DESC
    LIMIT 20
");
if (!$activity) die("DB Error (Activity): " . $conn->error);
$activity->bind_param("ii", $task_id, $task_id);
$activity->execute();
$activity_result = $activity->get_result();

// For reply status chart: count of each status across all replies (Including 'Data NIL')
$reply_status_counts = ['Accepted' => 0, 'Data NIL' => 0];
foreach ($reply_map as $replies_list) {
    foreach ($replies_list as $reply) {
        if (isset($reply_status_counts[$reply['reply_status']])) {
            $reply_status_counts[$reply['reply_status']]++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <script src="../../assets/js/chart.umd.min.js"></script>
    <script src="../../assets/js/pdf.min.js"></script>
    <style>
        :root {
            --bg-body: #F0F2F5; 
            --sidebar-bg: #FFFFFF; 
            --card-bg: #FFFFFF;
            --accent: #4318FF; 
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #4A5568;
            --text-muted: #707EAE;
            --border: #DDE2EF;
            --sidebar-width: 280px;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; 
            --sidebar-bg: #111C44; 
            --card-bg: #111C44;
            --accent: #7551FF; 
            --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #F8F9FA;       
            --text-muted: #A3AED0;       
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            font-family: "Segoe UI", sans-serif;
            transition: 0.3s;
            margin: 0;
        }

        /* Sidebar */
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
        }
        .nav-btn:hover, .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .submenu {
            padding-left: 20px;
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
        .sub-nav-btn:hover {
            color: var(--accent);
            border-left-color: var(--accent);
        }
        .sub-nav-btn.active-sub {
            color: var(--accent);
            border-left-color: var(--accent);
            font-weight: 700;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
        }
        .top-nav {
            position: sticky;
            top: 0;
            background: var(--bg-body);
            padding: 25px 30px;
            z-index: 90;
        }

        .details-container {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 30px;
            margin: 0 30px 30px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .badge {
            font-weight: 500;
            padding: 0.5em 0.85em;
        }
        .priority-high { background: #fee2e2; color: #991b1b; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-low { background: #dcfce7; color: #166534; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-partially_replied { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .reply-accepted { background: #d4edda; color: #155724; }
        .reply-data_nil { background: #e0f2fe; color: #0369a1; }
       
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--border);
        }
        .timeline-item:last-child { border-left-color: transparent; }
        .timeline-icon {
            position: absolute;
            left: -0.75rem;
            top: 0;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: var(--card-bg);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .timeline-item.forward .timeline-icon { border-color: var(--accent); color: var(--accent); }
        .timeline-item.reply .timeline-icon { border-color: #28a745; color: #28a745; }

        .nav-tabs .nav-link {
            color: var(--text-muted);
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: var(--accent);
            border-bottom: 3px solid var(--accent);
            background: transparent;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .stat-card {
            background: var(--bg-body);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
        }
        .stat-card i { font-size: 2rem; color: var(--accent); }

        .pdf-preview {
            height: 500px;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            overflow: auto;
            background: var(--bg-body);
        }

        .btn-outline-primary {
            border-color: var(--accent);
            color: var(--accent);
        }
        .btn-outline-primary:hover {
            background: var(--accent);
            color: #fff;
        }
        
        .table {
            color: var(--text-main);
        }
        strong {
            color: var(--text-main);
        }

        /* Animated Past Due Warning */
        @keyframes pulse-due {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.03); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .due-date-overdue {
            background: #fee2e2 !important;
            border: 2px solid #dc3545 !important;
            color: #991b1b !important;
            animation: pulse-due 1.8s infinite;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand-box mb-4 px-2">
            <div class="brand-name">
                <i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br>
                <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
            </div>
        </div>
        <nav>
            <a href="../dashboard/dashboard.php" class="nav-btn">
                <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
            </a>
            <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">
                Dispatching
            </div>
            
            <div>
                <div class="nav-btn" onclick="toggleSubmenu('internalSub')">
                    <i class="fa-solid fa-envelope-open-text me-2"></i> Internal Letters
                    <i class="fa-solid fa-chevron-down ms-auto small"></i>
                </div>
                <div id="internalSub" class="submenu">
                    <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                    <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                    <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
                </div>
            </div>

            <div>
                <div class="nav-btn active" onclick="toggleSubmenu('taskSub')">
                    <i class="fa-solid fa-tasks me-2"></i> Task Letters
                    <i class="fa-solid fa-chevron-down ms-auto small"></i>
                </div>
                <div id="taskSub" class="submenu show">
                    <a href="task_list.php" class="sub-nav-btn">View Tasking</a>               
                    <a href="task_details.php?id=<?= $task_id ?>" class="sub-nav-btn active-sub">Current Task</a>
                </div>
            </div>

            <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-nav d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-main">Task Details: <?= htmlspecialchars($task['letter_ref']) ?></h4>
        </header>

        <div class="details-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
                <div>
                    <button class="btn btn-outline-primary rounded-pill px-4" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                        <span class="badge bg-secondary bg-opacity-10 text-dark px-3 py-2 rounded-pill" style="color: var(--text-main) !important;">
                            <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($task['tsk_cdr_number'] ?? 'No CDR') ?>
                        </span>
                        <span class="badge priority-<?= strtolower($task['priority_status']) ?> rounded-pill">
                            <i class="fas fa-flag me-1"></i><?= $task['priority_status'] ?>
                        </span>
                        <span class="badge status-<?= str_replace(' ', '_', strtolower($derived_status)) ?> rounded-pill derived-badge">
                            <i class="fas fa-robot me-1"></i><?= $derived_status ?> (auto)
                        </span>
                    </div>
                    <h2 class="text-muted h3 mb-3"><?= htmlspecialchars($task['title']) ?></h2>
                    <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($task['description'])) ?></p>
                    <div class="row g-4">
                        <div class="text-muted col-md-3 col-6"><i class="far fa-calendar-alt text-muted me-2"></i>Letter Date: <strong><?= $task['letter_date'] ?></strong></div>
                        <div class="text-muted col-md-3 col-6"><i class="far fa-clock text-muted me-2"></i>Request Date: <strong><?= $task['request_date'] ?? 'N/A' ?></strong></div>
                        <div class="text-muted col-md-3 col-6"><i class="fas fa-user text-muted me-2"></i>Assigned By: <strong><?= htmlspecialchars($task['name_with_initial'] ?? 'System') ?></strong></div>
                        <div class="text-muted col-md-3 col-6"><i class="fas fa-calendar-plus text-muted me-2"></i>Created: <strong><?= date('d M Y H:i', strtotime($task['created_at'])) ?></strong></div>
                    </div>
                    <?php if (!empty($task['about_letter'])): ?>
                        <hr>
                        <p><strong>About Letter:</strong><br><span class="text-muted"><?= nl2br(htmlspecialchars($task['about_letter'])) ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($task['comments'])): ?>
                        <p><strong>Comments:</strong><br><span class="text-muted"><?= nl2br(htmlspecialchars($task['comments'])) ?></span></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="taskTabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab"><i class="fas fa-info-circle me-2"></i>Overview</button></li>
                        <li class="nav-item"><button class="nav-link" id="agencies-tab" data-bs-toggle="tab" data-bs-target="#agencies" type="button" role="tab"><i class="fas fa-building me-2"></i>Agencies <span class="badge bg-secondary rounded-pill ms-1"><?= $total_agencies ?></span></button></li>
                        <li class="nav-item"><button class="nav-link" id="letters-tab" data-bs-toggle="tab" data-bs-target="#letters" type="button" role="tab"><i class="fas fa-envelope me-2"></i>Linked Letters <?php if ($internal_result->num_rows): ?><span class="badge bg-secondary rounded-pill ms-1"><?= $internal_result->num_rows ?></span><?php endif; ?></button></li>
                        <li class="nav-item"><button class="nav-link" id="replies-tab" data-bs-toggle="tab" data-bs-target="#replies" type="button" role="tab"><i class="fas fa-reply-all me-2"></i>Replies <span class="badge bg-secondary rounded-pill ms-1"><?= $total_replies ?>/<?= $total_agencies ?></span></button></li>
                        <li class="nav-item"><button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline" type="button" role="tab"><i class="fas fa-history me-2"></i>Timeline</button></li>
                        <?php if (!empty($task['file_path'])): ?>
                        <li class="nav-item"><button class="nav-link" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview" type="button" role="tab"><i class="fas fa-file-pdf me-2"></i>Preview</button></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="taskTabsContent">
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <div class="row">
                                <div class="col-lg-6">
                                    <h5 class="text-muted mb-3">Task Information</h5>
                                    <table class="table table-borderless">
                                        <tr><th style="width:150px;">Reference</th><td><?= htmlspecialchars($task['letter_ref']) ?></td></tr>
                                        <tr><th>CDR Number</th><td><?= htmlspecialchars($task['tsk_cdr_number'] ?? 'N/A') ?></td></tr>
                                        <tr><th>Letter Date</th><td><?= $task['letter_date'] ?></td></tr>
                                        <tr><th>Request Date</th><td><?= $task['request_date'] ?? 'N/A' ?></td></tr>
                                        <tr><th>Priority</th><td><span class="badge priority-<?= strtolower($task['priority_status']) ?>"><?= $task['priority_status'] ?></span></td></tr>
                                        <tr><th>Derived Status</th><td><span class="badge status-<?= str_replace(' ', '_', strtolower($derived_status)) ?>"><?= $derived_status ?></span></td></tr>
                                    </table>
                                </div>
                                <div class="col-lg-6">
                                    <h5 class="text-muted mb-3 d-flex justify-content-between align-items-center">
                                        <span>Progress</span>
                                        <?php 
                                        // Determine due date status
                                        $due_date_str = $task['request_date'] ?? $task['letter_date'];
                                        $is_past_due = false;
                                        if (!empty($due_date_str)) {
                                            $due_timestamp = strtotime($due_date_str);
                                            // Past due if due date is before today and not fully completed
                                            if ($due_timestamp && $due_timestamp < strtotime('today') && $derived_status !== 'Completed') {
                                                $is_past_due = true;
                                            }
                                        }
                                        ?>
                                        <?php if ($is_past_due): ?>
                                            <span class="badge due-date-overdue rounded-pill px-3 py-2">
                                                <i class="fas fa-exclamation-triangle me-1"></i> OVERDUE (Due: <?= date('d M Y', strtotime($due_date_str)) ?>)
                                            </span>
                                        <?php elseif (!empty($due_date_str)): ?>
                                            <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                                <i class="far fa-calendar-alt me-1"></i> Due: <?= date('d M Y', strtotime($due_date_str)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </h5>

                                    <div class="row g-3">
                                        <!-- Agencies Assigned List -->
                                        <div class="col-sm-6">
                                            <div class="card h-100 mb-0 shadow-sm">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                                    <span class="fw-bold"><i class="fas fa-building text-primary me-2"></i>Agencies Assigned</span>
                                                    <span class="badge bg-primary rounded-pill"><?= $total_agencies ?></span>
                                                </div>
                                                <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                                    <?php 
                                                    $agency_result->data_seek(0);
                                                    if ($total_agencies > 0): 
                                                    ?>
                                                        <ul class="list-group list-group-flush small">
                                                            <?php while ($agency = $agency_result->fetch_assoc()): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                                                                    <span><?= htmlspecialchars($agency['regiment_name']) ?></span>
                                                                    <span class="text-muted fs-7"><?= date('d M Y', strtotime($agency['assigned_at'])) ?></span>
                                                                </li>
                                                            <?php endwhile; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p class="text-muted text-center my-3 small">No agencies assigned.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Agencies Replied List with Letter Count -->
                                        <div class="col-sm-6">
                                            <div class="card h-100 mb-0 shadow-sm">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                                                    <span class="fw-bold"><i class="fas fa-reply text-success me-2"></i>Agencies Replied</span>
                                                    <span class="badge bg-success rounded-pill"><?= $replied_count ?> / <?= $total_agencies ?></span>
                                                </div>
                                                <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                                                    <?php if ($replied_count > 0): ?>
                                                        <ul class="list-group list-group-flush small">
                                                            <?php 
                                                            foreach ($reply_map as $reg_id => $replies_list): 
                                                                $agency_name = $replies_list[0]['regiment_name'] ?? 'Unknown Agency';
                                                                $letter_count = count($replies_list);
                                                            ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 border-0 border-bottom">
                                                                    <div>
                                                                        <i class="fas fa-check-circle text-success me-1"></i>
                                                                       <strong class="text-muted"><?= htmlspecialchars($agency_name) ?></strong>
                                                                    </div>
                                                                    <span class="badge bg-info text-dark rounded-pill">
                                                                        <i class="fas fa-envelope me-1"></i><?= $letter_count ?> <?= $letter_count === 1 ? 'Letter' : 'Letters' ?>
                                                                    </span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p class="text-muted text-center my-3 small">No agencies replied yet.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress mt-3" style="height: 25px;">
                                        <?php $percent = $total_agencies ? round(($replied_count / $total_agencies) * 100) : 0; ?>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><?= $percent ?>%</div>
                                    </div>
                                    <canvas id="replyChart" style="max-height:200px; margin-top:20px;"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="agencies" role="tabpanel">
                            <h5 class="text-muted mb-3">Assigned Agencies & Reply Status</h5>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Regiment</th>
                                            <th>Assigned Date</th>
                                            <th>Replied?</th>
                                            <th>Latest Reply Status</th>
                                            <th>Reply Count</th>
                                            <th>Latest Reply Letter</th>
                                            <th>Latest Reply At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $agency_result->data_seek(0); while ($agency = $agency_result->fetch_assoc()): 
                                            $rid = $agency['regiment_id']; 
                                            $has_replied = isset($reply_map[$rid]);
                                            $replies_for_reg = $has_replied ? $reply_map[$rid] : [];
                                            $reply_count = count($replies_for_reg);
                                            $latest_reply = $reply_count > 0 ? $replies_for_reg[0] : null;
                                            $status_class = $has_replied ? str_replace(' ', '_', strtolower($latest_reply['reply_status'])) : '';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($agency['regiment_name']) ?></td>
                                            <td><?= date('d M Y H:i', strtotime($agency['assigned_at'])) ?></td>
                                            <td><?php if ($has_replied): ?><span class="badge bg-success"><i class="fas fa-check"></i> Yes</span><?php else: ?><span class="badge bg-secondary"><i class="fas fa-times"></i> No</span><?php endif; ?></td>
                                            <td><?php if ($has_replied): ?><span class="badge reply-<?= $status_class ?>"><?= $latest_reply['reply_status'] ?></span><?php else: ?>—<?php endif; ?></td>
                                            <td><?= $reply_count ?: '—' ?></td>
                                            <td>
                                                <?php if ($has_replied && !empty($latest_reply['internal_letter_id'])): ?>
                                                    <a href="../../letters/inward/internal_letter_details.php?id=<?= $latest_reply['internal_letter_id'] ?>"><?= htmlspecialchars($latest_reply['letter_reference']) ?></a>
                                                <?php elseif($has_replied): ?>
                                                    <em class="text-muted">No letter (Data NIL)</em>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $has_replied ? date('d M Y H:i', strtotime($latest_reply['replied_at'])) : '—' ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="letters" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light"><tr><th>Reference</th><th>Title</th><th>Status</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php if ($internal_result->num_rows > 0): while ($row = $internal_result->fetch_assoc()): ?>
                                        <tr><td><?= htmlspecialchars($row['letter_reference']) ?></td><td><?= htmlspecialchars($row['letter_title']) ?></td><td><span class="badge bg-secondary"><?= $row['letter_status'] ?></span></td><td><a href="../../letters/inward/internal_letter_details.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-eye"></i> View</a></td></tr>
                                        <?php endwhile; else: ?><tr><td colspan="4" class="text-center text-muted py-4">No internal letters linked</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="replies" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light"><tr><th>Regiment</th><th>Reply Letter</th><th>Status</th><th>Replied At</th></tr></thead>
                                    <tbody>
                                        <?php if (count($reply_map) > 0): 
                                            foreach ($reply_map as $reg_id => $replies_list): 
                                                foreach ($replies_list as $reply): 
                                                    $status_class = str_replace(' ', '_', strtolower($reply['reply_status']));
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($reply['regiment_name']) ?></td>
                                            <td><?php if (!empty($reply['internal_letter_id'])): ?><a href="../../letters/inward/internal_letter_details.php?id=<?= $reply['internal_letter_id'] ?>"><?= htmlspecialchars($reply['letter_reference']) ?></a><?php else: ?><em class="text-muted">No letter (Data NIL)</em><?php endif; ?></td>
                                            <td><span class="badge reply-<?= $status_class ?>"><?= $reply['reply_status'] ?></span></td>
                                            <td><?= date('d M Y H:i', strtotime($reply['replied_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; 
                                            endforeach; 
                                        else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">No replies yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="timeline" role="tabpanel">
                            <h5 class="text-muted mb-3">Activity Timeline</h5>
                            <div class="timeline">
                                <?php if ($activity_result->num_rows > 0): while ($event = $activity_result->fetch_assoc()): ?>
                                <div class="timeline-item <?= $event['type'] ?>">
                                    <div class="timeline-icon"><?php if ($event['type'] == 'forward'): ?><i class="fas fa-arrow-right fa-xs"></i><?php else: ?><i class="fas fa-reply fa-xs"></i><?php endif; ?></div>
                                    <div class="text-muted ms-3"><div class="d-flex flex-wrap align-items-center gap-2"><strong><?= htmlspecialchars($event['actor']) ?></strong><span class="text-muted small"><?= date('d M Y H:i', strtotime($event['event_time'])) ?></span></div><p class="mb-0"><?= htmlspecialchars($event['description']) ?></p><small class="text-muted">Letter: <?= htmlspecialchars($event['ref']) ?></small></div>
                                </div>
                                <?php endwhile; else: ?><p class="text-muted">No activity recorded yet.</p><?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($task['file_path'])): ?>
                        <div class="tab-pane fade" id="preview" role="tabpanel">
                            <div class="pdf-preview" id="pdfViewer"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src='../../assets/js/bootstrap.bundle.min.js'></script>
    <script>
        const savedTheme = localStorage.getItem('dispatch_theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const icon = submenu.previousElementSibling.querySelector('.fa-chevron-down');
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

        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('replyChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_keys($reply_status_counts)) ?>,
                        datasets: [{
                            data: <?= json_encode(array_values($reply_status_counts)) ?>,
                            backgroundColor: ['#574fc7', '#27a086'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            }

            <?php if (!empty($task['file_path'])): ?>
            const pdfPath = '../../<?= $task['file_path'] ?>';
            const pdfViewer = document.getElementById('pdfViewer');
            pdfjsLib.GlobalWorkerOptions.workerSrc = '../../assets/js/pdf.worker.min.js';
        if (pdfViewer) {
            pdfjsLib.getDocument(pdfPath).promise.then(pdf => {
                pdf.getPage(1).then(page => {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    pdfViewer.appendChild(canvas);
                    page.render({ canvasContext: context, viewport });
                });
            }).catch(() => { 
                pdfViewer.innerHTML = '<p class="text-danger p-3">Failed to load PDF preview.</p>'; 
            });
        }
            <?php endif; ?>
        });
    </script>
</body>
</html>