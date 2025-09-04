<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Initialize guest ID if not set
if (!isset($_SESSION['guest_id'])) {
    $_SESSION['guest_id'] = uniqid('guest_', true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_gender'])) {
    header('Content-Type: application/json');
    $gender = $_POST['gender'] ?? '';
    if (in_array($gender, ['male', 'female'])) {
        $_SESSION['user_gender'] = $gender;
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

require_once "../classes/Product.php";
require_once "../classes/Package.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/ProductPackage.php";
require_once "../classes/GuestCart.php";
require_once "../classes/Cart.php";
require_once "../classes/Category.php";
require_once "../classes/User.php";

// Initialize objects
$productObj = new Product();
$packageObj = new Package();
$optionGroupObj = new ProductOptionGroup();
$optionValueObj = new ProductOptionValue();
$productPackageObj = new ProductPackage();
$cartObj = new Cart();
$categoryObj = new Category();

$products = $productObj->getActiveProducts();

$packages = $packageObj->getAllActivePackages();
$packages = array_filter($packages, function($pkg) {
    return ($pkg['visible_to'] ?? 'guest') === 'guest' || ($pkg['visible_to'] ?? 'guest') === 'both';
});

// Get categories
$categories = $categoryObj->getAll();
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[$cat['id']] = htmlspecialchars($cat['name']);
}

// Handle add to cart POST request (modified to use GuestCart class like first code)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $guestCart = new GuestCart();
    $item_type = $_POST['item_type'] ?? '';
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? max(1, min(10, (int)$_POST['quantity'])) : 1;
    $selected_options = $_POST['options'] ?? [];

    // Validate item type
    if (!in_array($item_type, ['product', 'package'])) {
        die("Invalid item type");
    }

    if ($item_type === 'product') {
        $item = $productObj->getById($item_id);
    } else {
        $item = $packageObj->getById($item_id);
    }

    if (!$item) {
        die("Item not found");
    }

    // Check stock for products
    if ($item_type === 'product' && isset($item['quantity']) && $quantity > $item['quantity']) {
        die("Not enough stock available");
    }

    // Add to guest cart using GuestCart class (like first code)
    $success = $guestCart->addItem($_SESSION['guest_id'], $item_id, $item_type, $quantity, $selected_options);

    if ($success) {
        header("Location: new_student_dashboard.php?added=1");
    } else {
        echo "Failed to add to cart.";
    }
    exit;
}

$userGender = $_SESSION['user_gender'] ?? null;
$packages = array_filter($packages, function($pkg) use ($userGender) {
    $visible = ($pkg['visible_to'] ?? 'guest') === 'guest' || ($pkg['visible_to'] ?? 'guest') === 'both';
    $genderOk = empty($pkg['gender']) || $pkg['gender'] === 'both' || $pkg['gender'] === $userGender;
    return $visible && $genderOk;
});

// Get guest cart items using GuestCart class (like first code)
$guestCart = new GuestCart();
$guestCartItems = $guestCart->getItemsByGuest($_SESSION['guest_id']);
$packageIdsInCart = [];
foreach ($guestCartItems as $item) {
    if ($item['item_type'] === 'package') {
        $packageIdsInCart[] = $item['item_id'];
    }
}

// Check for success message
$showSuccess = isset($_GET['added']) && $_GET['added'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMJK Phor Tay Stationery Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        /* Light green and yellow color palette */
        --primary-color: #c8e6c9; /* Very light green */
        --primary-light: #e8f5e9;
        --primary-dark: #a5d6a7;
        --secondary-color: #fff9c4; /* Very light yellow */
        --secondary-light: #fffde7;
        --secondary-dark: #fff59d;
        --accent-color: #81c784; /* Soft green accent */
        --accent-hover: #66bb6a;
        --text-primary: #333333;
        --text-secondary: #666666;
        --divider-color: #e0e0e0;
        --background: #f9fbe7; /* Very light yellow */
        --surface: #ffffff;
        --error: #d32f2f;
        --success: #4caf50;
        --warning: #ffa000;
        
        /* Light Colors */
        --light-green: #e8f5e9; /* Very light green */
        --light-yellow: #fffde7; /* Very light yellow */
        
        /* Spacing */
        --spacing-xs: 4px;
        --spacing-sm: 8px;
        --spacing-md: 16px;
        --spacing-lg: 24px;
        --spacing-xl: 32px;
        --spacing-xxl: 48px;
        
        /* Typography */
        --font-size-sm: 0.875rem;
        --font-size-md: 1rem;
        --font-size-lg: 1.25rem;
        --font-size-xl: 1.5rem;
        --font-size-xxl: 2rem;
        
        /* Shadows */
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        --shadow-md: 0 3px 6px rgba(0,0,0,0.16), 0 3px 6px rgba(0,0,0,0.23);
        --shadow-lg: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
        --shadow-inset: inset 0 1px 2px rgba(0,0,0,0.1);
        
        /* Border radius */
        --border-radius-sm: 4px;
        --border-radius-md: 8px;
        --border-radius-lg: 12px;
        --border-radius-circle: 50%;
        --border-radius-pill: 50px;
        
        /* Transition */
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--background);
        color: var(--text-primary);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        padding-top: 120px; /* Added padding to account for fixed header */
    }

    a {
        text-decoration: none;
        color: inherit;
    }

    button {
        font-family: inherit;
        cursor: pointer;
        transition: var(--transition);
    }

    img {
        max-width: 100%;
        height: auto;
        display: block;
    }

    /* Utility Classes */
    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--spacing-md);
    }

    .flex {
        display: flex;
    }

    .flex-center {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .flex-between {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .text-center {
        text-align: center;
    }

    .text-uppercase {
        text-transform: uppercase;
    }

    .text-bold {
        font-weight: 600;
    }

    .text-muted {
        color: var(--text-secondary);
    }

    .mb-sm { margin-bottom: var(--spacing-sm); }
    .mb-md { margin-bottom: var(--spacing-md); }
    .mb-lg { margin-bottom: var(--spacing-lg); }
    .mb-xl { margin-bottom: var(--spacing-xl); }

    /* Button Styles - Enhanced and Consistent */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 16px;
        border-radius: var(--border-radius-pill);
        font-weight: 500;
        transition: var(--transition);
        border: none;
        white-space: nowrap;
        background-color: var(--primary-dark);
        color: var(--text-primary);
        box-shadow: var(--shadow-sm);
        position: relative;
        overflow: hidden;
        height: 40px;
        font-size: var(--font-size-sm);
        gap: 8px;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn:active {
        transform: translateY(0);
        box-shadow: var(--shadow-sm);
    }

    .btn--primary {
        background-color: var(--primary-dark);
        color: var(--text-primary);
    }

    .btn--primary:hover {
        background-color: var(--accent-color);
    }

    .btn--icon {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: var(--border-radius-circle);
    }

    .btn--icon .icon {
        font-size: var(--font-size-md);
    }

    .btn--accent {
        background-color: var(--accent-color);
        color: white;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: var(--font-size-sm);
    }

    .btn--accent:hover {
        background-color: var(--accent-hover);
    }

    .btn--outline {
        background-color: transparent;
        border: 2px solid var(--accent-color);
        color: var(--accent-color);
        font-weight: 600;
    }

    .btn--outline:hover {
        background-color: var(--accent-color);
        color: white;
    }

    .btn--rounded {
        border-radius: var(--border-radius-pill);
    }

    /* Button with icon */
    .btn--with-icon {
        gap: 8px;
    }

    /* Header Styles - Updated to be fixed */
    .header {
        background-color: var(--primary-color);
        color: var(--text-primary);
        padding: var(--spacing-md) 0;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        box-shadow: var(--shadow-md);
        border-bottom: 1px solid var(--divider-color);
        transition: transform 0.3s ease;
    }

    /* Class to hide header when scrolling down */
    .header.hide {
        transform: translateY(-100%);
    }

    .header__content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }

    .logo__img {
        height: 50px;
        width: auto;
    }

    .logo__text {
        font-size: var(--font-size-lg);
        font-weight: 600;
    }

    .header__actions {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }

    .avatar {
        width: 40px;
        height: 40px;
        border-radius: var(--border-radius-circle);
        object-fit: cover;
        border: 2px solid var(--surface);
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        width: 18px;
        height: 18px;
        background-color: var(--error);
        border-radius: var(--border-radius-circle);
        border: 2px solid var(--primary-color);
    }

    /* Success message styles - Updated to top right corner */
    .success-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: var(--success);
        color: white;
        padding: 12px 20px;
        border-radius: var(--border-radius-md);
        margin-bottom: 15px;
        text-align: center;
        animation: fadeInOut 2.5s ease-in-out;
        z-index: 1100;
        box-shadow: var(--shadow-md);
        opacity: 0;
        transform: translateX(100%);
    }
    
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translateX(100%); }
        20% { opacity: 1; transform: translateX(0); }
        80% { opacity: 1; transform: translateX(0); }
        100% { opacity: 0; transform: translateX(100%); }
    }

    /* Main Layout */
    .main {
        padding: var(--spacing-lg) 0;
        background-color: var(--light-yellow);
    }

    .content-wrapper {
        display: flex;
        gap: var(--spacing-lg);
    }

    /* Sidebar Styles - Updated to be fixed and hide/show with scroll */
    .sidebar {
        width: 240px;
        flex-shrink: 0;
        background-color: var(--light-green);
        border-radius: var(--border-radius-md);
        box-shadow: var(--shadow-sm);
        padding: var(--spacing-md);
        height: fit-content;
        position: sticky;
        top: calc(120px + var(--spacing-lg)); /* Adjusted for fixed header */
        transition: transform 0.3s ease;
        z-index: 900;
    }

    .sidebar.hide {
        transform: translateX(-100%);
    }

    .sidebar__title {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--spacing-md);
        padding-bottom: var(--spacing-sm);
        border-bottom: 1px solid var(--divider-color);
    }

    .filter-group {
        margin-bottom: var(--spacing-md);
    }

    .filter-label {
        display: block;
        margin-bottom: var(--spacing-sm);
        font-weight: 500;
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
    }

    .checkbox-group {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        font-size: var(--font-size-sm);
        cursor: pointer;
    }

    /* Main Content Styles */
    .main-content {
        flex: 1;
    }

    .section-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--text-primary);
        margin: var(--spacing-xl) 0 var(--spacing-md);
    }

    .section-title:first-child {
        margin-top: 0;
    }

    .search-container {
        display: flex;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-lg);
    }

    .search-input {
        flex: 1;
        padding: var(--spacing-sm) var(--spacing-md);
        border: 1px solid var(--divider-color);
        border-radius: var(--border-radius-pill);
        font-size: var(--font-size-md);
        transition: var(--transition);
        background-color: var(--surface);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 2px rgba(129, 199, 132, 0.2);
    }

    .search-btn {
        background-color: var(--accent-color);
        color: var(--text-primary);
        border: none;
        border-radius: var(--border-radius-pill);
        padding: 0 var(--spacing-md);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
    }

    .search-btn:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
    }

    /* Product Grid */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: var(--spacing-md);
    }

    .product-card {
        background: var(--surface);
        border-radius: var(--border-radius-md);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: var(--transition);
        border: 1px solid var(--divider-color);
        display: flex;
        flex-direction: column;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .product__image-container {
        position: relative;
        width: 100%;
        aspect-ratio: 3/4;
        overflow: hidden;
        background: var(--surface);
    }

    .product__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .product-card:hover .product__image {
        transform: scale(1.05);
    }

    .product__badge {
        position: absolute;
        top: var(--spacing-sm);
        right: var(--spacing-sm);
        background-color: var(--success);
        color: white;
        padding: 2px var(--spacing-sm);
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-sm);
        font-weight: 500;
        z-index: 1;
    }

    .product__content {
        padding: var(--spacing-md);
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product__name {
        font-size: var(--font-size-md);
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: var(--spacing-xs);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product__description {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        margin-bottom: var(--spacing-sm);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
    }

    .product__price {
        font-size: var(--font-size-lg);
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: var(--spacing-sm);
    }

    .product__stock {
        font-size: var(--font-size-sm);
        color: var(--success);
        margin-bottom: var(--spacing-sm);
    }

    .add-to-cart-btn {
        width: 100%;
        background-color: var(--accent-color);
        color: white;
        border: none;
        padding: 12px;
        font-size: var(--font-size-sm);
        border-radius: var(--border-radius-pill);
        cursor: pointer;
        transition: var(--transition);
        font-weight: 600;
        margin-top: auto;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 40px;
    }

    .add-to-cart-btn:hover {
        background-color: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .add-to-cart-btn:active {
        transform: translateY(0);
    }

    .add-to-cart-btn:disabled {
        background-color: var(--divider-color);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .add-to-cart-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Package Contents */
    .package-contents {
        margin: var(--spacing-sm) 0;
    }

    .contents-title {
        font-weight: 500;
        margin-bottom: var(--spacing-xs);
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
    }

    .contents-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .content-item {
        display: flex;
        align-items: center;
        padding: var(--spacing-xs) 0;
        border-bottom: 1px solid var(--divider-color);
        font-size: var(--font-size-sm);
    }

    .content-item:last-child {
        border-bottom: none;
    }

    .content-image {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: var(--border-radius-sm);
        margin-right: var(--spacing-sm);
    }

    .content-name {
        flex: 1;
        font-size: var(--font-size-sm);
    }

    .content-quantity {
        font-weight: 500;
        color: var(--success);
        font-size: var(--font-size-sm);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
        z-index: 1100;
        justify-content: center;
        align-items: center;
        padding: var(--spacing-md);
    }

    .modal--active {
        display: flex;
    }

    .modal__content {
        background: var(--light-yellow);
        border-radius: var(--border-radius-md);
        width: 100%;
        max-width: 480px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        animation: modalFadeIn 0.3s ease;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .modal__header {
        padding: var(--spacing-md);
        background-color: var(--primary-color);
        color: var(--text-primary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--divider-color);
    }

    .modal__title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        margin: 0;
    }

    .modal__close {
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: var(--font-size-xl);
        cursor: pointer;
        transition: var(--transition);
        padding: var(--spacing-xs);
        border-radius: var(--border-radius-circle);
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal__close:hover {
        background-color: rgba(0,0,0,0.1);
    }

    .modal__body {
        padding: var(--spacing-md);
        overflow-y: auto;
        flex: 1;
        background-color: var(--light-yellow);
    }

    .modal__footer {
        padding: var(--spacing-md);
        background-color: var(--light-green);
        display: flex;
        justify-content: flex-end;
        border-top: 1px solid var(--divider-color);
    }

    .option-group {
        margin-bottom: var(--spacing-md);
    }

    .option-label {
        display: block;
        margin-bottom: var(--spacing-xs);
        font-weight: 500;
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
    }

    .option-select {
        width: 100%;
        padding: var(--spacing-sm);
        border: 1px solid var(--divider-color);
        border-radius: var(--border-radius-sm);
        font-size: var(--font-size-md);
        transition: var(--transition);
        background-color: var(--surface);
    }

    .option-select:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 2px rgba(129, 199, 132, 0.2);
    }

    .quantity-control {
        display: flex;
        align-items: center;
        margin: var(--spacing-md) 0;
    }

    .quantity-label {
        margin-right: var(--spacing-md);
        font-weight: 500;
        font-size: var(--font-size-md);
    }

    .quantity-input {
        width: 80px;
        padding: var(--spacing-sm);
        border: 1px solid var(--divider-color);
        border-radius: var(--border-radius-sm);
        text-align: center;
        font-size: var(--font-size-md);
        background-color: var(--surface);
    }

    /* Image Gallery Modal */
    .image-modal .modal__content {
        max-width: 800px;
        background: transparent;
        box-shadow: none;
    }

    .image-modal__content {
        position: relative;
        height: 70vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .image-modal__img {
        max-width: 100%;
        max-height: 100%;
        border-radius: var(--border-radius-md);
        box-shadow: var(--shadow-lg);
    }

    .image-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background-color: rgba(255, 255, 255, 0.9);
        border: none;
        color: var(--text-primary);
        font-size: var(--font-size-xl);
        width: 48px;
        height: 48px;
        border-radius: var(--border-radius-circle);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        z-index: 10;
        box-shadow: var(--shadow-sm);
    }

    .image-arrow:hover {
        background-color: white;
        box-shadow: var(--shadow-md);
    }

    .image-arrow--left {
        left: var(--spacing-md);
    }

    .image-arrow--right {
        right: var(--spacing-md);
    }

    /* Sidebar toggle button */
    .sidebar-toggle {
        display: none;
        position: fixed;
        left: var(--spacing-md);
        top: calc(120px + var(--spacing-md)); /* Adjusted for fixed header */
        z-index: 1100;
        background: var(--accent-color);
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: var(--border-radius-pill);
        font-size: var(--font-size-sm);
        box-shadow: var(--shadow-md);
        cursor: pointer;
        transition: var(--transition);
    }

    .sidebar-toggle:hover {
        background-color: var(--accent-hover);
        transform: translateY(-2px);
    }

    /* Responsive Styles */
    /* Large devices (desktops, 992px and up) */
    @media (min-width: 992px) {
        .sidebar {
            display: block !important;
        }
    }

    /* Medium devices (tablets, 768px - 991px) */
    @media (max-width: 991px) {
        .content-wrapper {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            position: static;
            margin-bottom: var(--spacing-md);
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }

    /* Small devices (landscape phones, 576px - 767px) */
    @media (max-width: 767px) {
        body {
            padding-top: 100px; /* Reduced padding for mobile */
        }
        
        .header {
            padding: var(--spacing-sm) 0;
        }
        
        .header__content {
            flex-wrap: wrap;
            gap: var(--spacing-sm);
        }
        
        .logo {
            width: 100%;
            justify-content: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .header__actions {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .logo__img {
            height: 40px;
        }
        
        .logo__text {
            font-size: var(--font-size-md);
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: var(--spacing-sm);
        }
        
        .modal__content {
            max-height: 80vh;
        }
        
        .sidebar-toggle {
            display: block;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            z-index: 1050;
            transform: translateX(-100%);
            transition: var(--transition);
            overflow-y: auto;
            padding-top: 60px;
            display: none;
        }
        
        .sidebar--active {
            transform: translateX(0);
            display: block;
        }

        .header__actions .btn {
            padding: 8px 12px;
            font-size: 0.8rem;
        }

        .header__actions .btn svg {
            width: 14px;
            height: 14px;
        }

        .section-title {
            font-size: var(--font-size-md);
            margin: var(--spacing-md) 0;
        }
    }

    /* Extra small devices (portrait phones, less than 576px) */
    @media (max-width: 575px) {
        body {
            padding-top: 90px;
        }

        .product-grid {
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-sm);
        }
        
        .product__name {
            font-size: var(--font-size-sm);
        }
        
        .product__description {
            font-size: 0.75rem;
            -webkit-line-clamp: 2;
        }
        
        .product__price {
            font-size: var(--font-size-md);
        }
        
        .modal__content {
            max-width: 95vw;
        }

        .header__actions .btn span {
            display: none;
        }
        
        .header__actions .btn .icon {
            margin-right: 0;
        }

        .header__actions .btn {
            width: 40px;
            height: 40px;
            padding: 0;
            justify-content: center;
            border-radius: 50%;
        }

        .header__actions .btn svg {
            margin: 0;
        }

        .logo__text {
            font-size: 1rem;
        }

        .sidebar-toggle {
            top: calc(90px + var(--spacing-sm));
        }
    }

    /* Very small devices (phones less than 400px) */
    @media (max-width: 399px) {
        .product-grid {
            grid-template-columns: 1fr;
        }

        .product-card {
            max-width: 280px;
            margin: 0 auto;
        }

        .header {
            padding: var(--spacing-xs) 0;
        }

        .logo__img {
            height: 35px;
        }

        .logo__text {
            font-size: 0.9rem;
        }

        .header__actions .btn {
            width: 36px;
            height: 36px;
        }

        .avatar {
            width: 36px;
            height: 36px;
        }

        .sidebar {
            width: 260px;
        }
    }

    /* Orientation-specific styles */
    @media screen and (max-width: 767px) and (orientation: landscape) {
        .modal__content {
            max-height: 80vh;
        }

        .image-modal__content {
            height: 60vh;
        }
    }

    /* Gender Modal */
    .gender-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .gender-modal-content {
        background: white;
        padding: var(--spacing-xl);
        border-radius: var(--border-radius-md);
        text-align: center;
        max-width: 400px;
        width: 90%;
    }

    .gender-title {
        margin-bottom: var(--spacing-lg);
        color: var(--primary-dark);
    }

    .gender-btn {
        display: inline-block;
        padding: var(--spacing-md) var(--spacing-xl);
        margin: 0 var(--spacing-sm) var(--spacing-sm);
        background: var(--primary-light);
        color: var(--primary-dark);
        border: none;
        border-radius: var(--border-radius-pill);
        font-size: var(--font-size-lg);
        cursor: pointer;
        transition: var(--transition);
    }

    .gender-btn:hover {
        background: var(--primary-color);
        color: white;
    }

    .gender-back-btn {
        display: inline-block;
        margin-top: var(--spacing-lg);
        padding: var(--spacing-sm) var(--spacing-xl);
        background: white;
        color: var(--primary-dark);
        border: 2px solid var(--primary-dark);
        border-radius: var(--border-radius-pill);
        font-size: var(--font-size-md);
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
    }

    .gender-back-btn:hover {
        background: var(--primary-dark);
        color: white;
    }
    </style>
</head>
<body>
<?php if (!isset($_SESSION['user_gender'])): ?>
 <div class="gender-modal">
    <div class="gender-modal-content">
        <h2 class="gender-title">🌱 Please select your gender</h2>
        <form id="genderForm" method="POST">
            <input type="hidden" name="select_gender" value="1">
            <button type="button" class="gender-btn" data-gender="male">👦 Male</button>
            <button type="button" class="gender-btn" data-gender="female">👧 Female</button>
        </form>
        <a href="login.php" class="gender-back-btn">← Back to Login</a>
    </div>
</div>
<script>
document.querySelectorAll('.gender-btn').forEach(btn => {
    btn.onclick = function() {
        const gender = this.getAttribute('data-gender');
        fetch(location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'select_gender=1&gender=' + encodeURIComponent(gender)
        }).then(() => {
            document.querySelector('.gender-modal').style.display = 'none';
            location.reload();
        });
    };
});
</script>
<?php endif; ?>

<!-- Success message positioned at top right corner -->
<?php if ($showSuccess): ?>
<div class="success-message">
    Item added to cart successfully!
</div>
<?php endif; ?>

 <header class="header">
        <div class="container header__content">
            <div class="logo">
                <a href="new_.php">
                    <img src="images/llogo.png" alt="SMJK Phor Tay Logo" class="logo__img">
                </a>
                <span class="logo__text">SMJK Phor Tay Stationery</span>
            </div>
            <div class="header__actions">
                <a href="new_student_cart.php" class="btn btn--primary btn--with-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="icon">
                        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    <span>View Cart</span>
                </a>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="container">
            <button class="sidebar-toggle">☰ Filters</button>
            
            <div class="content-wrapper">
              <aside class="sidebar">
                    <h3 class="sidebar__title">Filter Products</h3>
                    <form id="categoryFilter">
                        <div class="filter-group">
                            <label class="filter-label">Categories</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" class="filter-checkbox" value="all" checked>
                                    All Categories
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" class="filter-checkbox category-checkbox" value="<?= htmlspecialchars($cat['name']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                </aside>

                <div class="main-content">
                   <div class="search-container">
                        <input type="text" class="search-input" placeholder="Search products or packages...">
                        <button class="search-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 1 1.414-1.414l3.182 3.182a1 1 0 0 1-1.414 1.414l-3.182-3.182zM12 6.5a5.5 5.5 0 1 0-11 0 5.5 5.5 0 0 0 11 0z"/>
                            </svg>
                        </button>
                    </div>

                    <h2 class="section-title">Packages</h2>
                    <div class="product-grid">
                        <?php foreach ($packages as $package): ?>
                            <?php
                            $alreadyInCart = in_array($package['id'], $packageIdsInCart);
                            $images = $packageObj->getImagesByPackageId($package['id']);
                            $mainImage = $package['image'] ?: 'default.jpg';
                            $imageArray = [];
                            if (!empty($images)) {
                                foreach ($images as $img) {
                                    if (isset($img['image'])) {
                                        $imageArray[] = $img['image'];
                                    } elseif (isset($img['image_path'])) {
                                        $imageArray[] = $img['image_path'];
                                    } elseif (isset($img['filename'])) {
                                        $imageArray[] = $img['filename'];
                                    }
                                }
                            } else {
                                $imageArray[] = $mainImage;
                            }
                            $packageCategories = $packageObj->getCategoriesByPackageId($package['id']);
                            $packageCategories[] = 'Packages';
                            $dataCategory = implode(',', array_map('htmlspecialchars', $packageCategories));
                            
                            $productsInPackage = $packageObj->getProductsInPackage($package['id']);
                            foreach ($productsInPackage as &$prodInPack) {
                                $optionGroups = $optionGroupObj->getByProduct($prodInPack['product_id']);
                                foreach ($optionGroups as &$group) {
                                    $group['options'] = [];
                                    $values = $optionValueObj->getByGroup($group['id']);
                                    foreach ($values as $val) {
                                        $group['options'][] = $val['value'];
                                    }
                                }
                                unset($group);
                                $prodInPack['options_group'] = $optionGroups;
                            }
                            unset($prodInPack);
                            $popupContents = [];
                            foreach ($productPackageObj->getByPackageId($package['id']) as $item) {
                                $prod = $productObj->getById($item['product_id']);
                                $productImages = method_exists($productObj, 'getImagesByProductId')
                                    ? $productObj->getImagesByProductId($prod['id'])
                                    : [];
                                $firstImage = !empty($productImages) && !empty($productImages[0]['image'])
                                    ? $productImages[0]['image']
                                    : 'default.jpg';

                                $popupContents[] = [
                                    'name' => $prod['name'],
                                    'image' => $firstImage,
                                    'quantity' => intval($item['quantity'])
                                ];
                            }
                            ?>
                            <div class="product-card" data-category="<?= $dataCategory ?>">
                                <div class="product__image-container">
                                    <img src="../uploads/<?= htmlspecialchars($mainImage) ?>"
                                        alt="<?= htmlspecialchars($package['name']) ?>"
                                        class="product__image package-image-click"
                                        data-images='<?= json_encode($imageArray) ?>'>
                                    <span class="product__badge">Package</span>
                                </div>
                                <div class="product__content">
                                    <h3 class="product__name"><?= htmlspecialchars($package['name']) ?></h3>
                                    <div class="package-contents">
                                        <strong class="contents-title">Includes:</strong>
                                        <ul class="contents-list">
                                            <?php
                                            $contents = $productPackageObj->getByPackageId($package['id']);
                                            $popupContents = [];
                                            foreach ($contents as $item) {
                                                $prod = $productObj->getById($item['product_id']);
                                                $productImages = method_exists($productObj, 'getImagesByProductId')
                                                    ? $productObj->getImagesByProductId($prod['id'])
                                                    : [];
                                                $prodImage = !empty($productImages) && !empty($productImages[0]['image'])
                                                    ? $productImages[0]['image']
                                                    : 'default.jpg';

                                                $popupContents[] = [
                                                    'name' => $prod['name'],
                                                    'image' => $prodImage,
                                                    'quantity' => intval($item['quantity'])
                                                ];
                                            }
                                            foreach (array_slice($contents, 0, 3) as $item):
                                                $prod = $productObj->getById($item['product_id']);
                                                $productImages = method_exists($productObj, 'getImagesByProductId')
                                                    ? $productObj->getImagesByProductId($prod['id'])
                                                    : [];
                                                $prodImage = !empty($productImages) && !empty($productImages[0]['image'])
                                                    ? $productImages[0]['image']
                                                    : 'default.jpg';
                                            ?>
                                            <li class="content-item">
                                                <img src="../uploads/<?= htmlspecialchars($prodImage) ?>" class="content-image" alt="<?= htmlspecialchars($prod['name']) ?>">
                                                <span class="content-name"><?= htmlspecialchars($prod['name']) ?></span>
                                                <span class="content-quantity">x<?= $item['quantity'] ?></span>
                                            </li>
                                            <?php endforeach; ?>
                                            <?php if (count($contents) > 3): ?>
                                            <li class="content-item" style="justify-content: center; color: var(--accent-color);">
                                                +<?= count($contents) - 3 ?> more items
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="product__price">RM<?= number_format($package['price'], 2) ?></div>
                                    <button class="add-to-cart-btn"
                                        data-type="package"
                                        data-id="<?= $package['id'] ?>"
                                        data-name="<?= htmlspecialchars($package['name']) ?>"
                                        data-price="<?= $package['price'] ?>"
                                        data-options='<?= json_encode($productsInPackage) ?>'
                                        data-contents='<?= json_encode($popupContents) ?>'
                                        data-description="<?= htmlspecialchars($package['description'], ENT_QUOTES) ?>"
                                        <?= $alreadyInCart ? 'disabled style="background:#ccc;cursor:not-allowed;"' : '' ?>
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                        </svg>
                                        <?= $alreadyInCart ? 'Already in Cart' : 'Add to Cart' ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h2 class="section-title">Products</h2>
                    <div class="product-grid">
                        <?php foreach ($products as $product): ?>
                        <?php
                        $optionGroups = $optionGroupObj->getByProduct($product['id']);
                        foreach ($optionGroups as &$group) {
                            $group['values'] = $optionValueObj->getByGroup($group['id']);
                        }
                        unset($group);

                        $productImages = method_exists($productObj, 'getImagesByProductId')
                            ? $productObj->getImagesByProductId($product['id'])
                            : [];
                        $imageArray = [];
                        if (!empty($productImages)) {
                            foreach ($productImages as $img) {
                                if (isset($img['image'])) {
                                    $imageArray[] = $img['image'];
                                } elseif (isset($img['image_path'])) {
                                    $imageArray[] = $img['image_path'];
                                } elseif (isset($img['filename'])) {
                                    $imageArray[] = $img['filename'];
                                }
                            }
                        }
                        if (empty($imageArray)) {
                            $imageArray[] = 'default.jpg';
                        }
                        ?>
                        <div class="product-card" data-category="<?= htmlspecialchars($categoryMap[$product['category_id']] ?? 'Uncategorized') ?>">
                            <div class="product__image-container">
                                <img src="../uploads/<?= htmlspecialchars($imageArray[0]) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                    class="product__image product-image-click"
                                    data-images='<?= json_encode($imageArray) ?>'>
                                <span class="product__badge">In Stock</span>
                            </div>
                            <div class="product__content">
                                <h3 class="product__name"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="product__description"><?= htmlspecialchars($product['description']) ?></p>
                                <div class="product__price">RM<?= number_format($product['price'], 2) ?></div>
                                <div class="product__stock">In Stock: <?= (int)$product['quantity'] ?></div>
                                <button class="add-to-cart-btn" 
                                    data-type="product"
                                    data-id="<?= $product['id'] ?>"
                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-price="<?= $product['price'] ?>"
                                    data-options='<?= json_encode($optionGroups) ?>'
                                    data-description="<?= htmlspecialchars($product['description'], ENT_QUOTES) ?>"
                                    data-images='<?= json_encode($imageArray) ?>'
                                    data-quantity="<?= (int)$product['quantity'] ?>"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Option Selection Modal -->
    <div id="optionModal" class="modal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title" id="modalTitle"></h3>
                <button class="modal__close">&times;</button>
            </div>
            <div class="modal__body">
                <form id="modalForm" method="POST" action="new_student_dashboard.php">
                    <input type="hidden" name="add_to_cart" value="1">
                    <input type="hidden" name="item_type" id="modal_item_type">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <div id="modalProductImages" class="mb-md"></div>
                    <div id="modalDescription" class="text-muted mb-md"></div>
                    <div id="modalContents" class="package-contents mb-md" style="display: none;"></div>
                    <div id="modalOptions"></div>
                    <div class="quantity-control">
                        <span class="quantity-label">Quantity:</span>
                        <input type="number" class="quantity-input" name="quantity" value="1" min="1" required>
                    </div>
            </div>
            <div class="modal__footer">
                    <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Gallery Modal -->
    <div id="imageModal" class="modal image-modal">
        <div class="modal__content">
            <div class="image-modal__content">
                <img id="modalImage" class="image-modal__img" src="" alt="">
                <button class="image-arrow image-arrow--left">&#8592;</button>
                <button class="image-arrow image-arrow--right">&#8594;</button>
            </div>
        </div>
    </div>

    <script>
    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');

    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (sidebar.classList.contains('active') && 
            !e.target.closest('.sidebar') && 
            !e.target.closest('.sidebar-toggle')) {
            sidebar.classList.remove('active');
        }
    });

    // Modal close logic
    const modals = document.querySelectorAll('.modal');
    const closeModalButtons = document.querySelectorAll('.modal__close');
    
    closeModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            modal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', (event) => {
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Add to cart button click
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        if (!btn.closest('#optionModal')) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = document.getElementById('optionModal');
                document.getElementById('modal_item_type').value = this.dataset.type;
                document.getElementById('modal_item_id').value = this.dataset.id;
                document.getElementById('modalTitle').textContent = this.dataset.name;
                
                document.getElementById('modalProductImages').innerHTML = '';
                document.getElementById('modalDescription').textContent = '';
                document.getElementById('modalContents').innerHTML = '';
                document.getElementById('modalContents').style.display = 'none';
                
                if (this.dataset.type === 'product' && this.dataset.images) {
                    const images = JSON.parse(this.dataset.images);
                    if (images.length > 0) {
                        setupModalImageGallery(images);
                    }
                }
                
                if (this.dataset.description) {
                    document.getElementById('modalDescription').textContent = this.dataset.description;
                }
                
                if (this.dataset.type === 'package' && this.dataset.contents) {
                    const contents = JSON.parse(this.dataset.contents);
                    if (contents.length > 0) {
                        const contentsContainer = document.getElementById('modalContents');
                        contentsContainer.style.display = 'block';
                        contentsContainer.innerHTML = '<strong class="contents-title">Package Contents:</strong><ul class="contents-list">';
                        contents.forEach(item => {
                            contentsContainer.innerHTML += `
                                <li class="content-item">
                                    <img src="../uploads/${item.image}" class="content-image" alt="${item.name}">
                                    <span class="content-name">${item.name}</span>
                                    <span class="content-quantity">x${item.quantity}</span>
                                </li>
                            `;
                        });
                        contentsContainer.innerHTML += '</ul>';
                    }
                }
                
                let optionsHtml = '';
                let options = JSON.parse(this.dataset.options);
                if (this.dataset.type === 'product') {
                    if (options.length > 0) {
                        options.forEach(group => {
                            optionsHtml += `<div class="option-group">
                                <label class="option-label">${group.name}</label>
                                <select class="option-select" name="options[${group.name}]" required>
                                    <option value="">Select ${group.name}</option>`;
                            if (group.values && group.values.length > 0) {
                                group.values.forEach(val => {
                                    optionsHtml += `<option value="${val.value}">${val.value}</option>`;
                                });
                            }
                            optionsHtml += `</select></div>`;
                        });
                    }
                } else if (this.dataset.type === 'package') {
                    options.forEach(product => {
                        if (product.options_group) {
                            product.options_group.forEach(group => {
                                optionsHtml += `<div class="option-group">
                                    <label class="option-label">${product.name} - ${group.name}</label>
                                    <select class="option-select" name="options[${product.product_id}][${group.name}]" required>
                                        <option value="">Select ${group.name}</option>`;
                                group.options.forEach(val => {
                                    optionsHtml += `<option value="${val}">${val}</option>`;
                                });
                                optionsHtml += `</select></div>`;
                            });
                        }
                    });
                }
                document.getElementById('modalOptions').innerHTML = optionsHtml;
                modal.style.display = 'flex';

                // Set max quantity based on stock
                const maxQty = parseInt(this.dataset.quantity, 10) || 1;
                const qtyInput = document.querySelector('#optionModal .quantity-input');
                qtyInput.max = maxQty;
                qtyInput.value = maxQty > 0 ? 1 : 0;
                qtyInput.disabled = maxQty === 0;
                // Optionally, show out of stock message
                if (maxQty === 0) {
                    qtyInput.closest('.quantity-control').insertAdjacentHTML('beforeend', '<span style="color:#e74c3c;margin-left:10px;">Out of stock</span>');
                    document.querySelector('#optionModal .add-to-cart-btn').disabled = true;
                } else {
                    document.querySelector('#optionModal .add-to-cart-btn').disabled = false;
                }
            });
        }
    });
    
    // Image gallery functionality
    function showImageModal(images, startIndex = 0) {
        let currentIndex = startIndex;
        const modalImage = document.getElementById('modalImage');
        const imageModal = document.getElementById('imageModal');
        const leftArrow = document.querySelector('.image-arrow--left');
        const rightArrow = document.querySelector('.image-arrow--right');
        
        function renderImage() {
            modalImage.src = '../uploads/' + images[currentIndex];
        }
        
        leftArrow.onclick = function(e) {
            e.stopPropagation();
            currentIndex = (currentIndex - 1 + images.length) % images.length;
            renderImage();
        };
        
        rightArrow.onclick = function(e) {
            e.stopPropagation();
            currentIndex = (currentIndex + 1) % images.length;
            renderImage();
        };
        
        renderImage();
        imageModal.style.display = 'flex';
    }
    
    // Product image click
    document.querySelectorAll('.product-image-click').forEach(image => {
        image.addEventListener('click', function() {
            const images = JSON.parse(this.getAttribute('data-images'));
            showImageModal(images, 0);
        });
    });
    
    // Package image click
    document.querySelectorAll('.package-image-click').forEach(image => {
        image.addEventListener('click', function() {
            const images = JSON.parse(this.getAttribute('data-images'));
            showImageModal(images, 0);
        });
    });
    
    // Search functionality
    document.querySelector('.search-input')?.addEventListener('input', function() {
        const searchTerm = this.value.trim().toLowerCase();
        document.querySelectorAll('.product-card').forEach(card => {
            const name = card.querySelector('.product__name')?.textContent.toLowerCase() || '';
            const desc = card.querySelector('.product__description')?.textContent.toLowerCase() || '';
            if (name.includes(searchTerm) || desc.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Category filter functionality
    const categoryForm = document.getElementById('categoryFilter');
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    const allCheckbox = document.querySelector('input[value="all"]');
    
    categoryCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (this.checked) allCheckbox.checked = false;
            filterProducts();
        });
    });
    
    allCheckbox.addEventListener('change', function() {
        if (this.checked) {
            categoryCheckboxes.forEach(cb => cb.checked = false);
            filterProducts();
        }
    });
    
    function filterProducts() {
        let checked = Array.from(categoryCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (allCheckbox.checked || checked.length === 0) {
            document.querySelectorAll('.product-card').forEach(card => card.style.display = '');
            allCheckbox.checked = true;
        } else {
            document.querySelectorAll('.product-card').forEach(card => {
                const catAttr = card.getAttribute('data-category') || '';
                const catList = catAttr.split(',').map(s => s.trim());
                if (catList.some(cat => checked.includes(cat))) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    }
    
    // Make product cards more touch-friendly
    document.querySelectorAll('.product-card').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function(e) {
            if (!e.target.closest('button') && !e.target.closest('a')) {
                const btn = this.querySelector('.add-to-cart-btn');
                if (btn) btn.click();
            }
        });
    });
    
    // Setup modal image gallery
    function setupModalImageGallery(images) {
        let currentIndex = 0;
        const imgContainer = document.getElementById('modalProductImages');
        imgContainer.innerHTML = `
            <div style="position:relative;">
                <img id="modalMainImage" src="../uploads/${images[0]}" alt="" style="width:100%; max-height:150px; object-fit:contain; border-radius:4px;">
                <button type="button" class="image-arrow left" style="display:${images.length > 1 ? 'block' : 'none'};position:absolute;left:10px;top:50%;transform:translateY(-50%);z-index:2;">&#8592;</button>
                <button type="button" class="image-arrow right" style="display:${images.length > 1 ? 'block' : 'none'};position:absolute;right:10px;top:50%;transform:translateY(-50%);z-index:2;">&#8594;</button>
            </div>
        `;

        const mainImg = imgContainer.querySelector('#modalMainImage');
        const leftBtn = imgContainer.querySelector('.image-arrow.left');
        const rightBtn = imgContainer.querySelector('.image-arrow.right');

        function showImage(idx) {
            currentIndex = (idx + images.length) % images.length;
            mainImg.src = '../uploads/' + images[currentIndex];
        }

        if (leftBtn) {
            leftBtn.onclick = function(e) {
                e.stopPropagation();
                showImage(currentIndex - 1);
            };
        }
        if (rightBtn) {
            rightBtn.onclick = function(e) {
                e.stopPropagation();
                showImage(currentIndex + 1);
            };
        }
    }
    </script>
</body>
</html>