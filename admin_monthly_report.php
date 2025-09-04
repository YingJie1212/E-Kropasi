<?php
session_start();
require_once "../classes/DB.php";
require_once "../classes/OrderManager.php";
require_once "../classes/Product.php";

// Only allow admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Initialize classes
$orderManager = new OrderManager();
$product = new Product();

// Fetch necessary data for sidebar
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

// Set safe defaults to avoid deprecated warnings
if ($adminData) {
    $admin_id = $adminData['id'];
    $admin_name = $adminData['name'] ?? 'Admin';
    $admin_email = $adminData['email'] ?? '';
    $is_admin = $adminData['is_admin'] ?? 0;
    $_SESSION['is_admin'] = $is_admin;
    $_SESSION['admin_name'] = $admin_name;
} else {
    $admin_id = 0;
    $admin_name = 'Admin';
    $admin_email = '';
    $is_admin = 0;
    $_SESSION['is_admin'] = $is_admin;
    $_SESSION['admin_name'] = $admin_name;
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

// Monthly report data
$pdo = (new DB())->getConnection();
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get search query from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all packages for quick lookup
$packageStmt = $pdo->query("SELECT id, name FROM packages");
$packageMap = [];
foreach ($packageStmt->fetchAll(PDO::FETCH_ASSOC) as $pkg) {
    $packageMap[$pkg['id']] = $pkg['name'];
}

// Fetch all products for quick lookup
$productStmt = $pdo->query("SELECT id, name FROM products");
$productMap = [];
foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $prod) {
    $productMap[$prod['id']] = $prod['name'];
}

// Modify the orders query to include search filter for products/packages
$sql = "
    SELECT o.*, u.name AS student_name, u.class_name AS student_class
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.status = 'Completed'
      AND MONTH(o.created_at) = ?
      AND YEAR(o.created_at) = ?
";
$params = [$month, $year];

if ($search !== '') {
    $sql .= " AND o.id IN (
        SELECT oi.order_id
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN packages pk ON oi.package_id = pk.id
        WHERE 
            p.name LIKE ? OR
            pk.name LIKE ? OR
            oi.product_id LIKE ? OR
            oi.package_id LIKE ?
    )
    OR (
        o.id LIKE ? OR
        u.name LIKE ? OR
        u.class_name LIKE ?
    )";
    // For order_items search
    $params[] = "%$search%"; // p.name
    $params[] = "%$search%"; // pk.name
    $params[] = "%$search%"; // oi.product_id
    $params[] = "%$search%"; // oi.package_id
    // For orders search
    $params[] = "%$search%"; // o.id
    $params[] = "%$search%"; // u.name
    $params[] = "%$search%"; // u.class_name
}

$sql .= " ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals and collect items
$totalOrders = count($orders);
$totalSales = 0;
$productCounts = [];
$orderDetails = [];

foreach ($orders as $order) {
    $orderTotal = 0;
    $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmtItems->execute([$order['id']]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        if (!empty($item['package_id']) && isset($packageMap[$item['package_id']])) {
            $productName = 'Package: ' . $packageMap[$item['package_id']];
        } elseif (!empty($item['product_id']) && isset($productMap[$item['product_id']])) {
            $productName = 'Product: ' . $productMap[$item['product_id']];
        } else {
            $productName = $item['product_name'] ?? 'Product/Package #' . ($item['product_id'] ?: $item['package_id']);
        }
        $orderTotal += $item['price'] * $item['quantity'];
        if (!isset($productCounts[$productName])) {
            $productCounts[$productName] = 0;
        }
        $productCounts[$productName] += $item['quantity'];
    }
    $totalSales += $orderTotal;
    $orderDetails[] = [
        'order' => $order,
        'items' => $items,
        'order_total' => $orderTotal
    ];
}

arsort($productCounts);
$mostPopular = key($productCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Monthly Sales Report</title>
    
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
            --header-height: 4rem;
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
            -webkit-text-size-adjust: 100%;
        }

        /* Typography */
        h1, h2, h3 {
            font-weight: 600;
            line-height: 1.25;
            color: var(--gray-900);
        }

        h1 {
            font-size: 1.5rem;
            margin-bottom: var(--space-sm);
        }

        h2 {
            font-size: 1.25rem;
            margin-bottom: var(--space-sm);
        }

        h3 {
            font-size: 1.125rem;
            margin-bottom: var(--space-sm);
        }

        p {
            margin-bottom: var(--space-sm);
            font-size: 0.9375rem;
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
            padding: 0 var(--space-md);
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
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-700);
            display: none;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        /* Menu Toggle Button */
        .menu-toggle {
            display: block;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.25rem;
            cursor: pointer;
            padding: var(--space-sm);
            margin-right: 0;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .menu-toggle:hover {
            background-color: var(--gray-100);
            color: var(--gray-800);
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-button {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition);
        }

        .user-button:hover {
            background-color: var(--gray-100);
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-700);
            font-weight: 600;
            font-size: 0.9375rem;
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
            display: none;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.9375rem;
            color: var(--gray-800);
        }

        .user-role {
            font-size: 0.8125rem;
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
            gap: var(--space-sm);
            border-bottom: 1px solid var(--gray-200);
        }

        .dropdown-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-700);
            font-weight: 600;
            font-size: 0.9375rem;
        }

        .dropdown-user-info {
            flex: 1;
            min-width: 0;
        }

        .dropdown-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-900);
            margin-bottom: 0.125rem;
        }

        .dropdown-email {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .dropdown-meta i {
            color: var(--primary-500);
            font-size: 0.75rem;
            margin-right: 0.25rem;
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
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--primary-700);
        }

        .dropdown-item i {
            color: var(--gray-500);
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
            transition: var(--transition);
        }

        .dropdown-item:hover i {
            color: var(--primary-500);
        }

        .dropdown-footer {
            padding: var(--space-sm) var(--space-md);
            font-size: 0.75rem;
            color: var(--gray-500);
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
            flex-direction: column;
        }

        /* Sidebar */
        .sidebar {
            width: 100%;
            max-width: 16rem;
            background-color: var(--white);
            border-right: 1px solid var(--gray-200);
            padding: var(--space-md) 0;
            display: flex;
            flex-direction: column;
            transition: var(--transition-slow);
            z-index: 90;
            overflow-y: auto;
            position: fixed;
            top: var(--header-height);
            bottom: 0;
            left: -100%;
            height: calc(100vh - var(--header-height));
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 0 var(--space-md) var(--space-md);
            border-bottom: 1px solid var(--gray-200);
            display: none;
        }

        .sidebar-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-sm);
        }

        .nav-menu {
            flex: 1;
            padding: var(--space-md) 0;
            overflow-y: auto;
        }

        .nav-group {
            margin-bottom: var(--space-md);
        }

        .nav-group-title {
            padding: 0 var(--space-md) var(--space-sm);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: var(--space-sm) var(--space-md);
            color: var(--gray-600);
            font-size: 0.875rem;
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
        }

        .nav-item i {
            width: 1.5rem;
            font-size: 1.125rem;
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
            padding: 0.125rem 0.5rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            color: var(--primary-700);
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: var(--space-md);
            background-color: var(--gray-50);
            min-height: calc(100vh - var(--header-height));
        }

        .page-header {
            margin-bottom: var(--space-md);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .page-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-top: var(--space-sm);
            flex-wrap: wrap;
        }

        /* Breadcrumb */
        .breadcrumb {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: var(--space-md);
            display: none;
        }

        .breadcrumb a {
            color: var(--gray-600);
        }

        .breadcrumb a:hover {
            color: var(--primary-600);
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: var(--space-sm);
            width: 100%;
            margin-bottom: var(--space-md);
            flex-direction: column;
        }

        .search-input {
            flex: 1;
            padding: var(--space-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9375rem;
            transition: var(--transition);
            width: 100%;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-300);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .search-buttons {
            display: flex;
            gap: var(--space-sm);
            width: 100%;
        }

        .search-buttons .btn {
            flex: 1;
            min-height: 44px;
        }

        /* Card */
        .card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
        }

        /* Report specific styles */
        .report-container { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05); 
            border: 1px solid #e0e0e0;
            overflow: hidden;
            box-sizing: border-box;
        }
        .summary { 
            margin: 20px 0; 
            color: #555;
        }
        .summary strong { 
            color: #222; 
        }
        ul { 
            margin: 0 0 20px 20px; 
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
            overflow-wrap: break-word;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px;
            background: white;
            table-layout: fixed;
        }
        th, td { 
            padding: 12px 15px; 
            border: 1px solid #e0e0e0;
            text-align: left;
            word-wrap: break-word;
        }
        th { 
            background: #f5f5f5;
            color: #444; 
            font-weight: 600;
        }
        .order-header { 
            background: #f7f7f7;
            font-weight: bold; 
        }
        .item-row { 
            background: #fafafa;
        }
        tr.item-row:nth-child(even) { 
            background: #f5f5f5;
        }
        .total-row { 
            background: #f5f5f5;
            font-weight: bold; 
        }
        .charts { 
            display: flex; 
            gap: 20px; 
            flex-wrap: wrap; 
            margin-bottom: 30px; 
        }
        .chart-container { 
            background: #fafafa;
            border-radius: 8px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.03); 
            padding: 15px; 
            flex: 1 1 100%; 
            border: 1px solid #e0e0e0;
            min-width: 0;
        }
        .chart-container h3 {
            color: #444;
            margin-top: 0;
            overflow-wrap: break-word;
            font-size: 1.1rem;
        }
        .print-btn { 
            display: inline-block; 
            margin-bottom: 20px; 
            padding: 10px 20px; 
            background: #e53935;
            color: white; 
            border: none; 
            border-radius: 5px; 
            font-size: 14px; 
            cursor: pointer; 
            transition: all 0.3s;
        }
        .print-btn:hover { 
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(229, 57, 53, 0.3);
        }
        .history-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            margin-left: 10px;
        }
        .history-btn:hover {
            background: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        }
        .summary-cards {
            display: flex; 
            gap: 20px; 
            margin-bottom: 30px; 
            flex-wrap: wrap;
        }
        .summary-cards > div {
            flex: 1; 
            min-width: 150px; 
            background:#f5f5f5;
            border-radius:8px; 
            padding:15px; 
            text-align:center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
            transition: transform 0.3s;
            box-sizing: border-box;
        }
        .summary-cards > div:hover {
            transform: translateY(-5px);
        }
        .summary-cards > div:nth-child(2) {
            background: #ededed;
        }
        .summary-cards > div:nth-child(3) {
            background: #e0e0e0;
        }
        .summary-cards div div:first-child {
            font-size: 1.5em; 
            color:#555; 
            font-weight:bold;
            margin-bottom: 5px;
            overflow-wrap: break-word;
        }
        .summary-cards div div:last-child {
            color:#888;
            font-weight: 500;
            overflow-wrap: break-word;
            font-size: 0.9rem;
        }
        
        /* Checkbox styles for print selection */
        .print-selection {
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            display: none; /* Initially hidden */
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            border: 1px solid #e0e0e0;
        }
        .print-selection.show {
            display: flex; /* Show when toggled */
        }
        .print-selection strong {
            margin-right: 10px;
            color: #444;
        }
        .print-part-checkbox {
            margin: 0 5px 0 0;
        }
        .print-selection label {
            display: flex;
            align-items: center;
            margin-right: 0;
            cursor: pointer;
            white-space: nowrap;
            padding: 5px 8px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        .print-selection .buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
            flex-wrap: wrap;
        }
        
        /* Toggle button for print options */
        .toggle-print-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 16px;
            background: #666;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .toggle-print-btn:hover {
            background: #555;
        }
        
        /* Chart scrollable container */
        .chart-scroll-container {
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .chart-scroll-container::-webkit-scrollbar {
            height: 8px;
        }
        .chart-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .chart-scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .chart-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .chart-wrapper {
            min-width: 300px; /* Minimum width for charts */
            position: relative;
            height: 300px;
        }
        
        /* Mobile-specific styles */
        .mobile-search-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.2rem;
            cursor: pointer;
            padding: var(--space-sm);
        }
        
        .search-form.mobile-active {
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            z-index: 90;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-form.mobile-active input {
            flex: 1;
            padding: 12px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        
        .search-form.mobile-active button {
            padding: 12px 16px;
            border-radius: 4px;
            border: none;
            background: #1976d2;
            color: white;
            cursor: pointer;
        }
        
        .mobile-table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        .mobile-table-container table {
            min-width: 600px;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25;
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            white-space: nowrap;
            min-height: 44px;
        }

        .btn-primary {
            background-color: var(--primary-600);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-700);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary-600);
            border-color: var(--primary-300);
        }

        .btn-outline:hover {
            background-color: var(--primary-50);
            color: var(--primary-700);
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
        }

        .btn-block {
            display: flex;
            width: 100%;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: var(--space-xs);
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            flex: 1;
            min-width: 80px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: var(--space-xl) var(--space-sm);
            color: var(--gray-500);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: var(--space-md);
            gap: var(--space-xs);
            flex-wrap: wrap;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 var(--space-sm);
            border-radius: var(--radius-md);
            color: var(--gray-700);
            font-weight: 500;
            background-color: var(--white);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            font-size: 0.8125rem;
        }

        .page-link:hover {
            background-color: var(--gray-100);
            color: var(--primary-600);
        }

        .page-item.active .page-link {
            background-color: var(--primary-600);
            border-color: var(--primary-600);
            color: var(--white);
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
            padding: var(--space-md);
        }

        .modal.show {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: var(--space-md);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 1.125rem;
        }

        .modal-body {
            padding: var(--space-md);
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: var(--space-sm);
        }

        /* Notification */
        .notification {
            position: fixed;
            top: var(--space-md);
            right: var(--space-md);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            background-color: var(--primary-600);
            color: var(--white);
            box-shadow: var(--shadow-lg);
            transform: translateX(200%);
            transition: transform 0.3s ease;
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            max-width: calc(100% - 2rem);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background-color: var(--success);
        }

        .notification.error {
            background-color: var(--danger);
        }

        .notification-icon {
            font-size: 1rem;
        }

        .notification-message {
            font-size: 0.875rem;
        }

        /* Mobile Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 80;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition-slow);
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Mobile-specific improvements */
        @media (max-width: 767px) {
            /* Header adjustments */
            .header {
                height: 3.5rem;
                padding: 0 var(--space-sm);
            }
            
            .logo-text {
                font-size: 1.1rem;
            }
            
            /* Main content spacing */
            .main-content {
                padding: var(--space-sm);
            }
            
            /* Card and table improvements */
            .card {
                padding: var(--space-sm);
                margin-bottom: var(--space-sm);
            }
            
            .report-container {
                padding: 15px;
            }
            
            /* Package details improvements */
            .summary-cards > div {
                min-width: 120px;
                padding: 12px;
            }
            
            .summary-cards div div:first-child {
                font-size: 1.3em;
            }
            
            /* Action buttons improvements */
            .action-buttons {
                flex-direction: column;
                gap: var(--space-xs);
            }
            
            .action-buttons .btn {
                min-width: unset;
                width: 100%;
                padding: 0.4rem 0.5rem;
                font-size: 0.75rem;
            }
            
            /* Search form improvements */
            .search-form {
                margin-bottom: var(--space-sm);
            }
            
            .search-input {
                font-size: 0.875rem;
                padding: 0.75rem;
            }
            
            /* Page header improvements */
            .page-title {
                font-size: 1.25rem;
            }
            
            .page-actions {
                margin-top: var(--space-xs);
            }
            
            /* Pagination improvements */
            .pagination {
                gap: 0.25rem;
            }
            
            .page-link {
                min-width: 2rem;
                height: 2rem;
                padding: 0 0.5rem;
                font-size: 0.75rem;
            }
            
            /* Modal improvements */
            .modal-content {
                margin: 0 var(--space-sm);
            }
            
            .modal-header,
            .modal-body,
            .modal-footer {
                padding: var(--space-sm);
            }
            
            /* Better touch targets */
            .btn, .nav-item, .dropdown-item {
                min-height: 44px;
                display: flex;
                align-items: center;
            }
            
            /* Improve dropdown menu for mobile */
            .dropdown-menu {
                width: 100%;
                right: 0;
                left: 0;
                border-radius: 0;
                position: fixed;
                top: auto;
                bottom: 0;
                transform: translateY(100%);
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .dropdown-menu.active {
                transform: translateY(0);
            }
            
            /* Better mobile notification */
            .notification {
                left: var(--space-sm);
                right: var(--space-sm);
                max-width: calc(100% - 2 * var(--space-sm));
            }
            
            /* Charts adjustments */
            .charts {
                flex-direction: column;
            }
            
            .chart-container {
                width: 100%;
            }
            
            .chart-wrapper {
                min-width: 280px;
                height: 250px;
            }
            
            /* Print selection adjustments */
            .print-selection {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                padding: 10px;
            }
            
            .print-selection .buttons {
                margin-left: 0;
                width: 100%;
                flex-direction: column;
                gap: 8px;
            }
            
            .print-btn, .history-btn, .toggle-print-btn {
                padding: 8px 16px;
                font-size: 14px;
                margin-bottom: 0;
                width: 100%;
                margin-left: 0;
            }
            
            /* Table adjustments */
            .mobile-table-container {
                overflow-x: auto;
                margin-bottom: 15px;
            }
            
            .mobile-table-container table {
                min-width: 600px;
                font-size: 0.85em;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            /* Hide less important information on mobile */
            .dropdown-meta {
                display: none;
            }
        }

        @media (max-width: 480px) {
            /* Stack table cells for better mobile viewing */
            .summary-cards {
                flex-direction: column;
                gap: 10px;
            }
            
            .summary-cards > div {
                width: 100%;
                min-width: auto;
            }
            
            /* Make action buttons more compact */
            .action-buttons .btn i {
                margin-right: 0;
            }
            
            .action-buttons .btn span {
                display: none;
            }
            
            /* Show tooltip for icon-only buttons */
            .action-buttons .btn {
                position: relative;
            }
            
            .action-buttons .btn::after {
                content: attr(aria-label);
                position: absolute;
                bottom: -30px;
                left: 50%;
                transform: translateX(-50%);
                background: var(--gray-800);
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.75rem;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s;
                z-index: 1000;
            }
            
            .action-buttons .btn:hover::after {
                opacity: 1;
            }
            
            /* Further reduce font sizes */
            .product-name {
                font-size: 0.8125rem;
            }
            
            .product-price {
                font-size: 0.8125rem;
            }
            
            /* Adjust spacing */
            .table td {
                padding: 0.5rem 0.25rem;
            }
            
            .chart-wrapper {
                min-width: 250px;
                height: 200px;
            }
            
            .print-selection label {
                width: 100%;
                justify-content: space-between;
            }
        }

        /* Orientation-specific adjustments */
        @media (max-width: 767px) and (orientation: landscape) {
            .main-content {
                padding: var(--space-xs);
            }
            
            .mobile-table-container {
                max-height: 60vh;
                overflow-y: auto;
            }
            
            .modal-body {
                max-height: 50vh;
            }
        }

        /* High-resolution devices */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .product-image,
            .product-thumbnail {
                border: 0.5px solid var(--gray-300);
            }
        }

        /* Reduced motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Responsive Styles */
        @media (min-width: 576px) {
            .search-form {
                flex-direction: row;
            }
            
            .search-buttons {
                width: auto;
            }
            
            .search-buttons .btn {
                flex: none;
            }
            
            .action-buttons .btn {
                flex: none;
            }
        }

        @media (min-width: 768px) {
            :root {
                --header-height: 5rem;
            }
            
            .main-layout {
                flex-direction: row;
            }
            
            .sidebar {
                position: fixed;
                left: 0;
                width: var(--sidebar-width);
                height: calc(100vh - var(--header-height));
                padding: var(--space-lg) 0;
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
                padding: var(--space-lg);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .logo-text {
                display: block;
            }
            
            .user-details {
                display: flex;
            }
            
            .user-button {
                padding: var(--space-sm);
                gap: var(--space-sm);
            }
            
            .breadcrumb {
                display: block;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .table th, .table td {
                padding: var(--space-md);
                font-size: 0.875rem;
            }
            
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9375rem;
            }
            
            .charts {
                flex-direction: row;
            }
            
            .chart-container {
                flex: 1 1 45%;
            }
        }

        @media (min-width: 992px) {
            .main-content {
                padding: var(--space-xl);
            }
            
            .page-header {
                margin-bottom: var(--space-xl);
            }
            
            .pagination {
                margin-top: var(--space-xl);
            }
            
            .chart-container {
                flex: 1 1 30%;
            }
        }

        /* PRINT STYLES - FIXED FOR CHART ASPECT RATIO */
        @media print {
            .header,
            .sidebar,
            .footer,
            .menu-toggle,
            .mobile-search-toggle {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            a {
                color: black !important;
                text-decoration: underline !important;
            }
            
            .dashboard-card {
                break-inside: avoid;
                page-break-inside: avoid;
                margin-bottom: 1rem;
            }
            
            .dashboard-grid {
                display: block !important;
            }
            
            .report-container { 
                background: white !important; 
                box-shadow: none !important; 
                padding: 0 !important;
                width: 100% !important;
                margin: 0 !important;
            }
            .print-btn, .history-btn, .print-selection, .toggle-print-btn { 
                display: none !important; 
            }
            .report-container { 
                margin: 0; 
                padding: 20px !important; 
                max-width: 100% !important; 
                border: none !important;
            }
            th { 
                background: #f5f5f5 !important; 
                color: #000 !important; 
                -webkit-print-color-adjust: exact;
            }
            .charts { 
                display: flex !important; 
                flex-wrap: wrap !important; 
                gap: 20px !important; 
                margin-bottom: 20px !important;
            }
            .chart-container {
                background: #fafafa !important;
                box-shadow: none !important;
                padding: 10px !important;
                border-radius: 0 !important;
                page-break-inside: avoid;
                border: 1px solid #e0e0e0 !important;
                min-width: 0 !important;
            }
            .chart-scroll-container {
                overflow-x: visible !important;
            }
            .chart-wrapper {
                min-width: auto !important;
                height: auto !important;
                /* Force square aspect ratio for pie chart */
                aspect-ratio: 1 / 1 !important;
                max-width: 400px !important;
                max-height: 400px !important;
                margin: 0 auto;
            }
            canvas {
                /* Ensure canvas maintains aspect ratio */
                width: 100% !important;
                height: auto !important;
                max-width: 100% !important;
                max-height: 100% !important;
                page-break-inside: avoid;
                display: block;
                margin: 0 auto;
            }
            /* Make summary cards print nicely */
            .summary-cards, .summary-cards > div {
                background: #f5f5f5 !important;
                color: #000 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                -webkit-print-color-adjust: exact;
                page-break-inside: avoid;
            }
            .summary-cards > div:nth-child(2) {
                background: #ededed !important;
            }
            .summary-cards > div:nth-child(3) {
                background: #e0e0e0 !important;
            }
            @page {
                size: auto;
                margin: 10mm;
            }
            /* Ensure tables don't break across pages */
            table {
                page-break-inside: auto !important;
            }
            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }
        }
    </style>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Audio for notifications -->
    <audio id="orderSound" src="notification.mp3" preload="auto"></audio>
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
                <button class="mobile-search-toggle" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>
                
                <div class="user-dropdown">
                    <button class="user-button" aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                        <div class="user-avatar online">
                            <span><?= isset($admin_name) && $admin_name ? substr($admin_name, 0, 1) : 'A' ?></span>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= htmlspecialchars($admin_name ?? 'Admin') ?></span>
                            <span class="user-role"><?= htmlspecialchars($admin_role ?? 'User') ?></span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.875rem;"></i>
                    </button>

                    <div class="dropdown-menu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span><?= isset($admin_name) && $admin_name ? substr($admin_name, 0, 1) : 'A' ?></span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name"><?= htmlspecialchars($admin_name ?? 'Admin') ?></div>
                                <div class="dropdown-email"><?= htmlspecialchars($admin_email ?? '') ?></div>
                                <div class="dropdown-meta">
                                    <i class="fas fa-shield-alt"></i>
                                    <span><?= htmlspecialchars($admin_role ?? 'User') ?></span>
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

                    <a href="orders.php" class="nav-item">
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
                    
                    <a href="admin_monthly_report.php" class="nav-item active">
                        <i class="fas fa-chart-bar"></i>
                        <span>Monthly Report</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Monthly Sales Report</h1>
                <p class="page-subtitle">Sales overview for <?= date('F Y') ?></p>
            </div>

            <div class="report-container">
                <!-- Toggle button for print options -->
                <button class="toggle-print-btn" onclick="togglePrintSelection()">🖨️ Print Options</button>
                
                <!-- Print selection section (initially hidden) -->
                <div id="print-selection" class="print-selection">
                    <strong>Select parts to print:</strong>
                    <label><input type="checkbox" class="print-part-checkbox" value="1" checked> Header</label>
                    <label><input type="checkbox" class="print-part-checkbox" value="2" checked> Summary Cards</label>
                    <label><input type="checkbox" class="print-part-checkbox" value="3" checked> Charts</label>
                    <label><input type="checkbox" class="print-part-checkbox" value="4" checked> Products Sold List</label>
                    <label><input type="checkbox" class="print-part-checkbox" value="5" checked> Detailed Orders</label>
                    <div class="buttons">
                        <button class="print-btn" onclick="printSelectedParts()">🖨️ Print Selected</button>
                        <button class="history-btn" onclick="window.location.href='admin_monthly_report_history.php'">📅 View History</button>
                    </div>
                </div>
                
                <!-- Search Bar - Desktop -->
                <form method="get" class="search-form" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Student, Class, or Order ID" style="padding: 8px; border-radius: 4px; border: 1px solid #ccc; flex: 1;">
                    <button type="submit" style="padding: 8px 16px; border-radius: 4px; border: none; background: #1976d2; color: white; cursor: pointer;">🔍 Search</button>
                    <?php if ($search): ?>
                        <a href="?month=<?= htmlspecialchars($month) ?>&year=<?= htmlspecialchars($year) ?>" style="padding: 8px 16px; border-radius: 4px; background: #e53935; color: white; text-decoration: none;">Clear</a>
                    <?php endif; ?>
                </form>

                <!-- 1. Header -->
                <div id="print-part-1">
                    <h2>Monthly Sales Report (<?= date('F Y') ?>)</h2>
                </div>

                <!-- 2. Summary Cards -->
                <div id="print-part-2" class="summary-cards">
                    <div>
                        <div><?= $totalOrders ?></div>
                        <div>Total Orders</div>
                    </div>
                    <div>
                        <div>RM<?= number_format($totalSales, 2) ?></div>
                        <div>Total Sales</div>
                    </div>
                    <div>
                        <div style="font-size:1.2em;"><?= htmlspecialchars($mostPopular) ?></div>
                        <div>Most Popular</div>
                    </div>
                </div>

                <!-- 3. Charts -->
                <div id="print-part-3" class="charts">
                    <div class="chart-container">
                        <h3>Products Sold (Bar Chart)</h3>
                        <div class="chart-scroll-container">
                            <div class="chart-wrapper">
                                <canvas id="barChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h3>Sales Distribution (Pie Chart)</h3>
                        <div class="chart-scroll-container">
                            <div class="chart-wrapper">
                                <canvas id="pieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Products Sold List -->
                <div id="print-part-4">
                    <h3>Products Sold This Month:</h3>
                    <ul>
                        <?php foreach ($productCounts as $name => $qty): ?>
                            <li><?= htmlspecialchars($name) ?>: <?= $qty ?> units</li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 5. Detailed Orders -->
                <div id="print-part-5">
                    <h3>Detailed Orders:</h3>
                    <div class="mobile-table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Order ID</th>
                                    <th style="width: 20%;">Student</th>
                                    <th style="width: 15%;">Class</th>
                                    <th style="width: 15%;">Date</th>
                                    <th style="width: 10%;">Items</th>
                                    <th style="width: 15%;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderDetails as $detail): 
                                    $order = $detail['order'];
                                    $items = $detail['items'];
                                ?>
                                    <tr class="order-header">
                                        <td><?= htmlspecialchars($order['id']) ?></td>
                                        <td><?= htmlspecialchars($order['student_name']) ?></td>
                                        <td><?= htmlspecialchars($order['student_class']) ?></td>
                                        <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                        <td><?= count($items) ?></td>
                                        <td>RM<?= number_format($detail['order_total'], 2) ?></td>
                                    </tr>
                                    <?php foreach ($items as $item): 
                                        if (!empty($item['package_id']) && isset($packageMap[$item['package_id']])) {
                                            $productName = 'Package: ' . $packageMap[$item['package_id']];
                                        } elseif (!empty($item['product_id']) && isset($productMap[$item['product_id']])) {
                                            $productName = 'Product: ' . $productMap[$item['product_id']];
                                        } else {
                                            $productName = $item['product_name'] ?? 'Product/Package #' . ($item['product_id'] ?: $item['package_id']);
                                        }
                                    ?>
                                        <tr class="item-row">
                                            <td colspan="2"></td>
                                            <td colspan="2"><?= htmlspecialchars($productName) ?></td>
                                            <td><?= $item['quantity'] ?> × RM<?= number_format($item['price'], 2) ?></td>
                                            <td>RM<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5" style="text-align: right;">Monthly Total:</td>
                                    <td>RM<?= number_format($totalSales, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-content">
            <div>© <?= date('Y') ?> SMJK Phor Tay. </div>
        </div>
    </footer>

    <script>
    // Toggle print selection visibility
    function togglePrintSelection() {
        const printSelection = document.getElementById('print-selection');
        printSelection.classList.toggle('show');
    }

    function printSelectedParts() {
        // 1. Get all part ids
        const partIds = [1,2,3,4,5];
        // 2. Get checked ones
        const checked = Array.from(document.querySelectorAll('.print-part-checkbox:checked')).map(cb => 'print-part-' + cb.value);
        // 3. Hide unchecked parts
        partIds.forEach(id => {
            const el = document.getElementById('print-part-' + id);
            if (el) el.style.display = checked.includes('print-part-' + id) ? '' : 'none';
        });
        // 4. Print
        window.print();
        // 5. Restore display after printing
        setTimeout(() => {
            partIds.forEach(id => {
                const el = document.getElementById('print-part-' + id);
                if (el) el.style.display = '';
            });
        }, 500);
    }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastPendingCount = <?= $pendingOrdersCount ?> || 0;
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

        setInterval(checkNewOrders, 10000); // Check every 10 seconds
        
        // Toggle sidebar on mobile
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');

        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
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

        // Close dropdown when clicking outside (improved)
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('.user-button')) {
                userButton.setAttribute('aria-expanded', 'false');
                dropdownMenu.classList.remove('active');
            }
        });

        // Mobile search toggle
        const mobileSearchToggle = document.querySelector('.mobile-search-toggle');
        const searchForm = document.querySelector('.search-form');
        
        mobileSearchToggle.addEventListener('click', function() {
            searchForm.classList.toggle('mobile-active');
        });

        // Close mobile search when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-form') && !e.target.closest('.mobile-search-toggle')) {
                searchForm.classList.remove('mobile-active');
            }
        });

        // Check for new orders periodically
        function checkNewOrders() {
            fetch('check_pending_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined') {
                        // Update badge in sidebar
                        let navBadge = document.querySelector('.nav-item[href="orders.php"] .nav-badge');
                        if (data.count > 0) {
                            if (!navBadge) {
                                navBadge = document.createElement('span');
                                navBadge.className = 'nav-badge';
                                document.querySelector('.nav-item[href="orders.php"]').appendChild(navBadge);
                            }
                            navBadge.textContent = data.count;
                        } else if (navBadge) {
                            navBadge.remove();
                        }

                        lastPendingCount = data.count;
                    }
                })
                .catch(() => {});
        }

        // Check for low stock periodically
        function checkLowStock() {
            fetch('check_low_stock.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined') {
                        // Update badge in sidebar
                        let navBadge = document.querySelector('.nav-item[href="view_products.php"] .nav-badge');
                        if (data.count > 0) {
                            if (!navBadge) {
                                navBadge = document.createElement('span');
                                navBadge.className = 'nav-badge';
                                document.querySelector('.nav-item[href="view_products.php"]').appendChild(navBadge);
                            }
                            navBadge.textContent = data.count + ' Low';
                        } else if (navBadge) {
                            navBadge.remove();
                        }
                    }
                })
                .catch(() => {});
        }

        // Check every 30 seconds
        setInterval(checkNewOrders, 30000);
        setInterval(checkLowStock, 30000);
        
        // Handle mobile view adjustments
        function handleMobileView() {
            const isMobile = window.innerWidth <= 767;
            const actionTexts = document.querySelectorAll('.action-text');
            
            // Show/hide action text based on screen size
            actionTexts.forEach(el => {
                el.style.display = isMobile ? 'none' : 'inline';
            });
        }
        
        // Run on load and resize
        window.addEventListener('load', handleMobileView);
        window.addEventListener('resize', handleMobileView);
    });
    </script>
    <script>
        // Pass PHP data to JS
        const productLabels = <?= json_encode(array_keys($productCounts)) ?>;
        const productData = <?= json_encode(array_values($productCounts)) ?>;

        // Function to truncate long labels
        function truncateLabel(label, maxLength = 20) {
            if (label.length > maxLength) {
                return label.substring(0, maxLength - 3) + '...';
            }
            return label;
        }

        function getColorArray(count) {
            const baseColors = [
                '#1976d2', '#e53935', '#fbc02d', '#43a047', '#fb8c00',
                '#8e24aa', '#00acc1', '#c0ca33', '#6d4c41', '#757575'
            ];
            const colors = [];
            for (let i = 0; i < count; i++) {
                if (i < baseColors.length) {
                    colors.push(baseColors[i]);
                } else {
                    // Generate a random color
                    colors.push('#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0'));
                }
            }
            return colors;
        }

        const customColors = getColorArray(productLabels.length);

        // Store chart instances for potential reuse
        let barChartInstance = null;
        let pieChartInstance = null;

        // Function to create or update charts
        function renderCharts() {
            // Destroy existing charts if they exist
            if (barChartInstance) {
                barChartInstance.destroy();
            }
            if (pieChartInstance) {
                pieChartInstance.destroy();
            }

            // Bar Chart
            barChartInstance = new Chart(document.getElementById('barChart'), {
                type: 'bar',
                data: {
                    labels: productLabels.map(label => truncateLabel(label)),
                    datasets: [{
                        label: 'Quantity Sold',
                        data: productData,
                        backgroundColor: customColors,
                        borderColor: '#333333',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { display: false },
                        title: {
                            display: true,
                            text: 'Products Sold This Month',
                            color: '#222'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    // Show full name in tooltip
                                    const index = context.dataIndex;
                                    return productLabels[index] + ': ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(40, 167, 69, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(40, 167, 69, 0.1)'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        }
                    }
                }
            });

            // Pie Chart
            pieChartInstance = new Chart(document.getElementById('pieChart'), {
                type: 'pie',
                data: {
                    labels: productLabels.map(label => truncateLabel(label)),
                    datasets: [{
                        data: productData,
                        backgroundColor: customColors,
                        borderColor: '#ffffff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true, // Changed to true for better printing
                    plugins: {
                        title: {
                            display: true,
                            text: 'Sales Distribution',
                            color: '#222'
                        },
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#222',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    // Show full name in tooltip
                                    const index = context.dataIndex;
                                    return productLabels[index] + ': ' + context.parsed;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initial chart rendering
        renderCharts();

        // Re-render charts before printing to ensure proper aspect ratio
        window.addEventListener('beforeprint', () => {
            // Force charts to maintain aspect ratio for printing
            if (pieChartInstance) {
                pieChartInstance.options.maintainAspectRatio = true;
                pieChartInstance.resize();
            }
        });

        // Restore chart settings after printing
        window.addEventListener('afterprint', () => {
            if (pieChartInstance) {
                pieChartInstance.options.maintainAspectRatio = false;
                pieChartInstance.resize();
            }
        });
    </script>
</body>
</html>