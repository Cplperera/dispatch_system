<?php
session_start();
require_once 'config/config.php';

// If user is logged in, redirect to dashboard
if(isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "letters/dashboard/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo SITE_NAME; ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<div class="index-container">
    <h1>Welcome to Letter Dispatching Management System</h1>
    <h2>LDMS</h2>
    <p>This system is designed to manage letters, tasks, and user-based permissions efficiently.</p>

    <a class="btn-login" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
</div>

<style>
/* Simple professional CSS */
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
    margin: 0;
    padding: 0;
}
.index-container {
    width: 400px;
    margin: 120px auto;
    padding: 40px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-align: center;
}
.index-container h1 {
    color: #333;
    margin-bottom: 20px;
}
.index-container p {
    color: #555;
    margin-bottom: 30px;
}
.btn-login {
    display: inline-block;
    text-decoration: none;
    padding: 12px 30px;
    background-color: #007bff;
    color: #fff;
    border-radius: 8px;
    font-weight: bold;
    transition: 0.3s;
}
.btn-login:hover {
    background-color: #0056b3;
}
</style>

</body>
</html>
