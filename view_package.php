<?php
require_once "../classes/Package.php";
require_once "../classes/Product.php";
require_once "../classes/PackageOption.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/ProductPackage.php";
require_once "../classes/OrderManager.php";
session_start();

$admin_id = $_SESSION['admin_id']; 

$package = new Package();
$product = new Product();
$orderManager = new OrderManager();

$search = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $packages = $package->searchPackages($search);
} else {
    $packages = $package->getAllPackages();
}

// Handle delete via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $packageId = $_POST['delete_id'];
    $db = new DB();
    $pdo = $db->getConnection();
    // Delete order_items referencing this package
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE package_id = ?");
    $stmt->execute([$packageId]);
    // Now delete the package
    $result = $package->delete($packageId);
    echo json_encode(['success' => $result]);
    exit;
}

// Pagination logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 10;
$totalPackages = count($packages);
$totalPages = ceil($totalPackages / $itemsPerPage);
$offset = ($page - 1) * $itemsPerPage;
$packages = array_slice($packages, $offset, $itemsPerPage);

$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
$lowStockCount = $product->countLowStock(10); // 10 is the threshold

function highlight($text, $search) {
    if (!$search) return htmlspecialchars($text);
    $textEsc = htmlspecialchars($text);
    $searchEsc = preg_quote($search, '/');
    return preg_replace(
        "/($searchEsc)/i",
        '<span class="highlight">$1</span>',
        $textEsc
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>View Packages | School Project</title>

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
        }

        /* Card */
        .card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: var(--space-md);
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            background: var(--white);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .table th {
            background-color: var(--primary-50);
            color: var(--primary-800);
            font-weight: 600;
            text-align: left;
            padding: var(--space-sm);
            white-space: nowrap;
            font-size: 0.8125rem;
        }

        .table td {
            padding: var(--space-sm);
            border-top: 1px solid var(--gray-200);
            vertical-align: top;
            font-size: 0.8125rem;
        }

        .table tr:hover {
            background-color: var(--gray-50);
        }

        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
        }

        .product-thumbnail {
            width: 25px;
            height: 25px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200);
        }

        .product-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
            font-size: 0.9375rem;
        }

        .product-description {
            color: var(--gray-500);
            font-size: 0.8125rem;
            margin-bottom: var(--space-xs);
        }

        .product-price {
            font-weight: 600;
            color: var(--primary-700);
            font-size: 0.9375rem;
        }

        .product-item {
            padding: var(--space-xs) 0;
            border-bottom: 1px dashed var(--gray-200);
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-item-name {
            font-weight: 500;
            color: var(--gray-800);
            font-size: 0.8125rem;
        }

        .product-item-option {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-style: italic;
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

        /* Highlight */
        .highlight {
            background-color: #FFF9C4;
            color: #795548;
            padding: 0 2px;
            border-radius: 2px;
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
            
            .product-image {
                width: 60px;
                height: 60px;
            }
            
            .product-thumbnail {
                width: 30px;
                height: 30px;
            }
            
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9375rem;
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
                            <span>U</span>
                        </div>
                        <div class="user-details">
                            <span class="user-name">Admin User</span>
                            <span class="user-role">Administrator</span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.875rem;"></i>
                    </button>

                    <div class="dropdown-menu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span>U</span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name">Admin User</div>
                                <div class="dropdown-email">admin@example.com</div>
                                <div class="dropdown-meta">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Administrator</span>
                                    <i class="fas fa-circle" style="font-size: 0.25rem;"></i>
                                    <i class="fas fa-clock"></i>
                                    <span>Last login: Just now</span>
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

                    <a href="view_package.php" class="nav-item active">
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
            <nav class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / Packages
            </nav>

            <div class="page-header">
                <h1 class="page-title">Available Products & Packages</h1>
                <div class="page-actions">
                    <a href="create_package.php" class="btn btn-primary btn-block">
                        <i class="fas fa-plus"></i> Add Package
                    </a>
                </div>
            </div>

            <div class="card">
                <form method="GET" id="packageSearchForm" class="search-form">
                    <input type="text" 
                           name="search" 
                           id="searchInput"
                           class="search-input" 
                           placeholder="Search packages..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <div class="search-buttons">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="button" id="clearSearchBtn" class="btn btn-outline">Clear</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <?php if (empty($packages)): ?>
                    <div class="empty-state">
                        <p>No packages found matching your search.</p>
                        <a href="?" class="btn btn-primary mt-3">Show All Packages</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Package Details</th>
                                    <th>Price</th>
                                    <th>Visibility</th>
                                    <th>Gender</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $pkg): ?>
                                    <tr id="package-row-<?= $pkg['id'] ?>">
                                        <td>
                                            <?php if (!empty($pkg['image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($pkg['image']) ?>" class="product-image" alt="Package Image">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="product-name"><?= highlight($pkg['name'], $search) ?></div>
                                            <?php if (!empty($pkg['description'])): ?>
                                                <div class="product-description"><?= highlight($pkg['description'], $search) ?></div>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <?php
                                                $items = $package->getProductsToPackage($pkg['id']);
                                                foreach ($items as $item):
                                                    $productImages = method_exists($product, 'getImagesByProductId')
                                                        ? $product->getImagesByProductId($item['id'])
                                                        : [];
                                                    $firstImage = !empty($productImages) && !empty($productImages[0]['image'])
                                                        ? $productImages[0]['image']
                                                        : 'default.jpg';
                                                ?>
                                                    <div class="product-item">
                                                        <div class="product-item-name"><?= highlight($item['name'], $search) ?></div>
                                                        <div class="d-flex justify-content-between">
                                                            <span><?= highlight('RM' . number_format($item['price'], 2), $search) ?></span>
                                                        </div>
                                                        <?php if (!empty($item['option_group']) && !empty($item['option_value'])): ?>
                                                            <div class="product-item-option">
                                                                <?= highlight($item['option_group'], $search) ?>: <?= highlight($item['option_value'], $search) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <img src="../uploads/<?= htmlspecialchars($firstImage) ?>" class="product-thumbnail mt-1" alt="Product Image">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-price">RM<?= number_format($pkg['price'], 2) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                                $visibleLabel = "Both";
                                                if (($pkg['visible_to'] ?? 'both') === 'student') {
                                                    $visibleLabel = "Student Only";
                                                } elseif (($pkg['visible_to'] ?? 'both') === 'guest') {
                                                    $visibleLabel = "Guest Only";
                                                }
                                                echo highlight($visibleLabel, $search);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                                $genderLabel = "Any";
                                                if (!empty($pkg['gender']) && $pkg['gender'] !== 'both') {
                                                    if ($pkg['gender'] === 'male') $genderLabel = "Male Only";
                                                    elseif ($pkg['gender'] === 'female') $genderLabel = "Female Only";
                                                    else $genderLabel = $pkg['gender'];
                                                }
                                                echo highlight($genderLabel, $search);
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_package.php?id=<?= $pkg['id'] ?>" class="btn btn-outline btn-sm">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="showDeleteModal(<?= $pkg['id'] ?>, '<?= htmlspecialchars(addslashes($pkg['name'])) ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link <?= $p == $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="dashboard.php" class="btn btn-outline btn-block">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the package "<strong id="packageNameToDelete"></strong>"?</p>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="notification">
        <i class="fas fa-check-circle notification-icon"></i>
        <span class="notification-message"></span>
    </div>

    <script>
    // Global variables
    let currentPackageIdToDelete = null;
    const deleteModal = document.getElementById('deleteModal');
    const notification = document.getElementById('notification');
    const notificationMessage = document.querySelector('.notification-message');
    const notificationIcon = document.querySelector('.notification-icon');

    // Show delete confirmation modal
    function showDeleteModal(packageId, packageName) {
        currentPackageIdToDelete = packageId;
        document.getElementById('packageNameToDelete').textContent = packageName;
        deleteModal.classList.add('show');
    }

    // Close delete modal
    function closeDeleteModal() {
        deleteModal.classList.remove('show');
        currentPackageIdToDelete = null;
    }

    // Confirm deletion
    function confirmDelete() {
        if (!currentPackageIdToDelete) return;
        
        const formData = new FormData();
        formData.append('delete_id', currentPackageIdToDelete);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Package deleted successfully!', 'success');
                const row = document.getElementById(`package-row-${currentPackageIdToDelete}`);
                if (row) {
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
            } else {
                showNotification('Failed to delete package', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred', 'error');
        })
        .finally(() => {
            closeDeleteModal();
        });
    }

    // Show notification
    function showNotification(message, type = 'success') {
        notificationMessage.textContent = message;
        notification.className = `notification ${type} show`;
        
        // Update icon based on type
        notificationIcon.className = `fas ${
            type === 'success' ? 'fa-check-circle' : 
            type === 'error' ? 'fa-exclamation-circle' : 
            'fa-info-circle'
        } notification-icon`;
        
        // Hide after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }

    // Clear search
    document.getElementById('clearSearchBtn').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        document.getElementById('packageSearchForm').submit();
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    });

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

    // Close dropdown when clicking outside (improved)
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('.user-button')) {
            userButton.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('active');
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
                }
            })
            .catch(() => {});
    }

    // Check every 30 seconds
    setInterval(checkNewOrders, 30000);
    </script>
</body>
</html>