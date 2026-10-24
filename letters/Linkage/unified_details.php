<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('VIEW_OUTGOING_LETTERS')) {
    die('Access Denied');
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'internal';
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0 || !in_array($type, ['internal', 'task', 'outward'])) {
    die('Invalid request');
}

function safePrepare($conn, $sql) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $err = htmlspecialchars($conn->error);
        $q   = htmlspecialchars($sql);
        die("<pre style='padding:20px;background:#fee;color:#900;font-family:monospace;white-space:pre-wrap;'>"
          . "<strong>SQL Prepare Failed</strong>\n\n"
          . "MySQL error: {$err}\n\n"
          . "Query:\n{$q}"
          . "</pre>");
    }
    return $stmt;
}

// ════════════════════════════════════════════════════════════
// FETCHERS
// ════════════════════════════════════════════════════════════
function fetchInternal($conn, $id) {
    $sql = "
        SELECT il.*, 
               lc.category AS category_name, r.regiment_name, r.address AS reg_address,
               i.informer_name, i.informer_code, i.category, i.contact_number,
               u_reg.name_with_initial AS reg_name, u_reg.`rank` AS reg_rank, 
               u_reg.service_number AS reg_service, u_reg.work_place AS reg_workplace,
               u_edit.name_with_initial AS edit_name, u_edit.`rank` AS edit_rank
        FROM internal_letters il
        LEFT JOIN letter_categories lc ON il.category_id = lc.id
        LEFT JOIN regiments r ON il.regiment_id = r.id
        LEFT JOIN informers i ON il.Informer_id = i.id
        LEFT JOIN users u_reg ON il.registered_by = u_reg.id
        LEFT JOIN users u_edit ON il.updated_by = u_edit.id
        WHERE il.id = ? AND il.is_deleted = 0
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function fetchTask($conn, $id) {
    $sql = "
        SELECT tl.*, u.name_with_initial AS assigned_name, u.`rank` AS assigned_rank,
               u.service_number AS assigned_service, u.work_place AS assigned_workplace
        FROM task_letters tl
        LEFT JOIN users u ON tl.assigned_by = u.id
        WHERE tl.id = ?
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function fetchOutward($conn, $id) {
    $sql = "
        SELECT o.*, 
               (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, o.receiver)) AS branch_names,
               creator.name_with_initial AS creator_name, creator.`rank` AS creator_rank, creator.service_number AS creator_service,
               approver.name_with_initial AS approver_name, approver.`rank` AS approver_rank,
               rejecter.name_with_initial AS rejecter_name, rejecter.`rank` AS rejecter_rank,
               dispatcher.name_with_initial AS dispatcher_name, dispatcher.`rank` AS dispatcher_rank
        FROM outward_letters o
        LEFT JOIN users creator ON o.created_by = creator.id
        LEFT JOIN users approver ON o.approved_by = approver.id
        LEFT JOIN users rejecter ON o.rejected_by = rejecter.id
        LEFT JOIN users dispatcher ON o.dispatched_by = dispatcher.id
        WHERE o.id = ? AND o.is_deleted = 0
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $id);
    $s->execute();
    return $s->get_result()->fetch_assoc();
}

function fetchForwardingHistory($conn, $letterId) {
    $sql = "
        SELECT f.id, f.comments, f.forwarded_at, f.seen,
               ub.name_with_initial AS by_name, ub.`rank` AS by_rank, ub.service_number AS by_service,
               ut.name_with_initial AS to_name, ut.`rank` AS to_rank, ut.service_number AS to_service,
               d.desk_name AS to_desk
        FROM internal_letters_forwarding f
        JOIN users ub ON f.forwarded_by = ub.id
        JOIN users ut ON f.forwarded_to = ut.id
        LEFT JOIN desks d ON ut.desk_id = d.id
        WHERE f.letter_id = ?
        ORDER BY f.forwarded_at ASC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $letterId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchOutwardForInternal($conn, $internalId) {
    $sql = "
        SELECT o.id, o.letter_ref, o.cdr_no, o.letter_date, o.title, o.status, o.dispatch_method,
               eb.branch_name, u.name_with_initial AS creator_name
        FROM outward_letters o
        JOIN outward_internal oi ON o.id = oi.outward_id
        LEFT JOIN external_branches eb ON o.receiver = eb.id
        LEFT JOIN users u ON o.created_by = u.id
        WHERE oi.internal_id = ? AND o.is_deleted = 0
        ORDER BY o.letter_date DESC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $internalId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchDisposalForInternal($conn, $internalId) {
    $sql = "
        SELECT d.id, d.remarks, d.status, d.forwarded_at, d.disposed_at,
               ds.desk_name, u.name_with_initial AS by_name, u.`rank` AS by_rank
        FROM internal_letters_final_disposal d
        LEFT JOIN desks ds ON d.forwarded_to = ds.id
        LEFT JOIN users u ON d.forwarded_by = u.id
        WHERE d.letter_id = ?
        ORDER BY d.forwarded_at ASC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $internalId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchArchiveForInternal($conn, $internalId) {
    $sql = "
        SELECT fd.id, fd.file_number, fd.file_location, fd.status, fd.disposed_at,
               u.name_with_initial AS by_name, u.`rank` AS by_rank
        FROM internal_letters_final_disposal_details fd
        LEFT JOIN users u ON fd.disposed_by = u.id
        WHERE fd.letter_id = ?
        ORDER BY fd.disposed_at ASC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $internalId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchAgenciesForTask($conn, $taskId) {
    $sql = "
        SELECT r.regiment_name, r.address, tla.assigned_at
        FROM task_letter_agencies tla
        JOIN regiments r ON tla.regiment_id = r.id
        WHERE tla.task_letter_id = ?
        ORDER BY r.regiment_name
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $taskId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchRepliesForTask($conn, $taskId) {
    $sql = "
        SELECT tr.status AS reply_status, tr.replied_at,
               r.regiment_name,
               u.name_with_initial AS replied_by_name, u.`rank` AS replied_by_rank,
               il.id AS internal_letter_id, il.cdr_number, il.letter_reference, il.letter_title,
               il.letter_date, il.priority_status, il.attachment
        FROM task_replies tr
        JOIN regiments r ON tr.regiment_id = r.id
        LEFT JOIN users u ON tr.replied_by = u.id
        LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id
        WHERE tr.task_letter_id = ?
        ORDER BY tr.replied_at DESC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $taskId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchInternalLinksForTask($conn, $taskId) {
    $sql = "
        SELECT il.id, il.cdr_number, il.letter_reference, il.letter_title,
               il.status AS letter_status, il.letter_date, il.priority_status, il.attachment
        FROM task_internal_links til
        JOIN internal_letters il ON til.internal_letter_id = il.id
        WHERE til.task_id = ? AND il.is_deleted = 0
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $taskId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchTasksLinkedToInternal($conn, $internalId) {
    $sql = "
        SELECT DISTINCT tl.id, tl.tsk_cdr_number, tl.title, tl.status, tl.priority_status, tl.letter_date
        FROM task_letters tl
        LEFT JOIN task_internal_links til ON tl.id = til.task_id
        LEFT JOIN task_replies tr ON tl.id = tr.task_letter_id
        WHERE til.internal_letter_id = ? OR tr.reply_internal_letter_id = ?
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("ii", $internalId, $internalId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchOutwardLinkedToTask($conn, $taskId) {
    $sql = "
        SELECT DISTINCT ol.id, ol.letter_ref, ol.title, ol.status
        FROM outward_letters ol
        JOIN outward_task ot ON ol.id = ot.outward_id
        WHERE ot.task_id = ? AND ol.is_deleted = 0
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $taskId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchInternalLinkedToOutward($conn, $outwardId) {
    $sql = "
        SELECT DISTINCT il.id, il.cdr_number, il.letter_title, il.status, il.letter_date
        FROM internal_letters il
        JOIN outward_internal oi ON il.id = oi.internal_id
        WHERE oi.outward_id = ? AND il.is_deleted = 0
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $outwardId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ────────────────────────────────────────────────────────────
   Fetch Task Letters linked to an Outward Letter
   Uses the `outward_task` pivot table (outward_id, task_id)
   ──────────────────────────────────────────────────────────── */
function fetchTasksLinkedToOutward($conn, $outwardId) {
    $sql = "
        SELECT DISTINCT tl.id, tl.tsk_cdr_number, tl.title, tl.status,
                        tl.priority_status, tl.letter_date
        FROM task_letters tl
        JOIN outward_task ot ON tl.id = ot.task_id
        WHERE ot.outward_id = ?
        ORDER BY tl.letter_date DESC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param("i", $outwardId);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ────────────────────────────────────────────────────────────
   NEW: Reply documents for a MAIN TASK (as section-4 items)
   Returns internal letters that came in as replies, plus
   the task they replied to.
   ──────────────────────────────────────────────────────────── */
function fetchReplyDocsForTasks($conn, $taskIds) {
    if (empty($taskIds)) return [];
    $ph    = implode(',', array_fill(0, count($taskIds), '?'));
    $types = str_repeat('i', count($taskIds));
    $sql   = "
        SELECT tr.status AS reply_status, tr.replied_at,
               r.regiment_name,
               tl.id AS task_id, tl.tsk_cdr_number, tl.title AS task_title,
               il.id AS internal_letter_id, il.cdr_number, il.letter_reference,
               il.letter_title, il.letter_date, il.priority_status, il.attachment
        FROM task_replies tr
        JOIN regiments r ON tr.regiment_id = r.id
        JOIN task_letters tl ON tr.task_letter_id = tl.id
        LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id
        WHERE tr.task_letter_id IN ($ph)
        ORDER BY tr.replied_at ASC
    ";
    $s = safePrepare($conn, $sql);
    $s->bind_param($types, ...$taskIds);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ════════════════════════════════════════════════════════════
// LOAD MAIN RECORD
// ════════════════════════════════════════════════════════════
$mainRecord = null;
if ($type === 'internal') $mainRecord = fetchInternal($conn, $id);
elseif ($type === 'task') $mainRecord = fetchTask($conn, $id);
else $mainRecord = fetchOutward($conn, $id);

if (!$mainRecord) die('Record not found');

$mainRef   = $type === 'internal' ? $mainRecord['cdr_number']
           : ($type === 'task' ? $mainRecord['tsk_cdr_number'] : $mainRecord['letter_ref']);
$mainTitle = $mainRecord['letter_title'] ?? $mainRecord['title'] ?? 'N/A';
$mainDate  = $mainRecord['letter_date'];

// ────────────────────────────────────────────────────────────
// PRE-COMPUTE: reply internal IDs across the whole chain
// (so we can keep "reply" letters out of "source")
// ────────────────────────────────────────────────────────────
$replyInternalIds = [];
$tasksInChainIds  = [];   // task IDs related to the main (used for reply section)

// ── Internal main ──
$forwarding      = $type === 'internal' ? fetchForwardingHistory($conn, $id) : [];
$disposals       = $type === 'internal' ? fetchDisposalForInternal($conn, $id) : [];
$archives        = $type === 'internal' ? fetchArchiveForInternal($conn, $id) : [];
$linkedOuts      = $type === 'internal' ? fetchOutwardForInternal($conn, $id) : [];
$linkedTasks     = $type === 'internal' ? fetchTasksLinkedToInternal($conn, $id) : [];

// ── Task main ──
$agencies        = $type === 'task' ? fetchAgenciesForTask($conn, $id) : [];
$replies         = $type === 'task' ? fetchRepliesForTask($conn, $id) : [];
$taskSourcesRaw  = $type === 'task' ? fetchInternalLinksForTask($conn, $id) : [];
$taskOuts        = $type === 'task' ? fetchOutwardLinkedToTask($conn, $id) : [];

// ── Outward main ──
$linkedInternalsRaw = $type === 'outward' ? fetchInternalLinkedToOutward($conn, $id) : [];
$linkedTasksFromOutward = $type === 'outward' ? fetchTasksLinkedToOutward($conn, $id) : [];

// ── Build replyInternalIds ──
if ($type === 'task') {
    $tasksInChainIds = [$id];
} elseif ($type === 'internal') {
    foreach ($linkedTasks as $t) $tasksInChainIds[] = (int)$t['id'];
} elseif ($type === 'outward') {
    foreach ($linkedTasksFromOutward as $t) $tasksInChainIds[] = (int)$t['id'];
}

foreach ($tasksInChainIds as $tid) {
    foreach (fetchRepliesForTask($conn, $tid) as $r) {
        if (!empty($r['internal_letter_id'])) {
            $replyInternalIds[(int)$r['internal_letter_id']] = true;
        }
    }
}

// ── Filter: SOURCE lists must exclude replies ──
$taskSources = array_values(array_filter(
    $taskSourcesRaw,
    fn($s) => !isset($replyInternalIds[(int)$s['id']])
));

$linkedInternals = array_values(array_filter(
    $linkedInternalsRaw,
    fn($s) => !isset($replyInternalIds[(int)$s['id']])
));

// ── REPLY docs for section 4 (task / outward / internal) ──
// For task main: its own replies
// For outward main: replies across all linked tasks
// For internal main: replies across all linked tasks
if ($type === 'task') {
    $replyDocs = [];
    foreach ($replies as $r) {
        if (!empty($r['internal_letter_id'])) {
            $replyDocs[] = [
                'internal_letter_id' => $r['internal_letter_id'],
                'cdr_number'         => $r['cdr_number'] ?? '',
                'letter_reference'   => $r['letter_reference'] ?? '',
                'letter_title'       => $r['letter_title'] ?? '',
                'letter_date'        => $r['letter_date'] ?? '',
                'regiment_name'      => $r['regiment_name'] ?? '',
                'reply_status'       => $r['reply_status'] ?? '',
                'replied_at'         => $r['replied_at'] ?? '',
                'task_id'            => $id,
                'tsk_cdr_number'     => $mainRecord['tsk_cdr_number'] ?? '',
                'task_title'         => $mainRecord['title'] ?? '',
            ];
        }
    }
} else {
    $replyDocs = fetchReplyDocsForTasks($conn, $tasksInChainIds);
}

$typeConfig = [
    'internal' => ['label' => 'Internal Letter', 'color' => '#4318FF', 'icon' => 'fa-envelope-open-text'],
    'task'     => ['label' => 'Task Letter',     'color' => '#f59e0b', 'icon' => 'fa-list-check'],
    'outward'  => ['label' => 'Outward Letter',  'color' => '#10b981', 'icon' => 'fa-paper-plane'],
];
$cfg = $typeConfig[$type];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($mainRef) ?> — <?= $cfg['label'] ?> | IDCMS</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg:#F0F2F5; --card:#FFF; --text:#1B2559; --muted:#707EAE; --border:#DDE2EF;
    --accent:<?= $cfg['color'] ?>; --accent-soft:<?= $cfg['color'] ?>20;
    --sidebar-width:270px;
    --sb-bg:#0B1437; --sb-text:#A3AED0; --sb-brand:#FFFFFF;
    --sb-active:#7551FF; --sb-active-bg:rgba(117,81,255,0.14);
    --sb-hover:rgba(255,255,255,0.04); --sb-border:rgba(255,255,255,0.06);
    --sec-main:#4318FF; --sec-source:#29417A; --sec-task:#B45F06; --sec-reply:#107841;
}
[data-theme="dark"] {
    --bg:#0B1437; --card:#111C44; --text:#FFF; --muted:#A3AED0; --border:rgba(255,255,255,0.1);
    color-scheme:dark;
}
* { box-sizing:border-box; margin:0; padding:0; }
body {
    background:var(--bg); color:var(--text); display:flex; min-height:100vh;
    font-family:'Iskoola Pota','Noto Sans Sinhala','Segoe UI',system-ui,sans-serif;
    transition:background 0.25s, color 0.25s;
}

/* SIDEBAR */
.sidebar {
    width:var(--sidebar-width);
    background:var(--sb-bg);
    border-right:1px solid var(--sb-border);
    padding:26px 16px 22px;
    position:fixed; height:100vh;
    display:flex; flex-direction:column;
    overflow-y:auto; z-index:100;
}
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:3px; }
.brand { display:flex; align-items:center; gap:10px; padding:0 8px; margin-bottom:26px; }
.brand i { color:var(--sb-active); font-size:1.35rem; }
.brand span { font-weight:800; font-size:1.25rem; color:var(--sb-brand); letter-spacing:0.3px; }
.nav-menu { display:flex; flex-direction:column; flex:1; }
.section-label {
    font-size:0.65rem; font-weight:800; color:var(--sb-text);
    text-transform:uppercase; letter-spacing:1.4px;
    padding:0 12px; margin:20px 0 8px; opacity:0.65;
}
.nav-btn {
    display:flex; align-items:center; gap:12px;
    padding:11px 13px; margin:2px 0;
    color:var(--sb-text); text-decoration:none;
    border-radius:10px; font-weight:600; font-size:0.88rem;
    background:transparent; border:none; width:100%;
    text-align:left; cursor:pointer; transition:0.18s; position:relative;
}
.nav-btn i:first-child { width:20px; text-align:center; font-size:0.95rem; }
.nav-btn .chevron { margin-left:auto; font-size:0.72rem; transition:transform 0.25s; opacity:0.8; }
.nav-btn:hover { color:#fff; background:var(--sb-hover); }
.nav-btn.active { color:var(--sb-active); background:var(--sb-active-bg); font-weight:700; }
.nav-btn.active i:first-child { color:var(--sb-active); }
.nav-btn.active .chevron { transform:rotate(180deg); }
.submenu {
    max-height:0; overflow:hidden;
    transition:max-height 0.3s ease;
    margin:2px 0 4px 22px; padding-left:8px;
    border-left:1px solid rgba(255,255,255,0.09);
}
.submenu.open { max-height:500px; }
.sub-nav-btn {
    display:block; padding:9px 14px; margin:1px 0;
    color:var(--sb-text); text-decoration:none;
    font-size:0.82rem; font-weight:500;
    border-radius:8px; transition:0.18s;
}
.sub-nav-btn:hover { color:#fff; background:var(--sb-hover); }
.sub-nav-btn.active-sub { color:var(--sb-active); font-weight:700; background:var(--sb-active-bg); }
.nav-btn.logout {
    color:#ef4444; margin-top:auto;
    border-top:1px solid var(--sb-border);
    padding-top:14px; border-radius:0; padding-left:13px;
}
.nav-btn.logout i:first-child { color:#ef4444; }
.nav-btn.logout:hover { background:rgba(239,68,68,0.08); color:#ef4444; }

/* MAIN */
.main { margin-left:var(--sidebar-width); width:calc(100% - var(--sidebar-width)); padding:30px; }

/* HERO */
.hero {
    background:linear-gradient(135deg, var(--accent) 0%, rgba(0,0,0,0.4) 100%);
    border-radius:20px; padding:30px 35px; color:#fff; margin-bottom:25px;
    position:relative; overflow:hidden;
}
.hero::before {
    content:''; position:absolute; right:-50px; top:-50px;
    width:220px; height:220px; background:rgba(255,255,255,0.08); border-radius:50%;
}
.hero-badge {
    display:inline-block; background:rgba(255,255,255,0.2);
    backdrop-filter:blur(10px); padding:5px 14px; border-radius:20px;
    font-size:0.72rem; font-weight:800; letter-spacing:1px; text-transform:uppercase;
}
.hero h1 { font-size:1.7rem; font-weight:800; margin:10px 0 6px; }
.hero .sub { font-size:0.95rem; opacity:0.95; max-width:800px; }
.hero .meta { display:flex; gap:22px; flex-wrap:wrap; margin-top:14px; font-size:0.82rem; opacity:0.9; }
.hero .meta div { display:flex; align-items:center; gap:7px; }
.hero-btns { margin-top:22px; display:flex; gap:10px; flex-wrap:wrap; }
.btn-hero {
    display:inline-flex; align-items:center; gap:8px; padding:9px 18px;
    border-radius:10px; font-weight:700; font-size:0.83rem; text-decoration:none;
    transition:0.2s; border:none; cursor:pointer;
}
.btn-hero-primary { background:#fff; color:var(--accent); }
.btn-hero-primary:hover { background:#f0f0f0; color:var(--accent); transform:translateY(-2px); }
.btn-hero-ghost { background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); }
.btn-hero-ghost:hover { background:rgba(255,255,255,0.25); color:#fff; }

/* CARDS */
.card {
    background:var(--card); border:1px solid var(--border); border-radius:16px;
    padding:22px; margin-bottom:20px;
    transition:background 0.25s, border-color 0.25s;
}
.card-title {
    font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:1.2px;
    color:var(--muted); margin-bottom:16px; padding-bottom:12px;
    border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px;
}
.card-title i { color:var(--accent); font-size:0.85rem; }

/* SECTION BANNER */
.section-banner {
    display:flex; align-items:center; gap:12px;
    padding:14px 20px; border-radius:14px;
    color:#fff; font-weight:800; font-size:0.95rem;
    letter-spacing:1px; text-transform:uppercase;
    margin: 26px 0 16px;
    box-shadow:0 6px 18px rgba(0,0,0,0.08);
}
.section-banner .num {
    width:32px; height:32px; border-radius:9px;
    background:rgba(255,255,255,0.22);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; font-weight:900;
}
.section-banner .cnt {
    margin-left:auto; font-size:0.78rem;
    background:rgba(255,255,255,0.22);
    padding:4px 12px; border-radius:20px;
    letter-spacing:0.5px;
}
.sb-main   { background:linear-gradient(135deg, var(--sec-main) 0%, #7551FF 100%); }
.sb-source { background:linear-gradient(135deg, var(--sec-source) 0%, #4A6FA5 100%); }
.sb-task   { background:linear-gradient(135deg, var(--sec-task) 0%, #D97706 100%); }
.sb-reply  { background:linear-gradient(135deg, var(--sec-reply) 0%, #10b981 100%); }

/* INFO GRID */
.info-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:14px; }
.info-item { padding:6px 0; }
.info-label {
    font-size:0.68rem; color:var(--muted); font-weight:700;
    text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;
}
.info-value {
    font-size:0.92rem; font-weight:600; color:var(--text);
    word-break:break-word; line-height:1.4;
}
.info-value.mono { font-family:'Courier New',monospace; color:var(--accent); font-size:0.88rem; }
.info-value.muted { color:var(--muted); font-weight:400; font-size:0.85rem; }

/* BADGES */
.badge {
    display:inline-block; padding:4px 12px; border-radius:20px;
    font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.3px;
}
.b-priority-Normal { background:rgba(107,114,128,0.15); color:#6b7280; }
.b-priority-Urgent { background:rgba(239,68,68,0.15); color:#ef4444; }
.b-priority-Immediately { background:rgba(17,24,39,0.9); color:#fff; }
.b-priority-High { background:rgba(239,68,68,0.15); color:#ef4444; }
.b-priority-Medium { background:rgba(245,158,11,0.15); color:#f59e0b; }
.b-priority-Low { background:rgba(16,185,129,0.15); color:#10b981; }
.b-status-Pending { background:rgba(245,158,11,0.15); color:#f59e0b; }
.b-status-Completed { background:rgba(16,185,129,0.15); color:#10b981; }
.b-status-completed { background:rgba(16,185,129,0.15); color:#10b981; }
.b-status-pending { background:rgba(245,158,11,0.15); color:#f59e0b; }
.b-status-Forwarded { background:rgba(59,130,246,0.15); color:#3b82f6; }
.b-status-NotForwarded { background:rgba(107,114,128,0.15); color:#6b7280; }
.b-status-PartiallyReplied { background:rgba(59,130,246,0.15); color:#3b82f6; }
.b-sec { background:rgba(139,92,246,0.15); color:#8b5cf6; }
.b-approved { background:rgba(16,185,129,0.15); color:#10b981; }
.b-rejected { background:rgba(239,68,68,0.15); color:#ef4444; }

/* TABLES */
.table-wrap { overflow-x:auto; border-radius:12px; border:1px solid var(--border); }
.tbl { width:100%; border-collapse:collapse; font-size:0.83rem; }
.tbl thead th {
    background:var(--bg); color:var(--muted); font-weight:800;
    text-transform:uppercase; font-size:0.68rem; letter-spacing:1px;
    padding:11px 14px; text-align:left; border-bottom:1px solid var(--border);
}
.tbl td {
    padding:12px 14px; border-bottom:1px solid var(--border);
    color:var(--text); vertical-align:middle;
}
.tbl tr:last-child td { border-bottom:none; }
.tbl tr:hover td { background:var(--accent-soft); }

/* TIMELINE */
.timeline { position:relative; padding-left:28px; }
.timeline::before {
    content:''; position:absolute; left:8px; top:8px; bottom:8px;
    width:2px; background:var(--border);
}
.tl-item { position:relative; padding-bottom:20px; }
.tl-item::before {
    content:''; position:absolute; left:-26px; top:6px;
    width:12px; height:12px; border-radius:50%;
    background:var(--accent); border:3px solid var(--card);
    box-shadow:0 0 0 2px var(--accent);
}
.tl-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px; flex-wrap:wrap; gap:6px; }
.tl-title { font-weight:700; font-size:0.87rem; }
.tl-date { font-size:0.72rem; color:var(--muted); font-family:'Courier New',monospace; }
.tl-body {
    font-size:0.82rem; color:var(--muted); line-height:1.5;
    background:var(--bg); padding:10px 13px; border-radius:10px; margin-top:6px;
}

/* LINK CARDS */
.links-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:10px; }
.link-item {
    display:flex; align-items:center; gap:12px; padding:12px 14px;
    background:var(--bg); border-radius:11px; text-decoration:none; color:var(--text);
    transition:0.2s; border-left:3px solid var(--accent);
}
.link-item:hover { background:var(--accent-soft); color:var(--accent); transform:translateX(4px); }
.link-item .icon {
    width:36px; height:36px; border-radius:9px; background:var(--accent-soft);
    display:flex; align-items:center; justify-content:center;
    color:var(--accent); flex-shrink:0;
}
.link-item .body { flex:1; }
.link-item .ref { font-weight:700; font-size:0.83rem; color:var(--accent); }
.link-item .ttl { font-size:0.76rem; color:var(--muted); margin-top:2px; }
.link-item .arr { color:var(--muted); }

/* EMPTY */
.empty {
    text-align:center; padding:30px 20px; color:var(--muted);
    background:var(--bg); border-radius:12px;
}
.empty i { font-size:2rem; opacity:0.4; margin-bottom:10px; display:block; }

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

    <nav class="nav-menu">
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i>
            <span>Home Page</span>
        </a>

        <div class="section-label">Dispatching</div>

        <button type="button" class="nav-btn active nav-toggle" data-target="internalSub">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>Internal Letters</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="submenu open" id="internalSub">
            <a href="../../letters/inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
            <a href="../../letters/inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
            <a href="../../letters/inward/inbox.php" class="sub-nav-btn">My Inbox</a>
            <a href="unified_details.php?type=internal&id=63" class="sub-nav-btn active-sub">Internal Details</a>
        </div>

        <button type="button" class="nav-btn nav-toggle" data-target="taskSub">
            <i class="fa-solid fa-list-check"></i>
            <span>Task Letters</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="submenu" id="taskSub">
            <a href="../../letters/tasking/task_letters.php" class="sub-nav-btn">Add Task</a>
            <a href="../../letters/tasking/task_list.php" class="sub-nav-btn">View Tasks</a>
            <a href="unified_details.php?type=task&id=13" class="sub-nav-btn">Task Details</a>
        </div>

        <button type="button" class="nav-btn nav-toggle" data-target="outwardSub">
            <i class="fa-solid fa-paper-plane"></i>
            <span>Outward Letters</span>
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="submenu" id="outwardSub">
            <a href="../../letters/outward/add_outward.php" class="sub-nav-btn">Add Outward</a>
            <a href="../../letters/outward/outward_list.php" class="sub-nav-btn">View Outward</a>
            <a href="unified_details.php?type=outward&id=34" class="sub-nav-btn">Outward Details</a>
        </div>

        <div class="section-label">Records</div>

        <a href="linkage_report.php" class="nav-btn">
            <i class="fa-solid fa-diagram-project"></i>
            <span>Linkage Report</span>
        </a>
        <a href="dossier_hub.php?type=<?= $type ?>&id=<?= (int)$id ?>" class="nav-btn">
            <i class="fa-solid fa-folder-open"></i>
            <span>Dossier Hub</span>
        </a>

        <a href="../../auth/logout.php" class="nav-btn logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main">

    <!-- ═══════════════ HERO ═══════════════ -->
    <div class="hero">
        <span class="hero-badge"><i class="fa-solid <?= $cfg['icon'] ?> me-2"></i><?= $cfg['label'] ?> — Full Details</span>
        <h1><?= htmlspecialchars($mainRef) ?></h1>
        <div class="sub"><?= htmlspecialchars($mainTitle) ?></div>
        <div class="meta">
            <div><i class="fa-solid fa-calendar"></i> <?= date('d M Y', strtotime($mainDate)) ?></div>
            <div><i class="fa-solid fa-hashtag"></i> Record #<?= (int)$id ?></div>
            <?php if ($type === 'internal'): ?>
                <div><i class="fa-solid fa-shield"></i> <?= htmlspecialchars($mainRecord['security_classification'] ?? '') ?></div>
                <div><i class="fa-solid fa-flag"></i> <?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?></div>
            <?php elseif ($type === 'task'): ?>
                <div><i class="fa-solid fa-flag"></i> <?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?></div>
                <div><i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars($mainRecord['status'] ?? '') ?></div>
            <?php else: ?>
                <div><i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars($mainRecord['status'] ?? '') ?></div>
                <div><i class="fa-solid fa-truck"></i> <?= htmlspecialchars($mainRecord['dispatch_method'] ?? '') ?></div>
            <?php endif; ?>
        </div>
        <div class="hero-btns">
            <a href="dossier_hub.php?type=<?= $type ?>&id=<?= (int)$id ?>" class="btn-hero btn-hero-primary">
                <i class="fa-solid fa-file-pdf"></i> Download Dossier
            </a>
            <button onclick="window.print()" class="btn-hero btn-hero-ghost">
                <i class="fa-solid fa-print"></i> Print
            </button>
            <a href="linkage_report.php" class="btn-hero btn-hero-ghost">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 1 — MAIN DOCUMENT
         ═══════════════════════════════════════════════════════ -->
    <div class="section-banner sb-main">
        <div class="num">1</div>
        <i class="fa-solid fa-file-lines"></i>
        <span>Main Document</span>
        <span class="cnt"><?= strtoupper($type) ?></span>
    </div>

    <?php if ($type === 'internal'): ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-circle-info"></i> Basic Information</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">CDR Number</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['cdr_number'] ?? '') ?></div></div>
            <div class="info-item"><div class="info-label">Letter Reference</div><div class="info-value"><?= htmlspecialchars($mainRecord['letter_reference'] ?: '—') ?></div></div>
            <div class="info-item"><div class="info-label">Letter Date</div><div class="info-value"><?= date('d M Y', strtotime($mainRecord['letter_date'])) ?></div></div>
            <div class="info-item"><div class="info-label">Regiment</div><div class="info-value"><?= htmlspecialchars($mainRecord['regiment_name'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Category</div><div class="info-value"><?= htmlspecialchars($mainRecord['category_name'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Security</div><div class="info-value"><span class="badge b-sec"><?= htmlspecialchars($mainRecord['security_classification'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Priority</div><div class="info-value"><span class="badge b-priority-<?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?>"><?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="badge b-status-<?= str_replace(' ','', $mainRecord['status'] ?? '') ?>"><?= htmlspecialchars($mainRecord['status'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Under Final Disposal</div><div class="info-value"><?= !empty($mainRecord['is_under_final_disposal']) ? 'Yes' : 'No' ?></div></div>
        </div>
        <div class="info-item" style="margin-top:14px;">
            <div class="info-label">Title</div>
            <div class="info-value"><?= htmlspecialchars($mainRecord['letter_title'] ?? '') ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-user-secret"></i> Informer Details</div>
        <?php if (!empty($mainRecord['informer_name'])): ?>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Informer Name</div><div class="info-value"><?= htmlspecialchars($mainRecord['informer_name']) ?></div></div>
            <div class="info-item"><div class="info-label">Code</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['informer_code'] ?? '') ?></div></div>
            <div class="info-item"><div class="info-label">Category</div><div class="info-value"><?= htmlspecialchars($mainRecord['category'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Contact</div><div class="info-value"><?= htmlspecialchars($mainRecord['contact_number'] ?? '—') ?></div></div>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fa-solid fa-user-slash"></i>No informer linked to this letter</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-user-pen"></i> Registration &amp; Metadata</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Record ID</div><div class="info-value mono">#<?= (int)$mainRecord['id'] ?></div></div>
            <div class="info-item"><div class="info-label">Registered By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['reg_rank'] ?? '').' '.($mainRecord['reg_name'] ?? ''))) ?></div></div>
            <div class="info-item"><div class="info-label">Service Number</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['reg_service'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Workplace</div><div class="info-value"><?= htmlspecialchars($mainRecord['reg_workplace'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Registered At</div><div class="info-value"><?= !empty($mainRecord['created_at']) ? date('d M Y H:i', strtotime($mainRecord['created_at'])) : '—' ?></div></div>
            <div class="info-item"><div class="info-label">Last Edited By</div><div class="info-value"><?= !empty($mainRecord['edit_name']) ? htmlspecialchars(trim(($mainRecord['edit_rank'] ?? '').' '.$mainRecord['edit_name'])) : 'Not edited' ?></div></div>
            <div class="info-item"><div class="info-label">Last Edited At</div><div class="info-value"><?= !empty($mainRecord['updated_at']) ? date('d M Y H:i', strtotime($mainRecord['updated_at'])) : '—' ?></div></div>
        </div>
    </div>

    <?php if (!empty($mainRecord['about_letter']) || !empty($mainRecord['comments'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-align-left"></i> Description</div>
        <?php if (!empty($mainRecord['about_letter'])): ?>
        <div class="info-item" style="margin-bottom:16px;">
            <div class="info-label">About Letter</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['about_letter'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($mainRecord['comments'])): ?>
        <div class="info-item">
            <div class="info-label">Comments</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['comments'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Minute Trail / Forwarding History (<?= count($forwarding) ?>)</div>
        <?php if (!empty($forwarding)): ?>
        <div class="timeline">
            <?php foreach ($forwarding as $i => $f): ?>
            <div class="tl-item">
                <div class="tl-head">
                    <div class="tl-title">
                        <span style="color:var(--accent);font-weight:800;">#<?= $i+1 ?></span>
                        <?= htmlspecialchars(trim(($f['by_rank'] ?? '').' '.($f['by_name'] ?? ''))) ?>
                        <i class="fa-solid fa-arrow-right-long" style="color:var(--muted);margin:0 8px;font-size:0.75rem;"></i>
                        <?= htmlspecialchars(trim(($f['to_rank'] ?? '').' '.($f['to_name'] ?? ''))) ?>
                    </div>
                    <div class="tl-date"><?= date('d M Y H:i', strtotime($f['forwarded_at'])) ?></div>
                </div>
                <div style="font-size:0.76rem;color:var(--muted);display:flex;gap:14px;flex-wrap:wrap;">
                    <?php if (!empty($f['to_desk'])): ?><span><i class="fa-solid fa-building me-1"></i><?= htmlspecialchars($f['to_desk']) ?></span><?php endif; ?>
                    <span><i class="fa-solid fa-circle-check me-1"></i><?= !empty($f['seen']) ? 'Seen' : 'Not seen' ?></span>
                </div>
                <?php if (!empty($f['comments'])): ?>
                <div class="tl-body"><?= nl2br(htmlspecialchars($f['comments'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fa-solid fa-inbox"></i>No forwarding records yet</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-box-archive"></i> Final Disposal Records (<?= count($disposals) ?>)</div>
        <?php if (!empty($disposals)): ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Target Desk</th><th>Authorized By</th><th>Status</th><th>Forwarded</th><th>Remarks</th></tr></thead>
                <tbody>
                    <?php foreach ($disposals as $d): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($d['desk_name'] ?? '—') ?></strong></td>
                        <td><?= htmlspecialchars(trim(($d['by_rank'] ?? '').' '.($d['by_name'] ?? ''))) ?></td>
                        <td><span class="badge b-status-<?= htmlspecialchars($d['status'] ?? '') ?>"><?= htmlspecialchars($d['status'] ?? '') ?></span></td>
                        <td><?= !empty($d['forwarded_at']) ? date('d M Y H:i', strtotime($d['forwarded_at'])) : '—' ?></td>
                        <td><?= htmlspecialchars($d['remarks'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fa-solid fa-folder-open"></i>No final disposal records</div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-folder-closed"></i> Physical Archive Records (<?= count($archives) ?>)</div>
        <?php if (!empty($archives)): ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>File Number</th><th>Location</th><th>Status</th><th>Disposed By</th><th>Disposed At</th></tr></thead>
                <tbody>
                    <?php foreach ($archives as $a): ?>
                    <tr>
                        <td><span class="info-value mono"><?= htmlspecialchars($a['file_number'] ?? '—') ?></span></td>
                        <td><?= htmlspecialchars($a['file_location'] ?? '—') ?></td>
                        <td><span class="badge b-status-<?= htmlspecialchars($a['status'] ?? '') ?>"><?= htmlspecialchars($a['status'] ?? '') ?></span></td>
                        <td><?= htmlspecialchars(trim(($a['by_rank'] ?? '').' '.($a['by_name'] ?? ''))) ?></td>
                        <td><?= !empty($a['disposed_at']) ? date('d M Y H:i', strtotime($a['disposed_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fa-solid fa-box"></i>Not yet archived to any physical file</div>
        <?php endif; ?>
    </div>

    <?php elseif ($type === 'task'): ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-circle-info"></i> Task Information</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Task CDR</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['tsk_cdr_number'] ?? '') ?></div></div>
            <div class="info-item"><div class="info-label">Reference</div><div class="info-value"><?= htmlspecialchars($mainRecord['letter_ref'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Letter Date</div><div class="info-value"><?= date('d M Y', strtotime($mainRecord['letter_date'])) ?></div></div>
            <div class="info-item"><div class="info-label">Request Date</div><div class="info-value"><?= !empty($mainRecord['request_date']) ? date('d M Y', strtotime($mainRecord['request_date'])) : '—' ?></div></div>
            <div class="info-item"><div class="info-label">Priority</div><div class="info-value"><span class="badge b-priority-<?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?>"><?= htmlspecialchars($mainRecord['priority_status'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="badge b-status-<?= str_replace(' ','', $mainRecord['status'] ?? '') ?>"><?= htmlspecialchars($mainRecord['status'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Assigned By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['assigned_rank'] ?? '').' '.($mainRecord['assigned_name'] ?? 'System'))) ?></div></div>
            <div class="info-item"><div class="info-label">Service Number</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['assigned_service'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Created At</div><div class="info-value"><?= !empty($mainRecord['created_at']) ? date('d M Y H:i', strtotime($mainRecord['created_at'])) : '—' ?></div></div>
        </div>
        <div class="info-item" style="margin-top:14px;">
            <div class="info-label">Title</div>
            <div class="info-value"><?= htmlspecialchars($mainRecord['title'] ?? '') ?></div>
        </div>
    </div>

    <?php if (!empty($mainRecord['description']) || !empty($mainRecord['about_letter']) || !empty($mainRecord['comments'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-align-left"></i> Description</div>
        <?php if (!empty($mainRecord['description'])): ?>
        <div class="info-item" style="margin-bottom:14px;">
            <div class="info-label">Description</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['description'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($mainRecord['about_letter'])): ?>
        <div class="info-item" style="margin-bottom:14px;">
            <div class="info-label">About Letter</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['about_letter'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($mainRecord['comments'])): ?>
        <div class="info-item">
            <div class="info-label">Comments</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['comments'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-building"></i> Assigned Agencies (<?= count($agencies) ?>)</div>
        <?php if (!empty($agencies)): ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>#</th><th>Regiment</th><th>Address</th><th>Assigned At</th></tr></thead>
                <tbody>
                    <?php foreach ($agencies as $i => $a): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($a['regiment_name'] ?? '') ?></strong></td>
                        <td><?= htmlspecialchars($a['address'] ?? '—') ?></td>
                        <td><?= !empty($a['assigned_at']) ? date('d M Y H:i', strtotime($a['assigned_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty"><i class="fa-solid fa-building-circle-xmark"></i>No agencies assigned</div>
        <?php endif; ?>
    </div>

    <?php if (!empty($mainRecord['file_path'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-paperclip"></i> Attachment</div>
        <a href="../../<?= htmlspecialchars($mainRecord['file_path']) ?>" target="_blank" class="link-item" style="max-width:400px;">
            <div class="icon"><i class="fa-solid fa-file-pdf"></i></div>
            <div class="body">
                <div class="ref">View Task PDF</div>
                <div class="ttl"><?= htmlspecialchars(basename($mainRecord['file_path'])) ?></div>
            </div>
            <div class="arr"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
        </a>
    </div>
    <?php endif; ?>

    <?php else: /* OUTWARD */ ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-circle-info"></i> Letter Information</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Letter Reference</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['letter_ref'] ?? '') ?></div></div>
            <div class="info-item"><div class="info-label">CDR Number</div><div class="info-value"><?= htmlspecialchars($mainRecord['cdr_no'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Letter Date</div><div class="info-value"><?= date('d M Y', strtotime($mainRecord['letter_date'])) ?></div></div>
            <div class="info-item"><div class="info-label">Receiver(s)</div><div class="info-value"><?= htmlspecialchars($mainRecord['branch_names'] ?? ($mainRecord['receiver'] ?? '')) ?></div></div>
            <div class="info-item"><div class="info-label">Dispatch Method</div><div class="info-value"><?= htmlspecialchars($mainRecord['dispatch_method'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="badge b-status-<?= htmlspecialchars($mainRecord['status'] ?? '') ?>"><?= htmlspecialchars($mainRecord['status'] ?? '') ?></span></div></div>
            <div class="info-item"><div class="info-label">Approval</div><div class="info-value"><span class="badge <?= ($mainRecord['approval_status'] ?? '')==='Approved' ? 'b-approved' : (($mainRecord['approval_status'] ?? '')==='Rejected' ? 'b-rejected' : 'b-status-Pending') ?>"><?= htmlspecialchars($mainRecord['approval_status'] ?? '') ?></span></div></div>
        </div>
        <div class="info-item" style="margin-top:14px;">
            <div class="info-label">Title</div>
            <div class="info-value"><?= htmlspecialchars($mainRecord['title'] ?? '') ?></div>
        </div>
    </div>

    <?php if (!empty($mainRecord['description']) || !empty($mainRecord['comment'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-align-left"></i> Description</div>
        <?php if (!empty($mainRecord['description'])): ?>
        <div class="info-item" style="margin-bottom:14px;">
            <div class="info-label">Description</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['description'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($mainRecord['comment'])): ?>
        <div class="info-item">
            <div class="info-label">Comments</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['comment'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-user-pen"></i> Creation &amp; Workflow</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Prepared By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['creator_rank'] ?? '').' '.($mainRecord['creator_name'] ?? ''))) ?></div></div>
            <div class="info-item"><div class="info-label">Service Number</div><div class="info-value mono"><?= htmlspecialchars($mainRecord['creator_service'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Prepared At</div><div class="info-value"><?= !empty($mainRecord['created_at']) ? date('d M Y H:i', strtotime($mainRecord['created_at'])) : '—' ?></div></div>
            <?php if (!empty($mainRecord['submitted_at'])): ?>
            <div class="info-item"><div class="info-label">Submitted At</div><div class="info-value"><?= date('d M Y H:i', strtotime($mainRecord['submitted_at'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($mainRecord['approved_at'])): ?>
            <div class="info-item"><div class="info-label">Approved By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['approver_rank'] ?? '').' '.($mainRecord['approver_name'] ?? ''))) ?></div></div>
            <div class="info-item"><div class="info-label">Approved At</div><div class="info-value"><?= date('d M Y H:i', strtotime($mainRecord['approved_at'])) ?></div></div>
            <?php endif; ?>
            <?php if (!empty($mainRecord['rejected_at'])): ?>
            <div class="info-item"><div class="info-label">Rejected By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['rejecter_rank'] ?? '').' '.($mainRecord['rejecter_name'] ?? ''))) ?></div></div>
            <div class="info-item"><div class="info-label">Rejected At</div><div class="info-value"><?= date('d M Y H:i', strtotime($mainRecord['rejected_at'])) ?></div></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($mainRecord['status'] ?? '') === 'Completed'): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-truck"></i> Dispatch Details</div>
        <div class="info-grid">
            <div class="info-item"><div class="info-label">Dispatched By</div><div class="info-value"><?= htmlspecialchars(trim(($mainRecord['dispatcher_rank'] ?? '').' '.($mainRecord['dispatcher_name'] ?? ''))) ?></div></div>
            <div class="info-item"><div class="info-label">Handed Over To</div><div class="info-value"><?= htmlspecialchars($mainRecord['handed_over_to'] ?? '—') ?></div></div>
            <div class="info-item"><div class="info-label">Dispatched At</div><div class="info-value"><?= !empty($mainRecord['handed_over_date']) ? date('d M Y H:i', strtotime($mainRecord['handed_over_date'])) : '—' ?></div></div>
        </div>
        <?php if (!empty($mainRecord['dispatch_remarks'])): ?>
        <div class="info-item" style="margin-top:14px;">
            <div class="info-label">Dispatch Remarks</div>
            <div class="info-value muted"><?= nl2br(htmlspecialchars($mainRecord['dispatch_remarks'])) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mainRecord['file_path'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-paperclip"></i> Attachment</div>
        <a href="../../<?= htmlspecialchars($mainRecord['file_path']) ?>" target="_blank" class="link-item" style="max-width:400px;">
            <div class="icon"><i class="fa-solid fa-file-pdf"></i></div>
            <div class="body">
                <div class="ref">View Outward PDF</div>
                <div class="ttl"><?= htmlspecialchars(basename($mainRecord['file_path'])) ?></div>
            </div>
            <div class="arr"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
        </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 2 — SOURCE DOCUMENTS
         (For Task/Outward main only; Internal is itself the source)
         ═══════════════════════════════════════════════════════ -->
    <?php if ($type === 'task'): ?>
        <?php if (!empty($taskSources)): ?>
        <div class="section-banner sb-source">
            <div class="num">2</div>
            <i class="fa-solid fa-book-bookmark"></i>
            <span>Source Documents</span>
            <span class="cnt"><?= count($taskSources) ?></span>
        </div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-envelope-open-text"></i> Source Internal Letters</div>
            <div class="links-grid">
                <?php foreach ($taskSources as $il): ?>
                <a href="unified_details.php?type=internal&id=<?= (int)$il['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($il['cdr_number'] ?? '') ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($il['letter_title'] ?? '', 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($type === 'outward'): ?>
        <?php if (!empty($linkedInternals)): ?>
        <div class="section-banner sb-source">
            <div class="num">2</div>
            <i class="fa-solid fa-book-bookmark"></i>
            <span>Source Documents</span>
            <span class="cnt"><?= count($linkedInternals) ?></span>
        </div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-envelope-open-text"></i> Source Internal Letters</div>
            <div class="links-grid">
                <?php foreach ($linkedInternals as $il): ?>
                <a href="unified_details.php?type=internal&id=<?= (int)$il['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($il['cdr_number'] ?? '') ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($il['letter_title'] ?? '', 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 3 — TASK DOCUMENTS
         ═══════════════════════════════════════════════════════ -->
    <?php if ($type === 'internal'): ?>
        <?php if (!empty($linkedTasks)): ?>
        <div class="section-banner sb-task">
            <div class="num">3</div>
            <i class="fa-solid fa-list-check"></i>
            <span>Task Documents</span>
            <span class="cnt"><?= count($linkedTasks) ?></span>
        </div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-list-check"></i> Linked Task Letters</div>
            <div class="links-grid">
                <?php foreach ($linkedTasks as $t): ?>
                <a href="unified_details.php?type=task&id=<?= (int)$t['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($t['tsk_cdr_number']) ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($t['title'], 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($linkedOuts)): ?>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-paper-plane"></i> Linked Outward Letters (<?= count($linkedOuts) ?>)</div>
            <div class="links-grid">
                <?php foreach ($linkedOuts as $o): ?>
                <a href="unified_details.php?type=outward&id=<?= (int)$o['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($o['letter_ref']) ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($o['title'], 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($type === 'outward'): ?>
        <?php if (!empty($linkedTasksFromOutward)): ?>
        <div class="section-banner sb-task">
            <div class="num">3</div>
            <i class="fa-solid fa-list-check"></i>
            <span>Task Documents</span>
            <span class="cnt"><?= count($linkedTasksFromOutward) ?></span>
        </div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-list-check"></i> Linked Task Letters</div>
            <div class="links-grid">
                <?php foreach ($linkedTasksFromOutward as $t): ?>
                <a href="unified_details.php?type=task&id=<?= (int)$t['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($t['tsk_cdr_number'] ?? '') ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($t['title'] ?? '', 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($type === 'task'): ?>
        <?php if (!empty($taskOuts)): ?>
        <div class="section-banner sb-task">
            <div class="num">3</div>
            <i class="fa-solid fa-paper-plane"></i>
            <span>Task Documents</span>
            <span class="cnt"><?= count($taskOuts) ?></span>
        </div>
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-paper-plane"></i> Linked Outward Letters</div>
            <div class="links-grid">
                <?php foreach ($taskOuts as $o): ?>
                <a href="unified_details.php?type=outward&id=<?= (int)$o['id'] ?>" class="link-item">
                    <div class="icon"><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($o['letter_ref'] ?? '') ?></div>
                        <div class="ttl"><?= htmlspecialchars(mb_substr($o['title'] ?? '', 0, 55)) ?></div>
                    </div>
                    <div class="arr"><i class="fa-solid fa-chevron-right"></i></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════
         SECTION 4 — REPLY DOCUMENTS
         ═══════════════════════════════════════════════════════ -->
    <?php if (!empty($replyDocs)): ?>
        <div class="section-banner sb-reply">
            <div class="num">4</div>
            <i class="fa-solid fa-reply-all"></i>
            <span>Reply Documents</span>
            <span class="cnt"><?= count($replyDocs) ?></span>
        </div>

        <div class="card">
            <div class="card-title"><i class="fa-solid fa-reply-all"></i> Replies Received (<?= count($replyDocs) ?>)</div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Regiment</th>
                            <th>Reply Letter</th>
                            <th>For Task</th>
                            <th>Status</th>
                            <th>Replied At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($replyDocs as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['regiment_name'] ?? '—') ?></strong></td>
                            <td>
                                <?php if (!empty($r['internal_letter_id'])): ?>
                                    <a href="unified_details.php?type=internal&id=<?= (int)$r['internal_letter_id'] ?>" style="color:var(--accent);text-decoration:none;font-weight:600;">
                                        <?= htmlspecialchars($r['cdr_number'] ?? '') ?>
                                        <?php if (!empty($r['letter_reference'])): ?>
                                            — <?= htmlspecialchars($r['letter_reference']) ?>
                                        <?php endif; ?>
                                    </a>
                                    <?php if (!empty($r['letter_title'])): ?>
                                        <div style="font-size:0.76rem;color:var(--muted);margin-top:2px;">
                                            <?= htmlspecialchars(mb_substr($r['letter_title'], 0, 70)) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em style="color:var(--muted);">Data NIL</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($r['task_id'])): ?>
                                    <a href="unified_details.php?type=task&id=<?= (int)$r['task_id'] ?>" style="color:var(--accent);text-decoration:none;">
                                        <?= htmlspecialchars($r['tsk_cdr_number'] ?? '—') ?>
                                    </a>
                                    <?php if (!empty($r['task_title'])): ?>
                                        <div style="font-size:0.76rem;color:var(--muted);margin-top:2px;">
                                            <?= htmlspecialchars(mb_substr($r['task_title'], 0, 60)) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge b-status-<?= ($r['reply_status'] ?? '') === 'Accepted' ? 'Completed' : 'Pending' ?>">
                                    <?= htmlspecialchars($r['reply_status'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <?= !empty($r['replied_at']) ? date('d M Y H:i', strtotime($r['replied_at'])) : '—' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($type === 'internal' && !empty($mainRecord['attachment'])): ?>
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-paperclip"></i> Original Attachment</div>
        <a href="../../uploads/letters/<?= htmlspecialchars($mainRecord['attachment']) ?>" target="_blank" class="link-item" style="max-width:400px;">
            <div class="icon"><i class="fa-solid fa-file-pdf"></i></div>
            <div class="body">
                <div class="ref">View Original PDF</div>
                <div class="ttl"><?= htmlspecialchars($mainRecord['attachment']) ?></div>
            </div>
            <div class="arr"><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
        </a>
    </div>
    <?php endif; ?>

</main>

<script>
(function() {
    const t = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', t);
})();

document.querySelectorAll('.nav-toggle').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.dataset.target;
        const sub = document.getElementById(targetId);
        if (!sub) return;
        sub.classList.toggle('open');
        const chevron = this.querySelector('.chevron');
        if (chevron) {
            chevron.style.transform = sub.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    });
});
</script>
</body>
</html>