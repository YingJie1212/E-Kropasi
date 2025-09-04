<?php
require_once "DB.php";

class ProductOption extends DB {
    public function add($product_id, $option_name, $option_value) {
        $stmt = $this->conn->prepare("
            INSERT INTO product_options (product_id, option_name, option_value) VALUES (?, ?, ?)
        ");
        return $stmt->execute([$product_id, $option_name, $option_value]);
    }

    public function deleteByProductId($product_id) {
        $stmt = $this->conn->prepare("DELETE FROM product_options WHERE product_id = ?");
        return $stmt->execute([$product_id]);
    }

    public function getByProductId($product_id) {
        $stmt = $this->conn->prepare("SELECT * FROM product_options WHERE product_id = ?");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
