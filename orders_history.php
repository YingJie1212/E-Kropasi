<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";

$db = new DB();
$conn = $db->getConnection();

// Check if it's an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Fetch all delivery options
$deliveryOptions = [];
$stmt = $conn->query("SELECT * FROM delivery_options");
if ($stmt) {
    $deliveryOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 5;
$ordersByDelivery = [];
$ordersCount = [];
$pageByOption = [];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

foreach ($deliveryOptions as $option) {
    $optionName = $option['option_name'];
    $optionId = $option['id'];
    $pageVar = 'page_' . strtolower(preg_replace('/\s+/', '_', $optionName));
    $pageByOption[$optionName] = isset($_GET[$pageVar]) ? max(1, (int)$_GET[$pageVar]) : 1;
    $offset = ($pageByOption[$optionName] - 1) * $itemsPerPage;

    $baseQuery = "FROM orders WHERE delivery_option_id = ? AND status IN ('Completed', 'Cancelled')";
    $params = [$optionId];

    if ($search !== '') {
        $baseQuery .= " AND (
            order_number LIKE ? OR
            student_name LIKE ? OR
            class_name LIKE ? OR
            status LIKE ? OR
            id LIKE ?
        )";
        $searchParam = "%$search%";
        $params = array_merge($params, array_fill(0, 5, $searchParam));
    }

    $orderBy = "ORDER BY created_at DESC";

    $stmt = $conn->prepare("SELECT * $baseQuery $orderBy LIMIT $offset, $itemsPerPage");
    $stmt->execute($params);
    $ordersByDelivery[$optionName] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtCount = $conn->prepare("SELECT COUNT(*) $baseQuery");
    $stmtCount->execute($params);
    $ordersCount[$optionName] = $stmtCount->fetchColumn();
}

// Pagination for user and guest orders
$userPage = isset($_GET['user_page']) ? max(1, (int)$_GET['user_page']) : 1;
$guestPage = isset($_GET['guest_page']) ? max(1, (int)$_GET['guest_page']) : 1;
$userItemsPerPage = 5;
$guestItemsPerPage = 5;

$userOffset = ($userPage - 1) * $userItemsPerPage;
$guestOffset = ($guestPage - 1) * $guestItemsPerPage;

// Count total user and guest orders
$userOrdersCount = $conn->query("SELECT COUNT(*) FROM orders WHERE user_id IS NOT NULL AND status IN ('Completed', 'Cancelled')")->fetchColumn();
$guestOrdersCount = $conn->query("SELECT COUNT(*) FROM orders WHERE guest_id IS NOT NULL AND status IN ('Completed', 'Cancelled')")->fetchColumn();

// Fetch paginated user orders
$userOrdersStmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE user_id IS NOT NULL AND status IN ('Completed', 'Cancelled')
    ORDER BY created_at DESC
    LIMIT :offset, :limit
");
$userOrdersStmt->bindValue(':offset', $userOffset, PDO::PARAM_INT);
$userOrdersStmt->bindValue(':limit', $userItemsPerPage, PDO::PARAM_INT);
$userOrdersStmt->execute();
$userOrders = $userOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch paginated guest orders
$guestOrdersStmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE guest_id IS NOT NULL AND status IN ('Completed', 'Cancelled')
    ORDER BY created_at DESC
    LIMIT :offset, :limit
");
$guestOrdersStmt->bindValue(':offset', $guestOffset, PDO::PARAM_INT);
$guestOrdersStmt->bindValue(':limit', $guestItemsPerPage, PDO::PARAM_INT);
$guestOrdersStmt->execute();
$guestOrders = $guestOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    return preg_replace(
        '/' . preg_quote($search, '/') . '/i',
        '<span style="background:#ffe0b2;">$0</span>',
        htmlspecialchars($text)
    );
}

// If it's an AJAX request, return only the relevant section
if ($isAjax) {
    $section = $_GET['section'] ?? '';
    $searchParam = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
    
    ob_start();
    
    if ($section === 'user_orders') {
        includeUserOrdersSection($userOrders, $userPage, $userOrdersCount, $userItemsPerPage, $guestPage, $searchParam);
    } elseif ($section === 'guest_orders') {
        includeGuestOrdersSection($guestOrders, $guestPage, $guestOrdersCount, $guestItemsPerPage, $userPage, $searchParam);
    }
    
    $content = ob_get_clean();
    echo $content;
    exit;
}

function includeUserOrdersSection($userOrders, $userPage, $userOrdersCount, $userItemsPerPage, $guestPage, $search) {
    ?>
    <div class="delivery-section" id="user-orders-section">
        <div class="delivery-header">
            <h2 class="delivery-title"><i class="fas fa-users"></i> User Orders (Completed & Cancelled)</h2>
            <span class="order-count"><?= $userOrdersCount ?> orders</span>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order Number</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($userOrders)): ?>
                    <tr><td colspan="8" class="no-orders">No user orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($userOrders as $i => $order): ?>
                        <tr>
                            <td><?= (($userPage - 1) * $userItemsPerPage) + $i + 1 ?></td>
                            <td><?= highlight($order['order_number'] ?? $order['id'], $search) ?></td>
                            <td><?= highlight($order['student_name'] ?? 'Unknown', $search) ?></td>
                            <td><?= highlight($order['class_name'] ?? 'Unknown', $search) ?></td>
                            <td>RM<?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($order['status'] === 'Completed'): ?>
                                    <span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>
                                <?php elseif ($order['status'] === 'Cancelled'): ?>
                                    <span class="status-badge status-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($order['status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-view" title="View Order Details">
                                        <i class="fas fa-list"></i> Items
                                    </a>
                                    <a href="view_user_orders.php?user_id=<?= urlencode($order['user_id']) ?>" class="btn btn-history" title="View User History">
                                        <i class="fas fa-user-clock"></i> History
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        $userTotalPages = max(1, ceil($userOrdersCount / $userItemsPerPage));
        if ($userTotalPages > 1): ?>
            <div class="pagination">
                <?php if ($userPage > 1): ?>
                    <a href="#" onclick="loadUserOrders(<?= $userPage - 1 ?>, <?= $guestPage ?>, '<?= $search ?>'); return false;" class="prev">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php 
                $startPage = max(1, $userPage - 2);
                $endPage = min($userTotalPages, $userPage + 2);
                
                if ($startPage > 1) {
                    echo '<a href="#" onclick="loadUserOrders(1, '.$guestPage.', \''.$search.'\'); return false;">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($p = $startPage; $p <= $endPage; $p++) {
                    if ($p == $userPage) {
                        echo '<span class="current">'.$p.'</span>';
                    } else {
                        echo '<a href="#" onclick="loadUserOrders('.$p.', '.$guestPage.', \''.$search.'\'); return false;" class="'.(($p < $startPage + 2 || $p > $endPage - 2) ? '' : 'mobile-hidden').'">'.$p.'</a>';
                    }
                }
                
                if ($endPage < $userTotalPages) {
                    if ($endPage < $userTotalPages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="#" onclick="loadUserOrders('.$userTotalPages.', '.$guestPage.', \''.$search.'\'); return false;">'.$userTotalPages.'</a>';
                }
                ?>
                
                <?php if ($userPage < $userTotalPages): ?>
                    <a href="#" onclick="loadUserOrders(<?= $userPage + 1 ?>, <?= $guestPage ?>, '<?= $search ?>'); return false;" class="next">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function includeGuestOrdersSection($guestOrders, $guestPage, $guestOrdersCount, $guestItemsPerPage, $userPage, $search) {
    ?>
    <div class="delivery-section" id="guest-orders-section">
        <div class="delivery-header">
            <h2 class="delivery-title"><i class="fas fa-user-clock"></i> Guest Orders (Completed & Cancelled)</h2>
            <span class="order-count"><?= $guestOrdersCount ?> orders</span>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order Number</th>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($guestOrders)): ?>
                    <tr><td colspan="8" class="no-orders">No guest orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($guestOrders as $i => $order): ?>
                        <tr>
                            <td><?= (($guestPage - 1) * $guestItemsPerPage) + $i + 1 ?></td>
                            <td><?= highlight($order['order_number'] ?? $order['id'], $search) ?></td>
                            <td><?= highlight($order['student_name'] ?? 'Guest', $search) ?></td>
                            <td><?= highlight($order['class_name'] ?? 'Unknown', $search) ?></td>
                            <td>RM<?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <?php if ($order['status'] === 'Completed'): ?>
                                    <span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>
                                <?php elseif ($order['status'] === 'Cancelled'): ?>
                                    <span class="status-badge status-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($order['status']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></td>
                            <td>
                                <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-view" title="View Order Details">
                                    <i class="fas fa-list"></i> Items
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        $guestTotalPages = max(1, ceil($guestOrdersCount / $guestItemsPerPage));
        if ($guestTotalPages > 1): ?>
            <div class="pagination">
                <?php if ($guestPage > 1): ?>
                    <a href="#" onclick="loadGuestOrders(<?= $userPage ?>, <?= $guestPage - 1 ?>, '<?= $search ?>'); return false;" class="prev">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php 
                $startPage = max(1, $guestPage - 2);
                $endPage = min($guestTotalPages, $guestPage + 2);
                
                if ($startPage > 1) {
                    echo '<a href="#" onclick="loadGuestOrders('.$userPage.', 1, \''.$search.'\'); return false;">1</a>';
                    if ($startPage > 2) {
                        echo '<span class="ellipsis">...</span>';
                    }
                }
                
                for ($p = $startPage; $p <= $endPage; $p++) {
                    if ($p == $guestPage) {
                        echo '<span class="current">'.$p.'</span>';
                    } else {
                        echo '<a href="#" onclick="loadGuestOrders('.$userPage.', '.$p.', \''.$search.'\'); return false;" class="'.(($p < $startPage + 2 || $p > $endPage - 2) ? '' : 'mobile-hidden').'">'.$p.'</a>';
                    }
                }
                
                if ($endPage < $guestTotalPages) {
                    if ($endPage < $guestTotalPages - 1) {
                        echo '<span class="ellipsis">...</span>';
                    }
                    echo '<a href="#" onclick="loadGuestOrders('.$userPage.', '.$guestTotalPages.', \''.$search.'\'); return false;">'.$guestTotalPages.'</a>';
                }
                ?>
                
                <?php if ($guestPage < $guestTotalPages): ?>
                    <a href="#" onclick="loadGuestOrders(<?= $userPage ?>, <?= $guestPage + 1 ?>, '<?= $search ?>'); return false;" class="next">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed & Cancelled Orders | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #e8f5e9;
            --primary-accent: #66bb6a;
            --primary-dark: #43a047;
            --secondary-color: #c8e6c9;
            --text-color: #2e7d32;
            --text-light: #81c784;
            --border-color: #a5d6a7;
            --white: #ffffff;
            --header-bg: #f1f8e9;
            --success-color: #4caf50;
            --success-dark: #388e3c;
            --danger-color: #ef5350;
            --danger-dark: #d32f2f;
            --box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            --border-radius: 4px;
            --transition: all 0.2s;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background-color: var(--header-bg);
            padding: 15px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .breadcrumb {
            font-size: 15px;
            color: var(--text-light);
        }
        
        .breadcrumb a {
            color: var(--text-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        h1, h2, h3 {
            color: var(--text-color);
            font-weight: 600;
        }
        
        h1 {
            font-size: 28px;
            margin: 20px 25px 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-form {
            margin: 0 25px 30px;
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
            background-color: var(--primary-color);
        }
        
        .search-form input:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(102,187,106,0.2);
            background-color: var(--white);
        }
        
        .search-form button {
            padding: 12px 25px;
            background-color: var(--primary-accent);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .search-form button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: var(--primary-color);
            color: var(--text-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: var(--secondary-color);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 13px;
            min-width: 90px;
        }
        
        .status-completed { 
            background: var(--success-color);
            color: white;
        }
        
        .status-cancelled { 
            background: var(--danger-color);
            color: white;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            text-align: center;
            white-space: nowrap;
        }
        
        .btn-view {
            background-color: var(--primary-accent);
            color: white;
        }
        
        .btn-view:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-history {
            background-color: var(--text-light);
            color: white;
        }
        
        .btn-history:hover {
            background-color: var(--text-color);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 25px 0 35px;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            background: var(--primary-color);
            min-width: 36px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
        }
        
        .pagination a:hover {
            background-color: var(--primary-accent);
            color: white;
            border-color: var(--primary-accent);
            transform: translateY(-1px);
        }
        
        .pagination .current {
            background-color: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
        }
        
        .pagination .ellipsis {
            border: none;
            background: transparent;
            pointer-events: none;
            display: flex;
            align-items: center;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 20px 25px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 15px;
            border-radius: var(--border-radius);
        }
        
        .back-link:hover {
            color: var(--text-color);
            background-color: var(--primary-color);
            text-decoration: none;
        }
        
        .no-orders {
            padding: 25px;
            text-align: center;
            color: #666;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            font-style: italic;
        }
        
        .delivery-section {
            margin: 0 20px 40px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .delivery-header {
            background-color: var(--primary-color);
            color: var(--text-color);
            padding: 16px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .delivery-title {
            font-weight: 600;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .order-count {
            background-color: var(--primary-accent);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            color: white;
        }
        
        /* Loading indicator */
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 30px;
            color: var(--text-light);
            font-size: 15px;
        }
        
        .loading-indicator i {
            margin-right: 10px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding: 0;
            }
            
            .header, .search-form, .delivery-section {
                margin-left: 15px;
                margin-right: 15px;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .search-form {
                flex-direction: column;
                gap: 10px;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            /* Improved mobile pagination */
            .pagination {
                gap: 5px;
                padding: 0 10px;
            }
            
            .pagination a, 
            .pagination span {
                padding: 6px 10px;
                font-size: 13px;
                min-width: 32px;
            }
            
            /* Hide some page numbers on small screens */
            .pagination .mobile-hidden {
                display: none;
            }
            
            .btn {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            h1 {
                font-size: 22px;
                margin: 15px;
            }
            
            .delivery-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 12px 15px;
            }
            
            .delivery-title {
                font-size: 16px;
            }
            
            /* Simplified pagination for very small screens */
            .pagination {
                justify-content: space-between;
            }
            
            .pagination a:not(.prev):not(.next),
            .pagination span:not(.current) {
                display: none;
            }
            
            .pagination .current {
                order: 0;
                margin: 0 5px;
            }
            
            .pagination .prev {
                order: -1;
            }
            
            .pagination .next {
                order: 1;
            }
            
            .back-link {
                margin: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a> / Completed & Cancelled Orders
            </div>
        </div>
        
        <h1><i class="fas fa-history"></i> Order History</h1>
        
        <form class="search-form" method="GET" id="search-form">
            <input type="text" name="search" id="search-input" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" placeholder="Search orders by student name, ID, class...">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <div id="loading-indicator" class="loading-indicator" style="display: none;">
            <i class="fas fa-spinner"></i> Loading...
        </div>

        <div id="user-orders-container">
            <?php includeUserOrdersSection($userOrders, $userPage, $userOrdersCount, $userItemsPerPage, $guestPage, $search); ?>
        </div>

        <div id="guest-orders-container">
            <?php includeGuestOrdersSection($guestOrders, $guestPage, $guestOrdersCount, $guestItemsPerPage, $userPage, $search); ?>
        </div>

        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <script>
        // Function to load user orders via AJAX
        function loadUserOrders(userPage, guestPage, search) {
            showLoadingIndicator();
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?user_page=${userPage}&guest_page=${guestPage}&search=${encodeURIComponent(search)}&section=user_orders`, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    document.getElementById('user-orders-container').innerHTML = this.responseText;
                    hideLoadingIndicator();
                    // Update URL without reloading
                    updateUrl(userPage, guestPage, search);
                }
            };
            
            xhr.send();
        }
        
        // Function to load guest orders via AJAX
        function loadGuestOrders(userPage, guestPage, search) {
            showLoadingIndicator();
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `?user_page=${userPage}&guest_page=${guestPage}&search=${encodeURIComponent(search)}&section=guest_orders`, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (this.status === 200) {
                    document.getElementById('guest-orders-container').innerHTML = this.responseText;
                    hideLoadingIndicator();
                    // Update URL without reloading
                    updateUrl(userPage, guestPage, search);
                }
            };
            
            xhr.send();
        }
        
        // Function to handle search form submission
        document.getElementById('search-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const search = document.getElementById('search-input').value.trim();
            
            // Reset to first page for both sections when searching
            loadUserOrders(1, 1, search);
            loadGuestOrders(1, 1, search);
        });
        
        // Function to show loading indicator
        function showLoadingIndicator() {
            document.getElementById('loading-indicator').style.display = 'block';
        }
        
        // Function to hide loading indicator
        function hideLoadingIndicator() {
            document.getElementById('loading-indicator').style.display = 'none';
        }
        
        // Function to update URL without reloading
        function updateUrl(userPage, guestPage, search) {
            let url = new URL(window.location.href);
            url.searchParams.set('user_page', userPage);
            url.searchParams.set('guest_page', guestPage);
            
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            
            window.history.pushState({}, '', url);
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const userPage = urlParams.get('user_page') || 1;
            const guestPage = urlParams.get('guest_page') || 1;
            const search = urlParams.get('search') || '';
            
            document.getElementById('search-input').value = search;
            
            loadUserOrders(userPage, guestPage, search);
            loadGuestOrders(userPage, guestPage, search);
        });
    </script>
</body>
</html>