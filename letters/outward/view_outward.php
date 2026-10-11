<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: outward_list.php?error=invalid_id');
    exit;
}

// Fetch letter details with related user names
$query = "SELECT o.*, 
          (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, o.receiver)) AS branch_names,
          creator.name_with_initial AS creator_name,
          approver.name_with_initial AS approver_name,
          rejecter.name_with_initial AS rejecter_name,
          dispatcher.name_with_initial AS dispatcher_name
          FROM outward_letters o
          LEFT JOIN users creator ON o.created_by = creator.id
          LEFT JOIN users approver ON o.approved_by = approver.id
          LEFT JOIN users rejecter ON o.rejected_by = rejecter.id
          LEFT JOIN users dispatcher ON o.dispatched_by = dispatcher.id
          WHERE o.id = ? AND o.is_deleted = 0";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header('Location: outward_list.php?error=not_found');
    exit;
}
$letter = $result->fetch_assoc();
$stmt->close();

// Determine status badge classes
$status = $letter['approval_status'];
$statusDisplay = $letter['status'] == 'Completed' ? 'Completed' : $status;
if ($letter['status'] == 'Completed') {
    $statusClass = 'badge-status completed';
    $statusIcon = 'fa-circle-check';
} elseif ($status == 'Pending RA Approval') {
    $statusClass = 'badge-status pending-ra';
    $statusIcon = 'fa-clock';
} elseif ($status == 'Approved') {
    $statusClass = 'badge-status approved';
    $statusIcon = 'fa-check-circle';
} elseif ($status == 'Rejected') {
    $statusClass = 'badge-status rejected';
    $statusIcon = 'fa-ban';
} elseif ($status == 'Draft') {
    $statusClass = 'badge-status draft';
    $statusIcon = 'fa-pen-to-square';
} else {
    $statusClass = 'badge-status';
    $statusIcon = 'fa-question';
}

$method = $letter['dispatch_method'] ?? 'Email';
$methodClass = $method == 'Email' ? 'badge-method email' : ($method == 'By Hand' ? 'badge-method hand' : 'badge-method');
$methodIcon = $method == 'Email' ? 'fa-envelope' : ($method == 'By Hand' ? 'fa-hand' : 'fa-truck');

$created_at = new DateTime($letter['created_at']);
$formatted_created = $created_at->format('d M Y, H:i');
$time_ago = time_elapsed_string($letter['created_at']);

// Helper function (reuse from list)
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime) return 'N/A';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View External Letter | IDCMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67,24,255,0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px; --transition-speed: 0.3s;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
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
        .detail-card { background:var(--card-bg); border:1px solid var(--border); border-radius:20px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.05); }
        .detail-section { margin-bottom:30px; }
        .detail-section h6 { font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:700; margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:8px; }
        .detail-row { display:flex; flex-wrap:wrap; margin-bottom:12px; }
        .detail-label { width:160px; font-weight:600; color:var(--text-muted); font-size:0.85rem; flex-shrink:0; }
        .detail-value { flex:1; color:var(--text-main); word-break:break-word; }
        .badge-status { padding:6px 14px; border-radius:8px; font-size:0.75rem; font-weight:800; text-transform:uppercase; display:inline-flex; align-items:center; gap:6px; }
        .badge-status.pending-ra { background:rgba(255,171,0,0.15); color:#FFAB00; }
        .badge-status.approved { background:rgba(0,200,83,0.15); color:#00C853; }
        .badge-status.rejected { background:rgba(239,68,68,0.15); color:#ef4444; }
        .badge-status.completed { background:rgba(59,130,246,0.15); color:#3b82f6; }
        .badge-status.draft { background:rgba(160,160,160,0.15); color:#A0A0A0; }
        .badge-method { padding:4px 12px; border-radius:8px; font-size:0.7rem; font-weight:700; display:inline-flex; align-items:center; gap:4px; }
        .badge-method.email { background:rgba(117,81,255,0.15); color:var(--accent); }
        .badge-method.hand { background:rgba(255,165,0,0.15); color:#f59e0b; }
        .badge-method { background:rgba(160,160,160,0.15); color:var(--text-muted); }
        .btn-secondary { background:transparent; border:1px solid var(--border); color:var(--text-muted); border-radius:12px; padding:8px 24px; font-weight:700; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:0.2s; }
        .btn-secondary:hover { border-color:var(--accent); color:var(--accent); }
        .btn-primary { background:var(--accent); color:white; border:none; border-radius:12px; padding:8px 24px; font-weight:700; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:0.2s; }
        .btn-primary:hover { opacity:0.9; transform:translateY(-2px); color:white; }
        .file-link { color:var(--accent); text-decoration:none; font-weight:600; }
        .file-link:hover { text-decoration:underline; }
        .mobile-menu-toggle { position:fixed; top:20px; left:20px; z-index:1060; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; padding:10px; cursor:pointer; display:none; }
        @media (max-width:768px) {
            .sidebar { transform:translateX(-100%); position:fixed; }
            .sidebar.show { transform:translateX(0); }
            .main-content { margin-left:0; width:100%; padding:20px; }
            .mobile-menu-toggle { display:block; }
            .top-nav { flex-direction:column; align-items:stretch; gap:10px; }
            .detail-label { width:100%; margin-bottom:2px; }
            .detail-row { flex-direction:column; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br>
            <span style="font-size:0.7rem;color:var(--text-muted);">VIEW LETTER</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-house"></i> Home Page</a>
        <div style="font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;margin:25px 0 10px 15px;opacity:0.6;letter-spacing:1px;">Dispatching</div>
        <button class="nav-btn active" onclick="toggleSubmenu('outwardSub')">
            <i class="fa-solid fa-paper-plane"></i> External Letters
            <i class="fa-solid fa-chevron-down ms-auto" style="font-size:0.7rem;"></i>
        </button>
        <div id="outwardSub" class="submenu show">
            <a href="add_outward.php" class="sub-nav-btn">Add External</a>
            <a href="outward_list.php" class="sub-nav-btn active-sub">View External Summary</a>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-power-off"></i> Logout</a>
    </nav>
</aside>
<div class="mobile-menu-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></div>

<main class="main-content">
    <header class="top-nav">
        <div>
            <h4 class="fw-bold mb-0">External Letter Details</h4>
            <p class="small --text-muted mb-0">CDR: <?= htmlspecialchars($letter['cdr_no']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($letter['status'] != 'Completed'): ?>
                <a href="edit_outward.php?id=<?= $letter['id'] ?>" class="btn-primary"><i class="fa-solid fa-pen-to-square me-2"></i>Edit</a>
            <?php endif; ?>
            <a href="outward_list.php" class="btn-secondary"><i class="fa-solid fa-arrow-left me-2"></i>Back to Registry</a>
        </div>
    </header>

    <div class="content-container">
        <div class="detail-card">
            <!-- Status and Method badges at top -->
            <div class="d-flex flex-wrap gap-3 mb-4">
                <div>
                    <span class="badge-status <?= $statusClass ?>"><i class="fa-solid <?= $statusIcon ?>"></i> <?= htmlspecialchars($statusDisplay) ?></span>
                </div>
                <div>
                    <span class="badge-method <?= $methodClass ?>"><i class="fa-solid <?= $methodIcon ?>"></i> <?= htmlspecialchars($method) ?></span>
                </div>
                <?php if ($letter['status'] == 'Completed'): ?>
                    <span class="badge-status completed"><i class="fa-solid fa-check-circle"></i> Dispatched</span>
                <?php endif; ?>
            </div>

            <!-- Main Details -->
            <div class="detail-section">
                <h6><i class="fa-solid fa-file-lines me-2"></i> Letter Information</h6>
                <div class="detail-row">
                    <span class="detail-label">CDR No</span>
                    <span class="detail-value fw-bold"><?= htmlspecialchars($letter['cdr_no']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Letter Reference</span>
                    <span class="detail-value"><?= htmlspecialchars($letter['letter_ref'] ?: '—') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Title</span>
                    <span class="detail-value"><?= htmlspecialchars($letter['title']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Letter Date</span>
                    <span class="detail-value"><?= date('d M Y', strtotime($letter['letter_date'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Receiver(s)</span>
                    <span class="detail-value"><?= htmlspecialchars($letter['branch_names'] ?? $letter['receiver']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dispatch Method</span>
                    <span class="detail-value"><?= htmlspecialchars($method) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Description</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($letter['description'] ?: '—')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Comment</span>
                    <span class="detail-value"><?= nl2br(htmlspecialchars($letter['comment'] ?: '—')) ?></span>
                </div>
            </div>

            <!-- Attachment -->
            <?php if (!empty($letter['file_path']) && file_exists('../../' . $letter['file_path'])): ?>
                <div class="detail-section">
                    <h6><i class="fa-solid fa-paperclip me-2"></i> Attachment</h6>
                    <div class="detail-row">
                        <span class="detail-label">File</span>
                        <span class="detail-value">
                            <a href="../../<?= htmlspecialchars($letter['file_path']) ?>" target="_blank" class="file-link">
                                <i class="fa-solid fa-file-pdf me-1"></i> View / Download
                            </a>
                            <span class="--text-muted ms-2" style="font-size:0.8rem;">
                                (<?= round(filesize('../../' . $letter['file_path']) / 1024) ?> KB)
                            </span>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Creator & Timestamps -->
            <div class="detail-section">
                <h6><i class="fa-solid fa-clock me-2"></i> Creation Info</h6>
                <div class="detail-row">
                    <span class="detail-label">Created By</span>
                    <span class="detail-value"><?= htmlspecialchars($letter['creator_name'] ?? 'Unknown') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created At</span>
                    <span class="detail-value"><?= $formatted_created ?> <span class="--text-muted">(<?= $time_ago ?>)</span></span>
                </div>
                <?php if ($letter['submitted_at']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Submitted At</span>
                        <span class="detail-value"><?= date('d M Y, H:i', strtotime($letter['submitted_at'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approval / Rejection Details -->
            <?php if ($status == 'Approved' || $status == 'Rejected'): ?>
                <div class="detail-section">
                    <h6><i class="fa-solid fa-circle-check me-2"></i> Registry Assistant Action</h6>
                    <?php if ($status == 'Approved'): ?>
                        <div class="detail-row">
                            <span class="detail-label">Approved By</span>
                            <span class="detail-value"><?= htmlspecialchars($letter['approver_name'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Approved At</span>
                            <span class="detail-value"><?= date('d M Y, H:i', strtotime($letter['approved_at'])) ?></span>
                        </div>
                    <?php else: ?>
                        <div class="detail-row">
                            <span class="detail-label">Rejected By</span>
                            <span class="detail-value"><?= htmlspecialchars($letter['rejecter_name'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Rejected At</span>
                            <span class="detail-value"><?= date('d M Y, H:i', strtotime($letter['rejected_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Dispatch Details (if completed) -->
            <?php if ($letter['status'] == 'Completed'): ?>
                <div class="detail-section">
                    <h6><i class="fa-solid fa-paper-plane me-2"></i> Dispatch Details</h6>
                    <div class="detail-row">
                        <span class="detail-label">Dispatched By</span>
                        <span class="detail-value"><?= htmlspecialchars($letter['dispatcher_name'] ?? 'Unknown') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Handed Over To</span>
                        <span class="detail-value"><?= htmlspecialchars($letter['handed_over_to'] ?: '—') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Dispatched At</span>
                        <span class="detail-value"><?= date('d M Y, H:i', strtotime($letter['handed_over_date'])) ?></span>
                    </div>
                    <?php if (!empty($letter['dispatch_remarks'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Remarks</span>
                            <span class="detail-value"><?= nl2br(htmlspecialchars($letter['dispatch_remarks'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    document.body.setAttribute('data-theme', savedTheme);

    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('show');
    }
    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        if(sub) sub.classList.toggle('show');
    }
</script>
</body>
</html>