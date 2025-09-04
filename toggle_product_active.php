<?php
error_log("product_id: " . $_POST['product_id']);
error_log("is_active: " . $_POST['is_active']);

session_start();
header('Content-Type: application/json');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../classes/Product.php";

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? 0);
$is_active = intval($_POST['is_active'] ?? 0);

error_log("Toggle request - Product ID: $product_id, Active: $is_active");

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $product = new Product();

    // Use the new method instead of accessing $conn directly
    $executed = $product->setActiveStatus($product_id, $is_active);

    if ($executed) {
        // Check if the row was actually updated
        // (Optional: you can add rowCount logic in setActiveStatus if needed)
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }

} catch (Exception $e) {
    error_log("Error in toggle_product_status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Update failed',
        'error' => $e->getMessage()
    ]);
}