<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!isset($_POST['forwarding_id'], $_POST['comment'])) {
    http_response_code(400);
    exit('Missing data');
}

$forwarding_id = intval($_POST['forwarding_id']);
$comment = trim($_POST['comment']);
$userId = $_SESSION['user_id'];

// Update comment
$stmt = $conn->prepare("UPDATE internal_letters_forwarding SET comments = ?, seen = 1 WHERE id = ? AND forwarded_to = ?");
$stmt->bind_param("sii", $comment, $forwarding_id, $userId);

if($stmt->execute()){
    echo json_encode(['status'=>'success']);
} else {
    http_response_code(500);
    echo json_encode(['status'=>'error']);
}
?>
