<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

/* ===============================
    FETCH EXTERNAL BRANCHES (Receivers)
================================ */
$branches = [];
$res = $conn->query("SELECT id, branch_name FROM external_branches ORDER BY branch_name ASC");
if (!$res) die("Branches Query Failed: " . $conn->error);
while ($row = $res->fetch_assoc()) {
    $branches[] = $row;
}

/* ===============================
    GENERATE CDR NUMBER FOR OUTWARD
================================ */
function generateOutwardCDR($conn)
{
    $year   = date('Y');
    $prefix = 'RPT';

    $sql = "
        SELECT cdr_no
        FROM outward_letters
        WHERE cdr_no LIKE '{$prefix}/{$year}/%'
        ORDER BY id DESC
        LIMIT 1
    ";

    $res = $conn->query($sql);
    $last = $res ? $res->fetch_assoc() : null;
    $num = $last ? ((int)explode('/', $last['cdr_no'])[2] + 1) : 1;

    return sprintf('%s/%s/%02d', $prefix, $year, $num);
}

$cdr_display = generateOutwardCDR($conn);
$error = $success = '';

/* ===============================
    HANDLE FORM SUBMISSION
================================ */
if (isset($_POST['submit'])) {
    $cdr_no           = $_POST['cdr_no'];
    $letter_ref      = trim(str_replace(' ', '', $_POST['letter_ref'] ?? ''));
    $title           = trim($_POST['title']);
    $letter_date     = $_POST['letter_date'];
    
    // Multiple receivers
    $receivers_arr   = $_POST['receivers'] ?? [];
    $receiver        = !empty($receivers_arr) ? implode(',', array_map('intval', $receivers_arr)) : null;

    $description     = $_POST['description'] ?? null;
    $comment         = $_POST['comment'] ?? null;
    $created_by      = $_SESSION['user_id'];
    
    // Workflow States
    $status          = 'Pending';
    $approval_status = 'Pending RA Approval';  // ✅ RA Approval එකට යවන්න
    $is_deleted      = 0;
    
    // New fields
    $dispatch_method = $_POST['dispatch_method'] ?? 'Email';
    $file_path       = null;

    // Validation
    if (empty($receivers_arr)) {
        $error = "Please select at least one receiver / external branch.";
    }
    if (empty($letter_ref)) {
        $error = "Letter Reference No is required.";
    }
    if (empty($title)) {
        $error = "Title / Subject is required.";
    }
    if (empty($letter_date)) {
        $error = "Letter Date is required.";
    }

    // ===== Duplicate Reference Check =====
    if (!$error && !empty($letter_ref)) {
        $dupCheck = $conn->prepare("SELECT id FROM outward_letters WHERE letter_ref = ? AND is_deleted = 0");
        if ($dupCheck) {
            $dupCheck->bind_param("s", $letter_ref);
            $dupCheck->execute();
            $dupCheck->store_result();
            if ($dupCheck->num_rows > 0) {
                $error = "Letter Reference already exists. Please use a unique reference.";
            }
            $dupCheck->close();
        }
    }

    // ===== File Upload =====
    if (!$error && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/outward/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $newFileName = uniqid('out_') . '.' . $ext;
        $dest = $uploadDir . $newFileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
            $file_path = 'uploads/outward/' . $newFileName;
        } else {
            $error = "Failed to upload file.";
        }
    }

    if (!$error) {
        $sql = "INSERT INTO outward_letters (
                    cdr_no, letter_ref, letter_date, title, receiver, 
                    description, comment, created_by, status, approval_status, is_deleted,
                    dispatch_method, file_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        // ✅ Corrected bind_param: 13 parameters (11 strings, 1 integer, 1 string) -> sssssssssssis
        $stmt->bind_param(
            "sssssssississ",
            $cdr_no,
            $letter_ref,
            $letter_date,
            $title,
            $receiver,
            $description,
            $comment,
            $created_by,
            $status,
            $approval_status,
            $is_deleted,
            $dispatch_method,
            $file_path
        );

        if ($stmt->execute()) {
            $success = "✅ Outward letter successfully registered and pending RA approval!";
            $cdr_display = generateOutwardCDR($conn);
            $_POST = array();
        } else {
            $error = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
        }
        $stmt->close();
    }
}

/* ===============================
    FETCH RECENT OUTWARD ENTRIES FOR PREVIEW
    - Only the 10 most recently created (by created_at)
================================ */
$previewLetters = [];
$previewQuery = "
    SELECT 
        ol.id,
        ol.cdr_no, 
        ol.letter_ref, 
        ol.title, 
        ol.letter_date, 
        ol.approval_status,
        ol.status,
        ol.created_at,
        ol.receiver,
        ol.dispatch_method,
        u.name_with_initial AS created_by_name
    FROM outward_letters ol
    LEFT JOIN users u ON ol.created_by = u.id
    WHERE ol.is_deleted = 0
    ORDER BY ol.created_at DESC   -- ← Order by creation date (most recent first)
    LIMIT 10                      -- ← Only the latest 10 records
";
$previewRes = $conn->query($previewQuery);
if ($previewRes) {
    while ($pRow = $previewRes->fetch_assoc()) {
        $recIds = $pRow['receiver'];
        $branchNames = 'N/A';
        if (!empty($recIds)) {
            $bQuery = $conn->query("SELECT branch_name FROM external_branches WHERE id IN ($recIds)");
            if ($bQuery) {
                $bNamesArr = [];
                while($bRow = $bQuery->fetch_assoc()) {
                    $bNamesArr[] = $bRow['branch_name'];
                }
                $branchNames = implode(', ', $bNamesArr);
            }
        }
        $pRow['branch_name'] = $branchNames;
        $previewLetters[] = $pRow;
    }
}

/* ===============================
    FETCH PENDING APPROVALS COUNT 
================================ */
$pendingCount = 0;
$countQuery = "SELECT COUNT(*) as total FROM outward_letters WHERE is_deleted = 0 AND approval_status = 'Pending RA Approval'";
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
    <title>Add Outward Letter | IDCMS</title>
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
        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; }
        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; margin: 0 30px 30px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
        .form-control, .form-select { background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); border-radius: 12px; padding: 10px 15px; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { background: var(--bg-body); color: var(--text-main); border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .form-control[readonly] { opacity: 0.7; background: var(--accent-soft); font-family: monospace; font-weight: bold; }
        .dropdown-menu { background-color: var(--card-bg) !important; border: 1px solid var(--border) !important; box-shadow: 0 10px 25px rgba(0,0,0,0.2); border-radius: 12px; }
        .form-check-input { background-color: var(--bg-body); border-color: var(--border); }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
        .form-check-label { font-size: 0.85rem; color: var(--text-main); cursor: pointer; }
        .btn-save { background: var(--accent); color: white; border: none; padding: 12px 30px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-clear { color: var(--text-muted); border: none; background: transparent; padding: 12px 20px; font-weight: 600; font-size: 0.85rem; transition: 0.3s; opacity: 0.5; }
        .btn-clear:hover { opacity: 1; color: var(--text-main); }
        .section-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; }
        .section-title i { margin-right: 12px; color: var(--accent); }
        .preview-table-wrapper { border-top: 1px solid var(--border); margin-top: 40px; padding-top: 30px; }
        .table { color: var(--text-main); margin-bottom: 0; vertical-align: middle; }
        .table th { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); padding: 15px 10px; border-bottom: 2px solid var(--border); }
        .table td { font-size: 0.85rem; padding: 15px 10px; border-bottom: 1px solid var(--border); }
        .badge-status { padding: 5px 12px; border-radius: 20px; font-weight: 700; font-size: 0.7rem; display: inline-block; text-transform: capitalize; }
        .badge-status.pending-review { background: rgba(255, 182, 0, 0.15); color: #FFB600; }
        .badge-status.approved { background: rgba(0, 200, 100, 0.15); color: #00C864; }
        .badge-status.rejected { background: rgba(255, 91, 91, 0.15); color: #FF5B5B; }
        .btn-view { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 5px 15px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-view:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        .action-cell { display: flex; gap: 8px; flex-wrap: wrap; }
        .stats-badge { background: var(--accent-soft); color: var(--accent); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }
        .stats-badge i { margin-right: 8px; }
        input[type="date"].form-control { color: var(--text-main) !important; background-color: var(--bg-body) !important; border-color: var(--border) !important; }
        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1) hue-rotate(180deg); cursor: pointer; }
        .file-upload-wrapper { position: relative; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }
        .sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
        .sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
        .sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

        /* Placeholder contrast fix for dark and light themes */
        .form-control::placeholder, .form-select::placeholder {
            color: var(--placeholder-color);
            opacity: 1; /* Ensures consistent opacity across browsers */
        }
        .form-control:-ms-input-placeholder, .form-select:-ms-input-placeholder {
            color: var(--placeholder-color);
        }
        .form-control::-ms-input-placeholder, .form-select::-ms-input-placeholder {
            color: var(--placeholder-color);
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">Outward Management</span></div>
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
                <a href="add_outward.php" class="sub-nav-btn text-primary fw-bold">Add Outward</a>
                <a href="outward_list.php" class="sub-nav-btn">View Outward List</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0 text-main">External Letters Correspondence</h4>
            <div class="stats-badge">
                <i class="fa-solid fa-clock"></i> Pending Reviews: <?= $pendingCount ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <div class="section-title"><i class="fa-solid fa-file-circle-plus"></i> Create New External Letters Entry</div>

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
                    <input type="text" name="cdr_no" class="form-control" value="<?= $cdr_display ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Letter Reference No <span class="text-danger">*</span></label>
                    <input type="text" name="letter_ref" id="letter_ref" class="form-control" placeholder="e.g. AHQ/DMI/INT/200/...." pattern="^\S+$" title="Spaces are not allowed" value="<?= htmlspecialchars($_POST['letter_ref'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Letter Date <span class="text-danger">*</span></label>
                    <input type="date" name="letter_date" class="form-control" value="<?= $_POST['letter_date'] ?? date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Title / Subject <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="Enter primary subject of the outward letter" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Receiver / External Branches <span class="text-danger">*</span></label>
                    <div class="dropdown">
                        <button class="form-select text-start d-flex justify-content-between align-items-center" type="button" id="receiverDropdownToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <span id="dropdownButtonText" class="text-truncate">-- Select Receivers --</span>
                        </button>
                        <ul class="dropdown-menu w-100 p-3 shadow" style="max-height: 220px; overflow-y: auto;" aria-labelledby="receiverDropdownToggle">
                            <?php if (empty($branches)): ?>
                                <li class="--text-muted text-center small">No branches available</li>
                            <?php else: ?>
                                <?php foreach ($branches as $b): ?>
                                    <li class="form-check mb-2">
                                        <input class="form-check-input receiver-checkbox" type="checkbox" name="receivers[]" value="<?= $b['id'] ?>" id="branch_<?= $b['id'] ?>" data-name="<?= htmlspecialchars($b['branch_name']) ?>" <?= (isset($_POST['receivers']) && in_array($b['id'], $_POST['receivers'])) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="branch_<?= $b['id'] ?>">
                                            <?= htmlspecialchars($b['branch_name']) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief summary..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Comment / Remarks</label>
                    <textarea name="comment" class="form-control" rows="2" placeholder="Internal notes..."><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Dispatch Method</label>
                    <select name="dispatch_method" class="form-select">
                        <option value="Email" <?= (isset($_POST['dispatch_method']) && $_POST['dispatch_method'] == 'Email') ? 'selected' : '' ?>>Email</option>
                        <option value="By Hand" <?= (isset($_POST['dispatch_method']) && $_POST['dispatch_method'] == 'By Hand') ? 'selected' : '' ?>>By Hand</option>
                        <option value="Post" <?= (isset($_POST['dispatch_method']) && $_POST['dispatch_method'] == 'Post') ? 'selected' : '' ?>>Post</option>
                        <option value="Courier" <?= (isset($_POST['dispatch_method']) && $_POST['dispatch_method'] == 'Courier') ? 'selected' : '' ?>>Courier</option>
                    </select>
                </div>
                <div class="col-md-8 file-upload-wrapper">
                    <label class="form-label">Attachment (PDF, DOC, DOCX, etc.)</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.txt,.jpg,.png">
                </div>

                <div class="col-12 mt-5 text-end">
                    <button type="reset" class="btn-clear me-3" onclick="resetDropdownText()">
                        <i class="fa-solid fa-rotate-left me-1"></i> Clear Form
                    </button>
                    <button type="submit" name="submit" class="btn-save">
                        <i class="fa-solid fa-paper-plane me-2"></i> Save & Forward to Registry Assistant
                    </button>
                </div>
            </div>
        </form>

        <div class="preview-table-wrapper">
            <div class="section-title mb-3">
                <i class="fa-solid fa-list-check"></i> Recent External Letters Workspace Entries 
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>CDR Number</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Receiver Branches</th>
                            <th>Dispatch Method</th>
                            <th>Approval Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previewLetters)): ?>
                            <tr>
                                <td colspan="7" class="text-center --text-muted py-4">
                                    <i class="fa-solid fa-check-circle me-2"></i> No records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($previewLetters as $letter): 
                                $appStatus = strtolower(trim($letter['approval_status']));
                                if ($appStatus === 'pending ra approval' || $appStatus === 'pending review') {
                                    $statusClass = 'badge-status pending-review';
                                    $statusDisplay = 'Pending RA Approval';
                                } elseif ($appStatus === 'approved') {
                                    $statusClass = 'badge-status approved';
                                    $statusDisplay = 'Approved';
                                } elseif ($appStatus === 'rejected') {
                                    $statusClass = 'badge-status rejected';
                                    $statusDisplay = 'Rejected';
                                } else {
                                    $statusClass = 'badge-status text-bg-secondary';
                                    $statusDisplay = $letter['approval_status'] ?? 'Pending';
                                }
                            ?>
                                <tr>
                                    <td class="fw-bold text-primary font-monospace"><?= htmlspecialchars($letter['cdr_no']) ?></td>
                                    <td class="font-monospace"><?= htmlspecialchars($letter['letter_ref'] ?: '--') ?></td>
                                    <td><?= htmlspecialchars(date('d-M-Y', strtotime($letter['letter_date']))) ?></td>
                                    <td><div class="fw-semibold"><?= htmlspecialchars($letter['branch_name']) ?></div></td>
                                    <td>
                                        <?php 
                                        $dMethod = $letter['dispatch_method'] ?? 'Email';
                                        $badgeClass = 'bg-secondary';
                                        if ($dMethod === 'Email') $badgeClass = 'bg-primary';
                                        elseif ($dMethod === 'By Hand') $badgeClass = 'bg-success';
                                        elseif ($dMethod === 'Courier') $badgeClass = 'bg-warning text-dark';
                                        elseif ($dMethod === 'Post') $badgeClass = 'bg-info text-dark';
                                        ?>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($dMethod) ?></span>
                                                                                                        
                                    <td><span class="<?= $statusClass ?>"><?= $statusDisplay ?></span></td>
                                    <td>
                                        <div class="action-cell">
                                            <a href="link_outward.php?id=<?= $letter['id'] ?>" class="btn-view">
                                                <i class="fa-solid fa-eye"></i> View Tags
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
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    const refInput = document.getElementById('letter_ref');
    if(refInput) {
        refInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/\s/g, '');
        });
    }

    const checkboxes = document.querySelectorAll('.receiver-checkbox');
    const dropdownText = document.getElementById('dropdownButtonText');

    function updateDropdownButtonText() {
        const checked = document.querySelectorAll('.receiver-checkbox:checked');
        if (checked.length === 0) {
            dropdownText.textContent = '-- Select Receivers --';
        } else if (checked.length === 1) {
            dropdownText.textContent = checked[0].getAttribute('data-name');
        } else {
            dropdownText.textContent = checked.length + ' branches selected';
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateDropdownButtonText);
    });

    function resetDropdownText() {
        setTimeout(() => {
            dropdownText.textContent = '-- Select Receivers --';
        }, 10);
    }

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

    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
        updateDropdownButtonText();
    });
</script>
</body>
</html>