<?php
require_once "../classes/DB.php";
require_once "../classes/User.php";
require_once "../classes/OrderManager.php";

$db = new DB();
$pdo = $db->getConnection();

$userObj = new User($db); // ✅ Pass the DB object
$orderObj = new OrderManager();

$selectedUserId = $_GET['user_id'] ?? null;
if (!$selectedUserId) {
    echo "<p>No user selected.</p>";
    exit;
}

   $_SESSION['is_admin'] = $is_admin;
    $_SESSION['admin_name'] = $admin_name;

$user = $userObj->find($selectedUserId);

// --- Search logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$allOrders = $orderObj->getOrdersByUserId($selectedUserId);

if ($search) {
    $allOrders = array_filter($allOrders, function($order) use ($search) {
        return stripos($order['status'], $search) !== false ||
               stripos($order['payment_method'], $search) !== false ||
               stripos($order['delivery_option'], $search) !== false ||
               stripos($order['shipping_address'], $search) !== false ||
               stripos($order['created_at'], $search) !== false ||
               stripos($order['id'], $search) !== false;
    });
}

// --- Pagination logic ---
$perPage = 10;
$totalOrders = count($allOrders);
$totalPages = ceil($totalOrders / $perPage);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $perPage;
$orders = array_slice($allOrders, $start, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Order History</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #e8f5e9;
            --primary-light: #f1f8e9;
            --primary-dark: #c8e6c9;
            --accent-color: #4caf50;
            --accent-dark: #388e3c;
            --text-dark: #263238;
            --text-medium: #546e7a;
            --text-light: #90a4ae;
            --white: #ffffff;
            --border-color: #e0e0e0;
            --success-color: #2e7d32;
            --warning-color: #ff8f00;
            --error-color: #c62828;
            --info-color: #0277bd;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f5;
            color: var(--text-dark);
            line-height: 1.6;
            font-weight: 400;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .header {
            background-color: var(--primary-color);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: var(--text-medium);
        }
        
        .breadcrumb a {
            color: var(--accent-color);
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .breadcrumb a:hover {
            color: var(--accent-dark);
            text-decoration: underline;
        }
        
        .page-title {
            padding: 1.5rem 2rem 0;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .user-info {
            padding: 0 2rem;
            color: var(--text-medium);
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }
        
        .search-container {
            padding: 0 2rem 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            max-width: 400px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: var(--white);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }
        
        .search-btn {
            margin-left: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-btn:hover {
            background-color: var(--accent-dark);
        }
        
        .table-container {
            overflow-x: auto;
            padding: 0 2rem;
        }
        
        .order-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .order-table th {
            background-color: var(--primary-light);
            color: var(--text-dark);
            font-weight: 500;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .order-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .order-table tr:last-child td {
            border-bottom: none;
        }
        
        .order-table tr:hover {
            background-color: var(--primary-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
            color: white;
        }
        
        .status-pending {
            background-color: var(--warning-color);
        }
        
        .status-shipping {
            background-color: var(--info-color);
        }
        
        .status-completed {
            background-color: var(--success-color);
        }
        
        .status-cancelled {
            background-color: var(--error-color);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            margin: 0 0.25rem;
            text-decoration: none;
            color: var(--text-medium);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background-color: var(--primary-color);
            color: var(--accent-dark);
            border-color: var(--accent-color);
        }
        
        .pagination .active {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .no-orders {
            padding: 2rem;
            text-align: center;
            color: var(--text-medium);
        }
        
        .back-link {
            display: inline-block;
            margin: 0 2rem 2rem;
            color: var(--accent-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--accent-dark);
            text-decoration: underline;
        }
        
        .amount {
            font-weight: 500;
            color: var(--text-dark);
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                border-radius: 6px;
            }
            
            .header, .page-title, .user-info, .search-container, .table-container {
                padding: 1rem;
            }
            
            .search-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                max-width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .search-btn {
                margin-left: 0;
                width: 100%;
            }
            
            .pagination {
                flex-wrap: wrap;
                padding: 1rem;
            }
            
            .pagination a, .pagination span {
                margin: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="view_user.php">User Management</a> &rsaquo; Order History
            </div>
        </div>
        
        <h1 class="page-title">Order History</h1>
        
        <div class="user-info">
            Student: <strong><?= htmlspecialchars($user['name']) ?></strong> (ID: <?= htmlspecialchars($user['student_id']) ?>)
        </div>
        
        <form class="search-container" method="get">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedUserId) ?>">
            <input type="text" name="search" class="search-input" placeholder="Search orders..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>
        
        <div class="table-container">
            <?php if ($orders): ?>
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Delivery</th>
                            <th>Shipping Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 1; foreach ($orders as $order): ?>
                            <tr>
                                <td><?= $rowNum++; ?></td>
                                <td><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= date('M j, Y H:i', strtotime($order['created_at'])) ?></td>
                                <td class="amount">RM<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <?php if ($order['status'] === 'Pending'): ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php elseif ($order['status'] === 'Shipping'): ?>
                                        <span class="status-badge status-shipping">Shipping</span>
                                    <?php elseif ($order['status'] === 'Completed'): ?>
                                        <span class="status-badge status-completed">Completed</span>
                                    <?php elseif ($order['status'] === 'Cancelled'): ?>
                                        <span class="status-badge status-cancelled">Cancelled</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($order['status']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order['payment_method']) ?></td>
                                <td><?= htmlspecialchars($order['delivery_option']) ?></td>
                                <td><?= htmlspecialchars($order['shipping_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-orders">
                    <p>No orders found for this customer.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?user_id=<?= $selectedUserId ?>&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                        &laquo; Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="active"><?= $p ?></span>
                    <?php else: ?>
                        <a href="?user_id=<?= $selectedUserId ?>&page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?user_id=<?= $selectedUserId ?>&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <a href="view_user.php" class="back-link">&larr; Back to User Management</a>
    </div>
</body>
</html>