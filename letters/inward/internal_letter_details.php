<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

if(!isset($_GET['id'])){
    die("Invalid Request");
}

$letter_id = intval($_GET['id']);

/* ======================================
   1. INWARD LETTER REGISTER DETAILS
======================================*/
$letterQuery = $conn->prepare("
SELECT 
    il.*,
    lc.category,
    r.regiment_name,
    i.informer_name,
    u.name_with_initial AS registered_user,
    u_created.name_with_initial AS created_by_user,
    u_edited.name_with_initial AS edited_by_user
FROM internal_letters il
LEFT JOIN letter_categories lc ON il.category_id = lc.id
LEFT JOIN regiments r ON il.regiment_id = r.id
LEFT JOIN informers i ON il.informer_id = i.id
LEFT JOIN users u ON il.registered_by = u.id
LEFT JOIN users u_created ON il.registered_by = u_created.id
LEFT JOIN users u_edited ON il.updated_by = u_edited.id
WHERE il.id = ?
LIMIT 1
");

$letterQuery->bind_param("i",$letter_id);
$letterQuery->execute();
$letterResult = $letterQuery->get_result()->fetch_assoc();

if(!$letterResult){
    die("Letter Not Found");
}

/* ======================================
   2. FORWARDING HISTORY
======================================*/
$forwardQuery = $conn->prepare("
SELECT 
    f.comments,
    f.forwarded_at,
    u1.name_with_initial AS forwarded_to_user,
    u2.name_with_initial AS forwarded_by_user
FROM internal_letters_forwarding f
LEFT JOIN users u1 ON f.forwarded_to = u1.id
LEFT JOIN users u2 ON f.forwarded_by = u2.id
WHERE f.letter_id = ?
ORDER BY f.forwarded_at ASC
");

$forwardQuery->bind_param("i",$letter_id);
$forwardQuery->execute();
$forwardResult = $forwardQuery->get_result();

/* ======================================
   3. OUTWARD LETTERS (Report Out)
======================================*/
$outwardQuery = $conn->prepare("
SELECT 
    o.id,
    o.letter_ref,
    o.cdr_no,
    o.letter_date,
    o.title,
    o.receiver,
    o.status,
    eb.branch_name
FROM outward_letters o
JOIN outward_internal oi ON o.id = oi.outward_id
LEFT JOIN external_branches eb ON o.receiver = eb.id
WHERE oi.internal_id = ?
ORDER BY o.letter_date DESC
");

$outwardQuery->bind_param("i",$letter_id);
$outwardQuery->execute();
$outwardResult = $outwardQuery->get_result();

/* ======================================
   4. FINAL DISPOSAL FORWARD DETAILS
======================================*/
$disposalQuery = $conn->prepare("
SELECT 
    d.remarks,
    d.status,
    d.forwarded_at,
    ds.desk_name,
    u.name_with_initial AS forwarded_by_user
FROM internal_letters_final_disposal d
LEFT JOIN desks ds ON d.forwarded_to = ds.id
LEFT JOIN users u ON d.forwarded_by = u.id
WHERE d.letter_id = ?
ORDER BY d.forwarded_at ASC
");

$disposalQuery->bind_param("i",$letter_id);
$disposalQuery->execute();
$disposalResult = $disposalQuery->get_result();

/* ======================================
   5. FINAL FILE / ARCHIVE DETAILS
======================================*/
$fileQuery = $conn->prepare("
SELECT 
    fd.file_number,
    fd.file_location,
    fd.status,
    fd.disposed_at,
    u.name_with_initial AS disposed_user
FROM internal_letters_final_disposal_details fd
LEFT JOIN users u ON fd.disposed_by = u.id
WHERE fd.letter_id = ?
ORDER BY fd.disposed_at ASC
");

$fileQuery->bind_param("i",$letter_id);
$fileQuery->execute();
$fileResult = $fileQuery->get_result();

// Permission check for outward letters (optional)
$canViewOutward = hasPermission('VIEW_OUTGOING_LETTERS');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <title>Letter Details | <?= htmlspecialchars($letterResult['cdr_number']) ?></title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        /* All existing styles (unchanged) */
        :root {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #7551FF;
            --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --text-danger: #f80202;
            --border: rgba(255, 255, 255, 0.1);
            --sidebar-width: 280px;
            --filter-bg: #1A1F37;
            --filter-border: #2D3748;
            --filter-text: #FFFFFF;
            --filter-placeholder: #718096;
        }

        [data-theme="light"] {
            --bg-body: #F0F2F5;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --accent: #4318FF;
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559;
            --text-muted: #707EAE;
            --text-danger: #f80202;
            --border: #DDE2EF;
            --filter-bg: #FFFFFF;
            --filter-border: #E2E8F0;
            --filter-text: #1B2559;
            --filter-placeholder: #A0AEC0;
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            min-height: 100vh; 
            font-family: "Segoe UI", sans-serif; 
            transition: background-color 0.3s, color 0.3s;
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
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .brand-name { 
            font-weight: 800; 
            font-size: 1.2rem; 
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
        }
        
        .sub-nav-btn:hover { 
            color: var(--accent); 
            border-left-color: var(--accent); 
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            padding: 40px; 
            transition: background-color 0.3s;
        }
        
        /* Data Display Styles */
        .section-card { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: 1px solid var(--border); 
            padding: 25px; 
            margin-bottom: 30px; 
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .section-title { 
            font-size: 1.1rem; 
            font-weight: 800; 
            margin-bottom: 20px; 
            color: var(--accent); 
            border-bottom: 1px solid var(--border); 
            padding-bottom: 10px; 
            display: flex; 
            align-items: center; 
        }
        
        .info-table { 
            width: 100%; 
        }
        
        .info-table th { 
            color: var(--text-muted); 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            padding: 12px; 
            width: 30%; 
            border-bottom: 1px solid var(--border); 
        }
        
        .info-table td { 
            padding: 12px; 
            font-weight: 600; 
            border-bottom: 1px solid var(--border); 
        }

        .history-table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0 8px; 
        }
        
        .history-table th { 
            color: var(--text-muted); 
            font-size: 0.65rem; 
            text-transform: uppercase; 
            padding: 10px; 
        }
        
        .history-table td { 
            background: var(--accent-soft);
            padding: 12px; 
            font-size: 0.85rem; 
            border-top: 1px solid var(--border); 
            border-bottom: 1px solid var(--border); 
        }
        
        .history-table td:first-child { 
            border-left: 1px solid var(--border); 
            border-top-left-radius: 10px; 
            border-bottom-left-radius: 10px; 
        }
        
        .history-table td:last-child { 
            border-right: 1px solid var(--border); 
            border-top-right-radius: 10px; 
            border-bottom-right-radius: 10px; 
        }

        .cdr-pill { 
            background: var(--accent-soft); 
            padding: 5px 12px; 
            border-radius: 8px; 
            color: var(--accent); 
            font-weight: 800; 
            font-family: 'Courier New', monospace; 
        }
        
        .status-badge { 
            padding: 4px 10px; 
            border-radius: 6px; 
            font-size: 0.7rem; 
            font-weight: 800; 
            text-transform: uppercase; 
        }
        
        .bg-pending { 
            background: rgba(255, 171, 0, 0.1); 
            color: #ffab00; 
        }
        
        .bg-completed { 
            background: rgba(0, 200, 83, 0.1); 
            color: #00c853; 
        }

        .btn-outline-light {
            border-color: var(--border);
            color: var(--text-main);
        }

        .btn-outline-light:hover {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        .text-primary {
            color: var(--accent) !important;
        }

        .--text-muted {
            color: var(--text-muted) !important;
        }

        .text-white {
            color: var(--text-main) !important;
        }

        /* Light theme specific adjustments */
        [data-theme="light"] .history-table td {
            background: rgba(67, 24, 255, 0.02);
        }

        [data-theme="light"] .bg-pending {
            background: rgba(255, 171, 0, 0.1);
            color: #b37b00;
        }

        [data-theme="light"] .bg-completed {
            background: rgba(0, 200, 83, 0.1);
            color: #008a39;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
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
                <a href="add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="#" class="sub-nav-btn text-primary fw-bold">Letter Details</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1">Letter Details</h3>
            <p class="--text-muted small">Comprehensive tracking history for CDR: <span class="text-white fw-bold"><?= htmlspecialchars($letterResult['cdr_number']) ?></span></p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="fa-solid fa-print me-2"></i> Print Report
        </button>
    </div>

    <!-- 1. Inward Letter Register Details -->
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-id-card me-2"></i> 1. Inward Letter Register Details</div>
        <table class="info-table">
             <tr><th>CDR Number</th><td><span class="cdr-pill"><?= htmlspecialchars($letterResult['cdr_number']) ?></span></td></tr>
             <tr><th>Reference</th><td><?= htmlspecialchars($letterResult['letter_reference']) ?></td></tr>
             <tr><th>Title</th><td><?= htmlspecialchars($letterResult['letter_title']) ?></td></tr>
             <tr><th>Letter Date</th><td><?= date('d M Y', strtotime($letterResult['letter_date'])) ?></td></tr>
             <tr><th>Section / Regiment</th><td><?= htmlspecialchars($letterResult['regiment_name']) ?></td></tr>
             <tr><th>Informer</th><td><?= htmlspecialchars($letterResult['informer_name'] ??'Not a Informer')?></td></tr>
             <tr><th>Classification</th><td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= htmlspecialchars($letterResult['security_classification']) ?></span></td></tr>
             <tr><th>Priority Status</th><td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25"><?= htmlspecialchars($letterResult['priority_status']) ?></span></td></tr>
             <tr><th>Comments</th><td class="--text-muted fw-normal"><?= nl2br(htmlspecialchars($letterResult['comments'])) ?></td></tr>
             <tr><th>Subject Summary</th><td class="--text-muted fw-normal"><?= nl2br(htmlspecialchars($letterResult['about_letter'])) ?></td></tr>
         </table>
    </div>

    <!-- 2. Metadata Details -->
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-hashtag me-2"></i> 2. Metadata Details</div>
        <table class="info-table">
             <tr><th>Record ID</th><td><span class="text-white font-monospace">#<?= htmlspecialchars($letterResult['id'] ?? '') ?></span></td></tr>
             <tr><th>Created By</th><td class="text-primary"><?= htmlspecialchars($letterResult['registered_user'] ?? '') ?></td></tr>
             <tr><th>Created At</th><td class="--text-muted"><?= !empty($letterResult['created_at']) ? date('d M Y, h:i A', strtotime($letterResult['created_at'])) : '--' ?></td></tr>
             <tr><th>Edited By</th><td class="text-danger"><?= htmlspecialchars($letterResult['edited_by_user'] ?? 'Not Edited') ?></td></tr>
             <tr><th>Edited at</th><td><?= !empty($letterResult['updated_at']) ? date('d M Y, h:i A', strtotime($letterResult['updated_at'])) : 'Not Edited' ?></td></tr>
        </table> 
    </div>


    <!-- 3. Forwarding History -->
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-share-nodes me-2"></i> 3. Forwarding History</div>
        <table class="history-table">
            <thead>
                 <tr>
                    <th>Recipient</th>
                    <th>Forwarded By</th>
                    <th>Date & Time</th>
                    <th>Instructions / Comments</th>
                 </tr>
            </thead>
            <tbody>
                <?php 
                if ($forwardResult->num_rows > 0) {
                    while($row = $forwardResult->fetch_assoc()){ ?>
                     <tr>
                        <td class="fw-bold text-white"><?= htmlspecialchars($row['forwarded_to_user']) ?></td>
                        <td><?= htmlspecialchars($row['forwarded_by_user']) ?></td>
                        <td class="--text-muted"><?= date('d M Y, h:i A', strtotime($row['forwarded_at'])) ?></td>
                        <td class="small fst-italic">"<?= htmlspecialchars($row['comments']) ?>"</td>
                     </tr>
                <?php 
                    }
                } else { ?>
                     <tr><td colspan="4" class="text-center --text-muted">No forwarding history available</td></tr>
                <?php } ?>
            </tbody>
         </table>
    </div>

    <!-- 4. Report Out (Outward Letters) - New Section -->
    <?php if ($canViewOutward && $outwardResult->num_rows > 0): ?>
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-paper-plane me-2"></i> 4. Report Out (Outward Letters)</div>
        <table class="history-table">
            <thead>
                 <tr>
                    <th>Ref No.</th>
                    <th>CDR No.</th>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Receiver</th>
                    <th>Status</th>
                 </tr>
            </thead>
            <tbody>
                <?php while($row = $outwardResult->fetch_assoc()): ?>
                 <tr>
                    <td><?= htmlspecialchars($row['letter_ref']) ?></td>
                    <td><?= htmlspecialchars($row['cdr_no']) ?></td>
                    <td><?= date('d M Y', strtotime($row['letter_date'])) ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['branch_name'] ?? $row['receiver']) ?></td>
                    <td><span class="badge bg-secondary"><?= $row['status'] ?></span></td>
                 </tr>
                <?php endwhile; ?>
            </tbody>
         </table>
    </div>
    <?php elseif ($canViewOutward && $outwardResult->num_rows == 0): ?>
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-paper-plane me-2"></i> 3. Report Out (Outward Letters)</div>
        <div class="text-center --text-muted py-3">No outward letters linked to this internal letter.</div>
    </div>
    <?php endif; ?>

    <!-- 5 Final Disposal Details (renumbered) -->
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-box-archive me-2"></i> <?= $canViewOutward ? '5. Final Disposal Details' : '4. Final Disposal Details' ?></div>
        <table class="history-table">
            <thead>
                 <tr>
                    <th>Target Desk</th>
                    <th>Authorized By</th>
                    <th>Remarks</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                 </tr>
            </thead>
            <tbody>
                <?php 
                if ($disposalResult->num_rows > 0) {
                    while($row = $disposalResult->fetch_assoc()){ ?>
                     <tr>
                        <td class="fw-bold"><?= htmlspecialchars($row['desk_name']) ?></td>
                        <td><?= htmlspecialchars($row['forwarded_by_user']) ?></td>
                        <td class="small"><?= htmlspecialchars($row['remarks']) ?></td>
                        <td><span class="status-badge <?= strtolower($row['status']) == 'completed' ? 'bg-completed' : 'bg-pending' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td class="--text-muted"><?= date('d M Y', strtotime($row['forwarded_at'])) ?></td>
                     </tr>
                <?php 
                    }
                } else { ?>
                     <tr><td colspan="5" class="text-center --text-muted">No final disposal details available</td></tr>
                <?php } ?>
            </tbody>
         </table>
    </div>

    <!-- 6. Physical Archive Details (renumbered) -->
    <div class="section-card shadow-sm">
        <div class="section-title"><i class="fa-solid fa-folder-closed me-2"></i> <?= $canViewOutward ? '6. Physical Archive Details' : '5. Physical Archive Details' ?></div>
        <table class="history-table">
            <thead>
                 <tr>
                    <th>File Number</th>
                    <th>Physical Location</th>
                    <th>Archived By</th>
                    <th>Date</th>
                    <th>System Status</th>
                 </tr>
            </thead>
            <tbody>
                <?php 
                if ($fileResult->num_rows > 0) {
                    while($row = $fileResult->fetch_assoc()){ ?>
                     <tr>
                        <td class="fw-bold text-primary"><?= htmlspecialchars($row['file_number']) ?></td>
                        <td><i class="fa-solid fa-location-dot me-1 --text-muted"></i> <?= htmlspecialchars($row['file_location']) ?></td>
                        <td><?= htmlspecialchars($row['disposed_user']) ?></td>
                        <td><?= date('d M Y', strtotime($row['disposed_at'])) ?></td>
                        <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3"><?= htmlspecialchars($row['status']) ?></span></td>
                     </tr>
                <?php 
                    }
                } else { ?>
                     <tr><td colspan="5" class="text-center --text-muted">No archive details available</td></tr>
                <?php } ?>
            </tbody>
         </table>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme toggle function
    function toggleTheme() {
        const current = document.documentElement.getAttribute("data-theme");
        const newTheme = current === "dark" ? "light" : "dark";
        document.documentElement.setAttribute("data-theme", newTheme);
        localStorage.setItem("dispatch_theme", newTheme);
    }

    // Submenu toggle function
    function toggleSubmenu(id) {
        document.getElementById(id).classList.toggle('show');
    }

    // Ensure theme is applied if localStorage changes in another tab
    window.addEventListener('storage', function(e) {
        if (e.key === 'dispatch_theme') {
            document.documentElement.setAttribute('data-theme', e.newValue);
        }
    });
</script>
</body>
</html>