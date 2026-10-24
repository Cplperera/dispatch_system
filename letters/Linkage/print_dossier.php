<?php
/**
 * PRINT DOSSIER — rendered by headless Chrome, one part at a time
 * ?token=...&part=cover|index|doc&idx=N
 *
 * CHAIN SECTIONS (mirrors generate_full_dossier.php):
 *   1. main    — Main Document
 *   2. source  — Source Documents
 *   3. task    — Task Documents
 *   4. reply   — Reply Documents
 */

require_once '../../config/db.php';
mb_internal_encoding('UTF-8');

// ── TOKEN CHECK ────────────────────────────────────────────
$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) { http_response_code(400); exit('Invalid token'); }

$tokenFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dossier_' . $token . '.json';
if (!file_exists($tokenFile)) { http_response_code(403); exit('Token not found'); }

$payload = json_decode(file_get_contents($tokenFile), true);
if (!$payload || $payload['expires'] < time()) { http_response_code(403); exit('Token expired'); }

$userId = (int) $payload['user_id'];
$type   = $payload['type'];
$id     = (int) $payload['id'];
$scope  = $payload['scope'];

$part = $_GET['part'] ?? 'all';
$idx  = isset($_GET['idx']) ? (int)$_GET['idx'] : -1;

// ═══════════════════════════════════════════════════════════
// FETCHERS
// ═══════════════════════════════════════════════════════════
function fetchInternal($conn, $id) {
    $s = $conn->prepare("SELECT il.*, lc.category AS category_name, r.regiment_name, i.informer_name, i.informer_code,
        u_reg.name_with_initial AS reg_name, u_reg.rank AS reg_rank, u_reg.service_number AS reg_service,
        u_edit.name_with_initial AS edit_name, u_edit.rank AS edit_rank
        FROM internal_letters il
        LEFT JOIN letter_categories lc ON il.category_id = lc.id
        LEFT JOIN regiments r ON il.regiment_id = r.id
        LEFT JOIN informers i ON il.Informer_id = i.id
        LEFT JOIN users u_reg ON il.registered_by = u_reg.id
        LEFT JOIN users u_edit ON il.updated_by = u_edit.id
        WHERE il.id = ? AND il.is_deleted = 0");
    $s->bind_param("i", $id); $s->execute(); return $s->get_result()->fetch_assoc();
}
function fetchTask($conn, $id) {
    $s = $conn->prepare("SELECT t.*, u.name_with_initial AS assigned_user, u.rank AS assigned_rank FROM task_letters t LEFT JOIN users u ON t.assigned_by = u.id WHERE t.id = ?");
    $s->bind_param("i", $id); $s->execute(); return $s->get_result()->fetch_assoc();
}
function fetchOutward($conn, $id) {
    $s = $conn->prepare("SELECT o.*, (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, o.receiver)) AS branch_names,
        creator.name_with_initial AS creator_name, creator.rank AS creator_rank,
        approver.name_with_initial AS approver_name, dispatcher.name_with_initial AS dispatcher_name
        FROM outward_letters o
        LEFT JOIN users creator ON o.created_by = creator.id
        LEFT JOIN users approver ON o.approved_by = approver.id
        LEFT JOIN users dispatcher ON o.dispatched_by = dispatcher.id
        WHERE o.id = ? AND o.is_deleted = 0");
    $s->bind_param("i", $id); $s->execute(); return $s->get_result()->fetch_assoc();
}
function fetchForwardingHistory($conn, $letterId) {
    $s = $conn->prepare("SELECT f.comments, f.forwarded_at, f.seen, u1.name_with_initial AS to_user, u1.rank AS to_rank, u2.name_with_initial AS by_user, u2.rank AS by_rank FROM internal_letters_forwarding f LEFT JOIN users u1 ON f.forwarded_to = u1.id LEFT JOIN users u2 ON f.forwarded_by = u2.id WHERE f.letter_id = ? ORDER BY f.forwarded_at ASC");
    $s->bind_param("i", $letterId); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchDisposalForInternal($conn, $internalId) {
    $s = $conn->prepare("SELECT d.remarks, d.status, d.forwarded_at, ds.desk_name, u.name_with_initial AS by_user, u.rank AS by_rank FROM internal_letters_final_disposal d LEFT JOIN desks ds ON d.forwarded_to = ds.id LEFT JOIN users u ON d.forwarded_by = u.id WHERE d.letter_id = ? ORDER BY d.forwarded_at ASC");
    $s->bind_param("i", $internalId); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchArchiveForInternal($conn, $internalId) {
    $s = $conn->prepare("SELECT fd.file_number, fd.file_location, fd.status, fd.disposed_at FROM internal_letters_final_disposal_details fd WHERE fd.letter_id = ? ORDER BY fd.disposed_at ASC");
    $s->bind_param("i", $internalId); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchAgenciesForTask($conn, $taskId) {
    $s = $conn->prepare("SELECT r.regiment_name, tla.assigned_at FROM task_letter_agencies tla JOIN regiments r ON tla.regiment_id = r.id WHERE tla.task_letter_id = ? ORDER BY r.regiment_name");
    $s->bind_param("i", $taskId); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchRepliesForTask($conn, $taskId) {
    $s = $conn->prepare("SELECT tr.status AS reply_status, tr.replied_at, r.regiment_name, il.id AS internal_letter_id, il.cdr_number FROM task_replies tr JOIN regiments r ON tr.regiment_id = r.id LEFT JOIN internal_letters il ON tr.reply_internal_letter_id = il.id WHERE tr.task_letter_id = ? ORDER BY tr.replied_at DESC");
    $s->bind_param("i", $taskId); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function fetchInternalLinksForTask($conn, $taskId) {
    $s = $conn->prepare("SELECT il.id FROM task_internal_links til JOIN internal_letters il ON til.internal_letter_id = il.id WHERE til.task_id = ? AND il.is_deleted = 0");
    $s->bind_param("i", $taskId); $s->execute(); return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function fetchTasksLinkedToInternal($conn, $internalId) {
    $s = $conn->prepare("SELECT DISTINCT tl.id FROM task_letters tl LEFT JOIN task_internal_links til ON tl.id = til.task_id LEFT JOIN task_replies tr ON tl.id = tr.task_letter_id WHERE til.internal_letter_id = ? OR tr.reply_internal_letter_id = ?");
    $s->bind_param("ii", $internalId, $internalId); $s->execute(); return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function fetchOutwardLinkedToTask($conn, $taskId) {
    $s = $conn->prepare("SELECT DISTINCT ol.id FROM outward_letters ol JOIN outward_task ot ON ol.id = ot.outward_id WHERE ot.task_id = ? AND ol.is_deleted = 0");
    $s->bind_param("i", $taskId); $s->execute(); return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function fetchInternalLinkedToOutward($conn, $outwardId) {
    $s = $conn->prepare("SELECT DISTINCT il.id FROM internal_letters il JOIN outward_internal oi ON il.id = oi.internal_id WHERE oi.outward_id = ? AND il.is_deleted = 0");
    $s->bind_param("i", $outwardId); $s->execute(); return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function fetchTasksLinkedToOutward($conn, $outwardId) {
    $s = $conn->prepare("SELECT DISTINCT tl.id FROM task_letters tl JOIN outward_task ot ON tl.id = ot.task_id WHERE ot.outward_id = ?");
    $s->bind_param("i", $outwardId); $s->execute(); return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}

// ═══════════════════════════════════════════════════════════
// BUILD CHAIN — SECTIONED (main → source → task → reply)
// ═══════════════════════════════════════════════════════════
$mainInternal = $type === 'internal' ? fetchInternal($conn, $id) : null;
$mainTask     = $type === 'task'     ? fetchTask($conn, $id)     : null;
$mainOutward  = $type === 'outward'  ? fetchOutward($conn, $id)  : null;
$mainRec      = $mainInternal ?: $mainTask ?: $mainOutward;
if (!$mainRec) { http_response_code(404); exit('Not found'); }

$chain   = [];
$visited = [];

$add = function($t, $i, $section) use (&$chain, &$visited) {
    $key = "{$t}_{$i}";
    if (isset($visited[$key])) return;
    $visited[$key] = true;
    $chain[] = ['type' => $t, 'id' => (int)$i, 'section' => $section];
};

// ─── PRE-COMPUTE: which internals are REPLIES ───
// (prevents reply letters from being mis-categorized as "source")
$replyInternalIds = [];

$taskIdsToScan = [];
if ($type === 'task') {
    $taskIdsToScan[] = $id;
} elseif ($type === 'internal') {
    $taskIdsToScan = fetchTasksLinkedToInternal($conn, $id);
} elseif ($type === 'outward') {
    $taskIdsToScan = fetchTasksLinkedToOutward($conn, $id);
}

foreach ($taskIdsToScan as $tid) {
    foreach (fetchRepliesForTask($conn, $tid) as $r) {
        if (!empty($r['internal_letter_id'])) {
            $replyInternalIds[(int)$r['internal_letter_id']] = true;
        }
    }
}

// ─── 1. MAIN ───
$add($type, $id, 'main');

// ─── 2. SOURCE (exclude replies) ───
if ($type === 'task') {
    foreach (fetchInternalLinksForTask($conn, $id) as $iid) {
        if (isset($replyInternalIds[(int)$iid])) continue;   // ← KEY FIX
        $add('internal', $iid, 'source');
    }
} elseif ($type === 'outward') {
    foreach (fetchInternalLinkedToOutward($conn, $id) as $iid) {
        if (isset($replyInternalIds[(int)$iid])) continue;   // ← KEY FIX
        $add('internal', $iid, 'source');
    }
}

// ─── 3. TASK ───
if ($type === 'internal') {
    foreach (fetchTasksLinkedToInternal($conn, $id) as $tid) $add('task', $tid, 'task');
} elseif ($type === 'outward') {
    foreach (fetchTasksLinkedToOutward($conn, $id) as $tid) $add('task', $tid, 'task');
}

// ─── 4. REPLY ───
$taskIdsInChain = [];
foreach ($chain as $c) if ($c['type'] === 'task') $taskIdsInChain[] = (int)$c['id'];

foreach ($taskIdsInChain as $tid) {
    foreach (fetchRepliesForTask($conn, $tid) as $r) {
        if (!empty($r['internal_letter_id'])) {
            $add('internal', (int)$r['internal_letter_id'], 'reply');
        }
    }
}

if (count($chain) > 30) $chain = array_slice($chain, 0, 30);

// Scope filters (preserve section order)
if ($scope === 'main') {
    $chain = [$chain[0]];
} elseif ($scope === 'internal') {
    $chain = array_values(array_filter($chain, fn($c) => $c['type'] === 'internal'));
} elseif ($scope === 'task') {
    $chain = array_values(array_filter($chain, fn($c) => $c['type'] === 'task'));
} elseif ($scope === 'outward') {
    $chain = array_values(array_filter($chain, fn($c) => $c['type'] === 'outward'));
}
if (empty($chain)) $chain = [['type'=>$type, 'id'=>$id, 'section'=>'main']];

$mainRef   = $type === 'internal' ? $mainInternal['cdr_number'] : ($type === 'task' ? $mainTask['tsk_cdr_number'] : $mainOutward['letter_ref']);
$mainTitle = $mainInternal['letter_title'] ?? $mainTask['title'] ?? $mainOutward['title'] ?? 'N/A';
$mainDate  = $mainInternal['letter_date'] ?? $mainTask['letter_date'] ?? $mainOutward['letter_date'];

// Counts: by type and by section
$counts         = ['internal'=>0, 'task'=>0, 'outward'=>0];
$sectionCounts  = ['main'=>0, 'source'=>0, 'task'=>0, 'reply'=>0];
foreach ($chain as $c) {
    $counts[$c['type']]++;
    if (isset($sectionCounts[$c['section']])) $sectionCounts[$c['section']]++;
}

// ⚠️ National Emblem path (must exist!)
$emblemUrl = '../../assets/images/sl_emblem.png';

// Friendly names
$sectionLabel = [
    'main'   => 'MAIN DOCUMENT',
    'source' => 'SOURCE DOCUMENT',
    'task'   => 'TASK DOCUMENT',
    'reply'  => 'REPLY DOCUMENT',
];
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="UTF-8">
<title>Dossier — <?= htmlspecialchars($mainRef) ?></title>
<style>
@page { size: A4; margin: 14mm 12mm; }
* { box-sizing: border-box; }
html, body {
    margin: 0; padding: 0; background: #fff; color: #1B2559;
    font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'Nirmala UI', 'Segoe UI', system-ui, sans-serif;
    font-size: 10.5pt; line-height: 1.45;
}
.page-block { page-break-after: always; }
.page-block:last-child { page-break-after: auto; }

/* ═══════════════ COVER ═══════════════ */
.cover { height: calc(297mm - 28mm); display: flex; flex-direction: column; }

.cover-emblem-wrap {
    text-align: center;
    padding: 18px 0 10px;
    background: #fff;
}
.cover-emblem-wrap img {
    max-height: 110px;
    max-width: 110px;
    object-fit: contain;
}

.cover-banner {
    background: linear-gradient(135deg, #29417A 0%, #4318FF 100%);
    color: #fff; padding: 30px 30px; text-align: center;
}
.cover-banner h1 { font-size: 26pt; font-weight: 800; margin: 0 0 6px; letter-spacing: 3px; }
.cover-banner h2 { font-size: 12pt; font-weight: 600; margin: 0; opacity: 0.95; letter-spacing: 1px; }

.cover-body { flex: 1; padding: 40px 40px 24px; text-align: center; }
.cover-ref { font-size: 20pt; font-weight: 800; color: #29417A; margin-bottom: 10px; letter-spacing: 1px; }
.cover-title { font-size: 12pt; color: #1B2559; margin-bottom: 16px; max-width: 500px; margin-left: auto; margin-right: auto; }
.cover-date { font-size: 10.5pt; color: #707EAE; margin-bottom: 34px; }
.cover-contents {
    border: 2px solid #DDE2EF; border-radius: 10px; padding: 20px;
    max-width: 420px; margin: 0 auto;
}
.cover-contents h3 {
    font-size: 9pt; letter-spacing: 2px; text-transform: uppercase;
    color: #707EAE; margin: 0 0 12px; border-bottom: 1px solid #DDE2EF; padding-bottom: 9px;
}
.cover-contents .count { font-size: 30pt; font-weight: 800; color: #4318FF; line-height: 1; }
.cover-contents .label { font-size: 8.5pt; color: #707EAE; text-transform: uppercase; letter-spacing: 1.5px; }
.cover-meta { margin-top: 13px; font-size: 10pt; color: #556080; }
.cover-sections {
    display: flex; justify-content: space-around; gap: 8px;
    margin-top: 14px; padding-top: 12px; border-top: 1px dashed #DDE2EF;
}
.cover-sections .sec { text-align: center; flex: 1; }
.cover-sections .sec .n { font-size: 14pt; font-weight: 800; color: #29417A; line-height: 1.1; }
.cover-sections .sec .l { font-size: 7.5pt; color: #707EAE; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 2px; }
.cover-footer { padding: 15px 40px; text-align: center; font-size: 8pt; color: #707EAE; border-top: 1px solid #DDE2EF; }
.cover-classification { font-weight: 800; color: #cd0505; letter-spacing: 2px; margin-top: 5px; }

/* ═══════════════ INDEX ═══════════════ */
.index-page h2 {
    font-size: 18pt; color: #29417A; border-bottom: 3px solid #4318FF;
    padding-bottom: 8px; margin: 0 0 20px; text-transform: uppercase; letter-spacing: 1px;
}
table.index-tbl { width: 100%; border-collapse: collapse; font-size: 9pt; }
table.index-tbl th {
    background: #29417A; color: #fff; padding: 9px 8px; text-align: left;
    font-size: 8pt; text-transform: uppercase; letter-spacing: 0.8px;
}
table.index-tbl td { padding: 7px 8px; border-bottom: 1px solid #DDE2EF; vertical-align: top; }
table.index-tbl tr:nth-child(even) td { background: #F8FAFF; }
.sec-tag {
    display: inline-block; padding: 2px 7px; border-radius: 4px;
    font-size: 7pt; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase;
    color: #fff;
}
.sec-tag.main   { background: #4318FF; }
.sec-tag.source { background: #29417A; }
.sec-tag.task   { background: #B45F06; }
.sec-tag.reply  { background: #107841; }

/* ═══════════════ DOCUMENT ═══════════════ */
.doc-header { padding: 18px 22px; color: #fff; border-radius: 6px 6px 0 0; }
.doc-header .num { font-size: 16pt; font-weight: 800; margin: 0; letter-spacing: 1px; }
.doc-header .type { font-size: 10.5pt; font-weight: 600; margin: 3px 0 0; opacity: 0.95; }
.doc-header .label-ref { font-size: 9pt; font-style: italic; margin: 3px 0 0; opacity: 0.85; }
.doc-body { padding: 12px 0 0; }

.section-title {
    font-size: 9.5pt; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px;
    color: #fff; padding: 6px 12px; margin: 14px 0 8px; border-radius: 4px;
    page-break-after: avoid;
}
.kv-row { display: flex; padding: 5px 0; border-bottom: 1px dashed #E6EAF3; font-size: 9.5pt; page-break-inside: avoid; }
.kv-row .k { width: 30%; font-weight: 700; color: #556080; padding-right: 10px; }
.kv-row .v { width: 70%; color: #1B2559; word-break: break-word; }
.kv-row .v.multiline { white-space: pre-wrap; }

.minute-block {
    background: #F8FAFF; border-left: 3px solid #4318FF; padding: 10px 14px;
    margin-bottom: 10px; border-radius: 0 6px 6px 0; page-break-inside: avoid;
}
.minute-block .head { font-weight: 800; color: #4318FF; font-size: 9.5pt; margin-bottom: 6px; }
.minute-block .kv-row { border-bottom: none; padding: 3px 0; }

table.mini-tbl { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 6px; }
table.mini-tbl th { background: #E6EAF3; padding: 6px 10px; text-align: left; font-weight: 700; font-size: 8.5pt; color: #556080; }
table.mini-tbl td { padding: 6px 10px; border-bottom: 1px solid #E6EAF3; }

.badge {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    background: #4318FF; color: #fff; font-size: 8pt; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.badge.green  { background: #10b981; }
.badge.red    { background: #cd0505; }
.badge.orange { background: #f59e0b; }
.badge.grey   { background: #6b7280; }
</style>
</head>
<body>

<?php
// ═══════════════════════════════════════════════════════════
// PART RENDERER — outputs the requested fragment(s)
// ═══════════════════════════════════════════════════════════
$renderCover = ($part === 'all' || $part === 'cover');
$renderIndex = ($part === 'all' || $part === 'index');
$renderDocs  = ($part === 'all' || $part === 'doc');

if ($renderCover): ?>
<div class="page-block cover">

    <!-- ⚠️ National Emblem of Sri Lanka -->
    <div class="cover-emblem-wrap">
        <img src="<?= htmlspecialchars($emblemUrl) ?>" alt="National Emblem of Sri Lanka">
    </div>

    <div class="cover-banner">
        <h1>COMPLETE DOSSIER</h1>
        <h2><?= strtoupper($type) ?> LETTER — FULL RECORD</h2>
    </div>

    <div class="cover-body">
        <div class="cover-ref">REFERENCE: <?= htmlspecialchars($mainRef) ?></div>
        <div class="cover-title" lang="si"><?= htmlspecialchars(mb_substr($mainTitle, 0, 160)) ?></div>
        <div class="cover-date">Letter Date <?= date('d M Y', strtotime($mainDate)) ?></div>
        <div class="cover-contents">
            <h3>Dossier Contents</h3>
            <div class="count"><?= count($chain) ?></div>
            <div class="label">Total Documents</div>

            <div class="cover-sections">
                <div class="sec">
                    <div class="n"><?= $sectionCounts['main'] ?></div>
                    <div class="l">Main</div>
                </div>
                <div class="sec">
                    <div class="n"><?= $sectionCounts['source'] ?></div>
                    <div class="l">Source</div>
                </div>
                <div class="sec">
                    <div class="n"><?= $sectionCounts['task'] ?></div>
                    <div class="l">Task</div>
                </div>
                <div class="sec">
                    <div class="n"><?= $sectionCounts['reply'] ?></div>
                    <div class="l">Reply</div>
                </div>
            </div>

            <div class="cover-meta" style="margin-top:14px;">
                <?= $counts['internal'] ?> Internal &nbsp;·&nbsp; <?= $counts['task'] ?> Task &nbsp;·&nbsp; <?= $counts['outward'] ?> Outward
            </div>
            <div class="cover-meta" style="font-size:9pt;text-transform:uppercase;letter-spacing:1px;color:#707EAE;">
                Scope: <?= strtoupper($scope) ?>
            </div>
        </div>
    </div>

    <div class="cover-footer">
        Directorate of Military Intelligence — Sri Lanka
        <div style="margin-top:4px;">Generated <?= date('d M Y H:i:s') ?> &nbsp;·&nbsp; Operator #<?= $userId ?></div>
        <div class="cover-classification">CONFIDENTIAL — FOR OFFICIAL USE ONLY</div>
    </div>
</div>
<?php endif;

if ($renderIndex): ?>
<div class="page-block index-page">
    <h2>Document Index</h2>
    <table class="index-tbl">
        <thead>
            <tr>
                <th style="width:34px;">#</th>
                <th style="width:80px;">Type</th>
                <th style="width:110px;">Section</th>
                <th style="width:140px;">Reference</th>
                <th>Title</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($chain as $i => $c):
            $rec = $c['type'] === 'internal' ? fetchInternal($conn, $c['id'])
                 : ($c['type'] === 'task' ? fetchTask($conn, $c['id']) : fetchOutward($conn, $c['id']));
            if (!$rec) continue;
            $ref = $c['type'] === 'internal' ? ($rec['cdr_number'] ?? '-')
                 : ($c['type'] === 'task' ? ($rec['tsk_cdr_number'] ?? '-') : ($rec['letter_ref'] ?? '-'));
            $ttl = $rec['letter_title'] ?? $rec['title'] ?? '-';
            $sec = $c['section'] ?? 'main';
        ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><span class="badge <?= $c['type']==='task'?'orange':($c['type']==='outward'?'green':'') ?>"><?= strtoupper($c['type']) ?></span></td>
                <td><span class="sec-tag <?= $sec ?>"><?= strtoupper($sec) ?></span></td>
                <td><strong><?= htmlspecialchars($ref) ?></strong></td>
                <td lang="si"><?= htmlspecialchars(mb_substr($ttl, 0, 120)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif;

if ($renderDocs):
    // Which documents to render?
    if ($part === 'doc' && $idx >= 0) {
        $docsToRender = isset($chain[$idx]) ? [$idx => $chain[$idx]] : [];
    } else {
        $docsToRender = $chain;
    }

    foreach ($docsToRender as $index => $item):
        $dt = $item['type']; $did = $item['id'];
        $sec = $item['section'] ?? 'main';
        $rec = $dt === 'internal' ? fetchInternal($conn, $did)
             : ($dt === 'task' ? fetchTask($conn, $did) : fetchOutward($conn, $did));
        if (!$rec) continue;

        $recRef   = $dt === 'internal' ? $rec['cdr_number']
                  : ($dt === 'task' ? $rec['tsk_cdr_number'] : $rec['letter_ref']);
        $headerBg = $dt === 'internal' ? '#29417A' : ($dt === 'task' ? '#B45F06' : '#107841');
        $secName  = $sectionLabel[$sec] ?? strtoupper($sec);
?>
<div class="page-block">
    <div class="doc-header" style="background:<?= $headerBg ?>;">
        <p class="num">DOCUMENT <?= $index + 1 ?> of <?= count($chain) ?></p>
        <p class="type"><?= strtoupper($dt) ?> LETTER</p>
        <p class="label-ref"><?= htmlspecialchars($secName) ?> &nbsp;·&nbsp; <?= htmlspecialchars($recRef) ?></p>
    </div>

    <div class="doc-body">

    <?php if ($dt === 'internal'): ?>

        <div class="section-title" style="background:<?= $headerBg ?>;">A. Basic Information</div>
        <div class="kv-row"><div class="k">CDR Number</div><div class="v"><?= htmlspecialchars($rec['cdr_number']) ?></div></div>
        <div class="kv-row"><div class="k">Reference</div><div class="v" lang="si"><?= htmlspecialchars($rec['letter_reference'] ?: '—') ?></div></div>
        <div class="kv-row"><div class="k">Title</div><div class="v" lang="si"><?= htmlspecialchars($rec['letter_title']) ?></div></div>
        <div class="kv-row"><div class="k">Letter Date</div><div class="v"><?= date('d M Y', strtotime($rec['letter_date'])) ?></div></div>
        <div class="kv-row"><div class="k">Regiment</div><div class="v" lang="si"><?= htmlspecialchars($rec['regiment_name'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Category</div><div class="v" lang="si"><?= htmlspecialchars($rec['category_name'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Security</div><div class="v"><span class="badge grey"><?= htmlspecialchars($rec['security_classification']) ?></span></div></div>
        <div class="kv-row"><div class="k">Priority</div><div class="v"><span class="badge <?= $rec['priority_status']==='Urgent'?'red':'' ?>"><?= htmlspecialchars($rec['priority_status']) ?></span></div></div>
        <div class="kv-row"><div class="k">Status</div><div class="v"><?= htmlspecialchars($rec['status']) ?></div></div>

        <div class="section-title" style="background:<?= $headerBg ?>;">B. Informer</div>
        <?php if ($rec['informer_name']): ?>
            <div class="kv-row"><div class="k">Informer Name</div><div class="v" lang="si"><?= htmlspecialchars($rec['informer_name']) ?></div></div>
            <div class="kv-row"><div class="k">Code</div><div class="v"><?= htmlspecialchars($rec['informer_code'] ?? '—') ?></div></div>
        <?php else: ?>
            <div class="kv-row"><div class="k">Informer</div><div class="v">Not linked</div></div>
        <?php endif; ?>

        <div class="section-title" style="background:<?= $headerBg ?>;">C. Registration</div>
        <div class="kv-row"><div class="k">Registered By</div><div class="v" lang="si"><?= htmlspecialchars(trim(($rec['reg_rank'] ?? '').' '.($rec['reg_name'] ?? ''))) ?></div></div>
        <div class="kv-row"><div class="k">Service Number</div><div class="v"><?= htmlspecialchars($rec['reg_service'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Registered At</div><div class="v"><?= date('d M Y H:i', strtotime($rec['created_at'])) ?></div></div>
        <?php if (!empty($rec['edit_name'])): ?>
            <div class="kv-row"><div class="k">Last Edited By</div><div class="v" lang="si"><?= htmlspecialchars(trim(($rec['edit_rank'] ?? '').' '.$rec['edit_name'])) ?> on <?= date('d M Y', strtotime($rec['updated_at'])) ?></div></div>
        <?php endif; ?>

        <?php if (!empty($rec['about_letter'])): ?>
            <div class="section-title" style="background:<?= $headerBg ?>;">D. About Letter</div>
            <div class="kv-row"><div class="v multiline" lang="si"><?= nl2br(htmlspecialchars($rec['about_letter'])) ?></div></div>
        <?php endif; ?>

        <?php $fwd = fetchForwardingHistory($conn, $did); ?>
        <div class="section-title" style="background:<?= $headerBg ?>;">E. Minute Trail (<?= count($fwd) ?>)</div>
        <?php if (!empty($fwd)): foreach ($fwd as $i => $f): ?>
            <div class="minute-block">
                <div class="head">Minute #<?= $i+1 ?> — <?= date('d M Y H:i', strtotime($f['forwarded_at'])) ?></div>
                <div class="kv-row"><div class="k">From</div><div class="v" lang="si"><?= htmlspecialchars(trim(($f['by_rank'] ?? '').' '.($f['by_user'] ?? ''))) ?></div></div>
                <div class="kv-row"><div class="k">To</div><div class="v" lang="si"><?= htmlspecialchars(trim(($f['to_rank'] ?? '').' '.($f['to_user'] ?? ''))) ?></div></div>
                <?php if (!empty($f['comments'])): ?>
                    <div class="kv-row"><div class="k">Comments</div><div class="v multiline" lang="si"><?= nl2br(htmlspecialchars($f['comments'])) ?></div></div>
                <?php endif; ?>
            </div>
        <?php endforeach; else: ?>
            <div class="kv-row"><div class="v">No forwarding records</div></div>
        <?php endif; ?>

        <?php $disp = fetchDisposalForInternal($conn, $did); ?>
        <div class="section-title" style="background:<?= $headerBg ?>;">F. Final Disposal (<?= count($disp) ?>)</div>
        <?php if (!empty($disp)): ?>
            <table class="mini-tbl">
                <thead><tr><th>Desk</th><th>By</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($disp as $d): ?>
                    <tr>
                        <td lang="si"><?= htmlspecialchars($d['desk_name'] ?? '—') ?></td>
                        <td lang="si"><?= htmlspecialchars(trim(($d['by_rank'] ?? '').' '.($d['by_user'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($d['status']) ?></td>
                        <td lang="si"><?= htmlspecialchars($d['remarks'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="kv-row"><div class="v">No disposal records</div></div>
        <?php endif; ?>

        <?php $arch = fetchArchiveForInternal($conn, $did); ?>
        <div class="section-title" style="background:<?= $headerBg ?>;">G. Physical Archive (<?= count($arch) ?>)</div>
        <?php if (!empty($arch)): ?>
            <table class="mini-tbl">
                <thead><tr><th>File No.</th><th>Location</th><th>Status</th><th>Disposed At</th></tr></thead>
                <tbody>
                <?php foreach ($arch as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['file_number'] ?? '—') ?></td>
                        <td lang="si"><?= htmlspecialchars($a['file_location'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($a['status']) ?></td>
                        <td><?= date('d M Y', strtotime($a['disposed_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="kv-row"><div class="v">Not yet archived</div></div>
        <?php endif; ?>

    <?php elseif ($dt === 'task'): ?>

        <div class="section-title" style="background:<?= $headerBg ?>;">A. Task Information</div>
        <div class="kv-row"><div class="k">Task CDR</div><div class="v"><?= htmlspecialchars($rec['tsk_cdr_number']) ?></div></div>
        <div class="kv-row"><div class="k">Reference</div><div class="v"><?= htmlspecialchars($rec['letter_ref']) ?></div></div>
        <div class="kv-row"><div class="k">Title</div><div class="v" lang="si"><?= htmlspecialchars($rec['title']) ?></div></div>
        <div class="kv-row"><div class="k">Letter Date</div><div class="v"><?= date('d M Y', strtotime($rec['letter_date'])) ?></div></div>
        <div class="kv-row"><div class="k">Request Date</div><div class="v"><?= !empty($rec['request_date']) ? date('d M Y', strtotime($rec['request_date'])) : '—' ?></div></div>
        <div class="kv-row"><div class="k">Priority</div><div class="v"><?= htmlspecialchars($rec['priority_status']) ?></div></div>
        <div class="kv-row"><div class="k">Status</div><div class="v"><?= htmlspecialchars($rec['status']) ?></div></div>
        <div class="kv-row"><div class="k">Assigned By</div><div class="v" lang="si"><?= htmlspecialchars(trim(($rec['assigned_rank'] ?? '').' '.($rec['assigned_user'] ?? 'System'))) ?></div></div>
        <div class="kv-row"><div class="k">Created At</div><div class="v"><?= date('d M Y H:i', strtotime($rec['created_at'])) ?></div></div>
        <?php if (!empty($rec['description'])): ?>
            <div class="kv-row"><div class="k">Description</div><div class="v multiline" lang="si"><?= nl2br(htmlspecialchars($rec['description'])) ?></div></div>
        <?php endif; ?>

        <?php $agencies = fetchAgenciesForTask($conn, $did); ?>
        <div class="section-title" style="background:<?= $headerBg ?>;">B. Assigned Agencies (<?= count($agencies) ?>)</div>
        <?php if (!empty($agencies)): ?>
            <table class="mini-tbl">
                <thead><tr><th>#</th><th>Regiment</th><th>Assigned At</th></tr></thead>
                <tbody>
                <?php foreach ($agencies as $i => $a): ?>
                    <tr><td><?= $i+1 ?></td><td lang="si"><?= htmlspecialchars($a['regiment_name']) ?></td><td><?= date('d M Y H:i', strtotime($a['assigned_at'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="kv-row"><div class="v">No agencies assigned</div></div>
        <?php endif; ?>

        <?php $replies = fetchRepliesForTask($conn, $did); ?>
        <div class="section-title" style="background:<?= $headerBg ?>;">C. Replies Received (<?= count($replies) ?>)</div>
        <?php if (!empty($replies)): ?>
            <table class="mini-tbl">
                <thead><tr><th>Regiment</th><th>Reply Letter</th><th>Status</th><th>Replied At</th></tr></thead>
                <tbody>
                <?php foreach ($replies as $r): ?>
                    <tr>
                        <td lang="si"><?= htmlspecialchars($r['regiment_name']) ?></td>
                        <td lang="si"><?= htmlspecialchars($r['cdr_number'] ?? 'Data NIL') ?></td>
                        <td><?= htmlspecialchars($r['reply_status']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($r['replied_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="kv-row"><div class="v">No replies received</div></div>
        <?php endif; ?>

    <?php else: ?>

        <div class="section-title" style="background:<?= $headerBg ?>;">A. Letter Information</div>
        <div class="kv-row"><div class="k">Letter Reference</div><div class="v"><?= htmlspecialchars($rec['letter_ref']) ?></div></div>
        <div class="kv-row"><div class="k">CDR Number</div><div class="v"><?= htmlspecialchars($rec['cdr_no'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Title</div><div class="v" lang="si"><?= htmlspecialchars($rec['title']) ?></div></div>
        <div class="kv-row"><div class="k">Letter Date</div><div class="v"><?= date('d M Y', strtotime($rec['letter_date'])) ?></div></div>
        <div class="kv-row"><div class="k">Receiver(s)</div><div class="v" lang="si"><?= htmlspecialchars($rec['branch_names'] ?? $rec['receiver'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Dispatch Method</div><div class="v"><?= htmlspecialchars($rec['dispatch_method'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Status</div><div class="v"><?= htmlspecialchars($rec['status']) ?></div></div>
        <div class="kv-row"><div class="k">Approval</div><div class="v"><?= htmlspecialchars($rec['approval_status'] ?? '—') ?></div></div>
        <div class="kv-row"><div class="k">Prepared By</div><div class="v" lang="si"><?= htmlspecialchars(trim(($rec['creator_rank'] ?? '').' '.($rec['creator_name'] ?? ''))) ?></div></div>
        <div class="kv-row"><div class="k">Prepared At</div><div class="v"><?= date('d M Y H:i', strtotime($rec['created_at'])) ?></div></div>
        <?php if (!empty($rec['description'])): ?>
            <div class="kv-row"><div class="k">Description</div><div class="v multiline" lang="si"><?= nl2br(htmlspecialchars($rec['description'])) ?></div></div>
        <?php endif; ?>

    <?php endif; ?>

        <?php
        $att = '';
        if ($dt === 'internal' && !empty($rec['attachment'])) $att = $rec['attachment'];
        elseif (($dt === 'task' || $dt === 'outward') && !empty($rec['file_path'])) $att = basename($rec['file_path']);
        ?>
        <?php if (!empty($att)): ?>
            <div style="margin-top:14px;padding:10px 14px;background:#FFF8E7;border:1px solid #F59E0B;border-radius:6px;font-size:9pt;color:#92400e;">
                <strong>📎 Attachment follows this page:</strong> <?= htmlspecialchars($att) ?>
            </div>
        <?php endif; ?>

    </div>
</div>
<?php endforeach; endif; ?>

</body>
</html>