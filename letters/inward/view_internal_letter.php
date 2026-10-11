<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if (!hasPermission('VIEW_INTERNAL_LETTERS')) {
    die("Access Denied!");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Letter ID");
}

$letterId = intval($_GET['id']);

/* ===============================
   FETCH FULL LETTER DETAILS
=================================*/
$sql = "
SELECT il.*, 
       lc.category AS letter_category_name,
       r.regiment_name,
       u.username AS registered_by_user,
       u.section AS user_section,
       i.informer_name, 
       i.informer_code
FROM internal_letters il
LEFT JOIN regiments r ON il.regiment_id = r.id
LEFT JOIN users u ON il.registered_by = u.id
LEFT JOIN informers i ON il.informer_id = i.id
LEFT JOIN letter_categories lc ON il.letter_category = lc.id
WHERE il.id = ? AND il.is_deleted = 0
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $letterId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Letter Not Found");
}

$letter = $result->fetch_assoc();

/* ===============================
   FETCH FORWARDING HISTORY
=================================*/
$forwardSql = "
SELECT 
    f.forwarded_at,
    f.seen,
    f.comments,
    u_to.name_with_initial AS forwarded_to_name,
    u_from.name_with_initial AS forwarded_by_name
FROM internal_letters_forwarding f
JOIN users u_to ON f.forwarded_to = u_to.id
JOIN users u_from ON f.forwarded_by = u_from.id
WHERE f.letter_id = ?
ORDER BY f.forwarded_at DESC
";

$fStmt = $conn->prepare($forwardSql);
$fStmt->bind_param("i", $letterId);
$fStmt->execute();
$forwardResult = $fStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Internal Letter Details</title>
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#0B1437; color:#fff; padding:30px; }
.card-box {
    background:#111C44;
    padding:30px;
    border-radius:20px;
}
.label { color:#A3AED0; font-size:0.8rem; text-transform:uppercase; }
.value { font-size:1rem; font-weight:600; margin-bottom:15px; }
.forward-box {
    background:rgba(255,255,255,0.05);
    padding:15px;
    border-radius:10px;
    margin-bottom:10px;
}
</style>
</head>
<body>

<div class="card-box">

<h3>Internal Letter Full Details</h3>
<hr>

<div class="row">

<div class="col-md-6">
    <div class="label">CDR Number</div>
    <div class="value"><?= htmlspecialchars($letter['cdr_number']) ?></div>

    <div class="label">Reference</div>
    <div class="value"><?= htmlspecialchars($letter['letter_reference']) ?></div>

    <div class="label">Title</div>
    <div class="value"><?= htmlspecialchars($letter['letter_title']) ?></div>

    <div class="label">Category</div>
    <div class="value"><?= htmlspecialchars($letter['letter_category_name']) ?></div>

    <div class="label">Letter Date</div>
    <div class="value"><?= htmlspecialchars($letter['letter_date']) ?></div>
</div>

<div class="col-md-6">
    <div class="label">Regiment</div>
    <div class="value"><?= htmlspecialchars($letter['regiment_name']) ?></div>

    <div class="label">Informer</div>
    <div class="value">
        <?= $letter['informer_name'] 
            ? htmlspecialchars($letter['informer_name'] . " (" . $letter['informer_code'] . ")")
            : "No Informer"; ?>
    </div>

    <div class="label">Security Classification</div>
    <div class="value"><?= htmlspecialchars($letter['security_classification']) ?></div>

    <div class="label">Status</div>
    <div class="value"><?= htmlspecialchars($letter['status']) ?></div>

    <div class="label">Registered By</div>
    <div class="value">
        <?= htmlspecialchars($letter['registered_by_user']) ?> 
        (<?= htmlspecialchars($letter['user_section']) ?>)
    </div>
</div>

</div>

<hr>

<div class="label">Attachment</div>
<div class="value">
<?php if ($letter['attachment']): ?>
    <a href="../../uploads/letters/<?= urlencode($letter['attachment']) ?>" target="_blank" class="btn btn-danger btn-sm">
        View Attachment
    </a>
<?php else: ?>
    No Attachment
<?php endif; ?>
</div>

<hr>

<h4>Forwarding History</h4>

<?php if ($forwardResult->num_rows > 0): ?>
    <?php while ($fRow = $forwardResult->fetch_assoc()): ?>
        <div class="forward-box">
            <strong><?= htmlspecialchars($fRow['forwarded_to_name']) ?></strong>
            <div style="font-size:0.85rem;">
                Forwarded By: <?= htmlspecialchars($fRow['forwarded_by_name']) ?><br>
                Date: <?= date('d M Y H:i', strtotime($fRow['forwarded_at'])) ?><br>
                Status: <?= $fRow['seen'] ? 'Seen' : 'Not Seen' ?>
            </div>
            <?php if ($fRow['comments']): ?>
                <div style="margin-top:5px;">
                    <strong>Comments:</strong><br>
                    <?= nl2br(htmlspecialchars($fRow['comments'])) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No forwarding history.</p>
<?php endif; ?>

<br>
<a href="internal_letters.php" class="btn btn-secondary">← Back</a>

</div>

</body>
</html>
