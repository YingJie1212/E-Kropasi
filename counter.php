<?php
session_start();
require_once "../classes/Product.php";
require_once "../classes/Package.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/ProductPackage.php";
require_once "../classes/CounterCart.php";
require_once "../classes/Category.php";
require_once "../classes/User.php";
require_once "../classes/OrderManager.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

$productObj = new Product();
$packageObj = new Package();
$optionGroupObj = new ProductOptionGroup();
$optionValueObj = new ProductOptionValue();
$productPackageObj = new ProductPackage();
$cartObj = new Cart();
$products = $productObj->getAll();
$packages = $packageObj->getAllPackages();
$cartItems = $cartObj->getItemsByUser($admin_id);
$cartItemIds = array_map(fn($item) => $item['id'], $cartItems);

$categoryObj = new Category();
$categories = $categoryObj->getAll();

$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[$cat['id']] = $cat['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = new Cart();
    $admin_id = $_SESSION['admin_id'];
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $item_type = $_POST['item_type'] ?? 'product';
    $quantity = max(1, min(10, (int) ($_POST['quantity'] ?? 1)));
    $selected_options = $_POST['options'] ?? [];

    $product = $productObj->getById($item_id);
    if ($item_type === 'product' && $quantity > $product['quantity']) {
        // Show error or set $quantity = $product['quantity'];
    }

$success = $cart->addItem($admin_id, $item_id, $item_type, $quantity, $selected_options);

if ($success) {
    // Optionally, play a sound or set a success flag for JS
    echo "<script>
        if(window.shippingSound){ shippingSound.play(); }
        // Optionally show a toast or alert here
    </script>";
    // Reload the page to clear POST and update cart count (optional)
    header("Location: counter.php?added=1");
    exit;
} else {
    echo "Failed to add to cart.";
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $item_type = $_POST['item_type'];
    $item_id = intval($_POST['item_id']);
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $selected_options = $_POST['options'] ?? [];

    $cart_item = [
        'type' => $item_type,
        'id' => $item_id,
        'quantity' => $quantity,
        'options' => $selected_options,
    ];

    $_SESSION['cart'][] = $cart_item;
    header("Location: counter.php");
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMJK PHOR TAY STATIONARI STORE</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        /* Soft Pastel Professional Color Scheme */
        --primary-color: #9DB4C0; /* Soft slate blue */
        --secondary-color: #E2E8F0; /* Very light gray-blue */
        --accent-color: #A5C4D4; /* Soft blue-gray */
        --light-color: #F5F7FA; /* Off-white background */
        --lighter-color: #FFFFFF; /* Pure white */
        --text-color: #4A5568; /* Dark gray-blue for text */
        --light-text: #718096; /* Medium gray for secondary text */
        --border-color: #CBD5E0; /* Light gray border */
        --success-color: #90C8AC; /* Soft green */
        --warning-color: #F0D78C; /* Soft yellow */
        --danger-color: #F4A7B9; /* Soft pink */
        --shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--light-color);
        color: var(--text-color);
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
        font-size: 14px;
    }

    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    /* Header Styles */
    .page-header {
        background-color: var(--lighter-color);
        box-shadow: var(--shadow);
        padding: 12px 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo {
        height: 50px;
        width: auto;
    }

    .page-title {
        color: var(--primary-color);
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .header-btn {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        text-decoration: none;
        font-size: 0.9rem;
    }

    .header-btn:hover {
        background-color: #8CA7B3;
        transform: translateY(-1px);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    }

    .header-btn.logout-btn {
        background-color: var(--danger-color);
    }

    .header-btn.logout-btn:hover {
        background-color: #E395A8;
    }

    /* Main Content Layout */
    .content-wrapper {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }

    /* Sidebar Styles */
    .sidebar {
        width: 220px;
        background-color: var(--lighter-color);
        border-radius: 6px;
        box-shadow: var(--shadow);
        padding: 15px;
        height: fit-content;
        position: sticky;
        top: 80px;
    }

    .sidebar-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }

    .filter-group {
        margin-bottom: 15px;
    }

    .filter-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
        font-size: 0.95rem;
    }

    .filter-checkbox {
        margin-right: 8px;
        accent-color: var(--primary-color);
        transform: scale(1.1);
    }

    /* Main Content Area */
    .main-content {
        flex: 1;
    }

    /* Search Bar */
    .search-container {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }

    .search-input {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 0.95rem;
        transition: var(--transition);
        background-color: var(--lighter-color);
        height: 40px;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(157, 180, 192, 0.2);
    }

    .search-button {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 0 20px;
        border-radius: 4px;
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.95rem;
    }

    .search-button:hover {
        background-color: #8CA7B3;
    }

    /* Product Grid */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
    }

    .product-card {
        background: var(--lighter-color);
        border-radius: 6px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: var(--transition);
        border: 1px solid var(--border-color);
    }

    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .product-image-container {
        position: relative;
        width: 100%;
        height: 180px;
        overflow: hidden;
        background: #f8f9fa;
    }

    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .product-card:hover .product-image {
        transform: scale(1.03);
    }

    .image-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(to bottom, rgba(0,0,0,0.05), rgba(0,0,0,0.1));
        display: flex;
        align-items: flex-end;
        padding: 10px;
    }

    .product-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: var(--success-color);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .product-content {
        padding: 12px;
    }

    .product-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-description {
        font-size: 0.85rem;
        color: var(--light-text);
        margin-bottom: 10px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 2.4em;
    }

    .product-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success-color);
        margin-bottom: 10px;
    }

    .add-to-cart-btn {
        width: 100%;
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 8px;
        font-size: 0.95rem;
        border-radius: 4px;
        cursor: pointer;
        transition: var(--transition);
        font-weight: 500;
    }

    .add-to-cart-btn:hover {
        background-color: #8CA7B3;
        transform: translateY(-1px);
    }

    /* Package Contents */
    .package-contents {
        margin: 10px 0;
    }

    .contents-title {
        font-weight: 500;
        margin-bottom: 8px;
        color: var(--text-color);
        font-size: 0.95rem;
    }

    .contents-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .content-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 0.85rem;
    }

    .content-item:last-child {
        border-bottom: none;
    }

    .content-image {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 10px;
    }

    .content-name {
        flex: 1;
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .content-quantity {
        font-weight: 500;
        color: var(--success-color);
        font-size: 0.85rem;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(2px);
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: var(--lighter-color);
        border-radius: 6px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .modal-header {
        padding: 15px 20px;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
    }

    .close-modal {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        transition: var(--transition);
    }

    .close-modal:hover {
        opacity: 0.8;
    }

    .modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }

    .option-group {
        margin-bottom: 15px;
    }

    .option-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
        font-size: 0.95rem;
    }

    .option-select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        font-size: 0.95rem;
        transition: var(--transition);
        background-color: var(--lighter-color);
    }

    .option-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 2px rgba(157, 180, 192, 0.2);
    }

    .quantity-control {
        display: flex;
        align-items: center;
        margin: 15px 0;
    }

    .quantity-label {
        margin-right: 15px;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .quantity-input {
        width: 70px;
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        text-align: center;
        font-size: 0.95rem;
        background-color: var(--lighter-color);
    }

    .modal-footer {
        padding: 15px 20px;
        background-color: #f8f9fa;
        display: flex;
        justify-content: flex-end;
    }

    /* Image Gallery Modal */
    #imageModal .modal-content {
        max-width: 700px;
        width: 95%;
        padding: 0;
        background: transparent;
        box-shadow: none;
    }

    #modalImages {
        position: relative;
        height: 400px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #modalImages img {
        max-width: 100%;
        max-height: 100%;
        border-radius: 6px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
    }

    .image-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background-color: rgba(255, 255, 255, 0.9);
        border: none;
        color: var(--text-color);
        font-size: 24px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        z-index: 10;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    }

    .image-arrow:hover {
        background-color: white;
        transform: translateY(-50%) scale(1.05);
    }

    .image-arrow.left {
        left: 10px;
    }

    .image-arrow.right {
        right: 10px;
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
        .content-wrapper {
            flex-direction: column;
        }
        
        .sidebar {
            width: 100%;
            position: static;
            margin-bottom: 20px;
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .page-header {
            padding: 10px 0;
        }
        
        .header-content {
            flex-direction: column;
            gap: 10px;
        }
        
        .logo-container {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            width: 100%;
            justify-content: center;
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        
        .sidebar-toggle {
            display: block;
            position: fixed;
            left: 10px;
            top: 10px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
            box-shadow: var(--shadow);
        }
        
        .sidebar {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100%;
            padding: 50px 15px 15px;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 1px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.active {
            display: block;
        }
    }

    @media (max-width: 576px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }
        
        .product-image-container {
            height: 150px;
        }
        
        .search-container {
            flex-direction: column;
        }
        
        .search-button {
            padding: 10px;
            justify-content: center;
        }
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--light-color);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #8CA7B3;
    }

    /* Animation for cart addition */
    @keyframes cartPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    .cart-pulse {
        animation: cartPulse 0.5s ease;
    }

    /* Smooth transitions for all interactive elements */
    button, input[type="checkbox"], input[type="radio"], .product-card, .modal-content {
        transition: var(--transition);
    }

    /* Enhanced focus states for accessibility */
    button:focus-visible, input:focus-visible, select:focus-visible {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    /* Larger section headings */
    h2 {
        font-size: 1.4rem;
        margin: 20px 0 15px 0;
    }
    </style>
</head>
<body>

    <header class="page-header">
        <div class="container header-content">
            <div class="logo-container">
                <a href="counter.php">
                  
                </a>
                <h1 class="page-title">SMJK PHOR TAY</h1>
            </div>
            <div class="header-actions">
                 <a href="dashboard.php" class="header-btn dashboard-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5ZM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5 5 5Z"/>
                    </svg>
                    Dashboard
                </a>
              <a href="counter_checkout.php" class="header-btn" id="goToCheckoutBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                    </svg>
                    View Checkout
                </a>
            
                <a href="logout.php" class="header-btn logout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                        <path d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </header>
    <div class="container">
        <div class="content-wrapper">
            <aside class="sidebar">
                <h3 class="sidebar-title">Filter Products</h3>
                <form id="categoryFilter">
                    <div class="filter-group">
                        <label class="filter-label">Categories</label>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.95rem;">
                                <input type="checkbox" class="filter-checkbox" value="all" checked>
                                All Categories
                            </label>
                            <?php foreach ($categories as $cat): ?>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.95rem;">
                                <input type="checkbox" class="filter-checkbox category-checkbox" value="<?= htmlspecialchars($cat['name']) ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </label>
                            <?php endforeach; ?>
                            <label style="display: block; margin-bottom: 8px; font-size: 0.95rem;">
                                <input type="checkbox" class="filter-checkbox category-checkbox" value="Packages">
                                Packages
                            </label>
                        </div>
                    </div>
                </form>
            </aside>
            <main class="main-content">
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search products or packages...">
                    <button class="search-button" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.414 1.414l3.182 3.182a.5.5 0 0 0 .708-.708l-3.182-3.182zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </button>
                </div>
                <h2 style="margin-bottom: 15px; color: var(--primary-color);">Packages</h2>
                <div class="product-grid">
                    <?php foreach ($packages as $package): ?>
                        <?php
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
                            // Get first image from product_images table for this
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
                            <div class="product-image-container">
                                <img src="../uploads/<?= htmlspecialchars($mainImage) ?>"
                                    alt="<?= htmlspecialchars($package['name']) ?>"
                                    class="product-image package-image-click"
                                    data-images='<?= json_encode($imageArray) ?>'>
                                <div class="image-overlay">
                                    <span class="product-badge">Package</span>
                                </div>
                            </div>
                            <div class="product-content">
                                <h3 class="product-name"><?= htmlspecialchars($package['name']) ?></h3>
                                <div class="package-contents">
                                    <strong class="contents-title">Includes:</strong>
                                    <ul class="contents-list">
                                        <?php
                                        $contents = $productPackageObj->getByPackageId($package['id']);
                                        $popupContents = [];
                                        foreach ($contents as $item) {
                                            $prod = $productObj->getById($item['product_id']);
                                            // Get first image from product_images table for this product
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
                                        foreach (array_slice($contents, 0, 3) as $item):
                                            $prod = $productObj->getById($item['product_id']);
                                            // Get first image for this product
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
                                <div class="product-price">RM<?= number_format($package['price'], 2) ?></div>
                                <button class="add-to-cart-btn"
                                    data-type="package"
                                    data-id="<?= $package['id'] ?>"
                                    data-name="<?= htmlspecialchars($package['name']) ?>"
                                    data-price="<?= $package['price'] ?>"
                                    data-options='<?= json_encode($productsInPackage) ?>'
                                    data-contents='<?= json_encode($popupContents) ?>'
                                    data-description="<?= htmlspecialchars($package['description'], ENT_QUOTES) ?>"
                                >Select</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <h2 style="margin:20px 0 15px 0; color: var(--primary-color);">Products</h2>
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
                        <div class="product-image-container">
                            <img src="../uploads/<?= htmlspecialchars($imageArray[0]) ?>"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                                class="product-image product-image-click"
                                data-images='<?= json_encode($imageArray) ?>'>
                            <div class="image-overlay">
                                <span class="product-badge">In Stock</span>
                            </div>
                        </div>
                        <div class="product-content">
                            <h3 class="product-name"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                            <div class="product-price">RM<?= number_format($product['price'], 2) ?></div>
                            <div class="product-stock" style="color: var(--success-color); font-size: 0.85rem; margin-bottom: 8px;">
                                In Stock: <?= (int)$product['quantity'] ?>
                            </div>
                            <button class="add-to-cart-btn" 
                                data-type="product"
                                data-id="<?= $product['id'] ?>"
                                data-name="<?= htmlspecialchars($product['name']) ?>"
                                data-price="<?= $product['price'] ?>"
                                data-options='<?= json_encode($optionGroups) ?>'
                                data-description="<?= htmlspecialchars($product['description'], ENT_QUOTES) ?>"
                                data-images='<?= json_encode($imageArray) ?>'
                                data-quantity="<?= (int)$product['quantity'] ?>" 
                            >Select</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
    <!-- Option Selection Modal -->
    <div id="optionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle"></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="modalForm" method="POST" action="counter.php">
                    <input type="hidden" name="item_type" id="modal_item_type">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <div id="modalProductImages" style="margin-bottom: 15px;"></div>
                    <div id="modalDescription" style="font-size: 0.85rem; color: var(--light-text); margin-bottom: 10px;"></div>
                    <div id="modalContents" class="package-contents" style="display: none;"></div>
                    <div id="modalOptions"></div>
                    <div class="quantity-control">
                        <span class="quantity-label">Quantity:</span>
                        <input type="number" class="quantity-input" name="quantity" value="1" min="1" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_to_cart" class="add-to-cart-btn"> Add to Cart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Image Gallery Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div id="modalImages"></div>
            <button class="image-arrow left" style="display:none;position:absolute;left:10px;top:50%;transform:translateY(-50%);z-index:2;">&#8592;</button>
            <button class="image-arrow right" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);z-index:2;">&#8594;</button>
        </div>
    </div>
    <audio id="shippingSound" src="sound/correct-choice-43861.mp3" preload="auto"></audio>

    <script>
        // Mobile sidebar toggle
        const sidebarToggle = document.createElement('button');
        sidebarToggle.className = 'sidebar-toggle';
        sidebarToggle.innerHTML = '☰ Filters';
        document.body.appendChild(sidebarToggle);

        sidebarToggle.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('active') && 
                !e.target.closest('.sidebar') && 
                !e.target.closest('.sidebar-toggle')) {
                sidebar.classList.remove('active');
            }
        });

        // Modal close logic
        const modals = document.querySelectorAll('.modal');
        const closeModalButtons = document.querySelectorAll('.close-modal');
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
                        qtyInput.closest('.quantity-control').insertAdjacentHTML('beforeend', '<span style="color:var(--danger-color);margin-left:10px;font-size:0.85rem;">Out of stock</span>');
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
            const modalImages = document.getElementById('modalImages');
            const imageModal = document.getElementById('imageModal');
            const leftArrow = document.querySelector('.image-arrow.left');
            const rightArrow = document.querySelector('.image-arrow.right');
            
            function renderImage() {
                modalImages.innerHTML = '';
                const imgElement = document.createElement('img');
                imgElement.src = '../uploads/' + images[currentIndex];
                imgElement.alt = 'Product Image';
                modalImages.appendChild(imgElement);
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
                const name = card.querySelector('.product-name')?.textContent.toLowerCase() || '';
                const desc = card.querySelector('.product-description')?.textContent.toLowerCase() || '';
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
        
        // Add this function after your modal open logic
        function setupModalImageGallery(images) {
            let currentIndex = 0;
            const imgContainer = document.getElementById('modalProductImages');
            imgContainer.innerHTML = `
                <div style="position:relative;">
                    <img id="modalMainImage" src="../uploads/${images[0]}" alt="" style="width:100%; max-height:180px; object-fit:contain; border-radius:4px;">
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

        document.getElementById('goToCheckoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('checkoutForm').submit();
        });
    </script>

    <form id="checkoutForm" method="POST" action="counter_checkout.php" style="display:none;">
    <?php foreach ($cartItemIds as $id): ?>
        <input type="hidden" name="selected_items[]" value="<?= $id ?>">
    <?php endforeach; ?>
    </form>
</body>
</html>