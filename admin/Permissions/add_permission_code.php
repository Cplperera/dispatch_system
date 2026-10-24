<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

/* ─── Page permission ──────────────────────────────── */
if (!hasPermission('MANAGE_PERMISSIONS')) {
    die('Access Denied!');
}

/* ─── Fetch distinct categories for datalist suggestions ── */
$existingCategories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM permission_codes WHERE category IS NOT NULL AND category <> '' ORDER BY category");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) $existingCategories[] = $r['category'];
}

$error   = '';
$success = '';

/* ─── Form handler ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = strtoupper(trim($_POST['code']        ?? ''));
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category']    ?? '');
    $status      = 'Active';

    if ($code === '' || $description === '') {
        $error = 'Code and Description are required.';
    } elseif (!preg_match('/^[A-Z0-9_]+$/', $code)) {
        $error = 'Code must contain only uppercase letters, numbers, and underscores (e.g. VIEW_LETTERS).';
    } elseif (mb_strlen($description) > 255) {
        $error = 'Description must be 255 characters or fewer.';
    } else {
        /* Duplicate check */
        $check = $conn->prepare("SELECT id FROM permission_codes WHERE code = ?");
        $check->bind_param("s", $code);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Permission code already exists.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO permission_codes (code, description, category, status)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $code, $description, $category, $status);

            if ($stmt->execute()) {
                $success = 'Permission added successfully!';
                /* Clear the form values on success */
                $code = $description = $category = '';
            } else {
                $error = 'Database error: ' . htmlspecialchars($conn->error);
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Permission Code | IDCMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700;800&display=swap" rel="stylesheet">
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
    --ok:#10b981; --warn:#f59e0b; --err:#ef4444;
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
    color-scheme: dark;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh;
    font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', 'Segoe UI', system-ui, sans-serif; transition: 0.3s; }

/* ═══════════════════ SIDEBAR ═══════════════════ */
.sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border);
    padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }

.brand-box { margin-bottom: 24px; padding: 0 8px; }
.brand-name { font-weight: 800; font-size: 1.15rem; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
.brand-name i { color: var(--accent); font-size: 1.25rem; }

.nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted);
    text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
    transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left;
    cursor: pointer; gap: 10px; }
.nav-btn i:first-child { width: 18px; text-align: center; }
.nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
.nav-btn i.small { font-size: 0.7rem; margin-left: auto; opacity: 0.7; transition: transform 0.25s; }

.submenu { padding-left: 20px; display: none; }
.submenu.show { display: block; }
.sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none;
    font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
.sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
.sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

.section-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted);
    text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7; letter-spacing: 1px; }

/* ═══════════════════ MAIN ═══════════════════ */
.main { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
.page-head h2 { font-size: 1.4rem; font-weight: 800; margin: 0 0 4px; color: var(--text-main); }
.page-head p { color: var(--text-muted); font-size: 0.88rem; margin: 0 0 22px; }

.card-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 26px;
    margin-bottom: 20px; transition: background 0.25s, border-color 0.25s; }
.card-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
    color: var(--text-muted); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--accent); }

/* Form */
.form-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 8px; display: block; }
.form-control, .form-select {
    width: 100%; background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main);
    border-radius: 12px; padding: 11px 16px; font-size: 0.9rem; transition: 0.2s; font-family: inherit;
}
.form-control::placeholder { color: var(--placeholder-color); opacity: 0.8; font-weight: 400; }
.form-control:focus, .form-select:focus {
    background: var(--bg-body); color: var(--text-main); border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft); outline: none;
}
.form-control.mono { font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 0.3px;
    text-transform: uppercase; }
.form-text { font-size: 0.72rem; color: var(--text-muted); margin-top: 6px; display: block; }

/* Buttons */
.btn-primary-c { display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: #fff;
    border: none; padding: 11px 22px; border-radius: 12px; font-weight: 800; font-size: 0.85rem;
    cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-primary-c:hover { opacity: 0.9; transform: translateY(-1px); color: #fff; }

.btn-ghost-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--text-muted);
    border: 1px solid var(--border); padding: 10px 20px; border-radius: 12px; font-weight: 700;
    font-size: 0.83rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-ghost-c:hover { border-color: var(--accent); color: var(--accent); }

/* Alert */
.alert-box { padding: 14px 18px; border-radius: 12px; font-size: 0.87rem; font-weight: 600;
    margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-box.err { background: rgba(239,68,68,0.1); color: var(--err); border-left: 4px solid var(--err); }
.alert-box.ok  { background: rgba(16,185,129,0.1); color: var(--ok); border-left: 4px solid var(--ok); }
.alert-box i { font-size: 1rem; }
.alert-box a { color: inherit; font-weight: 800; text-decoration: underline; margin-left: 6px; }

/* Info bar */
.info-card { background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px;
    padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.info-card .icon-wrap { width: 42px; height: 42px; border-radius: 10px; background: var(--accent-soft);
    display: flex; align-items: center; justify-content: center; color: var(--accent); font-size: 1.1rem; flex-shrink: 0; }
.info-card .body { flex: 1; min-width: 0; }
.info-card .label { font-size: 0.65rem; color: var(--text-muted); font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.8px; }
.info-card .value { font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 2px; }

/* Live code preview */
.code-preview {
    display: inline-block; padding: 5px 12px; border-radius: 8px;
    background: var(--accent-soft); color: var(--accent);
    font-family: 'Courier New', monospace; font-weight: 700; font-size: 0.8rem;
    letter-spacing: 0.3px; transition: 0.2s; min-width: 60px; text-align: center;
}

/* Footer buttons */
.form-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;
    padding-top: 20px; border-top: 1px solid var(--border); margin-top: 26px; }

/* Success panel */
.success-panel { text-align: center; padding: 40px 20px; }
.success-panel .icon-big { width: 80px; height: 80px; border-radius: 50%;
    background: rgba(16,185,129,0.12); color: var(--ok); display: inline-flex;
    align-items: center; justify-content: center; font-size: 2.4rem; margin-bottom: 18px; }
.success-panel h3 { font-weight: 800; font-size: 1.2rem; margin-bottom: 8px; color: var(--text-main); }
.success-panel p { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 22px; }

@media(max-width:900px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; width: 100%; padding: 20px; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions > * { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>

    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>

        <div class="section-label">Dispatching</div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="internalSub" class="submenu">
                <a href="../letters/inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../letters/inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="../letters/inward/inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('taskSub')">
                <i class="fa-solid fa-list-check"></i> Task Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="taskSub" class="submenu">
                <a href="../letters/tasking/task_letters.php" class="sub-nav-btn">Add Task</a>
                <a href="../letters/tasking/task_list.php" class="sub-nav-btn">View Tasks</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('outwardSub')">
                <i class="fa-solid fa-paper-plane"></i> Outward Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="outwardSub" class="submenu">
                <a href="../letters/outward/add_outward.php" class="sub-nav-btn">Add Outward</a>
                <a href="../letters/outward/outward_list.php" class="sub-nav-btn">View Outward</a>
            </div>
        </div>

        <div class="section-label">Records</div>

        <a href="../reports/linkage_report.php" class="nav-btn">
            <i class="fa-solid fa-diagram-project"></i> Linkage Report
        </a>

        <div class="section-label">Administration</div>

        <a href="permission_codes.php" class="nav-btn active">
            <i class="fa-solid fa-key"></i> Permission Codes
        </a>

        <a href="../auth/logout.php" class="nav-btn mt-4" style="color:#ef4444;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main">
    <!-- ═══ Header ═══ -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="page-head">
            <h2><i class="fa-solid fa-plus me-2" style="color:var(--accent);"></i>Add Permission Code</h2>
            <p>Create a new permission code for the system</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="permission_codes.php" class="btn-ghost-c">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- ═══ Alerts ═══ -->
    <?php if ($error): ?>
        <div class="alert-box err">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- ═══ Success panel (shown after success) ═══ -->
    <?php if ($success): ?>
        <div class="card-box">
            <div class="success-panel">
                <div class="icon-big">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3><?= htmlspecialchars($success) ?></h3>
                <p>The new permission code is now available in the system.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="permission_codes.php" class="btn-primary-c">
                        <i class="fa-solid fa-list"></i> View Permission Codes
                    </a>
                    <a href="add_permission_code.php" class="btn-ghost-c">
                        <i class="fa-solid fa-plus"></i> Add Another
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>

    <!-- ═══ Main form card ═══ -->
    <div class="card-box">
        <div class="card-title">
            <i class="fa-solid fa-key"></i>
            New Permission Details
        </div>

        <!-- Info bar -->
        <div class="info-card">
            <div class="icon-wrap">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="body">
                <div class="label">Live Code Preview</div>
                <div class="value">
                    <span class="code-preview" id="codePreview">CODE_HERE</span>
                </div>
            </div>
        </div>

        <form method="post" autocomplete="off" id="addForm">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        Permission Code <span style="color:var(--err);">*</span>
                    </label>
                    <input type="text" name="code" id="codeInput"
                           class="form-control mono"
                           placeholder="e.g. VIEW_LETTERS"
                           value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                           pattern="[A-Z0-9_]+"
                           title="Uppercase letters, numbers, underscores only"
                           maxlength="100"
                           required autofocus>
                    <small class="form-text">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Uppercase letters, numbers, underscores only. Must be unique.
                    </small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="categoryInput"
                           class="form-control"
                           placeholder="e.g. Letters, Reports, Administration"
                           value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
                           list="categoryList"
                           maxlength="100">
                    <datalist id="categoryList">
                        <?php foreach ($existingCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="form-text">
                        <i class="fa-solid fa-lightbulb me-1"></i>
                        Pick an existing category or type a new one.
                    </small>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Description <span style="color:var(--err);">*</span>
                    </label>
                    <input type="text" name="description"
                           class="form-control"
                           placeholder="Short description of what this permission allows"
                           value="<?= htmlspecialchars($_POST['description'] ?? '') ?>"
                           maxlength="255"
                           required>
                    <small class="form-text">
                        <i class="fa-solid fa-info-circle me-1"></i>
                        Max 255 characters.
                    </small>
                </div>

                <div class="col-12">
                    <label class="form-label">Status</label>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
                                background:var(--bg-body);border:1px solid var(--border);
                                border-radius:12px;font-size:0.85rem;">
                        <span style="display:inline-flex;align-items:center;gap:8px;
                                     padding:5px 12px;border-radius:8px;
                                     background:rgba(16,185,129,0.15);color:var(--ok);
                                     font-weight:800;font-size:0.75rem;text-transform:uppercase;">
                            <span style="width:8px;height:8px;border-radius:50%;background:var(--ok);"></span>
                            Active
                        </span>
                        <span style="color:var(--text-muted);font-size:0.78rem;">
                            New permissions are always created as <strong>Active</strong> by default.
                            You can change this later from the Edit page.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <a href="permission_codes.php" class="btn-ghost-c">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <button type="reset" class="btn-ghost-c" id="resetBtn">
                    <i class="fa-solid fa-rotate-left"></i> Clear Form
                </button>
                <button type="submit" class="btn-primary-c">
                    <i class="fa-solid fa-plus"></i> Add Permission
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>
</main>

<script>
/* ─── Theme — read from localStorage (set by home page) ─── */
const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);

/* ─── Sidebar submenu toggle ─────────────────────── */
function toggleSubmenu(id) {
    var submenu = document.getElementById(id);
    if (!submenu) return;
    var btn = submenu.previousElementSibling;
    var icon = btn ? btn.querySelector('.fa-chevron-down') : null;

    if (submenu.classList.contains('show')) {
        submenu.classList.remove('show');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('show');
        if (icon) icon.style.transform = 'rotate(180deg)';
    }
}

/* ─── Live code preview + auto-uppercase ─────────── */
(function() {
    var codeInput = document.getElementById('codeInput');
    var preview   = document.getElementById('codePreview');
    if (!codeInput || !preview) return;

    function refresh() {
        preview.textContent = codeInput.value || 'CODE_HERE';
    }
    codeInput.addEventListener('input', function() {
        // Auto-uppercase & convert spaces/dashes to underscores
        var pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/[\s\-]+/g, '_');
        this.setSelectionRange(pos, pos);
        refresh();
    });
    refresh();
})();

/* ─── Reset button also refreshes preview ─────────── */
(function() {
    var resetBtn = document.getElementById('resetBtn');
    if (!resetBtn) return;
    resetBtn.addEventListener('click', function() {
        setTimeout(function() {
            var preview = document.getElementById('codePreview');
            if (preview) preview.textContent = 'CODE_HERE';
        }, 10);
    });
})();

/* ─── Auto-fade alerts ───────────────────────────── */
(function() {
    var alerts = document.querySelectorAll('.alert-box');
    alerts.forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 400);
        }, 5000);
    });
})();

/* ─── Keyboard shortcuts ─────────────────────────── */
document.addEventListener('keydown', function(e) {
    // Ctrl+S = save
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        var f = document.getElementById('addForm');
        if (f) f.requestSubmit();
    }
    // Esc = cancel
    if (e.key === 'Escape') {
        window.location.href = 'permission_codes.php';
    }
});
</script>
</body>
</html>