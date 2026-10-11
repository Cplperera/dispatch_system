<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

$q = $_GET['q'] ?? '';

// Include CDR number in search
$stmt = $conn->prepare("
    SELECT id, cdr_number, letter_reference, letter_title
    FROM internal_letters
    WHERE is_deleted = 0
      AND (cdr_number LIKE ? OR letter_reference LIKE ? OR letter_title LIKE ?)
    LIMIT 50
");

$search = "%$q%";
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);