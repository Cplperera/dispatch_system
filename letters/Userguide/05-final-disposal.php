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
<title>Final Disposal | IDCMS Manual</title>
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

/* Workflow */
.workflow { display:flex; align-items:stretch; gap:0; margin:20px 0; flex-wrap:wrap; justify-content:center; }
.wf-step { flex:1; min-width:140px; background:var(--card); border:2px solid var(--border); border-radius:14px; padding:16px 12px; text-align:center; margin:6px 4px; }
.wf-step .wf-icon { width:44px; height:44px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
.wf-step .wf-title { font-weight:800; font-size:0.8rem; margin-bottom:2px; }
.wf-step .wf-desc { font-size:0.7rem; color:var(--muted); }
.wf-step.pending .wf-icon { background: #FFAB00; }
.wf-step.process .wf-icon { background: #3b82f6; }
.wf-step.completed .wf-icon { background: #00C853; }

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.pending   { background:rgba(255,171,0,0.15); color: #FFAB00; }
.chip.completed { background:rgba(0,200,83,0.15); color: #00C853; }
.chip.active    { background:rgba(59,130,246,0.15); color: #3b82f6; }

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
        <a href="04-task-letters.php"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>

        <div class="m-section-label">Records &amp; Disposal</div>
        <a href="05-final-disposal.php" class="active"><i class="fa-solid fa-box-archive"></i><span>Final Disposal</span></a>
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
            <span class="current">Final Disposal</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-box-archive"></i> Records &amp; Disposal</span>
        <h1>Final Disposal</h1>
        <p>ලිපියක් අවසන් වශයෙන් බැහැර කිරීම, භෞතික ගොනුවකට ඇතුළත් කිරීම සහ සංරක්ෂණය පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 18 min read</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-inbox"><i class="fa-solid fa-inbox"></i> Disposal Inbox</button>
        <button class="m-tab" data-tab="tab-forward"><i class="fa-solid fa-share"></i> Forward to Disposal</button>
        <button class="m-tab" data-tab="tab-process"><i class="fa-solid fa-file-import"></i> Process Disposal</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Final Disposal</strong> module එක මගින්, සියලුම කටයුතු අවසන් වූ ලිපියක් <strong>ස්ථිරවම බැහැර කර, භෞතික ගොනුවකට (Physical File) ඇතුළත් කර</strong> සංරක්ෂණය කිරීම සිදු වේ. එයින් පසු ලිපියට නැවත කිසිදු ක්‍රියාකාරකමක් සිදු කළ නොහැක.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 3ක්</span></div>
            <ul class="m-steps">
                <li><strong>Disposal Inbox</strong> — ඔබේ ඩෙස්කයට බැහැර කිරීමට ලැබුණු ලිපි ලැයිස්තුව</li>
                <li><strong>Forward to Disposal</strong> — ලිපියක් බැහැර කිරීමට යොමු කිරීම (Registry එකේ සිට)</li>
                <li><strong>Process Disposal</strong> — භෞතික ගොනුවකට ඇතුළත් කර බැහැර කිරීම සම්පූර්ණ කිරීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>ප්‍රධාන භූමිකා</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Role</th><th>භූමිකාව</th><th>කරන කටයුතු</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Registry Officer</strong></td><td>ලේඛන නිලධාරී</td><td>ලිපියක් බැහැර කිරීමට යොමු කිරීම</td></tr>
                        <tr><td><strong>Desk Officer</strong></td><td>ඩෙස්ක් නිලධාරී</td><td>තමන්ගේ ඩෙස්කයට ලැබුණු ලිපි බැහැර කිරීම සහ ගොනු කිරීම</td></tr>
                        <tr><td><strong>Record Keeper</strong></td><td>වාර්තා සංරක්ෂක</td><td>භෞතික ගොනු කළමනාකරණය</td></tr>
                        <tr><td><strong>Administrator</strong></td><td>පරිපාලක</td><td>සියලුම ක්‍රියාකාරකම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>Status Types</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip pending"><i class="fa-solid fa-clock"></i> Pending</span>
                <span class="chip active"><i class="fa-solid fa-rotate"></i> Processing</span>
                <span class="chip completed"><i class="fa-solid fa-check"></i> Completed</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Status</th><th>අර්ථය</th><th>කවදාද?</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Pending</strong></td><td>පොරොත්තුවෙන්</td><td>ඩෙස්කයට ලැබී ඇත, නමුත් ක්‍රියා කර නැහැ</td></tr>
                        <tr><td><strong>Processing</strong></td><td>ක්‍රියාත්මක වෙමින්</td><td>භෞතික ගොනුවකට ඇතුළත් කරමින්</td></tr>
                        <tr><td><strong>Completed</strong></td><td>සම්පූර්ණයි</td><td>ගොනුවට ඇතුළත් කර අවසන්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>වැදගත්</strong>
                <p>Final Disposal ක්‍රියාවලිය <strong>ආපසු හැරවිය නොහැක</strong>. ලිපිය භෞතික ගොනුවකට ඇතුළත් කළ පසු, එය නැවත edit කළ නොහැක, reject කළ නොහැක, සහ නැවත වෙනත් ඩෙස්කයකට යැවිය නොහැක.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: DISPOSAL INBOX ═══════════════ -->
    <div class="m-tab-panel" id="tab-inbox">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-inbox"></i><span>Disposal Inbox — ඔබේ බැහැර කිරීම්</span></div>
            <p>
                <code>final_disposal_inbox.php</code> page එක මගින්, ඔබේ ඩෙස්කයට බැහැර කිරීම සඳහා ලැබී ඇති ලිපි ලැයිස්තුව පෙන්වයි.
            </p>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>මතක තබා ගන්න</strong>
                    <p>මෙම page එක භාවිතා කළ හැක්කේ <strong>ඩෙස්කයකට පැවරී ඇති නිලධාරීන්ට පමණයි</strong>. ඩෙස්කයක් නොමැති නම් <em>"System Error: Your account is not assigned to a desk."</em> පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Inbox එක භාවිතා කරන ආකාරය</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>My Inbox → Disposal Inbox</code> තෝරන්න.</li>
                <li>ඉහළ දකුණේ ඔබේ <strong>Active Desk</strong> එක පෙන්වයි.</li>
                <li>ලැබී ඇති සියලුම බැහැර කිරීම් ලැයිස්තුවක් ලෙස පෙන්වයි.</li>
                <li>ඕනෑම ලිපියක් මත ක්‍රියා කරන්න.</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Ref No</strong></td><td>CDR අංකය (monospace font, purple badge)</td></tr>
                        <tr><td><strong>Title</strong></td><td>ලිපියේ මාතෘකාව</td></tr>
                        <tr><td><strong>Forwarded By</strong></td><td>ලිපිය යොමු කළ නිලධාරියාගේ නම</td></tr>
                        <tr><td><strong>Date</strong></td><td>යොමු කළ දිනය (dd-Mmm-yyyy)</td></tr>
                        <tr><td><strong>Action</strong></td><td>Preview (👁) සහ File (📥) බොත්තම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම්</span></div>
            <ul class="m-steps">
                <li>
                    <strong>Preview (👁)</strong> — ලිපියේ attachment එක (PDF) modal එකක පෙන්වයි. Attachment එකක් නැත්නම් මෙම බොත්තම නොපෙනේ.
                </li>
                <li>
                    <strong>File (📥)</strong> — බැහැර කිරීමේ ක්‍රියාවලිය ආරම්භ කිරීමට <code>process_disposal.php</code> page එකට යයි.
                </li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-magnifying-glass"></i><span>සෙවුම</span></div>
            <p>ඉහළින් ඇති search box එකෙන් පහත අනුව පෙරහන් කරන්න පුළුවන්:</p>
            <ul class="m-steps">
                <li><strong>CDR No</strong> — උදා: <code>REG/2026/13</code></li>
                <li><strong>Subject</strong> — ලිපියේ මාතෘකාව</li>
                <li><strong>Sender</strong> — යොමු කළ නිලධාරියාගේ නම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>PDF Preview Modal</span></div>
            <p>Attachment එක ඇති ලිපි සඳහා <i class="fa-solid fa-eye"></i> අයිකනය මත ක්ලික් කළ විට:</p>
            <ul class="m-steps">
                <li>විශාල modal එකක් (95% viewport) විවෘත වේ</li>
                <li>PDF එක කෙලින්ම embed වේ</li>
                <li>Modal header එකේ CDR සහ Title පෙන්වයි</li>
                <li>ESC key එකෙන් හෝ × බොත්තමෙන් වහන්න පුළුවන්</li>
            </ul>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>PDF එක කියවා, ලිපිය බැහැර කිරීමට සුදුසු ද යන්න තහවුරු කරගෙන පසුව <strong>File</strong> බොත්තම ඔබන්න.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-inbox"></i><span>Empty State</span></div>
            <p>ඔබේ ඩෙස්කයට ලිපි නොමැති නම් <em>"No pending disposal items"</em> ලෙස පෙන්වයි.</p>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: FORWARD TO DISPOSAL ═══════════════ -->
    <div class="m-tab-panel" id="tab-forward">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-share"></i><span>ලිපියක් බැහැර කිරීමට යොමු කිරීම</span></div>
            <p>
                <code>forward_final_disposal.php</code> page එක මගින්, කටයුතු අවසන් වූ ලිපියක් <strong>භෞතිකව ගොනු කිරීම සඳහා අදාළ ඩෙස්කයකට</strong> යොමු කළ හැක.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>Registry එකේ (Internal Letters list) ලිපියේ <strong>"Final Disposal"</strong> බොත්තම ඔබන්න.</li>
                <li>
                    Info box එකේ ලිපියේ CDR අංකය සහ මාතෘකාව පෙන්වයි.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <p>මෙම පිටුවට පිවිසෙන්න <code>letter_id</code> parameter එකක් අවශ්‍යයි. නැත්නම් "Invalid Request" පෙන්වයි.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Destination Storage Desk</strong> — ලිපිය ගොනු කළ යුතු ඩෙස්කය තෝරන්න.
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li>Active සහ ඉවත් නොකළ ඩෙස්ක පමණක් පෙන්වයි</li>
                        <li>ඩෙස්ක නාමය අනුව A-Z ලෙස තෝරා ඇත</li>
                    </ul>
                </li>
                <li>
                    <strong>Final Instructions / Disposal Remarks</strong> — අමතර උපදෙස් හෝ ටිප්පණි ලියන්න (optional).
                    <br><em style="font-size:0.85rem;color:var(--muted);">උදා: "Filed the Correct Reference"</em>
                </li>
                <li>
                    <strong>Submit for Disposal</strong> බොත්තම ඔබන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <p>සාර්ථක වූ පසු, <code>internal_letters.php?status=forwarded_success</code> ට redirect වේ.</p>
                        </div>
                    </div>
                </li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>Database Updates</span></div>
            <p>මෙම form එක submit වූ විට පද්ධතිය පහත update දෙක සිදු කරයි:</p>
            <div class="m-code" data-lang="sql">
-- 1. Internal Letter එකේ තත්ත්වය යාවත්කාලීන කිරීම
UPDATE internal_letters 
SET is_under_final_disposal = 1, 
    status = 'Completed' 
WHERE id = [letter_id];

-- 2. බැහැර කිරීමේ record එකක් සෑදීම
INSERT INTO internal_letters_final_disposal 
    (letter_id, forwarded_to, forwarded_by, remarks, status, forwarded_at) 
VALUES 
    ([letter_id], [desk_id], [user_id], [remarks], 'Pending', NOW());</div>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Transaction Safety</strong>
                    <p>මෙම update දෙකම <strong>එකම transaction එකක්</strong> ලෙස සිදු වේ. එකක් fail වුනොත් දෙකම rollback වේ — දත්ත අසම්පූර්ණ නොවේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-ban"></i><span>නියමිත සීමාවන්</span></div>
            <ul class="m-steps">
                <li>දැනටමත් <code>is_under_final_disposal = 1</code> වූ ලිපියක් ආයෙත් යොමු කළ නොහැක</li>
                <li>වලංගු ඩෙස්කයක් තෝරා නැත්නම් error එකක් පෙන්වයි</li>
                <li>ලිපිය ඉවත් කර (soft delete) ඇත්නම් යොමු කළ නොහැක</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: PROCESS DISPOSAL ═══════════════ -->
    <div class="m-tab-panel" id="tab-process">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-file-import"></i><span>Process Disposal — බැහැර කිරීම සම්පූර්ණ කිරීම</span></div>
            <p>
                <code>process_disposal.php</code> page එක මගින්, ඩෙස්කයට ලැබී ඇති බැහැර කිරීමේ ඉල්ලීම <strong>සත්‍ය ලෙසම සම්පූර්ණ කරයි</strong> — එනම් ලිපිය භෞතික ගොනුවකට ඇතුළත් කරයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>Disposal Inbox එකේ ලිපියේ <strong>File</strong> බොත්තම ඔබන්න.</li>
                <li>
                    Info box එකේ ලිපියේ CDR සහ මාතෘකාව පෙන්වයි.
                </li>
                <li>
                    <strong>Target Physical File Reference</strong> — ලිපිය ඇතුළත් කළ යුතු භෞතික ගොනුව තෝරන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div>
                            <strong>Auto Folio Number</strong>
                            <p>පද්ධතිය ස්වයංක්‍රීයව <strong>ඊළඟ folio අංකය</strong> ගණනය කරයි. ඔබට අතින් ඇතුළත් කිරීමට අවශ්‍ය නැහැ.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Complete Filing &amp; Close Case</strong> බොත්තම ඔබන්න.
                </li>
                <li>සාර්ථක වූ පසු, Disposal Inbox එකට redirect වේ. ලිපිය දැන් බැහැර කර අවසන්.</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-calculator"></i><span>Folio Number Auto-Generation</span></div>
            <p>එකම ගොනුවකට ලිපි කිහිපයක් ඇතුළත් කළ හැක. සෑම ලිපියකටම අනුක්‍රමික folio අංකයක් ලැබේ:</p>
            <div class="m-code" data-lang="example">
File: PFR/2026/INT/001

Letter 1 → Folio No: 1
Letter 2 → Folio No: 2
Letter 3 → Folio No: 3
...</div>

            <p style="margin-top:12px;">පද්ධතිය මෙසේ ගණනය කරයි:</p>
            <div class="m-code" data-lang="sql">
-- පවතින ගොනුවේ ලිපි ගණන ලබා ගැනීම
SELECT COUNT(*) as current_count 
FROM internal_letters_final_disposal_details 
WHERE file_number = '[selected_file]';

-- ඊළඟ folio = පවතින ගණන + 1
next_folio = current_count + 1;</div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>Database Updates (4 Tables)</span></div>
            <p>මෙම form එක submit වූ විට පද්ධතිය පහත update 4 සිදු කරයි:</p>
            <div class="m-code" data-lang="sql">
-- 1. බැහැර කිරීමේ විස්තර record එක සෑදීම
INSERT INTO internal_letters_final_disposal_details 
    (letter_id, file_number, file_location, disposed_by, status, disposed_at) 
VALUES 
    ([letter_id], [file_ref], 'Folio No: X', [user_id], 'Completed', NOW());

-- 2. ඩෙස්කයේ බැහැර කිරීමේ queue එක යාවත්කාලීන කිරීම
UPDATE internal_letters_final_disposal 
SET status = 'Completed', disposed_at = NOW() 
WHERE id = [disposal_id];

-- 3. Internal Letter එකේ අවසන් තත්ත්වය
UPDATE internal_letters 
SET status = 'Completed', is_under_final_disposal = 1 
WHERE id = [letter_id];</div>

            <div class="m-callout info">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong>Transaction Safety</strong>
                    <p>මෙම update සියල්ලම එකම transaction එකක යෙදී ඇත. එකක් fail වුනොත් සියල්ලම rollback වේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම්</span></div>
            <ul class="m-steps">
                <li><strong>Back to Inbox</strong> — Disposal Inbox එකට නැවත යාම (කිසිවක් සුරකින්නේ නැහැ)</li>
                <li><strong>Complete Filing &amp; Close Case</strong> — බැහැර කිරීම සම්පූර්ණ කිරීම</li>
            </ul>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>අවසාන ක්‍රියාව</strong>
                    <p>මෙය <strong>ආපසු හැරවිය නොහැකි</strong> ක්‍රියාවක්. ලිපිය දැන් ස්ථිරවම ගොනු කර අවසන්. Edit, Delete, Reject කිසිවක් කළ නොහැක.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-lock"></i><span>නියමිත සීමාවන්</span></div>
            <ul class="m-steps">
                <li>දැනටමත් <strong>Completed</strong> වූ බැහැර කිරීමක් ආයෙත් process කළ නොහැක</li>
                <li>වලංගු ගොනුවක් තෝරා නැත්නම් error එකක් පෙන්වයි</li>
                <li>මෙම page එකට පිවිසෙන්න <code>FINAL_DISPOSAL</code> permission එක අවශ්‍යයි</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>Final Disposal Lifecycle</span></div>
            <div class="workflow">
                <div class="wf-step completed">
                    <div class="wf-icon"><i class="fa-solid fa-check"></i></div>
                    <div class="wf-title">1. Case Closed</div>
                    <div class="wf-desc">සියලු කටයුතු අවසන්</div>
                </div>
                <div class="wf-step pending">
                    <div class="wf-icon"><i class="fa-solid fa-share"></i></div>
                    <div class="wf-title">2. Forwarded</div>
                    <div class="wf-desc">ඩෙස්කයකට යොමු කළා</div>
                </div>
                <div class="wf-step pending">
                    <div class="wf-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="wf-title">3. Pending</div>
                    <div class="wf-desc">ඩෙස්කය බලාපොරොත්තුවෙන්</div>
                </div>
                <div class="wf-step process">
                    <div class="wf-icon"><i class="fa-solid fa-file-import"></i></div>
                    <div class="wf-title">4. Processing</div>
                    <div class="wf-desc">ගොනු කරමින්</div>
                </div>
                <div class="wf-step completed">
                    <div class="wf-icon"><i class="fa-solid fa-box-archive"></i></div>
                    <div class="wf-title">5. Filed</div>
                    <div class="wf-desc">ගොනුවට ඇතුළත්</div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-route"></i><span>සම්පූර්ණ ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>ලිපිය සම්පූර්ණ වීම</strong> — සියලුම කටයුතු අවසන් වූ පසු status <code>Completed</code> වේ</li>
                <li><strong>Forward to Disposal</strong> — Registry නිලධාරී විසින් අදාළ ඩෙස්කයට යොමු කරයි</li>
                <li><strong>Disposal Inbox</strong> — ඩෙස්කයට ලැබේ, status <code>Pending</code></li>
                <li><strong>Process Disposal</strong> — ඩෙස්ක් නිලධාරී භෞතික ගොනුවකට ඇතුළත් කරයි</li>
                <li><strong>Filed</strong> — ලිපිය ගොනු කර අවසන්, status <code>Completed</code></li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>මැදිහත් වන Tables 4ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Table</th><th>කරන කාර්යය</th></tr></thead>
                    <tbody>
                        <tr><td><code>internal_letters</code></td><td>ලිපියේ status සහ is_under_final_disposal flags</td></tr>
                        <tr><td><code>internal_letters_final_disposal</code></td><td>ඩෙස්කයට යොමු කිරීමේ queue</td></tr>
                        <tr><td><code>internal_letters_final_disposal_details</code></td><td>භෞතික ගොනුවට ඇතුළත් කිරීමේ විස්තර (folio අංකය සමඟ)</td></tr>
                        <tr><td><code>physical_file_records</code></td><td>භෞතික ගොනු ලැයිස්තුව</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-shield"></i><span>Data Integrity</span></div>
            <ul class="m-steps">
                <li>සෑම ක්‍රියාවක්ම MySQL <strong>transaction</strong> එකක් තුළ සිදු වේ</li>
                <li>එක update එකක් fail වුනොත් සියල්ලම rollback වේ</li>
                <li>දත්ත අසම්පූර්ණ තත්ත්වයකට පත් නොවේ</li>
                <li>Status transitions නිශ්චිතව පාලනය වේ</li>
            </ul>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: PERMISSIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-perms">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permission Codes</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Permission</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="perm-code">FINAL_DISPOSAL</span></td>
                            <td>මුළු Final Disposal module එකටම ප්‍රවේශය</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;">
                මෙම permission එක නැත්නම්, පහත පිටු 3ටම ප්‍රවේශය නොමැත:
            </p>
            <ul class="m-steps">
                <li>Disposal Inbox (final_disposal_inbox.php)</li>
                <li>Forward to Disposal (forward_final_disposal.php)</li>
                <li>Process Disposal (process_disposal.php)</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-shield"></i><span>Desk අවශ්‍යතාවය</span></div>
            <p>Final Disposal module එක භාවිතා කිරීමට ඔබව <strong>ඩෙස්කයකට පැවරී තිබිය යුතුයි</strong>.</p>
            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Desk නොමැති නම්</strong>
                    <p>ඔබේ ගිණුමට ඩෙස්කයක් පැවරී නැත්නම් <em>"System Error: Your account is not assigned to a desk."</em> ලෙස පෙන්වයි. ඔබේ පද්ධති පරිපාලක අමතන්න.</p>
                </div>
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
                            <th>Desk Officer</th>
                            <th>Record Keeper</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>View Disposal Inbox</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Forward to Disposal</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Process Disposal</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Preview Attachment</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Search Inbox</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Access Denied පෙන්වයි නම්</strong>
                <p>ඔබට <span class="perm-code">FINAL_DISPOSAL</span> permission එක නැහැ හෝ ඔබේ ගිණුමට ඩෙස්කයක් පැවරී නැහැ. පද්ධති පරිපාලක අමතන්න.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="05-final-disposal" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="04-task-letters.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Task Letters</span></a>
        <a href="06-record-room.php" class="next"><span class="lbl">Next →</span><span class="ttl">Record Room</span></a>
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