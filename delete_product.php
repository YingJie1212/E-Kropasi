<?php
require_once "../classes/Product.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/DB.php";
require_once "../classes/Package.php";
require_once "../classes/OrderManager.php";
session_start();

try {
    // Access control
    if (!isset($_SESSION['admin_id'])) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }
        header("Location: ../login.php");
        exit;
    }

    // Accept both GET and POST for AJAX
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    if (!isset($id) || !is_numeric($id)) {
        if (isset($_POST['ajax'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        header("Location: view_products.php?error=invalid_id");
        exit;
    }

    $productId = (int)$id;

    $product = new Product();
    $group = new ProductOptionGroup();
    $value = new ProductOptionValue();
    $db = new DB();
    $pdo = $db->getConnection();
    $package = new Package();
    $orderManager = new OrderManager();
    $conn = $orderManager->getConnection();

    // Check for active (non-completed, non-canceled) orders
    $stmt = $conn->prepare("
        SELECT o.id
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        WHERE oi.product_id = ? AND o.status NOT IN ('Completed', 'Cancelled')
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $activeOrder = $stmt->fetch();

    if ($activeOrder) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete: There are active orders for this product.'
        ]);
        exit;
    }

    // Step 0: Delete packages that contain only this product
    $stmt = $pdo->prepare("
        SELECT pp.package_id
        FROM product_packages pp
        WHERE pp.product_id = ?
        GROUP BY pp.package_id
        HAVING COUNT(*) = 1
    ");
    $stmt->execute([$productId]);
    $packagesToDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($packagesToDelete as $pkgId) {
        $package->delete($pkgId);
    }

    // Step 1: Delete option values and their groups
    $groups = $group->getByProduct($productId);
    foreach ($groups as $g) {
        $value->deleteByGroup($g['id']);
        $group->delete($g['id']);
    }

    // Step 2: Delete product image if it exists
    $productData = $product->getById($productId);
    if ($productData && !empty($productData['image'])) {
        $imagePath = "../uploads/" . $productData['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    // Step 2.6: Delete product-package relationships
    $stmt = $pdo->prepare("DELETE FROM product_packages WHERE product_id = ?");
    $stmt->execute([$productId]);

    // Step 3: Mark the product as deleted
    $stmt = $pdo->prepare("UPDATE products SET is_active = 2 WHERE id = ?");
    $stmt->execute([$productId]);

    // Return JSON if AJAX
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => true]);
        exit;
    }

    // Redirect otherwise
    header("Location: view_products.php?deleted=1");
    exit;

} catch (Exception $e) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    // Redirect with error if not AJAX
    header("Location: view_products.php?error=delete_failed");
    exit;
}

