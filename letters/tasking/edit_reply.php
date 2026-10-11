<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

$userId   = $_SESSION['user_id'] ?? 0;
$userRank = $_SESSION['rank'] ?? '';
$userName = $_SESSION['name'] ?? '';

// Ensure CSRF Token is set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Accept both 'id' or 'task_id' securely to prevent missing parameter errors
$task_id = 0;
if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $task_id = intval($_GET['id']);
} elseif (isset($_GET['task_id']) && intval($_GET['task_id']) > 0) {
    $task_id = intval($_GET['task_id']);
}

if ($task_id <= 0) {
    header("Location: task_list.php?error=" . urlencode("Error: Task ID is required."));
    exit;
}

// Fetch Task Details
$stmt = $conn->prepare("
    SELECT t.*, a.agency_name 
    FROM task_letters t
    LEFT JOIN agencies a ON t.agency_id = a.id
    WHERE t.id = ?
");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
$stmt->close();

if (!$task) {
    header("Location: task_list.php?error=" . urlencode("Task record not found."));
    exit;
}

$error_message   = '';
$success_message = '';

// Handle Form Submission for New Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid security token. Please try again.";
    } else {
        $reply_text   = trim($_POST['reply_text'] ?? '');
        $reply_status = trim($_POST['status'] ?? 'Pending');
        $remarks      = trim($_POST['remarks'] ?? '');
        $agency_id    = $task['agency_id'];

        if (empty($reply_text)) {
            $error_message = "Reply text cannot be empty.";
        } else {
            $attachment_path = '';
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath   = $_FILES['attachment']['tmp_name'];
                $fileName      = $_FILES['attachment']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    $uploadFileDir = '../../uploads/task_replies/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $newFileName = 'reply_t_' . $task_id . '_' . time() . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $attachment_path = 'uploads/task_replies/' . $newFileName;
                    } else {
                        $error_message = "Error moving the uploaded file.";
                    }
                } else {
                    $error_message = "Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, PNG, ZIP.";
                }
            }

            if (empty($error_message)) {
                $insert_stmt = $conn->prepare("
                    INSERT INTO task_replies (task_letter_id, agency_id, reply_text, status, remarks, attachment_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $insert_stmt->bind_param("iissss", $task_id, $agency_id, $reply_text, $reply_status, $remarks, $attachment_path);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Reply submitted successfully!";
                } else {
                    $error_message = "Database error: " . $conn->error;
                }
                $insert_stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply to Task | LDMS</title>
    <?php
    $local_css = '../../assets/css/bootstrap.min.css';
    $local_fa  = '../../assets/css/all.min.css';
    echo file_exists($local_css) 
        ? '<link href="' . $local_css . '" rel="stylesheet">' 
        : '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo file_exists($local_fa) 
        ? '<link href="' . $local_fa . '" rel="stylesheet">' 
        : '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
    ?>
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
            background-color: var(--bg-body); color: var(--text-main);
            display: flex; min-height: 100vh; font-family: 'Inter', 'Segoe UI', sans-serif;
            transition: 0.3s; margin: 0;
        }
        .sidebar {
            width: var(--sidebar-width); background: var(--sidebar-bg);
            border-right: 1px solid var(--border); padding: 30px 15px; position: fixed;
            height: 100vh; overflow-y: auto; z-index: 100;
        }
        .brand-name { font-weight: 800; font-size: 1.2rem; color: var(--text-main); line-height: 1.2; }
        .nav-btn {
            display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted);
            text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
            transition: 0.2s; width: 100%; margin-bottom: 5px;
        }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .main-content {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            display: flex; flex-direction: column;
        }
        .top-nav {
            background: var(--bg-body); padding: 20px 30px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 90; backdrop-filter: blur(10px);
        }
        .content-container { padding: 30px; max-width: 900px; margin: 0 auto; width: 100%; }
        .form-card { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        .form-control, .form-select {
            background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px;
            padding: 12px 15px; color: var(--text-main); font-size: 0.9rem; width: 100%; transition: 0.2s;
        }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .btn-submit { background: var(--accent); color: white; border: none; border-radius: 12px; padding: 12px 25px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-back { background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 12px; padding: 12px 25px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: 0.2s; display: inline-block; }
        .btn-back:hover { border-color: var(--accent); color: var(--accent); }
        .task-context-box { background: var(--accent-soft); border-radius: 14px; padding: 15px 20px; margin-bottom: 25px; border-left: 4px solid var(--accent); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px;">Task Management</div>
        <a href="task_dashboard.php" class="nav-btn"><i class="fa-solid fa-chart-pie me-2"></i> Dashboard</a>
        <a href="task_list.php" class="nav-btn active"><i class="fa-solid fa-list-check me-2"></i> View All Tasks</a>
        <a href="task_replies.php" class="nav-btn"><i class="fa-solid fa-reply-all me-2"></i> All Replies</a>
        <a href="pending_replies.php" class="nav-btn"><i class="fa-solid fa-hourglass-half me-2"></i> Pending Replies</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div>
            <h4 class="fw-bold mb-0">Submit Task Reply</h4>
            <p class="text-muted small mb-0">Provide official feedback or compliance documents for assigned task</p>
        </div>
        <div class="text-end d-none d-md-block">
            <div class="fw-bold"><?= htmlspecialchars($userRank . ' ' . $userName) ?></div>
        </div>
    </header>

    <div class="content-container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger rounded-4 mb-4"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success rounded-4 mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <div class="task-context-box">
                <small class="text-muted d-block text-uppercase fw-bold mb-1">Target Task Details</small>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($task['tsk_cdr_number'] ?: $task['letter_ref']) ?>: <?= htmlspecialchars($task['title']) ?></h5>
                <p class="mb-0 text-muted small"><i class="fa-regular fa-building me-1"></i> Assigned Agency: <strong><?= htmlspecialchars($task['agency_name'] ?? 'N/A') ?></strong> | <i class="fa-regular fa-calendar me-1"></i> Deadline: <strong><?= htmlspecialchars($task['deadline'] ?? 'None') ?></strong></p>
            </div>

            <form action="reply_task.php?id=<?= $task_id ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="mb-4">
                    <label class="form-label">Reply Message / Details *</label>
                    <textarea name="reply_text" rows="5" class="form-control" placeholder="Type your official response or report summary here..." required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Status Update</label>
                        <select name="status" class="form-select">
                            <option value="Pending">Pending Review</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-4">
                        <label class="form-label">Attachment Document (Optional)</label>
                        <input type="file" name="attachment" class="form-control">
                        <small class="text-muted" style="font-size: 0.7rem;">Allowed: PDF, DOC, DOCX, JPG, PNG, ZIP</small>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Additional Remarks</label>
                    <textarea name="remarks" rows="2" class="form-control" placeholder="Any internal notes or contextual remarks..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="task_list.php" class="btn-back"><i class="fa-solid fa-arrow-left me-2"></i>Back to Tasks</a>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane me-2"></i>Submit Reply</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
</script>

<?php
$local_js = '../../assets/js/bootstrap.bundle.min.js';
echo file_exists($local_js) 
    ? '<script src="' . $local_js . '"></script>' 
    : '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
?>
</body>
</html>