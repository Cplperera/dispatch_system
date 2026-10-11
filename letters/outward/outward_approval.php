<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();


if (!hasPermission('APPROVE_OUTWARD')) {
    die('Access Denied! Only Registry Assistants can approve outward letters.');
}

$error = $success = '';

// Approve Action
if (isset($_GET['approve']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $update = $conn->prepare("UPDATE outward_letters 
                              SET approval_status = 'Approved', 
                                  approved_by = ?, 
                                  approved_at = NOW() 
                              WHERE id = ? AND approval_status = 'Pending RA Approval'");
    $update->bind_param("ii", $user_id, $id);
    if ($update->execute() && $update->affected_rows > 0) {
        $success = "✅ Outward letter #$id has been APPROVED and forwarded to Dispatch Clerk.";
    } else {
        $error = "⚠️ Failed to approve. It may have already been processed.";
    }
    $update->close();
}

// Reject Action
if (isset($_GET['reject']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    $update = $conn->prepare("UPDATE outward_letters 
                              SET approval_status = 'Rejected', 
                                  rejected_by = ?, 
                                  rejected_at = NOW() 
                              WHERE id = ? AND approval_status = 'Pending RA Approval'");
    $update->bind_param("ii", $user_id, $id);
    if ($update->execute() && $update->affected_rows > 0) {
        $success = "❌ Outward letter #$id has been REJECTED and returned to the creator.";
    } else {
        $error = "⚠️ Failed to reject. It may have already been processed.";
    }
    $update->close();
}

// Fetch pending approvals
$pendingLetters = [];
$query = "SELECT ol.*, 
          (SELECT GROUP_CONCAT(branch_name SEPARATOR ', ') FROM external_branches WHERE FIND_IN_SET(id, ol.receiver)) AS branch_names,
          u.name_with_initial AS creator_name
          FROM outward_letters ol
          LEFT JOIN users u ON ol.created_by = u.id
          WHERE ol.approval_status = 'Pending RA Approval' AND ol.is_deleted = 0
          ORDER BY ol.created_at ASC";
$res = $conn->query($query);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pendingLetters[] = $row;
    }
}

$pendingCount = count($pendingLetters);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>RA Approval | Outward</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root[data-theme='dark'] { --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44; --accent: #7551FF; --text-main: #FFFFFF; --border: rgba(255,255,255,0.1); --text-muted: #A3AED0; }
        :root { --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF; --accent: #4318FF; --text-main: #1B2559; --border: #DDE2EF; --text-muted: #707EAE; --sidebar-width: 280px; }
        body { background: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 25px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .main-content { margin-left: var(--sidebar-width); padding: 30px; flex: 1; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .table { color: var(--text-main); }
        .table thead th { border: none; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px; }
        .table tbody td { border-top: 1px solid var(--border); padding: 15px; vertical-align: middle; }
        .badge-ref { background: rgba(117, 81, 255, 0.1); color: var(--accent); padding: 6px 12px; border-radius: 8px; font-weight: 700; }
        .badge-status.pending-ra { background: rgba(255, 182, 0, 0.15); color: #FFB600; }
        .btn-approve { background: #00C864; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 0.7rem; text-decoration: none; display: inline-block; }
        .btn-approve:hover { opacity: 0.8; color: white; }
        .btn-reject { background: #FF5B5B; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 0.7rem; text-decoration: none; display: inline-block; }
        .btn-reject:hover { opacity: 0.8; color: white; }
        .btn-view { background: transparent; color: var(--text-muted); border: 1px solid var(--border); padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.7rem; text-decoration: none; display: inline-block; }
        .btn-view:hover { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); }
        .action-cell { display: flex; gap: 6px; flex-wrap: wrap; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: rgba(117, 81, 255, 0.1); color: var(--text-main); }
        .submenu { padding-left: 20px; display: none; flex-direction: column; }
        .submenu.show { display: flex; }
        .sub-nav-btn { padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.8rem; border-radius: 8px; }
        .sub-nav-btn:hover, .sub-nav-btn.active-sub { color: var(--accent); background: rgba(255, 255, 255, 0.05); }
        .stats-badge { background: rgba(117, 81, 255, 0.1); color: var(--accent); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 0.85rem; }
        .stats-badge i { margin-right: 8px; }
        .brand-name { font-weight: 800; font-size: 1.2rem; line-height: 1.2; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> IDCMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">RA Approval</span></div>
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
                <a href="add_outward.php" class="sub-nav-btn">Add Outward</a>
                <a href="outward_list.php" class="sub-nav-btn">View Registry</a>
                <a href="outward_approval.php" class="sub-nav-btn active-sub">RA Approval <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?></span></a>
            </div>
        </div>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">RA Approval Dashboard</h2>
            <p class="text-muted small">Approve or reject outward letters</p>
        </div>
        <div class="stats-badge">
            <i class="fa-solid fa-clock"></i> Pending: <?= $pendingCount ?>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <div class="card">
        <?php if (empty($pendingLetters)): ?>
            <div class="text-center py-5">
                <i class="fa-solid fa-check-circle text-success" style="font-size: 3rem;"></i>
                <h5 class="mt-3">All caught up! No pending approvals.</h5>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>CDR</th>
                            <th>Ref</th>
                            <th>Title</th>
                            <th>Receiver(s)</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingLetters as $row): ?>
                        <tr>
                            <td><span class="badge-ref"><?= htmlspecialchars($row['cdr_no']) ?></span></td>
                            <td class="text-muted small"><?= htmlspecialchars($row['letter_ref']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['branch_names'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['creator_name']) ?></td>
                            <td>
                                <div class="action-cell">
                                    <a href="?approve=1&id=<?= $row['id'] ?>" class="btn-approve" onclick="return confirm('Approve this outward letter?')">
                                        <i class="fa-solid fa-check-circle"></i> Approve
                                    </a>
                                    <a href="?reject=1&id=<?= $row['id'] ?>" class="btn-reject" onclick="return confirm('Reject this outward letter?')">
                                        <i class="fa-solid fa-times-circle"></i> Reject
                                    </a>
                                    <a href="view_outward.php?id=<?= $row['id'] ?>" class="btn-view">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.documentElement.setAttribute('data-theme', localStorage.getItem('dispatch_theme') || 'dark');
    
    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        if(!sub) return;
        sub.classList.toggle('show');
        const icon = sub.previousElementSibling?.querySelector('.fa-chevron-down');
        if(icon) icon.style.transform = sub.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        }, 5000);
    });
</script>
</body>
</html>