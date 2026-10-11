<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// 1. Permission check
if (!hasPermission('FINAL_DISPOSAL')) {
    die("Access Denied: You do not have the required permissions.");
}

// 2. Initialize variables
$error = null;
$letter_id = isset($_GET['letter_id']) ? intval($_GET['letter_id']) : 0;
$current_user_id = $_SESSION['user_id'] ?? 0;

if ($letter_id <= 0) {
    die("Invalid Request: Missing or invalid Letter ID.");
}

// 3. Fetch letter details and safety check
$stmt = $conn->prepare("SELECT cdr_number, letter_title, is_under_final_disposal FROM internal_letters WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $letter_id);
$stmt->execute();
$letter = $stmt->get_result()->fetch_assoc();

if (!$letter) {
    die("Letter not found or has been deleted.");
}

// Prevent re-forwarding if already in process
if ($letter['is_under_final_disposal'] == 1) {
    die("This letter is already in the final disposal process.");
}

// 4. Fetch ALL Active Desks for the selection dropdown
$desks_result = $conn->query("SELECT id, desk_name FROM desks WHERE status = 'Active' AND is_deleted = 0 ORDER BY desk_name ASC");

// 5. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_desk_id = intval($_POST['forward_to_desk']);
    $remarks = trim($_POST['remarks']);

    if ($target_desk_id <= 0) {
        $error = "Please select a valid destination desk.";
    } else {
        $conn->begin_transaction();

        try {
            $update_letter = $conn->prepare("UPDATE internal_letters SET is_under_final_disposal = 1, status = 'Completed' WHERE id = ?");
$update_letter->bind_param("i", $letter_id);
            if (!$update_letter->execute()) throw new Exception("Failed to update letter status.");

            $insert_disposal = $conn->prepare("INSERT INTO internal_letters_final_disposal 
                (letter_id, forwarded_to, forwarded_by, remarks, status, forwarded_at) 
                VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $insert_disposal->bind_param("iiis", $letter_id, $target_desk_id, $current_user_id, $remarks);
            
            if (!$insert_disposal->execute()) throw new Exception("Failed to insert disposal record.");

            $conn->commit();
            header("Location: ../inward/internal_letters.php?status=forwarded_success");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $error = "System Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Disposal | LDMS</title>
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
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); }
        .sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
        
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { padding: 40px 30px 20px 30px; }
        
        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 35px; margin: 0 30px 30px 30px; max-width: 900px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .info-box { background: var(--bg-body); border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid var(--accent); }
        .cdr-pill { background: var(--accent-soft); padding: 6px 14px; border-radius: 10px; color: var(--accent); font-weight: 800; font-family: 'Courier New', monospace; font-size: 1rem; border: 1px solid var(--accent); }
        
        .form-label { font-weight: 700; color: var(--text-main); font-size: 0.85rem; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control, .form-select { 
            background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); 
            border-radius: 12px; padding: 12px 15px; font-size: 0.95rem; font-weight: 600; transition: 0.2s;
        }
        
        /* Placeholder Lighting Fix */
        ::placeholder { color: var(--text-muted) !important; opacity: 0.7 !important; }
        ::-webkit-input-placeholder { color: var(--text-muted) !important; opacity: 0.7; }

        .form-control:focus, .form-select:focus { 
            background: var(--bg-body); color: var(--text-main); border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); 
        }
        
        .btn-primary { background: var(--accent); border: none; border-radius: 12px; padding: 12px 30px; font-weight: 700; transition: 0.3s; }
        .btn-primary:hover { background: #3512D9; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(67, 24, 255, 0.3); }
        .btn-light { background: var(--bg-body); border: 1px solid var(--border); color: var(--text-muted); border-radius: 12px; padding: 12px 25px; font-weight: 700; }
        
        .section-title { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 25px 0 15px 10px; opacity: 0.7; }
        .label-muted { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div class="section-title">Dispatching</div>
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text me-2"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="internalSub" class="submenu show">
                <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h3 class="fw-bold mb-1">Final Disposal Routing</h3>
                <p style="color: var(--text-muted);" class="small mb-0"><i class="fa-solid fa-box-archive me-1"></i> Document completion and archival workflow.</p>
            </div>
            <div class="d-flex gap-2">
                 <button onclick="window.location.reload()" class="btn btn-white border-0" style="color: var(--text-muted);"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
    </header>

    <div class="form-container">
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-3 d-flex align-items-center">
                <i class="fa-solid fa-circle-exclamation me-3"></i>
                <div class="fw-bold"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <div class="row align-items-center mb-3">
                <div class="col-md-3">
                    <span class="label-muted">Letter Reference</span>
                </div>
                <div class="col-md-9">
                    <span class="cdr-pill"><?= htmlspecialchars($letter['cdr_number']) ?></span>
                </div>
            </div>
            <div class="row align-items-start">
                <div class="col-md-3">
                    <span class="label-muted">Letter Title</span>
                </div>
                <div class="col-md-9">
                    <h5 class="fw-bold text-primary mb-0"><?= htmlspecialchars($letter['letter_title']) ?></h5>
                </div>
            </div>
        </div>

        <form method="POST" action="">
            <div class="row g-4">
                <div class="col-md-12">
                    <label for="forward_to_desk" class="form-label">Destination Storage Desk</label>
                    <div class="input-group">
                        <span class="input-group-text bg-body border-end-0" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-building-user text-primary"></i></span>
                        <select name="forward_to_desk" id="forward_to_desk" class="form-select border-start-0" required style="border-radius: 0 12px 12px 0;">
                            <option value="">-- Choose target desk for filing --</option>
                            <?php if ($desks_result): ?>
                                <?php while($desk = $desks_result->fetch_assoc()): ?>
                                    <option value="<?= $desk['id'] ?>">
                                        <?= htmlspecialchars($desk['desk_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-12">
                    <label for="remarks" class="form-label">Final Instructions / Disposal Remarks</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="5" placeholder="Filed the Correct Reference."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top border-opacity-10">
                <a href="../inward/internal_letters.php" class="btn btn-light px-4">
                    <i class="fa-solid fa-chevron-left me-2"></i> Back to Registry
                </a>
                <button type="submit" class="btn btn-primary px-5 shadow-sm">
                    <i class="fa-solid fa-check-double me-2"></i> Submit for Disposal
                </button>
            </div>
        </form>
    </div>
</main>

<script>
    function toggleSubmenu(id) {
        document.getElementById(id).classList.toggle('show');
    }
    
    window.onload = function() {
        const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    };
</script>

</body>
</html>