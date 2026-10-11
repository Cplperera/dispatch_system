<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_REGIMENTS')){
    die('Access Denied!');
}

$id = (int)($_GET['id'] ?? 0);
$res = $conn->query("SELECT * FROM regiments WHERE id=$id AND is_deleted=0");
if($res->num_rows==0){
    die("Regiment not found.");
}

$row = $res->fetch_assoc();
$error = $success = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $name = trim($_POST['regiment_name']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE regiments SET regiment_name=?, address=?, status=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $address, $status, $id);

    if($stmt->execute()){
        $success = "Regiment updated successfully!";
        $row['regiment_name'] = $name;
        $row['address'] = $address;
        $row['status'] = $status;
    } else {
        $error = "Error: ".$stmt->error;
    }
}
?>

<h2>Edit Regiment</h2>
<?php if($error) echo "<p style='color:red'>$error</p>"; ?>
<?php if($success) echo "<p style='color:green'>$success</p>"; ?>

<form method="post">
    Regiment Name: <input type="text" name="regiment_name" value="<?= htmlspecialchars($row['regiment_name']) ?>" required><br><br>
    Address: <input type="text" name="address" value="<?= htmlspecialchars($row['address']) ?>" required><br><br>
    Status:
    <select name="status">
        <option value="Active" <?= $row['status']=='Active'?'selected':'' ?>>Active</option>
        <option value="Inactive" <?= $row['status']=='Inactive'?'selected':'' ?>>Inactive</option>
    </select><br><br>
    <button type="submit">Update Regiment</button>
</form>
