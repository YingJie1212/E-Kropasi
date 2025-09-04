<?php
require_once "DB.php";

class Product extends DB {

    public function add($name, $description, $price, $image, $category_id, $quantity) {
        $stmt = $this->conn->prepare("
            INSERT INTO products (name, description, price, image, category_id, quantity, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $description, $price, $image, $category_id, $quantity]);
        return $this->conn->lastInsertId();
    }

    public function update($id, $name, $desc, $price, $image, $category_id, $quantity) {
        $stmt = $this->conn->prepare("
            UPDATE products 
            SET name = ?, description = ?, price = ?, image = ?, category_id = ?, quantity = ?
            WHERE id = ?
        ");
        return $stmt->execute([$name, $desc, $price, $image, $category_id, $quantity, $id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // This is the preferred getAll with categories joined
    public function getAll() {
        $stmt = $this->conn->prepare("
            SELECT 
                products.*, 
                categories.name AS category_name 
            FROM 
                products 
            LEFT JOIN categories ON products.category_id = categories.id 
            ORDER BY products.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id) {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOptionsByProductId($product_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                g.id AS group_id, 
                g.name AS group_name, 
                v.id AS value_id, 
                v.value AS value_name
            FROM product_option_groups g
            LEFT JOIN product_option_values v ON v.group_id = g.id
            WHERE g.product_id = ?
        ");
        $stmt->execute([$product_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        foreach ($rows as $row) {
            $groupId = $row['group_id'];
            if (!isset($options[$groupId])) {
                $options[$groupId] = [
                    'group_name' => $row['group_name'],
                    'values' => []
                ];
            }
            if ($row['value_id']) {
                $options[$groupId]['values'][] = $row['value_name'];
            }
        }

        return $options;
    }

    public function search($keyword) {
        $stmt = $this->conn->prepare("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.name LIKE :kw 
               OR p.description LIKE :kw 
               OR c.name LIKE :kw
               OR p.id LIKE :kw
               OR p.price LIKE :kw
               OR p.quantity LIKE :kw
            ORDER BY p.created_at DESC
        ");
        $stmt->execute(['kw' => '%' . $keyword . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllProductsOnly() {
        $stmt = $this->conn->prepare("SELECT * FROM products WHERE type = 'product'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllPackages() {
        $stmt = $this->conn->prepare("SELECT * FROM packages");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllProducts() {
    $stmt = $this->conn->prepare("SELECT * FROM products ORDER BY name ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function searchPaginated($search, $offset, $limit) {
    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.name LIKE :search OR p.description LIKE :search
            ORDER BY p.created_at DESC
            LIMIT :offset, :limit";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countSearch($search) {
    $sql = "SELECT COUNT(*) FROM products WHERE name LIKE :search OR description LIKE :search";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn();
}

public function getPaginated($offset, $limit) {
    $sql = "SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.created_at DESC
            LIMIT :offset, :limit";
    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addProductImage($productId, $imageName) {
    $stmt = $this->conn->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
    return $stmt->execute([$productId, $imageName]);
}

public function getImages($productId) {
    $stmt = $this->conn->prepare("SELECT image FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

public function getImagesByProductId($productId) {
    $stmt = $this->conn->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getProductImageById($imageId) {
    $stmt = $this->conn->prepare("SELECT * FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function deleteProductImageById($imageId) {
    $stmt = $this->conn->prepare("DELETE FROM product_images WHERE id = ?");
    return $stmt->execute([$imageId]);
}

public function getAllWithFirstImage() {
    $stmt = $this->conn->prepare("
        SELECT 
            p.*, 
            c.name AS category_name,
            pi.image AS first_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN (
            SELECT product_id, MIN(id) as min_id
            FROM product_images
            GROUP BY product_id
        ) pim ON pim.product_id = p.id
        LEFT JOIN product_images pi ON pi.id = pim.min_id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateImage($productId, $imageName) {
    $stmt = $this->db->prepare("UPDATE products SET image = ? WHERE id = ?");
    $stmt->execute([$imageName, $productId]);
}

public function countLowStock($threshold = 5) {
    $stmt = $this->conn->prepare("SELECT COUNT(*) FROM products WHERE quantity <= ? AND quantity > 0 AND is_active = 1");
    $stmt->execute([$threshold]);
    return (int)$stmt->fetchColumn();
}

public function updateQuantity($product_id, $quantity) {
    $stmt = $this->conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
    return $stmt->execute([$quantity, $product_id]);
}

public function setActiveStatus($product_id, $is_active) {
    $stmt = $this->conn->prepare("UPDATE products SET is_active = ? WHERE id = ?");
    return $stmt->execute([$is_active, $product_id]);
}

public function getActiveProductsWithFirstImage() {
    $stmt = $this->conn->prepare("
        SELECT 
            p.*, 
            c.name AS category_name,
            pi.image AS first_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN (
            SELECT product_id, MIN(id) as min_id
            FROM product_images
            GROUP BY product_id
        ) pim ON pim.product_id = p.id
        LEFT JOIN product_images pi ON pi.id = pim.min_id
        WHERE p.is_active = 1
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getActiveProducts() {
    $sql = "SELECT * FROM products WHERE is_active = 1";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function addProductPdf($productId, $pdfName) {
    $sql = "INSERT INTO product_pdfs (product_id, pdf_name) VALUES (?, ?)";
    $stmt = $this->conn->prepare($sql);
    return $stmt->execute([$productId, $pdfName]);
}

public function getPdfs($productId) {
    $stmt = $this->conn->prepare("SELECT pdf_name FROM product_pdfs WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
}
