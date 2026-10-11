<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_PERMISSIONS')){
    echo "Access Denied!"; exit;
}

$id = $_GET['id'] ?? 0;

$conn->query("DELETE FROM permission_codes WHERE id=$id");
header("Location: permission_codes.php");
exit;
