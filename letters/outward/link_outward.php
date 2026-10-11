<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('ADD_OUTWARD_LETTERS')) {
    die('Access Denied!');
}

$outward_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$outward_id) {
    header("Location: outward.php");
    exit;
}

// --- Add 'source' column if missing ---
$col_check = $conn->query("SHOW COLUMNS FROM outward_internal LIKE 'source'");
if ($col_check->num_rows === 0) {
    $conn->query("ALTER TABLE outward_internal ADD COLUMN source VARCHAR(255) DEFAULT 'direct'");
}

// Fetch Outward Letter
$outward_query = $conn->prepare("SELECT * FROM outward_letters WHERE id = ?");
if (!$outward_query) die("Prepare failed: " . $conn->error);
$outward_query->bind_param("i", $outward_id);
$outward_query->execute();
$outward = $outward_query->get_result()->fetch_assoc();
$outward_query->close();

if (!$outward) {
    header("Location: outward.php?error=notfound");
    exit;
}

$success = '';
$error   = '';
$debug_info = ''; // For debug output

// --- Helper function to link all associated internals for a given task ---
function linkTaskInternals($conn, $outward_id, $task_id, &$debug = '') {
    $count = 0;
    $details = [];
    $all_found = [];

    // Get task title for source
    $task_title_q = $conn->prepare("SELECT title FROM task_letters WHERE id = ?");
    if (!$task_title_q) {
        $debug .= "Error preparing task title query: " . $conn->error . "\n";
        return 0;
    }
    $task_title_q->bind_param("i", $task_id);
    $task_title_q->execute();
    $task_title = $task_title_q->get_result()->fetch_assoc()['title'] ?? 'Task #'.$task_id;
    $task_title_q->close();
    $source = "Task {$task_id} - " . substr($task_title, 0, 30);

    // 1. From task_replies
    $fetch_replies = $conn->prepare("SELECT reply_internal_letter_id FROM task_replies WHERE task_letter_id = ?");
    if ($fetch_replies) {
        $fetch_replies->bind_param("i", $task_id);
        $fetch_replies->execute();
        $res = $fetch_replies->get_result();
        while ($row = $res->fetch_assoc()) {
            $int_id = (int)$row['reply_internal_letter_id'];
            $all_found[] = $int_id;
            $chk = $conn->prepare("SELECT id FROM outward_internal WHERE outward_id = ? AND internal_id = ?");
            if (!$chk) { $debug .= "Prepare failed: " . $conn->error . "\n"; continue; }
            $chk->bind_param("ii", $outward_id, $int_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO outward_internal (outward_id, internal_id, source) VALUES (?, ?, ?)");
                if (!$ins) { $debug .= "Prepare failed: " . $conn->error . "\n"; continue; }
                $ins->bind_param("iis", $outward_id, $int_id, $source);
                if ($ins->execute()) {
                    $count++;
                    $details[] = "Reply ID $int_id linked";
                } else {
                    $debug .= "Insert failed for reply ID $int_id: " . $ins->error . "\n";
                }
                $ins->close();
            } else {
                $details[] = "Reply ID $int_id already linked";
            }
            $chk->close();
        }
        $fetch_replies->close();
    } else {
        $debug .= "task_replies table not found or query failed\n";
    }

    // 2. From task_internal_links
    $fetch_links = $conn->prepare("SELECT internal_letter_id FROM task_internal_links WHERE task_id = ?");
    if ($fetch_links) {
        $fetch_links->bind_param("i", $task_id);
        $fetch_links->execute();
        $res = $fetch_links->get_result();
        while ($row = $res->fetch_assoc()) {
            $int_id = (int)$row['internal_letter_id'];
            $all_found[] = $int_id;
            $chk = $conn->prepare("SELECT id FROM outward_internal WHERE outward_id = ? AND internal_id = ?");
            if (!$chk) { $debug .= "Prepare failed: " . $conn->error . "\n"; continue; }
            $chk->bind_param("ii", $outward_id, $int_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                $ins = $conn->prepare("INSERT INTO outward_internal (outward_id, internal_id, source) VALUES (?, ?, ?)");
                if (!$ins) { $debug .= "Prepare failed: " . $conn->error . "\n"; continue; }
                $ins->bind_param("iis", $outward_id, $int_id, $source);
                if ($ins->execute()) {
                    $count++;
                    $details[] = "Link ID $int_id linked";
                } else {
                    $debug .= "Insert failed for link ID $int_id: " . $ins->error . "\n";
                }
                $ins->close();
            } else {
                $details[] = "Link ID $int_id already linked";
            }
            $chk->close();
        }
        $fetch_links->close();
    } else {
        $debug .= "task_internal_links table not found or query failed\n";
    }

    // Build debug summary
    $all_found = array_unique($all_found);
    $debug .= "Task ID $task_id: Found " . count($all_found) . " associated internal letters: " . implode(', ', $all_found) . "\n";
    $debug .= "Newly linked: $count\n";
    $debug .= implode("\n", $details) . "\n";

    return $count;
}

// --- BULK LINK INTERNAL (checkboxes) ---
if (isset($_POST['link_internal_bulk']) && isset($_POST['internal_ids']) && is_array($_POST['internal_ids'])) {
    $internal_ids = array_filter(array_map('intval', $_POST['internal_ids']));
    $count = 0;
    foreach ($internal_ids as $int_id) {
        $check = $conn->prepare("SELECT id FROM outward_internal WHERE outward_id = ? AND internal_id = ?");
        if (!$check) die("Prepare failed: " . $conn->error);
        $check->bind_param("ii", $outward_id, $int_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO outward_internal (outward_id, internal_id, source) VALUES (?, ?, 'direct')");
            if (!$stmt) die("Prepare failed: " . $conn->error);
            $stmt->bind_param("ii", $outward_id, $int_id);
            if ($stmt->execute()) $count++;
            $stmt->close();
        }
        $check->close();
    }
    header("Location: link_outward.php?id=$outward_id&msg=bulk_int&count=$count");
    exit;
}

// --- BULK LINK TASK (checkboxes) - AUTO-LINK ALWAYS ON ---
if (isset($_POST['link_task_bulk']) && isset($_POST['task_ids']) && is_array($_POST['task_ids'])) {
    $task_ids = array_filter(array_map('intval', $_POST['task_ids']));
    $count_tasks = 0;
    $count_internals = 0;
    $debug_log = "=== Bulk Task Link Debug ===\n";

    foreach ($task_ids as $t_id) {
        // Link Task itself
        $check = $conn->prepare("SELECT id FROM outward_task WHERE outward_id = ? AND task_id = ?");
        if (!$check) die("Prepare failed: " . $conn->error);
        $check->bind_param("ii", $outward_id, $t_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO outward_task (outward_id, task_id) VALUES (?, ?)");
            if (!$stmt) die("Prepare failed: " . $conn->error);
            $stmt->bind_param("ii", $outward_id, $t_id);
            if ($stmt->execute()) $count_tasks++;
            $stmt->close();
        }
        $check->close();

        // Auto-link ALL associated internal letters (replies + links) - ALWAYS
        $added = linkTaskInternals($conn, $outward_id, $t_id, $debug_log);
        $count_internals += $added;
    }

    // Store debug in session for display
    if (isset($_POST['debug_mode']) && $_POST['debug_mode'] == '1') {
        $_SESSION['link_debug'] = $debug_log;
        header("Location: link_outward.php?id=$outward_id&msg=bulk_task&tasks=$count_tasks&internals=$count_internals&debug=1");
    } else {
        header("Location: link_outward.php?id=$outward_id&msg=bulk_task&tasks=$count_tasks&internals=$count_internals");
    }
    exit;
}

// --- SINGLE LINK TASK (Legacy) - AUTO-LINK ALWAYS ON ---
if (isset($_POST['link_task'])) {
    $task_id = (int)$_POST['task_id'];
    $debug_log = "=== Single Task Link Debug ===\n";

    $check = $conn->prepare("SELECT id FROM outward_task WHERE outward_id = ? AND task_id = ?");
    if (!$check) die("Prepare failed: " . $conn->error);
    $check->bind_param("ii", $outward_id, $task_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "Task letter is already linked.";
    } else {
        $stmt = $conn->prepare("INSERT INTO outward_task (outward_id, task_id) VALUES (?, ?)");
        if (!$stmt) die("Prepare failed: " . $conn->error);
        $stmt->bind_param("ii", $outward_id, $task_id);
        if ($stmt->execute()) {
            // Auto-link all associated internals
            $count = linkTaskInternals($conn, $outward_id, $task_id, $debug_log);
            if (isset($_POST['debug_mode_single']) && $_POST['debug_mode_single'] == '1') {
                $_SESSION['link_debug'] = $debug_log;
                header("Location: link_outward.php?id=$outward_id&msg=2&debug=1");
            } else {
                header("Location: link_outward.php?id=$outward_id&msg=2");
            }
            exit;
        }
    }
}

// --- SYNC MISSING INTERNALS (Maintenance) ---
if (isset($_POST['sync_missing'])) {
    $tasks_linked = $conn->prepare("SELECT task_id FROM outward_task WHERE outward_id = ?");
    if (!$tasks_linked) die("Prepare failed: " . $conn->error);
    $tasks_linked->bind_param("i", $outward_id);
    $tasks_linked->execute();
    $res = $tasks_linked->get_result();
    $total_added = 0;
    $total_tasks = 0;
    $debug_log = "=== Sync Missing Links Debug ===\n";

    while ($row = $res->fetch_assoc()) {
        $task_id = $row['task_id'];
        $added = linkTaskInternals($conn, $outward_id, $task_id, $debug_log);
        $total_added += $added;
        $total_tasks++;
    }
    $tasks_linked->close();

    if (isset($_POST['debug_mode_sync']) && $_POST['debug_mode_sync'] == '1') {
        $_SESSION['link_debug'] = $debug_log;
        $msg = "Sync complete: $total_added missing internal letter(s) linked across $total_tasks task(s).";
        if ($total_added == 0) {
            $msg = "All associated internal letters are already linked. No changes made.";
        }
        header("Location: link_outward.php?id=$outward_id&sync_msg=" . urlencode($msg) . "&debug=1");
    } else {
        $msg = "Sync complete: $total_added missing internal letter(s) linked across $total_tasks task(s).";
        if ($total_added == 0) {
            $msg = "All associated internal letters are already linked. No changes made.";
        }
        header("Location: link_outward.php?id=$outward_id&sync_msg=" . urlencode($msg));
    }
    exit;
}

// --- UNLINK HANDLERS ---
if (isset($_GET['unlink_internal'])) {
    $link_id = (int)$_GET['unlink_internal'];
    $conn->query("DELETE FROM outward_internal WHERE id = $link_id");
    header("Location: link_outward.php?id=$outward_id");
    exit;
}
if (isset($_GET['unlink_task'])) {
    $link_id = (int)$_GET['unlink_task'];
    if (isset($_GET['remove_inwards']) && $_GET['remove_inwards'] == '1') {
        $task_q = $conn->prepare("SELECT task_id FROM outward_task WHERE id = ?");
        if ($task_q) {
            $task_q->bind_param("i", $link_id);
            $task_q->execute();
            $task_res = $task_q->get_result();
            if ($task_row = $task_res->fetch_assoc()) {
                $task_id = $task_row['task_id'];
                $source_pattern = "Task {$task_id} -%";
                $del = $conn->prepare("DELETE FROM outward_internal WHERE outward_id = ? AND source LIKE ?");
                if ($del) {
                    $del->bind_param("is", $outward_id, $source_pattern);
                    $del->execute();
                    $del->close();
                }
            }
            $task_q->close();
        }
    }
    $conn->query("DELETE FROM outward_task WHERE id = $link_id");
    header("Location: link_outward.php?id=$outward_id");
    exit;
}
if (isset($_GET['remove_inward_source'])) {
    $link_id = (int)$_GET['remove_inward_source'];
    $conn->query("DELETE FROM outward_internal WHERE id = $link_id");
    header("Location: link_outward.php?id=$outward_id");
    exit;
}

// --- MESSAGES ---
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 1) $success = "✅ Internal letter linked successfully!";
    if ($_GET['msg'] == 2) $success = "✅ Task letter linked successfully with all associated internal letters!";
    if ($_GET['msg'] == 'bulk_int') {
        $count = (int)($_GET['count'] ?? 0);
        $success = "✅ $count Internal letter(s) linked successfully!";
    }
    if ($_GET['msg'] == 'bulk_task') {
        $tasks = (int)($_GET['tasks'] ?? 0);
        $internals = (int)($_GET['internals'] ?? 0);
        $success = "✅ $tasks Task(s) linked. $internals associated Internal letter(s) auto-linked!";
        if (isset($_GET['debug']) && $_GET['debug'] == '1' && isset($_SESSION['link_debug'])) {
            $debug_info = $_SESSION['link_debug'];
            unset($_SESSION['link_debug']);
        }
    }
}
if (isset($_GET['sync_msg'])) {
    $success = "🔄 " . htmlspecialchars($_GET['sync_msg']);
    if (isset($_GET['debug']) && $_GET['debug'] == '1' && isset($_SESSION['link_debug'])) {
        $debug_info = $_SESSION['link_debug'];
        unset($_SESSION['link_debug']);
    }
}

// --- FETCH DATA (for checkboxes & tables) ---
$internals_options = $conn->prepare("SELECT id, cdr_number, letter_title FROM internal_letters WHERE is_deleted = 0 ORDER BY id DESC");
if (!$internals_options) die("Prepare failed: " . $conn->error);
$internals_options->execute();
$internals_result = $internals_options->get_result();

$tasks_options = $conn->prepare("SELECT id, tsk_cdr_number, title FROM task_letters ORDER BY id DESC");
if (!$tasks_options) die("Prepare failed: " . $conn->error);
$tasks_options->execute();
$tasks_result = $tasks_options->get_result();

$linked_internals = $conn->prepare("SELECT oi.id as link_id, oi.internal_id, il.cdr_number, il.letter_title, oi.source FROM outward_internal oi JOIN internal_letters il ON oi.internal_id = il.id WHERE oi.outward_id = ? ORDER BY oi.id DESC");
if (!$linked_internals) die("Prepare failed: " . $conn->error);
$linked_internals->bind_param("i", $outward_id);
$linked_internals->execute();
$linked_internals_result = $linked_internals->get_result();

$linked_tasks = $conn->prepare("SELECT ot.id as link_id, ot.task_id, tl.tsk_cdr_number, tl.title FROM outward_task ot JOIN task_letters tl ON ot.task_id = tl.id WHERE ot.outward_id = ?");
if (!$linked_tasks) die("Prepare failed: " . $conn->error);
$linked_tasks->bind_param("i", $outward_id);
$linked_tasks->execute();
$linked_tasks_result = $linked_tasks->get_result();

// Already linked IDs for disabling checkboxes
$internal_ids_linked = [];
$temp = $conn->prepare("SELECT internal_id FROM outward_internal WHERE outward_id = ?");
if ($temp) {
    $temp->bind_param("i", $outward_id);
    $temp->execute();
    $res_temp = $temp->get_result();
    while ($row = $res_temp->fetch_assoc()) {
        $internal_ids_linked[] = $row['internal_id'];
    }
    $temp->close();
}
$task_ids_linked = [];
$temp2 = $conn->prepare("SELECT task_id FROM outward_task WHERE outward_id = ?");
if ($temp2) {
    $temp2->bind_param("i", $outward_id);
    $temp2->execute();
    $res_temp2 = $temp2->get_result();
    while ($row = $res_temp2->fetch_assoc()) {
        $task_ids_linked[] = $row['task_id'];
    }
    $temp2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Correspondence | LDMS</title>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
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
            --hover-bg: rgba(67, 24, 255, 0.03);
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
            --hover-bg: rgba(255, 255, 255, 0.03);
        }

        body { background: var(--bg-body); color: var(--text-main); font-family: "Segoe UI", system-ui, sans-serif; display: flex; min-height: 100vh; margin: 0; transition: 0.3s; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 25px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 1000; }
        .brand-box { padding-bottom: 20px; border-bottom: 1px solid var(--border); }
        .brand-name { font-weight: 800; font-size: 1.3rem; line-height: 1.2; letter-spacing: 1px; color: var(--text-main); }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; margin-bottom: 5px; transition: 0.3s; cursor: pointer; border: none; background: transparent; width: 100%; font-size: 0.95rem; font-weight: 700; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--text-main); }
        .nav-btn.active i { color: var(--accent); }
        .submenu { padding-left: 20px; display: none; flex-direction: column; gap: 5px; }
        .submenu.show { display: flex; }
        .sub-nav-btn { padding: 8px 15px; color: var(--text-muted); text-decoration: none; font-size: 0.85rem; border-radius: 8px; transition: 0.2s; font-weight: 600; }
        .sub-nav-btn:hover { color: var(--text-main); background: var(--accent-soft); }
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 30px; }
        @media (max-width: 992px) {
            .sidebar { width: 100%; height: auto; position: relative; border-right: none; border-bottom: 1px solid var(--border); }
            .main-content { margin-left: 0; padding: 20px; }
        }

        .outward-summary {
            background: var(--accent-soft);
            border-left: 4px solid var(--accent);
            padding: 18px 25px;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .outward-summary .info span { font-size: 0.8rem; color: var(--text-muted); }
        .outward-summary .info strong { font-size: 1.2rem; color: var(--text-main); }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            padding: 1.5rem;
            color: var(--text-main);
        }
        .card:hover { transform: translateY(-3px); }

        .nav-tabs .nav-link {
            color: var(--text-muted);
            border: none;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
            transition: all 0.3s;
            background: transparent;
        }
        .nav-tabs .nav-link.active {
            color: white;
            background: var(--accent);
            border: none;
        }
        .nav-tabs .nav-link:hover:not(.active) {
            background: var(--accent-soft);
            color: var(--text-main);
        }
        .tab-content { padding-top: 1.5rem; }

        .form-control, .form-select {
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 12px;
            padding: 10px 15px;
        }
        .form-control::placeholder {
            color: var(--placeholder-color);
            opacity: 0.8;
        }
        .form-control:focus, .form-select:focus {
            background: var(--bg-body);
            color: var(--text-main);
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem var(--accent-soft);
        }
        .btn-primary {
            background: var(--accent);
            border: none;
            border-radius: 12px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.2s;
            color: white;
        }
        .btn-primary:hover { background: var(--accent); opacity: 0.9; transform: scale(1.02); }
        .btn-outline-light { border-radius: 50px; padding: 6px 20px; color: var(--text-main); border-color: var(--border); }
        .btn-outline-light:hover { background: var(--accent-soft); color: var(--text-main); border-color: var(--accent); }
        .btn-sm { border-radius: 10px; }
        .btn-success { background: #28a745; border: none; border-radius: 12px; padding: 10px 25px; font-weight: 600; color: white; }
        .btn-success:hover { background: #218838; }

        .table {
            color: var(--text-main);
            font-size: 0.9rem;
        }
        .table thead th {
            border-bottom: 2px solid var(--border);
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody tr:hover { background: var(--hover-bg); }
        .badge-source {
            font-size: 0.7rem;
            padding: 0.25rem 0.7rem;
            border-radius: 20px;
            background: var(--accent-soft);
            color: var(--text-muted);
        }
        .badge-direct {
            background: rgba(40,167,69,0.2);
            color: #5cb85c;
        }
        .form-check-input {
            background-color: var(--bg-body);
            border-color: var(--border);
        }
        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        .tooltip-inner {
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            max-width: 350px;
        }
        .alert {
            border-radius: 16px;
            font-weight: 500;
        }
        .letter-grid {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px;
            background: var(--accent-soft);
        }
        .letter-grid .form-check {
            padding: 6px 10px;
            margin: 0;
            border-radius: 8px;
            transition: background 0.15s;
        }
        .letter-grid .form-check:hover {
            background: var(--hover-bg);
        }
        .letter-grid .form-check-input {
            margin-top: 0.3rem;
        }
        .letter-grid .form-check-label {
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main);
        }
        .letter-grid .badge-secondary {
            background: var(--border);
            color: var(--text-muted);
            font-weight: 400;
            font-size: 0.65rem;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
        }
        .search-box { margin-bottom: 10px; }
        .select-all-wrapper {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 10px;
        }
        .select-all-wrapper .form-check { margin: 0; }
        .selected-count { font-size: 0.85rem; color: var(--text-muted); }
        .sync-section {
            background: var(--accent-soft);
            border-radius: 12px;
            padding: 1.2rem;
            margin-top: 1.5rem;
            border: 1px dashed var(--border);
        }
        .sync-section .btn { min-width: 200px; }
        .debug-box {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
            font-family: monospace;
            font-size: 0.8rem;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            color: #28a745;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br>
            <span style="font-size:0.7rem; color:var(--text-muted);">EXECUTIVE TERMINAL</span>
        </div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin:20px 0 10px 10px; opacity:0.7;">Dispatching</div>
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
        <a href="outward.php" class="nav-btn"><i class="fa-solid fa-paper-plane me-2"></i> Outward Letters</a>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <div class="container-fluid">
        <!-- Header & Outward Summary -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h2 class="fw-bold mb-0 text-main"><i class="fa-solid fa-link me-2 text-primary"></i>Link Correspondence</h2>
            <a href="outward.php" class="btn btn-outline-light btn-sm px-4 rounded-pill">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Registry
            </a>
        </div>

        <div class="outward-summary">
            <div class="info">
                <span>Outward Reference</span><br>
                <strong><?= htmlspecialchars($outward['cdr_no'] ?? 'N/A') ?></strong>
                <span class="text-muted ms-3">| Subject: <?= htmlspecialchars($outward['title'] ?? '—') ?></span>
            </div>
            <a href="view_outward.php?id=<?= $outward_id ?>" class="btn btn-primary btn-sm rounded-pill">
                <i class="fa-solid fa-eye me-1"></i> View Full Details
            </a>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success border-0 shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-check me-2 fs-5"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center" role="alert">
                <i class="fa-solid fa-circle-xmark me-2 fs-5"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <?php if (!empty($debug_info)): ?>
            <div class="card mb-3" style="border-color: #28a745;">
                <div class="card-body">
                    <h6 class="fw-bold text-success"><i class="fa-solid fa-circle-info me-1"></i>Debug Information</h6>
                    <div class="debug-box"><?= htmlspecialchars($debug_info) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="linkTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="internal-tab" data-bs-toggle="tab" data-bs-target="#internal" type="button" role="tab">
                    <i class="fa-solid fa-envelope-open-text me-2"></i>Internal Letters
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="task-tab" data-bs-toggle="tab" data-bs-target="#task" type="button" role="tab">
                    <i class="fa-solid fa-list-check me-2"></i>Task Letters
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                    <i class="fa-solid fa-screwdriver-wrench me-2"></i>Maintenance
                </button>
            </li>
        </ul>

        <div class="tab-content" id="linkTabsContent">
            <!-- INTERNAL TAB -->
            <div class="tab-pane fade show active" id="internal" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <form method="post" id="internalLinkForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-12">
                                    <label class="form-label">Select Internal Letters to Link</label>
                                    <input type="text" id="internalSearch" class="form-control search-box" placeholder="Search by CDR or title...">
                                    <div class="select-all-wrapper">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="internalSelectAll">
                                            <label class="form-check-label" for="internalSelectAll">Select All</label>
                                        </div>
                                        <span class="selected-count" id="internalSelectedCount">0 selected</span>
                                    </div>
                                    <div class="letter-grid" id="internalGrid">
                                        <?php 
                                        $internals_result->data_seek(0);
                                        while ($row = $internals_result->fetch_assoc()): 
                                            $linked = in_array($row['id'], $internal_ids_linked);
                                            $disabled = $linked ? 'disabled' : '';
                                        ?>
                                            <div class="form-check" data-search="<?= strtolower($row['cdr_number'].' '.$row['letter_title']) ?>">
                                                <input class="form-check-input internal-check" type="checkbox" name="internal_ids[]" value="<?= $row['id'] ?>" id="int_<?= $row['id'] ?>" <?= $disabled ?>>
                                                <label class="form-check-label" for="int_<?= $row['id'] ?>">
                                                    <span class="fw-bold"><?= htmlspecialchars($row['cdr_number']) ?></span>
                                                    <span>— <?= htmlspecialchars($row['letter_title']) ?></span>
                                                    <?php if ($linked): ?>
                                                        <span class="badge-secondary">Already Linked</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="submit" name="link_internal_bulk" class="btn btn-primary w-100">
                                        <i class="fa-solid fa-link me-2"></i> Link Selected Internal Letters
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4" style="border-color:var(--border);">

                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-link me-2"></i>Currently Linked Internal Letters</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>CDR No</th><th>Title</th><th>Source</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if ($linked_internals_result->num_rows > 0): ?>
                                        <?php while ($row = $linked_internals_result->fetch_assoc()): ?>
                                            <tr>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['cdr_number']) ?></td>
                                                <td class="small"><?= htmlspecialchars($row['letter_title']) ?></td>
                                                <td>
                                                    <?php if ($row['source'] == 'direct'): ?>
                                                        <span class="badge-source badge-direct"><i class="fa-solid fa-circle-check me-1"></i>Direct</span>
                                                    <?php else: ?>
                                                        <span class="badge-source"><i class="fa-solid fa-code-branch me-1"></i><?= htmlspecialchars($row['source']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($row['source'] == 'direct'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#unlinkModal"
                                                                data-link-id="<?= $row['link_id'] ?>"
                                                                data-outward="<?= $outward_id ?>"
                                                                data-label="<?= htmlspecialchars($row['cdr_number']) ?>">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small" title="Indirect link via task. Cannot unlink directly.">
                                                            <i class="fa-solid fa-lock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            <i class="fa-solid fa-inbox me-2"></i>No internal letters linked yet.
                                        </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TASK TAB -->
            <div class="tab-pane fade" id="task" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <form method="post" id="taskLinkForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-12">
                                    <label class="form-label">Select Task Letters to Link</label>
                                    <input type="text" id="taskSearch" class="form-control search-box" placeholder="Search by Task No or title...">
                                    <div class="select-all-wrapper">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="taskSelectAll">
                                            <label class="form-check-label" for="taskSelectAll">Select All</label>
                                        </div>
                                        <span class="selected-count" id="taskSelectedCount">0 selected</span>
                                    </div>
                                    <div class="letter-grid" id="taskGrid">
                                        <?php 
                                        $tasks_result->data_seek(0);
                                        while ($row = $tasks_result->fetch_assoc()): 
                                            $linked = in_array($row['id'], $task_ids_linked);
                                            $disabled = $linked ? 'disabled' : '';
                                        ?>
                                            <div class="form-check" data-search="<?= strtolower($row['tsk_cdr_number'].' '.$row['title']) ?>">
                                                <input class="form-check-input task-check" type="checkbox" name="task_ids[]" value="<?= $row['id'] ?>" id="task_<?= $row['id'] ?>" <?= $disabled ?>>
                                                <label class="form-check-label" for="task_<?= $row['id'] ?>">
                                                    <span class="fw-bold"><?= htmlspecialchars($row['tsk_cdr_number']) ?></span>
                                                    <span>— <?= htmlspecialchars($row['title']) ?></span>
                                                    <?php if ($linked): ?>
                                                        <span class="badge-secondary">Already Linked</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="mt-2 text-muted small">
                                        <i class="fa-solid fa-info-circle me-1"></i> When you link a task, all associated internal letters (both replies and linked) will be auto‑linked.
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="debug_mode" value="1" id="debugMode">
                                        <label class="form-check-label" for="debugMode">
                                            <i class="fa-solid fa-circle-info"></i> Show debug information
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="submit" name="link_task_bulk" class="btn btn-primary w-100">
                                        <i class="fa-solid fa-link me-2"></i> Link Selected Task Letters (Auto‑link all associated internals)
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4" style="border-color:var(--border);">

                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-link me-2"></i>Currently Linked Task Letters</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead><tr><th>Task No</th><th>Title</th><th>Associated Internals</th><th class="text-end">Action</th></tr></thead>
                                <tbody>
                                    <?php if ($linked_tasks_result->num_rows > 0): ?>
                                        <?php while ($task = $linked_tasks_result->fetch_assoc()): 
                                            $source_pattern = "Task {$task['task_id']} -%";
                                            $inward_q = $conn->prepare("SELECT il.cdr_number, il.letter_title FROM outward_internal oi JOIN internal_letters il ON oi.internal_id = il.id WHERE oi.outward_id = ? AND oi.source LIKE ?");
                                            $inwards_list = [];
                                            if ($inward_q) {
                                                $inward_q->bind_param("is", $outward_id, $source_pattern);
                                                $inward_q->execute();
                                                $inward_res = $inward_q->get_result();
                                                while ($inv = $inward_res->fetch_assoc()) {
                                                    $inwards_list[] = $inv['cdr_number'] . ' - ' . $inv['letter_title'];
                                                }
                                                $inward_q->close();
                                            }
                                        ?>
                                            <tr>
                                                <td class="fw-bold text-info"><?= htmlspecialchars($task['tsk_cdr_number']) ?></td>
                                                <td class="small"><?= htmlspecialchars($task['title']) ?></td>
                                                <td>
                                                    <?php if (count($inwards_list) > 0): ?>
                                                        <span class="badge badge-secondary" data-bs-toggle="tooltip" title="<?= htmlspecialchars(implode("\n", $inwards_list)) ?>">
                                                            <i class="fa-solid fa-envelope me-1"></i> <?= count($inwards_list) ?> Internal(s)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">None linked</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#unlinkTaskModal"
                                                            data-link-id="<?= $task['link_id'] ?>"
                                                            data-task-id="<?= $task['task_id'] ?>"
                                                            data-outward="<?= $outward_id ?>"
                                                            data-label="<?= htmlspecialchars($task['tsk_cdr_number']) ?>"
                                                            data-has-inwards="<?= count($inwards_list) > 0 ? '1' : '0' ?>">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            <i class="fa-solid fa-inbox me-2"></i>No task letters linked yet.
                                        </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAINTENANCE TAB -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-wrench me-2 text-warning"></i>Maintenance – Sync Missing Links</h5>
                        <p class="text-muted">
                            This will scan all Task letters currently linked to this Outward, fetch all associated Internal Letters
                            (from <strong>replies</strong> and <strong>task_internal_links</strong>), and link any that are missing.
                        </p>
                        <div class="sync-section">
                            <form method="post">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="debug_mode_sync" value="1" id="debugModeSync">
                                    <label class="form-check-label" for="debugModeSync">
                                        <i class="fa-solid fa-circle-info me-1"></i> Show debug information
                                    </label>
                                </div>
                                <button type="submit" name="sync_missing" class="btn btn-success">
                                    <i class="fa-solid fa-arrows-rotate me-2"></i> Sync Missing Internal Letters
                                </button>
                                <small class="text-muted ms-3">This will not remove any existing links.</small>
                            </form>
                        </div>
                        <hr class="my-4" style="border-color:var(--border);">
                        <div class="alert alert-info border-0" role="alert">
                            <i class="fa-solid fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> If you have just added new replies or links to a Task, run this sync to update the Outward's linked letters.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modals -->
<div class="modal fade" id="unlinkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border);">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-trash-can me-2 text-danger"></i>Confirm Unlink</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to unlink <strong id="unlinkLabel"></strong>? This will remove the direct link.
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmUnlinkBtn" class="btn btn-danger">Yes, Unlink</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="unlinkTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border);">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-trash-can me-2 text-danger"></i>Confirm Unlink Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to unlink <strong id="unlinkTaskLabel"></strong>?</p>
                <div class="form-check mt-2" id="removeInwardsWrapper" style="display:none;">
                    <input class="form-check-input" type="checkbox" value="1" id="removeInwardsCheck" checked>
                    <label class="form-check-label" for="removeInwardsCheck">
                        <i class="fa-solid fa-circle-check me-1"></i> Also remove all internal letters linked via this task
                    </label>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmUnlinkTaskBtn" class="btn btn-danger">Yes, Unlink</a>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Theme Logic
    const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);

    // Sidebar toggle
    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        sub.classList.toggle('show');
        const icon = sub.previousElementSibling.querySelector('.fa-chevron-down');
        if (icon) icon.style.transform = sub.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    // Tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el) {
            return new bootstrap.Tooltip(el, {
                template: '<div class="tooltip" role="tooltip"><div class="tooltip-inner" style="background:var(--card-bg);color:var(--text-main);border:1px solid var(--border);"></div></div>'
            });
        });
    });

    // ---- INTERNAL TAB ----
    const internalCheckboxes = document.querySelectorAll('.internal-check');
    const internalSelectAll = document.getElementById('internalSelectAll');
    const internalSelectedCount = document.getElementById('internalSelectedCount');
    const internalSearch = document.getElementById('internalSearch');
    const internalGrid = document.getElementById('internalGrid');

    function updateInternalCount() {
        const checked = document.querySelectorAll('.internal-check:checked:not(:disabled)').length;
        internalSelectedCount.textContent = checked + ' selected';
        const total = document.querySelectorAll('.internal-check:not(:disabled)').length;
        internalSelectAll.checked = (checked === total && total > 0);
        internalSelectAll.indeterminate = (checked > 0 && checked < total);
    }

    internalSelectAll.addEventListener('change', function() {
        const isChecked = this.checked;
        document.querySelectorAll('.internal-check:not(:disabled)').forEach(cb => cb.checked = isChecked);
        updateInternalCount();
    });
    internalCheckboxes.forEach(cb => cb.addEventListener('change', updateInternalCount));
    internalSearch.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        const items = internalGrid.querySelectorAll('.form-check');
        items.forEach(item => {
            const searchData = item.dataset.search || '';
            const match = searchData.includes(term);
            item.style.display = match ? '' : 'none';
        });
    });
    updateInternalCount();

    // ---- TASK TAB ----
    const taskCheckboxes = document.querySelectorAll('.task-check');
    const taskSelectAll = document.getElementById('taskSelectAll');
    const taskSelectedCount = document.getElementById('taskSelectedCount');
    const taskSearch = document.getElementById('taskSearch');
    const taskGrid = document.getElementById('taskGrid');

    function updateTaskCount() {
        const checked = document.querySelectorAll('.task-check:checked:not(:disabled)').length;
        taskSelectedCount.textContent = checked + ' selected';
        const total = document.querySelectorAll('.task-check:not(:disabled)').length;
        taskSelectAll.checked = (checked === total && total > 0);
        taskSelectAll.indeterminate = (checked > 0 && checked < total);
    }

    taskSelectAll.addEventListener('change', function() {
        const isChecked = this.checked;
        document.querySelectorAll('.task-check:not(:disabled)').forEach(cb => cb.checked = isChecked);
        updateTaskCount();
    });
    taskCheckboxes.forEach(cb => cb.addEventListener('change', updateTaskCount));
    taskSearch.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        const items = taskGrid.querySelectorAll('.form-check');
        items.forEach(item => {
            const searchData = item.dataset.search || '';
            const match = searchData.includes(term);
            item.style.display = match ? '' : 'none';
        });
    });
    updateTaskCount();

    // ---- MODALS ----
    const unlinkModal = document.getElementById('unlinkModal');
    if (unlinkModal) {
        unlinkModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('unlinkLabel').innerText = button.getAttribute('data-label') || 'this letter';
            document.getElementById('confirmUnlinkBtn').href = `?id=${button.getAttribute('data-outward')}&unlink_internal=${button.getAttribute('data-link-id')}`;
        });
    }

    const unlinkTaskModal = document.getElementById('unlinkTaskModal');
    if (unlinkTaskModal) {
        unlinkTaskModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const linkId = button.getAttribute('data-link-id');
            const outwardId = button.getAttribute('data-outward');
            const label = button.getAttribute('data-label');
            const hasInwards = button.getAttribute('data-has-inwards') === '1';

            document.getElementById('unlinkTaskLabel').innerText = label || 'this task';
            const wrapper = document.getElementById('removeInwardsWrapper');
            wrapper.style.display = hasInwards ? 'block' : 'none';

            const check = document.getElementById('removeInwardsCheck');
            let url = `?id=${outwardId}&unlink_task=${linkId}`;
            if (check && check.checked) url += '&remove_inwards=1';
            document.getElementById('confirmUnlinkTaskBtn').href = url;

            if (check) {
                check.onchange = function() {
                    let newUrl = `?id=${outwardId}&unlink_task=${linkId}`;
                    if (this.checked) newUrl += '&remove_inwards=1';
                    document.getElementById('confirmUnlinkTaskBtn').href = newUrl;
                };
            }
        });
    }
</script>
</body>
</html>