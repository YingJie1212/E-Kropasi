<?php
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

$admin_id = $_SESSION['admin_id']; 

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

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/login.php");
    exit;
}

$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');

$category = new Category();
$search = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $categories = $category->searchByName($search);
} else {
    $categories = $category->getAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management | Admin Panel</title>
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
            font-size: 1.25rem;
            color: var(--gray-600);
            cursor: pointer;
            padding: 0.5rem;
            margin-right: 0.5rem;
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
            background: none;
            border: none;
        }

        .user-button:hover {
            background-color: var(--gray-100);
        }

        .user-button[aria-expanded="true"] {
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
            width: 0.75rem;
            height: 0.75rem;
            border-radius: var(--radius-full);
            background-color: var(--success);
            border: 2px solid var(--white);
        }

        .user-details {
            display: none;
            flex-direction: column;
            margin-right: var(--space-xs);
        }

        .user-name {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--gray-800);
        }

        .user-role {
            font-size: 0.75rem;
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
            gap: 0.5rem;
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
            padding: var(--space-xs) var(--space-sm);
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
            padding: var(--space-xs) var(--space-sm);
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
            left: -100%;
            width: 16rem;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 0 var(--space-lg) var(--space-md);
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
            margin-bottom: var(--space-md);
        }

        .nav-group-title {
            padding: 0 var(--space-lg) var(--space-xs);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: var(--space-xs) var(--space-lg);
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
            font-size: 1rem;
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
            transition: var(--transition-slow);
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
            flex-wrap: wrap;
        }

        /* Alerts */
        .alert {
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            line-height: 1.5;
        }

        .alert-success {
            background-color: var(--primary-50);
            color: var(--primary-800);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-icon {
            margin-right: 0.5rem;
            font-size: 1rem;
            margin-top: 0.125rem;
        }

        /* Card */
        .card {
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background-color: var(--white);
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .card-body {
            padding: 1rem;
        }

        .card-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--gray-200);
            background-color: var(--gray-50);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 500;
            line-height: 1.5;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-primary {
            background-color: var(--primary-600);
            color: var(--white);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background-color: var(--primary-700);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            background-color: transparent;
            color: var(--primary-600);
            border-color: var(--primary-300);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-50);
            color: var(--primary-700);
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }

        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
        }

        .btn-outline-danger {
            background-color: transparent;
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-outline-danger:hover {
            background-color: #fef2f2;
        }

        .btn-icon {
            margin-right: 0.375rem;
            font-size: 0.8125rem;
        }

        /* Search Form */
        .search-form {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.25rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            transition: var(--transition);
            background-color: var(--white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            color: var(--gray-500);
            pointer-events: none;
            font-size: 0.875rem;
        }

        .clear-btn {
            position: absolute;
            right: 0.75rem;
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            padding: 0.125rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .clear-btn:hover {
            color: var(--danger);
            background-color: var(--gray-100);
        }

        /* Category Grid */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .category-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .category-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .category-header {
            padding: 1.25rem 1.25rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .category-id {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .category-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .category-date {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .category-body {
            padding: 1rem 1.25rem;
            flex-grow: 1;
        }

        .category-actions {
            padding: 0.75rem 1.25rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        /* Empty State */
        .empty-state {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--gray-600);
        }

        .empty-state-icon {
            font-size: 2.5rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
            opacity: 0.75;
        }

        .empty-state-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .empty-state-description {
            font-size: 0.8125rem;
            margin-bottom: 1.5rem;
            max-width: 28rem;
            margin-left: auto;
            margin-right: auto;
        }

        /* Highlight */
        .highlight {
            background-color: var(--secondary-200);
            color: var(--secondary-900);
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            padding: 1rem;
        }

        .modal.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-dialog {
            width: 100%;
            max-width: 24rem;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transform: translateY(-1rem);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--danger);
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 0.75rem 1rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.125rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            padding: 0.125rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--gray-700);
            background-color: var(--gray-100);
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 0.875rem;
            height: 0.875rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--white);
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.375rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Overlay for mobile sidebar */
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

        /* Breadcrumb */
        .breadcrumb {
            font-size: 0.8125rem;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .breadcrumb-separator {
            margin: 0 0.375rem;
            color: var(--gray-400);
        }

        /* Flex utility classes */
        .d-flex {
            display: flex;
        }

        .gap-4 {
            gap: 1rem;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .flex-wrap {
            flex-wrap: wrap;
        }

        .flex-column {
            flex-direction: column;
        }

        /* Responsive adjustments */
        @media (min-width: 576px) {
            .main-content {
                padding: var(--space-lg);
            }

            .card-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .user-details {
                display: flex;
            }

            .user-button {
                gap: var(--space-sm);
                padding: var(--space-xs) var(--space-sm);
            }
        }

        @media (min-width: 768px) {
            .sidebar {
                left: 0;
                width: var(--sidebar-width);
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

            .card-header {
                padding: 1rem 1.5rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }

        @media (min-width: 992px) {
            .header {
                padding: 0 var(--space-lg);
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .page-title {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header class="header">
        <div class="header-content">
            <button class="menu-toggle" aria-label="Toggle menu" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <div class="logo">
                <span class="logo-text">SMJK PHOR TAY</span>
            </div>

            <div class="header-actions">
                <div class="user-dropdown">
                    <button class="user-button" aria-expanded="false" aria-haspopup="true" aria-label="User menu" id="userDropdownButton">
                        <div class="user-avatar online">
                            <span>A</span>
                        </div>
                        <div class="user-details">
                            <span class="user-name">Admin</span>
                            <span class="user-role">Administrator</span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem;"></i>
                    </button>

                    <div class="dropdown-menu" id="userDropdownMenu" aria-hidden="true">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <span>A</span>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-name">Admin</div>
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Layout -->
    <div class="main-layout">
        <!-- Sidebar Navigation - Hidden by default on mobile -->
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
                        <span class="nav-badge">2 Low</span>
                    </a>

                    <a href="view_categories.php" class="nav-item active">
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
        <main class="main-content" id="mainContent">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">Category Management</h1>
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a>
                        <span class="breadcrumb-separator">/</span>
                        <span>Categories</span>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle alert-icon"></i>
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle alert-icon"></i>
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Categories</h2>
                        <div class="d-flex gap-4 flex-wrap">
                            <a href="add_category.php" class="btn btn-primary">
                                <i class="fas fa-plus btn-icon"></i>
                                Add Category
                            </a>
                            <form method="GET" class="search-form">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" class="search-input" placeholder="Search categories..." value="<?= htmlspecialchars($search) ?>">
                                <?php if ($search): ?>
                                    <button type="button" class="clear-btn" id="clearSearch">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <h3 class="empty-state-title">No Categories Found</h3>
                                <p class="empty-state-description">There are currently no categories in the system. Start by adding a new category.</p>
                                <a href="add_category.php" class="btn btn-primary">
                                    <i class="fas fa-plus btn-icon"></i>
                                    Add New Category
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="category-grid">
                                <?php foreach ($categories as $i => $cat): ?>
                                    <div class="category-card">
                                        <div class="category-header">
                                            <div class="category-id">ID: <?= $i + 1 ?></div>
                                            <h3 class="category-name"><?= highlight($cat['name'], $search) ?></h3>
                                            <div class="category-date">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?= highlight($cat['created_at'], $search) ?>
                                            </div>
                                        </div>
                                        <div class="category-body">
                                            <!-- Additional category information can be added here -->
                                        </div>
                                        <div class="category-actions">
                                            <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit btn-icon"></i>
                                                Edit
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>">
                                                <i class="fas fa-trash-alt btn-icon"></i>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        <?php endif ?>
                    </div>
                    <?php if (!empty($categories)): ?>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-sm text-gray-600">
                                    Showing <span class="font-medium"><?= count($categories) ?></span> categories
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="modal-close" data-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<strong id="categoryNameToDelete"></strong>"? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <span class="spinner" id="deleteSpinner"></span>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio element for order notifications -->
    <audio id="orderSound" src="../sound/notification.mp3" preload="auto"></audio>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle sidebar on mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('menuToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        // Function to toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }
        
        // Toggle sidebar when menu button is clicked
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
        
        // Close sidebar when clicking outside or on overlay
        sidebarOverlay.addEventListener('click', function() {
            toggleSidebar();
        });
        
        // Close sidebar when clicking on a nav item (for mobile)
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });
        
        // User dropdown functionality
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
        function toggleUserDropdown() {
            const isExpanded = userDropdownButton.getAttribute('aria-expanded') === 'true';
            userDropdownButton.setAttribute('aria-expanded', !isExpanded);
            userDropdownMenu.classList.toggle('active');
            
            if (!isExpanded) {
                // Close when clicking outside
                document.addEventListener('click', closeUserDropdownOutside);
            } else {
                document.removeEventListener('click', closeUserDropdownOutside);
            }
        }
        
        function closeUserDropdownOutside(event) {
            if (!userDropdownButton.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                userDropdownButton.setAttribute('aria-expanded', 'false');
                userDropdownMenu.classList.remove('active');
                document.removeEventListener('click', closeUserDropdownOutside);
            }
        }
        
        userDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleUserDropdown();
        });
        
        // Close dropdown when clicking on a dropdown item
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                userDropdownButton.setAttribute('aria-expanded', 'false');
                userDropdownMenu.classList.remove('active');
                document.removeEventListener('click', closeUserDropdownOutside);
            });
        });
        
        // Order notification system
        let lastPendingCount = <?= $pendingOrdersCount ?>;
        const audio = document.getElementById('orderSound');

        function unlockAudio() {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(() => {});
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        }
        window.addEventListener('click', unlockAudio);
        window.addEventListener('touchstart', unlockAudio);

        function checkNewOrders() {
            fetch('check_pending_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (typeof data.count !== 'undefined' && data.count > lastPendingCount) {
                        audio.play().catch(() => {});
                        lastPendingCount = data.count;
                    }
                })
                .catch(() => {});
        }

        setInterval(checkNewOrders, 10000); // Check every 10 seconds

        // Clear search functionality
        const clearSearchBtn = document.getElementById('clearSearch');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                const searchInput = document.querySelector('.search-input');
                searchInput.value = '';
                searchInput.closest('form').submit();
            });
        }

        // Delete category modal functionality
        const deleteModal = document.getElementById('deleteModal');
        const deleteButtons = document.querySelectorAll('.btn-delete');
        const categoryNameToDelete = document.getElementById('categoryNameToDelete');
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const deleteSpinner = document.getElementById('deleteSpinner');
        let currentCategoryId = null;

        // Show modal when delete button is clicked
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentCategoryId = this.getAttribute('data-id');
                const categoryName = this.getAttribute('data-name');
                categoryNameToDelete.textContent = categoryName;
                
                // Show modal with animation
                deleteModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        });

        // Close modal when clicking X or cancel button
        document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                deleteModal.classList.remove('show');
                document.body.style.overflow = '';
            });
        });

        // Close modal when clicking outside
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                deleteModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && deleteModal.classList.contains('show')) {
                deleteModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Handle delete confirmation
        confirmDeleteBtn.addEventListener('click', function() {
            if (!currentCategoryId) return;
            
            // Show loading spinner
            deleteSpinner.style.display = 'inline-block';
            confirmDeleteBtn.disabled = true;
            
            fetch('delete_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + encodeURIComponent(currentCategoryId) + '&ajax=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the card from the grid
                    const card = document.querySelector(`.btn-delete[data-id="${currentCategoryId}"]`).closest('.category-card');
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle alert-icon"></i>
                        Category deleted successfully!
                    `;
                    document.querySelector('.page-header').insertAdjacentElement('afterend', alertDiv);
                    
                    // Remove alert after 5 seconds
                    setTimeout(() => {
                        alertDiv.style.transition = 'opacity 0.5s ease';
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 500);
                    }, 5000);
                } else {
                    alert(data.error || 'Failed to delete category. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the category.');
            })
            .finally(() => {
                // Hide modal and reset state
                deleteModal.classList.remove('show');
                document.body.style.overflow = '';
                deleteSpinner.style.display = 'none';
                confirmDeleteBtn.disabled = false;
                currentCategoryId = null;
            });
        });
    });
    </script>
</body>
</html>