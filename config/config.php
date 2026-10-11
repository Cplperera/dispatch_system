<?php
// Site Name
define('SITE_NAME', 'Letter Dispatching System');

// Base URL (adjust if system is not in root folder)
define('BASE_URL', 'http://localhost/dispatch-system/');

// Default timezone
date_default_timezone_set('Asia/Colombo');

// Admin email (for notifications)
define('ADMIN_EMAIL', 'admin@lds.com');

// Default user settings
define('DEFAULT_PASSWORD_LENGTH', 8);

// Upload paths
define('UPLOAD_LETTERS_PATH', __DIR__ . '/../uploads/letters/');

// Session timeout in seconds (optional)
define('SESSION_TIMEOUT', 600);
?>
