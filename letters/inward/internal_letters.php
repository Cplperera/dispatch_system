<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS')) {
    die("Access Denied!");
}

/* -----------------------------
    1. FETCH FILTER DATA
------------------------------*/
$categories = $conn->query("SELECT id, category FROM letter_categories");
$regiments  = $conn->query("SELECT id, regiment_name FROM regiments");

/* -----------------------------
    2. MAIN QUERY
    Note: We pull disposal_status from the final disposal table as the 
    source of truth. This gives us double safety in case triggers are 
    ever disabled.
------------------------------*/
$sql = "SELECT il.*, 
               lc.category AS letter_category_name, 
               r.regiment_name, 
               i.informer_code,
               ifd.status AS disposal_status
        FROM internal_letters il
        LEFT JOIN regiments r ON il.regiment_id = r.id
        LEFT JOIN informers i ON il.informer_id = i.id
        LEFT JOIN letter_categories lc ON il.category_id = lc.id
        LEFT JOIN internal_letters_final_disposal ifd ON il.id = ifd.letter_id
        WHERE il.is_deleted = 0 
        ORDER BY il.letter_date DESC, il.id DESC";  

$result = $conn->query($sql);
$letters = [];

$urgentCount = 0;
$immediateCount = 0;

while($row = $result->fetch_assoc()){
    // Determine final disposal state
    $disposalStatus = $row['disposal_status'] ?? '';
    $hasFinalDisposal = !empty($disposalStatus);
    $isFinalDisposed = ($hasFinalDisposal && strcasecmp($disposalStatus, 'Completed') === 0);
    $isPendingFinalDisposal = ($hasFinalDisposal && !$isFinalDisposed);

    // SAFETY: Force-correct the display state (in case DB is out of sync)
    if ($isPendingFinalDisposal) {
        $row['status'] = 'Pending';
        $row['is_under_final_disposal'] = 1;
    } elseif ($isFinalDisposed) {
        $row['status'] = 'Completed';
        $row['is_under_final_disposal'] = 0;
    }

    // Count Urgent/Immediate for active items only
    if(strcasecmp($row['status'], 'Completed') !== 0) {
        if($row['priority_status'] == 'Urgent') $urgentCount++;
        if($row['priority_status'] == 'Immediately') $immediateCount++;
    }

    // Fetch minute trail
    $lId = $row['id'];
    $hStmt = $conn->prepare("SELECT f.comments, f.forwarded_at, 
                                    u_by.name_with_initial as sender_name, u_by.rank as sender_rank,
                                    u_to.name_with_initial as receiver_name, u_to.rank as receiver_rank
                             FROM internal_letters_forwarding f 
                             JOIN users u_by ON f.forwarded_by = u_by.id 
                             JOIN users u_to ON f.forwarded_to = u_to.id
                             WHERE f.letter_id = ? 
                             ORDER BY f.forwarded_at ASC");
    $hStmt->bind_param("i", $lId);
    $hStmt->execute();
    $row['minutes'] = $hStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $letters[] = $row;
}

$letterIds = array_column($letters, 'id');

/* -----------------------------
    3. FORWARDING DATA MAP
------------------------------*/
$forwardingData = [];
if (!empty($letterIds)) {
    $placeholders = implode(',', array_fill(0, count($letterIds), '?'));
    $forwardSql = "SELECT f.letter_id, u_to.name_with_initial AS forwarded_to_name 
                    FROM internal_letters_forwarding f 
                    JOIN users u_to ON f.forwarded_to = u_to.id 
                    WHERE f.letter_id IN ($placeholders) ORDER BY f.forwarded_at DESC";
    $fStmt = $conn->prepare($forwardSql);
    $fStmt->bind_param(str_repeat('i', count($letterIds)), ...$letterIds);
    $fStmt->execute();
    $fRes = $fStmt->get_result();
    while ($fRow = $fRes->fetch_assoc()) { 
        if (!isset($forwardingData[$fRow['letter_id']])) {
            $forwardingData[$fRow['letter_id']] = $fRow['forwarded_to_name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Internal | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
            --filter-bg: #FFFFFF; --filter-border: #E2E8F0;
            --filter-text: #1B2559; --filter-placeholder: #A0AEC0;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
            --filter-bg: #1A1F37; --filter-border: #2D3748;
            --filter-text: #FFFFFF; --filter-placeholder: #718096;
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
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 20px 30px; z-index: 90; border-bottom: 1px solid var(--border); }
        
        .table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 25px; margin: 30px; }
        .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .custom-table thead th { border: none; color: var(--text-muted); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; padding: 10px 15px; }
        .letter-row td { background: var(--card-bg); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 15px; vertical-align: middle; }
        .letter-row td:first-child { border-left: 1px solid var(--border); border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .letter-row td:last-child { border-right: 1px solid var(--border); border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        .letter-row:hover td { background: var(--accent-soft); }

        .cdr-pill { background: var(--accent-soft); padding: 4px 10px; border-radius: 8px; color: var(--accent); font-weight: 800; font-family: 'Courier New', monospace; font-size: 0.8rem; display: inline-block; }
        .cdr-pill a { color: var(--accent); text-decoration: none; }
        .cdr-pill a:hover { text-decoration: underline; }
        .bg-maroon { background-color: #800000 !important; color: #ffffff !important; }
        .mini-badge { font-size: 0.65rem; padding: 3px 8px; font-weight: 700; text-transform: uppercase; border-radius: 6px; }
        .action-dock { background: var(--bg-body); border: 1px solid var(--border); padding: 5px; border-radius: 10px; display: inline-flex; gap: 5px; }

        .filters-section { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 16px 20px; margin-bottom: 20px; }
        .filter-group { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .filter-input { background: var(--filter-bg); border: 1px solid var(--filter-border); color: var(--filter-text); border-radius: 12px; padding: 10px 16px; font-weight: 500; font-size: 0.85rem; outline: none; transition: all 0.2s ease; min-width: 160px; height: 42px; }
        .filter-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .filter-input::placeholder { color: var(--filter-placeholder); font-weight: 400; }
        .search-wrapper { position: relative; flex: 1; min-width: 280px; }
        .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.9rem; pointer-events: none; }
        .search-input { background: var(--filter-bg); border: 1px solid var(--filter-border); color: var(--filter-text); border-radius: 12px; padding: 10px 16px 10px 42px; font-weight: 500; font-size: 0.85rem; outline: none; transition: all 0.2s ease; width: 100%; height: 42px; }
        .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .filter-actions { display: flex; gap: 8px; margin-left: auto; }
        .btn-filter { background: var(--accent); color: white; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s ease; height: 42px; }
        .btn-filter:hover { background: var(--accent); opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(67, 24, 255, 0.3); }
        .btn-reset { background: transparent; color: var(--text-muted); border: 1px solid var(--filter-border); border-radius: 10px; padding: 10px 20px; font-weight: 500; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s ease; height: 42px; }
        .btn-reset:hover { background: var(--filter-bg); border-color: var(--accent); color: var(--accent); }
        .filter-badge { background: var(--accent-soft); color: var(--accent); padding: 6px 12px; border-radius: 30px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: 1px solid transparent; transition: all 0.2s ease; }
        .filter-badge i { font-size: 0.7rem; }
        
        .stat-tag { font-size: 0.7rem; font-weight: 800; padding: 5px 12px; border-radius: 8px; display: flex; align-items: center; gap: 6px; }
        .stat-urgent { background: rgba(220, 53, 69, 0.1); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.2); }
        .stat-immediate { background: rgba(128, 0, 0, 0.1); color: #ff4d4d; border: 1px solid rgba(128, 0, 0, 0.2); }
        .stat-total { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--border); }

        .row-new { border-left: 5px solid var(--accent) !important; }
        .badge-new { background: #dc3545; color: white; font-size: 0.6rem; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2); }
        .badge-under-final { background: #ffc10720; color: #ffc107; border: 1px solid #ffc10740; font-size: 0.65rem; padding: 3px 8px; font-weight: 700; border-radius: 6px; display: inline-block; }
        .badge-final-disposed { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); font-size: 0.65rem; padding: 3px 8px; font-weight: 700; border-radius: 6px; display: inline-block; }

        .modal-xl { max-width: 98vw; }
        .modal-content { height: 95vh; background: var(--card-bg); border-radius: 20px; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .pdf-viewer-pane { flex: 7.2; background: #1e1e2e; height: 100%; display: flex; flex-direction: column; position: relative; }
        .pdf-toolbar { background: rgba(30, 30, 46, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        .pdf-title { display: flex; align-items: center; gap: 12px; }
        .pdf-title i { font-size: 1.4rem; color: #f40f02; }
        .pdf-title span { color: white; font-weight: 500; font-size: 0.95rem; }
        .pdf-controls { display: flex; gap: 8px; }
        .pdf-control-btn { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .pdf-control-btn:hover { background: rgba(255, 255, 255, 0.2); border-color: rgba(255, 255, 255, 0.3); transform: translateY(-1px); }
        #pdfContainer { flex-grow: 1; background: #1a1a2a; position: relative; }
        #pdfContainer embed { width: 100%; height: 100%; border: none; }
        
        .minutes-pane { flex: 2.8; background: var(--sidebar-bg); border-left: 1px solid var(--border); height: 100%; display: flex; flex-direction: column; position: relative; }
        .minutes-header { padding: 20px 20px 15px; border-bottom: 1px solid var(--border); background: var(--sidebar-bg); }
        .minutes-header h6 { color: var(--accent); font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .minutes-header p { color: var(--text-muted); font-size: 0.7rem; margin-bottom: 0; }
        .timeline-container { flex-grow: 1; overflow-y: auto; padding: 20px; background: var(--sidebar-bg); }
        .trail-card { position: relative; padding-left: 50px; margin-bottom: 25px; border-left: 2px dashed var(--border); margin-left: 10px; transition: all 0.2s ease; }
        .trail-card:hover { border-left-color: var(--accent); }
        .trail-number { position: absolute; left: -19px; top: 0; width: 36px; height: 36px; background: var(--accent); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; border: 4px solid var(--sidebar-bg); z-index: 5; box-shadow: 0 4px 10px rgba(67, 24, 255, 0.3); }
        .trail-user { color: var(--accent); font-weight: 700; font-size: 0.85rem; margin-bottom: 2px; }
        .trail-direction { color: var(--text-muted); font-size: 0.75rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .trail-direction i { font-size: 0.65rem; color: var(--accent); }
        .trail-date { color: var(--text-muted); font-size: 0.65rem; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
        .trail-date i { font-size: 0.6rem; }
        .trail-comment-box { background: var(--bg-body); border: 1px solid var(--border); padding: 14px; border-radius: 14px; font-size: 0.82rem; color: var(--text-main); position: relative; font-style: normal; line-height: 1.5; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); margin-top: 8px; }
        .trail-comment-box::before { content: ''; position: absolute; left: -6px; top: 15px; width: 12px; height: 12px; background: var(--bg-body); border-left: 1px solid var(--border); border-bottom: 1px solid var(--border); transform: rotate(45deg); border-radius: 2px; }
        .trail-comment-box i { color: var(--accent); margin-right: 6px; font-size: 0.7rem; }
        .minutes-footer { padding: 20px; border-top: 1px solid var(--border); background: var(--sidebar-bg); }
        .minutes-footer .btn { padding: 12px; font-weight: 600; font-size: 0.85rem; border-radius: 12px; transition: all 0.2s ease; }
        .minutes-footer .btn-primary { background: var(--accent); border: none; }
        .minutes-footer .btn-primary:hover { background: var(--accent); opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(67, 24, 255, 0.3); }
        .minutes-footer .btn-success { background: #10b981; border: none; }
        .minutes-footer .btn-success:hover { background: #10b981; opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .timeline-container::-webkit-scrollbar { width: 6px; }
        .timeline-container::-webkit-scrollbar-track { background: var(--border); border-radius: 10px; }
        .timeline-container::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }
        .timeline-container::-webkit-scrollbar-thumb:hover { background: var(--accent); opacity: 0.8; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br><span style="font-size: 0.7rem; color: var(--text-muted);"> </span></div>
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
                <a href="internal_letters.php" class="sub-nav-btn text-primary fw-bold">View Registry</a>
                <a href="inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="filters-section">
            <div class="filter-group">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="tableSearch" class="search-input" placeholder="Search by subject, CDR number or reference...">
                </div>
                
                <select id="regimentFilter" class="filter-input">
                    <option value="">All Sections</option>
                    <?php $regiments->data_seek(0); while($r = $regiments->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($r['regiment_name']) ?>"><?= htmlspecialchars($r['regiment_name']) ?></option>
                    <?php endwhile; ?>
                </select>

                <select id="priorityFilter" class="filter-input">
                    <option value="">All Priorities</option>
                    <option value="Normal">Normal</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Immediately">Immediately</option>
                </select>

                <select id="statusFilter" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="On Desk">On Desk</option>
                    <option value="On Officer">On Officer</option>
                    <option value="Completed">Completed</option>
                </select>

                <div class="filter-actions">
                    <button onclick="runFilters()" class="btn-filter"><i class="fa-solid fa-sliders"></i> Apply Filters</button>
                    <button onclick="resetFilters()" class="btn-reset"><i class="fa-solid fa-rotate-right"></i> Clear</button>
                </div>
            </div>
            
            <div class="d-flex gap-2 mt-3" id="activeFilters" style="display: none !important;">
                <span class="filter-badge"><i class="fa-solid fa-building"></i> <span id="activeRegiment"></span></span>
                <span class="filter-badge"><i class="fa-solid fa-flag"></i> <span id="activePriority"></span></span>
                <span class="filter-badge"><i class="fa-solid fa-circle-check"></i> <span id="activeStatus"></span></span>
            </div>
        </div>
    </header>

    <section>
        <div class="table-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h6 class="mb-1 fw-bold text-main"><i class="fa-solid fa-list-ul me-2 text-primary"></i> Internal Letters Registry</h6>
                    <p class="--text-muted mb-0" style="font-size: 0.7rem;">Manage and track internal organizational correspondence.</p>
                </div>
                <div class="d-flex gap-2">
                    <div class="stat-tag stat-urgent"><i class="fa-solid fa-triangle-exclamation"></i> URGENT: <?= $urgentCount ?></div>
                    <div class="stat-tag stat-immediate"><i class="fa-solid fa-bolt"></i> IMMEDIATE: <?= $immediateCount ?></div>
                    <div class="stat-tag stat-total">TOTAL: <?= count($letters) ?></div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="custom-table" id="lettersTable">
                    <thead>
                         <tr>
                            <th style="width: 25%">CDR & Reference</th>
                            <th style="width: 30%">Title /Date & Routing</th>
                            <th style="width: 15%">Unit/Source</th>
                            <th style="width: 10%">Priority</th>
                            <th style="width: 10%">Process</th>
                            <th class="text-end">Action</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($letters as $row): 
                            $priority = $row['priority_status'] ?? 'Normal';
                            $pClass = ($priority == 'Urgent') ? 'bg-danger' : (($priority == 'Immediately') ? 'bg-maroon' : 'bg-success');
                            $pBorder = ($priority == 'Urgent') ? '#dc3545' : (($priority == 'Immediately') ? '#800000' : '#198754');
                            
                            // ✅ FINAL DISPOSAL STATE
                            $disposalStatus = $row['disposal_status'] ?? '';
                            $isFinalDisposed = (!empty($disposalStatus) && strcasecmp($disposalStatus, 'Completed') === 0);
                            $isPendingFinalDisposal = (!empty($disposalStatus) && !$isFinalDisposed);

                            // Disable all action buttons when in final disposal pipeline OR completed
                            $disableActions = ($isPendingFinalDisposal || $isFinalDisposed || strcasecmp($row['status'], 'Completed') === 0);

                            $jsonPayload = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr class="letter-row" id="row-<?= $row['id'] ?>" style="border-left: 4px solid <?= $pBorder ?>;">
                             <td>
                                <div class="cdr-pill mb-1">
                                    <a href="internal_letter_details.php?id=<?= $row['id'] ?>"><?= $row['cdr_number'] ?></a>
                                </div>
                                <div class="small --text-muted fw-bold ps-1"><?= $row['letter_reference'] ?: 'NO-REF' ?></div>
                                <div id="badge-container-<?= $row['id'] ?>" class="mt-1"></div>
                             </td>
                             <td>
                                <div class="fw-bold text-main mb-1" style="font-size: 0.85rem;"><?= htmlspecialchars($row['letter_title']) ?></div>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="--text-muted"><i class="fa-regular fa-calendar-check me-1"></i><?= date('d M Y', strtotime($row['letter_date'])) ?></small>
                                    <?php if(isset($forwardingData[$row['id']])): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill" style="font-size: 0.6rem;">
                                            <i class="fa-solid fa-share me-1"></i>With: <?= $forwardingData[$row['id']] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                             </td>
                             <td>
                                <div class="text-primary regiment-val fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($row['regiment_name']) ?></div>
                                <div class="--text-muted small" style="font-size: 0.65rem;"><?= htmlspecialchars($row['letter_category_name'] ?? 'Uncategorized') ?></div>
                                <?php if(!empty($row['informer_code'])): ?>
                                    <div class="text-warning fw-bold mt-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                        <i class="fa-solid fa-user-secret me-1"></i>INF: <?= htmlspecialchars($row['informer_code']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($isPendingFinalDisposal): ?>
                                    <div class="mt-1">
                                        <span class="badge-under-final"><i class="fa-regular fa-clock me-1"></i>Under Final Disposal</span>
                                    </div>
                                <?php elseif ($isFinalDisposed): ?>
                                    <div class="mt-1">
                                        <span class="badge-final-disposed"><i class="fa-solid fa-circle-check me-1"></i>Final Disposed</span>
                                    </div>
                                <?php endif; ?>
                             </td>
                             <td><span class="mini-badge priority-val text-white <?= $pClass ?> shadow-sm"><?= $priority ?></span></td>
                             <td>
                                <div class="status-val fw-bold text-uppercase" style="font-size: 0.65rem; color: var(--accent);">
                                    <i class="fa-solid fa-circle-dot me-1" style="font-size: 0.5rem;"></i><?= $row['status'] ?>
                                </div>
                             </td>
                            <td class="text-end">
                                <div class="action-dock">
                                    <?php if (hasPermission('PREVIEW_INTERNAL_PDF')): ?>
                                        <button class="btn btn-sm btn-white border-0 preview-trigger" 
                                                data-payload='<?= htmlspecialchars($jsonPayload, ENT_QUOTES, 'UTF-8') ?>' 
                                                title="Quick Preview">
                                            <i class="fa-solid fa-eye text-primary"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('EDIT_INTERNAL_LETTER')): ?>
                                        <?php if (!$disableActions): ?>
                                            <a href="edit_internal_letter.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0" title="Edit Letter">
                                                <i class="fa-solid fa-pen-to-square text-warning"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-white border-0" disabled title="Edit not allowed"><i class="fa-solid fa-pen-to-square text-muted"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (hasPermission('FORWARD_INTERNAL_LETTER')): ?>
                                        <?php if (!$disableActions): ?>
                                            <a href="forward_letter.php?letter_id=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0" title="Forward/Minute">
                                                <i class="fa-solid fa-paper-plane text-info"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-white border-0" disabled title="Forwarding not allowed"><i class="fa-solid fa-paper-plane text-muted"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (hasPermission('FINAL_DISPOSAL')): ?>
                                        <?php if (!$disableActions): ?>
                                            <a href="../Final_Disposal/forward_final_disposal.php?letter_id=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0" title="Send to Final Disposal">
                                                <i class="fa-solid fa-check-double text-success"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-white border-0" disabled title="Final disposal already initiated"><i class="fa-solid fa-check-double text-muted"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (hasPermission('DELETE_INTERNAL_LETTER')): ?>
                                        <?php if (!$disableActions): ?>
                                            <a href="delete_internal_letter.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-white border-0" onclick="return confirm('Are you sure?')" title="Delete Letter">
                                                <i class="fa-solid fa-trash-can text-danger"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-white border-0" disabled title="Delete not allowed"><i class="fa-solid fa-trash-can text-muted"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content d-flex flex-row">
            <div class="pdf-viewer-pane">
                <div class="pdf-toolbar">
                    <div class="pdf-title">
                        <i class="fa-regular fa-file-pdf"></i>
                        <span id="modalDocTitle" class="text-white small fw-bold"></span>
                    </div>
                    <div class="pdf-controls">
                        <a id="fullScreenPdf" href="#" target="_blank" class="pdf-control-btn" title="Open in new tab">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                        <button type="button" class="pdf-control-btn" data-bs-dismiss="modal" title="Close">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>
                <div id="pdfContainer" class="flex-grow-1"></div>
            </div>
            
            <div class="minutes-pane">
                <div class="minutes-header">
                    <h6><i class="fa-regular fa-clock me-2"></i>Minute Trail</h6>
                    <p>Document workflow history</p>
                </div>
                <div class="timeline-container" id="modalTimeline"></div>
                <div class="minutes-footer">
                    <?php if (hasPermission('FORWARD_INTERNAL_LETTER')): ?>
                    <a id="modalForwardBtn" href="#" class="btn btn-primary w-100 mb-2">
                        <i class="fa-regular fa-paper-plane me-2"></i>Forward / Reply
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('FINAL_DISPOSAL')): ?>
                    <a id="modalDisposalBtn" href="#" class="btn btn-success w-100">
                        <i class="fa-regular fa-circle-check me-2"></i>Final Disposal
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    function initHighlights() {
        const seenIds = JSON.parse(localStorage.getItem('seen_internal_letters') || '[]');
        document.querySelectorAll('.letter-row').forEach(row => {
            const letterId = row.id.split('-')[1];
            if (!seenIds.includes(letterId)) {
                row.classList.add('row-new');
                const badgeBox = document.getElementById(`badge-container-${letterId}`);
                if (badgeBox) badgeBox.innerHTML = '<span class="badge-new">NEW</span>';
            }
        });
    }

    function markAsRead(id) {
        let seenIds = JSON.parse(localStorage.getItem('seen_internal_letters') || '[]');
        if (!seenIds.includes(id.toString())) {
            seenIds.push(id.toString());
            localStorage.setItem('seen_internal_letters', JSON.stringify(seenIds));
        }
        const targetRow = document.getElementById(`row-${id}`);
        const targetBadge = document.getElementById(`badge-container-${id}`);
        if (targetRow) targetRow.classList.remove('row-new');
        if (targetBadge) targetBadge.innerHTML = '';
    }

    function applyGlobalTheme() {
        const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
    function toggleSubmenu(id) { document.getElementById(id).classList.toggle('show'); }

    document.querySelectorAll('.preview-trigger').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const data = JSON.parse(this.dataset.payload);
                markAsRead(data.id);
                
                document.getElementById('modalDocTitle').innerText = `${data.cdr_number} | ${data.letter_title}`;
                
                const forwardBtn = document.getElementById('modalForwardBtn');
                const disposalBtn = document.getElementById('modalDisposalBtn');
                
                const isFinalDisposed = (data.disposal_status === 'Completed');
                const isPendingFinal = (data.disposal_status && data.disposal_status !== 'Completed');
                const isDisabled = (isPendingFinal || isFinalDisposed || data.status === 'Completed');
                
                if (forwardBtn) {
                    if (isDisabled) {
                        forwardBtn.removeAttribute('href');
                        forwardBtn.classList.add('disabled');
                        forwardBtn.style.pointerEvents = 'none';
                        forwardBtn.style.opacity = '0.6';
                    } else {
                        forwardBtn.href = `forward_letter.php?letter_id=${data.id}`;
                        forwardBtn.classList.remove('disabled');
                        forwardBtn.style.pointerEvents = '';
                        forwardBtn.style.opacity = '';
                    }
                }
                
                if (disposalBtn) {
                    if (isDisabled) {
                        disposalBtn.removeAttribute('href');
                        disposalBtn.classList.add('disabled');
                        disposalBtn.style.pointerEvents = 'none';
                        disposalBtn.style.opacity = '0.6';
                    } else {
                        disposalBtn.href = `../Final_Disposal/forward_final_disposal.php?letter_id=${data.id}`;
                        disposalBtn.classList.remove('disabled');
                        disposalBtn.style.pointerEvents = '';
                        disposalBtn.style.opacity = '';
                    }
                }
                
                const container = document.getElementById('pdfContainer');
                const fullScreenBtn = document.getElementById('fullScreenPdf');
                
                if (data.attachment && data.attachment.trim() !== '') {
                    const pdfUrl = `../../uploads/letters/${encodeURIComponent(data.attachment)}`;
                    container.innerHTML = `<embed src="${pdfUrl}#toolbar=0&navpanes=0&view=FitH" type="application/pdf" width="100%" height="100%" />`;
                    fullScreenBtn.href = pdfUrl;
                    fullScreenBtn.style.display = 'flex';
                } else {
                    container.innerHTML = `<div class="h-100 d-flex flex-column align-items-center justify-content-center text-white-50">
                        <i class="fa-regular fa-file-lines fa-4x mb-3 opacity-50"></i>
                        <p class="opacity-50">No attachment available for this document</p>
                    </div>`;
                    fullScreenBtn.style.display = 'none';
                }

                const timeline = document.getElementById('modalTimeline');
                timeline.innerHTML = '';
                if(data.minutes && data.minutes.length > 0) {
                    data.minutes.forEach((m, index) => {
                        timeline.innerHTML += `
                            <div class="trail-card">
                                <div class="trail-number">${index + 1}</div>
                                <div class="trail-user">${m.sender_rank} ${m.sender_name}</div>
                                <div class="trail-direction">
                                    <i class="fa-regular fa-arrow-right-long"></i>
                                    To: ${m.receiver_rank} ${m.receiver_name}
                                </div>
                                <div class="trail-date">
                                    <i class="fa-regular fa-calendar"></i>
                                    ${new Date(m.forwarded_at).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                </div>
                                <div class="trail-comment-box">
                                    <i class="fa-regular fa-message"></i>
                                    ${m.comments || 'Document forwarded without additional comments.'}
                                </div>
                            </div>`;
                    });
                } else {
                    timeline.innerHTML = '<div class="text-center p-5 text-white-50"><i class="fa-regular fa-timeline fa-3x mb-3 opacity-25"></i><p>No minute trail recorded for this document.</p></div>';
                }

                const modalEl = document.getElementById('quickViewModal');
                let modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
                modalInstance.show();

            } catch (err) {
                console.error("Payload Error:", err);
            }
        });
    });

    function runFilters() {
        const s = document.getElementById('tableSearch').value.toLowerCase();
        const r = document.getElementById('regimentFilter').value.toLowerCase();
        const p = document.getElementById('priorityFilter').value.toLowerCase();
        const st = document.getElementById('statusFilter').value.toLowerCase();
        
        document.querySelectorAll('.letter-row').forEach(row => {
            const rowText = row.innerText.toLowerCase();
            const regText = row.querySelector('.regiment-val').innerText.toLowerCase();
            const prioText = row.querySelector('.priority-val').innerText.toLowerCase();
            const statText = row.querySelector('.status-val').innerText.toLowerCase();
            
            const matches = rowText.includes(s) && 
                            (r === "" || regText === r) && 
                            (p === "" || prioText === p) && 
                            (st === "" || statText.includes(st));
            
            row.style.display = matches ? "" : "none";
        });
        
        updateActiveFilters();
    }

    function resetFilters() {
        document.getElementById('tableSearch').value = '';
        document.getElementById('regimentFilter').value = '';
        document.getElementById('priorityFilter').value = '';
        document.getElementById('statusFilter').value = '';
        runFilters();
    }
    
    function updateActiveFilters() {
        const regiment = document.getElementById('regimentFilter').value;
        const priority = document.getElementById('priorityFilter').value;
        const status = document.getElementById('statusFilter').value;
        
        const activeFiltersDiv = document.getElementById('activeFilters');
        if (regiment || priority || status) {
            activeFiltersDiv.style.display = 'flex';
            document.getElementById('activeRegiment').innerText = regiment || 'All Sections';
            document.getElementById('activePriority').innerText = priority || 'All Priorities';
            document.getElementById('activeStatus').innerText = status || 'All Statuses';
        } else {
            activeFiltersDiv.style.display = 'none';
        }
    }

    window.onload = function() {
        applyGlobalTheme();
        initHighlights();
    };
</script>
</body>
</html>