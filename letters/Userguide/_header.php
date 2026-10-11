<?php
/**
 * Shared <head> + sidebar + topbar + hero.
 * Requires: $page (array), $manualSections (from _config.php)
 *
 * Expected $page keys:
 *   slug, icon, title, title_si, desc, diff, time, group
 */
if (!isset($page) || !is_array($page)) {
    die('User Manual error: $page not defined.');
}

$baseUrl = '../';   // from sections/ -> userguide/
$assetBase = '../assets/';
$dashboardUrl = '../../../dashboard/dashboard.php';   // adjust if dashboard is elsewhere
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

<!-- ═══════════ SIDEBAR ═══════════ -->
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
        <a href="../index.php" class="<?= ($page['slug'] === 'home') ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i><span>Manual Home</span>
        </a>
        <?php foreach ($manualSections as $g): ?>
            <div class="m-section-label"><?= htmlspecialchars($g['group']) ?></div>
            <?php foreach ($g['items'] as $it): ?>
                <a href="<?= htmlspecialchars($it['slug']) ?>.php"
                   class="<?= $it['slug'] === $page['slug'] ? 'active' : '' ?>">
                    <i class="fa-solid <?= htmlspecialchars($it['icon']) ?>"></i>
                    <span><?= htmlspecialchars($it['title']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <a href="<?= $dashboardUrl ?>" class="m-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </nav>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="m-main">

    <div class="m-topbar">
        <nav class="m-breadcrumbs">
            <a href="<?= $dashboardUrl ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
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
            <i class="fa-solid <?= htmlspecialchars($page['icon']) ?>"></i>
            <?= htmlspecialchars($page['group'] ?? 'Manual') ?>
        </span>
        <h1><?= htmlspecialchars($page['title']) ?></h1>
        <p><?= htmlspecialchars($page['desc']) ?></p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($page['time'] ?? '5 min') ?> read</span>
            <span><i class="fa-solid fa-signal"></i> <?= strtoupper($page['diff'] ?? 'basic') ?></span>
            <?php if (!empty($page['title_si'])): ?>
                <span><i class="fa-solid fa-language"></i> <?= htmlspecialchars($page['title_si']) ?></span>
            <?php endif; ?>
        </div>
    </div>