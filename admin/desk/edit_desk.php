<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('EDIT_DESK')) {
    echo "Access Denied!";
    exit;
}

$id = $_GET['id'] ?? 0;
$error = $success = '';

// Fetch desk details
$stmt = $conn->prepare("SELECT * FROM desks WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$desk = $stmt->get_result()->fetch_assoc();

if (!$desk) {
    echo "Desk not found!";
    exit;
}

if(isset($_POST['submit'])) {
    $desk_name = $_POST['desk_name'];
    $description = $_POST['description'];
    $status = $_POST['status'];

    $update_stmt = $conn->prepare("UPDATE desks SET desk_name=?, description=?, status=? WHERE id=?");
    $update_stmt->bind_param("sssi", $desk_name, $description, $status, $id);

    if($update_stmt->execute()) {
        $success = "Desk updated successfully!";
        // Refresh desk data
        $stmt->execute();
        $desk = $stmt->get_result()->fetch_assoc();
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDCMS | Edit Desk</title>
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

        /* Form Customization */
        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; margin: 0 30px 30px 30px; max-width: 700px; }
        .form-label { color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
        .form-control, .form-select {
            background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main);
            border-radius: 12px; padding: 12px 15px; font-size: 0.85rem; transition: 0.3s;
        }
        .form-control:focus, .form-select:focus {
            background: var(--bg-body); color: var(--text-main); outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
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
            <h4 class="fw-bold mb-0 text-main">Edit Desk</h4>
            <p class="--text-muted small mb-0">Modify system desk information</p>
        </div>
        <div>
            <a href="add_desk.php" class="btn btn-outline-secondary px-4 rounded-pill fw-bold shadow-sm">
                <i class="fa-solid fa-arrow-left me-2"></i> Back to Desks
            </a>
        </div>
    </header>

    <div class="form-container shadow-sm">
        <?php if($error): ?>
            <div class="alert alert-danger rounded-3 p-3 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success rounded-3 p-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Desk Name</label>
                <input type="text" name="desk_name" class="form-control" value="<?= htmlspecialchars($desk['desk_name']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($desk['description']); ?>">
            </div>

            <div class="mb-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Active" <?= (strtolower($desk['status']) == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?= (strtolower($desk['status']) == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="desks.php" class="btn btn-light px-4 rounded-pill fw-bold">Cancel</a>
                <button type="submit" name="submit" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Update Desk
                </button>
            </div>
        </form>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Sync
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
</script>
</body>
</html>