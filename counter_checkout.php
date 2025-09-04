<?php
session_start();
require_once "../classes/CounterCart.php";
require_once "../classes/BankAccount.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$cart = new Cart();
$bankAccountObj = new BankAccount($cart->getPDO());
$bankInfo = $bankAccountObj->getLatest();

// --- Add this block to handle removal from cart ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $removeId = (int) $_POST['remove_id'];
    $cart->removeItem($removeId, $admin_id); // Remove from cart in DB
    // Remove from selected items array for this request
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $_POST['selected_items'] = array_filter($_POST['selected_items'], fn($id) => (int)$id !== $removeId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    require_once "../classes/Product.php";
    $productObj = new Product();
    $cartItem = $cart->getItemById($edit_id, $admin_id);
    $maxQty = 10;
    if ($cartItem && $cartItem['item_type'] === 'product') {
        $prod = $productObj->getById($cartItem['item_id']);
        $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
    }
    $quantity = isset($_POST['quantity'][$edit_id]) ? max(1, min($maxQty, (int)$_POST['quantity'][$edit_id])) : 1;
    $options = isset($_POST['options'][$edit_id]) ? $_POST['options'][$edit_id] : [];
    $cart->updateItem($edit_id, $admin_id, $options, $quantity);
    $_POST['selected_items'] = $_POST['selected_items'] ?? [$edit_id];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_items'])) {
    // Improved empty state
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Checkout | GreenLeaf Market</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            :root {
                --primary-color: #9DB4C0;
                --secondary-color: #E2E8F0;
                --light-color: #F5F7FA;
                --lighter-color: #FFFFFF;
                --text-color: #4A5568;
                --light-text: #718096;
                --border-color: #CBD5E0;
                --success-color: #90C8AC;
                --danger-color: #F4A7B9;
                --radius: 8px;
                --radius-sm: 4px;
                --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-color);
                background-color: var(--light-color);
                padding: 0;
                margin: 0;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                min-height: 80vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                background-color: var(--lighter-color);
                border-radius: var(--radius);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                max-width: 600px;
                margin: 0 auto;
            }
            
            .empty-icon {
                font-size: 64px;
                color: var(--primary-color);
                margin-bottom: 20px;
            }
            
            .empty-title {
                font-size: 24px;
                color: var(--text-color);
                margin-bottom: 15px;
                font-weight: 600;
            }
            
            .empty-message {
                font-size: 16px;
                color: var(--light-text);
                margin-bottom: 30px;
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .btn {
                padding: 12px 24px;
                border-radius: var(--radius-sm);
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: var(--transition);
                border: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
            }
            
            .btn-primary {
                background-color: var(--primary-color);
                color: var(--lighter-color);
            }
            
            .btn-primary:hover {
                background-color: #8CA7B3;
                transform: translateY(-2px);
            }
            
            .btn-outline {
                background-color: transparent;
                border: 1px solid var(--primary-color);
                color: var(--primary-color);
                margin-left: 15px;
            }
            
            .btn-outline:hover {
                background-color: rgba(157, 180, 192, 0.1);
                transform: translateY(-2px);
            }
            
            @media (max-width: 576px) {
                .empty-title {
                    font-size: 20px;
                }
                
                .empty-message {
                    font-size: 15px;
                }
                
                .btn {
                    width: 100%;
                    justify-content: center;
                    margin-bottom: 10px;
                }
                
                .btn-outline {
                    margin-left: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="empty-title">Your Checkout is Empty</h2>
                <p class="empty-message">
                    You haven't selected any items for checkout. Please return to the counter and add items before proceeding.
                </p>
                <div>
                    <a href="counter.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Return to Counter
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$selectedItemIds = array_map('intval', $_POST['selected_items']);
$allItems = $cart->getItemsByUser($admin_id);

if (isset($_POST['remove_id'])) {
    $removeId = (int) $_POST['remove_id'];
    $selectedItemIds = array_filter($selectedItemIds, fn($id) => $id !== $removeId);
}

$checkoutItems = array_filter($allItems, fn($item) => in_array($item['id'], $selectedItemIds));

if (empty($checkoutItems)) {
    // Improved empty state after removing all items
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Checkout | GreenLeaf Market</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            :root {
                --primary-color: #9DB4C0;
                --secondary-color: #E2E8F0;
                --light-color: #F5F7FA;
                --lighter-color: #FFFFFF;
                --text-color: #4A5568;
                --light-text: #718096;
                --border-color: #CBD5E0;
                --success-color: #90C8AC;
                --danger-color: #F4A7B9;
                --radius: 8px;
                --radius-sm: 4px;
                --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-color);
                background-color: var(--light-color);
                padding: 0;
                margin: 0;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
                min-height: 80vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                background-color: var(--lighter-color);
                border-radius: var(--radius);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                max-width: 600px;
                margin: 0 auto;
            }
            
            .empty-icon {
                font-size: 64px;
                color: var(--danger-color);
                margin-bottom: 20px;
            }
            
            .empty-title {
                font-size: 24px;
                color: var(--text-color);
                margin-bottom: 15px;
                font-weight: 600;
            }
            
            .empty-message {
                font-size: 16px;
                color: var(--light-text);
                margin-bottom: 30px;
                max-width: 500px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .btn {
                padding: 12px 24px;
                border-radius: var(--radius-sm);
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: var(--transition);
                border: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
            }
            
            .btn-primary {
                background-color: var(--primary-color);
                color: var(--lighter-color);
            }
            
            .btn-primary:hover {
                background-color: #8CA7B3;
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h2 class="empty-title">No Items Left in Checkout</h2>
                <p class="empty-message">
                    All items have been removed from your checkout. Please return to counter and add new items to proceed.
                </p>
                <div>
                    <a href="counter.php" class="btn btn-primary">
                        <i class="fas fa-shopping-basket"></i> Return to Counter
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$total = 0;
foreach ($checkoutItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Initialize coupon discount from POST if available
$couponDiscount = isset($_POST['coupon_discount']) ? (float)$_POST['coupon_discount'] : 0;
$couponCode = isset($_POST['coupon_code']) ? $_POST['coupon_code'] : '';

// Get only active payment methods
$paymentMethods = [];
$stmt = $cart->getPDO()->query("SELECT * FROM counter_payment_methods WHERE is_active = 1");
if ($stmt) $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get only active delivery options
$deliveryOptions = [];
$stmt = $cart->getPDO()->query("SELECT * FROM counter_delivery_options WHERE is_active = 1");
if ($stmt) $deliveryOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set default payment method (first active one)
$defaultPaymentMethod = !empty($paymentMethods) ? $paymentMethods[0]['method_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | GreenLeaf Market</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
    --radius: 8px;
    --radius-sm: 4px;
    --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
    color: var(--text-color);
    background-color: var(--light-color);
    padding: 0;
    margin: 0;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Header Section */
.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background-color: var(--lighter-color);
    padding: 15px 20px;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.checkout-title {
    font-size: 28px;
    color: var(--text-color);
    font-weight: 600;
    position: relative;
    padding-bottom: 10px;
}

.checkout-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 80px;
    height: 3px;
    background-color: var(--primary-color);
}

.back-link {
    display: inline-flex;
    align-items: center;
    color: var(--light-text);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    padding: 8px 12px;
    border-radius: var(--radius-sm);
}

.back-link:hover {
    color: var(--primary-color);
    background-color: rgba(157, 180, 192, 0.1);
}

.back-link i {
    margin-right: 8px;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--lighter-color);
    box-shadow: var(--shadow);
    border-radius: var(--radius);
    overflow: hidden;
}

.items-table thead {
    background-color: var(--primary-color);
    color: var(--lighter-color);
}

.items-table th {
    padding: 16px;
    text-align: left;
    font-weight: 600;
}

.items-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}

.items-table tr:last-child td {
    border-bottom: none;
}

.items-table tr:nth-child(even) {
    background-color: var(--light-color);
}

/* Product Info */
.product-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.product-image {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-color);
}

.product-name {
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 5px;
}

.product-type {
    font-size: 13px;
    color: var(--light-text);
    background-color: var(--secondary-color);
    padding: 2px 8px;
    border-radius: 20px;
    display: inline-block;
}

.product-stock {
    font-size: 13px;
    color: var(--success-color);
    margin-top: 4px;
}

/* Options & Quantity */
.options-qty-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.option-group {
    margin-bottom: 8px;
}

.option-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
    color: var(--text-color);
}

.option-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    font-size: 14px;
    background-color: var(--lighter-color);
    transition: var(--transition);
}

.option-select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(157, 180, 192, 0.2);
}

.quantity-control {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quantity-input {
    width: 70px;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    text-align: center;
    font-size: 14px;
    transition: var(--transition);
    background-color: var(--lighter-color);
}

.quantity-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(157, 180, 192, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: var(--lighter-color);
}

.btn-primary:hover {
    background-color: #8CA7B3;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline:hover {
    background-color: rgba(157, 180, 192, 0.1);
}

.btn-danger {
    background-color: var(--danger-color);
    color: var(--lighter-color);
}

.btn-danger:hover {
    background-color: #E395A8;
}

/* Summary Card */
.summary-card {
    background-color: var(--lighter-color);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 24px;
    position: sticky;
    top: 20px;
}

.summary-title {
    font-size: 20px;
    color: var(--text-color);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 15px;
}

.summary-label {
    color: var(--light-text);
}

.summary-value {
    font-weight: 500;
}

.summary-total {
    font-size: 18px;
    font-weight: 600;
    color: var(--text-color);
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
}

.checkout-btn {
    width: 100%;
    padding: 14px;
    background-color: var(--primary-color);
    color: var(--lighter-color);
    border: none;
    border-radius: var(--radius-sm);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 20px;
    transition: var(--transition);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.checkout-btn:hover {
    background-color: #8CA7B3;
}

/* Payment & Delivery Options */
.options-section {
    margin-top: 30px;
    background-color: var(--lighter-color);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 24px;
}

.section-title {
    font-size: 18px;
    color: var(--text-color);
    margin-bottom: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary-color);
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.radio-option {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.radio-option:hover {
    border-color: var(--primary-color);
}

.radio-option input {
    margin-right: 12px;
}

.option-label {
    flex: 1;
    font-size: 15px;
}

.option-fee {
    color: var(--light-text);
    font-weight: 500;
}

/* Upload Sections */
.upload-section {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background-color: var(--light-color);
    border-radius: var(--radius-sm);
    border: 1px dashed var(--primary-color);
}

.upload-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--text-color);
}

.bank-info {
    background-color: var(--lighter-color);
    padding: 15px;
    border-radius: var(--radius-sm);
    margin-bottom: 15px;
    font-size: 14px;
}

.bank-info div {
    margin-bottom: 8px;
}

.bank-info strong {
    color: var(--text-color);
}

.file-input {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    font-size: 14px;
    margin-bottom: 8px;
    background-color: var(--lighter-color);
}

.upload-note {
    font-size: 13px;
    color: var(--light-text);
}

/* QR Code */
.qr-container {
    text-align: center;
    margin: 15px 0;
}

.qr-img {
    max-width: 180px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    padding: 10px;
    background-color: var(--lighter-color);
}

/* Coupon Section */
.coupon-section {
    margin: 20px 0;
    padding: 15px;
    background-color: var(--lighter-color);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.coupon-label {
    font-weight: 600;
    margin-right: 10px;
    color: var(--text-color);
}

.coupon-input {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    margin-right: 10px;
    width: 200px;
    background-color: var(--lighter-color);
}

.coupon-message {
    margin-left: 15px;
    color: var(--danger-color);
    font-size: 14px;
}

.coupon-success {
    color: var(--success-color) !important;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .product-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .items-table th, 
    .items-table td {
        padding: 12px;
        font-size: 14px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .header-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .checkout-title {
        font-size: 24px;
    }

    .items-table {
        display: block;
        overflow-x: auto;
    }

    .options-qty-container {
        gap: 8px;
    }
    
    .coupon-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .coupon-input {
        width: 100%;
        margin-right: 0;
    }
}
    </style>
</head>
<body>
    <main class="container">
        <div class="header-section">
            <h1 class="checkout-title">Checkout</h1>
            <a href="counter.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Counter
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="checkout-form">
            <input type="hidden" name="edit_id" id="edit_id_input" value="">
            <?php foreach ($selectedItemIds as $id): ?>
                <input type="hidden" name="selected_items[]" value="<?= $id ?>">
            <?php endforeach; ?>
            <input type="hidden" id="coupon_discount" name="coupon_discount" value="<?= $couponDiscount ?>">

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Options & Quantity</th>
                        <th>Subtotal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checkoutItems as $item): ?>
                    <tr data-item-id="<?= $item['id'] ?>">
                        <td>
                            <div class="product-info">
                                <?php
                                $productImage = 'default.jpg';
                                if ($item['item_type'] === 'product') {
                                    require_once "../classes/Product.php";
                                    $productObj = new Product();
                                    $images = method_exists($productObj, 'getImagesByProductId') ? $productObj->getImagesByProductId($item['item_id']) : [];
                                    if (!empty($images) && !empty($images[0]['image'])) $productImage = $images[0]['image'];
                                } elseif ($item['item_type'] === 'package') {
                                    require_once "../classes/Package.php";
                                    $packageObj = new Package();
                                    $package = $packageObj->getById($item['item_id']);
                                    if (!empty($package['image'])) $productImage = $package['image'];
                                }
                                ?>
                                <img src="../uploads/<?= htmlspecialchars($productImage) ?>" class="product-image">
                                <div>
                                    <div class="product-name">
                                        <?= htmlspecialchars($item['display_name']) ?>
                                        <span class="product-type"><?= $item['item_type'] ?></span>
                                    </div>
                                    <?php if ($item['item_type'] === 'product'): ?>
                                    <div class="product-stock">
                                        <i class="fas fa-box-open"></i> In Stock: 
                                        <?php
                                            $productObj = new Product();
                                            $prod = $productObj->getById($item['item_id']);
                                            echo isset($prod['quantity']) ? (int)$prod['quantity'] : 0;
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="options-qty-container">
                                <?php
                                $selected = $item['selected_options'] ?? [];
                                if ($item['item_type'] === 'product') {
                                    $optionGroups = $cart->getOptionGroupsWithValues($item['item_id']);
                                    foreach ($optionGroups as $group):
                                        $groupName = $group['group_name'];
                                        $selectedId = $selected[$groupName] ?? null;
                                ?>
                                    <div class="option-group">
                                        <label class="option-label"><?= htmlspecialchars($groupName) ?></label>
                                        <select class="option-select editable-field" name="options[<?= $item['id'] ?>][<?= htmlspecialchars($groupName) ?>]" disabled>
                                            <?php foreach ($group['options'] as $opt): ?>
                                                <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $selectedId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($opt['value']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach;
                                } elseif ($item['item_type'] === 'package') {
                                    $packageOptions = $cart->getOptionsForPackage($item['item_id']);
                                    foreach ($packageOptions as $product_id => $product):
                                ?>
                                    <div style="margin-bottom: 12px;">
                                        <div style="font-weight: 500; margin-bottom: 6px; color: var(--text);"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                            <?php foreach ($product['option_groups'] as $group):
                                                $groupName = $group['group_name'];
                                                $selectedId = $selected[$product_id][$groupName] ?? null;
                                            ?>
                                                <div class="option-group" style="min-width: 150px;">
                                                    <label class="option-label"><?= htmlspecialchars($groupName) ?></label>
                                                    <select class="option-select editable-field" name="options[<?= $item['id'] ?>][<?= $product_id ?>][<?= htmlspecialchars($groupName) ?>]" disabled>
                                                        <?php foreach ($group['options'] as $opt): ?>
                                                            <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $selectedId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($opt['value']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; } ?>
                                <div class="quantity-control">
                                    <label class="option-label">Quantity:</label>
                                    <?php
                                    $maxQty = 10;
                                    if ($item['item_type'] === 'product') {
                                        $productObj = new Product();
                                        $prod = $productObj->getById($item['item_id']);
                                        $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
                                    }
                                    ?>
                                    <input type="number" class="quantity-input editable-field" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="1" max="<?= $maxQty ?>" readonly>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 500; color: var(--text-dark);">
                                RM<?= number_format($item['price'] * $item['quantity'], 2) ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-outline btn-sm edit-btn" data-item-id="<?= $item['id'] ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm save-btn" style="display:none;" data-item-id="<?= $item['id'] ?>">
                                    <i class="fas fa-save"></i> Save
                                </button>
                                <button type="submit" name="remove_id" value="<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this item from checkout?');">
                                    <i class="fas fa-trash-alt"></i> Remove
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="coupon-section">
                <label for="coupon_code" class="coupon-label">Coupon Code:</label>
                <input type="text" id="coupon_code" name="coupon_code" placeholder="Enter coupon code (e.g. RM20, 10%)" class="coupon-input" value="<?= htmlspecialchars($couponCode) ?>">
                <button type="button" class="btn btn-primary" id="apply-coupon-btn">Apply</button>
                <span id="coupon-message" class="coupon-message <?= $couponDiscount > 0 ? 'coupon-success' : '' ?>">
                    <?php if ($couponDiscount > 0): ?>
                        Coupon applied: -RM<?= number_format($couponDiscount, 2) ?>
                    <?php endif; ?>
                </span>
            </div>

            <?php if (!empty($paymentMethods)): ?>
            <div class="options-section">
                <h3 class="section-title">
                    <i class="fas fa-credit-card"></i> Payment Method
                </h3>
                <div class="radio-group">
                    <?php foreach ($paymentMethods as $index => $method): ?>
                    <label class="radio-option">
                        <input type="radio" name="payment_method" value="<?= htmlspecialchars($method['method_name']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                        <span class="option-label">
                            <?= htmlspecialchars(ucfirst($method['method_name'])) ?>
                            <?php if ($method['method_name'] === 'ewallet' && !empty($method['image'])): ?>
                                (Scan QR to pay)
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($method['fee'])): ?>
                            <span class="option-fee">+RM<?= number_format($method['fee'], 2) ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div id="bank-slip-upload" class="upload-section" style="<?= $defaultPaymentMethod === 'bank-in' ? 'display:block;' : 'display:none;' ?>">
                    <h4 class="upload-title">
                        <i class="fas fa-file-upload"></i> Upload Bank Transfer Slip
                    </h4>
                    <div class="bank-info">
                        <?php if ($bankInfo): ?>
                            <div><strong><i class="fas fa-university"></i> Bank Name:</strong> <?= htmlspecialchars($bankInfo['bank_name']) ?></div>
                            <div><strong><i class="fas fa-hashtag"></i> Account Number:</strong> <?= htmlspecialchars($bankInfo['account_number']) ?></div>
                            <div><strong><i class="fas fa-user"></i> Account Holder:</strong> <?= htmlspecialchars($bankInfo['account_holder']) ?></div>
                            <?php if (!empty($bankInfo['school_email'])): ?>
                                <div><strong><i class="fas fa-envelope"></i> School Email:</strong> <?= htmlspecialchars($bankInfo['school_email']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <em>Bank information not available.</em>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="bank_slip" class="file-input" accept="image/*,.pdf">
                    <div class="upload-note">
                        <i class="fas fa-info-circle"></i> Accepted formats: JPG, PNG, PDF. Max size: 2MB.
                    </div>
                </div>

                <div id="ewallet-upload" class="upload-section" style="<?= $defaultPaymentMethod === 'ewallet' ? 'display:block;' : 'display:none;' ?>">
                    <h4 class="upload-title">
                        <i class="fas fa-mobile-alt"></i> Upload E-wallet Payment
                    </h4>
                    <div id="ewallet-qr-container" class="qr-container" style="display:none;">
                        <img id="ewallet-qr-img" src="" alt="E-wallet QR" class="qr-img">
                        <div class="upload-note" style="margin-top: 10px;">
                            Scan this QR code to make payment
                        </div>
                    </div>
                    <input type="file" name="ewallet_slip" class="file-input" accept="image/*,.pdf">
                    <div class="upload-note">
                        <i class="fas fa-info-circle"></i> Upload screenshot of your payment confirmation. Accepted formats: JPG, PNG, PDF. Max size: 2MB.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($deliveryOptions)): ?>
            <div class="options-section">
                <h3 class="section-title">
                    <i class="fas fa-truck"></i> Delivery Option
                </h3>
                <div class="radio-group">
                    <?php foreach ($deliveryOptions as $option): ?>
                    <label class="radio-option">
                        <input type="radio" name="delivery_option" value="<?= htmlspecialchars($option['option_name']) ?>" <?= $option['option_name'] === 'pickup' ? 'checked' : '' ?>>
                        <span class="option-label"><?= htmlspecialchars(ucfirst($option['option_name'])) ?></span>
                        <?php if (!empty($option['fee'])): ?>
                            <span class="option-fee">+RM<?= number_format($option['fee'], 2) ?></span>
                        <?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: flex-end; margin-top: 30px;">
                <div class="summary-card" style="width: 300px;">
                    <h3 class="summary-title">Order Summary</h3>
                    <div class="summary-row">
                        <span class="summary-label">Subtotal:</span>
                        <span class="summary-value">RM<?= number_format($total, 2) ?></span>
                    </div>
                    <?php if ($couponDiscount > 0): ?>
                    <div class="summary-row">
                        <span class="summary-label">Coupon Discount:</span>
                        <span class="summary-value" style="color: var(--success-color);">-RM<?= number_format($couponDiscount, 2) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-total" id="totalPrice">
                        Total: RM<?= number_format(max(0, $total - $couponDiscount), 2) ?>
                    </div>
                    <button type="submit" class="checkout-btn" id="place-order-btn">
                        <i class="fas fa-lock"></i> Place Order
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
    const paymentMethods = <?= json_encode($paymentMethods) ?>;
    const deliveryOptions = <?= json_encode($deliveryOptions) ?>;
    const baseTotal = <?= $total ?>;
    let couponDiscount = <?= $couponDiscount ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Payment method toggles
        function togglePayment() {
            const bankSlip = document.querySelector('input[name="bank_slip"]');
            const ewalletSlip = document.querySelector('input[name="ewallet_slip"]');
            const bankInChecked = document.querySelector('input[name="payment_method"][value="bank-in"]')?.checked || false;
            const ewalletChecked = document.querySelector('input[name="payment_method"][value="ewallet"]')?.checked || false;

            // Always clear required first
            if (bankSlip) bankSlip.required = false;
            if (ewalletSlip) ewalletSlip.required = false;

            document.getElementById('bank-slip-upload').style.display = bankInChecked ? 'block' : 'none';
            document.getElementById('ewallet-upload').style.display = ewalletChecked ? 'block' : 'none';

            if (bankInChecked && bankSlip) bankSlip.required = true;
            if (ewalletChecked && ewalletSlip) ewalletSlip.required = true;

            updateEwalletQR();
            updateTotal();
        }

        function updateEwalletQR() {
            const qrContainer = document.getElementById('ewallet-qr-container');
            const qrImg = document.getElementById('ewallet-qr-img');
            if (document.querySelector('input[name="payment_method"][value="ewallet"]')?.checked) {
                const method = paymentMethods.find(m => m.method_name === 'ewallet');
                if (method && method.image) {
                    qrImg.src = '../uploads/' + method.image;
                    qrContainer.style.display = 'block';
                } else {
                    qrContainer.style.display = 'none';
                }
            } else {
                qrContainer.style.display = 'none';
            }
        }

        function parseCoupon(code, total) {
            code = code.trim();
            let discount = 0;
            let message = '';
            let success = false;
            
            // Check for RM format (e.g. RM20, RM100)
            const rmMatch = code.match(/^RM(\d+)$/i);
            if (rmMatch) {
                discount = parseFloat(rmMatch[1]);
                if (discount > 0) {
                    discount = Math.min(discount, total); // Don't allow negative total
                    message = 'Coupon applied: -RM' + discount.toFixed(2);
                    success = true;
                }
            } 
            // Check for percentage format (e.g. 10%, 20%)
            else if (/^\d+%$/.test(code)) {
                let percent = parseFloat(code.replace('%', ''));
                if (percent > 0 && percent <= 100) {
                    discount = total * (percent / 100);
                    message = 'Coupon applied: -' + percent + '% (RM' + discount.toFixed(2) + ')';
                    success = true;
                }
            } 
            // Check for negative RM format (e.g. -RM10)
            else if (/^-RM\d+$/i.test(code)) {
                discount = parseFloat(code.replace(/[^0-9]/g, ''));
                if (discount > 0) {
                    discount = Math.min(discount, total);
                    message = 'Coupon applied: -RM' + discount.toFixed(2);
                    success = true;
                }
            } 
            // Invalid format
            else if (code.length > 0) {
                message = 'Invalid coupon format. Use RM20 or 10%';
            }
            
            return {discount, message, success};
        }

        function updateTotal() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            let fee = 0, deliveryFee = 0;

            if (selectedPayment) {
                const method = paymentMethods.find(m => m.method_name === selectedPayment.value);
                if (method && method.fee) fee = parseFloat(method.fee);
            }
            
            const selectedDelivery = document.querySelector('input[name="delivery_option"]:checked');
            if (selectedDelivery) {
                const option = deliveryOptions.find(o => o.option_name === selectedDelivery.value);
                if (option && option.fee) deliveryFee = parseFloat(option.fee);
            }

            let newTotal = baseTotal + fee + deliveryFee - couponDiscount;
            if (newTotal < 0) newTotal = 0;

            document.getElementById('totalPrice').textContent = 'Total: RM' + newTotal.toFixed(2) +
                (fee > 0 ? ' (incl. RM' + fee.toFixed(2) + ' payment fee)' : '') +
                (deliveryFee > 0 ? ' (incl. RM' + deliveryFee.toFixed(2) + ' delivery fee)' : '') +
                (couponDiscount > 0 ? ' (coupon: -RM' + couponDiscount.toFixed(2) + ')' : '');
        }

        // Event listeners
        document.querySelectorAll('input[name="payment_method"], input[name="delivery_option"]').forEach(radio => {
            radio.addEventListener('change', togglePayment);
        });

        document.getElementById('place-order-btn').addEventListener('click', function(e) {
            document.getElementById('checkout-form').action = 'counter_place_order.php';
            const bankInChecked = document.querySelector('input[name="payment_method"][value="bank-in"]')?.checked || false;
            const ewalletChecked = document.querySelector('input[name="payment_method"][value="ewallet"]')?.checked || false;
            
            if (bankInChecked && !document.querySelector('input[name="bank_slip"]')?.value) {
                alert('Please upload your bank transfer slip before placing the order.');
                e.preventDefault();
                return false;
            }
            if (ewalletChecked && !document.querySelector('input[name="ewallet_slip"]')?.value) {
                alert('Please upload your e-wallet payment screenshot before placing the order.');
                e.preventDefault();
                return false;
            }
        });

        // Edit/Save functionality
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const itemId = btn.dataset.itemId;
                const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
                row.querySelectorAll('.editable-field').forEach(field => {
                    field.disabled = false;
                    field.readOnly = false;
                });
                row.querySelector('.save-btn').style.display = 'inline-block';
                btn.style.display = 'none';
            });
        });

        document.querySelectorAll('.save-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_id_input').value = btn.dataset.itemId;
                btn.form.submit();
            });
        });

        document.getElementById('apply-coupon-btn').addEventListener('click', function() {
            const code = document.getElementById('coupon_code').value;
            const result = parseCoupon(code, baseTotal);
            couponDiscount = result.discount;
            
            const messageEl = document.getElementById('coupon-message');
            messageEl.textContent = result.message;
            
            if (result.success) {
                messageEl.classList.remove('coupon-message');
                messageEl.classList.add('coupon-success');
                document.getElementById('coupon_discount').value = couponDiscount;
            } else {
                messageEl.classList.remove('coupon-success');
                messageEl.classList.add('coupon-message');
                couponDiscount = 0;
                document.getElementById('coupon_discount').value = 0;
            }
            
            updateTotal();
        });

        // Allow pressing Enter in coupon field to apply
        document.getElementById('coupon_code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('apply-coupon-btn').click();
            }
        });

        // Initial calls
        togglePayment();
    });
    </script>
</body>
</html>