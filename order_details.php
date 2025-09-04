<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/Admin.php";

// 权限检查
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Order ID is missing.");
}

$order_id = intval($_GET['id']);
$orderManager = new OrderManager();

// 获取订单信息
require_once "../classes/DB.php";
$pdo = (new DB())->getConnection();
$stmt = $pdo->prepare("
    SELECT 
        o.bank_slip, o.status, o.user_id, o.order_number, o.ewallet_slip, o.note,
        pm.method_name AS payment_method, pm.fee AS payment_fee, 
        d.option_name AS delivery_option, d.fee AS delivery_fee
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
    LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取学生和班级
$studentName = '-';
$className = '-';
if (!empty($orderInfo['user_id'])) {
    $stmtUser = $pdo->prepare("SELECT name, class_name FROM users WHERE id = ?");
    $stmtUser->execute([$orderInfo['user_id']]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($userRow) {
        $studentName = $userRow['name'];
        $className = $userRow['class_name'];
    }
}

// 获取订单商品
$items = $orderManager->getOrderItems($order_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?= $order_id ?></title>
    <style>
        :root {
            --primary-color: #e67e22;
            --primary-light: #fdf6e9;
            --secondary-color: #42a5f5;
            --success-color: #4caf50;
            --danger-color: #e57373;
            --text-color: #333;
            --light-gray: #f0f0f0;
            --border-color: #e0e0e0;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #666;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        h2 {
            color: var(--primary-color);
            margin: 0;
            font-size: 24px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: var(--shadow);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
        }
        
        tr:hover {
            background-color: #fffaf3;
        }
        
        .button {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 4px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            margin-bottom: 10px;
            margin-right: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .button.primary {
            background: var(--primary-color); 
            color: #fff; 
        }
        
        .button.success {
            background: var(--success-color); 
            color: #fff; 
        }
        
        .button.danger {
            background: var(--danger-color); 
            color: #fff; 
        }
        
        .button.info {
            background: var(--secondary-color); 
            color: #fff; 
        }
        
        .button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .image-cell img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #eee;
        }
        
        .options-cell {
            max-width: 250px;
            word-wrap: break-word;
        }
        
        .price-cell {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .no-image {
            color: #999;
            font-style: italic;
            font-size: 13px;
        }
        
        .info-box {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
        }
        
        .total-box {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--light-gray);
        }
        
        .total-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
        }
        
        .action-buttons {
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        .status-shipping {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 12px;
            }
        }
        
        @media (max-width: 768px) {
            .info-box {
                flex-direction: column;
                gap: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .button {
                padding: 8px 15px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 10px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .button {
                width: 100%;
                margin-right: 0;
                margin-bottom: 8px;
                text-align: center;
            }
            
            .image-cell img {
                max-width: 60px;
                max-height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / <a href="orders.php">Orders</a> / Order Details
            </div>
            <h2>Order #<?= htmlspecialchars($orderInfo['order_number'] ?? $order_id) ?></h2>
        </div>

        <div class="info-box">
            <div class="info-item">
                <div class="info-label">Payment Method</div>
                <div class="info-value"><?= htmlspecialchars($orderInfo['payment_method'] ?? '-') ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Delivery Option</div>
                <div class="info-value"><?= htmlspecialchars($orderInfo['delivery_option'] ?? '-') ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Student Name</div>
                <div class="info-value"><?= htmlspecialchars($studentName) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Class Name</div>
                <div class="info-value"><?= htmlspecialchars($className) ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span class="status-badge status-<?= strtolower($orderInfo['status'] ?? '') ?>">
                        <?= htmlspecialchars($orderInfo['status'] ?? '-') ?>
                    </span>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Order Note</div>
                <textarea id="order-note" style="width:100%;min-height:60px;resize:vertical;" placeholder="Type order note..."><?= htmlspecialchars($orderInfo['note'] ?? '') ?></textarea>
                <span id="note-status" style="font-size:13px;color:#888;"></span>
            </div>
        </div>

        <?php if (!empty($orderInfo['bank_slip'])): ?>
            <div style="margin-bottom:20px; background:#fff; padding:15px; border-radius:5px; box-shadow:var(--shadow);">
                <strong>Bank Transfer Slip:</strong><br>
                <a href="../uploads/<?= htmlspecialchars($orderInfo['bank_slip']) ?>" target="_blank">
                    <img src="../uploads/<?= htmlspecialchars($orderInfo['bank_slip']) ?>" alt="Bank Slip" style="max-width:220px;max-height:220px;border:1px solid #ccc;border-radius:6px;margin-top:8px;">
                </a>
            </div>
        <?php endif; ?>

        <?php if (!empty($orderInfo['ewallet_slip'])): ?>
            <div style="margin-bottom:20px; background:#fff; padding:15px; border-radius:5px; box-shadow:var(--shadow);">
                <strong>E-wallet Receipt:</strong><br>
                <a href="../uploads/<?= htmlspecialchars($orderInfo['ewallet_slip']) ?>" target="_blank">
                    <img src="../uploads/<?= htmlspecialchars($orderInfo['ewallet_slip']) ?>" alt="E-wallet Receipt" style="max-width:220px;max-height:220px;border:1px solid #ccc;border-radius:6px;margin-top:8px;">
                </a>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Options</th>
                    <th>Image</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php 
                            if (!empty($item['product_name'])) {
                                echo htmlspecialchars($item['product_name']);
                            } elseif (!empty($item['package_name'])) {
                                echo htmlspecialchars($item['package_name']);
                            } else {
                                echo "Unknown Item";
                            }
                        ?>
                    </td>
                    <td><?= $item['quantity'] ?></td>
                    <td class="price-cell">RM<?= number_format($item['price'], 2) ?></td>
                    <td class="options-cell">
                        <?php if ($item['package_id']): ?>
                            <?php
                                require_once "../classes/Package.php";
                                $pkgObj = new Package();
                                $pkgDetails = $pkgObj->getById($item['package_id']);
                                $pkgProducts = $pkgObj->getProductsToPackage($item['package_id']);
                            ?>

                            <div>
                                <strong>Products in Package:</strong>
                                <ul style="margin:0;padding-left:18px;">
                                    <?php foreach ($pkgProducts as $prod): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                            (RM<?= number_format($prod['price'], 2) ?>)
                                            <?php if (!empty($prod['option_group']) && !empty($prod['option_value'])): ?>
                                                <em><?= htmlspecialchars($prod['option_group']) ?>: <?= htmlspecialchars($prod['option_value']) ?></em>
                                            <?php endif; ?>
                                            <?php
                                                require_once "../classes/Product.php";
                                                $prodObj = new Product();
                                                $prodImages = method_exists($prodObj, 'getImagesByProductId')
                                                    ? $prodObj->getImagesByProductId($prod['id'])
                                                    : [];
                                                $prodImg = !empty($prodImages) && !empty($prodImages[0]['image'])
                                                    ? $prodImages[0]['image']
                                                    : 'default.jpg';
                                            ?>
                                            <br>
                                            <img src="../uploads/<?= htmlspecialchars($prodImg) ?>" alt="Product Image" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #eee;margin-top:2px;">
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php elseif (!empty($item['selected_options'])): ?>
                            <?php foreach ($item['selected_options'] as $key => $val): ?>
                                <?= htmlspecialchars("$key: $val") ?><br>
                            <?php endforeach; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="image-cell">
                        <?php
                        $imgFile = '';
                        if (!empty($item['product_images'])) {
                            $imgFile = $item['product_images'];
                        } elseif (!empty($item['product_id'])) {
                            require_once "../classes/Product.php";
                            $prodObj = new Product();
                            $prodImages = method_exists($prodObj, 'getImagesByProductId')
                                ? $prodObj->getImagesByProductId($item['product_id'])
                                : [];
                            $imgFile = !empty($prodImages) && !empty($prodImages[0]['image']) ? $prodImages[0]['image'] : '';
                        } elseif (!empty($item['package_id'])) {
                            $imgFile = !empty($pkgDetails['image']) ? $pkgDetails['image'] : '';
                        }
                        // 检查文件是否存在
                        $imgPath = '../uploads/' . $imgFile;
                        if (!empty($imgFile) && file_exists($imgPath)) {
                            echo '<img src="' . $imgPath . '" alt="Product Image">';
                        } else {
                            echo '<img src="../uploads/default.jpg" alt="No Image">';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $payment_fee = isset($orderInfo['payment_fee']) ? floatval($orderInfo['payment_fee']) : 0;
        $delivery_fee = isset($orderInfo['delivery_fee']) ? floatval($orderInfo['delivery_fee']) : 0;

        $orderTotal = 0;
        foreach ($items as $item) {
            $orderTotal += $item['price'] * $item['quantity'];
        }
        $grandTotal = $orderTotal + $payment_fee + $delivery_fee;
        ?>

        <div class="total-box">
            <div class="total-row">
                <span>Order Subtotal:</span>
                <span>RM<?= number_format($orderTotal, 2) ?></span>
            </div>
            
            <?php if ($payment_fee > 0): ?>
                <div class="total-row">
                    <span>Payment Method Fee (<?= htmlspecialchars($orderInfo['payment_method']) ?>):</span>
                    <span>RM<?= number_format($payment_fee, 2) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($delivery_fee > 0): ?>
                <div class="total-row">
                    <span>Delivery Fee (<?= htmlspecialchars($orderInfo['delivery_option']) ?>):</span>
                    <span>RM<?= number_format($delivery_fee, 2) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-row">
                <span>Grand Total:</span>
                <span style="color:var(--primary-color);">RM<?= number_format($grandTotal, 2) ?></span>
            </div>
        </div>

        <div class="action-buttons">
            <a class="button primary" href="orders.php">
                ← Back to Orders
            </a>

            <?php if (isset($orderInfo['status']) && ($orderInfo['status'] === 'Pending' || $orderInfo['status'] === 'Shipping')): ?>
                <?php if ($orderInfo['status'] === 'Pending'): ?>
                    <form action="update_order_status.php" method="POST" style="display:inline;">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <input type="hidden" name="new_status" value="Shipping">
                        <button type="submit" class="button info" onclick="return confirm('Mark this order as shipping?')">Mark as Shipping</button>
                    </form>
                <?php endif; ?>
                <form action="update_order_status.php" method="POST" style="display:inline;">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <button type="submit" class="button success" onclick="return confirm('Mark this order as completed?')">Mark as Completed</button>
                </form>
                <form action="update_order_status.php" method="POST" style="display:inline;">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <input type="hidden" name="new_status" value="Cancelled">
                    <button type="submit" class="button danger" onclick="return confirm('Cancel this order?')">Cancel Order</button>
                </form>
            <?php else: ?>
                <span style="color: #666; font-size:15px; padding:8px 0;">Order is <?= htmlspecialchars($orderInfo['status'] ?? '-') ?></span>
            <?php endif; ?>

            <a class="button info" href="invoice.php?id=<?= $order_id ?>">
                Print Invoice
            </a>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Start with the current count from the server (optional fallback to 0)
        let lastPendingCount = window.__pendingOrdersCount || 0;
        const audio = document.getElementById('orderSound');

        // Unlock audio on first user interaction
        function unlockAudio() {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(()=>{});
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        }
        window.addEventListener('click', unlockAudio);
        window.addEventListener('touchstart', unlockAudio);

        function checkNewOrders() {
            fetch('check_pending_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined') {
                        if (data.count > lastPendingCount) {
                            audio.play().catch(()=>{});
                        }
                        lastPendingCount = data.count;
                    }
                })
                .catch(() => {});
        }

        setInterval(checkNewOrders, 1000); // Check every 1 second
    });
    </script>

    <?php
    require_once "../classes/OrderManager.php";
    $orderManager = new OrderManager();
    $pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
    ?>
    <script>
    window.__pendingOrdersCount = <?= (int)$pendingOrdersCount ?>;
    </script>

    <script>
const noteBox = document.getElementById('order-note');
const statusSpan = document.getElementById('note-status');
let noteTimeout = null;

noteBox.addEventListener('input', function() {
    clearTimeout(noteTimeout);
    statusSpan.textContent = 'Saving...';
    // Wait 700ms after last keystroke before saving
    noteTimeout = setTimeout(() => {
        fetch('update_order_note.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'order_id=' + encodeURIComponent(<?= json_encode($order_id) ?>) + '&note=' + encodeURIComponent(noteBox.value)
        })
        .then(res => res.text())
        .then(txt => {
            statusSpan.textContent = txt; // Show raw response for debugging
            setTimeout(() => { statusSpan.textContent = ''; }, 3000);
        })
        .catch(() => {
            statusSpan.textContent = 'Error saving note';
        });
    }, 700);
});
</script>
</body>
</html>