<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

if (!hasPermission('MANAGE_USERS')) {
    die('Access Denied!');
}

// Fetch desks for dropdown
$desks = [];
$res = $conn->query("SELECT id, desk_name FROM desks WHERE is_deleted = 0 AND status='Active'");
while ($row = $res->fetch_assoc()) {
    $desks[] = $row;
}

// ENUM and predefined values based on SQL schema
$sections     = ['IT Section', 'Administration Section', 'Registry Section'];
$categories   = ['Officer', 'ORs'];
$work_places  = ['DMI', '1 MIC GP', '2 MIC GP', '3 MIC GP', '4 MIC GP', '5 MIC GP', '6 MIC GP', '7 MIC GP', '8 MIC GP', 'OMEGA', 'SIEERA'];
$appointments = ['Desk Officer','Chief Clerk (CC)','Registry Assistance (RA)','Desk Head','Dispatch Clerk', 'Subject clerk','User'];
$statuses     = ['Active', 'Inactive', 'Suspended'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username    = trim($_POST['username']);
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $name        = trim($_POST['name_with_initial']);
    $service     = trim($_POST['service_number']);
    $rank        = trim($_POST['rank']);
    $regiment    = trim($_POST['regiment']);
    $work_place  = $_POST['work_place'] ?? 'DMI';
    $desk_id     = (int)$_POST['desk_id'];
    $section     = $_POST['section'] ?? 'Administration Section';
    $appointment = $_POST['Appointment'] ?? 'Subject clerk';
    $user_cat    = $_POST['user_category'] ?? 'ORs';
    $status      = $_POST['status'] ?? 'Active';

    // Validation checks for ENUM values
    if (!in_array($section, $sections)) {
        $section = 'Administration Section';
    }
    if (!in_array($work_place, $work_places)) {
        $work_place = 'DMI';
    }
    if (!in_array($appointment, $appointments)) {
        $appointment = 'Subject clerk';
    }
    if (!in_array($user_cat, $categories)) {
        $user_cat = 'ORs';
    }
    if (!in_array($status, $statuses)) {
        $status = 'Active';
    }

    // SQL statement covering all fields in `users` table
    $stmt = $conn->prepare("INSERT INTO users 
        (username, password, name_with_initial, service_number, rank, regiment, work_place, desk_id, section, Appointment, user_category, status, is_deleted) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Types: s = string, i = integer (ssssssisssss -> 12 parameters)
    $stmt->bind_param(
        "sssssssissss",
        $username,
        $password,
        $name,
        $service,
        $rank,
        $regiment,
        $work_place,
        $desk_id,
        $section,
        $appointment,
        $user_cat,
        $status
    );

    if ($stmt->execute()) {
        header("Location: users.php");
        exit;
    } else {
        $error = "Failed to add user: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IDCMS | Add User</title>
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
            transition: 0.2s; 
        }

        .sub-nav-btn:hover { 
            color: var(--accent); 
            border-left-color: var(--accent); 
        }

        .sub-nav-btn.active-sub { 
            color: var(--accent); 
            border-left-color: var(--accent); 
            font-weight: 700; 
        }

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

        .form-container { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            padding: 30px; 
            margin: 0 30px 30px 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }

        .form-label { 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            color: var(--text-muted); 
            margin-bottom: 8px; 
        }
        
        .form-control, .form-select { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 12px; 
            padding: 10px 15px; 
            font-size: 0.9rem; 
        }

        .form-control::placeholder { 
            color: var(--placeholder-color); 
            opacity: 0.8; 
            font-weight: 400; 
        }

        .form-control:focus, .form-select:focus { 
            background: var(--bg-body); 
            color: var(--text-main); 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px var(--accent-soft); 
        }

        .btn-save { 
            background: var(--accent); 
            color: white; 
            border: none; 
            padding: 12px 30px; 
            border-radius: 12px; 
            font-weight: 700; 
            transition: 0.3s; 
        }

        .btn-save:hover { 
            opacity: 0.9; 
            transform: translateY(-2px); 
        }

        .btn-clear { 
            color: var(--text-muted); 
            border: none; 
            background: transparent; 
            padding: 12px 20px; 
            font-weight: 600; 
            font-size: 0.85rem; 
            transition: 0.3s; 
            opacity: 0.5; 
        }

        .btn-clear:hover { 
            opacity: 1; 
            color: var(--text-main); 
        }
        
        .section-title { 
            font-size: 1.25rem; 
            font-weight: 800; 
            color: var(--text-main); 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
        }

        .section-title i { 
            margin-right: 12px; 
            color: var(--accent); 
        }

        .stats-badge { 
            background: var(--accent-soft); 
            color: var(--accent); 
            padding: 8px 16px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.85rem; 
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS</div>
    </div>
    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
        </a>
        
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">
            USER CONTROL
        </div>

        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('userMenu')">
                <i class="fa-solid fa-users-gear me-2"></i> Manage Users
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="userMenu" class="submenu show">
                <a href="add_user.php" class="sub-nav-btn text-primary fw-bold active-sub">Add New User</a>
                <a href="users.php" class="sub-nav-btn">User Directory</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-main">User Creation</h4>
            <a href="users.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </header>

    <div class="form-container">
        <div class="section-title">
            <i class="fa-solid fa-user-plus"></i> Create New User Account
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4 border-0 mb-4 fw-bold">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <!-- Authentication Details -->
            <div class="section-title fs-6 mb-3 opacity-75">
                <i class="fa-solid fa-id-card-clip"></i> Authentication Details
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Unique system ID" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Strong password required" required>
                </div>
            </div>

            <!-- Profile Information -->
            <div class="section-title fs-6 mb-3 opacity-75">
                <i class="fa-solid fa-user-tag"></i> Profile Information
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Name with Initials</label>
                    <input type="text" name="name_with_initial" class="form-control" placeholder="e.g. Perera AB" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Service Number</label>
                    <input type="text" name="service_number" class="form-control" placeholder="Unique S/N" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rank</label>
                    <input type="text" name="rank" class="form-control" placeholder="Major | CPL | L/CPL" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Regiment</label>
                    <input type="text" name="regiment" class="form-control" placeholder="Assigned Regiment" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Work Place</label>
                    <select name="work_place" class="form-select" required>
                        <?php foreach ($work_places as $wp): ?>
                            <option value="<?= $wp ?>"><?= $wp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Section</label>
                    <select name="section" class="form-select" required>
                        <?php foreach ($sections as $sec): ?>
                            <option value="<?= $sec ?>"><?= $sec ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">User Category</label>
                    <select name="user_category" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Appointment</label>
                    <select name="Appointment" class="form-select" required>
                        <?php foreach ($appointments as $appt): ?>
                            <option value="<?= $appt ?>"><?= $appt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Assignment & Status -->
            <div class="section-title fs-6 mb-3 opacity-75">
                <i class="fa-solid fa-gears"></i> Assignment & Status
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Desk Assignment</label>
                    <select name="desk_id" class="form-select" required>
                        <option value="">-- Select Desk --</option>
                        <?php foreach ($desks as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['desk_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Account Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $st): ?>
                            <option value="<?= $st ?>"><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 mt-5 text-end">
                    <button type="reset" class="btn-clear me-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> Clear Form
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="fa-solid fa-user-check me-2"></i> Confirm & Save User
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Logic
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // Sidebar Submenu Toggle Function
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

    // Auto-close alert after 5 seconds
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