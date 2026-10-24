<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Check permission
if (!hasPermission('MANAGE_BRANCHES')) {
    die('Access Denied!');
}

$error = '';
$success = '';

/* -----------------------------
    1. HANDLE ACTIONS (CRUD)
------------------------------*/

// --- DELETE LOGIC ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE external_branches SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>window.location.href='branches.php?status=deleted';</script>";
        exit;
    }
}

// --- ADD / UPDATE LOGIC ---
if (isset($_POST['submit'])) {
    $id          = $_POST['id'] ?? null;
    $branch_name = trim($_POST['branch_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $status      = $_POST['status'] ?? 'Active';

    if (!empty($id)) {
        $stmt = $conn->prepare("UPDATE external_branches SET branch_name=?, address=?, status=? WHERE id=?");
        $stmt->bind_param("sssi", $branch_name, $address, $status, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO external_branches (branch_name, address, status) VALUES (?,?,?)");
        $stmt->bind_param("sss", $branch_name, $address, $status);
    }

    if ($stmt && $stmt->execute()) {
        echo "<script>window.location.href='branches.php?status=success';</script>";
        exit;
    } else { 
        $error = "Database Error: " . $conn->error; 
    }
}

/* -----------------------------
    2. FETCH DATA
------------------------------*/
$branches_res = $conn->query("SELECT * FROM external_branches WHERE is_deleted = 0 ORDER BY branch_name ASC");
$branches = [];
while($row = $branches_res->fetch_assoc()) { $branches[] = $row; }

$edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_res = $conn->query("SELECT * FROM external_branches WHERE id=$edit_id");
    if($edit_res) { $edit = $edit_res->fetch_assoc(); }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Branches | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px; --filter-bg: #FFFFFF;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
            --filter-bg: #1A1F37;
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; margin:0; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; margin-bottom: 4px; cursor: pointer;}
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .submenu { display: none; padding-left: 35px; margin-bottom: 10px; }
        .submenu.show { display: block; }
        .sub-nav-btn { display: block; padding: 8px 12px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; border-radius: 8px; }
        .sub-nav-btn:hover { color: var(--accent); background: var(--accent-soft); }
        
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 20px 30px; z-index: 90; border-bottom: 1px solid var(--border); }
        
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 25px; margin: 30px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .branch-row td { background: var(--card-bg); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 15px; vertical-align: middle; }
        .branch-row td:first-child { border-left: 1px solid var(--border); border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .branch-row td:last-child { border-right: 1px solid var(--border); border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        
        .form-control, .form-select { background: var(--filter-bg); border: 1px solid var(--border); color: var(--text-main); border-radius: 12px; padding: 10px 15px; }
        .action-dock { background: var(--bg-body); border: 1px solid var(--border); padding: 5px; border-radius: 10px; display: inline-flex; gap: 5px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name" style="font-weight: 800; color: var(--text-main); line-height: 1.2;">
            <i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br>
            <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Dispatching</div>
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('outwardSub')">
                <i class="fa-solid fa-paper-plane me-2"></i> Outward Letters
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="outwardSub" class="submenu show">
                <a href="../outward/outward.php" class="sub-nav-btn">View Registry</a>
                <a href="branches.php" class="sub-nav-btn text-primary fw-bold">External Branches</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <h4 class="fw-bold m-0 text-main">External Branches Registry</h4>
    </header>

    <div class="px-2">
        <div class="table-container shadow-sm mb-4">
            <h6 class="fw-bold mb-4 text-primary"><?= $edit ? 'Edit Branch' : 'Register New Branch' ?></h6>
            <form method="post">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="small fw-bold text-muted mb-2">BRANCH NAME</label>
                        <input type="text" name="branch_name" class="form-control" required value="<?= htmlspecialchars($edit['branch_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold text-muted mb-2">STATUS</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= (isset($edit['status']) && $edit['status']=='Active')?'selected':'' ?>>Active</option>
                            <option value="Inactive" <?= (isset($edit['status']) && $edit['status']=='Inactive')?'selected':'' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="small fw-bold text-muted mb-2">ADDRESS</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($edit['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="submit" class="btn btn-primary w-100 fw-bold" style="border-radius:12px; height:45px;">
                            <i class="fa-solid fa-save me-2"></i> Save Information
                        </button>
                    </div>
                    <?php if($edit): ?>
                    <div class="col-md-2">
                        <a href="branches.php" class="btn btn-outline-secondary w-100" style="border-radius:12px; height:45px;">Cancel</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-container shadow-sm">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr class="text-muted small">
                            <th>ID</th>
                            <th>BRANCH NAME</th>
                            <th>ADDRESS</th>
                            <th>STATUS</th>
                            <th class="text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $row): ?>
                        <tr class="branch-row">
                            <td><span class="text-muted fw-bold">#<?= $row['id'] ?></span></td>
                            <td><div class="fw-bold text-main"><?= htmlspecialchars($row['branch_name']) ?></div></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['address']) ?></td>
                            <td>
                                <span class="badge <?= $row['status'] == 'Active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $row['status'] == 'Active' ? 'text-success' : 'text-danger' ?> border rounded-pill px-3">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="action-dock">
                                    <a href="branches.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0">
                                        <i class="fa-solid fa-pen text-warning"></i>
                                    </a>
                                    <a href="branches.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0" onclick="return confirm('Are you sure?')">
                                        <i class="fa-solid fa-trash text-danger"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    function toggleSubmenu(id) {
        document.getElementById(id).classList.toggle('show');
    }

    function applyGlobalTheme() {
        const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    }

    window.onload = function() {
        applyGlobalTheme();
    };
</script>
</body>
</html>