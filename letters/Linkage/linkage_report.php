<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('VIEW_OUTGOING_LETTERS')) die('Access Denied');

function safeQuery($conn, $sql) { $r = $conn->query($sql); if (!$r) die("SQL Error: " . $conn->error); return $r; }

// ════════════════════════════════════════════════════════════
// FILTERS (dates + desk)
// ════════════════════════════════════════════════════════════
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']   ?? '') ? $_GET['date_to']   : '';
$deskId   = isset($_GET['desk_id']) ? intval($_GET['desk_id']) : 0;

$desks = [];
$dq = $conn->query("SELECT id, desk_name FROM desks ORDER BY desk_name");
if ($dq) while ($d = $dq->fetch_assoc()) $desks[] = $d;

$wOut = ["o.is_deleted = 0"];
$wInt = ["il.is_deleted = 0"];
$wTsk = ["1=1"];

if ($dateFrom) {
    $wOut[] = "o.letter_date >= '$dateFrom'";
    $wInt[] = "il.letter_date >= '$dateFrom'";
    $wTsk[] = "tl.letter_date >= '$dateFrom'";
}
if ($dateTo) {
    $wOut[] = "o.letter_date <= '$dateTo'";
    $wInt[] = "il.letter_date <= '$dateTo'";
    $wTsk[] = "tl.letter_date <= '$dateTo'";
}
if ($deskId > 0) {
    $wOut[] = "cu.desk_id = $deskId";
    $wInt[] = "COALESCE(
        (SELECT u1.desk_id FROM internal_letters_forwarding f1
         JOIN users u1 ON f1.forwarded_to = u1.id
         WHERE f1.letter_id = il.id
         ORDER BY f1.forwarded_at DESC LIMIT 1),
        (SELECT u2.desk_id FROM users u2 WHERE u2.id = il.registered_by)
    ) = $deskId";
    $wTsk[] = "au.desk_id = $deskId";
}

$whereOut = implode(" AND ", $wOut);
$whereInt = implode(" AND ", $wInt);
$whereTsk = implode(" AND ", $wTsk);

$outwardData = []; $internalData = []; $taskData = [];

$sqlOutward = "SELECT o.id AS doc_id, o.letter_ref, o.cdr_no, o.letter_date, o.title, o.receiver, o.status,
    d.desk_name,
    GROUP_CONCAT(DISTINCT CONCAT(il.cdr_number, '|', il.letter_title) SEPARATOR '||') AS linked_internals,
    GROUP_CONCAT(DISTINCT CONCAT(tl.tsk_cdr_number, '|', tl.title) SEPARATOR '||') AS linked_tasks
    FROM outward_letters o
    LEFT JOIN users cu ON o.created_by = cu.id
    LEFT JOIN desks d  ON cu.desk_id = d.id
    LEFT JOIN outward_internal oi ON o.id = oi.outward_id
    LEFT JOIN internal_letters il ON oi.internal_id = il.id AND il.is_deleted = 0
    LEFT JOIN outward_task ot ON o.id = ot.outward_id
    LEFT JOIN task_letters tl ON ot.task_id = tl.id
    WHERE $whereOut
    GROUP BY o.id ORDER BY o.created_at DESC";
$result = safeQuery($conn, $sqlOutward);
while ($row = $result->fetch_assoc()) $outwardData[] = $row;

$sqlInternal = "SELECT il.id AS doc_id, il.cdr_number, il.letter_title, r.regiment_name, il.status, il.priority_status,
    il.is_under_final_disposal,
    (SELECT CONCAT(u.name_with_initial, ' (', u.rank, ')') FROM internal_letters_forwarding f JOIN users u ON f.forwarded_to = u.id WHERE f.letter_id = il.id ORDER BY f.forwarded_at DESC LIMIT 1) AS last_forwarded_to,
    (SELECT forwarded_at FROM internal_letters_forwarding WHERE letter_id = il.id ORDER BY forwarded_at DESC LIMIT 1) AS last_forwarded_at,
    COALESCE(
        (SELECT d1.desk_name FROM internal_letters_forwarding f1
         JOIN users u1 ON f1.forwarded_to = u1.id
         LEFT JOIN desks d1 ON u1.desk_id = d1.id
         WHERE f1.letter_id = il.id
         ORDER BY f1.forwarded_at DESC LIMIT 1),
        (SELECT d2.desk_name FROM users u2 LEFT JOIN desks d2 ON u2.desk_id = d2.id WHERE u2.id = il.registered_by)
    ) AS last_desk_name,
    fd.status AS final_disposal_status, fdd.file_number AS final_file_number,
    GROUP_CONCAT(DISTINCT CONCAT(o.letter_ref, '|', o.cdr_no) SEPARATOR '||') AS linked_outward,
    GROUP_CONCAT(DISTINCT CONCAT(tl.tsk_cdr_number, '|', tl.title) SEPARATOR '||') AS linked_tasks
    FROM internal_letters il
    LEFT JOIN regiments r ON il.regiment_id = r.id
    LEFT JOIN internal_letters_final_disposal fd ON il.id = fd.letter_id AND fd.status = 'Completed'
    LEFT JOIN internal_letters_final_disposal_details fdd ON il.id = fdd.letter_id AND fdd.status = 'Completed'
    LEFT JOIN outward_internal oi ON il.id = oi.internal_id
    LEFT JOIN outward_letters o ON oi.outward_id = o.id AND o.is_deleted = 0
    LEFT JOIN task_replies tr ON il.id = tr.reply_internal_letter_id
    LEFT JOIN task_letters tl ON tr.task_letter_id = tl.id
    WHERE $whereInt
    GROUP BY il.id ORDER BY il.letter_date DESC, il.id DESC";
$result = safeQuery($conn, $sqlInternal);
while ($row = $result->fetch_assoc()) $internalData[] = $row;

$sqlTask = "SELECT tl.id AS doc_id, tl.letter_ref, tl.tsk_cdr_number, tl.title, tl.status, tl.priority_status,
    d.desk_name,
    GROUP_CONCAT(DISTINCT r.regiment_name SEPARATOR ', ') AS assigned_regiments,
    GROUP_CONCAT(DISTINCT CONCAT(il.cdr_number, '|', il.letter_title) SEPARATOR '||') AS replies,
    GROUP_CONCAT(DISTINCT CONCAT(o.letter_ref, '|', o.cdr_no) SEPARATOR '||') AS linked_outward
    FROM task_letters tl
    LEFT JOIN users au ON tl.assigned_by = au.id
    LEFT JOIN desks d  ON au.desk_id = d.id
    LEFT JOIN task_letter_agencies tla ON tl.id = tla.task_letter_id
    LEFT JOIN regiments r ON tla.regiment_id = r.id
    LEFT JOIN task_replies tr ON tl.id = tr.task_letter_id
    LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id AND il.is_deleted = 0
    LEFT JOIN outward_task ot ON tl.id = ot.task_id
    LEFT JOIN outward_letters o ON ot.outward_id = o.id AND o.is_deleted = 0
    WHERE $whereTsk
    GROUP BY tl.id ORDER BY tl.created_at DESC";
$result = safeQuery($conn, $sqlTask);
while ($row = $result->fetch_assoc()) $taskData[] = $row;

$reportData = [
    'outward'  => $outwardData,
    'internal' => $internalData,
    'task'     => $taskData,
];

$activeDeskName = '';
foreach ($desks as $d) if ((int)$d['id'] === $deskId) $activeDeskName = $d['desk_name'];
$hasFilter = $dateFrom || $dateTo || $deskId > 0;
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Linkage Report | IDCMS</title>

<!-- ═══════ Sinhala Web Font (works on all devices) ═══════ -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700;800&display=swap" rel="stylesheet">

<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
:root {
    --bg:#F0F2F5; --card:#FFF; --accent:#4318FF; --accent-soft:rgba(67,24,255,0.08);
    --text:#1B2559; --muted:#707EAE; --border:#DDE2EF; --sidebar-width:260px;
    /* Light sidebar */
    --sb-bg:#FFFFFF;
    --sb-text:#707EAE;
    --sb-brand:#1B2559;
    --sb-active:#4318FF;
    --sb-active-bg:rgba(67,24,255,0.08);
    --sb-hover:rgba(67,24,255,0.05);
    --sb-border:#E6EAF3;
    --sb-divider:#DDE2EF;
}
[data-theme="dark"] {
    --bg:#0B1437; --card:#111C44; --accent:#7551FF; --accent-soft:rgba(117,81,255,0.1);
    --text:#FFF; --muted:#A3AED0; --border:rgba(255,255,255,0.1); color-scheme:dark;
    /* Dark sidebar */
    --sb-bg:#0B1437;
    --sb-text:#A3AED0;
    --sb-brand:#FFFFFF;
    --sb-active:#7551FF;
    --sb-active-bg:rgba(117,81,255,0.14);
    --sb-hover:rgba(255,255,255,0.04);
    --sb-border:rgba(255,255,255,0.06);
    --sb-divider:rgba(255,255,255,0.09);
}
* { box-sizing:border-box; }
body {
    background:var(--bg);
    color:var(--text);
    display:flex;
    min-height:100vh;
    font-family:
        'Noto Sans Sinhala',
        'Plus Jakarta Sans',
        'Iskoola Pota',
        'Nirmala UI',
        'Malithi Web',
        'Segoe UI',
        system-ui,
        -apple-system,
        sans-serif;
    margin:0;
    transition:background 0.25s, color 0.25s;
}

/* ════════════════════════════════════════════════════════════
   SIDEBAR
   ════════════════════════════════════════════════════════════ */
.sidebar {
    width:var(--sidebar-width);
    background:var(--sb-bg);
    border-right:1px solid var(--sb-border);
    padding:26px 16px 22px;
    position:fixed;
    height:100vh;
    display:flex;
    flex-direction:column;
    overflow-y:auto;
    z-index:100;
    transition:background 0.25s, border-color 0.25s;
}
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:var(--sb-border); border-radius:3px; }

.brand { display:flex; align-items:center; gap:10px; padding:0 8px; }
.brand i { color:var(--sb-active); font-size:1.3rem; }
.brand span { font-weight:800; font-size:1.25rem; color:var(--sb-brand); letter-spacing:0.3px; transition:color 0.25s; }
.brand-sub { font-size:0.72rem; color:var(--sb-text); opacity:0.75; padding:0 8px; margin:2px 0 22px; font-weight:500; }

.nav-menu { display:flex; flex-direction:column; flex:1; }

.section-label {
    font-size:0.63rem; font-weight:800; color:var(--sb-text);
    text-transform:uppercase; letter-spacing:1.4px;
    padding:0 12px; margin:18px 0 6px; opacity:0.8;
}

.nav-btn {
    display:flex; align-items:center; gap:12px;
    padding:11px 12px; margin:2px 0;
    color:var(--sb-text); text-decoration:none;
    border-radius:10px; font-weight:600; font-size:0.87rem;
    background:transparent; border:none; width:100%;
    text-align:left; cursor:pointer; transition:0.18s;
    position:relative;
}
.nav-btn i:first-child { width:20px; text-align:center; font-size:0.95rem; }
.nav-btn:hover { color:var(--sb-brand); background:var(--sb-hover); }
.nav-btn.active { color:var(--sb-active); background:var(--sb-active-bg); font-weight:700; }
.nav-btn.active i:first-child { color:var(--sb-active); }
.nav-btn .chevron { margin-left:auto; width:auto; font-size:0.7rem; transition:transform 0.25s; color:inherit; opacity:0.8; }
.nav-btn.active .chevron { transform:rotate(180deg); }

.submenu {
    max-height:0; overflow:hidden;
    transition:max-height 0.3s ease;
    margin:2px 0 4px 22px;
    border-left:1px solid var(--sb-divider);
    padding-left:6px;
}
.submenu.open { max-height:400px; }
.sub-nav-btn {
    display:block; padding:9px 14px; margin:1px 0;
    color:var(--sb-text); text-decoration:none;
    font-size:0.82rem; font-weight:500;
    border-radius:8px; transition:0.18s;
}
.sub-nav-btn:hover { color:var(--sb-brand); background:var(--sb-hover); }
.sub-nav-btn.active { color:var(--sb-active); font-weight:700; background:var(--sb-active-bg); }

.nav-btn.logout {
    color:#ef4444; margin-top:auto;
    border-top:1px solid var(--sb-border);
    padding-top:14px; border-radius:0;
}
.nav-btn.logout i:first-child { color:#ef4444; }
.nav-btn.logout:hover { background:rgba(239,68,68,0.08); color:#ef4444; }

/* ════════════════════════════════════════════════════════════
   MAIN
   ════════════════════════════════════════════════════════════ */
.main { margin-left:var(--sidebar-width); width:calc(100% - var(--sidebar-width)); padding:30px; }
.card-box { background:var(--card); border:1px solid var(--border); border-radius:20px; padding:20px; margin-bottom:20px; transition:background 0.25s, border-color 0.25s; }
.nav-tabs { border-bottom:1px solid var(--border); }
.nav-tabs .nav-link { color:var(--muted); font-weight:600; border:none; }
.nav-tabs .nav-link.active { background:var(--accent-soft); color:var(--accent); border-bottom:2px solid var(--accent); }
.table { color:var(--text); border-color:var(--border); }
.table thead th { border-bottom:2px solid var(--border); color:var(--muted); text-transform:uppercase; font-size:0.7rem; letter-spacing:1px; }
.linked-items { font-size:0.85rem; }
.linked-items ul { margin:0; padding-left:1rem; }
.btn-export { background:var(--accent); border:none; border-radius:12px; padding:8px 20px; font-weight:600; transition:0.2s; color:#fff; }
.btn-export:hover { opacity:0.9; transform:translateY(-1px); color:#fff; }
.action-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:8px; font-size:0.72rem; font-weight:700; text-decoration:none; transition:0.2s; white-space:nowrap; margin:2px; }
.btn-view { background:#4318FF; color:#fff; }
.btn-view:hover { background:#3310CC; color:#fff; transform:translateY(-1px); }
.btn-pdf { background:#800000; color:#fff; }
.btn-pdf:hover { background:#a00000; color:#fff; transform:translateY(-1px); }

/* ── Theme toggle button ─────────────────────────────────── */
.btn-theme {
    width:38px; height:38px;
    display:inline-flex; align-items:center; justify-content:center;
    background:var(--card);
    border:1px solid var(--border);
    color:var(--text);
    border-radius:12px;
    cursor:pointer;
    transition:0.2s;
    font-size:0.95rem;
}
.btn-theme:hover {
    border-color:var(--accent);
    color:var(--accent);
    transform:translateY(-1px);
}

/* ── Filter bar ───────────────────────────────────────────── */
.filter-bar { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px 16px; transition:background 0.25s, border-color 0.25s; }
.filter-bar .form-label { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--muted); }
.filter-chip {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--accent-soft); color:var(--accent);
    padding:4px 10px; border-radius:20px; font-size:0.72rem; font-weight:700;
    margin-right:6px; margin-top:6px;
}
.filter-chip i { font-size:0.7rem; }
.btn-reset { background:transparent; border:1px solid var(--border); color:var(--muted); border-radius:12px; padding:8px 16px; font-weight:600; font-size:0.8rem; text-decoration:none; }
.btn-reset:hover { border-color:var(--accent); color:var(--accent); }

/* ── Dark theme form controls ────────────────────────────── */
[data-theme="dark"] .form-control, [data-theme="dark"] .form-select { background-color:#1a1f3a !important; color:#fff !important; border-color:rgba(255,255,255,0.15); }
[data-theme="dark"] .form-control::placeholder { color:#8898aa !important; }
[data-theme="dark"] .form-control::-webkit-calendar-picker-indicator { filter:invert(1); }
[data-theme="dark"] .dataTables_wrapper .dataTables_filter input, [data-theme="dark"] .dataTables_wrapper .dataTables_length select { background-color:#1a1f3a !important; color:#fff !important; border:1px solid rgba(255,255,255,0.15); border-radius:8px; padding:5px 10px; }

@media(max-width:900px) {
    .sidebar { transform:translateX(-100%); transition:transform 0.3s; }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; width:100%; padding:20px; }
}
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar">
    <div class="brand">
        <i class="fa-solid fa-shield-halved"></i>
        <span>IDCMS</span>
    </div>
    <div class="brand-sub">Report Center</div>

    <nav class="nav-menu">
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i>
            <span>Home Page</span>
        </a>

        <div class="section-label">Reports</div>

        <button type="button" class="nav-btn active nav-toggle" data-target="reportsSub">
            <i class="fa-solid fa-link"></i>
            <span>Linkage Report</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="submenu open" id="reportsSub">
            <a href="#outward"  class="sub-nav-btn tab-link active" data-tab="outward">Outward</a>
            <a href="#internal" class="sub-nav-btn tab-link" data-tab="internal">Internal</a>
            <a href="#task"     class="sub-nav-btn tab-link" data-tab="task">Tasks</a>
        </div>

        <a href="../../auth/logout.php" class="nav-btn logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Comprehensive Linkage Report</h2>
            <p class="text-muted small mb-0">Complete view of document relationships across the system</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn-theme" id="themeToggle" title="Toggle theme" type="button">
                <i class="fa-solid fa-moon" id="theme-icon"></i>
            </button>
            <button class="btn btn-export btn-sm" id="exportExcel"><i class="fa-solid fa-file-excel me-2"></i>Excel</button>
            <button class="btn btn-export btn-sm" id="exportPDF"><i class="fa-solid fa-file-pdf me-2"></i>PDF</button>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="get" class="filter-bar mb-3" autocomplete="off">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label mb-1">From Date</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label mb-1">To Date</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label mb-1">Desk</label>
                <select name="desk_id" class="form-select form-select-sm">
                    <option value="">— All Desks —</option>
                    <?php foreach ($desks as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $deskId === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['desk_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button type="submit" class="btn btn-export btn-sm">
                    <i class="fa-solid fa-filter me-1"></i> Apply
                </button>
                <?php if ($hasFilter): ?>
                    <a href="linkage_report.php" class="btn-reset ms-1">
                        <i class="fa-solid fa-xmark me-1"></i>Reset
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($hasFilter): ?>
        <div class="mt-2">
            <span class="text-muted small me-2"><i class="fa-solid fa-circle-info me-1"></i>Active filters:</span>
            <?php if ($dateFrom): ?><span class="filter-chip"><i class="fa-solid fa-calendar"></i> From <?= htmlspecialchars($dateFrom) ?></span><?php endif; ?>
            <?php if ($dateTo):   ?><span class="filter-chip"><i class="fa-solid fa-calendar"></i> To <?= htmlspecialchars($dateTo) ?></span><?php endif; ?>
            <?php if ($deskId):   ?><span class="filter-chip"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($activeDeskName ?: ('Desk #' . $deskId)) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
    </form>

    <div class="card-box">
        <ul class="nav nav-tabs mb-3" id="reportTab" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#outward" type="button"><i class="fa-solid fa-paper-plane me-2"></i>Outward <span class="badge bg-secondary ms-1"><?= count($outwardData) ?></span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#internal" type="button"><i class="fa-solid fa-envelope me-2"></i>Internal <span class="badge bg-secondary ms-1"><?= count($internalData) ?></span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#task" type="button"><i class="fa-solid fa-list-check me-2"></i>Tasks <span class="badge bg-secondary ms-1"><?= count($taskData) ?></span></button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="outward" role="tabpanel">
                <div class="table-responsive">
                    <table id="outwardTable" class="table table-hover align-middle" style="width:100%">
                        <thead><tr>
                            <th style="width:140px;">Actions</th>
                            <th>Ref</th><th>CDR</th><th>Date</th><th>Title</th>
                            <th>Desk</th>
                            <th>Status</th>
                            <th>Linked Internals</th><th>Linked Tasks</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($outwardData as $row): ?>
                            <tr>
                                <td>
                                    <a href="unified_details.php?type=outward&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-view"><i class="fa-solid fa-eye"></i> View</a>
                                    <a href="dossier_hub.php?type=outward&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                                </td>
                                <td><?= htmlspecialchars($row['letter_ref']) ?></td>
                                <td><?= htmlspecialchars($row['cdr_no'] ?? '-') ?></td>
                                <td><?= date('d M Y', strtotime($row['letter_date'])) ?></td>
                                <td lang="si"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['desk_name'] ?? '-') ?></td>
                                <td><span class="badge bg-secondary"><?= $row['status'] ?></span></td>
                                <td class="linked-items">
                                    <?php if ($row['linked_internals']): ?><ul>
                                        <?php foreach (explode('||', $row['linked_internals']) as $item): $p = explode('|', $item, 2); ?>
                                        <li lang="si"><?= htmlspecialchars($p[0] ?? '') ?></li>
                                        <?php endforeach; ?>
                                    </ul><?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="linked-items">
                                    <?php if ($row['linked_tasks']): ?><ul>
                                        <?php foreach (explode('||', $row['linked_tasks']) as $item): $p = explode('|', $item, 2); ?>
                                        <li lang="si"><?= htmlspecialchars($p[0] ?? '') ?></li>
                                        <?php endforeach; ?>
                                    </ul><?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="internal" role="tabpanel">
                <div class="table-responsive">
                    <table id="internalTable" class="table table-hover align-middle" style="width:100%">
                        <thead><tr>
                            <th style="width:140px;">Actions</th>
                            <th>CDR</th><th>Title</th><th>Regiment</th>
                            <th>Desk</th>
                            <th>Status</th><th>Priority</th>
                            <th>Last Forwarded To</th><th>Final Disposal</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($internalData as $row):
                            $pc = 'secondary';
                            if ($row['priority_status'] == 'Urgent') $pc = 'danger';
                            elseif ($row['priority_status'] == 'Immediately') $pc = 'dark';
                        ?>
                            <tr>
                                <td>
                                    <a href="unified_details.php?type=internal&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-view"><i class="fa-solid fa-eye"></i> View</a>
                                    <a href="dossier_hub.php?type=internal&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                                </td>
                                <td><?= htmlspecialchars($row['cdr_number']) ?></td>
                                <td lang="si"><?= htmlspecialchars($row['letter_title']) ?></td>
                                <td lang="si"><?= htmlspecialchars($row['regiment_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['last_desk_name'] ?? '-') ?></td>
                                <td><?= $row['status'] ?></td>
                                <td><span class="badge bg-<?= $pc ?>"><?= $row['priority_status'] ?></span></td>
                                <td lang="si"><?= $row['last_forwarded_to'] ? htmlspecialchars($row['last_forwarded_to']) . '<br><small>' . date('d M Y', strtotime($row['last_forwarded_at'])) . '</small>' : '-' ?></td>
                                <td>
                                    <?php if ($row['final_disposal_status'] == 'Completed'): ?><span class="badge bg-success">Filed</span>
                                    <?php elseif ($row['is_under_final_disposal']): ?><span class="badge bg-warning">Pending</span>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="task" role="tabpanel">
                <div class="table-responsive">
                    <table id="taskTable" class="table table-hover align-middle" style="width:100%">
                        <thead><tr>
                            <th style="width:140px;">Actions</th>
                            <th>Ref</th><th>CDR</th><th>Title</th>
                            <th>Desk</th>
                            <th>Status</th><th>Priority</th>
                            <th>Agencies</th><th>Replies</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($taskData as $row): ?>
                            <tr>
                                <td>
                                    <a href="unified_details.php?type=task&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-view"><i class="fa-solid fa-eye"></i> View</a>
                                    <a href="dossier_hub.php?type=task&id=<?= (int)$row['doc_id'] ?>" class="action-btn btn-pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                                </td>
                                <td><?= htmlspecialchars($row['letter_ref']) ?></td>
                                <td><?= htmlspecialchars($row['tsk_cdr_number']) ?></td>
                                <td lang="si"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['desk_name'] ?? '-') ?></td>
                                <td><?= $row['status'] ?></td>
                                <td><?= $row['priority_status'] ?></td>
                                <td lang="si"><?= htmlspecialchars($row['assigned_regiments'] ?? '-') ?></td>
                                <td class="linked-items">
                                    <?php if ($row['replies']): ?><ul>
                                        <?php foreach (explode('||', $row['replies']) as $item): $p = explode('|', $item, 2); ?>
                                        <li lang="si"><?= htmlspecialchars($p[0] ?? '') ?></li>
                                        <?php endforeach; ?>
                                    </ul><?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
window.REPORT_DATA = <?= json_encode($reportData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap5.min.js"></script>
<script src="../../assets/js/xlsx.full.min.js"></script>
<script src="../../assets/js/jspdf.umd.min.js"></script>
<script src="../../assets/js/jspdf.plugin.autotable.min.js"></script>
<script>
// ════════════════════════════════════════════════════════════
// THEME — applied immediately (before DOMContentLoaded) to
// prevent a flash of the wrong theme, then wired up fully.
// ════════════════════════════════════════════════════════════
(function () {
    const saved = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
})();

window.addEventListener('DOMContentLoaded', () => {
    const saved = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('theme-icon');
    if (saved === 'light' && icon) {
        icon.classList.replace('fa-moon', 'fa-sun');
    }
});

// Toggle handler
document.addEventListener('click', function (e) {
    const btn = e.target.closest('#themeToggle');
    if (!btn) return;
    const html = document.documentElement;
    const current = html.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('dispatch_theme', next);
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.classList.toggle('fa-moon', next === 'dark');
        icon.classList.toggle('fa-sun',  next === 'light');
    }
});

$(document).ready(function() {
    var outwardTable  = $('#outwardTable').DataTable({ order:[[3,'desc']], pageLength:10, columnDefs:[{orderable:false,targets:0}] });
    var internalTable = $('#internalTable').DataTable({ order:[[1,'desc']], pageLength:10, columnDefs:[{orderable:false,targets:0}] });
    var taskTable     = $('#taskTable').DataTable({ order:[[2,'desc']], pageLength:10, columnDefs:[{orderable:false,targets:0}] });

    // ── Sidebar submenu toggle ────────────────────────────
    $('.nav-toggle').on('click', function(e) {
        e.preventDefault();
        var $sub = $('#' + $(this).data('target'));
        $sub.toggleClass('open');
        $(this).toggleClass('active');
    });

    // ── Sidebar tab-link → activate Bootstrap tab ─────────
    $('.tab-link').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.tab-link').removeClass('active');
        $(this).addClass('active');
        var $btn = $('.nav-tabs .nav-link[data-bs-target="#' + tab + '"]');
        if ($btn.length) { new bootstrap.Tab($btn[0]).show(); }
    });

    $('.nav-tabs .nav-link').on('shown.bs.tab', function() {
        var target = $(this).attr('data-bs-target').replace('#','');
        $('.tab-link').removeClass('active');
        $('.tab-link[data-tab="' + target + '"]').addClass('active');
    });

    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        var $btn = $('.nav-tabs .nav-link[data-bs-target="#' + hash + '"]');
        if ($btn.length) { new bootstrap.Tab($btn[0]).show(); }
        $('.tab-link').removeClass('active');
        $('.tab-link[data-tab="' + hash + '"]').addClass('active');
    }

    // ── Helpers ──────────────────────────────────────────
    function pairsToText(str) {
        if (!str) return '';
        return String(str).split('||').map(function(item) {
            var parts = item.split('|');
            var left  = (parts[0] || '').trim();
            var right = (parts[1] || '').trim();
            return right ? (left + ' — ' + right) : left;
        }).join('\n');
    }
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function fmtDate(s, withTime) {
        if (!s) return '';
        var d = new Date(String(s).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(s);
        var out = ('0' + d.getDate()).slice(-2) + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
        if (withTime) out += ' ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
        return out;
    }
    function buildSheet(rows, columns) {
        var aoa = [columns.map(function(c) { return c.label; })];
        rows.forEach(function(row) {
            aoa.push(columns.map(function(c) {
                var v = row[c.key];
                if (v === null || v === undefined) v = '';
                if (typeof c.format === 'function') return c.format(v, row);
                return String(v);
            }));
        });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        ws['!cols'] = columns.map(function(c, idx) {
            var max = String(c.label).length;
            for (var r = 1; r < aoa.length; r++) {
                var cell = String(aoa[r][idx] == null ? '' : aoa[r][idx]);
                cell.split('\n').forEach(function(line) { if (line.length > max) max = line.length; });
            }
            return { wch: Math.min(Math.max(max + 2, 8), 60) };
        });
        ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s:{r:0,c:0}, e:{r:aoa.length-1, c:columns.length-1} }) };
        return ws;
    }

    // ── Excel export ─────────────────────────────────────
    $('#exportExcel').on('click', function() {
        var data = window.REPORT_DATA || {};
        var wb = XLSX.utils.book_new();

        var outwardCols = [
            { key:'letter_ref',       label:'Letter Ref' },
            { key:'cdr_no',           label:'CDR No' },
            { key:'letter_date',      label:'Letter Date', format:function(v){ return fmtDate(v); } },
            { key:'title',            label:'Title' },
            { key:'desk_name',        label:'Desk' },
            { key:'status',           label:'Status' },
            { key:'linked_internals', label:'Linked Internals', format:function(v){ return pairsToText(v); } },
            { key:'linked_tasks',     label:'Linked Tasks',     format:function(v){ return pairsToText(v); } }
        ];
        XLSX.utils.book_append_sheet(wb, buildSheet(data.outward || [], outwardCols), 'Outward');

        var internalCols = [
            { key:'cdr_number',            label:'CDR Number' },
            { key:'letter_title',          label:'Title' },
            { key:'regiment_name',         label:'Regiment' },
            { key:'last_desk_name',        label:'Desk' },
            { key:'status',                label:'Status' },
            { key:'priority_status',       label:'Priority' },
            { key:'last_forwarded_to',     label:'Last Forwarded To' },
            { key:'last_forwarded_at',     label:'Last Forwarded At', format:function(v){ return fmtDate(v, true); } },
            { key:'final_disposal_status', label:'Final Disposal',   format:function(v){ return v || ''; } },
            { key:'final_file_number',     label:'Final File No.' },
            { key:'linked_outward',        label:'Linked Outward',   format:function(v){ return pairsToText(v); } },
            { key:'linked_tasks',          label:'Linked Tasks',     format:function(v){ return pairsToText(v); } }
        ];
        XLSX.utils.book_append_sheet(wb, buildSheet(data.internal || [], internalCols), 'Internal');

        var taskCols = [
            { key:'letter_ref',         label:'Letter Ref' },
            { key:'tsk_cdr_number',     label:'Task CDR' },
            { key:'title',              label:'Title' },
            { key:'desk_name',          label:'Desk' },
            { key:'status',             label:'Status' },
            { key:'priority_status',    label:'Priority' },
            { key:'assigned_regiments', label:'Assigned Agencies' },
            { key:'replies',            label:'Replies',        format:function(v){ return pairsToText(v); } },
            { key:'linked_outward',     label:'Linked Outward', format:function(v){ return pairsToText(v); } }
        ];
        XLSX.utils.book_append_sheet(wb, buildSheet(data.task || [], taskCols), 'Tasks');

        var stamp = new Date().toISOString().slice(0, 10);
        XLSX.writeFile(wb, 'linkage_report_' + stamp + '.xlsx');
    });

    // ── PDF export (visible tab) ─────────────────────────
    $('#exportPDF').on('click', function() {
        var jsPDF = window.jspdf.jsPDF;
        var doc = new jsPDF('landscape', 'mm', 'a4');
        var activeTab = $('.tab-pane.active').attr('id');
        var table = activeTab === 'outward' ? $('#outwardTable') : (activeTab === 'internal' ? $('#internalTable') : $('#taskTable'));
        doc.autoTable({ html: table[0], startY:20, theme:'striped', headStyles:{fillColor:[67,24,255]}, margin:{top:20} });
        doc.save('linkage_report.pdf');
    });
});
</script>
</body>
</html>