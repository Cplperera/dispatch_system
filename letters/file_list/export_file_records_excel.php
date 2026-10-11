<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Check if the user has permission to view records (required for exporting)
if (!hasPermission('VIEW_FILE_RECORDS')) {
    die('Access Denied');
}

// ==========================================
// 1. GET FILTER PARAMETERS FROM URL
// ==========================================
$search = isset($_GET['search_value']) ? trim($_GET['search_value']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_desk = isset($_GET['desk']) ? intval($_GET['desk']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_my_files = isset($_GET['my_files']) ? intval($_GET['my_files']) : 0;

// ==========================================
// 2. BUILD THE SQL QUERY
// ==========================================
$query = "
    SELECT f.file_reference, f.file_name, f.volume, f.rack_no, f.row_no, 
           d.desk_name, f.status, u.username AS created_by_name, 
           f.created_at, u2.username AS edited_by_name, f.edited_at
    FROM physical_file_records f
    LEFT JOIN users u ON f.created_by = u.id
    LEFT JOIN users u2 ON f.edited_by = u2.id
    LEFT JOIN desks d ON f.desk_id = d.id
    WHERE f.is_deleted = 0
";

$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (f.file_reference LIKE ? OR f.file_name LIKE ? OR f.volume LIKE ? OR f.rack_no LIKE ? OR f.row_no LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
if (!empty($filter_status)) { $query .= " AND f.status = ?"; $params[] = $filter_status; $types .= 's'; }
if (!empty($filter_desk)) { $query .= " AND f.desk_id = ?"; $params[] = $filter_desk; $types .= 'i'; }
if ($filter_my_files && isset($_SESSION['user_id'])) { $query .= " AND f.created_by = ?"; $params[] = $_SESSION['user_id']; $types .= 'i'; }
if (!empty($filter_date_from) && !empty($filter_date_to)) {
    $query .= " AND DATE(f.created_at) BETWEEN ? AND ?";
    $params[] = $filter_date_from; $params[] = $filter_date_to; $types .= 'ss';
} elseif (!empty($filter_date_from)) {
    $query .= " AND DATE(f.created_at) >= ?"; $params[] = $filter_date_from; $types .= 's';
} elseif (!empty($filter_date_to)) {
    $query .= " AND DATE(f.created_at) <= ?"; $params[] = $filter_date_to; $types .= 's';
}

$query .= " ORDER BY f.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ==========================================
// 3. SET HEADERS FOR EXCEL DOWNLOAD
// ==========================================
$filename = "file_records_" . date('Y-m-d_H-i') . ".xls";

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ==========================================
// 4. OUTPUT AS AN HTML TABLE FORMATTED AS EXCEL
// ==========================================
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
echo '<style>';
echo '  table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }';
echo '  th { background-color: #f2f2f2; color: #000000; font-weight: bold; border: 1px solid #cccccc; padding: 8px; text-align: left; }';
echo '  td { border: 1px solid #cccccc; padding: 8px; text-align: left; vertical-align: top; }';
echo '  .text-center { text-align: center; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th>File Reference</th>';
echo '<th>File Name</th>';
echo '<th>Volume</th>';
echo '<th>Rack No</th>';
echo '<th>Row No</th>';
echo '<th>Desk</th>';
echo '<th>Status</th>';
echo '<th>Created By</th>';
echo '<th>Created Date</th>';
echo '<th>Edited By</th>';
echo '<th>Edited Date</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $created_date = $row['created_at'] ? date('d M Y H:i', strtotime($row['created_at'])) : '-';
        $edited_date = $row['edited_at'] ? date('d M Y H:i', strtotime($row['edited_at'])) : '-';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['file_reference']) . '</td>';
        echo '<td>' . htmlspecialchars($row['file_name'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['volume']) . '</td>';
        echo '<td>' . htmlspecialchars($row['rack_no']) . '</td>';
        echo '<td>' . htmlspecialchars($row['row_no']) . '</td>';
        echo '<td>' . htmlspecialchars($row['desk_name'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($row['status']) . '</td>';
        echo '<td>' . htmlspecialchars($row['created_by_name'] ?? 'Unknown') . '</td>';
        echo '<td>' . $created_date . '</td>';
        echo '<td>' . htmlspecialchars($row['edited_by_name'] ?? '-') . '</td>';
        echo '<td>' . $edited_date . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="11" class="text-center">No records found</td></tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
exit;
?>