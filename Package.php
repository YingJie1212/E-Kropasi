<?php
require_once "DB.php";

class Package extends DB {
    public function add($name, $description, $price, $image, $category_id, $visible_to, $gender = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO packages (name, description, price, image, category_id, visible_to, gender, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $description, $price, $image, $category_id, $visible_to, $gender]);
        return $this->conn->lastInsertId();
    }

    public function addItemToPackage($package_id, $product_id, $quantity) {
        $stmt = $this->conn->prepare("INSERT INTO product_packages (package_id, product_id, quantity) VALUES (?, ?, ?)");
        return $stmt->execute([$package_id, $product_id, $quantity]);
    }

    public function addImage($packageId, $imagePath) {
        $stmt = $this->conn->prepare("INSERT INTO package_images (package_id, image_path) VALUES (?, ?)");
        return $stmt->execute([$packageId, $imagePath]);
    }
    

    public function addOption($package_id, $product_id, $option_group, $option_value) {
        $stmt = $this->conn->prepare("INSERT INTO package_options (package_id, product_id, option_group, option_value) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$package_id, $product_id, $option_group, $option_value]);
    }

    // Fetch all packages from DB
public function getAllPackages() {
    $stmt = $this->conn->prepare("SELECT * FROM packages ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getProductsInPackage($packageId) {
    $stmt = $this->conn->prepare("
        SELECT 
            pp.product_id, 
            p.name, 
            pi.image AS image, 
            pp.quantity, 
            p.price
        FROM product_packages pp
        JOIN products p ON pp.product_id = p.id
        LEFT JOIN (
            SELECT product_id, MIN(id) as min_id
            FROM product_images
            GROUP BY product_id
        ) pim ON pim.product_id = p.id
        LEFT JOIN product_images pi ON pi.id = pim.min_id
        WHERE pp.package_id = ?
    ");
    $stmt->execute([$packageId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function delete($id) {
    // Delete associated product-package relationships
    $stmt1 = $this->conn->prepare("DELETE FROM product_packages WHERE package_id = ?");
    $stmt1->execute([$id]);

    // Delete package images (optional)
    $stmt2 = $this->conn->prepare("DELETE FROM package_images WHERE package_id = ?");
    $stmt2->execute([$id]);

    // Finally, delete the package itself
    $stmt3 = $this->conn->prepare("DELETE FROM packages WHERE id = ?");
    return $stmt3->execute([$id]);
}

public function searchPackages($keyword) {
    $stmt = $this->conn->prepare("
        SELECT p.*, c.name AS category_name
        FROM packages p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE :kw
           OR p.description LIKE :kw
           OR p.id LIKE :kw
           OR p.price LIKE :kw
           OR p.visible_to LIKE :kw
           OR p.gender LIKE :kw
           OR c.name LIKE :kw
        ORDER BY p.created_at DESC
    ");
    $stmt->execute(['kw' => '%' . $keyword . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getOptions($packageId) {
    $stmt = $this->conn->prepare("SELECT * FROM package_options WHERE package_id = ?");
    $stmt->execute([$packageId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getImagesByPackageId($packageId) {
    $stmt = $this->conn->prepare("SELECT image_path FROM package_images WHERE package_id = ?");
    $stmt->execute([$packageId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getById($id) {
    $stmt = $this->conn->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

public function update($id, $name, $description, $price, $image, $category_id, $visible_to, $gender = null) {
    $stmt = $this->conn->prepare("UPDATE packages SET name=?, description=?, price=?, image=?, category_id=?, visible_to=?, gender=? WHERE id=?");
    $stmt->execute([$name, $description, $price, $image, $category_id, $visible_to, $gender, $id]);
}

public function removeAllItemsFromPackage($packageId) {
    $stmt = $this->conn->prepare("DELETE FROM product_packages WHERE package_id = ?");
    $stmt->execute([$packageId]);
}

public function addCategory($packageId, $categoryId) {
    $stmt = $this->conn->prepare("INSERT INTO package_categories (package_id, category_id) VALUES (?, ?)");
    return $stmt->execute([$packageId, $categoryId]);
}

public function removeAllCategoriesFromPackage($packageId) {
    $stmt = $this->conn->prepare("DELETE FROM package_categories WHERE package_id = ?");
    return $stmt->execute([$packageId]);
}

public function updateCategory($packageId, $categoryId) {
    $stmt = $this->conn->prepare("UPDATE packages SET category_id = ? WHERE id = ?");
    return $stmt->execute([$categoryId, $packageId]);
}

public function getCategoriesByPackageId($packageId) {
    $stmt = $this->conn->prepare("SELECT c.name FROM package_categories pc JOIN categories c ON pc.category_id = c.id WHERE pc.package_id = ?");
    $stmt->execute([$packageId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

public function getProductsToPackage($packageId) {
    $stmt = $this->conn->prepare("
        SELECT p.*, pi.image_path AS package_image
        FROM products p
        JOIN product_packages pp ON pp.product_id = p.id
        LEFT JOIN package_images pi ON pi.package_id = pp.package_id AND pi.id = (
            SELECT id FROM package_images WHERE package_id = pp.package_id LIMIT 1
        )
        WHERE pp.package_id = ?
    ");
    $stmt->execute([$packageId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getAllActivePackages() {
    $sql = "SELECT * FROM packages WHERE is_active = '1'";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}
