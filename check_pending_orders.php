<?php
// check_pending_orders.php

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

// Include necessary files
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";

try {
    $orderManager = new OrderManager();
    
    // Count both Pending and Shipping orders
    $pendingCount = $orderManager->countOrdersByStatus('Pending');
    $shippingCount = $orderManager->countOrdersByStatus('Shipping');
    
    $totalCount = (int)$pendingCount + (int)$shippingCount;
    
    echo json_encode([
        'success' => true,
        'count' => $totalCount,
        'pending_count' => (int)$pendingCount,
        'shipping_count' => (int)$shippingCount
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'pending_count' => 0,
        'shipping_count' => 0,
        'error' => 'Unable to fetch order count'
    ]);
}