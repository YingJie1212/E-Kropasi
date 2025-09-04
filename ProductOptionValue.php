<?php
require_once "DB.php";

class ProductOptionValue extends DB {
    public function add($group_id, $value) {
        $stmt = $this->conn->prepare("INSERT INTO product_option_values (group_id, value) VALUES (?, ?)");
        $stmt->execute([$group_id, $value]);
    }

    public function getByGroup($group_id) {
        $stmt = $this->conn->prepare("SELECT * FROM product_option_values WHERE group_id = ?");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByGroup($group_id) {
        $stmt = $this->conn->prepare("DELETE FROM product_option_values WHERE group_id = ?");
        return $stmt->execute([$group_id]);
    }
    
}
