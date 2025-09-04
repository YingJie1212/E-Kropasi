<?php
require_once "DB.php";

class GuestCart extends DB {
    // Add item to cart for guest
    public function addItem($guest_id, $item_id, $item_type, $quantity, array $selected_options = []) {
        $optionsJson = json_encode($selected_options);
        $stmt = $this->conn->prepare("
            INSERT INTO cart (guest_id, item_id, item_type, quantity, selected_options, added_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$guest_id, $item_id, $item_type, $quantity, $optionsJson]);
    }

    // Get all cart items for guest
    public function getItemsByGuest($guest_id) {
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
            WHERE c.guest_id = :guest_id
            GROUP BY c.id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['guest_id' => $guest_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['selected_options'] = json_decode($item['selected_options'], true);
            $item['display_images'] = $item['display_images'] ? explode(',', $item['display_images']) : [];
        }

        return $items;
    }

    // Update quantity and options for guest cart item
    public function updateItem($cart_id, $guest_id, $selected_options, $quantity) {
        $stmt = $this->conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND guest_id = ?");
        $stmt->execute([$quantity, $cart_id, $guest_id]);

        $optionsJson = json_encode($selected_options);
        $stmt = $this->conn->prepare("UPDATE cart SET selected_options = ? WHERE id = ? AND guest_id = ?");
        $stmt->execute([$optionsJson, $cart_id, $guest_id]);
    }

    // Remove item from guest cart
    public function removeItem($cart_id, $guest_id) {
        $stmt = $this->conn->prepare("DELETE FROM cart WHERE id = ? AND guest_id = ?");
        return $stmt->execute([$cart_id, $guest_id]);
    }

    // Get a single cart item for guest
    public function getItemById($cart_id, $guest_id) {
        $stmt = $this->conn->prepare("SELECT * FROM cart WHERE id = ? AND guest_id = ?");
        $stmt->execute([$cart_id, $guest_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getOptionsForPackage($package_id) {
        // Use the correct table name here:
        $stmt = $this->conn->prepare("
            SELECT pp.product_id, p.name as product_name
            FROM product_packages pp
            JOIN products p ON pp.product_id = p.id
            WHERE pp.package_id = ?
        ");
        $stmt->execute([$package_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($products as $prod) {
            $groups = $this->getOptionGroupsWithValues($prod['product_id']);
            $result[$prod['product_id']] = [
                'product_name' => $prod['product_name'],
                'option_groups' => $groups
            ];
        }
        return $result;
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

    public function getPDO() {
        return $this->conn;
    }
}