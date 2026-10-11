<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_INTERNAL_LETTER')) {
    die('Access Denied!');
}

$isAdminSection = ($_SESSION['section'] ?? '') === 'Administration Section';
$isRegistry     = !$isAdminSection;

/* ===============================
    FETCH REGIMENTS
================================ */
$regiments = [];
$res = $conn->query("SELECT id, regiment_name FROM regiments WHERE status='Active'");
if (!$res) die("Regiment Query Failed: " . $conn->error);
while ($row = $res->fetch_assoc()) {
    $regiments[] = $row;
}

/* ===============================
    FETCH INFORMERS (Registry)
================================ */
$informers = [];
if ($isRegistry) {
    $res = $conn->query("
        SELECT id, informer_name, informer_code, regiment_id
        FROM informers
        WHERE is_deleted=0 AND status='Active'
    ");
    if (!$res) die("Informer Query Failed: " . $conn->error);
    while ($row = $res->fetch_assoc()) {
        $informers[] = $row;
    }
}

/* ===============================
    FETCH CATEGORIES (Registry)
================================ */
$categories = [];
if ($isRegistry) {
    $res = $conn->query("SELECT id, category FROM letter_categories WHERE status='Active'");
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

/* ===============================
    GENERATE CDR NUMBER
================================ */
function generateCDR($conn, $section)
{
    $year   = date('Y');
    $prefix = ($section === 'Administration Section') ? 'ADM' : 'REG';

    $sql = "
        SELECT cdr_number
        FROM internal_letters
        WHERE cdr_number LIKE '{$prefix}/{$year}/%'
        ORDER BY id DESC
        LIMIT 1
    ";

    $res = $conn->query($sql);
    $last = $res->fetch_assoc();
    $num = $last ? ((int)explode('/', $last['cdr_number'])[2] + 1) : 1;

    return sprintf('%s/%s/%02d', $prefix, $year, $num);
}

$cdr_display = generateCDR($conn, $_SESSION['section']);
$error = $success = '';

/* ===============================
    HANDLE FORM SUBMISSION
================================ */
if (isset($_POST['submit'])) {
    $cdr_number       = $_POST['cdr_number'];
    $letter_reference = str_replace(' ', '', $_POST['letter_reference'] ?? null);
    $letter_title     = trim($_POST['letter_title']);
    $letter_date      = $_POST['letter_date'];
    $regiment_id      = (int)$_POST['regiment_id'];

    $security_classification = $_POST['security_classification'];
    $about_letter = $_POST['about_letter'] ?? null;
    $comments     = $_POST['comments'] ?? null;
    $registered_by = $_SESSION['user_id'];
    $priority_status = $_POST['priority_status'] ?? 'Normal';
    
    // Initializing state as specified: 'not Forwarded'
    $status = 'Not Forwarded';
    $is_deleted = 0;
    $is_under_final_disposal = 0; // පෙරනිමියෙන් 0 ලෙස ලබා දීම

    // ===== Duplicate Reference Check =====
    if (!empty($letter_reference)) {
        $dupCheck = $conn->prepare("SELECT id FROM internal_letters WHERE letter_reference = ? AND is_deleted = 0");
        $dupCheck->bind_param("s", $letter_reference);
        $dupCheck->execute();
        $dupCheck->store_result();
        if ($dupCheck->num_rows > 0) {
            $error = "Letter Reference already exists. Please use a unique reference.";
        }
        $dupCheck->close();
    }
    // ===========================================

    $letter_category = null; 
    $letter_classification = null;
    $informer_id = null;

    if ($isRegistry) {
        if (!empty($_POST['letter_category'])) {
            $letter_category = (int) $_POST['letter_category'];
            $catCheck = $conn->prepare("SELECT category FROM letter_categories WHERE id = ?");
            $catCheck->bind_param("i", $letter_category);
            $catCheck->execute();
            $catResult = $catCheck->get_result()->fetch_assoc();
            $categoryName = $catResult['category'] ?? null;
            $catCheck->close();

            if ($categoryName === 'MISR') {
                if (!empty($_POST['letter_classification'])) {
                    $letter_classification = (int) $_POST['letter_classification'];
                }
                if (!empty($_POST['informer_id'])) {
                    $tempInformer = (int) $_POST['informer_id'];
                    $check = $conn->prepare("SELECT id FROM informers WHERE id = ?");
                    $check->bind_param("i", $tempInformer);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $informer_id = $tempInformer;
                    }
                    $check->close();
                }
            }
        }
    }

    $attachment = null;
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = __DIR__ . '/../../uploads/letters/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = time() . '_' . basename($_FILES['attachment']['name']);
        $uploadPath = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadPath)) {
            $attachment = $filename;
        }
    }

    if (!$error) {
        $sql = "INSERT INTO internal_letters (
                    cdr_number, letter_reference, letter_title, letter_date,
                    regiment_id, category_id, letter_classification, security_classification,
                    priority_status, about_letter, comments, Informer_id, attachment,
                    registered_by, status, is_deleted, created_at, updated_at, is_under_final_disposal
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        $created_at = date('Y-m-d H:i:s');
        $updated_at = date('Y-m-d H:i:s');

        $stmt->bind_param(
            "ssssiiissssisisissi",
            $cdr_number,             // 1. s
            $letter_reference,       // 2. s
            $letter_title,           // 3. s
            $letter_date,            // 4. s
            $regiment_id,            // 5. i
            $letter_category,        // 6. i (category_id සඳහා මෙහි නිවැරදි විචල්‍යය ලබා දී ඇත)
            $letter_classification,  // 7. i
            $security_classification,// 8. s
            $priority_status,        // 9. s
            $about_letter,           // 10. s
            $comments,               // 11. s
            $informer_id,            // 12. i
            $attachment,             // 13. s
            $registered_by,          // 14. i
            $status,                 // 15. s
            $is_deleted,             // 16. i
            $created_at,             // 17. s
            $updated_at,             // 18. s
            $is_under_final_disposal // 19. i
        );

        if ($stmt->execute()) {
            $success = "Letter successfully registered!";
            $cdr_display = generateCDR($conn, $_SESSION['section']);
        } else {
            $error = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
        $stmt->close();
    }
}

/* ===============================
    FETCH RECENT ENTRIES FOR PREVIEW
================================ */
$previewLetters = [];
$previewQuery = "
    SELECT 
        il.id,
        il.cdr_number, 
        il.letter_reference, 
        il.letter_title, 
        il.letter_date, 
        il.security_classification, 
        il.priority_status,
        il.status,
        il.created_at,
        r.regiment_name, 
        lc.category AS category_name,
        u.name_with_initial AS registered_by_name
    FROM internal_letters il
    LEFT JOIN regiments r ON il.regiment_id = r.id
    LEFT JOIN letter_categories lc ON il.category_id = lc.id
    LEFT JOIN users u ON il.registered_by = u.id
    WHERE il.is_deleted = 0 AND (il.status = 'Not Forwarded' OR il.status IS NULL OR il.status = '')
    ORDER BY il.id DESC 
    LIMIT 10
";
$previewRes = $conn->query($previewQuery);
if ($previewRes) {
    while ($pRow = $previewRes->fetch_assoc()) {
        $previewLetters[] = $pRow;
    }
}

/* ===============================
    FETCH PENDING FORWARDING COUNT 
================================ */
$pendingCount = 0;
$countQuery = "SELECT COUNT(*) as total FROM internal_letters WHERE is_deleted = 0 AND (status = 'Not Forwarded' OR status IS NULL OR status = '')";
$countRes = $conn->query($countQuery);
if ($countRes) {
    $pendingCount = $countRes->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Internal Letter | IDCMS</title>
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
            --text-muted: #707EAE; --border: #DDE2EF;
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

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', "Segoe UI", sans-serif; transition: 0.3s; }
        
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
        .sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
        .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; }

        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; margin: 0 30px 30px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
        
        .form-control, .form-select { 
            background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); 
            border-radius: 12px; padding: 10px 15px; font-size: 0.9rem; 
        }

        .form-control::placeholder { color: var(--placeholder-color); opacity: 0.8; font-weight: 400; }
        .form-control:focus, .form-select:focus { 
            background: var(--bg-body); color: var(--text-main); border-color: var(--accent); 
            box-shadow: 0 0 0 3px var(--accent-soft); 
        }
        .form-control[readonly] { opacity: 0.7; background: var(--accent-soft); font-family: monospace; font-weight: bold; }

        .btn-save { background: var(--accent); color: white; border: none; padding: 12px 30px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-clear { color: var(--text-muted); border: none; background: transparent; padding: 12px 20px; font-weight: 600; font-size: 0.85rem; transition: 0.3s; opacity: 0.5; }
        .btn-clear:hover { opacity: 1; color: var(--text-main); }
        
        .section-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; }
        .section-title i { margin-right: 12px; color: var(--accent); }

        /* Preview Table Styles */
        .preview-table-wrapper { border-top: 1px solid var(--border); margin-top: 40px; padding-top: 30px; }
        .table { color: var(--text-main); margin-bottom: 0; vertical-align: middle; }
        .table th { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); padding: 15px 10px; border-bottom: 2px solid var(--border); }
        .table td { font-size: 0.85rem; padding: 15px 10px; border-bottom: 1px solid var(--border); }
        .badge-priority { padding: 5px 10px; border-radius: 8px; font-weight: 700; font-size: 0.75rem; }
        .badge-normal { background: var(--accent-soft); color: var(--accent); }
        .badge-urgent { background: rgba(255, 182, 0, 0.15); color: #FFB600; }
        .badge-immediate { background: rgba(255, 91, 91, 0.15); color: #FF5B5B; }
        .badge-security { border: 1px solid var(--border); padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 0.75rem; color: var(--text-muted); }
        
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.7rem; display: inline-block; text-transform: capitalize; }
        .badge-status.not-forwarded { background: rgba(255, 182, 0, 0.15); color: #FFB600; }
        .badge-status.forwarded { background: rgba(67, 24, 255, 0.15); color: var(--accent); }
        .badge-status.pending { background: rgba(0, 150, 255, 0.15); color: #0096FF; }
        .badge-status.completed { background: rgba(0, 200, 100, 0.15); color: #00C864; }
        
        .btn-forward { background: var(--accent); color: white; border: none; padding: 5px 15px; border-radius: 8px; font-weight: 700; font-size: 0.75rem; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-forward:hover { opacity: 0.8; transform: scale(0.95); color: white; }
        .btn-view { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 5px 15px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-view:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        
        .action-cell { display: flex; gap: 8px; flex-wrap: wrap; }
        .stats-badge { background: var(--accent-soft); color: var(--accent); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }
        .stats-badge i { margin-right: 8px; }

        /* Date Input Fields පෙනෙන සේ සැකසීම (Dark & Light Mode සඳහා) */
        input[type="date"].form-control, 
        .filter-control[type="date"] {
            color: var(--text-main) !important;
            background-color: var(--bg-body) !important;
            border-color: var(--border) !important;
        }

        /* Dark theme එකේදී Calendar icon එක සුදු පැහැයට හැරවීම */
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] .filter-control[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) hue-rotate(180deg);
            cursor: pointer;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br><span style="font-size: 0.7rem; color: var(--text-muted);"></span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Dispatching</div>
        
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text me-2"></i> Internal Letters
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="internalSub" class="submenu show">
                <a href="add_internal_letter.php" class="sub-nav-btn text-primary fw-bold">Add Internal</a>
                <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-main">Internal Correspondence</h4>
            <div class="stats-badge">
                <i class="fa-solid fa-clock"></i> Not Forwarded: <?= $pendingCount ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <div class="section-title"><i class="fa-solid fa-pen-to-square"></i> Create New Entry</div>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4 border-0 mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success rounded-4 border-0 mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">CDR Number</label>
                    <input type="text" name="cdr_number" class="form-control" value="<?= $cdr_display ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Letter Reference</label>
                    <input type="text" name="letter_reference" id="letter_reference" class="form-control" placeholder="e.g. D/REG/2026/001" pattern="^\S+$" title="Spaces are not allowed">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Letter Date</label>
                    <input type="date" name="letter_date" class="form-control" required>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Letter Title / Subject</label>
                    <input type="text" name="letter_title" class="form-control" required placeholder="Enter primary subject of the letter">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Source Regiment</label>
                    <select name="regiment_id" id="regiment_id" class="form-select" required>
                        <option value="">-- Select Regiment --</option>
                        <?php foreach ($regiments as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['regiment_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isRegistry): ?>
                <div class="col-md-4">
                    <label class="form-label">Letter Category</label>
                    <select name="letter_category" id="letter_category" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= $c['category'] ?>">
                                <?= htmlspecialchars($c['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4" id="classification_box" style="display:none;">
                    <label class="form-label">Letter Classification</label>
                    <select name="letter_classification" class="form-select">
                        <?php for ($i=1;$i<=6;$i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4" id="informer_box" style="display:none;">
                    <label class="form-label">Informer</label>
                    <select name="informer_id" id="informer_id" class="form-select">
                        <option value="">-- Select Informer --</option>
                        <?php foreach ($informers as $i): ?>
                            <option value="<?= $i['id'] ?>" data-regiment="<?= $i['regiment_id'] ?>">
                                <?= htmlspecialchars($i['informer_name'].' ['.$i['informer_code'].']') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Priority Status</label>
                    <select name="priority_status" class="form-select">
                        <option value="Normal" selected>Normal</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Immediately">Immediately</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Security Classification</label>
                    <select name="security_classification" class="form-select">
                        <option>Public</option>
                        <option selected>Restricted</option>
                        <option>Confidential</option>
                        <option>Secret</option>
                        <option>Top Secret</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Attachment (PDF/Image)</label>
                    <input type="file" name="attachment" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">About Letter / Minute</label>
                    <textarea name="about_letter" class="form-control" rows="4" placeholder="Brief summary of letter content..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Remarks / Comments</label>
                    <textarea name="comments" class="form-control" rows="4" placeholder="Any internal notes..."></textarea>
                </div>

                <div class="col-12 mt-5 text-end">
                    <button type="reset" class="btn-clear me-3">
                        <i class="fa-solid fa-rotate-left me-1"></i> Clear Form
                    </button>
                    <button type="submit" name="submit" class="btn-save">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i> Register & Save Letter
                    </button>
                </div>
            </div>
        </form>

        <div class="preview-table-wrapper">
            <div class="section-title mb-3">
                <i class="fa-solid fa-list-check"></i> Recent Correspondence Workspace Entries 
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>CDR Number</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Regiment / Category</th>
                            <th>Security</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previewLetters)): ?>
                            <tr>
                                <td colspan="8" class="text-center --text-muted py-4">
                                    <i class="fa-solid fa-check-circle me-2"></i> No records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($previewLetters as $letter): 
                                $pStatus = strtolower($letter['priority_status']);
                                $badgeClass = 'badge-normal';
                                if ($pStatus === 'urgent') $badgeClass = 'badge-urgent';
                                if ($pStatus === 'immediately') $badgeClass = 'badge-immediate';
                                
                                $statusDisplay = $letter['status'] ?: 'Not Forwarded';
                                $statusKey = strtolower(trim($statusDisplay));
                                
                                if ($statusKey === 'not forwarded') {
                                    $statusClass = 'badge-status not-forwarded';
                                    $statusDisplay = 'Not Forwarded';
                                } elseif ($statusKey === 'forwarded') {
                                    $statusClass = 'badge-status forwarded';
                                    $statusDisplay = 'Forwarded';
                                } elseif ($statusKey === 'pending') {
                                    $statusClass = 'badge-status pending';
                                    $statusDisplay = 'Pending';
                                } elseif ($statusKey === 'completed') {
                                    $statusClass = 'badge-status completed';
                                    $statusDisplay = 'Completed';
                                } else {
                                    $statusClass = 'badge-status text-bg-secondary';
                                }
                            ?>
                                <tr>
                                    <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($letter['cdr_number']) ?></td>
                                    <td class="font-monospace"><?= htmlspecialchars($letter['letter_reference'] ?: '--') ?></td>
                                    <td><?= htmlspecialchars(date('d-M-Y', strtotime($letter['letter_date']))) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($letter['regiment_name'] ?: 'N/A') ?></div>
                                        <small class="--text-muted"><?= htmlspecialchars($letter['category_name'] ?: 'N/A') ?></small>
                                    </td>

                                    <td><span class="badge-security"><?= htmlspecialchars($letter['security_classification']) ?></span></td>
                                    <td><span class="badge-priority <?= $badgeClass ?>"><?= htmlspecialchars($letter['priority_status']) ?></span></td>
                                    <td><span class="<?= $statusClass ?>"><?= $statusDisplay ?></span></td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if ($statusKey === 'not forwarded'): ?>
                                                <a href="forward_letter.php?letter_id=<?= $letter['id'] ?>" class="btn-forward">
                                                    <i class="fa-solid fa-paper-plane"></i> Forward
                                                </a>
                                            <?php endif; ?>
                                            <a href="internal_letter_details.php?id=<?= $letter['id'] ?>" class="btn-view" target="_blank">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Logic
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // No Space logic for Letter Reference
    const refInput = document.getElementById('letter_reference');
    if(refInput) {
        refInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\s/g, '');
        });
    }

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

    <?php if ($isRegistry): ?>
    const category = document.getElementById('letter_category');
    const classBox = document.getElementById('classification_box');
    const informerBox = document.getElementById('informer_box');
    const regiment = document.getElementById('regiment_id');
    const informer = document.getElementById('informer_id');

    function filterInformers() {
        if(!regiment || !informer) return;
        const reg = regiment.value;
        Array.from(informer.options).forEach(opt => {
            if (!opt.value) return;
            opt.style.display = (opt.dataset.regiment === reg) ? 'block' : 'none';
        });
    }

    if(category) {
        category.addEventListener('change', function () {
            const name = this.selectedOptions[0]?.dataset.name;
            if (name === 'MISR') {
                if(classBox) classBox.style.display = 'block';
                if(informerBox) informerBox.style.display = 'block';
                filterInformers();
            } else {
                if(classBox) classBox.style.display = 'none';
                if(informerBox) informerBox.style.display = 'none';
                if(informer) informer.value = '';
            }
        });
    }

    if(regiment) {
        regiment.addEventListener('change', filterInformers);
    }
    <?php endif; ?>

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