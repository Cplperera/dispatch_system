<?php
/* ─────────────────────────────────────────────────────────────
   PDF -> TEXT EXTRACTOR (Offline) — Smart Multi-Engine
   Chain:
     1. pdftotext -layout    (best for digital PDFs)
     2. pdftotext -raw       (fallback for linear PDFs)
     3. OCR (pdftoppm+tesseract Sinhala)  <- FIX for broken-font PDFs
   Quality score auto-picks the best result.
   
   Cleanup options:
     - Auto: files older than N days deleted on page load (throttled)
     - Manual: "Clean old", "Delete selected", "Delete all"
   
   Theme: reads from localStorage (set by home page dashboard)
   ───────────────────────────────────────────────────────────── */

/* chdir trick so auth.php's ../../config resolves */
$__saved_cwd = getcwd();
$__root      = realpath(__DIR__ . '/..');
$__target    = null;
if ($__root) {
    foreach (['letters/inward','letters/outward','letters/tasking','modules','includes'] as $__sub) {
        $__cand = $__root . '/' . $__sub;
        if (is_dir($__cand) && is_dir($__cand . '/../../config')) { $__target = $__cand; break; }
    }
    if ($__target === null) {
        $__target = $__root . '/.tmp_cwd/a';
        if (!is_dir($__target)) @mkdir($__target, 0700, true);
    }
}
if ($__target) chdir($__target);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

if ($__saved_cwd && is_dir($__saved_cwd)) chdir($__saved_cwd);

checkSessionTimeout();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit('Session Invalid'); }
if (!hasPermission('VIEW_INTERNAL_LETTERS') && !hasPermission('VIEW_OUTGOING_LETTERS')) {
    http_response_code(403); exit('Access Denied');
}
mb_internal_encoding('UTF-8');

/* ─── Time & memory limits (OCR can be slow) ─────────────── */
@set_time_limit(0);                       // Unlimited (dev only)
@ini_set('max_execution_time', '0');      // Or '600' for 10 min cap
@ini_set('memory_limit', '512M');
@ini_set('max_input_time', '600');
ignore_user_abort(true);                  // Continue even if browser disconnects

/* ─── Config ───────────────────────────────────────────── */
$maxFileSize        = 25 * 1024 * 1024;
$fileRetentionDays  = 7;
$cleanupThrottleSec = 3600;

$rootDir   = realpath(__DIR__ . '/..');
$uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_extract';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0700, true);

$cleanupMarker = $uploadDir . DIRECTORY_SEPARATOR . '.last_cleanup';

/* ─── Init result vars ─────────────────────────────────── */
$result = null; $error = null; $extractedText = '';
$wordCount = 0; $charCount = 0; $lineCount = 0; $pageCount = 0;
$manualCleanup = false;
$manualMode    = null;
$cleanupInfo   = null;

/* ─── Helpers ──────────────────────────────────────────── */
function findBin(array $candidates, string $winCmd, string $nixCmd): ?string {
    foreach ($candidates as $c) { if (@is_file($c)) return $c; }
    $cmd = (PHP_OS_FAMILY === 'Windows') ? $winCmd : $nixCmd;
    $out = @shell_exec($cmd);
    if ($out) {
        $first = trim(strtok($out, "\n"));
        if ($first && @is_file($first)) return $first;
    }
    return null;
}

function deleteFiles(string $dir, string $mode, int $days = 7, array $targets = []): array {
    $res = ['deleted' => 0, 'freed' => 0, 'errors' => 0];
    if (!is_dir($dir)) return $res;

    $cutoff = time() - ($days * 86400);
    $scan   = @scandir($dir);
    if (!is_array($scan)) return $res;

    foreach ($scan as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','txt'], true)) continue;

        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($p)) continue;

        $doDelete = false;
        if ($mode === 'all') {
            $doDelete = true;
        } elseif ($mode === 'old') {
            $mtime = @filemtime($p);
            if ($mtime !== false && $mtime < $cutoff) $doDelete = true;
        } elseif ($mode === 'list') {
            $base = preg_replace('/\.(pdf|txt)$/i', '', $f);
            if (in_array($base, $targets, true)) $doDelete = true;
        }
        if (!$doDelete) continue;

        $size = @filesize($p);
        if (@unlink($p)) {
            $res['deleted']++;
            if ($size !== false) $res['freed'] += $size;
        } else {
            $res['errors']++;
        }
    }
    return $res;
}

/* ─── Binary detection ─────────────────────────────────── */
$pdftotextBin = findBin([
    '/usr/bin/pdftotext','/usr/local/bin/pdftotext','/opt/homebrew/bin/pdftotext',
    'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe',
    'C:\\Program Files (x86)\\poppler\\Library\\bin\\pdftotext.exe',
    'C:\\poppler\\Library\\bin\\pdftotext.exe',
    'C:\\poppler\\bin\\pdftotext.exe',
    'C:\\xampp\\poppler\\Library\\bin\\pdftotext.exe',
], 'where pdftotext 2>NUL', 'command -v pdftotext 2>/dev/null');

$pdftoppmBin = findBin([
    '/usr/bin/pdftoppm','/usr/local/bin/pdftoppm','/opt/homebrew/bin/pdftoppm',
    'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
    'C:\\Program Files (x86)\\poppler\\Library\\bin\\pdftoppm.exe',
    'C:\\poppler\\Library\\bin\\pdftoppm.exe',
    'C:\\poppler\\bin\\pdftoppm.exe',
    'C:\\xampp\\poppler\\Library\\bin\\pdftoppm.exe',
], 'where pdftoppm 2>NUL', 'command -v pdftoppm 2>/dev/null');

$tesseractBin = findBin([
    '/usr/bin/tesseract','/usr/local/bin/tesseract','/opt/homebrew/bin/tesseract',
    'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
    'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
    'C:\\Tesseract-OCR\\tesseract.exe',
], 'where tesseract 2>NUL', 'command -v tesseract 2>/dev/null');

$tesseractHasSinhala = false;
if ($tesseractBin) {
    $out = @shell_exec(escapeshellarg($tesseractBin) . ' --list-langs 2>&1');
    if ($out && preg_match('/^\s*sin\s*$/mi', $out)) $tesseractHasSinhala = true;
}

/* ─── MANUAL CLEANUP HANDLERS ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = (string) $_POST['action'];

    if ($act === 'cleanup_old') {
        $cleanupInfo   = deleteFiles($uploadDir, 'old', $fileRetentionDays);
        $manualCleanup = true;
        $manualMode    = 'old';
        @file_put_contents($cleanupMarker, (string) time());
    } elseif ($act === 'delete_all') {
        $cleanupInfo   = deleteFiles($uploadDir, 'all');
        $manualCleanup = true;
        $manualMode    = 'all';
        @file_put_contents($cleanupMarker, (string) time());
    } elseif ($act === 'delete_selected') {
        $targets = isset($_POST['targets']) && is_array($_POST['targets']) ? $_POST['targets'] : [];
        $targets = array_values(array_filter(array_map(function($t){
            return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$t);
        }, $targets), function($t){ return $t !== ''; }));

        if (empty($targets)) {
            $cleanupInfo   = ['deleted'=>0, 'freed'=>0, 'errors'=>0, 'empty'=>true];
            $manualCleanup = true;
            $manualMode    = 'selected';
        } else {
            $cleanupInfo   = deleteFiles($uploadDir, 'list', 0, $targets);
            $manualCleanup = true;
            $manualMode    = 'selected';
            @file_put_contents($cleanupMarker, (string) time());
        }
    }
}

/* ─── Auto-cleanup ─────────────────────────────────────── */
if (!$manualCleanup) {
    $shouldClean = false;
    if (is_file($cleanupMarker)) {
        $last = (int) @file_get_contents($cleanupMarker);
        if (time() - $last >= $cleanupThrottleSec) $shouldClean = true;
    } else {
        $shouldClean = true;
    }
    if ($shouldClean) {
        $cleanupInfo = deleteFiles($uploadDir, 'old', $fileRetentionDays);
        @file_put_contents($cleanupMarker, (string) time());
    }
}

/* ─── Quality scoring ──────────────────────────────────── */
function sinhalaQuality(string $text): int {
    $text = trim($text);
    if ($text === '') return 0;
    $total = mb_strlen($text);
    if ($total < 20) return 20;
    preg_match_all('/[\x{0D80}-\x{0DFF}]/u', $text, $m);
    $charRatio = count($m[0]) / max(1, $total);
    preg_match_all('/\S+/u', $text, $words);
    $totalWords = count($words[0]);
    if ($totalWords === 0) return 10;
    $single = 0; $sinWords = 0;
    foreach ($words[0] as $w) {
        if (mb_strlen($w) === 1) $single++;
        if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $w)) $sinWords++;
    }
    $brokenRatio  = $single / $totalWords;
    $sinWordRatio = $sinWords / $totalWords;
    preg_match_all('/[A-Za-z]/', $text, $ascii);
    $asciiRatio = count($ascii[0]) / max(1, $total);
    $score  = 0;
    $score += (int)($charRatio * 55);
    $score += (int)($sinWordRatio * 30);
    $score -= (int)($brokenRatio * 55);
    if ($asciiRatio > 0.35) $score -= 20;
    if ($charRatio   < 0.15) $score -= 15;
    return max(0, min(100, $score));
}

/* ─── Extractors ────────────────────────────────────────── */
function extractWithPdftotext(string $pdf, string $bin, string $flag = '-layout'): ?string {
    $tmp = tempnam(sys_get_temp_dir(), 'pdftxt_') . '.txt';
    $cmd = escapeshellarg($bin) . " {$flag} -enc UTF-8 "
         . escapeshellarg($pdf) . ' ' . escapeshellarg($tmp) . ' 2>&1';
    @exec($cmd, $out, $code);
    if ($code === 0 && is_file($tmp)) {
        $t = @file_get_contents($tmp);
        @unlink($tmp);
        return $t !== false ? $t : null;
    }
    @unlink($tmp);
    return null;
}

function extractWithSmalot(string $pdf): ?string {
    if (!class_exists('Smalot\\PdfParser\\Parser')) return null;
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $doc    = $parser->parseFile($pdf);
        $t      = $doc->getText();
        return is_string($t) ? $t : null;
    } catch (\Throwable $e) { return null; }
}

function extractWithOCR(string $pdf, string $pdftoppmBin, string $tesseractBin, string $lang = 'sin+eng'): ?string {
    if (!$pdftoppmBin || !$tesseractBin) return null;
    $work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . bin2hex(random_bytes(5));
    if (!@mkdir($work, 0700, true)) return null;
    try {
        $prefix = $work . DIRECTORY_SEPARATOR . 'p';
        $cmd = escapeshellarg($pdftoppmBin) . ' -r 300 -png -aa yes '
             . escapeshellarg($pdf) . ' ' . escapeshellarg($prefix) . ' 2>&1';
        @exec($cmd, $o1, $c1);
        if ($c1 !== 0) return null;
        $pages = glob($prefix . '*.png');
        if (!$pages) return null;
        sort($pages, SORT_NATURAL);
        $full = '';
        foreach ($pages as $i => $img) {
            $outBase = $work . DIRECTORY_SEPARATOR . 'o' . $i;
            $cmd = escapeshellarg($tesseractBin) . ' '
                 . escapeshellarg($img) . ' '
                 . escapeshellarg($outBase) . ' '
                 . '-l ' . escapeshellarg($lang)
                 . ' --psm 6 --oem 1 2>&1';
            @exec($cmd, $o, $c);
            $tf = $outBase . '.txt';
            if (is_file($tf)) {
                $full .= file_get_contents($tf);
                if ($i < count($pages) - 1) $full .= "\n\f\n";
            }
            @unlink($img);
            @unlink($tf);
        }
        return $full !== '' ? $full : null;
    } finally {
        if (is_dir($work)) {
            foreach (glob($work . DIRECTORY_SEPARATOR . '*') as $f) @unlink($f);
            @rmdir($work);
        }
    }
}

function extractPdfText(string $pdf, array $eng): array {
    $attempts = []; $best = null; $bestScore = -1;
    if (!empty($eng['pdftotext'])) {
        $t = extractWithPdftotext($pdf, $eng['pdftotext'], '-layout');
        if ($t !== null && trim($t) !== '') {
            $s = sinhalaQuality($t);
            $attempts[] = "pdftotext -layout: {$s}/100";
            if ($s > $bestScore) { $best = ['text'=>$t, 'engine'=>'pdftotext (layout)']; $bestScore = $s; }
            if ($s >= 70) { $best['attempts'] = $attempts; $best['score'] = $s; return $best; }
        }
    }
    if (!empty($eng['pdftotext'])) {
        $t = extractWithPdftotext($pdf, $eng['pdftotext'], '-raw');
        if ($t !== null && trim($t) !== '') {
            $s = sinhalaQuality($t);
            $attempts[] = "pdftotext -raw: {$s}/100";
            if ($s > $bestScore) { $best = ['text'=>$t, 'engine'=>'pdftotext (raw)']; $bestScore = $s; }
            if ($s >= 70) { $best['attempts'] = $attempts; $best['score'] = $s; return $best; }
        }
    }
    if (!empty($eng['pdftoppm']) && !empty($eng['tesseract'])) {
        $lang = !empty($eng['tesseract_has_sin']) ? 'sin+eng' : 'eng';
        $t = extractWithOCR($pdf, $eng['pdftoppm'], $eng['tesseract'], $lang);
        if ($t !== null && trim($t) !== '') {
            $s = sinhalaQuality($t);
            $attempts[] = "OCR ({$lang}): {$s}/100";
            if ($s > $bestScore) { $best = ['text'=>$t, 'engine'=>"OCR Tesseract ({$lang})"]; $bestScore = $s; }
        }
    } else {
        $attempts[] = 'OCR skipped (missing pdftoppm or tesseract)';
    }
    if (!empty($eng['smalot'])) {
        $t = extractWithSmalot($pdf);
        if ($t !== null && trim($t) !== '') {
            $s = sinhalaQuality($t);
            $attempts[] = "Smalot: {$s}/100";
            if ($s > $bestScore) { $best = ['text'=>$t, 'engine'=>'Smalot\\PdfParser']; $bestScore = $s; }
        }
    }
    if ($best === null) throw new RuntimeException('No engine produced text. Attempts: ' . implode(' / ', $attempts));
    $best['attempts'] = $attempts;
    $best['score']    = $bestScore;
    return $best;
}

/* ─── UPLOAD HANDLER ───────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $file = $_FILES['pdf_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE=>'File exceeds server limit', UPLOAD_ERR_FORM_SIZE=>'File exceeds form limit',
            UPLOAD_ERR_PARTIAL=>'Partial upload', UPLOAD_ERR_NO_FILE=>'No file',
            UPLOAD_ERR_NO_TMP_DIR=>'No temp dir', UPLOAD_ERR_CANT_WRITE=>'Cannot write',
            UPLOAD_ERR_EXTENSION=>'Extension blocked',
        ];
        $error = 'Upload failed: ' . ($errMap[$file['error']] ?? ('code '.$file['error']));
    }
    elseif ($file['size'] <= 0)            { $error = 'File is empty.'; }
    elseif ($file['size'] > $maxFileSize)  { $error = 'File too large.'; }
    else {
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = finfo_file($fi, $file['tmp_name']); finfo_close($fi); }
        }
        $magic = '';
        if ($fp = @fopen($file['tmp_name'],'rb')) { $magic = fread($fp,5); fclose($fp); }

        if ($mime !== 'application/pdf' && $magic !== '%PDF-') {
            $error = 'Invalid file type.';
        } else {
            $base     = pathinfo($file['name'], PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
            $safeBase = mb_substr($safeBase, 0, 60);
            $stamp    = date('Ymd_His');
            $rand     = bin2hex(random_bytes(4));
            $storedPdf = "{$stamp}_{$rand}_{$safeBase}.pdf";
            $storedTxt = "{$stamp}_{$rand}_{$safeBase}.txt";
            $pdfPath   = $uploadDir . DIRECTORY_SEPARATOR . $storedPdf;
            $txtPath   = $uploadDir . DIRECTORY_SEPARATOR . $storedTxt;

            if (!@move_uploaded_file($file['tmp_name'], $pdfPath)) {
                $error = 'Cannot store upload. Check permissions on uploads/pdf_extract/.';
            } else {
                try {
                    $eng = [
                        'pdftotext'         => $pdftotextBin,
                        'pdftoppm'          => $pdftoppmBin,
                        'tesseract'         => $tesseractBin,
                        'tesseract_has_sin' => $tesseractHasSinhala,
                        'smalot'            => $smalotAvailable ?? false,
                    ];
                    $extract       = extractPdfText($pdfPath, $eng);
                    $extractedText = $extract['text'];
                    $engineUsed    = $extract['engine'];
                    $quality       = $extract['score'];
                    $attempts      = $extract['attempts'];

                    $extractedText = preg_replace("/\r\n|\r/", "\n", $extractedText);
                    $extractedText = preg_replace('/^\xEF\xBB\xBF/', '', $extractedText);
                    file_put_contents($txtPath, $extractedText);

                    $charCount = mb_strlen($extractedText);
                    $lineCount = substr_count($extractedText, "\n") + 1;
                    preg_match_all('/\S+/u', $extractedText, $mw);
                    $wordCount = is_array($mw[0]) ? count($mw[0]) : 0;
                    $pageCount = substr_count($extractedText, "\f") + 1;

                    $result = [
                        'original'   => $file['name'],
                        'stored_pdf' => $storedPdf,
                        'stored_txt' => $storedTxt,
                        'size'       => $file['size'],
                        'engine'     => $engineUsed,
                        'score'      => $quality,
                        'attempts'   => $attempts,
                        'chars'      => $charCount,
                        'words'      => $wordCount,
                        'lines'      => $lineCount,
                        'pages'      => $pageCount,
                        'at'         => date('d M Y H:i:s'),
                    ];
                } catch (\Throwable $e) {
                    $error = 'Extraction failed: ' . $e->getMessage();
                    @unlink($pdfPath);
                }
            }
        }
    }
}

/* ─── Recent files + stats ─────────────────────────────── */
$recentFiles = [];
$totalSize   = 0;
$totalCount  = 0;
$oldCount    = 0;
$oldCutoff   = time() - ($fileRetentionDays * 86400);

if (is_dir($uploadDir)) {
    $scan = @scandir($uploadDir, SCANDIR_SORT_DESCENDING);
    if (is_array($scan)) {
        $seen = [];
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['txt','pdf'], true)) continue;

            $p = $uploadDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($p)) continue;
            $totalCount++;
            $totalSize += (int) @filesize($p);

            if ($ext !== 'txt') continue;
            $k = preg_replace('/\.txt$/', '', $f);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;

            $mt = (int) @filemtime($p);
            if ($mt > 0 && $mt < $oldCutoff) $oldCount++;

            $recentFiles[] = [
                'key'  => $k,
                'txt'  => $f,
                'pdf'  => preg_replace('/\.txt$/','.pdf',$f),
                'size' => filesize($p),
                'time' => filemtime($p),
            ];
            if (count($recentFiles) >= 20) break;
        }
    }
}
$totalSizeMb = $totalSize / 1048576;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PDF to Text Extractor | IDCMS</title>
<link href="../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/css/all.min.css" rel="stylesheet">
<style>
:root {
    --bg-body: #F0F2F5;
    --sidebar-bg: #FFFFFF;
    --card-bg: #FFFFFF;
    --accent: #4318FF;
    --accent-soft: rgba(67, 24, 255, 0.08);
    --text-main: #1B2559;
    --text-muted: #707EAE;
    --border: #DDE2EF;
    --sidebar-width: 280px;
    --placeholder-color: #A3AED0;
    --ok:#10b981; --warn:#f59e0b; --err:#ef4444;
}
[data-theme="dark"] {
    --bg-body: #0B1437;
    --sidebar-bg: #111C44;
    --card-bg: #111C44;
    --accent: #7551FF;
    --accent-soft: rgba(117, 81, 255, 0.1);
    --text-main: #FFFFFF;
    --text-muted: #A3AED0;
    --border: rgba(255, 255, 255, 0.1);
    --placeholder-color: #707EAE;
    color-scheme: dark;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh;
    font-family: 'Iskoola Pota', 'Noto Sans Sinhala', 'UN-Abhaya', 'Segoe UI', system-ui, sans-serif; transition: 0.3s; }

/* ═══════════════════ SIDEBAR ═══════════════════ */
.sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border);
    padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
.sidebar::-webkit-scrollbar { width:6px; }
.sidebar::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }

.brand-box { margin-bottom: 24px; padding: 0 8px; }
.brand-name { font-weight: 800; font-size: 1.15rem; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
.brand-name i { color: var(--accent); font-size: 1.25rem; }

.nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted);
    text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
    transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left;
    cursor: pointer; gap: 10px; }
.nav-btn i:first-child { width: 18px; text-align: center; }
.nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
.nav-btn i.small { font-size: 0.7rem; margin-left: auto; opacity: 0.7; transition: transform 0.25s; }

.submenu { padding-left: 20px; display: none; }
.submenu.show { display: block; }
.sub-nav-btn { display: block; padding: 8px 15px; color: var(--text-muted); text-decoration: none;
    font-size: 0.8rem; font-weight: 600; border-left: 2px solid var(--border); transition: 0.2s; }
.sub-nav-btn:hover { color: var(--accent); border-left-color: var(--accent); }
.sub-nav-btn.active-sub { color: var(--accent); border-left-color: var(--accent); font-weight: 700; }

.section-label { font-size: 0.65rem; font-weight: 800; color: var(--text-muted);
    text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7; letter-spacing: 1px; }

/* ═══════════════════ MAIN ═══════════════════ */
.main { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
.page-head h2 { font-size: 1.4rem; font-weight: 800; margin: 0 0 4px; color: var(--text-main); }
.page-head p { color: var(--text-muted); font-size: 0.88rem; margin: 0 0 22px; }

.card-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 22px;
    margin-bottom: 20px; transition: background 0.25s, border-color 0.25s; }
.card-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px;
    color: var(--text-muted); margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--accent); }

.upload-zone { border: 2px dashed var(--border); border-radius: 14px; padding: 36px 22px; text-align: center;
    cursor: pointer; transition: 0.2s; background: var(--bg-body); }
.upload-zone:hover, .upload-zone.drag { border-color: var(--accent); background: var(--accent-soft); }
.upload-zone i.big { font-size: 2.5rem; color: var(--accent); opacity: 0.85; }
.upload-zone h5 { font-size: 1rem; font-weight: 800; margin: 12px 0 6px; color: var(--text-main); }
.upload-zone p  { font-size: 0.82rem; color: var(--text-muted); margin: 0 0 12px; }
.upload-zone .file-info { font-size: 0.8rem; color: var(--accent); font-weight: 700; margin-top: 10px; }

.btn-primary-c { display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: #fff;
    border: none; padding: 11px 22px; border-radius: 12px; font-weight: 800; font-size: 0.85rem;
    cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-primary-c:hover { opacity: 0.9; transform: translateY(-1px); color: #fff; }
.btn-primary-c:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.btn-ghost-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--text-muted);
    border: 1px solid var(--border); padding: 10px 20px; border-radius: 12px; font-weight: 700;
    font-size: 0.83rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-ghost-c:hover { border-color: var(--accent); color: var(--accent); }

.btn-danger-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--err);
    border: 1px solid var(--err); padding: 9px 16px; border-radius: 12px; font-weight: 700;
    font-size: 0.78rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-danger-c:hover { background: var(--err); color: #fff; }
.btn-danger-c:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-warn-c { display: inline-flex; align-items: center; gap: 8px; background: transparent; color: var(--warn);
    border: 1px solid var(--warn); padding: 9px 16px; border-radius: 12px; font-weight: 700;
    font-size: 0.78rem; cursor: pointer; transition: 0.2s; text-decoration: none; }
.btn-warn-c:hover { background: var(--warn); color: #fff; }
.btn-warn-c:disabled { opacity: 0.4; cursor: not-allowed; }

.engine-row { display: flex; align-items: center; gap: 10px; font-size: 0.8rem; padding: 6px 0; }
.engine-row .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.engine-row .dot.ok   { background: var(--ok); box-shadow: 0 0 0 3px rgba(16,185,129,0.15); }
.engine-row .dot.err  { background: var(--err); box-shadow: 0 0 0 3px rgba(239,68,68,0.15); }
.engine-row .dot.warn { background: var(--warn); box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }
.engine-row strong { font-weight: 700; color: var(--text-main); }
.engine-row span.note { color: var(--text-muted); margin-left: auto; font-size: 0.75rem; }

.alert-box { padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; }
.alert-box.err { background: rgba(239,68,68,0.1); color: var(--err); border-left: 4px solid var(--err); }
.alert-box.ok  { background: rgba(16,185,129,0.1); color: var(--ok); border-left: 4px solid var(--ok); }
.alert-box.warn{ background: rgba(245,158,11,0.1); color: var(--warn); border-left: 4px solid var(--warn); }
.alert-box.info{ background: var(--accent-soft); color: var(--accent); border-left: 4px solid var(--accent); }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 16px; }
.stat { background: var(--bg-body); border-radius: 10px; padding: 10px 14px; text-align: left; }
.stat .label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); font-weight: 800; }
.stat .value { font-size: 1.1rem; font-weight: 800; color: var(--accent); margin-top: 2px; }

.preview { background: var(--bg-body); border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px;
    max-height: 520px; overflow-y: auto; font-family: 'Noto Sans Sinhala','Courier New',monospace;
    font-size: 0.9rem; line-height: 1.7; white-space: pre-wrap; word-break: break-word; color: var(--text-main); }
.preview::-webkit-scrollbar { width: 8px; }
.preview::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

.recent-list { display: flex; flex-direction: column; gap: 8px; }
.recent-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: var(--bg-body);
    border-radius: 10px; font-size: 0.82rem; border-left: 3px solid var(--accent);
    transition: background 0.15s, border-color 0.15s; }
.recent-item.selected { background: var(--accent-soft); border-left-color: var(--err); }
.recent-item input[type="checkbox"] {
    width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); flex-shrink: 0;
}
.recent-item .icon { width: 32px; height: 32px; border-radius: 8px; background: var(--accent-soft);
    display: flex; align-items: center; justify-content: center; color: var(--accent); flex-shrink: 0; }
.recent-item .body { flex: 1; min-width: 0; }
.recent-item .ref { font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.recent-item .meta { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }
.recent-item .age-badge { font-size: 0.65rem; font-weight: 800; padding: 2px 7px; border-radius: 8px;
    background: var(--accent-soft); color: var(--accent); }
.recent-item .age-badge.old { background: rgba(239,68,68,0.15); color: var(--err); }
.recent-item .act { color: var(--accent); text-decoration: none; font-weight: 700; }

.storage-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px;
    background: var(--bg-body); border-radius: 10px; font-size: 0.8rem; }
.storage-bar .fill { flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
.storage-bar .fill > span { display: block; height: 100%; background: linear-gradient(90deg, var(--accent), #a78bfa);
    border-radius: 3px; transition: width 0.3s; }

.toolbar-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 10px 14px;
    background: var(--bg-body); border-radius: 10px; }
.sel-info { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }

@media(max-width:900px) {
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; width: 100%; padding: 20px; }
}
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar">
    <div class="brand-box">
        <div class="brand-name">
            <i class="fa-solid fa-shield-halved"></i> IDCMS
        </div>
    </div>

    <nav>
        <a href="../letters/dashboard/dashboard.php" class="nav-btn">
            <i class="fa-solid fa-table-cells-large"></i> Home Page
        </a>

        <div class="section-label">Dispatching</div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('internalSub')">
                <i class="fa-solid fa-envelope-open-text"></i> Internal Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="internalSub" class="submenu">
                <a href="../letters/inward/add_internal_letter.php" class="sub-nav-btn">Add Internal</a>
                <a href="../letters/inward/internal_letters.php" class="sub-nav-btn">View Registry</a>
                <a href="../letters/inward/inbox.php" class="sub-nav-btn">My Inbox</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('taskSub')">
                <i class="fa-solid fa-list-check"></i> Task Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="taskSub" class="submenu">
                <a href="../letters/tasking/task_letters.php" class="sub-nav-btn">Add Task</a>
                <a href="../letters/tasking/task_list.php" class="sub-nav-btn">View Tasks</a>
            </div>
        </div>

        <div>
            <div class="nav-btn" onclick="toggleSubmenu('outwardSub')">
                <i class="fa-solid fa-paper-plane"></i> Outward Letters
                <i class="fa-solid fa-chevron-down small"></i>
            </div>
            <div id="outwardSub" class="submenu">
                <a href="../letters/outward/add_outward.php" class="sub-nav-btn">Add Outward</a>
                <a href="../letters/outward/outward_list.php" class="sub-nav-btn">View Outward</a>
            </div>
        </div>

        <div class="section-label">Records</div>

        <a href="../reports/linkage_report.php" class="nav-btn">
            <i class="fa-solid fa-diagram-project"></i> Linkage Report
        </a>

        <div class="section-label">Tools</div>

        <a href="pdf_extract.php" class="nav-btn active">
            <i class="fa-solid fa-file-pdf"></i> PDF to Text
        </a>

        <a href="../auth/logout.php" class="nav-btn mt-4" style="color:#ef4444;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </nav>
</aside>

<main class="main">
    <div class="mb-3">
        <div class="page-head">
            <h2><i class="fa-solid fa-file-pdf me-2" style="color:var(--accent);"></i>PDF to Text Extractor</h2>
            <p>Offline &middot; smart fallback: pdftotext &rarr; OCR (Tesseract Sinhala)</p>
        </div>
    </div>

    <!-- ═══ Cleanup notification ═══ -->
    <?php if ($manualCleanup): ?>
        <?php if (!empty($cleanupInfo['empty'])): ?>
            <div class="alert-box warn">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                No files were selected.
            </div>
        <?php elseif ($cleanupInfo['deleted'] > 0): ?>
            <div class="alert-box ok">
                <i class="fa-solid fa-circle-check me-2"></i>
                <strong>
                    <?php
                        if ($manualMode === 'all')           echo 'Deleted all:';
                        elseif ($manualMode === 'selected')  echo 'Deleted selected:';
                        else                                 echo 'Cleanup complete:';
                    ?>
                </strong>
                <strong><?= (int)$cleanupInfo['deleted'] ?></strong> file(s) deleted &middot;
                <?= number_format($cleanupInfo['freed'] / 1024, 1) ?> KB freed
                <?php if (!empty($cleanupInfo['errors'])): ?>
                    &middot; <span style="color:var(--err);"><?= (int)$cleanupInfo['errors'] ?> error(s)</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert-box info">
                <i class="fa-solid fa-circle-info me-2"></i>
                <?php if ($manualMode === 'old'): ?>
                    No files older than <strong><?= (int)$fileRetentionDays ?> days</strong> found.
                <?php else: ?>
                    Nothing to delete — folder is empty.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($cleanupInfo && $cleanupInfo['deleted'] > 0): ?>
        <div class="alert-box info">
            <i class="fa-solid fa-broom me-2"></i>
            Auto-cleanup: <strong><?= (int)$cleanupInfo['deleted'] ?></strong> file(s) older than
            <?= (int)$fileRetentionDays ?> days deleted &middot;
            <?= number_format($cleanupInfo['freed'] / 1024, 1) ?> KB freed
        </div>
    <?php endif; ?>

    <!-- ═══ Engines ═══ -->
    <div class="card-box">
        <div class="card-title"><i class="fa-solid fa-microchip"></i> Extraction Engines</div>
        <div class="engine-row">
            <span class="dot <?= $pdftotextBin ? 'ok' : 'err' ?>"></span>
            <strong>pdftotext</strong>
            <span class="note"><?= $pdftotextBin ? htmlspecialchars(basename($pdftotextBin)) : 'Not installed' ?></span>
        </div>
        <div class="engine-row">
            <span class="dot <?= $pdftoppmBin ? 'ok' : 'err' ?>"></span>
            <strong>pdftoppm</strong>
            <span class="note"><?= $pdftoppmBin ? 'Ready (for OCR)' : 'Not installed' ?></span>
        </div>
        <div class="engine-row">
            <span class="dot <?= $tesseractBin ? ($tesseractHasSinhala ? 'ok' : 'warn') : 'err' ?>"></span>
            <strong>Tesseract OCR</strong>
            <span class="note"><?= $tesseractBin ? ($tesseractHasSinhala ? 'Sinhala pack ready' : 'Missing Sinhala pack') : 'Not installed' ?></span>
        </div>
        <?php if (!$pdftotextBin && !$tesseractBin): ?>
            <div class="alert-box err" style="margin-top:12px; margin-bottom:0;">
                <strong>No engines available.</strong> Install poppler + Tesseract.
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══ Storage card ═══ -->
    <div class="card-box">
        <div class="card-title"><i class="fa-solid fa-broom"></i> Storage &amp; Cleanup</div>

        <div class="storage-bar mb-3">
            <i class="fa-solid fa-database" style="color:var(--accent);"></i>
            <span><strong><?= number_format($totalCount) ?></strong> files &middot;
                  <?= number_format($totalSizeMb, 2) ?> MB</span>
            <div class="fill"><span style="width:<?= min(100, $totalSizeMb) ?>%"></span></div>
        </div>

        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <span style="font-size:0.8rem;color:var(--text-muted);">
                <i class="fa-solid fa-clock me-1"></i>
                Auto-delete after <strong style="color:var(--accent);"><?= (int)$fileRetentionDays ?> days</strong>
                <?php if ($oldCount > 0): ?>
                    &middot; <span style="color:var(--err);font-weight:800;"><?= (int)$oldCount ?> old file(s) pending</span>
                <?php endif; ?>
            </span>
        </div>

        <div class="toolbar-row">
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete files older than <?= (int)$fileRetentionDays ?> days?');">
                <input type="hidden" name="action" value="cleanup_old">
                <button type="submit" class="btn-warn-c">
                    <i class="fa-solid fa-broom"></i> Clean old (&gt;<?= (int)$fileRetentionDays ?>d)
                </button>
            </form>
            <form method="post" id="deleteAllForm" style="display:inline;"
                  onsubmit="return confirm('⚠️ DELETE ALL FILES? This cannot be undone!');">
                <input type="hidden" name="action" value="delete_all">
                <button type="submit" class="btn-danger-c">
                    <i class="fa-solid fa-trash-can"></i> Delete all
                </button>
            </form>
        </div>
    </div>

    <!-- ═══ Upload ═══ -->
    <div class="card-box">
        <div class="card-title"><i class="fa-solid fa-cloud-arrow-up"></i> Upload PDF</div>

        <?php if ($error): ?>
            <div class="alert-box err"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert-box ok">
                <i class="fa-solid fa-circle-check me-2"></i>
                Extracted <strong><?= htmlspecialchars($result['original']) ?></strong>
                via <strong><?= htmlspecialchars($result['engine']) ?></strong>
                &middot; quality <strong><?= (int)$result['score'] ?>/100</strong>
            </div>
            <?php if (!empty($result['attempts'])): ?>
                <div style="font-size:0.72rem;color:var(--text-muted);margin:-8px 0 12px;">
                    <?= htmlspecialchars(implode(' &middot; ', $result['attempts'])) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="uploadForm" autocomplete="off">
            <input type="file" name="pdf_file" id="pdfFile" accept="application/pdf,.pdf" hidden>
            <div class="upload-zone" id="dropZone">
                <i class="fa-solid fa-file-pdf big"></i>
                <h5>Drop PDF here or click to browse</h5>
                <p>Max <?= round($maxFileSize / 1048576, 1) ?> MB &middot; Only <strong>.pdf</strong> files</p>
                <button type="button" class="btn-ghost-c" id="browseBtn">
                    <i class="fa-solid fa-folder-open"></i> Choose file
                </button>
                <div class="file-info" id="fileInfo" style="display:none;"></div>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn-primary-c" id="submitBtn" disabled>
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Extract Text
                </button>
                <a href="pdf_extract.php" class="btn-ghost-c"><i class="fa-solid fa-rotate"></i> Clear</a>
            </div>
        </form>
    </div>

    <!-- ═══ Extracted text ═══ -->
    <?php if ($result && $extractedText !== ''): ?>
    <div class="card-box">
        <div class="card-title"><i class="fa-solid fa-file-lines"></i> Extracted Text</div>
        <div class="stat-grid">
            <div class="stat"><div class="label">Quality</div><div class="value"><?= (int)$result['score'] ?>/100</div></div>
            <div class="stat"><div class="label">Chars</div><div class="value"><?= number_format($charCount) ?></div></div>
            <div class="stat"><div class="label">Words</div><div class="value"><?= number_format($wordCount) ?></div></div>
            <div class="stat"><div class="label">Lines</div><div class="value"><?= number_format($lineCount) ?></div></div>
            <div class="stat"><div class="label">Pages</div><div class="value"><?= number_format($pageCount) ?></div></div>
        </div>
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="pdf_extract_download.php?f=<?= urlencode($result['stored_txt']) ?>" class="btn-primary-c">
                <i class="fa-solid fa-download"></i> Download .txt
            </a>
            <a href="pdf_extract_download.php?f=<?= urlencode($result['stored_pdf']) ?>" class="btn-ghost-c">
                <i class="fa-solid fa-file-pdf"></i> Original PDF
            </a>
            <button type="button" class="btn-ghost-c" id="copyBtn">
                <i class="fa-solid fa-copy"></i> Copy
            </button>
            <button type="button" class="btn-ghost-c" id="wrapToggle">
                <i class="fa-solid fa-text-width"></i> Toggle wrap
            </button>
        </div>
        <div class="preview" id="previewBox" data-raw="<?= htmlspecialchars($extractedText, ENT_QUOTES) ?>"><?= htmlspecialchars($extractedText) ?></div>
    </div>
    <?php endif; ?>

    <!-- ═══ Recent files ═══ -->
    <?php if (!empty($recentFiles)): ?>
    <div class="card-box">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Recent Extractions
            <span style="margin-left:auto;font-size:0.7rem;color:var(--text-muted);text-transform:none;letter-spacing:0;">
                <?= count($recentFiles) ?> file(s) shown
            </span>
        </div>

        <form method="post" id="selForm">
            <input type="hidden" name="action" value="delete_selected">

            <div class="toolbar-row mb-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;margin:0;">
                    <input type="checkbox" id="selectAll" style="width:16px;height:16px;accent-color:var(--accent);">
                    <span style="font-weight:700;font-size:0.82rem;color:var(--text-main);">Select all</span>
                </label>
                <span class="sel-info" id="selInfo" style="margin-left:auto;">0 selected</span>
                <button type="submit" class="btn-danger-c" id="deleteSelBtn" disabled
                        onclick="return confirmDeleteSelected();">
                    <i class="fa-solid fa-trash-can"></i> Delete selected
                </button>
            </div>

            <div class="recent-list">
                <?php foreach ($recentFiles as $rf):
                    $isOld = ($rf['time'] > 0 && $rf['time'] < $oldCutoff);
                    $ageDays = floor((time() - $rf['time']) / 86400);
                ?>
                <label class="recent-item" data-key="<?= htmlspecialchars($rf['key']) ?>">
                    <input type="checkbox" name="targets[]" value="<?= htmlspecialchars($rf['key']) ?>" class="file-check">
                    <div class="icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="body">
                        <div class="ref"><?= htmlspecialchars($rf['txt']) ?></div>
                        <div class="meta">
                            <?= number_format($rf['size'] / 1024, 1) ?> KB &middot;
                            <?= date('d M Y H:i', $rf['time']) ?>
                            &middot;
                            <span class="age-badge <?= $isOld ? 'old' : '' ?>">
                                <?= $ageDays == 0 ? 'Today' : ($ageDays . 'd ago') ?>
                                <?= $isOld ? ' · OLD' : '' ?>
                            </span>
                        </div>
                    </div>
                    <a href="pdf_extract_download.php?f=<?= urlencode($rf['txt']) ?>"
                       class="act" title=".txt" onclick="event.stopPropagation();">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    <?php if (is_file($uploadDir . DIRECTORY_SEPARATOR . $rf['pdf'])): ?>
                        <a href="pdf_extract_download.php?f=<?= urlencode($rf['pdf']) ?>"
                           class="act" title="PDF" style="margin-left:10px;" onclick="event.stopPropagation();">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>
</main>

<script>
/* ─── Theme — read from localStorage (set by home page) ─── */
const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);

/* ─── Sidebar submenu toggle ─────────────────────── */
function toggleSubmenu(id) {
    var submenu = document.getElementById(id);
    if (!submenu) return;
    var btn = submenu.previousElementSibling;
    var icon = btn ? btn.querySelector('.fa-chevron-down') : null;

    if (submenu.classList.contains('show')) {
        submenu.classList.remove('show');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('show');
        if (icon) icon.style.transform = 'rotate(180deg)';
    }
}

/* ─── Upload ─────────────────────────────────────── */
var dropZone  = document.getElementById('dropZone');
var fileInput = document.getElementById('pdfFile');
var fileInfo  = document.getElementById('fileInfo');
var submitBtn = document.getElementById('submitBtn');
var form      = document.getElementById('uploadForm');
var MAX       = <?= (int)$maxFileSize ?>;

document.getElementById('browseBtn').addEventListener('click', function(e){
    e.stopPropagation(); fileInput.click();
});
dropZone.addEventListener('click', function(){ fileInput.click(); });
fileInput.addEventListener('change', function(){ showFile(fileInput.files[0]); });

['dragenter','dragover'].forEach(function(ev){
    dropZone.addEventListener(ev, function(e){ e.preventDefault(); dropZone.classList.add('drag'); });
});
['dragleave','drop'].forEach(function(ev){
    dropZone.addEventListener(ev, function(e){ e.preventDefault(); dropZone.classList.remove('drag'); });
});
dropZone.addEventListener('drop', function(e){
    var f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) { fileInput.files = e.dataTransfer.files; showFile(f); }
});

function showFile(f) {
    if (!f) return;
    if (f.type !== 'application/pdf' && !f.name.toLowerCase().endsWith('.pdf')) {
        fileInfo.style.display='block'; fileInfo.style.color='var(--err)';
        fileInfo.textContent='Only PDF files allowed.'; submitBtn.disabled=true; return;
    }
    if (f.size > MAX) {
        fileInfo.style.display='block'; fileInfo.style.color='var(--err)';
        fileInfo.textContent='File too large.'; submitBtn.disabled=true; return;
    }
    fileInfo.style.display='block'; fileInfo.style.color='var(--accent)';
    fileInfo.textContent = f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    submitBtn.disabled = false;
}

form.addEventListener('submit', function(){
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Extracting...';
});

/* ─── Checkbox selection ─────────────────────────── */
var selectAll   = document.getElementById('selectAll');
var selInfo     = document.getElementById('selInfo');
var deleteSelBtn= document.getElementById('deleteSelBtn');
var checks      = document.querySelectorAll('.file-check');

function refreshSelection() {
    var n = 0;
    checks.forEach(function(c){
        var item = c.closest('.recent-item');
        if (c.checked) { n++; item.classList.add('selected'); }
        else           { item.classList.remove('selected'); }
    });
    if (selInfo)     selInfo.textContent = n + ' selected';
    if (deleteSelBtn) deleteSelBtn.disabled = (n === 0);
    if (selectAll)   selectAll.checked = (n > 0 && n === checks.length);
    if (selectAll)   selectAll.indeterminate = (n > 0 && n < checks.length);
}

checks.forEach(function(c){ c.addEventListener('change', refreshSelection); });
if (selectAll) {
    selectAll.addEventListener('change', function(){
        checks.forEach(function(c){ c.checked = selectAll.checked; });
        refreshSelection();
    });
}
refreshSelection();

function confirmDeleteSelected() {
    var n = document.querySelectorAll('.file-check:checked').length;
    if (n === 0) { alert('No files selected.'); return false; }
    return confirm('Delete ' + n + ' selected file(s)? This cannot be undone.');
}

/* ─── Copy + wrap toggle ─────────────────────────── */
var copyBtn = document.getElementById('copyBtn');
if (copyBtn) copyBtn.addEventListener('click', function(){
    var raw = document.getElementById('previewBox').dataset.raw || '';
    navigator.clipboard.writeText(raw).then(function(){
        var b = document.getElementById('copyBtn');
        var orig = b.innerHTML;
        b.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(function(){ b.innerHTML = orig; }, 1600);
    }).catch(function(e){ alert('Copy failed: ' + e.message); });
});

var wrapOn = true;
var wrapBtn = document.getElementById('wrapToggle');
if (wrapBtn) wrapBtn.addEventListener('click', function(){
    var box = document.getElementById('previewBox');
    if (!box) return;
    wrapOn = !wrapOn;
    box.style.whiteSpace = wrapOn ? 'pre-wrap' : 'pre';
    box.style.overflowX  = wrapOn ? 'hidden'   : 'auto';
});
</script>
</body>
</html>