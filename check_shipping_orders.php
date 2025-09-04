
<?php
session_start();
require_once "../classes/OrderManager.php";
header('Content-Type: application/json');
$count = 0;
if (isset($_SESSION['user_id'])) {
    $orderManager = new OrderManager();
    $shippingOrders = $orderManager->getOrdersByUserAndStatus($_SESSION['user_id'], 'Shipping');
    if (is_array($shippingOrders)) {
        $count = count($shippingOrders);
    }
}
echo json_encode(['count' => $count]);