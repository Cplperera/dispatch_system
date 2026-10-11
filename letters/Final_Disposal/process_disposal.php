<?php
// 1. Core configuration and Authentication
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Permission check
if (!hasPermission('FINAL_DISPOSAL')) {
    die("Access Denied: You do not have the required permissions.");
}

// 2. Validate the Disposal ID from the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid Request: Missing Disposal ID");
}

$disposal_id = intval($_GET['id']);
$current_user_id = $_SESSION['user_id'] ?? 0;

if ($current_user_id === 0) {
    die("Session Error: User identification failed. Please log in again.");
}

// 3. Fetch current disposal and letter details
$query = "SELECT fd.letter_id, fd.forwarded_to as desk_id, l.cdr_number, l.letter_title, d.desk_name 
          FROM internal_letters_final_disposal fd 
          JOIN internal_letters l ON fd.letter_id = l.id 
          JOIN desks d ON fd.forwarded_to = d.id
          WHERE fd.id = ? AND fd.status = 'Pending'";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $disposal_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Record not found or already processed.");
}

$letter_id = $data['letter_id'];

// 4. Fetch Physical Files for the dropdown
$files_result = $conn->query("SELECT file_reference FROM physical_file_records WHERE status = 'Active' ORDER BY file_reference ASC");

// 5. Handle Final Disposal Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_ref = $_POST['file_reference'];
    $error = null;

    // Begin Transaction to ensure all updates succeed together
    $conn->begin_transaction();

    try {
        // A. AUTO-GENERATE FOLIO NUMBER
        $folio_query = "SELECT COUNT(*) as current_count FROM internal_letters_final_disposal_details WHERE file_number = ?";
        $f_stmt = $conn->prepare($folio_query);
        $f_stmt->bind_param("s", $file_ref);
        $f_stmt->execute();
        $count_res = $f_stmt->get_result()->fetch_assoc();
        $next_folio = $count_res['current_count'] + 1;
        $file_location = "Folio No: " . $next_folio;

        // B. INSERT INTO FINAL DISPOSAL DETAILS
        $ins_stmt = $conn->prepare("INSERT INTO internal_letters_final_disposal_details 
            (letter_id, file_number, file_location, disposed_by, status, disposed_at) 
            VALUES (?, ?, ?, ?, 'Completed', NOW())");
        $ins_stmt->bind_param("issi", $letter_id, $file_ref, $file_location, $current_user_id);
        $ins_stmt->execute();

        // C. UPDATE DISPOSAL QUEUE STATUS
        $upd_fd = $conn->prepare("UPDATE internal_letters_final_disposal SET status = 'Completed', disposed_at = NOW() WHERE id = ?");
        $upd_fd->bind_param("i", $disposal_id);
        $upd_fd->execute();

        // D. UPDATE MAIN INTERNAL LETTERS TABLE
        $upd_letter = $conn->prepare("UPDATE internal_letters SET status = 'Completed', is_under_final_disposal = 1 WHERE id = ?");
        $upd_letter->bind_param("i", $letter_id);
        $upd_letter->execute();

        $conn->commit();
        header("Location: final_disposal_inbox.php?status=completed_success");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Filing failed: " . $e->getMessage();
    }
}

/* ===============================
    FETCH PENDING DISPOSAL COUNT 
================================ */
$pendingDisposalCount = 0;
$countQuery = "SELECT COUNT(*) as total FROM internal_letters_final_disposal WHERE status = 'Pending'";
$countRes = $conn->query($countQuery);
if ($countRes) {
    $pendingDisposalCount = $countRes->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Final Disposal | IDCMS</title>
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
            --success: #00C864;
            --warning: #FFB600;
            --danger: #FF5B5B;
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
            font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', "Segoe UI", sans-serif; 
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
        
        .brand-name { 
            font-weight: 800; 
            font-size: 1.1rem; 
            color: var(--text-main); 
            line-height: 1.2; 
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

        /* Main Content */
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

        /* Form Container */
        .form-container { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            padding: 30px; 
            margin: 0 30px 30px 30px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
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
        
        .section-title .badge-count {
            margin-left: auto;
            background: var(--accent-soft);
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .form-label { 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            color: var(--text-muted); 
            margin-bottom: 8px; 
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .form-control, .form-select { 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            color: var(--text-main); 
            border-radius: 12px; 
            padding: 10px 15px; 
            font-size: 0.9rem; 
            width: 100%;
            transition: 0.2s;
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
            outline: none;
        }

        /* Letter Preview Card */
        .letter-preview {
            background: var(--accent-soft);
            border-left: 4px solid var(--accent);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 28px;
        }

        .preview-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 22px;
        }

        .preview-item .lbl {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .preview-item .val {
            font-size: 1.05rem;
            color: var(--accent);
            font-weight: 800;
            margin: 0;
            font-family: monospace;
        }

        .preview-item .val-plain {
            font-size: 1rem;
            color: var(--text-main);
            font-weight: 700;
            margin: 0;
        }

        .preview-subject-block {
            margin-top: 22px;
        }

        .preview-subject-block .preview-subject {
            font-size: 1.05rem;
            color: var(--text-main);
            font-weight: 700;
            line-height: 1.5;
            margin: 0;
        }

        /* Buttons */
        .btn-save { 
            background: var(--accent); 
            color: white; 
            border: none; 
            padding: 12px 30px; 
            border-radius: 12px; 
            font-weight: 700; 
            transition: 0.3s; 
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-save:hover { 
            opacity: 0.9; 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px var(--accent-soft);
        }

        .btn-back {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }

        /* Alerts */
        .alert { 
            border-radius: 16px; 
            border: none; 
            padding: 16px 20px; 
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .alert-danger { 
            background: rgba(255, 91, 91, 0.12); 
            color: var(--danger); 
            border-left: 4px solid var(--danger);
        }
        
        .alert-success { 
            background: rgba(0, 200, 100, 0.12); 
            color: var(--success); 
            border-left: 4px solid var(--success);
        }
        
        .alert i {
            font-size: 1.2rem;
        }

        /* Info Callout */
        .info-callout {
            background: var(--accent-soft);
            border: 1px solid rgba(117, 81, 255, 0.25);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            margin: 24px 0;
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .info-callout i {
            color: var(--accent);
            font-size: 1.15rem;
            margin-top: 2px;
        }

        .info-callout strong {
            font-weight: 800;
        }

        .badge-inline {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 800;
            background: rgba(0, 200, 100, 0.15);
            color: var(--success);
            border: 1px solid rgba(0, 200, 100, 0.3);
            margin: 0 3px;
        }

        /* Form Hint */
        .form-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 10px;
        }

        /* Instructions Grid */
        .instructions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .instruction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .instruction-item .num {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--accent-soft);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .instruction-item .txt {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .instruction-item .txt .lbl {
            font-size: 0.65rem;
            color: var(--text-muted);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .instruction-item .txt .val {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Badge Pending */
        .badge-pending {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(255, 182, 0, 0.15);
            color: var(--warning);
        }

        /* Stats Badge */
        .stats-badge { 
            background: var(--accent-soft); 
            color: var(--accent); 
            padding: 8px 16px; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.85rem; 
        }
        
        .stats-badge i { 
            margin-right: 8px; 
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            margin-top: 28px;
            flex-wrap: wrap;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .top-nav {
                padding: 20px;
            }
            .form-container {
                margin: 0 20px 20px 20px;
                padding: 20px;
            }
            .action-bar {
                flex-direction: column-reverse;
            }
            .action-bar .btn-save,
            .action-bar .btn-back {
                width: 100%;
                justify-content: center;
            }
        }

        /* Date input dark mode */
        input[type="date"].form-control {
            color: var(--text-main) !important;
            background-color: var(--bg-body) !important;
            border-color: var(--border) !important;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1) hue-rotate(180deg);
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar" id="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS 
            <br>
            <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
        </a>

        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Dispatching</div>

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
            <div class="nav-btn active" onclick="toggleSubmenu('disposalSub')">
                <i class="fa-solid fa-box-archive me-2"></i> Final Disposal
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="disposalSub" class="submenu show">
                <a href="final_disposal_inbox.php" class="sub-nav-btn">Disposal Inbox</a>
                <a href="process_final_disposal.php?id=<?= $disposal_id ?>" class="sub-nav-btn active-sub">Process Disposal</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<main class="main-content">

    <header class="top-nav">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="fw-bold mb-0">Process Final Disposal</h4>
            <div class="stats-badge">
                <i class="fa-solid fa-clock"></i> Pending: <?= $pendingDisposalCount ?>
            </div>
        </div>
    </header>

    <div class="form-container">

        <div class="section-title">
            <i class="fa-solid fa-box-archive"></i> Finalize Physical Disposal
            <span class="badge-count">
                <i class="fa-solid fa-clock me-1"></i> Pending Action
            </span>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Letter Preview Card -->
        <div class="letter-preview">
            <div class="preview-row">
                <div class="preview-item">
                    <span class="lbl"><i class="fa-solid fa-hashtag"></i> Reference Number</span>
                    <p class="val"><?= htmlspecialchars($data['cdr_number']) ?></p>
                </div>
                <div class="preview-item">
                    <span class="lbl"><i class="fa-solid fa-building"></i> Assigned Desk</span>
                    <p class="val-plain"><?= htmlspecialchars($data['desk_name']) ?></p>
                </div>
            </div>
            <div class="preview-subject-block">
                <span class="lbl"><i class="fa-solid fa-file-lines"></i> Subject / Title</span>
                <p class="preview-subject"><?= htmlspecialchars($data['letter_title']) ?></p>
            </div>
        </div>

        <!-- Form -->
        <form method="POST">
            <div class="mb-4">
                <label class="form-label">
                    <i class="fa-solid fa-folder-open"></i>
                    Target Physical File Reference
                </label>
                <select name="file_reference" class="form-select" required>
                    <option value="">— Choose Physical File —</option>
                    <?php if ($files_result): ?>
                        <?php while($file = $files_result->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($file['file_reference']) ?>">
                                <?= htmlspecialchars($file['file_reference']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <div class="form-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>The system will automatically generate the next Folio Number for this file.</span>
                </div>
            </div>

            <!-- Info Callout -->
            <div class="info-callout">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Important:</strong> Submitting this form will update the letter status to
                    <span class="badge-inline">Completed</span>
                    across all modules.
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <a href="final_disposal_inbox.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Inbox
                </a>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-check-circle"></i>
                    Complete Filing &amp; Close Case
                </button>
            </div>
        </form>
    </div>

    <!-- Instructions Card -->
    <div class="form-container">
        <div class="section-title">
            <i class="fa-solid fa-circle-question"></i> Filing Instructions
        </div>
        <div class="instructions">
            <div class="instruction-item">
                <div class="num"><i class="fa-solid fa-1"></i></div>
                <div class="txt">
                    <span class="lbl">Step 1</span>
                    <span class="val">Select physical file</span>
                </div>
            </div>
            <div class="instruction-item">
                <div class="num"><i class="fa-solid fa-2"></i></div>
                <div class="txt">
                    <span class="lbl">Step 2</span>
                    <span class="val">Auto-generate folio</span>
                </div>
            </div>
            <div class="instruction-item">
                <div class="num"><i class="fa-solid fa-3"></i></div>
                <div class="txt">
                    <span class="lbl">Step 3</span>
                    <span class="val">Complete transaction</span>
                </div>
            </div>
        </div>
    </div>

</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // ═══════ Theme Logic ═══════
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // ═══════ Sidebar Submenu Toggle ═══════
    function toggleSubmenu(id) {
        const submenu = document.getElementById(id);
        if (!submenu) return;
        const icon = submenu.previousElementSibling 
            ? submenu.previousElementSibling.querySelector('.fa-chevron-down') 
            : null;

        if (submenu.style.display === "block" || submenu.classList.contains('show')) {
            submenu.style.display = "none";
            submenu.classList.remove('show');
            if (icon) icon.style.transform = "rotate(0deg)";
        } else {
            submenu.style.display = "block";
            submenu.classList.add('show');
            if (icon) icon.style.transform = "rotate(180deg)";
        }
    }
</script>
</body>
</html>