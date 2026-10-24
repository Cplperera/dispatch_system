<?php
/**
 * Secure download for extracted .txt / uploaded .pdf
 * Only serves files inside uploads/pdf_extract/
 */
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('Session Invalid'); }
if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('VIEW_OUTGOING_LETTERS')) {
    http_response_code(403); exit('Access Denied');
}

$rootDir   = realpath(__DIR__ . '/../../');
$uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_extract';
$uploadReal = realpath($uploadDir);
if (!$uploadReal) { http_response_code(500); exit('Storage missing'); }

$f = (string)($_GET['f'] ?? '');

// Strict filename whitelist: letters, digits, underscore, dash, dot
if ($f === '' || !preg_match('/^[a-zA-Z0-9_.\-]+$/', $f)) {
    http_response_code(400); exit('Invalid filename');
}
// Only .pdf or .txt
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf','txt'], true)) {
    http_response_code(400); exit('Invalid file type');
}

$path = $uploadReal . DIRECTORY_SEPARATOR . $f;
$real = realpath($path);
if (!$real || strpos($real, $uploadReal) !== 0 || !is_file($real)) {
    http_response_code(404); exit('File not found');
}

$mime = $ext === 'pdf' ? 'application/pdf' : 'text/plain; charset=utf-8';
$dlName = basename($real);

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $dlName . '"');
header('Content-Length: ' . filesize($real));
header('Cache-Control: private, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($real);
exit;