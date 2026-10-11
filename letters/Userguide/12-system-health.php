<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();
if (!isset($_SESSION['user_id'])) die('Session Invalid');

/* ─────────────────────────────────────────────
   ONLY ADMIN can view system health
   ───────────────────────────────────────────── */
if (!hasPermission('MANAGE_USERS')) {
    die('Access Denied! Only administrators can view system health.');
}

/* ═══════════════════════════════════════════════
   HELPER FUNCTIONS — Safe execution of shell cmds
   ═══════════════════════════════════════════════ */
function safe_exec($cmd, $default = null) {
    if (!function_exists('shell_exec')) return $default;
    $disabled = explode(',', ini_get('disable_functions'));
    if (in_array('shell_exec', array_map('trim', $disabled))) return $default;
    @exec($cmd . ' 2>&1', $output, $code);
    return $code === 0 ? trim(implode("\n", $output)) : $default;
}

function format_bytes($bytes, $precision = 2) {
    if (!is_numeric($bytes) || $bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
}

function is_windows() { return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'; }

/* ═══════════════════════════════════════════════
   COLLECT METRICS — Server
   ═══════════════════════════════════════════════ */
$health = [
    'server'   => [],
    'php'      => [],
    'database' => [],
    'files'    => [],
    'app'      => [],
    'security' => [],
    'errors'   => [],
    'external' => [],
];

/* ─── Server Info ─── */
$health['server']['os']       = PHP_OS_FAMILY . ' (' . php_uname('r') . ')';
$health['server']['hostname'] = gethostname() ?: 'N/A';
$health['server']['php_sapi'] = PHP_SAPI;
$health['server']['server_sw'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$health['server']['current_time'] = date('Y-m-d H:i:s');

if (is_windows()) {
    $cpuLoad = safe_exec('wmic cpu get loadpercentage /value');
    if ($cpuLoad && preg_match('/LoadPercentage=(\d+)/', $cpuLoad, $m)) {
        $health['server']['cpu_load'] = (int)$m[1] . '%';
    } else {
        $health['server']['cpu_load'] = 'N/A';
    }

    $memInfo = safe_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value');
    if ($memInfo) {
        preg_match('/FreePhysicalMemory=(\d+)/', $memInfo, $fm);
        preg_match('/TotalVisibleMemorySize=(\d+)/', $memInfo, $tm);
        if (!empty($fm[1]) && !empty($tm[1])) {
            $free  = (int)$fm[1] * 1024;
            $total = (int)$tm[1] * 1024;
            $health['server']['ram_used']  = format_bytes($total - $free);
            $health['server']['ram_total'] = format_bytes($total);
            $health['server']['ram_pct']   = round((($total - $free) / $total) * 100, 1);
        }
    }

    $disk = safe_exec('wmic logicaldisk where "DeviceID=\'C:\'" get FreeSpace,Size /value');
    if ($disk) {
        preg_match('/FreeSpace=(\d+)/', $disk, $df);
        preg_match('/Size=(\d+)/', $disk, $ds);
        if (!empty($df[1]) && !empty($ds[1])) {
            $health['server']['disk_free']  = format_bytes((int)$df[1]);
            $health['server']['disk_total'] = format_bytes((int)$ds[1]);
            $health['server']['disk_pct']   = round(((1 - ((int)$df[1] / (int)$ds[1])) * 100), 1);
        }
    }

    $uptime = safe_exec('wmic OS get LastBootUpTime /value');
    if ($uptime && preg_match('/LastBootUpTime=(\d{14})/', $uptime, $m)) {
        $boot = strtotime(substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2) . ' ' .
                          substr($m[1], 8, 2) . ':' . substr($m[1], 10, 2) . ':' . substr($m[1], 12, 2));
        $diff = time() - $boot;
        $health['server']['uptime'] = floor($diff / 86400) . 'd ' . floor(($diff % 86400) / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
    }
} else {
    $load = @file_get_contents('/proc/loadavg');
    if ($load) {
        $parts = explode(' ', $load);
        $health['server']['load_avg'] = "{$parts[0]} / {$parts[1]} / {$parts[2]}";
    }

    $mem = @file_get_contents('/proc/meminfo');
    if ($mem) {
        preg_match('/MemTotal:\s+(\d+)/', $mem, $mt);
        preg_match('/MemAvailable:\s+(\d+)/', $mem, $ma);
        if (!empty($mt[1]) && !empty($ma[1])) {
            $total = (int)$mt[1] * 1024;
            $avail = (int)$ma[1] * 1024;
            $health['server']['ram_used']  = format_bytes($total - $avail);
            $health['server']['ram_total'] = format_bytes($total);
            $health['server']['ram_pct']   = round((($total - $avail) / $total) * 100, 1);
        }
    }

    $diskFree  = @disk_free_space('/');
    $diskTotal = @disk_total_space('/');
    if ($diskFree !== false && $diskTotal) {
        $health['server']['disk_free']  = format_bytes($diskFree);
        $health['server']['disk_total'] = format_bytes($diskTotal);
        $health['server']['disk_pct']   = round(((1 - ($diskFree / $diskTotal)) * 100), 1);
    }

    $up = @file_get_contents('/proc/uptime');
    if ($up) {
        $sec = (int)explode(' ', $up)[0];
        $health['server']['uptime'] = floor($sec / 86400) . 'd ' . floor(($sec % 86400) / 3600) . 'h ' . floor(($sec % 3600) / 60) . 'm';
    }
}

/* ─── PHP Info ─── */
$health['php']['version']        = PHP_VERSION;
$health['php']['memory_limit']   = ini_get('memory_limit');
$health['php']['memory_usage']   = format_bytes(memory_get_usage(true));
$health['php']['memory_peak']    = format_bytes(memory_get_peak_usage(true));
$health['php']['max_exec_time']  = ini_get('max_execution_time') . 's';
$health['php']['upload_max']     = ini_get('upload_max_filesize');
$health['php']['post_max']       = ini_get('post_max_size');
$health['php']['extensions']     = count(get_loaded_extensions());
$health['php']['timezone']       = date_default_timezone_get();
$health['php']['display_errors'] = ini_get('display_errors') ? 'ON' : 'OFF';
$health['php']['opcache']        = function_exists('opcache_get_status') ? 'Enabled' : 'Disabled';

/* ─── Database ─── */
try {
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    $sizeQ = $conn->query("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
        COUNT(*) AS table_count
        FROM information_schema.TABLES 
        WHERE table_schema = '" . $conn->real_escape_string($dbName) . "'");
    if ($sizeQ) {
        $sizeRow = $sizeQ->fetch_assoc();
        $health['database']['name']        = $dbName;
        $health['database']['size']        = $sizeRow['size_mb'] . ' MB';
        $health['database']['tables']      = $sizeRow['table_count'];
    }

    $health['database']['status'] = 'Connected';
    $health['database']['version'] = $conn->server_info;

    $statusQ = $conn->query("SHOW GLOBAL STATUS WHERE Variable_name IN 
        ('Uptime','Threads_connected','Queries','Slow_queries','Aborted_connects')");
    if ($statusQ) {
        while ($row = $statusQ->fetch_assoc()) {
            $key = strtolower($row['Variable_name']);
            $health['database'][$key] = $row['Value'];
        }
    }

    $counts = [];
    $tables = ['internal_letters', 'outward_letters', 'task_letters', 'users', 'informers', 'physical_file_records'];
    foreach ($tables as $t) {
        $r = $conn->query("SELECT COUNT(*) AS c FROM `$t` WHERE 1");
        if ($r) $counts[$t] = (int)$r->fetch_assoc()['c'];
    }
    $health['app']['counts'] = $counts;

    $recentQ = $conn->query("SELECT COUNT(*) AS c FROM internal_letters WHERE created_at >= NOW() - INTERVAL 1 DAY");
    if ($recentQ) $health['app']['recent_letters_24h'] = (int)$recentQ->fetch_assoc()['c'];

    $sessQ = $conn->query("SELECT COUNT(*) AS c FROM users WHERE last_login >= NOW() - INTERVAL 1 HOUR");
    if ($sessQ) $health['app']['recent_logins_1h'] = (int)$sessQ->fetch_assoc()['c'];

} catch (Exception $e) {
    $health['database']['status'] = 'Error: ' . $e->getMessage();
}

/* ─── Files ─── */
$root = realpath(__DIR__ . '/../..');
$dirs = [
    'uploads/letters'       => 'Internal Attachments',
    'uploads/outward'       => 'Outward Attachments',
    'uploads/tasks'         => 'Task Attachments',
    'uploads/task_replies'  => 'Task Replies',
];

function dir_size($path) {
    if (!is_dir($path)) return 0;
    $size = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($files as $f) {
        if ($f->isFile()) $size += $f->getSize();
    }
    return $size;
}

foreach ($dirs as $sub => $label) {
    $full = $root . DIRECTORY_SEPARATOR . $sub;
    $health['files'][$sub] = [
        'label' => $label,
        'size'  => format_bytes(dir_size($full)),
        'count' => is_dir($full) ? iterator_count(new FilesystemIterator($full)) : 0,
        'writable' => is_writable($full),
    ];
}

/* ─── Security ─── */
$health['security']['https']          = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'Yes' : 'No';
$health['security']['session_secure'] = ini_get('session.cookie_secure') ? 'Yes' : 'No';
$health['security']['httponly']       = ini_get('session.cookie_httponly') ? 'Yes' : 'No';
$health['security']['display_errors'] = ini_get('display_errors') ? 'ON ⚠️' : 'OFF ✓';

$failedQ = @$conn->query("SELECT COUNT(*) AS c FROM users WHERE failed_attempts > 0");
if ($failedQ && $failedQ !== false) {
    $health['security']['failed_logins'] = (int)$failedQ->fetch_assoc()['c'];
}

/* ─── External (Chrome for PDF) ─── */
$chromePaths = is_windows()
    ? ['C:\Program Files\Google\Chrome\Application\chrome.exe',
       'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
       'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe']
    : ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser'];

$chromeFound = null;
foreach ($chromePaths as $p) {
    if (file_exists($p)) { $chromeFound = $p; break; }
}
$health['external']['chrome'] = $chromeFound ?: 'Not Found';

$health['external']['composer'] = is_dir($root . '/vendor') ? 'Installed' : 'Missing';
$health['external']['tcpdf'] = is_dir($root . '/vendor/tecnickcom/tcpdf') ? 'Available' : 'Missing';
$health['external']['fpdi']  = is_dir($root . '/vendor/setasign/fpdi') ? 'Available' : 'Missing';

/* ─── Overall Health Score ─── */
$score = 100;
if (($health['server']['disk_pct'] ?? 0) > 90)    $score -= 25;
if (($health['server']['ram_pct'] ?? 0) > 90)     $score -= 20;
if (($health['database']['status'] ?? '') !== 'Connected') $score -= 30;
if (($health['security']['https'] ?? 'No') === 'No') $score -= 10;
if (($health['external']['chrome'] ?? '') === 'Not Found') $score -= 10;
if (($health['php']['display_errors'] ?? 'OFF') !== 'OFF') $score -= 5;
$score = max(0, $score);

if ($score >= 90)      { $statusLevel = 'excellent'; $statusText = 'Excellent'; }
elseif ($score >= 70)  { $statusLevel = 'good';      $statusText = 'Good'; }
elseif ($score >= 50)  { $statusLevel = 'warning';   $statusText = 'Warning'; }
else                   { $statusLevel = 'critical';  $statusText = 'Critical'; }
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Health | IDCMS</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<link href="assets/manual.css" rel="stylesheet">
<style>
/* ═══════ Health-specific ═══════ */
.health-hero {
    display:flex; align-items:center; justify-content:space-between;
    gap:24px; padding:28px; border-radius:18px;
    background:var(--card); border:1px solid var(--border);
    margin:16px 0; flex-wrap:wrap;
}
.score-circle {
    width:140px; height:140px; border-radius:50%;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    position:relative; flex-shrink:0;
}
.score-circle svg { position:absolute; transform:rotate(-90deg); width:140px; height:140px; }
.score-circle circle { fill:none; stroke-width:10; }
.score-circle .track { stroke:var(--border); }
.score-circle .fill { stroke-linecap:round; transition:stroke-dashoffset 1s ease; }
.score-circle .num { font-size:2.2rem; font-weight:800; line-height:1; z-index:1; }
.score-circle .lbl { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; z-index:1; margin-top:4px; }
.score-excellent .fill { stroke:#10B981; } .score-excellent .num { color:#10B981; }
.score-good      .fill { stroke:#3B82F6; } .score-good .num { color:#3B82F6; }
.score-warning   .fill { stroke:#F59E0B; } .score-warning .num { color:#F59E0B; }
.score-critical  .fill { stroke:#EF4444; } .score-critical .num { color:#EF4444; }

.health-summary { flex:1; min-width:250px; }
.health-summary h2 { font-size:1.5rem; font-weight:800; color:var(--text-strong); margin:0 0 6px; }
.health-summary p { font-size:0.9rem; color:var(--muted); margin:0 0 14px; line-height:1.55; }
.health-badge {
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 14px; border-radius:20px;
    font-size:0.78rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;
}
.health-badge.excellent { background:rgba(16,185,129,0.15); color:#10B981; }
.health-badge.good      { background:rgba(59,130,246,0.15); color:#3B82F6; }
.health-badge.warning   { background:rgba(245,158,11,0.15); color:#F59E0B; }
.health-badge.critical  { background:rgba(239,68,68,0.15); color:#EF4444; }

.metric-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
    gap:16px; margin:16px 0;
}
.metric-card {
    background:var(--card); border:1px solid var(--border);
    border-radius:14px; padding:18px; transition:0.2s;
}
.metric-card:hover { transform:translateY(-3px); border-color:var(--accent); }
.metric-card .mc-head {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:12px;
}
.metric-card .mc-title {
    display:flex; align-items:center; gap:8px;
    font-size:0.85rem; font-weight:800; color:var(--text-strong);
}
.metric-card .mc-icon {
    width:32px; height:32px; border-radius:9px;
    background:var(--accent-soft); color:var(--accent);
    display:flex; align-items:center; justify-content:center;
    font-size:0.9rem;
}
.metric-card .mc-value {
    font-size:1.6rem; font-weight:800; color:var(--text-strong);
    line-height:1; margin-bottom:6px;
    font-family:'JetBrains Mono',monospace;
}
.metric-card .mc-sub {
    font-size:0.75rem; color:var(--muted); line-height:1.4;
}

.mini-bar {
    height:6px; background:var(--border);
    border-radius:3px; overflow:hidden; margin-top:10px;
}
.mini-bar .fill {
    height:100%; border-radius:3px;
    transition:width 0.8s ease;
}
.bar-green { background:linear-gradient(90deg,#10B981,#059669); }
.bar-yellow { background:linear-gradient(90deg,#F59E0B,#D97706); }
.bar-red { background:linear-gradient(90deg,#EF4444,#B91C1C); }
.bar-blue { background:linear-gradient(90deg,#3B82F6,#1D4ED8); }
.bar-purple { background:linear-gradient(90deg,#8B5CF6,#6D28D9); }

.health-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
.health-table th {
    text-align:left; padding:10px 12px;
    background:var(--bg); color:var(--muted);
    font-size:0.72rem; text-transform:uppercase;
    letter-spacing:0.5px; font-weight:800;
    border-bottom:2px solid var(--border);
}
.health-table td {
    padding:10px 12px; border-bottom:1px solid var(--border);
    color:var(--text);
}
.health-table td:first-child { font-weight:700; color:var(--text-strong); }
.health-table tr:hover { background:var(--bg); }

.status-dot {
    display:inline-flex; align-items:center; gap:6px;
    padding:3px 10px; border-radius:12px;
    font-size:0.72rem; font-weight:800;
}
.status-dot.ok       { background:rgba(16,185,129,0.15); color:#10B981; }
.status-dot.warn     { background:rgba(245,158,11,0.15); color:#F59E0B; }
.status-dot.error    { background:rgba(239,68,68,0.15); color:#EF4444; }
.status-dot::before {
    content:''; width:6px; height:6px; border-radius:50%;
    background:currentColor;
}

.refresh-bar {
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; padding:12px 16px; border-radius:12px;
    background:var(--card); border:1px solid var(--border);
    margin:16px 0; flex-wrap:wrap;
}
.refresh-bar .rb-info {
    display:flex; align-items:center; gap:10px;
    font-size:0.82rem; color:var(--muted);
}
.refresh-bar .rb-info i { color:var(--accent); }
.btn-refresh {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 18px; border-radius:10px; border:none;
    background:var(--accent); color:#fff;
    font-weight:800; font-size:0.82rem;
    cursor:pointer; transition:0.2s; font-family:inherit;
}
.btn-refresh:hover { opacity:0.9; transform:translateY(-1px); }
.btn-refresh.spinning i { animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

.sec-title {
    font-size:1rem; font-weight:800; color:var(--text-strong);
    margin:24px 0 14px; padding-bottom:8px;
    border-bottom:2px solid var(--accent-soft);
    display:flex; align-items:center; gap:10px;
}
.sec-title i { color:var(--accent); font-size:0.9rem; }
</style>
</head>
<body>
<div class="reading-progress" id="readingProgress"></div>
<div class="m-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════════════════════════════
     SIDEBAR — matches the layout from the reference image
     ═══════════════════════════════════════════════════════ -->
<aside class="m-sidebar">
    <div class="m-brand"><i class="fa-solid fa-shield-halved"></i><span>IDCMS</span></div>
    <div class="m-brand-sub">User Manual</div>
    <div class="m-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="sidebarSearch" placeholder="Search sections...">
    </div>
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
        <a href="09-administration.php"><i class="fa-solid fa-user-shield"></i><span>Administration</span></a>

        <div class="m-section-label">Analytics</div>
        <a href="10-dashboards.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics Dashboards</span></a>

        <div class="m-section-label">Developer</div>
        <a href="11-developer-details.php"><i class="fa-solid fa-code"></i><span>Developer Details</span></a>
        <a href="12-system-health.php" class="active"><i class="fa-solid fa-heart-pulse"></i><span>System Health</span></a>

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
            <span class="current">System Health</span>
        </nav>
    </div>

    <div class="m-hero" style="background:linear-gradient(135deg, #064E3B 0%, #059669 50%, #10B981 100%);">
        <span class="m-hero-badge"><i class="fa-solid fa-heart-pulse"></i> Real-time Monitoring</span>
        <h1>System Health Monitor</h1>
        <p>IDCMS පද්ධතියේ server, database, files, security සහ external services වල තත්ත්වය සජීවීව නිරීක්ෂණය කරන්න.</p>
        <div class="m-hero-meta">
            <span><i class="fa-regular fa-clock"></i> <?= date('Y-m-d H:i:s') ?></span>
            <span><i class="fa-solid fa-server"></i> <?= htmlspecialchars($health['server']['hostname']) ?></span>
            <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($health['server']['os']) ?></span>
        </div>
    </div>

    <!-- ═══════ OVERALL SCORE ═══════ -->
    <div class="health-hero">
        <div class="score-circle score-<?= $statusLevel ?>">
            <svg viewBox="0 0 140 140">
                <circle class="track" cx="70" cy="70" r="60"></circle>
                <circle class="fill" cx="70" cy="70" r="60"
                    stroke-dasharray="<?= 2 * M_PI * 60 ?>"
                    stroke-dashoffset="<?= 2 * M_PI * 60 * (1 - $score / 100) ?>"></circle>
            </svg>
            <div class="num"><?= $score ?></div>
            <div class="lbl">Score</div>
        </div>
        <div class="health-summary">
            <h2>Overall System Status</h2>
            <p>
                <?php if ($statusLevel === 'excellent'): ?>
                    සියලුම පද්ධති සාමාන්‍ය ලෙස ක්‍රියාත්මක වේ. කිසිදු ගැටලුවක් නොමැත.
                <?php elseif ($statusLevel === 'good'): ?>
                    පද්ධතිය හොඳින් ක්‍රියාත්මක වේ. සුළු සුදුසුම්කිරීම් කිහිපයක් සිදු කළ හැක.
                <?php elseif ($statusLevel === 'warning'): ?>
                    අවධානය අවශ්‍ය ගැටලු කිහිපයක් ඇත. පහත metrics පරීක්ෂා කරන්න.
                <?php else: ?>
                    බරපතල ගැටලු හඳුනාගෙන ඇත! වහාම ක්‍රියාමාර්ග ගන්න.
                <?php endif; ?>
            </p>
            <span class="health-badge <?= $statusLevel ?>">
                <i class="fa-solid fa-circle"></i> <?= $statusText ?>
            </span>
        </div>
    </div>

    <div class="refresh-bar">
        <div class="rb-info">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>අවසන් refresh: <strong id="lastRefresh"><?= date('H:i:s') ?></strong></span>
            <span style="color:var(--muted);">•</span>
            <span>Auto-refresh: <strong>30s</strong></span>
        </div>
        <button class="btn-refresh" id="btnRefresh" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i> Refresh Now
        </button>
    </div>

    <!-- ═══════ SERVER METRICS ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-server"></i> Server Metrics</div>
    <div class="metric-grid">

        <?php if (!empty($health['server']['cpu_load'])): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-microchip"></i></div> CPU Load</div>
            </div>
            <div class="mc-value"><?= htmlspecialchars($health['server']['cpu_load']) ?></div>
            <div class="mc-sub">වර්තමාන processor භාවිතය</div>
            <?php $cpuPct = (int)str_replace('%', '', $health['server']['cpu_load']); ?>
            <div class="mini-bar"><div class="fill <?= $cpuPct > 80 ? 'bar-red' : ($cpuPct > 50 ? 'bar-yellow' : 'bar-green') ?>" style="width:<?= $cpuPct ?>%"></div></div>
        </div>
        <?php elseif (!empty($health['server']['load_avg'])): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-microchip"></i></div> Load Average</div>
            </div>
            <div class="mc-value" style="font-size:1.1rem;"><?= htmlspecialchars($health['server']['load_avg']) ?></div>
            <div class="mc-sub">1 min / 5 min / 15 min</div>
        </div>
        <?php endif; ?>

        <?php if (!empty($health['server']['ram_pct'])): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-memory"></i></div> RAM Usage</div>
            </div>
            <div class="mc-value"><?= $health['server']['ram_pct'] ?>%</div>
            <div class="mc-sub"><?= $health['server']['ram_used'] ?> / <?= $health['server']['ram_total'] ?></div>
            <div class="mini-bar"><div class="fill <?= $health['server']['ram_pct'] > 85 ? 'bar-red' : ($health['server']['ram_pct'] > 65 ? 'bar-yellow' : 'bar-green') ?>" style="width:<?= $health['server']['ram_pct'] ?>%"></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($health['server']['disk_pct'])): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-hard-drive"></i></div> Disk Usage</div>
            </div>
            <div class="mc-value"><?= $health['server']['disk_pct'] ?>%</div>
            <div class="mc-sub">Free: <?= $health['server']['disk_free'] ?> / Total: <?= $health['server']['disk_total'] ?></div>
            <div class="mini-bar"><div class="fill <?= $health['server']['disk_pct'] > 90 ? 'bar-red' : ($health['server']['disk_pct'] > 75 ? 'bar-yellow' : 'bar-green') ?>" style="width:<?= $health['server']['disk_pct'] ?>%"></div></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($health['server']['uptime'])): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-clock"></i></div> Server Uptime</div>
            </div>
            <div class="mc-value" style="font-size:1.15rem;"><?= htmlspecialchars($health['server']['uptime']) ?></div>
            <div class="mc-sub">Server එක ධාවනය වන කාලය</div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ═══════ DATABASE ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-database"></i> Database Status</div>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-plug-circle-check"></i></div> Connection</div>
                <?php if ($health['database']['status'] === 'Connected'): ?>
                    <span class="status-dot ok">Connected</span>
                <?php else: ?>
                    <span class="status-dot error">Error</span>
                <?php endif; ?>
            </div>
            <div class="mc-value" style="font-size:1rem;"><?= htmlspecialchars($health['database']['name'] ?? 'N/A') ?></div>
            <div class="mc-sub">MySQL <?= htmlspecialchars($health['database']['version'] ?? '') ?></div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-database"></i></div> Database Size</div>
            </div>
            <div class="mc-value"><?= htmlspecialchars($health['database']['size'] ?? 'N/A') ?></div>
            <div class="mc-sub"><?= $health['database']['tables'] ?? 0 ?> tables</div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-bolt"></i></div> Total Queries</div>
            </div>
            <div class="mc-value"><?= number_format((int)($health['database']['queries'] ?? 0)) ?></div>
            <div class="mc-sub"><?= (int)($health['database']['slow_queries'] ?? 0) ?> slow queries</div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-users"></i></div> Active Connections</div>
            </div>
            <div class="mc-value"><?= (int)($health['database']['threads_connected'] ?? 0) ?></div>
            <div class="mc-sub">DB එකට සම්බන්ධ සංඛ්‍යාව</div>
        </div>
    </div>

    <!-- ═══════ PHP CONFIG ═══════ -->
    <div class="sec-title"><i class="fa-brands fa-php"></i> PHP Configuration</div>
    <div class="m-card">
        <div class="health-table-wrap">
            <table class="health-table">
                <thead>
                    <tr><th>Setting</th><th>Value</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td>PHP Version</td><td><?= $health['php']['version'] ?></td>
                        <td><?= version_compare(PHP_VERSION, '7.4', '>=') ? '<span class="status-dot ok">OK</span>' : '<span class="status-dot error">Old</span>' ?></td></tr>
                    <tr><td>Memory Limit</td><td><?= $health['php']['memory_limit'] ?></td>
                        <td><span class="status-dot ok">OK</span></td></tr>
                    <tr><td>Current Memory Usage</td><td><?= $health['php']['memory_usage'] ?></td>
                        <td><span class="status-dot ok">OK</span></td></tr>
                    <tr><td>Peak Memory</td><td><?= $health['php']['memory_peak'] ?></td>
                        <td><span class="status-dot ok">OK</span></td></tr>
                    <tr><td>Max Execution Time</td><td><?= $health['php']['max_exec_time'] ?></td>
                        <td><?= (int)ini_get('max_execution_time') >= 60 ? '<span class="status-dot ok">OK</span>' : '<span class="status-dot warn">Low</span>' ?></td></tr>
                    <tr><td>Upload Max Filesize</td><td><?= $health['php']['upload_max'] ?></td>
                        <td><?= (int)ini_get('upload_max_filesize') >= 10 ? '<span class="status-dot ok">OK</span>' : '<span class="status-dot warn">Low</span>' ?></td></tr>
                    <tr><td>Display Errors</td><td><?= $health['php']['display_errors'] ?></td>
                        <td><?= ini_get('display_errors') ? '<span class="status-dot warn">ON</span>' : '<span class="status-dot ok">OFF</span>' ?></td></tr>
                    <tr><td>OPcache</td><td><?= $health['php']['opcache'] ?></td>
                        <td><?= $health['php']['opcache'] === 'Enabled' ? '<span class="status-dot ok">ON</span>' : '<span class="status-dot warn">OFF</span>' ?></td></tr>
                    <tr><td>Loaded Extensions</td><td><?= $health['php']['extensions'] ?></td>
                        <td><span class="status-dot ok">OK</span></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════ SECURITY ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-shield-halved"></i> Security Status</div>
    <div class="m-card">
        <div class="health-table-wrap">
            <table class="health-table">
                <thead>
                    <tr><th>Check</th><th>Status</th><th>Recommendation</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>HTTPS</td>
                        <td><?= $health['security']['https'] === 'Yes' ? '<span class="status-dot ok">Enabled</span>' : '<span class="status-dot error">Disabled</span>' ?></td>
                        <td><?= $health['security']['https'] === 'Yes' ? '✓ Good' : '⚠️ Production වලට HTTPS අනිවාර්යයි' ?></td>
                    </tr>
                    <tr>
                        <td>Session Cookie Secure</td>
                        <td><?= $health['security']['session_secure'] === 'Yes' ? '<span class="status-dot ok">Yes</span>' : '<span class="status-dot warn">No</span>' ?></td>
                        <td><?= $health['security']['session_secure'] === 'Yes' ? '✓ Good' : 'session.cookie_secure = 1 කරන්න' ?></td>
                    </tr>
                    <tr>
                        <td>Session HttpOnly</td>
                        <td><?= $health['security']['httponly'] === 'Yes' ? '<span class="status-dot ok">Yes</span>' : '<span class="status-dot warn">No</span>' ?></td>
                        <td><?= $health['security']['httponly'] === 'Yes' ? '✓ Good' : 'session.cookie_httponly = 1 කරන්න' ?></td>
                    </tr>
                    <tr>
                        <td>Display Errors</td>
                        <td><?= ini_get('display_errors') ? '<span class="status-dot error">ON</span>' : '<span class="status-dot ok">OFF</span>' ?></td>
                        <td><?= ini_get('display_errors') ? 'Production වලට OFF කරන්න' : '✓ Good' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════ APPLICATION ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-chart-simple"></i> Application Records</div>
    <div class="metric-grid">
        <?php foreach ($health['app']['counts'] ?? [] as $tbl => $cnt): ?>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title">
                    <div class="mc-icon"><i class="fa-solid fa-<?= 
                        $tbl === 'internal_letters' ? 'envelope' :
                        ($tbl === 'outward_letters' ? 'paper-plane' :
                        ($tbl === 'task_letters' ? 'list-check' :
                        ($tbl === 'users' ? 'user' :
                        ($tbl === 'informers' ? 'user-secret' : 'folder')))) ?>"></i></div>
                    <?= ucwords(str_replace('_', ' ', $tbl)) ?>
                </div>
            </div>
            <div class="mc-value"><?= number_format($cnt) ?></div>
            <div class="mc-sub">සම්පූර්ණ records ගණන</div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══════ FILES ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-folder-tree"></i> File System</div>
    <div class="m-card">
        <div class="health-table-wrap">
            <table class="health-table">
                <thead>
                    <tr><th>Folder</th><th>Size</th><th>Files</th><th>Writable</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($health['files'] as $sub => $info): ?>
                    <tr>
                        <td><?= htmlspecialchars($info['label']) ?></td>
                        <td><?= $info['size'] ?></td>
                        <td><?= $info['count'] ?></td>
                        <td><?= $info['writable'] ? '<span class="status-dot ok">Yes</span>' : '<span class="status-dot error">No</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════ EXTERNAL ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-plug"></i> External Dependencies</div>
    <div class="metric-grid">
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-brands fa-chrome"></i></div> Headless Chrome</div>
                <?php if ($health['external']['chrome'] !== 'Not Found'): ?>
                    <span class="status-dot ok">Found</span>
                <?php else: ?>
                    <span class="status-dot error">Missing</span>
                <?php endif; ?>
            </div>
            <div class="mc-value" style="font-size:0.8rem; word-break:break-all;"><?= htmlspecialchars(basename($health['external']['chrome'])) ?></div>
            <div class="mc-sub">PDF generation සඳහා</div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-cube"></i></div> Composer</div>
                <?php if ($health['external']['composer'] === 'Installed'): ?>
                    <span class="status-dot ok">Installed</span>
                <?php else: ?>
                    <span class="status-dot error">Missing</span>
                <?php endif; ?>
            </div>
            <div class="mc-value" style="font-size:1rem;">vendor/</div>
            <div class="mc-sub">PHP dependencies</div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-file-pdf"></i></div> TCPDF</div>
                <?php if ($health['external']['tcpdf'] === 'Available'): ?>
                    <span class="status-dot ok">Available</span>
                <?php else: ?>
                    <span class="status-dot error">Missing</span>
                <?php endif; ?>
            </div>
            <div class="mc-value" style="font-size:1rem;">PDF Generation</div>
            <div class="mc-sub">tecnickcom/tcpdf</div>
        </div>
        <div class="metric-card">
            <div class="mc-head">
                <div class="mc-title"><div class="mc-icon"><i class="fa-solid fa-layer-group"></i></div> FPDI</div>
                <?php if ($health['external']['fpdi'] === 'Available'): ?>
                    <span class="status-dot ok">Available</span>
                <?php else: ?>
                    <span class="status-dot error">Missing</span>
                <?php endif; ?>
            </div>
            <div class="mc-value" style="font-size:1rem;">PDF Merge</div>
            <div class="mc-sub">setasign/fpdi</div>
        </div>
    </div>

    <!-- ═══════ SERVER INFO ═══════ -->
    <div class="sec-title"><i class="fa-solid fa-circle-info"></i> Server Information</div>
    <div class="m-card">
        <div class="health-table-wrap">
            <table class="health-table">
                <tbody>
                    <tr><td>Operating System</td><td><?= htmlspecialchars($health['server']['os']) ?></td></tr>
                    <tr><td>Hostname</td><td><?= htmlspecialchars($health['server']['hostname']) ?></td></tr>
                    <tr><td>PHP SAPI</td><td><?= htmlspecialchars($health['server']['php_sapi']) ?></td></tr>
                    <tr><td>Web Server</td><td><?= htmlspecialchars($health['server']['server_sw']) ?></td></tr>
                    <tr><td>Current Time</td><td><?= htmlspecialchars($health['server']['current_time']) ?></td></tr>
                    <tr><td>Timezone</td><td><?= htmlspecialchars($health['php']['timezone']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="m-callout info" style="margin-top:24px;">
        <i class="fa-solid fa-circle-info"></i>
        <div>
            <strong>Auto-Refresh</strong>
            <p>මෙම පිටුව සෑම තත්පර 30කට වරක් ස්වයංක්‍රීයව refresh වේ. අතින් refresh කිරීමට ඉහළ ඇති <strong>Refresh Now</strong> බොත්තම භාවිතා කරන්න.</p>
        </div>
    </div>

    <div class="m-page-nav">
        <a href="12-system-details.php" class="prev"><span class="lbl">← Previous</span><span class="ttl">Developer Details</span></a>
        <a href="index.php" class="next"><span class="lbl">Next →</span><span class="ttl">Manual Home</span></a>
    </div>
</main>

<button class="m-mobile-toggle" id="mobileToggle" type="button"><i class="fa-solid fa-bars"></i></button>
<script src="assets/manual.js"></script>
<script>
/* ═══════ Auto-refresh every 30s ═══════ */
let refreshTimer = setTimeout(() => location.reload(), 30000);

/* Pause auto-refresh when tab is hidden */
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearTimeout(refreshTimer);
    } else {
        refreshTimer = setTimeout(() => location.reload(), 30000);
    }
});

/* Manual refresh button spin */
document.getElementById('btnRefresh')?.addEventListener('click', function() {
    this.classList.add('spinning');
});

/* Keyboard shortcut: R = refresh */
document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'r' || e.key === 'R') {
        if (!e.ctrlKey && !e.metaKey) {
            location.reload();
        }
    }
});
</script>
</body>
</html>