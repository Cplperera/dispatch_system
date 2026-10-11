<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_TASK_LETTERS')) {
    die('Access Denied!');
}

/* ===============================
    GENERATE TSK NUMBER
================================ */
function generateTSK($conn)
{
    $year = date('Y');
    $prefix = 'TSK';

    $sql = "SELECT tsk_cdr_number FROM task_letters WHERE tsk_cdr_number LIKE '{$prefix}/{$year}/%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    if(!$res) return "{$prefix}/{$year}/01"; 
    $last = $res->fetch_assoc();
    $num = $last ? ((int)explode('/', $last['tsk_cdr_number'])[2] + 1) : 1;

    return sprintf('%s/%s/%02d', $prefix, $year, $num);
}

$tsk_display = generateTSK($conn);
$error = $success = '';
$current_user_id = $_SESSION['user_id'];

/* ===============================
    HANDLE FORM SUBMISSION
================================ */
if (isset($_POST['submit'])) {
    $tsk_cdr_number  = $_POST['tsk_cdr_number']; 
    $letter_ref      = str_replace(' ', '', $_POST['letter_ref']);
    $letter_date     = $_POST['letter_date'];
    $request_date    = !empty($_POST['request_date']) ? $_POST['request_date'] : null;
    $title           = trim($_POST['title']);
    
    // Captured all fields matching your DB structure
    $description     = isset($_POST['description']) ? trim($_POST['description']) : null;
    $priority_status = $_POST['priority_status'] ?: 'Medium';
    $about_letter    = isset($_POST['about_letter']) ? trim($_POST['about_letter']) : null;
    $comments        = isset($_POST['comments']) ? trim($_POST['comments']) : null;
    
    $assigned_by     = $current_user_id;
    $status          = 'Pending';
    
    $assigned_regiments = $_POST['regiments'] ?? [];
    $linked_letters     = $_POST['internal_letters'] ?? []; 

    $check = $conn->prepare("SELECT id FROM task_letters WHERE letter_ref = ?");
    $check->bind_param("s", $letter_ref);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "This Reference Number has already been registered.";
    }

    if (!$error) {
        $file_path = null;
        if (!empty($_FILES['letter_file']['name'])) {
            $uploadDir = '../../uploads/tasks/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($_FILES['letter_file']['name']));
            if (move_uploaded_file($_FILES['letter_file']['tmp_name'], $uploadDir . $filename)) {
                $file_path = 'uploads/tasks/' . $filename;
            }
        }

        $stmt = $conn->prepare("INSERT INTO task_letters 
            (tsk_cdr_number, letter_ref, letter_date, request_date, title, description, priority_status, about_letter, comments, file_path, assigned_by, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssss", $tsk_cdr_number, $letter_ref, $letter_date, $request_date, $title, $description, $priority_status, $about_letter, $comments, $file_path, $assigned_by, $status);

        if ($stmt->execute()) {
            $task_id = $conn->insert_id;
            if (!empty($assigned_regiments)) {
                $agencyStmt = $conn->prepare("INSERT INTO task_letter_agencies (task_letter_id, regiment_id) VALUES (?, ?)");
                foreach ($assigned_regiments as $reg_id) {
                    $agencyStmt->bind_param("ii", $task_id, $reg_id);
                    $agencyStmt->execute();
                }
            }
            if (!empty($linked_letters)) {
                $linkStmt = $conn->prepare("INSERT INTO task_internal_links (task_id, internal_letter_id) VALUES (?, ?)");
                foreach ($linked_letters as $int_id) {
                    $linkStmt->bind_param("ii", $task_id, $int_id);
                    $linkStmt->execute();
                }
            }
            $success = 'Task Letter registered successfully: ' . $tsk_cdr_number;
            $tsk_display = generateTSK($conn);
        } else { $error = 'Database Error: ' . $conn->error; }
    }
}

$regiments_q = $conn->query("SELECT id, regiment_name FROM regiments WHERE status='Active' ORDER BY regiment_name");
$internals_q = $conn->query("SELECT id, cdr_number, letter_reference FROM internal_letters WHERE is_deleted=0 ORDER BY id DESC LIMIT 200");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Task Letter | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px; --ph: #A3AED0;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1); --ph: #cbd5e0;
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; margin: 0; }
        ::placeholder { color: var(--ph) !important; opacity: 1; }

        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; transition: 0.3s; }
        .brand-box { padding: 0 10px; margin-bottom: 40px; }
        .brand-name { font-weight: 800; font-size: 1.2rem; line-height: 1.2; color: var(--text-main); }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; margin-bottom: 5px; border: none; background: transparent; width: 100%; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .nav-btn i { width: 20px; font-size: 1.1rem; margin-right: 12px; }
        
        .submenu { padding-left: 20px; margin-bottom: 10px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); background: transparent; }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; min-height: 100vh; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; display: flex; align-items: center; }
        .form-container { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border); padding: 35px; margin: 0 30px 30px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .form-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; letter-spacing: 0.5px; }
        .form-control, .form-select { background-color: var(--bg-body) !important; border: 1px solid var(--border) !important; color: var(--text-main) !important; border-radius: 12px !important; padding: 12px 15px !important; font-size: 0.9rem; }
        
        .search-list-container { background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-top: 5px; }
        .list-search-input { border: none !important; border-bottom: 1px solid var(--border) !important; border-radius: 0 !important; font-size: 0.85rem !important; }
        .list-items-box { max-height: 160px; overflow-y: auto; padding: 5px; }
        .list-item { display: flex; align-items: center; padding: 7px 12px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: 0.2s; color: var(--text-main); }
        .list-item:hover { background: var(--accent-soft); }
        .list-item input { margin-right: 12px; accent-color: var(--accent); width: 16px; height: 16px; }

        .readonly-box { background: var(--accent-soft) !important; color: var(--accent); font-weight: 800; border: none !important; }
        .btn-save { background: var(--accent); color: white; border: none; padding: 14px 40px; border-radius: 14px; font-weight: 700; transition: 0.3s; }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 5px 15px var(--accent-soft); }
    
            /* prepared the Date Input Fields (for the Dark & Light Mode) */
        input[type="date"].form-control, 
        .filter-control[type="date"] {
            color: var(--text-main) !important;
            background-color: var(--bg-body) !important;
            border-color: var(--border) !important;
        }

        /* change the Calendar icon in Dark theme*/
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] .filter-control[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) hue-rotate(180deg);
            cursor: pointer;
        }
        
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br><span style="font-size: 0.7rem; color: var(--text-muted); letter-spacing: 1px;"></span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-house"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 25px 0 10px 15px; opacity: 0.6; letter-spacing: 1px;">Management</div>
        <button class="nav-btn active" onclick="toggleSubmenu('taskSub')">
            <i class="fa-solid fa-list-check"></i> Tasking Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size: 0.7rem;"></i>
        </button>
        <div id="taskSub" class="submenu show">
            <a href="task_letters.php" class="sub-nav-btn active-sub">Add New Task</a>
            <a href="task_list.php" class="sub-nav-btn">View Tasking</a>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-power-off"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <h4 class="fw-bold mb-0">Create Task Assignment</h4>
    </header>

    <div class="form-container">
        <h5 class="fw-bold mb-4"><i class="fa-solid fa-file-signature me-2 text-primary"></i> New Task Entry</h5>

        <?php if ($error): ?><div class="alert alert-danger border-0 rounded-4 mb-4 small fw-bold"><?= $error ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success border-0 rounded-4 mb-4 small fw-bold"><?= $success ?></div><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label">Task CDR Number</label>
                    <input type="text" name="tsk_cdr_number" class="form-control readonly-box" value="<?= $tsk_display ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Manual Reference</label>
                    <input type="text" name="letter_ref" class="form-control" placeholder="Type Reference..." required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Letter Date</label>
                    <input type="date" name="letter_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Deadline Date</label>
                    <input type="date" name="request_date" class="form-control">
                </div>

                <div class="col-md-9">
                    <label class="form-label">Subject / Title of Task</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority Level</label>
                    <select name="priority_status" class="form-select">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Task Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of the task..."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assign Units (Searchable)</label>
                    <div class="search-list-container">
                        <input type="text" class="form-control list-search-input" placeholder="Search Unit Name..." onkeyup="filterList(this, 'unitsList')">
                        <div class="list-items-box" id="unitsList">
                            <?php while($r = $regiments_q->fetch_assoc()): ?>
                                <label class="list-item">
                                    <input type="checkbox" name="regiments[]" value="<?= $r['id'] ?>">
                                    <span><?= htmlspecialchars($r['regiment_name']) ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Link Internal Letters (Searchable)</label>
                    <div class="search-list-container">
                        <input type="text" class="form-control list-search-input" placeholder="Search CDR or Ref..." onkeyup="filterList(this, 'lettersList')">
                        <div class="list-items-box" id="lettersList">
                            <?php while($il = $internals_q->fetch_assoc()): ?>
                                <label class="list-item">
                                    <input type="checkbox" name="internal_letters[]" value="<?= $il['id'] ?>">
                                    <span><?= $il['cdr_number'] ?> | <?= $il['letter_reference'] ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Minute / Detailed Context (about_letter)</label>
                    <textarea name="about_letter" class="form-control" rows="4" placeholder="Enter full letter context here..."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Official Remarks / Comments</label>
                    <textarea name="comments" class="form-control" rows="4" placeholder="Any additional notes..."></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">File Attachment</label>
                    <input type="file" name="letter_file" class="form-control">
                </div>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" name="submit" class="btn-save">Register Task</button>
                </div>
            </div>
        </form>
    </div>
</main>

<script src="../../assets/js/jquery.min.js"></script>
<script>
    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        submenu.style.display = (submenu.style.display === "block") ? "none" : "block";
    }

    function filterList(input, listId) {
        const filter = input.value.toLowerCase();
        const items = document.getElementById(listId).getElementsByClassName('list-item');
        for (let i = 0; i < items.length; i++) {
            const txtValue = items[i].textContent || items[i].innerText;
            items[i].style.display = (txtValue.toLowerCase().indexOf(filter) > -1) ? "" : "none";
        }
    }

    document.documentElement.setAttribute('data-theme', localStorage.getItem('dispatch_theme') || 'dark');
</script>
</body>
</html>