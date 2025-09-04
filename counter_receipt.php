<?php
session_start();
require_once "../classes/CounterCart.php";
require_once "../classes/DB.php";

$success = false;
$error = '';
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    $error = "Invalid order ID.";
} else {
    try {
        $pdo = (new DB())->getConnection();
        // Fetch order
        $stmt = $pdo->prepare("
            SELECT o.*, pm.method_name AS payment_method, pm.fee AS payment_fee, d.option_name AS delivery_option, d.fee AS delivery_fee
            FROM orders o
            LEFT JOIN counter_payment_methods pm ON o.payment_method_id = pm.id
            LEFT JOIN counter_delivery_options d ON o.delivery_option_id = d.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error = "Order not found.";
        } else {
            $success = true;
            // Fetch user info
            $userInfo = [
                'name' => $order['student_name'],
                'class_name' => $order['class_name']
            ];
            // Fetch order items
            $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total
            $totalAmount = 0;
            foreach ($orderItems as &$item) {
                if ($item['product_id']) {
                    $stmtProd = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                    $stmtProd->execute([$item['product_id']]);
                    $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);
                    $item['display_name'] = $prod ? $prod['name'] : 'Product';
                    $item['item_type'] = 'product';
                } elseif ($item['package_id']) {
                    $stmtPack = $pdo->prepare("SELECT name FROM packages WHERE id = ?");
                    $stmtPack->execute([$item['package_id']]);
                    $pack = $stmtPack->fetch(PDO::FETCH_ASSOC);
                    $item['display_name'] = $pack ? $pack['name'] : 'Package';
                    $item['item_type'] = 'package';
                } else {
                    $item['display_name'] = 'Unknown';
                    $item['item_type'] = 'unknown';
                }
                $item['selected_options'] = $item['selected_options'] ? json_decode($item['selected_options'], true) : [];
                $totalAmount += $item['price'] * $item['quantity'];
            }
            unset($item);

            $payment_fee = isset($order['payment_fee']) ? floatval($order['payment_fee']) : 0;
            $delivery_fee = isset($order['delivery_fee']) ? floatval($order['delivery_fee']) : 0;

            // Coupon logic: treat coupon as discount amount (if numeric or starts with RM)
            $coupon = isset($order['coupon']) ? $order['coupon'] : '';
            $coupon_discount = 0;
            if ($coupon) {
                if (is_numeric($coupon) && floatval($coupon) > 0) {
                    $coupon_discount = floatval($coupon);
                } elseif (preg_match('/RM\s*([\d.]+)/i', $coupon, $matches)) {
                    $coupon_discount = floatval($matches[1]);
                } elseif (preg_match('/([\d.]+)\s*%/', $coupon, $matches)) {
                    // Percentage discount, e.g. "20%"
                    $percent = floatval($matches[1]);
                    if ($percent > 0 && $percent <= 100) {
                        $coupon_discount = ($totalAmount * $percent) / 100;
                    }
                }
            }

            $grandTotal = $totalAmount + $payment_fee + $delivery_fee - $coupon_discount;
            if ($grandTotal < 0) $grandTotal = 0;

            // Payment and delivery
            $payment_method = $order['payment_method'];
            $delivery_option = $order['delivery_option'];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$deliveryOptions = [];
$stmt = $pdo->query("SELECT * FROM delivery_options");
if ($stmt) {
    $deliveryOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt</title>
    <style>
        :root {
            /* Soft Pastel Professional Color Scheme */
            --primary-color: #9DB4C0; /* Soft slate blue */
            --secondary-color: #E2E8F0; /* Very light gray-blue */
            --accent-color: #A5C4D4; /* Soft blue-gray */
            --light-color: #F5F7FA; /* Off-white background */
            --lighter-color: #FFFFFF; /* Pure white */
            --text-color: #4A5568; /* Dark gray-blue for text */
            --light-text: #718096; /* Medium gray for secondary text */
            --border-color: #CBD5E0; /* Light gray border */
            --success-color: #90C8AC; /* Soft green */
            --warning-color: #F0D78C; /* Soft yellow */
            --danger-color: #F4A7B9; /* Soft pink */
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 30px;
            font-size: 16px;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--lighter-color);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .receipt-header {
            background-color: var(--primary-color);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            color: white;
        }
        
        .receipt-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .receipt-subtitle {
            color: var(--secondary-color);
            font-size: 16px;
            font-weight: 400;
        }
        
        .receipt-body {
            padding: 30px;
        }
        
        .receipt-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background-color: var(--accent-color);
            border-radius: 8px;
            color: white;
        }
        
        .receipt-meta-item {
            min-width: 0;
        }
        
        .receipt-meta-label {
            font-size: 14px;
            color: var(--secondary-color);
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .receipt-meta-value {
            font-weight: 500;
            font-size: 16px;
            word-break: break-word;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            font-size: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 0 1px var(--border-color);
        }
        
        .receipt-table th {
            background-color: var(--primary-color);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: white;
            border-bottom: 2px solid var(--border-color);
            font-size: 15px;
        }
        
        .receipt-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .receipt-table tr:nth-child(even) {
            background-color: var(--secondary-color);
        }
        
        .receipt-table tr:nth-child(odd) {
            background-color: var(--lighter-color);
        }
        
        .receipt-table tr:last-child td {
            border-bottom: none;
        }
        
        .receipt-totals {
            margin-top: 30px;
            padding: 20px;
            background-color: var(--secondary-color);
            border-radius: 8px;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        
        .receipt-total-row:not(:last-child) {
            border-bottom: 1px dashed var(--border-color);
        }
        
        .receipt-total-label {
            font-weight: 500;
            color: var(--text-color);
        }
        
        .receipt-total-value {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .receipt-grand-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid var(--border-color);
            font-size: 18px;
        }
        
        .receipt-note {
            text-align: center;
            font-style: italic;
            color: var(--light-text);
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
            font-size: 14px;
        }
        
        .receipt-error {
            color: var(--danger-color);
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: #ffebee;
            border-radius: 8px;
            font-size: 18px;
        }
        
        .receipt-footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-link:hover {
            background-color: #8CA7B3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .item-name {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .item-type {
            font-size: 13px;
            color: var(--text-color);
            background-color: var(--accent-color);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            color: white;
        }
        
        .option-value {
            font-size: 14px;
            color: var(--light-text);
            margin-bottom: 3px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .receipt-container {
                border-radius: 8px;
            }
            
            .receipt-header, .receipt-body {
                padding: 20px;
            }
            
            .receipt-title {
                font-size: 24px;
            }
            
            .receipt-meta {
                grid-template-columns: 1fr;
                padding: 15px;
            }
            
            .receipt-table th, 
            .receipt-table td {
                padding: 12px 10px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .receipt-header {
                padding: 20px 15px;
            }
            
            .receipt-title {
                font-size: 20px;
                flex-direction: column;
                gap: 8px;
            }
            
            .receipt-body {
                padding: 15px;
            }
            
            .receipt-table {
                display: block;
                overflow-x: auto;
            }
            
            .back-link {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <?php if (!empty($success)): ?>
            <div class="receipt-header">
                <h1 class="receipt-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Order Confirmation
                </h1>
                <p class="receipt-subtitle">Thank you for your purchase!</p>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-meta">
                    <div class="receipt-meta-item">
                        <div class="receipt-meta-label">Order Number</div>
                        <div class="receipt-meta-value">#<?= htmlspecialchars($order['order_number'] ?? $order_id) ?></div>
                    </div>
                    <div class="receipt-meta-item">
                        <div class="receipt-meta-label">Date & Time</div>
                        <div class="receipt-meta-value"><?= date("F j, Y, g:i a", strtotime($order['created_at'] ?? 'now')) ?></div>
                    </div>
                    <div class="receipt-meta-item">
                        <div class="receipt-meta-label">Customer</div>
                        <div class="receipt-meta-value"><?= htmlspecialchars($userInfo['name']) ?></div>
                    </div>
                    <div class="receipt-meta-item">
                        <div class="receipt-meta-label">Class</div>
                        <div class="receipt-meta-value"><?= htmlspecialchars($userInfo['class_name']) ?></div>
                    </div>
                    <?php if ($coupon): ?>
                        <div class="receipt-meta-item">
                            <span class="receipt-meta-label">Coupon:</span>
                            <span class="receipt-meta-value"><?= htmlspecialchars($coupon_discount > 0 ? "RM" . number_format($coupon_discount, 2) : $coupon) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Details</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <div class="item-name"><?= htmlspecialchars($item['display_name']) ?></div>
                                    <span class="item-type"><?= ucfirst($item['item_type']) ?></span>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td>
                                    <?php
                                        $options = $item['selected_options'];
                                        if (is_array($options) && count($options) > 0) {
                                            foreach ($options as $key => $value) {
                                                if (is_array($value)) {
                                                    foreach ($value as $subKey => $subValue) {
                                                        echo '<div class="option-value">' . htmlspecialchars("$subKey: $subValue") . '</div>';
                                                    }
                                                } else {
                                                    echo '<div class="option-value">' . htmlspecialchars("$key: $value") . '</div>';
                                                }
                                            }
                                        } else {
                                            echo '<div class="option-value">-</div>';
                                        }
                                    ?>
                                </td>
                                <td>RM<?= number_format($item['price'], 2) ?></td>
                                <td>RM<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="receipt-totals">
                    <?php if ($payment_fee > 0): ?>
                        <div class="receipt-total-row">
                            <span class="receipt-total-label">Payment Fee (<?= htmlspecialchars($payment_method) ?>):</span>
                            <span class="receipt-total-value">RM<?= number_format($payment_fee, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($delivery_fee > 0): ?>
                        <div class="receipt-total-row">
                            <span class="receipt-total-label">Delivery Fee (<?= htmlspecialchars($delivery_option) ?>):</span>
                            <span class="receipt-total-value">RM<?= number_format($delivery_fee, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($coupon_discount > 0): ?>
                        <div class="receipt-total-row">
                            <span class="receipt-total-label">Coupon Discount:</span>
                            <span class="receipt-total-value" style="color: var(--success-color);">-RM<?= number_format($coupon_discount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="receipt-total-row receipt-grand-total">
                        <span class="receipt-total-label">Total Amount:</span>
                        <span class="receipt-total-value">RM<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
                
                <div class="receipt-note">
                    Your order has been processed successfully. Please keep this receipt for your records.
                </div>
                
                <div class="receipt-footer">
                    <a href="counter.php" class="back-link">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Return to Store
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <div class="receipt-header" style="background-color: var(--danger-color);">
                <h1 class="receipt-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    Order Processing Failed
                </h1>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-error">
                    <p><?= htmlspecialchars($error ?? "An unknown error occurred while processing your order") ?></p>
                </div>
                
                <div class="receipt-footer">
                    <a href="counter_cart.php" class="back-link" style="background-color: var(--danger-color);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Return to Cart
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>