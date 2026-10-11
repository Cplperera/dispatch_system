<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_USERS')){
    echo "Access Denied!"; exit;
}

$id = $_GET['id'] ?? 0;

$conn->query("UPDATE users SET is_deleted=1 WHERE id=$id");
header("Location: users.php");
exit;
