<?php
require_once '../../includes/auth.php';
require_once '../../config/db.php';
checkSessionTimeout();

if(!hasPermission('MANAGE_PERMISSIONS')) {
    echo "Access Denied!";
    exit;
}

// Fetch all permission codes
$result = $conn->query("SELECT * FROM permission_codes");

?>

<h2>Permission Codes</h2>
<a href="add_permission_code.php">Add Permission Code</a>

<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>Code</th>
    <th>Description</th>
    <th>Category</th>
    <th>Status</th>
    <th>Actions</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['code']; ?></td>
    <td><?php echo $row['description']; ?></td>
    <td><?php echo $row['category']; ?></td>
    <td><?php echo $row['status']; ?></td>
    <td>
        <a href="edit_permission_code.php?id=<?php echo $row['id']; ?>">Edit</a> |
        <a href="delete_permission_code.php?id=<?php echo $row['id']; ?>">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
