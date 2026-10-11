<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();
if (!isset($_SESSION['user_id'])) die('Session Invalid');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Developer, Version &amp; Features | IDCMS</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="assets/manual.css" rel="stylesheet">
<style>
/* ═══════ Tabs ═══════ */
.m-tabs { display:flex; gap:4px; margin:24px 0 20px; border-bottom:2px solid var(--border); overflow-x:auto; padding-bottom:2px; }
.m-tab { padding:12px 18px; background:transparent; border:none; color:var(--muted); font-weight:700; font-size:0.84rem; cursor:pointer; border-bottom:3px solid transparent; transition:0.2s; white-space:nowrap; font-family:inherit; display:inline-flex; align-items:center; gap:8px; margin-bottom:-2px; }
.m-tab:hover { color:var(--accent); }
.m-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
.m-tab-panel { display:none; animation:fadeIn 0.25s ease; }
.m-tab-panel.active { display:block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(6px);} to{opacity:1;transform:translateY(0);} }

/* ═══════ Feature Grid ═══════ */
.feature-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:16px; margin:16px 0; }
.feature-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px; transition:0.25s; position:relative; overflow:hidden; }
.feature-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; background:var(--accent); transform:scaleY(0); transform-origin:top; transition:0.3s; }
.feature-card:hover { transform:translateY(-4px); border-color:var(--accent); box-shadow:0 12px 28px rgba(67,24,255,0.12); }
.feature-card:hover::before { transform:scaleY(1); }
.feature-card .fc-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#fff; margin-bottom:14px; background:linear-gradient(135deg, var(--accent), #A855F7); }
.feature-card .fc-icon.green { background:linear-gradient(135deg, #10B981, #059669); }
.feature-card .fc-icon.orange { background:linear-gradient(135deg, #F59E0B, #D97706); }
.feature-card .fc-icon.blue { background:linear-gradient(135deg, #3B82F6, #1D4ED8); }
.feature-card .fc-icon.red { background:linear-gradient(135deg, #EF4444, #B91C1C); }
.feature-card .fc-icon.purple { background:linear-gradient(135deg, #8B5CF6, #6D28D9); }
.feature-card .fc-icon.cyan { background:linear-gradient(135deg, #06B6D4, #0E7490); }
.feature-card h5 { font-weight:800; font-size:1rem; color:var(--text-strong); margin:0 0 8px; }
.feature-card p { font-size:0.83rem; color:var(--muted); margin:0; line-height:1.55; }

/* ═══════ Stack Cards ═══════ */
.stack-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:14px; margin:16px 0; }
.stack-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:18px; transition:0.2s; position:relative; overflow:hidden; }
.stack-card:hover { transform:translateY(-3px); border-color:var(--accent); box-shadow:0 10px 24px rgba(67,24,255,0.1); }
.stack-card .lang-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:#fff; margin-bottom:12px; font-weight:800; }
.stack-card .lang-name { font-weight:800; font-size:1rem; color:var(--text-strong); margin-bottom:4px; }
.stack-card .lang-role { font-size:0.78rem; color:var(--muted); margin-bottom:8px; line-height:1.4; }
.stack-card .lang-version { font-family:'JetBrains Mono',monospace; font-size:0.7rem; color:var(--accent); background:var(--accent-soft); padding:2px 8px; border-radius:5px; display:inline-block; }

.lc-php { background:linear-gradient(135deg, #777BB4, #4F5B93); }
.lc-mysql { background:linear-gradient(135deg, #00758F, #00546B); }
.lc-html { background:linear-gradient(135deg, #E34F26, #C43E1E); }
.lc-css { background:linear-gradient(135deg, #1572B6, #0F5A8F); }
.lc-js { background:linear-gradient(135deg, #F7DF1E, #E0C60A); color:#000 !important; }
.lc-bootstrap { background:linear-gradient(135deg, #7952B3, #5A3E8A); }
.lc-fa { background:linear-gradient(135deg, #528DD7, #3A6BA8); }
.lc-chart { background:linear-gradient(135deg, #FF6384, #E64A6E); }
.lc-jq { background:linear-gradient(135deg, #0769AD, #054D80); }
.lc-xlsx { background:linear-gradient(135deg, #217346, #164E2F); }
.lc-jspdf { background:linear-gradient(135deg, #D32F2F, #B71C1C); }
.lc-tcpdf { background:linear-gradient(135deg, #212529, #000); }
.lc-chrome { background:linear-gradient(135deg, #4285F4, #34A853); }
.lc-git { background:linear-gradient(135deg, #F05032, #C63D1E); }
.lc-composer { background:linear-gradient(135deg, #885630, #5E3A20); }
.lc-apache { background:linear-gradient(135deg, #D22128, #A01820); }

/* ═══════ Developer Profile ═══════ */
.dev-hero {
    display:flex; align-items:center; gap:24px;
    background:linear-gradient(135deg, rgba(67,24,255,0.08), rgba(168,85,247,0.08));
    border:1px solid var(--border); border-radius:18px;
    padding:28px; margin:16px 0;
    position:relative; overflow:hidden;
}
.dev-hero::before {
    content:''; position:absolute; top:-50%; right:-10%;
    width:300px; height:300px; border-radius:50%;
    background:radial-gradient(circle, rgba(67,24,255,0.15), transparent 70%);
}
.dev-avatar {
    width:110px; height:110px; border-radius:24px;
    background:linear-gradient(135deg, #4318FF, #A855F7);
    display:flex; align-items:center; justify-content:center;
    font-size:2.6rem; color:#fff; flex-shrink:0;
    box-shadow:0 12px 32px rgba(67,24,255,0.3);
    position:relative; z-index:1;
}
.dev-info { flex:1; position:relative; z-index:1; }
.dev-info h2 { font-size:1.6rem; font-weight:800; color:var(--text-strong); margin:0 0 6px; }
.dev-info .dev-role { color:var(--accent); font-weight:700; font-size:0.95rem; margin-bottom:10px; }
.dev-info .dev-desc { font-size:0.88rem; color:var(--muted); line-height:1.6; margin:0 0 12px; }
.dev-tags { display:flex; flex-wrap:wrap; gap:8px; }
.dev-tags span {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--card); border:1px solid var(--border);
    padding:5px 12px; border-radius:20px;
    font-size:0.75rem; font-weight:700; color:var(--text);
}
.dev-tags span i { color:var(--accent); font-size:0.72rem; }

.dev-contact {
    display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:12px; margin:16px 0;
}
.dev-contact-item {
    display:flex; align-items:center; gap:12px;
    background:var(--card); border:1px solid var(--border);
    padding:14px 16px; border-radius:12px; transition:0.2s;
}
.dev-contact-item:hover { border-color:var(--accent); transform:translateY(-2px); }
.dev-contact-item .dci-icon {
    width:40px; height:40px; border-radius:10px;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; flex-shrink:0;
}
.dev-contact-item .dci-body { flex:1; min-width:0; }
.dev-contact-item .dci-label { font-size:0.7rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px; }
.dev-contact-item .dci-value { font-size:0.85rem; color:var(--text-strong); font-weight:700; word-break:break-word; }

/* ═══════ Timeline ═══════ */
.timeline { position:relative; padding-left:34px; margin:18px 0; }
.timeline::before { content:''; position:absolute; left:11px; top:8px; bottom:8px; width:2px; background:linear-gradient(180deg, var(--accent), #A855F7, transparent); }
.tl-item { position:relative; padding:0 0 22px; }
.tl-item::before { content:''; position:absolute; left:-30px; top:6px; width:14px; height:14px; border-radius:50%; background:var(--accent); border:3px solid var(--bg); box-shadow:0 0 0 2px var(--accent); }
.tl-item .tl-date { font-size:0.72rem; color:var(--accent); font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:4px; }
.tl-item .tl-title { font-size:0.95rem; color:var(--text-strong); font-weight:800; margin:0 0 6px; }
.tl-item .tl-desc { font-size:0.83rem; color:var(--muted); margin:0; line-height:1.55; }
.tl-item .tl-desc ul { margin:6px 0 0; padding-left:18px; }
.tl-item .tl-desc ul li { font-size:0.8rem; margin-bottom:3px; }

/* ═══════ Stat Boxes ═══════ */
.stat-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:14px; margin:18px 0; }
.stat-box { text-align:center; padding:20px 14px; background:var(--card); border:1px solid var(--border); border-radius:14px; transition:0.2s; }
.stat-box:hover { transform:translateY(-3px); border-color:var(--accent); }
.stat-box .sb-num { font-size:2rem; font-weight:800; line-height:1; background:linear-gradient(135deg,var(--accent),#A855F7); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:6px; }
.stat-box .sb-lbl { font-size:0.78rem; color:var(--muted); font-weight:600; }

/* ═══════ Inline Code ═══════ */
code { background:var(--code-bg); color:var(--accent); padding:2px 7px; border-radius:5px; font-family:'JetBrains Mono',monospace; font-size:0.82em; border:1px solid var(--code-border); }

/* Responsive */
@media (max-width: 640px) {
    .dev-hero { flex-direction:column; text-align:center; }
    .dev-avatar { width:90px; height:90px; font-size:2.2rem; }
    .dev-tags { justify-content:center; }
    .m-tab { padding:10px 14px; font-size:0.78rem; }
}
</style>
</head>
<body>
<div class="reading-progress" id="readingProgress"></div>
<div class="m-overlay" id="sidebarOverlay"></div>

<aside class="m-sidebar">
    <div class="m-brand"><i class="fa-solid fa-shield-halved"></i><span>IDCMS</span></div>
    <div class="m-brand-sub">System Documentation</div>
    <div class="m-search"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="sidebarSearch" placeholder="Search sections..."></div>
    <nav class="m-nav">
        <a href="index.php"><i class="fa-solid fa-house"></i><span>Manual Home</span></a>
        <div class="m-section-label">Getting Started</div>
        <a href="01-getting-started.php"><i class="fa-solid fa-rocket"></i><span>Getting Started</span></a>
        <div class="m-section-label">Dashboards</div>
        <a href="10-dashboards.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics Dashboards</span></a>
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
        <div class="m-section-label">Developer</div>
        <a href="12-system-details.php" class="active"><i class="fa-solid fa-circle-info"></i><span>System &amp; Developer Details</span></a>
        <a href="../dashboard/dashboard.php" class="m-back"><i class="fa-solid fa-arrow-left"></i><span>Back to Dashboard</span></a>
    </nav>
</aside>

<main class="m-main">
    <div class="m-topbar">
        <nav class="m-breadcrumbs">
            <a href="../dashboard/dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="index.php">Docs</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span class="current">System &amp; Developer Details</span>
        </nav>
        <div class="m-actions">
            <button class="m-icon-btn" onclick="toggleTheme()" type="button" data-tooltip="Theme (T)"><i class="fa-solid fa-moon" id="theme-icon"></i></button>
            <button class="m-icon-btn" onclick="printManual()" type="button" data-tooltip="Print (P)"><i class="fa-solid fa-print"></i></button>
        </div>
    </div>

    <div class="m-hero" style="background:linear-gradient(135deg, #0F172A 0%, #1E3A8A 35%, #4318FF 70%, #A855F7 100%);">
        <span class="m-hero-badge"><i class="fa-solid fa-layer-group"></i> Complete Documentation</span>
        <h1>System &amp; Developer Details</h1>
        <p>Developer information, version history, and the full set of features that power the IDCMS platform.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> 12 min read</span>
            <span><i class="fa-solid fa-layer-group"></i> 3 Sections</span>
            <span><i class="fa-solid fa-tag"></i> v2.0 Release</span>
        </div>
    </div>

    <div class="m-tabs">
        <button class="m-tab active" data-tab="tab-developer"><i class="fa-solid fa-code"></i> Developer Details</button>
        <button class="m-tab" data-tab="tab-version"><i class="fa-solid fa-code-branch"></i> Version Details</button>
        <button class="m-tab" data-tab="tab-features"><i class="fa-solid fa-star"></i> System Features</button>
    </div>

    <!-- ═══════════════════ TAB 1: DEVELOPER DETAILS ═══════════════════ -->
    <div class="m-tab-panel active" id="tab-developer">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-user-tie"></i><span>Developer Profile</span></div>

            <div class="dev-hero">
                <div class="dev-avatar"><i class="fa-solid fa-user-tie"></i></div>
                <div class="dev-info">
                    <h2>Kasun Perera</h2>
                    <div class="dev-role"><i class="fa-solid fa-location-dot"></i> Senior Full-Stack Developer — IT Division</div>
                    <p class="dev-desc">
                        Lead developer of the IDCMS system. He designed the complete backend architecture, database schema, and the Sinhala-aware PDF rendering technology of this system, which is built on the LAMP stack.
                    </p>
                    <div class="dev-tags">
                        <span><i class="fa-solid fa-code"></i> Full-Stack PHP</span>
                        <span><i class="fa-solid fa-database"></i> MySQL Design</span>
                        <span><i class="fa-solid fa-shield-halved"></i> Security</span>
                        <span><i class="fa-solid fa-file-pdf"></i> PDF Engineering</span>
                        <span><i class="fa-solid fa-mobile-screen"></i> Responsive UI</span>
                    </div>
                </div>
            </div>

            <div class="dev-contact">
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-envelope"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Email</div>
                        <div class="dci-value">kasun.perera@example.com</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-phone"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Phone</div>
                        <div class="dci-value">+94 XX XXX XXXX</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Location</div>
                        <div class="dci-value">Colombo, Sri Lanka</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Department</div>
                        <div class="dci-value">Information Technology</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-code"></i><span>Languages &amp; Frameworks Used</span></div>
            <div class="stack-grid">
                <div class="stack-card">
                    <div class="lang-icon lc-php"><i class="fa-brands fa-php"></i></div>
                    <div class="lang-name">PHP</div>
                    <div class="lang-role">Backend language — server-side scripting</div>
                    <span class="lang-version">v7.4+ / 8.x</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-mysql"><i class="fa-solid fa-database"></i></div>
                    <div class="lang-name">MySQL</div>
                    <div class="lang-role">Relational database engine</div>
                    <span class="lang-version">5.7+ / MariaDB 10+</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-html"><i class="fa-brands fa-html5"></i></div>
                    <div class="lang-name">HTML5</div>
                    <div class="lang-role">Markup language</div>
                    <span class="lang-version">HTML5 Standard</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-css"><i class="fa-brands fa-css3-alt"></i></div>
                    <div class="lang-name">CSS3</div>
                    <div class="lang-role">Styling, Grid, Flexbox, Variables</div>
                    <span class="lang-version">CSS3</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-js"><i class="fa-brands fa-js"></i></div>
                    <div class="lang-name">JavaScript</div>
                    <div class="lang-role">Client-side interactivity</div>
                    <span class="lang-version">ES6+</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-bootstrap"><i class="fa-brands fa-bootstrap"></i></div>
                    <div class="lang-name">Bootstrap</div>
                    <div class="lang-role">CSS framework (responsive layout)</div>
                    <span class="lang-version">v5.x</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-jq">$</div>
                    <div class="lang-name">jQuery</div>
                    <div class="lang-role">DOM &amp; AJAX library</div>
                    <span class="lang-version">v3.6</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-fa"><i class="fa-solid fa-icons"></i></div>
                    <div class="lang-name">Font Awesome</div>
                    <div class="lang-role">Icon library</div>
                    <span class="lang-version">v6</span>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-book-bookmark"></i><span>Libraries &amp; Packages</span></div>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead><tr><th>Library</th><th>Type</th><th>Purpose</th><th>Version</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Chart.js</strong></td><td>Frontend JS</td><td>Dashboard charts (Doughnut, Bar, Line)</td><td><code>v4</code></td></tr>
                        <tr><td><strong>SheetJS (XLSX)</strong></td><td>Frontend JS</td><td>Excel export (xlsx, xls, csv)</td><td><code>0.18.x</code></td></tr>
                        <tr><td><strong>jsPDF</strong></td><td>Frontend JS</td><td>Client-side PDF generation</td><td><code>2.x</code></td></tr>
                        <tr><td><strong>jsPDF autoTable</strong></td><td>jsPDF Plugin</td><td>PDF table rendering</td><td><code>3.x</code></td></tr>
                        <tr><td><strong>html2canvas</strong></td><td>Frontend JS</td><td>Rasterize DOM (Sinhala-safe PDF)</td><td><code>1.4.x</code></td></tr>
                        <tr><td><strong>DataTables</strong></td><td>jQuery Plugin</td><td>Sortable, searchable tables</td><td><code>1.13</code></td></tr>
                        <tr><td><strong>TCPDF</strong></td><td>PHP (Composer)</td><td>PDF generation library</td><td><code>^6.6</code></td></tr>
                        <tr><td><strong>FPDI</strong></td><td>PHP (Composer)</td><td>Import/merge existing PDFs</td><td><code>^2.6</code></td></tr>
                        <tr><td><strong>Google Fonts</strong></td><td>CDN</td><td>Noto Sans Sinhala, JetBrains Mono</td><td>Latest</td></tr>
                        <tr><td><strong>Headless Chrome</strong></td><td>External CLI</td><td>HTML → PDF rendering</td><td><code>Chrome 90+</code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-toolbox"></i><span>Development Tools &amp; Environment</span></div>
            <div class="stack-grid">
                <div class="stack-card">
                    <div class="lang-icon lc-apache"><i class="fa-solid fa-server"></i></div>
                    <div class="lang-name">Apache / XAMPP</div>
                    <div class="lang-role">Local development server bundle</div>
                    <span class="lang-version">Apache 2.4+</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-composer">C</div>
                    <div class="lang-name">Composer</div>
                    <div class="lang-role">PHP dependency manager</div>
                    <span class="lang-version">v2.x</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-git"><i class="fa-brands fa-git-alt"></i></div>
                    <div class="lang-name">Git</div>
                    <div class="lang-role">Version control</div>
                    <span class="lang-version">v2.40+</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon lc-chrome"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                    <div class="lang-name">Chrome DevTools</div>
                    <div class="lang-role">Debugging &amp; profiling</div>
                    <span class="lang-version">Built-in</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon" style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);"><i class="fa-solid fa-code"></i></div>
                    <div class="lang-name">VS Code</div>
                    <div class="lang-role">Primary IDE</div>
                    <span class="lang-version">Latest</span>
                </div>
                <div class="stack-card">
                    <div class="lang-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706);"><i class="fa-solid fa-palette"></i></div>
                    <div class="lang-name">Figma / Photopea</div>
                    <div class="lang-role">UI/UX design &amp; asset creation</div>
                    <span class="lang-version">—</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════ TAB 2: VERSION DETAILS ═══════════════════ -->
    <div class="m-tab-panel" id="tab-version">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-tag"></i><span>Current Version Information</span></div>
            <div class="stat-row">
                <div class="stat-box"><div class="sb-num">v2.0</div><div class="sb-lbl">Current Version</div></div>
                <div class="stat-box"><div class="sb-num">2024</div><div class="sb-lbl">First Release</div></div>
                <div class="stat-box"><div class="sb-num">Stable</div><div class="sb-lbl">Release Channel</div></div>
                <div class="stat-box"><div class="sb-num">4</div><div class="sb-lbl">Total Releases</div></div>
            </div>

            <div class="dev-contact">
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-code-branch"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Project Version</div>
                        <div class="dci-value">IDCMS v2.0</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">First Release</div>
                        <div class="dci-value">v1.0 — 2024</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-tag"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Current Release</div>
                        <div class="dci-value">v2.0 — Stable</div>
                    </div>
                </div>
                <div class="dev-contact-item">
                    <div class="dci-icon"><i class="fa-solid fa-server"></i></div>
                    <div class="dci-body">
                        <div class="dci-label">Platform Requirements</div>
                        <div class="dci-value">PHP 7.4+ / MySQL 5.7+ / Chrome 90+</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-timeline"></i><span>Version History</span></div>
            <div class="timeline">
                <div class="tl-item">
                    <div class="tl-date">v2.0 — Current</div>
                    <div class="tl-title">Major Release — Architecture Overhaul</div>
                    <div class="tl-desc">
                        <ul>
                            <li>Complete PDF dossier generation with Sinhala support</li>
                            <li>Chart.js dashboard analytics</li>
                            <li>FPDI-based PDF merging with watermark</li>
                            <li>Dark/Light theme persistence</li>
                            <li>Full user manual (11+ pages)</li>
                        </ul>
                    </div>
                </div>
                <div class="tl-item">
                    <div class="tl-date">v1.5</div>
                    <div class="tl-title">Linkage &amp; Dossier System</div>
                    <div class="tl-desc">Linked letters detection, Excel export, and comprehensive chain-of-records visualization.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-date">v1.2</div>
                    <div class="tl-title">Informer Module + Analytics</div>
                    <div class="tl-desc">Informer management and an analytics dashboard for informer-linked letters.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-date">v1.0 — Initial</div>
                    <div class="tl-title">First Stable Release</div>
                    <div class="tl-desc">Internal Letters, Outward Letters, Task Letters, and a basic Dashboard.</div>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-circle-info"></i><span>License &amp; Usage</span></div>
            <p style="font-size:0.88rem; color:var(--muted); line-height:1.65; margin:0;">
                IDCMS is developed strictly for internal use. Redistribution, modification, or use of the source code outside the intended organization is not permitted.
            </p>
        </div>
    </div>

    <!-- ═══════════════════ TAB 3: SYSTEM FEATURES ═══════════════════ -->
    <div class="m-tab-panel" id="tab-features">
        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-star"></i><span>Core System Features</span></div>
            <p>IDCMS combines document management, movement tracking, tasking, and archiving into one unified platform. Below are the key features offered by the system.</p>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="fc-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                    <h5>Internal Letters</h5>
                    <p>Register internal letters, monitor movements, and maintain a complete audit history for every document.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
                    <h5>Outward Letters</h5>
                    <p>Draft outward letters, route for approval, and log every dispatch with full traceability.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon orange"><i class="fa-solid fa-list-check"></i></div>
                    <h5>Task Letters</h5>
                    <p>Assign tasks to multiple units, collect replies, and automatically track completion status.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon red"><i class="fa-solid fa-box-archive"></i></div>
                    <h5>Final Disposal</h5>
                    <p>Manage the final disposal workflow with approvals and hand-off to the record room.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon green"><i class="fa-solid fa-warehouse"></i></div>
                    <h5>Record Room</h5>
                    <p>Register physical files, assign shelf locations, and track file issuances and returns.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon purple"><i class="fa-solid fa-user-secret"></i></div>
                    <h5>Informer Management</h5>
                    <p>Maintain informer profiles, their linked letters, and detailed statistical reports.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon cyan"><i class="fa-solid fa-diagram-project"></i></div>
                    <h5>Linkage Reports</h5>
                    <p>Automatically discover linked letter chains and export them as a single dossier.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon"><i class="fa-solid fa-chart-pie"></i></div>
                    <h5>Analytics Dashboards</h5>
                    <p>Live charts and filterable statistics powered by Chart.js for informed decision-making.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon blue"><i class="fa-solid fa-file-pdf"></i></div>
                    <h5>PDF Dossier</h5>
                    <p>Generate Sinhala-aware PDFs with watermark, bundling the entire letter chain together.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon green"><i class="fa-solid fa-file-excel"></i></div>
                    <h5>Excel Export</h5>
                    <p>Download reports in Excel format using the SheetJS library.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon orange"><i class="fa-solid fa-key"></i></div>
                    <h5>Permission System</h5>
                    <p>Fine-grained control — every action is gated by its own permission code.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon purple"><i class="fa-solid fa-moon"></i></div>
                    <h5>Dark / Light Theme</h5>
                    <p>Eye-friendly dark mode and light mode, persisted via localStorage.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon cyan"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <h5>Real-time Search</h5>
                    <p>AJAX-powered search delivers results as you type from the dashboard.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
                    <h5>Session &amp; Security</h5>
                    <p>Session-based authentication, CSRF tokens, and SQL injection protection throughout.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon green"><i class="fa-solid fa-print"></i></div>
                    <h5>Print-Friendly Views</h5>
                    <p>Every page offers print-friendly CSS for clean physical output.</p>
                </div>
                <div class="feature-card">
                    <div class="fc-icon red"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <h5>Full Audit Trail</h5>
                    <p>Every action — who did what and when — is recorded for transparency.</p>
                </div>
            </div>
        </div>

        <div class="m-card">
            <div class="m-card-title"><i class="fa-solid fa-chart-simple"></i><span>System Impact</span></div>
            <div class="stat-row">
                <div class="stat-box"><div class="sb-num">↓ 75%</div><div class="sb-lbl">Letter Search Time</div></div>
                <div class="stat-box"><div class="sb-num">↑ 90%</div><div class="sb-lbl">Report Accuracy</div></div>
                <div class="stat-box"><div class="sb-num">↑ 60%</div><div class="sb-lbl">Task Efficiency</div></div>
                <div class="stat-box"><div class="sb-num">100%</div><div class="sb-lbl">Audit Trail</div></div>
            </div>
        </div>
    </div>

    <button class="m-mark-read" id="markRead" data-page="12-system-details" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <a href="10-dashboards.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Analytics Dashboards</span></a>
        <a href="index.php" class="next"><span class="lbl">Next →</span><span class="ttl">Manual Home</span></a>
    </div>
</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
<script src="assets/manual.js"></script>
<script>
/* ═══════════ Tab System ═══════════ */
(function() {
    const tabs = document.querySelectorAll('.m-tab');
    const panels = document.querySelectorAll('.m-tab-panel');

    function activateTab(targetId, pushHistory) {
        tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === targetId));
        panels.forEach(p => p.classList.toggle('active', p.id === targetId));
        if (pushHistory !== false) history.replaceState(null, '', '#' + targetId);
        const tabsBar = document.querySelector('.m-tabs');
        if (tabsBar) {
            const y = tabsBar.getBoundingClientRect().top + window.pageYOffset - 80;
            window.scrollTo({ top: y, behavior: 'smooth' });
        }
        const activeTab = document.querySelector('.m-tab.active');
        if (activeTab) activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    tabs.forEach(tab => tab.addEventListener('click', () => activateTab(tab.dataset.tab)));

    if (location.hash) {
        const targetId = location.hash.slice(1);
        if (document.getElementById(targetId)) activateTab(targetId, false);
    }

    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
        const arr = Array.from(tabs);
        const idx = arr.findIndex(t => t.classList.contains('active'));
        if (e.key === 'ArrowRight' && idx < arr.length - 1) { e.preventDefault(); activateTab(arr[idx + 1].dataset.tab); }
        else if (e.key === 'ArrowLeft' && idx > 0) { e.preventDefault(); activateTab(arr[idx - 1].dataset.tab); }
    });

    window.addEventListener('load', () => {
        const icon = document.getElementById('theme-icon');
        if (icon) {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            icon.className = isDark ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }
    });
})();
</script>
</body>
</html>