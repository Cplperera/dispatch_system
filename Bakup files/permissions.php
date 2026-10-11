<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_PERMISSIONS')) {
    echo "Access Denied!";
    exit;
}

$user_id = $_GET['id'] ?? 0;

// Fetch all permissions
$permissions = $conn->query("SELECT * FROM permission_codes");

// Fetch user's assigned permissions
$user_perms = [];
$result = $conn->query("SELECT permission_code FROM user_permissions WHERE user_id = $user_id AND is_allowed = 1");
while($row = $result->fetch_assoc()) {
    $user_perms[] = $row['permission_code'];
}
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
            --bg-body: #0B1437; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Roboto, sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); padding: 40px 20px; }

        .glass-card { 
            max-width: 900px; margin: 0 auto;
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 24px; 
            padding: 35px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .header-box { border-bottom: 1px solid var(--border); margin-bottom: 30px; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header-box h2 { font-weight: 800; font-size: 1.6rem; display: flex; align-items: center; gap: 15px; margin: 0; }
        .header-box i { color: var(--accent); }

        .permission-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
            gap: 15px; 
            margin-bottom: 30px;
        }

        .perm-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            padding: 15px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .perm-item:hover { background: var(--accent-soft); border-color: var(--accent); }

        input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .perm-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
        .perm-code { font-size: 0.65rem; color: var(--text-muted); display: block; text-transform: uppercase; margin-top: 2px; }

        .btn-save {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 700;
            transition: 0.3s;
            display: flex; align-items: center; gap: 8px;
        }
        
        .btn-save:hover { background: #5a37e0; transform: translateY(-2px); }

        .select-all-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .select-all-btn:hover { border-color: var(--accent); color: var(--accent); }
    </style>
</head>
<body>

<div class="glass-card">
    <div class="header-box">
        <div>
            <h2><i class="fa-solid fa-key"></i> Assign Permissions</h2>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Configuring access levels for User ID: #<?= htmlspecialchars($user_id) ?></p>
        </div>
        <button type="button" class="select-all-btn" onclick="toggleAll()">Toggle All</button>
    </div>

    <form method="post" action="save_permissions.php">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id); ?>">
        
        <div class="permission-grid">
            <?php while($perm = $permissions->fetch_assoc()): ?>
                <label class="perm-item">
                    <input type="checkbox" class="perm-check" name="permissions[]" value="<?= $perm['code']; ?>" 
                    <?= in_array($perm['code'], $user_perms) ? 'checked' : ''; ?>>
                    <div>
                        <span class="perm-label"><?= htmlspecialchars($perm['description']); ?></span>
                        <span class="perm-code"><?= htmlspecialchars($perm['code']); ?></span>
                    </div>
                </label>
            <?php endwhile; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 20px; border-top: 1px solid var(--border); padding-top: 25px;">
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-shield-check"></i> Save Permissions
            </button>
            <a href="users_list.php" style="text-decoration: none; color: var(--text-muted); font-size: 0.85rem; font-weight: 600;">Back to Users</a>
        </div>
    </form>
</div>

<script>
    function toggleAll() {
        const checkboxes = document.querySelectorAll('.perm-check');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
</script>

</body>
</html>