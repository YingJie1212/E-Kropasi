<?php
session_start();
require_once "../classes/Cart.php";
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
            LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
            LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
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
            $totalAmount = $order['total_amount'];
            $payment_fee = isset($order['payment_fee']) ? floatval($order['payment_fee']) : 0;
            $delivery_fee = isset($order['delivery_fee']) ? floatval($order['delivery_fee']) : 0;
            $grandTotal = $totalAmount; // total_amount already includes all fees

            // For displaying options and names
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
            }
            unset($item);

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
            --primary-light: #f8f8f2;
            --secondary-light: #f0f0e6;
            --accent-yellow: #f5e8c0;
            --accent-green: #e8f5e0;
            --text-dark: #333333;
            --text-medium: #5a5a5a;
            --text-light: #777777;
            --border-light: #e0e0d8;
            --success-color: #4a8f4a;
            --error-color: #d45d5d;
            --highlight-color: #f9f7e8;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        
        body {
            background-color: var(--secondary-light);
            color: var(--text-dark);
            line-height: 1.6;
            padding: 40px 20px;
            font-size: 15px;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid var(--border-light);
        }
        
        .receipt-header {
            background-color: var(--accent-green);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }
        
        .receipt-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .receipt-subtitle {
            font-size: 16px;
            color: var(--text-medium);
            font-weight: 400;
        }
        
        .receipt-body {
            padding: 30px;
        }
        
        .receipt-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 3px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 15px;
        }
        
        .receipt-table thead {
            background-color: var(--accent-yellow);
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }
        
        .receipt-table th {
            padding: 14px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 15px;
        }
        
        .receipt-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: top;
        }
        
        .receipt-table tr:last-child td {
            border-bottom: none;
        }
        
        .receipt-table tr:hover {
            background-color: var(--highlight-color);
        }
        
        .item-name {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .item-type {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 3px;
        }
        
        .option-value {
            font-size: 13px;
            color: var(--text-medium);
            margin-top: 3px;
            line-height: 1.5;
        }
        
        .price-cell {
            font-family: 'Courier New', monospace;
            text-align: right;
        }
        
        .totals-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }
        
        .total-label {
            color: var(--text-medium);
        }
        
        .total-value {
            font-weight: 600;
            color: var(--text-dark);
            font-family: 'Courier New', monospace;
        }
        
        .grand-total {
            font-size: 18px;
            padding: 12px 0;
            margin-top: 10px;
            border-top: 1px dashed var(--border-light);
            color: var(--success-color);
        }
        
        .receipt-footer {
            background-color: var(--accent-green);
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid var(--border-light);
        }
        
        .thank-you {
            font-size: 16px;
            color: var(--text-medium);
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .contact-info {
            font-size: 14px;
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 15px;
        }
        
        .btn-primary {
            background-color: var(--success-color);
            color: white;
            border: 1px solid var(--success-color);
        }
        
        .btn-primary:hover {
            background-color: #3a7a3a;
            border-color: #3a7a3a;
        }
        
        .btn-secondary {
            background-color: white;
            color: var(--text-medium);
            border: 1px solid var(--border-light);
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-light);
            color: var(--text-dark);
        }
        
        .receipt-error {
            padding: 40px;
            text-align: center;
        }
        
        .error-icon {
            font-size: 50px;
            color: var(--error-color);
            margin-bottom: 20px;
        }
        
        .error-title {
            font-size: 24px;
            color: var(--error-color);
            margin-bottom: 15px;
        }
        
        .error-message {
            font-size: 16px;
            color: var(--text-medium);
            margin-bottom: 25px;
        }
        
        /* Print-specific styles */
        @media print {
            body {
                padding: 0;
                background: none;
            }
            
            .receipt-container {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            
            .action-buttons {
                display: none;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 20px 10px;
            }
            
            .receipt-header, .receipt-body, .receipt-footer {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .receipt-table {
                font-size: 14px;
            }
            
            .receipt-table th, 
            .receipt-table td {
                padding: 10px 8px;
            }
        }
        
        @media (max-width: 480px) {
            .receipt-title {
                font-size: 22px;
            }
            
            .receipt-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
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
                <div class="receipt-section">
                    <h2 class="section-title">Order Summary</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Order Number</div>
                            <div class="info-value"><?= htmlspecialchars($order['order_number'] ?? $order_id) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date</div>
                            <div class="info-value"><?= date("F j, Y, g:i a", strtotime($order['created_at'] ?? 'now')) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value" style="color: var(--success-color); font-weight: 600;">Completed</div>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h2 class="section-title">Customer Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Name</div>
                            <div class="info-value"><?= htmlspecialchars($userInfo['name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Class</div>
                            <div class="info-value"><?= htmlspecialchars($userInfo['class_name']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Payment Method</div>
                            <div class="info-value"><?= htmlspecialchars($payment_method ?? '') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Delivery Option</div>
                            <div class="info-value"><?= htmlspecialchars($delivery_option ?? '') ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-section">
                    <h2 class="section-title">Order Details</h2>
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style="text-align: right;">Qty</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="item-name"><?= htmlspecialchars($item['display_name']) ?></div>
                                        <div class="item-type"><?= ucfirst($item['item_type']) ?></div>
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
                                            }
                                        ?>
                                    </td>
                                    <td style="text-align: right;"><?= $item['quantity'] ?></td>
                                    <td class="price-cell">RM<?= number_format($item['price'], 2) ?></td>
                                    <td class="price-cell">RM<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="totals-section">
                    <?php if ($payment_fee > 0): ?>
                        <div class="total-row">
                            <span class="total-label">Payment Fee (<?= htmlspecialchars($payment_method) ?>)</span>
                            <span class="total-value">RM<?= number_format($payment_fee, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($delivery_fee > 0): ?>
                        <div class="total-row">
                            <span class="total-label">Delivery Fee (<?= htmlspecialchars($delivery_option) ?>)</span>
                            <span class="total-value">RM<?= number_format($delivery_fee, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="total-row grand-total">
                        <span class="total-label">Total Amount</span>
                        <span class="total-value">RM<?= number_format($grandTotal, 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="receipt-footer">
                
                <div class="action-buttons">
                    <a href="products.php" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Continue Shopping
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Print Receipt
                    </button>
                </div>
            </div>
            
        <?php else: ?>
            <div class="receipt-error">
                <div class="error-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h2 class="error-title">Order Not Found</h2>
                <p class="error-message"><?= htmlspecialchars($error ?? "We couldn't locate your order. Please try again or contact support.") ?></p>
                <div class="action-buttons">
                    <a href="cart.php" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="10" cy="20.5" r="1"/><circle cx="18" cy="20.5" r="1"/>
                            <path d="M2.5 2.5h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6l1.6-8.4H7.1"/>
                        </svg>
                        Return to Cart
                    </a>
                    <a href="products.php" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        Browse Products
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>