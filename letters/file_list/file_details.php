<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// 1. Check permission to view internal letters (or file records)
if (!hasPermission('VIEW_INTERNAL_LETTERS')) {
    die('Access Denied');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: file_list.php");
    exit;
}

// 3. Fetch file record
$stmt = $conn->prepare("
    SELECT f.*, 
           u.username AS creator_username, u.name_with_initial AS creator_fullname, u.service_number AS creator_service, u.rank AS creator_rank,
           u2.username AS editor_username, u2.name_with_initial AS editor_fullname, u2.service_number AS editor_service, u2.rank AS editor_rank,
           d.desk_name
    FROM physical_file_records f
    LEFT JOIN users u ON f.created_by = u.id
    LEFT JOIN users u2 ON f.edited_by = u2.id
    LEFT JOIN desks d ON f.desk_id = d.id
    WHERE f.id = ? AND f.is_deleted = 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file) {
    die("File record not found or has been deleted.");
}

// 4. Fetch internal letters linked via final disposal (folios)
$folios = [];
$folioStmt = $conn->prepare("
    SELECT il.id, il.cdr_number, il.letter_title, il.letter_date, il.priority_status, il.status, fdd.file_location
    FROM internal_letters il
    JOIN internal_letters_final_disposal_details fdd ON il.id = fdd.letter_id
    WHERE fdd.file_number = ? AND il.is_deleted = 0
    ORDER BY il.letter_date DESC, il.id DESC
");
$folioStmt->bind_param("s", $file['file_reference']);
$folioStmt->execute();
$folios = $folioStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. Calculate Folio Statistics
$totalFolios = count($folios);
$urgentFolios = 0;
$pendingFolios = 0;
$completedFolios = 0;

foreach ($folios as $f) {
    if (in_array(strtolower($f['priority_status']), ['urgent', 'immediately'])) {
        $urgentFolios++;
    }
    $statusLower = strtolower($f['status']);
    if (in_array($statusLower, ['pending', 'in progress', 'pending dispatch'])) {
        $pendingFolios++;
    } elseif ($statusLower == 'completed') {
        $completedFolios++;
    }
}
?>
<!DOCTYPE html>
<html lang="si" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Details: <?= htmlspecialchars($file['file_reference']) ?> | IDCMS</title>
    
    <!-- ============================================================
         FONTS — Priority order for Sinhala rendering
         ============================================================ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        /* ============================================================
           FONT STACK — Sinhala fonts FIRST for proper rendering
           ============================================================ */
        @font-face {
            font-family: 'Iskoola Pota Local';
            src: local('Iskoola Pota'),
                 local('IskoolaPota'),
                 local('Nirmala UI'),
                 local('Noto Sans Sinhala');
            font-display: swap;
        }

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

            /* Font stack: Noto Sans Sinhala first (loaded from Google Fonts), 
               then native Sinhala fonts, then English fallback */
            --font-sinhala: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', sans-serif;
            --font-english: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Courier New', monospace;
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
            color-scheme: dark;
        }

        /* ============================================================
           GLOBAL RESET — Sinhala-aware font family
           ============================================================ */
        * {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            font-feature-settings: "kern" 1, "liga" 1;
            font-kerning: normal;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            transition: 0.3s;
            font-size: 0.9rem;
            line-height: 1.6;
            letter-spacing: 0.01em;
        }

        /* Force Sinhala rendering quality */
        [lang="si"],
        .sinhala-text,
        h1, h2, h3, h4, h5, h6,
        p, span, div, td, th, label, button, input, textarea, select, a {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        /* ============================================================
           SIDEBAR
           ============================================================ */
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
            font-size: 1.2rem;
            color: var(--accent);
            letter-spacing: -0.3px;
        }
        .nav-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: 0.2s;
            background: transparent;
            width: 100%;
            border: none;
            text-align: left;
            cursor: pointer;
            line-height: 1.5;
        }
        .nav-btn i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
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
            font-size: 0.85rem;
            font-weight: 600;
            border-left: 2px solid var(--border);
            transition: 0.2s;
            line-height: 1.5;
        }
        .sub-nav-btn:hover, .sub-nav-btn.active {
            color: var(--accent);
            border-left-color: var(--accent);
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            display: flex;
            flex-direction: column;
            padding: 30px;
        }
        
        /* ============================================================
           STAT CARDS
           ============================================================ */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-info { display: flex; flex-direction: column; }
        .stat-label {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.8px;
            line-height: 1.4;
        }
        .stat-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.2;
        }

        /* ============================================================
           CARDS & TABLES
           ============================================================ */
        .card-box {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .table {
            color: var(--text-main);
            border-color: var(--border);
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.8px;
            font-weight: 800;
            padding: 12px 10px;
            white-space: nowrap;
        }
        .table td, .table th {
            border-top: 1px solid var(--border);
            vertical-align: middle;
            padding: 12px 10px;
            font-size: 0.88rem;
            line-height: 1.55;
        }
        .table-hover tbody tr:hover {
            background: var(--accent-soft);
        }
        
        /* ============================================================
           BADGES
           ============================================================ */
        .badge-active { background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 5px 10px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; display: inline-block; }
        .badge-archived { background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 5px 10px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; display: inline-block; }
        .badge-urgent { background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 5px 10px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; display: inline-block; }
        .badge-immediately { background: #111827; color: #ffffff; padding: 5px 10px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; display: inline-block; }
        .badge-normal { background: rgba(107, 114, 128, 0.2); color: #6b7280; padding: 5px 10px; border-radius: 30px; font-size: 0.72rem; font-weight: 700; display: inline-block; }
        .badge-count {
            background: var(--accent-soft);
            color: var(--accent);
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.78rem;
            font-weight: 800;
            display: inline-block;
        }

        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 12px;
            padding: 8px 20px;
            font-weight: 700;
            transition: 0.2s;
            font-size: 0.85rem;
        }
        .btn-primary:hover {
            background: var(--accent);
            opacity: 0.9;
            transform: translateY(-1px);
            color: white;
        }
        .btn-outline-secondary {
            border-color: var(--border);
            color: var(--text-muted);
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .btn-outline-secondary:hover {
            background: var(--accent-soft);
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ============================================================
           INFO GRID
           ============================================================ */
        .info-label {
            color: var(--text-muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 800;
            margin-bottom: 4px;
            letter-spacing: 0.6px;
        }
        .info-value {
            font-weight: 600;
            font-size: 0.92rem;
            margin-bottom: 15px;
            line-height: 1.55;
        }
        
        /* ============================================================
           USER PROFILE
           ============================================================ */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .user-profile i {
            font-size: 1.3rem;
            color: var(--accent);
            flex-shrink: 0;
        }
        .user-details { display: flex; flex-direction: column; }
        .user-name {
            font-weight: 800;
            font-size: 0.92rem;
            color: var(--text-main);
            line-height: 1.4;
        }
        .user-meta {
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.4;
        }
        .user-timestamp {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* ============================================================
           HEADINGS — Better Sinhala weight rendering
           ============================================================ */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 800;
            line-height: 1.4;
            letter-spacing: 0;
        }
        h4 { font-size: 1.4rem; }
        h5 { font-size: 1.15rem; }
        h6 { font-size: 1rem; }

        /* Sinhala text specifically — better spacing */
        .sinhala-text {
            font-family: 'Noto Sans Sinhala', 'Iskoola Pota', 'IskoolaPota', 'Nirmala UI', sans-serif;
            line-height: 1.75;
            letter-spacing: 0.015em;
        }

        /* ============================================================
           DATATABLE OVERRIDES
           ============================================================ */
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 8px;
            padding: 6px 12px;
            font-family: inherit;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_filter label {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .pagination .page-link {
            background: var(--card-bg);
            border-color: var(--border);
            color: var(--text-main);
            border-radius: 8px !important;
            margin: 0 2px;
            font-weight: 700;
        }
        .pagination .page-link:hover {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: var(--accent);
        }
        .pagination .page-item.active .page-link {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            .sidebar, .btn, .stat-card, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }
            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-family: 'Noto Sans Sinhala', 'Iskoola Pota', sans-serif !important;
            }
            .card-box, .file-header {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
            }
            .table {
                color: #000 !important;
            }
            .table thead th {
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
            }
            .table td, .table th {
                border-top: 1px solid #ddd !important;
            }
        }

        /* ============================================================
           RESPONSIVE — Mobile
           ============================================================ */
        @media (max-width: 900px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open { transform: translateX(0); }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
            .stat-value { font-size: 1.15rem; }
            h4 { font-size: 1.15rem; }
            .table td, .table th {
                padding: 10px 8px;
                font-size: 0.82rem;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large me-2"></i> Home Page
        </a>
        <div style="font-size: 0.68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7; letter-spacing: 1px;">
            E-FILES OPERATIONS
        </div>
        <div>
            <div class="nav-btn active" onclick="toggleSubmenu('recordRoomSub')">
                <i class="fa-solid fa-folder-open me-2"></i> Record Room
                <i class="fa-solid fa-chevron-down ms-auto small"></i>
            </div>
            <div id="recordRoomSub" class="submenu show">
                <a href="add_file.php" class="sub-nav-btn">Add File</a>
                <a href="file_list.php" class="sub-nav-btn active">File List</a>
                <a href="issue_file.php" class="sub-nav-btn">Issue & Returned File</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">
    <div class="container-fluid">
        
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0">
                    <i class="fa-solid fa-folder-open me-2 text-primary"></i>
                    <span class="sinhala-text"><?= htmlspecialchars($file['file_reference']) ?></span>
                    <span class="badge-count ms-2">
                        <i class="fa-solid fa-layer-group me-1"></i><?= $totalFolios ?> Folios
                    </span>
                </h4>
            </div>
            <div class="d-flex gap-2 no-print">
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-print me-1"></i> Print
                </button>
                <a href="file_list.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4 no-print">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(117, 81, 255, 0.15); color: #7551FF;">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">TOTAL FOLIOS</span>
                        <span class="stat-value"><?= $totalFolios ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">URGENT</span>
                        <span class="stat-value"><?= $urgentFolios ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">PENDING</span>
                        <span class="stat-value"><?= $pendingFolios ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">COMPLETED</span>
                        <span class="stat-value"><?= $completedFolios ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILE METADATA CARD -->
        <div class="card-box mb-4">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="mb-3">
                        <i class="fa-solid fa-folder me-2"></i>File Metadata
                        <span class="badge bg-secondary ms-2" style="font-size: 0.7rem;">
                            Updated: <?= $file['edited_at'] ? date('d M Y', strtotime($file['edited_at'])) : 'Never' ?>
                        </span>
                    </h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-label">Volume</div>
                            <div class="info-value sinhala-text"><?= htmlspecialchars($file['volume']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Rack No</div>
                            <div class="info-value sinhala-text"><?= htmlspecialchars($file['rack_no']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Row No</div>
                            <div class="info-value sinhala-text"><?= htmlspecialchars($file['row_no']) ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-label">Desk</div>
                            <div class="info-value sinhala-text"><?= htmlspecialchars($file['desk_name'] ?? 'Not assigned') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-label">Status</div>
                            <div class="info-value">
                                <span class="<?= $file['status'] == 'ACTIVE' ? 'badge-active' : 'badge-archived' ?>">
                                    <?= $file['status'] ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php if ($file['about_file']): ?>
                        <div class="mt-2">
                            <div class="info-label">About File</div>
                            <div class="info-value sinhala-text" style="font-weight: 500; font-size: 0.88rem; color: var(--text-muted);">
                                <?= nl2br(htmlspecialchars($file['about_file'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- DETAILED USER WHOIS SECTION -->
                <div class="col-md-4 border-start" style="border-color: var(--border) !important;">
                    <div class="ps-md-4">
                        
                        <!-- Created By Details -->
                        <div class="info-label">Created By</div>
                        <div class="user-profile">
                            <i class="fa-solid fa-user-circle"></i>
                            <div class="user-details">
                                <span class="user-name sinhala-text">
                                    <?= htmlspecialchars($file['creator_fullname'] ?? $file['creator_username'] ?? 'Unknown') ?>
                                </span>
                                <span class="user-meta">
                                    <i class="fa-solid fa-id-badge me-1"></i><?= htmlspecialchars($file['creator_service'] ?? 'N/A') ?> 
                                    | <?= htmlspecialchars($file['creator_rank'] ?? 'N/A') ?>
                                </span>
                            </div>
                        </div>
                        <div class="user-timestamp">
                            <i class="fa-solid fa-clock me-1"></i>
                            <?= date('d M Y H:i', strtotime($file['created_at'])) ?>
                        </div>
                        
                        <hr style="border-color: var(--border); margin: 15px 0;">
                        
                        <!-- Last Updated By Details -->
                        <div class="info-label">Last Updated By</div>
                        <?php if ($file['editor_username']): ?>
                            <div class="user-profile">
                                <i class="fa-solid fa-user-edit"></i>
                                <div class="user-details">
                                    <span class="user-name sinhala-text">
                                        <?= htmlspecialchars($file['editor_fullname'] ?? $file['editor_username']) ?>
                                    </span>
                                    <span class="user-meta">
                                        <i class="fa-solid fa-id-badge me-1"></i><?= htmlspecialchars($file['editor_service'] ?? 'N/A') ?> 
                                        | <?= htmlspecialchars($file['editor_rank'] ?? 'N/A') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="user-timestamp">
                                <i class="fa-solid fa-clock me-1"></i>
                                <?= date('d M Y H:i', strtotime($file['edited_at'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="--text-muted small mt-2">
                                <i class="fa-solid fa-circle-xmark me-1"></i>No edits recorded
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- FOLIOS TABLE -->
        <div class="card-box">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-list me-2"></i>Folios (Internal Letters)
                    <span class="badge-count ms-2"><?= $totalFolios ?> records found</span>
                </h6>
            </div>
            
            <?php if (empty($folios)): ?>
                <div class="--text-muted text-center py-5">
                    <i class="fa-solid fa-folder-open fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">No internal letters linked to this file.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="foliosTable" class="table table-hover align-middle" style="width:100%">
                        <thead>
                            <tr>
                                <th>Folio No</th>
                                <th>CDR Number</th>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($folios as $folio): ?>
                                <tr>
                                    <td class="fw-bold text-primary sinhala-text">
                                        <?= htmlspecialchars($folio['file_location'] ?? '-') ?>
                                    </td>
                                    <td class="fw-bold sinhala-text">
                                        <?= htmlspecialchars($folio['cdr_number']) ?>
                                    </td>
                                    <td class="sinhala-text">
                                        <?= htmlspecialchars($folio['letter_title']) ?>
                                    </td>
                                    <td><?= date('d M Y', strtotime($folio['letter_date'])) ?></td>
                                    <td>
                                        <?php 
                                            $priority = strtolower($folio['priority_status']);
                                            $priorityClass = 'badge-normal';
                                            if ($priority == 'urgent') $priorityClass = 'badge-urgent';
                                            elseif ($priority == 'immediately') $priorityClass = 'badge-immediately';
                                        ?>
                                        <span class="<?= $priorityClass ?>">
                                            <?= htmlspecialchars($folio['priority_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = strtolower($folio['status']);
                                            $statusClass = 'badge-normal';
                                            if ($status == 'completed') $statusClass = 'badge-active';
                                            elseif ($status == 'pending' || $status == 'in progress') $statusClass = 'badge-urgent'; 
                                        ?>
                                        <span class="<?= $statusClass ?>">
                                            <?= htmlspecialchars($folio['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../../assets/js/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/jquery.dataTables.min.js"></script>
<script src="../../assets/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTables for Folios
        if ($('#foliosTable').length > 0) {
            $('#foliosTable').DataTable({
                order: [[3, 'desc']], 
                pageLength: 10,
                searching: true,
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"--text-muted small"i><"d-flex align-items-center gap-2"f>>rt<"d-flex justify-content-between align-items-center mt-3"<"--text-muted small"i><"pagination"p>>',
                language: {
                    emptyTable: "No folios found",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    lengthMenu: "Per page: _MENU_",
                    search: "Search:",
                    paginate: { 
                        first: "First", 
                        last: "Last", 
                        next: "Next", 
                        previous: "Previous" 
                    }
                }
            });
        }

        // Theme persistence
        (function() {
            const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    });

    function toggleSubmenu(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('show');
    }
</script>
</body>
</html>