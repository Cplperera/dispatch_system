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
<title>User Manual | IDCMS</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="assets/manual.css" rel="stylesheet">
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
    <div class="m-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="sidebarSearch" placeholder="Search sections...">
    </div>
    <nav class="m-nav">
        <a href="index.php" class="active"><i class="fa-solid fa-house"></i><span>Manual Home</span></a>

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
            <span class="current">User Manual</span>
        </nav>
    </div>

    <div class="m-hero">
        <span class="m-hero-badge"><i class="fa-solid fa-graduation-cap"></i> Complete Guide</span>
        <h1>IDCMS පරිශීලක අත්පොත</h1>
        <p>ලිපි ලියාපදිංචි කිරීම, බෙදාහැරීම, කාර්ය පැවරීම, ගොනු කිරීම සහ අවසන් බැහැර කිරීම — සියලුම කාර්යයන් සඳහා පියවරෙන් පියවර උපදෙස්.</p>
        <div class="m-hero-meta">
            <span><i class="fa-solid fa-file-lines"></i> 12 Sections</span>
            <span><i class="fa-solid fa-layer-group"></i> 6 Groups</span>
            <span><i class="fa-solid fa-clock"></i> Updated <?= date('d M Y') ?></span>
        </div>
    </div>

    <!-- ============================================================
         GETTING STARTED
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-rocket"></i>
            <span>Getting Started — ආරම්භ කිරීම</span>
            <span class="tag">1 Topic</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="01-getting-started.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-rocket"></i></div>
                <div>
                    <h3>Getting Started</h3>
                    <span class="m-card-si">ආරම්භ කිරීම</span>
                </div>
                <p class="m-card-desc">Introduction, login, dashboard overview, user roles සහ permissions</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 12 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         DISPATCHING
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-paper-plane"></i>
            <span>Dispatching — ලිපි බෙදාහැරීම</span>
            <span class="tag">3 Topics</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="02-internal-letters.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div>
                    <h3>Internal Letters</h3>
                    <span class="m-card-si">අභ්‍යන්තර ලිපි</span>
                </div>
                <p class="m-card-desc">Add, view, edit, forward, inbox සහ disposal routing</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 20 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="03-outward-letters.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <h3>Outward Letters</h3>
                    <span class="m-card-si">බාහිර ලිපි</span>
                </div>
                <p class="m-card-desc">Add, approve, reject, dispatch සහ receiver management</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 18 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="04-task-letters.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-list-check"></i></div>
                <div>
                    <h3>Task Letters</h3>
                    <span class="m-card-si">කාර්ය ලිපි</span>
                </div>
                <p class="m-card-desc">Assign tasks to agencies, track replies සහ status</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 15 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         RECORDS & DISPOSAL
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-box-archive"></i>
            <span>Records &amp; Disposal — වාර්තා සහ බැහැර කිරීම</span>
            <span class="tag">3 Topics</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="05-final-disposal.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-box-archive"></i></div>
                <div>
                    <h3>Final Disposal</h3>
                    <span class="m-card-si">අවසන් බැහැර කිරීම</span>
                </div>
                <p class="m-card-desc">Disposal inbox, processing, archiving workflow</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 14 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="06-record-room.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-warehouse"></i></div>
                <div>
                    <h3>Record Room</h3>
                    <span class="m-card-si">වාර්තා කාමරය</span>
                </div>
                <p class="m-card-desc">File creation, issuing, returning සහ tracking</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 12 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="07-linkage-reports.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-diagram-project"></i></div>
                <div>
                    <h3>Linkage Reports</h3>
                    <span class="m-card-si">සම්බන්ධතා වාර්තා</span>
                </div>
                <p class="m-card-desc">Document relationships සහ PDF dossier generation</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 10 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         ADMINISTRATION
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-user-shield"></i>
            <span>Administration — පරිපාලනය</span>
            <span class="tag">2 Topics</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="08-informers.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-user-secret"></i></div>
                <div>
                    <h3>Informers</h3>
                    <span class="m-card-si">මානව බුද්ධිය</span>
                </div>
                <p class="m-card-desc">Register, update සහ manage informer records</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 8 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="09-administration.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-user-shield"></i></div>
                <div>
                    <h3>Administration</h3>
                    <span class="m-card-si">පද්ධති පරිපාලනය</span>
                </div>
                <p class="m-card-desc">Users, permissions, desks සහ categories</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 16 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         ANALYTICS
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Analytics — විශ්ලේෂණ</span>
            <span class="tag">1 Topic</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="10-dashboards.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <div>
                    <h3>Analytics Dashboards</h3>
                    <span class="m-card-si">විශ්ලේෂණ පුවරු</span>
                </div>
                <p class="m-card-desc">Internal, Outward, Task, Informer analytics සහ charts</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 15 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         DEVELOPER
         ============================================================ -->
    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-code"></i>
            <span>Developer — සංවර්ධක</span>
            <span class="tag">2 Topics</span>
        </div>
        <div class="m-grid" style="margin-bottom:0;">
            <a href="11-developer-details.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-code"></i></div>
                <div>
                    <h3>Developer Details</h3>
                    <span class="m-card-si">සංවර්ධක තොරතුරු</span>
                </div>
                <p class="m-card-desc">Tech stack, architecture, database සහ libraries</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 25 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
            <a href="12-system-health.php" class="m-card-link">
                <div class="m-card-icon"><i class="fa-solid fa-heart-pulse"></i></div>
                <div>
                    <h3>System Health</h3>
                    <span class="m-card-si">පද්ධති සෞඛ්‍යය</span>
                </div>
                <p class="m-card-desc">Real-time server, database සහ application monitoring</p>
                <div class="m-card-foot">
                    <span><i class="fa-regular fa-clock"></i> 10 min read</span>
                    <span class="arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </div>
            </a>
        </div>
    </div>

    <!-- ============================================================
         INFO CALLOUT
         ============================================================ -->
    <div class="m-callout info">
        <i class="fa-solid fa-keyboard"></i>
        <div>
            <strong>Keyboard Shortcuts</strong>
            <p><kbd>/</kbd> Search &nbsp;·&nbsp; <kbd>T</kbd> Toggle theme &nbsp;·&nbsp; <kbd>P</kbd> Print &nbsp;·&nbsp; <kbd>Esc</kbd> Close sidebar</p>
        </div>
    </div>

</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
<script src="assets/manual.js"></script>
</body>
</html>