<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('MANAGE_PERMISSIONS')) {
    die('Access Denied!');
}

/* ─── Fetch all permission codes ─────────────────────── */
$permissions = [];
$result = $conn->query("SELECT * FROM permission_codes ORDER BY category, code");
if ($result) {
    while ($row = $result->fetch_assoc()) $permissions[] = $row;
}

/* ─── Stats ─────────────────────────────────────────── */
$totalCodes  = count($permissions);
$activeCount = 0;
$inactiveCount = 0;
$categories  = [];
foreach ($permissions as $p) {
    if (($p['status'] ?? '') === 'Active') $activeCount++;
    else                                   $inactiveCount++;
    $cat = $p['category'] ?: 'Uncategorized';
    if (!isset($categories[$cat])) $categories[$cat] = 0;
    $categories[$cat]++;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Permission Codes | IDCMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg-body: #F0F2F5;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;
    --accent: #4318FF;
    --accent-soft: rgba(67, 24, 255, 0.08);
    --text-main: #1B2559;
    --text-muted: #707EAE;
    --border: #DDE2EF;
    --sidebar-width: 280px;
    --placeholder-color: #A3AED0;
    --ok:#10b981; --warn:#f59e0b; --err:#ef4444;
}
[data-theme="dark"] {
    --bg-body: #0B1437;
    --sidebar-bg: #111C44;
    --card-bg: #111C44;
    --accent: #7551FF;
    --accent-soft: rgba(117, 81, 255, 0.1);
    --text-main: #FFFFFF;
    --text-muted: #A3AED0;
    --border: rgba(255, 255, 255, 0.1);
    --placeholder-color: #707EAE;
    color-scheme: dark;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh;
    font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', 'Segoe UI', system-ui, sans-serif; transition: 0.3s; }

/* ═══════════════════ SIDEBAR ═══════════════════ */
.sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border);
    padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }

.brand-box { margin-bottom: 24px; padding: 0 8px; }
.brand-name { font-weight: 800; font-size: 1.15rem; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
.brand-name i { color: var(--accent); font-size: 1.25rem; }

.nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted);
    text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
    transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left;
    cursor: pointer; gap: 10px; }
.nav-btn i:first-child { width: 18px; text-align: center; }
.nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
.nav-btn i.small { font-size: 0.7rem; margin-left: auto; opacity: 0.7; transition: transform 0.25s; }

.submenu { padding-left: 20px; display: none; }
.submenu.show { display: block; }
.sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none;
    font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
.sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
.sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

.section-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted);
    text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7; letter-spacing: 1px; }

/* ═══════════════════ MAIN ═══════════════════ */
.main { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
.page-head h2 { font-size: 1.4rem; font-weight: 800; margin: 0 0 4px; color: var(--text-main); }
.page-head p { color: var(--text-muted); font-size: 0.88rem; margin: 0 0 22px; }

.card-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 22px;
    margin-bottom: 20px; transition: background 0.25s, border-color 0.25s; }
.card-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
    color: var(--text-muted); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--accent); }

/* Buttons */
.btn-primary-c { display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: #fff;
    border: none; padding: 11px 22px; border-radius: 12px; font-weight: 800; font-size: 0.85rem;
    cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-primary-c:hover { opacity: 0.9; transform: translateY(-1px); color: #fff; }

.btn-ghost-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--text-muted);
    border: 1px solid var(--border); padding: 10px 20px; border-radius: 12px; font-weight: 700;
    font-size: 0.83rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-ghost-c:hover { border-color: var(--accent); color: var(--accent); }

.btn-danger-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--err);
    border: 1px solid var(--err); padding: 9px 16px; border-radius: 12px; font-weight: 700;
    font-size: 0.78rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-danger-c:hover { background: var(--err); color: #fff; }

/* Action icons (compact) */
.action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; padding: 0;
    border-radius: 8px; font-size: 0.75rem;
    text-decoration: none; transition: 0.2s;
    margin: 1px; line-height: 1; border: 1px solid transparent;
}
.btn-edit   { background: var(--accent-soft); color: var(--accent); }
.btn-edit:hover { background: var(--accent); color: #fff; }
.btn-del    { background: rgba(239,68,68,0.12); color: var(--err); }
.btn-del:hover { background: var(--err); color: #fff; }

/* Stats */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
.stat { background: var(--card-bg); border: 1px solid var(--border); border-radius: 14px; padding: 16px 18px; }
.stat .label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); font-weight: 800; }
.stat .value { font-size: 1.5rem; font-weight: 800; color: var(--accent); margin-top: 4px; line-height: 1; }
.stat.ok  .value { color: var(--ok); }
.stat.err .value { color: var(--err); }

/* Table */
.table { color: var(--text-main); border-color: var(--border); margin-bottom: 0; font-size: 0.85rem; }
.table thead th { border-bottom: 2px solid var(--border); color: var(--text-muted);
    text-transform: uppercase; font-size: 0.68rem; letter-spacing: 1px; padding: 12px 10px;
    font-weight: 800; background: transparent; }
.table tbody td { padding: 12px 10px; border-bottom: 1px solid var(--border);
    color: var(--text-main); vertical-align: middle; }
.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover td { background: var(--accent-soft); }

.code-mono { font-family: 'Courier New', monospace; font-weight: 700; color: var(--accent);
    background: var(--accent-soft); padding: 3px 8px; border-radius: 6px; font-size: 0.78rem; }

.badge-cat { display: inline-block; padding: 4px 10px; border-radius: 20px;
    background: var(--accent-soft); color: var(--accent); font-weight: 700;
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.3px; }

.badge-status { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px;
    font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px; }
.badge-status .dot { width: 6px; height: 6px; border-radius: 50%; }
.badge-active   { background: rgba(16,185,129,0.15); color: var(--ok); }
.badge-active .dot   { background: var(--ok); }
.badge-inactive { background: rgba(107,114,128,0.15); color: var(--text-muted); }
.badge-inactive .dot { background: var(--text-muted); }

/* Search bar */
.search-bar { position: relative; }
.search-bar input {
    width: 100%; padding: 11px 16px 11px 42px;
    background: var(--bg-body); color: var(--text-main);
    border: 1px solid var(--border); border-radius: 12px;
    font-size: 0.85rem; transition: 0.2s;
}
.search-bar input:focus { outline: none; border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft); }
.search-bar input::placeholder { color: var(--placeholder-color); }
.search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 0.85rem; pointer-events: none; }

/* ═══ Category dropdown filter ═══ */
.filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.filter-bar label {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    white-space: nowrap;
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.filter-select {
    background: var(--bg-body);
    color: var(--text-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 9px 38px 9px 14px;
    font-size: 0.85rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    min-width: 200px;
    outline: none;
    transition: 0.18s;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23A3AED0' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 14px center;
}
.filter-select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}
[data-theme="dark"] .filter-select {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23707EAE' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
}
.filter-select option {
    background: var(--card-bg);
    color: var(--text-main);
    font-weight: 600;
}
.filter-count {
    margin-left: auto;
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 700;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.filter-count strong { color: var(--accent); font-weight: 800; }

/* Empty state */
.empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
.empty-state i { font-size: 3rem; opacity: 0.4; margin-bottom: 14px; display: block;
    color: var(--accent); }
.empty-state h5 { font-weight: 700; color: var(--text-main); margin-bottom: 6px; }

@media(max-width:900px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; width: 100%; padding: 20px; }
    .filter-select { min-width: 140px; }
}
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>

    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>

        <div class="section-label">Dispatching</div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="internalSub" class="submenu">
                <a href="../letters/inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../letters/inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="../letters/inward/inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('taskSub')">
                <i class="fa-solid fa-list-check"></i> Task Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="taskSub" class="submenu">
                <a href="../letters/tasking/task_letters.php" class="sub-nav-btn">Add Task</a>
                <a href="../letters/tasking/task_list.php" class="sub-nav-btn">View Tasks</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('outwardSub')">
                <i class="fa-solid fa-paper-plane"></i> Outward Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="outwardSub" class="submenu">
                <a href="../letters/outward/add_outward.php" class="sub-nav-btn">Add Outward</a>
                <a href="../letters/outward/view_outward.php" class="sub-nav-btn">View Outward</a>
            </div>
        </div>

        <div class="section-label">Records</div>

        <a href="../reports/linkage_report.php" class="nav-btn">
            <i class="fa-solid fa-diagram-project"></i> Linkage Report
        </a>

        <div class="section-label">Administration</div>

        <a href="permission_codes.php" class="nav-btn active">
            <i class="fa-solid fa-key"></i> Permission Codes
        </a>

        <a href="../auth/logout.php" class="nav-btn mt-4" style="color:#ef4444;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main">
    <!-- ═══ Header ═══ -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="page-head">
            <h2><i class="fa-solid fa-key me-2" style="color:var(--accent);"></i>Permission Codes</h2>
            <p>Manage system permission codes, categories and status</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="add_permission_code.php" class="btn-primary-c">
                <i class="fa-solid fa-plus"></i> Add Permission Code
            </a>
        </div>
    </div>

    <!-- ═══ Stats ═══ -->
    <div class="stat-grid">
        <div class="stat">
            <div class="label">Total Codes</div>
            <div class="value"><?= number_format($totalCodes) ?></div>
        </div>
        <div class="stat ok">
            <div class="label">Active</div>
            <div class="value"><?= number_format($activeCount) ?></div>
        </div>
        <div class="stat err">
            <div class="label">Inactive</div>
            <div class="value"><?= number_format($inactiveCount) ?></div>
        </div>
        <div class="stat">
            <div class="label">Categories</div>
            <div class="value"><?= number_format(count($categories)) ?></div>
        </div>
    </div>

    <!-- ═══ Main card ═══ -->
    <div class="card-box">

        <!-- Search bar -->
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Search by code, description or category...">
                </div>
            </div>
            <div class="col-md-4 text-end d-flex justify-content-md-end align-items-center">
                <span class="filter-count">
                    <i class="fa-solid fa-circle-info"></i>
                    Showing <strong id="visibleCount"><?= count($permissions) ?></strong> of <?= count($permissions) ?>
                </span>
            </div>
        </div>

        <!-- ═══ Category dropdown filter ═══ -->
        <?php if (!empty($categories)): ?>
        <div class="filter-bar">
            <label for="categoryFilter">
                <i class="fa-solid fa-filter"></i> Category
            </label>
            <select id="categoryFilter" class="filter-select">
                <option value="all">All Categories (<?= count($permissions) ?>)</option>
                <?php foreach ($categories as $cat => $cnt): ?>
                    <option value="<?= htmlspecialchars($cat) ?>">
                        <?= htmlspecialchars($cat) ?> (<?= $cnt ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Table -->
        <?php if (empty($permissions)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-key"></i>
                <h5>No permission codes yet</h5>
                <p class="mb-3">Add your first permission code to get started.</p>
                <a href="add_permission_code.php" class="btn-primary-c">
                    <i class="fa-solid fa-plus"></i> Add Permission Code
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="permTable">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th style="width:220px;">Code</th>
                            <th>Description</th>
                            <th style="width:250px;">Category</th>
                            <th style="width:120px;">Status</th>
                            <th style="width:100px;text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $p):
                            $statusClass = (($p['status'] ?? '') === 'Active') ? 'badge-active' : 'badge-inactive';
                            $cat = $p['category'] ?: 'Uncategorized';
                        ?>
                        <tr data-category="<?= htmlspecialchars($cat) ?>"
                            data-search="<?= htmlspecialchars(strtolower($p['code'] . ' ' . $p['description'] . ' ' . $cat)) ?>">
                            <td><span style="color:var(--text-muted);font-weight:700;">#<?= (int)$p['id'] ?></span></td>
                            <td><span class="code-mono"><?= htmlspecialchars($p['code']) ?></span></td>
                            <td><?= htmlspecialchars($p['description']) ?></td>
                            <td><span class="badge-cat"><?= htmlspecialchars($cat) ?></span></td>
                            <td>
                                <span class="badge-status <?= $statusClass ?>">
                                    <span class="dot"></span>
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <a href="edit_permission_code.php?id=<?= (int)$p['id'] ?>"
                                   class="action-btn btn-edit" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <a href="delete_permission_code.php?id=<?= (int)$p['id'] ?>"
                                   class="action-btn btn-del" title="Delete"
                                   onclick="return confirm('Delete permission code &quot;<?= htmlspecialchars($p['code'], ENT_QUOTES) ?>&quot;? This cannot be undone.');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Hidden empty row for no-results state -->
                        <tr id="noResults" style="display:none;">
                            <td colspan="6">
                                <div class="empty-state" style="padding:30px;">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <h5>No matching permission codes</h5>
                                    <p>Try a different search or category filter.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
/* ─── Theme — read from localStorage (set by home page) ─── */
const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);

/* ─── Sidebar submenu toggle ─────────────────────── */
function toggleSubmenu(id) {
    var submenu = document.getElementById(id);
    if (!submenu) return;
    var btn = submenu.previousElementSibling;
    var icon = btn ? btn.querySelector('.fa-chevron-down') : null;

    if (submenu.classList.contains('show')) {
        submenu.classList.remove('show');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('show');
        if (icon) icon.style.transform = 'rotate(180deg)';
    }
}

/* ─── Live search + category DROPDOWN filter ─────── */
(function() {
    var searchInput   = document.getElementById('searchInput');
    var categorySelect= document.getElementById('categoryFilter');
    var rows          = document.querySelectorAll('#permTable tbody tr[data-category]');
    var noResults     = document.getElementById('noResults');
    var visibleCount  = document.getElementById('visibleCount');

    var activeCat = 'all';
    var query     = '';

    function applyFilters() {
        var visible = 0;
        rows.forEach(function(row) {
            var cat   = row.dataset.category || '';
            var text  = row.dataset.search || '';
            var matchCat  = (activeCat === 'all') || (cat === activeCat);
            var matchText = (query === '') || text.indexOf(query) !== -1;

            if (matchCat && matchText) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });
        if (noResults) noResults.style.display = (visible === 0) ? '' : 'none';
        if (visibleCount) visibleCount.textContent = visible;
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            query = this.value.trim().toLowerCase();
            applyFilters();
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            activeCat = this.value;
            applyFilters();
        });
    }
})();
</script>
</body>
</html>