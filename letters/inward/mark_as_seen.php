<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

if (isset($_GET['id'])) {
    $forwardId = intval($_GET['id']);
    $userId = $_SESSION['user_id'];

    // Security: Ensure the user only marks their own letters as seen
    $stmt = $conn->prepare("UPDATE internal_letters_forwarding SET seen = 1 WHERE id = ? AND forwarded_to = ?");
    $stmt->bind_param("ii", $forwardId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>