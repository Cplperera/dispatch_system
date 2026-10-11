<?php
/**
 * User Manual — Introduction
 * Path: dispatch-system/letters/userguide/sections/introduction.php
 */
require_once '../../../includes/auth.php';
require_once '../../../config/db.php';
checkSessionTimeout();
if (!isset($_SESSION['user_id'])) die('Session Invalid');

// ═══════════════════════════════════════════════════════════
// PAGE METADATA
// ═══════════════════════════════════════════════════════════
$page = [
    'slug'     => 'introduction',
    'icon'     => 'fa-circle-info',
    'title'    => 'Introduction',
    'title_si' => 'හැඳින්වීම',
    'desc'     => 'IDCMS පද්ධතියේ සමස්ත දසුන සහ අරමුණ',
    'diff'     => 'basic',
    'time'     => '4 min',
    'group'    => 'Getting Started',
];

// Navigation (prev/next) — අවශ්‍ය පමණයි
$prev = null;
$next = ['slug' => 'login', 'title' => 'Login & Session'];
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page['title']) ?> | IDCMS Manual</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link href="../../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../../assets/css/all.min.css" rel="stylesheet">
<link href="../assets/manual.css" rel="stylesheet">
</head>
<body>

<div class="reading-progress" id="readingProgress"></div>
<div class="m-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="m-sidebar">
    <div class="m-brand">
        <i class="fa-solid fa-shield-halved"></i>
        <span>IDCMS</span>
    </div>
    <div class="m-brand-sub">User Manual</div>

    <div class="m-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="sidebarSearch" placeholder="Search sections...">
    </div>

    <nav class="m-nav">
        <a href="../index.php"><i class="fa-solid fa-house"></i><span>Manual Home</span></a>

        <div class="m-section-label">Getting Started</div>
        <a href="introduction.php" class="active"><i class="fa-solid fa-circle-info"></i><span>Introduction</span></a>
        <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i><span>Login &amp; Session</span></a>
        <a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i><span>Dashboard Overview</span></a>

        <div class="m-section-label">Dispatching</div>
        <a href="add-internal-letter.php"><i class="fa-solid fa-envelope-open-text"></i><span>Add Internal Letter</span></a>
        <a href="view-internal.php"><i class="fa-solid fa-list"></i><span>View Internal Registry</span></a>
        <a href="forward-letter.php"><i class="fa-solid fa-share"></i><span>Forward a Letter</span></a>
        <a href="add-outward.php"><i class="fa-solid fa-share-from-square"></i><span>Add Outward Letter</span></a>
        <a href="task-letters.php"><i class="fa-solid fa-list-check"></i><span>Task Letters</span></a>

        <div class="m-section-label">Records &amp; Disposal</div>
        <a href="final-disposal.php"><i class="fa-solid fa-box-archive"></i><span>Final Disposal</span></a>
        <a href="record-room.php"><i class="fa-solid fa-warehouse"></i><span>Record Room</span></a>
        <a href="linkage-report.php"><i class="fa-solid fa-diagram-project"></i><span>Linkage Report</span></a>

        <div class="m-section-label">Administration</div>
        <a href="informers.php"><i class="fa-solid fa-user-secret"></i><span>Informers</span></a>
        <a href="user-management.php"><i class="fa-solid fa-users-gear"></i><span>User Management</span></a>
        <a href="permissions.php"><i class="fa-solid fa-key"></i><span>Permissions</span></a>
        <a href="desks.php"><i class="fa-solid fa-sitemap"></i><span>Desk Control</span></a>

        <div class="m-section-label">Help</div>
        <a href="troubleshooting.php"><i class="fa-solid fa-life-ring"></i><span>Troubleshooting</span></a>
        <a href="glossary.php"><i class="fa-solid fa-book"></i><span>Glossary</span></a>

        <a href="../../../dashboard/dashboard.php" class="m-back">
            <i class="fa-solid fa-arrow-left"></i><span>Back to Dashboard</span>
        </a>
    </nav>
</aside>

<!-- ═══════════════ MAIN ═══════════════ -->
<main class="m-main">

    <div class="m-topbar">
        <nav class="m-breadcrumbs">
            <a href="../../../dashboard/dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <i class="fa-solid fa-chevron-right"></i>
            <a href="../index.php">Manual</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span class="current"><?= htmlspecialchars($page['title']) ?></span>
        </nav>
        <div class="m-actions">
            <button class="m-icon-btn" onclick="toggleTheme()" type="button" data-tooltip="Theme (T)">
                <i class="fa-solid fa-moon" id="theme-icon"></i>
            </button>
            <button class="m-icon-btn" onclick="printManual()" type="button" data-tooltip="Print (P)">
                <i class="fa-solid fa-print"></i>
            </button>
        </div>
    </div>

    <!-- HERO -->
    <div class="m-hero">
        <span class="m-hero-badge">
            <i class="fa-solid <?= $page['icon'] ?>"></i> <?= htmlspecialchars($page['group']) ?>
        </span>
        <h1><?= htmlspecialchars($page['title']) ?></h1>
        <p><?= htmlspecialchars($page['desc']) ?></p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> <?= $page['time'] ?> read</span>
            <span><i class="fa-solid fa-signal"></i> <?= strtoupper($page['diff']) ?></span>
            <span><i class="fa-solid fa-language"></i> <?= htmlspecialchars($page['title_si']) ?></span>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         CONTENT — මෙතනට page content එක ලියන්න
         ═══════════════════════════════════════════════════════ -->

    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-bullseye"></i>
            <span>IDCMS යනු කුමක්ද?</span>
        </div>
        <p>
            <strong>IDCMS (Integrated Document &amp; Communication Management System)</strong> යනු ආයතනික ලිපි සහ සන්නිවේදන කටයුතු කළමනාකරණය සඳහා නිර්මාණය කරන ලද පද්ධතියකි. මෙය ලිපි ලියාපදිංචි කිරීම, යොමු කිරීම, කාර්ය පැවරීම, ගොනු කිරීම සහ අවසන් බැහැර කිරීම — සියල්ල එකම තැනකින් කළමනාකරණය කිරීමට ඉඩ සලසයි.
        </p>
    </div>

    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-star"></i>
            <span>ප්‍රධාන අංග — Main Modules</span>
        </div>
        <div class="m-table-wrap">
            <table class="m-table">
                <thead>
                    <tr><th>Module</th><th>අරමුණ</th><th>විස්තරය</th></tr>
                </thead>
                <tbody>
                    <tr><td><strong>Internal Letters</strong></td><td>අභ්‍යන්තර ලිපි</td><td>ආයතනය තුළ ලිපි ලියාපදිංචි කිරීම සහ යොමු කිරීම</td></tr>
                    <tr><td><strong>Outward Letters</strong></td><td>බාහිර ලිපි</td><td>බාහිර ආයතන වෙත ලිපි යැවීම</td></tr>
                    <tr><td><strong>Task Letters</strong></td><td>කාර්ය ලිපි</td><td>රෙජිමේන්තු/අංශ වලට කාර්ය පැවරීම</td></tr>
                    <tr><td><strong>Informers</strong></td><td>තොරතුරු දෙන්නන්</td><td>තොරතුරු දෙන්නන්ගේ විස්තර කළමනාකරණය</td></tr>
                    <tr><td><strong>Record Room</strong></td><td>වාර්තා කාමරය</td><td>ගොනු සෑදීම සහ නිකුත් කිරීම</td></tr>
                    <tr><td><strong>Final Disposal</strong></td><td>අවසන් බැහැර කිරීම</td><td>ලිපි අවසන් කිරීම සහ සංරක්ෂණය</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="m-card">
        <div class="m-card-title">
            <i class="fa-solid fa-users"></i>
            <span>පරිශීලක භූමිකා — User Roles</span>
        </div>
        <ul class="m-steps">
            <li><strong>Administrator</strong> — පද්ධතියේ සියලුම අංශ වලට ප්‍රවේශය සහ පරිපාලන අවසර</li>
            <li><strong>Officer</strong> — ලිපි ලියාපදිංචි කිරීම, යොමු කිරීම සහ කාර්ය පැවරීම</li>
            <li><strong>Desk Officer</strong> — අදාළ ඩෙස්කයට ලැබුණු ලිපි කළමනාකරණය</li>
            <li><strong>Viewer</strong> — ලිපි බැලීම සහ වාර්තා ලබා ගැනීම පමණයි</li>
        </ul>

        <div class="m-callout info">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Note</strong>
                <p>ඔබට ලැබෙන අවසර පැවරෙන්නේ ඔබේ භූමිකාව සහ <code>permission_code</code> අනුවයි. යම් අංශයක් නොපෙනේ නම්, ඔබට එයට අවසර නැහැ.</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         FOOTER: Mark as read + Prev/Next
         ═══════════════════════════════════════════════════════ -->

    <button class="m-mark-read" id="markRead" data-page="<?= $page['slug'] ?>" type="button">
        <i class="fa-regular fa-circle-check"></i> Mark as Read
    </button>

    <div class="m-page-nav">
        <?php if ($prev): ?>
            <a href="<?= $prev['slug'] ?>.php" class="prev">
                <span class="lbl">← Previous</span>
                <span class="ttl"><?= htmlspecialchars($prev['title']) ?></span>
            </a>
        <?php else: ?><span></span><?php endif; ?>

        <?php if ($next): ?>
            <a href="<?= $next['slug'] ?>.php" class="next">
                <span class="lbl">Next →</span>
                <span class="ttl"><?= htmlspecialchars($next['title']) ?></span>
            </a>
        <?php endif; ?>
    </div>

</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button">
    <i class="fa-solid fa-bars"></i>
</button>

<script src="../assets/manual.js"></script>
</body>
</html>