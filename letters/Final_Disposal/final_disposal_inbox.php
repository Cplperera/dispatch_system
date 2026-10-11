<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('FINAL_DISPOSAL')) {
    die("Access Denied.");
}

$current_desk_id = $_SESSION['desk_id'] ?? null;
if (!$current_desk_id) {
    die("System Error: Your account is not assigned to a desk.");
}

/* -----------------------------
    FETCH DESK NAME FOR DISPLAY
------------------------------*/
$display_desk_name = "Unknown Desk";
$desk_query = "SELECT desk_name FROM desks WHERE id = ?";
$desk_stmt = $conn->prepare($desk_query);
$desk_stmt->bind_param("i", $current_desk_id);
$desk_stmt->execute();
$desk_res = $desk_stmt->get_result();
if ($desk_row = $desk_res->fetch_assoc()) {
    $display_desk_name = $desk_row['desk_name'];
}

/* -----------------------------
    MAIN QUERY
------------------------------*/
$query = "SELECT fd.id as disposal_id, l.id as letter_id, l.cdr_number, l.letter_title, l.attachment,
                 l.letter_reference, l.letter_date,
                 fd.forwarded_at, u.name_with_initial as sender, d.desk_name
          FROM internal_letters_final_disposal fd
          JOIN internal_letters l ON fd.letter_id = l.id
          JOIN users u ON fd.forwarded_by = u.id
          JOIN desks d ON fd.forwarded_to = d.id
          WHERE fd.forwarded_to = ? AND fd.status = 'Pending'
          ORDER BY fd.forwarded_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_desk_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposal Inbox | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        /* ══════════════ LIGHT THEME (default) ══════════════ */
        :root {
            --bg-body: #F0F2F5;
            --sidebar-bg: #FFFFFF;
            --card-bg: #FFFFFF;
            --accent: #4318FF;
            --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559;
            --text-muted: #707EAE;
            --border: #DDE2EF;
            --row-bg: rgba(0, 0, 0, 0.015);
            --row-bg-hover: rgba(67, 24, 255, 0.04);
            --input-bg: #F4F6FA;
            --modal-pdf-bg: #E9ECF3;
            --sidebar-width: 280px;
            --transition-speed: 0.3s;
        }

        /* ══════════════ DARK THEME ══════════════ */
        [data-theme="dark"] {
            --bg-body: #0B1437;
            --sidebar-bg: #111C44;
            --card-bg: #111C44;
            --accent: #7551FF;
            --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --border: rgba(255, 255, 255, 0.1);
            --row-bg: rgba(255, 255, 255, 0.02);
            --row-bg-hover: rgba(117, 81, 255, 0.08);
            --input-bg: rgba(255, 255, 255, 0.05);
            --modal-pdf-bg: #1a1a2a;
            color-scheme: dark;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            margin: 0;
            font-family: "Segoe UI", 'Iskoola Pota', 'Noto Sans Sinhala', system-ui, sans-serif;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        /* ══════════════ SIDEBAR ══════════════ */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 30px 15px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: background var(--transition-speed), border var(--transition-speed);
        }
        .brand-name {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--text-main);
            line-height: 1.2;
            transition: color var(--transition-speed);
        }
        .brand-name .text-primary { color: var(--accent) !important; }

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
        .nav-btn:hover,
        .nav-btn.active {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .submenu { padding-left: 20px; display: none; }
        .submenu.show { display: block; }

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

        /* ══════════════ MAIN CONTENT ══════════════ */
        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
        }

        /* Table & Search */
        .table-container {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 25px;
            transition: background var(--transition-speed), border-color var(--transition-speed);
        }

        .search-wrapper { position: relative; max-width: 400px; margin-bottom: 20px; }
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .table-search {
            background: var(--input-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            width: 100%;
            transition: 0.3s;
        }
        .table-search::placeholder { color: var(--text-muted); opacity: 0.7; }
        .table-search:focus {
            background: var(--input-bg);
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
            color: var(--text-main);
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .custom-table thead th {
            border: none;
            color: var(--text-muted);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 15px;
            background: transparent;
        }
        .letter-row td {
            background: var(--row-bg);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 15px;
            vertical-align: middle;
            color: var(--text-main);
            transition: background var(--transition-speed);
        }
        .letter-row:hover td { background: var(--row-bg-hover); }
        .letter-row td:first-child {
            border-left: 1px solid var(--border);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .letter-row td:last-child {
            border-right: 1px solid var(--border);
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .cdr-pill {
            background: var(--accent-soft);
            padding: 4px 10px;
            border-radius: 8px;
            color: var(--accent);
            font-weight: 800;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
        }

        .btn-action {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            color: #fff;
        }
        .btn-view {
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.8rem;
            margin-right: 5px;
            transition: 0.2s;
        }
        .btn-view:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ══════════════ THEME TOGGLE ══════════════ */
        .btn-theme {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
            font-size: 1rem;
        }
        .btn-theme:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }

        /* ══════════════ MODAL ══════════════ */
        .modal-xl { max-width: 95vw; }
        .modal-content {
            height: 90vh;
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
        }
        .modal-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
        }
        #pdfContainer {
            background: var(--modal-pdf-bg);
            flex-grow: 1;
        }

        /* Fix close button visibility in light mode */
        [data-theme="light"] .btn-close,
        :root .btn-close { filter: none; }
        [data-theme="dark"] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        /* Small utilities */
        .text-soft { color: var(--text-muted) !important; }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 2.5rem;
            opacity: 0.4;
            margin-bottom: 12px;
            display: block;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br>
            <span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span>
        </div>
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
                <a href="../inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="#" class="sub-nav-btn" style="color:var(--accent);font-weight:800;">Disposal Inbox</a>
            </div>
        </div>

        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </nav>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1">Final Disposal Inbox - <?= htmlspecialchars($display_desk_name) ?> Desk</h3>
            <p class="text-soft small mb-0">Manage final disposal records for your assigned desk.</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="badge p-2 px-3 rounded-pill"
                 style="background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent) !important; opacity: 0.9;">
                <i class="fa-solid fa-desktop me-2"></i>Active Desk: <?= htmlspecialchars($display_desk_name) ?>
            </div>

            <!-- ═══════ THEME TOGGLE ═══════ -->
            <button class="btn-theme" id="themeToggle" title="Toggle theme" type="button">
                <i class="fa-solid fa-moon" id="theme-icon"></i>
            </button>
        </div>
    </div>

    <div class="search-wrapper">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" id="inboxSearch" class="table-search" placeholder="Search by CDR No, Subject or Sender..." onkeyup="filterInbox()">
    </div>

    <div class="table-container shadow-sm">
        <div class="table-responsive">
            <table class="custom-table" id="inboxTable">
                <thead>
                    <tr>
                        <th style="width: 15%">Ref No</th>
                        <th style="width: 35%">Title</th>
                        <th style="width: 20%">Forwarded By</th>
                        <th style="width: 10%">Date</th>
                        <th class="text-end" style="width: 20%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-inbox"></i>
                                    <div class="fw-bold">No pending disposal items</div>
                                    <div class="small">There are no letters currently waiting for disposal at your desk.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while($row = $result->fetch_assoc()):
                            $jsonPayload = json_encode([
                                'id' => $row['letter_id'],
                                'cdr_number' => $row['cdr_number'],
                                'letter_title' => $row['letter_title'],
                                'attachment' => $row['attachment']
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr class="letter-row">
                            <td><span class="cdr-pill"><?= htmlspecialchars($row['cdr_number']) ?></span></td>
                            <td class="letter-title">
                                <div class="fw-bold"><?= htmlspecialchars($row['letter_title']) ?></div>
                            </td>
                            <td class="sender-name">
                                <div class="text-soft small"><?= htmlspecialchars($row['sender']) ?></div>
                            </td>
                            <td>
                                <div class="text-soft small"><?= date('d M Y', strtotime($row['forwarded_at'])) ?></div>
                            </td>
                            <td class="text-end">
                                <?php if(!empty($row['attachment'])): ?>
                                    <button class="btn btn-view preview-trigger"
                                            data-payload='<?= htmlspecialchars($jsonPayload, ENT_QUOTES, 'UTF-8') ?>'>
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="process_disposal.php?id=<?= $row['disposal_id'] ?>" class="btn btn-action">
                                    <i class="fa-solid fa-file-import me-1"></i> File
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content d-flex flex-column">
            <div class="modal-header-bar">
                <h6 class="mb-0 fw-bold" id="modalDocTitle">Document Preview</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div id="pdfContainer" class="flex-grow-1"></div>
        </div>
    </div>
</div>

<script>
    /* ═══════════════════════════════════════════════════
       THEME — applied before first paint to avoid flash
       ═══════════════════════════════════════════════════ */
    (function () {
        const saved = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
    })();

    function toggleSubmenu(id) {
        document.getElementById(id).classList.toggle('show');
    }

    /* ═══════════════════════════════════════════════════
       SEARCH FILTER
       ═══════════════════════════════════════════════════ */
    function filterInbox() {
        const input = document.getElementById('inboxSearch');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('inboxTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const row = tr[i];
            // Skip the empty-state row (it has a colspan cell)
            if (row.querySelector('td[colspan]')) continue;
            const text = row.textContent || row.innerText;
            row.style.display = (text.toLowerCase().indexOf(filter) > -1) ? "" : "none";
        }
    }

    /* ═══════════════════════════════════════════════════
       PREVIEW MODAL
       ═══════════════════════════════════════════════════ */
    document.querySelectorAll('.preview-trigger').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.payload);
            document.getElementById('modalDocTitle').innerText = `${data.cdr_number} | ${data.letter_title}`;
            const container = document.getElementById('pdfContainer');
            if (data.attachment) {
                const pdfUrl = `../../uploads/letters/${encodeURIComponent(data.attachment)}`;
                container.innerHTML = `<embed src="${pdfUrl}#toolbar=0" type="application/pdf" width="100%" height="100%" />`;
            } else {
                container.innerHTML = `<div class="p-5 text-center text-soft">No file attached</div>`;
            }
            const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
            modal.show();
        });
    });

    /* ═══════════════════════════════════════════════════
       THEME — icon sync + toggle handler
       ═══════════════════════════════════════════════════ */
    window.addEventListener('DOMContentLoaded', () => {
        const saved = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
        const icon = document.getElementById('theme-icon');
        if (saved === 'light' && icon) {
            icon.classList.replace('fa-moon', 'fa-sun');
        }
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#themeToggle');
        if (!btn) return;
        const html = document.documentElement;
        const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        html.setAttribute('data-theme', next);
        localStorage.setItem('dispatch_theme', next);
        const icon = document.getElementById('theme-icon');
        if (icon) {
            icon.classList.toggle('fa-moon', next === 'dark');
            icon.classList.toggle('fa-sun',  next === 'light');
        }
    });
</script>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>