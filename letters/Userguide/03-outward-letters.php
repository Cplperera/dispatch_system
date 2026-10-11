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
<title>Outward Letters | IDCMS Manual</title>
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
.workflow {
    display: flex;
    align-items: stretch;
    gap: 0;
    margin: 20px 0;
    flex-wrap: wrap;
    justify-content: center;
}
.wf-step {
    flex: 1;
    min-width: 130px;
    background: var(--card);
    border: 2px solid var(--border);
    border-radius: 14px;
    padding: 16px 12px;
    text-align: center;
    position: relative;
    margin: 6px 4px;
}
.wf-step .wf-icon {
    width: 44px; height: 44px;
    border-radius: 50%;
    margin: 0 auto 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    color: #fff;
}
.wf-step .wf-title { font-weight: 800; font-size: 0.8rem; margin-bottom: 2px; }
.wf-step .wf-desc { font-size: 0.7rem; color: var(--muted); }

/* Status colors */
.wf-step.draft  .wf-icon { background: #6b7280; }
.wf-step.pending .wf-icon { background: #FFAB00; }
.wf-step.approved .wf-icon { background: #00C853; }
.wf-step.rejected .wf-icon { background: #ef4444; }
.wf-step.completed .wf-icon { background: #3b82f6; }

/* Badge chips for statuses */
.chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 8px;
    font-size: 0.7rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 2px;
}
.chip.draft    { background: rgba(160,160,160,0.15); color: #A0A0A0; }
.chip.pending  { background: rgba(255,171,0,0.15); color: #FFAB00; }
.chip.approved { background: rgba(0,200,83,0.15); color: #00C853; }
.chip.rejected { background: rgba(239,68,68,0.15); color: #ef4444; }
.chip.completed{ background: rgba(59,130,246,0.15); color: #3b82f6; }

/* Permission chip */
.perm-code {
    display: inline-block;
    background: var(--code-bg);
    color: var(--accent);
    padding: 4px 12px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid var(--code-border);
    margin: 2px 4px 2px 0;
}
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
        <a href="03-outward-letters.php" class="active"><i class="fa-solid fa-paper-plane"></i><span>Outward Letters</span></a>
        <a href="04-task-letters.php"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>

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
            <span class="current">Outward Letters</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-paper-plane"></i> Dispatching</span>
        <h1>Outward Letters</h1>
        <p>බාහිර ලිපි සකස් කිරීම, RA අනුමැතිය ලබා ගැනීම, යැවීම සහ සම්බන්ධ කිරීම් (linkage) පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 25 min read</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-add"><i class="fa-solid fa-plus"></i> Add New</button>
        <button class="m-tab" data-tab="tab-registry"><i class="fa-solid fa-list"></i> Registry</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-linking"><i class="fa-solid fa-link"></i> Linking</button>
        <button class="m-tab" data-tab="tab-edit"><i class="fa-solid fa-pen-to-square"></i> Edit &amp; View</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Outward Letters</strong> module එක මගින් ආයතනයෙන් <strong>බාහිර ආයතන වෙත යවන ලිපි</strong> සකස් කිරීම, අනුමැතිය ලබා ගැනීම, යැවීම සහ ලිපි අතර සම්බන්ධතා (linkage) නිර්මාණය කිරීම සිදු වේ.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>ප්‍රධාන භූමිකා</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Role</th><th>භූමිකාව</th><th>කරන කටයුතු</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Creator / Officer</strong></td><td>ලිපිය සකසන්නා</td><td>නව ලිපියක් එක් කිරීම, අනුමැතියට යැවීම</td></tr>
                        <tr><td><strong>Registry Assistant (RA)</strong></td><td>ලේඛන සහායක</td><td>අනුමැතිය ලබා දීම හෝ ප්‍රතික්ෂේප කිරීම</td></tr>
                        <tr><td><strong>Dispatch Clerk</strong></td><td>යැවීමේ නිලධාරී</td><td>අනුමත ලිපි බාහිරට යැවීම (dispatch)</td></tr>
                        <tr><td><strong>Administrator</strong></td><td>පරිපාලක</td><td>සියලුම ක්‍රියාකාරකම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>ප්‍රධාන අංග 6ක්</span></div>
            <ul class="m-steps">
                <li><strong>Add Outward</strong> — නව බාහිර ලිපියක් සකස් කිරීම</li>
                <li><strong>Registry / List</strong> — සියලුම ලිපි දැක බලා කළමනාකරණය</li>
                <li><strong>RA Approval</strong> — Registry Assistant අනුමැතිය</li>
                <li><strong>Dispatch</strong> — අනුමත ලිපි බාහිරට යැවීම</li>
                <li><strong>Link Correspondence</strong> — Internal සහ Task ලිපි සමඟ සම්බන්ධ කිරීම</li>
                <li><strong>Edit &amp; View</strong> — ලිපි විස්තර දැක බලා වෙනස් කිරීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-route"></i><span>සම්පූර්ණ Workflow එක</span></div>
            <div class="workflow">
                <div class="wf-step draft"><div class="wf-icon"><i class="fa-solid fa-pen"></i></div><div class="wf-title">Draft</div><div class="wf-desc">සකස් කරමින්</div></div>
                <div class="wf-step pending"><div class="wf-icon"><i class="fa-solid fa-clock"></i></div><div class="wf-title">Pending RA</div><div class="wf-desc">අනුමැතිය බලාපොරොත්තුවෙන්</div></div>
                <div class="wf-step approved"><div class="wf-icon"><i class="fa-solid fa-check"></i></div><div class="wf-title">Approved</div><div class="wf-desc">අනුමත විය</div></div>
                <div class="wf-step completed"><div class="wf-icon"><i class="fa-solid fa-paper-plane"></i></div><div class="wf-title">Completed</div><div class="wf-desc">යවා අවසන්</div></div>
            </div>
            <p style="text-align:center;font-size:0.8rem;color:var(--muted);margin-top:8px;">
                <i class="fa-solid fa-arrow-right" style="color:var(--accent);"></i>
                Rejected වුනොත් &nbsp;→&nbsp; <strong>Resubmit</strong> කර නැවත අනුමැතියට යවනවා
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>Status Types — 5ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip draft"><i class="fa-solid fa-pen"></i> Draft</span>
                <span class="chip pending"><i class="fa-solid fa-clock"></i> Pending RA Approval</span>
                <span class="chip approved"><i class="fa-solid fa-check"></i> Approved</span>
                <span class="chip rejected"><i class="fa-solid fa-ban"></i> Rejected</span>
                <span class="chip completed"><i class="fa-solid fa-paper-plane"></i> Completed</span>
            </div>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>CDR Number එක</strong>
                    <p>Outward ලිපි සඳහා CDR prefix එක <code>RPT</code> වේ. උදා: <code>RPT/2026/01</code>. දැනට තියෙන අවසන් අංකයට +1 කර නව අංකය ස්වයංක්‍රීයව ජනනය වේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: ADD NEW ═══════════════ -->
    <div class="m-tab-panel" id="tab-add">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Overview</span></div>
            <p>නව බාහිර ලිපියක් සකස් කිරීමට <code>Add Outward</code> page එක භාවිතා කරන්න. සුරැකීමෙන් පසු ලිපිය <strong>Pending RA Approval</strong> තත්ත්වයට යයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Outward Letters → Add Outward</code> තෝරන්න.</li>
                <li><strong>CDR Number</strong> — පද්ධතිය විසින්ම පුරවා ඇත. වෙනස් කළ නොහැක.</li>
                <li><strong>Letter Reference No</strong> කොටුවේ ලිපියේ යොමුව ඇතුළත් කරන්න (අනිවාර්යයි · හිස්තැන් තහනම් · duplicate නොවිය යුතුයි).</li>
                <li><strong>Letter Date</strong> — ලිපියේ දිනය තෝරන්න (පෙරනිමිය: අද).</li>
                <li><strong>Title / Subject</strong> — ලිපියේ මාතෘකාව ලියන්න (අනිවාර්යයි).</li>
                <li>
                    <strong>Receiver / External Branches</strong> — Dropdown එකෙන් ආයතන තෝරන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Multi-select</strong>
                            <p>ඔබට <strong>එකකට වඩා ආයතන</strong> තෝරන්න පුළුවන්. Checkbox එකක් තෝරා ගත් විට button එකේ නම පෙන්වයි. තුනක් හෝ වැඩි ගණනක් නම් "N branches selected" ලෙස පෙන්වයි.</p>
                        </div>
                    </div>
                </li>
                <li><strong>Description</strong> — ලිපියේ කෙටි විස්තරය ලියන්න (optional).</li>
                <li><strong>Comment / Remarks</strong> — අභ්‍යන්තර සටහන් (optional).</li>
                <li>
                    <strong>Dispatch Method</strong> — ලිපිය යවන ආකාරය තෝරන්න:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><code>Email</code> — විද්‍යුත් තැපෑලෙන්</li>
                        <li><code>By Hand</code> — අතින් බාරදීමෙන්</li>
                        <li><code>Post</code> — තැපෑලෙන්</li>
                        <li><code>Courier</code> — කූරියර් සේවයෙන්</li>
                    </ul>
                </li>
                <li><strong>Attachment</strong> — ලිපියේ PDF/DOC ගොනුව උඩුගත කරන්න (optional).</li>
                <li>
                    <strong>Save &amp; Forward to Registry Assistant</strong> බොත්තම ඔබන්න.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <p>සාර්ථක වූ පසු හරිත පණිවිඩයක් පෙන්වයි: <em>"Outward letter successfully registered and pending RA approval!"</em></p>
                        </div>
                    </div>
                </li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th><th>විස්තර</th></tr></thead>
                    <tbody>
                        <tr><td><code>CDR Number</code></td><td>ලියාපදිංචි අංකය</td><td><span class="optional">Auto</span></td><td><code>RPT/2026/XX</code> ආකෘතියෙන්</td></tr>
                        <tr><td><code>Letter Reference</code></td><td>යොමු අංකය</td><td><span class="required">Required</span></td><td>Unique · හිස්තැන් නැහැ</td></tr>
                        <tr><td><code>Letter Date</code></td><td>ලිපියේ දිනය</td><td><span class="required">Required</span></td><td>Calendar එකෙන්</td></tr>
                        <tr><td><code>Title / Subject</code></td><td>මාතෘකාව</td><td><span class="required">Required</span></td><td>ලිපියේ විෂය</td></tr>
                        <tr><td><code>Receiver</code></td><td>බාහිර ආයතන</td><td><span class="required">Required</span></td><td>Multi-select · එකක්වත් තෝරන්න ඕන</td></tr>
                        <tr><td><code>Description</code></td><td>විස්තරය</td><td><span class="optional">Optional</span></td><td>සාරාංශය</td></tr>
                        <tr><td><code>Comment</code></td><td>ටිප්පණි</td><td><span class="optional">Optional</span></td><td>අභ්‍යන්තර සටහන්</td></tr>
                        <tr><td><code>Dispatch Method</code></td><td>යැවීමේ ක්‍රමය</td><td><span class="optional">Default</span></td><td>පෙරනිමිය: Email</td></tr>
                        <tr><td><code>Attachment</code></td><td>ගොනුව</td><td><span class="optional">Optional</span></td><td>PDF · DOC · DOCX · JPG · PNG</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>වැදගත්</strong>
                <ul>
                    <li>Letter Reference එක <strong>හිස්තැන් රහිතව</strong> ලියන්න — space bar එබුවොත් ස්වයංක්‍රීයව ඉවත් වේ</li>
                    <li>එකම reference එකක් දැනටමත් තියෙනවා නම් error එකක් පෙන්වයි</li>
                    <li>සුරැකීමෙන් පසු <strong>Pending RA Approval</strong> තත්ත්වයට යයි — කෙලින්ම යවන්නේ නැහැ</li>
                </ul>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>Preview Table</span></div>
            <p>Form එකට පහළින් <strong>අවසන් ලිපි 10ක්</strong> ලැයිස්තුවක් පෙන්වයි. මෙය අලුතින් එක් කළ දේ තහවුරු කර ගැනීමට උපකාරී වේ.</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>CDR Number</strong></td><td>ලියාපදිංචි අංකය</td></tr>
                        <tr><td><strong>Reference</strong></td><td>Letter Reference</td></tr>
                        <tr><td><strong>Date</strong></td><td>ලිපියේ දිනය</td></tr>
                        <tr><td><strong>Receiver Branches</strong></td><td>ආයතන වල නම්</td></tr>
                        <tr><td><strong>Dispatch Method</strong></td><td>Email / By Hand වර්ණ badge ලෙස</td></tr>
                        <tr><td><strong>Approval Status</strong></td><td>Pending / Approved / Rejected</td></tr>
                        <tr><td><strong>Actions</strong></td><td>"View Tags" බොත්තම — Link Outward page එකට යයි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: REGISTRY ═══════════════ -->
    <div class="m-tab-panel" id="tab-registry">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Registry / List Page</span></div>
            <p>මෙය <code>outward_list.php</code> page එකයි. සියලුම බාහිර ලිපි දකින්න, පෙරහන් කරන්න, සහ කළමනාකරණය කරන්න මෙය භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stats Cards — 5ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th><th>වර්ණය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total</strong></td><td>සියලුම ලිපි</td><td style="color: var(--accent);">දම්</td></tr>
                        <tr><td><strong>Pending RA</strong></td><td>අනුමැතිය බලාපොරොත්තුවෙන්</td><td style="color:#FFAB00;">තැඹිලි</td></tr>
                        <tr><td><strong>Approved RA</strong></td><td>අනුමත වූ</td><td style="color:#00C853;">කොළ</td></tr>
                        <tr><td><strong>Rejected RA</strong></td><td>ප්‍රතික්ෂේප වූ</td><td style="color:#ef4444;">රතු</td></tr>
                        <tr><td><strong>Completed</strong></td><td>යවා අවසන් වූ</td><td style="color:#3b82f6;">නිල්</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>ඕනෑම card එකක් මත ක්ලික් කර, එම තත්ත්වයේ ලිපි පමණක් බලන්න පුළුවන්.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-filter"></i><span>Filters — 5ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Filter</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Status</strong></td><td>Draft · Pending RA · Approved · Rejected · Completed</td></tr>
                        <tr><td><strong>External Branches</strong></td><td>ආයතනය අනුව පෙරහන් කිරීම</td></tr>
                        <tr><td><strong>Internal Desk</strong></td><td>ලිපිය සකස් කළ ඩෙස්කය අනුව</td></tr>
                        <tr><td><strong>Dispatch Method</strong></td><td>Email · By Hand (වෙනත් ක්‍රම එකතු කළ හැක)</td></tr>
                        <tr><td><strong>Date Range</strong></td><td>From Date සහ To Date අතර ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-sort"></i><span>Sorting, Search, Pagination</span></div>
            <ul class="m-steps">
                <li><strong>Search</strong> — ඉහළ දකුණේ කොටුවෙන් CDR / Title / Reference අනුව සොයන්න</li>
                <li><strong>Sort</strong> — CDR/Ref · Title · Status · Created අනුව ඉහළ/පහළ කරන්න (<i class="fa-solid fa-sort"></i> අයිකනය)</li>
                <li><strong>Pagination</strong> — පහළින් පිටු අතර මාරු වෙන්න</li>
                <li><strong>Per Page</strong> — එක පිටුවක 10, 15, 25, 50, හෝ 100ක් පෙන්වන්න</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-excel"></i><span>Excel Export</span></div>
            <p><strong>Excel</strong> බොත්තම ක්ලික් කළ විට, දැනට පෙරහන් කර ඇති ලිපි ලැයිස්තුවක් <code>.xls</code> ගොනුවක් ලෙස බාගත වේ. සියලුම columns (ID, CDR, Title, Reference, Receiver, Dispatch, Status, Creator, Dates, Approvals) ඇතුළත්.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Row Actions</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Action</th><th>අයිකනය</th><th>කරන්නේ කුමක්ද</th><th>කවුරුන්ට</th></tr></thead>
                    <tbody>
                        <tr><td><strong>View</strong></td><td><i class="fa-solid fa-eye"></i></td><td>සම්පූර්ණ විස්තර page එකට</td><td>සියලුම MANAGE_OUTWARD users</td></tr>
                        <tr><td><strong>Edit</strong></td><td><i class="fa-solid fa-pen-to-square"></i></td><td>ලිපිය වෙනස් කිරීම</td><td>Completed නොවන ලිපි පමණයි</td></tr>
                        <tr><td><strong>PDF Preview</strong></td><td><i class="fa-solid fa-file-pdf"></i></td><td>Attachment එක විවෘත කිරීම</td><td>ගොනුවක් ඇත්නම් පමණයි</td></tr>
                        <tr><td><strong>Submit</strong></td><td><i class="fa-solid fa-upload"></i></td><td>Draft එක අනුමැතියට යැවීම</td><td>Draft හිමිකරු පමණයි</td></tr>
                        <tr><td><strong>Approve</strong></td><td><i class="fa-solid fa-check"></i></td><td>අනුමත කිරීම</td><td>APPROVE_OUTWARD permission</td></tr>
                        <tr><td><strong>Reject</strong></td><td><i class="fa-solid fa-xmark"></i></td><td>ප්‍රතික්ෂේප කිරීම</td><td>APPROVE_OUTWARD permission</td></tr>
                        <tr><td><strong>Resubmit</strong></td><td><i class="fa-solid fa-rotate-right"></i></td><td>නැවත අනුමැතියට යැවීම</td><td>හිමිකරු හෝ RESUBMIT_OUTWARD</td></tr>
                        <tr><td><strong>Dispatch</strong></td><td><i class="fa-solid fa-paper-plane"></i></td><td>යැවීම සහ Complete කිරීම</td><td>Dispatch Clerk පමණයි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-check"></i><span>RA Approval — අනුමැතිය ලබා දීම</span></div>
            <p>Registry Assistant කෙනෙකුට <strong>RA Approval Dashboard</strong> එකෙන් අනුමැතිය ලබා දිය හැක.</p>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Outward Letters → RA Approval</code> තෝරන්න.</li>
                <li>Pending ලිපි ලැයිස්තුවක් පෙන්වයි (CDR, Ref, Title, Receiver, Creator).</li>
                <li><strong>Approve</strong> බොත්තම ඔබා තහවුරු කරන්න.</li>
                <li>ලිපිය <strong>Approved</strong> තත්ත්වයට යයි — යැවීමට සූදානම්.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Permissions</strong>
                    <p>මෙම page එකට ප්‍රවේශය සඳහා <span class="perm-code">APPROVE_OUTWARD</span> permission එක අවශ්‍යයි. නැත්නම් <em>"Access Denied! Only Registry Assistants can approve outward letters."</em> පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-xmark"></i><span>Rejection — ප්‍රතික්ෂේප කිරීම</span></div>
            <ol class="m-steps">
                <li>RA Approval page එකේ <strong>Reject</strong> බොත්තම ඔබන්න.</li>
                <li>තහවුරු කිරීමේ පණිවිඩය පිළිගන්න.</li>
                <li>ලිපිය <strong>Rejected</strong> තත්ත්වයට යයි — නිර්මාතෘට නැවත ලැබේ.</li>
                <li>නිර්මාතෘට ලිපිය සංශෝධනය කර <strong>Resubmit</strong> කළ හැක.</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-rotate-right"></i><span>Resubmit — නැවත ඉදිරිපත් කිරීම</span></div>
            <p>Rejected වූ ලිපියක් නිර්මාතෘට Edit කර නැවත අනුමැතියට යවන්න පුළුවන්.</p>
            <ul class="m-steps">
                <li><strong>කවුරුන්ට</strong> — ලිපිය සෑදූ නිලධාරියාට හෝ <span class="perm-code">RESUBMIT_OUTWARD</span> permission ඇති අයට</li>
                <li><strong>කරන ආකාරය</strong> — Registry එකේ <strong>Resubmit</strong> බොත්තම ඔබන්න</li>
                <li><strong>ප්‍රතිඵලය</strong> — ලිපිය නැවත <strong>Pending RA Approval</strong> තත්ත්වයට යයි</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-paper-plane"></i><span>Dispatch — යැවීම සහ අවසන් කිරීම</span></div>
            <p>Approved වූ ලිපියක් <strong>Dispatch Clerk</strong> කෙනෙකුට යැවිය හැක.</p>
            <ol class="m-steps">
                <li>Registry එකේ Approved ලිපියේ <strong>Dispatch</strong> බොත්තම ඔබන්න.</li>
                <li>Modal එකේ පහත තොරතුරු පුරවන්න:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Handed Over To</strong> <span class="required" style="color:var(--danger);font-weight:800;font-size:0.72rem;">REQUIRED</span> — ලිපිය භාරදුන් පුද්ගලයා/ආයතනය</li>
                        <li><strong>Remarks</strong> — අමතර සටහන් (optional)</li>
                    </ul>
                </li>
                <li><strong>Confirm Dispatch</strong> බොත්තම ඔබන්න.</li>
                <li>ලිපිය <strong>Completed</strong> තත්ත්වයට යයි — නැවත වෙනස් කළ නොහැක.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>වැදගත්</strong>
                    <p>Dispatch කිරීමෙන් පසු ලිපිය <strong>Completed</strong> වේ. ඉන්පසු ලිපිය <strong>Edit කළ නොහැක</strong>, <strong>Reject කළ නොහැක</strong>, සහ <strong>නැවත යැවිය නොහැක</strong>.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-nodes"></i><span>සම්පූර්ණ Workflow නැවත බැලීම</span></div>
            <div class="workflow">
                <div class="wf-step draft"><div class="wf-icon"><i class="fa-solid fa-pen"></i></div><div class="wf-title">1. Draft</div><div class="wf-desc">Create කරන්න</div></div>
                <div class="wf-step pending"><div class="wf-icon"><i class="fa-solid fa-clock"></i></div><div class="wf-title">2. Pending RA</div><div class="wf-desc">Submit කරන්න</div></div>
                <div class="wf-step approved"><div class="wf-icon"><i class="fa-solid fa-check"></i></div><div class="wf-title">3. Approved</div><div class="wf-desc">RA අනුමත කරයි</div></div>
                <div class="wf-step completed"><div class="wf-icon"><i class="fa-solid fa-paper-plane"></i></div><div class="wf-title">4. Completed</div><div class="wf-desc">Dispatch කරයි</div></div>
            </div>
            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <p><strong>Rejected වුනොත්:</strong> Step 2 සිට නැවත ආරම්භ වේ — Resubmit කර Pending RA තත්ත්වයට යවයි.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: LINKING ═══════════════ -->
    <div class="m-tab-panel" id="tab-linking">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>Link Correspondence</span></div>
            <p><code>link_outward.php</code> page එක මගින් බාහිර ලිපියක් <strong>Internal Letters</strong> සහ <strong>Task Letters</strong> සමඟ සම්බන්ධ කළ හැක. මෙය ලිපිවල ඉතිහාසය සහ සම්බන්ධතා දැනගැනීමට උපකාරී වේ.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Tabs 3ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Tab</th><th>අරමුණ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Internal Letters</strong></td><td>Internal ලිපි කෙලින්ම සම්බන්ධ කිරීම</td></tr>
                        <tr><td><strong>Task Letters</strong></td><td>Task ලිපි සම්බන්ධ කිරීම (සියලුම සම්බන්ධ internals auto-link වේ)</td></tr>
                        <tr><td><strong>Maintenance</strong></td><td>නැතිවූ සම්බන්ධතා sync කිරීම</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-plus"></i><span>Internal ලිපි සම්බන්ධ කිරීම</span></div>
            <ol class="m-steps">
                <li><strong>Internal Letters</strong> tab එකට යන්න.</li>
                <li>සෙවුම් කොටුවෙන් CDR / Title අනුව සොයන්න.</li>
                <li>අදාළ ලිපි checkbox වලින් තෝරන්න (හෝ <strong>Select All</strong> භාවිතා කරන්න).</li>
                <li><strong>Link Selected Internal Letters</strong> බොත්තම ඔබන්න.</li>
                <li>දැනට link කර ඇති ලිපි "Already Linked" ලෙස පෙන්වයි — disable කර ඇත.</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>Task ලිපි සම්බන්ධ කිරීම (Auto-Link)</span></div>
            <p>Task ලිපියක් සම්බන්ධ කළ විට, ඒ Task එකට අදාළ <strong>සියලුම Internal ලිපි ස්වයංක්‍රීයව සම්බන්ධ වේ</strong>.</p>
            <ol class="m-steps">
                <li><strong>Task Letters</strong> tab එකට යන්න.</li>
                <li>සෙවුම් කොටුවෙන් සොයන්න.</li>
                <li>Task ලිපි checkbox වලින් තෝරන්න.</li>
                <li><strong>Debug Mode</strong> checkbox එක tick කර නිගමන පෙන්වන්නත් පුළුවන්.</li>
                <li><strong>Link Selected Task Letters</strong> බොත්තම ඔබන්න.</li>
                <li>පද්ධතිය ස්වයංක්‍රීයව:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Task එකට අදාළ <strong>task_replies</strong> — reply_internal_letter_id සියල්ල</li>
                        <li>Task එකට අදාළ <strong>task_internal_links</strong> — internal_letter_id සියල්ල</li>
                    </ul>
                    සම්බන්ධ කරයි.
                </li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>Task එකක් සම්බන්ධ කළ පසු, එහි සියලුම සම්බන්ධ internals "Task X - [title]" ලෙස source එකක් සමඟ පෙන්වයි. මේවා <strong>direct unlink කළ නොහැක</strong>.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-wrench"></i><span>Maintenance — Sync Missing</span></div>
            <p>කලින් Task සම්බන්ධ නොකළ නමුත්, පසුව Task එකට අලුත් reply එකක් එකතු වුනොත් — Maintenance tab එකෙන් sync කරන්න පුළුවන්.</p>
            <ol class="m-steps">
                <li><strong>Maintenance</strong> tab එකට යන්න.</li>
                <li><strong>Sync Missing Internal Letters</strong> බොත්තම ඔබන්න.</li>
                <li>පද්ධතිය සියලුම සම්බන්ධ task වලට අලුත් internals තියෙනවා ද බලයි.</li>
                <li>නැති ඒවා ස්වයංක්‍රීයව සම්බන්ධ කරයි.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <p><strong>මෙය කිසිවක් ඉවත් නොකරයි</strong> — දැනටමත් ඇති සම්බන්ධතා එහෙමම තියෙනවා, නැති ඒවා විතරයි එකතු කරන්නේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-trash"></i><span>Unlink කිරීම</span></div>
            <p>දැනට සම්බන්ධ කර ඇති ලිපි ලැයිස්තුවෙන් <i class="fa-solid fa-trash-can" style="color:var(--danger);"></i> අයිකනය මත ක්ලික් කර සම්බන්ධතාවය ඉවත් කළ හැක.</p>
            <ul class="m-steps">
                <li><strong>Direct Internals</strong> — කෙලින්ම unlink කළ හැක</li>
                <li><strong>Task-Associated Internals</strong> — කෙලින්ම unlink කළ නොහැක (lock අයිකනය). Task එක unlink කරන විට ඒවත් ඉවත් කළ හැක.</li>
                <li><strong>Task Unlink කරන විට</strong> — "Also remove all internal letters linked via this task" checkbox එකෙන් අදාළ internals ඉවත් කරන්නත් පුළුවන්</li>
            </ul>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>වැදගත්</strong>
                    <p>Unlink කිරීම ස්ථිරයි. නැවත link කිරීමට අවශ්‍ය නම්, නැවත එකතු කරන්න.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: EDIT & VIEW ═══════════════ -->
    <div class="m-tab-panel" id="tab-edit">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-pen-to-square"></i><span>Edit Outward Letter</span></div>
            <p>දැනට ඇති ලිපියක් වෙනස් කිරීමට <code>edit_outward.php</code> page එක භාවිතා කරන්න.</p>
            <ol class="m-steps">
                <li>Registry එකේ ලිපියේ <i class="fa-solid fa-pen-to-square"></i> අයිකනය මත ක්ලික් කරන්න.</li>
                <li>පහත fields වෙනස් කරන්න පුළුවන්:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Letter Reference</li>
                        <li>Title</li>
                        <li>Letter Date</li>
                        <li>Receivers (checkbox grid එකෙන්)</li>
                        <li>Dispatch Method</li>
                        <li>Description</li>
                        <li>Comment</li>
                        <li>Attachment (නව ගොනුවක් upload කළහොත් පරණ එක ප්‍රතිස්ථාපනය වේ)</li>
                    </ul>
                </li>
                <li><strong>Update Letter</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-lock"></i>
                <div>
                    <strong>CDR Number වෙනස් කළ නොහැක</strong>
                    <p>CDR Number එක <strong>ස්ථිරයි</strong> — ලිපිය සාදන විට පමණක් ජනනය වේ. Edit කිරීමේදී එය disable කර ඇත.</p>
                </div>
            </div>

            <div class="m-callout danger">
                <i class="fa-solid fa-ban"></i>
                <div>
                    <strong>Completed ලිපි Edit කළ නොහැක</strong>
                    <p>ලිපිය <strong>Completed</strong> තත්ත්වයේ නම්, සියලුම fields read-only වේ. Edit බොත්තම වෙනුවට lock අයිකනය පෙන්වයි. එය <em>"This letter is already Completed and cannot be edited."</em> පණිවිඩය පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>View Full Details</span></div>
            <p><code>view_outward.php</code> page එක ලිපියේ සියලුම තොරතුරු එකම තැනක පෙන්වයි.</p>

            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Section</th><th>අඩංගු දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Status &amp; Method Badges</strong></td><td>ඉහළින් තත්ත්වය සහ යැවීමේ ක්‍රමය</td></tr>
                        <tr><td><strong>Letter Information</strong></td><td>CDR · Reference · Title · Date · Receiver(s) · Method · Description · Comment</td></tr>
                        <tr><td><strong>Attachment</strong></td><td>ගොනුව ඇත්නම් — බාගත කිරීමට link + ගොනු ප්‍රමාණය (KB)</td></tr>
                        <tr><td><strong>Creation Info</strong></td><td>Creator · Created At · Submitted At</td></tr>
                        <tr><td><strong>RA Action</strong></td><td>Approve/Reject වූ නම් — කවුරුන්, කවදාද</td></tr>
                        <tr><td><strong>Dispatch Details</strong></td><td>Completed නම් — Dispatcher · Handed Over To · Date · Remarks</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-clock"></i><span>Time Elapsed Format</span></div>
            <p>ඉතිහාසයේ සමහර තැන්වල "2 hours ago", "just now", "3 days ago" වගේ format එකක් පෙන්වයි. මෙය <code>created_at</code> එකෙන් ගණනය කරයි.</p>
            <div class="m-code" data-lang="example">
5 minutes ago
2 hours ago
3 days ago
1 week ago
just now</div>
        </div>
    </div>

    <!-- ═══════════════ TAB 7: PERMISSIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-perms">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permission Codes</span></div>
            <p>Outward module එකේ විවිධ ක්‍රියාකාරකම් සඳහා පහත permissions භාවිතා වේ:</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Permission</th><th>අර්ථය</th><th>කවුරුන්ට</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="perm-code">MANAGE_OUTWARD_LETTERS</span></td>
                            <td>Outward module එකට ප්‍රවේශය</td>
                            <td>සියලුම Outward users</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">APPROVE_OUTWARD</span></td>
                            <td>ලිපි අනුමත කිරීම / ප්‍රතික්ෂේප කිරීම</td>
                            <td>Registry Assistant (RA)</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">DISPATCH_OUTWARD</span></td>
                            <td>ලිපි බාහිරට යැවීම</td>
                            <td>Dispatch Clerk</td>
                        </tr>
                        <tr>
                            <td><span class="perm-code">RESUBMIT_OUTWARD</span></td>
                            <td>Rejected ලිපි නැවත ඉදිරිපත් කිරීම</td>
                            <td>නිර්මාතෘ හෝ නියමිත නිලධාරීන්</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-shield"></i><span>Appointment අනුව</span></div>
            <p>සමහර ක්‍රියාකාරකම් සඳහා ඔබේ <strong>Appointment</strong> එකද අදාළ වේ:</p>
            <ul class="m-steps">
                <li><strong>Dispatch Clerk</strong> — <code>Dispatch</code> බොත්තම පෙන්වන්නේ මෙම appointment එක ඇති අයට පමණයි</li>
                <li><strong>Registry Assistant</strong> — Approve / Reject කළ හැක</li>
                <li><strong>Officer</strong> — ලිපි සෑදීම සහ Resubmit කිරීම</li>
            </ul>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Access Denied පෙන්වයි නම්</strong>
                <p>ඔබට අදාළ permission එක නැහැ. ඔබේ පද්ධති පරිපාලක අමතා <strong>Permission Codes</strong> page එකෙන් එය ලබා ගන්න.</p>
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
                            <th>RA</th>
                            <th>Dispatch Clerk</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Add Outward</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View List</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Edit (own)</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#6b7280;">—</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Approve</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Reject</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Dispatch</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Link Letters</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Resubmit</td><td style="color:#00C853;">✓ own</td><td style="color:#6b7280;">—</td><td style="color:#6b7280;">—</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="03-outward-letters" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="02-internal-letters.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Internal Letters</span></a>
        <a href="04-task-letters.php" class="next"><span class="lbl">Next →</span><span class="ttl">Task Letters</span></a>
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