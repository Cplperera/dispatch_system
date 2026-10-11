<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

if (!hasPermission('ADMIN_ACCESS')) {
    die("Access Denied!");
}

/* =========================
   API MODE
========================= */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    $data = [];

    // DB STATUS
    $data['db'] = ($conn && !$conn->connect_error);

    // TABLE CHECK
    $tables = ['users','internal_letters','outward_letters','task_letters','task_assignments'];
    foreach ($tables as $t) {
        $res = $conn->query("SHOW TABLES LIKE '$t'");
        $data['tables'][$t] = ($res && $res->num_rows > 0);
    }

    // RECORD COUNTS
    foreach ($tables as $t) {
        $res = $conn->query("SELECT COUNT(*) as c FROM $t");
        $data['counts'][$t] = $res ? $res->fetch_assoc()['c'] : 0;
    }

    // DB LOAD
    $status = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
    $data['db_connections'] = $status->fetch_assoc()['Value'];

    // SESSION
    $data['session'] = isset($_SESSION['user_id']);

    // SMART ALERTS
    $alerts = [];

    if (!$data['db']) $alerts[] = "Database connection failed";
    foreach ($data['tables'] as $t => $ok) {
        if (!$ok) $alerts[] = "Missing table: $t";
    }
    if ($data['counts']['task_letters'] == 0) {
        $alerts[] = "No task letters found (system inactive?)";
    }

    $data['alerts'] = $alerts;

    echo json_encode($data);
    exit;
}

/* =========================
   HANDLE ACTIONS
========================= */

if (isset($_POST['clear_logs'])) {
    file_put_contents('../../logs/error.log', '');
}

/* =========================
   UI START
========================= */
?>

<!DOCTYPE html>
<html>
<head>
    <title>Advanced Diagnostics</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        body { background:#eef1f5; }
        .card { border-radius:12px; }
        .alert-box { font-weight:bold; }
    </style>
</head>

<body>

<div class="container py-4">

<h2>⚙️ Advanced Diagnostics Panel</h2>

<!-- ALERT BOX -->
<div id="alerts"></div>

<!-- SYSTEM STATUS -->
<div class="card p-3 mb-3">
    <h5>Live System Status</h5>
    <p>Database: <span id="db_status"></span></p>
    <p>Session: <span id="session_status"></span></p>
    <p>DB Connections: <span id="db_conn"></span></p>
</div>

<!-- TABLE STATS -->
<div class="card p-3 mb-3">
    <h5>Table Statistics</h5>
    <table class="table">
        <thead>
            <tr><th>Table</th><th>Status</th><th>Records</th></tr>
        </thead>
        <tbody id="table_data"></tbody>
    </table>
</div>

<!-- LOG VIEWER -->
<div class="card p-3 mb-3">
    <h5>Error Logs</h5>

    <form method="POST">
        <button class="btn btn-danger btn-sm" name="clear_logs">Clear Logs</button>
    </form>

    <pre id="logViewer" style="background:#000;color:#0f0;height:200px;overflow:auto;"></pre>
</div>

</div>

<script>
function loadDiagnostics() {
    $.get('?api=1', function(data) {

        // DB
        $('#db_status').html(data.db ? '✅ OK' : '❌ FAIL');
        $('#session_status').html(data.session ? '✅ Active' : '❌ Lost');
        $('#db_conn').text(data.db_connections);

        // TABLES
        let html = '';
        for (let t in data.tables) {
            html += `<tr>
                <td>${t}</td>
                <td>${data.tables[t] ? 'OK' : 'FAIL'}</td>
                <td>${data.counts[t]}</td>
            </tr>`;
        }
        $('#table_data').html(html);

        // ALERTS
        let alertHtml = '';
        if (data.alerts.length > 0) {
            data.alerts.forEach(a => {
                alertHtml += `<div class="alert alert-danger">${a}</div>`;
            });
        } else {
            alertHtml = `<div class="alert alert-success">System Stable</div>`;
        }
        $('#alerts').html(alertHtml);

    });
}

// LOAD LOG FILE
function loadLogs() {
    $.get('../../logs/error.log', function(data) {
        $('#logViewer').text(data);
    });
}

// AUTO REFRESH
setInterval(() => {
    loadDiagnostics();
    loadLogs();
}, 10000);

// INITIAL LOAD
loadDiagnostics();
loadLogs();
</script>

</body>
</html>