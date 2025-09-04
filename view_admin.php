<?php

require_once "../classes/DB.php";
require_once "../classes/OrderManager.php";
session_start();
// Block access if not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$currentUserIsSuperAdmin = (isset($_SESSION['is_admin']) && intval($_SESSION['is_admin']) === 2);

$db = new DB();
$conn = $db->getConnection();
$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');

// Handle search functionality
$search = '';
$where = "WHERE is_admin > 0"; // Changed to show both regular admins (1) and super admins (2)
$params = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $where .= " AND (name LIKE :search OR email LIKE :search OR phone LIKE :search)";
    $params['search'] = "%$search%";
}

// Handle clear search
if (isset($_GET['clear'])) {
    $search = '';
    header("Location: view_admin.php");
    exit;
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM users $where";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalAdmins = $countStmt->fetchColumn();

$perPage = 10;
$totalPages = ceil($totalAdmins / $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $perPage;

// Get paginated admins - now including is_admin field
$sql = "SELECT id, name, email, phone, created_at, is_admin FROM users $where ORDER BY is_admin DESC, created_at DESC LIMIT :start, :perPage";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle promote to super admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_super_admin_id']) && $currentUserIsSuperAdmin) {
    $promoteId = intval($_POST['promote_super_admin_id']);
    $stmt = $conn->prepare("UPDATE users SET is_admin = 2 WHERE id = ?");
    $stmt->execute([$promoteId]);
    echo '<script>window.location.href = "view_admin.php";</script>';
    exit;
}

// Handle unpromote admin (set is_admin=0 if they have a student_id)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unpromote_admin_id']) && $currentUserIsSuperAdmin) {
    $unpromoteId = intval($_POST['unpromote_admin_id']);
    // Only unpromote if student_id is not null/empty (now applies to both regular and super admins)
    $stmt = $conn->prepare("UPDATE users SET is_admin = 0 WHERE id = ? AND student_id IS NOT NULL AND student_id != ''");
    $stmt->execute([$unpromoteId]);
    echo '<script>window.location.href = "view_admin.php";</script>';
    exit;
}

// Handle delete admin (including self-deletion for super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin_id']) && $currentUserIsSuperAdmin) {
    $deleteId = intval($_POST['delete_admin_id']);
    
    // Check if we're deleting ourselves
    $isDeletingSelf = ($deleteId == $_SESSION['admin_id']);
    
    // First check if this is the last super admin
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 2 AND id != ?");
    $stmt->execute([$deleteId]);
    $remainingSuperAdmins = $stmt->fetchColumn();
    
    if ($remainingSuperAdmins == 0) {
        $_SESSION['error_message'] = "Cannot delete the last super admin account.";
        header("Location: view_admin.php");
        exit;
    }
    
    // Proceed with deletion
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$deleteId]);
    
    // If deleting self, log out
    if ($isDeletingSelf) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    header("Location: view_admin.php?deleted=1");
    exit;
}

// Highlight function that supports all characters
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Management | Admin Panel</title>
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
            -webkit-text-size-adjust: 100%;
        }

        /* Typography */
        h1, h2, h3 {
            font-weight: 600;
            line-height: 1.25;
            color: var(--gray-900);
        }

        h1 {
            font-size: 1.75rem;
            margin-bottom: var(--space-md);
        }

        h2 {
            font-size: 1.375rem;
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
            padding: var(--space-xs);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: var(--transition);
            background: none;
            border: none;
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
            font-size: 1rem;
            position: relative;
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
            text-decoration: none;
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
            left: -100%;
            -webkit-overflow-scrolling: touch;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 0 var(--space-lg) var(--space-lg);
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
            padding: var(--space-lg) 0;
            overflow-y: auto;
        }

        .nav-group {
            margin-bottom: var(--space-lg);
        }

        .nav-group-title {
            padding: 0 var(--space-lg) var(--space-sm);
            font-size: 0.75rem;
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
            width: 100%;
        }

        .page-header {
            margin-bottom: var(--space-lg);
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
            margin-top: var(--space-md);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-bottom: var(--space-lg);
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            min-width: 600px;
        }

        .table th {
            background-color: var(--gray-50);
            color: var(--gray-600);
            text-align: left;
            padding: 0.75rem;
            font-weight: 600;
            border-bottom: 1px solid var(--gray-200);
        }

        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table tr:hover {
            background-color: var(--gray-50);
        }

        .actions {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .badge-primary {
            background-color: var(--primary-100);
            color: var(--primary-700);
        }

        .badge-super-admin {
            background-color: rgba(139, 92, 246, 0.1);
            color: #6d28d9;
        }

        .badge-warning {
            background-color: var(--secondary-100);
            color: var(--secondary-700);
        }

        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 2rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state p {
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: var(--space-xs);
            padding: var(--space-md) 0;
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            border-radius: var(--radius-md);
            background-color: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.8125rem;
            padding: 0 0.5rem;
        }

        .page-link:hover {
            background-color: var(--gray-100);
            border-color: var(--gray-300);
        }

        .page-link.active {
            background-color: var(--primary-600);
            color: var(--white);
            border-color: var(--primary-600);
        }

        /* Search Form */
        .search-form {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-lg);
            width: 100%;
        }

        .search-input {
            flex: 1;
            padding: 0.625rem 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: var(--transition);
            min-width: 0;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .search-button {
            padding: 0.625rem 1rem;
            background-color: var(--primary-600);
            color: var(--white);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .search-button:hover {
            background-color: var(--primary-700);
        }

        .clear-button {
            padding: 0.625rem 1rem;
            background-color: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
        }

        .clear-button:hover {
            background-color: var(--gray-100);
            border-color: var(--gray-400);
        }

        /* Alert Messages */
        .alert {
            padding: 0.875rem 1rem;
            margin-bottom: var(--space-lg);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.875rem;
        }

        .alert-success {
            background-color: var(--primary-50);
            color: var(--primary-800);
            border-left: 4px solid var(--primary-500);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
            border-left: 4px solid var(--danger);
        }

        /* Highlight for search results */
        .highlight {
            background-color: var(--secondary-200);
            color: var(--secondary-800);
            padding: 0.125rem 0.25rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
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

        /* Toolbar styles */
        .toolbar {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
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

        .btn-warning {
            background-color: var(--warning);
            color: var(--white);
        }

        .btn-warning:hover {
            background-color: #ca8a04;
        }

        .btn-success {
            background-color: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background-color: #16a34a;
        }

        .btn-info {
            background-color: var(--info);
            color: var(--white);
        }

        .btn-info:hover {
            background-color: #2563eb;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: var(--space-sm);
        }

        .separator {
            margin: 0 var(--space-xs);
            color: var(--gray-400);
        }

        /* Responsive Styles */
        @media (min-width: 576px) {
            .header {
                padding: 0 var(--space-lg);
            }
            
            .logo-text {
                display: block;
            }
            
            .user-details {
                display: flex;
                flex-direction: column;
                margin-right: var(--space-sm);
            }
            
            .main-content {
                padding: var(--space-lg);
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .toolbar {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .search-form {
                width: auto;
            }
        }

        @media (min-width: 768px) {
            .sidebar {
                left: 0;
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .sidebar-overlay {
                display: none;
            }
            
            .table {
                font-size: 0.8125rem;
                min-width: 0;
            }
            
            .badge {
                font-size: 0.75rem;
            }
        }

        @media (min-width: 992px) {
            .sidebar {
                width: var(--sidebar-width);
            }
            
            .main-content {
                padding: var(--space-xl);
            }
            
            .table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="header-content">
            <button class="menu-toggle" id="headerMenuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>

            <div class="logo">
                <span class="logo-text">SMJK PHOR TAY</span>
            </div>

            <div class="header-actions">
                <div class="user-dropdown">
                    <button class="user-button" id="userDropdownButton" aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                        <div class="user-avatar online">
                            <span><?= substr($_SESSION['admin_name'], 0, 1) ?></span>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                            <span class="user-role"><?= $currentUserIsSuperAdmin ? 'Super Administrator' : 'Administrator' ?></span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </button>

                    <div class="dropdown-menu" id="userDropdownMenu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span><?= substr($_SESSION['admin_name'], 0, 1) ?></span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
                                <div class="dropdown-email"><?= htmlspecialchars($_SESSION['admin_email']) ?></div>
                                <div class="dropdown-meta">
                                    <i class="fas fa-shield-alt"></i>
                                    <span><?= $currentUserIsSuperAdmin ? 'Super Administrator' : 'Administrator' ?></span>
                                    <i class="fas fa-circle" style="font-size: 0.25rem;"></i>
                                    <i class="fas fa-clock"></i>
                                    <span>Last login: <?= date("M j, Y g:i A", strtotime("-1 hour")) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="edit_admin.php?id=<?= urlencode($_SESSION['admin_id']) ?>" class="dropdown-item">
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
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
                        <?php if ($pendingOrdersCount > 0): ?>
                            <span class="nav-badge"><?= $pendingOrdersCount ?> Pending</span>
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

                    <a href="view_admin.php" class="nav-item active">
                        <i class="fas fa-user-shield"></i>
                        <span>Admins</span>
                    </a>

                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
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
                <h1 class="page-title">Admin Management</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span class="separator">/</span>
                    <span>Admin Management</span>
                </div>
            </div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Admin account was successfully removed.
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error_message'] ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="toolbar">
                <?php if ($currentUserIsSuperAdmin): ?>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Admin
                    </a>
                <?php endif; ?>
                
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Search admins..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i>
                        Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="view_admin.php?clear=1" class="clear-button">
                            <i class="fas fa-times"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <p>No admin accounts found</p>
                                    <?php if ($currentUserIsSuperAdmin): ?>
                                        <a href="register.php" class="btn btn-primary">
                                            <i class="fas fa-plus"></i>
                                            Add New Admin
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= highlight($admin['name'], $search) ?></td>
                                    <td><?= highlight($admin['email'], $search) ?></td>
                                    <td><?= highlight($admin['phone'], $search) ?></td>
                                    <td>
                                        <?php if ($admin['is_admin'] == 2): ?>
                                            <span class="badge badge-super-admin">
                                                <i class="fas fa-shield-alt"></i> Super Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-primary">
                                                <i class="fas fa-user-cog"></i> Admin
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('M j, Y', strtotime($admin['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($currentUserIsSuperAdmin): ?>
                                                <?php
                                                // Fetch student_id for this admin
                                                $stmtStudent = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
                                                $stmtStudent->execute([$admin['id']]);
                                                $studentId = $stmtStudent->fetchColumn();
                                                ?>
                                                
                                                <?php if ($admin['is_admin'] == 1): ?>
                                                    <!-- Promote to Super Admin Button -->
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="promote_super_admin_id" value="<?= $admin['id'] ?>">
                                                        <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Promote this admin to Super Admin? They will have full system access.')">
                                                            <i class="fas fa-shield-alt"></i>
                                                            <span class="action-text">Promote</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($studentId)): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="unpromote_admin_id" value="<?= $admin['id'] ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Unpromote this admin? They will become a normal user.')">
                                                            <i class="fas fa-user-minus"></i>
                                                            <span class="action-text">Demote</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="delete_admin_id" value="<?= $admin['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this admin account? This action cannot be undone.')">
                                                        <i class="fas fa-trash-alt"></i>
                                                        <span class="action-text">Delete</span>
                                                    </button>
                                                </form>
                                            <?php elseif ($_SESSION['admin_id'] == $admin['id']): ?>
                                                <span class="badge badge-primary">Current User</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($page + 2, $totalPages); $p++): ?>
                        <a href="?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link <?= $p == $page ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all the necessary elements
        const headerMenuToggle = document.getElementById('headerMenuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');

        // Function to toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        // Function to toggle user dropdown
        function toggleUserDropdown() {
            const isExpanded = userDropdownButton.getAttribute('aria-expanded') === 'true';
            userDropdownButton.setAttribute('aria-expanded', !isExpanded);
            userDropdownMenu.classList.toggle('active', !isExpanded);
        }

        // Add event listeners to toggle buttons
        headerMenuToggle.addEventListener('click', toggleSidebar);
        userDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserDropdown();
        });

        // Close sidebar when clicking on overlay
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('.user-button')) {
                userDropdownButton.setAttribute('aria-expanded', 'false');
                userDropdownMenu.classList.remove('active');
            }
        });

        // Close sidebar when clicking on a nav link (for mobile)
        const navLinks = document.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });

        // Check for new orders periodically
        function checkNewOrders() {
            fetch('check_pending_orders.php')
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
                            navBadge.textContent = data.count + ' Pending';
                        } else if (navBadge) {
                            navBadge.remove();
                        }
                    }
                })
                .catch(() => {});
        }                

        // Check every 30 seconds
        setInterval(checkNewOrders, 30000);
        
        // Handle window resize
        function handleResize() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
        
        window.addEventListener('resize', handleResize);
    });
    </script>
</body>
</html>