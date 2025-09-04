<?php
require_once "DB.php";

class Category extends DB {
    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM categories ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function add($name, $description = null) {
        $stmt = $this->conn->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
        return $stmt->execute([$name, $description]);
    }

    // Single update method
    public function update($id, $name, $description = null) {
        $stmt = $this->conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function existsByName($name, $excludeId = null) {
        $sql = "SELECT id FROM categories WHERE name = ?";
        $params = [$name];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function searchByName($keyword) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM categories 
             WHERE name LIKE :kw 
                OR id LIKE :kw 
                OR created_at LIKE :kw
             ORDER BY created_at DESC"
        );
        $stmt->execute(['kw' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    
}
