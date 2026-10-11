<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('EDIT_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

// Ensure required columns exist
function ensure_outward_columns($conn) {
    $columns = [
        'approval_status' => "ALTER TABLE outward_letters ADD COLUMN approval_status ENUM('Draft','Pending RA Approval','Approved','Rejected') DEFAULT 'Draft'",
        'is_deleted'      => "ALTER TABLE outward_letters ADD COLUMN is_deleted TINYINT(1) DEFAULT 0",
        'approved_by'     => "ALTER TABLE outward_letters ADD COLUMN approved_by INT(11) NULL",
        'approved_at'     => "ALTER TABLE outward_letters ADD COLUMN approved_at DATETIME NULL",
        'rejected_by'     => "ALTER TABLE outward_letters ADD COLUMN rejected_by INT(11) NULL",
        'rejected_at'     => "ALTER TABLE outward_letters ADD COLUMN rejected_at DATETIME NULL",
        'submitted_at'    => "ALTER TABLE outward_letters ADD COLUMN submitted_at DATETIME NULL",
        'dispatch_remarks'=> "ALTER TABLE outward_letters ADD COLUMN dispatch_remarks TEXT NULL"
    ];
    foreach ($columns as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM outward_letters LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            $conn->query($sql) or error_log("Failed to add column $col: " . $conn->error);
        }
    }
}
ensure_outward_columns($conn);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: outward_list.php?error=invalid_id');
    exit;
}

// Fetch letter data
$stmt = $conn->prepare("SELECT * FROM outward_letters WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header('Location: outward_list.php?error=not_found');
    exit;
}
$letter = $result->fetch_assoc();
$stmt->close();

// Check if completed – we'll use this to disable editing
$is_completed = ($letter['status'] == 'Completed');

// Fetch all external branches for the receiver multi-select
$branches = $conn->query("SELECT id, branch_name FROM external_branches ORDER BY branch_name ASC");
$allBranches = [];
while ($b = $branches->fetch_assoc()) {
    $allBranches[] = $b;
}

// Current receiver IDs (stored as comma-separated string in 'receiver' field)
$currentReceivers = array_filter(explode(',', $letter['receiver'] ?? ''));

// Handle form submission
$update_message = '';
$update_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // Prevent editing if completed
    if ($is_completed) {
        $update_message = 'This letter is already Completed and cannot be edited.';
        $update_type = 'warning';
    } else {
        // Validate and sanitize
        // CDR No is NOT updated – it remains permanent.
        $letter_ref = trim($_POST['letter_ref']);
        $title = trim($_POST['title']);
        $letter_date = $_POST['letter_date'];
        $receiver_ids = isset($_POST['receiver']) ? array_map('intval', $_POST['receiver']) : [];
        $receiver_str = implode(',', $receiver_ids);
        $dispatch_method = $_POST['dispatch_method'];
        $description = trim($_POST['description']);
        $comment = trim($_POST['comment']); // corrected column name
        $file_path = $letter['file_path']; // keep existing by default

        // Handle file upload (optional)
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $upload_dir = '../../uploads/outward/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $new_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
                // Delete old file if exists
                if (!empty($letter['file_path']) && file_exists('../../' . $letter['file_path'])) {
                    unlink('../../' . $letter['file_path']);
                }
                $file_path = 'uploads/outward/' . $new_name;
            } else {
                $update_message = 'Failed to upload file.';
                $update_type = 'warning';
            }
        }

        if (empty($title) || empty($letter_date) || empty($receiver_ids)) {
            $update_message = 'Please fill in all required fields (Title, Letter Date, and Receiver).';
            $update_type = 'warning';
        } else {
            // Update query – CDR No is NOT included
            $update_stmt = $conn->prepare("
                UPDATE outward_letters 
                SET letter_ref = ?, title = ?, letter_date = ?, receiver = ?, 
                    dispatch_method = ?, description = ?, comment = ?, file_path = ?
                WHERE id = ? AND is_deleted = 0
            ");
            $update_stmt->bind_param("ssssssssi", $letter_ref, $title, $letter_date, $receiver_str,
                                      $dispatch_method, $description, $comment, $file_path, $id);
            if ($update_stmt->execute()) {
                $update_message = 'Letter updated successfully.';
                $update_type = 'success';
                // Refresh letter data
                $refresh_stmt = $conn->prepare("SELECT * FROM outward_letters WHERE id = ? AND is_deleted = 0");
                $refresh_stmt->bind_param("i", $id);
                $refresh_stmt->execute();
                $refresh_res = $refresh_stmt->get_result();
                $letter = $refresh_res->fetch_assoc();
                $refresh_stmt->close();
                $is_completed = ($letter['status'] == 'Completed');
            } else {
                $update_message = 'Failed to update: ' . $conn->error;
                $update_type = 'danger';
            }
            $update_stmt->close();
        }
    }
}

// Determine if fields should be disabled (for completed) – CDR is always disabled
$disabled = $is_completed ? 'disabled' : '';
$cdr_disabled = 'disabled'; // CDR is always read-only

// Fetch creator name for display
$creator_stmt = $conn->prepare("SELECT name_with_initial FROM users WHERE id = ?");
$creator_stmt->bind_param("i", $letter['created_by']);
$creator_stmt->execute();
$creator_res = $creator_stmt->get_result();
$creator_name = $creator_res->fetch_assoc()['name_with_initial'] ?? 'Unknown';
$creator_stmt->close();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Outward Letter | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67,24,255,0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px; --transition-speed: 0.3s;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117,81,255,0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255,255,255,0.1);
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; }
        body { background:var(--bg-body); color:var(--text-main); min-height:100vh; display:flex; overflow-x:hidden; transition:background var(--transition-speed), color var(--transition-speed); }
        .sidebar { width:var(--sidebar-width); background:var(--sidebar-bg); border-right:1px solid var(--border); padding:30px 15px; position:fixed; height:100vh; overflow-y:auto; z-index:100; transition:0.3s; }
        .brand-box { padding:0 10px; margin-bottom:40px; }
        .brand-name { font-weight:800; font-size:1.2rem; line-height:1.2; color:var(--text-main); }
        .nav-btn { display:flex; align-items:center; padding:12px 15px; color:var(--text-muted); text-decoration:none; border-radius:12px; font-weight:700; font-size:0.85rem; transition:0.2s; margin-bottom:5px; border:none; background:transparent; width:100%; text-align:left; }
        .nav-btn:hover, .nav-btn.active { background:var(--accent-soft); color:var(--accent); }
        .nav-btn i { width:20px; font-size:1.1rem; margin-right:12px; }
        .submenu { padding-left:20px; margin-bottom:10px; display:none; }
        .submenu.show { display:block; }
        .sub-nav-btn { display:block; padding:8px 15px; color:var(--text-muted); text-decoration:none; font-size:0.8rem; font-weight:600; border-left:2px solid var(--border); transition:0.2s; }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub { color:var(--accent); border-left-color:var(--accent); background:transparent; }
        .sidebar-divider { font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin:25px 0 10px 15px; opacity:0.5; letter-spacing:1px; }
        .main-content { margin-left:var(--sidebar-width); width:calc(100% - var(--sidebar-width)); padding:40px; display:flex; flex-direction:column; gap:30px; min-height:100vh; background:var(--bg-body); }
        .top-nav { background:var(--card-bg); border:1px solid var(--border); border-radius:24px; padding:20px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 20px rgba(0,0,0,0.02); }
        .content-container { flex:1; }
        .card-form { background:var(--card-bg); border:1px solid var(--border); border-radius:20px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.05); }
        .form-label { font-weight:600; font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
        .form-control { background:var(--bg-body); border:1px solid var(--border); border-radius:12px; padding:10px 14px; color:var(--text-main); font-size:0.9rem; transition:0.2s; }
        .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .form-control:disabled { opacity:0.6; }
        .btn-primary { background:var(--accent); color:white; border:none; border-radius:12px; padding:10px 28px; font-weight:700; font-size:0.9rem; transition:0.2s; }
        .btn-primary:hover { opacity:0.9; transform:translateY(-2px); color:white; }
        .btn-secondary { background:transparent; border:1px solid var(--border); color:var(--text-muted); border-radius:12px; padding:10px 28px; font-weight:700; font-size:0.9rem; text-decoration:none; }
        .btn-secondary:hover { border-color:var(--accent); color:var(--accent); }
        .receivers-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:8px; background:var(--bg-body); border-radius:12px; padding:12px; border:1px solid var(--border); }
        .receivers-grid .form-check { padding:4px 0; }
        .receivers-grid .form-check-input:checked { background-color:var(--accent); border-color:var(--accent); }
        .mobile-menu-toggle { position:fixed; top:20px; left:20px; z-index:1060; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; padding:10px; cursor:pointer; display:none; }
        @media (max-width:768px) {
            .sidebar { transform:translateX(-100%); position:fixed; }
            .sidebar.show { transform:translateX(0); }
            .main-content { margin-left:0; width:100%; }
            .mobile-menu-toggle { display:block; }
            .top-nav { flex-direction:column; align-items:stretch; gap:10px; }
        }
        input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1); cursor:pointer; }
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1) brightness(10); }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br>
            <span style="font-size:0.7rem;color:var(--text-muted);">EDIT LETTER</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-house"></i> Home Page</a>
        <div style="font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;margin:25px 0 10px 15px;opacity:0.6;letter-spacing:1px;">Dispatching</div>
        <button class="nav-btn active" onclick="toggleSubmenu('outwardSub')">
            <i class="fa-solid fa-paper-plane"></i> Outward Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
        </button>
        <div id="outwardSub" class="submenu show">
            <a href="add_outward.php" class="sub-nav-btn">Add Outward</a>
            <a href="outward_list.php" class="sub-nav-btn active-sub">View Registry</a>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-power-off"></i> Logout</a>
    </nav>
</aside>
<div class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></div>

<main class="main-content">
    <header class="top-nav">
        <div>
            <h4 class="fw-bold mb-0">Edit External Letter</h4>
            <p class="small --text-muted mb-0"><?= htmlspecialchars($letter['cdr_no']) ?> – <?= htmlspecialchars($letter['title']) ?></p>
        </div>
        <a href="outward_list.php" class="btn-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Registry</a>
    </header>

    <div class="content-container">
        <?php if (!empty($update_message)): ?>
            <div class="alert alert-<?= $update_type ?> alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= $update_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($is_completed): ?>
            <div class="alert alert-warning rounded-4 border-0 shadow-sm">
                <i class="fa-solid fa-lock me-2"></i> <strong>This letter is marked as Completed.</strong> You cannot edit it. All fields are read-only.
            </div>
        <?php endif; ?>

        <div class="card-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i> CDR No <span class="text-danger">(permanent)</span></label>
                        <input type="text" name="cdr_no" class="form-control" value="<?= htmlspecialchars($letter['cdr_no']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-file-lines me-1"></i> Letter Reference</label>
                        <input type="text" name="letter_ref" class="form-control" value="<?= htmlspecialchars($letter['letter_ref']) ?>" <?= $disabled ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-heading me-1"></i> Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($letter['title']) ?>" required <?= $disabled ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-calendar me-1"></i> Letter Date <span class="text-danger">*</span></label>
                        <input type="date" name="letter_date" class="form-control" value="<?= htmlspecialchars($letter['letter_date']) ?>" required <?= $disabled ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fa-solid fa-building me-1"></i> Receivers (External Branches) <span class="text-danger">*</span></label>
                        <div class="receivers-grid">
                            <?php foreach ($allBranches as $branch): 
                                $checked = in_array($branch['id'], $currentReceivers) ? 'checked' : '';
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="receiver[]" value="<?= $branch['id'] ?>" id="rec<?= $branch['id'] ?>" <?= $checked ?> <?= $disabled ?>>
                                    <label class="form-check-label" for="rec<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-truck me-1"></i> Dispatch Method</label>
                        <select name="dispatch_method" class="form-control" <?= $disabled ?>>
                            <option value="Email" <?= $letter['dispatch_method'] == 'Email' ? 'selected' : '' ?>>Email</option>
                            <option value="By Hand" <?= $letter['dispatch_method'] == 'By Hand' ? 'selected' : '' ?>>By Hand</option>
                            <option value="Courier" <?= $letter['dispatch_method'] == 'Courier' ? 'selected' : '' ?>>Courier</option>
                            <option value="Post" <?= $letter['dispatch_method'] == 'Post' ? 'selected' : '' ?>>Post</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fa-solid fa-user me-1"></i> Created By</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($creator_name) ?>" disabled>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fa-solid fa-align-left me-1"></i> Description</label>
                        <textarea name="description" class="form-control" rows="3" <?= $disabled ?>><?= htmlspecialchars($letter['description']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fa-solid fa-comment me-1"></i> Comment</label>
                        <textarea name="comment" class="form-control" rows="2" <?= $disabled ?>><?= htmlspecialchars($letter['comment'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fa-solid fa-paperclip me-1"></i> Attachment</label>
                        <?php if (!empty($letter['file_path'])): ?>
                            <div class="mb-2">
                                <a href="../../<?= htmlspecialchars($letter['file_path']) ?>" target="_blank" class="text-primary">
                                    <i class="fa-solid fa-file me-1"></i> View Current File
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="attachment" class="form-control" <?= $disabled ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                        <small class="--text-muted">Leave blank to keep current file. Max size: 10MB</small>
                    </div>
                    <div class="col-12 d-flex gap-3 mt-3">
                        <?php if (!$is_completed): ?>
                            <button type="submit" name="update" class="btn-primary"><i class="fa-solid fa-save me-2"></i>Update Letter</button>
                        <?php else: ?>
                            <button type="button" class="btn-primary" disabled style="opacity:0.6;cursor:not-allowed;"><i class="fa-solid fa-lock me-2"></i>Locked</button>
                        <?php endif; ?>
                        <a href="outward_list.php" class="btn-secondary"><i class="fa-solid fa-times me-2"></i>Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('show');
    }
    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        if(sub) sub.classList.toggle('show');
    }
    document.querySelectorAll('.alert').forEach(a => {
        setTimeout(() => { a.style.opacity='0'; setTimeout(()=>a.remove(),500); }, 6000);
    });
</script>
</body>
</html>