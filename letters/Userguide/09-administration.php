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
<title>Administration | IDCMS Manual</title>
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
.chip.active    { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.inactive  { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.suspended { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.officer   { background:rgba(139,92,246,0.15); color: #8b5cf6; }
.chip.ors       { background:rgba(59,130,246,0.15); color: #3b82f6; }
.chip.admin     { background:rgba(67,24,255,0.15); color: #4318FF; }
.chip.registry  { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.it        { background:rgba(139,92,246,0.15); color: #8b5cf6; }

.perm-code { display:inline-block; background:var(--code-bg); color:var(--accent); padding:4px 12px; border-radius:6px; font-family:'JetBrains Mono', monospace; font-size:0.78rem; font-weight:700; border:1px solid var(--code-border); margin:2px 4px 2px 0; }

.info-badge {
    display:flex; align-items:center; gap:12px;
    padding:12px 16px; border-radius:12px;
    background:var(--bg); border-left:4px solid var(--accent);
    margin:10px 0;
}
.info-badge .ib-icon {
    width:36px; height:36px; border-radius:9px;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0;
}
.info-badge .ib-body { flex:1; }
.info-badge .ib-body h6 { font-weight:800; margin:0 0 2px; color:var(--text-strong); font-size:0.87rem; }
.info-badge .ib-body p { font-size:0.8rem; color:var(--muted); margin:0; line-height:1.4; }
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
        <a href="07-linkage-reports.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>
        <div class="m-section-label">Administration</div>
        <a href="08-informers.php"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
        <a href="09-administration.php" class="active"><i class="fa-solid fa-user-shield"></i><span>Administration</span></a>
        <div class="m-section-label">Help</div>
        <a href="12-system-health.php"><i class="fa-solid fa-life-ring"></i><span>Help &amp; Glossary</span></a>
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
            <span class="current">Administration</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-user-shield"></i> Administration</span>
        <h1>Administration Module</h1>
        <p>පරිශීලක ගිණුම් සෑදීම, කළමනාකරණය, අවසර පැවරීම සහ ඩෙස්ක් පාලනය පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 20 min read</span>
            <span><i class="fa-solid fa-signal"></i> ADVANCED</span>
            <span><i class="fa-solid fa-language"></i> පරිපාලනය</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-add"><i class="fa-solid fa-user-plus"></i> Add User</button>
        <button class="m-tab" data-tab="tab-directory"><i class="fa-solid fa-users"></i> User Directory</button>
        <button class="m-tab" data-tab="tab-edit"><i class="fa-solid fa-user-pen"></i> Edit User</button>
        <button class="m-tab" data-tab="tab-delete"><i class="fa-solid fa-user-xmark"></i> Delete User</button>
        <button class="m-tab" data-tab="tab-permissions"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Administration Module</strong> එක මගින් පද්ධතියේ <strong>පරිශීලක ගිණුම්</strong> සෑදීම, යාවත්කාලීන කිරීම, ඉවත් කිරීම, සහ ඔවුන්ගේ <strong>අවසර (Permissions)</strong> පැවරීම සිදු කරයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 4ක්</span></div>
            <ul class="m-steps">
                <li><strong>Add User</strong> — නව පරිශීලක ගිණුමක් සෑදීම</li>
                <li><strong>User Directory</strong> — සියලුම පරිශීලකයන් දැක බලා කළමනාකරණය</li>
                <li><strong>Edit User</strong> — දැනට ඇති ගිණුමක් සංශෝධනය</li>
                <li><strong>Assign Permissions</strong> — පරිශීලකයෙකුට අවසර පැවරීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>User Categories — 2ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip officer"><i class="fa-solid fa-user-tie"></i> Officer</span>
                <span class="chip ors"><i class="fa-solid fa-user"></i> ORs (Other Ranks)</span>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;color:var(--muted);">පරිශීලකයන් අයත් වන කාණ්ඩය අනුව ඔවුන්ගේ පද්ධති ප්‍රවේශය වෙනස් විය හැක.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-sitemap"></i><span>System Sections — 3ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip admin"><i class="fa-solid fa-user-shield"></i> Administration Section</span>
                <span class="chip registry"><i class="fa-solid fa-inbox"></i> Registry Section</span>
                <span class="chip it"><i class="fa-solid fa-desktop"></i> IT Section</span>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;color:var(--muted);">
                පරිශීලකයා අයත් වන Section එක අනුව ලිපි වර්ග සහ ක්‍රියාකාරකම් වෙනස් වේ.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-briefcase"></i><span>Appointments — 7ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Appointment</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Desk Officer</strong></td><td>ඩෙස්ක නිලධාරී</td></tr>
                        <tr><td><strong>Desk Head</strong></td><td>ඩෙස්ක ප්‍රධානී</td></tr>
                        <tr><td><strong>Chief Clerk (CC)</strong></td><td>ප්‍රධාන ලිපිකරු</td></tr>
                        <tr><td><strong>Registry Assistant (RA)</strong></td><td>ලේඛන සහායක</td></tr>
                        <tr><td><strong>Dispatch Clerk</strong></td><td>යැවීමේ ලිපිකරු</td></tr>
                        <tr><td><strong>Subject Clerk</strong></td><td>විෂය ලිපිකරු</td></tr>
                        <tr><td><strong>User</strong></td><td>සාමාන්‍ය පරිශීලක</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>අවධානය</strong>
                <p>User Management යනු පද්ධතියේ <strong>වඩාත් සංවේදී</strong> module එකකි. සියලුම ක්‍රියාකාරකම් සටහන් වේ. <span class="perm-code">MANAGE_USERS</span> permission එක ඇති අයට පමණක් ප්‍රවේශය ලැබේ.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: ADD USER ═══════════════ -->
    <div class="m-tab-panel" id="tab-add">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Overview</span></div>
            <p>නව පරිශීලක ගිණුමක් සෑදීමට <code>add_user.php</code> page එක භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>
                    පැති තීරුවෙන් <code>Manage Users → Add New User</code> තෝරන්න.
                </li>

                <li>
                    <strong>Authentication Details</strong> — පළමුව මූලික පිවිසුම් තොරතුරු:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Username</strong> — අනන්‍ය පරිශීලක නාමය</li>
                        <li><strong>Password</strong> — ශක්තිමත් මුරපදය (auto-hash වේ)</li>
                    </ul>
                </li>

                <li>
                    <strong>Profile Information</strong> — පුද්ගලික තොරතුරු:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Name with Initials</strong> — මුලකුරු සමඟ නම (උදා: Perera AB)</li>
                        <li><strong>Service Number</strong> — අනන්‍ය S/N</li>
                        <li><strong>Rank</strong> — නිලය (Major | CPL | L/CPL)</li>
                        <li><strong>Regiment</strong> — රෙජිමේන්තුව</li>
                        <li><strong>Work Place</strong> — සේවා ස්ථානය (DMI, 1-8 MIC GP, OMEGA, SIEERA)</li>
                        <li><strong>Section</strong> — අංශය (Administration, Registry, IT)</li>
                        <li><strong>User Category</strong> — Officer හෝ ORs</li>
                        <li><strong>Appointment</strong> — තනතුර</li>
                    </ul>
                </li>

                <li>
                    <strong>Assignment &amp; Status</strong> — අවසාන පියවර:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Desk Assignment</strong> — අදාළ ඩෙස්කය තෝරන්න</li>
                        <li><strong>Account Status</strong> — Active, Inactive, හෝ Suspended</li>
                    </ul>
                </li>

                <li><strong>Confirm &amp; Save User</strong> බොත්තම ඔබන්න.</li>
                <li>සාර්ථක වූ පසු, User Directory එකට redirect වේ.</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>මුරපදය <code>password_hash()</code> මගින් <strong>bcrypt</strong> ලෙස hash වේ. කිසිවිටෙක පැහැදිලි මුරපදය DB එකේ save නොවේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th><th>විකල්ප</th></tr></thead>
                    <tbody>
                        <tr><td><code>username</code></td><td>පරිශීලක නාමය</td><td><span class="required">Required</span></td><td>Unique</td></tr>
                        <tr><td><code>password</code></td><td>මුරපදය</td><td><span class="required">Required</span></td><td>bcrypt hash</td></tr>
                        <tr><td><code>name_with_initial</code></td><td>නම</td><td><span class="required">Required</span></td><td>—</td></tr>
                        <tr><td><code>service_number</code></td><td>සේවා අංකය</td><td><span class="required">Required</span></td><td>Unique</td></tr>
                        <tr><td><code>rank</code></td><td>නිලය</td><td><span class="required">Required</span></td><td>Free text</td></tr>
                        <tr><td><code>regiment</code></td><td>රෙජිමේන්තුව</td><td><span class="required">Required</span></td><td>Free text</td></tr>
                        <tr><td><code>work_place</code></td><td>සේවා ස්ථානය</td><td><span class="required">Required</span></td><td>ENUM (11)</td></tr>
                        <tr><td><code>section</code></td><td>අංශය</td><td><span class="required">Required</span></td><td>ENUM (3)</td></tr>
                        <tr><td><code>Appointment</code></td><td>තනතුර</td><td><span class="required">Required</span></td><td>ENUM (7)</td></tr>
                        <tr><td><code>user_category</code></td><td>කාණ්ඩය</td><td><span class="required">Required</span></td><td>Officer / ORs</td></tr>
                        <tr><td><code>desk_id</code></td><td>ඩෙස්කය</td><td><span class="required">Required</span></td><td>Active ඩෙස්ක පමණයි</td></tr>
                        <tr><td><code>status</code></td><td>තත්ත්වය</td><td><span class="required">Required</span></td><td>Active / Inactive / Suspended</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Validation</strong>
                <p>සියලුම ENUM fields (section, work_place, appointment, user_category, status) සඳහා <strong>whitelist validation</strong> එකක් සිදු වේ. වලංගු නොවන අගයක් ආවොත් default අගයක් භාවිතා වේ.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: USER DIRECTORY ═══════════════ -->
    <div class="m-tab-panel" id="tab-directory">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users"></i><span>User Directory Page</span></div>
            <p>
                <code>users.php</code> page එක සියලුම පරිශීලකයන් එකම තැනක පෙන්වයි. ඉහළින් <strong>Dashboard Stats</strong>, පසුව filters සහ table එක පෙන්වයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>Stat Cards — 4ක්</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Card</th><th>විස්තරය</th><th>වර්ණය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Total Registered</strong></td><td>සියලුම පරිශීලකයන්</td><td style="color:var(--accent);">දම්</td></tr>
                        <tr><td><strong>Active Accounts</strong></td><td>සක්‍රීය ගිණුම්</td><td style="color:#00C864;">කොළ</td></tr>
                        <tr><td><strong>Inactive / Suspended</strong></td><td>අක්‍රීය සහ තහනම්</td><td style="color:#FF5B5B;">රතු</td></tr>
                        <tr><td><strong>Unassigned Desk</strong></td><td>ඩෙස්කයක් නොමැති පරිශීලකයන්</td><td style="color:#FFB600;">තැඹිලි</td></tr>
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
                        <tr><td><strong>Category Pills</strong></td><td>Dynamic buttons — All, Officer, ORs (users වලින් ජනනය වේ)</td></tr>
                        <tr><td><strong>Appointment</strong></td><td>Dropdown — users වලින් dynamic ලෙස ජනනය වේ</td></tr>
                        <tr><td><strong>Search Box</strong></td><td>Name, S/N, Rank, Unit අනුව සොයන්න (live)</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="m-callout tip" style="margin-top:16px;">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <p>Filters <strong>සියල්ල එකට</strong> භාවිතා කළ හැක. Category + Appointment + Search — තුනම එකවර යෙදේ.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Staff Profile</strong></td><td>Avatar (initials) + නම + @username + rank</td></tr>
                        <tr><td><strong>Service No</strong></td><td>monospace font එකෙන්</td></tr>
                        <tr><td><strong>Category</strong></td><td>Officer / ORs badge</td></tr>
                        <tr><td><strong>Workplace &amp; Regiment</strong></td><td>සේවා ස්ථානය + රෙජිමේන්තුව</td></tr>
                        <tr><td><strong>Section &amp; Appointment</strong></td><td>අංශය + තනතුර</td></tr>
                        <tr><td><strong>Desk Assignment</strong></td><td>ඩෙස්කයේ නම (නොමැති නම් "Unassigned")</td></tr>
                        <tr><td><strong>Status</strong></td><td>Active / Inactive / Suspended (වර්ණ badge)</td></tr>
                        <tr><td><strong>Actions</strong></td><td>Edit · Rules (permissions) බොත්තම්</td></tr>
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
                        <tr><td><strong>Edit</strong></td><td><i class="fa-solid fa-pen-nib"></i></td><td>Edit User page එකට</td></tr>
                        <tr><td><strong>Rules</strong></td><td><i class="fa-solid fa-user-lock"></i></td><td>Permissions පැවරීමේ page එකට</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-plus"></i><span>Add New User (Top Right)</span></div>
            <p>ඉහළ දකුණේ ඇති <strong>"Add New User"</strong> බොත්තම මගින් <code>add_user.php</code> page එකට යයි.</p>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: EDIT USER ═══════════════ -->
    <div class="m-tab-panel" id="tab-edit">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-pen"></i><span>Edit User Page</span></div>
            <p><code>edit_user.php</code> page එක භාවිතා කරන්නේ දැනට ඇති පරිශීලක ගිණුමක් සංශෝධනය කිරීමටයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>User Directory එකේ අදාළ පරිශීලකයාගේ <strong>Edit</strong> බොත්තම ඔබන්න.</li>
                <li>URL එකට <code>?id=X</code> ලෙස එකතු වේ.</li>
                <li>ශීර්ෂයේ <strong>"Updating details for: [Name]"</strong> ලෙස පෙන්වයි.</li>
                <li>පහත fields වෙනස් කළ හැක:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>Username</strong> — නම වෙනස් කිරීම</li>
                        <li><strong>Password</strong> — හිස් තැබුවොත් එහෙමම තිබේ (හෝ නව මුරපදයක්)</li>
                        <li><strong>Name · S/N · Rank · Regiment</strong></li>
                        <li><strong>Work Place · Section · Category · Appointment</strong></li>
                        <li><strong>Desk Assignment · Status</strong></li>
                    </ul>
                </li>
                <li><strong>Update User Details</strong> බොත්තම ඔබන්න.</li>
                <li>සාර්ථක වූ පසු, User Directory එකට redirect වේ.</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Password Handling</span></div>
            <div class="info-badge">
                <div class="ib-icon"><i class="fa-solid fa-lock"></i></div>
                <div class="ib-body">
                    <h6>හිස් තැබුවොත් — password වෙනස් නොවේ</h6>
                    <p>Password කොටුව හිස්ව තැබුවොත්, පැරණි මුරපදය එහෙමම තියෙනවා. Update query එකේ password field එක <strong>යවන්නේම නැහැ</strong>.</p>
                </div>
            </div>
            <div class="info-badge">
                <div class="ib-icon"><i class="fa-solid fa-key"></i></div>
                <div class="ib-body">
                    <h6>නව මුරපදයක් දැම්මොත් — auto-hash වේ</h6>
                    <p>නව මුරපදයක් ඇතුළත් කළොත්, එය <code>password_hash()</code> මගින් bcrypt ලෙස hash වේ. DB එකට plain text කිසිවිටෙක යන්නේ නැහැ.</p>
                </div>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Edit හි විශේෂත්වය</strong>
                <p>මෙම page එකේ <strong>Update Statement දෙකක්</strong> තියෙනවා — එකක් password සමඟ, අනෙක password නැතුව. System එක තෝරන්නේ password field එකේ අගය අනුවයි.</p>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම්</span></div>
            <ul class="m-steps">
                <li><strong>Cancel</strong> — වෙනස්කම් නොසුරකින්නේ. Directory එකට ආපසු යයි.</li>
                <li><strong>Update User Details</strong> — වෙනස්කම් සුරකිනවා.</li>
            </ul>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>වැදගත්</strong>
                <ul>
                    <li><strong>Username</strong> වෙනස් කරන විට — පරිශීලකයා නැවත පිවිසෙන්න ඕන අලුත් නමින්</li>
                    <li><strong>Status</strong> "Inactive" හෝ "Suspended" කළහොත්, ඔවුන්ට පද්ධතියට පිවිසෙන්න බැහැ</li>
                    <li><strong>Desk</strong> වෙනස් කිරීමෙන් — ඔවුන්ගේ inbox, disposal tasks වෙනස් වේ</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: DELETE USER ═══════════════ -->
    <div class="m-tab-panel" id="tab-delete">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-xmark"></i><span>Delete User (Soft Delete)</span></div>
            <p>
                <code>delete_user.php</code> page එක මගින් පරිශීලකයෙකු ඉවත් කරයි. නමුත් මෙය <strong>soft delete</strong> එකක් — දත්ත ගබඩාවෙන් සැබවින්ම ඉවත් නොවේ.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-gear"></i><span>ක්‍රියා කරන ආකාරය</span></div>
            <div class="m-code" data-lang="sql">
-- Delete query එකේ සත්‍ය ස්වරූපය:
UPDATE users 
SET is_deleted = 1 
WHERE id = [user_id];</div>

            <p style="margin-top:14px;font-size:0.9rem;">
                එනම්, ගිණුම සැබවින්ම ඉවත් කරන්නේ නැහැ. <code>is_deleted = 1</code> ලෙස <strong>flag</strong> එකක් විතරයි දාන්නේ.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>Soft Delete හි වාසි</span></div>
            <ul class="m-steps">
                <li><strong>Audit Trail</strong> — පද්ධතියේ සිදු වූ සියලු ක්‍රියාකාරකම් වලට හිමිකරුවා තවම දත්ත ගබඩාවේ ඇත</li>
                <li><strong>Letters Security</strong> — ලිපිවල registered_by, forwarded_by වැනි field වලට refer කරන අය තවම ඇත</li>
                <li><strong>Reversible</strong> — වැරදීමකින් ඉවත් කළොත්, DB admin ට නැවත active කළ හැක</li>
                <li><strong>FK Integrity</strong> — Foreign keys බිඳෙන්නේ නැහැ</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Delete කරන ආකාරය</span></div>
            <ol class="m-steps">
                <li>මෙම action එක <strong>User Directory එකේ row එකෙන් සෘජුවම</strong> කරන්නේ නැහැ. පළමුව අදාළ පරිශීලකයාගේ <strong>Edit</strong> page එකට යන්න.</li>
                <li>හෝ — <code>delete_user.php?id=X</code> ලෙස URL එකට යන්න.</li>
                <li>Access Denied නොවේ නම්, ගිණුම ස්වයංක්‍රීයව ඉවත් වේ.</li>
                <li>නැවත User Directory එකට redirect වේ.</li>
                <li>ලැයිස්තුවෙන් එම පරිශීලකයා නොපෙනේ.</li>
            </ol>

            <div class="m-callout warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>අවධානය</strong>
                    <p>මෙම page එකට <strong>confirmation dialog එකක් නැහැ</strong>. URL එකට ගිය විගසම delete වේ. භාවිත කිරීමේදී <strong>ඉතා පරිස්සම් වන්න</strong>.</p>
                </div>
            </div>
        </div>

        <div class="m-callout tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div>
                <strong>නිර්දේශය</strong>
                <p>Delete වෙනුවට <strong>Status = "Inactive"</strong> ලෙස වෙනස් කිරීම වඩා හොඳයි. එවිට audit trail තවම ඇත, පස්සේ අවශ්‍ය නම් නැවත active කළ හැක. Delete කරන්නේ පද්ධතියෙන් ස්ථිරවම ඉවත් කිරීමට අවශ්‍ය විටයි.</p>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-shield"></i><span>Restore කිරීම (DB Admin පමණයි)</span></div>
            <p>Soft delete කළ පරිශීලකයෙකු නැවත ලබා ගැනීමට, DB admin ට පහත query එක run කළ හැක:</p>
            <div class="m-code" data-lang="sql">
UPDATE users 
SET is_deleted = 0 
WHERE id = [user_id];</div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: PERMISSIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-permissions">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-key"></i><span>Permission Codes</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Permission</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><span class="perm-code">MANAGE_USERS</span></td>
                            <td>User Management module එකට ප්‍රවේශය (add, edit, delete, view)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;color:var(--muted);">
                මෙම permission එක නැත්නම් පහත පිටු 4ටම ප්‍රවේශය නොමැත:
            </p>
            <ul class="m-steps">
                <li>User Directory (users.php)</li>
                <li>Add User (add_user.php)</li>
                <li>Edit User (edit_user.php)</li>
                <li>Delete User (delete_user.php)</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Permission Matrix</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Officer</th>
                            <th>IT Staff</th>
                            <th>HR Manager</th>
                            <th>System Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>View Directory</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Add User</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Edit User</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Delete User</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Assign Permissions</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-lock"></i><span>Assign Permissions Page</span></div>
            <p>User Directory එකේ <strong>Rules</strong> බොත්තම මගින් අදාළ පරිශීලකයාට අවසර පැවරීමේ page එකට යයි.</p>
            <p style="margin-top:10px;font-size:0.88rem;color:var(--muted);">
                URL: <code>admin/permissions/assign_permissions.php?id=X</code>
            </p>
            <div class="m-callout info" style="margin-top:12px;">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <p>මෙම page එක මෙම manual එකේ වෙන වෙනම කොටසක් ලෙස විස්තර කර ඇත. <strong>Permissions</strong> පැවරීම පිළිබඳ තොරතුරු එහි දකින්න.</p>
                </div>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>User Management ආරක්ෂාව</strong>
                <ul>
                    <li>මෙම module එක <strong>වඩාත් සංවේදී</strong> — වැරදීමකින් කරන ක්‍රියාවකින් පද්ධතියේ ආරක්ෂාව බිඳිය හැක</li>
                    <li>පරිශීලකයෙකු එකතු කිරීමට පෙර — ඔහුගේ අනන්‍යතාවය සහ අවශ්‍යතාවය <strong>තහවුරු කරගන්න</strong></li>
                    <li>Delete කිරීමට පෙර — <strong>දෙපාරක් සිතන්න</strong>. Audit trail සඳහා අවශ්‍ය විය හැක</li>
                    <li>Appointment වැනි fields <strong>ඉතා සුපරීක්ෂාකාරීව</strong> තෝරන්න — එය අනුව පද්ධතියේ ක්‍රියාකාරකම් වෙනස් වේ</li>
                </ul>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="09-administration" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="08-informers.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Informers</span></a>
        <a href="10-help.php" class="next"><span class="lbl">Next →</span><span class="ttl">Help &amp; Glossary</span></a>
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