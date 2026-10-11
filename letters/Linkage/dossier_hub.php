<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('	VIEW_OUTWARD_LETTERS')) die('Access Denied');

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'internal';
$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!in_array($type, ['internal', 'task', 'outward'])) $type = 'internal';

// Default fallback - first record
if ($id <= 0) {
    if ($type === 'internal') $r = $conn->query("SELECT id FROM internal_letters WHERE is_deleted = 0 ORDER BY id ASC LIMIT 1");
    elseif ($type === 'task') $r = $conn->query("SELECT id FROM task_letters ORDER BY id ASC LIMIT 1");
    else $r = $conn->query("SELECT id FROM outward_letters WHERE is_deleted = 0 ORDER BY id ASC LIMIT 1");
    $row = $r ? $r->fetch_assoc() : null;
    $id = $row ? (int)$row['id'] : 0;
}
if ($id <= 0) die('No records found');

// Fetch basic info
if ($type === 'internal') $s = $conn->prepare("SELECT cdr_number AS ref, letter_title AS title, letter_date AS date FROM internal_letters WHERE id = ? AND is_deleted = 0");
elseif ($type === 'task') $s = $conn->prepare("SELECT tsk_cdr_number AS ref, title, letter_date AS date FROM task_letters WHERE id = ?");
else $s = $conn->prepare("SELECT letter_ref AS ref, title, letter_date AS date FROM outward_letters WHERE id = ? AND is_deleted = 0");
$s->bind_param("i", $id); $s->execute();
$r = $s->get_result()->fetch_assoc();
if (!$r) die('Record not found');
$ref = $r['ref']; $title = $r['title']; $date = $r['date'];

$cfg = [
    'internal' => ['label'=>'Internal Letter', 'color'=>'#4318FF', 'icon'=>'fa-envelope-open-text'],
    'task'     => ['label'=>'Task Letter',     'color'=>'#f59e0b', 'icon'=>'fa-list-check'],
    'outward'  => ['label'=>'Outward Letter',  'color'=>'#10b981', 'icon'=>'fa-paper-plane'],
];
$c = $cfg[$type];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Download Dossier — <?= htmlspecialchars($ref) ?></title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<style>
:root { --bg:#F0F2F5; --card:#FFF; --text:#1B2559; --muted:#707EAE; --border:#DDE2EF; --accent:<?= $c['color'] ?>; }
[data-theme="dark"] { --bg:#0B1437; --card:#111C44; --text:#FFF; --muted:#A3AED0; --border:rgba(255,255,255,0.1); color-scheme:dark; }
* { box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:'Iskoola Pota','Noto Sans Sinhala','Segoe UI',system-ui,sans-serif; min-height:100vh; margin:0; padding:40px 20px; }
.wrap { max-width:960px; margin:0 auto; }
.head { text-align:center; margin-bottom:40px; }
.head .badge { display:inline-block; padding:6px 16px; background:var(--accent); color:#fff; border-radius:20px; font-size:0.75rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; }
.head h1 { font-size:2rem; font-weight:800; margin:16px 0 8px; }
.head p { color:var(--muted); font-size:0.95rem; margin:0; }
.grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:18px; }
.btn-card { background:var(--card); border:1px solid var(--border); border-radius:18px; padding:24px; text-decoration:none; color:var(--text); transition:0.25s; display:block; position:relative; overflow:hidden; }
.btn-card:hover { transform:translateY(-4px); box-shadow:0 12px 30px rgba(0,0,0,0.15); border-color:var(--accent); color:var(--text); }
.btn-card .ic { width:48px; height:48px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:14px; background:var(--accent); color:#fff; }
.btn-card h3 { font-size:1rem; font-weight:800; margin:0 0 6px; }
.btn-card p { font-size:0.8rem; color:var(--muted); margin:0; line-height:1.5; }
.btn-card .pill { display:inline-block; margin-top:10px; padding:3px 10px; background:var(--accent); color:#fff; border-radius:12px; font-size:0.68rem; font-weight:700; text-transform:uppercase; }
.footer { text-align:center; margin-top:40px; color:var(--muted); font-size:0.8rem; }
.footer a { color:var(--accent); text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div class="badge"><i class="fa-solid <?= $c['icon'] ?> me-2"></i><?= $c['label'] ?></div>
        <h1><?= htmlspecialchars($ref) ?></h1>
        <p><?= htmlspecialchars($title) ?> · <?= date('d M Y', strtotime($date)) ?></p>
    </div>

    <div class="grid">
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=all" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-file-pdf"></i></div>
            <h3>Complete Dossier</h3>
            <p>All related documents with full metadata and attachments. Most comprehensive option.</p>
            <span class="pill">Recommended</span>
        </a>
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=main" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-file-lines"></i></div>
            <h3>Main Document Only</h3>
            <p>Just this letter with its metadata and attachment — no linked records.</p>
        </a>
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=internal" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-envelope-open-text"></i></div>
            <h3>Internal Letters Only</h3>
            <p>All internal letter sections from the chain with attachments.</p>
        </a>
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=task" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-list-check"></i></div>
            <h3>Task Letters Only</h3>
            <p>All task letter sections with agencies, replies, and attachments.</p>
        </a>
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=outward" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-paper-plane"></i></div>
            <h3>Outward Letters Only</h3>
            <p>All outward letter sections with dispatch details and attachments.</p>
        </a>
        <a href="generate_full_dossier.php?type=<?= $type ?>&id=<?= $id ?>&scope=chain" target="_blank" class="btn-card">
            <div class="ic"><i class="fa-solid fa-diagram-project"></i></div>
            <h3>Metadata Only</h3>
            <p>All metadata and relationships without attachments. Fast export.</p>
            <span class="pill">Light</span>
        </a>
    </div>

    <div class="footer">
        <p><i class="fa-solid fa-shield-halved me-1"></i> DMI Dossier Hub · <a href="unified_details.php?type=<?= $type ?>&id=<?= $id ?>">Back to Details</a> · <a href="linkage_report.php">Linkage Report</a></p>
    </div>
</div>
<script>
(function(){ const t = localStorage.getItem('dispatch_theme') || 'dark'; document.documentElement.setAttribute('data-theme', t); })();
</script>
</body>
</html>