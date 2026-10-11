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
<title>Linkage Reports | IDCMS Manual</title>
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
.wf-step.find .wf-icon { background: #4318FF; }
.wf-step.view .wf-icon { background: #f59e0b; }
.wf-step.hub .wf-icon { background: #10b981; }
.wf-step.build .wf-icon { background: #3b82f6; }
.wf-step.download .wf-icon { background: #8b5cf6; }

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.internal { background:rgba(67,24,255,0.15); color: #4318FF; }
.chip.task     { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.outward  { background:rgba(16,185,129,0.15); color: #10b981; }

.perm-code { display:inline-block; background:var(--code-bg); color:var(--accent); padding:4px 12px; border-radius:6px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; border:1px solid var(--code-border); margin:2px 4px 2px 0; }

/* Dossier option card */
.dossier-option {
    display:flex; gap:14px; padding:16px;
    background:var(--bg); border-radius:12px;
    border-left:4px solid var(--accent); margin:10px 0;
    align-items:flex-start;
}
.dossier-option .opt-icon {
    width:44px; height:44px; border-radius:11px;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; flex-shrink:0;
}
.dossier-option .opt-body h6 { font-weight:800; margin:0 0 4px; color:var(--text-strong); font-size:0.92rem; }
.dossier-option .opt-body p { font-size:0.82rem; color:var(--muted); margin:0 0 6px; line-height:1.5; }
.dossier-option .opt-scope { font-family:'JetBrains Mono', monospace; font-size:0.72rem; color:var(--accent); background:var(--accent-soft); padding:2px 8px; border-radius:5px; display:inline-block; }
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
        <a href="06-record-room.php"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="07-linkage-reports.php" class="active"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>
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
            <span class="current">Linkage Reports</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-diagram-project"></i> Records &amp; Disposal</span>
        <h1>Linkage Reports &amp; Dossier</h1>
        <p>ලිපි අතර සම්බන්ධතා බැලීම, සම්පූර්ණ විස්තර දැක බලා, PDF dossier සාදා බාගත කිරීම පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 20 min read</span>
            <span><i class="fa-solid fa-signal"></i> ADVANCED</span>
            <span><i class="fa-solid fa-language"></i> සම්බන්ධතා වාර්තා</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-report"><i class="fa-solid fa-table"></i> Linkage Report</button>
        <button class="m-tab" data-tab="tab-details"><i class="fa-solid fa-eye"></i> Unified Details</button>
        <button class="m-tab" data-tab="tab-hub"><i class="fa-solid fa-folder-open"></i> Dossier Hub</button>
        <button class="m-tab" data-tab="tab-pdf"><i class="fa-solid fa-file-pdf"></i> PDF Generation</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Linkage Reports</strong> module එක මගින්, ලිපි අතර ඇති <strong>සම්බන්ධතා (relationships)</strong> සොයා බලා, ඒවා එකට එකතු කර, PDF <strong>Dossier</strong> එකක් ලෙස බාගත කිරීමට ඉඩ සලසයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 4ක්</span></div>
            <ul class="m-steps">
                <li><strong>Linkage Report</strong> — සියලුම ලිපි වර්ග 3ක (Internal, Outward, Task) ලැයිස්තුව සහ ඒවා අතර සම්බන්ධතා</li>
                <li><strong>Unified Details</strong> — එක් ලිපියක සම්පූර්ණ විස්තර (minute trail, disposal, replies, linked records)</li>
                <li><strong>Dossier Hub</strong> — ලිපියට අදාළ PDF එකක් බාගත කිරීමේ මධ්‍යස්ථානය</li>
                <li><strong>PDF Generation</strong> — Chrome + FPDI + Watermark භාවිතයෙන් සම්පූර්ණ dossier එක සාදයි</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>ලිපි අතර සම්බන්ධතා 4ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>From</th><th>To</th><th>සම්බන්ධය</th></tr></thead>
                    <tbody>
                        <tr><td>Internal Letter</td><td>Task Letter</td><td>මෙම ලිපියට අදාළ කාර්යය</td></tr>
                        <tr><td>Internal Letter</td><td>Outward Letter</td><td>මෙම ලිපියෙන් යවන ලද බාහිර ලිපිය</td></tr>
                        <tr><td>Task Letter</td><td>Internal Letter</td><td>කාර්යයට ලැබුණු පිළිතුරු ලිපි</td></tr>
                        <tr><td>Outward Letter</td><td>Internal Letter</td><td>බාහිර ලිපියට මූලාශ්‍ර වූ ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>ලිපි වර්ග 3ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip internal"><i class="fa-solid fa-envelope-open-text"></i> Internal</span>
                <span class="chip task"><i class="fa-solid fa-list-check"></i> Task</span>
                <span class="chip outward"><i class="fa-solid fa-paper-plane"></i> Outward</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Type</th><th>වර්ණය</th><th>ID Format</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Internal</strong></td><td style="color: #4318FF;">දම්</td><td><code>REG/2026/XX</code> හෝ <code>ADM/2026/XX</code></td></tr>
                        <tr><td><strong>Task</strong></td><td style="color: #f59e0b;">තැඹිලි</td><td><code>TSK/2026/XX</code></td></tr>
                        <tr><td><strong>Outward</strong></td><td style="color: #10b981;">කොළ</td><td><code>RPT/2026/XX</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Dossier යනු කුමක්ද?</strong>
                <p>Dossier එකක් යනු එක් ලිපියක් සහ එයට සම්බන්ධ <strong>සියලුම ලිපි</strong> එකම PDF ගොනුවකට එකතු කිරීමයි. ලිපි, minute trail, attachments, watermark සියල්ල ඇතුළත් වේ.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: LINKAGE REPORT ═══════════════ -->
    <div class="m-tab-panel" id="tab-report">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Linkage Report Page</span></div>
            <p><code>linkage_report.php</code> page එක මගින්, සියලුම ලිපි වර්ග 3ක ලැයිස්තුව සහ ඒවා අතර සම්බන්ධතා පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-open"></i><span>Tabs 3ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Tab</th><th>අඩංගු දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Outward</strong></td><td>බාහිර ලිපි + ඒවාට සම්බන්ධ Internal සහ Task ලිපි</td></tr>
                        <tr><td><strong>Internal</strong></td><td>අභ්‍යන්තර ලිපි + ඒවාට සම්බන්ධ Outward, Task + Final Disposal තත්ත්වය</td></tr>
                        <tr><td><strong>Tasks</strong></td><td>කාර්ය ලිපි + පැවරූ රෙජිමේන්තු + පිළිතුරු</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-filter"></i><span>Filters — 3ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Filter</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>From Date</strong></td><td>මෙම දිනයෙන් පසු ලිපි පමණයි</td></tr>
                        <tr><td><strong>To Date</strong></td><td>මෙම දිනයට පෙර ලිපි පමණයි</td></tr>
                        <tr><td><strong>Desk</strong></td><td>අදාළ ඩෙස්කය අනුව (Internal ලිපි සඳහා last forwarded desk)</td></tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:12px;font-size:0.88rem;color:var(--muted);">Filter කරන විට, ඉහළින් <strong>Active filters</strong> chips ලෙස පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <h6 style="margin-top:12px;font-weight:800;color:var(--text-strong);">Outward Tab:</h6>
            <p style="font-size:0.88rem;color:var(--muted);margin:6px 0;">Actions · Ref · CDR · Date · Title · Desk · Status · Linked Internals · Linked Tasks</p>

            <h6 style="margin-top:16px;font-weight:800;color:var(--text-strong);">Internal Tab:</h6>
            <p style="font-size:0.88rem;color:var(--muted);margin:6px 0;">Actions · CDR · Title · Regiment · Desk · Status · Priority · Last Forwarded To · Final Disposal</p>

            <h6 style="margin-top:16px;font-weight:800;color:var(--text-strong);">Tasks Tab:</h6>
            <p style="font-size:0.88rem;color:var(--muted);margin:6px 0;">Actions · Ref · CDR · Title · Desk · Status · Priority · Agencies · Replies</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Action</th><th>අයිකනය</th><th>කරන්නේ කුමක්ද</th></tr></thead>
                    <tbody>
                        <tr><td><strong>View</strong></td><td><i class="fa-solid fa-eye"></i></td><td>Unified Details page එකට</td></tr>
                        <tr><td><strong>PDF</strong></td><td><i class="fa-solid fa-file-pdf"></i></td><td>Dossier Hub එකට</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-excel"></i><span>Export Options</span></div>
            <ul class="m-steps">
                <li><strong>Excel</strong> — සියලුම ලිපි වර්ග 3ම sheets 3ක් ලෙස <code>.xlsx</code> ගොනුවක් ලෙස බාගත වේ</li>
                <li><strong>PDF</strong> — දැනට විවෘත tab එකේ table එක PDF එකක් ලෙස බාගත වේ</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>Linked Items Display</span></div>
            <p>"Linked Internals" සහ "Linked Tasks" columns වල ලිපි <strong>bulleted list</strong> ලෙස පෙන්වයි. හැම item එකක්ම:</p>
            <ul class="m-steps">
                <li><strong>CDR අංකය</strong> — ලිපියේ අනන්‍ය හැඳුනුම්පත</li>
                <li>එම ලිපියට click කර Unified Details page එකට යා හැක</li>
            </ul>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-language"></i>
            <div>
                <strong>Sinhala Support</strong>
                <p>සියලුම Sinhala text <code>Noto Sans Sinhala</code> font එකෙන් render වේ. <code>lang="si"</code> attribute එක යොදා ඇත — shaping හරියටම වැඩ කරයි.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: UNIFIED DETAILS ═══════════════ -->
    <div class="m-tab-panel" id="tab-details">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>Unified Details Page</span></div>
            <p><code>unified_details.php</code> page එක එකම layout එකෙන් ලිපි වර්ග 3ම පෙන්වයි. ලිපියේ වර්ගය අනුව <strong>වර්ණය සහ කොටස් වෙනස් වේ</strong>.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-palette"></i><span>Type අනුව වර්ණ සහ Icon</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Type</th><th>වර්ණය</th><th>Icon</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Internal</strong></td><td style="color: #4318FF;">දම්</td><td><i class="fa-solid fa-envelope-open-text"></i> fa-envelope-open-text</td></tr>
                        <tr><td><strong>Task</strong></td><td style="color: #f59e0b;">තැඹිලි</td><td><i class="fa-solid fa-list-check"></i> fa-list-check</td></tr>
                        <tr><td><strong>Outward</strong></td><td style="color: #10b981;">කොළ</td><td><i class="fa-solid fa-paper-plane"></i> fa-paper-plane</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-lines"></i><span>Internal Letter Sections</span></div>
            <ul class="m-steps">
                <li><strong>Basic Information</strong> — CDR, Reference, Title, Date, Regiment, Category, Security, Priority, Status</li>
                <li><strong>Informer Details</strong> — නම, කේතය, Category, සම්බන්ධතා</li>
                <li><strong>Registration &amp; Metadata</strong> — කවුරුන් සටහන් කළාද, Service Number, දිනය</li>
                <li><strong>Description</strong> — About Letter සහ Comments</li>
                <li><strong>Minute Trail</strong> — යොමු කිරීම් ඉතිහාසය (timeline ලෙස)</li>
                <li><strong>Final Disposal Records</strong> — බැහැර කිරීමේ වාර්තා</li>
                <li><strong>Physical Archive</strong> — භෞතික ගොනු විස්තර</li>
                <li><strong>Linked Task Letters</strong> — සම්බන්ධ කාර්ය ලිපි (clickable cards)</li>
                <li><strong>Linked Outward Letters</strong> — සම්බන්ධ බාහිර ලිපි</li>
                <li><strong>Original Attachment</strong> — PDF එක බැලීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Task Letter Sections</span></div>
            <ul class="m-steps">
                <li><strong>Task Information</strong> — Task CDR, Reference, Dates, Priority, Status, Assigned By</li>
                <li><strong>Description</strong> — Description, About Letter, Comments</li>
                <li><strong>Assigned Agencies</strong> — පැවරූ රෙජිමේන්තු table එකක් ලෙස</li>
                <li><strong>Replies Received</strong> — ලැබුණු පිළිතුරු</li>
                <li><strong>Source Internal Letters</strong> — මූලාශ්‍ර ලිපි</li>
                <li><strong>Linked Outward Letters</strong> — සම්බන්ධ බාහිර ලිපි</li>
                <li><strong>Attachment</strong> — Task PDF</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-paper-plane"></i><span>Outward Letter Sections</span></div>
            <ul class="m-steps">
                <li><strong>Letter Information</strong> — Reference, CDR, Date, Receiver(s), Method, Status, Approval</li>
                <li><strong>Description</strong> — Description සහ Comment</li>
                <li><strong>Creation &amp; Workflow</strong> — කවුරුන් සාදා, කවුරුන් අනුමත කළාද (timestamps සමඟ)</li>
                <li><strong>Dispatch Details</strong> — Completed නම් පමණයි</li>
                <li><strong>Source Internal Letters</strong> — මූලාශ්‍ර ලිපි</li>
                <li><strong>Attachment</strong> — Outward PDF</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම් (Hero section)</span></div>
            <ul class="m-steps">
                <li><strong>Download Dossier</strong> — Dossier Hub එකට යවයි</li>
                <li><strong>Print</strong> — Browser එකේ print dialog එක විවෘත කරයි</li>
                <li><strong>Back</strong> — Linkage Report එකට නැවත යයි</li>
            </ul>
        </div>

        <div class="m-callout tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div>
                <strong>Linked Records Navigation</strong>
                <p>සම්බන්ධ ලිපි (Linked Tasks, Linked Outward) කාඩ්පත් ලෙස පෙන්වයි. ඕනෑම එකක් click කළ විට ඒ ලිපියේ Unified Details page එකට යයි.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: DOSSIER HUB ═══════════════ -->
    <div class="m-tab-panel" id="tab-hub">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-open"></i><span>Dossier Hub Page</span></div>
            <p><code>dossier_hub.php</code> page එක ලිපියට අදාළ PDF බාගත කිරීමේ තේරීම් 6ක් පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-pdf"></i><span>Dossier Options — 6ක්</span></div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-file-pdf"></i></div>
                <div class="opt-body">
                    <h6>Complete Dossier</h6>
                    <p>සම්පූර්ණ දත්ත සහ අමුණුම් සමඟ සියලුම සම්බන්ධිත ලිපි. <strong>වඩාත් සවිස්තරාත්මක තේරීම.</strong></p>
                    <span class="opt-scope">scope=all</span>
                </div>
            </div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="opt-body">
                    <h6>Main Document Only</h6>
                    <p>මෙම ලිපිය පමණයි — metadata සහ attachment සමඟ. සම්බන්ධිත ලිපි ඇතුළත් නොවේ.</p>
                    <span class="opt-scope">scope=main</span>
                </div>
            </div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div class="opt-body">
                    <h6>Internal Letters Only</h6>
                    <p>Chain එකේ සියලුම Internal ලිපි කොටස් අමුණුම් සමඟ.</p>
                    <span class="opt-scope">scope=internal</span>
                </div>
            </div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-list-check"></i></div>
                <div class="opt-body">
                    <h6>Task Letters Only</h6>
                    <p>සියලුම Task ලිපි කොටස් — agencies, replies සහ attachments සමඟ.</p>
                    <span class="opt-scope">scope=task</span>
                </div>
            </div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <div class="opt-body">
                    <h6>Outward Letters Only</h6>
                    <p>සියලුම Outward ලිපි කොටස් — dispatch details සහ attachments සමඟ.</p>
                    <span class="opt-scope">scope=outward</span>
                </div>
            </div>

            <div class="dossier-option">
                <div class="opt-icon"><i class="fa-solid fa-diagram-project"></i></div>
                <div class="opt-body">
                    <h6>Metadata Only</h6>
                    <p>සියලුම metadata සහ relationships — attachments නැතුව. <strong>ඉක්මන් export</strong>.</p>
                    <span class="opt-scope">scope=chain</span>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Scope Comparison</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Scope</th>
                            <th>Documents</th>
                            <th>Attachments</th>
                            <th>Watermark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>all</code></td><td>සියල්ල</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><code>main</code></td><td>ප්‍රධාන ලිපිය පමණයි</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><code>internal</code></td><td>Internal පමණයි</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><code>task</code></td><td>Task පමණයි</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><code>outward</code></td><td>Outward පමණයි</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><code>chain</code></td><td>සියල්ල (metadata පමණයි)</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Dossier බාගත කරන ආකාරය</span></div>
            <ol class="m-steps">
                <li>Linkage Report එකේ හෝ Unified Details එකේ <strong>PDF</strong> බොත්තම ඔබන්න.</li>
                <li>Dossier Hub එකේ ලිපියේ සාරාංශය පෙන්වයි.</li>
                <li>අවශ්‍ය scope එක තෝරන්න (උදා: Complete Dossier).</li>
                <li><strong>Generating Dossier</strong> page එකට යයි.</li>
                <li>PDF එක සාදන ගමන් ප්‍රගතිය පෙන්වයි.</li>
                <li>සූදානම් වූ පසු <strong>Download PDF</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Cache</strong>
                    <p>එකම ලිපිය දෙවෙනි වතාවට generate කරන විට <strong>cache</strong> එකෙන් ඉක්මනින් ලැබේ. සංඥාව: "from cache" text එක file meta එකේ දිස්වේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: PDF GENERATION ═══════════════ -->
    <div class="m-tab-panel" id="tab-pdf">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-pdf"></i><span>PDF Generation Process</span></div>
            <p>මෙම process එක පිටුපස ඇති තාක්ෂණය:</p>
            <div class="workflow">
                <div class="wf-step find"><div class="wf-icon"><i class="fa-solid fa-magnifying-glass"></i></div><div class="wf-title">1. Chain Build</div><div class="wf-desc">සම්බන්ධ ලිපි සොයයි</div></div>
                <div class="wf-step view"><div class="wf-icon"><i class="fa-brands fa-chrome"></i></div><div class="wf-title">2. Chrome Render</div><div class="wf-desc">HTML → PDF</div></div>
                <div class="wf-step hub"><div class="wf-icon"><i class="fa-solid fa-layer-group"></i></div><div class="wf-title">3. FPDI Merge</div><div class="wf-desc">Attachments එකතු කරයි</div></div>
                <div class="wf-step build"><div class="wf-icon"><i class="fa-solid fa-stamp"></i></div><div class="wf-title">4. Watermark</div><div class="wf-desc">හැම පිටුවකම</div></div>
                <div class="wf-step download"><div class="wf-icon"><i class="fa-solid fa-download"></i></div><div class="wf-title">5. Download</div><div class="wf-desc">PDF ලබා දේ</div></div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>Detailed Workflow</span></div>
            <ol class="m-steps">
                <li>
                    <strong>Chain Build</strong> — ලිපියේ වර්ගය අනුව සම්බන්ධ ලිපි සොයයි.
                    <div style="margin-top:6px;font-size:0.85rem;color:var(--muted);">
                        Internal → Tasks + Outwards · Task → Sources + Replies + Outwards · Outward → Sources
                    </div>
                </li>
                <li><strong>Chrome Render</strong> — Headless Chrome මගින් HTML → PDF (Sinhala text හරියටම render වේ)</li>
                <li><strong>FPDI Merge</strong> — ලිපි PDF එකට පසුව එහි attachment PDF එක merge කරයි</li>
                <li><strong>Watermark</strong> — හැම පිටුවකටම watermark 2ක් එකතු කරයි</li>
                <li><strong>Cache</strong> — PDF එක cache directory එකේ save කරයි (පැය 24ක් සඳහා)</li>
                <li><strong>Download</strong> — Token එකක් සමඟ user ට stream කරයි</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-stamp"></i><span>Watermark — හැම පිටුවකම</span></div>
            <ul class="m-steps">
                <li>
                    <strong>Text</strong> — "Property of Directorate of Military Intelligence"
                    <div style="margin-top:6px;font-size:0.85rem;color:var(--muted);">
                        45° කෝණයෙන්, අළු පැහැයෙන්, opacity 7%
                    </div>
                </li>
                <li>
                    <strong>National Emblem</strong> — ලංකාවේ ජාතික ලාංඡනය
                    <div style="margin-top:6px;font-size:0.85rem;color:var(--muted);">
                        පිටුවේ මැදට, 60x60mm, opacity 7%
                    </div>
                </li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-tree"></i><span>PDF Structure</span></div>
            <div class="m-code" data-lang="structure">
Dossier PDF Structure:
│
├── Cover Page
│   ├── National Emblem
│   ├── "COMPLETE DOSSIER"
│   ├── Reference + Title + Date
│   └── Contents summary
│
├── Index Page
│   └── Table of all documents
│
├── For each document:
│   ├── Doc section (Chrome rendered)
│   │   ├── Header (color-coded)
│   │   ├── Sections A, B, C...
│   │   └── Attachment reference
│   │
│   └── Attachment PDF (FPDI merged)
│
└── Watermark on EVERY page</div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Generation Status</span></div>
            <p>PDF සාදන ගමන් පහත ප්‍රගතිය පෙන්වයි:</p>
            <ul class="m-steps">
                <li><i class="fa-solid fa-circle-notch"></i> Loading document chain</li>
                <li><i class="fa-solid fa-circle-notch"></i> Rendering pages (Chrome)</li>
                <li><i class="fa-solid fa-circle-notch"></i> Merging attachments &amp; watermark</li>
                <li><i class="fa-solid fa-circle-notch"></i> Finalising PDF</li>
            </ul>

            <div class="m-callout info">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Estimated Time</strong>
                    <ul>
                        <li><strong>Main doc</strong> (scope=main): ~3-5s පළමු වතාවේ, ~0.3s cache-hit</li>
                        <li><strong>Docs 5ක්</strong> (scope=all): ~10-15s පළමු වතාවේ</li>
                        <li><strong>Cache-hit</strong> — පසු වතාවලදී ~0.3s</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-triangle-exclamation"></i><span>Troubleshooting</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>ගැටළුව</th><th>විසඳුම</th></tr></thead>
                    <tbody>
                        <tr><td>"Chrome not found"</td><td>Server එකේ Chrome/Edge install නැහැ. පද්ධති පරිපාලක අමතන්න.</td></tr>
                        <tr><td>PDF හිස් වගේ</td><td>අලුතින් Try Again බොත්තම ඔබන්න. Cache clear වේ.</td></tr>
                        <tr><td>Sinhala කැඩිලා</td><td>Noto Sans Sinhala font load වීමට තත්පර කිහිපයක් අවශ්‍යයි. නැවත උත්සාහ කරන්න.</td></tr>
                        <tr><td>Timeout</td><td>Documents 30ට වඩා නම් chain කැපේ. Scope එක පටු කරන්න.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-route"></i><span>සම්පූර්ණ ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>Linkage Report බලන්න</strong> — සියලුම ලිපි එකම තැනකින් බලන්න</li>
                <li><strong>Filter කරන්න</strong> — Date range, Desk අනුව පෙරහන්</li>
                <li><strong>Tab මාරු කරන්න</strong> — Outward, Internal, Tasks</li>
                <li><strong>View කරන්න</strong> — ලිපියේ සම්පූර්ණ විස්තර (Unified Details)</li>
                <li><strong>PDF බොත්තම ඔබන්න</strong> — Dossier Hub එකට</li>
                <li><strong>Scope තෝරන්න</strong> — Complete / Main / Internal / Task / Outward / Metadata</li>
                <li><strong>Generate එකට රැඳී සිටින්න</strong> — ප්‍රගතිය පෙන්වයි</li>
                <li><strong>Download PDF</strong> — සූදානම් වූ පසු</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>මැදිහත් වන Tables</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Table</th><th>කරන කාර්යය</th></tr></thead>
                    <tbody>
                        <tr><td><code>internal_letters</code></td><td>Internal ලිපි</td></tr>
                        <tr><td><code>outward_letters</code></td><td>බාහිර ලිපි</td></tr>
                        <tr><td><code>task_letters</code></td><td>කාර්ය ලිපි</td></tr>
                        <tr><td><code>outward_internal</code></td><td>Outward ↔ Internal සම්බන්ධතා</td></tr>
                        <tr><td><code>outward_task</code></td><td>Outward ↔ Task සම්බන්ධතා</td></tr>
                        <tr><td><code>task_internal_links</code></td><td>Task ↔ Internal සම්බන්ධතා</td></tr>
                        <tr><td><code>task_replies</code></td><td>Task පිළිතුරු</td></tr>
                        <tr><td><code>internal_letters_forwarding</code></td><td>Minute trail</td></tr>
                        <tr><td><code>internal_letters_final_disposal</code></td><td>බැහැර කිරීම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-shield"></i><span>Security — One-Time Token</span></div>
            <p>PDF සාදන විට ආරක්ෂාව සඳහා:</p>
            <ul class="m-steps">
                <li>32-character random token එකක් සාදයි</li>
                <li>Token එක temp file එකක save වේ (පැය 10ක් සඳහා)</li>
                <li>Chrome එකට URL එක දෙන විට token එක අවශ්‍යයි</li>
                <li>PDF සාදා අවසන් වූ පසු token එක delete වේ</li>
                <li>User ට download token එකක් වෙනම දෙනවා (පැය 1ක් සඳහා)</li>
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
                            <td><span class="perm-code">VIEW_INTERNAL_LETTERS</span></td>
                            <td>Internal ලිපි සහ Linkage Report බැලීම</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">VIEW_OUTGOING_LETTERS</span></td>
                            <td>Outward ලිපි සහ Linkage Report බැලීම</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;color:var(--muted);">
                මෙම permissions දෙකෙන් එකක් හෝ දෙකම තිබිය යුතුයි. දෙකම නැත්නම් "Access Denied" පෙන්වයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Permission Matrix</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Officer</th>
                            <th>Desk Officer</th>
                            <th>Record Keeper</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>View Linkage Report</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View Unified Details</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Access Dossier Hub</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Generate PDF Dossier</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Export Excel</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Export PDF (table)</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Note</strong>
                <p>මෙම module එක <strong>view-only</strong> — ලිපි සංශෝධනය, ඉවත් කිරීම, යොමු කිරීම කිසිවක් මෙතනින් කළ නොහැක. ඒවා අදාළ module වලින් කරන්න.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="07-linkage-reports" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="06-record-room.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Record Room</span></a>
        <a href="08-informers.php" class="next"><span class="lbl">Next →</span><span class="ttl">Informers</span></a>
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