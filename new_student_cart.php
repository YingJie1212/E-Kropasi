<?php
session_start();
require_once "../classes/GuestCart.php";
require_once "../classes/Package.php";

$guest_id = $_SESSION['guest_id'] ?? null;
if (!$guest_id) {
    // Generate a guest_id if not set
    $guest_id = uniqid('guest_', true);
    $_SESSION['guest_id'] = $guest_id;
}

$guestCart = new GuestCart();
$packageObj = new Package();
$guestGender = $_SESSION['user_gender'] ?? null;
$allPackages = $packageObj->getAllPackages();
$allPackageIds = [];
foreach ($allPackages as $pkg) {
    $visible = ($pkg['visible_to'] ?? 'guest') === 'guest' || ($pkg['visible_to'] ?? 'guest') === 'both';
    $genderOk = empty($pkg['gender']) || $pkg['gender'] === 'both' || $pkg['gender'] === $guestGender;
    if ($visible && $genderOk) {
        $allPackageIds[] = $pkg['id'];
    }
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $item_id = intval($_POST['item_id'] ?? 0);
    $item_type = $_POST['item_type'] ?? 'product'; // 'product' or 'package'
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
    $selected_options = $_POST['selected_options'] ?? [];

    // Prevent adding the same package twice
    if ($item_type === 'package') {
        $existing = $guestCart->getItemsByGuest($guest_id);
        foreach ($existing as $item) {
            if ($item['item_type'] === 'package' && $item['item_id'] == $item_id) {
                $_SESSION['cart_error'] = "You can only buy a package once.";
                header("Location: new_student_cart.php");
                exit;
            }
        }
    }

    if ($item_id > 0 && in_array($item_type, ['product', 'package'])) {
        $guestCart->addItem($guest_id, $item_id, $item_type, $quantity, $selected_options);
        $_SESSION['cart_success'] = "Item added to cart!";
        $redirect = $_POST['redirect'] ?? 'new_student_cart.php';
        header("Location: $redirect");
        exit;
    } else {
        $_SESSION['cart_error'] = "Invalid item.";
        header("Location: new_student_cart.php");
        exit;
    }
}

// Handle removals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $cart_id = (int) ($_POST['cart_id'] ?? 0);
    $guestCart->removeItem($cart_id, $guest_id);
    header("Location: new_student_cart.php");
    exit;
}

// Handle updates (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $cart_id = (int) ($_POST['cart_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $selected_options = $_POST['selected_options'] ?? [];

    // Optionally, check max quantity for products
    if ($cart_id) {
        $cartItem = $guestCart->getItemById($cart_id, $guest_id);
        if ($cartItem && $cartItem['item_type'] === 'product') {
            require_once "../classes/Product.php";
            $productObj = new Product();
            $prod = $productObj->getById($cartItem['item_id']);
            $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
            $quantity = min($quantity, $maxQty);
        } else {
            $quantity = min($quantity, 10);
        }
        $guestCart->updateItem($cart_id, $guest_id, $selected_options, $quantity);
    }
    $redirect = $_POST['redirect'] ?? 'new_student_cart.php';
    header("Location: $redirect");
    exit;
}

// Get all items in guest cart
$items = $guestCart->getItemsByGuest($guest_id);

$cartIdToPackageId = [];
foreach ($items as $item) {
    if ($item['item_type'] === 'package') {
        $cartIdToPackageId[$item['id']] = $item['item_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart | GreenLeaf</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-light: #f9fbe7;
            --primary-light-2: #f0f4c3;
            --primary-main: #dce775;
            --primary-dark: #c0ca33;
            --secondary-light: #e8f5e9;
            --secondary-main: #a5d6a7;
            --secondary-dark: #81c784;
            --text-primary: #2e7d32;
            --text-secondary: #5a4e42;
            --text-disabled: #8b7d70;
            --error-main: #f44336;
            --error-light: #ffcdd2;
            --background-default: #f9fbe7;
            --background-paper: #ffffff;
            --divider: rgba(0, 0, 0, 0.12);
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--background-default);
            color: var(--text-secondary);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--divider);
        }

        .cart-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .continue-shopping:hover {
            background-color: var(--secondary-light);
            text-decoration: underline;
        }

        /* Empty Cart Styles */
        .empty-cart {
            background-color: var(--background-paper);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            box-shadow: var(--box-shadow);
            margin-top: 20px;
        }

        .empty-cart-icon {
            font-size: 60px;
            color: var(--secondary-main);
            margin-bottom: 20px;
        }

        .empty-cart-title {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .empty-cart-text {
            color: var(--text-secondary);
            margin-bottom: 25px;
            font-size: 16px;
        }

        .browse-btn {
            background-color: var(--primary-dark);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .browse-btn:hover {
            background-color: var(--primary-main);
        }

        /* Cart Table Styles */
        .cart-container {
            background-color: var(--background-paper);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table thead {
            background-color: var(--secondary-light);
        }

        .cart-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }

        .cart-table td {
            padding: 16px;
            border-bottom: 1px solid var(--divider);
            vertical-align: middle;
        }

        .cart-table tr:last-child td {
            border-bottom: none;
        }

        .cart-table tr:hover {
            background-color: var(--primary-light);
        }

        /* Product Info Styles */
        .product-cell {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--divider);
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 16px;
        }

        .product-type {
            font-size: 13px;
            color: var(--text-disabled);
            display: inline-block;
            background-color: var(--primary-light);
            padding: 2px 8px;
            border-radius: 12px;
            margin-top: 4px;
        }

        .stock-info {
            font-size: 13px;
            color: var(--text-primary);
            margin-top: 4px;
        }

        /* Options & Quantity Styles */
        .options-container {
            max-width: 250px;
        }

        .option-group {
            margin-bottom: 12px;
        }

        .option-label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .option-select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--divider);
            border-radius: 4px;
            font-size: 14px;
            background-color: var(--background-paper);
            color: var(--text-secondary);
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }

        .quantity-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .quantity-input {
            width: 70px;
            padding: 8px;
            border: 1px solid var(--divider);
            border-radius: 4px;
            text-align: center;
            font-size: 14px;
        }

        /* Price Styles */
        .price-cell {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background-color: var(--primary-light-2);
            color: var(--text-primary);
        }

        .btn-edit:hover {
            background-color: var(--primary-main);
        }

        .btn-save {
            background-color: var(--secondary-dark);
            color: white;
            display: none;
        }

        .btn-save:hover {
            background-color: var(--secondary-main);
        }

        .btn-remove {
            background-color: var(--error-light);
            color: var(--error-main);
        }

        .btn-remove:hover {
            background-color: var(--error-main);
            color: white;
        }

        /* Checkout Section */
        .checkout-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--background-paper);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            margin-top: 30px;
        }

        .total-price {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .total-price span {
            font-size: 24px;
        }

        .checkout-btn {
            background-color: var(--secondary-dark);
            color: white;
            border: none;
            padding: 12px 28px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .checkout-btn:hover {
            background-color: var(--secondary-main);
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .badge-out-of-stock {
            background-color: var(--error-light);
            color: var(--error-main);
        }

        /* Checkbox Styles */
        .select-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--secondary-main);
            cursor: pointer;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-title {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .modal-message {
            margin-bottom: 25px;
            font-size: 16px;
            color: var(--text-secondary);
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .modal-btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .modal-btn-cancel {
            background-color: var(--error-light);
            color: var(--error-main);
        }

        .modal-btn-cancel:hover {
            background-color: var(--error-main);
            color: white;
        }

        .modal-btn-confirm {
            background-color: var(--secondary-dark);
            color: white;
        }

        .modal-btn-confirm:hover {
            background-color: var(--secondary-main);
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .container {
                padding: 15px;
            }
            
            .cart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .product-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .product-image {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 768px) {
            .cart-table {
                display: block;
                overflow-x: auto;
            }
            
            .options-container {
                max-width: 200px;
            }
            
            .checkout-section {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .modal-buttons {
                flex-direction: column;
            }

            .modal-btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .empty-cart {
                padding: 30px 20px;
            }
            
            .empty-cart-icon {
                font-size: 48px;
            }
            
            .empty-cart-title {
                font-size: 20px;
            }
            
            .browse-btn {
                padding: 10px 20px;
                font-size: 15px;
            }
            
            .cart-table th, 
            .cart-table td {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .checkout-btn {
                width: 100%;
                justify-content: center;
                padding: 12px;
            }

            .modal-content {
                padding: 20px;
            }

            .modal-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cart-header">
            <h1 class="cart-title">Your Shopping Cart</h1>
            <a href="new_student_dashboard.php" class="continue-shopping">
                <i class="fas fa-arrow-left"></i>
                Continue Shopping
            </a>
        </header>

        <?php if (!empty($_SESSION['cart_success'])): ?>
    <div style="background:#e8f5e9;color:#2e7d32;padding:10px 20px;border-radius:4px;margin-bottom:20px;">
        <?= htmlspecialchars($_SESSION['cart_success']) ?>
    </div>
    <?php unset($_SESSION['cart_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['cart_error'])): ?>
    <div style="background:#ffcdd2;color:#c62828;padding:10px 20px;border-radius:4px;margin-bottom:20px;">
        <?= htmlspecialchars($_SESSION['cart_error']) ?>
    </div>
    <?php unset($_SESSION['cart_error']); ?>
<?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="empty-cart-title">Your cart is empty</h2>
                <p class="empty-cart-text">Looks like you haven't added any items to your cart yet.</p>
                <a href="new_student_dashboard.php" class="browse-btn">
                    <i class="fas fa-store"></i> Browse Products
                </a>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Product</th>
                            <th style="width: 120px;">Price</th>
                            <th style="width: 250px;">Options</th>
                            <th style="width: 100px;">Total</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($items as $index => $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $grandTotal += $itemTotal;
                        $checkboxId = 'select-item-' . $item['id'];
                        $selectedOptions = $item['selected_options'] ?? [];

                        // Check stock status
                        $isOutOfStock = false;
                        if ($item['item_type'] === 'product') {
                            require_once "../classes/Product.php";
                            $productObj = new Product();
                            $prod = $productObj->getById($item['item_id']);
                            $isOutOfStock = (isset($prod['quantity']) && (int)$prod['quantity'] === 0);
                        } elseif ($item['item_type'] === 'package') {
                            require_once "../classes/Package.php";
                            $packageObj = new Package();
                            $package = $packageObj->getById($item['item_id']);
                            $isOutOfStock = (isset($package['quantity']) && (int)$package['quantity'] === 0);
                        }
                    ?>
                        <tr>
                            <td>
                                <input type="checkbox" id="<?= $checkboxId ?>" 
                                       name="selected_items[]" value="<?= $item['id'] ?>" 
                                       class="select-checkbox" <?= $isOutOfStock ? 'disabled' : '' ?> checked>
                                <?php if ($isOutOfStock): ?>
                                    <div class="status-badge badge-out-of-stock">Out of Stock</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="product-cell">
                                    <?php
                                    $productImage = '../assets/default.jpg';
                                    if ($item['item_type'] === 'product') {
                                        require_once "../classes/Product.php";
                                        $productObj = new Product();
                                        $prod = $productObj->getById($item['item_id']);
                                        $images = $productObj->getImagesByProductId($item['item_id']);
                                        if (!empty($images) && !empty($images[0]['image'])) {
                                            $productImage = "../uploads/" . htmlspecialchars($images[0]['image']);
                                        }
                                    } elseif ($item['item_type'] === 'package') {
                                        require_once "../classes/Package.php";
                                        $packageObj = new Package();
                                        $package = $packageObj->getById($item['item_id']);
                                        if (!empty($package['image'])) {
                                            $productImage = "../uploads/" . htmlspecialchars($package['image']);
                                        }
                                    }
                                    ?>
                                    <img src="<?= $productImage ?>" class="product-image" alt="<?= htmlspecialchars($item['display_name'] ?? '') ?>">
                                    <div class="product-info">
                                        <div class="product-name"><?= htmlspecialchars($item['display_name'] ?? 'Unknown') ?></div>
                                        <span class="product-type"><?= ucfirst($item['item_type']) ?></span>
                                        <?php if ($item['item_type'] === 'product'): ?>
                                            <div class="stock-info">
                                                Stock: <?= isset($prod['quantity']) ? (int)$prod['quantity'] : 0 ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="price-cell">RM<?= number_format($item['price'], 2) ?></td>
                            <td>
                                <form method="POST" action="new_student_cart.php" class="edit-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <div class="options-container">
                                        <?php
                                        if ($item['item_type'] === 'product') {
                                            $optionGroups = $guestCart->getOptionGroupsWithValues($item['item_id']);
                                            foreach ($optionGroups as $group):
                                                $groupName = $group['group_name'];
                                                $selectedId = $selectedOptions[$groupName] ?? null;
                                        ?>
                                            <div class="option-group">
                                                <label class="option-label"><?= htmlspecialchars($groupName) ?></label>
                                                <select class="option-select" name="selected_options[<?= htmlspecialchars($groupName) ?>]" disabled>
                                                    <?php foreach ($group['options'] as $opt): ?>
                                                        <option value="<?= $opt['id'] ?>" <?= ($opt['id'] == $selectedId) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($opt['value']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php
                                            endforeach;
                                        } elseif ($item['item_type'] === 'package') {
                                            $packageOptions = $guestCart->getOptionsForPackage($item['item_id']);
                                            foreach ($packageOptions as $product_id => $product) {
                                                echo '<div class="option-label" style="font-weight:600;margin-top:12px;">' . htmlspecialchars($product['product_name']) . '</div>';
                                                foreach ($product['option_groups'] as $group) {
                                                    $groupName = $group['group_name'];
                                                    $selectedId = $selectedOptions[$product_id][$groupName] ?? null;
                                        ?>
                                            <div class="option-group">
                                                <label class="option-label"><?= htmlspecialchars($groupName) ?></label>
                                                <select class="option-select" name="selected_options[<?= $product_id ?>][<?= htmlspecialchars($groupName) ?>]" disabled>
                                                    <?php foreach ($group['options'] as $opt): ?>
                                                        <option value="<?= $opt['id'] ?>" <?= ($opt['id'] == $selectedId) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($opt['value']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="quantity-control">
                                            <span class="quantity-label">Quantity:</span>
                                            <?php
                                                $maxQty = 10;
                                                if ($item['item_type'] === 'product') {
                                                    require_once "../classes/Product.php";
                                                    $productObj = new Product();
                                                    $prod = $productObj->getById($item['item_id']);
                                                    $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
                                                }
                                            ?>
                                            <input type="number" class="quantity-input" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= $maxQty ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="action-buttons" style="margin-top:12px;">
                                        <button type="button" class="btn btn-edit edit-btn">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="submit" class="btn btn-save save-btn">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                    </div>
                                </form>
                            </td>
                            <td class="price-cell">RM<?= number_format($itemTotal, 2) ?></td>
                            <td>
                                <form method="POST" action="new_student_cart.php" onsubmit="return confirm('Are you sure you want to remove this item?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-remove">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="checkout-section">
                <div class="total-price">
                    Selected Total: <span id="selected-total">RM<?= number_format($grandTotal, 2) ?></span>
                </div>
                <button type="button" class="checkout-btn" id="checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="package-confirmation-modal">
        <div class="modal-content">
            <h3 class="modal-title">Are you sure?</h3>
            <p class="modal-message">You haven't selected all required packages. Are you sure you don't want to purchase the packages?</p>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel" id="modal-cancel">No, take me back</button>
                <button type="button" class="modal-btn modal-btn-confirm" id="modal-confirm">Yes, I already bought it before</button>
            </div>
        </div>
    </div>

    <script>
        // Calculate and update the selected total
        function updateSelectedTotal() {
            let total = 0;
            const checkboxes = document.querySelectorAll('input[name="selected_items[]"]:checked:not(:disabled)');
            
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const priceText = row.querySelector('td:nth-child(5)').textContent;
                const price = parseFloat(priceText.replace('RM', '').trim());
                total += price;
            });
            
            document.getElementById('selected-total').textContent = 'RM' + total.toFixed(2);
        }

        // Make rows clickable for checkbox selection
        document.querySelectorAll('.cart-table tbody tr').forEach(row => {
            row.addEventListener('click', (e) => {
                // Ignore clicks on buttons, links, inputs, etc.
                if (e.target.tagName === 'INPUT' || 
                    e.target.tagName === 'BUTTON' || 
                    e.target.tagName === 'A' || 
                    e.target.tagName === 'SELECT' ||
                    e.target.closest('button') ||
                    e.target.closest('a') ||
                    e.target.closest('input') ||
                    e.target.closest('select')) {
                    return;
                }
                
                const checkbox = row.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    updateSelectedTotal();
                }
            });
        });

        // Update total when checkboxes are clicked
        document.querySelectorAll('input[name="selected_items[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedTotal);
        });

        // Initialize the total on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedTotal();
        });

        // Enable editing of cart items
        document.querySelectorAll('.edit-form').forEach(form => {
            const editBtn = form.querySelector('.edit-btn');
            const saveBtn = form.querySelector('.save-btn');
            const selects = form.querySelectorAll('.option-select');
            const qtyInput = form.querySelector('.quantity-input');

            editBtn.addEventListener('click', () => {
                selects.forEach(select => select.disabled = false);
                qtyInput.readOnly = false;
                editBtn.style.display = 'none';
                saveBtn.style.display = 'inline-flex';
            });
        });

        // Handle checkout button click
        document.getElementById('checkout-btn')?.addEventListener('click', () => {
            const checkedItems = Array.from(document.querySelectorAll('input[name="selected_items[]"]:checked:not(:disabled)')).map(el => el.value);
            
            if (checkedItems.length === 0) {
                alert('Please select at least one item to checkout');
                return;
            }

            // Gather selected package IDs from checked cart IDs
            const selectedPackageIds = checkedItems
                .map(id => cartIdToPackageId[id])
                .filter(id => !!id)
                .map(id => parseInt(id));
            
            // Check if all packages are selected
            const missing = allPackageIds.filter(pkgId => !selectedPackageIds.includes(pkgId));
            if (allPackageIds.length > 0 && missing.length > 0) {
                // Show confirmation modal instead of alert
                const modal = document.getElementById('package-confirmation-modal');
                modal.classList.add('active');
                
                // Handle modal button clicks
                document.getElementById('modal-cancel').onclick = function() {
                    modal.classList.remove('active');
                    // Redirect to dashboard
                    window.location.href = 'new_student_dashboard.php';
                };
                
                document.getElementById('modal-confirm').onclick = function() {
                    modal.classList.remove('active');
                    // Proceed to checkout
                    proceedToCheckout(checkedItems);
                };
                
                return;
            }

            // If all packages are selected or no packages exist, proceed directly
            proceedToCheckout(checkedItems);
        });

        // Function to proceed to checkout
        function proceedToCheckout(checkedItems) {
            // Create a form to submit to checkout.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'new_student_checkout.php';
            
            checkedItems.forEach(itemId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = itemId;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // Show save button when quantity is changed directly (without clicking edit)
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('input', function() {
                if (parseInt(this.value) > parseInt(this.max)) {
                    this.value = this.max;
                }
                if (parseInt(this.value) < 1) {
                    this.value = 1;
                }
            });
            input.addEventListener('change', function() {
                if (!this.readOnly) {
                    const form = this.closest('.edit-form');
                    const saveBtn = form.querySelector('.save-btn');
                    saveBtn.style.display = 'inline-flex';
                }
            });
        });

        const allPackageIds = <?= json_encode($allPackageIds) ?>;
        const cartIdToPackageId = <?= json_encode($cartIdToPackageId) ?>;
    </script>
</body>
</html>