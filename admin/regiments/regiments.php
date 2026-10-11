<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

// Permission check
if(!hasPermission('MANAGE_REGIMENTS')){
    die('Access Denied!');
}

// Handle soft delete
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("UPDATE regiments SET is_deleted=1 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: regiments.php");
    exit;
}

// Fetch all regiments
$result = $conn->query("SELECT * FROM regiments WHERE is_deleted=0 ORDER BY id DESC");
?>

<h2>Regiments Management</h2>
<a href="add_regiment.php">➕ Add New Regiment</a><br><br>

<table border="1" cellpadding="6" cellspacing="0">
<tr>
    <th>ID</th>
    <th>Regiment</th>
    <th>Address</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['regiment_name']) ?></td>
    <td><?= htmlspecialchars($row['address']) ?></td>
    <td><?= $row['status'] ?></td>
    <td>
        <a href="edit_regiment.php?id=<?= $row['id'] ?>">Edit</a> | 
        <a href="regiments.php?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure to delete this regiment?')">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
