<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('MANAGE_PERMISSIONS')) {
    die('Access Denied!');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch user details (including user_category)
$user = null;
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, name_with_initial, user_category, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    die('Invalid user ID.');
}

// Handle POST (AJAX will call this via fetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    // Delete old permissions
    $conn->query("DELETE FROM user_permissions WHERE user_id = $user_id");

    // Insert new permissions
    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
        $stmt = $conn->prepare("INSERT INTO user_permissions (user_id, permission_code, is_allowed) VALUES (?, ?, 1)");
        foreach ($_POST['permissions'] as $perm_code) {
            $stmt->bind_param("is", $user_id, $perm_code);
            $stmt->execute();
        }
        $stmt->close();
    }
    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully!']);
    exit;
}

// Fetch all permission codes (only active ones for assignment)
$all_permissions = [];
$res = $conn->query("SELECT code, description, category FROM permission_codes WHERE status = 'Active' ORDER BY category, description");
while ($row = $res->fetch_assoc()) {
    $all_permissions[] = $row;
}

// Group by category
$grouped = [];
foreach ($all_permissions as $perm) {
    $cat = $perm['category'] ?? 'General';
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }
    $grouped[$cat][] = $perm;
}

// Fetch user's current permissions
$user_perms = [];
$res = $conn->query("SELECT permission_code FROM user_permissions WHERE user_id = $user_id AND is_allowed = 1");
while ($row = $res->fetch_assoc()) {
    $user_perms[] = $row['permission_code'];
}

$total_perms = count($all_permissions);
$categories = array_keys($grouped);
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Permissions | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
            --placeholder-color: #A3AED0;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
            --placeholder-color: #707EAE;
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; border-bottom: 1px solid var(--border); }

        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; margin: 30px; box-shadow: 0px 18px 40px rgba(112, 144, 176, 0.12); }
        [data-theme="dark"] .form-container { box-shadow: none; }

        .section-header { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .section-header i { color: var(--accent); font-size: 1.2rem; }

        .btn-save { background: var(--accent); color: white; border: none; padding: 12px 35px; border-radius: 12px; font-weight: 700; transition: 0.2s; }
        .btn-save:hover { opacity: 0.9; transform: translateY(-1px); }

        .search-box { background: var(--bg-body); border: 1px solid var(--border); border-radius: 30px; padding: 6px 16px; display: flex; align-items: center; gap: 10px; max-width: 240px; }
        .search-box input { background: transparent; border: none; outline: none; color: var(--text-main); width: 100%; font-size: 0.85rem; }
        .search-box input::placeholder { color: var(--placeholder-color); opacity: 0.8; }

        /* Modern List Design Styles */
        .category-wrapper {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .category-header {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            font-weight: 700;
            color: var(--accent);
        }

        .perm-list {
            display: flex;
            flex-direction: column;
        }
        .perm-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            transition: 0.2s;
        }
        .perm-item:last-child {
            border-bottom: none;
        }
        .perm-item:hover {
            background: var(--accent-soft);
        }
        .perm-item .desc {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .perm-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .stats-bar {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast-custom { background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); border-radius: 16px; padding: 16px 24px; box-shadow: 0 8px 30px rgba(0,0,0,0.2); min-width: 280px; display: flex; align-items: center; gap: 12px; margin-bottom: 10px; animation: slideIn 0.3s ease; }
        .toast-custom.success { border-left: 4px solid #2ecc71; }
        .toast-custom.error { border-left: 4px solid #e74c3c; }
        .toast-custom i { font-size: 1.3rem; }
        .toast-custom .msg { flex: 1; font-size: 0.9rem; font-weight: 500; }
        .toast-custom .close-toast { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem; padding: 0 4px; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }

        @media (max-width: 768px) {
            .sidebar { width: 70px; padding: 20px 10px; }
            .sidebar .brand-name span { display: none; }
            .main-content { margin-left: 70px; width: calc(100% - 70px); }
            .top-nav { padding: 15px; flex-wrap: wrap; }
            .form-container { margin: 15px; padding: 20px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../../letters/dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">System Admin</div>
        <a href="../users/users.php" class="nav-btn"><i class="fa-solid fa-users-gear me-2"></i> Manage Users</a>
        <a href="permission_codes.php" class="nav-btn"><i class="fa-solid fa-lock me-2"></i> Permission Codes</a>
        <a href="assign_permissions.php?id=<?= $user_id ?>" class="nav-btn active"><i class="fa-solid fa-user-shield me-2"></i> Assign Permissions</a>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="fw-bold mb-0 text-main">Assign Permissions</h4>
            <p class="--text-muted small mb-0 mt-1">
                <strong><?= htmlspecialchars($user['username']) ?></strong> 
                (<?= htmlspecialchars($user['name_with_initial']) ?>) 
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?= htmlspecialchars($user['user_category']) ?></span>
                <span class="badge bg-success bg-opacity-10 text-success ms-1"><?= htmlspecialchars($user['status']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="search-box">
                <i class="fa-solid fa-search text-muted"></i>
                <input type="text" id="searchInput" placeholder="Search permissions...">
            </div>
            <select id="categoryFilter" class="form-select" style="border-radius:30px; padding:6px 20px; font-size:0.85rem; background:var(--bg-body); border:1px solid var(--border); color:var(--text-main); width:auto;">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="../users/users.php" class="btn btn-sm btn-light border px-3 rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </header>

    <div class="form-container">
        <div class="section-header"><i class="fa-solid fa-lock"></i> Permission Matrix</div>

        <!-- Global actions -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div class="stats-bar">
                <span id="selectedCount">0</span> of <span id="totalCount"><?= $total_perms ?></span> permissions selected
            </div>
            <div>
                <a class="text-primary small fw-bold me-3" id="selectAllGlobal" style="cursor:pointer; text-decoration:none;">
                    <i class="fa-regular fa-square-check me-1"></i> Select All
                </a>
                <a class="text-danger small fw-bold" id="deselectAllGlobal" style="cursor:pointer; text-decoration:none;">
                    <i class="fa-regular fa-square me-1"></i> Clear All
                </a>
            </div>
        </div>

        <!-- Permission groups -->
        <form id="permForm" method="post">
            <input type="hidden" name="action" value="save_permissions">
            
            <div id="permissionListContainer">
                <?php foreach ($grouped as $category => $perms): ?>
                    <div class="category-wrapper" data-category="<?= htmlspecialchars($category) ?>">
                        <div class="category-header">
                            <span><i class="fa-regular fa-folder-open me-2"></i> <?= htmlspecialchars($category) ?></span>
                        </div>
                        <div class="perm-list">
                            <?php foreach ($perms as $perm): 
                                $checked = in_array($perm['code'], $user_perms) ? 'checked' : '';
                            ?>
                                <div class="perm-item" data-search="<?= strtolower($perm['description']) ?>">
                                    <span class="desc"><?= htmlspecialchars($perm['description']) ?></span>
                                    <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm['code']) ?>" <?= $checked ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn-save" id="saveBtn">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Save Permissions
                </button>
            </div>
        </form>
    </div>
</main>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
    // Theme
    document.documentElement.setAttribute('data-theme', localStorage.getItem('dispatch_theme') || 'dark');

    // ---- Helper: update selected count ----
    function updateCount() {
        const total = document.querySelectorAll('.perm-item input[type="checkbox"]:checked').length;
        document.getElementById('selectedCount').textContent = total;
    }
    document.querySelectorAll('.perm-item input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
    updateCount();

    // ---- Search & Filter ----
    function applyFilters() {
        const search = document.getElementById('searchInput').value.toLowerCase().trim();
        const category = document.getElementById('categoryFilter').value;

        document.querySelectorAll('.category-wrapper').forEach(wrapper => {
            const wrapCat = wrapper.dataset.category;
            const showWrapper = !category || wrapCat === category;
            wrapper.style.display = showWrapper ? '' : 'none';

            const items = wrapper.querySelectorAll('.perm-item');
            let visibleItemsCount = 0;

            items.forEach(item => {
                const text = item.dataset.search || '';
                const matchSearch = !search || text.includes(search);
                if (showWrapper && matchSearch) {
                    item.style.display = '';
                    visibleItemsCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Hide wrapper if no items match inside it
            if (visibleItemsCount === 0) {
                wrapper.style.display = 'none';
            }
        });
    }

    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('categoryFilter').addEventListener('change', applyFilters);
    applyFilters();

    // ---- Global Select / Clear ----
    document.getElementById('selectAllGlobal').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.perm-item:not([style*="display: none"]) input[type="checkbox"]').forEach(cb => cb.checked = true);
        updateCount();
    });
    document.getElementById('deselectAllGlobal').addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.perm-item:not([style*="display: none"]) input[type="checkbox"]').forEach(cb => cb.checked = false);
        updateCount();
    });

    // ---- Toast function ----
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark';
        const color = type === 'success' ? '#2ecc71' : '#e74c3c';
        const toast = document.createElement('div');
        toast.className = `toast-custom ${type}`;
        toast.innerHTML = `
            <i class="fa-regular ${icon}" style="color:${color};"></i>
            <span class="msg">${message}</span>
            <button class="close-toast">&times;</button>
        `;
        toast.querySelector('.close-toast').addEventListener('click', () => toast.remove());
        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4000);
    }

    // ---- AJAX form submission ----
    document.getElementById('permForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const checkboxes = document.querySelectorAll('.perm-item input[type="checkbox"]:checked');
        const selected = Array.from(checkboxes).map(cb => cb.value);
        const formData = new FormData();
        formData.append('action', 'save_permissions');
        selected.forEach(perm => formData.append('permissions[]', perm));

        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Saving...';

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'An error occurred.', 'error');
            }
        })
        .catch(err => {
            showToast('Server error. Please try again.', 'error');
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i> Save Permissions';
        });
    });
</script>
</body>
</html>