<?php
session_start();
require_once "../classes/DB.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$db = new DB();
$conn = $db->getConnection();

// Fetch activity logs (latest 100)
$stmt = $conn->prepare("
    SELECT l.*, a.name AS admin_name
    FROM activity_logs l
    LEFT JOIN users a ON l.admin_id = a.id
    ORDER BY l.created_at DESC
    LIMIT 100
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Logs</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; }
        th { background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>Activity Logs</h1>
    <table>
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['admin_name'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['details']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>



