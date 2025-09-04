<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";

// Check admin permissions
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Missing order ID");
}

$order_id = intval($_GET['id']);
$orderManager = new OrderManager();

// Get order information
$pdo = (new DB())->getConnection();
$stmt = $pdo->prepare("
    SELECT o.*, pm.method_name AS payment_method, pm.fee AS payment_fee, 
           d.option_name AS delivery_option, d.fee AS delivery_fee
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
    LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Get student information
$studentName = '-';
$className = '-';
if (!empty($order['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT name, class_name FROM users WHERE id = ?");
    $stmtUser->execute([$order['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $studentName = $user['name'];
        $className = $user['class_name'];
    }
}

// Get order items
$items = $orderManager->getOrderItems($order_id);
$total = 0;
foreach ($items as $item) {
    $subtotal = $item['quantity'] * $item['price'];
    $total += $subtotal;
}
$payment_fee = isset($order['payment_fee']) ? floatval($order['payment_fee']) : 0;
$delivery_fee = isset($order['delivery_fee']) ? floatval($order['delivery_fee']) : 0;
$grandTotal = $total + $payment_fee + $delivery_fee;
$printDate = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $order_id ?> - SMJK Phor Tay</title>
    <style>
        :root {
            --primary-color: #81C784; /* Light green */
            --secondary-color: #FFD54F; /* Light yellow */
            --accent-color: #4DB6AC; /* Teal accent */
            --text-color: #333;
            --light-gray: #F5F5F5;
            --medium-gray: #E0E0E0;
            --dark-gray: #757575;
        }
        
        @page {
            size: A4;
            margin: 1cm;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            background-color: #FFFDE7; /* Very light yellow background */
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: var(--primary-color); /* Light green */
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .school-info {
            flex: 1;
        }
        
        .school-logo {
            max-height: 80px;
            margin-bottom: 10px;
        }
        
        .school-name {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .school-address {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 0;
        }
        
        .invoice-title {
            text-align: right;
        }
        
        .invoice-title h1 {
            font-size: 28px;
            margin: 0 0 5px;
            color: white;
        }
        
        .invoice-title .invoice-number {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            padding: 25px 40px;
            background: #F1F8E9; /* Very light green */
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .meta-column {
            flex: 1;
        }
        
        .meta-label {
            font-weight: bold;      /* 加粗 */
            color: #000;            /* 黑色 */
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .meta-value {
            font-size: 16px;
        }
        
        .delivery-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .badge-class {
            background: #689F38; /* Medium green */
            color: white;
        }
        
        .badge-pickup {
            background: var(--accent-color); /* Teal */
            color: white;
        }
        
        .invoice-body {
            padding: 30px 40px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0 30px;
        }
        
        .items-table th {
            background: var(--primary-color); /* Light green */
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }
        
        .items-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--medium-gray);
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total-section {
            margin-top: 30px;
            width: 100%;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }
        
        .total-label {
            width: 200px;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        .total-value {
            width: 150px;
            text-align: right;
            font-weight: 600;
        }
        
        .grand-total {
            font-size: 18px;
            color: #689F38; /* Medium green */
            border-top: 2px solid var(--medium-gray);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .invoice-footer {
            padding: 20px 40px;
            background: #F1F8E9; /* Very light green */
            border-top: 1px solid var(--medium-gray);
            text-align: center;
            font-size: 14px;
            color: var(--dark-gray);
        }
        
        .print-actions {
            text-align: center;
            margin: 20px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primary-color); /* Light green */
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 10px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #689F38; /* Medium green */
        }
        
        .btn-print {
            background: var(--accent-color); /* Teal */
        }
        
        .btn-print:hover {
            background: #00897B; /* Darker teal */
        }
        
        @media print {
            body {
                background: none;
                padding: 0;
            }
            
            .print-actions {
                display: none;
            }
            
            .invoice-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <a href="order_details.php?id=<?= $order_id ?>" class="btn">← Back to Order</a>
        <button onclick="window.print()" class="btn btn-print">🖨️ Print Invoice</button>
    </div>

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="school-info">
                <img src="images/llogo.png" alt="SMJK Phor Tay" class="school-logo">
                <h1 class="school-name">SMJK PHOR TAY</h1>
                <p class="school-address">Jalan Johor, 11400 Penang, Malaysia</p>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <div class="invoice-number">#<?= htmlspecialchars($order['order_number'] ?? $order_id) ?></div>
            </div>
        </div>
        
        <div class="invoice-meta">
            <div class="meta-column">
                <span class="meta-label">Student Name</span>
                <span class="meta-value"><?= htmlspecialchars($studentName) ?></span>
                
                <span class="meta-label">Class</span>
                <span class="meta-value"><?= htmlspecialchars($className) ?></span>
            </div>
            
            <div class="meta-column">
                <span class="meta-label">Order Date</span>
                <span class="meta-value"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
                
                <span class="meta-label">Status</span>
                <span class="meta-value"><?= htmlspecialchars(ucfirst($order['status'])) ?></span>
            </div>
            
            <div class="meta-column">
                <span class="meta-label">Delivery Method</span>
                <span class="meta-value">
                    <?= htmlspecialchars($order['delivery_option']) ?>
                </span>
                
                <span class="meta-label">Payment Method</span>
                <span class="meta-value"><?= htmlspecialchars($order['payment_method']) ?></span>
            </div>
        </div>
        
        <div class="invoice-body">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        $itemName = $item['product_name'] ?: $item['package_name'] ?: 'Unknown';
                        $subtotal = $item['quantity'] * $item['price'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($itemName) ?></td>
                        <td class="text-right">RM <?= number_format($item['price'], 2) ?></td>
                        <td class="text-right"><?= $item['quantity'] ?></td>
                        <td class="text-right">RM <?= number_format($subtotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row">
                    <div class="total-label">Subtotal:</div>
                    <div class="total-value">RM <?= number_format($total, 2) ?></div>
                </div>
                
                <?php if ($payment_fee > 0): ?>
                <div class="total-row">
                    <div class="total-label">Payment Fee (<?= htmlspecialchars($order['payment_method']) ?>):</div>
                    <div class="total-value">RM <?= number_format($payment_fee, 2) ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($delivery_fee > 0): ?>
                <div class="total-row">
                    <div class="total-label">Delivery Fee (<?= htmlspecialchars($order['delivery_option']) ?>):</div>
                    <div class="total-value">RM <?= number_format($delivery_fee, 2) ?></div>
                </div>
                <?php endif; ?>
                
                <div class="total-row grand-total">
                    <div class="total-label">TOTAL AMOUNT:</div>
                    <div class="total-value">RM <?= number_format($grandTotal, 2) ?></div>
                </div>
            </div>
        </div>
        
        <div class="invoice-footer">
            <p>Invoice generated on <?= $printDate ?> | Thank you for your order!</p>
            <p>For any inquiries, please contact the school administration office.</p>
        </div>
    </div>
</body>
</html>