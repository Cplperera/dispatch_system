<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

if (!hasPermission('MANAGE_USERS')) {
    die('Access Denied!');
}

/* ===============================
    DASHBOARD STATS CALCULATIONS
================================ */
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_deleted = 0")->fetch_assoc()['count'] ?? 0;
$activeUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Active' AND is_deleted = 0")->fetch_assoc()['count'] ?? 0;
$inactiveUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE status IN ('Inactive', 'Suspended') AND is_deleted = 0")->fetch_assoc()['count'] ?? 0;
$unassignedDesks = $conn->query("SELECT COUNT(*) as count FROM users WHERE (desk_id IS NULL OR desk_id = 0) AND is_deleted = 0")->fetch_assoc()['count'] ?? 0;

/* ===============================
    FETCH ALL USERS WITH DESKS
================================ */
$sql = "
    SELECT 
        u.id,
        u.username,
        u.name_with_initial,
        u.service_number,
        u.rank,
        u.regiment,
        u.work_place,
        u.section,
        u.Appointment,
        u.user_category,
        u.status,
        d.desk_name
    FROM users u
    LEFT JOIN desks d ON u.desk_id = d.id
    WHERE u.is_deleted = 0
    ORDER BY u.id DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query Failed: " . $conn->error);
}

/* Dynamic Categories & Appointments list for Filters */
$categories = [];
$catRes = $conn->query("SELECT DISTINCT user_category FROM users WHERE is_deleted = 0 AND user_category IS NOT NULL AND user_category != ''");
if ($catRes) {
    while ($c = $catRes->fetch_assoc()) {
        $categories[] = $c['user_category'];
    }
}

$appointments = [];
$appRes = $conn->query("SELECT DISTINCT Appointment FROM users WHERE is_deleted = 0 AND Appointment IS NOT NULL AND Appointment != '' ORDER BY Appointment ASC");
if ($appRes) {
    while ($a = $appRes->fetch_assoc()) {
        $appointments[] = $a['Appointment'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | IDCMS</title>
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
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            min-height: 100vh; 
            font-family: "Segoe UI", sans-serif; 
            transition: 0.3s; 
        }
        
        /* Sidebar Styling */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--sidebar-bg); 
            border-right: 1px solid var(--border); 
            padding: 30px 15px; 
            position: fixed; 
            height: 100vh; 
            overflow-y: auto; 
            z-index: 100; 
        }
        .nav-btn { 
            display: flex; 
            align-items: center; 
            padding: 12px 15px; 
            color: var(--text-muted); 
            text-decoration: none; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.85rem; 
            transition: 0.2s; 
            background: transparent; 
            width: 100%; 
            border: none; 
            text-align: left; 
            cursor: pointer;
        }
        .nav-btn:hover, .nav-btn.active { 
            background: var(--accent-soft); 
            color: var(--accent); 
        }
        
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { 
            display: block; 
            padding: 8px 15px; 
            color: var(--text-muted); 
            text-decoration: none; 
            font-size: 0.8rem; 
            font-weight: 600; 
            border-left: 2px solid var(--border); 
            transition: 0.2s; 
        }
        .sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
        .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

        /* Main Layout */
        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            display: flex; 
            flex-direction: column; 
        }
        .top-nav { 
            position: sticky; 
            top: 0; 
            background: var(--bg-body); 
            padding: 25px 30px; 
            z-index: 90; 
        }

        /* Stat Cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 18px;
            border: 1px solid var(--border);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .stat-icon.primary { background: var(--accent-soft); color: var(--accent); }
        .stat-icon.success { background: rgba(0, 200, 100, 0.12); color: #00C864; }
        .stat-icon.warning { background: rgba(255, 182, 0, 0.12); color: #FFB600; }
        .stat-icon.danger { background: rgba(255, 91, 91, 0.12); color: #FF5B5B; }
        .stat-number { font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2; }
        .stat-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin: 0; }

        /* Container & Card Styles */
        .form-container { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            padding: 30px; 
            margin: 0 30px 30px 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .section-title { 
            font-size: 1.1rem; 
            font-weight: 800; 
            color: var(--text-main); 
            display: flex; 
            align-items: center; 
        }
        .section-title i { margin-right: 10px; color: var(--accent); }

        /* Category Filter Pills & Select */
        .category-pills { display: flex; gap: 8px; flex-wrap: wrap; }
        .cat-pill {
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .cat-pill:hover, .cat-pill.active {
            background: var(--accent);
            color: #FFFFFF;
            border-color: var(--accent);
        }

        /* Form Controls & Select */
        .form-control, .form-select { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 12px; 
            padding: 10px 15px; 
            font-size: 0.85rem; 
        }
        .form-control::placeholder { color: var(--placeholder-color); opacity: 0.8; font-weight: 400; }
        .form-control:focus, .form-select:focus { 
            background: var(--bg-body); 
            color: var(--text-main); 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px var(--accent-soft); 
        }

        /* Table Design */
        .table { color: var(--text-main); margin-bottom: 0; vertical-align: middle; }
        .table th { 
            font-size: 0.75rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            color: var(--text-muted); 
            padding: 15px 10px; 
            border-bottom: 2px solid var(--border); 
        }
        .table td { font-size: 0.85rem; padding: 15px 10px; border-bottom: 1px solid var(--border); }
        
        .user-avatar { 
            width: 38px; 
            height: 38px; 
            background: var(--accent-soft); 
            color: var(--accent); 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 800; 
            font-size: 0.85rem; 
        }

        /* Status Badges */
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.7rem; display: inline-block; text-transform: capitalize; }
        .badge-status.active { background: rgba(0, 200, 100, 0.15); color: #00C864; }
        .badge-status.inactive { background: rgba(255, 91, 91, 0.15); color: #FF5B5B; }
        .badge-status.suspended { background: rgba(255, 182, 0, 0.15); color: #FFB600; }

        /* Action Buttons */
        .btn-action { 
            background: transparent; 
            color: var(--text-muted); 
            border: 1px solid var(--border); 
            padding: 6px 12px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 0.75rem; 
            transition: 0.3s; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
        }
        .btn-action:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        .btn-add-user { 
            background: var(--accent); 
            color: white; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.85rem; 
            transition: 0.3s; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
        }
        .btn-add-user:hover { opacity: 0.9; color: white; transform: translateY(-2px); }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS 
            <br><span style="font-size: 0.7rem; color: var(--text-muted);"></span>
        </div>
    </div>
    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">System Admin</div>
        
        <!-- User Submenu -->
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('userSub')">
                <i class="fa-solid fa-users-gear me-2"></i> User Management
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="userSub" class="submenu show">
                <a href="users.php" class="sub-nav-btn text-primary fw-bold active-sub">User Directory</a>
                <a href="add_user.php" class="sub-nav-btn">Add New User</a>
               
            </div>
        </div>

        <!-- Desk Submenu -->
        <a href="../../admin/desk/add_desk.php" class="nav-btn mt-1">
            <i class="fa-solid fa-desktop me-2"></i> Desk Settings
        </a>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0 text-main">User Administration Dashboard</h4>
                <p class="text-muted small mb-0">Overview of personnel system accounts and category dispatching</p>
            </div>
            <a href="add_user.php" class="btn-add-user">
                <i class="fa-solid fa-user-plus"></i> Add New User
            </a>
        </div>
    </header>

    <!-- DASHBOARD STAT CARDS -->
    <div class="px-4 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <h3 class="stat-number"><?= $totalUsers ?></h3>
                        <p class="stat-label">Total Registered</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fa-solid fa-user-check"></i></div>
                    <div>
                        <h3 class="stat-number"><?= $activeUsers ?></h3>
                        <p class="stat-label">Active Accounts</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon danger"><i class="fa-solid fa-user-xmark"></i></div>
                    <div>
                        <h3 class="stat-number"><?= $inactiveUsers ?></h3>
                        <p class="stat-label">Inactive / Suspended</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fa-solid fa-desktop"></i></div>
                    <div>
                        <h3 class="stat-number"><?= $unassignedDesks ?></h3>
                        <p class="stat-label">Unassigned Desk</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN USER DIRECTORY CONTAINER -->
    <div class="form-container">
        
        <!-- CATEGORY & APPOINTMENT BREAKDOWN FILTERS ROW -->
        <div class="row g-3 align-items-end mb-4">
            <div class="col-lg-5 col-md-12">
                <div class="section-title mb-2">
                    <i class="fa-solid fa-filter"></i> Category Breakdown
                </div>
                <div class="category-pills">
                    <button class="cat-pill active" onclick="filterCategory('ALL', this)">All</button>
                    <?php foreach ($categories as $catName): ?>
                        <button class="cat-pill" onclick="filterCategory('<?= htmlspecialchars($catName) ?>', this)">
                            <?= htmlspecialchars($catName) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label d-block text-muted small fw-bold mb-1"><i class="fa-solid fa-briefcase me-1"></i> APPOINTMENT</label>
                <select id="appointmentFilter" class="form-select" onchange="applyCombinedFilter()">
                    <option value="ALL">All Appointments</option>
                    <?php foreach ($appointments as $app): ?>
                        <option value="<?= htmlspecialchars($app) ?>"><?= htmlspecialchars($app) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-4 col-md-6">
                <label class="form-label d-block text-muted small fw-bold mb-1"><i class="fa-solid fa-magnifying-glass me-1"></i> SEARCH DIRECTORY</label>
                <input type="text" id="userSearch" class="form-control" placeholder="Search Name, S/N, Rank, Unit...">
            </div>
        </div>

        <!-- USER TABLE -->
        <div class="table-responsive">
            <table class="table table-hover" id="userTable">
                <thead>
                    <tr>
                        <th>Staff Profile</th>
                        <th>Service No</th>
                        <th>Category</th>
                        <th>Workplace & Regiment</th>
                        <th>Section & Appointment</th>
                        <th>Desk Assignment</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalUsers === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fa-solid fa-circle-info me-2"></i> No personnel found in system.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $statusLower = strtolower(trim($row['status']));
                            $statusClass = 'inactive';
                            if ($statusLower === 'active') {
                                $statusClass = 'active';
                            } elseif ($statusLower === 'suspended') {
                                $statusClass = 'suspended';
                            }

                            $initials = strtoupper(substr($row['username'] ?? 'U', 0, 2));
                            $sectionName = !empty($row['section']) ? $row['section'] : 'General Section';
                            $appointment = !empty($row['Appointment']) ? $row['Appointment'] : 'N/A';
                            $workPlace = !empty($row['work_place']) ? $row['work_place'] : 'N/A';
                            $deskName = !empty($row['desk_name']) ? $row['desk_name'] : 'Unassigned';
                            $userCategory = !empty($row['user_category']) ? $row['user_category'] : 'General';
                        ?>
                        <tr data-category="<?= htmlspecialchars($userCategory) ?>" data-appointment="<?= htmlspecialchars($appointment) ?>">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <div>
                                        <div class="fw-bold text-main"><?= htmlspecialchars($row['name_with_initial']) ?></div>
                                        <div class="text-muted small">@<?= htmlspecialchars($row['username']) ?> • <?= htmlspecialchars($row['rank']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['service_number']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-muted border px-2 py-1 rounded-2 fw-bold font-monospace" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($userCategory) ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-main"><?= htmlspecialchars($workPlace) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['regiment']) ?></small>
                            </td>
                            <td>
                                <div class="text-main"><?= htmlspecialchars($sectionName) ?></div>
                                <small class="text-muted"><i class="fa-solid fa-briefcase me-1"></i><?= htmlspecialchars($appointment) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border p-2 rounded-3" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-id-badge me-1 text-primary"></i> 
                                    <?= htmlspecialchars($deskName) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="edit_user.php?id=<?= $row['id']; ?>" class="btn-action" title="Edit Profile">
                                        <i class="fa-solid fa-pen-nib"></i> Edit
                                    </a>
                                    <a href="../permissions/assign_permissions.php?id=<?= $row['id']; ?>" class="btn-action" title="Manage Permissions">
                                        <i class="fa-solid fa-user-lock"></i> Rules
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Syncing with LocalStorage
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // Sidebar Submenu Toggle
    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        if(!submenu) return;
        const icon = submenu.previousElementSibling ? submenu.previousElementSibling.querySelector('.fa-chevron-down') : null;
        
        if (submenu.style.display === "block" || submenu.classList.contains('show')) {
            submenu.style.display = "none";
            submenu.classList.remove('show');
            if(icon) icon.style.transform = "rotate(0deg)";
        } else {
            submenu.style.display = "block";
            submenu.classList.add('show');
            if(icon) icon.style.transform = "rotate(180deg)";
        }
    }

    // Category Dynamic Filter Function
    let activeCategory = 'ALL';
    function filterCategory(catName, btnElement) {
        activeCategory = catName;
        
        // Active status update on buttons
        document.querySelectorAll('.cat-pill').forEach(btn => btn.classList.remove('active'));
        if(btnElement) btnElement.classList.add('active');

        applyCombinedFilter();
    }

    // Live Search Input Filter
    const searchInput = document.getElementById('userSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', applyCombinedFilter);
    }

    // Combined Filtering (Category + Appointment + Search Text)
    function applyCombinedFilter() {
        let searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        let appFilter = document.getElementById('appointmentFilter') ? document.getElementById('appointmentFilter').value : 'ALL';
        let rows = document.querySelectorAll('#userTable tbody tr');

        rows.forEach(row => {
            let rowCategory = row.getAttribute('data-category') || '';
            let rowAppointment = row.getAttribute('data-appointment') || '';
            let rowText = row.textContent.toLowerCase();

            let matchesCategory = (activeCategory === 'ALL' || rowCategory === activeCategory);
            let matchesAppointment = (appFilter === 'ALL' || rowAppointment === appFilter);
            let matchesSearch = rowText.includes(searchValue);

            if (matchesCategory && matchesAppointment && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
http://localhost/dispatch-system/admin/permissions/assign_permissions.php
    // Auto-close alert notifications
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
</script>
</body>
</html>