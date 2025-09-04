<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include necessary files
include($_SERVER['DOCUMENT_ROOT'] . '/school_project/includes/auth.php');
require_once "../classes/OrderManager.php";
require_once "../classes/Product.php";
require_once "../classes/DB.php";

// Initialize classes
$orderManager = new OrderManager();
$product = new Product();

// Fetch necessary data
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
$shippingOrdersCount = $orderManager->countOrdersByStatus('Shipping');
$pendingAndShippingOrdersCount = $pendingOrdersCount + $shippingOrdersCount;
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

// Language handling
$available_languages = ['en' => 'English', 'ms' => 'Malay', 'zh' => 'Chinese'];
$default_language = 'en';

// Check if language is set in session
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = $default_language;
}

// Check if language change is requested
if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_languages)) {
    $_SESSION['language'] = $_GET['lang'];
}

$current_language = $_SESSION['language'];

// Translation arrays
$translations = [
    'en' => [
        'dashboard' => 'Dashboard',
        'overview' => 'Overview',
        'welcome_back' => 'Welcome back',
        'happening_today' => 'Here\'s what\'s happening today',
        'management' => 'Management',
        'products' => 'Products',
        'categories' => 'Categories',
        'counter' => 'Counter',
        'administration' => 'Administration',
        'students' => 'Students',
        'admins' => 'Admins',
        'orders' => 'Orders',
        'packages' => 'Packages',
        'monthly_report' => 'Monthly Report',
        'product_management' => 'Product Management',
        'manage_inventory' => 'Manage inventory and product details',
        'view_products' => 'View Products',
        'student_administration' => 'Student Administration',
        'manage_student_accounts' => 'Manage student accounts',
        'view_students' => 'View Students',
        'order_processing' => 'Order Processing',
        'process_track_orders' => 'Process and track orders',
        'view_orders' => 'View Orders',
        'counter_system' => 'Counter System',
        'process_student_purchases' => 'Process student purchases',
        'go_to_counter' => 'Go to Counter',
        'admin_management' => 'Admin Management',
        'manage_admin_accounts' => 'Manage administrator accounts',
        'view_admins' => 'View Admins',
        'packages_management' => 'Packages',
        'manage_product_packages' => 'Manage product packages',
        'view_packages' => 'View Packages',
        'account_settings' => 'Account Settings',
        'school_settings' => 'School Settings',
        'logout' => 'Logout',
        'session_expires' => 'Session expires in 30 minutes',
        'last_login' => 'Last login',
        'low_stock' => 'Low Stock',
        'to_process' => 'To Process',
        'main_navigation' => 'Main Navigation',
        'copyright' => 'Copyright',
    ],
    'ms' => [
        'dashboard' => 'Papan Pemuka',
        'overview' => 'Gambaran Keseluruhan',
        'welcome_back' => 'Selamat kembali',
        'happening_today' => 'Inilah yang berlaku hari ini',
        'management' => 'Pengurusan',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'counter' => 'Kaunter',
        'administration' => 'Pentadbiran',
        'students' => 'Pelajar',
        'admins' => 'Pentadbir',
        'orders' => 'Pesanan',
        'packages' => 'Pakej',
        'monthly_report' => 'Laporan Bulanan',
        'product_management' => 'Pengurusan Produk',
        'manage_inventory' => 'Urus inventori dan butiran produk',
        'view_products' => 'Lihat Produk',
        'student_administration' => 'Pentadbiran Pelajar',
        'manage_student_accounts' => 'Urus akaun pelajar',
        'view_students' => 'Lihat Pelajar',
        'order_processing' => 'Pemprosesan Pesanan',
        'process_track_orders' => 'Proses dan jejak pesanan',
        'view_orders' => 'Lihat Pesanan',
        'counter_system' => 'Sistem Kaunter',
        'process_student_purchases' => 'Proses pembelian pelajar',
        'go_to_counter' => 'Pergi ke Kaunter',
        'admin_management' => 'Pengurusan Pentadbir',
        'manage_admin_accounts' => 'Urus akaun pentadbir',
        'view_admins' => 'Lihat Pentadbir',
        'packages_management' => 'Pakej',
        'manage_product_packages' => 'Urus pakej produk',
        'view_packages' => 'Lihat Pakej',
        'account_settings' => 'Tetapan Akaun',
        'school_settings' => 'Tetapan Sekolah',
        'logout' => 'Log Keluar',
        'session_expires' => 'Sesi tamat dalam 30 minit',
        'last_login' => 'Log masuk terakhir',
        'low_stock' => 'Stok Rendah',
        'to_process' => 'Untuk Diproses',
        'main_navigation' => 'Navigasi Utama',
        'copyright' => 'Hak Cipta',
    ],
    'zh' => [
        'dashboard' => '仪表板',
        'overview' => '概览',
        'welcome_back' => '欢迎回来',
        'happening_today' => '这是今天的情况',
        'management' => '管理',
        'products' => '产品',
        'categories' => '类别',
        'counter' => '柜台',
        'administration' => '行政',
        'students' => '学生',
        'admins' => '管理员',
        'orders' => '订单',
        'packages' => '包裹',
        'monthly_report' => '月度报告',
        'product_management' => '产品管理',
        'manage_inventory' => '管理库存和产品详情',
        'view_products' => '查看产品',
        'student_administration' => '学生管理',
        'manage_student_accounts' => '管理学生账户',
        'view_students' => '查看学生',
        'order_processing' => '订单处理',
        'process_track_orders' => '处理和跟踪订单',
        'view_orders' => '查看订单',
        'counter_system' => '柜台系统',
        'process_student_purchases' => '处理学生购买',
        'go_to_counter' => '前往柜台',
        'admin_management' => '管理员管理',
        'manage_admin_accounts' => '管理管理员账户',
        'view_admins' => '查看管理员',
        'packages_management' => '包裹',
        'manage_product_packages' => '管理产品包裹',
        'view_packages' => '查看包裹',
        'account_settings' => '账户设置',
        'school_settings' => '学校设置',
        'logout' => '登出',
        'session_expires' => '会话将在30分钟后过期',
        'last_login' => '最后登录',
        'low_stock' => '低库存',
        'to_process' => '待处理',
        'main_navigation' => '主导航',
        'copyright' => '版权',
    ]
];

// Function to translate text
function t($key) {
    global $translations, $current_language;
    return $translations[$current_language][$key] ?? $key;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo t('dashboard'); ?> | School Project</title>

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

        /* Language Selector */
        .language-selector {
            position: relative;
            margin-right: var(--space-sm);
        }

        .language-button {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-sm);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            background-color: var(--gray-100);
            border: 1px solid var(--gray-200);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .language-button:hover {
            background-color: var(--gray-200);
        }

        .language-button i {
            font-size: 1rem;
        }

        .language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            width: 10rem;
            padding: var(--space-sm) 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(0.5rem);
            transition: var(--transition-slow);
            z-index: 110;
            border: 1px solid var(--gray-200);
        }

        .language-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .language-item {
            padding: var(--space-sm) var(--space-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--gray-700);
            font-size: 0.9375rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .language-item:hover {
            background: var(--gray-100);
            color: var(--primary-700);
        }

        .language-item.active {
            background: var(--primary-50);
            color: var(--primary-700);
            font-weight: 500;
        }

        .language-item.active i {
            color: var(--primary-500);
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
            white-space: nowrap;
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
            flex-shrink: 0;
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
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-name {
            font-weight: 500;
            font-size: 1rem;
            color: var(--gray-800);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }

        .user-role {
            font-size: 0.875rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
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
        }

        .nav-group {
            margin-bottom: var(--space-lg);
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

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        /* Dashboard Cards */
        .dashboard-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--gray-200);
            position: relative;
            display: block;
            color: inherit;
        }

        .dashboard-card:hover {
            transform: translateY(-0.25rem);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-300);
            text-decoration: none;
        }

        .card-header {
            padding: var(--space-lg);
            position: relative;
        }

        .card-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: var(--radius-md);
            background-color: var(--secondary-100);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-md);
            color: var(--secondary-600);
            font-size: 1.75rem;
        }

        .card-badge {
            position: absolute;
            top: var(--space-md);
            right: var(--space-md);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--white);
        }

        .card-badge.warning {
            background-color: var(--warning);
        }

        .card-badge.danger {
            background-color: var(--danger);
        }

        .card-title {
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .card-subtitle {
            font-size: 0.9375rem;
            color: var(--gray-500);
            margin-bottom: var(--space-sm);
        }

        .card-body {
            padding: 0 var(--space-lg) var(--space-lg);
        }

        .card-link {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--primary-600);
            text-decoration: none;
            transition: var(--transition);
            padding: var(--space-xs) 0;
        }

        .card-link:hover {
            color: var(--primary-700);
        }

        .card-link i {
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .card-link:hover i {
            transform: translateX(0.25rem);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .stat-title {
            font-size: 0.9375rem;
            color: var(--gray-500);
            margin-bottom: var(--space-sm);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-xs);
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
           颜色: var(--danger);
        }

        /* Date Display */
        .date-display {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9375rem;
            color: var(--gray-500);
            padding: var(--space-sm) var(--space-md);
            background-color: var(--gray-100);
            border-radius: var(--radius-md);
        }

        .date-display i {
            color: var(--gray-400);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-size: 0.9375rem;
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

        /* Footer */
        .footer {
            padding: var(--space-lg);
            background-color: var(--white);
            color: var(--gray-500);
            font-size: 0.9375rem;
            border-top: 1px solid var(--gray-200);
            margin-left: var(--sidebar-width);
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

        /* Responsive Styles - Extended */
        @media (max-width: 1440px) {
            .main-content {
                padding: var(--space-xl) var(--space-lg);
            }
            
            .dashboard-grid {
                grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
                gap: var(--space-md);
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .sidebar {
                width: 14rem;
                padding: var(--space-lg) var(--space-md);
            }
            
            .nav-item {
                padding: var(--space-sm) var(--space-md);
            }
            
            .dropdown-menu {
                width: 16rem;
            }
            
            .card-header {
                padding: var(--space-md);
            }
            
            .card-icon {
                width: 3rem;
                height: 3rem;
                font-size: 1.5rem;
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 16rem;
                padding: var(--space-lg);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: var(--space-lg);
            }

            .footer {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }
            
            .header {
                padding: 0 var(--space-md);
            }
            
            .logo-text {
                font-size: 1.25rem;
            }
            
            .user-name {
                max-width: 100px;
            }
            
            .user-role {
                max-width: 100px;
            }
            
            .dashboard-grid {
                grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
            }
            
            .dropdown-menu {
                right: 0;
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-height: 4rem;
                --sidebar-width: 14rem;
            }
            
            .main-layout {
                margin-top: 4rem;
            }
            
            .sidebar {
                top: 4rem;
            }
            
            .page-header {
                margin-bottom: var(--space-lg);
            }

            .page-title {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .dropdown-menu {
                width: 14rem;
            }
            
            .card-title {
                font-size: 1.125rem;
            }
            
            .card-subtitle {
                font-size: 0.875rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-display {
                width: 100%;
                justify-content: center;
            }
            
            .dropdown-menu {
                width: 12rem;
            }
            
            .dropdown-header {
                flex-direction: column;
                text-align: center;
            }
            
            .dropdown-user-info {
                text-align: center;
            }
            
            .dropdown-meta {
                justify-content: center;
            }
            
            .user-name {
                max-width: 80px;
            }
            
            .user-role {
                max-width: 80px;
            }
        }

        @media (max-width: 480px) {
            :root {
                --space-xl: 1.5rem;
                --space-lg: 1rem;
                --space-md: 0.75rem;
            }
            
            .header {
                padding: 0 var(--space-sm);
                height: 3.5rem;
            }
            
            .main-layout {
                margin-top: 3.5rem;
            }
            
            .sidebar {
                top: 3.5rem;
                width: 12rem;
                padding: var(--space-md) 0;
            }
            
            .nav-item {
                padding: var(--space-sm);
                font-size: 0.875rem;
            }
            
            .nav-item i {
                width: 1.5rem;
                font-size: 1rem;
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
            
            .user-avatar {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 0.875rem;
            }
            
            .dropdown-menu {
                width: 10rem;
            }
            
            .dropdown-item {
                padding: var(--space-xs) var(--space-sm);
            }
            
            .card-header {
                padding: var(--space-md) var(--space-sm);
            }
            
            .card-body {
                padding: 0 var(--space-sm) var(--space-sm);
            }
            
            .user-name {
                max-width: 60px;
            }
            
            .user-role {
                max-width: 60px;
            }
        }

        @media (max-width: 360px) {
            .header {
                padding: 0 var(--space-xs);
            }
            
            .menu-toggle {
                margin-right: 0;
                padding: var(--space-xs);
            }
            
            .logo-text {
                font-size: 1rem;
            }
            
            .sidebar {
                width: 10rem;
            }
            
            .nav-group-title {
                padding: 0 var(--space-sm) var(--space-xs);
                font-size: 0.75rem;
            }
            
            .dropdown-menu {
                width: 8rem;
            }
            
            .stat-card {
                padding: var(--space-md) var(--space-sm);
            }
            
            .stat-title {
                font-size: 0.875rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .user-name {
                max-width: 40px;
            }
            
            .user-role {
                max-width: 40px;
            }
        }

        /* Print Styles */
        @media print {
            .header,
            .sidebar,
            .footer,
            .menu-toggle {
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
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            :root {
                --white: #1a1a1a;
                --gray-50: #2d2d2d;
                --gray-100: #3d3d3d;
                --gray-200: #4d4d4d;
                --gray-300: #5d5d5d;
                --gray-400: #7a7a7a;
                --gray-500: #9e9e9e;
                --gray-600: #c2c2c2;
                --gray-700: #e0e0e0;
                --gray-800: #f0f0f0;
                --gray-900: #ffffff;
                --black: #ffffff;
            }
            
            .dashboard-card,
            .stat-card {
                border-color: var(--gray-300);
            }
            
            .dropdown-menu {
                border-color: var(--gray-300);
            }
        }

        /* High Contrast Mode */
        @media (prefers-contrast: high) {
            :root {
                --primary-500: #0066cc;
                --primary-600: #004499;
                --secondary-500: #996600;
                --danger: #cc0000;
            }
            
            body {
                -webkit-font-smoothing: none;
            }
            
            .btn {
                border-width: 2px;
            }
            
            .nav-item.active::before {
                width: 4px;
            }
        }

        /* Motion Reduction */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
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
                <!-- Language Selector -->
                <div class="language-selector">
                    <button class="language-button" aria-expanded="false" aria-haspopup="true" aria-label="Language selector">
                        <i class="fas fa-globe"></i>
                        <span><?php echo $available_languages[$current_language]; ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </button>

                    <div class="language-menu" aria-hidden="true">
                        <div class="language-item <?php echo $current_language === 'en' ? 'active' : ''; ?>" data-lang="en">
                            <span>English</span>
                        </div>
                        <div class="language-item <?php echo $current_language === 'ms' ? 'active' : ''; ?>" data-lang="ms">
                            <span>Malay</span>
                        </div>
                        <div class="language-item <?php echo $current_language === 'zh' ? 'active' : ''; ?>" data-lang="zh">
                            <span>Chinese</span>
                        </div>
                    </div>
                </div>

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
                                    <span><?php echo t('last_login'); ?>: <?= htmlspecialchars($last_login) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="edit_admin.php?id=<?= urlencode($admin_id) ?>" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            <span><?php echo t('account_settings'); ?></span>
                        </a>

                        <?php if ($is_admin == 2): ?>
                            <a href="system_settings.php" class="dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span><?php echo t('school_settings'); ?></span>
                            </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>

                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span><?php echo t('logout'); ?></span>
                        </a>

                        <div class="dropdown-footer">
                            <?php echo t('session_expires'); ?>
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
                <div class="sidebar-title"><?php echo t('main_navigation'); ?></div>
            </div>

            <nav class="nav-menu">
                <div class="nav-group">
                    <div class="nav-group-title"><?php echo t('management'); ?></div>

                    <a href="dashboard.php" class="nav-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span><?php echo t('dashboard'); ?></span>
                    </a>

                    <a href="view_products.php" class="nav-item">
                        <i class="fas fa-box-open"></i>
                        <span><?php echo t('products'); ?></span>
                        <?php if ($lowStockCount > 0): ?>
                            <span class="nav-badge"><?= $lowStockCount ?> <?php echo t('low_stock'); ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="view_categories.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span><?php echo t('categories'); ?></span>
                    </a>

                    <a href="counter.php" class="nav-item">
                        <i class="fas fa-cash-register"></i>
                        <span><?php echo t('counter'); ?></span>
                    </a>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title"><?php echo t('administration'); ?></div>

                    <a href="view_user.php" class="nav-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo t('students'); ?></span>
                    </a>

                    <a href="view_admin.php" class="nav-item">
                        <i class="fas fa-user-shield"></i>
                        <span><?php echo t('admins'); ?></span>
                    </a>

                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span><?php echo t('orders'); ?></span>
                        <?php if ($pendingAndShippingOrdersCount > 0): ?>
                            <span class="nav-badge"><?= $pendingAndShippingOrdersCount ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="view_package.php" class="nav-item">
                        <i class="fas fa-boxes"></i>
                        <span><?php echo t('packages'); ?></span>
                    </a>
                    
                    <a href="admin_monthly_report.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span><?php echo t('monthly_report'); ?></span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title"><?php echo t('dashboard'); ?> <?php echo t('overview'); ?></h1>
                <p class="page-subtitle"><?php echo t('welcome_back'); ?>, <?= htmlspecialchars($admin_name ?? 'Admin') ?>! <?php echo t('happening_today'); ?></p>

                <div class="page-actions">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-grid">
                <a href="view_products.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <?php if ($lowStockCount > 0): ?>
                            <span class="card-badge warning"><?= $lowStockCount ?> <?php echo t('low_stock'); ?></span>
                        <?php endif; ?>
                        <h3 class="card-title"><?php echo t('product_management'); ?></h3>
                        <p class="card-subtitle"><?php echo t('manage_inventory'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('view_products'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>

                <a href="view_user.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon" style="background-color: var(--primary-100); color: var(--primary-600);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="card-title"><?php echo t('student_administration'); ?></h3>
                        <p class="card-subtitle"><?php echo t('manage_student_accounts'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('view_students'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>

                <a href="orders.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon" style="background-color: rgba(155, 81, 224, 0.1); color: #9b51e0;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <?php if ($pendingAndShippingOrdersCount > 0): ?>
                            <span class="card-badge danger"><?= $pendingAndShippingOrdersCount ?> <?php echo t('to_process'); ?></span>
                        <?php endif; ?>
                        <h3 class="card-title"><?php echo t('order_processing'); ?></h3>
                        <p class="card-subtitle"><?php echo t('process_track_orders'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('view_orders'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>

                <a href="counter.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <h3 class="card-title"><?php echo t('counter_system'); ?></h3>
                        <p class="card-subtitle"><?php echo t('process_student_purchases'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('go_to_counter'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>

                <a href="view_admin.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3 class="card-title"><?php echo t('admin_management'); ?></h3>
                        <p class="card-subtitle"><?php echo t('manage_admin_accounts'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('view_admins'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>

                <a href="view_package.php" class="dashboard-card">
                    <div class="card-header">
                        <div class="card-icon" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h3 class="card-title"><?php echo t('packages_management'); ?></h3>
                        <p class="card-subtitle"><?php echo t('manage_product_packages'); ?></p>
                    </div>
                    <div class="card-body">
                        <span class="card-link">
                            <span><?php echo t('view_packages'); ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </div>
                </a>
            </div>
        </main>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-content">
            <div>© <?= date('Y') ?> SMJK Phor Tay. <?php echo t('copyright'); ?></div>
        </div>
    </footer>

   <script>
document.addEventListener('DOMContentLoaded', function() {
    let lastPendingCount = <?= (int)$pendingAndShippingOrdersCount ?>;
    let lastLowStockCount = <?= (int)$lowStockCount ?>;

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

    // Toggle language selector
    const languageSelector = document.querySelector('.language-selector');
    const languageButton = languageSelector.querySelector('.language-button');
    const languageMenu = languageSelector.querySelector('.language-menu');

    languageButton.addEventListener('click', function(e) {
        e.stopPropagation();
        const isExpanded = languageButton.getAttribute('aria-expanded') === 'true';
        languageButton.setAttribute('aria-expanded', !isExpanded);
        languageMenu.classList.toggle('active', !isExpanded);
    });

    // Language selection
    const languageItems = document.querySelectorAll('.language-item');
    languageItems.forEach(item => {
        item.addEventListener('click', function() {
            const lang = this.getAttribute('data-lang');
            window.location.href = `?lang=${lang}`;
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-menu') && !e.target.closest('.user-button')) {
            userButton.setAttribute('aria-expanded', 'false');
            dropdownMenu.classList.remove('active');
        }
        
        if (!e.target.closest('.language-menu') && !e.target.closest('.language-button')) {
            languageButton.setAttribute('aria-expanded', 'false');
            languageMenu.classList.remove('active');
        }
    });

    // Check for new orders periodically (both Pending and Shipping)
    function checkNewOrders() {
        fetch('check_pending_and_shipping_orders.php')
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

                    // Update badge in dashboard card
                    let cardBadge = document.querySelector('.dashboard-card:nth-child(3) .card-badge');
                    if (data.count > 0) {
                        if (!cardBadge) {
                            cardBadge = document.createElement('span');
                            cardBadge.className = 'card-badge danger';
                            document.querySelector('.dashboard-card:nth-child(3) .card-header').appendChild(cardBadge);
                        }
                        cardBadge.textContent = data.count + ' <?php echo t('to_process'); ?>';
                    } else if (cardBadge) {
                        cardBadge.remove();
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
                        navBadge.textContent = data.count + ' <?php echo t('low_stock'); ?>';
                    } else if (navBadge) {
                        navBadge.remove();
                    }

                    // Update badge in dashboard card
                    let cardBadge = document.querySelector('.dashboard-card:first-child .card-badge');
                    if (data.count > 0) {
                        if (!cardBadge) {
                            cardBadge = document.createElement('span');
                            cardBadge.className = 'card-badge warning';
                            document.querySelector('.dashboard-card:first-child .card-header').appendChild(cardBadge);
                        }
                        cardBadge.textContent = data.count + ' <?php echo t('low_stock'); ?>';
                    } else if (cardBadge) {
                        cardBadge.remove();
                    }

                    lastLowStockCount = data.count;
                }
            })
            .catch(() => {});
    }

    // Check every 30 seconds
    setInterval(checkNewOrders, 30000);
    setInterval(checkLowStock, 30000);
    
    // Initial check after page load
    setTimeout(checkNewOrders, 1000);
    setTimeout(checkLowStock, 1000);
});
</script>
</body>
</html>