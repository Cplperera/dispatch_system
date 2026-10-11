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
<title>Task Letters | IDCMS Manual</title>
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

/* Workflow diagram */
.workflow { display:flex; align-items:stretch; gap:0; margin:20px 0; flex-wrap:wrap; justify-content:center; }
.wf-step { flex:1; min-width:130px; background:var(--card); border:2px solid var(--border); border-radius:14px; padding:16px 12px; text-align:center; margin:6px 4px; }
.wf-step .wf-icon { width:44px; height:44px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
.wf-step .wf-title { font-weight:800; font-size:0.8rem; margin-bottom:2px; }
.wf-step .wf-desc { font-size:0.7rem; color:var(--muted); }
.wf-step.pending .wf-icon { background: #FFAB00; }
.wf-step.partial .wf-icon { background: #8b5cf6; }
.wf-step.completed .wf-icon { background: #00C853; }
.wf-step.overdue .wf-icon { background: #ef4444; }

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.pending   { background:rgba(255,171,0,0.15); color: #FFAB00; }
.chip.partial   { background:rgba(139,92,246,0.15); color: #8b5cf6; }
.chip.completed { background:rgba(0,200,83,0.15); color: #00C853; }
.chip.high      { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.medium    { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.low       { background:rgba(59,130,246,0.15); color: #3b82f6; }

.perm-code { display:inline-block; background:var(--code-bg); color:var(--accent); padding:4px 12px; border-radius:6px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; border:1px solid var(--code-border); margin:2px 4px 2px 0; }
</style>
</head>
<body>
<div class="reading-progress" id="readingProgress"></div>
<div class="m-overlay" id="sidebarOverlay"></div>

<!-- ============================================================
     SIDEBAR — 12 Sections
     ============================================================ -->
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
        <a href="04-task-letters.php" class="active"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>

        <div class="m-section-label">Records &amp; Disposal</div>
        <a href="05-final-disposal.php"><i class="fa-solid fa-box-archive"></i><span>Final Disposal</span></a>
        <a href="06-record-room.php"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="07-linkage-reports.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>

        <div class="m-section-label">Administration</div>
        <a href="08-informers.php"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
        <a href="09-administration.php"><i class="fa-solid fa-user-shield"></i><span>Administration</span></a>

        <div class="m-section-label">Analytics</div>
        <a href="10-dashboards.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics Dashboards</span></a>

        <div class="m-section-label">Developer</div>
        <a href="11-developer-details.php"><i class="fa-solid fa-code"></i><span>Developer Details</span></a>
        <a href="12-system-health.php"><i class="fa-solid fa-heart-pulse"></i><span>System Health</span></a>

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
            <span class="current">Task Letters</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-list-check"></i> Dispatching</span>
        <h1>Task Letters</h1>
        <p>රෙජිමේන්තු සහ අංශ වලට කාර්ය පැවරීම, පිළිතුරු ලබා ගැනීම සහ සම්පූර්ණ කිරීම පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 25 min read</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-add"><i class="fa-solid fa-plus"></i> Add New Task</button>
        <button class="m-tab" data-tab="tab-list"><i class="fa-solid fa-list"></i> Task List</button>
        <button class="m-tab" data-tab="tab-view"><i class="fa-solid fa-eye"></i> View Task</button>
        <button class="m-tab" data-tab="tab-reply"><i class="fa-solid fa-reply"></i> Submit Reply</button>
        <button class="m-tab" data-tab="tab-edit"><i class="fa-solid fa-pen-to-square"></i> Edit Task</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Task Letters</strong> module එක මගින් ආයතනයේ ලිපියක් <strong>රෙජිමේන්තු එකකට හෝ කිහිපයකට කාර්යයක් ලෙස පැවරීම</strong>, ඔවුන්ගේ පිළිතුරු ලබා ගැනීම, සහ පැවරුම සම්පූර්ණ වීම දක්වා නිරීක්ෂණය කිරීම සිදු කරයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 7ක්</span></div>
            <ul class="m-steps">
                <li><strong>Add Task</strong> — නව කාර්ය ලිපියක් සකස් කිරීම සහ රෙජිමේන්තු වලට පැවරීම</li>
                <li><strong>Task List</strong> — සියලුම කාර්ය ලිපි දැක බලා කළමනාකරණය (Filters + Stats)</li>
                <li><strong>View Task</strong> — එක් කාර්යයක සම්පූර්ණ විස්තර, agencies, replies, timeline</li>
                <li><strong>Submit Reply</strong> — රෙජිමේන්තුවක් විසින් පිළිතුරු ලබා දීම</li>
                <li><strong>Edit Task</strong> — දැනට ඇති කාර්ය ලිපියක් සංශෝධනය</li>
                <li><strong>Auto Status Update</strong> — පිළිතුරු ලැබෙන විට තත්ත්වය ස්වයංක්‍රීයව යාවත්කාලීන වේ</li>
                <li><strong>Linkage</strong> — Internal සහ Outward ලිපි සමඟ ස්වයංක්‍රීය සම්බන්ධතා</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>Task Status Types — 3ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip pending"><i class="fa-solid fa-clock"></i> Pending</span>
                <span class="chip partial"><i class="fa-solid fa-reply-all"></i> Partially Replied</span>
                <span class="chip completed"><i class="fa-solid fa-check"></i> Completed</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Status</th><th>අර්ථය</th><th>කවදාද?</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Pending</strong></td><td>පොරොත්තුවෙන්</td><td>කිසිම පිළිතුරක් ලැබී නැහැ</td></tr>
                        <tr><td><strong>Partially Replied</strong></td><td>අර්ධ වශයෙන් පිළිතුරු ලැබී ඇත</td><td>සමහර රෙජිමේන්තු පමණයි පිළිතුරු දුන්නේ</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සම්පූර්ණයි</td><td>පැවරූ සියලුම රෙජිමේන්තු පිළිතුරු දුන්නා</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-flag"></i><span>Priority Levels — 3ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip high"><i class="fa-solid fa-exclamation"></i> High</span>
                <span class="chip medium"><i class="fa-solid fa-minus"></i> Medium</span>
                <span class="chip low"><i class="fa-solid fa-arrow-down"></i> Low</span>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hashtag"></i><span>TSK CDR Number</span></div>
            <p>Task Letters වලට වෙනම CDR අංකයක් තියෙනවා — <code>TSK</code> prefix එකෙන්. උදා: <code>TSK/2026/01</code>. සෑම වර්ෂයකම <code>01</code> සිට නැවත ආරම්භ වේ.</p>
            <div class="m-code" data-lang="format">
TSK/2026/01   ← පළමු task letter
TSK/2026/02   ← දෙවන task letter
TSK/2026/03   ← තුන්වන...</div>
        </div>

        <div class="m-callout tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div>
                <strong>Auto Status Update</strong>
                <p>රෙජිමේන්තුවක් පිළිතුරු දෙන සෑම අවස්ථාවකම, පද්ධතිය විසින්ම Task එකේ තත්ත්වය නැවත ගණනය කරයි. ඔබ අතින් update කරන්න අවශ්‍ය නැහැ.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: ADD NEW TASK ═══════════════ -->
    <div class="m-tab-panel" id="tab-add">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Overview</span></div>
            <p>නව කාර්ය ලිපියක් සකස් කිරීමට <code>task_letters.php</code> page එක භාවිතා කරන්න. සාර්ථක වූ පසු Task එක <strong>Pending</strong> තත්ත්වයේ රැඳී තිබේ.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Tasking Letters → Add New Task</code> තෝරන්න.</li>
                <li><strong>Task CDR Number</strong> — පද්ධතිය විසින්ම පුරවා ඇත. වෙනස් කළ නොහැක.</li>
                <li><strong>Manual Reference</strong> — ලිපියේ යොමුව ඇතුළත් කරන්න (අනිවාර්යයි · duplicate නොවිය යුතුයි).</li>
                <li><strong>Letter Date</strong> — ලිපියේ දිනය තෝරන්න.</li>
                <li><strong>Deadline Date</strong> — පැවරුම සම්පූර්ණ කළ යුතු දිනය (optional).</li>
                <li><strong>Subject / Title of Task</strong> — කාර්යයේ මාතෘකාව ලියන්න.</li>
                <li>
                    <strong>Priority Level</strong> — ප්‍රමුඛතාව තෝරන්න:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><code>Low</code> — පහළ ප්‍රමුඛතාව</li>
                        <li><code>Medium</code> — මධ්‍යම (පෙරනිමිය)</li>
                        <li><code>High</code> — ඉහළ ප්‍රමුඛතාව</li>
                    </ul>
                </li>
                <li><strong>Task Description</strong> — කෙටි සාරාංශය ලියන්න.</li>
                <li>
                    <strong>Assign Units (Searchable)</strong> — රෙජිමේන්තු තෝරන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Search + Multi-select</strong>
                            <p>ඉහළින් ඇති search box එකෙන් නම ටයිප් කර පෙරහන් කරන්න පුළුවන්. එකකට වඩා රෙජිමේන්තු තෝරන්නත් පුළුවන්.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Link Internal Letters (Searchable)</strong> — මෙම කාර්යයට අදාළ අභ්‍යන්තර ලිපි තෝරන්න.
                    <p style="margin-top:6px;font-size:0.85rem;color:var(--muted);">CDR අංකය හෝ Reference එකෙන් සොයන්න පුළුවන්.</p>
                </li>
                <li><strong>Minute / Detailed Context</strong> — සම්පූර්ණ සන්දර්භය ලියන්න (optional).</li>
                <li><strong>Official Remarks / Comments</strong> — අමතර සටහන් (optional).</li>
                <li><strong>File Attachment</strong> — ලිපියේ PDF/DOC ගොනුව උඩුගත කරන්න (optional).</li>
                <li><strong>Register Task</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Auto Link</strong>
                    <p>Task එකක් සම්බන්ධ කරන Internal ලිපි, පසුව Outward ලිපියකට link වන විට <strong>ස්වයංක්‍රීයව</strong> ඒවාත් link වේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th><th>විස්තර</th></tr></thead>
                    <tbody>
                        <tr><td><code>tsk_cdr_number</code></td><td>Task අංකය</td><td><span class="optional">Auto</span></td><td><code>TSK/2026/XX</code></td></tr>
                        <tr><td><code>letter_ref</code></td><td>යොමු අංකය</td><td><span class="required">Required</span></td><td>Unique විය යුතුයි</td></tr>
                        <tr><td><code>letter_date</code></td><td>ලිපියේ දිනය</td><td><span class="required">Required</span></td><td>Calendar එකෙන්</td></tr>
                        <tr><td><code>request_date</code></td><td>Deadline</td><td><span class="optional">Optional</span></td><td>පැවරුම අවසන් කළ යුතු දිනය</td></tr>
                        <tr><td><code>title</code></td><td>මාතෘකාව</td><td><span class="required">Required</span></td><td>කාර්යයේ විෂය</td></tr>
                        <tr><td><code>priority_status</code></td><td>ප්‍රමුඛතාව</td><td><span class="optional">Default</span></td><td>Medium</td></tr>
                        <tr><td><code>description</code></td><td>විස්තරය</td><td><span class="optional">Optional</span></td><td>සාරාංශය</td></tr>
                        <tr><td><code>about_letter</code></td><td>සන්දර්භය</td><td><span class="optional">Optional</span></td><td>විස්තරාත්මක</td></tr>
                        <tr><td><code>comments</code></td><td>ටිප්පණි</td><td><span class="optional">Optional</span></td><td>අමතර සටහන්</td></tr>
                        <tr><td><code>regiments[]</code></td><td>පැවරූ රෙජිමේන්තු</td><td><span class="optional">Optional</span></td><td>Multi-select</td></tr>
                        <tr><td><code>internal_letters[]</code></td><td>සම්බන්ධ ලිපි</td><td><span class="optional">Optional</span></td><td>Multi-select</td></tr>
                        <tr><td><code>letter_file</code></td><td>Attachment</td><td><span class="optional">Optional</span></td><td>PDF · DOC · DOCX</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: TASK LIST ═══════════════ -->
    <div class="m-tab-panel" id="tab-list">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Task List Page</span></div>
            <p>මෙය <code>task_list.php</code> page එකයි. සියලුම කාර්ය ලිපි දකින්න, පෙරහන් කරන්න, සහ කළමනාකරණය කරන්න මෙය භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stats Cards — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total</strong></td><td>සියලුම කාර්ය ලිපි ගණන</td></tr>
                        <tr><td><strong>Pending</strong></td><td>පොරොත්තුවෙන් ඇති</td></tr>
                        <tr><td><strong>Partial</strong></td><td>අර්ධ වශයෙන් පිළිතුරු ලැබූ</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සම්පූර්ණ වූ</td></tr>
                        <tr><td><strong>Overdue</strong></td><td>කල් ඉකුත් වූ (deadline පසු වූ)</td></tr>
                        <tr><td><strong>High Priority</strong></td><td>ඉහළ ප්‍රමුඛතාව, සම්පූර්ණ නොවූ</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-filter"></i><span>Filters — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Filter</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Status</strong></td><td>All · Pending · Partial · Completed · Overdue</td></tr>
                        <tr><td><strong>Priority</strong></td><td>All · High · Medium · Low</td></tr>
                        <tr><td><strong>Desk</strong></td><td>කාර්යය පැවරූ ඩෙස්කය අනුව</td></tr>
                        <tr><td><strong>From / To Date</strong></td><td>Deadline දින පරාසයක් අනුව</td></tr>
                        <tr><td><strong>My Tasks Only</strong></td><td>ඔබ පැවරූ කාර්ය පමණක් පෙන්වයි</td></tr>
                        <tr><td><strong>Search</strong></td><td>CDR / Title / Reference / Description අනුව සොයන්න</td></tr>
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
                        <tr><td><strong>Task CDR</strong></td><td>Task අංකය + created date</td></tr>
                        <tr><td><strong>Letter Ref</strong></td><td>Manual reference</td></tr>
                        <tr><td><strong>Title & Assignment</strong></td><td>මාතෘකාව + Desk + Assigner</td></tr>
                        <tr><td><strong>Due Date</strong></td><td>Deadline · overdue/upcoming badge සමඟ</td></tr>
                        <tr><td><strong>Priority</strong></td><td>වර්ණ badge ලෙස</td></tr>
                        <tr><td><strong>Agencies</strong></td><td>සෑම රෙජිමේන්තුවක්ම — පිළිතුරු දුන්නාද යන්න සමඟ</td></tr>
                        <tr><td><strong>Status</strong></td><td>Pending / Partial / Completed</td></tr>
                        <tr><td><strong>Actions</strong></td><td>4ක් — View Modal · Full View · Reply · Edit</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Row Actions</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Action</th><th>අයිකනය</th><th>කරන්නේ කුමක්ද</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Quick View</strong></td><td><i class="fa-solid fa-eye"></i></td><td>Modal එකක සාරාංශය පෙන්වයි</td></tr>
                        <tr><td><strong>Full View</strong></td><td><i class="fa-solid fa-arrow-right"></i></td><td>සම්පූර්ණ විස්තර page එකට</td></tr>
                        <tr><td><strong>Reply</strong></td><td><i class="fa-solid fa-reply"></i></td><td>පිළිතුරු දෙන page එකට</td></tr>
                        <tr><td><strong>Edit</strong></td><td><i class="fa-solid fa-pen"></i></td><td>කාර්ය ලිපිය සංශෝධනය</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-excel"></i><span>Excel Export</span></div>
            <p><strong>Excel</strong> බොත්තම ක්ලික් කළ විට, දැනට පෙරහන් කර ඇති කාර්ය ලැයිස්තුවක් <code>.xls</code> ගොනුවක් ලෙස බාගත වේ.</p>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Due Date Warning</strong>
                <ul>
                    <li><strong>රතු pulse animation</strong> — කල් ඉකුත් වූ (overdue) ලිපි</li>
                    <li><strong>කහ pulse animation</strong> — දින 2ක් ඇතුළත අවසන් වන ලිපි</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: VIEW TASK ═══════════════ -->
    <div class="m-tab-panel" id="tab-view">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>View Task Details</span></div>
            <p><code>view_task.php</code> page එක එක් කාර්යයක සම්පූර්ණ තොරතුරු tabs 6ක් ලෙස පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-open"></i><span>Tabs 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Tab</th><th>අඩංගු දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Overview</strong></td><td>මූලික තොරතුරු + Progress bar + Reply chart</td></tr>
                        <tr><td><strong>Agencies</strong></td><td>සෑම රෙජිමේන්තුවකම පිළිතුරු තත්ත්වය විස්තරාත්මකව</td></tr>
                        <tr><td><strong>Linked Letters</strong></td><td>මෙම කාර්යයට සම්බන්ධ Internal ලිපි</td></tr>
                        <tr><td><strong>Replies</strong></td><td>ලැබුණු සියලුම පිළිතුරු (multiple per regiment)</td></tr>
                        <tr><td><strong>Timeline</strong></td><td>Forwarding සහ reply events එකතුව</td></tr>
                        <tr><td><strong>Preview</strong></td><td>Attachment PDF preview (තිබේ නම්)</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-pie"></i><span>Overview Tab</span></div>
            <ul class="m-steps">
                <li><strong>Task badges</strong> — TSK CDR · Priority · Derived Status (auto)</li>
                <li><strong>Basic info</strong> — Reference, Dates, Priority, Assigner</li>
                <li><strong>Progress Bar</strong> — පිළිතුරු දුන් ප්‍රතිශතය</li>
                <li><strong>Agencies Assigned card</strong> — පැවරූ සියලුම රෙජිමේන්තු</li>
                <li><strong>Agencies Replied card</strong> — පිළිතුරු දුන් රෙජිමේන්තු + ලිපි ගණන</li>
                <li><strong>Doughnut Chart</strong> — Accepted vs Data NIL පිළිතුරු</li>
            </ul>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Overdue Badge</strong>
                    <p>Deadline පසු වී ඇත්නම් සහ සම්පූර්ණ නොවී නම්, රතු pulse badge එකක් "OVERDUE" ලෙස පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-building"></i><span>Agencies Tab</span></div>
            <p>සෑම රෙජිමේන්තුවකම පහත තොරතුරු පෙන්වයි:</p>
            <ul class="m-steps">
                <li><strong>Regiment</strong> — රෙජිමේන්තුවේ නම</li>
                <li><strong>Assigned Date</strong> — පැවරූ දිනය</li>
                <li><strong>Replied?</strong> — ඔව් / නැහැ</li>
                <li><strong>Latest Reply Status</strong> — Accepted / Data NIL</li>
                <li><strong>Reply Count</strong> — කීප වතාවක් පිළිතුරු දුන්නාද</li>
                <li><strong>Latest Reply Letter</strong> — අවසන් පිළිතුරු ලිපියට link</li>
                <li><strong>Latest Reply At</strong> — අවසන් පිළිතුරු දුන් වේලාව</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-clock-rotate-left"></i><span>Timeline Tab</span></div>
            <p>කාර්යයට අදාළ සියලුම සිදුවීම් කාලානුක්‍රමිකව පෙන්වයි:</p>
            <ul class="m-steps">
                <li><i class="fa-solid fa-arrow-right" style="color:var(--accent);"></i> <strong>Forwarding</strong> — Internal ලිපිය යොමු කිරීම්</li>
                <li><i class="fa-solid fa-reply" style="color:#28a745;"></i> <strong>Reply</strong> — රෙජිමේන්තු පිළිතුරු</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: SUBMIT REPLY ═══════════════ -->
    <div class="m-tab-panel" id="tab-reply">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-reply"></i><span>Overview</span></div>
            <p>රෙජිමේන්තු නිලධාරියෙකුට කාර්යයකට පිළිතුරු දීමට <code>reply_task.php</code> page එක භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>Task List එකේ කාර්යයේ <i class="fa-solid fa-reply"></i> බොත්තම ඔබන්න.</li>
                <li>
                    <strong>Your Regiment</strong> — ඔබේ රෙජිමේන්තුව තෝරන්න.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <p>මෙම ලැයිස්තුවේ පෙන්වන්නේ <strong>මෙම කාර්යයට පැවරූ රෙජිමේන්තු පමණයි</strong>.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Current Reply Status</strong> — තත්ත්වය තෝරන්න:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><code>Accepted</code> — පිළිගනු ලැබේ (ලිපියක් සම්බන්ධ කළ යුතුයි)</li>
                        <li><code>Data NIL</code> — දත්ත නොමැත (ලිපියක් අවශ්‍ය නැහැ)</li>
                    </ul>
                </li>
                <li>
                    <strong>Link Reply Internal Letter</strong> — අදාළ Internal ලිපිය තෝරන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Auto-filter</strong>
                            <p>රෙජිමේන්තුව තෝරන විට, <strong>එම රෙජිමේන්තුවට අයත්</strong> සහ <strong>තවම link නොකළ</strong> Internal ලිපි පමණක් පෙන්වයි.</p>
                        </div>
                    </div>
                </li>
                <li><strong>Submit Response</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>වැදගත්</strong>
                    <ul>
                        <li><strong>Data NIL</strong> තෝරා ගත් විට Internal ලිපියක් අවශ්‍ය නැහැ</li>
                        <li><strong>Accepted</strong> තෝරා ගත් විට අනිවාර්යයෙන්ම ලිපියක් link කළ යුතුයි</li>
                        <li>එකම රෙජිමේන්තුවක් යළි පිළිතුරු දෙන විට <strong>පැරණි පිළිතුර update වේ</strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr><td><code>regiment_id</code></td><td>ඔබේ රෙජිමේන්තුව</td><td><span class="required">Required</span></td></tr>
                        <tr><td><code>status</code></td><td>Accepted / Data NIL</td><td><span class="required">Required</span></td></tr>
                        <tr><td><code>reply_internal_letter_id</code></td><td>සම්බන්ධ Internal ලිපිය</td><td><span class="optional">Accepted නම් Required</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-robot"></i><span>Auto Status Update</span></div>
            <p>පිළිතුරු සුරැකීමෙන් පසු, පද්ධතිය ස්වයංක්‍රීයව Task එකේ තත්ත්වය ගණනය කරයි:</p>
            <div class="m-code" data-lang="logic">
IF no replies:
    Status = 'Pending'
ELSE IF replies < assigned agencies:
    Status = 'Partially Replied'
ELSE:
    Status = 'Completed'</div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-pen-to-square"></i><span>Edit Reply Page</span></div>
            <p><code>edit_reply.php</code> page එක භාවිතා කරන්නේ පැරණි ක්‍රමයට පිළිතුරු සකස් කිරීමටයි. එහි අමතර fields තියෙනවා:</p>
            <ul class="m-steps">
                <li><strong>Reply Message / Details</strong> — සම්පූර්ණ පිළිතුරු පණිවිඩය (අනිවාර්යයි)</li>
                <li><strong>Status Update</strong> — Pending Review / In Progress / Completed</li>
                <li><strong>Attachment</strong> — PDF, DOC, DOCX, JPG, PNG, ZIP</li>
                <li><strong>Additional Remarks</strong> — අමතර සටහන්</li>
            </ul>

            <div class="m-callout info">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong>CSRF Protection</strong>
                    <p>මෙම page එකට CSRF token එකක් අවශ්‍යයි. Form එක submit වෙන විට token එක ස්වයංක්‍රීයව එකතු වේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: EDIT TASK ═══════════════ -->
    <div class="m-tab-panel" id="tab-edit">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-pen-to-square"></i><span>Edit Task Letter</span></div>
            <p><code>edit_task.php</code> page එක භාවිතා කරන්නේ දැනට ඇති Task එකක් සංශෝධනය කිරීමටයි.</p>
            <ol class="m-steps">
                <li>Task List එකේ <i class="fa-solid fa-pen"></i> අයිකනය මත ක්ලික් කරන්න.</li>
                <li>පහත fields වෙනස් කරන්න පුළුවන්:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Letter Reference</li>
                        <li>TSK CDR Number</li>
                        <li>Letter Date · Request Date</li>
                        <li>Priority Status</li>
                        <li>Overall Task Status</li>
                        <li>Task Title · Description · About Letter · Comments</li>
                        <li>Assigned Agencies (Checkbox grid එකෙන්)</li>
                        <li>Attachment (නව ගොනුවක් දැම්මොත් පරණ එක ප්‍රතිස්ථාපනය වේ)</li>
                    </ul>
                </li>
                <li><strong>Save Task Changes</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Sync Agencies</strong>
                    <p>Edit කරන විට agencies ලැයිස්තුව සම්පූර්ණයෙන්ම නැවත ලියා ඇත. එනම්, <strong>ඔබ ඉවත් කළ රෙජිමේන්තු Task එකෙන් ඉවත් වේ</strong>. එකතු කළ ඒවා එකතු වේ.</p>
                </div>
            </div>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>File Handling</strong>
                    <p>නව ගොනුවක් upload නොකළහොත් පැරණි file එක එහෙමම තියෙනවා. අලුත් එකක් upload කළහොත් පමණයි ප්‍රතිස්ථාපනය වන්නේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 7: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>Task Lifecycle</span></div>
            <div class="workflow">
                <div class="wf-step pending">
                    <div class="wf-icon"><i class="fa-solid fa-pen"></i></div>
                    <div class="wf-title">1. Create</div>
                    <div class="wf-desc">Task සකසයි</div>
                </div>
                <div class="wf-step pending">
                    <div class="wf-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="wf-title">2. Pending</div>
                    <div class="wf-desc">පිළිතුරු බලාපොරොත්තුවෙන්</div>
                </div>
                <div class="wf-step partial">
                    <div class="wf-icon"><i class="fa-solid fa-reply-all"></i></div>
                    <div class="wf-title">3. Partial</div>
                    <div class="wf-desc">සමහර ඒවා පිළිතුරු දුන්නා</div>
                </div>
                <div class="wf-step completed">
                    <div class="wf-icon"><i class="fa-solid fa-check"></i></div>
                    <div class="wf-title">4. Completed</div>
                    <div class="wf-desc">සියල්ලම පිළිතුරු දුන්නා</div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>Auto Linkage Chain</span></div>
            <p>Task Letters එකක් සම්බන්ධ වන විට, පද්ධතිය ස්වයංක්‍රීයව සම්බන්ධතා නිර්මාණය කරයි:</p>
            <ol class="m-steps">
                <li><strong>Task + Internal Letters</strong> — Task එකක් හදන විට manual වශයෙන් internal ලිපි link කරන්න පුළුවන්</li>
                <li><strong>Task + Agencies</strong> — සෑම රෙජිමේන්තුවක්ම task එකට සම්බන්ධ වේ</li>
                <li><strong>Task + Replies</strong> — පිළිතුරු ලිපි ස්වයංක්‍රීයව task එකට සම්බන්ධ වේ</li>
                <li><strong>Outward + Task</strong> — Outward ලිපියකට task එකක් link කළ විට, task එකේ <strong>සියලුම associated internals</strong> ද outward එකට auto-link වේ</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Source Tag</strong>
                    <p>Auto-link වූ ලිපි <code>Task X - [Title]</code> ලෙස source tag එකක් සමඟ පෙන්වයි. ඒවා direct unlink කළ නොහැක.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-robot"></i><span>Auto Status Update Logic</span></div>
            <div class="m-code" data-lang="logic">
For each Task:
    assigned_count = COUNT(task_letter_agencies)
    reply_count    = COUNT(DISTINCT regiment_id FROM task_replies)

    IF reply_count = 0:
        status = 'Pending'
    ELSE IF reply_count >= assigned_count:
        status = 'Completed'
    ELSE:
        status = 'Partially Replied'</div>
            <p style="margin-top:12px;font-size:0.88rem;color:var(--muted);">
                මෙය <strong>task_list.php</strong> page එක load වන සෑම අවස්ථාවකම ස්වයංක්‍රීයව ධාවනය වේ. ඔබ අතින් update කරන්න අවශ්‍ය නැහැ.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-clock"></i><span>Due Date Tracking</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>තත්ත්වය</th><th>වර්ණය</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Overdue</strong></td><td style="color:#ef4444;">රතු pulse</td><td>Deadline පසු විය · සම්පූර්ණ නොවූ</td></tr>
                        <tr><td><strong>Upcoming</strong></td><td style="color:#f59e0b;">කහ pulse</td><td>දින 2ක් ඇතුළත අවසන් වේ</td></tr>
                        <tr><td><strong>Normal</strong></td><td style="color:var(--muted);">සාමාන්‍ය</td><td>තව වේලාවක් ඇත</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 8: PERMISSIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-perms">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permission Codes</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Permission</th><th>අර්ථය</th><th>කවුරුන්ට</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="perm-code">VIEW_TASK_LETTERS</span></td>
                            <td>Task module එකට ප්‍රවේශය, list බැලීම</td>
                            <td>සියලුම Task users</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">EDIT_TASK_LETTERS</span></td>
                            <td>Task සංශෝධනය කිරීම</td>
                            <td>Task managers</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">VIEW_TASK</span></td>
                            <td>Individual Task බැලීම</td>
                            <td>සියලුම officers</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">REPLY_TASK</span></td>
                            <td>පිළිතුරු දීම</td>
                            <td>රෙජිමේන්තු නිලධාරීන්</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">EDIT_TASK</span></td>
                            <td>Edit බොත්තම පෙන්වීම</td>
                            <td>Task owners</td>
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
                            <th>Task Manager</th>
                            <th>Regiment</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Add Task</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View List</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View Details</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Submit Reply</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Edit Task</td><td style="color:#00C853;">✓ own</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Export Excel</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Access Denied පෙන්වයි නම්</strong>
                <p>ඔබට අදාළ permission එක නැහැ. ඔබේ පද්ධති පරිපාලක අමතන්න.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="04-task-letters" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="03-outward-letters.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Outward Letters</span></a>
        <a href="05-final-disposal.php" class="next"><span class="lbl">Next →</span><span class="ttl">Final Disposal</span></a>
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