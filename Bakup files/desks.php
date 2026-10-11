<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('DELETE_DESK')) {
    echo "Access Denied!";
    exit;
}

// Fetch desks
$result = $conn->query("SELECT * FROM desks WHERE is_deleted = 0");
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDCMS | Desks Management</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; }

        /* Search Bar Styles */
        .search-wrapper { position: relative; width: 300px; }
        .search-input { 
            background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); 
            border-radius: 12px; padding: 10px 15px 10px 40px; font-size: 0.85rem; width: 100%; transition: 0.3s;
        }
        .search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem; }

        /* Table Customization */
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 20px; margin: 0 30px 30px 30px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 5px; font-size: 0.82rem; }
        .custom-table thead th { color: var(--text-muted); font-size: 0.65rem; text-transform: uppercase; padding: 15px; border: none; }
        .custom-table td { padding: 15px; vertical-align: middle; border-bottom: 1px solid var(--border); }
        
        .desk-avatar { width: 35px; height: 35px; background: var(--accent-soft); color: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; }
        .badge-status { padding: 5px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; }
        .status-active { background: rgba(0, 200, 83, 0.1); color: #00C853; }
        .status-inactive { background: rgba(255, 61, 0, 0.1); color: #FF3D00; }
        
        .action-btn { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; transition: 0.2s; border: 1px solid var(--border); color: var(--text-main); text-decoration: none; }
        .action-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">System Admin</div>
        <a href="../users/users.php" class="nav-btn"><i class="fa-solid fa-users-gear me-2"></i> Manage Users</a>
        <a href="desks.php" class="nav-btn active"><i class="fa-solid fa-desktop me-2"></i> Desk Settings</a>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0 text-main">Desks Management</h4>
            <p class="--text-muted small mb-0">Manage system desks and configurations</p>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="deskSearch" class="search-input" placeholder="Search by desk name or description...">
            </div>
            <a href="add_desk.php" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-2"></i> Add Desk
            </a>
        </div>
    </header>

   <div class="table-container shadow-sm">
    <table class="custom-table" id="deskTable">
        <thead>
            <tr>
                <th>Desk ID / Info</th>
                <th>Desk Name</th>
                <th>Description</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): 
                $statusVal = isset($row['status']) ? $row['status'] : 'active';
                $statusClass = (strtolower($statusVal) == 'active') ? 'status-active' : 'status-inactive';
                $words = explode(' ', trim($row['desk_name']));
$initials = strtoupper((isset($words[0]) ? substr($words[0], 0, 1) : '') . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
if (empty($initials)) {
    $initials = 'DS'; // Fallback if name is empty
} ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="desk-avatar"><?= htmlspecialchars($initials) ?></div>
                        <div>
                            <div class="fw-bold font-monospace text-main">#<?= htmlspecialchars($row['id']) ?></div>
                            <div class="--text-muted small">Desk of DMI</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="fw-bold text-main"><?= htmlspecialchars($row['desk_name']) ?></span>
                </td>
                <td>
                    <div class="--text-muted small"><?= htmlspecialchars($row['description']) ?></div>
                </td>
                <td>
                    <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($statusVal)) ?></span>
                </td>
                <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="edit_desk.php?id=<?= $row['id']; ?>" class="action-btn" title="Edit Desk">
                            <i class="fa-solid fa-pen-nib"></i>
                        </a>
                        <a href="delete_desk.php?id=<?= $row['id']; ?>" class="action-btn text-danger" title="Delete Desk" onclick="return confirm('Are you sure you want to delete this desk?');">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Sync
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // LIVE SEARCH FUNCTIONALITY
    document.getElementById('deskSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#deskTable tbody tr');

        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
</script>
</body>
</html>