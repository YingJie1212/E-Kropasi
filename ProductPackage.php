<?php
require_once "DB.php";

class ProductPackage extends DB {
    /**
     * Add a product to a package with a quantity
     */
    public function add(int $package_id, int $product_id, int $quantity): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO product_packages (package_id, product_id, quantity) 
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$package_id, $product_id, $quantity]);
    }

    /**
     * Delete all product entries for a package
     */
    public function deleteByPackageId(int $package_id): bool {
        $stmt = $this->conn->prepare("DELETE FROM product_packages WHERE package_id = ?");
        return $stmt->execute([$package_id]);
    }

    /**
     * Get all raw entries for a package (product_id, quantity)
     */
    public function getByPackageId(int $package_id): array {
        $stmt = $this->conn->prepare("SELECT * FROM product_packages WHERE package_id = ?");
        $stmt->execute([$package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed products (name, quantity) included in a package
     */
    public function getProductsInPackage($packageId) {
        $stmt = $this->conn->prepare("
            SELECT p.name, pp.quantity 
            FROM product_packages pp
            JOIN products p ON pp.product_id = p.id
            WHERE pp.package_id = ?
        ");
        $stmt->execute([$packageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPackageIdsByProductId($productId) {
    $stmt = $this->conn->prepare("SELECT package_id FROM product_packages WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
}
