<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();
if (!isset($_SESSION['user_id'])) die('Session Invalid');
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Record Room | IDCMS Manual</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="assets/manual.css" rel="stylesheet">
<style>
.m-tabs { display:flex; gap:4px; margin:24px 0 20px; border-bottom:2px solid var(--border); overflow-x:auto; scrollbar-width:none; }
.m-tabs::-webkit-scrollbar { display:none; }
.m-tab { padding:12px 20px; background:transparent; border:none; color:var(--muted); font-weight:700; font-size:0.88rem; cursor:pointer; border-bottom:3px solid transparent; transition:0.2s; white-space:nowrap; font-family:inherit; display:inline-flex; align-items:center; gap:8px; margin-bottom:-2px; }
.m-tab:hover { color:var(--accent); }
.m-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.m-tab-panel { display:none; animation:fadeIn 0.25s ease; }
.m-tab-panel.active { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px);} to{opacity:1;transform:translateY(0);} }

.workflow { display:flex; align-items:stretch; gap:0; margin:20px 0; flex-wrap:wrap; justify-content:center; }
.wf-step { flex:1; min-width:140px; background:var(--card); border:2px solid var(--border); border-radius:14px; padding:16px 12px; text-align:center; margin:6px 4px; }
.wf-step .wf-icon { width:44px; height:44px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
.wf-step .wf-title { font-weight:800; font-size:0.8rem; margin-bottom:2px; }
.wf-step .wf-desc { font-size:0.7rem; color:var(--muted); }
.wf-step.create .wf-icon { background: #4318FF; }
.wf-step.active .wf-icon { background: #00C853; }
.wf-step.issued .wf-icon { background: #f59e0b; }
.wf-step.returned .wf-icon { background: #10b981; }
.wf-step.archived .wf-icon { background: #6b7280; }

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.active    { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.archived  { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.issued    { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.returned  { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.overdue   { background:rgba(239,68,68,0.15); color: #ef4444; }

.perm-code { display:inline-block; background:var(--code-bg); color:var(--accent); padding:4px 12px; border-radius:6px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; border:1px solid var(--code-border); margin:2px 4px 2px 0; }
</style>
</head>
<body>
<div class="reading-progress" id="readingProgress"></div>
<div class="m-overlay" id="sidebarOverlay"></div>

<aside class="m-sidebar">
    <div class="m-brand"><i class="fa-solid fa-shield-halved"></i><span>IDCMS</span></div>
    <div class="m-brand-sub">User Manual</div>
    <div class="m-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="sidebarSearch" placeholder="Search sections..."></div>
    <nav class="m-nav">
        <a href="index.php"><i class="fa-solid fa-house"></i><span>Manual Home</span></a>
        <div class="m-section-label">Getting Started</div>
        <a href="01-getting-started.php"><i class="fa-solid fa-rocket"></i><span>Getting Started</span></a>
        <div class="m-section-label">Dispatching</div>
        <a href="02-internal-letters.php"><i class="fa-solid fa-envelope-open-text"></i><span>Internal Letters</span></a>
        <a href="03-outward-letters.php"><i class="fa-solid fa-paper-plane"></i><span>Outward Letters</span></a>
        <a href="04-task-letters.php"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>
        <div class="m-section-label">Records &amp; Disposal</div>
        <a href="05-final-disposal.php"><i class="fa-solid fa-box-archive"></i><span>Final Disposal</span></a>
        <a href="06-record-room.php" class="active"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="07-linkage-reports.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>
        <div class="m-section-label">Administration</div>
        <a href="08-informers.php"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
        <a href="09-administration.php"><i class="fa-solid fa-user-shield"></i><span>Administration</span></a>
        <div class="m-section-label">Help</div>
        <a href="10-help.php"><i class="fa-solid fa-life-ring"></i><span>Help &amp; Glossary</span></a>
        <a href="../dashboard/dashboard.php" class="m-back"><i class="fa-solid fa-arrow-left"></i><span>Back to Dashboard</span></a>
    </nav>
</aside>

<main class="m-main">
    <div class="m-topbar">
        <nav class="m-breadcrumbs">
            <a href="../dashboard/dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="index.php">Manual</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span class="current">Record Room</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-warehouse"></i> Records &amp; Disposal</span>
        <h1>Record Room / E-Files</h1>
        <p>භෞතික ගොනු සෑදීම, කළමනාකරණය, නිකුත් කිරීම සහ ආපසු ලබා ගැනීම පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 22 min read</span>
            <span><i class="fa-solid fa-signal"></i> INTERMEDIATE</span>
            <span><i class="fa-solid fa-language"></i> වාර්තා කාමරය</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-add"><i class="fa-solid fa-plus"></i> Add File</button>
        <button class="m-tab" data-tab="tab-list"><i class="fa-solid fa-list"></i> File List</button>
        <button class="m-tab" data-tab="tab-details"><i class="fa-solid fa-eye"></i> File Details</button>
        <button class="m-tab" data-tab="tab-issue"><i class="fa-solid fa-share-square"></i> Issue &amp; Return</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Record Room / E-Files</strong> module එක මගින් ආයතනයේ <strong>භෞතික ගොනු (Physical Files)</strong> කළමනාකරණය කරයි. ලිපි Final Disposal එකෙන් ගොනු කළ පසු, ඒවා මෙම ගොනු වලට සම්බන්ධ වේ. එමෙන්ම පුද්ගලයින්ට ගොනු <strong>නිකුත් කිරීම සහ ආපසු ලබා ගැනීම</strong> ද සිදු කරයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 5ක්</span></div>
            <ul class="m-steps">
                <li><strong>Add File</strong> — නව භෞතික ගොනුවක් ලියාපදිංචි කිරීම</li>
                <li><strong>File List</strong> — සියලුම ගොනු දැක බලා කළමනාකරණය (Filters + Edit + Delete + Excel)</li>
                <li><strong>File Details</strong> — එක් ගොනුවක සම්පූර්ණ විස්තර + එහි ඇති සියලුම ලිපි (Folios)</li>
                <li><strong>Issue File</strong> — ගොනු පුද්ගලයින්ට නිකුත් කිරීම (Bulk mode සමඟ)</li>
                <li><strong>Return File</strong> — නිකුත් කළ ගොනු ආපසු ලබා ගැනීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>ප්‍රධාන භූමිකා</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Role</th><th>භූමිකාව</th><th>කරන කටයුතු</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Record Keeper</strong></td><td>වාර්තා සංරක්ෂක</td><td>ගොනු සෑදීම, යාවත්කාලීන කිරීම, ඉවත් කිරීම</td></tr>
                        <tr><td><strong>Records Officer</strong></td><td>වාර්තා නිලධාරී</td><td>ගොනු නිකුත් කිරීම සහ ආපසු ලබා ගැනීම</td></tr>
                        <tr><td><strong>Officer</strong></td><td>නිලධාරී</td><td>ගොනු ලබා ගැනීම, බැලීම</td></tr>
                        <tr><td><strong>Administrator</strong></td><td>පරිපාලක</td><td>සියලුම ක්‍රියාකාරකම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>Status Types</span></div>
            <p style="margin-bottom:10px;font-size:0.9rem;"><strong>ගොනු වල තත්ත්වය:</strong></p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip active"><i class="fa-solid fa-check"></i> Active</span>
                <span class="chip archived"><i class="fa-solid fa-box"></i> Archived</span>
            </div>
            <p style="margin-top:16px;margin-bottom:10px;font-size:0.9rem;"><strong>නිකුතු වල තත්ත්වය:</strong></p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip issued"><i class="fa-solid fa-clock"></i> Issued</span>
                <span class="chip returned"><i class="fa-solid fa-undo"></i> Returned</span>
                <span class="chip overdue"><i class="fa-solid fa-exclamation"></i> Overdue</span>
            </div>

            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Status</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Active</strong></td><td>ගොනුව සක්‍රීයව භාවිතයේ ඇත — නිකුත් කළ හැක</td></tr>
                        <tr><td><strong>Archived</strong></td><td>ගොනුව සංරක්ෂිතයි — නිකුත් කළ නොහැක</td></tr>
                        <tr><td><strong>Issued</strong></td><td>ගොනුව පුද්ගලයෙකුට නිකුත් කර ඇත</td></tr>
                        <tr><td><strong>Returned</strong></td><td>නිකුත් කළ ගොනුව ආපසු ලබා දී ඇත</td></tr>
                        <tr><td><strong>Overdue</strong></td><td>ආපසු දිය යුතු දිනය පසු වී ඇත</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-tree"></i><span>ගොනු වල ව්‍යුහය</span></div>
            <p>භෞතික ලෝකයේ ගොනු පවතින ආකාරය:</p>
            <div class="m-code" data-lang="structure">
Record Room
    └── Rack 01
        ├── Row 01
        │   ├── File: ADM-2026-001 (Vol 1)
        │   ├── File: ADM-2026-002 (Vol 1)
        │   └── File: FIN-2026-001 (Vol 2)
        ├── Row 02
        │   ├── File: HR-2026-001
        │   └── File: HR-2026-002
        └── Row 03
            └── File: LEG-2026-001

File එකක් ඇතුළේ:
    ├── Folio 1: REG/2026/13 - යුද හමුදා වාර්තාව
    ├── Folio 2: REG/2026/14 - තොරතුරු වාර්තාව
    └── Folio 3: REG/2026/15 - උපදෙස් ලිපිය</div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: ADD FILE ═══════════════ -->
    <div class="m-tab-panel" id="tab-add">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Overview</span></div>
            <p>නව භෞතික ගොනුවක් ලියාපදිංචි කිරීමට <code>add_file.php</code> page එක භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Record Room → Add File</code> තෝරන්න.</li>
                <li>
                    <strong>File Reference</strong> — ගොනුවේ අනන්‍ය හැඳුනුම්පත ඇතුළත් කරන්න.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Format</strong>
                            <p>හොඳ පිළිවෙල: <code>PREFIX-YEAR-NUMBER</code> උදා: <code>ADM-2026-001</code>, <code>FIN-2026-042</code></p>
                        </div>
                    </div>
                </li>
                <li><strong>Volume</strong> — ගොනුවේ වෙළුම් අංකය ලියන්න (උදා: <code>Vol 1</code>).</li>
                <li><strong>Rack No</strong> — ගොනුව තබා ඇති රාක්කය (උදා: <code>Rack 01</code>).</li>
                <li><strong>Row No</strong> — රාක්කයේ පේළිය (උදා: <code>Row 03</code>).</li>
                <li><strong>Desk</strong> — අදාළ ඩෙස්කය තෝරන්න (optional).</li>
                <li><strong>About File</strong> — කෙටි විස්තරයක් ලියන්න (optional).</li>
                <li><strong>Save Record</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>ලියාපදිංචි කිරීමෙන් පසු, ඔබව <strong>File List</strong> එකට ගෙන යනු ලැබේ. එහි අලුත් ගොනුව පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th><th>උදාහරණය</th></tr></thead>
                    <tbody>
                        <tr><td><code>file_reference</code></td><td>ගොනුවේ අනන්‍ය හැඳුනුම්පත</td><td><span class="required">Required</span></td><td>ADM-2026-001</td></tr>
                        <tr><td><code>volume</code></td><td>වෙළුම් අංකය</td><td><span class="required">Required</span></td><td>Vol 1</td></tr>
                        <tr><td><code>rack_no</code></td><td>රාක්කය</td><td><span class="required">Required</span></td><td>Rack 01</td></tr>
                        <tr><td><code>row_no</code></td><td>පේළිය</td><td><span class="required">Required</span></td><td>Row 03</td></tr>
                        <tr><td><code>desk_id</code></td><td>අදාළ ඩෙස්කය</td><td><span class="optional">Optional</span></td><td>Admin Desk</td></tr>
                        <tr><td><code>about_file</code></td><td>විස්තරය</td><td><span class="optional">Optional</span></td><td>2026 Administration files</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>වැදගත්</strong>
                <ul>
                    <li><strong>File Reference</strong> එක සුවිශේෂී විය යුතුයි — දෙපාරක් නොවිය යුතුයි</li>
                    <li>ලියාපදිංචි කළ පසු File List එකට redirect වේ</li>
                    <li>Desk එක නොදැම්මත් වැඩ කරයි — පස්සේ Edit කරන්න පුළුවන්</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: FILE LIST ═══════════════ -->
    <div class="m-tab-panel" id="tab-list">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>File List Page</span></div>
            <p>මෙය <code>file_list.php</code> page එකයි. සියලුම භෞතික ගොනු දකින්න, පෙරහන් කරන්න, සහ කළමනාකරණය කරන්න මෙය භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-filter"></i><span>Filters — 5ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Filter</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Search</strong></td><td>File Reference, Name, Volume, Rack අනුව සොයන්න</td></tr>
                        <tr><td><strong>Status</strong></td><td>All · Active · Archived</td></tr>
                        <tr><td><strong>Desk</strong></td><td>ඩෙස්කය අනුව පෙරහන් කරන්න</td></tr>
                        <tr><td><strong>Date Range</strong></td><td>From / To දින පරාසය</td></tr>
                        <tr><td><strong>My Files Only</strong></td><td>ඔබ සෑදූ ගොනු පමණක් පෙන්වයි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>File Reference</strong></td><td>ලින්ක් එකක් — click කළ විට Details page එකට</td></tr>
                        <tr><td><strong>File Name</strong></td><td>ගොනුවේ නම (optional)</td></tr>
                        <tr><td><strong>Volume</strong></td><td>වෙළුම් අංකය</td></tr>
                        <tr><td><strong>Rack No</strong></td><td>රාක්කය</td></tr>
                        <tr><td><strong>Row No</strong></td><td>පේළිය</td></tr>
                        <tr><td><strong>Desk</strong></td><td>අදාළ ඩෙස්කය</td></tr>
                        <tr><td><strong>Status</strong></td><td>ACTIVE / ARCHIVED badge</td></tr>
                        <tr><td><strong>Created By</strong></td><td>ගොනුව සෑදූ පුද්ගලයා</td></tr>
                        <tr><td><strong>Created Date</strong></td><td>දිනය සහ වේලාව</td></tr>
                        <tr><td><strong>Edited By</strong></td><td>අවසන් වරට edit කළ පුද්ගලයා + දිනය</td></tr>
                        <tr><td><strong>Actions</strong></td><td>View · Edit · Delete බොත්තම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Row Actions</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Action</th><th>අයිකනය</th><th>කරන්නේ කුමක්ද</th><th>Permission</th></tr></thead>
                    <tbody>
                        <tr><td><strong>View</strong></td><td><i class="fa-solid fa-eye"></i></td><td>File Details page එකට</td><td>VIEW_FILE_RECORDS</td></tr>
                        <tr><td><strong>Edit</strong></td><td><i class="fa-solid fa-pen"></i></td><td>Modal එකකින් ගොනුව සංශෝධනය</td><td>EDIT_FILE_RECORD</td></tr>
                        <tr><td><strong>Delete</strong></td><td><i class="fa-solid fa-trash"></i></td><td>Soft delete (is_deleted = 1)</td><td>DELETE_FILE_RECORD</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-pen-to-square"></i><span>Edit Modal</span></div>
            <p>Edit බොත්තම click කළ විට modal එකක් විවෘත වේ. එහි පහත fields වෙනස් කළ හැක:</p>
            <ul class="m-steps">
                <li>File Reference</li>
                <li>File Name</li>
                <li>Volume · Rack · Row</li>
                <li>Desk</li>
                <li>Status (Active / Archived)</li>
                <li>About File</li>
            </ul>
            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Auto-tracked</strong>
                    <p>Edit කරන සෑම අවස්ථාවකම <code>edited_by</code> සහ <code>edited_at</code> ස්වයංක්‍රීයව සටහන් වේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-excel"></i><span>Excel Export</span></div>
            <p><strong>Excel</strong> බොත්තම (කොළ පැහැති) ක්ලික් කළ විට:</p>
            <ul class="m-steps">
                <li>දැනට පෙරහන් කර ඇති ගොනු ලැයිස්තුවක් <code>.xls</code> ගොනුවක් ලෙස බාගත වේ</li>
                <li>Column 11ක් ඇතුළත්: File Ref, Name, Volume, Rack, Row, Desk, Status, Created By/Date, Edited By/Date</li>
                <li>Excel හෝ Google Sheets වලින් open කළ හැක</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-trash"></i><span>Soft Delete</span></div>
            <p>Delete බොත්තම ක්ලික් කළ විට තහවුරු කිරීමේ පණිවිඩයක් එයි. තහවුරු කළ පසු:</p>
            <ul class="m-steps">
                <li>ගොනුව සැබවින්ම ඉවත් නොවේ — <code>is_deleted = 1</code> ලෙස සටහන් වේ</li>
                <li>ලැයිස්තුවෙන් ඉවත් වේ</li>
                <li>දත්ත ගබඩාවේ තවමත් ඇත (audit සඳහා)</li>
                <li>පද්ධති පරිපාලකට DB එකෙන් නැවත ලබා ගත හැක</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: FILE DETAILS ═══════════════ -->
    <div class="m-tab-panel" id="tab-details">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>File Details Page</span></div>
            <p><code>file_details.php</code> page එක එක් ගොනුවක සම්පූර්ණ විස්තර පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 4ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Folios</strong></td><td>ගොනුවේ ඇති මුළු ලිපි ගණන</td></tr>
                        <tr><td><strong>Urgent</strong></td><td>Urgent / Immediately ලිපි ගණන</td></tr>
                        <tr><td><strong>Pending</strong></td><td>තවම ක්‍රියාත්මක වන ලිපි</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සම්පූර්ණ වූ ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder"></i><span>File Metadata</span></div>
            <p>ගොනුවේ මූලික තොරතුරු:</p>
            <ul class="m-steps">
                <li><strong>Volume · Rack No · Row No</strong> — භෞතික ස්ථානය</li>
                <li><strong>Desk</strong> — අදාළ ඩෙස්කය</li>
                <li><strong>Status</strong> — Active / Archived badge</li>
                <li><strong>About File</strong> — විස්තරය</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user"></i><span>User Info — කවුරුන් සෑදූවාද</span></div>
            <p>දකුණු පැත්තේ පහත තොරතුරු පෙන්වයි:</p>
            <ul class="m-steps">
                <li><strong>Created By</strong> — නම, Service Number, Rank, දිනය</li>
                <li><strong>Last Updated By</strong> — අවසන් වරට edit කළ පුද්ගලයා</li>
                <li>Edit නොකළ නම් — "No edits recorded"</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Folios Table</span></div>
            <p>ගොනුවේ ඇති සියලුම ලිපි (Folios) ලැයිස්තුවක් ලෙස පෙන්වයි. මෙම ලිපි Final Disposal ක්‍රියාවලිය හරහා ගොනුවට එකතු වූ ඒවායි.</p>

            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Folio No</strong></td><td>ගොනුවේ ලිපියේ අනුක්‍රමික අංකය (උදා: Folio No: 1)</td></tr>
                        <tr><td><strong>CDR Number</strong></td><td>ලිපියේ ලියාපදිංචි අංකය</td></tr>
                        <tr><td><strong>Title</strong></td><td>ලිපියේ මාතෘකාව</td></tr>
                        <tr><td><strong>Date</strong></td><td>ලිපියේ දිනය</td></tr>
                        <tr><td><strong>Priority</strong></td><td>හදිසි මට්ටම (වර්ණ badge)</td></tr>
                        <tr><td><strong>Status</strong></td><td>ලිපියේ තත්ත්වය</td></tr>
                        <tr><td><strong>Action</strong></td><td>Dossier බොත්තම — PDF සාදන්න</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="m-callout info">
                <i class="fa-solid fa-print"></i>
                <div>
                    <strong>Print Support</strong>
                    <p>මෙම page එක <strong>Print එකට සකසන ලදි</strong>. Sidebar, බොත්තම් සහ stat cards නොපෙනේ — කේවල් විස්තරය පමණක් මුද්‍රණය වේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: ISSUE & RETURN ═══════════════ -->
    <div class="m-tab-panel" id="tab-issue">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-share-square"></i><span>File Issuance — ගොනු නිකුත් කිරීම</span></div>
            <p><code>issue_file.php</code> page එක මගින් ගොනු පුද්ගලයින්ට නිකුත් කිරීම සහ ආපසු ලබා ගැනීම කළමනාකරණය කරයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 3ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Currently Issued</strong></td><td>දැනට නිකුත් කර ඇති ගොනු (තව ආපසු නොලැබූ)</td></tr>
                        <tr><td><strong>Overdue</strong></td><td>ආපසු දිය යුතු දිනය පසු වූ</td></tr>
                        <tr><td><strong>Returned</strong></td><td>ආපසු ලබා දුන් මුළු ගොනු</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-plus-circle"></i><span>Bulk Issue — ගොනු කිහිපයක් නිකුත් කිරීම</span></div>
            <p>මෙම page එකේ <strong>Bulk Mode</strong> — එකවර ගොනු කිහිපයක් නිකුත් කළ හැක.</p>

            <h6 style="margin-top:16px;font-weight:800;color:var(--text-strong);">පියවර:</h6>
            <ol class="m-steps">
                <li>
                    <strong>Right panel එකේ</strong> Available Files table එකේ ඔබට නිකුත් කළ යුතු ගොනු සොයන්න.
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Search box එකෙන් Ref / Name / Volume / Rack අනුව සොයන්න</li>
                        <li><strong>Desk Filter</strong> එකෙන් නිශ්චිත ඩෙස්කයේ ගොනු පමණක් පෙන්වන්න</li>
                    </ul>
                </li>
                <li>
                    <strong>Add</strong> බොත්තම ඔබා ගොනුව queue එකට එකතු කරන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <p>එකතු කළ පසු බොත්තම <em>"Added"</em> ලෙස වෙනස් වේ. Queue එකේ වම් පැත්තේ පෙන්වයි.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Selected Files Queue</strong> — එකතු කළ ගොනු පෙන්වයි. ඕනෑම එකක් <i class="fa-solid fa-times"></i> අයිකනයෙන් ඉවත් කරන්න පුළුවන්.
                </li>
                <li><strong>Issue To (User)</strong> — ගොනු ලබා දෙන පුද්ගලයා තෝරන්න.</li>
                <li><strong>Due Date</strong> — ආපසු දිය යුතු දිනය (optional).</li>
                <li><strong>Remarks</strong> — අමතර සටහන් (optional).</li>
                <li><strong>Issue Selected Files</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>නියමිත සීමාවන්</strong>
                    <ul>
                        <li>දැනටමත් <strong>Issued</strong> වූ ගොනු Available list එකේ නොපෙනේ</li>
                        <li><strong>Archived</strong> ගොනු නිකුත් කළ නොහැක</li>
                        <li>Duplicate එකතු කිරීම වලක්වයි — "Added" ලෙස පෙන්වයි</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Issued Files Log</span></div>
            <p>පහළින්, සියලුම නිකුතු වල ඉතිහාසය පෙන්වයි:</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>File Reference</strong></td><td>ගොනුවේ Ref + නම</td></tr>
                        <tr><td><strong>Issued To</strong></td><td>ලබා දුන් පුද්ගලයා + Service Number</td></tr>
                        <tr><td><strong>Issued By</strong></td><td>නිකුත් කළ නිලධාරියා</td></tr>
                        <tr><td><strong>Issue Date</strong></td><td>නිකුත් කළ දිනය</td></tr>
                        <tr><td><strong>Due Date</strong></td><td>ආපසු දිය යුතු දිනය</td></tr>
                        <tr><td><strong>Status</strong></td><td>ISSUED / RETURNED / OVERDUE</td></tr>
                        <tr><td><strong>Action</strong></td><td>Return බොත්තම (Issued නම් පමණයි)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-undo"></i><span>Return File — ගොනුව ආපසු ලබා ගැනීම</span></div>
            <ol class="m-steps">
                <li>Issued Files Log එකේ අදාළ ගොනුවේ <strong>Return</strong> බොත්තම ඔබන්න.</li>
                <li>Modal එකේ ආපසු දෙන විස්තර ලියන්න:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Return Remarks</strong> — ගොනුවේ තත්ත්වය (උදා: "Returned in good condition")</li>
                    </ul>
                </li>
                <li><strong>Confirm Return</strong> බොත්තම ඔබන්න.</li>
                <li>ගොනුව නැවත Available list එකට එකතු වේ — යළි නිකුත් කළ හැක.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Auto Timestamp</strong>
                    <p>Return කළ වේලාව <code>return_date</code> ලෙස ස්වයංක්‍රීයව සටහන් වේ. පැරණි remarks එකට එකතු වේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-exclamation-triangle"></i><span>Overdue Tracking</span></div>
            <p>Due Date පසු වූ ගොනු <strong>රතු OVERDUE badge</strong> එකකින් පෙන්වයි. මෙය ස්වයංක්‍රීයව ගණනය වේ.</p>
            <div class="m-code" data-lang="logic">
IF status = 'Issued' AND due_date < today:
    Display = 'OVERDUE' (රතු)
ELSE IF status = 'Issued':
    Display = 'ISSUED' (තැඹිලි)
ELSE:
    Display = 'RETURNED' (කොළ)</div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>File Lifecycle</span></div>
            <div class="workflow">
                <div class="wf-step create">
                    <div class="wf-icon"><i class="fa-solid fa-plus"></i></div>
                    <div class="wf-title">1. Create</div>
                    <div class="wf-desc">ගොනුව ලියාපදිංචි කරයි</div>
                </div>
                <div class="wf-step active">
                    <div class="wf-icon"><i class="fa-solid fa-check"></i></div>
                    <div class="wf-title">2. Active</div>
                    <div class="wf-desc">සක්‍රීයව භාවිතයේ</div>
                </div>
                <div class="wf-step issued">
                    <div class="wf-icon"><i class="fa-solid fa-share"></i></div>
                    <div class="wf-title">3. Issued</div>
                    <div class="wf-desc">පුද්ගලයෙකුට ලබා දුන්නා</div>
                </div>
                <div class="wf-step returned">
                    <div class="wf-icon"><i class="fa-solid fa-undo"></i></div>
                    <div class="wf-title">4. Returned</div>
                    <div class="wf-desc">ආපසු ලබා ගත්තා</div>
                </div>
                <div class="wf-step archived">
                    <div class="wf-icon"><i class="fa-solid fa-box"></i></div>
                    <div class="wf-title">5. Archived</div>
                    <div class="wf-desc">සංරක්ෂිතයි</div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-route"></i><span>සම්පූර්ණ ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>Add File</strong> — නව ගොනුවක් ලියාපදිංචි කිරීම (Status: Active)</li>
                <li><strong>Folios එකතු වීම</strong> — Final Disposal ක්‍රියාවලියෙන් ලිපි ගොනුවට එකතු වේ</li>
                <li><strong>Issue File</strong> — අවශ්‍ය අවස්ථාවලදී පුද්ගලයින්ට ලබා දීම (Status: Issued)</li>
                <li><strong>Return File</strong> — නිකුත් කළ ගොනු ආපසු ලබා ගැනීම (Status: Returned)</li>
                <li><strong>Archive</strong> — තව භාවිත නොකරන ගොනු සංරක්ෂණය (Status: Archived)</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>මැදිහත් වන Tables</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Table</th><th>කරන කාර්යය</th></tr></thead>
                    <tbody>
                        <tr><td><code>physical_file_records</code></td><td>ගොනු වල මූලික තොරතුරු</td></tr>
                        <tr><td><code>file_issuances</code></td><td>නිකුතු සහ ආපසු ලබා ගැනීම්</td></tr>
                        <tr><td><code>internal_letters</code></td><td>ගොනුවට ඇතුළත් ලිපි</td></tr>
                        <tr><td><code>internal_letters_final_disposal_details</code></td><td>ලිපිය ගොනුවට ඇතුළත් කළ විස්තර (folio අංකය)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>Final Disposal සමඟ සම්බන්ධය</span></div>
            <p>Final Disposal ක්‍රියාවලියේදී ලිපියක් භෞතික ගොනුවකට ඇතුළත් කළ විට:</p>
            <ul class="m-steps">
                <li><strong>Folio අංකය</strong> — ගොනුවේ අනුක්‍රමික අංකය ස්වයංක්‍රීයව ජනනය වේ</li>
                <li><strong>File Reference</strong> — ලිපිය ගොනුවට සම්බන්ධ වේ</li>
                <li><strong>File Details page</strong> — එම ගොනුවේ Folios table එකේ පෙන්වයි</li>
                <li><strong>Dossier බොත්තම</strong> — හැම folio එකකටම PDF සාදන්න පුළුවන්</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 7: PERMISSIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-perms">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permission Codes</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Permission</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="perm-code">ADD_FILE_RECORD</span></td>
                            <td>නව ගොනු ලියාපදිංචි කිරීම</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">VIEW_FILE_RECORDS</span></td>
                            <td>ගොනු ලැයිස්තුව බැලීම සහ Excel export</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">EDIT_FILE_RECORD</span></td>
                            <td>ගොනු සංශෝධනය කිරීම</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">DELETE_FILE_RECORD</span></td>
                            <td>ගොනු ඉවත් කිරීම (soft delete)</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">VIEW_INTERNAL_LETTERS</span></td>
                            <td>File Details සහ Issue File page වලට ප්‍රවේශය</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Permission Matrix</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Officer</th>
                            <th>Record Keeper</th>
                            <th>Records Officer</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Add File</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View File List</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View File Details</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Edit File</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Delete File</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Excel Export</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Issue File</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Return File</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Access Denied පෙන්වයි නම්</strong>
                <p>ඔබට අදාළ permission එක නැහැ. ඔබේ පද්ධති පරිපාලක අමතා <strong>Permission Codes</strong> page එකෙන් එය ලබා ගන්න.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="06-record-room" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="05-final-disposal.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Final Disposal</span></a>
        <a href="07-linkage-reports.php" class="next"><span class="lbl">Next →</span><span class="ttl">Linkage Reports</span></a>
    </div>
</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
<script src="assets/manual.js"></script>
<script>
document.querySelectorAll('.m-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        document.querySelectorAll('.m-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.m-tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(target).classList.add('active');
        history.replaceState(null, '', '#' + target);
    });
});
if (location.hash) {
    const tab = document.querySelector('.m-tab[data-tab="' + location.hash.slice(1) + '"]');
    if (tab) tab.click();
}
</script>
</body>
</html>