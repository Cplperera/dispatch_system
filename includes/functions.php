<?php
require_once __DIR__ . '/../config/config.php';

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Permission check function
function hasPermission($permissionCode) {
    if(isset($_SESSION['permissions'][$permissionCode])) {
        return $_SESSION['permissions'][$permissionCode] == 1;
    }
    return false;
}

// Redirect if not logged in
function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

// Optional: Auto-logout after inactivity
function checkSessionTimeout() {
    if(isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        session_destroy();
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}
?>
