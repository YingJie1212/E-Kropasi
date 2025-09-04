<?php
session_start();
require_once "../classes/DB.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$pdo = (new DB())->getConnection();

// Pagination setup
$ordersPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $ordersPerPage;

// Get total order count for this user (excluding Pending and Shipping)
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('Pending', 'Shipping')");
$countStmt->execute([$user_id]);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $ordersPerPage);

// Fetch paginated orders (excluding Pending and Shipping)
$stmt = $pdo->prepare("
    SELECT o.*, 
           pm.method_name AS payment_method, 
           d.option_name AS delivery_option
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
    LEFT JOIN delivery_options d ON o.delivery_option_id = d.id
    WHERE o.user_id = ? AND o.status NOT IN ('Pending', 'Shipping')
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $ordersPerPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Color Scheme */
        :root {
            --primary-light: #f9fbe7; /* Light yellow */
            --primary-light-2: #f0f4c3; /* Slightly darker yellow */
            --secondary-light: #dcedc8; /* Light green */
            --secondary-medium: #c5e1a5; /* Medium green */
            --accent-green: #8bc34a; /* Vibrant green */
            --accent-dark: #689f38; /* Darker green */
            --text-primary: #2e3a30; /* Dark green-gray */
            --text-secondary: #5a6b5f; /* Medium green-gray */
            --border-light: #e0e0e0;
            --white: #ffffff;
            --status-pending: #ffb74d;
            --status-shipping: #64b5f6;
            --status-completed: #81c784;
            --status-cancelled: #e57373;
        }

        /* Base Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--primary-light);
            color: var(--text-primary);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }

        /* Typography */
        h1, h2, h3, h4 {
            font-weight: 600;
            color: var(--text-primary);
        }

        h1 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }

        /* Layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Card Component */
        .card {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 800px;
        }

        thead th {
            background-color: var(--secondary-light);
            color: var(--text-primary);
            font-weight: 600;
            text-align: left;
            padding: 1rem 1.25rem;
            position: sticky;
            top: 0;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: var(--primary-light);
        }

        tbody td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }

        .status-pending {
            background-color: var(--status-pending);
        }

        .status-shipping {
            background-color: var(--status-shipping);
        }

        .status-completed {
            background-color: var(--status-completed);
        }

        .status-cancelled {
            background-color: var(--status-cancelled);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background-color: var(--accent-green);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--accent-dark);
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--accent-green);
            color: var(--accent-green);
        }

        .btn-outline:hover {
            background-color: rgba(139, 195, 74, 0.1);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 6px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background-color: var(--secondary-light);
            color: var(--text-primary);
        }

        .page-link.active {
            background-color: var(--accent-green);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--secondary-medium);
            margin-bottom: 1rem;
        }

        .empty-state-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--accent-green);
            text-decoration: none;
            font-weight: 500;
            margin-top: 1.5rem;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--accent-dark);
            text-decoration: underline;
        }

        .back-link svg {
            margin-right: 0.5rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem 1rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            thead th, tbody td {
                padding: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1rem 0.75rem;
            }
            
            .card {
                padding: 1.25rem;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }

        /* Mobile table adjustments */
        @media (max-width: 640px) {
            table {
                min-width: 100%;
            }
            
            thead {
                display: none;
            }
            
            tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid var(--border-light);
                border-radius: 8px;
                padding: 0.75rem;
            }
            
            tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: none;
            }
            
            tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: auto;
                padding-right: 1rem;
                color: var(--text-secondary);
            }
            
            tbody td:last-child {
                padding-bottom: 0;
            }
            
            .status-badge, .btn {
                margin-left: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Your Order History</h1>
            
            <?php if (count($orders) === 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <h3>No Orders Found</h3>
                    <p class="empty-state-text">You haven't placed any orders yet.</p>
                    <a href="products.php" class="btn btn-primary">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
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
                            <?php 
                            $rowNum = ($page - 1) * $ordersPerPage + 1; 
                            foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="#"><?= $rowNum++; ?></td>
                                    <td data-label="Order ID"><?= htmlspecialchars($order['order_number']) ?></td>
                                    <td data-label="Date"><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                    <td data-label="Total (RM)"><?= number_format($order['total_amount'], 2) ?></td>
                                    <td data-label="Status">
                                        <?php if ($order['status'] === 'Completed'): ?>
                                            <span class="status-badge status-completed">Completed</span>
                                        <?php elseif ($order['status'] === 'Cancelled'): ?>
                                            <span class="status-badge status-cancelled">Cancelled</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($order['status']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Payment"><?= htmlspecialchars($order['payment_method']) ?></td>
                                    <td data-label="Delivery"><?= htmlspecialchars($order['delivery_option']) ?></td>
                                    <td data-label="Action">
                                        <a href="receipt.php?order_id=<?= $order['id'] ?>" class="btn btn-outline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page-1 ?>" class="page-link">&laquo;</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item">
                                <a href="?page=<?= $p ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a href="?page=<?= $page+1 ?>" class="page-link">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            
            <a href="products.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Products
            </a>
        </div>
    </div>
</body>
</html>