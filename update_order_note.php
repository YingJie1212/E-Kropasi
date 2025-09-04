
<?php
session_start();
require_once "../classes/DB.php";
if (!isset($_SESSION['admin_id'])) exit;

$order_id = intval($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($order_id > 0) {
    $pdo = (new DB())->getConnection();
    $stmt = $pdo->prepare("UPDATE orders SET note = ? WHERE id = ?");
    $stmt->execute([$note, $order_id]);
    echo "OK";
} else {
    echo "Invalid";
}

