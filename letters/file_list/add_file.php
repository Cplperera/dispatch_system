<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_FILE_RECORD')) {
    die('Access Denied');
}

$error = '';
$success = false;

// Fetch active desks for dropdown
$desks = $conn->query("SELECT id, desk_name FROM desks WHERE status = 'Active' AND is_deleted = 0 ORDER BY desk_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_reference = trim($_POST['file_reference']);
    $volume         = trim($_POST['volume']);
    $rack_no        = trim($_POST['rack_no']);
    $row_no         = trim($_POST['row_no']);
    $about_file     = trim($_POST['about_file']);
    $desk_id        = !empty($_POST['desk_id']) ? (int)$_POST['desk_id'] : null;
    $created_by     = $_SESSION['user_id'];

    if (empty($file_reference) || empty($volume) || empty($rack_no) || empty($row_no)) {
        $error = 'Please fill in all required fields (File Reference, Volume, Rack No, Row No).';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO physical_file_records 
            (file_reference, volume, rack_no, row_no, about_file, desk_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssii", $file_reference, $volume, $rack_no, $row_no, $about_file, $desk_id, $created_by);

        if ($stmt->execute()) {
            header("Location: file_list.php?success=1");
            exit();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Physical File Record | IDCMS</title>
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

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            font-family: "Segoe UI", sans-serif;
            transition: 0.3s;
        }
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
        .brand-name {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--accent);
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
        .submenu {
            padding-left: 20px;
            display: none;
        }
        .submenu.show {
            display: block;
        }
        .sub-nav-btn {
            display: block;
            padding: 8px 15px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            border-left: 2px solid var(--border);
        }
        .sub-nav-btn:hover {
            color: var(--accent);
            border-left-color: var(--accent);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
            padding: 30px;
        }
        .card-box {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .label {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .form-control, .form-select {
            background: var(--sidebar-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 12px;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            background: var(--sidebar-bg);
            border-color: var(--accent);
            box-shadow: none;
            color: var(--text-main);
        }
        /* === DARK MODE READABILITY FIXES === */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #1a1f3a !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.15);
        }
        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] .form-select::placeholder {
            color: #a0aec0 !important;
            opacity: 1;
        }
        [data-theme="dark"] .form-text,
        [data-theme="dark"] .label {
            color: #cbd5e0 !important;
            opacity: 0.9;
        }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #1a1f3a !important;
            color: #ffffff !important;
            border-color: var(--accent);
        }
        [data-theme="dark"] .btn-secondary {
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.2);
        }
        [data-theme="dark"] .btn-secondary:hover {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 12px;
            padding: 10px 24px;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-primary:hover {
            background: var(--accent);
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 12px;
            padding: 10px 20px;
        }
        .btn-secondary:hover {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }
        .alert {
            border-radius: 12px;
            background: rgba(220, 53, 69, 0.1);
            border: none;
            color: #dc3545;
        }
    </style>
</head>
<body>

<!-- SIDEBAR MODIFIED TO MATCH IMAGE -->
<aside class="sidebar">
    <div class="brand-box mb-4 px-2 d-flex align-items-center">
        <i class="fa-solid fa-shield-halved text-primary me-2"></i>
        <div class="brand-name">IDCMS</div>
    </div>
    <nav>
        <!-- Home Page -->
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
        </a>

        <!-- Dispatching Section -->
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">E-Files Operations</div>
        
        <!-- E-Files Operations Dropdown (Active & Open) -->
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('efileSub')">
                <i class="fa-solid fa-warehouse text me-2"></i> Record Room
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="efileSub" class="submenu show">
                <a href="add_file.php" class="sub-nav-btn text-primary fw-bold">Add File</a>
                <a href="../file_list/file_list.php" class="sub-nav-btn">File List</a>
                <a href="#" class="sub-nav-btn">Issue & Returned File</a>
            </div>
        </div>

        <!-- Logout -->
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Add Physical File Record</h2>
                <p class="--text-muted small">Register a new physical file in the archive system</p>
            </div>
            <a href="file_list.php" class="btn btn-secondary btn-sm px-3">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-box">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="label">File Reference *</div>
                        <input type="text" name="file_reference" class="form-control" required 
                               placeholder="e.g., ADM-2026-001">
                        <div class="form-text --text-muted small">Unique identifier (e.g., FIN-2026-001)</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="label">Volume *</div>
                        <input type="text" name="volume" class="form-control" required 
                               placeholder="e.g., Vol 1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="label">Rack No *</div>
                        <input type="text" name="rack_no" class="form-control" required 
                               placeholder="e.g., Rack 01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="label">Row No *</div>
                        <input type="text" name="row_no" class="form-control" required 
                               placeholder="e.g., Row 01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="label">Desk (Optional)</div>
                        <select name="desk_id" class="form-select">
                            <option value="">Select Desk</option>
                            <?php while ($desk = $desks->fetch_assoc()): ?>
                                <option value="<?= $desk['id'] ?>"><?= htmlspecialchars($desk['desk_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text --text-muted small">Optional – assign to a desk</div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="label">About File (Optional)</div>
                        <textarea name="about_file" rows="3" class="form-control" 
                                  placeholder="Brief description or remarks about this file"></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-regular fa-floppy-disk me-2"></i>Save Record
                    </button>
                    <a href="file_list.php" class="btn btn-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    function toggleSubmenu(id) {
        const el = document.getElementById(id);
        el.classList.toggle('show');
    }

    // Theme persistence
    (function() {
        const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
</script>
</body>
</html>