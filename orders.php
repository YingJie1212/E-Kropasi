<?php
session_start();
require_once "../classes/OrderManager.php";
require_once "../classes/DB.php";
require_once "../classes/Product.php";
require_once "../classes/DB.php";

// Initialize classes
$orderManager = new OrderManager();
$product = new Product();

// Fetch necessary data
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
$lowStockCount = $product->countLowStock(10); // 10 is the threshold

// Create an instance of your database connection class
$db = new DB();
$conn = $db->getConnection();

// Get admin_id from session and fetch admin info
$admin_id = $_SESSION['admin_id']; // Get from session

// Fetch admin info from DB using PDO
$stmt = $conn->prepare("SELECT id, name, email, is_admin FROM users WHERE id = :id");
$stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
$stmt->execute();
$adminData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($adminData) {
    $admin_id = $adminData['id']; // Ensure we have the ID
    $admin_name = $adminData['name'];
    $admin_email = $adminData['email'];
    $is_admin = $adminData['is_admin'];
    $_SESSION['is_admin'] = $is_admin;
} else {
    die("No admin data found.");
}

// Set role based on is_admin value
if ($is_admin == 2) {
    $admin_role = "Super Administrator";
} elseif ($is_admin == 1) {
    $admin_role = "Administrator";
} else {
    $admin_role = "User";
}

$last_login = date("M j, Y g:i A", strtotime("-1 hour"));

// Fetch all delivery options
$deliveryOptions = [];
$stmt = $conn->query("SELECT * FROM delivery_options");
if ($stmt) {
    $deliveryOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if this is an AJAX request for a specific table
if (isset($_GET['ajax'])) {
    $optionId = isset($_GET['option_id']) ? (int)$_GET['option_id'] : 0;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $itemsPerPage = 5;
    $offset = ($page - 1) * $itemsPerPage;

    // Status filter
    $statusFilter = " AND (status = 'Pending' OR status = 'Shipping')";

    // Build base query
    $baseQuery = "FROM orders WHERE delivery_option_id = ?";
    $params = [$optionId];
    $baseQuery .= $statusFilter;

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

    // Order by clause: Pending first, then Shipping, then by created_at ASC
    $orderBy = "ORDER BY 
        CASE 
            WHEN status = 'Pending' THEN 0
            WHEN status = 'Shipping' THEN 1
            ELSE 2
        END,
        created_at ASC";

    // Get orders
    $stmt = $conn->prepare("SELECT * $baseQuery $orderBy LIMIT $offset, $itemsPerPage");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $stmtCount = $conn->prepare("SELECT COUNT(*) $baseQuery");
    $stmtCount->execute($params);
    $totalOrders = $stmtCount->fetchColumn();
    $totalPages = ceil($totalOrders / $itemsPerPage);

    // Get delivery option name
    $optionName = '';
    $stmtOption = $conn->prepare("SELECT option_name FROM delivery_options WHERE id = ?");
    $stmtOption->execute([$optionId]);
    if ($row = $stmtOption->fetch(PDO::FETCH_ASSOC)) {
        $optionName = $row['option_name'];
        $displayName = ucfirst(str_replace('_', ' ', $optionName));
    }

    // Output the cards HTML
    ?>
    <div class="orders-cards-container" data-option-id="<?= $optionId ?>" data-total-orders="<?= $totalOrders ?>">
        <?php if (empty($orders)): ?>
            <div class="empty-state-card">
                <i class="fas fa-box-open"></i>
                <p>No orders found for this delivery method.</p>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $i => $order): ?>
                <div class="order-card status-<?= strtolower($order['status']) ?>">
                    <div class="card-header">
                        <div class="order-id">#<?= highlight($order['order_number'] ?? $order['id'], $search) ?></div>
                        <div class="order-status">
                            <span class="status-badge"><?= htmlspecialchars($order['status']) ?></span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="order-info">
                            <div class="info-row">
                                <span class="info-label">Student:</span>
                                <span class="info-value"><?= highlight($order['student_name'] ?? 'Guest', $search) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Class:</span>
                                <span class="info-value"><?= highlight($order['class_name'] ?? 'Unknown', $search) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Amount:</span>
                                <span class="info-value"><?= highlight('RM' . number_format($order['total_amount'], 2), $search) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date:</span>
                                <span class="info-value"><?= highlight(date('Y-m-d H:i', strtotime($order['created_at'])), $search) ?></span>
                            </div>
                        </div>
                        
                        <div class="order-note">
                            <label>Note:</label>
                            <div class="note-input-container">
                                <input 
                                    type="text" 
                                    class="order-note-input" 
                                    data-order-id="<?= $order['id'] ?>" 
                                    value="<?= htmlspecialchars($order['note'] ?? '') ?>" 
                                    placeholder="Type a note..."
                                >
                                <button class="save-note-btn" data-order-id="<?= $order['id'] ?>">
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="action-buttons">
                            <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> <span class="btn-text">View</span>
                            </a>
                            
                            <?php if ($order['status'] === 'Pending'): ?>
                                <form action="update_orders_status.php" method="POST" class="action-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="Shipping">
                                    <input type="hidden" name="ajax" value="1">
                                    <button type="submit" class="action-btn btn-shipping">
                                        <i class="fas fa-truck"></i> <span class="btn-text">Ship</span>
                                    </button>
                                </form>
                                <form action="update_orders_status.php" method="POST" class="action-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="Completed">
                                    <input type="hidden" name="ajax" value="1">
                                    <button type="submit" class="action-btn btn-complete">
                                        <i class="fas fa-check"></i> <span class="btn-text">Complete</span>
                                    </button>
                                </form>
                                <form action="update_orders_status.php" method="POST" class="action-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="Cancelled">
                                    <input type="hidden" name="ajax" value="1">
                                    <button type="submit" class="action-btn btn-cancel">
                                        <i class="fas fa-times"></i> <span class="btn-text">Cancel</span>
                                    </button>
                                </form>
                            <?php elseif ($order['status'] === 'Shipping'): ?>
                                <form action="update_orders_status.php" method="POST" class="action-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="Completed">
                                    <input type="hidden" name="ajax" value="1">
                                    <button type="submit" class="action-btn btn-complete">
                                        <i class="fas fa-check"></i> <span class="btn-text">Complete</span>
                                    </button>
                                </form>
                                <form action="update_orders_status.php" method="POST" class="action-form">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="Cancelled">
                                    <button type="submit" class="action-btn btn-cancel">
                                        <i class="fas fa-times"></i> <span class="btn-text">Cancel</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination" data-option-id="<?= $optionId ?>">
                <?php if ($page > 1): ?>
                    <a href="#" data-page="1">
                        <i class="fas fa-angle-double-left"></i> First
                    </a>
                    <a href="#" data-page="<?= $page - 1 ?>">
                        <i class="fas fa-angle-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="#" data-page="<?= $p ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="#" data-page="<?= $page + 1 ?>">
                        Next <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="#" data-page="<?= $totalPages ?>">
                        Last <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    exit();
}

function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    return preg_replace(
        '/' . preg_quote($search, '/') . '/i',
        '<span style="background:#ffe0b2;">$0</span>',
        htmlspecialchars($text)
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Order Management | School Project</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Primary Color Palette - Green */
            --primary-50: #f0fdf4;
            --primary-100: #dcfce7;
            --primary-200: #bbf7d0;
            --primary-300: #86efac;
            --primary-400: #4ade80;
            --primary-500: #22c55e;
            --primary-600: #16a34a;
            --primary-700: #15803d;
            --primary-800: #166534;
            --primary-900: #14532d;

            /* Secondary Color Palette - Yellow */
            --secondary-50: #fefce8;
            --secondary-100: #fef9c3;
            --secondary-200: #fef08a;
            --secondary-300: #fde047;
            --secondary-400: #facc15;
            --secondary-500: #eab308;
            --secondary-600: #ca8a04;
            --secondary-700: #a16207;
            --secondary-800: #854d0e;
            --secondary-900: #713f12;

            /* Neutral Colors */
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --black: #000000;

            /* Semantic Colors */
            --success: var(--primary-500);
            --warning: var(--secondary-500);
            --danger: #ef4444;
            --info: #3b82f6;

            /* Shadows */
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);

            /* Border Radius */
            --radius-sm: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            --radius-full: 9999px;

            /* Spacing */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 2.5rem;
            --space-3xl: 3rem;

            /* Transitions */
            --transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

            /* Layout */
            --sidebar-width: 16rem;
            --header-height: 5rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Typography */
        h1, h2, h3 {
            font-weight: 600;
            line-height: 1.25;
            color: var(--gray-900);
        }

        h1 {
            font-size: 2rem;
            margin-bottom: var(--space-md);
        }

        h2 {
            font-size: 1.5rem;
            margin-bottom: var(--space-sm);
        }

        h3 {
            font-size: 1.25rem;
            margin-bottom: var(--space-sm);
        }

        p {
            margin-bottom: var(--space-sm);
        }

        a {
            color: var(--primary-600);
            text-decoration: none;
            transition: var(--transition);
        }

        a:hover {
            color: var(--primary-700);
        }

        /* Layout Components */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-md);
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background-color: var(--white);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            padding: 0 var(--space-lg);
            z-index: 100;
            border-bottom: 1px solid var(--gray-200);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-700);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition);
        }

        .user-button:hover {
            background-color: var(--gray-100);
        }

        .user-avatar {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-700);
            font-weight: 600;
            font-size: 1rem;
        }

        .user-avatar.online::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: var(--radius-full);
            background-color: var(--success);
            border: 2px solid var(--white);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            margin-right: var(--space-sm);
            text-align: left;
        }

        .user-name {
            font-weight: 500;
            font-size: 1rem;
            color: var(--gray-800);
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            width: 18rem;
            padding: var(--space-sm) 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(0.5rem);
            transition: var(--transition-slow);
            z-index: 110;
            border: 1px solid var(--gray-200);
        }

        .dropdown-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-md);
            border-bottom: 1px solid var(--gray-200);
        }

        .dropdown-avatar {
            width: 3rem;
            height: 3rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-700);
            font-weight: 600;
            font-size: 1.125rem;
        }

        .dropdown-user-info {
            flex: 1;
            min-width: 0;
        }

        .dropdown-name {
            font-weight: 600;
            font-size: 1.0625rem;
            color: var(--gray-900);
            margin-bottom: 0.125rem;
        }

        .dropdown-email {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-meta {
            font-size: 0.8125rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .dropdown-meta i {
            color: var(--primary-500);
            font-size: 0.8125rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: var(--space-sm) 0;
            border: none;
        }

        .dropdown-item {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            margin: 0 var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--gray-700);
            font-size: 0.9375rem;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--primary-700);
        }

        .dropdown-item i {
            color: var(--gray-500);
            font-size: 1.125rem;
            width: 1.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .dropdown-item:hover i {
            color: var(--primary-500);
        }

        .dropdown-footer {
            padding: var(--space-sm) var(--space-md);
            font-size: 0.8125rem;
            color: var(--gray-50);
            background: var(--gray-50);
            border-radius: 0 0 var(--radius-md) var(--radius-md);
            text-align: center;
            margin-top: var(--space-xs);
            border-top: 1px solid var(--gray-200);
        }

        /* Layout Components */
        .main-layout {
            display: flex;
            min-height: calc(100vh - var(--header-height));
            margin-top: var(--header-height);
            position: relative;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--white);
            border-right: 1px solid var(--gray-200);
            padding: var(--space-lg) 0;
            display: flex;
            flex-direction: column;
            transition: var(--transition-slow);
            z-index: 90;
            overflow-y: auto;
            position: fixed;
            top: var(--header-height);
            bottom: 0;
            left: 0;
        }

        .sidebar-header {
            padding: 0 var(--space-lg) var(--space-lg);
            border-bottom: 1px solid var(--gray-200);
        }

        .sidebar-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-sm);
        }

        .nav-menu {
            flex: 1;
            padding: var(--space-lg) 0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .nav-group {
            margin-bottom: var(--space-lg);
            position: relative;
        }

        .nav-group-title {
            padding: 0 var(--space-lg) var(--space-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: var(--space-sm) var(--space-lg);
            color: var(--gray-600);
            font-size: 0.9375rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
        }

        .nav-item:hover {
            color: var(--primary-600);
            background-color: var(--primary-50);
        }

        .nav-item.active {
            color: var(--primary-700);
            background-color: var(--primary-50);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background-color: var(--primary-500);
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
            transition: var(--transition);
        }

        .nav-item i {
            width: 1.75rem;
            font-size: 1.25rem;
            margin-right: var(--space-sm);
            color: var(--gray-500);
            transition: var(--transition);
        }

        .nav-item:hover i,
        .nav-item.active i {
            color: var(--primary-500);
        }

        .nav-badge {
            margin-left: auto;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            color: var(--primary-700);
            font-size: 0.8125rem;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: var(--space-xl);
            margin-left: var(--sidebar-width);
            background-color: var(--gray-50);
            min-height: calc(100vh - var(--header-height));
            transition: var(--transition);
        }

        .page-header {
            margin-bottom: var(--space-xl);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }

        /* Orders Management Specific Styles */
        .search-form {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
        }

        @media (min-width: 768px) {
            .search-form {
                flex-direction: row;
                align-items: center;
            }
        }

        .search-input-container {
            flex: 1;
        }

        .search-form input[type="text"] {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
        }

        .search-form input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }

        .search-buttons {
            display: flex;
            gap: var(--space-sm);
        }

        .search-form button, .btn-history {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .search-form button {
            background-color: var(--primary-600);
            color: var(--white);
        }

        .search-form button:hover {
            background-color: var(--primary-700);
        }

        .btn-history {
            background-color: var(--gray-200);
            color: var(--gray-800);
            text-decoration: none;
        }

        .btn-history:hover {
            background-color: var(--gray-300);
        }

        /* Orders Cards */
        .orders-cards-container {
            margin-bottom: var(--space-xl);
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .order-card {
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
        }

        .order-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .order-card.status-pending {
            border-left: 4px solid var(--warning);
        }

        .order-card.status-shipping {
            border-left: 4px solid var(--info);
        }

        .order-card.status-completed {
            border-left: 4px solid var(--success);
        }

        .order-card.status-cancelled {
            border-left: 4px solid var(--danger);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--space-sm) var(--space-md);
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
        }

        .order-id {
            font-weight: 600;
            color: var(--gray-700);
        }

        .card-body {
            padding: var(--space-md);
        }

        .order-info {
            display: grid;
            gap: var(--space-sm);
            margin-bottom: var(--space-md);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
        }

        .info-label {
            font-weight: 500;
            color: var(--gray-600);
        }

        .info-value {
            color: var(--gray-800);
            text-align: right;
        }

        .order-note {
            margin-top: var(--space-md);
        }

        .order-note label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 500;
            color: var(--gray-600);
        }

        .note-input-container {
            display: flex;
            gap: var(--space-xs);
        }

        .order-note-input {
            flex: 1;
            padding: var(--space-xs) var(--space-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
        }

        .save-note-btn {
            padding: var(--space-xs) var(--space-sm);
            background-color: var(--primary-500);
            color: var(--white);
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .save-note-btn:hover {
            background-color: var(--primary-600);
        }

        .card-footer {
            padding: var(--space-sm) var(--space-md);
            background-color: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-xs);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending .status-badge {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-shipping .status-badge {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-completed .status-badge {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-cancelled .status-badge {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Empty State */
        .empty-state-card {
            padding: var(--space-xl);
            text-align: center;
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .empty-state-card i {
            font-size: 2rem;
            color: var(--gray-400);
            margin-bottom: var(--space-sm);
        }

        .empty-state-card p {
            color: var(--gray-500);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            gap: var(--space-xs);
        }

        .btn-view {
            background-color: var(--primary-600);
            color: var(--white);
        }

        .btn-view:hover {
            background-color: var(--primary-700);
        }

        /* Action Buttons */
        .action-form {
            display: inline;
        }

        .action-btn {
            padding: var(--space-xs) var(--space-sm);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            flex: 1;
            min-width: 0;
            text-align: center;
            justify-content: center;
        }

        .btn-shipping {
            background-color: var(--warning);
            color: var(--white);
        }

        .btn-shipping:hover {
            background-color: var(--secondary-600);
        }

        .btn-complete {
            background-color: var(--success);
            color: var(--white);
        }

        .btn-complete:hover {
            background-color: var(--primary-700);
        }

        .btn-cancel {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-cancel:hover {
            background-color: #dc2626;
        }

        /* Button text for mobile */
        .btn-text {
            display: inline;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--space-xs);
            margin-top: var(--space-md);
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .pagination a {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        .pagination a:hover {
            background-color: var(--gray-200);
        }

        .pagination .current {
            background-color: var(--primary-600);
            color: var(--white);
        }

        /* Confirmation Dialog */
        .confirmation-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .dialog-content {
            background-color: var(--white);
            padding: var(--space-lg);
            border-radius: var(--radius-lg);
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .dialog-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-sm);
            color: var(--gray-900);
        }

        .dialog-message {
            margin-bottom: var(--space-lg);
            color: var(--gray-600);
        }

        .dialog-buttons {
            display: flex;
            justify-content: flex-end;
            gap: var(--space-sm);
        }

        .dialog-btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .dialog-btn-cancel {
            background-color: var(--gray-100);
            color: var(--gray-700);
            border: none;
        }

        .dialog-btn-cancel:hover {
            background-color: var(--gray-200);
        }

        .dialog-btn-confirm {
            background-color: var(--danger);
            color: var(--white);
            border: none;
        }

        .dialog-btn-confirm:hover {
            background-color: #dc2626;
        }

        /* Footer */
        .footer {
            padding: var(--space-lg);
            background-color: var(--white);
            color: var(--gray-500);
            font-size: 0.9375rem;
            border-top: 1px solid var(--gray-200);
            margin-left: var(--sidebar-width);
            transition: var(--transition);
        }

        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-md);
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.5rem;
            cursor: pointer;
            padding: var(--space-sm);
            margin-right: var(--space-sm);
        }

        /* Overlay for mobile menu */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 80;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition-slow);
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .main-content {
                padding: var(--space-lg);
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .footer {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 var(--space-md);
            }

            .logo-text {
                font-size: 1.25rem;
            }

            .page-header {
                margin-bottom: var(--space-lg);
            }

            .page-title {
                font-size: 1.5rem;
            }

            .search-buttons {
                width: 100%;
            }

            .search-form button, .btn-history {
                flex: 1;
            }

            .orders-grid {
                grid-template-columns: 1fr;
            }
        }

    @media (max-width: 768px) {
            .header {
                height: 4rem;
            }

            .main-layout {
                margin-top: 4rem;
                min-height: calc(100vh - 4rem);
            }

            .sidebar {
                top: 4rem;
                width: 16rem;
            }

            .user-details {
                display: none;
            }

            .logo-text {
                display: none;
            }

            .main-content {
                padding: var(--space-md);
            }

            .page-title {
                font-size: 1.25rem;
            }

            .page-subtitle {
                font-size: 0.875rem;
            }

            /* 优化移动端按钮样式，保持横向紧凑排列 */
            .action-buttons {
                display: flex;
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 0.5rem;
                justify-content: flex-start;
                align-items: center;
            }
            .action-btn {
                padding: 0.5rem 0.7rem;
                font-size: 1rem;
                min-width: 2.2rem;
                width: auto;
                border-radius: 0.4rem;
                flex: none;
            }
            .btn-text {
                display: inline;
            }
            .action-btn i {
                margin-right: 0.3rem;
                font-size: 1.1rem;
            }
            .btn-view {
                padding: 0.5rem 0.7rem;
                font-size: 1rem;
                min-width: 2.2rem;
                width: auto;
                border-radius: 0.4rem;
                flex: none;
                margin-bottom: 0;
                justify-content: center;
            }
            .card-header, .card-body, .card-footer {
                padding: var(--space-sm);
            }
            .note-input-container {
                flex-direction: row;
                gap: var(--space-xs);
                align-items: center;
            }
            .save-note-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 0.7rem;
                font-size: 1.1rem;
                min-width: 2.2rem;
                width: auto;
                border-radius: 0.4rem;
                margin: 0 auto;
            }
        }

        /* Special styles for very small screens */
        @media (max-width: 400px) {
            .action-buttons {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .action-btn {
                width: auto;
                flex: 1;
                min-width: 60px;
            }

            .btn-view {
                width: 100%;
                margin-bottom: var(--space-xs);
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="header-content">
            <button class="menu-toggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="logo">
                <span class="logo-text">SMJK PHOR TAY</span>
            </div>

            <div class="header-actions">
                <div class="user-dropdown">
                    <button class="user-button" aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                        <div class="user-avatar online">
                            <span><?= substr($admin_name, 0, 1) ?></span>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= htmlspecialchars($admin_name) ?></span>
                            <span class="user-role"><?= htmlspecialchars($admin_role) ?></span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.875rem;"></i>
                    </button>

                    <div class="dropdown-menu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span><?= substr($admin_name, 0, 1) ?></span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name"><?= htmlspecialchars($admin_name) ?></div>
                                <div class="dropdown-email"><?= htmlspecialchars($admin_email) ?></div>
                                <div class="dropdown-meta">
                                    <i class="fas fa-shield-alt"></i>
                                    <span><?= htmlspecialchars($admin_role) ?></span>
                                    <i class="fas fa-circle" style="font-size: 0.25rem;"></i>
                                    <i class="fas fa-clock"></i>
                                    <span>Last login: <?= htmlspecialchars($last_login) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="edit_admin.php?id=<?= urlencode($admin_id) ?>" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            <span>Account Settings</span>
                        </a>

                        <?php if ($is_admin == 2): ?>
                            <a href="system_settings.php" class="dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span>School Settings</span>
                            </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>

                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>

                        <div class="dropdown-footer">
                            Session expires in 30 minutes
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Main Navigation</div>
            </div>

            <nav class="nav-menu">
                <div class="nav-group">
                    <div class="nav-group-title">Management</div>

                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>

                    <a href="view_products.php" class="nav-item">
                        <i class="fas fa-box-open"></i>
                        <span>Products</span>
                        <?php if ($lowStockCount > 0): ?>
                            <span class="nav-badge"><?= $lowStockCount ?> Low</span>
                        <?php endif; ?>
                    </a>

                    <a href="view_categories.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>

                    <a href="counter.php" class="nav-item">
                        <i class="fas fa-cash-register"></i>
                        <span>Counter</span>
                    </a>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Administration</div>

                    <a href="view_user.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span>Students</span>
                    </a>

                    <a href="view_admin.php" class="nav-item">
                        <i class="fas fa-user-shield"></i>
                        <span>Admins</span>
                    </a>

                    <a href="orders.php" class="nav-item active">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                        <?php if ($pendingOrdersCount > 0): ?>
                            <span class="nav-badge"><?= $pendingOrdersCount ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="view_package.php" class="nav-item">
                        <i class="fas fa-boxes"></i>
                        <span>Packages</span>
                    </a>
                    
                     <a href="admin_monthly_report.php" class="nav-item ">
                        <i class="fas fa-chart-bar"></i>
                        <span>Monthly Report</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Order Management</h1>
                <p class="page-subtitle">Manage and process student orders</p>
            </div>

            <form class="search-form" method="GET" id="searchForm">
                <input type="hidden" name="ajax" value="1">
                <div class="search-input-container">
                    <input type="text" name="search" id="searchInput" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" placeholder="Search orders by student name, ID...">
                </div>
                <div class="search-buttons">
                    <button type="submit" id="searchBtn"><i class="fas fa-search"></i> Search</button>
                    <button type="button" id="clearSearchBtn"><i class="fas fa-times"></i> Clear</button>
                    <a href="orders_history.php" class="btn-history"><i class="fas fa-history"></i> Order History</a>
                </div>
            </form>

            <div id="ordersContainer">
                <?php foreach ($deliveryOptions as $option): ?>
                    <?php
                    $optionId = $option['id'];
                    $optionName = $option['option_name'];
                    $displayName = ucfirst(str_replace('_', ' ', $optionName));
                    ?>
                    <div class="orders-section" data-option-id="<?= $optionId ?>">
                        <h3><?= htmlspecialchars($displayName) ?> <span class="order-count" data-option-id="<?= $optionId ?>"></span></h3>
                        <div class="orders-cards-container" data-option-id="<?= $optionId ?>">
                            <!-- Content will be loaded via AJAX -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-content">
            <div>© <?= date('Y') ?> SMJK Phor Tay. All rights reserved.</div>
        </div>
    </footer>

    <!-- Confirmation Dialog -->
    <div class="confirmation-dialog" id="confirmationDialog">
        <div class="dialog-content">
            <div class="dialog-title" id="dialogTitle">Confirm Action</div>
            <div class="dialog-message" id="dialogMessage">Are you sure you want to perform this action?</div>
            <div class="dialog-buttons">
                <button class="dialog-btn dialog-btn-cancel" id="dialogCancel">Cancel</button>
                <button class="dialog-btn dialog-btn-confirm" id="dialogConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmationDialog = document.getElementById('confirmationDialog');
        const dialogTitle = document.getElementById('dialogTitle');
        const dialogMessage = document.getElementById('dialogMessage');
        const dialogCancel = document.getElementById('dialogCancel');
        const dialogConfirm = document.getElementById('dialogConfirm');
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const ordersContainer = document.getElementById('ordersContainer');
        let currentForm = null;

        // Toggle sidebar on mobile
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });

        // Toggle user dropdown
        const userDropdown = document.querySelector('.user-dropdown');
        const userButton = userDropdown.querySelector('.user-button');
        const dropdownMenu = userDropdown.querySelector('.dropdown-menu');

        userButton.addEventListener('click', function(e) {
            e.stopPropagation();
            const isExpanded = userButton.getAttribute('aria-expanded') === 'true';
            userButton.setAttribute('aria-expanded', !isExpanded);
            dropdownMenu.classList.toggle('active', !isExpanded);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('.user-button')) {
                userButton.setAttribute('aria-expanded', 'false');
                dropdownMenu.classList.remove('active');
            }
        });

        // Load orders for a specific delivery option
        function loadOrders(optionId, page = 1, search = '') {
            const container = document.querySelector(`.orders-cards-container[data-option-id="${optionId}"]`);
            const countElement = document.querySelector(`.order-count[data-option-id="${optionId}"]`);
            
            const params = new URLSearchParams();
            params.append('ajax', '1');
            params.append('option_id', optionId);
            params.append('page', page);
            if (search) {
                params.append('search', search);
            }
            
            fetch(`?${params.toString()}`)
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                    
                    // Update count if available
                    const countMatch = html.match(/data-total-orders="(\d+)"/);
                    if (countMatch) {
                        const totalOrders = countMatch[1];
                        countElement.textContent = `(${totalOrders} orders)`;
                    }
                    
                    // Setup pagination for this table
                    setupPagination(optionId, search);
                    
                    // Setup action forms
                    setupActionForms();
                    
                    // Setup note saving functionality
                    setupNoteSaving();
                })
                .catch(error => {
                    console.error('Error loading orders:', error);
                    container.innerHTML = `<div class="empty-state-card"><i class="fas fa-exclamation-circle"></i><p>Error loading orders. Please try again.</p></div>`;
                });
        }

        // Setup pagination event listeners
        function setupPagination(optionId, search = '') {
            const pagination = document.querySelector(`.pagination[data-option-id="${optionId}"]`);
            if (!pagination) return;
            
            const links = pagination.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.getAttribute('data-page');
                    loadOrders(optionId, page, search);
                    
                    // Update URL without reload
                    updateUrlParam(`page_${optionId}`, page);
                });
            });
        }

        // Setup action form event listeners
        function setupActionForms() {
            document.querySelectorAll('.action-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const action = this.querySelector('button[type="submit"]').textContent.trim();
                    const message = `Are you sure you want to ${action} this order?`;
                    
                    showConfirmation('Confirm Action', message, () => {
                        const formData = new FormData(this);
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Reload the orders table
                                const optionId = this.closest('.orders-section').dataset.optionId;
                                const search = searchInput.value.trim();
                                const currentPage = getCurrentPageForOption(optionId);
                                loadOrders(optionId, currentPage, search);
                            } else {
                                alert(data.message || 'Error processing request');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                    });
                });
            });
        }

        // Setup note saving functionality
        function setupNoteSaving() {
            document.querySelectorAll('.save-note-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    const input = document.querySelector(`.order-note-input[data-order-id="${orderId}"]`);
                    const note = input.value.trim();
                    
                    // Send AJAX request to save the note
                    fetch('save_order_note.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'order_id=' + encodeURIComponent(orderId) + '&note=' + encodeURIComponent(note) + '&ajax=1'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Note saved successfully');
                        } else {
                            alert(data.message || 'Error saving note');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    });
                });
            });
        }

        // Get current page for a delivery option
        function getCurrentPageForOption(optionId) {
            const pagination = document.querySelector(`.pagination[data-option-id="${optionId}"]`);
            if (pagination) {
                const currentPageSpan = pagination.querySelector('.current');
                if (currentPageSpan) {
                    return parseInt(currentPageSpan.textContent);
                }
            }
            return 1;
        }

        // Update URL parameter without reload
        function updateUrlParam(key, value) {
            const params = new URLSearchParams(window.location.search);
            if (value) {
                params.set(key, value);
            } else {
                params.delete(key);
            }
            history.pushState(null, '', `?${params.toString()}`);
        }

        // Show confirmation dialog
        function showConfirmation(title, message, callback) {
            dialogTitle.textContent = title;
            dialogMessage.textContent = message;
            confirmationDialog.style.display = 'flex';
            
            dialogConfirm.onclick = function() {
                callback();
                confirmationDialog.style.display = 'none';
            };
            
            dialogCancel.onclick = function() {
                confirmationDialog.style.display = 'none';
            };
        }

        // Clear search
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            const search = '';
            
            // Load all tables with empty search
            document.querySelectorAll('.orders-section').forEach(section => {
                const optionId = section.dataset.optionId;
                loadOrders(optionId, 1, search);
            });
            
            // Update URL
            updateUrlParam('search', '');
        });

        // Handle search form submission
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const search = searchInput.value.trim();
            
            // Load all tables with search term
            document.querySelectorAll('.orders-section').forEach(section => {
                const optionId = section.dataset.optionId;
                loadOrders(optionId, 1, search);
            });
            
            // Update URL
            updateUrlParam('search', search);
        });

        // Handle popstate (back/forward navigation)
        window.addEventListener('popstate', function() {
            const params = new URLSearchParams(window.location.search);
            const search = params.get('search') || '';
            searchInput.value = search;
            
            document.querySelectorAll('.orders-section').forEach(section => {
                const optionId = section.dataset.optionId;
                const pageVar = `page_${optionId}`;
                const page = params.get(pageVar) || 1;
                loadOrders(optionId, page, search);
            });
        });

        // Initial load of all tables
        function initialLoad() {
            const params = new URLSearchParams(window.location.search);
            const search = params.get('search') || '';
            searchInput.value = search;
            
            document.querySelectorAll('.orders-section').forEach(section => {
                const optionId = section.dataset.optionId;
                const pageVar = `page_${optionId}`;
                const page = params.get(pageVar) || 1;
                loadOrders(optionId, page, search);
            });
        }

        // Start the initial load
        initialLoad();

        // Refresh orders every 15 seconds
        setInterval(function() {
            document.querySelectorAll('.orders-section').forEach(section => {
                const optionId = section.dataset.optionId;
                const page = getCurrentPageForOption(optionId);
                const search = searchInput.value.trim();
                loadOrders(optionId, page, search);
            });
        }, 15000); // every 15 seconds
    });
    </script>
</body>
</html>