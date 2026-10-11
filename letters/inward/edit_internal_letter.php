<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('EDIT_INTERNAL_LETTER')) {
    die("Access Denied!");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid Letter ID");
}

/* ================= FETCH LETTER ================= */
$stmt = $conn->prepare("SELECT * FROM internal_letters WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$letter = $result->fetch_assoc();

if (!$letter) {
    die("Letter not found");
}

/* ================= SECTION CONTROL ================= */
$isAdminSection = ($_SESSION['section'] ?? '') === 'Administration Section';
$isRegistry = !$isAdminSection;

/* ================= FETCH DATA ================= */
$regiments = [];
$res = $conn->query("SELECT id, regiment_name FROM regiments WHERE status='Active'");
while ($row = $res->fetch_assoc()) {
    $regiments[] = $row;
}

$categories = [];
if ($isRegistry) {
    $res = $conn->query("SELECT id, category FROM letter_categories WHERE status='Active'");
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

$informers = [];
if ($isRegistry) {
    $res = $conn->query("
        SELECT id, informer_name, informer_code, regiment_id
        FROM informers
        WHERE is_deleted = 0 AND status='Active'
    ");
    while ($row = $res->fetch_assoc()) {
        $informers[] = $row;
    }
}

$message = "";

/* ================= HANDLE UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $letter_reference        = $_POST['letter_reference'] ?? null;
    $letter_title            = trim($_POST['letter_title'] ?? '');
    $letter_date             = $_POST['letter_date'] ?? null;
    $regiment_id             = (int)($_POST['regiment_id'] ?? 0);
    $security_classification = $_POST['security_classification'] ?? 'Public';
    $about_letter            = $_POST['about_letter'] ?? null;
    $comments                = $_POST['comments'] ?? null;

    $informer_id = null;
    if ($isRegistry && isset($_POST['informer_id']) && $_POST['informer_id'] !== '') {
        $tempInformer = (int)$_POST['informer_id'];
        $check = $conn->prepare("SELECT id FROM informers WHERE id = ?");
        $check->bind_param("i", $tempInformer);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $informer_id = $tempInformer;
        }
    }

    $letter_category = null;
    $letter_classification = null;
    if ($isRegistry && isset($_POST['letter_category']) && $_POST['letter_category'] !== '') {
        $letter_category = (int)$_POST['letter_category'];
        if (isset($_POST['letter_classification']) && $_POST['letter_classification'] !== '') {
            $letter_classification = (int)$_POST['letter_classification'];
        }
    }

    $attachment = $letter['attachment'] ?? null;
    if (!empty($_FILES['attachment']['name'])) {
        $newFile = time() . '_' . basename($_FILES['attachment']['name']);
        $targetPath = '../../uploads/letters/' . $newFile;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
            if (!empty($attachment) && file_exists('../../uploads/letters/' . $attachment)) {
                unlink('../../uploads/letters/' . $attachment);
            }
            $attachment = $newFile;
        }
    }

    $update = $conn->prepare("
        UPDATE internal_letters SET
            letter_reference = ?, letter_title = ?, letter_date = ?, regiment_id = ?,
            category_id = ?, letter_classification = ?, security_classification = ?,
            about_letter = ?, comments = ?, Informer_id = ?, attachment = ?, updated_by = ?
        WHERE id = ?
    ");
    
    // bind_param හි අක්ෂර 13 ක් සඳහා දත්ත වර්ග (types) 13 ක් නිවැරදිව දක්වා ඇත
    $update->bind_param("sssiiisssssii", $letter_reference, $letter_title, $letter_date, $regiment_id, $letter_category, $letter_classification, $security_classification, $about_letter, $comments, $informer_id, $attachment, $_SESSION['user_id'], $id);

    if ($update->execute()) {
        $message = "Letter updated successfully.";
        $stmt = $conn->prepare("SELECT * FROM internal_letters WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $letter = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "Update failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Letter | LDMS</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F0F2F5; --sidebar-bg: #FFFFFF; --card-bg: #FFFFFF;
            --accent: #4318FF; --accent-soft: rgba(67, 24, 255, 0.08);
            --text-main: #1B2559; --text-muted: #707EAE; --border: #DDE2EF;
            --sidebar-width: 280px;
        }
        [data-theme="dark"] {
            --bg-body: #0B1437; --sidebar-bg: #111C44; --card-bg: #111C44;
            --accent: #7551FF; --accent-soft: rgba(117, 81, 255, 0.1);
            --text-main: #FFFFFF; --text-muted: #A3AED0; --border: rgba(255, 255, 255, 0.1);
        }

        body { background-color: var(--bg-body); color: var(--text-main); display: flex; min-height: 100vh; font-family: "Segoe UI", sans-serif; transition: 0.3s; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid var(--border); padding: 30px 15px; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .nav-btn { display: flex; align-items: center; padding: 12px 15px; color: var(--text-muted); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 0.85rem; transition: 0.2s; background: transparent; width: 100%; border: none; text-align: left; }
        .nav-btn:hover, .nav-btn.active { background: var(--accent-soft); color: var(--accent); }
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); display: flex; flex-direction: column; }
        .top-nav { position: sticky; top: 0; background: var(--bg-body); padding: 25px 30px; z-index: 90; }
        
        .form-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 30px; margin: 0 30px 30px 30px; }
        .form-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
        .form-control, .form-select { background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); border-radius: 12px; padding: 12px; font-weight: 600; }
        .form-control:focus, .form-select:focus { background: var(--bg-body); border-color: var(--accent); color: var(--text-main); box-shadow: none; }
        .form-control[readonly] { opacity: 0.6; cursor: not-allowed; }
        
        .section-divider { border-bottom: 1px solid var(--border); margin: 25px 0; display: flex; align-items: center; }
        .section-divider span { background: var(--card-bg); padding-right: 15px; font-size: 0.8rem; font-weight: 800; color: var(--accent); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="brand-box mb-4 px-2">
        <div class="brand-name"><i class="fa-solid fa-shield-halved text-primary"></i> LDMS <br><span style="font-size: 0.7rem; color: var(--text-muted);">EXECUTIVE TERMINAL</span></div>
    </div>
    <nav>
        <a href="../dashboard/dashboard.php" class="nav-btn"><i class="fa-solid fa-table-cells-large me-2"></i> Home Page</a>
        <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin: 20px 0 10px 10px; opacity: 0.7;">Dispatching</div>
        <a href="internal_letters.php" class="nav-btn active"><i class="fa-solid fa-envelope-open-text me-2"></i> Internal Registry</a>
        <a href="../../auth/logout.php" class="nav-btn mt-4 text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-nav d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold mb-0">Update Correspondence</h4>
            <small class="text-muted">Modify records for CDR: <b><?= htmlspecialchars($letter['cdr_number'] ?? '') ?></b></small>
        </div>
        <a href="internal_letters.php" class="btn btn-light border px-4 rounded-pill fw-bold small">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to Registry
        </a>
    </header>

    <div class="form-container">
        <?php if ($message): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center">
                <i class="fa-solid fa-circle-check fs-4 me-3"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">CDR Number (System)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($letter['cdr_number'] ?? '') ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Letter Reference No</label>
                    <input type="text" name="letter_reference" class="form-control" value="<?= htmlspecialchars($letter['letter_reference'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Correspondence Date</label>
                    <input type="date" name="letter_date" class="form-control" value="<?= htmlspecialchars($letter['letter_date'] ?? '') ?>" required>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Letter Subject / Title</label>
                    <input type="text" name="letter_title" class="form-control" value="<?= htmlspecialchars($letter['letter_title'] ?? '') ?>" required>
                </div>

                <div class="section-divider"><span><i class="fa-solid fa-sitemap me-2"></i>Routing & Classification</span></div>

                <div class="col-md-6">
                    <label class="form-label">Originating Regiment</label>
                    <select name="regiment_id" id="regiment_id" class="form-select" required>
                        <option value="">-- Select Regiment --</option>
                        <?php foreach ($regiments as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= ($letter['regiment_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['regiment_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Security Classification</label>
                    <select name="security_classification" class="form-select">
                        <?php $levels = ['Public','Restricted','Confidential','Secret','Top Secret'];
                        foreach ($levels as $level): ?>
                            <option <?= ($letter['security_classification'] ?? '') == $level ? 'selected' : '' ?>><?= $level ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isRegistry): ?>
                <div class="col-md-4">
                    <label class="form-label">Letter Category</label>
                    <select name="letter_category" id="letter_category" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" data-name="<?= $c['category'] ?>" <?= ($letter['letter_category'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['category']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4" id="classification_box" style="display:none;">
                    <label class="form-label">MISR Classification</label>
                    <select name="letter_classification" class="form-select">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= ($letter['letter_classification'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4" id="informer_box" style="display:none;">
                    <label class="form-label">Assigned Informer</label>
                    <select name="informer_id" id="informer_id" class="form-select">
                        <option value="">-- Select Informer --</option>
                        <?php foreach ($informers as $inf): ?>
                        <option value="<?= $inf['id'] ?>" data-regiment="<?= $inf['regiment_id'] ?>" <?= ($letter['informer_id'] ?? '') == $inf['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inf['informer_name'] . ' [' . $inf['informer_code'] . ']') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="section-divider"><span><i class="fa-solid fa-file-lines me-2"></i>Content & Evidence</span></div>

                <div class="col-md-6">
                    <label class="form-label">About Letter / Description</label>
                    <textarea name="about_letter" class="form-control" rows="4"><?= htmlspecialchars($letter['about_letter'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Internal Comments</label>
                    <textarea name="comments" class="form-control" rows="4"><?= htmlspecialchars($letter['comments'] ?? '') ?></textarea>
                </div>

                <div class="col-md-12 mt-4">
                    <div class="p-4 border rounded-4 bg-light bg-opacity-10 d-flex align-items-center justify-content-between">
                        <div>
                            <label class="form-label d-block mb-1">Document Attachment</label>
                            <input type="file" name="attachment" class="form-control-file small">
                        </div>
                        <?php if (!empty($letter['attachment'])): ?>
                        <a href="../../uploads/letters/<?= urlencode($letter['attachment']) ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                            <i class="fa-solid fa-file-pdf me-2"></i>View Current Attachment
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-12 text-end mt-4">
                    <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Updated Record
                    </button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
/* Theme Loader */
const savedTheme = localStorage.getItem('dispatch_theme') || 'dark';
document.documentElement.setAttribute('data-theme', savedTheme);

/* Functional Logic */
const category = document.getElementById('letter_category');
const classBox = document.getElementById('classification_box');
const informerBox = document.getElementById('informer_box');
const regiment = document.getElementById('regiment_id');
const informer = document.getElementById('informer_id');

function filterInformers() {
    if (!informer) return;
    const reg = regiment.value;
    Array.from(informer.options).forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (opt.dataset.regiment === reg) ? 'block' : 'none';
    });
}

function initForm() {
    if (!category) return;
    const selected = category.selectedOptions[0]?.dataset.name;
    if (selected === 'MISR') {
        classBox.style.display = 'block';
        informerBox.style.display = 'block';
        filterInformers();
    }
}

if (category) {
    category.addEventListener('change', function() {
        const selected = this.selectedOptions[0]?.dataset.name;
        if (selected === 'MISR') {
            classBox.style.display = 'block';
            informerBox.style.display = 'block';
            filterInformers();
        } else {
            classBox.style.display = 'none';
            informerBox.style.display = 'none';
            if (informer) informer.value = '';
        }
    });
}

if (regiment) {
    regiment.addEventListener('change', filterInformers);
}

window.addEventListener('load', initForm);
</script>
</body>
</html>