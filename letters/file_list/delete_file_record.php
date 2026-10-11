<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

header('Content-Type: application/json');

if (!hasPermission('DELETE_FILE_RECORD')) {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Check if file exists and is not already deleted
    $check = $conn->prepare("SELECT id FROM physical_file_records WHERE id = ? AND is_deleted = 0");
    $check->bind_param("i", $id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Record not found or already deleted']);
        exit;
    }

    // SOFT DELETE: Update is_deleted to 1 instead of DELETE FROM
    $stmt = $conn->prepare("UPDATE physical_file_records SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>