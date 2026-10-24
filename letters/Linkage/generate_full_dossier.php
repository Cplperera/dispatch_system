<?php
/**
 * GENERATE FULL DOSSIER
 *   - Attachments come right after their own document
 *   - Every page gets a watermark (text + emblem)
 *   - Two-phase UX: build JSON + download button
 *
 * DOCUMENT CATEGORIES (in order):
 *   1. Main Document
 *   2. Source Documents
 *   3. Task Documents
 *   4. Reply Documents (replies to Task documents)
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

use setasign\Fpdi\TcpdfFpdi;

checkSessionTimeout();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('Session Invalid'); }
$userId = (int) $_SESSION['user_id'];

if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('VIEW_OUTGOING_LETTERS')) {
    http_response_code(403); exit('Access Denied');
}
mb_internal_encoding('UTF-8');

// ═══════════════════════════════════════════════════════════
// INPUT
// ═══════════════════════════════════════════════════════════
$action = $_GET['action'] ?? 'page';
$type   = strtolower(trim($_GET['type']  ?? 'internal'));
$id     = intval($_GET['id'] ?? 0);
$scope  = strtolower(trim($_GET['scope'] ?? 'all'));

if (!in_array($scope, ['all','main','internal','task','outward','chain'])) $scope = 'all';
$includeAttachments = ($scope !== 'chain');
if (!in_array($type, ['internal','task','outward'])) {
    if ($action === 'build') { header('Content-Type: application/json');
        echo json_encode(['status'=>'error','error'=>'Invalid type']); exit; }
    http_response_code(400); exit('Invalid type');
}

if ($id <= 0) {
    if ($type === 'internal') $r = $conn->query("SELECT id FROM internal_letters WHERE is_deleted=0 ORDER BY id ASC LIMIT 1");
    elseif ($type === 'task') $r = $conn->query("SELECT id FROM task_letters ORDER BY id ASC LIMIT 1");
    else $r = $conn->query("SELECT id FROM outward_letters WHERE is_deleted=0 ORDER BY id ASC LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    $id = $row ? (int)$row['id'] : 0;
}
if ($id <= 0) {
    if ($action === 'build') { header('Content-Type: application/json');
        echo json_encode(['status'=>'error','error'=>'No records']); exit; }
    http_response_code(400); exit('No records');
}

$root_dir = realpath(__DIR__ . '/../../');
if ($root_dir === false) { http_response_code(500); exit('Root dir failed'); }

// Watermark emblem — must exist
$watermarkEmblem = $root_dir . '/assets/images/sl_emblem.png';

// ═══════════════════════════════════════════════════════════
// MAIN REF + LAST UPDATED
// ═══════════════════════════════════════════════════════════
$refSql = $type === 'internal' ? "SELECT cdr_number AS ref, updated_at FROM internal_letters WHERE id=?"
        : ($type === 'task' ? "SELECT tsk_cdr_number AS ref, created_at AS updated_at FROM task_letters WHERE id=?"
                            : "SELECT letter_ref AS ref, created_at AS updated_at FROM outward_letters WHERE id=?");
$mainRef = 'DOC'; $mainUpdated = '0';
if ($s = $conn->prepare($refSql)) {
    $s->bind_param("i", $id); $s->execute();
    if ($row = $s->get_result()->fetch_assoc()) {
        $mainRef = $row['ref'] ?? 'DOC'; $mainUpdated = $row['updated_at'] ?? '0';
    }
    $s->close();
}

// ═══════════════════════════════════════════════════════════
// CACHE
// ═══════════════════════════════════════════════════════════
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dossier_cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);

$cacheKey  = md5($type.'|'.$id.'|'.$scope.'|'.$mainUpdated.'|wm4-reply-fix');
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.pdf';

$cleanRef = preg_replace('/[^a-zA-Z0-9_-]/', '_', $mainRef);
$filename = 'DMI_' . ucfirst($type) . '_' . $cleanRef . '_' . date('Ymd_His') . '.pdf';

// ═══════════════════════════════════════════════════════════
// ACTION: DOWNLOAD
// ═══════════════════════════════════════════════════════════
if ($action === 'download') {
    if (!file_exists($cacheFile)) { http_response_code(404); exit('File not found.'); }
    $token = $_GET['token'] ?? '';
    if (!isset($_SESSION['dossier_downloads'][$token]) ||
        $_SESSION['dossier_downloads'][$token]['file'] !== $cacheFile) {
        http_response_code(403); exit('Invalid or expired token.');
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($_SESSION['dossier_downloads'][$token]['filename']) . '"');
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($cacheFile);
    exit;
}

// ═══════════════════════════════════════════════════════════
// ACTION: BUILD
// ═══════════════════════════════════════════════════════════
if ($action === 'build') {
    ignore_user_abort(true);
    set_time_limit(300);
    header('Content-Type: application/json; charset=utf-8');

    // Fast path: cache hit
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        $token = bin2hex(random_bytes(16));
        $_SESSION['dossier_downloads'][$token] = [
            'file' => $cacheFile, 'filename' => $filename, 'expires' => time() + 3600,
        ];
        echo json_encode(['status'=>'ok','token'=>$token,'cached'=>true,'size'=>filesize($cacheFile),'pages'=>0]);
        exit;
    }

    try {
        // ─────────── BUILD CHAIN (SECTIONED) ───────────
        // Sections, in order:  main → source → task → reply
        $chain = (function() use ($conn, $type, $id, $scope) {
            $fetchIds = function($sql, $types, ...$params) use ($conn) {
                $s = $conn->prepare($sql); $s->bind_param($types, ...$params); $s->execute();
                return array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
            };
            $fetchRows = function($sql, $types, ...$params) use ($conn) {
                $s = $conn->prepare($sql); $s->bind_param($types, ...$params); $s->execute();
                return $s->get_result()->fetch_all(MYSQLI_ASSOC);
            };

            $chain   = [];
            $visited = [];

            $add = function($t, $i, $section) use (&$chain, &$visited) {
                $key = "{$t}_{$i}";
                if (isset($visited[$key])) return;
                $visited[$key] = true;
                $chain[] = ['type' => $t, 'id' => (int)$i, 'section' => $section];
            };

            // ─── PRE-COMPUTE: which internals are REPLIES ───
            // Must be done BEFORE adding source, so replies don't get
            // mis-categorized as "source" (they may also live in task_internal_links).
            $replyInternalIds = [];

            $taskIdsToScan = [];
            if ($type === 'task') {
                $taskIdsToScan[] = $id;
            } elseif ($type === 'internal') {
                $taskIdsToScan = $fetchIds(
                    "SELECT DISTINCT tl.id
                       FROM task_letters tl
                       LEFT JOIN task_internal_links til ON tl.id = til.task_id
                       LEFT JOIN task_replies         tr  ON tl.id = tr.task_letter_id
                      WHERE til.internal_letter_id = ? OR tr.reply_internal_letter_id = ?",
                    'ii', $id, $id
                );
            } elseif ($type === 'outward') {
                $taskIdsToScan = $fetchIds(
                    "SELECT DISTINCT tl.id
                       FROM task_letters tl
                       JOIN outward_task ot ON tl.id = ot.task_id
                      WHERE ot.outward_id = ?",
                    'i', $id
                );
            }

            foreach ($taskIdsToScan as $tid) {
                $rows = $fetchRows(
                    "SELECT reply_internal_letter_id
                       FROM task_replies
                      WHERE task_letter_id = ? AND reply_internal_letter_id IS NOT NULL",
                    'i', $tid
                );
                foreach ($rows as $r) {
                    $replyInternalIds[(int)$r['reply_internal_letter_id']] = true;
                }
            }

            // ─── 1. MAIN DOCUMENT ───
            $add($type, $id, 'main');

            // ─── 2. SOURCE DOCUMENTS (exclude replies) ───
            if ($type === 'task') {
                $srcIds = $fetchIds(
                    "SELECT il.id
                       FROM task_internal_links til
                       JOIN internal_letters il ON til.internal_letter_id = il.id
                      WHERE til.task_id = ? AND il.is_deleted = 0
                      ORDER BY til.linked_at ASC",
                    'i', $id
                );
                foreach ($srcIds as $i) {
                    if (isset($replyInternalIds[(int)$i])) continue;   // ← KEY FIX
                    $add('internal', $i, 'source');
                }

            } elseif ($type === 'outward') {
                $srcIds = $fetchIds(
                    "SELECT DISTINCT il.id
                       FROM internal_letters il
                       JOIN outward_internal oi ON il.id = oi.internal_id
                      WHERE oi.outward_id = ? AND il.is_deleted = 0
                      ORDER BY il.id ASC",
                    'i', $id
                );
                foreach ($srcIds as $i) {
                    if (isset($replyInternalIds[(int)$i])) continue;   // ← KEY FIX
                    $add('internal', $i, 'source');
                }
            }

            // ─── 3. TASK DOCUMENTS ───
            if ($type === 'internal') {
                $taskIds = $fetchIds(
                    "SELECT DISTINCT tl.id
                       FROM task_letters tl
                       LEFT JOIN task_internal_links til ON tl.id = til.task_id
                       LEFT JOIN task_replies         tr  ON tl.id = tr.task_letter_id
                      WHERE til.internal_letter_id = ? OR tr.reply_internal_letter_id = ?
                      ORDER BY tl.created_at ASC",
                    'ii', $id, $id
                );
                foreach ($taskIds as $t) $add('task', $t, 'task');

            } elseif ($type === 'outward') {
                $taskIds = $fetchIds(
                    "SELECT DISTINCT tl.id
                       FROM task_letters tl
                       JOIN outward_task ot ON tl.id = ot.task_id
                      WHERE ot.outward_id = ?
                      ORDER BY tl.created_at ASC",
                    'i', $id
                );
                foreach ($taskIds as $t) $add('task', $t, 'task');
            }

            // ─── 4. REPLY DOCUMENTS ───
            $taskIdsInChain = [];
            foreach ($chain as $c) if ($c['type'] === 'task') $taskIdsInChain[] = (int)$c['id'];

            if (!empty($taskIdsInChain)) {
                foreach ($taskIdsInChain as $tid) {
                    $replyIds = $fetchIds(
                        "SELECT il.id
                           FROM task_replies tr
                           JOIN internal_letters il ON tr.reply_internal_letter_id = il.id
                          WHERE tr.task_letter_id = ? AND il.is_deleted = 0
                          ORDER BY tr.replied_at ASC",
                        'i', $tid
                    );
                    foreach ($replyIds as $ri) $add('internal', $ri, 'reply');
                }
            }

            // Safety cap
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

            if (empty($chain)) $chain = [['type'=>$type,'id'=>$id,'section'=>'main']];
            return $chain;
        })();

        // ─────────── FIND CHROME ───────────
        $chrome = (function() {
            $c = [
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
                'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
                '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium', '/usr/bin/chromium-browser', '/snap/bin/chromium',
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            ];
            foreach ($c as $p) if (@is_file($p)) return $p;
            return null;
        })();
        if (!$chrome) { echo json_encode(['status'=>'error','error'=>'Chrome not found']); exit; }

        // ─────────── ONE-TIME TOKEN ───────────
        $token     = bin2hex(random_bytes(16));
        $tokenFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dossier_' . $token . '.json';
        file_put_contents($tokenFile, json_encode([
            'user_id'=>$userId, 'type'=>$type, 'id'=>$id, 'scope'=>$scope, 'expires'=>time() + 600,
        ]));
        @chmod($tokenFile, 0600);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $base   = "{$scheme}://{$host}{$dir}/print_dossier.php?token={$token}";

        // ─────────── CHROME HELPER ───────────
        $chromeToPdf = function($url) use ($chrome) {
            $out = tempnam(sys_get_temp_dir(), 'dp_') . '.pdf';
            $args = [
                escapeshellarg($chrome),
                '--headless', '--disable-gpu', '--no-sandbox',
                '--disable-dev-shm-usage',
                '--no-pdf-header-footer', '--print-to-pdf-no-header',
                '--virtual-time-budget=10000',
                '--run-all-compositor-stages-before-draw',
                '--no-first-run', '--no-default-browser-check',
                '--disable-extensions', '--hide-scrollbars',
                '--print-to-pdf=' . escapeshellarg($out),
                escapeshellarg($url),
            ];
            exec(implode(' ', $args) . ' 2>&1', $o, $code);
            if ($code !== 0 || !file_exists($out) || filesize($out) < 500
                || file_get_contents($out, false, null, 0, 5) !== '%PDF-') {
                @unlink($out); return null;
            }
            return $out;
        };

        // ─────────── RENDER PARTS ───────────
        $tmpFiles = [];
        $orderedParts = [];

        // 1. Cover + Index
        $coverPdf = $chromeToPdf($base . '&part=cover');
        if ($coverPdf) { $tmpFiles[] = $coverPdf; $orderedParts[] = ['file'=>$coverPdf, 'kind'=>'cover']; }

        $indexPdf = $chromeToPdf($base . '&part=index');
        if ($indexPdf) { $tmpFiles[] = $indexPdf; $orderedParts[] = ['file'=>$indexPdf, 'kind'=>'index']; }

        // 2. Each document → its own PDF, then attachment right after
        //    Chain is already ordered by section (main → source → task → reply)
        foreach ($chain as $i => $item) {
            $docPdf = $chromeToPdf($base . '&part=doc&idx=' . $i);
            if ($docPdf) {
                $tmpFiles[] = $docPdf;
                $orderedParts[] = ['file'=>$docPdf, 'kind'=>'doc', 'chainIdx'=>$i, 'section'=>$item['section'] ?? 'main'];
            }

            if (!$includeAttachments) continue;

            // Resolve attachment for this specific doc
            $attRel = null;
            if ($item['type'] === 'internal') {
                if ($s = $conn->prepare("SELECT attachment FROM internal_letters WHERE id=?")) {
                    $s->bind_param("i", $item['id']); $s->execute();
                    if ($r = $s->get_result()->fetch_assoc())
                        if (!empty($r['attachment'])) $attRel = 'uploads/letters/' . $r['attachment'];
                    $s->close();
                }
            } elseif ($item['type'] === 'task') {
                if ($s = $conn->prepare("SELECT file_path FROM task_letters WHERE id=?")) {
                    $s->bind_param("i", $item['id']); $s->execute();
                    if ($r = $s->get_result()->fetch_assoc())
                        if (!empty($r['file_path'])) $attRel = $r['file_path'];
                    $s->close();
                }
            } elseif ($item['type'] === 'outward') {
                if ($s = $conn->prepare("SELECT file_path FROM outward_letters WHERE id=?")) {
                    $s->bind_param("i", $item['id']); $s->execute();
                    if ($r = $s->get_result()->fetch_assoc())
                        if (!empty($r['file_path'])) $attRel = $r['file_path'];
                    $s->close();
                }
            }
            if ($attRel) {
                $clean = str_replace(['..', "\0"], '', str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $attRel));
                $real  = realpath($root_dir . DIRECTORY_SEPARATOR . $clean);
                $rootReal = realpath($root_dir);
                if ($real && $rootReal && strpos($real, $rootReal) === 0
                    && strtolower(pathinfo($real, PATHINFO_EXTENSION)) === 'pdf') {
                    $orderedParts[] = ['file'=>$real, 'kind'=>'attachment', 'chainIdx'=>$i, 'section'=>$item['section'] ?? 'main'];
                }
            }
        }

        @unlink($tokenFile);
        if (empty($orderedParts)) { echo json_encode(['status'=>'error','error'=>'No PDF parts generated']); exit; }

        // ─────────── MERGE + WATERMARK ───────────
        while (ob_get_level()) ob_end_clean();
        ob_start();

        $pdf = new TcpdfFpdi('P', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        $totalPages = 0;
        $hasEmblem = file_exists($watermarkEmblem);

        foreach ($orderedParts as $part) {
            $pageCount = $pdf->setSourceFile($part['file']);
            for ($p = 1; $p <= $pageCount; $p++) {
                $tpl  = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tpl);
                $w = $size['width']  ?? $size['w'] ?? 210;
                $h = $size['height'] ?? $size['h'] ?? 297;
                $pdf->AddPage($size['orientation'] ?? 'P', [$w, $h]);
                $pdf->useTemplate($tpl, 0, 0, $w, $h);
                $totalPages++;

                // ─── WATERMARK on this page ───
                $cx = $w / 2;
                $cy = $h / 2;

                $pdf->SetAlpha(0.07);

                if ($hasEmblem) {
                    try {
                        $pdf->Image($watermarkEmblem, $cx - 30, $cy - 30, 60, 60, '', '', '', false, 300, '', false, false, 0);
                    } catch (Throwable $e) { /* ignore */ }
                }

                $pdf->StartTransform();
                $pdf->Rotate(45, $cx, $cy);
                $pdf->SetFont('helvetica', 'B', 26);
                $pdf->SetTextColor(60, 60, 90);
                $txt = 'Property of Directorate of Military Intelligence';
                $tw = $pdf->GetStringWidth($txt);
                $pdf->Text($cx - $tw / 2, $cy + 6, $txt);
                $pdf->StopTransform();

                $pdf->SetAlpha(1);
            }
        }

        $pdf->Output($cacheFile, 'F');

        foreach ($tmpFiles as $f) @unlink($f);
        ob_end_clean();

        if (!file_exists($cacheFile)) { echo json_encode(['status'=>'error','error'=>'Merge failed']); exit; }

        $dlToken = bin2hex(random_bytes(16));
        $_SESSION['dossier_downloads'][$dlToken] = [
            'file' => $cacheFile, 'filename' => $filename, 'expires' => time() + 3600,
        ];

        echo json_encode([
            'status' => 'ok',
            'token'  => $dlToken,
            'cached' => false,
            'size'   => filesize($cacheFile),
            'pages'  => $totalPages,
        ]);
    } catch (Throwable $e) {
        error_log('Dossier build error: ' . $e->getMessage());
        echo json_encode(['status'=>'error','error'=>$e->getMessage()]);
    }
    exit;
}

// ═══════════════════════════════════════════════════════════
// DEFAULT: HTML GENERATING PAGE
// ═══════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generating Dossier — <?= htmlspecialchars($mainRef) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<style>
:root { --bg:#F0F2F5; --card:#FFF; --accent:#4318FF; --accent-soft:rgba(67,24,255,0.08); --text:#1B2559; --muted:#707EAE; --border:#DDE2EF; }
[data-theme="dark"] { --bg:#0B1437; --card:#111C44; --accent:#7551FF; --accent-soft:rgba(117,81,255,0.1); --text:#FFF; --muted:#A3AED0; --border:rgba(255,255,255,0.1); color-scheme:dark; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); min-height:100vh; margin:0; display:flex; align-items:center; justify-content:center;
       font-family:'Noto Sans Sinhala','Plus Jakarta Sans','Iskoola Pota','Segoe UI',system-ui,sans-serif; padding:20px; transition:background 0.3s, color 0.3s; }
.card-box { background:var(--card); border:1px solid var(--border); border-radius:24px; padding:50px 40px; max-width:560px; width:100%;
            text-align:center; box-shadow:0 20px 60px rgba(0,0,0,0.06); transition:background 0.3s, border-color 0.3s; }
.icon-circle { width:100px; height:100px; border-radius:50%; margin:0 auto 24px; display:flex; align-items:center; justify-content:center;
               background:var(--accent-soft); color:var(--accent); font-size:2.6rem; position:relative; }
.spinner { position:absolute; inset:-4px; border:3px solid transparent; border-top-color:var(--accent); border-right-color:var(--accent);
           border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
h1 { font-size:1.4rem; font-weight:800; margin:0 0 10px; color:var(--text); }
.ref-line { font-size:0.95rem; font-weight:700; color:var(--accent); margin-bottom:8px; font-family:'Courier New', monospace; }
.sub { font-size:0.9rem; color:var(--muted); margin-bottom:26px; line-height:1.5; }
.progress-track { width:100%; height:8px; background:var(--accent-soft); border-radius:10px; overflow:hidden; margin-bottom:14px; }
.progress-bar { height:100%; width:5%; background:linear-gradient(90deg, var(--accent), #7551FF); border-radius:10px; transition:width 0.4s ease; }
.status-text { font-size:0.82rem; color:var(--muted); font-weight:600; letter-spacing:0.5px; min-height:20px; }
.success-icon { background:rgba(16,185,129,0.12); color:#10b981; }
.btn-download { display:inline-flex; align-items:center; gap:10px; background:var(--accent); color:#fff; padding:14px 28px; border-radius:14px;
                font-weight:800; font-size:0.95rem; text-decoration:none; border:none; cursor:pointer; transition:0.2s; margin-top:6px; }
.btn-download:hover { transform:translateY(-2px); box-shadow:0 10px 24px var(--accent-soft); color:#fff; opacity:0.95; }
.btn-secondary { display:inline-flex; align-items:center; gap:8px; background:transparent; color:var(--muted); padding:12px 22px; border-radius:12px;
                 border:1px solid var(--border); font-weight:700; font-size:0.85rem; text-decoration:none; cursor:pointer; transition:0.2s; margin-top:12px; }
.btn-secondary:hover { border-color:var(--accent); color:var(--accent); }
.file-meta { background:var(--accent-soft); color:var(--accent); padding:10px 16px; border-radius:12px; font-size:0.8rem; font-weight:700;
             margin:16px 0 0; display:inline-block; }
.error-msg { background:rgba(239,68,68,0.1); color:#ef4444; padding:14px 18px; border-radius:12px; font-size:0.85rem; font-weight:600;
             margin:16px 0; line-height:1.5; text-align:left; word-break:break-word; }
.steps { text-align:left; margin:22px auto 0; max-width:340px; font-size:0.82rem; color:var(--muted); }
.steps .step { display:flex; align-items:center; gap:10px; padding:5px 0; transition:color 0.3s; }
.steps .step.active { color:var(--accent); font-weight:700; }
.steps .step.done { color:#10b981; font-weight:700; }
.steps .step i { width:18px; font-size:0.75rem; }
</style>
</head>
<body>
<div class="card-box">
    <!-- GENERATING -->
    <div id="stateGenerating">
        <div class="icon-circle"><i class="fa-solid fa-file-pdf"></i><div class="spinner"></div></div>
        <h1>Generating Dossier...</h1>
        <div class="ref-line"><?= htmlspecialchars($mainRef) ?></div>
        <div class="sub">Building your complete dossier. Combining Sinhala text, attachments and watermarks into a single PDF.</div>
        <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
        <div class="status-text" id="statusText">Initialising…</div>
        <div class="steps" id="stepsBlock">
            <div class="step" data-step="1"><i class="fa-solid fa-circle-notch"></i> Loading document chain</div>
            <div class="step" data-step="2"><i class="fa-solid fa-circle-notch"></i> Rendering pages (Chrome)</div>
            <div class="step" data-step="3"><i class="fa-solid fa-circle-notch"></i> Merging attachments &amp; watermark</div>
            <div class="step" data-step="4"><i class="fa-solid fa-circle-notch"></i> Finalising PDF</div>
        </div>
    </div>

    <!-- SUCCESS -->
    <div id="stateSuccess" style="display:none;">
        <div class="icon-circle success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h1>Dossier Ready!</h1>
        <div class="ref-line"><?= htmlspecialchars($mainRef) ?></div>
        <div class="sub">Your PDF is ready. Click the button below to download it.</div>
        <a href="#" id="downloadBtn" class="btn-download"><i class="fa-solid fa-download"></i> <span>Download PDF</span></a>
        <div id="fileMeta" class="file-meta"></div>
        <div>
            <a href="dossier_hub.php?type=<?= htmlspecialchars($type) ?>&id=<?= (int)$id ?>" class="btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Dossier Hub
            </a>
        </div>
    </div>

    <!-- ERROR -->
    <div id="stateError" style="display:none;">
        <div class="icon-circle" style="background:rgba(239,68,68,0.12);color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h1>Generation Failed</h1>
        <div class="error-msg" id="errorMsg">Unknown error.</div>
        <div><button onclick="location.reload()" class="btn-download" style="background:#ef4444;"><i class="fa-solid fa-rotate-right"></i> <span>Try Again</span></button></div>
        <div><a href="dossier_hub.php?type=<?= htmlspecialchars($type) ?>&id=<?= (int)$id ?>" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a></div>
    </div>
</div>

<script>
(function(){ document.documentElement.setAttribute('data-theme', localStorage.getItem('dispatch_theme') || 'dark'); })();

const PARAMS = { type: <?= json_encode($type) ?>, id: <?= (int)$id ?>, scope: <?= json_encode($scope) ?> };
const $steps = document.querySelectorAll('.steps .step');
const $bar = document.getElementById('progressBar');
const $status = document.getElementById('statusText');

let p = 5;
const t1 = setInterval(() => { if (p < 88) { p += Math.random() * 4; $bar.style.width = Math.min(p, 88) + '%'; } }, 400);
let si = 0;
const t2 = setInterval(() => {
    if (si < $steps.length - 1) {
        $steps[si].classList.remove('active'); $steps[si].classList.add('done');
        $steps[si].querySelector('i').className = 'fa-solid fa-circle-check';
        si++; $steps[si].classList.add('active');
        $status.textContent = $steps[si].textContent.trim() + '…';
    }
}, 1300);
$steps[0].classList.add('active');

fetch(`generate_full_dossier.php?action=build&type=${PARAMS.type}&id=${PARAMS.id}&scope=${PARAMS.scope}`, { credentials:'same-origin' })
  .then(r => r.json())
  .then(data => {
    clearInterval(t1); clearInterval(t2); $bar.style.width = '100%';
    if (data.status !== 'ok') { showError(data.error || 'Unknown error'); return; }
    $steps.forEach(s => { s.classList.remove('active'); s.classList.add('done'); s.querySelector('i').className = 'fa-solid fa-circle-check'; });
    $status.textContent = 'Complete!';
    setTimeout(() => {
        document.getElementById('stateGenerating').style.display = 'none';
        document.getElementById('stateSuccess').style.display = 'block';
        document.getElementById('downloadBtn').href =
            `generate_full_dossier.php?action=download&type=${PARAMS.type}&id=${PARAMS.id}&scope=${PARAMS.scope}&token=${encodeURIComponent(data.token)}`;
        const kb = Math.round(data.size / 1024);
        let meta = kb > 1024 ? (kb/1024).toFixed(2) + ' MB' : kb + ' KB';
        if (data.pages) meta += ' · ' + data.pages + ' pages';
        if (data.cached) meta += ' · from cache';
        document.getElementById('fileMeta').textContent = meta;
    }, 400);
  })
  .catch(err => { clearInterval(t1); clearInterval(t2); showError('Network error: ' + err.message); });

function showError(msg) {
    document.getElementById('stateGenerating').style.display = 'none';
    document.getElementById('stateSuccess').style.display = 'none';
    document.getElementById('stateError').style.display = 'block';
    document.getElementById('errorMsg').textContent = msg;
}
</script>
</body>
</html>