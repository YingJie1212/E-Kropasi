<?php
session_start();
require_once "../classes/DB.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = (new DB())->getConnection();

// Fetch only Pending and Shipping orders for this user
$stmt = $pdo->prepare("
    SELECT o.*, 
           pm.method_name AS payment_method, 
           d.option_name AS delivery_option
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
    LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
    WHERE o.user_id = ?
      AND (o.status = 'Pending' OR o.status = 'Shipping')
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if any order is Shipping
$hasShippingOrder = false;
foreach ($orders as $order) {
    if ($order['status'] === 'Shipping') {
        $hasShippingOrder = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Active Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern CSS with professional typography and spacing */
        :root {
            /* Color Palette */
            --primary-50: #f9fbe7;
            --primary-100: #f0f4c3;
            --primary-200: #e6ee9c;
            --primary-300: #dce775;
            --primary-400: #d4e157;
            --primary-500: #cddc39;
            --primary-600: #c0ca33;
            --primary-700: #afb42b;
            --primary-800: #9e9d24;
            --primary-900: #827717;
            
            --secondary-50: #e8f5e9;
            --secondary-100: #c8e6c9;
            --secondary-200: #a5d6a7;
            --secondary-300: #81c784;
            --secondary-400: #66bb6a;
            --secondary-500: #4caf50;
            --secondary-600: #43a047;
            --secondary-700: #388e3c;
            --secondary-800: #2e7d32;
            --secondary-900: #1b5e20;
            
            --neutral-50: #fafafa;
            --neutral-100: #f5f5f5;
            --neutral-200: #eeeeee;
            --neutral-300: #e0e0e0;
            --neutral-400: #bdbdbd;
            --neutral-500: #9e9e9e;
            --neutral-600: #757575;
            --neutral-700: #616161;
            --neutral-800: #424242;
            --neutral-900: #212121;
            
            --success: #4caf50;
            --info: #2196f3;
            --warning: #ff9800;
            --error: #f44336;
            
            /* Typography */
            --font-base: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            --font-mono: 'Roboto Mono', monospace;
            
            /* Spacing */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 3rem;
            
            /* Border radius */
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-full: 9999px;
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
            
            /* Transitions */
            --transition-fast: 0.15s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: var(--font-base);
            line-height: 1.6;
            color: var(--neutral-800);
            background-color: var(--primary-50);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: var(--space-md);
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.25;
            margin-bottom: var(--space-md);
            color: var(--neutral-900);
        }
        
        h1 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        p {
            margin-bottom: var(--space-md);
        }
        
        a {
            color: var(--secondary-700);
            text-decoration: none;
            transition: color var(--transition-fast);
        }
        
        a:hover {
            color: var(--secondary-900);
            text-decoration: underline;
        }
        
        /* Layout */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }
        
        .card {
            background-color: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 0.875rem;
            line-height: 1.5;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--secondary-600);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-700);
            text-decoration: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border-color: var(--neutral-300);
            color: var(--neutral-700);
        }
        
        .btn-outline:hover {
            border-color: var(--neutral-400);
            background-color: var(--neutral-50);
            text-decoration: none;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: var(--radius-full);
            text-transform: capitalize;
        }
        
        .badge-primary {
            background-color: var(--secondary-100);
            color: var(--secondary-800);
        }
        
        .badge-success {
            background-color: #e8f5e9;
            color: var(--success);
        }
        
        .badge-info {
            background-color: #e3f2fd;
            color: var(--info);
        }
        
        .badge-warning {
            background-color: #fff8e1;
            color: var(--warning);
        }
        
        .badge-error {
            background-color: #ffebee;
            color: var(--error);
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: var(--space-md);
            font-size: 0.875rem;
        }
        
        .table th {
            text-align: left;
            padding: 0.75rem;
            background-color: var(--primary-100);
            color: var(--neutral-900);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        
        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--neutral-200);
            vertical-align: middle;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover td {
            background-color: var(--primary-50);
        }
        
        /* Alert */
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            display: flex;
            align-items: flex-start;
            position: relative;
        }
        
        .alert-info {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .alert-icon {
            margin-right: var(--space-sm);
            font-size: 1.25rem;
            line-height: 1;
        }
        
        .alert-close {
            position: absolute;
            top: var(--space-sm);
            right: var(--space-sm);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: var(--space-xs);
            color: inherit;
        }
        
        /* Utility classes */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mt-0 {
            margin-top: 0;
        }
        
        .my-2 {
            margin-top: var(--space-md);
            margin-bottom: var(--space-md);
        }
        
        .py-2 {
            padding-top: var(--space-md);
            padding-bottom: var(--space-md);
        }
        
        .d-flex {
            display: flex;
        }
        
        .justify-content-between {
            justify-content: space-between;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table {
                display: block;
            }
            
            .table thead {
                display: none;
            }
            
            .table tr {
                display: block;
                margin-bottom: var(--space-md);
                border: 1px solid var(--neutral-200);
                border-radius: var(--radius-md);
                padding: var(--space-sm);
            }
            
            .table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem;
                border-bottom: 1px solid var(--neutral-200);
            }
            
            .table td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: auto;
                padding-right: var(--space-sm);
                color: var(--neutral-600);
            }
            
            .table td:last-child {
                border-bottom: none;
            }
            
            .alert {
                flex-direction: column;
            }
            
            .alert-icon {
                margin-right: 0;
                margin-bottom: var(--space-sm);
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background: none;
                padding: 0;
                font-size: 12pt;
            }
            
            .card {
                box-shadow: none;
                padding: 0;
            }
            
            .btn {
                display: none;
            }
            
            .table {
                page-break-inside: avoid;
            }
            
            .alert {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php foreach ($orders as $order): ?>
                <?php if ($order['status'] === 'Shipping'): ?>
                    <div id="shipping-notification-<?= htmlspecialchars($order['id']) ?>" class="alert alert-info" data-order-id="<?= htmlspecialchars($order['id']) ?>">
                        <span class="alert-icon">🚚</span>
                        <div>
                            <strong>Order #<?= htmlspecialchars($order['id']) ?> is now Shipping!</strong>
                            <p class="mb-0">Please check your order status for updates.</p>
                            <?php if (strtolower($order['delivery_option']) === 'pickup'): ?>
                                <?php
                                    $shippingDate = !empty($order['status_updated_at']) ? $order['status_updated_at'] : $order['created_at'];
                                    $pickupDate = date('F j, Y', strtotime($shippingDate . ' +3 days'));
                                ?>
                                <p class="mb-0 mt-1">📅 You can come pickup your order on <strong><?= $pickupDate ?></strong></p>
                            <?php endif; ?>
                        </div>
                        <button class="alert-close" aria-label="Close notification">&times;</button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1>My Active Orders</h1>
                <a href="order_history.php" class="btn btn-outline">View Order History</a>
            </div>
            
            <?php if (count($orders) === 0): ?>
                <div class="text-center py-2">
                    <p>You have no pending or shipping orders.</p>
                    <a href="products.php" class="btn btn-primary">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Total (RM)</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Delivery</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rowNum = 1; foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="#"><?= $rowNum++; ?></td>
                                    <td data-label="Order ID"><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td data-label="Date"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                    <td data-label="Total (RM)"><?= number_format($order['total_amount'], 2) ?></td>
                                    <td data-label="Status">
                                        <?php if ($order['status'] === 'Pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php elseif ($order['status'] === 'Shipping'): ?>
                                            <span class="badge badge-info">Shipping</span>
                                        <?php elseif ($order['status'] === 'Completed'): ?>
                                            <span class="badge badge-success">Completed</span>
                                        <?php elseif ($order['status'] === 'Cancelled'): ?>
                                            <span class="badge badge-error">Cancelled</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($order['status']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Payment"><?= htmlspecialchars($order['payment_method']) ?></td>
                                    <td data-label="Delivery"><?= htmlspecialchars($order['delivery_option']) ?></td>
                                    <td data-label="Action">
                                        <a href="receipt.php?order_id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="products.php" class="btn btn-outline">&larr; Back to Products</a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide notifications that have been closed before
        document.querySelectorAll('.alert').forEach(function(notif) {
            var orderId = notif.getAttribute('data-order-id');
            if (localStorage.getItem('hideShippingNotif_' + orderId) === '1') {
                notif.style.display = 'none';
            }
        });

        // Add close event
        document.querySelectorAll('.alert-close').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var notif = btn.closest('.alert');
                var orderId = notif.getAttribute('data-order-id');
                notif.style.display = 'none';
                localStorage.setItem('hideShippingNotif_' + orderId, '1');
            });
        });
    });
    </script>
</body>
</html>