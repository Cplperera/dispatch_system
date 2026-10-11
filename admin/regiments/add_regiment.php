<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_REGIMENTS')){
    die('Access Denied!');
}

$error = $success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name = trim($_POST['regiment_name']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO regiments (regiment_name, address, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $address, $status);

    if($stmt->execute()){
        $success = "Regiment added successfully!";
    } else {
        $error = "Error: ".$stmt->error;
    }
}
?>

<h2>Add Regiment</h2>
<?php if($error) echo "<p style='color:red'>$error</p>"; ?>
<?php if($success) echo "<p style='color:green'>$success</p>"; ?>

<form method="post">
    Regiment Name: <input type="text" name="regiment_name" required><br><br>
    Address: <input type="text" name="address" required><br><br>
    Status:
    <select name="status">
        <option value="Active" selected>Active</option>
        <option value="Inactive">Inactive</option>
    </select><br><br>
    <button type="submit">Add Regiment</button>
</form>
