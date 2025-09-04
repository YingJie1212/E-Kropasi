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
        // Fetch order with delivery option name and fee
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   pm.method_name AS payment_method, pm.fee AS payment_fee, 
                   d.option_name AS delivery_option, d.fee AS delivery_fee
            FROM orders o
            LEFT JOIN guest_payment_methods pm ON o.payment_method_id = pm.id
            LEFT JOIN guest_delivery_options d ON o.delivery_option_id = d.id
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
            $delivery_fee = isset($order['delivery_fee']) ? floatval($order['delivery_fee']) : 0;
            $grandTotal = $totalAmount + $delivery_fee;

            // For displaying options and names
            foreach ($orderItems as &$item) {
                // You may need to adjust these lines based on your DB structure
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
            $delivery_option = $order['delivery_option']; // This is from the alias in your SQL
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Receipt</title>
    <style>
        :root {
            --primary-beige: #f5f5f0;
            --secondary-beige: #e8e6df;
            --accent-beige: #d8d5cd;
            --text-color: #333;
            --border-color: #c4beb5;
            --success-color: #5a8f5a;
            --error-color: #c45656;
            --header-bg: #e8e6df;
            --row-even: #f9f8f6;
            --row-odd: #ffffff;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--primary-beige);
            color: var(--text-color);
            line-height: 1.6;
            padding: 30px;
        }
        
        .receipt-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .receipt-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .receipt-meta-item {
            flex: 1;
            min-width: 150px;
        }
        
        .receipt-meta-label {
            font-size: 13px;
            color: #8b7d70;
            margin-bottom: 3px;
        }
        
        .receipt-meta-value {
            font-weight: 500;
            color: #5a4e42;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .receipt-table th {
            background-color: var(--header-bg);
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #5a4e42;
            border-bottom: 2px solid var(--border-color);
        }
        
        .receipt-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .receipt-table tr:nth-child(even) {
            background-color: var(--row-even);
        }
        
        .receipt-table tr:nth-child(odd) {
            background-color: var(--row-odd);
        }
        
        .receipt-total {
            text-align: right;
            font-size: 18px;
            font-weight: 600;
            color: #5a4e42;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .receipt-note {
            text-align: center;
            font-style: italic;
            color: #8b7d70;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px dashed var(--border-color);
        }
        
        .receipt-error {
            color: var(--error-color);
            text-align: center;
            margin: 20px 0;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--button-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: var(--button-hover);
            text-decoration: underline;
        }
        
        .option-value {
            font-size: 13px;
            color: #666;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <div id="printable-receipt" class="receipt-container">
        <?php if (!empty($success)): ?>
            <div class="receipt-header">
                <h1 class="receipt-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Order Receipt
                </h1>
            </div>
            
            <div class="receipt-meta">
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Order ID</div>
                    <div class="receipt-meta-value">
                        <?= htmlspecialchars($order['order_number'] ?? $order_id) ?>
                    </div>
                </div>
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Date</div>
                    <div class="receipt-meta-value"><?= date("Y-m-d H:i:s") ?></div>
                </div>
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Student Name</div>
                    <div class="receipt-meta-value"><?= htmlspecialchars($userInfo['name']) ?></div>
                </div>
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Class</div>
                    <div class="receipt-meta-value"><?= htmlspecialchars($userInfo['class_name']) ?></div>
                </div>
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Payment Method</div>
                    <div class="receipt-meta-value"><?= htmlspecialchars($payment_method ?? '') ?></div>
                </div>
                <div class="receipt-meta-item">
                    <div class="receipt-meta-label">Delivery Option</div>
                    <div class="receipt-meta-value"><?= htmlspecialchars($delivery_option ?? '') ?></div>
                </div>
            </div>
            
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 30%;">Options</th>
                        <th style="width: 15%;">Price</th>
                        <th style="width: 15%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars($item['display_name']) ?></div>
                                <div style="font-size: 12px; color: #8b7d70;">
                                    <?= $item['item_type'] === 'product' ? 'Product' : 'Package' ?>
                                </div>
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
            
            <div class="receipt-total">
                <strong>Grand Total:</strong> RM<?= number_format($totalAmount, 2) ?>
                <?php if ($delivery_fee > 0): ?>
                    <br><span style="font-size:14px;">(includes delivery fee of RM<?= number_format($delivery_fee, 2) ?>)</span>
                <?php endif; ?>
            </div>
          
            
        <?php else: ?>
            <div class="receipt-header">
                <h1 class="receipt-title" style="color: var(--error-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    Order Failed
                </h1>
            </div>
            
            <div class="receipt-error">
                <p><?= htmlspecialchars($error ?? "Unknown error occurred") ?></p>
            </div>
            

        <?php endif; ?>
    </div>
    <script>
        window.onload = function() {
            setTimeout(function() {
                const receipt = document.getElementById('printable-receipt');
                html2canvas(receipt, { scale: 2 }).then(function(canvas) {
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new window.jspdf.jsPDF('p', 'pt', 'a4');
                    // Calculate width/height to fit A4
                    const pageWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();
                    const imgWidth = pageWidth - 40;
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    pdf.addImage(imgData, 'PNG', 20, 20, imgWidth, imgHeight);
                    pdf.save('receipt.pdf');
                });
            }, 500);
        };
    </script>
</body>
</html>