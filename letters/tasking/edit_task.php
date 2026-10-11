<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Permission check
if (!hasPermission('EDIT_TASK_LETTERS') && !hasPermission('VIEW_TASK_LETTERS')) {
    die('Access Denied!');
}

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$task_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

/* ==========================
   FETCH TASK DETAILS
========================== */
$stmt = $conn->prepare("SELECT * FROM task_letters WHERE id = ?");
if (!$stmt) die("DB Error: " . $conn->error);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
if (!$task) die("Task not found");

/* ==========================
   FETCH ALL REGIMENTS / AGENCIES
========================== */
$agencies_query = $conn->query("SELECT id, regiment_name FROM regiments ORDER BY regiment_name ASC");

/* ==========================
   FETCH CURRENTLY ASSIGNED AGENCIES
========================== */
$assigned_stmt = $conn->prepare("SELECT regiment_id FROM task_letter_agencies WHERE task_letter_id = ?");
$assigned_stmt->bind_param("i", $task_id);
$assigned_stmt->execute();
$assigned_res = $assigned_stmt->get_result();
$current_agencies = [];
while ($row = $assigned_res->fetch_assoc()) {
    $current_agencies[] = $row['regiment_id'];
}

/* ==========================
   HANDLE FORM SUBMISSION
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $letter_ref      = trim($_POST['letter_ref'] ?? '');
    $tsk_cdr_number  = !empty($_POST['tsk_cdr_number']) ? trim($_POST['tsk_cdr_number']) : NULL;
    $letter_date     = $_POST['letter_date'] ?? '';
    $request_date    = !empty($_POST['request_date']) ? $_POST['request_date'] : NULL;
    $title           = trim($_POST['title'] ?? '');
    $description     = !empty($_POST['description']) ? trim($_POST['description']) : NULL;
    $priority_status = $_POST['priority_status'] ?? 'Medium';
    $about_letter    = !empty($_POST['about_letter']) ? trim($_POST['about_letter']) : NULL;
    $comments        = !empty($_POST['comments']) ? trim($_POST['comments']) : NULL;
    $status          = $_POST['status'] ?? 'Pending';
    $selected_agencies = $_POST['agencies'] ?? [];

    $file_path = $task['file_path']; // Keep existing file path by default

    // File Upload Handling
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['task_file']['tmp_name'];
        $file_name = $_FILES['task_file']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = '../../uploads/tasks/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = 'task_' . $task_id . '_' . time() . '.' . $file_ext;
            $destination   = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $file_path = 'uploads/tasks/' . $new_file_name;
            } else {
                $error_msg = "Failed to upload file.";
            }
        } else {
            $error_msg = "Invalid file type. Allowed: PDF, DOC, DOCX, PNG, JPG.";
        }
    }

    if (empty($error_msg)) {
        // Update task_letters
        $update_stmt = $conn->prepare("
            UPDATE task_letters SET 
                letter_ref = ?, 
                tsk_cdr_number = ?, 
                letter_date = ?, 
                request_date = ?, 
                title = ?, 
                description = ?, 
                priority_status = ?, 
                about_letter = ?, 
                comments = ?, 
                status = ?, 
                file_path = ?
            WHERE id = ?
        ");

        $update_stmt->bind_param(
            "sssssssssssi",
            $letter_ref,
            $tsk_cdr_number,
            $letter_date,
            $request_date,
            $title,
            $description,
            $priority_status,
            $about_letter,
            $comments,
            $status,
            $file_path,
            $task_id
        );

        if ($update_stmt->execute()) {
            // Sync Task Letter Agencies
            $del_agencies = $conn->prepare("DELETE FROM task_letter_agencies WHERE task_letter_id = ?");
            $del_agencies->bind_param("i", $task_id);
            $del_agencies->execute();

            if (!empty($selected_agencies)) {
                $ins_agency = $conn->prepare("INSERT INTO task_letter_agencies (task_letter_id, regiment_id) VALUES (?, ?)");
                foreach ($selected_agencies as $regiment_id) {
                    $reg_id_int = intval($regiment_id);
                    $ins_agency->bind_param("ii", $task_id, $reg_id_int);
                    $ins_agency->execute();
                }
            }

            $success_msg = "Task updated successfully!";

            // Refresh task array & assigned agencies
            $stmt->execute();
            $task = $stmt->get_result()->fetch_assoc();
            
            $assigned_stmt->execute();
            $assigned_res = $assigned_stmt->get_result();
            $current_agencies = [];
            while ($row = $assigned_res->fetch_assoc()) {
                $current_agencies[] = $row['regiment_id'];
            }
        } else {
            $error_msg = "Database Update Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; 
            --sidebar-bg: #FFFFFF; 
            --card-bg: #FFFFFF;
            --accent: #4318FF; 
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #2B3674;
            --text-muted: #707EAE;
            --border: #DDE2EF;
            --sidebar-width: 280px;
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
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            font-family: "Segoe UI", sans-serif;
            transition: 0.3s;
            margin: 0;
        }

        /* Sidebar */
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

        .details-container {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 30px;
            margin: 0 30px 30px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Dark Mode Text Fixes */
        .form-label, .form-check-label {
            color: var(--text-main) !important;
        }
        .text-muted {
            color: var(--text-muted) !important;
        }
        .text-main {
            color: var(--text-main) !important;
        }

        .form-control, .form-select {
            background-color: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 10px;
            padding: 0.6rem 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem var(--accent-soft);
            background-color: var(--bg-body);
            color: var(--text-main);
        }

        /* Checkbox Box Styling in Dark Mode */
        .agency-box {
            max-height: 220px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background-color: var(--bg-body);
        }

        .attachment-preview-box {
            background-color: var(--bg-body) !important;
            border-color: var(--border) !important;
            color: var(--text-main);
        }

        .btn-primary-custom {
            background-color: var(--accent);
            border-color: var(--accent);
            color: #fff;
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-primary-custom:hover {
            opacity: 0.9;
            color: #fff;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="brand-box mb-4 px-2">
            <div class="brand-name">
                <i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br>
                <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
            </div>
        </div>
        <nav>
            <a href="../dashboard/dashboard.php" class="nav-btn">
                <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
            </a>
            <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">
                Dispatching
            </div>
            
            <div>
                <div class="nav-btn" onclick="toggleSubmenu('internalSub')">
                    <i class="fa-solid fa-envelope-open-text me-2"></i> Internal Letters
                    <i class="fa-solid fa-chevron-down ms-auto small"></i>
                </div>
                <div id="internalSub" class="submenu">
                    <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                    <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                    <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
                </div>
            </div>

            <div>
                <div class="nav-btn active" onclick="toggleSubmenu('taskSub')">
                    <i class="fa-solid fa-tasks me-2"></i> Task Letters
                    <i class="fa-solid fa-chevron-down ms-auto small"></i>
                </div>
                <div id="taskSub" class="submenu show">
                    <a href="task_list.php" class="sub-nav-btn">View Tasking</a>               
                    <a href="task_details.php?id=<?= $task_id ?>" class="sub-nav-btn">Current Task</a>
                    <a href="edit_task.php?id=<?= $task_id ?>" class="sub-nav-btn active-sub">Edit Task</a>
                </div>
            </div>

            <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-nav d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-main">Edit Task: <?= htmlspecialchars($task['letter_ref']) ?></h4>
        </header>

        <div class="details-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="task_details.php?id=<?= $task_id ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Task Details
                </a>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-pen-to-square text-primary me-2"></i> General Task Information
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Letter Reference <span class="text-danger">*</span></label>
                                <input type="text" name="letter_ref" class="form-control" value="<?= htmlspecialchars($task['letter_ref']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">CDR Number (TSK CDR)</label>
                                <input type="text" name="tsk_cdr_number" class="form-control" value="<?= htmlspecialchars($task['tsk_cdr_number'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Letter Date <span class="text-danger">*</span></label>
                                <input type="date" name="letter_date" class="form-control" value="<?= $task['letter_date'] ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Request / Due Date</label>
                                <input type="date" name="request_date" class="form-control" value="<?= $task['request_date'] ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Priority Status</label>
                                <select name="priority_status" class="form-select">
                                    <option value="Low" <?= $task['priority_status'] === 'Low' ? 'selected' : '' ?>>Low</option>
                                    <option value="Medium" <?= $task['priority_status'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="High" <?= $task['priority_status'] === 'High' ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Overall Task Status</label>
                                <select name="status" class="form-select">
                                    <option value="Pending" <?= $task['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Partially Replied" <?= $task['status'] === 'Partially Replied' ? 'selected' : '' ?>>Partially Replied</option>
                                    <option value="Completed" <?= $task['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Task Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">About Letter</label>
                                <textarea name="about_letter" class="form-control" rows="2"><?= htmlspecialchars($task['about_letter'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Comments</label>
                                <textarea name="comments" class="form-control" rows="2"><?= htmlspecialchars($task['comments'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-building text-primary me-2"></i> Assign Agencies & Attachments
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small mb-2">Assigned Agencies (Regiments)</label>
                                <div class="agency-box">
                                    <?php 
                                    if ($agencies_query && $agencies_query->num_rows > 0): 
                                        while ($agency = $agencies_query->fetch_assoc()):
                                            $is_checked = in_array($agency['id'], $current_agencies) ? 'checked' : '';
                                    ?>
                                        <div class="form-check py-1">
                                            <input class="form-check-input" type="checkbox" name="agencies[]" value="<?= $agency['id'] ?>" id="reg_<?= $agency['id'] ?>" <?= $is_checked ?>>
                                            <label class="form-check-label" for="reg_<?= $agency['id'] ?>">
                                                <?= htmlspecialchars($agency['regiment_name']) ?>
                                            </label>
                                        </div>
                                    <?php 
                                        endwhile; 
                                    else: 
                                    ?>
                                        <p class="text-muted small mb-0">No agencies available in database.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small mb-2">Task Document Attachment</label>
                                <?php if (!empty($task['file_path'])): ?>
                                    <div class="mb-3 p-3 border rounded-3 attachment-preview-box d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-file-pdf text-danger me-2 fa-lg"></i>
                                            <span class="small text-truncate d-inline-block" style="max-width:200px;">
                                                <?= basename($task['file_path']) ?>
                                            </span>
                                        </div>
                                        <a href="../../<?= htmlspecialchars($task['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <label class="form-label small text-muted">Upload New Document (Replaces current file)</label>
                                <input type="file" name="task_file" class="form-control" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="task_details.php?id=<?= $task_id ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-save me-2"></i> Save Task Changes
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const savedTheme = localStorage.getItem('dispatch_theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const icon = submenu.previousElementSibling.querySelector('.fa-chevron-down');
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
    </script>
</body>
</html>