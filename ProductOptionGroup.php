<?php
require_once "DB.php";

// File: classes/ProductOptionGroup.php
class ProductOptionGroup {
    private $conn;

    public function __construct() {
        require_once "DB.php";
        $db = new Db();
        $this->conn = $db->getConnection();
    }

    // Add this method
    public function getByProduct($productId) {
        $stmt = $this->conn->prepare("SELECT * FROM product_option_groups WHERE product_id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function add($productId, $name) {
        $stmt = $this->conn->prepare("INSERT INTO product_option_groups (product_id, name) VALUES (?, ?)");
        $stmt->execute([$productId, $name]);
        return $this->conn->lastInsertId();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM product_option_groups WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
}
