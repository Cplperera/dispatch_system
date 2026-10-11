<?php
require_once __DIR__ . '/config.php'; // Load global settings

$servername = "localhost";
$db_username = "root";
$db_password = "";
$db_name = "dispatch_system";

$conn = new mysqli($servername, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
