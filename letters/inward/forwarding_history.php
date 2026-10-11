<?php
$letter_id = intval($_GET['letter_id']);
$query = $conn->prepare("
    SELECT lf.forwarded_at, u1.name_with_initial AS from_user, u2.name_with_initial AS to_user, lf.comments, lf.seen
    FROM letter_forwarding lf
    JOIN users u1 ON lf.forwarded_by = u1.id
    JOIN users u2 ON lf.forwarded_to = u2.id
    WHERE lf.letter_id = ?
    ORDER BY lf.forwarded_at DESC
");
$query->bind_param("i", $letter_id);
$query->execute();
$result = $query->get_result();
?>

<h3>Forwarding History</h3>
<table border="1">
<tr>
    <th>Forwarded At</th>
    <th>From</th>
    <th>To</th>
    <th>Comments</th>
    <th>Status</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?php echo $row['forwarded_at']; ?></td>
    <td><?php echo htmlspecialchars($row['from_user']); ?></td>
    <td><?php echo htmlspecialchars($row['to_user']); ?></td>
    <td><?php echo htmlspecialchars($row['comments']); ?></td>
    <td><?php echo $row['seen'] ? 'Seen' : 'Not Seen'; ?></td>
</tr>
<?php endwhile; ?>
</table>
