<?php
require_once "DB.php";

class OrderManager extends DB {
    public function getAllOrdersWithUserDetails() {
        $stmt = $this->conn->prepare("
            SELECT 
                o.id AS order_id,
                o.total_amount,
                o.status,
                o.created_at,
                o.shipping_address,
                u.name AS student_name,
                u.class_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderItems($order_id) {
        // Base query to get items, joining product/package data
        $sql = "
            SELECT 
                oi.*,
                p.name AS product_name,
                p.image AS product_image,
                pk.name AS package_name,
                pk.image AS package_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN packages pk ON oi.package_id = pk.id
            WHERE oi.order_id = :order_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['order_id' => $order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($items as &$item) {
            $item['selected_options'] = json_decode($item['selected_options'], true);
    
            if ($item['package_id']) {
                // Fetch products inside this package with their options
                $item['package_products'] = $this->getProductsWithOptionsInPackage($item['package_id']);
                
                // Fetch multiple images for package
                $item['package_images'] = $this->getPackageImages($item['package_id']);
            }
        }
        return $items;
    }
    
    public function getProductsWithOptionsInPackage($package_id) {
        $sql = "
        SELECT 
            pp.product_id,
            p.name AS product_name,
            p.image AS product_image,
            pp.quantity
        FROM product_packages pp
        JOIN products p ON pp.product_id = p.id
        WHERE pp.package_id = :package_id
    ";
    
    
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['package_id' => $package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPackageImages($package_id) {
        $sql = "SELECT image_path FROM package_images WHERE package_id = :package_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['package_id' => $package_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function updateOrderStatus($orderId, $newStatus) {
        $sql = "UPDATE orders SET status = :status WHERE id = :order_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':status' => $newStatus,
            ':order_id' => $orderId
        ]);
    }

    public function countOrdersByStatus($status) {
    $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmt->execute([$status]);
    return (int)$stmt->fetchColumn();
}
    
 public function getOrdersByUserId($userId) {
    $stmt = $this->conn->prepare("
        SELECT o.*, 
               pm.method_name AS payment_method, 
               d.option_name AS delivery_option
        FROM orders o
        LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
        LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getOrdersByUserAndStatus($user_id, $status) {
    $stmt = $this->conn->prepare("SELECT * FROM orders WHERE user_id = ? AND status = ?");
    $stmt->execute([$user_id, $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

 public function getOrdersByDeliveryOption($optionId, $statusFilter = "Pending,Shipping", $page = 1, $itemsPerPage = 5, $search = '') {
        $offset = ($page - 1) * $itemsPerPage;
        $statuses = explode(',', $statusFilter);
        
        // Base query
        $baseQuery = "
            SELECT 
                o.*,
                u.name AS student_name,
                u.class_name,
                d.option_name AS delivery_option
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN delivery_options d ON o.delivery_option_id = d.id
            WHERE o.delivery_option_id = ?
            AND o.status IN (" . implode(',', array_fill(0, count($statuses), '?')) . ")
        ";
        
        $params = array_merge([$optionId], $statuses);
        
        // Add search if needed
        if (!empty($search)) {
            $baseQuery .= " AND (
                o.order_number LIKE ? OR
                u.name LIKE ? OR
                u.class_name LIKE ? OR
                o.status LIKE ? OR
                o.id LIKE ?
            )";
            $searchParam = "%$search%";
            $params = array_merge($params, array_fill(0, 5, $searchParam));
        }
        
        // Order by clause
        $orderBy = "
            ORDER BY 
                CASE 
                    WHEN o.status = 'Pending' THEN 0
                    WHEN o.status = 'Shipping' THEN 1
                    ELSE 2
                END,
                o.created_at ASC
            LIMIT $offset, $itemsPerPage
        ";
        
        // Get orders
        $stmt = $this->conn->prepare($baseQuery . $orderBy);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get count
        $countSql = "SELECT COUNT(*) FROM (" . $baseQuery . ") AS count_query";
        $stmtCount = $this->conn->prepare($countSql);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();
        
        return [
            'orders' => $orders,
            'total' => $total,
            'totalPages' => ceil($total / $itemsPerPage)
        ];
    }

    public function getConnection() {
    return $this->conn;
}

}
?>
