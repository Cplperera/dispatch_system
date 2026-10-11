<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] == 'get_letters') {
    $reg_id = intval($_GET['reg_id']);
    
    // Query: Get letters for this regiment AND Exclude letters already used
    $stmt = $conn->prepare("
        SELECT id, letter_reference, letter_title 
        FROM internal_letters 
        WHERE regiment_id = ? 
        AND id NOT IN (SELECT reply_internal_letter_id FROM task_replies WHERE reply_internal_letter_id IS NOT NULL)
        AND is_deleted = 0
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $reg_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $letters = [];
    while ($row = $result->fetch_assoc()) {
        $letters[] = $row;
    }
    echo json_encode($letters);
    exit;
}

if (!isset($_GET['task_id'])) {
    die("Error: Task ID is required.");
}

$task_id = intval($_GET['task_id']);

// Fetch Task Info
$task_stmt = $conn->prepare("SELECT letter_ref, title FROM task_letters WHERE id = ?");
$task_stmt->bind_param("i", $task_id);
$task_stmt->execute();
$task = $task_stmt->get_result()->fetch_assoc();

// Fetch Assigned Regiments for this task
$reg_stmt = $conn->prepare("
    SELECT r.id, r.regiment_name 
    FROM task_letter_agencies tla 
    JOIN regiments r ON tla.regiment_id = r.id 
    WHERE tla.task_letter_id = ?
");
$reg_stmt->bind_param("i", $task_id);
$reg_stmt->execute();
$regiments = $reg_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Task Reply | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; }
        
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; }

        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 35px; margin: 0 30px 30px 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header-info { background: var(--accent-soft); padding: 20px; border-radius: 15px; margin-bottom: 25px; border-left: 5px solid var(--accent); }
        
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
        .form-control, .form-select { 
            background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); 
            border-radius: 12px; padding: 12px 15px; font-size: 0.9rem; 
        }
        .form-select:disabled { opacity: 0.5; cursor: not-allowed; }

        .btn-submit { background: var(--accent); color: white; border: none; padding: 14px 30px; border-radius: 12px; font-weight: 700; transition: 0.3s; width: 100%; margin-top: 20px; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
        
        .section-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; }
        .info-label { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; font-weight: 700; }
        .info-value { color: var(--text-main); font-weight: 600; font-size: 1rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <a href="view_task.php?id=<?= $task_id ?>" class="nav-btn"><i class="fa-solid fa-arrow-left me-2"></i> Back to Task</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Actions</div>
        <a href="#" class="nav-btn active"><i class="fa-solid fa-reply me-2"></i> Submit Reply</a>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav">
        <h4 class="fw-bold mb-0 text-main">Task Management</h4>
    </header>

    <div class="form-container">
        <div class="section-title"><i class="fa-solid fa-paper-plane"></i> Submit Task Reply</div>

        <div class="header-info">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-label">Task Reference</div>
                    <div class="info-value"><?= htmlspecialchars($task['letter_ref']) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Subject</div>
                    <div class="info-value"><?= htmlspecialchars($task['title']) ?></div>
                </div>
            </div>
        </div>

        <form action="process_reply.php" method="POST">
            <!-- Hidden Fields Matching SQL Columns -->
            <input type="hidden" name="task_letter_id" value="<?= $task_id; ?>">
            <input type="hidden" name="replied_by" value="<?= $_SESSION['user_id'] ?? ''; ?>">

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">1. Your Regiment</label>
                    <select name="regiment_id" id="regiment_id" class="form-select" required onchange="filterLetters(this.value)">
                        <option value="">-- Select Assigned Regiment --</option>
                        <?php while($row = $regiments->fetch_assoc()): ?>
                            <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['regiment_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">2. Current Reply Status</label>
                    <select name="status" id="status" class="form-select" required onchange="handleStatusChange(this.value)">
                        <option value="Accepted">Accepted</option>
                        <option value="Data NIL">Data NIL</option>
          
                    </select>
                </div>

                <div class="text-muted col-12">
                    <label class="form-label">3. Link Reply Internal Letter</label>
                    <select name="reply_internal_letter_id" id="letter_dropdown" class="form-select" disabled>
                        <option value="">-- Select Regiment First --</option>
                    </select>
                    <div id="letter_status" class="mt-2" style="font-size: 0.75rem; font-weight: 600;"></div>
                </div>

                <div class="col-12">
                    <button type="submit" name="save_reply" class="btn-submit">
                        <i class="fa-solid fa-check-circle me-2"></i> Submit Response
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    function handleStatusChange(statusValue) {
        const letterDropdown = document.getElementById('letter_dropdown');
        if (statusValue === 'Data NIL') {
            letterDropdown.required = false;
        } else {
            letterDropdown.required = true;
        }
    }

    function filterLetters(regimentId) {
        const letterDropdown = document.getElementById('letter_dropdown');
        const statusMsg = document.getElementById('letter_status');
        const statusVal = document.getElementById('status').value;
        
        letterDropdown.innerHTML = '<option value="">-- Loading Letters... --</option>';
        letterDropdown.disabled = true;
        statusMsg.innerText = "";
        statusMsg.style.color = "var(--text-muted)";

        if (!regimentId) {
            letterDropdown.innerHTML = '<option value="">-- Select Regiment First --</option>';
            return;
        }

        fetch(`?task_id=<?= $task_id ?>&action=get_letters&reg_id=${regimentId}`)
            .then(response => response.json())
            .then(data => {
                letterDropdown.innerHTML = '<option value="">-- Select Internal Letter --</option>';
                
                if (data.length === 0) {
                    statusMsg.innerText = "⚠ No available internal letters found for this regiment.";
                    statusMsg.style.color = "#ff5e5e";
                    letterDropdown.innerHTML = '<option value="">-- No Letters Available --</option>';
                } else {
                    data.forEach(letter => {
                        const option = document.createElement('option');
                        option.value = letter.id;
                        option.textContent = `${letter.letter_reference} | ${letter.letter_title}`;
                        letterDropdown.appendChild(option);
                    });
                    letterDropdown.disabled = false;
                    statusMsg.innerText = `✓ ${data.length} available letters found.`;
                    statusMsg.style.color = "#2ecc71";
                }

                handleStatusChange(statusVal);
            })
            .catch(error => {
                console.error('Error:', error);
                statusMsg.innerText = "Error loading letters.";
                statusMsg.style.color = "#ff5e5e";
            });
    }
</script>

</body>
</html>