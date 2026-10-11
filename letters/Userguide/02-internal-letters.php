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
<title>Add Internal Letter | IDCMS Manual</title>
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

/* Form Preview Mock */
.form-preview {
    background: var(--code-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 24px;
    margin: 18px 0;
}
.form-preview .row-mock {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}
.form-preview .field-mock {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.form-preview .field-mock label {
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--muted);
}
.form-preview .field-mock .input-mock {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 0.85rem;
    color: var(--muted);
    font-family: 'JetBrains Mono', monospace;
}
.form-preview .field-mock .input-mock.readonly {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 700;
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
        <a href="02-internal-letters.php" class="active"><i class="fa-solid fa-envelope-open-text"></i><span>Internal Letters</span></a>
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
            <span class="current">Internal Letters</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-envelope-open-text"></i> Dispatching</span>
        <h1>Add Internal Letter</h1>
        <p>අභ්‍යන්තර ලිපියක් පද්ධතියට ලියාපදිංචි කිරීමේ සම්පූර්ණ මාර්ගෝපදේශය.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 8 min read</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-overview"><i class="fa-solid fa-circle-info"></i> Overview</button>
        <button class="m-tab" data-tab="tab-steps"><i class="fa-solid fa-list-ol"></i> Step-by-Step</button>
        <button class="m-tab" data-tab="tab-fields"><i class="fa-solid fa-table"></i> Field Reference</button>
        <button class="m-tab" data-tab="tab-rules"><i class="fa-solid fa-shield-halved"></i> Rules &amp; Permissions</button>
        <button class="m-tab" data-tab="tab-preview"><i class="fa-solid fa-list-check"></i> Preview Table</button>
    </div>

    <!-- ═══════════════ TAB 1: Overview ═══════════════ -->
    <div class="m-tab-panel active" id="tab-overview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bullseye"></i><span>මෙම Page එකේ අරමුණ</span></div>
            <p>
                <strong>Add Internal Letter</strong> page එක මගින් ආයතනයට ලැබුණු නව අභ්‍යන්තර ලිපියක් පද්ධතියට ලියාපදිංචි කිරීමට ඉඩ සලසයි. ලිපිය ලියාපදිංචි කිරීමෙන් පසුව, එය <strong>Not Forwarded</strong> තත්ත්වයේ රැඳී තිබේ. අදාළ නිලධාරියාට හෝ ඩෙස්කයට යොමු කිරීමට <strong>Forward</strong> බොත්තම භාවිතා කළ හැක.
            </p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>ප්‍රධාන ලක්ෂණ</span></div>
            <ul class="m-steps">
                <li><strong>Auto CDR Generation</strong> — ලිපි අංකය (CDR) ස්වයංක්‍රීයව ජනනය වේ (<code>REG/2026/01</code> හෝ <code>ADM/2026/01</code>)</li>
                <li><strong>Duplicate Reference Check</strong> — එකම Letter Reference දෙපාරක් ඇතුළත් කළ නොහැක</li>
                <li><strong>Section-based Rules</strong> — Administration Section සහ Registry සඳහා වෙනස් නීති</li>
                <li><strong>Conditional Fields</strong> — Category <code>MISR</code> තෝරා ගත් විට පමණක් Classification සහ Informer පෙන්වයි</li>
                <li><strong>Attachment Upload</strong> — PDF හෝ Image ගොනු උඩුගත කළ හැක</li>
                <li><strong>Live Preview Table</strong> — අවසන් ලිපි 10ක් පහළින් පෙන්වයි</li>
                <li><strong>Not Forwarded Counter</strong> — යොමු නොකළ ලිපි ගණන ඉහළ දකුණේ පෙන්වයි</li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-diagram-project"></i><span>ලිපියක් ලියාපදිංචි වූ පසු ගමන් මග</span></div>
            <ol class="m-steps">
                <li><strong>Not Forwarded</strong> — ලියාපදිංචි කළ නමුත් යොමු නොකළ</li>
                <li><strong>Forwarded</strong> — අදාළ ඩෙස්කයට / නිලධාරියාට යොමු කළ</li>
                <li><strong>Pending</strong> — ක්‍රියාත්මක වෙමින් පවතින</li>
                <li><strong>Completed</strong> — කටයුතු අවසන් වූ</li>
                <li><strong>Final Disposal</strong> — බැහැර කර ගොනු කළ</li>
            </ol>
        </div>
    </div>

    <!-- ═══════════════ TAB 2: Step-by-Step ═══════════════ -->
    <div class="m-tab-panel" id="tab-steps">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-ol"></i><span>නව ලිපියක් ලියාපදිංචි කිරීම</span></div>
            <ol class="m-steps">
                <li>පැති තීරුවෙන් <code>Internal Letters → Add Internal</code> තෝරන්න.</li>
                <li>
                    <strong>CDR Number</strong> කොටුව — පද්ධතිය විසින්ම පුරවා ඇත. ඔබට වෙනස් කළ නොහැක.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>Format එක</strong>
                            <p>Administration Section සඳහා <code>ADM/2026/01</code>, Registry සඳහා <code>REG/2026/01</code>. සෑම වර්ෂයකටම අංක <code>01</code> සිට නැවත ආරම්භ වේ.</p>
                        </div>
                    </div>
                </li>
                <li>
                    <strong>Letter Reference</strong> කොටුවේ ලිපියේ යොමුව ඇතුළත් කරන්න.
                    <div class="m-callout warn" style="margin-top:8px;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <strong>නීති දෙකක්</strong>
                            <ul>
                                <li><strong>හිස්තැන් (spaces) තහනම්</strong> — ඔබ space bar එබුවහොත් එය ස්වයංක්‍රීයව ඉවත් වේ</li>
                                <li><strong>Duplicate නොවිය යුතුයි</strong> — දැනටමත් තියෙන reference එකක් ආයෙත් ඇතුළත් කළහොත් දෝෂ පණිවිඩයක් පෙන්වයි</li>
                            </ul>
                            <p style="margin-top:6px;">හොඳ උදාහරණ: <code>D/REG/2026/001</code>, <code>DMI/ADMIN/001</code></p>
                        </div>
                    </div>
                </li>
                <li><strong>Letter Date</strong> කොටුවෙන් ලිපියේ දිනය තෝරන්න (Calendar අයිකනය භාවිතා කරන්න).</li>
                <li><strong>Letter Title / Subject</strong> කොටුවේ ලිපියේ මාතෘකාව ලියන්න (අනිවාර්යයි).</li>
                <li>
                    <strong>Source Regiment</strong> ලැයිස්තුවෙන් ලිපිය ලැබුණු රෙජිමේන්තුව තෝරන්න.
                    <div class="m-callout info" style="margin-top:8px;">
                        <i class="fa-solid fa-circle-info"></i>
                        <div><p>මෙය <strong>Letter Category</strong> තෝරා ගැනීමට පෙර තෝරන්න — මන්ද MISR ලිපි සඳහා Informer ලැයිස්තුව රෙජිමේන්තුව අනුව පෙරහන් වේ.</p></div>
                    </div>
                </li>
                <li>
                    <strong>Letter Category</strong> ලැයිස්තුවෙන් වර්ගය තෝරන්න (Registry users සඳහා පමණි).
                    <br>
                    <strong>MISR</strong> තෝරා ගත් විට අමතර කොටු දෙකක් පෙන්වයි:
                    <ul style="margin-top:6px; padding-left:24px;">
                        <li><strong>Letter Classification</strong> (1 සිට 6 දක්වා අංක)</li>
                        <li><strong>Informer</strong> (රෙජිමේන්තුව අනුව පෙරහන් වූ ලැයිස්තුව)</li>
                    </ul>
                </li>
                <li><strong>Priority Status</strong> තෝරන්න — <code>Normal</code> / <code>Urgent</code> / <code>Immediately</code>.</li>
                <li><strong>Security Classification</strong> තෝරන්න — <code>Public</code> / <code>Restricted</code> / <code>Confidential</code> / <code>Secret</code> / <code>Top Secret</code>.</li>
                <li><strong>Attachment</strong> — ලිපියේ PDF හෝ Image ගොනුව උඩුගත කරන්න (අවශ්‍ය නම් පමණයි).</li>
                <li><strong>About Letter / Minute</strong> — ලිපියේ කෙටි සාරාංශය ලියන්න.</li>
                <li><strong>Remarks / Comments</strong> — අභ්‍යන්තර සටහන් තිබේ නම් ලියන්න.</li>
                <li>
                    <strong>Register &amp; Save Letter</strong> බොත්තම ඔබන්න.
                    <div class="m-callout tip" style="margin-top:8px;">
                        <i class="fa-solid fa-lightbulb"></i>
                        <div><p>සාර්ථක වූ පසු <strong>හරිත පණිවිඩයක්</strong> ("Letter successfully registered!") පෙන්වයි, CDR අංකය ඊළඟට යාවත්කාලීන වේ, සහ ලිපිය පහළ ලැයිස්තුවට එකතු වේ.</p></div>
                    </div>
                </li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-rotate-left"></i><span>Clear Form බොත්තම</span></div>
            <p>ඕනෑම වේලාවක ලියූ දේවල් සියල්ල ඉවත් කිරීමට <strong>Clear Form</strong> බොත්තම භාවිතා කරන්න. මෙය ලියාපදිංචි කරන ලද දත්ත කිසිවක් ඉවත් නොකරයි.</p>
        </div>
    </div>

    <!-- ═══════════════ TAB 3: Field Reference ═══════════════ -->
    <div class="m-tab-panel" id="tab-fields">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table"></i><span>සියලුම Field පිළිබඳ තොරතුරු</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>අර්ථය</th>
                            <th>Status</th>
                            <th>විස්තර</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>CDR Number</code></td>
                            <td>ලිපියේ ලියාපදිංචි අංකය</td>
                            <td><span class="optional">Auto</span></td>
                            <td>පද්ධතිය මගින් ස්වයංක්‍රීයව ජනනය වේ. වෙනස් කළ නොහැක.</td>
                        </tr>
                        <tr>
                            <td><code>Letter Reference</code></td>
                            <td>ලිපියේ යොමු අංකය</td>
                            <td><span class="optional">Optional</span></td>
                            <td>හිස්තැන් තහනම් · Duplicate නොවිය යුතුයි</td>
                        </tr>
                        <tr>
                            <td><code>Letter Date</code></td>
                            <td>ලිපියේ දිනය</td>
                            <td><span class="required">Required</span></td>
                            <td>Calendar එකෙන් තෝරන්න</td>
                        </tr>
                        <tr>
                            <td><code>Letter Title</code></td>
                            <td>ලිපියේ මාතෘකාව</td>
                            <td><span class="required">Required</span></td>
                            <td>ලිපියේ ප්‍රධාන විෂය</td>
                        </tr>
                        <tr>
                            <td><code>Source Regiment</code></td>
                            <td>ලිපිය ලැබුණු රෙජිමේන්තුව</td>
                            <td><span class="required">Required</span></td>
                            <td>Active රෙජිමේන්තු පමණක් පෙන්වයි</td>
                        </tr>
                        <tr>
                            <td><code>Letter Category</code></td>
                            <td>ලිපියේ වර්ගය</td>
                            <td><span class="optional">Registry</span></td>
                            <td>Admin Section users ට පෙන්වන්නේ නැහැ</td>
                        </tr>
                        <tr>
                            <td><code>Letter Classification</code></td>
                            <td>වර්ගීකරණ අංකය (1-6)</td>
                            <td><span class="optional">Conditional</span></td>
                            <td>Category = MISR විට පමණක් පෙන්වයි</td>
                        </tr>
                        <tr>
                            <td><code>Informer</code></td>
                            <td>තොරතුරු දෙන්නා</td>
                            <td><span class="optional">Conditional</span></td>
                            <td>MISR විට පමණක් · රෙජිමේන්තුව අනුව පෙරහන් වේ</td>
                        </tr>
                        <tr>
                            <td><code>Priority Status</code></td>
                            <td>හදිසි මට්ටම</td>
                            <td><span class="optional">Default</span></td>
                            <td>පෙරනිමිය: Normal</td>
                        </tr>
                        <tr>
                            <td><code>Security Classification</code></td>
                            <td>රහස්‍යතා මට්ටම</td>
                            <td><span class="required">Required</span></td>
                            <td>පෙරනිමිය: Restricted</td>
                        </tr>
                        <tr>
                            <td><code>Attachment</code></td>
                            <td>අමුණන ගොනුව</td>
                            <td><span class="optional">Optional</span></td>
                            <td>PDF හෝ Image · <code>uploads/letters/</code> වෙත save වේ</td>
                        </tr>
                        <tr>
                            <td><code>About Letter</code></td>
                            <td>ලිපියේ සාරාංශය</td>
                            <td><span class="optional">Optional</span></td>
                            <td>Multi-line textarea</td>
                        </tr>
                        <tr>
                            <td><code>Remarks / Comments</code></td>
                            <td>අභ්‍යන්තර සටහන්</td>
                            <td><span class="optional">Optional</span></td>
                            <td>Multi-line textarea</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-eye"></i><span>Form Preview — කොටු පෙනෙන ආකාරය</span></div>
            <div class="form-preview">
                <div class="row-mock">
                    <div class="field-mock"><label>CDR Number</label><div class="input-mock readonly">REG/2026/13</div></div>
                    <div class="field-mock"><label>Letter Reference</label><div class="input-mock">D/REG/2026/001</div></div>
                    <div class="field-mock"><label>Letter Date</label><div class="input-mock">2026-09-28</div></div>
                </div>
                <div class="row-mock">
                    <div class="field-mock" style="grid-column: span 2;"><label>Letter Title / Subject</label><div class="input-mock">යුද හමුදා වාර්තාව</div></div>
                    <div class="field-mock"><label>Source Regiment</label><div class="input-mock">Colombo Regiment</div></div>
                </div>
                <div class="row-mock">
                    <div class="field-mock"><label>Priority Status</label><div class="input-mock">Urgent</div></div>
                    <div class="field-mock"><label>Security Classification</label><div class="input-mock">Confidential</div></div>
                    <div class="field-mock"><label>Attachment</label><div class="input-mock">letter_13.pdf</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ TAB 4: Rules & Permissions ═══════════════ -->
    <div class="m-tab-panel" id="tab-rules">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-lock"></i><span>Permission අවශ්‍යතාව</span></div>
            <p>මෙම page එකට පිවිසෙන්න <strong><code>ADD_INTERNAL_LETTER</code></strong> permission එක අවශ්‍යයි. එය නැත්නම් <em>"Access Denied!"</em> පෙන්වයි.</p>

            <div class="m-callout info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Permission ලබා ගැනීම</strong>
                    <p>ඔබට මෙම permission එක නැත්නම්, ඔබේ පද්ධති පරිපාලක අමතන්න.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-users-gear"></i><span>Section අනුව නීති</span></div>
            <p>පද්ධතිය ඔබව <strong>Section</strong> දෙකකට වෙන් කරයි:</p>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr><th>Section</th><th>CDR Prefix</th><th>විශේෂ ලක්ෂණ</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Administration Section</strong></td>
                            <td><code>ADM</code></td>
                            <td>Letter Category සහ Informer fields පෙන්වන්නේ නැහැ</td>
                        </tr>
                        <tr>
                            <td><strong>Registry Section</strong></td>
                            <td><code>REG</code></td>
                            <td>සියලුම fields පෙන්වයි · Category MISR විට අමතර fields</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-ban"></i><span>Validator Rules</span></div>
            <ol class="m-steps">
                <li><strong>Required Fields</strong> — Letter Title, Letter Date, Source Regiment, Security Classification</li>
                <li><strong>No Spaces in Reference</strong> — Letter Reference කොටුවේ space එබුවොත් එය auto-remove වේ</li>
                <li><strong>Unique Reference</strong> — දැනටමත් ඇති reference එකක් ආයෙත් දාන්න බැහැ</li>
                <li><strong>Informer Regiment Check</strong> — තෝරන Informer අදාළ රෙජිමේන්තුවට අයත් ද යන්න පරීක්ෂා කරයි</li>
                <li><strong>Attachment Upload</strong> — සාර්ථකව upload වුනොත් පමණක් save වේ</li>
            </ol>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hashtag"></i><span>CDR Generation Logic</span></div>
            <p>සෑම වර්ෂයකටම අංක <code>01</code> සිට ආරම්භ වේ. පද්ධතිය අවසන් වරට භාවිතා කළ අංකය සොයා, එයට <code>+1</code> එකතු කරයි.</p>
            <div class="m-code" data-lang="format">
ADM/2026/01   ← පළමු Administration letter
ADM/2026/02   ← දෙවන letter
...
REG/2026/01   ← පළමු Registry letter
REG/2026/02   ← දෙවන letter</div>
        </div>
    </div>

    <!-- ═══════════════ TAB 5: Preview Table ═══════════════ -->
    <div class="m-tab-panel" id="tab-preview">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-list-check"></i><span>Recent Correspondence Workspace</span></div>
            <p>Form එකට පහළින් <strong>අවසන් ලිපි 10ක්</strong> ලැයිස්තුවක් පෙන්වයි. මෙහි අඩංගු වන්නේ <strong>Not Forwarded</strong> තත්ත්වයේ ලිපි පමණයි.</p>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-table-columns"></i><span>Table Columns</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr><th>Column</th><th>විස්තරය</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>CDR Number</strong></td><td>ලිපියේ ලියාපදිංචි අංකය (monospace font)</td></tr>
                        <tr><td><strong>Reference</strong></td><td>Letter Reference (— ලෙස පෙන්වයි එකක් නැත්නම්)</td></tr>
                        <tr><td><strong>Date</strong></td><td>ලිපියේ දිනය (dd-Mmm-yyyy ආකෘතියෙන්)</td></tr>
                        <tr><td><strong>Regiment / Category</strong></td><td>රෙජිමේන්තුව සහ ලිපි වර්ගය</td></tr>
                        <tr><td><strong>Security</strong></td><td>රහස්‍යතා මට්ටම (badge ලෙස)</td></tr>
                        <tr><td><strong>Priority</strong></td><td>හදිසි මට්ටම (වර්ණ badge ලෙස)</td></tr>
                        <tr><td><strong>Status</strong></td><td>Not Forwarded / Forwarded / Pending / Completed</td></tr>
                        <tr><td><strong>Actions</strong></td><td>Forward බොත්තම සහ View බොත්තම</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-palette"></i><span>Priority Badge වර්ණ</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Priority</th><th>වර්ණය</th><th>අර්ථය</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Normal</strong></td><td style="color: var(--accent);">නිල්-දම්</td><td>සාමාන්‍ය ලිපිය</td></tr>
                        <tr><td><strong>Urgent</strong></td><td style="color: #FFB600;">කහ-තැඹිලි</td><td>ඉක්මන් ක්‍රියාමාර්ගයක් අවශ්‍යයි</td></tr>
                        <tr><td><strong>Immediately</strong></td><td style="color: #FF5B5B;">රතු</td><td>වහාම ක්‍රියාත්මක විය යුතුයි</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-hand-pointer"></i><span>Action බොත්තම්</span></div>
            <ul class="m-steps">
                <li>
                    <strong>Forward</strong> — <em>Not Forwarded</em> ලිපි සඳහා පමණක් පෙන්වයි. ක්ලික් කළ විට <code>forward_letter.php</code> page එකට යවයි.
                </li>
                <li>
                    <strong>View (👁️)</strong> — ලිපියේ සම්පූර්ණ විස්තර page එක නව tab එකක open කරයි.
                </li>
            </ul>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-bell"></i><span>Not Forwarded Counter</span></div>
            <p>ඉහළ දකුණේ <strong>"Not Forwarded: N"</strong> ලෙස පෙන්වයි. මෙය මුළු පද්ධතියේම යොමු නොකළ ලිපි ගණනයි — පමණක් ඔබේ නොවේ.</p>
            <div class="m-callout tip">
                <i class="fa-solid fa-lightbulb"></i>
                <div>
                    <strong>Tip</strong>
                    <p>මෙම අංකය නිතර බලන්න. ගණන වැඩි නම්, යොමු කිරීමට රැඳී ඇති ලිපි කිහිපයක් ඇත.</p>
                </div>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="02-internal-letters" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="01-getting-started.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Getting Started</span></a>
        <a href="03-outward-letters.php" class="next"><span class="lbl">Next →</span><span class="ttl">Outward Letters</span></a>
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