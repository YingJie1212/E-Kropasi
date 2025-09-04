<?php
require_once "../classes/Product.php";
require_once "../classes/DB.php";
session_start();

try {
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }

    $imageId = $_POST['image_id'] ?? null;
    if (!$imageId) {
        throw new Exception('No image ID');
    }

    $db = new DB();
    $pdo = $db->getConnection();

    // Get image info
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        throw new Exception('Image not found');
    }

    $productId = $image['product_id'];
    $imagePath = $image['image'];

    // Remove from product_images table
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);

    // Remove file
    if ($imagePath && file_exists("../uploads/" . $imagePath)) {
        if (!unlink("../uploads/" . $imagePath)) {
            throw new Exception('Failed to delete file');
        }
    }

    // Check if this was the main image
    $product = new Product();
    $productData = $product->getById($productId);

    if ($productData && $productData['image'] === $imagePath) {
        // Get the next image for this product
        $stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$productId]);
        $nextImage = $stmt->fetchColumn();

        // Update the main image in products table
        $product->updateImage($productId, $nextImage ?: null);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;