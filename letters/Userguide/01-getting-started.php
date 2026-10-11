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
<title>Getting Started | IDCMS Manual</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="assets/manual.css" rel="stylesheet">
<style>
/* ── Tabs ── */
.m-tabs {
    display: flex; gap: 4px; margin: 24px 0 20px;
    border-bottom: 2px solid var(--border); overflow-x: auto;
    scrollbar-width: none;
}
.m-tabs::-webkit-scrollbar { display: none; }
.m-tab {
    padding: 12px 20px; background: transparent; border: none;
    color: var(--muted); font-weight: 700; font-size: 0.88rem;
    cursor: pointer; border-bottom: 3px solid transparent;
    transition: 0.2s; white-space: nowrap; font-family: inherit;
    display: inline-flex; align-items: center; gap: 8px;
    margin-bottom: -2px;
}
.m-tab:hover { color: var(--accent); }
.m-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.m-tab i { font-size: 0.9rem; }
.m-tab-panel { display: none; animation: fadeIn 0.25s ease; }
.m-tab-panel.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
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
        <a href="01-getting-started.php" class="active"><i class="fa-solid fa-rocket"></i><span>Getting Started</span></a>

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
            <span class="current">Getting Started</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-rocket"></i> Getting Started</span>
        <h1>Getting Started</h1>
        <p>IDCMS පද්ධතියට පිවිසීම, Dashboard එක තේරුම් ගැනීම සහ ඔබේ භූමිකාව හඳුනා ගැනීම.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 12 min read</span>
        </div>
    </div>

    <!-- Tabs -->
    <div class="m-tabs" id="tabs">
        <button class="m-tab active" data-tab="tab-intro"><i class="fa-solid fa-circle-info"></i> Introduction</button>
        <button class="m-tab" data-tab="tab-login"><i class="fa-solid fa-right-to-bracket"></i> Login</button>
        <button class="m-tab" data-tab="tab-dashboard"><i class="fa-solid fa-table-cells-large"></i> Dashboard</button>
        <button class="m-tab" data-tab="tab-roles"><i class="fa-solid fa-users"></i> Roles &amp; Permissions</button>
    </div>

    <!-- ═══════ TAB 1: Introduction ═══════ -->
    <div class="m-tab-panel active" id="tab-intro">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>IDCMS යනු කුමක්ද?</span></div>
            <p><strong>IDCMS (Integrated Document & Correspondence Management System)</strong> යනු ආයතනික ලිපි සහ සන්නිවේදන කටයුතු කළමනාකරණය සඳහා නිර්මාණය කරන ලද පද්ධතියකි. මෙය ලිපි ලියාපදිංචි කිරීම, යොමු කිරීම, කාර්ය පැවරීම, ගොනු කිරීම සහ අවසන් බැහැර කිරීම — සියල්ල එකම තැනකින් කළමනාකරණය කිරීමට ඉඩ සලසයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන Modules</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Module</th><th>අරමුණ</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Internal Letters</strong></td><td>අභ්‍යන්තර ලිපි</td><td>ආයතනය තුළ ලිපි ලියාපදිංචි කිරීම සහ යොමු කිරීම</td></tr>
                        <tr><td><strong>Outward Letters</strong></td><td>බාහිර ලිපි</td><td>බාහිර ආයතන වෙත ලිපි යැවීම</td></tr>
                        <tr><td><strong>Task Letters</strong></td><td>කාර්ය ලිපි</td><td>රෙජිමේන්තු/අංශ වලට කාර්ය පැවරීම</td></tr>
                        <tr><td><strong>Informers</strong></td><td>තොරතුරු දෙන්නන්</td><td>තොරතුරු දෙන්නන්ගේ විස්තර</td></tr>
                        <tr><td><strong>Record Room</strong></td><td>වාර්තා කාමරය</td><td>ගොනු සෑදීම සහ නිකුත් කිරීම</td></tr>
                        <tr><td><strong>Final Disposal</strong></td><td>අවසන් බැහැර කිරීම</td><td>ලිපි අවසන් කිරීම සහ සංරක්ෂණය</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>ලිපියක ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>Register</strong> — ලිපිය පද්ධතියට ඇතුළත් කිරීම (Internal Letter)</li>
                <li><strong>Forward</strong> — අදාළ නිලධාරියාට හෝ ඩෙස්කයට යොමු කිරීම (minute සමඟ)</li>
                <li><strong>Task</strong> — අවශ්‍ය නම් රෙජිමේන්තු වලට කාර්ය පැවරීම</li>
                <li><strong>Reply</strong> — රෙජිමේන්තු පිළිතුරු ලැබීම</li>
                <li><strong>Report Out</strong> — අවශ්‍ය නම් බාහිර ලිපියක් ලෙස යැවීම</li>
                <li><strong>Dispose</strong> — අවසන් බැහැර කිරීම සහ ගොනු කිරීම</li>
            </ol>
        </div>
    </div>

    <!-- ═══════ TAB 2: Login ═══════ -->
    <div class="m-tab-panel" id="tab-login">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-right-to-bracket"></i><span>පද්ධතියට පිවිසීම</span></div>
            <ol class="m-steps">
                <li>ඔබේ browser එකේ IDCMS URL එක විවෘත කරන්න.</li>
                <li><strong>Username</strong> කොටුවේ ඔබේ පරිශීලක නාමය ඇතුළත් කරන්න (service number සාමාන්‍යයෙන්).</li>
                <li><strong>Password</strong> කොටුවේ මුරපදය ඇතුළත් කරන්න.</li>
                <li><strong>Login</strong> බොත්තම ඔබන්න.</li>
                <li>සාර්ථක වූ පසු ඔබව ස්වයංක්‍රීයව Dashboard එකට ගෙන එනු ලැබේ.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Session Timeout</strong>
                    <p>ඔබ පැය කිහිපයක් නිශ්චලව සිටියහොත්, ආරක්ෂාව සඳහා පද්ධතිය ඔබව ස්වයංක්‍රීයව ඉවත් කරයි. නැවත පිවිසෙන්න.</p>
                </div>
            </div>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>ආරක්ෂක උපදෙස්</strong>
                    <ul>
                        <li>මුරපදය කිසිවිටෙක අන් අය සමඟ බෙදා නොගන්න</li>
                        <li>පොදු පරිගණකයක් භාවිතා කරන්නේ නම්, වැඩ අවසානයේ <strong>Logout</strong> ඔබන්න</li>
                        <li>ඔබේ ගිණුම හරහා සිදුවන සියලු ක්‍රියාකාරකම් පද්ධතිය විසින් සටහන් කරයි</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>මුරපදය අමතක වුනොත්</span></div>
            <p>ඔබේ මුරපදය අමතක වුනොත් හෝ වෙනස් කර ගැනීමට අවශ්‍ය නම්, ඔබේ <strong>පද්ධති පරිපාලක</strong> අමතන්න. ස්වයංක්‍රීය reset option එකක් නැහැ — ආරක්ෂාව සඳහා මෙය අතින් කළ යුතුයි.</p>
        </div>
    </div>

    <!-- ═══════ TAB 3: Dashboard ═══════ -->
    <div class="m-tab-panel" id="tab-dashboard">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-cells-large"></i><span>Dashboard — ප්‍රධාන පිටුව</span></div>
            <p>Dashboard එක තමයි ඔබ පද්ධතියට පිවිසුණු පසු පළමුව දකින page එක. මෙහි ඔබට ලිපි සංඛ්‍යාලේඛන, ඔබට යොමු කර ඇති ලිපි සහ සජීවී සෙවුම් එකම තැනකින් දැකිය හැක.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Dashboard එකේ කොටස්</span></div>
            <ol class="m-steps">
                <li><strong>Sidebar</strong> — පද්ධතියේ සියලුම modules වලට පිවිසුම්</li>
                <li><strong>Top Bar</strong> — ඔරලෝසුව, notifications, ඔබේ පරිශීලක තොරතුරු</li>
                <li><strong>Search Bar</strong> — සජීවී සෙවුම (CDR, TSK, title)</li>
                <li><strong>Filter Controls</strong> — Type, Status, Limit, Deep Search</li>
                <li><strong>Metrics Grid</strong> — ලිපි සංඛ්‍යාලේඛන 6ක්</li>
                <li><strong>Search Preview</strong> — සෙවුම් ප්‍රතිඵල කාඩ්පත් ලෙස</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-line"></i><span>Metrics — සංඛ්‍යාලේඛන</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Metric</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Today (Inward Only)</strong></td><td>අද දිනයේ ලැබුණු අභ්‍යන්තර ලිපි ගණන</td></tr>
                        <tr><td><strong>Pending</strong></td><td>අවසන් බැහැර කිරීමට රැඳී ඇති ලිපි</td></tr>
                        <tr><td><strong>All Letters</strong></td><td>සියලුම ලිපි (Internal + External + Tasked)</td></tr>
                        <tr><td><strong>Internal</strong></td><td>මුළු අභ්‍යන්තර ලිපි</td></tr>
                        <tr><td><strong>External</strong></td><td>මුළු බාහිර ලිපි</td></tr>
                        <tr><td><strong>Tasked</strong></td><td>මුළු කාර්ය ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-magnifying-glass"></i><span>සෙවුම් තීරුව</span></div>
            <p>සෙවුම් තීරුවේ ඔබ ටයිප් කරන විටම ප්‍රතිඵල පහළින් පෙන්වයි:</p>
            <ul class="m-steps">
                <li><strong>CDR Number</strong> — උදා: <code>REG/2026/13</code></li>
                <li><strong>TSK Number</strong> — කාර්ය ලිපි සඳහා</li>
                <li><strong>Letter Reference</strong> සහ <strong>Title</strong></li>
            </ul>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p><strong>Deep Search</strong> තෝරා ගත් විට, ලිපියේ විස්තරය, ටිප්පණි සහ About Letter කොටස්වල පවා ඔබේ වචනය සොයයි.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════ TAB 4: Roles ═══════ -->
    <div class="m-tab-panel" id="tab-roles">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users"></i><span>පරිශීලක භූමිකා — User Roles</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Role</th><th>භූමිකාව</th><th>අවසර</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Administrator</strong></td><td>පද්ධති පරිපාලක</td><td>සියලුම අංශ වලට ප්‍රවේශය, user management</td></tr>
                        <tr><td><strong>Officer</strong></td><td>නිලධාරී</td><td>ලිපි ලියාපදිංචි කිරීම, යොමු කිරීම, කාර්ය පැවරීම</td></tr>
                        <tr><td><strong>Desk Officer</strong></td><td>ඩෙස්ක් නිලධාරී</td><td>අදාළ ඩෙස්කයට ලැබුණු ලිපි පමණයි</td></tr>
                        <tr><td><strong>Viewer</strong></td><td>නරඹන්නා</td><td>ලිපි බැලීම සහ වාර්තා ලබා ගැනීම පමණයි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permissions — අවසර</span></div>
            <p>ඔබට ලැබෙන අවසර පැවරෙන්නේ ඔබේ භූමිකාව සහ <code>permission_code</code> අනුවයි. සාමාන්‍ය permission codes:</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Code</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><code>VIEW_DASHBOARD</code></td><td>Dashboard එක බැලීම</td></tr>
                        <tr><td><code>VIEW_INTERNAL_LETTERS</code></td><td>අභ්‍යන්තර ලිපි බැලීම</td></tr>
                        <tr><td><code>VIEW_OUTGOING_LETTERS</code></td><td>බාහිර ලිපි බැලීම</td></tr>
                        <tr><td><code>FINAL_DISPOSAL</code></td><td>අවසන් බැහැර කිරීමේ අවසර</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Note</strong>
                    <p>යම් අංශයක් ඔබට නොපෙනේ නම් හෝ "Access Denied" පෙන්නුම් කරන්නේ නම්, ඔබට එයට අවසර නැහැ. පද්ධති පරිපාලකගෙන් ඉල්ලන්න.</p>
                </div>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="01-getting-started" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="index.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Manual Home</span></a>
        <a href="02-internal-letters.php" class="next"><span class="lbl">Next →</span><span class="ttl">Internal Letters</span></a>
    </div>
</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
<script src="assets/manual.js"></script>
<script>
// Tabs handler
document.querySelectorAll('.m-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        document.querySelectorAll('.m-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.m-tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.getElementById(target);
        if (panel) panel.classList.add('active');
        history.replaceState(null, '', '#' + target);
    });
});
// Restore tab from hash
if (location.hash) {
    const tab = document.querySelector('.m-tab[data-tab="' + location.hash.slice(1) + '"]');
    if (tab) tab.click();
}
</script>
</body>
</html>