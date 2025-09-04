<?php
session_start();
header('Content-Type: application/json');
require_once "../classes/Product.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);

if ($product_id > 0 && $quantity >= 0) {
    $product = new Product();
    if ($product->updateQuantity($product_id, $quantity)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update quantity']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity']);
}