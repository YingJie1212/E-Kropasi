<?php
session_start();
require_once "../classes/Cart.php";
require_once "../classes/BankAccount.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart = new Cart();
$bankAccountObj = new BankAccount($cart->getPDO());
$bankInfo = $bankAccountObj->getLatest();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    require_once "../classes/Product.php";
    $productObj = new Product();
    $cartItem = $cart->getItemById($edit_id, $user_id);
    $maxQty = 10;
    if ($cartItem && $cartItem['item_type'] === 'product') {
        $prod = $productObj->getById($cartItem['item_id']);
        $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
    }
    $quantity = isset($_POST['quantity'][$edit_id]) ? max(1, min($maxQty, (int)$_POST['quantity'][$edit_id])) : 1;
    $options = isset($_POST['options'][$edit_id]) ? $_POST['options'][$edit_id] : [];
    $cart->updateItem($edit_id, $user_id, $options, $quantity);
    $_POST['selected_items'] = $_POST['selected_items'] ?? [$edit_id];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_items'])) {
    echo "<p style='font-size:18px;'>No items selected. <a href='cart.php' style='font-size:18px;'>Return to cart</a>.</p>";
    exit;
}

$selectedItemIds = array_map('intval', $_POST['selected_items']);
$allItems = $cart->getItemsByUser($user_id);

if (isset($_POST['remove_id'])) {
    $removeId = (int) $_POST['remove_id'];
    $selectedItemIds = array_filter($selectedItemIds, fn($id) => $id !== $removeId);
}

$checkoutItems = array_filter($allItems, fn($item) => in_array($item['id'], $selectedItemIds));

if (empty($checkoutItems)) {
    echo "<p style='font-size:18px;'>No items left in checkout. <a href='cart.php' style='font-size:18px;'>Return to cart</a>.</p>";
    exit;
}

$total = 0;
foreach ($checkoutItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

$paymentMethods = [];
$stmt = $cart->getPDO()->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY id ASC");
if ($stmt) $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$deliveryOptions = [];
$stmt = $cart->getPDO()->query("SELECT * FROM delivery_options WHERE is_active = 1 ORDER BY id ASC");
if ($stmt) {
    $allOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $seen = [];
    foreach ($allOptions as $opt) {
        $optionNameLower = strtolower(trim($opt['option_name']));
        if (!in_array($optionNameLower, $seen)) {
            $deliveryOptions[] = $opt;
            $seen[] = $optionNameLower;
        }
    }
}
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
            --primary-light: #f9fbe7;
            --primary: #e6ee9c;
            --primary-dark: #c5e1a5;
            --secondary: #81c784;
            --secondary-dark: #66bb6a;
            --accent: #43a047;
            --text: #2e7d32;
            --text-light: #5a9216;
            --text-dark: #1b5e20;
            --border: #c8e6c9;
            --error: #d32f2f;
            --warning: #ffa000;
            --info: #1976d2;
            --success: #388e3c;
            --white: #ffffff;
            --gray-light: #f5f5f5;
            --gray: #e0e0e0;
            --gray-dark: #757575;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.15);
            --radius: 8px;
            --radius-sm: 4px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--primary-light);
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
        }

        .checkout-title {
            font-size: 28px;
            color: var(--text-dark);
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
            background-color: var(--secondary);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
        }

        .back-link:hover {
            color: var(--accent);
            background-color: rgba(129, 199, 132, 0.1);
        }

        .back-link i {
            margin-right: 8px;
        }

        /* Checkout Grid */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--white);
            box-shadow: var(--shadow);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .items-table thead {
            background-color: var(--secondary);
            color: var(--white);
        }

        .items-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
        }

        .items-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .items-table tr:nth-child(even) {
            background-color: var(--primary-light);
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
            border: 1px solid var(--border);
        }

        .product-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .product-type {
            font-size: 13px;
            color: var(--gray-dark);
            background-color: var(--gray-light);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }

        .product-stock {
            font-size: 13px;
            color: var(--success);
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
            color: var(--text);
        }

        .option-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            background-color: var(--white);
            transition: var(--transition);
        }

        .option-select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(129, 199, 132, 0.2);
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-input {
            width: 70px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-align: center;
            font-size: 14px;
            transition: var(--transition);
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(129, 199, 132, 0.2);
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
            background-color: var(--secondary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--secondary-dark);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background-color: rgba(129, 199, 132, 0.1);
        }

        .btn-danger {
            background-color: #ef5350;
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #e53935;
        }

        /* Summary Card */
        .summary-card {
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            position: sticky;
            top: 20px;
        }

        .summary-title {
            font-size: 20px;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .summary-label {
            color: var(--gray-dark);
        }

        .summary-value {
            font-weight: 500;
        }

        .summary-total {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .checkout-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--accent);
            color: white;
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
            background-color: var(--text-dark);
        }

        /* Payment & Delivery Options */
        .options-section {
            margin-top: 30px;
            background-color: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .section-title {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--secondary-dark);
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
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .radio-option:hover {
            border-color: var(--secondary);
        }

        .radio-option input {
            margin-right: 12px;
        }

        .option-label {
            flex: 1;
            font-size: 15px;
        }

        .option-fee {
            color: var(--text-light);
            font-weight: 500;
        }

        /* Upload Sections */
        .upload-section {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background-color: var(--primary-light);
            border-radius: var(--radius-sm);
            border: 1px dashed var(--secondary);
        }

        .upload-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-dark);
        }

        .bank-info {
            background-color: var(--white);
            padding: 15px;
            border-radius: var(--radius-sm);
            margin-bottom: 15px;
            font-size: 14px;
        }

        .bank-info div {
            margin-bottom: 8px;
        }

        .bank-info strong {
            color: var(--text-dark);
        }

        .file-input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .upload-note {
            font-size: 13px;
            color: var(--gray-dark);
        }

        /* QR Code */
        .qr-container {
            text-align: center;
            margin: 15px 0;
        }

        .qr-img {
            max-width: 180px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px;
            background-color: var(--white);
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
        }
    </style>
</head>
<body>
    <main class="container">
        <div class="header-section">
            <h1 class="checkout-title">Checkout</h1>
            <a href="cart.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Cart
            </a>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="checkout-form">
            <input type="hidden" name="edit_id" id="edit_id_input" value="">
            <?php foreach ($selectedItemIds as $id): ?>
                <input type="hidden" name="selected_items[]" value="<?= $id ?>">
            <?php endforeach; ?>

            <div class="checkout-grid">
                <div class="checkout-items">
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
                                                            </select>
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

                    <div class="options-section">
                        <h3 class="section-title">
                            <i class="fas fa-credit-card"></i> Payment Method
                        </h3>
                        <div class="radio-group">
                            <?php foreach ($paymentMethods as $i => $method): ?>
                            <label class="radio-option">
                                <input type="radio" name="payment_method" value="<?= htmlspecialchars($method['method_name']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
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

                        <div id="bank-slip-upload" class="upload-section">
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

                        <div id="ewallet-upload" class="upload-section">
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

                    <div class="options-section">
                        <h3 class="section-title">
                            <i class="fas fa-truck"></i> Delivery Option
                        </h3>
                        <div class="radio-group">
                            <?php foreach ($deliveryOptions as $i => $option): ?>
                            <label class="radio-option">
                                <input type="radio" name="delivery_option" value="<?= htmlspecialchars($option['option_name']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="option-label">
                                    <?= htmlspecialchars($option['option_name']) ?>
                                </span>
                                <?php if (!empty($option['fee']) && $option['fee'] > 0): ?>
                                    <span class="option-fee">+RM<?= number_format($option['fee'], 2) ?></span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="checkout-summary">
                    <div class="summary-card">
                        <h3 class="summary-title">Order Summary</h3>
                        
                        <div class="summary-row">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value">RM<?= number_format($total, 2) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Payment Fee:</span>
                            <span class="summary-value" id="payment-fee">RM0.00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Delivery Fee:</span>
                            <span class="summary-value" id="delivery-fee">RM0.00</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span class="summary-label">Total:</span>
                            <span class="summary-value" id="total-price">RM<?= number_format($total, 2) ?></span>
                        </div>

                        <button type="submit" class="checkout-btn" id="place-order-btn">
                            <i class="fas fa-lock"></i> Place Order
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
    const paymentMethods = <?= json_encode($paymentMethods) ?>;
    const deliveryOptions = <?= json_encode($deliveryOptions) ?>;
    const baseTotal = <?= json_encode($total) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Payment method toggles
        function togglePayment() {
            const bankSlip = document.querySelector('input[name="bank_slip"]');
            const ewalletSlip = document.querySelector('input[name="ewallet_slip"]');
            let bankInChecked = false;
            let ewalletChecked = false;
            const radios = document.querySelectorAll('input[name="payment_method"]');
            radios.forEach(radio => {
                if (radio.checked) {
                    if (radio.value && radio.value.toLowerCase().includes('bank-in')) bankInChecked = true;
                    if (radio.value && radio.value.toLowerCase() === 'ewallet') ewalletChecked = true;
                }
            });

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
            if (document.querySelector('input[name="payment_method"][value="ewallet"]').checked) {
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

        function updateTotal() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            const selectedDelivery = document.querySelector('input[name="delivery_option"]:checked');
            let fee = 0, deliveryFee = 0;
            
            if (selectedPayment) {
                const method = paymentMethods.find(m => m.method_name === selectedPayment.value);
                if (method && method.fee) fee = parseFloat(method.fee);
            }
            if (selectedDelivery) {
                const option = deliveryOptions.find(o => o.option_name === selectedDelivery.value);
                if (option && option.fee) deliveryFee = parseFloat(option.fee);
            }
            
            const newTotal = baseTotal + fee + deliveryFee;
            document.getElementById('payment-fee').textContent = 'RM' + fee.toFixed(2);
            document.getElementById('delivery-fee').textContent = 'RM' + deliveryFee.toFixed(2);
            document.getElementById('total-price').textContent = 'RM' + newTotal.toFixed(2);
        }

        // Event listeners
        document.querySelectorAll('input[name="payment_method"], input[name="delivery_option"]').forEach(radio => {
            radio.addEventListener('change', togglePayment);
        });

        document.getElementById('place-order-btn').addEventListener('click', function(e) {
            document.getElementById('checkout-form').action = 'place_order.php';
            const bankInChecked = document.querySelector('input[name="payment_method"][value="bank-in"]').checked;
            const ewalletChecked = document.querySelector('input[name="payment_method"][value="ewallet"]').checked;
            
            if (bankInChecked && !document.querySelector('input[name="bank_slip"]').value) {
                alert('Please upload your bank transfer slip before placing the order.');
                e.preventDefault();
                return false;
            }
            if (ewalletChecked && !document.querySelector('input[name="ewallet_slip"]').value) {
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

        // Initial calls
        togglePayment();
    });
    </script>
</body>
</html>