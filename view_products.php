<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../classes/Product.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/OrderManager.php";
session_start();

// Add highlight function
function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    $textEsc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $searchEsc = preg_quote($search, '/');
    return preg_replace_callback(
        "/($searchEsc)/iu",
        function($m) {
            return '<span class="highlight">' . $m[1] . '</span>';
        },
        $textEsc
    );
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

$product = new Product();

$search = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $products = $product->search($search);
} else {
    $products = $product->getAll();
}

// Filter out products where is_active = 2
$products = array_filter($products, function($p) {
    return (!isset($p['is_active']) || $p['is_active'] != 2);
});

// Sort products - active first, inactive last
usort($products, function($a, $b) {
    $aActive = isset($a['is_active']) ? $a['is_active'] : 0;
    $bActive = isset($b['is_active']) ? $b['is_active'] : 0;
    return $bActive - $aActive;
});

$groupObj = new ProductOptionGroup();
$valueObj = new ProductOptionValue();

// Pagination setup
$productsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $productsPerPage;
$totalProducts = count($products);
$totalPages = ceil($totalProducts / $productsPerPage);
$products = array_slice($products, $offset, $productsPerPage);

$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
$lowStockCount = $product->countLowStock(10); // 10 is the threshold
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            display: flex;
            flex-direction: column;
            font-size: 14px;
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
            padding: 0 var(--space-sm);
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
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
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
            width: 2.25rem;
            height: 2.25rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-700);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .user-avatar.online::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0.625rem;
            height: 0.625rem;
            border-radius: var(--radius-full);
            background-color: var(--success);
            border: 2px solid var(--white);
        }

        .user-details {
            display: none;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            width: 16rem;
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
            padding: var(--space-sm);
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
            font-size: 1rem;
        }

        .dropdown-user-info {
            flex: 1;
            min-width: 0;
        }

        .dropdown-name {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--gray-900);
            margin-bottom: 0.125rem;
        }

        .dropdown-email {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-meta {
            font-size: 0.6875rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .dropdown-meta i {
            color: var(--primary-500);
            font-size: 0.6875rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: var(--space-xs) 0;
            border: none;
        }

        .dropdown-item {
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            margin: 0 var(--space-xs);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            color: var(--gray-700);
            font-size: 0.8125rem;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--primary-700);
        }

        .dropdown-item i {
            color: var(--gray-500);
            font-size: 0.9375rem;
            width: 1.25rem;
            text-align: center;
            transition: var(--transition);
        }

        .dropdown-item:hover i {
            color: var(--primary-500);
        }

        .dropdown-footer {
            padding: var(--space-xs) var(--space-sm);
            font-size: 0.6875rem;
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
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
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
            left: 0;
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-header {
            padding: 0 var(--space-md) var(--space-md);
            border-bottom: 1px solid var(--gray-200);
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
            padding: 0 var(--space-md) var(--space-xs);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: var(--space-xs) var(--space-md);
            color: var(--gray-600);
            font-size: 0.8125rem;
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
            font-size: 1rem;
            margin-right: var(--space-xs);
            color: var(--gray-500);
            transition: var(--transition);
        }

        .nav-item:hover i,
        .nav-item.active i {
            color: var(--primary-500);
        }

        .nav-badge {
            margin-left: auto;
            padding: 0.125rem 0.375rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-100);
            color: var(--primary-700);
            font-size: 0.6875rem;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: var(--space-md);
            background-color: var(--gray-50);
            min-height: calc(100vh - var(--header-height));
            width: 100%;
        }

        .page-header {
            margin-bottom: var(--space-md);
        }

        .page-title {
            font-size: 1.25rem;
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
            gap: var(--space-xs);
            margin-top: var(--space-sm);
            flex-wrap: wrap;
        }

        /* Cards */
        .card {
            background-color: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            background-color: var(--gray-50);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .card-title svg {
            width: 1rem;
            height: 1rem;
            color: var(--primary-500);
        }

        .card-body {
            padding: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.8125rem;
            line-height: 1;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-md {
            padding: 0.5rem 1rem;
        }

        .btn-lg {
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--primary-600);
            color: white;
            border-color: var(--primary-600);
        }

        .btn-primary:hover {
            background-color: var(--primary-700);
            border-color: var(--primary-700);
        }

        .btn-outline {
            background-color: white;
            border-color: var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
            border-color: var(--success);
        }

        .btn-success:hover {
            background-color: var(--primary-700);
            border-color: var(--primary-700);
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
            border-color: var(--warning);
        }

        .btn-warning:hover {
            background-color: var(--secondary-600);
            border-color: var(--secondary-600);
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }

        .btn-icon {
            padding: 0.375rem;
            border-radius: var(--radius);
            font-size: 0.75rem;
        }

        .btn-group {
            display: flex;
            gap: 0.375rem;
        }

        /* Forms */
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            line-height: 1;
            color: var(--gray-700);
            background-color: white;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-300);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .form-control-lg {
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
        }

        .search-form {
            display: flex;
            gap: 0.375rem;
            margin-bottom: 1rem;
            width: 100%;
            flex-wrap: wrap;
        }

        .search-form .form-control {
            flex: 1;
            min-width: 150px;
        }

        /* Tables */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            background-color: white;
            border: 1px solid var(--gray-200);
            margin-bottom: 1rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background-color: white;
        }

        .table th {
            background-color: var(--primary-50);
            color: var(--primary-700);
            font-weight: 600;
            text-align: left;
            padding: 0.5rem;
            text-transform: uppercase;
            font-size: 0.6875rem;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--gray-200);
        }

        .table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background-color: var(--primary-50);
        }

        /* Product image */
        .product-img {
            width: 2.5rem;
            height: 2.5rem;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }

        .product-img-placeholder {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--gray-100);
            border-radius: var(--radius);
            color: var(--gray-400);
        }

        /* Status badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.375rem;
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            border-radius: var(--radius-full);
        }

        .badge-success {
            background-color: var(--primary-100);
            color: var(--primary-800);
        }

        .badge-warning {
            background-color: var(--warning-100);
            color: var(--warning-800);
        }

        .badge-danger {
            background-color: var(--danger-100);
            color: var(--danger-800);
        }

        .badge-info {
            background-color: var(--primary-100);
            color: var(--primary-800);
        }

        /* Pagination */
        .pagination {
            display: flex;
            gap: 0.375rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 2rem;
            border-radius: var(--radius);
            background-color: white;
            color: var(--primary-600);
            border: 1px solid var(--gray-200);
            font-weight: 500;
            font-size: 0.75rem;
            transition: var(--transition);
        }

        .page-link:hover {
            background-color: var(--primary-50);
            border-color: var(--primary-300);
        }

        .page-link.active {
            background-color: var(--primary-600);
            border-color: var(--primary-600);
            color: white;
        }

        /* Text utilities */
        .text-muted {
            color: var(--gray-500);
        }

        .text-sm {
            font-size: 0.6875rem;
        }

        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 120px;
        }

        /* Highlight */
        .highlight {
            background-color: var(--primary-200);
            padding: 0.0625rem 0.125rem;
            border-radius: var(--radius-sm);
        }

        /* Modals */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 150ms ease;
            padding: 1rem;
        }

        .modal.show {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-content {
            background-color: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            transform: translateY(20px);
            transition: transform 150ms ease;
            border: 1px solid var(--gray-200);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--gray-50);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--gray-100);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background-color: var(--gray-50);
            position: sticky;
            bottom: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.125rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            padding: 0;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-btn:hover {
            color: var(--gray-700);
        }

        /* Quantity modal - Improved Styles */
        .quantity-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: var(--primary-50);
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border: 1px solid var(--primary-100);
        }

        .quantity-display span:first-child {
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .quantity-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-700);
            background-color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--primary-200);
            min-width: 60px;
            text-align: center;
        }

        .quantity-controls {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .quantity-row {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .quantity-btn {
            padding: 0.5rem;
            min-width: 3rem;
            border-radius: var(--radius);
            background-color: var(--primary-100);
            color: var(--primary-800);
            border: 1px solid var(--primary-200);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.8125rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background-color: var(--primary-200);
            transform: translateY(-1px);
        }

        .quantity-btn:active {
            transform: translateY(0);
        }

        .quantity-input-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1rem 0;
            justify-content: center;
        }

        .quantity-input {
            width: 5rem;
            padding: 0.5rem;
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            border: 2px solid var(--primary-200);
            border-radius: var(--radius);
            color: var(--gray-800);
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--primary-300);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .quantity-adjust-btn {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-full);
            background-color: var(--primary-500);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            font-weight: bold;
        }

        .quantity-adjust-btn:hover {
            background-color: var(--primary-600);
            transform: scale(1.05);
        }

        .quantity-adjust-btn:active {
            transform: scale(0.98);
        }

        .quantity-adjust-btn.minus {
            background-color: var(--danger-100);
            color: var(--danger-600);
            border: 1px solid var(--danger-200);
        }

        .quantity-adjust-btn.minus:hover {
            background-color: var(--danger-200);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            left: 1rem;
            background-color: var(--primary-100);
            color: var(--primary-800);
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1100;
            transform: translateY(100px);
            opacity: 0;
            transition: transform 150ms ease, opacity 150ms ease;
            border: 1px solid var(--primary-200);
            max-width: 300px;
            margin: 0 auto;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.error {
            background-color: var(--danger-100);
            color: var(--danger-800);
            border-color: var(--danger-200);
        }

        .toast-icon {
            font-size: 1rem;
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 0.875rem;
            height: 0.875rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-full);
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
        }

        .empty-state-icon {
            font-size: 2.5rem;
            color: var(--gray-300);
            margin-bottom: 0.75rem;
        }

        .empty-state-text {
            color: var(--gray-500);
            font-size: 0.9375rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Mobile Menu Toggle */
        .menu-toggle {
            display: block;
            background: none;
            border: none;
            color: var(--gray-600);
            font-size: 1.25rem;
            cursor: pointer;
            padding: var(--space-xs);
            margin-right: var(--space-xs);
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

        /* Mobile table styles */
        @media screen and (max-width: 768px) {
            .table-responsive {
                border: 0;
            }
            
            .table {
                border: 0;
                min-width: 100%;
            }
            
            .table thead {
                border: none;
                clip: rect(0 0 0 0);
                height: 1px;
                margin: -1px;
                overflow: hidden;
                padding: 0;
                position: absolute;
                width: 1px;
            }
            
            .table tr {
                border-bottom: 3px solid #ddd;
                display: block;
                margin-bottom: 0.625rem;
            }
            
            .table td {
                border-bottom: 1px solid #ddd;
                display: block;
                font-size: 0.8em;
                text-align: right;
                padding: 0.75rem 0.5rem;
                position: relative;
            }
            
            .table td::before {
                content: attr(data-label);
                font-weight: bold;
                text-transform: uppercase;
                position: absolute;
                left: 0.5rem;
                top: 50%;
                transform: translateY(-50%);
                text-align: left;
            }
            
            .table td:last-child {
                border-bottom: 0;
            }
            
            .product-img, .product-img-placeholder {
                margin-left: auto;
            }
            
            .btn-group {
                justify-content: flex-end;
            }
        }

        /* Responsive adjustments for larger screens */
        @media (min-width: 769px) {
            .sidebar {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .user-details {
                display: flex;
                flex-direction: column;
                margin-right: var(--space-xs);
            }
            
            .logo-text {
                display: block;
            }
        }

        @media (min-width: 1024px) {
            .container {
                padding: 0 var(--space-md);
            }
            
            .main-content {
                padding: var(--space-lg);
            }
            
            .header {
                padding: 0 var(--space-lg);
            }
            
            .sidebar {
                padding: var(--space-lg) 0;
            }
            
            .sidebar-header {
                padding: 0 var(--space-lg) var(--space-lg);
            }
            
            .nav-group-title {
                padding: 0 var(--space-lg) var(--space-sm);
            }
            
            .nav-item {
                padding: var(--space-sm) var(--space-lg);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .card-header {
                padding: 1.25rem 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .table {
                font-size: 0.875rem;
            }
            
            .table th {
                padding: 0.75rem 1rem;
            }
            
            .table td {
                padding: 1rem;
            }
        }

        /* Touch-friendly elements */
        button, .btn, a {
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Prevent zoom on input focus on mobile */
        @media screen and (max-width: 768px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }

        /* Mobile-optimized product list styles */
        .mobile-product-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .mobile-product-header {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .mobile-product-image {
            width: 3.5rem;
            height: 3.5rem;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            flex-shrink: 0;
        }

        .mobile-product-image-placeholder {
            width: 3.5rem;
            height: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--gray-100);
            border-radius: var(--radius);
            color: var(--gray-400);
            flex-shrink: 0;
        }

        .mobile-product-info {
            flex: 1;
            min-width: 0;
        }

        .mobile-product-name {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .mobile-product-description {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .mobile-product-description.collapsed {
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .mobile-product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .mobile-product-meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .mobile-product-meta-item strong {
            color: var(--gray-800);
        }

        .mobile-product-options {
            margin-bottom: 0.75rem;
        }

        .mobile-option-group {
            margin-bottom: 0.5rem;
        }

        .mobile-option-group-name {
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .mobile-option-values {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .mobile-option-value {
            padding: 0.125rem 0.375rem;
            background-color: var(--gray-100);
            border-radius: var(--radius-sm);
            font-size: 0.6875rem;
            color: var(--gray-700);
        }

        .mobile-product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-100);
        }

        .mobile-product-status {
            font-size: 0.75rem;
            font-weight: 500;
        }

        .mobile-product-status.active {
            color: var(--success);
        }

        .mobile-product-status.inactive {
            color: var(--danger);
        }

        .mobile-product-actions {
            display: flex;
            gap: 0.375rem;
        }

        .see-more-btn {
            background: none;
            border: none;
            color: var(--primary-600);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.125rem 0.25rem;
            margin-top: 0.25rem;
        }

        .see-more-btn:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }

        .desktop-only {
            display: table;
        }

        .mobile-only {
            display: none;
        }

        @media screen and (max-width: 768px) {
            .desktop-only {
                display: none;
            }
            
            .mobile-only {
                display: block;
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
                            <span>AD</span>
                        </div>
                        <div class="user-details">
                            <span class="user-name">Admin</span>
                            <span class="user-role">Administrator</span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </button>

                    <div class="dropdown-menu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span>AD</span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name">Admin</div>
                                <div class="dropdown-email">admin@phortay.edu.my</div>
                                <div class="dropdown-meta">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Administrator</span>
                                    <i class="fas fa-circle" style="font-size: 0.25rem;"></i>
                                    <i class="fas fa-clock"></i>
                                    <span>Last login: <?= date("M j, Y g:i A", strtotime("-1 hour")) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="edit_admin.php?id=<?= urlencode($admin_id) ?>" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            <span>Account Settings</span>
                        </a>

                        <a href="system_settings.php" class="dropdown-item">
                            <i class="fas fa-sliders-h"></i>
                            <span>School Settings</span>
                        </a>

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

                    <a href="view_products.php" class="nav-item active">
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
                    
                     <a href="admin_monthly_report.php" class="nav-item ">
                        <i class="fas fa-chart-bar"></i>
                        <span>Monthly Report</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="container">
                <header class="page-header">
                    <div>
                        <h1 class="page-title">
                            Product Inventory
                        </h1>
                        <p class="breadcrumb">
                            <a href="dashboard.php">Dashboard</a> / Products
                        </p>
                    </div>
                    <div class="page-actions">
                        <a href="admin_monthly_report.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-file-alt"></i>
                            Report
                        </a>
                        <a href="add_product.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i>
                            Add Product
                        </a>
                    </div>
                </header>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-list"></i>
                            Product List
                        </h2>
                        <form method="GET" class="search-form" id="searchForm">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Search
                            </button>
                            <?php if (!empty($search)): ?>
                            <a href="view_products.php" class="btn btn-outline" id="clearSearchBtn">
                                Clear
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <p class="empty-state-text">No products found matching your search criteria.</p>
                            </div>
                        <?php else: ?>
                        <!-- Desktop Table View -->
                        <div class="desktop-only">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Options</th>
                                            <th>Created</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $index => $p): ?>
                                        <tr id="product-row-<?= $p['id'] ?>" <?= isset($p['is_active']) && !$p['is_active'] ? 'style="opacity: 0.7;"' : '' ?>>
                                            <td data-label="No"><?= $offset + $index + 1 ?></td>
                                            <td data-label="Image">
                                                <?php
                                                $images = $product->getImages($p['id']);
                                                if (empty($images) && $p['image'] && file_exists("../uploads/" . $p['image'])) {
                                                    $images = [$p['image']];
                                                }
                                                if (!empty($images)):
                                                    foreach ($images as $img):
                                                        if ($img && file_exists("../uploads/" . $img)):
                                                ?>
                                                <img src="../uploads/<?= htmlspecialchars($img) ?>" 
                                                     alt="Product Image" 
                                                     class="product-img quantity-edit-btn" 
                                                     data-id="<?= $p['id'] ?>"
                                                     style="cursor: pointer;">
                                                <?php
                                                        endif;
                                                    endforeach;
                                                else:
                                                ?>
                                                <div class="product-img-placeholder">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Name">
                                                <div style="font-weight: 500;"><?= highlight($p['name'], $search) ?></div>
                                                <?php if (isset($p['type']) && $p['type'] === 'package'): ?>
                                                    <span class="badge badge-info" style="margin-top: 0.25rem;">Package</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Description">
                                                <div class="text-truncate" title="<?= htmlspecialchars($p['description']) ?>">
                                                    <?= highlight(nl2br(htmlspecialchars(substr($p['description'], 0, 60))), $search) ?><?= strlen($p['description']) > 60 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td data-label="Category"><?= highlight($p['category_name'], $search) ?></td>
                                            <td data-label="Price" style="font-weight: 500;"><?= highlight('RM' . number_format($p['price'], 2), $search) ?></td>
                                            <td data-label="Stock" class="quantity-cell">
                                                <span class="product-quantity" data-id="<?= $p['id'] ?>">
                                                    <?= highlight(isset($p['quantity']) ? (int)$p['quantity'] : 0, $search) ?>
                                                </span>
                                                <?php if (isset($p['quantity']) && $p['quantity'] > 0 && $p['quantity'] <= 10): ?>
                                                    <span class="badge badge-warning">Low</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Options">
                                                <?php
                                                $groups = $groupObj->getByProduct($p['id']);
                                                if (!empty($groups)) {
                                                    foreach ($groups as $g) {
                                                        echo "<div style='margin-bottom: 0.25rem;'><strong style='display: block; font-size: 0.75rem;'>" . highlight($g['name'], $search) . "</strong>";
                                                        $values = $valueObj->getByGroup($g['id']);
                                                        $vals = array_map(fn($v) => highlight($v['value'], $search), $values);
                                                        echo "<span style='font-size: 0.75rem; color: var(--gray-500);'>" . implode(", ", $vals) . "</span></div>";
                                                    }
                                                } else {
                                                    echo "<span class='text-muted text-sm'>None</span>";
                                                }
                                                ?>
                                            </td>
                                            <td data-label="Created">
                                                <div><?= highlight(date('M d, Y', strtotime($p['created_at'])), $search) ?></div>
                                                <div class="text-muted text-sm"><?= highlight(date('h:i A', strtotime($p['created_at'])), $search) ?></div>
                                            </td>
                                            <td data-label="Status">
                                                <button 
                                                    type="button" 
                                                    class="btn btn-sm status-toggle-btn <?= isset($p['is_active']) && $p['is_active'] ? 'btn-success' : 'btn-danger' ?>" 
                                                    data-id="<?= $p['id'] ?>" 
                                                    data-active="<?= isset($p['is_active']) && $p['is_active'] ? '1' : '0' ?>"
                                                >
                                                    <?= isset($p['is_active']) && $p['is_active'] ? 'Active' : 'Inactive' ?>
                                                </button>
                                            </td>
                                            <td data-label="Actions">
                                                <div class="btn-group">
                                                    <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm delete-product-btn" data-id="<?= $p['id'] ?>" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="mobile-only">
                            <?php foreach ($products as $index => $p): ?>
                            <div class="mobile-product-card" id="mobile-product-<?= $p['id'] ?>" <?= isset($p['is_active']) && !$p['is_active'] ? 'style="opacity: 0.7;"' : '' ?>>
                                <div class="mobile-product-header">
                                    <?php
                                    $images = $product->getImages($p['id']);
                                    if (empty($images) && $p['image'] && file_exists("../uploads/" . $p['image'])) {
                                        $images = [$p['image']];
                                    }
                                    if (!empty($images)):
                                        foreach ($images as $img):
                                            if ($img && file_exists("../uploads/" . $img)):
                                    ?>
                                    <img src="../uploads/<?= htmlspecialchars($img) ?>" 
                                         alt="Product Image" 
                                         class="mobile-product-image quantity-edit-btn" 
                                         data-id="<?= $p['id'] ?>"
                                         style="cursor: pointer;">
                                    <?php
                                            endif;
                                        endforeach;
                                    else:
                                    ?>
                                    <div class="mobile-product-image-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mobile-product-info">
                                        <h3 class="mobile-product-name"><?= highlight($p['name'], $search) ?></h3>
                                        <div class="mobile-product-description collapsed" id="description-<?= $p['id'] ?>">
                                            <?= highlight(htmlspecialchars($p['description']), $search) ?>
                                        </div>
                                        <?php if (strlen($p['description']) > 100): ?>
                                        <button class="see-more-btn" data-target="description-<?= $p['id'] ?>">See more</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mobile-product-meta">
                                    <div class="mobile-product-meta-item">
                                        <strong>Category:</strong> <?= highlight($p['category_name'], $search) ?>
                                    </div>
                                    <div class="mobile-product-meta-item">
                                        <strong>Price:</strong> <?= highlight('RM' . number_format($p['price'], 2), $search) ?>
                                    </div>
                                    <div class="mobile-product-meta-item">
                                        <strong>Stock:</strong> 
                                        <span class="product-quantity" data-id="<?= $p['id'] ?>">
                                            <?= highlight(isset($p['quantity']) ? (int)$p['quantity'] : 0, $search) ?>
                                        </span>
                                        <?php if (isset($p['quantity']) && $p['quantity'] > 0 && $p['quantity'] <= 10): ?>
                                            <span class="badge badge-warning">Low</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mobile-product-meta-item">
                                        <strong>Created:</strong> <?= highlight(date('M d, Y', strtotime($p['created_at'])), $search) ?>
                                    </div>
                                </div>
                                
                                <?php
                                $groups = $groupObj->getByProduct($p['id']);
                                if (!empty($groups)): 
                                ?>
                                <div class="mobile-product-options">
                                    <?php foreach ($groups as $g): ?>
                                    <div class="mobile-option-group">
                                        <div class="mobile-option-group-name"><?= highlight($g['name'], $search) ?></div>
                                        <div class="mobile-option-values">
                                            <?php
                                            $values = $valueObj->getByGroup($g['id']);
                                            foreach ($values as $v):
                                            ?>
                                            <span class="mobile-option-value"><?= highlight($v['value'], $search) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mobile-product-footer">
                                    <div class="mobile-product-status <?= isset($p['is_active']) && $p['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= isset($p['is_active']) && $p['is_active'] ? 'Active' : 'Inactive' ?>
                                    </div>
                                    
                                    <div class="mobile-product-actions">
                                        <button 
                                            type="button" 
                                            class="btn btn-icon status-toggle-btn <?= isset($p['is_active']) && $p['is_active'] ? 'btn-success' : 'btn-danger' ?>" 
                                            data-id="<?= $p['id'] ?>" 
                                            data-active="<?= isset($p['is_active']) && $p['is_active'] ? '1' : '0' ?>"
                                            title="<?= isset($p['is_active']) && $p['is_active'] ? 'Active' : 'Inactive' ?>"
                                        >
                                            <i class="fas <?= isset($p['is_active']) && $p['is_active'] ? 'fa-check' : 'fa-times' ?>"></i>
                                        </button>
                                        
                                        <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn btn-icon btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <button type="button" class="btn btn-icon btn-danger delete-product-btn" data-id="<?= $p['id'] ?>" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                                        &laquo;
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p == $page): ?>
                                        <span class="page-link active"><?= $p ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link"><?= $p ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                                        &raquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <a href="dashboard.php" class="btn btn-outline" style="margin-top: 1.5rem;">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button type="button" class="close-btn" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Adjust Quantity Modal - Improved Version -->
    <div class="modal" id="quantityModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Adjust Quantity</h3>
                <button type="button" class="close-btn" id="closeQuantityModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="quantity-display">
                    <span>Current Quantity:</span>
                    <span id="currentQuantity" class="quantity-value">0</span>
                </div>
                <form id="quantityForm">
                    <input type="hidden" name="product_id" id="quantityProductId">
                    <div class="quantity-controls">
                        <div class="quantity-row">
                            <button type="button" class="quantity-btn" data-delta="-1000">-1000</button>
                            <button type="button" class="quantity-btn" data-delta="-100">-100</button>
                            <button type="button" class="quantity-btn" data-delta="-10">-10</button>
                            <button type="button" class="quantity-btn" data-delta="-1">-1</button>
                        </div>
                        <div class="quantity-input-container">
                            <button type="button" class="quantity-adjust-btn minus" id="minusBtn">-</button>
                            <input type="number" name="quantity" id="quantityInput" class="quantity-input" value="1" min="1" step="1">
                            <button type="button" class="quantity-adjust-btn" id="plusBtn">+</button>
                        </div>
                        <div class="quantity-row">
                            <button type="button" class="quantity-btn" data-delta="1">+1</button>
                            <button type="button" class="quantity-btn" data-delta="10">+10</button>
                            <button type="button" class="quantity-btn" data-delta="100">+100</button>
                            <button type="button" class="quantity-btn" data-delta="1000">+1000</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelQuantityBtn">Cancel</button>
                <button type="submit" form="quantityForm" class="btn btn-primary">Update Quantity</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toastNotification">
        <span class="toast-icon">✓</span>
        <span id="toastMessage">Product deleted successfully</span>
    </div>

    <audio id="orderSound" src="../assets/sounds/error-tone-10-363618.mp3" preload="auto"></audio>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // === Sidebar Toggle Functionality ===
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.menu-toggle');
        const sidebarOverlay = document.querySelector('.sidebar-overlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
        }
        
        sidebarToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // === User Dropdown ===
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

        // === Clear Search Button ===
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'view_products.php';
            });
        }

        // === Config ===
        let lastPendingCount = <?= $pendingOrdersCount ?> || 0;
        const audio = document.getElementById('orderSound');
        
        // === Unlock audio on first user interaction ===
        function unlockAudio() {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(err => {
                console.warn("Audio unlock failed", err);
            });
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        }
        window.addEventListener('click', unlockAudio);
        window.addEventListener('touchstart', unlockAudio);

        // === Check new orders every 10 seconds ===
        function checkNewOrders() {
            fetch('check_pending_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined' && data.count > lastPendingCount) {
                        audio.play().catch(err => console.warn("Audio play error", err));
                        lastPendingCount = data.count;
                    }
                })
                .catch(err => console.error('Error checking orders:', err));
        }
        setInterval(checkNewOrders, 10000);

        // === Toast Notification ===
        const toast = document.getElementById('toastNotification');
        const toastMsg = document.getElementById('toastMessage');
        function showToast(message, isError = false) {
            toastMsg.textContent = message;
            toast.className = isError ? 'toast error show' : 'toast show';
            setTimeout(() => toast.className = 'toast', 3000);
        }

        // === Delete Product Logic ===
        const deleteModal = document.getElementById('deleteModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        let productIdToDelete = null;

        document.querySelectorAll('.delete-product-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                productIdToDelete = this.dataset.id;
                deleteModal.classList.add('show');
            });
        });

        cancelDeleteBtn?.addEventListener('click', () => {
            deleteModal.classList.remove('show');
            productIdToDelete = null;
        });

        closeDeleteModal?.addEventListener('click', () => {
            deleteModal.classList.remove('show');
            productIdToDelete = null;
        });

        confirmDeleteBtn?.addEventListener('click', () => {
            if (!productIdToDelete) return;
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<span class="spinner"></span> Deleting...';

            fetch('delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(productIdToDelete)}&ajax=1`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Remove both desktop and mobile views
                    const row = document.getElementById(`product-row-${productIdToDelete}`);
                    const mobileCard = document.getElementById(`mobile-product-${productIdToDelete}`);
                    
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    }
                    
                    if (mobileCard) {
                        mobileCard.style.transition = 'all 0.3s ease';
                        mobileCard.style.opacity = '0';
                        setTimeout(() => mobileCard.remove(), 300);
                    }
                    
                    showToast('Product deleted successfully');
                } else {
                    showToast(data.message || 'Failed to delete product', true);
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                showToast('An error occurred while deleting the product', true);
            })
            .finally(() => {
                deleteModal.classList.remove('show');
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
                productIdToDelete = null;
            });
        });

        // === Quantity Update Logic ===
        const quantityModal = document.getElementById('quantityModal');
        const cancelQuantityBtn = document.getElementById('cancelQuantityBtn');
        const closeQuantityModal = document.getElementById('closeQuantityModal');
        const quantityForm = document.getElementById('quantityForm');
        const currentQty = document.getElementById('currentQuantity');
        const qtyInput = document.getElementById('quantityInput');
        const qtyProductId = document.getElementById('quantityProductId');
        const minusBtn = document.getElementById('minusBtn');
        const plusBtn = document.getElementById('plusBtn');
        let productIdToEdit = null;

        document.querySelectorAll('.quantity-edit-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                productIdToEdit = this.dataset.id;
                const qtyCell = document.querySelector(`.product-quantity[data-id="${productIdToEdit}"]`);
                const currentVal = qtyCell.innerText.trim();
                qtyProductId.value = productIdToEdit;
                currentQty.innerText = currentVal;
                qtyInput.value = currentVal;
                quantityModal.classList.add('show');
            });
        });

        cancelQuantityBtn?.addEventListener('click', () => {
            quantityModal.classList.remove('show');
            productIdToEdit = null;
        });

        closeQuantityModal?.addEventListener('click', () => {
            quantityModal.classList.remove('show');
            productIdToEdit = null;
        });

        // Quantity step adjustment buttons
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const delta = parseInt(btn.dataset.delta, 10);
                let value = parseInt(qtyInput.value) || 0;
                value = Math.max(1, value + delta);
                qtyInput.value = value;
            });
        });

        minusBtn?.addEventListener('click', () => {
            let value = parseInt(qtyInput.value) || 0;
            if (value > 1) qtyInput.value = value - 1;
        });

        plusBtn?.addEventListener('click', () => {
            qtyInput.value = (parseInt(qtyInput.value) || 0) + 1;
        });

        quantityForm?.addEventListener('submit', e => {
            e.preventDefault();
            const formData = new FormData(quantityForm);
            const queryString = new URLSearchParams(formData).toString();
            const submitBtn = quantityForm.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Updating...';

            fetch('update_product_quantity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: queryString
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update both desktop and mobile views
                    const qtyCells = document.querySelectorAll(`.product-quantity[data-id="${productIdToEdit}"]`);
                    qtyCells.forEach(cell => {
                        cell.innerText = qtyInput.value;
                    });

                    // Update low stock label in both views
                    const qtyVal = parseInt(qtyInput.value);
                    
                    // Desktop view
                    const row = document.getElementById(`product-row-${productIdToEdit}`);
                    if (row) {
                        let lowStock = row.querySelector('.quantity-cell .badge-warning');
                        
                        if (qtyVal <= 10) {
                            if (!lowStock) {
                                lowStock = document.createElement('span');
                                lowStock.className = 'badge badge-warning';
                                lowStock.textContent = 'Low';
                                row.querySelector('.quantity-cell').appendChild(lowStock);
                            }
                        } else if (lowStock) {
                            lowStock.remove();
                        }
                    }
                    
                    // Mobile view
                    const mobileCard = document.getElementById(`mobile-product-${productIdToEdit}`);
                    if (mobileCard) {
                        let lowStock = mobileCard.querySelector('.badge-warning');
                        
                        if (qtyVal <= 10) {
                            if (!lowStock) {
                                lowStock = document.createElement('span');
                                lowStock.className = 'badge badge-warning';
                                lowStock.textContent = 'Low';
                                const metaItem = mobileCard.querySelector('.mobile-product-meta-item:has(.product-quantity)');
                                if (metaItem) metaItem.appendChild(lowStock);
                            }
                        } else if (lowStock) {
                            lowStock.remove();
                        }
                    }

                    showToast('Quantity updated successfully');
                } else {
                    showToast(data.message || 'Failed to update quantity', true);
                }
            })
            .catch(err => {
                console.error('Quantity update error:', err);
                showToast('An error occurred while updating the quantity', true);
            })
            .finally(() => {
                quantityModal.classList.remove('show');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Quantity';
                productIdToEdit = null;
            });
        });

        // === Toggle Product Active Status ===
        document.querySelectorAll('.status-toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const current = this.dataset.active;
                const newVal = current === "1" ? "0" : "1";
                
                // Optimistically update the UI for both desktop and mobile
                this.dataset.active = newVal;
                
                // Desktop view
                if (this.classList.contains('btn-success') || this.classList.contains('btn-danger')) {
                    this.classList.toggle('btn-success', newVal === "1");
                    this.classList.toggle('btn-danger', newVal === "0");
                    this.textContent = newVal === "1" ? 'Active' : 'Inactive';
                } 
                // Mobile view
                else {
                    this.classList.toggle('btn-success', newVal === "1");
                    this.classList.toggle('btn-danger', newVal === "0");
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = newVal === "1" ? 'fas fa-check' : 'fas fa-times';
                    }
                    
                    // Update status text in mobile view
                    const statusEl = document.querySelector(`#mobile-product-${id} .mobile-product-status`);
                    if (statusEl) {
                        statusEl.textContent = newVal === "1" ? 'Active' : 'Inactive';
                        statusEl.className = `mobile-product-status ${newVal === "1" ? 'active' : 'inactive'}`;
                    }
                }
                
                // Add loading state
                const originalHTML = this.innerHTML;
                this.innerHTML = '<span class="spinner"></span>';
                this.disabled = true;

                fetch('toggle_product_active.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${id}&is_active=${newVal}`
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        // Revert if failed
                        this.dataset.active = current;
                        
                        // Desktop view
                        if (this.classList.contains('btn-success') || this.classList.contains('btn-danger')) {
                            this.classList.toggle('btn-success', current === "1");
                            this.classList.toggle('btn-danger', current === "0");
                            this.textContent = current === "1" ? 'Active' : 'Inactive';
                        } 
                        // Mobile view
                        else {
                            this.classList.toggle('btn-success', current === "1");
                            this.classList.toggle('btn-danger', current === "0");
                            const icon = this.querySelector('i');
                            if (icon) {
                                icon.className = current === "1" ? 'fas fa-check' : 'fas fa-times';
                            }
                            
                            // Revert status text in mobile view
                            const statusEl = document.querySelector(`#mobile-product-${id} .mobile-product-status`);
                            if (statusEl) {
                                statusEl.textContent = current === "1" ? 'Active' : 'Inactive';
                                statusEl.className = `mobile-product-status ${current === "1" ? 'active' : 'inactive'}`;
                            }
                        }
                        
                        showToast(data.message || "Failed to update status", true);
                    } else {
                        showToast(`Product status updated to ${newVal === "1" ? 'Active' : 'Inactive'}`);
                        
                        // Move the rows to the appropriate position with animation
                        const row = document.getElementById(`product-row-${id}`);
                        const mobileCard = document.getElementById(`mobile-product-${id}`);
                        
                        if (row) {
                            row.style.transition = 'all 0.5s ease';
                            
                            if (newVal === "0") {
                                // When setting to inactive, move down with animation
                                row.style.opacity = '0.7';
                                
                                // After a short delay to allow opacity animation to start
                                setTimeout(() => {
                                    const tbody = row.parentNode;
                                    tbody.removeChild(row);
                                    tbody.appendChild(row); // Move to bottom
                                }, 200);
                            } else {
                                // When setting to active, move up with animation
                                row.style.opacity = '1';
                                
                                setTimeout(() => {
                                    const tbody = row.parentNode;
                                    tbody.removeChild(row);
                                    
                                    // Find the first inactive product to insert before it
                                    let insertBefore = null;
                                    const rows = tbody.querySelectorAll('tr');
                                    for (let i = 0; i < rows.length; i++) {
                                        const btn = rows[i].querySelector('.status-toggle-btn');
                                        if (btn && btn.dataset.active === "0") {
                                            insertBefore = rows[i];
                                            break;
                                        }
                                    }
                                    
                                    if (insertBefore) {
                                        tbody.insertBefore(row, insertBefore);
                                    } else {
                                        tbody.appendChild(row);
                                    }
                                }, 200);
                            }
                        }
                        
                        if (mobileCard) {
                            mobileCard.style.transition = 'all 0.5s ease';
                            
                            if (newVal === "0") {
                                // When setting to inactive, move down with animation
                                mobileCard.style.opacity = '0.7';
                                
                                // After a short delay to allow opacity animation to start
                                setTimeout(() => {
                                    const container = mobileCard.parentNode;
                                    container.removeChild(mobileCard);
                                    container.appendChild(mobileCard); // Move to bottom
                                }, 200);
                            } else {
                                // When setting to active, move up with animation
                                mobileCard.style.opacity = '1';
                                
                                setTimeout(() => {
                                    const container = mobileCard.parentNode;
                                    container.removeChild(mobileCard);
                                    
                                    // Find the first inactive product to insert before it
                                    let insertBefore = null;
                                    const cards = container.querySelectorAll('.mobile-product-card');
                                    for (let i = 0; i < cards.length; i++) {
                                        const btn = cards[i].querySelector('.status-toggle-btn');
                                        if (btn && btn.dataset.active === "0") {
                                            insertBefore = cards[i];
                                            break;
                                        }
                                    }
                                    
                                    if (insertBefore) {
                                        container.insertBefore(mobileCard, insertBefore);
                                    } else {
                                        container.appendChild(mobileCard);
                                    }
                                }, 200);
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Toggle status error:', err);
                    // Revert on error
                    this.dataset.active = current;
                    
                    // Desktop view
                    if (this.classList.contains('btn-success') || this.classList.contains('btn-danger')) {
                        this.classList.toggle('btn-success', current === "1");
                        this.classList.toggle('btn-danger', current === "0");
                        this.textContent = current === "1" ? 'Active' : 'Inactive';
                    } 
                    // Mobile view
                    else {
                        this.classList.toggle('btn-success', current === "1");
                        this.classList.toggle('btn-danger', current === "0");
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.className = current === "1" ? 'fas fa-check' : 'fas fa-times';
                        }
                        
                        // Revert status text in mobile view
                        const statusEl = document.querySelector(`#mobile-product-${id} .mobile-product-status`);
                        if (statusEl) {
                            statusEl.textContent = current === "1" ? 'Active' : 'Inactive';
                            statusEl.className = `mobile-product-status ${current === "1" ? 'active' : 'inactive'}`;
                        }
                    }
                    
                    showToast("An error occurred while updating status", true);
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                });
            });
        });

        // === See More functionality for mobile view ===
        document.querySelectorAll('.see-more-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                
                if (targetEl) {
                    if (targetEl.classList.contains('collapsed')) {
                        targetEl.classList.remove('collapsed');
                        this.textContent = 'See less';
                    } else {
                        targetEl.classList.add('collapsed');
                        this.textContent = 'See more';
                    }
                }
            });
        });

        // === Close modal when clicking outside ===
        window.addEventListener('click', e => {
            if (e.target === deleteModal) {
                deleteModal.classList.remove('show');
                productIdToDelete = null;
            }
            if (e.target === quantityModal) {
                quantityModal.classList.remove('show');
                productIdToEdit = null;
            }
        });
    });
    </script>
</body>
</html>