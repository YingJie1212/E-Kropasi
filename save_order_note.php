<?php
session_start();
require_once "../classes/DB.php";
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}
$order_id = intval($_POST['order_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
if ($order_id > 0) {
    $pdo = (new DB())->getConnection();
    $stmt = $pdo->prepare("UPDATE orders SET note = ? WHERE id = ?");
    if ($stmt->execute([$note, $order_id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
}