<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "../classes/DB.php";
require_once "../classes/Admin.php";
require_once "../classes/OrderManager.php";
session_start();

// Initialize language settings
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en'; // Default to English
}

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ms', 'zh'])) {
    $_SESSION['language'] = $_GET['lang'];
    // Redirect to same page without language parameter to avoid issues on refresh
    $url = str_replace(array('?lang=en', '?lang=ms', '?lang=zh', '&lang=en', '&lang=ms', '&lang=zh'), '', $_SERVER['REQUEST_URI']);
    if (strpos($url, '?') === false) {
        $url .= '?';
    } else {
        $url .= '&';
    }
    header('Location: ' . $url);
    exit;
}

// Language translations
$translations = [
    'en' => [
        'dashboard' => 'Dashboard',
        'products' => 'Products',
        'categories' => 'Categories',
        'counter' => 'Counter',
        'students' => 'Students',
        'admins' => 'Admins',
        'orders' => 'Orders',
        'packages' => 'Packages',
        'monthly_report' => 'Monthly Report',
        'management' => 'Management',
        'administration' => 'Administration',
        'account_settings' => 'Account Settings',
        'school_settings' => 'School Settings',
        'logout' => 'Logout',
        'student_management' => 'Student Management',
        'manage_student_accounts' => 'Manage student accounts and permissions',
        'search_students' => 'Search students by name, ID, email, class...',
        'search' => 'Search',
        'register_new_student' => 'Register New Student',
        'name' => 'Name',
        'chinese_name' => 'Chinese Name',
        'gender' => 'Gender',
        'student_id' => 'Student ID',
        'email' => 'Email',
        'class' => 'Class',
        'created_at' => 'Created At',
        'status' => 'Status',
        'actions' => 'Actions',
        'view_orders' => 'View Orders',
        'edit_student' => 'Edit Student',
        'ban_student' => 'Ban Student',
        'unban_student' => 'Unban Student',
        'delete_student' => 'Delete Student',
        'promote_to_admin' => 'Promote to Admin',
        'promote_to_super_admin' => 'Promote to Super Admin',
        'delete_selected' => 'Delete Selected',
        'select_multiple' => 'Select Multiple',
        'selected' => 'selected',
        'cancel' => 'Cancel',
        'confirm_action' => 'Confirm Action',
        'are_you_sure_ban' => 'Are you sure you want to ban this student?',
        'are_you_sure_unban' => 'Are you sure you want to unban this student?',
        'are_you_sure_delete' => 'Are you sure you want to delete this student? This will also delete all their orders and cannot be undone.',
        'are_you_sure_promote' => 'Are you sure you want to promote this student to {role}?',
        'are_you_sure_mass_delete' => 'Are you sure you want to delete {count} selected user(s)? This will also delete all their orders and cannot be undone.',
        'confirm' => 'Confirm',
        'active' => 'Active',
        'banned' => 'Banned',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
        'see_more' => 'See More',
        'see_less' => 'See Less',
        'back_to_dashboard' => 'Back to Dashboard',
        'user_deleted_success' => 'User deleted successfully',
        'users_deleted_success' => 'Successfully deleted {count} user(s)',
        'user_banned_success' => 'User banned successfully',
        'user_unbanned_success' => 'User unbanned successfully',
        'user_promoted_success' => 'User promoted to {role} successfully',
        'failed_action' => 'Failed to complete action',
        'error_occurred' => 'An error occurred',
        'please_select_user' => 'Please select at least one user to delete',
        'no' => 'No',
        'yes' => 'Yes',
        'prev' => 'Prev',
        'next' => 'Next',
    ],
    'ms' => [
        'dashboard' => 'Papan Pemuka',
        'products' => 'Produk',
        'categories' => 'Kategori',
        'counter' => 'Kaunter',
        'students' => 'Pelajar',
        'admins' => 'Pentadbir',
        'orders' => 'Pesanan',
        'packages' => 'Pakej',
        'monthly_report' => 'Laporan Bulanan',
        'management' => 'Pengurusan',
        'administration' => 'Pentadbiran',
        'account_settings' => 'Tetapan Akaun',
        'school_settings' => 'Tetapan Sekolah',
        'logout' => 'Log Keluar',
        'student_management' => 'Pengurusan Pelajar',
        'manage_student_accounts' => 'Urus akaun pelajar dan kebenaran',
        'search_students' => 'Cari pelajar mengikut nama, ID, e-mel, kelas...',
        'search' => 'Cari',
        'register_new_student' => 'Daftar Pelajar Baru',
        'name' => 'Nama',
        'chinese_name' => 'Nama Cina',
        'gender' => 'Jantina',
        'student_id' => 'ID Pelajar',
        'email' => 'E-mel',
        'class' => 'Kelas',
        'created_at' => 'Dicipta Pada',
        'status' => 'Status',
        'actions' => 'Tindakan',
        'view_orders' => 'Lihat Pesanan',
        'edit_student' => 'Edit Pelajar',
        'ban_student' => 'Sekat Pelajar',
        'unban_student' => 'Nyahsekat Pelajar',
        'delete_student' => 'Padam Pelajar',
        'promote_to_admin' => 'Naikkan ke Pentadbir',
        'promote_to_super_admin' => 'Naikkan ke Pentadbir Super',
        'delete_selected' => 'Padam Yang Dipilih',
        'select_multiple' => 'Pilih Berbilang',
        'selected' => 'dipilih',
        'cancel' => 'Batal',
        'confirm_action' => 'Sahkan Tindakan',
        'are_you_sure_ban' => 'Adakah anda pasti mahu menyekat pelajar ini?',
        'are_you_sure_unban' => 'Adakah anda pasti mahu menyahsekat pelajar ini?',
        'are_you_sure_delete' => 'Adakah anda pasti mahu memadam pelajar ini? Tindakan ini juga akan memadam semua pesanan mereka dan tidak boleh dibuat asal.',
        'are_you_sure_promote' => 'Adakah anda pasti mahu menaikkan pelajar ini kepada {role}?',
        'are_you_sure_mass_delete' => 'Adakah anda pasti mahu memadam {count} pengguna yang dipilih? Tindakan ini juga akan memadam semua pesanan mereka dan tidak boleh dibuat asal.',
        'confirm' => 'Sahkan',
        'active' => 'Aktif',
        'banned' => 'Disekat',
        'admin' => 'Pentadbir',
        'super_admin' => 'Pentadbir Super',
        'see_more' => 'Lihat Lagi',
        'see_less' => 'Lihat Kurang',
        'back_to_dashboard' => 'Kembali ke Papan Pemuka',
        'user_deleted_success' => 'Pengguna berjaya dipadam',
        'users_deleted_success' => 'Berjaya memadam {count} pengguna',
        'user_banned_success' => 'Pengguna berjaya disekat',
        'user_unbanned_success' => 'Pengguna berjaya dinyahsekat',
        'user_promoted_success' => 'Pengguna berjaya dinaikkan kepada {role}',
        'failed_action' => 'Gagal menyelesaikan tindakan',
        'error_occurred' => 'Ralat telah berlaku',
        'please_select_user' => 'Sila pilih sekurang-kurangnya seorang pengguna untuk dipadam',
        'no' => 'Tidak',
        'yes' => 'Ya',
        'prev' => 'Sebelum',
        'next' => 'Seterus',
    ],
    'zh' => [
        'dashboard' => '仪表板',
        'products' => '产品',
        'categories' => '类别',
        'counter' => '柜台',
        'students' => '学生',
        'admins' => '管理员',
        'orders' => '订单',
        'packages' => '套餐',
        'monthly_report' => '月度报告',
        'management' => '管理',
        'administration' => '行政',
        'account_settings' => '账户设置',
        'school_settings' => '学校设置',
        'logout' => '登出',
        'student_management' => '学生管理',
        'manage_student_accounts' => '管理学生账户和权限',
        'search_students' => '按姓名、ID、电子邮件、班级搜索学生...',
        'search' => '搜索',
        'register_new_student' => '注册新学生',
        'name' => '姓名',
        'chinese_name' => '中文姓名',
        'gender' => '性别',
        'student_id' => '学生ID',
        'email' => '电子邮件',
        'class' => '班级',
        'created_at' => '创建于',
        'status' => '状态',
        'actions' => '操作',
        'view_orders' => '查看订单',
        'edit_student' => '编辑学生',
        'ban_student' => '禁止学生',
        'unban_student' => '解除禁止',
        'delete_student' => '删除学生',
        'promote_to_admin' => '提升为管理员',
        'promote_to_super_admin' => '提升为超级管理员',
        'delete_selected' => '删除所选',
        'select_multiple' => '选择多个',
        'selected' => '已选择',
        'cancel' => '取消',
        'confirm_action' => '确认操作',
        'are_you_sure_ban' => '您确定要禁止此学生吗？',
        'are_you_sure_unban' => '您确定要解除禁止此学生吗？',
        'are_you_sure_delete' => '您确定要删除此学生吗？这将同时删除他们的所有订单且无法撤销。',
        'are_you_sure_promote' => '您确定要将此学生提升为{role}吗？',
        'are_you_sure_mass_delete' => '您确定要删除{count}个选定的用户吗？这将同时删除他们的所有订单且无法撤销。',
        'confirm' => '确认',
        'active' => '活跃',
        'banned' => '已禁止',
        'admin' => '管理员',
        'super_admin' => '超级管理员',
        'see_more' => '查看更多',
        'see_less' => '查看更少',
        'back_to_dashboard' => '返回仪表板',
        'user_deleted_success' => '用户删除成功',
        'users_deleted_success' => '成功删除{count}个用户',
        'user_banned_success' => '用户禁止成功',
        'user_unbanned_success' => '用户解除禁止成功',
        'user_promoted_success' => '用户成功提升为{role}',
        'failed_action' => '操作失败',
        'error_occurred' => '发生错误',
        'please_select_user' => '请选择至少一个用户进行删除',
        'no' => '否',
        'yes' => '是',
        'prev' => '上一页',
        'next' => '下一页',
    ]
];

// Helper function to get translation
function t($key, $params = []) {
    global $translations;
    $lang = $_SESSION['language'];
    
    if (isset($translations[$lang][$key])) {
        $text = $translations[$lang][$key];
        
        // Replace parameters if provided
        foreach ($params as $param => $value) {
            $text = str_replace('{' . $param . '}', $value, $text);
        }
        
        return $text;
    }
    
    // Fallback to English if translation not found
    if (isset($translations['en'][$key])) {
        $text = $translations['en'][$key];
        
        // Replace parameters if provided
        foreach ($params as $param => $value) {
            $text = str_replace('{' . $param . '}', $value, $text);
        }
        
        return $text;
    }
    
    return $key; // Return the key if no translation found
}

$db = new DB();

// Initialize admin variables to avoid undefined warnings
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$is_admin = $_SESSION['is_admin'] ?? 0;

// Set role based on is_admin value
if ($is_admin == 2) {
    $admin_role = t('super_admin');
} elseif ($is_admin == 1) {
    $admin_role = t('admin');
} else {
    $admin_role = t('user');
}

// --- BEGIN: Restrict actions to is_admin = 2 only ---
$currentUserIsSuperAdmin = (isset($_SESSION['is_admin']) && intval($_SESSION['is_admin']) === 2);

// Handle AJAX requests for pagination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $admin = new Admin($db);
        $allUsers = $admin->getAllUsers();
        
        // Search logic
        $search = isset($_POST['search']) ? $_POST['search'] : '';
        if ($search) {
            $searchNorm = preg_replace('/\s+/', '', mb_strtolower($search));
            $allUsers = array_filter($allUsers, function($user) use ($searchNorm) {
                $row = implode(' ', [
                    $user['name'],
                    $user['student_id'],
                    $user['email'],
                    $user['class_name'],
                    $user['parent_name'],
                    $user['created_at'],
                    $user['status']
                ]);
                $rowNorm = preg_replace('/\s+/', '', mb_strtolower($row));
                return strpos($rowNorm, $searchNorm) !== false;
            });
            $allUsers = array_values($allUsers); // Reindex array
        }
        
        // Pagination logic
        $perPage = 10;
        $totalUsers = count($allUsers);
        $totalPages = ceil($totalUsers / $perPage);
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $start = ($page - 1) * $perPage;  // Corrected calculation
        $users = array_slice($allUsers, $start, $perPage);
        
        // Prepare response
        $response = [
            'success' => true,
            'users' => $users,
            'pagination' => [
                'total_pages' => $totalPages,
                'current_page' => $page,
                'total_users' => $totalUsers
            ]
        ];
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle POST requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mass_delete']) && !empty($_POST['selected_user_ids'])) {
    // Only allow super admin
    if (!$currentUserIsSuperAdmin) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit;
    }
    $conn = $db->getConnection();
    $deletedIds = [];
    try {
        foreach ($_POST['selected_user_ids'] as $deleteId) {
            $deleteId = intval($deleteId);

            // Get all order IDs for this user
            $orderIds = $conn->prepare("SELECT id FROM orders WHERE user_id = ?");
            $orderIds->execute([$deleteId]);
            $orderIdsArr = $orderIds->fetchAll(PDO::FETCH_COLUMN);

            // Delete order_items for these orders
            if (!empty($orderIdsArr)) {
                $in = implode(',', array_fill(0, count($orderIdsArr), '?'));
                $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id IN ($in)");
                $stmt->execute($orderIdsArr);
            }

            // Delete related cart records first
            $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$deleteId]);
            // Delete related orders
            $conn->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$deleteId]);
            // Now delete the user
            $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$deleteId]);
            $deletedIds[] = $deleteId;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'deleted_ids' => $deletedIds]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false];
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    // Only allow super admin for these actions
    if (!$currentUserIsSuperAdmin) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Permission denied.']);
        exit;
    }
    
    if ($userId > 0) {
        $conn = $db->getConnection();
        
        switch ($_POST['action']) {
            case 'ban':
                $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $response = ['success' => true, 'new_status' => 'banned'];
                }
                break;
                
            case 'unban':
                $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $response = ['success' => true, 'new_status' => 'active'];
                }
                break;
                
            case 'delete':
                // Get all order IDs for this user
                $orderIds = $conn->prepare("SELECT id FROM orders WHERE user_id = ?");
                $orderIds->execute([$userId]);
                $orderIdsArr = $orderIds->fetchAll(PDO::FETCH_COLUMN);

                // Delete order_items for these orders
                if (!empty($orderIdsArr)) {
                    $in = implode(',', array_fill(0, count($orderIdsArr), '?'));
                    $conn->prepare("DELETE FROM order_items WHERE order_id IN ($in)")->execute($orderIdsArr);
                }

                // Delete related cart records first
                $conn->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
                // Delete related orders
                $conn->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$userId]);
                // Now delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    $response = ['success' => true, 'deleted_id' => $userId];
                }
                break;
                
            case 'promote':
                // First check current admin level
                $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $currentLevel = $stmt->fetchColumn();
                
                $newLevel = ($currentLevel == 0) ? 1 : 2; // Promote to next level
                
                $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                if ($stmt->execute([$newLevel, $userId])) {
                    $response = ['success' => true, 'promoted_id' => $userId, 'new_level' => $newLevel];
                }
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Initial page load - get first page of users
$admin = new Admin($db);
$allUsers = $admin->getAllUsers();
$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');

// Pagination logic
$perPage = 10;
$totalUsers = count($allUsers);
$totalPages = ceil($totalUsers / $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $perPage;  // Corrected calculation
$users = array_slice($allUsers, $start, $perPage);

// Search logic
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $searchNorm = preg_replace('/\s+/', '', mb_strtolower($search));
    $allUsers = array_filter($allUsers, function($user) use ($searchNorm) {
        $row = implode(' ', [
            $user['name'],
            $user['student_id'],
            $user['email'],
            $user['class_name'],
            $user['created_at'],
            $user['status']
        ]);
        $rowNorm = preg_replace('/\s+/', '', mb_strtolower($row));
        return strpos($rowNorm, $searchNorm) !== false;
    });
    $allUsers = array_values($allUsers); // Reindex array
    $totalUsers = count($allUsers);
    $totalPages = ceil($totalUsers / $perPage);
    $page = 1;
    $users = array_slice($allUsers, 0, $perPage);
}

function highlight($text, $search) {
    if ($text === null) $text = '';
    if (!$search) return htmlspecialchars($text);
    $textEsc = htmlspecialchars($text);
    $searchNorm = preg_replace('/\s+/', '', mb_strtolower($search));
    if ($searchNorm === '') return $textEsc;

    $textNorm = preg_replace('/\s+/', '', mb_strtolower($text));
    $pos = mb_stripos($textNorm, $searchNorm);
    if ($pos === false) return $textEsc;

    $pattern = '/' . preg_replace('/\s+/', '', preg_quote($search, '/')) . '/iu';
    $replace = '<span class="highlight">$0</span>';
    $textForSearch = preg_replace('/\s+/', '', $textEsc);
    return preg_replace($pattern, $replace, $textForSearch);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('student_management') ?> | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

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

        /* Language Toggle */
        .language-toggle {
            display: flex;
            align-items: center;
            margin-right: var(--space-md);
        }

        .language-btn {
            background: none;
            border: none;
            padding: var(--space-sm);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--gray-600);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .language-btn:hover {
            background-color: var(--gray-100);
            color: var(--gray-800);
        }

        .language-btn.active {
            background-color: var(--primary-100);
            color: var(--primary-700);
        }

        /* Menu Toggle Button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--gray-600);
            cursor: pointer;
            padding: var(--space-sm);
            margin-right: var(--space-sm);
        }

        @media (max-width: 1024px) {
            .menu-toggle {
                display: block;
            }
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
            background: none;
            border: none;
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
            display: flex;
            flex-direction: column;
            margin-right: var(--space-sm);
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

        .dropdown-menu.show {
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
            text-decoration: none;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: var(--gray-100);
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

        /* Search Bar */
        .search-container {
            display: flex;
            margin-bottom: var(--space-lg);
            max-width: 100%;
        }

        .search-input {
            flex: 1;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md) 0 0 var(--radius-md);
            font-size: 0.9375rem;
            transition: var(--transition);
            min-width: 0;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }

        .search-btn {
            padding: var(--space-sm) var(--space-lg);
            background-color: var(--primary-600);
            color: var(--white);
            border: none;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            cursor: pointer;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .search-btn:hover {
            background-color: var(--primary-700);
        }

        .clear-search-btn {
            padding: var(--space-sm) var(--space-md);
            background-color: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-size: 0.9375rem;
            transition: var(--transition);
        }

        .clear-search-btn:hover {
            background-color: var(--gray-300);
        }

        /* Table Styles */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-lg);
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        th {
            background-color: var(--gray-50);
            color: var(--gray-700);
            text-align: left;
            padding: var(--space-md);
            font-weight: 500;
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        tr:not(:last-child) {
            border-bottom: 1px solid var(--gray-200);
        }

        tr:hover {
            background-color: var(--gray-50);
        }

        /* Status Styles */
        .status-active {
            color: var(--success);
            font-weight: 500;
        }

        .status-banned {
            color: var(--danger);
            font-weight: 500;
        }

        .status-admin {
            color: #7c3aed;
            font-weight: 500;
        }

        .status-super-admin {
            color: #1e40af;
            font-weight: 500;
        }

        /* Highlight Styles */
        .highlight {
            background-color: rgba(255, 255, 0, 0.3);
            padding: 0.125rem 0.25rem;
            border-radius: 0.125rem;
            font-weight: 500;
        }

        /* Checkbox Styles */
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-checkbox {
            width: 1rem;
            height: 1rem;
            cursor: pointer;
            accent-color: var(--primary-600);
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

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-indigo {
            background-color: #4f46e5;
            color: var(--white);
        }

        .btn-indigo:hover {
            background-color: #4338ca;
        }

        .btn-blue {
            background-color: #1e40af;
            color: var(--white);
        }

        .btn-blue:hover {
            background-color: #1e3a8a;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            background-color: var(--primary-600);
            color: var(--white);
            padding: var(--space-sm) var(--space-md);
            font-size: 0.875rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .dropdown-toggle:hover {
            background-color: var(--primary-700);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: var(--white);
            min-width: 12rem;
            box-shadow: var(--shadow-md);
            z-index: 50;
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-item {
            color: var(--gray-700);
            padding: var(--space-sm) var(--space-md);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .dropdown-item i {
            width: 1rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .dropdown-item:hover {
            background-color: var(--gray-50);
        }

        .dropdown-divider {
            height: 1px;
            background-color: var(--gray-200);
            margin: 0;
            border: none;
        }

        /* Pagination */
        .pagination-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: var(--space-md) 0;
            flex-wrap: wrap;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-sm) var(--space-md);
            margin: var(--space-xs);
            background-color: var(--white);
            color: var(--primary-600);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
            min-width: 2.5rem;
        }

        .pagination-btn:hover {
            background-color: var(--primary-50);
            border-color: var(--primary-300);
        }

        .pagination-btn.active {
            background-color: var(--primary-600);
            color: var(--white);
            pointer-events: none;
            border-color: var(--primary-600);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition-slow);
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 24rem;
            transform: translateY(1rem);
            transition: var(--transition-slow);
            overflow: hidden;
            margin: var(--space-md);
        }

        .modal-overlay.show .modal {
            transform: translateY(0);
        }

        .modal-header {
            padding: var(--space-md) var(--space-lg);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--gray-500);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--gray-700);
        }

        .modal-body {
            padding: var(--space-lg);
        }

        .modal-message {
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--gray-600);
            margin-bottom: var(--space-lg);
        }

        .modal-footer {
            padding: var(--space-md) var(--space-lg);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: var(--space-sm);
        }

        /* Toast notification */
        .toast {
            position: fixed;
            top: var(--space-lg);
            right: var(--space-lg);
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            box-shadow: var(--shadow-md);
            z-index: 1000;
            transform: translateX(200%);
            transition: var(--transition-slow);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background-color: var(--success);
        }

        .toast.error {
            background-color: var(--danger);
        }

        .toast.warning {
            background-color: var(--warning);
        }

        .toast i {
            font-size: 1rem;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: var(--header-height);
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
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
        .mobile-user-card {
            display: none;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: var(--space-md);
            margin-bottom: var(--space-sm);
            position: relative;
        }

        .mobile-user-card.selected {
            background-color: var(--primary-50);
            border-left: 3px solid var(--primary-500);
        }

        .mobile-user-field {
            display: flex;
            margin-bottom: var(--space-sm);
        }

        .mobile-user-label {
            font-weight: 500;
            color: var(--gray-600);
            min-width: 100px;
        }

        .mobile-user-value {
            flex: 1;
            word-break: break-word;
        }

        .mobile-user-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: var(--space-md);
        }

        /* Mobile selection checkbox */
        .mobile-checkbox {
            position: absolute;
            top: var(--space-sm);
            left: var(--space-sm);
            width: 1.25rem;
            height: 1.25rem;
            accent-color: var(--primary-600);
            cursor: pointer;
        }

        /* See More Button */
        .see-more-btn {
            background: none;
            border: none;
            color: var(--primary-600);
            font-size: 0.875rem;
            cursor: pointer;
            padding: var(--space-xs) 0;
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            margin-top: var(--space-sm);
        }

        .see-more-btn i {
            transition: transform 0.2s;
        }

        .see-more-btn.expanded i {
            transform: rotate(180deg);
        }

        /* Additional fields container */
        .additional-fields {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .additional-fields.expanded {
            max-height: 500px; /* Adjust based on your content */
        }

        /* Mobile selection mode */
        .selection-mode {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--white);
            padding: var(--space-md);
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 90;
            transform: translateY(100%);
            transition: var(--transition-slow);
        }

        .selection-mode.active {
            transform: translateY(0);
        }

        .selection-count {
            font-weight: 500;
            color: var(--gray-700);
        }

        .selection-actions {
            display: flex;
            gap: var(--space-sm);
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

            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 var(--space-md);
            }

            .page-title {
                font-size: 1.5rem;
            }

            .pagination-btn {
                padding: var(--space-sm);
                min-width: 2rem;
            }

            /* Switch to mobile table view */
            table {
                display: none;
            }

            .mobile-user-card {
                display: block;
                padding-left: var(--space-xl);
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-actions {
                margin-top: var(--space-md);
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .language-toggle {
                margin-right: 0;
            }
        }

        @media (max-width: 640px) {
            .main-content {
                padding: var(--space-md);
            }

            .page-title {
                font-size: 1.25rem;
            }

            .search-container {
                flex-direction: column;
            }

            .search-input {
                border-radius: var(--radius-md) var(--radius-md) 0 0;
            }

            .search-btn {
                border-radius: 0 0 var(--radius-md) var(--radius-md);
            }

            .clear-search-btn {
                display: none;
            }

            .user-details {
                display: none;
            }

            .dropdown-menu {
                width: 16rem;
                right: -1rem;
            }

            .language-toggle {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 0 var(--space-sm);
            }

            .logo-text {
                font-size: 1.25rem;
            }

            .user-avatar {
                width: 2.25rem;
                height: 2.25rem;
                font-size: 0.875rem;
            }

            .dropdown-menu {
                width: 14rem;
            }

            .modal {
                margin: var(--space-sm);
            }

            .modal-body, .modal-footer {
                padding: var(--space-md);
            }

            .mobile-user-field {
                flex-direction: column;
            }

            .mobile-user-label {
                margin-bottom: var(--space-xs);
            }

            .mobile-checkbox {
                top: var(--space-xs);
                left: var(--space-xs);
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
                <!-- Language Toggle -->
                <div class="language-toggle">
                    <button class="language-btn <?= $_SESSION['language'] == 'en' ? 'active' : '' ?>" onclick="changeLanguage('en')" title="English">
                        EN
                    </button>
                    <button class="language-btn <?= $_SESSION['language'] == 'ms' ? 'active' : '' ?>" onclick="changeLanguage('ms')" title="Malay">
                        MS
                    </button>
                    <button class="language-btn <?= $_SESSION['language'] == 'zh' ? 'active' : '' ?>" onclick="changeLanguage('zh')" title="Chinese">
                        中文
                    </button>
                </div>

                <div class="user-dropdown">
                    <button id="userDropdownButton" class="user-button" aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                        <div class="user-avatar online">
                            <span><?= isset($admin_name) && $admin_name ? substr($admin_name, 0, 1) : 'A' ?></span>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= htmlspecialchars($admin_name ?? 'Admin') ?></span>
                            <span class="user-role"><?= htmlspecialchars($admin_role ?? 'User') ?></span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.875rem;"></i>
                    </button>

                    <div id="userDropdownMenu" class="dropdown-menu" aria-hidden="true">
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
                                </div>
                            </div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="edit_admin.php?id=<?= urlencode($admin_id) ?>" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            <span><?= t('account_settings') ?></span>
                        </a>

                        <?php if ($is_admin == 2): ?>
                            <a href="system_settings.php" class="dropdown-item">
                                <i class="fas fa-sliders-h"></i>
                                <span><?= t('school_settings') ?></span>
                            </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>

                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span><?= t('logout') ?></span>
                        </a>
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
            <div class="nav-menu">
                <div class="nav-group">
                    <div class="nav-group-title"><?= t('management') ?></div>

                    <a href="dashboard.php" class="nav-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <span><?= t('dashboard') ?></span>
                    </a>

                    <a href="view_products.php" class="nav-item">
                        <i class="fas fa-box-open"></i>
                        <span><?= t('products') ?></span>
                        <?php if (isset($lowStockCount) && $lowStockCount > 0): ?>
                            <span class="nav-badge"><?= $lowStockCount ?> Low</span>
                        <?php endif; ?>
                    </a>

                    <a href="view_categories.php" class="nav-item">
                        <i class="fas fa-tags"></i>
                        <span><?= t('categories') ?></span>
                    </a>

                    <a href="counter.php" class="nav-item">
                        <i class="fas fa-cash-register"></i>
                        <span><?= t('counter') ?></span>
                    </a>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title"><?= t('administration') ?></div>

                    <a href="view_user.php" class="nav-item active">
                        <i class="fas fa-user-graduate"></i>
                        <span><?= t('students') ?></span>
                    </a>

                    <a href="view_admin.php" class="nav-item">
                        <i class="fas fa-user-shield"></i>
                        <span><?= t('admins') ?></span>
                    </a>

                    <a href="orders.php" class="nav-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span><?= t('orders') ?></span>
                        <?php if ($pendingOrdersCount > 0): ?>
                            <span class="nav-badge"><?= $pendingOrdersCount ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="view_package.php" class="nav-item">
                        <i class="fas fa-boxes"></i>
                        <span><?= t('packages') ?></span>
                    </a>
                    
                     <a href="admin_monthly_report.php" class="nav-item ">
                        <i class="fas fa-chart-bar"></i>
                        <span><?= t('monthly_report') ?></span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="page-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="margin-bottom: var(--space-sm);">
                        <h1 class="page-title"><?= t('student_management') ?></h1>
                        <p class="page-subtitle"><?= t('manage_student_accounts') ?></p>
                    </div>
                    <a href="user_register.php" class="btn btn-primary" style="margin-left: auto;">
                        <i class="fas fa-user-plus"></i> <?= t('register_new_student') ?>
                    </a>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-container">
                <form id="userSearchForm" method="get" class="search-container" style="width: 100%;">
                    <input type="text" 
                           id="searchInput" 
                           name="search" 
                           class="search-input" 
                           placeholder="<?= t('search_students') ?>" 
                           value="<?= htmlspecialchars($search) ?>"
                           aria-label="<?= t('search_students') ?>">
                    <?php if ($search): ?>
                        <button type="button" id="clearSearchBtn" class="clear-search-btn" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> <?= t('search') ?>
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <form method="post" id="massDeleteForm">
                    <input type="hidden" name="mass_delete" value="1">
                    <div id="usersTableContainer">
                        <!-- Desktop Table -->
                        <table id="usersTable">
                            <thead>
                                <tr>
                                    <th class="checkbox-container"><input type="checkbox" id="selectAllUsers"></th>
                                    <th>#</th>
                                    <th><?= t('name') ?></th>
                                    <th><?= t('chinese_name') ?></th>
                                    <th><?= t('gender') ?></th>
                                    <th><?= t('student_id') ?></th>
                                    <th><?= t('email') ?></th>
                                    <th><?= t('class') ?></th>
                                    <th><?= t('created_at') ?></th>
                                    <th><?= t('status') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php $rowNumber = ($page - 1) * $perPage + 1; ?>
                                <?php foreach ($users as $user): ?>
                                    <tr id="user-row-<?= $user['id'] ?>">
                                        <td class="checkbox-container"><input type="checkbox" name="selected_user_ids[]" value="<?= $user['id'] ?>" class="user-checkbox custom-checkbox" <?= !$currentUserIsSuperAdmin ? 'disabled' : '' ?>></td>
                                        <td><?= $rowNumber++ ?></td>
                                        <td><?= highlight($user['name'], $search) ?></td>
                                        <td><?= highlight($user['chinese_name'], $search) ?></td>
                                        <td><?= highlight($user['gender'], $search) ?></td>
                                        <td><?= highlight($user['student_id'], $search) ?></td>
                                        <td><?= highlight($user['email'], $search) ?></td>
                                        <td><?= highlight($user['class_name'], $search) ?></td>
                                        <td><?= highlight($user['created_at'], $search) ?></td>
                                        <td class="user-status">
                                            <?php if ($user['is_admin'] == 2): ?>
                                                <span class="status-super-admin"><?= t('super_admin') ?></span>
                                            <?php elseif ($user['is_admin'] == 1): ?>
                                                <span class="status-admin"><?= t('admin') ?></span>
                                            <?php elseif ($user['status'] === 'banned'): ?>
                                                <span class="status-banned"><?= t('banned') ?></span>
                                            <?php else: ?>
                                                <span class="status-active"><?= t('active') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="dropdown-toggle btn btn-primary btn-sm" onclick="toggleDropdown(this)"><i class="fas fa-cog"></i> <span><?= t('actions') ?></span></button>
                                                <div class="dropdown-content">
                                                    <a href="view_user_orders.php?user_id=<?= $user['id'] ?>" class="dropdown-item"><i class="fas fa-history"></i> <?= t('view_orders') ?></a>
                                                    <?php if ($currentUserIsSuperAdmin): ?>
                                                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="dropdown-item"><i class="fas fa-edit"></i> <?= t('edit_student') ?></a>
                                                        <hr class="dropdown-divider">
                                                      <?php if ($user['status'] !== 'banned'): ?>
                                                            <?php if ($user['is_admin'] == 0): ?>
                                                                <button type="button" class="dropdown-item ban-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('ban', <?= $user['id'] ?>, '<?= t('are_you_sure_ban') ?>')">
                                                                    <i class="fas fa-ban"></i> <?= t('ban_student') ?>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php elseif ($user['status'] === 'banned'): ?>
                                                            <button type="button" class="dropdown-item unban-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('unban', <?= $user['id'] ?>, '<?= t('are_you_sure_unban') ?>')">
                                                                <i class="fas fa-check-circle"></i> <?= t('unban_student') ?>
                                                            </button>
                                                        <?php endif; ?>
                                                        <hr class="dropdown-divider">
                                                        <button type="button" class="dropdown-item delete-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('delete', <?= $user['id'] ?>, '<?= t('are_you_sure_delete') ?>')">
                                                            <i class="fas fa-trash-alt"></i> <?= t('delete_student') ?>
                                                        </button>
                                                        <?php if ($user['is_admin'] < 2): ?>
                                                        <hr class="dropdown-divider">
                                                        <button type="button" class="dropdown-item promote-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('promote', <?= $user['id'] ?>, '<?= t('are_you_sure_promote', ['role' => $user['is_admin'] == 0 ? t('admin') : t('super_admin')]) ?>')">
                                                            <i class="fas fa-user-shield"></i> <?= $user['is_admin'] == 0 ? t('promote_to_admin') : t('promote_to_super_admin') ?>
                                                        </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Mobile Cards -->
                        <div id="mobileUsersView">
                            <?php $rowNumber = ($page - 1) * $perPage + 1; ?>
                            <?php foreach ($users as $user): ?>
                                <div class="mobile-user-card" id="mobile-user-<?= $user['id'] ?>" data-user-id="<?= $user['id'] ?>">
                                    <input type="checkbox" name="selected_user_ids[]" value="<?= $user['id'] ?>" class="mobile-checkbox" <?= !$currentUserIsSuperAdmin ? 'disabled' : '' ?>>
                                    <div class="mobile-user-field">
                                        <div class="mobile-user-label">#</div>
                                        <div class="mobile-user-value"><?= $rowNumber++ ?></div>
                                    </div>
                                    <div class="mobile-user-field">
                                        <div class="mobile-user-label"><?= t('name') ?></div>
                                        <div class="mobile-user-value"><?= highlight($user['name'], $search) ?></div>
                                    </div>
                                    <div class="mobile-user-field">
                                        <div class="mobile-user-label"><?= t('chinese_name') ?></div>
                                        <div class="mobile-user-value"><?= highlight($user['chinese_name'], $search) ?></div>
                                    </div>
                                    
                                    <!-- See More Button -->
                                    <button class="see-more-btn" onclick="toggleSeeMore(this, event)">
                                        <span><?= t('see_more') ?></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    
                                    <!-- Additional Fields (Hidden by default) -->
                                    <div class="additional-fields">
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('gender') ?></div>
                                            <div class="mobile-user-value"><?= highlight($user['gender'], $search) ?></div>
                                        </div>
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('student_id') ?></div>
                                            <div class="mobile-user-value"><?= highlight($user['student_id'], $search) ?></div>
                                        </div>
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('email') ?></div>
                                            <div class="mobile-user-value"><?= highlight($user['email'], $search) ?></div>
                                        </div>
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('class') ?></div>
                                            <div class="mobile-user-value"><?= highlight($user['class_name'], $search) ?></div>
                                        </div>
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('created_at') ?></div>
                                            <div class="mobile-user-value"><?= highlight($user['created_at'], $search) ?></div>
                                        </div>
                                        <div class="mobile-user-field">
                                            <div class="mobile-user-label"><?= t('status') ?></div>
                                            <div class="mobile-user-value">
                                                <?php if ($user['is_admin'] == 2): ?>
                                                    <span class="status-super-admin"><?= t('super_admin') ?></span>
                                                <?php elseif ($user['is_admin'] == 1): ?>
                                                    <span class="status-admin"><?= t('admin') ?></span>
                                                <?php elseif ($user['status'] === 'banned'): ?>
                                                    <span class="status-banned"><?= t('banned') ?></span>
                                                <?php else: ?>
                                                    <span class="status-active"><?= t('active') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mobile-user-actions">
                                        <div class="dropdown">
                                            <button type="button" class="dropdown-toggle btn btn-primary btn-sm" onclick="toggleDropdown(this)"><i class="fas fa-cog"></i> <span><?= t('actions') ?></span></button>
                                            <div class="dropdown-content">
                                                <a href="view_user_orders.php?user_id=<?= $user['id'] ?>" class="dropdown-item"><i class="fas fa-history"></i> <?= t('view_orders') ?></a>
                                                <?php if ($currentUserIsSuperAdmin): ?>
                                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="dropdown-item"><i class="fas fa-edit"></i> <?= t('edit_student') ?></a>
                                                    <hr class="dropdown-divider">
                                                  <?php if ($user['status'] !== 'banned'): ?>
                                                        <?php if ($user['is_admin'] == 0): ?>
                                                            <button type="button" class="dropdown-item ban-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('ban', <?= $user['id'] ?>, '<?= t('are_you_sure_ban') ?>')">
                                                                <i class="fas fa-ban"></i> <?= t('ban_student') ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php elseif ($user['status'] === 'banned'): ?>
                                                        <button type="button" class="dropdown-item unban-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('unban', <?= $user['id'] ?>, '<?= t('are_you_sure_unban') ?>')">
                                                            <i class="fas fa-check-circle"></i> <?= t('unban_student') ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <hr class="dropdown-divider">
                                                    <button type="button" class="dropdown-item delete-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('delete', <?= $user['id'] ?>, '<?= t('are_you_sure_delete') ?>')">
                                                        <i class="fas fa-trash-alt"></i> <?= t('delete_student') ?>
                                                    </button>
                                                    <?php if ($user['is_admin'] < 2): ?>
                                                    <hr class="dropdown-divider">
                                                    <button type="button" class="dropdown-item promote-btn" data-user-id="<?= $user['id'] ?>" onclick="confirmAction('promote', <?= $user['id'] ?>, '<?= t('are_you_sure_promote', ['role' => $user['is_admin'] == 0 ? t('admin') : t('super_admin')]) ?>')">
                                                        <i class="fas fa-user-shield"></i> <?= $user['is_admin'] == 0 ? t('promote_to_admin') : t('promote_to_super_admin') ?>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ($currentUserIsSuperAdmin): ?>
                        <button type="button" class="btn btn-danger" style="margin:1rem;" onclick="confirmMassDelete()"><i class="fas fa-trash-alt"></i> <?= t('delete_selected') ?></button>
                        <button type="button" id="mobileSelectBtn" class="btn btn-outline" style="margin:1rem; display: none;" onclick="toggleMobileSelection()"><i class="fas fa-check-square"></i> <?= t('select_multiple') ?></button>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Mobile Selection Mode Bar -->
            <div class="selection-mode" id="selectionModeBar">
                <div class="selection-count" id="selectionCount">0 <?= t('selected') ?></div>
                <div class="selection-actions">
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleMobileSelection()"><?= t('cancel') ?></button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmMassDelete()"><?= t('delete') ?></button>
                </div>
            </div>
            
            <div id="paginationContainer">
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-bar">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn">
                                &laquo; <?= t('prev') ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($totalPages <= 10): ?>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="pagination-btn active"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        <?php else: ?>
                            <?php
                            // Always show first page
                            ?>
                            <a href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn">1</a>
                            
                            <?php if ($page > 3): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php
                            // Show pages around current page
                            $startPage = max(2, $page - 2);
                            $endPage = min($totalPages - 1, $page + 2);
                            
                            for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="pagination-btn active"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages - 3): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php
                            // Always show last page
                            ?>
                            <a href="?page=<?= $totalPages ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn"><?= $totalPages ?></a>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="pagination-btn">
                                <?= t('next') ?> &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <a href="dashboard.php" class="btn btn-outline" style="margin-top: var(--space-lg);"><i class="fas fa-arrow-left"></i> <?= t('back_to_dashboard') ?></a>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmationModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><?= t('confirm_action') ?></div>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-message" id="modalMessage"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()"><?= t('cancel') ?></button>
                <button class="btn btn-danger" id="confirmActionBtn"><?= t('confirm') ?></button>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="toast" class="toast"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get DOM elements
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mobileSelectBtn = document.getElementById('mobileSelectBtn');
        const massDeleteForm = document.getElementById('massDeleteForm');
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');

        // User dropdown functionality
        if (userDropdownButton && userDropdownMenu) {
            userDropdownButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdownMenu.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                userDropdownMenu.classList.remove('show');
            });
        }

        // Prevent form submission when pressing enter in search input
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('userSearchForm').submit();
            }
        });

        // Show mobile select button on mobile devices
        if (window.innerWidth <= 768 && mobileSelectBtn) {
            mobileSelectBtn.style.display = 'inline-flex';
        }

        // Toggle sidebar when menu button is clicked
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        });

        // Close sidebar when overlay is clicked
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-content')) {
                closeAllDropdowns();
            }
        });

        // Select All functionality
        const selectAll = document.getElementById('selectAllUsers');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.user-checkbox, .mobile-checkbox');
                checkboxes.forEach(cb => cb.checked = selectAll.checked);
                updateMobileSelectionUI();
            });
        }

        // Search form submission
        const searchForm = document.getElementById('userSearchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                const searchValue = searchInput.value.trim();
                const urlParams = new URLSearchParams(window.location.search);
                
                // Update URL with search parameter
                if (searchValue) {
                    urlParams.set('search', searchValue);
                } else {
                    urlParams.delete('search');
                }
                
                // Reset to page 1 when searching
                urlParams.set('page', '1');
                
                // Navigate to the new URL
                window.location.search = urlParams.toString();
            });
        }

        // Toggle between mobile and desktop views based on screen size
        function updateView() {
            const isMobile = window.innerWidth <= 768;
            const table = document.getElementById('usersTable');
            const mobileView = document.getElementById('mobileUsersView');
            
            if (isMobile) {
                if (table) table.style.display = 'none';
                if (mobileView) mobileView.style.display = 'block';
                if (mobileSelectBtn) mobileSelectBtn.style.display = 'inline-flex';
            } else {
                if (table) table.style.display = 'table';
                if (mobileView) mobileView.style.display = 'none';
                if (mobileSelectBtn) mobileSelectBtn.style.display = 'none';
                // Exit selection mode when switching to desktop
                exitMobileSelection();
            }
        }

        // Initial view update
        updateView();
        
        // Update view on window resize
        window.addEventListener('resize', updateView);
    });

    // Language change function
    function changeLanguage(lang) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('lang', lang);
        window.location.search = urlParams.toString();
    }

    // Toggle "See More" functionality - modified to prevent form submission
    function toggleSeeMore(button, event) {
        event.preventDefault();
        event.stopPropagation();
        
        const card = button.closest('.mobile-user-card');
        const additionalFields = card.querySelector('.additional-fields');
        
        button.classList.toggle('expanded');
        additionalFields.classList.toggle('expanded');
        
        // Update button text
        const span = button.querySelector('span');
        span.textContent = button.classList.contains('expanded') ? '<?= t('see_less') ?>' : '<?= t('see_more') ?>';
    }

    // Toggle mobile selection mode
    function toggleMobileSelection() {
        const selectionModeBar = document.getElementById('selectionModeBar');
        const mobileCards = document.querySelectorAll('.mobile-user-card');
        
        if (selectionModeBar.classList.contains('active')) {
            // Exit selection mode
            exitMobileSelection();
        } else {
            // Enter selection mode
            selectionModeBar.classList.add('active');
            mobileCards.forEach(card => {
                card.style.cursor = 'pointer';
                card.addEventListener('click', handleMobileCardClick);
            });
            
            // Hide other action buttons
            document.querySelectorAll('.mobile-user-actions').forEach(actions => {
                actions.style.display = 'none';
            });
            
            // Update count
            updateSelectionCount();
        }
    }
    
    // Exit mobile selection mode
    function exitMobileSelection() {
        const selectionModeBar = document.getElementById('selectionModeBar');
        const mobileCards = document.querySelectorAll('.mobile-user-card');
        
        selectionModeBar.classList.remove('active');
        mobileCards.forEach(card => {
            card.classList.remove('selected');
            card.style.cursor = '';
            card.removeEventListener('click', handleMobileCardClick);
            
            // Show checkboxes (hidden by default)
            const checkbox = card.querySelector('.mobile-checkbox');
            if (checkbox) {
                checkbox.checked = false;
            }
        });
        
        // Show action buttons again
        document.querySelectorAll('.mobile-user-actions').forEach(actions => {
            actions.style.display = 'flex';
        });
    }
    
    // Handle mobile card click in selection mode
    function handleMobileCardClick(event) {
        // Don't trigger if clicking on the checkbox directly
        if (event.target.classList.contains('mobile-checkbox')) {
            return;
        }
        
        const card = event.currentTarget;
        const checkbox = card.querySelector('.mobile-checkbox');
        
        if (checkbox) {
            checkbox.checked = !checkbox.checked;
            updateMobileSelectionUI();
        }
    }
    
    // Update mobile selection UI (highlighting and count)
    function updateMobileSelectionUI() {
        const mobileCards = document.querySelectorAll('.mobile-user-card');
        let selectedCount = 0;
        
        mobileCards.forEach(card => {
            const checkbox = card.querySelector('.mobile-checkbox');
            if (checkbox && checkbox.checked) {
                card.classList.add('selected');
                selectedCount++;
            } else {
                card.classList.remove('selected');
            }
        });
        
        // Update count display
        const selectionCount = document.getElementById('selectionCount');
        if (selectionCount) {
            selectionCount.textContent = selectedCount + ' <?= t('selected') ?>';
        }
    }
    
    // Update selection count
    function updateSelectionCount() {
        const checkboxes = document.querySelectorAll('.mobile-checkbox:checked');
        const selectionCount = document.getElementById('selectionCount');
        if (selectionCount) {
            selectionCount.textContent = checkboxes.length + ' <?= t('selected') ?>';
        }
    }

    // Clear search function
    function clearSearch() {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.delete('search');
        window.location.search = urlParams.toString();
    }

    // AJAX function to load users
    function loadPage(page) {
        const search = document.getElementById('searchInput').value;
        const loadingSpinner = document.getElementById('loadingSpinner');
        const usersTableBody = document.getElementById('usersTableBody');
        const paginationContainer = document.getElementById('paginationContainer');
        
        // Show loading spinner
        loadingSpinner.style.display = 'block';
        usersTableBody.innerHTML = '';
        paginationContainer.innerHTML = '';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('ajax_action', 'get_users');
        formData.append('page', page);
        formData.append('search', search);
        
        fetch('view_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update table body
                renderUsers(data.users, (page - 1) * 10 + 1, search);
                
                // Update pagination
                renderPagination(page, data.pagination.total_pages);
                
                // Hide loading spinner
                loadingSpinner.style.display = 'none';
            } else {
                showToast(data.error || '<?= t('failed_action') ?>', 'error');
                loadingSpinner.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
            loadingSpinner.style.display = 'none';
        });
    }

    // Render users in the table
    function renderUsers(users, startNumber, searchTerm) {
        const tbody = document.getElementById('usersTableBody');
        const mobileView = document.getElementById('mobileUsersView');
        tbody.innerHTML = '';
        mobileView.innerHTML = '';
        
        users.forEach((user, index) => {
            const rowNumber = startNumber + index;
            
            // Desktop table row
            const row = document.createElement('tr');
            row.id = `user-row-${user.id}`;
            
            // Helper function to create a cell with content
            const createCell = (content) => {
                const td = document.createElement('td');
                td.innerHTML = content;
                return td;
            };
            
            // Checkbox cell
            const checkboxCell = document.createElement('td');
            checkboxCell.className = 'checkbox-container';
            checkboxCell.innerHTML = `<input type="checkbox" name="selected_user_ids[]" value="${user.id}" class="user-checkbox custom-checkbox">`;
            row.appendChild(checkboxCell);
            
            // Number cell
            row.appendChild(createCell(rowNumber));
            
            // User data cells
            row.appendChild(createCell(highlightText(user.name, searchTerm)));
            row.appendChild(createCell(highlightText(user.chinese_name, searchTerm)));
            row.appendChild(createCell(highlightText(user.gender, searchTerm)));
            row.appendChild(createCell(highlightText(user.student_id, searchTerm)));
            row.appendChild(createCell(highlightText(user.email, searchTerm)));
            row.appendChild(createCell(highlightText(user.class_name, searchTerm)));
            row.appendChild(createCell(highlightText(user.created_at, searchTerm)));
            
            // Status cell
            const statusCell = document.createElement('td');
            statusCell.className = 'user-status';
            if (user.is_admin == 2) {
                statusCell.innerHTML = '<span class="status-super-admin"><?= t('super_admin') ?></span>';
            } else if (user.is_admin == 1) {
                statusCell.innerHTML = '<span class="status-admin"><?= t('admin') ?></span>';
            } else if (user.status === 'banned') {
                statusCell.innerHTML = '<span class="status-banned"><?= t('banned') ?></span>';
            } else {
                statusCell.innerHTML = '<span class="status-active"><?= t('active') ?></span>';
            }
            row.appendChild(statusCell);
            
            // Actions cell
            const actionsCell = document.createElement('td');
            actionsCell.innerHTML = getActionButtonsHTML(user);
            row.appendChild(actionsCell);
            
            tbody.appendChild(row);
            
            // Mobile card
            const card = document.createElement('div');
            card.className = 'mobile-user-card';
            card.id = `mobile-user-${user.id}`;
            card.dataset.userId = user.id;
            
            card.innerHTML = `
                <input type="checkbox" name="selected_user_ids[]" value="${user.id}" class="mobile-checkbox">
                <div class="mobile-user-field">
                    <div class="mobile-user-label">#</div>
                    <div class="mobile-user-value">${rowNumber}</div>
                </div>
                <div class="mobile-user-field">
                    <div class="mobile-user-label"><?= t('name') ?></div>
                    <div class="mobile-user-value">${highlightText(user.name, searchTerm)}</div>
                </div>
                <div class="mobile-user-field">
                    <div class="mobile-user-label"><?= t('chinese_name') ?></div>
                    <div class="mobile-user-value">${highlightText(user.chinese_name, searchTerm)}</div>
                </div>
                
                <!-- See More Button -->
                <button class="see-more-btn" onclick="toggleSeeMore(this, event)">
                    <span><?= t('see_more') ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <!-- Additional Fields (Hidden by default) -->
                <div class="additional-fields">
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('gender') ?></div>
                        <div class="mobile-user-value">${highlightText(user.gender, searchTerm)}</div>
                    </div>
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('student_id') ?></div>
                        <div class="mobile-user-value">${highlightText(user.student_id, searchTerm)}</div>
                    </div>
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('email') ?></div>
                        <div class="mobile-user-value">${highlightText(user.email, searchTerm)}</div>
                    </div>
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('class') ?></div>
                        <div class="mobile-user-value">${highlightText(user.class_name, searchTerm)}</div>
                    </div>
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('created_at') ?></div>
                        <div class="mobile-user-value">${highlightText(user.created_at, searchTerm)}</div>
                    </div>
                    <div class="mobile-user-field">
                        <div class="mobile-user-label"><?= t('status') ?></div>
                        <div class="mobile-user-value">
                            ${user.is_admin == 2 ? '<span class="status-super-admin"><?= t('super_admin') ?></span>' : 
                             user.is_admin == 1 ? '<span class="status-admin"><?= t('admin') ?></span>' : 
                             user.status === 'banned' ? '<span class="status-banned"><?= t('banned') ?></span>' : 
                             '<span class="status-active"><?= t('active') ?></span>'}
                        </div>
                    </div>
                </div>
                
                <div class="mobile-user-actions">
                    ${getActionButtonsHTML(user)}
                </div>
            `;
            
            mobileView.appendChild(card);
        });
        
        // Add event listeners to mobile checkboxes
        document.querySelectorAll('.mobile-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const card = this.closest('.mobile-user-card');
                if (this.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
                updateSelectionCount();
            });
        });
    }

    // Get action buttons HTML for both desktop and mobile
    function getActionButtonsHTML(user) {
        return `
            <div class="dropdown">
                <button type="button" class="dropdown-toggle btn btn-primary btn-sm" onclick="toggleDropdown(this)"><i class="fas fa-cog"></i> <span><?= t('actions') ?></span></button>
                <div class="dropdown-content">
                    <a href="view_user_orders.php?user_id=${user.id}" class="dropdown-item"><i class="fas fa-history"></i> <?= t('view_orders') ?></a>
                    <?php if ($currentUserIsSuperAdmin): ?>
                        <a href="edit_user.php?id=${user.id}" class="dropdown-item"><i class="fas fa-edit"></i> <?= t('edit_student') ?></a>
                        <hr class="dropdown-divider">
                        ${user.status !== 'banned' ? 
                            (user.is_admin == 0 ? 
                                `<button type="button" class="dropdown-item ban-btn" data-user-id="${user.id}" onclick="confirmAction('ban', ${user.id}, '<?= t('are_you_sure_ban') ?>')">
                                    <i class="fas fa-ban"></i> <?= t('ban_student') ?>
                                </button>` : '') : 
                            user.status === 'banned' ? 
                            `<button type="button" class="dropdown-item unban-btn" data-user-id="${user.id}" onclick="confirmAction('unban', ${user.id}, '<?= t('are_you_sure_unban') ?>')">
                                <i class="fas fa-check-circle"></i> <?= t('unban_student') ?>
                            </button>` : ''}
                        <hr class="dropdown-divider">
                        <button type="button" class="dropdown-item delete-btn" data-user-id="${user.id}" onclick="confirmAction('delete', ${user.id}, '<?= t('are_you_sure_delete') ?>')">
                            <i class="fas fa-trash-alt"></i> <?= t('delete_student') ?>
                        </button>
                        ${(user.is_admin < 2) ? `
                        <hr class="dropdown-divider">
                        <button type="button" class="dropdown-item promote-btn" data-user-id="${user.id}" onclick="confirmAction('promote', ${user.id}, '<?= t('are_you_sure_promote') ?>'.replace('{role}', ${user.is_admin == 0 ? '<?= t('admin') ?>' : '<?= t('super_admin') ?>'}))">
                            <i class="fas fa-user-shield"></i> ${user.is_admin == 0 ? '<?= t('promote_to_admin') ?>' : '<?= t('promote_to_super_admin') ?>'}
                        </button>` : ''}
                    <?php endif; ?>
                </div>
            </div>
        `;
    }

    // Render pagination with ellipsis for many pages
    function renderPagination(currentPage, totalPages) {
        const paginationContainer = document.getElementById('paginationContainer');
        if (totalPages <= 1) return;
        
        let paginationHTML = '<div class="pagination-bar">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHTML += `
                <a href="javascript:void(0);" class="pagination-btn" onclick="loadPage(${currentPage - 1})">
                    &laquo; <?= t('prev') ?>
                </a>
            `;
        }
        
        // Always show first page
        paginationHTML += `
            <a href="javascript:void(0);" class="pagination-btn ${currentPage === 1 ? 'active' : ''}" onclick="loadPage(1)">1</a>
        `;
        
        if (totalPages <= 5) {
            // Show all pages if less than 5
            for (let p = 2; p <= totalPages; p++) {
                if (p == currentPage) {
                    paginationHTML += `<span class="pagination-btn active">${p}</span>`;
                } else {
                    paginationHTML += `
                        <a href="javascript:void(0);" class="pagination-btn" onclick="loadPage(${p})">${p}</a>
                    `;
                }
            }
        } else {
            // Show ellipsis if needed after first page
            if (currentPage > 3) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
            
            // Show pages around current page
            const startPage = Math.max(2, currentPage - 1);
            const endPage = Math.min(totalPages - 1, currentPage + 1);
            
            for (let p = startPage; p <= endPage; p++) {
                if (p == currentPage) {
                    paginationHTML += `<span class="pagination-btn active">${p}</span>`;
                } else {
                    paginationHTML += `
                        <a href="javascript:void(0);" class="pagination-btn" onclick="loadPage(${p})">${p}</a>
                    `;
                }
            }
            
            // Show ellipsis if needed before last page
            if (currentPage < totalPages - 2) {
                paginationHTML += `<span class="pagination-ellipsis">...</span>`;
            }
            
            // Always show last page
            paginationHTML += `
                <a href="javascript:void(0);" class="pagination-btn ${currentPage === totalPages ? 'active' : ''}" onclick="loadPage(${totalPages})">${totalPages}</a>
            `;
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `
                <a href="javascript:void(0);" class="pagination-btn" onclick="loadPage(${currentPage + 1})">
                    <?= t('next') ?> &raquo;
                </a>
            `;
        }
        
        paginationHTML += '</div>';
        paginationContainer.innerHTML = paginationHTML;
    }

    // Highlight text helper function
    function highlightText(text, search) {
        if (!text) text = '';
        if (!search) return escapeHtml(text);
        
        const searchNorm = search.replace(/\s+/g, '').toLowerCase();
        const textNorm = text.toString().replace(/\s+/g, '').toLowerCase();
        
        if (searchNorm === '' || textNorm.indexOf(searchNorm) === -1) {
            return escapeHtml(text);
        }
        
        const pattern = new RegExp(search.replace(/\s+/g, ''), 'gi');
        return escapeHtml(text).replace(pattern, match => `<span class="highlight">${match}</span>`);
    }

    // Escape HTML helper function
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toggle dropdown
    function toggleDropdown(button) {
        event.stopPropagation();
        const dropdown = button.closest('.dropdown');
        const dropdownContent = button.nextElementSibling;
        
        // Check if this dropdown is already open
        const isOpen = dropdownContent.classList.contains('show');
        
        // Close all dropdowns first
        closeAllDropdowns();
        
        // Toggle this dropdown only if it wasn't already open
        if (!isOpen) {
            dropdownContent.classList.add('show');
            
            // Check if dropdown will go below viewport
            const dropdownRect = dropdownContent.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            
            if (dropdownRect.bottom > viewportHeight) {
                // Not enough space below, position above the button
                dropdown.classList.add('bottom-up');
            } else {
                dropdown.classList.remove('bottom-up');
            }
        }
    }

    // Close all dropdowns
    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }

    // Show modal
    let currentAction = '';
    let currentUserId = 0;

    function confirmAction(action, userId, message) {
        currentAction = action;
        currentUserId = userId;
        document.getElementById('modalMessage').textContent = message;
        
        // Set confirm button style based on action type
        const confirmBtn = document.getElementById('confirmActionBtn');
        confirmBtn.className = 'btn ';
        
        switch(action) {
            case 'delete':
            case 'ban':
                confirmBtn.className += 'btn-danger';
                confirmBtn.innerHTML = '<i class="fas fa-trash-alt"></i> <?= t('confirm') ?>';
                break;
            case 'unban':
                confirmBtn.className += 'btn-success';
                confirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> <?= t('confirm') ?>';
                break;
            case 'promote':
                confirmBtn.className += 'btn-blue';
                confirmBtn.innerHTML = '<i class="fas fa-user-shield"></i> <?= t('confirm') ?>';
                break;
            default:
                confirmBtn.className += 'btn-primary';
                confirmBtn.innerHTML = '<i class="fas fa-check"></i> <?= t('confirm') ?>';
        }
        
        document.getElementById('confirmationModal').classList.add('show');
    }

    // Close modal
    function closeModal() {
        document.getElementById('confirmationModal').classList.remove('show');
    }

    // Handle confirm action
    document.getElementById('confirmActionBtn').addEventListener('click', function() {
        closeModal();
        
        switch(currentAction) {
            case 'ban':
                banUser(currentUserId);
                break;
            case 'unban':
                unbanUser(currentUserId);
                break;
            case 'delete':
                deleteUser(currentUserId);
                break;
            case 'promote':
                promoteUser(currentUserId);
                break;
        }
    });

    // Confirm mass delete
    function confirmMassDelete() {
        const checkboxes = document.querySelectorAll('.user-checkbox:checked, .mobile-checkbox:checked');
        if (checkboxes.length === 0) {
            showToast('<?= t('please_select_user') ?>', 'error');
            return;
        }
        
        confirmAction('mass_delete', 0, '<?= t('are_you_sure_mass_delete') ?>'.replace('{count}', checkboxes.length));
        
        // Override the confirm action for mass delete
        document.getElementById('confirmActionBtn').addEventListener('click', function handler() {
            this.removeEventListener('click', handler);
            performMassDelete();
        }, { once: true });
    }

    // Perform mass delete
    function performMassDelete() {
        const formData = new FormData(document.getElementById('massDeleteForm'));
        
        fetch('view_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then( data => {
            if (data.success) {
                data.deleted_ids.forEach(id => {
                    const row = document.getElementById(`user-row-${id}`);
                    if (row) row.remove();
                    const mobileCard = document.getElementById(`mobile-user-${id}`);
                    if (mobileCard) mobileCard.remove();
                });
                showToast('<?= t('users_deleted_success') ?>'.replace('{count}', data.deleted_ids.length), 'success');
                // Uncheck select all checkbox
                document.getElementById('selectAllUsers').checked = false;
                // Exit mobile selection mode
                exitMobileSelection();
                // Reload current page to update pagination
                const currentPage = document.querySelector('.pagination-btn.active')?.textContent || 1;
                loadPage(parseInt(currentPage));
            } else {
                showToast(data.error ? data.error : '<?= t('failed_action') ?>', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
        });
    }

    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.innerHTML = '';
        
        // Add icon based on type
        const icon = document.createElement('i');
        switch(type) {
            case 'success':
                icon.className = 'fas fa-check-circle';
                break;
            case 'error':
                icon.className = 'fas fa-exclamation-circle';
                break;
            case 'warning':
                icon.className = 'fas fa-exclamation-triangle';
                break;
            default:
                icon.className = 'fas fa-info-circle';
        }
        toast.appendChild(icon);
        
        // Add message text
        const text = document.createElement('span');
        text.textContent = message;
        toast.appendChild(text);
        
        // Set toast class
        toast.className = `toast show ${type}`;
        
        // Auto hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Ban user
    function banUser(userId) {
        fetch('view_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=ban&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update desktop view
                const statusCell = document.querySelector(`#user-row-${userId} .user-status`);
                if (statusCell) {
                    statusCell.innerHTML = '<span class="status-banned"><?= t('banned') ?></span>';
                }
                
                // Update mobile view
                const mobileStatus = document.querySelector(`#mobile-user-${userId} .mobile-user-value`);
                if (mobileStatus) {
                    mobileStatus.innerHTML = '<span class="status-banned"><?= t('banned') ?></span>';
                }
                
                // Change button from ban to unban
                const banBtns = document.querySelectorAll(`.ban-btn[data-user-id="${userId}"]`);
                banBtns.forEach(banBtn => {
                    if (banBtn) {
                        banBtn.className = 'dropdown-item unban-btn';
                        banBtn.innerHTML = '<i class="fas fa-check-circle"></i> <?= t('unban_student') ?>';
                        banBtn.onclick = function(e) { 
                            e.stopPropagation(); 
                            confirmAction('unban', userId, '<?= t('are_you_sure_unban') ?>');
                        };
                    }
                });
                
                showToast('<?= t('user_banned_success') ?>', 'success');
            } else {
                showToast('<?= t('failed_action') ?>', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
        });
    }

    // Unban user
    function unbanUser(userId) {
        fetch('view_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=unban&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update desktop view
                const statusCell = document.querySelector(`#user-row-${userId} .user-status`);
                if (statusCell) {
                    statusCell.innerHTML = '<span class="status-active"><?= t('active') ?></span>';
                }
                
                // Update mobile view
                const mobileStatus = document.querySelector(`#mobile-user-${userId} .mobile-user-value`);
                if (mobileStatus) {
                    mobileStatus.innerHTML = '<span class="status-active"><?= t('active') ?></span>';
                }
                
                // Change button from unban to ban
                const unbanBtns = document.querySelectorAll(`.unban-btn[data-user-id="${userId}"]`);
                unbanBtns.forEach(unbanBtn => {
                    if (unbanBtn) {
                        unbanBtn.className = 'dropdown-item ban-btn';
                        unbanBtn.innerHTML = '<i class="fas fa-ban"></i> <?= t('ban_student') ?>';
                        unbanBtn.onclick = function(e) { 
                            e.stopPropagation(); 
                            confirmAction('ban', userId, '<?= t('are_you_sure_ban') ?>');
                        };
                    }
                });
                
                showToast('<?= t('user_unbanned_success') ?>', 'success');
            } else {
                showToast('<?= t('failed_action') ?>', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
        });
    }

    // Delete user
    function deleteUser(userId) {
        fetch('view_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove desktop row
                const row = document.getElementById(`user-row-${userId}`);
                if (row) {
                    row.style.transition = 'all 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
                
                // Remove mobile card
                const mobileCard = document.getElementById(`mobile-user-${userId}`);
                if (mobileCard) {
                    mobileCard.style.transition = 'all 0.3s';
                    mobileCard.style.opacity = '0';
                    setTimeout(() => mobileCard.remove(), 300);
                }
                
                showToast('<?= t('user_deleted_success') ?>', 'success');
                // Reload current page to update pagination
                const currentPage = document.querySelector('.pagination-btn.active')?.textContent || 1;
                loadPage(parseInt(currentPage));
            } else {
                showToast(data.error ? data.error : '<?= t('failed_action') ?>', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
        });
    }

    // Promote user to admin or super admin
    function promoteUser(userId) {
        fetch('view_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=promote&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update status badge in desktop view
                const statusCell = document.querySelector(`#user-row-${userId} .user-status`);
                if (statusCell) {
                    if (data.new_level == 2) {
                        statusCell.innerHTML = '<span class="status-super-admin"><?= t('super_admin') ?></span>';
                    } else {
                        statusCell.innerHTML = '<span class="status-admin"><?= t('admin') ?></span>';
                    }
                }
                
                // Update status in mobile view
                const mobileStatus = document.querySelector(`#mobile-user-${userId} .mobile-user-value`);
                if (mobileStatus) {
                    if (data.new_level == 2) {
                        mobileStatus.innerHTML = '<span class="status-super-admin"><?= t('super_admin') ?></span>';
                    } else {
                        mobileStatus.innerHTML = '<span class="status-admin"><?= t('admin') ?></span>';
                    }
                }
                
                // Remove promote button if user is now super admin
                if (data.new_level == 2) {
                    const promoteBtns = document.querySelectorAll(`.promote-btn[data-user-id="${userId}"]`);
                    promoteBtns.forEach(promoteBtn => {
                        if (promoteBtn) {
                            promoteBtn.remove();
                        }
                    });
                } else {
                    // Update promote button text if user is now admin
                    const promoteBtns = document.querySelectorAll(`.promote-btn[data-user-id="${userId}"]`);
                    promoteBtns.forEach(promoteBtn => {
                        if (promoteBtn) {
                            promoteBtn.innerHTML = '<i class="fas fa-user-shield"></i> <?= t('promote_to_super_admin') ?>';
                            promoteBtn.onclick = function(e) { 
                                e.stopPropagation(); 
                                confirmAction('promote', userId, '<?= t('are_you_sure_promote', ['role' => t('super_admin')]) ?>');
                            };
                        }
                    });
                }
                
                showToast('<?= t('user_promoted_success') ?>'.replace('{role}', data.new_level == 2 ? '<?= t('super_admin') ?>' : '<?= t('admin') ?>'), 'success');
            } else {
                showToast('<?= t('failed_action') ?>', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('<?= t('error_occurred') ?>', 'error');
        });
    }

    // Start with the current count from the server (optional fallback to 0)
    let lastPendingCount = <?= (int)$pendingOrdersCount ?> || 0;
    const audio = document.getElementById('orderSound');

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
    </script>
</body>
</html>