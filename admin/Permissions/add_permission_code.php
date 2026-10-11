<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';

checkSessionTimeout();

/* Page permission */
if (!hasPermission('ADD_PERMISSION_CODE')) {
    die('Access Denied');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code        = trim($_POST['code']);
    $description = trim($_POST['description']);
    $category    = trim($_POST['category']);
    $status      = 'Active';

    if ($code === '' || $description === '') {
        $error = 'Code and Description are required.';
    } else {

        /* Check duplicate code */
        $check = $conn->prepare(
            "SELECT id FROM permission_codes WHERE code = ?"
        );
        $check->bind_param("s", $code);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Permission code already exists.';
        } else {

            /* Insert permission */
            $stmt = $conn->prepare(
                "INSERT INTO permission_codes (code, description, category, status)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssss",
                $code,
                $description,
                $category,
                $status
            );

            if ($stmt->execute()) {
                $success = 'Permission added successfully!';
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    }
}
?>

<h2>Add Permission Code</h2>

<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <p style="color:green;"><?php echo $success; ?></p>
<?php endif; ?>

<form method="post">
    <label>Permission Code</label><br>
    <input type="text" name="code" required placeholder="e.g. VIEW_USERS"><br><br>

    <label>Description</label><br>
    <input type="text" name="description" required><br><br>

    <label>Category</label><br>
    <input type="text" name="category" placeholder="User Management"><br><br>

    <button type="submit">Add Permission</button>
</form>
