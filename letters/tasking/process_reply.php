<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reply'])) {
    
    // 1. POST Data ලබාගැනීම සහ Form Validations
    $task_letter_id = intval($_POST['task_letter_id'] ?? 0);
    $replied_by     = intval($_POST['replied_by'] ?? $_SESSION['user_id'] ?? 0);
    $regiment_id    = intval($_POST['regiment_id'] ?? 0);
    $status         = trim($_POST['status'] ?? 'Accepted');
    
    // Status එක 'Data NIL' නොවේ නම් පමණක් Letter ID එක ගන්න (NIL නම් NULL කරන්න)
    $reply_letter_id = (!empty($_POST['reply_internal_letter_id']) && $status !== 'Data NIL') 
                        ? intval($_POST['reply_internal_letter_id']) 
                        : NULL;

    if ($task_letter_id <= 0 || $regiment_id <= 0 || empty($status)) {
        die("Error: Required parameters are missing.");
    }

    // 2. මෙම Regiment එකෙන් මීට පෙර Reply එකක් ලබා දී තිබේදැයි බලන්න (agency_id වෙනුවට regiment_id භාවිත කර ඇත)
    $check_stmt = $conn->prepare("SELECT id FROM task_replies WHERE task_letter_id = ? AND regiment_id = ?");
    $check_stmt->bind_param("ii", $task_letter_id, $regiment_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing) {
        // පවතින Record එක UPDATE කිරීම
        $stmt = $conn->prepare("
            UPDATE task_replies 
            SET reply_internal_letter_id = ?, 
                status = ?, 
                replied_by = ?, 
                replied_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("isii", $reply_letter_id, $status, $replied_by, $existing['id']);
    } else {
        // අලුත් Record එකක් INSERT කිරීම
        $stmt = $conn->prepare("
            INSERT INTO task_replies (task_letter_id, regiment_id, reply_internal_letter_id, status, replied_by, replied_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iiisi", $task_letter_id, $regiment_id, $reply_letter_id, $status, $replied_by);
    }

    // 3. Query එක Execute කිරීම
    if ($stmt->execute()) {
        $stmt->close();

        // 4. task_letters හි status එක AUTO UPDATE කිරීම ('Pending', 'Partially Replied', 'Completed')
        $update_task_sql = "
            UPDATE task_letters t
            LEFT JOIN (
                SELECT task_letter_id, COUNT(*) AS total_assigned 
                FROM task_letter_agencies 
                WHERE task_letter_id = ?
            ) a ON t.id = a.task_letter_id
            LEFT JOIN (
                SELECT task_letter_id, COUNT(DISTINCT regiment_id) AS total_replied 
                FROM task_replies 
                WHERE task_letter_id = ?
            ) r ON t.id = r.task_letter_id
            SET t.status = CASE 
                WHEN COALESCE(r.total_replied, 0) = 0 THEN 'Pending'
                WHEN COALESCE(a.total_assigned, 0) > 0 AND COALESCE(r.total_replied, 0) >= COALESCE(a.total_assigned, 0) THEN 'Completed'
                ELSE 'Partially Replied'
            END
            WHERE t.id = ?
        ";

        $status_stmt = $conn->prepare($update_task_sql);
        $status_stmt->bind_param("iii", $task_letter_id, $task_letter_id, $task_letter_id);
        $status_stmt->execute();
        $status_stmt->close();

        // Task View එකට Redirect කිරීම
        header("Location: view_task.php?id=" . $task_letter_id . "&msg=reply_submitted");
        exit;
    } else {
        echo "Database Update Error: " . $stmt->error;
    }
} else {
    header("Location: task_list.php");
    exit;
}
?>