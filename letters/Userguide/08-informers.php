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
<title>Informers | IDCMS Manual</title>
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
.wf-step.link   .wf-icon { background: #f59e0b; }
.wf-step.report .wf-icon { background: #3b82f6; }
.wf-step.inactive .wf-icon { background: #ef4444; }

.chip { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin:2px; }
.chip.agent    { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.informer { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.casual   { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.active   { background:rgba(16,185,129,0.15); color: #10b981; }
.chip.inactive { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.class-a  { background:rgba(239,68,68,0.15); color: #ef4444; }
.chip.class-b  { background:rgba(245,158,11,0.15); color: #f59e0b; }
.chip.class-c  { background:rgba(59,130,246,0.15); color: #3b82f6; }
.chip.class-d  { background:rgba(139,92,246,0.15); color: #8b5cf6; }
.chip.class-e  { background:rgba(107,114,128,0.15); color: #6b7280; }
.chip.class-f  { background:rgba(107,114,128,0.15); color: #6b7280; }

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
        <a href="06-record-room.php"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="07-linkage-reports.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Reports</span></a>
        <div class="m-section-label">Administration</div>
        <a href="08-informers.php" class="active"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
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
            <span class="current">Informers</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-user-secret"></i> Administration</span>
        <h1>Informers Management</h1>
        <p>තොරතුරු දෙන්නන් ලියාපදිංචි කිරීම, යාවත්කාලීන කිරීම, සහ ඔවුන්ට අදාළ ලිපි නිරීක්ෂණය කිරීම පිළිබඳ සම්පූර්ණ අත්පොත.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 16 min read</span>
            <span><i class="fa-solid fa-signal"></i> INTERMEDIATE</span>
            <span><i class="fa-solid fa-language"></i> තොරතුරු දෙන්නන්</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-add"><i class="fa-solid fa-plus"></i> Add Informer</button>
        <button class="m-tab" data-tab="tab-registry"><i class="fa-solid fa-list"></i> Master Database</button>
        <button class="m-tab" data-tab="tab-details"><i class="fa-solid fa-eye"></i> Informer Details</button>
        <button class="m-tab" data-tab="tab-categories"><i class="fa-solid fa-tags"></i> Categories</button>
        <button class="m-tab" data-tab="tab-workflow"><i class="fa-solid fa-diagram-project"></i> Workflow</button>
        <button class="m-tab" data-tab="tab-perms"><i class="fa-solid fa-key"></i> Permissions</button>
    </div>

    <!-- ═══════════════ TAB 1: OVERVIEW ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Module එකේ අරමුණ</span></div>
            <p>
                <strong>Informers Management</strong> module එක මගින් ආයතනයට තොරතුරු සපයන <strong>තොරතුරු දෙන්නන්</strong> (Informers) ලියාපදිංචි කිරීම, ඔවුන්ගේ වර්ගීකරණය, සහ ඔවුන් සම්බන්ධ ලිපි නිරීක්ෂණය කිරීම සිදු කරයි.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන අංග 4ක්</span></div>
            <ul class="m-steps">
                <li><strong>Add Informer</strong> — නව තොරතුරු දෙන්නෙකු ලියාපදිංචි කිරීම</li>
                <li><strong>Master Database</strong> — සියලුම තොරතුරු දෙන්නන් ලැයිස්තුවක් ලෙස දැක බලා කළමනාකරණය</li>
                <li><strong>Informer Details</strong> — එක් අයෙකුගේ සම්පූර්ණ විස්තර + ඔහුට අදාළ සියලුම ලිපි</li>
                <li><strong>Soft Delete</strong> — දත්ත ගබඩාවෙන් ඉවත් නොකර ලැයිස්තුවෙන් ඉවත් කිරීම</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>ප්‍රධාන භූමිකා</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Role</th><th>භූමිකාව</th><th>ක්‍රියාකාරකම</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Intelligence Officer</strong></td><td>බුද්ධි නිලධාරී</td><td>තොරතුරු දෙන්නන් ලියාපදිංචි කිරීම සහ යාවත්කාලීන කිරීම</td></tr>
                        <tr><td><strong>Handler</strong></td><td>සම්බන්ධක නිලධාරී</td><td>තොරතුරු දෙන්නා සමඟ සම්බන්ධතා පවත්වන අය</td></tr>
                        <tr><td><strong>Administrator</strong></td><td>පරිපාලක</td><td>සියලුම ක්‍රියාකාරකම්</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-shield"></i><span>විශේෂ ලක්ෂණ</span></div>
            <ul class="m-steps">
                <li><strong>Anonymous Tracking</strong> — තොරතුරු දෙන්නන් හඳුනාගන්නේ <strong>Code</strong> එකෙන් (නමෙන් නොවේ)</li>
                <li><strong>Classification Levels</strong> — A සිට F දක්වා විශ්වාස මට්ටම්</li>
                <li><strong>Category Types</strong> — Informer, Casual Contact, Agent</li>
                <li><strong>Handler Assignment</strong> — හැම අයෙකුටම handler කෙනෙක්</li>
                <li><strong>Linked Letters View</strong> — තොරතුරු දෙන්නා සම්බන්ධ සියලුම Internal, Outward, Task ලිපි</li>
            </ul>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>වැදගත්</strong>
                <p>තොරතුරු දෙන්නන්ගේ තොරතුරු <strong>අති රහස්‍යයි</strong>. මෙම module එකට ඇතුළු වන සියලුම ක්‍රියාකාරකම් පද්ධතිය විසින් සටහන් කරයි.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: ADD INFORMER ═══════════════ -->
    <div class="m-tab-panel" id="tab-add">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>Overview</span></div>
            <p>නව තොරතුරු දෙන්නෙකු ලියාපදිංචි කිරීමට <code>informers.php</code> page එකේ ඉහළ form එක භාවිතා කරන්න.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>Step-by-Step</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Informer Unit → View Registry</code> තෝරන්න.</li>
                <li>ඉහළින් <strong>"Register New Informer"</strong> form එක පෙන්වයි.</li>
                <li>
                    <strong>Informer Code</strong> — තොරතුරු දෙන්නාගේ අනන්‍ය කේතය ඇතුළත් කරන්න.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Format</strong>
                            <p>හොඳ පිළිවෙල: <code>INF-2026-001</code>, <code>AGT-2026-042</code></p>
                        </div>
                    </div>
                </li>
                <li><strong>Informer Name</strong> — නම ඇතුළත් කරන්න (හෝ කේත නාමයක්).</li>
                <li><strong>Regiment</strong> — අදාළ රෙජිමේන්තුව තෝරන්න.</li>
                <li>
                    <strong>Category</strong> — තොරතුරු දෙන්නාගේ වර්ගය:
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><code>Informer</code> — සාමාන්‍ය තොරතුරු දෙන්නා</li>
                        <li><code>Casual Contact</code> — අහඹු සම්බන්ධතාවය</li>
                        <li><code>Agent</code> — ක්‍රියාකාරී නියෝජිතයා</li>
                    </ul>
                </li>
                <li>
                    <strong>Classification</strong> — විශ්වාස මට්ටම තෝරන්න (A-F):
                    <ul style="margin-top:6px;padding-left:22px;">
                        <li><strong>A</strong> — ඉහළම විශ්වාසය</li>
                        <li><strong>F</strong> — අවම විශ්වාසය</li>
                    </ul>
                </li>
                <li><strong>Handler Details</strong> — අදාළ නිලධාරියාගේ විස්තර.</li>
                <li><strong>Contact Number</strong> — සම්බන්ධතා අංකය.</li>
                <li><strong>Status</strong> — <code>Active</code> හෝ <code>Inactive</code> තෝරන්න.</li>
                <li><strong>Save Informer</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>සාර්ථක වූ පසු, form එක නැවත reset වේ. සහ හරිත පණිවිඩයක් "New informer registered successfully!" ලෙස පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>Field Reference</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>අර්ථය</th><th>Status</th><th>උදාහරණය</th></tr></thead>
                    <tbody>
                        <tr><td><code>informer_code</code></td><td>අනන්‍ය කේතය</td><td><span class="required">Required</span></td><td>INF-2026-001</td></tr>
                        <tr><td><code>informer_name</code></td><td>නම / කේත නාමය</td><td><span class="required">Required</span></td><td>John Doe</td></tr>
                        <tr><td><code>regiment_id</code></td><td>රෙජිමේන්තුව</td><td><span class="required">Required</span></td><td>Colombo Regiment</td></tr>
                        <tr><td><code>category</code></td><td>වර්ගය</td><td><span class="required">Required</span></td><td>Informer</td></tr>
                        <tr><td><code>classification</code></td><td>විශ්වාස මට්ටම</td><td><span class="required">Required</span></td><td>A</td></tr>
                        <tr><td><code>handler_details</code></td><td>හැන්ඩ්ලර්</td><td><span class="optional">Optional</span></td><td>Capt. Perera</td></tr>
                        <tr><td><code>contact_number</code></td><td>සම්බන්ධතා අංකය</td><td><span class="optional">Optional</span></td><td>0771234567</td></tr>
                        <tr><td><code>status</code></td><td>තත්ත්වය</td><td><span class="required">Required</span></td><td>Active</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: MASTER DATABASE ═══════════════ -->
    <div class="m-tab-panel" id="tab-registry">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list"></i><span>Master Database</span></div>
            <p><code>informers.php</code> page එකේ පහළින්, සියලුම තොරතුරු දෙන්නන් ලැයිස්තුවක් ලෙස පෙන්වයි. සියලුම දෙනා <strong>අලුත්ම පළමුව</strong> (DESC) ලෙස පෙන්වයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Code &amp; Name</strong></td><td>කේතය (accent වර්ණයෙන්) සහ නම (muted)</td></tr>
                        <tr><td><strong>Regiment</strong></td><td>අදාළ රෙජිමේන්තුවේ නම</td></tr>
                        <tr><td><strong>Classification</strong></td><td>විශ්වාස මට්ටම (A-F) — badge ලෙස</td></tr>
                        <tr><td><strong>Category</strong></td><td>Informer / Casual Contact / Agent</td></tr>
                        <tr><td><strong>Handler</strong></td><td>හැන්ඩ්ලර්ගේ විස්තර + Contact Number</td></tr>
                        <tr><td><strong>Status</strong></td><td>Active (කොළ) හෝ Inactive (රතු) badge</td></tr>
                        <tr><td><strong>Actions</strong></td><td>Edit (පැන්සල් අයිකනය) සහ Delete (කසළ බඳුන)</td></tr>
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
                        <tr><td><strong>Edit</strong></td><td><i class="fa-solid fa-pen-to-square"></i></td><td>Form එක ඉහළට පුරවා Edit mode එකට යයි</td></tr>
                        <tr><td><strong>Delete</strong></td><td><i class="fa-solid fa-trash"></i></td><td>Soft delete (තහවුරු කිරීමෙන් පසු)</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="m-callout warn" style="margin-top:16px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>තහවුරු කිරීම</strong>
                    <p>Delete බොත්තම ඔබන විට browser එකේ confirmation dialog එකක් එයි: <em>"Confirm deletion of this record?"</em></p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-pen-to-square"></i><span>Edit Mode</span></div>
            <ol class="m-steps">
                <li>Master Database එකේ අදාළ අයෙකුගේ <i class="fa-solid fa-pen-to-square"></i> අයිකනය ඔබන්න.</li>
                <li>
                    URL එකට <code>?edit=ID</code> ලෙස එකතු වේ.
                </li>
                <li>Form එකේ ශීර්ෂය <strong>"Update Informer Profile"</strong> ලෙස වෙනස් වේ.</li>
                <li>දැනට ඇති දත්ත form එකේ පුරවා ඇත.</li>
                <li>වෙනස් කර, <strong>Update Records</strong> බොත්තම ඔබන්න.</li>
                <li>Edit mode එකෙන් ඉවත් වීමට <strong>Cancel Edit</strong> බොත්තම ඔබන්න.</li>
            </ol>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <p>සාර්ථක වූ පසු, form එක නැවත reset වේ. හරිත පණිවිඩයක් "Informer records updated successfully!" ලෙස පෙන්වයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-trash"></i><span>Soft Delete</span></div>
            <p>Delete බොත්තම ඔබා තහවුරු කළ පසු:</p>
            <ul class="m-steps">
                <li>තොරතුරු දෙන්නා සැබවින්ම ඉවත් නොවේ — <code>is_deleted = 1</code> ලෙස සටහන් වේ</li>
                <li>ලැයිස්තුවෙන් ඉවත් වේ</li>
                <li>දත්ත ගබඩාවේ තවමත් ඇත (audit සඳහා)</li>
                <li>පද්ධති පරිපාලකට DB එකෙන් නැවත ලබා ගත හැක</li>
            </ul>

            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <p>තොරතුරු දෙන්නා තව භාවිත නොකරන්නේ නම්, Delete වෙනුවට <strong>Status = Inactive</strong> ලෙස වෙනස් කිරීම වඩා හොඳයි. එවිට ලැයිස්තුවේ තවම පෙනෙනවා — හැබැයි Active නොවේ.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: INFORMER DETAILS ═══════════════ -->
    <div class="m-tab-panel" id="tab-details">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>Informer Details Page</span></div>
            <p>
                <code>view_informer.php</code> page එක එක් තොරතුරු දෙන්නෙකුගේ සම්පූර්ණ විස්තර පෙන්වයි. මෙය <strong>රහස්‍යතාවයෙන් තොරව</strong> ලිපි ගණන පෙන්වයි.
            </p>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>URL Format</strong>
                    <p><code>view_informer.php?id=X</code> — X යනු තොරතුරු දෙන්නාගේ ID එකයි.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-folder-open"></i><span>Page Sections</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Section</th><th>අඩංගු දේ</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Basic Information</strong></td><td>Code, Name, Category, Classification, Regiment, Status, Handler, Contact, Created At, Updated At</td></tr>
                        <tr><td><strong>Internal Letters</strong></td><td>මෙම අයෙකුට සම්බන්ධ Internal ලිපි (CDR, Title, Category, Security, Priority, Status, Forward count)</td></tr>
                        <tr><td><strong>Outward Letters</strong></td><td>Internal ලිපිය අනුව කාණ්ඩ වශයෙන් Outward ලිපි</td></tr>
                        <tr><td><strong>Task Letters</strong></td><td>Internal ලිපිය අනුව කාණ්ඩ වශයෙන් Task ලිපි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-secret"></i><span>Basic Information Card</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Field</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Informer Code</strong></td><td>අනන්‍ය කේතය</td></tr>
                        <tr><td><strong>Full Name</strong></td><td>නම / කේත නාමය</td></tr>
                        <tr><td><strong>Category</strong></td><td>වර්ණ badge ලෙස (Agent = රතු · Informer = කොළ · Casual Contact = තැඹිලි)</td></tr>
                        <tr><td><strong>Classification</strong></td><td>"Class A" වගේ badge ලෙස</td></tr>
                        <tr><td><strong>Regiment</strong></td><td>අදාළ රෙජිමේන්තුව</td></tr>
                        <tr><td><strong>Status</strong></td><td>Active (කොළ) හෝ Inactive (රතු)</td></tr>
                        <tr><td><strong>Handler Details</strong></td><td>හැන්ඩ්ලර්ගේ විස්තර</td></tr>
                        <tr><td><strong>Contact Number</strong></td><td>සම්බන්ධතා අංකය</td></tr>
                        <tr><td><strong>Created At</strong></td><td>ලියාපදිංචි කළ දිනය</td></tr>
                        <tr><td><strong>Updated At</strong></td><td>අවසන් වරට යාවත්කාලීන කළ දිනය</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-envelope-open-text"></i><span>Internal Letters Section</span></div>
            <p>මෙම තොරතුරු දෙන්නා සම්බන්ධ සියලුම Internal ලිපි පෙන්වයි:</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Column</th><th>විස්තරය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>CDR No.</strong></td><td>ලිපියේ ලියාපදිංචි අංකය</td></tr>
                        <tr><td><strong>Title</strong></td><td>මාතෘකාව</td></tr>
                        <tr><td><strong>Category</strong></td><td>ලිපියේ වර්ගය</td></tr>
                        <tr><td><strong>Security</strong></td><td>රහස්‍යතා මට්ටම</td></tr>
                        <tr><td><strong>Priority</strong></td><td>හදිසි මට්ටම</td></tr>
                        <tr><td><strong>Status</strong></td><td>ලිපියේ තත්ත්වය</td></tr>
                        <tr><td><strong>Forwarded</strong></td><td>කීප වතාවක් යොමු කර ඇත්ද</td></tr>
                        <tr><td><strong>Actions</strong></td><td>View (👁) බොත්තම</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-paper-plane"></i><span>Outward Letters Section</span></div>
            <p>Internal ලිපිය අනුව <strong>කාණ්ඩ වශයෙන්</strong> Outward ලිපි පෙන්වයි. හැම කාණ්ඩයකම:</p>
            <ul class="m-steps">
                <li><strong>Internal Letter Header</strong> — CDR + Title + Outward count</li>
                <li><strong>Outward Letters table</strong> — Ref, Title, Receiver, Method, Status, Actions</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Task Letters Section</span></div>
            <p>Internal ලිපිය අනුව කාණ්ඩ වශයෙන් Task ලිපි පෙන්වයි:</p>
            <ul class="m-steps">
                <li><strong>Ref.</strong> — Task Reference</li>
                <li><strong>Title</strong> — Task මාතෘකාව</li>
                <li><strong>Priority</strong> — හදිසි මට්ටම</li>
                <li><strong>Status</strong> — Task තත්ත්වය</li>
                <li><strong>Actions</strong> — View (👁) බොත්තම</li>
            </ul>
        </div>

        <div class="m-callout tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div>
                <strong>Tip</strong>
                <p>ඕනෑම ලිපියක් මත ඇති <i class="fa-regular fa-eye"></i> බොත්තම ඔබා එම ලිපියේ Unified Details page එකට යා හැකියි.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: CATEGORIES & CLASSIFICATIONS ═══════════════ -->
    <div class="m-tab-panel" id="tab-categories">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tags"></i><span>Categories — 3ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip informer"><i class="fa-solid fa-user-secret"></i> Informer</span>
                <span class="chip casual"><i class="fa-solid fa-user"></i> Casual Contact</span>
                <span class="chip agent"><i class="fa-solid fa-user-tie"></i> Agent</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Category</th><th>අර්ථය</th><th>උදාහරණය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Informer</strong></td><td>ස්ථිර තොරතුරු දෙන්නා</td><td>දිගු කාලීන සම්බන්ධතාවය</td></tr>
                        <tr><td><strong>Casual Contact</strong></td><td>අහඹු සම්බන්ධතාවය</td><td>එක වරක් හෝ දෙපාරක් තොරතුරු දුන් අය</td></tr>
                        <tr><td><strong>Agent</strong></td><td>ක්‍රියාකාරී නියෝජිතයා</td><td>පද්ධතිය තුළ ක්‍රියාකාරී අය</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-signal"></i><span>Classification Levels — 6ක්</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip class-a">A</span>
                <span class="chip class-b">B</span>
                <span class="chip class-c">C</span>
                <span class="chip class-d">D</span>
                <span class="chip class-e">E</span>
                <span class="chip class-f">F</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Class</th><th>විශ්වාස මට්ටම</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>A</strong></td><td>ඉහළම</td><td>සම්පූර්ණයෙන්ම විශ්වාස කළ හැකි අය</td></tr>
                        <tr><td><strong>B</strong></td><td>ඉහළ</td><td>විශ්වාසනීය අය</td></tr>
                        <tr><td><strong>C</strong></td><td>මධ්‍යම</td><td>සාමාන්‍ය විශ්වාසය</td></tr>
                        <tr><td><strong>D</strong></td><td>පහළ-මධ්‍යම</td><td>පරීක්ෂාව අවශ්‍ය</td></tr>
                        <tr><td><strong>E</strong></td><td>පහළ</td><td>අවිශ්වාසනීය</td></tr>
                        <tr><td><strong>F</strong></td><td>ඉතා පහළ</td><td>ඉතා අවිශ්වාසනීය</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>භාවිතය</strong>
                    <p>Classification එක භාවිතා වන්නේ තොරතුරු දෙන්නාගේ විශ්වාසනීයත්වය මැන බැලීමටයි. A-E වලට වඩා F හොඳයි කියලා නොසිතන්න — ස්වභාවය වෙනස්.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-toggle-on"></i><span>Status Types</span></div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0;">
                <span class="chip active"><i class="fa-solid fa-check"></i> Active</span>
                <span class="chip inactive"><i class="fa-solid fa-xmark"></i> Inactive</span>
            </div>
            <div class="m-table-wrap" style="margin-top:14px;">
                <table class="m-table">
                    <thead><tr><th>Status</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Active</strong></td><td>දැනට ක්‍රියාකාරීව තොරතුරු සපයන අය</td></tr>
                        <tr><td><strong>Inactive</strong></td><td>තව තොරතුරු සපයන්නේ නැති අය</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 6: WORKFLOW ═══════════════ -->
    <div class="m-tab-panel" id="tab-workflow">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>Informer Lifecycle</span></div>
            <div class="workflow">
                <div class="wf-step create">
                    <div class="wf-icon"><i class="fa-solid fa-plus"></i></div>
                    <div class="wf-title">1. Register</div>
                    <div class="wf-desc">තොරතුරු දෙන්නා ලියාපදිංචි කරයි</div>
                </div>
                <div class="wf-step active">
                    <div class="wf-icon"><i class="fa-solid fa-check"></i></div>
                    <div class="wf-title">2. Active</div>
                    <div class="wf-desc">ක්‍රියාකාරීව තොරතුරු සපයයි</div>
                </div>
                <div class="wf-step link">
                    <div class="wf-icon"><i class="fa-solid fa-link"></i></div>
                    <div class="wf-title">3. Linked</div>
                    <div class="wf-desc">ලිපි සම්බන්ධ වේ</div>
                </div>
                <div class="wf-step report">
                    <div class="wf-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="wf-title">4. Track</div>
                    <div class="wf-desc">ලිපි සහ ක්‍රියාකාරකම් නිරීක්ෂණය</div>
                </div>
                <div class="wf-step inactive">
                    <div class="wf-icon"><i class="fa-solid fa-xmark"></i></div>
                    <div class="wf-title">5. Inactive</div>
                    <div class="wf-desc">තව භාවිත නොකරයි</div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-route"></i><span>සම්පූර්ණ ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>Register</strong> — නව තොරතුරු දෙන්නෙකු ලියාපදිංචි කිරීම</li>
                <li><strong>Create Internal Letter</strong> — Internal ලිපියක් සාදන විට තොරතුරු දෙන්නා සම්බන්ධ කිරීම</li>
                <li><strong>Link Letters</strong> — එම ලිපියට Outward, Task ලිපි සම්බන්ධ වීම</li>
                <li><strong>Track Activity</strong> — Informer Details page එකෙන් සියලුම ක්‍රියාකාරකම් නිරීක්ෂණය</li>
                <li><strong>Deactivate</strong> — තව භාවිත නොකරන්නේ නම් Inactive කිරීම</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-link"></i><span>ලිපි සමඟ සම්බන්ධය</span></div>
            <p>තොරතුරු දෙන්නෙකු Internal ලිපියකට සම්බන්ධ වන්නේ <code>internal_letters.Informer_id</code> කොටස හරහායි.</p>
            <div class="m-code" data-lang="sql">
-- එක් informer එකකට සම්බන්ධ සියලුම internal letters
SELECT il.* 
FROM internal_letters il
WHERE il.Informer_id = [informer_id]
  AND il.is_deleted = 0;

-- ඒවාට අදාළ outward letters
SELECT ol.* 
FROM outward_letters ol
JOIN outward_internal oi ON ol.id = oi.outward_id
WHERE oi.internal_id IN ([informer_letters]);</div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-database"></i><span>මැදිහත් වන Tables</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Table</th><th>කරන කාර්යය</th></tr></thead>
                    <tbody>
                        <tr><td><code>informers</code></td><td>තොරතුරු දෙන්නන්ගේ මූලික දත්ත</td></tr>
                        <tr><td><code>regiments</code></td><td>රෙජිමේන්තු</td></tr>
                        <tr><td><code>internal_letters</code></td><td>Informer_id හරහා සම්බන්ධ ලිපි</td></tr>
                        <tr><td><code>outward_letters</code></td><td>සම්බන්ධ බාහිර ලිපි</td></tr>
                        <tr><td><code>task_letters</code></td><td>සම්බන්ධ කාර්ය ලිපි</td></tr>
                        <tr><td><code>outward_internal</code></td><td>Outward ↔ Internal සම්බන්ධතා</td></tr>
                        <tr><td><code>task_internal_links</code></td><td>Task ↔ Internal සම්බන්ධතා</td></tr>
                    </tbody>
                </table>
            </div>
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
                            <td><span class="perm-code">MANAGE_INFORMERS</span></td>
                            <td>Informer module එකට ප්‍රවේශය (add, edit, delete, view)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top:14px;font-size:0.88rem;color:var(--muted);">
                මෙම permission එක නැත්නම්, පහත පිටු 2ටම ප්‍රවේශය නොමැත:
            </p>
            <ul class="m-steps">
                <li>Informers Registry (informers.php)</li>
                <li>Informer Details (view_informer.php)</li>
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
                            <th>Intelligence Officer</th>
                            <th>Handler</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>View Informers List</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>View Details</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Add Informer</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Edit Informer</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                        <tr><td>Delete Informer</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td><td style="color:#ef4444;">✗</td><td style="color:#00C853;">✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-callout warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Access Denied පෙන්වයි නම්</strong>
                <p>ඔබට <span class="perm-code">MANAGE_INFORMERS</span> permission එක නැහැ. ඔබේ පද්ධති පරිපාලක අමතන්න.</p>
            </div>
        </div>

        <div class="m-callout info">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>රහස්‍යතා අවවාදය</strong>
                <p>තොරතුරු දෙන්නන්ගේ දත්ත අති රහස්‍යයි. ඔබේ පරිශීලක ගිණුම හරහා සිදුවන සියලු ක්‍රියාකාරකම් පද්ධතිය විසින් සටහන් කරයි. මුරපදය කිසිවිටෙක බෙදා නොගන්න.</p>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="08-informers" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="07-linkage-reports.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Linkage Reports</span></a>
        <a href="09-administration.php" class="next"><span class="lbl">Next →</span><span class="ttl">Administration</span></a>
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