<?php
require_once "DB.php";

class Cart extends DB {
    // Add item to cart (supports both products and packages)
    public function addItem($admin_id, $item_id, $item_type, $quantity, array $selected_options = []) {
        $optionsJson = json_encode($selected_options);

        $stmt = $this->conn->prepare("
            INSERT INTO cart (admin_id, item_id, item_type, quantity, selected_options, added_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$admin_id, $item_id, $item_type, $quantity, $optionsJson]);
    }

    // Get all cart items for user
    public function getItemsByUser($admin_id) {
        $sql = "
            SELECT 
                c.*, 
                CASE 
                    WHEN c.item_type = 'product' THEN p.name 
                    WHEN c.item_type = 'package' THEN pk.name 
                END AS display_name,
                CASE 
                    WHEN c.item_type = 'product' THEN p.image 
                    WHEN c.item_type = 'package' THEN GROUP_CONCAT(pi.image_path) 
                END AS display_images,
                CASE 
                    WHEN c.item_type = 'product' THEN p.price 
                    WHEN c.item_type = 'package' THEN pk.price 
                END AS price
            FROM cart c
            LEFT JOIN products p ON c.item_type = 'product' AND c.item_id = p.id
            LEFT JOIN packages pk ON c.item_type = 'package' AND c.item_id = pk.id
            LEFT JOIN package_images pi ON c.item_type = 'package' AND c.item_id = pi.package_id
            WHERE c.admin_id = :admin_id
            GROUP BY c.id
        ";
    
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['admin_id' => $admin_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($items as &$item) {
            $item['selected_options'] = json_decode($item['selected_options'], true);
            $item['display_images'] = $item['display_images'] ? explode(',', $item['display_images']) : [];
        }
    
        return $items;
    }
    
    
    
    // Update quantity of a cart item
    public function updateQuantityAndOptions($cart_id, $admin_id, $quantity, $options = []) {
        $stmt = $this->conn->prepare("UPDATE cart SET quantity = ?, selected_options = ? WHERE id = ? AND admin_id = ?");
        $options_json = json_encode($options);
        $stmt->execute([$quantity, $options_json, $cart_id, $admin_id]);
    }
    

    // Remove item from cart
    public function removeItem($cart_id, $admin_id) {
        $stmt = $this->conn->prepare("DELETE FROM cart WHERE id = ? AND admin_id = ?");
        return $stmt->execute([$cart_id, $admin_id]);
    }

    public function getPackageOptionsByProduct($product_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                pp.package_id,
                pp.quantity,
                pog.name AS option_name
            FROM product_packages pp
            JOIN product_option_groups pog ON pp.product_id = pog.product_id
            WHERE pp.product_id = :product_id
        ");
        $stmt->execute(['product_id' => $product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getOptionGroupsWithValues($product_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                pog.id AS group_id,
                pog.name AS group_name,
                pov.id AS option_id,
                pov.value AS option_value
            FROM product_option_groups pog
            LEFT JOIN product_option_values pov ON pog.id = pov.group_id
            WHERE pog.product_id = :product_id
            ORDER BY pog.id, pov.id
        ");
        $stmt->execute(['product_id' => $product_id]);
    
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $groups = [];
        foreach ($results as $row) {
            $group_id = $row['group_id'];
            if (!isset($groups[$group_id])) {
                $groups[$group_id] = [
                    'group_name' => $row['group_name'],
                    'options' => []
                ];
            }
            if ($row['option_id'] !== null) {
                $groups[$group_id]['options'][] = [
                    'id' => $row['option_id'],
                    'value' => $row['option_value']
                ];
            }
        }
    
        return array_values($groups);
    }
    
    public function getOptionsForPackage($package_id) {
        $stmt = $this->conn->prepare("
            SELECT
                pp.product_id,
                pp.quantity,
                p.name AS product_name,
                pog.id AS option_group_id,
                pog.name AS option_group_name,
                pov.id AS option_id,
                pov.value AS option_value
            FROM product_packages pp
            JOIN products p ON pp.product_id = p.id
            LEFT JOIN product_option_groups pog ON p.id = pog.product_id
            LEFT JOIN product_option_values pov ON pog.id = pov.group_id
            WHERE pp.package_id = :package_id
            ORDER BY p.id, pog.id, pov.id
        ");
        $stmt->execute(['package_id' => $package_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $packageOptions = [];
        foreach ($results as $row) {
            $product_id = $row['product_id'];
            $product_name = $row['product_name'];
    
            if (!isset($packageOptions[$product_id])) {
                $packageOptions[$product_id] = [
                    'product_name' => $product_name,
                    'quantity' => $row['quantity'],
                    'option_groups' => []
                ];
            }
    
            $group_id = $row['option_group_id'];
            if ($group_id) {
                if (!isset($packageOptions[$product_id]['option_groups'][$group_id])) {
                    $packageOptions[$product_id]['option_groups'][$group_id] = [
                        'group_name' => $row['option_group_name'],
                        'options' => []
                    ];
                }
                if ($row['option_id']) {
                    $packageOptions[$product_id]['option_groups'][$group_id]['options'][] = [
                        'id' => $row['option_id'],
                        'value' => $row['option_value']
                    ];
                }
            }
        }
    
        // Format for easier consumption
        foreach ($packageOptions as &$product) {
            $product['option_groups'] = array_values($product['option_groups']);
        }
    
        return $packageOptions;
    }
    
    public function getOptionValueLabel($optionValueId) {
        $stmt = $this->conn->prepare("
            SELECT v.value, g.name AS group_name
            FROM product_option_values v
            JOIN product_option_groups g ON v.group_id = g.id
            WHERE v.id = ?
        ");
        $stmt->execute([$optionValueId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? "{$row['group_name']}: {$row['value']}" : 'N/A';
    }
    
    public function getPDO() {
        return $this->conn;
    }
    
public function updateItem($cart_id, $admin_id, $selected_options, $quantity) {
    // Use $this->conn for DB connection
    $stmt = $this->conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND admin_id = ?");
    $stmt->execute([$quantity, $cart_id, $admin_id]);

    // Update selected options (assuming you store as JSON in a column named selected_options)
    $optionsJson = json_encode($selected_options);
    $stmt = $this->conn->prepare("UPDATE cart SET selected_options = ? WHERE id = ? AND admin_id = ?");
    $stmt->execute([$optionsJson, $cart_id, $admin_id]);
}

public function getItemById($cart_id, $admin_id) {
    $stmt = $this->conn->prepare("SELECT * FROM cart WHERE id = ? AND admin_id = ?");
    $stmt->execute([$cart_id, $admin_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}