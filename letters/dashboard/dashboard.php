<?php
/**
 * ==========================================================================
 * CENTRAL LOGISTICS & REGISTRY DASHBOARD (IDCMS)
 * ==========================================================================
 */

require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

if (!hasPermission('VIEW_HOMEPAGE')) {
    die('Access Denied');
}

if (!isset($_SESSION['user_id'])) {
    die('Session Invalid');
}

$userId = (int) $_SESSION['user_id'];

/* --------------------------------------------------------------------------
   PERMISSION CACHE — Sidebar item enable/disable state
   Each item is checked ONCE and cached. If permission is missing, the item
   is rendered with a "disabled" class (grayed out, not clickable).
   All codes below EXACTLY match `permission_codes.code` in the database.
   -------------------------------------------------------------------------- */
$perm = [
    // Home
    'home'              => hasPermission('ACCESS_HOMEPAGE') || hasPermission('VIEW_HOMEPAGE'),

    // Dashboards
    'dash_internal'     => hasPermission('ACCESS_INTERNAL_DASHBOARD'),
    'dash_external'     => hasPermission('ACCESS_EXTERNAL_DASHBOARD'),
    'dash_tasking'      => hasPermission('ACCESS_TASKING_DASHBOARD'),
    'dash_informer'     => hasPermission('ACCESS_INFORMER_DASHBOARD'),

    // Internal Letters
    'internal_add'      => hasPermission('ADD_INTERNAL_LETTER'),
    'internal_view'     => hasPermission('VIEW_INTERNAL_LETTERS'),

    // External Letters
    'external_add'      => hasPermission('ADD_OUTWARD_LETTERS'),
    'external_view'     => hasPermission('VIEW_OUTWARD_LETTERS'),

    // Tasking Letters
    'tasking_add'       => hasPermission('MANAGE_TASK_LETTERS'),
    'tasking_view'      => hasPermission('VIEW_TASK_LETTERS') || hasPermission('VIEW_TASK'),

    // My Inbox
    'inbox_internal'    => hasPermission('VIEW_INTERNAL_LETTERS'),
    'inbox_disposal'    => hasPermission('VIEW_FINAL_DISPOSAL') || hasPermission('FINAL_DISPOSAL'),

    // Linkage
    'linkage'           => hasPermission('VIEW_INTERNAL_LETTERS') || hasPermission('VIEW_OUTWARD_LETTERS'),

    // Informers
    'informer_add'      => hasPermission('ADD_INFORMER') || hasPermission('VIEW_INFORMERS'),

    // Record Room
    'file_add'          => hasPermission('ADD_FILE_RECORD'),
    'file_view'         => hasPermission('VIEW_FILE_RECORDS'),
    'file_issue'        => hasPermission('VIEW_FILE_RECORDS'),

    // Letter Category
    'cat_add'           => hasPermission('ADD_CATEGORY'),

    // System Permission
    'perm_add'          => hasPermission('ADD_PERMISSION_CODE'),
    'perm_view'         => hasPermission('MANAGE_PERMISSIONS'),

    // User Control
    'user_add'          => hasPermission('MANAGE_USERS'),
    'user_view'         => hasPermission('VIEW_USERS') || hasPermission('MANAGE_USERS'),

    // Desk Control
    'desk_add'          => hasPermission('ADD_DESK'),
];

// Parent groups are ENABLED if at least one child is enabled
$grp = [
    'dashboards' => $perm['dash_internal'] || $perm['dash_external'] || $perm['dash_tasking'] || $perm['dash_informer'],
    'internal'   => $perm['internal_add'] || $perm['internal_view'],
    'external'   => $perm['external_add'] || $perm['external_view'],
    'tasking'    => $perm['tasking_add'] || $perm['tasking_view'],
    'inbox'      => $perm['inbox_internal'] || $perm['inbox_disposal'],
    'informer'   => $perm['informer_add'],
    'recordroom' => $perm['file_add'] || $perm['file_view'] || $perm['file_issue'],
    'category'   => $perm['cat_add'],
    'permission' => $perm['perm_add'] || $perm['perm_view'],
    'users'      => $perm['user_add'] || $perm['user_view'],
    'desk'       => $perm['desk_add'],
];

/* --------------------------------------------------------------------------
   BACKEND GATEWAY: REAL-TIME SEARCH PREVIEW CENTER AJAX DATA PIPELINE
   -------------------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'search_preview') {
    header('Content-Type: application/json; charset=utf-8');
    $searchQuery = isset($_GET['query']) ? trim($_GET['query']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $types = isset($_GET['types']) && is_array($_GET['types']) ? $_GET['types'] : [];
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $deep = isset($_GET['deep']) && $_GET['deep'] === '1';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit < 1 || $limit > 100) $limit = 10;
    $offset = ($page - 1) * $limit;

    $searchInternal = empty($types) || in_array('internal', $types);
    $searchExternal = empty($types) || in_array('external', $types);
    $searchTask = empty($types) || in_array('task', $types);

    if ($searchQuery === '') {
        echo json_encode(['status' => 'empty', 'data' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
        exit;
    }

    $likeParam = "%" . $searchQuery . "%";
    $allResults = [];
    $totalCount = 0;

    if ($searchInternal) {
        $countSql = "SELECT COUNT(*) as total FROM internal_letters il
                     LEFT JOIN regiments r ON il.regiment_id = r.id
                     LEFT JOIN letter_categories lc ON il.category_id = lc.id
                     LEFT JOIN informers i ON il.Informer_id = i.id
                     WHERE il.is_deleted = 0
                       AND (il.cdr_number LIKE ? OR il.letter_reference LIKE ? OR il.letter_title LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam];
        $typesStr = "sss";
        if ($deep) {
            $countSql .= " OR il.about_letter LIKE ? OR il.comments LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "ss";
        }
        $countSql .= ")";
        if (!empty($status)) {
            $countSql .= " AND il.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $stmt = $conn->prepare($countSql);
        if ($stmt) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $totalCount += (int)$row['total'];
            }
            $stmt->close();
        }

        $sql = "SELECT il.id, il.cdr_number, il.letter_reference, il.letter_title AS subject, il.status, il.created_at,
                       r.regiment_name, lc.category AS category_name, il.security_classification, il.priority_status,
                       i.informer_code
                FROM internal_letters il
                LEFT JOIN regiments r ON il.regiment_id = r.id
                LEFT JOIN letter_categories lc ON il.category_id = lc.id
                LEFT JOIN informers i ON il.Informer_id = i.id
                WHERE il.is_deleted = 0
                  AND (il.cdr_number LIKE ? OR il.letter_reference LIKE ? OR il.letter_title LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam];
        $typesStr = "sss";
        if ($deep) {
            $sql .= " OR il.about_letter LIKE ? OR il.comments LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "ss";
        }
        $sql .= ")";
        if (!empty($status)) {
            $sql .= " AND il.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $sql .= " ORDER BY il.id DESC";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $allResults[] = [
                    'type'    => 'internal',
                    'type_label' => 'Internal Letter',
                    'id'      => $row['id'],
                    'ref_no'  => $row['cdr_number'] ?? 'N/A',
                    'subject' => $row['subject'],
                    'status'  => $row['status'] ?? 'Pending',
                    'date'    => date('d M Y', strtotime($row['created_at'])),
                    'badge'   => '#cd0505',
                    'details' => [
                        'Letter Ref'  => $row['letter_reference'] ?? 'N/A',
                        'Regiment'    => $row['regiment_name'] ?? 'N/A',
                        'Category'    => $row['category_name'] ?? 'N/A',
                        'Security'    => $row['security_classification'] ?? 'N/A',
                        'Priority'    => $row['priority_status'] ?? 'Normal',
                        'Informer'    => $row['informer_code'] ?? 'None',
                    ],
                    'link' => '../../letters/inward/internal_letter_details.php?id=' . $row['id']
                ];
            }
            $stmt->close();
        }
    }

    if ($searchExternal) {
        $countSql = "SELECT COUNT(*) as total FROM outward_letters ol
                     LEFT JOIN external_branches b ON ol.receiver = b.id
                     WHERE (ol.cdr_no LIKE ? OR ol.letter_ref LIKE ? OR ol.title LIKE ? OR b.branch_name LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam, $likeParam];
        $typesStr = "ssss";
        if ($deep) {
            $countSql .= " OR ol.description LIKE ? OR ol.comment LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "ss";
        }
        $countSql .= ")";
        if (!empty($status)) {
            $countSql .= " AND ol.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $stmt = $conn->prepare($countSql);
        if ($stmt) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $totalCount += (int)$row['total'];
            }
            $stmt->close();
        }

        $sql = "SELECT ol.id, ol.cdr_no, ol.letter_ref, ol.title AS subject, ol.status, ol.created_at, ol.description,
                       COALESCE(b.branch_name, ol.receiver, 'N/A') AS receiver_name
                FROM outward_letters ol
                LEFT JOIN external_branches b ON ol.receiver = b.id
                WHERE (ol.cdr_no LIKE ? OR ol.letter_ref LIKE ? OR ol.title LIKE ? OR b.branch_name LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam, $likeParam];
        $typesStr = "ssss";
        if ($deep) {
            $sql .= " OR ol.description LIKE ? OR ol.comment LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "ss";
        }
        $sql .= ")";
        if (!empty($status)) {
            $sql .= " AND ol.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $sql .= " ORDER BY ol.id DESC";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $allResults[] = [
                    'type'       => 'external',
                    'type_label' => 'External Letter',
                    'id'         => $row['id'],
                    'ref_no'     => $row['cdr_no'] ?? 'N/A',
                    'subject'    => $row['subject'],
                    'status'     => $row['status'] ?? 'Sent',
                    'date'       => date('d M Y', strtotime($row['created_at'])),
                    'badge'      => '#dbff0c',
                    'details'    => [
                        'Letter Ref'  => $row['letter_ref'] ?? 'N/A',
                        'Receiver'    => $row['receiver_name'] ?? 'N/A',
                        'Description' => !empty($row['description']) ? substr($row['description'], 0, 60) . '...' : 'N/A',
                    ],
                    'link' => '../../letters/outward/view_outward.php?id=' . $row['id']
                ];
            }
            $stmt->close();
        }
    }

    if ($searchTask) {
        $countSql = "SELECT COUNT(*) as total FROM task_letters tl
                     LEFT JOIN users u ON tl.assigned_by = u.id
                     WHERE (tl.tsk_cdr_number LIKE ? OR tl.letter_ref LIKE ? OR tl.title LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam];
        $typesStr = "sss";
        if ($deep) {
            $countSql .= " OR tl.description LIKE ? OR tl.about_letter LIKE ? OR tl.comments LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "sss";
        }
        $countSql .= ")";
        if (!empty($status)) {
            $countSql .= " AND tl.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $stmt = $conn->prepare($countSql);
        if ($stmt) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $totalCount += (int)$row['total'];
            }
            $stmt->close();
        }

        $sql = "SELECT tl.id, tl.tsk_cdr_number AS ref_no, tl.letter_ref, tl.title AS subject, tl.status, tl.created_at,
                       tl.priority_status, u.name_with_initial AS assigned_by_name, tl.request_date
                FROM task_letters tl
                LEFT JOIN users u ON tl.assigned_by = u.id
                WHERE (tl.tsk_cdr_number LIKE ? OR tl.letter_ref LIKE ? OR tl.title LIKE ?";
        $params = [$likeParam, $likeParam, $likeParam];
        $typesStr = "sss";
        if ($deep) {
            $sql .= " OR tl.description LIKE ? OR tl.about_letter LIKE ? OR tl.comments LIKE ?";
            $params[] = $likeParam;
            $params[] = $likeParam;
            $params[] = $likeParam;
            $typesStr .= "sss";
        }
        $sql .= ")";
        if (!empty($status)) {
            $sql .= " AND tl.status = ?";
            $params[] = $status;
            $typesStr .= "s";
        }
        $sql .= " ORDER BY tl.id DESC";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($typesStr, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $allResults[] = [
                    'type'    => 'task',
                    'type_label' => 'Tasked Letter',
                    'id'      => $row['id'],
                    'ref_no'  => $row['ref_no'] ?? 'N/A',
                    'subject' => $row['subject'],
                    'status'  => $row['status'] ?? 'Pending',
                    'date'    => date('d M Y', strtotime($row['created_at'])),
                    'badge'   => '#ab00ee',
                    'details' => [
                        'Letter Ref'    => $row['letter_ref'] ?? 'N/A',
                        'Priority'      => $row['priority_status'] ?? 'Medium',
                        'Assigned By'   => $row['assigned_by_name'] ?? 'N/A',
                        'Request Date'  => $row['request_date'] ? date('d M Y', strtotime($row['request_date'])) : 'N/A',
                    ],
                    'link' => '../../letters/tasking/view_task.php?id=' . $row['id']
                ];
            }
            $stmt->close();
        }
    }

    usort($allResults, function($a, $b) {
        return $b['id'] - $a['id'];
    });

    $paginated = array_slice($allResults, $offset, $limit);
    $totalPages = ($totalCount > 0) ? ceil($totalCount / $limit) : 1;

    echo json_encode([
        'status' => 'success',
        'data' => $paginated,
        'total' => $totalCount,
        'page' => $page,
        'pages' => $totalPages
    ]);
    exit;
}

/* --------------------------------------------------------------------------
   BACKEND CONTROL: GET CURRENT OPERATOR SYSTEM PROFILE DETAILS
   -------------------------------------------------------------------------- */
$userName = 'System Admin';
$userRole = 'Officer | Operations';
$userInitials = 'SA';

if ($stmt = $conn->prepare("SELECT name_with_initial, rank, section FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $userName = $userData['name_with_initial'] ?? $userName;
        $rank     = $userData['rank'] ?? 'Officer';
        $section  = $userData['section'] ?? 'Operations';
        $userRole = $rank . " | " . $section;

        $nameParts = explode(' ', trim($userName));
        $userInitials = (count($nameParts) > 1)
            ? strtoupper($nameParts[0][0] . $nameParts[1][0])
            : strtoupper($nameParts[0][0]);
    }
    $stmt->close();
}

/* --------------------------------------------------------------------------
   BACKEND CONTROL: LIVE REGISTRY METRICS TELEMETRY
   -------------------------------------------------------------------------- */
function getCount($conn, $query) {
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

$todayInward    = getCount($conn, "SELECT COUNT(*) as total FROM internal_letters WHERE DATE(created_at) = CURDATE() AND is_deleted = 0");
$pendingCount   = getCount($conn, "SELECT COUNT(*) as total FROM internal_letters WHERE status = 'Pending' AND is_deleted = 0");

$internalCount  = getCount($conn, "SELECT COUNT(*) as total FROM internal_letters WHERE is_deleted = 0");
$externalCount  = getCount($conn, "SELECT COUNT(*) as total FROM outward_letters");
$taskedCount    = getCount($conn, "SELECT COUNT(*) as total FROM task_letters");

$allLettersCount = $internalCount + $externalCount + $taskedCount;

/* --------------------------------------------------------------------------
    INTERNAL LETTERS FORWARDING: FETCH NOT SEEN / INBOX DETAILS FOR USER
    -------------------------------------------------------------------------- */
$inboxInternalList = [];
$notSeenCount = 0;

$forwardingQuery = "SELECT f.id, f.letter_id, f.forwarded_at, f.comments AS forward_note, il.cdr_number, il.letter_title 
                    FROM internal_letters_forwarding f 
                    JOIN internal_letters il ON f.letter_id = il.id 
                    WHERE f.forwarded_to = ? AND f.seen = 0 
                    ORDER BY f.id DESC LIMIT 5";
if ($stmtF = $conn->prepare($forwardingQuery)) {
    $stmtF->bind_param("i", $userId);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($rowF = $resF->fetch_assoc()) {
        $inboxInternalList[] = $rowF;
    }
    $stmtF->close();
}

$countQuery = "SELECT COUNT(*) as total FROM internal_letters_forwarding WHERE forwarded_to = ? AND seen = 0";
if ($stmtC = $conn->prepare($countQuery)) {
    $stmtC->bind_param("i", $userId);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    if ($rowC = $resC->fetch_assoc()) {
        $notSeenCount = (int)$rowC['total'];
    }
    $stmtC->close();
}

/* --------------------------------------------------------------------------
   FINAL DISPOSAL INBOX: FETCH PENDING ITEMS FOR USER'S DESK
   -------------------------------------------------------------------------- */
$finalDisposalList = [];
$finalDisposalCount = 0;
$userDeskId = 0;

if ($stmtD = $conn->prepare("SELECT desk_id FROM users WHERE id = ? LIMIT 1")) {
    $stmtD->bind_param("i", $userId);
    $stmtD->execute();
    $resD = $stmtD->get_result();
    if ($rowD = $resD->fetch_assoc()) {
        $userDeskId = (int)($rowD['desk_id'] ?? 0);
    }
    $stmtD->close();
}

if ($userDeskId > 0) {
    $fdQuery = "SELECT d.id, d.letter_id, d.forwarded_at, d.remarks, d.status,
                       il.cdr_number, il.letter_title, il.priority_status
                FROM internal_letters_final_disposal d
                JOIN internal_letters il ON d.letter_id = il.id
                WHERE d.forwarded_to = ? 
                  AND d.status <> 'Completed' 
                  AND il.is_deleted = 0
                ORDER BY d.id DESC LIMIT 5";
    if ($stmtFD = $conn->prepare($fdQuery)) {
        $stmtFD->bind_param("i", $userDeskId);
        $stmtFD->execute();
        $resFD = $stmtFD->get_result();
        while ($rowFD = $resFD->fetch_assoc()) {
            $finalDisposalList[] = $rowFD;
        }
        $stmtFD->close();
    }

    $fdCountQuery = "SELECT COUNT(*) as total 
                     FROM internal_letters_final_disposal d
                     JOIN internal_letters il ON d.letter_id = il.id
                     WHERE d.forwarded_to = ? 
                       AND d.status <> 'Completed' 
                       AND il.is_deleted = 0";
    if ($stmtFC = $conn->prepare($fdCountQuery)) {
        $stmtFC->bind_param("i", $userDeskId);
        $stmtFC->execute();
        $resFC = $stmtFC->get_result();
        if ($rowFC = $resFC->fetch_assoc()) {
            $finalDisposalCount = (int)$rowFC['total'];
        }
        $stmtFC->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | IDCMS</title>
    
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
            --sidebar-width: 290px;
            --transition-speed: 0.3s;
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            transition: background var(--transition-speed), border var(--transition-speed);
        }

        .brand-box { margin-bottom: 35px; padding: 0 10px; }
        .brand-name { font-size: 1.2rem; font-weight: 800; line-height: 1.3; letter-spacing: -0.5px; }
        .brand-name i { color: var(--accent); font-size: 1.6rem; margin-right: 4px; vertical-align: middle; }

        nav { display: flex; flex-direction: column; gap: 6px; }

        .nav-group-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 0 8px 10px;
        }

        .nav-btn, .sub-nav-btn {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: transparent;
            border: none;
            width: 100%;
            text-align: left;
        }

        .nav-btn i:first-child { font-size: 1.1rem; margin-right: 12px; width: 20px; text-align: center; }
        .nav-btn .chevron { margin-left: auto; font-size: 0.8rem; transition: transform 0.2s ease; }

        .nav-btn:hover, .sub-nav-btn:hover { color: var(--text-main); background: var(--accent-soft); }

        /* ═══════════════ DISABLED STATE — permission denied ═══════════════ */
        .nav-btn.disabled,
        .sub-nav-btn.disabled {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
            filter: grayscale(0.6);
            position: relative;
        }
        .nav-btn.disabled:hover,
        .sub-nav-btn.disabled:hover {
            background: transparent;
            color: var(--text-muted);
        }
        .nav-btn.disabled::after {
            content: '\f023'; /* lock icon */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            color: var(--text-muted);
            opacity: 0.6;
        }
        /* For parent items with chevron, place lock before chevron */
        .nav-btn.disabled .chevron { margin-left: 6px; }

        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.25s ease-out;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding-left: 20px;
        }

        .submenu.show { max-height: 500px; margin-top: 4px; margin-bottom: 4px; }

        .sub-nav-btn { font-size: 0.85rem; padding: 10px 14px; font-weight: 600; }

        .main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            min-height: 100vh;
        }

        .top-nav {
            display: flex;
            flex-direction: column;
            gap: 20px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 20px 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            transition: background var(--transition-speed), border var(--transition-speed);
        }

        .top-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }

        .datetime-box { 
            display: flex; align-items: center; gap: 15px; 
            background: var(--bg-body); padding: 8px 16px; 
            border-radius: 12px; border: 1px solid var(--border); 
        }

        #digital-clock { 
            font-weight: 800; color: var(--accent); font-size: 1rem; 
            border-right: 1px solid var(--border); padding-right: 15px; 
        }

        #digital-date { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); }

        .user-pill {
            display: flex; align-items: center; gap: 12px;
            background: var(--bg-body); padding: 6px 14px 6px 6px;
            border-radius: 12px; border: 1px solid var(--border);
        }

        .search-container { position: relative; width: 100%; }
        .search-container i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1rem; }

        .main-search {
            width: 100%; background: var(--bg-body); border: 1px solid var(--border);
            padding: 14px 14px 14px 48px; border-radius: 14px;
            color: var(--text-main); font-size: 0.9rem; font-weight: 600;
            outline: none; transition: all 0.2s;
        }

        .main-search:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }

        .filter-controls {
            display: flex; flex-wrap: wrap; align-items: center;
            gap: 15px 25px; padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .filter-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .filter-group label { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        .filter-group .form-check { margin: 0; padding-left: 0; display: flex; align-items: center; gap: 4px; }
        .filter-group .form-check-input { width: 14px; height: 14px; margin: 0 2px 0 0; accent-color: var(--accent); }
        .filter-group .form-check-label { font-size: 0.8rem; font-weight: 600; color: var(--text-main); }

        .filter-select {
            background: var(--bg-body); border: 1px solid var(--border);
            border-radius: 8px; padding: 6px 12px;
            color: var(--text-main); font-size: 0.8rem; font-weight: 600; outline: none;
        }

        .filter-select:focus { border-color: var(--accent); }

        .btn-reset-filters {
            background: transparent; border: 1px solid var(--border);
            border-radius: 8px; padding: 6px 14px;
            font-size: 0.75rem; font-weight: 700; color: var(--text-muted);
            cursor: pointer; transition: all 0.2s;
        }

        .btn-reset-filters:hover { background: var(--accent-soft); color: var(--text-main); }

        .preview-flow { display: flex; flex-direction: column; gap: 25px; }

        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }

        .metric-box {
            background: var(--card-bg); padding: 20px 18px;
            border-radius: 20px; border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            transition: transform var(--transition-speed), background var(--transition-speed);
        }

        .metric-box:hover { transform: translateY(-2px); }

        .metric-title {
            font-size: 0.75rem; color: var(--text-muted); font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
            display: block; margin-bottom: 8px;
        }

        .metric-value { font-size: 1.8rem; font-weight: 800; margin: 0; line-height: 1; }

        .preview-wrapper {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: 24px; padding: 30px; min-height: 350px;
            transition: background var(--transition-speed), border var(--transition-speed);
        }

        .preview-heading-area {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px; margin-bottom: 20px;
        }

        .preview-grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }

        .result-item-link { display: block; text-decoration: none; color: inherit; }
        .result-item-link:hover .result-item-card { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }

        .result-item-card {
            background: var(--bg-body); border: 1px solid var(--border);
            border-radius: 16px; padding: 18px;
            display: flex; flex-direction: column; gap: 6px;
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;
        }

        .result-type-ribbon { position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .result-header { display: flex; justify-content: space-between; align-items: center; }

        .result-channel {
            font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.5px; padding: 4px 8px; border-radius: 6px;
            background: rgba(255, 255, 255, 0.05);
        }

        .result-ref { font-weight: 700; font-size: 0.9rem; color: var(--text-muted); margin: 0; }
        .result-subject { font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0; line-height: 1.4; }
        .result-details { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 4px; }

        .result-detail-badge {
            font-size: 0.7rem; background: var(--card-bg);
            padding: 2px 10px; border-radius: 20px;
            border: 1px solid var(--border); color: var(--text-muted);
            font-weight: 600; white-space: nowrap;
        }

        .result-detail-badge strong { color: var(--text-main); font-weight: 700; }

        .result-footer {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 0.75rem; color: var(--text-muted);
            margin-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 8px;
        }

        .pagination-controls {
            display: flex; justify-content: center; align-items: center;
            gap: 15px; padding: 15px 0 5px 0;
            border-top: 1px solid var(--border); margin-top: 15px;
        }

        .pagination-btn {
            background: var(--bg-body); border: 1px solid var(--border);
            color: var(--text-main); padding: 6px 18px;
            border-radius: 8px; font-weight: 700; font-size: 0.8rem;
            cursor: pointer; transition: all 0.2s;
        }

        .pagination-btn:hover:not(:disabled) { background: var(--accent-soft); border-color: var(--accent); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .pagination-info { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

        .preview-placeholder-empty {
            text-align: center; padding: 60px 20px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }

        /* ═══════════════ NOTIFICATION DROPDOWNS ═══════════════ */
        .notif-btn {
            cursor: pointer; position: relative; color: var(--text-muted);
            font-size: 1.2rem; padding: 5px 10px;
            background: var(--bg-body); border: 1px solid var(--border);
            border-radius: 12px; display: flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .notif-btn:hover { border-color: var(--accent); color: var(--accent); }
        .notif-btn.disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; filter: grayscale(0.6); }
        .notif-label { font-size: 0.75rem; font-weight: 800; color: var(--text-main); }
        .notif-badge { position: absolute; top: -6px; right: -6px; font-size: 0.6rem; padding: 3px 6px; }
        .notif-menu {
            width: 340px; max-height: 420px; overflow-y: auto;
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: 16px; padding: 10px; margin-top: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        .notif-menu .dropdown-header {
            font-size: 0.75rem; font-weight: 800; color: var(--text-muted);
            text-transform: uppercase; padding: 8px 12px;
        }
        .notif-menu .dropdown-divider { border-color: var(--border); margin: 4px 0; }
        .notif-item { border-radius: 10px; padding: 10px 12px; white-space: normal; display: block; text-decoration: none; transition: background 0.2s; }
        .notif-item:hover { background: var(--accent-soft); }
        .notif-ref { font-size: 0.75rem; font-weight: 800; color: var(--accent); }
        .notif-title { font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-top: 2px; }
        .notif-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; }
        .notif-viewall { font-size: 0.8rem; color: var(--accent); padding: 8px; border-radius: 8px; text-align: center; font-weight: 800; display: block; text-decoration: none; }
        .notif-empty { font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 15px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> 
            IDCMS <br>
            <span style="font-size: 0.7rem; color: var(--text-muted);">  </span>
        </div>
    </div>

    <nav>
        <!-- ══════════ HOME PAGE ══════════ -->
        <a href="dashboard.php" class="nav-btn <?= $perm['home'] ? '' : 'disabled' ?>">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>
        
        <!-- ══════════ DASHBOARD OPERATIONS ══════════ -->
        <div class="nav-group-label">Dashboard Operations</div>
        <div>
            <div class="nav-btn <?= $grp['dashboards'] ? '' : 'disabled' ?>" <?= $grp['dashboards'] ? 'onclick="toggleSubmenu(\'dashboardSub\')"' : '' ?>>
                <i class="fa-solid fa-desktop"></i> Preview Dashboards
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="dashboardSub" class="submenu">
                <a href="internal_dashboard.php" class="sub-nav-btn <?= $perm['dash_internal'] ? '' : 'disabled' ?>">Internal Dashboard</a>
                <a href="external_dashboard.php" class="sub-nav-btn <?= $perm['dash_external'] ? '' : 'disabled' ?>">External Dashboard</a>
                <a href="task_dashboard.php" class="sub-nav-btn <?= $perm['dash_tasking'] ? '' : 'disabled' ?>">Tasking Dashboard</a>
                <a href="Informer_dashboard.php" class="sub-nav-btn <?= $perm['dash_informer'] ? '' : 'disabled' ?>">Informer Dashboard</a>
            </div>
        </div>

        <!-- ══════════ DISPATCHING OPERATIONS ══════════ -->
        <div class="nav-group-label">Dispatching Operations</div>

        <!-- Internal Letters -->
        <div>
            <div class="nav-btn <?= $grp['internal'] ? '' : 'disabled' ?>" <?= $grp['internal'] ? 'onclick="toggleSubmenu(\'internalSub\')"' : '' ?>>
                <i class="fa-solid fa-envelope-open-text"></i> Inward Letters
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="internalSub" class="submenu">
                <a href="../../letters/inward/add_internal_letter.php" class="sub-nav-btn <?= $perm['internal_add'] ? '' : 'disabled' ?>">Add Inward</a>
                <a href="../../letters/inward/internal_letters.php" class="sub-nav-btn <?= $perm['internal_view'] ? '' : 'disabled' ?>">View Inward</a>
            </div>
        </div>

        <!-- External Letters -->
        <div>
            <div class="nav-btn <?= $grp['external'] ? '' : 'disabled' ?>" <?= $grp['external'] ? 'onclick="toggleSubmenu(\'extranalSub\')"' : '' ?>>
                <i class="fa-solid fa-paper-plane"></i> Outward Letters
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="extranalSub" class="submenu">
                <a href="../../letters/outward/add_outward.php" class="sub-nav-btn <?= $perm['external_add'] ? '' : 'disabled' ?>">Add External</a>
                <a href="../../letters/outward/outward_list.php" class="sub-nav-btn <?= $perm['external_view'] ? '' : 'disabled' ?>">View External</a>
            </div>
        </div>

        <!-- Tasking Letters -->
        <div>
            <div class="nav-btn <?= $grp['tasking'] ? '' : 'disabled' ?>" <?= $grp['tasking'] ? 'onclick="toggleSubmenu(\'TaskingSub\')"' : '' ?>>
                <i class="fa-solid fa-list-check"></i> Tasking Letters
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="TaskingSub" class="submenu">
                <a href="../../letters/tasking/task_letters.php" class="sub-nav-btn <?= $perm['tasking_add'] ? '' : 'disabled' ?>">Add Tasking</a>
                <a href="../../letters/tasking/task_list.php" class="sub-nav-btn <?= $perm['tasking_view'] ? '' : 'disabled' ?>">View Tasking</a>
            </div>
        </div>

        <!-- My Inbox -->
        <div>
            <div class="nav-btn <?= $grp['inbox'] ? '' : 'disabled' ?>" <?= $grp['inbox'] ? 'onclick="toggleSubmenu(\'inboxSub\')"' : '' ?>>
               <i class="fa-solid fa-envelope"></i> My Inbox
               <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="inboxSub" class="submenu">
                <a href="../../letters/inward/inbox.php" class="sub-nav-btn <?= $perm['inbox_internal'] ? '' : 'disabled' ?>">Inward Inbox</a>
                <a href="../../letters/Final_Disposal/final_disposal_inbox.php" class="sub-nav-btn <?= $perm['inbox_disposal'] ? '' : 'disabled' ?>">Disposal Inbox</a>
            </div>
        </div>

        <!-- Linkage Reports -->
        <div>
            <div class="nav-btn <?= $perm['linkage'] ? '' : 'disabled' ?>" <?= $perm['linkage'] ? 'onclick="toggleSubmenu(\'linkSub\')"' : '' ?>>
               <i class="fa-solid fa-link"></i> Linkage Reports
               <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="linkSub" class="submenu">
                <a href="../../letters/linkage/linkage_report.php" class="sub-nav-btn <?= $perm['linkage'] ? '' : 'disabled' ?>">Linkagel Letters</a>
            </div>
        </div>

        <!-- ══════════ INFORMER OPERATIONS ══════════ -->
        <div class="nav-group-label">Informer Operations</div>        
        <div>
            <div class="nav-btn <?= $grp['informer'] ? '' : 'disabled' ?>" <?= $grp['informer'] ? 'onclick="toggleSubmenu(\'InformersSub\')"' : '' ?>>
                <i class="fa-solid fa-users-gear"></i> Informers Unity
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="InformersSub" class="submenu">
                <a href="../../admin/informer/informers.php" class="sub-nav-btn <?= $perm['informer_add'] ? '' : 'disabled' ?>">Add Informers</a>
            </div>
        </div>

        <!-- ══════════ E-FILES OPERATIONS ══════════ -->
        <div class="nav-group-label">E-Files Operations</div>        
        <div>
            <div class="nav-btn <?= $grp['recordroom'] ? '' : 'disabled' ?>" <?= $grp['recordroom'] ? 'onclick="toggleSubmenu(\'recordroomSub\')"' : '' ?>>
                <i class="fa-solid fa-warehouse"></i> Record Room
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="recordroomSub" class="submenu">
                <a href="../../letters/file_list/add_file.php" class="sub-nav-btn <?= $perm['file_add'] ? '' : 'disabled' ?>">Add File</a>
                <a href="../../letters/file_list/file_list.php" class="sub-nav-btn <?= $perm['file_view'] ? '' : 'disabled' ?>">View File List</a>
                <a href="../../letters/file_list/issue_file.php" class="sub-nav-btn <?= $perm['file_issue'] ? '' : 'disabled' ?>">Issue &amp; Returned File</a>
            </div>
        </div>
      
        <!-- ══════════ MODULES ══════════ -->
        <div class="nav-group-label">MODULES</div>

        <!-- PDF CONVERTER -->
        <div>
            <div class="nav-btn <?= $grp['category'] ? '' : 'disabled' ?>" <?= $grp['category'] ? 'onclick="toggleSubmenu(\'PDFcon\')"' : '' ?>>
                <i class="fa-solid fa-file-pdf"></i> PDF Converter
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="PDFcon" class="submenu">
                <a href="../../modules/pdf_extract.php" class="sub-nav-btn <?= $perm['cat_add'] ? '' : 'disabled' ?>">PDF to TEXT</a>
            </div>    
        </div>
        
        <!-- ══════════ SYSTEM MANAGEMENT ══════════ -->
        <div class="nav-group-label">System Management</div>

        <!-- Letter Category -->
        <div>
            <div class="nav-btn <?= $grp['category'] ? '' : 'disabled' ?>" <?= $grp['category'] ? 'onclick="toggleSubmenu(\'ltrcatSub\')"' : '' ?>>
                <i class="fa-solid fa-tags"></i> Letter Category
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="ltrcatSub" class="submenu">
                <a href="../../admin/category/category.php" class="sub-nav-btn <?= $perm['cat_add'] ? '' : 'disabled' ?>">Add Categories</a>
            </div>    
        </div>


        <!-- System Permission -->
        <div>
            <div class="nav-btn <?= $grp['permission'] ? '' : 'disabled' ?>" <?= $grp['permission'] ? 'onclick="toggleSubmenu(\'permissionSub\')"' : '' ?>>
                <i class="fa-solid fa-key me-2"></i> System Permission
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="permissionSub" class="submenu">
                <a href="../../admin/Permissions/add_permission_code.php" class="sub-nav-btn <?= $perm['perm_add'] ? '' : 'disabled' ?>">Add Permission</a>
                <a href="../../admin/Permissions/permission_codes.php" class="sub-nav-btn <?= $perm['perm_view'] ? '' : 'disabled' ?>">View Permission</a>
            </div>
        </div>

        <!-- User Control -->
        <div>
            <div class="nav-btn <?= $grp['users'] ? '' : 'disabled' ?>" <?= $grp['users'] ? 'onclick="toggleSubmenu(\'ControlSub\')"' : '' ?>>
                <i class="fa-solid fa-user-shield"></i> User Control
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="ControlSub" class="submenu">
                <a href="../../admin/users/add_user.php" class="sub-nav-btn <?= $perm['user_add'] ? '' : 'disabled' ?>">Add Users</a>
                <a href="../../admin/users/users.php" class="sub-nav-btn <?= $perm['user_view'] ? '' : 'disabled' ?>">View Users</a>
            </div>
        </div>

        <!-- Desk Control -->
        <div>
            <div class="nav-btn <?= $grp['desk'] ? '' : 'disabled' ?>" <?= $grp['desk'] ? 'onclick="toggleSubmenu(\'deskSub\')"' : '' ?>>
                <i class="fa-solid fa-sitemap me-2"></i> Desk Control
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div id="deskSub" class="submenu">
                <a href="../../admin/desk/add_desk.php" class="sub-nav-btn <?= $perm['desk_add'] ? '' : 'disabled' ?>">Add Desk</a>
            </div>
        </div>

        <!-- User Manual (always enabled) -->
        <a href="../../letters/userguide/index.php" class="nav-btn">
            <i class="fa-solid fa-book me-2"></i> User Manual
        </a>
    </nav>

    <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border);">
        <a href="../../auth/logout.php" class="nav-btn" style="color: #ff5b5b;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="top-nav">
        <div class="top-row">
            <div class="datetime-box">
                <div id="digital-clock">00:00:00</div>
                <div id="digital-date">Monday, 01 Jan 2026</div>
            </div>

            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <!-- ══════════ INBOX INTERNAL NOTIFICATION ══════════ -->
                <div class="dropdown" style="position: relative;">
                    <div id="inboxDropdownBtn" class="notif-btn <?= $perm['inbox_internal'] ? '' : 'disabled' ?>" <?= $perm['inbox_internal'] ? 'data-bs-toggle="dropdown" aria-expanded="false"' : '' ?>>
                        <i class="fa-solid fa-bell"></i>
                        <span class="notif-label">Inbox Internal</span>
                        <?php if ($notSeenCount > 0 && $perm['inbox_internal']): ?>
                            <span class="badge rounded-pill bg-danger notif-badge">
                                <?= $notSeenCount ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($perm['inbox_internal']): ?>
                    <ul class="dropdown-menu dropdown-menu-end notif-menu">
                        <li class="dropdown-header">
                            Inbox Internal (<?= $notSeenCount ?> New)
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <?php if (empty($inboxInternalList)): ?>
                            <li><span class="notif-empty">No new forwarding letters</span></li>
                        <?php else: ?>
                            <?php foreach ($inboxInternalList as $item): ?>
                                <li>
                                    <a class="dropdown-item notif-item" href="../../letters/inward/inbox.php">
                                        <div class="notif-ref">Ref: <?= htmlspecialchars($item['cdr_number'] ?? 'N/A') ?></div>
                                        <div class="notif-title"><?= htmlspecialchars(substr($item['letter_title'], 0, 45)) ?>...</div>
                                        <div class="notif-meta"><?= $item['forwarded_at'] ?></div>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <li>
                            <a class="dropdown-item notif-viewall" href="../../letters/inward/inbox.php">
                                View All Inbox <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- ══════════ FINAL DISPOSAL NOTIFICATION ══════════ -->
                <div class="dropdown" style="position: relative;">
                    <div id="finalDisposalDropdownBtn" class="notif-btn <?= $perm['inbox_disposal'] ? '' : 'disabled' ?>" <?= $perm['inbox_disposal'] ? 'data-bs-toggle="dropdown" aria-expanded="false"' : '' ?>>
                        <i class="fa-solid fa-box-archive"></i>
                        <span class="notif-label">Final Disposal</span>
                        <?php if ($finalDisposalCount > 0 && $perm['inbox_disposal']): ?>
                            <span class="badge rounded-pill bg-danger notif-badge">
                                <?= $finalDisposalCount ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($perm['inbox_disposal']): ?>
                    <ul class="dropdown-menu dropdown-menu-end notif-menu">
                        <li class="dropdown-header">
                            Final Disposal Pending (<?= $finalDisposalCount ?>)
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        
                        <?php if (empty($finalDisposalList)): ?>
                            <li><span class="notif-empty">No pending disposal items</span></li>
                        <?php else: ?>
                            <?php foreach ($finalDisposalList as $item): ?>
                                <li>
                                    <a class="dropdown-item notif-item" href="../../letters/Final_Disposal/final_disposal_inbox.php">
                                        <div class="notif-ref">Ref: <?= htmlspecialchars($item['cdr_number'] ?? 'N/A') ?></div>
                                        <div class="notif-title"><?= htmlspecialchars(substr($item['letter_title'], 0, 45)) ?>...</div>
                                        <div class="notif-meta">
                                            <?php if (!empty($item['priority_status'])): ?>
                                                <span style="color: #ff5b5b; font-weight: 700;"><?= htmlspecialchars($item['priority_status']) ?></span> · 
                                            <?php endif; ?>
                                            <?= date('d M Y H:i', strtotime($item['forwarded_at'])) ?>
                                        </div>
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <li>
                            <a class="dropdown-item notif-viewall" href="../../letters/Final_Disposal/final_disposal_inbox.php">
                                View All Disposals <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>

                <div onclick="toggleTheme()" style="cursor: pointer; color: var(--text-muted); font-size: 1.2rem; padding: 5px;">
                    <i id="theme-icon" class="fa-solid fa-moon"></i>
                </div>
                <div class="user-pill">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--accent); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.75rem;"><?= $userInitials ?></div>
                    <div style="line-height: 1.2;">
                        <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($userName) ?></div>
                        <div style="font-size: 0.6rem; color: var(--text-muted); font-weight: 700;"><?= htmlspecialchars($userRole) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="live-registry-search" class="main-search" placeholder="Search by reference (CDR, TSK, etc.) or title across all letter types...">
        </div>

        <!-- FILTER CONTROLS -->
        <div class="filter-controls">
            <div class="filter-group">
                <label>Type:</label>
                <div class="form-check">
                    <input class="form-check-input filter-type" type="checkbox" value="internal" id="typeInternal" checked>
                    <label class="form-check-label" for="typeInternal">Internal</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input filter-type" type="checkbox" value="external" id="typeExternal" checked>
                    <label class="form-check-label" for="typeExternal">External</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input filter-type" type="checkbox" value="task" id="typeTask" checked>
                    <label class="form-check-label" for="typeTask">Task</label>
                </div>
            </div>

            <div class="filter-group">
                <label for="filterStatus">Status:</label>
                <select id="filterStatus" class="filter-select">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Forwarded">Forwarded</option>
                    <option value="Completed">Completed</option>
                    <option value="Sent">Sent</option>
                    <option value="Draft">Draft</option>
                    <option value="Archived">Archived</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="filterLimit">Limit:</label>
                <select id="filterLimit" class="filter-select">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>

            <div class="filter-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="deepSearchToggle">
                    <label class="form-check-label" for="deepSearchToggle">Deep Search</label>
                </div>
            </div>

            <button class="btn-reset-filters" id="resetFiltersBtn">
                <i class="fa-solid fa-rotate-right"></i> Reset
            </button>
        </div>
    </header>

    <section class="preview-flow">
        <div class="metric-grid">
            <div class="metric-box">
                <span class="metric-title">Today <br> (Inward Only)</span>
                <h2 class="metric-value" style="color: var(--accent);"><?= $todayInward ?></h2>
            </div>
            <div class="metric-box">
                <span class="metric-title">Pending (for the final Disposal)</span>
                <h2 class="metric-value" style="color: #ff051a;"><?= $pendingCount ?></h2>
            </div>
            <div class="metric-box">
                <span class="metric-title">All Letters (Inward<br>| Outward | Tasked)</span>
                <h2 class="metric-value"><?= $allLettersCount ?></h2>
            </div>
            <div class="metric-box">
                <span class="metric-title">Inward (Total Inward letters) </span>
                <h2 class="metric-value" style="color: #f80808;"><?= $internalCount ?></h2>
            </div>
            <div class="metric-box">
                <span class="metric-title">Outward  (Total outward letters)</span>
                <h2 class="metric-value" style="color: #d2ff0a;"><?= $externalCount ?></h2>
            </div>
            <div class="metric-box">
                <span class="metric-title">Tasked (total of tasked to agencis)</span>
                <h2 class="metric-value" style="color: #B800FF;"><?= $taskedCount ?></h2>
            </div>
        </div>

        <div class="preview-wrapper">
            <div class="preview-heading-area">
                <h4 style="font-weight: 800; font-size: 1.05rem; margin: 0; color: var(--text-main);">
                    <i class="fa-solid fa-desktop text-accent me-2"></i>Search Preview Center
                </h4>
                <span id="preview-result-count" style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Idle Mode</span>
            </div>
            
            <div id="search-preview-container">
                <div class="preview-placeholder-empty">
                    <i class="fa-solid fa-keyboard" style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3; color: var(--text-muted);"></i>
                    <h5 style="font-weight: 700; color: var(--text-main); font-size: 1rem;">Waiting for query parameters...</h5>
                    <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 400px; margin: 5px 0 0 0;">Begin typing inside the command search bar above to fetch records instantly as workspace element cards.</p>
                </div>
            </div>
            
            <div id="pagination-container" style="display: none;">
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevPageBtn" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                    <span class="pagination-info" id="pageInfo">Page 1 of 1</span>
                    <button class="pagination-btn" id="nextPageBtn">Next <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    let currentPage = 1;
    let totalPages = 1;
    let currentQuery = '';
    let currentTypes = [];
    let currentStatus = '';
    let currentLimit = 10;
    let currentDeep = 0;

    function toggleSubmenu(id) {
        const sub = document.getElementById(id);
        const chevron = sub.previousElementSibling.querySelector('.chevron');
        sub.classList.toggle('show');
        if (chevron) {
            chevron.style.transform = sub.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    function updateDateTime() {
        const now = new Date();
        document.getElementById('digital-clock').textContent = now.toLocaleTimeString('en-GB', { hour12: false });
        const options = { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' };
        document.getElementById('digital-date').textContent = now.toLocaleDateString('en-GB', options);
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();

    function toggleTheme() {
        const html = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const current = html.getAttribute('data-theme');
        const target = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', target);
        localStorage.setItem('dispatch_theme', target);
        if (target === 'dark') {
            icon.classList.replace('fa-sun', 'fa-moon');
        } else {
            icon.classList.replace('fa-moon', 'fa-sun');
        }
    }

    function getSelectedTypes() {
        const checkboxes = document.querySelectorAll('.filter-type:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function buildDetailsHtml(details) {
        let html = '<div class="result-details">';
        for (const [key, value] of Object.entries(details)) {
            if (value && value !== 'N/A' && value !== 'None') {
                html += `<span class="result-detail-badge"><strong>${key}:</strong> ${value}</span>`;
            }
        }
        html += '</div>';
        return html;
    }

    function performSearch(page) {
        page = page || 1;
        const query = document.getElementById('live-registry-search').value.trim();
        const container = document.getElementById('search-preview-container');
        const countIndicator = document.getElementById('preview-result-count');
        const paginationContainer = document.getElementById('pagination-container');

        const types = getSelectedTypes();
        const status = document.getElementById('filterStatus').value;
        const limit = parseInt(document.getElementById('filterLimit').value);
        const deep = document.getElementById('deepSearchToggle').checked ? 1 : 0;

        currentQuery = query;
        currentTypes = types;
        currentStatus = status;
        currentLimit = limit;
        currentDeep = deep;

        if (query.length === 0) {
            countIndicator.textContent = 'Idle Mode';
            paginationContainer.style.display = 'none';
            container.innerHTML = `
                <div class="preview-placeholder-empty">
                    <i class="fa-solid fa-keyboard" style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3; color: var(--text-muted);"></i>
                    <h5 style="font-weight: 700; color: var(--text-main); font-size: 1rem;">Waiting for query parameters...</h5>
                    <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 400px; margin: 5px 0 0 0;">Begin typing inside the command search bar above to fetch records instantly as workspace cards.</p>
                </div>`;
            return;
        }

        if (types.length === 0) {
            countIndicator.textContent = '0 Matches';
            paginationContainer.style.display = 'none';
            container.innerHTML = `
                <div class="preview-placeholder-empty">
                    <i class="fa-solid fa-sliders" style="font-size: 3.5rem; margin-bottom: 15px; color: var(--text-muted); opacity: 0.5;"></i>
                    <h5 style="font-weight: 700; color: var(--text-main); font-size: 1rem;">No Letter Types Selected</h5>
                    <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 400px; margin: 5px 0 0 0;">Please select at least one letter type (Internal, External, or Task) to search.</p>
                </div>`;
            return;
        }

        countIndicator.textContent = 'Searching...';
        currentPage = page;

        let url = `dashboard.php?action=search_preview&query=${encodeURIComponent(query)}&page=${page}&limit=${limit}&deep=${deep}`;
        types.forEach(t => { url += `&types[]=${t}`; });
        if (status) { url += `&status=${encodeURIComponent(status)}`; }

        fetch(url)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    totalPages = res.pages || 1;
                    if (res.data && res.data.length > 0) {
                        countIndicator.textContent = `${res.total} Matches (Page ${res.page} of ${res.pages})`;
                        
                        let gridHtml = '<div class="preview-grid-cards">';
                        res.data.forEach(item => {
                            let detailsHtml = buildDetailsHtml(item.details || {});
                            gridHtml += `
                                <a href="${item.link}" class="result-item-link">
                                    <div class="result-item-card">
                                        <div class="result-type-ribbon" style="background: ${item.badge};"></div>
                                        <div class="result-header">
                                            <span class="result-channel" style="color: ${item.badge};">${item.type_label}</span>
                                        </div>
                                        <div class="result-ref">${item.ref_no}</div>
                                        <h6 class="result-subject">${item.subject}</h6>
                                        ${detailsHtml}
                                        <div class="result-footer">
                                            <span>Status: <strong style="color: ${item.status === 'Pending' ? '#FFB800' : 'var(--text-main)'};">${item.status}</strong></span>
                                            <span>${item.date}</span>
                                        </div>
                                    </div>
                                </a>
                            `;
                        });
                        gridHtml += '</div>';
                        container.innerHTML = gridHtml;
                        paginationContainer.style.display = 'block';
                        updatePaginationControls(page, totalPages);
                    } else {
                        countIndicator.textContent = '0 Matches';
                        paginationContainer.style.display = 'none';
                        container.innerHTML = `
                            <div class="preview-placeholder-empty">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size: 3.5rem; margin-bottom: 15px; color: #FFB800; opacity: 0.8;"></i>
                                <h5 style="font-weight: 700; color: var(--text-main); font-size: 1rem;">No Records Located</h5>
                                <p style="font-size: 0.85rem; color: var(--text-muted); max-width: 400px; margin: 5px 0 0 0;">No items matching "${query}" were identified with the current filters.</p>
                            </div>`;
                    }
                } else {
                    countIndicator.textContent = 'Error';
                }
            })
            .catch(err => {
                console.error('Search preview transaction fault:', err);
                countIndicator.textContent = 'Error';
            });
    }

    function updatePaginationControls(page, total) {
        const prevBtn = document.getElementById('prevPageBtn');
        const nextBtn = document.getElementById('nextPageBtn');
        const info = document.getElementById('pageInfo');
        info.textContent = `Page ${page} of ${total}`;
        prevBtn.disabled = (page <= 1);
        nextBtn.disabled = (page >= total);
    }

    document.getElementById('live-registry-search').addEventListener('input', function() {
        currentPage = 1;
        performSearch(1);
    });

    document.querySelectorAll('.filter-type, #filterStatus, #filterLimit, #deepSearchToggle').forEach(el => {
        el.addEventListener('change', function() {
            currentPage = 1;
            performSearch(1);
        });
    });

    document.getElementById('prevPageBtn').addEventListener('click', function() {
        if (currentPage > 1) {
            performSearch(currentPage - 1);
        }
    });
    document.getElementById('nextPageBtn').addEventListener('click', function() {
        if (currentPage < totalPages) {
            performSearch(currentPage + 1);
        }
    });

    document.getElementById('resetFiltersBtn').addEventListener('click', function() {
        document.querySelectorAll('.filter-type').forEach(cb => cb.checked = true);
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterLimit').value = '10';
        document.getElementById('deepSearchToggle').checked = false;
        currentPage = 1;
        performSearch(1);
    });

    window.addEventListener('DOMContentLoaded', () => {
        const saved = localStorage.getItem('dispatch_theme') || 'dark';
        document.documentElement.setAttribute('data-theme', saved);
        const icon = document.getElementById('theme-icon');
        if (saved === 'light' && icon) {
            icon.classList.replace('fa-moon', 'fa-sun');
        }
    });
</script>

<script src="../../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>