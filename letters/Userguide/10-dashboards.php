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
<title>Dashboards | IDCMS Manual</title>
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

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.internal { background:rgba(67,24,255,0.15); color: #4318FF; }
.chip.task     { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.outward  { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.informer { background:rgba(139,92,246,0.15); color: #8b5cf6; }

.perm-code { display:inline-block; background:var(--code-bg); color:var(--accent); padding:4px 12px; border-radius:6px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; border:1px solid var(--code-border); margin:2px 4px 2px 0; }

/* Dashboard mini-preview */
.dash-preview {
    background:var(--code-bg);
    border:1px solid var(--border);
    border-radius:14px;
    padding:20px;
    margin:14px 0;
}
.dash-preview-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(100px, 1fr));
    gap:10px;
    margin-bottom:14px;
}
.dash-stat-mini {
    background:var(--card);
    border-radius:10px;
    padding:10px 12px;
    border-left:3px solid var(--accent);
}
.dash-stat-mini .lbl { font-size:0.6rem; text-transform:uppercase; color:var(--muted); font-weight:800; letter-spacing:0.4px; margin-bottom:2px; }
.dash-stat-mini .val { font-size:1.2rem; font-weight:800; color:var(--text-strong); }

.chart-mock {
    display:flex; align-items:flex-end; gap:6px;
    height:80px; margin-top:10px;
}
.chart-mock .bar {
    flex:1; background:linear-gradient(180deg, var(--accent), rgba(117,81,255,0.3));
    border-radius:4px 4px 0 0; min-height:8px;
}

/* Feature list with icons */
.feat-list { display:grid; gap:10px; margin:14px 0; }
.feat-item {
    display:flex; gap:12px; padding:12px 16px;
    background:var(--bg); border-radius:12px;
    border-left:3px solid var(--accent);
    align-items:flex-start;
}
.feat-item .fi-icon {
    width:36px; height:36px; border-radius:9px;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0;
}
.feat-item .fi-body { flex:1; }
.feat-item .fi-body h6 { font-weight:800; margin:0 0 2px; color:var(--text-strong); font-size:0.88rem; }
.feat-item .fi-body p { font-size:0.82rem; color:var(--muted); margin:0; line-height:1.5; }
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
        <div class="m-section-label">Dashboards</div>
        <a href="10-dashboards.php" class="active"><i class="fa-solid fa-chart-pie"></i><span>Analytics Dashboards</span></a>
        <div class="m-section-label">Dispatching</div>
        <a href="02-internal-letters.php"><i class="fa-solid fa-envelope-open-text"></i><span>Internal Letters</span></a>
        <a href="03-outward-letters.php"><i class="fa-solid fa-paper-plane"></i><span>Outward Letters</span></a>
        <a href="04-task-letters.php"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>
        <div class="m-section-label">Records &amp; Disposal</div>
        <a href="05-final-disposal.php"><i class="fa-solid fa-box-archive"></i><span>Final Disposal</span></a>
        <a href="06-record-room.php"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="07-linkage-reports.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>
        <div class="m-section-label">Administration</div>
        <a href="08-informers.php"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
        <a href="09-administration.php"><i class="fa-solid fa-user-shield"></i><span>Administration</span></a>
        <div class="m-section-label">Help</div>
        <a href="11-help.php"><i class="fa-solid fa-life-ring"></i><span>Help &amp; Glossary</span></a>
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
            <span class="current">Analytics Dashboards</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-chart-pie"></i> Analytics</span>
        <h1>Analytics Dashboards</h1>
        <p>ලිපි වර්ග 4ක (Internal, Outward, Task, Informer) සඳහා තත්‍ය කාලීන දත්ත, ප්‍රස්තාර, සහ ප්‍රගති විශ්ලේෂණය පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 15 min read</span>
            <span><i class="fa-solid fa-signal"></i> INTERMEDIATE</span>
            <span><i class="fa-solid fa-language"></i> ප්‍රස්තාර පුවරු</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-internal"><i class="fa-solid fa-envelope-open-text"></i> Internal</button>
        <button class="m-tab" data-tab="tab-outward"><i class="fa-solid fa-paper-plane"></i> External</button>
        <button class="m-tab" data-tab="tab-task"><i class="fa-solid fa-list-check"></i> Tasking</button>
        <button class="m-tab" data-tab="tab-informer"><i class="fa-solid fa-user-secret"></i> Informer</button>
        <button class="m-tab" data-tab="tab-filters"><i class="fa-solid fa-filter"></i> Filters</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Analytics Dashboards</strong> 4ක් මගින් පද්ධතියේ සියලුම ලිපි ක්‍රියාකාරකම් පිළිබඳ <strong>තත්‍ය කාලීන දර්ශක</strong> සහ <strong>ප්‍රස්තාර</strong> ලබා දෙයි. එක් එක් ලිපි වර්ගය සඳහා අදාළ dashboard එක තෝරාගෙන බලන්න.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-layer-group"></i><span>Dashboards 4ක්</span></div>
            <div class="feat-list">
                <div class="feat-item">
                    <div class="fi-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <div class="fi-body">
                        <h6>Internal Letters Dashboard</h6>
                        <p>අභ්‍යන්තර ලිපි සම්බන්ධ සියලු දර්ශක — status, priority, security, regiments, desks, aging alerts</p>
                    </div>
                </div>
                <div class="feat-item">
                    <div class="fi-icon"><i class="fa-solid fa-paper-plane"></i></div>
                    <div class="fi-body">
                        <h6>External Letters Dashboard</h6>
                        <p>බාහිර ලිපි සම්බන්ධ දර්ශක — approval workflow, dispatch methods, receivers, desk/user performance</p>
                    </div>
                </div>
                <div class="feat-item">
                    <div class="fi-icon"><i class="fa-solid fa-list-check"></i></div>
                    <div class="fi-body">
                        <h6>Tasking Letters Dashboard</h6>
                        <p>කාර්ය ලිපි සම්බන්ධ දර්ශක — reply rates, agencies, overdue breakdown, desk performance</p>
                    </div>
                </div>
                <div class="feat-item">
                    <div class="fi-icon"><i class="fa-solid fa-user-secret"></i></div>
                    <div class="fi-body">
                        <h6>Informer Dashboard</h6>
                        <p>තොරතුරු දෙන්නන් සම්බන්ධ දර්ශක — categories, classifications, letter counts, top informers</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>සියලුම Dashboards වලට පොදු ලක්ෂණ</span></div>
            <ul class="m-steps">
                <li><strong>Animated Stat Counters</strong> — අංක පිටුව load වන විට සජීවීව ගණන් කරයි</li>
                <li><strong>Chart.js Visualizations</strong> — Doughnut, Bar, Line charts</li>
                <li><strong>Real-time Data</strong> — DB එකෙන් සෘජුවම දත්ත</li>
                <li><strong>Filter Bar</strong> — Status, Dates, Desk, Regiment අනුව පෙරහන්</li>
                <li><strong>Dark / Light Theme</strong> — ඔබේ පද්ධතියේ අනෙක් පිටු සමඟ sync වේ</li>
                <li><strong>Responsive Design</strong> — Mobile, Tablet, Desktop සඳහා ගැලපේ</li>
                <li><strong>Recent Activity Feed</strong> — අලුත්ම ලිපි/ක්‍රියාකාරකම්</li>
                <li><strong>Performance Tables</strong> — Regiment, Desk, User, Agency ප්‍රගති සංසන්දනය</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Dashboard Preview</span></div>
            <div class="dash-preview">
                <div class="dash-preview-grid">
                    <div class="dash-stat-mini"><div class="lbl">Total</div><div class="val">248</div></div>
                    <div class="dash-stat-mini" style="border-left-color:#f59e0b;"><div class="lbl">Pending</div><div class="val">32</div></div>
                    <div class="dash-stat-mini" style="border-left-color:#00C853;"><div class="lbl">Completed</div><div class="val">184</div></div>
                    <div class="dash-stat-mini" style="border-left-color:#ef4444;"><div class="lbl">Overdue</div><div class="val">12</div></div>
                </div>
                <div class="chart-mock">
                    <div class="bar" style="height:40%;"></div>
                    <div class="bar" style="height:65%;"></div>
                    <div class="bar" style="height:85%;"></div>
                    <div class="bar" style="height:50%;"></div>
                    <div class="bar" style="height:70%;"></div>
                    <div class="bar" style="height:90%;"></div>
                    <div class="bar" style="height:55%;"></div>
                    <div class="bar" style="height:75%;"></div>
                </div>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Dashboard එකට පිවිසෙන ආකාරය</strong>
                <p>පැති තීරුවෙන් <code>Preview Dashboards</code> section එකේ අදාළ dashboard එක තෝරන්න. උදා: <code>Preview Dashboards → Internal Dashboard</code>.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: INTERNAL DASHBOARD ═══════════════ -->
    <div class="m-tab-panel" id="tab-internal">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-envelope-open-text"></i><span>Internal Letters Dashboard</span></div>
            <p>අභ්‍යන්තර ලිපි පිළිබඳ සම්පූර්ණ විශ්ලේෂණය. URL: <code>internal_dashboard.php</code></p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Letters</strong></td><td>සියලුම අභ්‍යන්තර ලිපි</td></tr>
                        <tr><td><strong>Not Forwarded</strong></td><td>තවම යොමු නොකළ ලිපි (initial action අවශ්‍යයි)</td></tr>
                        <tr><td><strong>Forwarded</strong></td><td>යොමු කර ඇති ලිපි (in circulation)</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සම්පූර්ණ වූ ලිපි + % rate</td></tr>
                        <tr><td><strong>My Inbox</strong></td><td>ඔබට නොබැලූ යොමු කිරීම්</td></tr>
                        <tr><td><strong>Under Final Disposal</strong></td><td>බැහැර කිරීමට යවන ලද ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-pie"></i><span>Charts — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Chart</th><th>වර්ගය</th><th>පෙන්වන දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Status Distribution</strong></td><td>Doughnut</td><td>Status අනුව ලිපි බෙදීම</td></tr>
                        <tr><td><strong>Priority Breakdown</strong></td><td>Bar</td><td>Normal / Urgent / Immediately</td></tr>
                        <tr><td><strong>Monthly Trend</strong></td><td>Line</td><td>පසුගිය මාස 12ක්</td></tr>
                        <tr><td><strong>Security Classification</strong></td><td>Bar (dual)</td><td>Top Secret → Public (Active vs Total)</td></tr>
                        <tr><td><strong>Peak Activity Hours</strong></td><td>Line</td><td>පසුගිය දින 30 තුළ පැය අනුව</td></tr>
                        <tr><td><strong>Top Categories</strong></td><td>Doughnut</td><td>Letter categories අනුව</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Performance Tables — 3ක්</span></div>
            <ul class="m-steps">
                <li><strong>Regiment Performance</strong> — Total · Completed · Progress (රෙජිමේන්තුව අනුව)</li>
                <li><strong>Desk Performance</strong> — Total · In Progress · Final Disposal · Completed (ඩෙස්කය අනුව)</li>
                <li><strong>User Performance</strong> — Total · Completed (පරිශීලකයා අනුව)</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-triangle-exclamation"></i><span>Aging Alerts</span></div>
            <p>
                <strong>High Priority</strong> (Urgent / Immediately) සහ <strong>දින 3ක් පැරණි</strong> ලිපි මෙහි රතු alert card ලෙස පෙන්වයි.
            </p>
            <div class="m-callout warn">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>ඉක්මන් ක්‍රියාමාර්ගය අවශ්‍යයි</strong>
                    <p>මෙම alerts වල CDR අංකය මත click කර ලිපිය විවෘත කරන්න. ඉක්මනින් ක්‍රියාත්මක නොවුනොත් කාර්යක්ෂමතාවය අඩු වේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Recent Letters Feed</span></div>
            <p>අලුත්ම ලිපි 10ක් — CDR, Title, Priority, Status සමඟ. දකුණු පසින් අදාළ page එකට link එකක් ඇත.</p>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-key"></i>
            <div>
                <strong>Permission අවශ්‍යයි</strong>
                <p><span class="perm-code">VIEW_INTERNAL_LETTERS</span> — මෙම dashboard එකට ප්‍රවේශය සඳහා</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: EXTERNAL DASHBOARD ═══════════════ -->
    <div class="m-tab-panel" id="tab-outward">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-paper-plane"></i><span>External Letters Dashboard</span></div>
            <p>බාහිර ලිපි පිළිබඳ විශ්ලේෂණය. URL: <code>external_dashboard.php</code></p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Letters</strong></td><td>සියලුම බාහිර ලිපි</td></tr>
                        <tr><td><strong>Pending</strong></td><td>යැවීමට පොරොත්තුවෙන්</td></tr>
                        <tr><td><strong>Completed</strong></td><td>යවා අවසන් + % rate</td></tr>
                        <tr><td><strong>Pending Approval</strong></td><td>RA අනුමැතිය බලාපොරොත්තුවෙන්</td></tr>
                        <tr><td><strong>Approved</strong></td><td>අනුමත වූ</td></tr>
                        <tr><td><strong>Rejected</strong></td><td>ප්‍රතික්ෂේප වූ</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-pie"></i><span>Charts — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Chart</th><th>වර්ගය</th><th>පෙන්වන දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Status Distribution</strong></td><td>Doughnut</td><td>Pending / Completed</td></tr>
                        <tr><td><strong>Approval Status</strong></td><td>Doughnut</td><td>Pending RA / Approved / Rejected</td></tr>
                        <tr><td><strong>Dispatch Method</strong></td><td>Bar</td><td>Email / By Hand / Post / Courier</td></tr>
                        <tr><td><strong>Monthly Trend</strong></td><td>Line</td><td>මාස 12ක් (Total vs Completed)</td></tr>
                        <tr><td><strong>Peak Activity Hours</strong></td><td>Line</td><td>පැය අනුව submissions</td></tr>
                        <tr><td><strong>Top Receivers</strong></td><td>Bar</td><td>External branches 10ක්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Performance Tables — 3ක්</span></div>
            <ul class="m-steps">
                <li><strong>Receiver Performance</strong> — External branches — Total · Completed · Progress</li>
                <li><strong>Desk Performance</strong> — Creator ගේ ඩෙස්කය අනුව</li>
                <li><strong>User Performance</strong> — එක් එක් user විසින් සාදන ලද ලිපි</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hourglass-half"></i><span>Approval Aging Alerts</span></div>
            <p>
                <strong>Pending RA Approval</strong> සහ <strong>දින 3ක් පැරණි</strong> ලිපි පෙන්වයි.
                CDR link එක මත click කර විස්තර බලන්න.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Recent Letters Feed</span></div>
            <p>අලුත්ම ලිපි 10ක්. හැම එකකම Dispatch Method, Approval status, Status badge — සියල්ල එකවර දැකිය හැක.</p>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-key"></i>
            <div>
                <strong>Permission අවශ්‍යයි</strong>
                <p><span class="perm-code">VIEW_OUTWARD_LETTERS</span></p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: TASK DASHBOARD ═══════════════ -->
    <div class="m-tab-panel" id="tab-task">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Task Letters Dashboard</span></div>
            <p>කාර්ය ලිපි සහ රෙජිමේන්තු පිළිතුරු පිළිබඳ විශ්ලේෂණය. URL: <code>task_dashboard.php</code></p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-robot"></i><span>Auto Status Update</span></div>
            <p>මෙම dashboard එක load වන විට, පද්ධතිය ස්වයංක්‍රීයව සියලුම task වල තත්ත්වය නැවත ගණනය කරයි:</p>
            <div class="m-code" data-lang="logic">
IF no replies: Status = 'Pending'
ELSE IF replies < assigned: Status = 'Partially Replied'
ELSE: Status = 'Completed'</div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 6ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Tasks</strong></td><td>සියලුම කාර්ය ලිපි</td></tr>
                        <tr><td><strong>Pending</strong></td><td>පිළිතුරු නොමැති</td></tr>
                        <tr><td><strong>Partially Replied</strong></td><td>සමහර රෙජිමේන්තු පිළිතුරු දී ඇත</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සියල්ලම පිළිතුරු දී ඇත</td></tr>
                        <tr><td><strong>Overdue</strong></td><td>Deadline පසු වූ</td></tr>
                        <tr><td><strong>High Priority</strong></td><td>ඉහළ ප්‍රමුඛතාව, සම්පූර්ණ නොවූ</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-pie"></i><span>Charts</span></div>
            <ul class="m-steps">
                <li><strong>Status Distribution</strong> (Doughnut) — Pending / Partial / Completed</li>
                <li><strong>Priority Breakdown</strong> (Bar) — High / Medium / Low</li>
                <li><strong>Task Creation Trend</strong> (Line) — පසුගිය දින 30</li>
                <li><strong>Overdue Breakdown</strong> (Custom bars) — 1-7 / 8-14 / 15-30 / 30+ days</li>
                <li><strong>Reply Activity</strong> (Line) — පසුගිය දින 7</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Performance Tables — 2ක්</span></div>
            <ul class="m-steps">
                <li>
                    <strong>Agency Performance</strong> — සෑම රෙජිමේන්තුවකම:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Assigned tasks ගණන</li>
                        <li>Replied tasks ගණන</li>
                        <li>Reply rate % — Progress bar සමඟ</li>
                    </ul>
                </li>
                <li>
                    <strong>Desk Performance</strong> — සෑම ඩෙස්කයකම:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Total tasks</li>
                        <li>Pending / Partial / Completed</li>
                        <li>Progress %</li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-clock-rotate-left"></i><span>Recent Tasks Feed</span></div>
            <p>අලුත්ම task 8ක් — Task CDR, Title, Assigner, Agency count, Reply count, Overdue අවවාදය සමඟ.</p>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-key"></i>
            <div>
                <strong>Permission අවශ්‍යයි</strong>
                <p><span class="perm-code">VIEW_TASK_LETTERS</span></p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: INFORMER DASHBOARD ═══════════════ -->
    <div class="m-tab-panel" id="tab-informer">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-secret"></i><span>Informer Dashboard</span></div>
            <p>තොරතුරු දෙන්නන් පිළිබඳ විශ්ලේෂණය. URL: <code>Informer_dashboard.php</code></p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 4ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Informers</strong></td><td>සියලුම තොරතුරු දෙන්නන්</td></tr>
                        <tr><td><strong>Active</strong></td><td>ක්‍රියාකාරී</td></tr>
                        <tr><td><strong>Inactive</strong></td><td>අක්‍රීය</td></tr>
                        <tr><td><strong>Total Letters</strong></td><td>ඔවුන්ට සම්බන්ධ මුළු ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-pie"></i><span>Charts — 4ක්</span></div>
            <ul class="m-steps">
                <li><strong>Category Distribution</strong> (Doughnut) — Informer / Casual Contact / Agent</li>
                <li><strong>Classification Breakdown</strong> (Bar) — Class A to F</li>
                <li><strong>Regiment Distribution</strong> (Bar) — රෙජිමේන්තු අනුව top 10</li>
                <li><strong>Status Distribution</strong> (Doughnut) — Active vs Inactive</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Performance Tables — 3ක්</span></div>
            <ul class="m-steps">
                <li><strong>Top Informers</strong> — වඩාත්ම ලිපි සම්බන්ධ 5ක්</li>
                <li><strong>Recent Informers</strong> — අලුතින් ලියාපදිංචි වූ 5ක්</li>
                <li>
                    <strong>Informer Branch Performance</strong> — Pivot table:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Rows: Informers (code අනුව)</li>
                        <li>Columns: External branches</li>
                        <li>Cells: එම branch එකට යවන ලද outward ලිපි ගණන</li>
                    </ul>
                </li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Full Registry Table</span></div>
            <p>පහළින් සියලුම තොරතුරු දෙන්නන් විස්තරාත්මක table එකක් ලෙස පෙන්වයි:</p>
            <ul class="m-steps">
                <li><strong>Search</strong> — Code හෝ Name අනුව සොයන්න</li>
                <li>Columns — Code, Name, Category, Class, Regiment, Handler, Status, Letters, Actions</li>
                <li><strong>Actions</strong> — View (විස්තර) සහ Edit (සංශෝධනය)</li>
            </ul>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-key"></i>
            <div>
                <strong>Permission අවශ්‍යයි</strong>
                <p><span class="perm-code">ACCESS_INFORMER_DASHBOARD</span></p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: FILTERS ═══════════════ -->
    <div class="m-tab-panel" id="tab-filters">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-filter"></i><span>Filter Bar — පොදු භාවිතය</span></div>
            <p>සියලුම dashboards වල ඉහළින් <strong>Filter Bar</strong> එකක් ඇත. එකම filters දෙකක් හෝ වැඩි ගණනක් එකවර භාවිතා කළ හැක.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Dashboard එක අනුව Filters</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Filter</th>
                            <th>Internal</th>
                            <th>External</th>
                            <th>Task</th>
                            <th>Informer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Status</strong></td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><strong>Approval Status</strong></td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td></tr>
                        <tr><td><strong>Dispatch Method</strong></td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td></tr>
                        <tr><td><strong>Desk</strong></td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td></tr>
                        <tr><td><strong>Regiment</strong></td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><strong>Date From / To</strong></td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><strong>Category</strong></td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><strong>Classification</strong></td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td><strong>Search</strong></td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Filter කරන ආකාරය</span></div>
            <ol class="m-steps">
                <li>අවශ්‍ය values තෝරන්න (Status, Desk, Date පරාසය, ආදිය).</li>
                <li><strong>Apply</strong> බොත්තම ඔබන්න.</li>
                <li>Dashboard එක අලුත් දත්ත සමඟ නැවත load වේ.</li>
                <li>පෙරහන් ඉවත් කිරීමට <strong>Reset</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>Filters එකවර multiple ලෙස භාවිතා කළ හැක. උදා: <em>Desk = Admin + Date From = 2026-01-01</em> — එවිට එම ඩෙස්කයේ එම දිනයෙන් පසු සියලු දත්ත පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-palette"></i><span>Theme Switch</span></div>
            <p>සෑම dashboard එකකම ඉහළ දකුණේ <strong>Theme Toggle</strong> ඇත:</p>
            <ul class="m-steps">
                <li>🌙 <strong>Dark Mode</strong> — රාත්‍රී වේලාවට හොඳයි. ඇස්වලට පහසුයි.</li>
                <li>☀️ <strong>Light Mode</strong> — දිවා කාලයට හොඳයි. පැහැදිලියි.</li>
                <li>ඔබේ තේරීම ස්වයංක්‍රීයව සුරැකේ — ඊළඟ වතාවේ පිවිසෙන විටත් එම තේරීමම පෙන්වයි.</li>
                <li>Charts වල වර්ණ ද theme එකට ගැලපේ.</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Charts වල භාවිතය</span></div>
            <ul class="m-steps">
                <li><strong>Hover කරන්න</strong> — Chart elements මත mouse එක ගෙන ගියහොත් අගය පෙන්වයි.</li>
                <li><strong>Legend Click</strong> — Legend item එකක් click කළහොත් එය hide/show වේ.</li>
                <li><strong>Animated</strong> — පිටුව load වන විට charts වල animation එකක් ඇත.</li>
                <li><strong>Responsive</strong> — ඔබේ screen එකට ගැලපීමට auto-size වේ.</li>
            </ul>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Performance Note</strong>
                <p>Dashboards එකවර 4ක් load නොකරන්න. එකක් බලලා අනෙකට යාම වේගවත් සහ resource-හිතකරයි.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="10-dashboards" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="09-administration.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Administration</span></a>
        <a href="11-help.php" class="next"><span class="lbl">Next →</span><span class="ttl">Help &amp; Glossary</span></a>
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