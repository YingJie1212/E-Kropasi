<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$db = new DB();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    $newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : 'Completed';

    if ($orderId) {
        // If marking as completed, minus product quantities
        if ($newStatus === 'Completed') {
            // 1. Minus for individual products
            $stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $stmtProd = $conn->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
                $stmtProd->execute([$item['quantity'], $item['product_id']]);
            }

            // 2. Minus for packages (affecting products inside the package)
            $stmt = $conn->prepare("SELECT package_id, quantity FROM order_items WHERE order_id = ? AND package_id IS NOT NULL");
            $stmt->execute([$orderId]);
            $packageItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($packageItems as $pkgItem) {
                $packageId = $pkgItem['package_id'];
                $packageQty = $pkgItem['quantity'];

                // Get all products in this package
                $stmtPkg = $conn->prepare("SELECT product_id, quantity FROM product_packages WHERE package_id = ?");
                $stmtPkg->execute([$packageId]);
                $productsInPackage = $stmtPkg->fetchAll(PDO::FETCH_ASSOC);

                foreach ($productsInPackage as $prodInPkg) {
                    $prodId = $prodInPkg['product_id'];
                    $prodQtyInPkg = $prodInPkg['quantity'];
                    // Total to minus = package quantity ordered * product quantity in package
                    $totalMinus = $packageQty * $prodQtyInPkg;
                    $stmtProd = $conn->prepare("UPDATE products SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?");
                    $stmtProd->execute([$totalMinus, $prodId]);
                }
            }
        }

        // Update order status and status_updated_at
$stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE id = ?");
$stmt->execute([$newStatus, $orderId]);
    }

    header("Location: order_details.php?id=" . intval($orderId));
    exit;
}
?>
