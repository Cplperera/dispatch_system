<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

//if (!hasPermission('EDIT_PERMISSION_CODE')) {
   // die('Access Denied');
//}

/* ---------- Validate ID ---------- */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid Permission ID');
}

$id = (int)$_GET['id'];

/* ---------- Fetch permission ---------- */
$stmt = $conn->prepare(
    "SELECT code, description, category, status 
     FROM permission_codes 
     WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$permission = $result->fetch_assoc();

/* ---------- If not found ---------- */
if (!$permission) {
    die('Permission not found');
}

/* ---------- Update ---------- */
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code        = trim($_POST['code']);
    $description = trim($_POST['description']);
    $category    = trim($_POST['category']);
    $status      = $_POST['status'];

    if ($code === '' || $description === '') {
        $error = 'Code and Description are required.';
    } else {

        $update = $conn->prepare(
            "UPDATE permission_codes
             SET code = ?, description = ?, category = ?, status = ?
             WHERE id = ?"
        );
        $update->bind_param(
            "ssssi",
            $code,
            $description,
            $category,
            $status,
            $id
        );

        if ($update->execute()) {
            $success = 'Permission updated successfully!';
            $permission['code']        = $code;
            $permission['description'] = $description;
            $permission['category']    = $category;
            $permission['status']      = $status;
        } else {
            $error = 'Update failed.';
        }
    }
}
?>

<h2>Edit Permission Code</h2>

<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p style="color:green;"><?php echo $success; ?></p>
<?php endif; ?>

<form method="post">

    <label>Code</label><br>
    <input type="text" name="code"
        value="<?php echo htmlspecialchars($permission['code']); ?>" required>
    <br><br>

    <label>Description</label><br>
    <input type="text" name="description"
        value="<?php echo htmlspecialchars($permission['description']); ?>" required>
    <br><br>

    <label>Category</label><br>
    <input type="text" name="category"
        value="<?php echo htmlspecialchars($permission['category']); ?>">
    <br><br>

    <label>Status</label><br>
    <select name="status">
        <option value="Active"   <?= $permission['status']=='Active'?'selected':'' ?>>Active</option>
        <option value="Inactive" <?= $permission['status']=='Inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <br><br>

    <button type="submit">Update Permission</button>

</form>
