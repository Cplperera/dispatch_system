<?php
// PHP warnings/notices මගින් JSON corrupt වීම වැළැක්වීමට buffer කිරීම
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/auth.php';
require_once '../../config/db.php';

// MySQLi Error reporting (Strict mode)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    checkSessionTimeout();

    // 1. Permission check
    if (!hasPermission('FORWARD_INTERNAL_LETTER')) {
        echo json_encode([
            "success" => false, 
            "status" => "error", 
            "error" => "Access Denied! You do not have permission to forward letters."
        ]);
        exit();
    }

    // 2. Validate POST details
    if (!isset($_POST['letter_id'], $_POST['forward_users']) || !is_array($_POST['forward_users'])) {
        echo json_encode([
            "success" => false, 
            "status" => "error", 
            "error" => "Invalid request details."
        ]);
        exit();
    }

    $letter_id = intval($_POST['letter_id']);
    // Integer values පමණක් වෙන් කරගැනීම
    $forward_users = array_filter(array_map('intval', $_POST['forward_users'])); 
    $comments = trim($_POST['comments'] ?? '');
    $forwarded_by = $_SESSION['user_id'] ?? 0;
    $forwarded_at = date("Y-m-d H:i:s");

    if ($letter_id <= 0) {
        throw new Exception("Invalid Letter ID.");
    }

    if (empty($forward_users)) {
        throw new Exception("Please select at least one recipient user.");
    }

    if ($forwarded_by <= 0) {
        throw new Exception("User session invalid. Please log in again.");
    }

    // Begin transaction
    $conn->begin_transaction();

    // 3. Insert forwarding records
    $stmt = $conn->prepare("
        INSERT INTO internal_letters_forwarding (letter_id, forwarded_to, forwarded_by, forwarded_at, comments)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($forward_users as $user_id) {
        $stmt->bind_param("iiiss", $letter_id, $user_id, $forwarded_by, $forwarded_at, $comments);
        $stmt->execute();
    }
    $stmt->close();

    // 4. Update internal letter status to 'Forwarded'
    $updateStmt = $conn->prepare("UPDATE internal_letters SET status = 'Forwarded' WHERE id = ? AND is_deleted = 0");
    $updateStmt->bind_param("i", $letter_id);
    $updateStmt->execute();
    $updateStmt->close();

    // Commit transaction
    $conn->commit();

    ob_end_clean();
    echo json_encode([
        "success" => true, 
        "status" => "success", 
        "message" => "Letter forwarded successfully."
    ]);

} catch (Throwable $e) {
    // දෝෂයක් ආවොත් Rollback කිරීම
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }

    ob_end_clean();
    echo json_encode([
        "success" => false, 
        "status" => "error", 
        "error" => $e->getMessage()
    ]);
}
exit();
?>