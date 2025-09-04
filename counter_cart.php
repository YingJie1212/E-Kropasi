<?php
session_start();
require_once "../classes/CounterCart.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$cart = new Cart();

// Handle removals and updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cart_id = (int) ($_POST['cart_id'] ?? 0);

    if ($action === 'remove') {
        $cart->removeItem($cart_id, $admin_id);
        header("Location: counter_cart.php");
        exit;
    } elseif ($action === 'update') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        if ($action === 'update' && $cart_id) {
            // Fetch the cart item to get the product id
            $cartItem = $cart->getItemById($cart_id, $admin_id);
            if ($cartItem && $cartItem['item_type'] === 'product') {
                require_once "../classes/Product.php";
                $productObj = new Product();
                $prod = $productObj->getById($cartItem['item_id']);
                $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
                $quantity = min($quantity, $maxQty);
            } else {
                $quantity = min($quantity, 10);
            }
            $selected_options = $_POST['selected_options'] ?? [];
            $cart->updateItem($cart_id, $admin_id, $selected_options, $quantity);
            header("Location: counter_cart.php");
            exit;
        }
    }
}

$items = $cart->getItemsByUser($admin_id);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Shopping Cart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-green: #f9fbe7;
            --secondary-green: #a3c8ab;
            --accent-green: #7bb58a;
            --text-color: #1a3a1a;
            --border-color: #7bb58a;
            --button-color: #218838;
            --button-hover: #1e7e34;
            --header-bg: #a3c8ab;
            --row-even: #d8ebd9;
            --row-odd: #f5f5f5;
            --highlight-color: #e6f7e6;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--primary-green);
            color: var(--text-color);
            line-height: 1.7;
            padding: 20px;
            font-size: 16px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        h1 {
            font-weight: 500;
            color: #5a4e42;
            font-size: 30px;
        }
        
        .continue-shopping {
            color: var(--button-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
            white-space: nowrap;
            font-size: 17px;
        }
        
        .continue-shopping:hover {
            color: var(--button-hover);
            text-decoration: underline;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 16px;
        }
        
        .cart-table th {
            background-color: var(--header-bg);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: #5a4e42;
            border-bottom: 2px solid var(--border-color);
            font-size: 16px;
        }
        
        .cart-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
            font-size: 15px;
        }
        
        .cart-table tr:nth-child(even) {
            background-color: var(--row-even);
        }
        
        .cart-table tr:nth-child(odd) {
            background-color: var(--row-odd);
        }
        
        .cart-table tr:hover {
            background-color: var(--highlight-color);
        }
        
        .product-image {
            width: 85px;
            height: 85px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            margin-right: 10px;
        }
        
        .product-info {
            display: flex;
            align-items: center;
            max-width: 300px;
        }
        
        .product-name {
            font-weight: 600;
            color: #5a4e42;
            margin-bottom: 5px;
            word-break: break-word;
            font-size: 16px;
        }
        
        .product-type {
            font-size: 13px;
            color: #8b7d70;
            margin-left: 5px;
        }
        
        .option-group {
            margin-bottom: 8px;
        }
        
        .option-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 3px;
            color: #5a4e42;
        }
        
        .option-select {
            width: 100%;
            padding: 7px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            font-size: 14px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
        }
        
        .quantity-input {
            width: 65px;
            padding: 7px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            text-align: center;
            font-size: 15px;
        }
        
        .action-button {
            background-color: var(--button-color);
            color: white;
            border: none;
            padding: 9px 13px;
            font-size: 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .action-button:hover {
            background-color: var(--button-hover);
        }
        
        .remove-button {
            background-color: #c45656;
        }
        
        .remove-button:hover {
            background-color: #a33d3d;
        }
        
        .checkout-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .total-price {
            font-size: 20px;
            font-weight: 600;
            color: #5a4e42;
        }
        
        .checkout-button {
            background-color: #5a8f5a;
            color: white;
            border: none;
            padding: 13px 25px;
            font-size: 17px;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .checkout-button:hover {
            background-color: #487a48;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .empty-cart-icon {
            font-size: 50px;
            color: #c4beb5;
            margin-bottom: 20px;
        }
        
        .empty-cart-message {
            font-size: 19px;
            color: #5a4e42;
            margin-bottom: 20px;
        }
        
        .select-checkbox {
            transform: scale(1.3);
            margin: 0 auto;
            display: block;
        }

        .out-of-stock-badge {
            display: inline-block;
            background: #e53935;
            color: #fff;
            border-radius: 16px;
            padding: 4px 14px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 8px;
            margin-bottom: 4px;
            box-shadow: 0 2px 6px rgba(229,57,53,0.08);
            letter-spacing: 0.5px;
            text-align: center;
            min-width: 80px;
        }

        /* Mobile-specific styles */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cart-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start;
                max-width: none;
            }
            
            .product-image {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .checkout-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .checkout-button {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .cart-table td:first-child {
                display: flex;
                flex-direction: column;
                align-items: center;
                min-width: 60px;
            }
            
            .cart-table th,
            .cart-table td {
                padding: 9px 11px;
                font-size: 15px;
            }
            
            .product-image {
                width: 65px;
                height: 65px;
            }
            
            .action-button {
                padding: 7px 11px;
                font-size: 14px;
            }
            
            .checkout-button {
                padding: 11px 16px;
                font-size: 16px;
            }
            
            .empty-cart {
                padding: 25px;
            }
            
            .empty-cart-icon {
                font-size: 40px;
            }
            
            .empty-cart-message {
                font-size: 17px;
            }

            .out-of-stock-badge {
                font-size: 13px;
                padding: 3px 10px;
                min-width: 70px;
            }
        }

        /* Print styles */
        @media print {
            body {
                background-color: white;
                color: black;
                padding: 0;
                font-size: 12pt;
            }
            
            .page-header,
            .continue-shopping,
            .action-button,
            .remove-button,
            .checkout-section {
                display: none;
            }
            
            .cart-table {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .cart-table th {
                background-color: #f5f5f5 !important;
                color: black !important;
            }
            
            .option-select,
            .quantity-input {
                border: none;
                background: transparent;
                padding: 0;
                appearance: none;
            }
            
            .option-select {
                -webkit-appearance: none;
                -moz-appearance: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <h1>Your Shopping Cart</h1>
        <a href="counter.php" class="continue-shopping">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Continue Shopping
        </a>
    </div>

    <?php if (empty($items)): ?>
        <div class="empty-cart">
            <div class="empty-cart-icon">🛒</div>
            <div class="empty-cart-message">Your cart is empty</div>
            <a href="counter.php" class="action-button">Browse Products</a>
        </div>
    <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th style="width: 250px;">Product</th>
                    <th style="width: 100px;">Unit Price</th>
                    <th style="width: 250px;">Options & Quantity</th>
                    <th style="width: 100px;">Total</th>
                    <th style="width: 100px;">Actions</th>
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

                // --- Add this block to determine out of stock status ---
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
                // ------------------------------------------------------
            ?>
                <tr class="selectable-row" data-checkbox-id="<?= $checkboxId ?>">
                    <td style="vertical-align: middle;">
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge" style="margin-bottom:6px;">Out of Stock</div>
                            <?php endif; ?>
                            <input type="checkbox" id="<?= $checkboxId ?>" name="selected_items[]" value="<?= $item['id'] ?>" class="select-checkbox" <?= $isOutOfStock ? 'disabled' : '' ?>>
                        </div>
                    </td>
                    <td>
                        <div class="product-info">
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
                            <img src="<?= $productImage ?>" class="product-image">
                            <div>
                                <div class="product-name">
                                    <?= htmlspecialchars($item['display_name'] ?? 'Unknown') ?>
                                    <span class="product-type">(<?= $item['item_type'] ?>)</span>
                                </div>
                                <?php if ($item['item_type'] === 'product'): ?>
                                    <div class="product-stock" style="font-size:13px; color:#388e3c;">
                                        In Stock: 
                                        <?php
                                            echo isset($prod['quantity']) ? (int)$prod['quantity'] : 0;
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>RM<?= number_format($item['price'], 2) ?></td>
                    <td>
                        <form method="POST" action="counter_cart.php" class="edit-form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                            <?php
                            if ($item['item_type'] === 'product') {
                                $optionGroups = $cart->getOptionGroupsWithValues($item['item_id']);
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
                                $packageOptions = $cart->getOptionsForPackage($item['item_id']);
                                foreach ($packageOptions as $product_id => $product) {
                                    echo '<div class="product-option-title">' . htmlspecialchars($product['product_name']) . '</div>';
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
                                <label>Qty:</label>
                                <?php
                                    $maxQty = 10;
                                    if ($item['item_type'] === 'product') {
                                        require_once "../classes/Product.php";
                                        $productObj = new Product();
                                        $prod = $productObj->getById($item['item_id']);
                                        $maxQty = isset($prod['quantity']) ? (int)$prod['quantity'] : 10;
                                    }
                                ?>
                                <input type="number" class="quantity-input" name="quantity" value="<?= $item['quantity'] ?>" min="1" readonly>
                            </div>
                            <button type="button" class="action-button edit-btn">Edit</button>
                            <button type="submit" class="action-button save-btn" style="display:none;">Save</button>
                        </form>
                    </td>
                    <td>RM<?= number_format($itemTotal, 2) ?></td>
                    <td>
                        <form method="POST" action="counter_cart.php" onsubmit="return confirm('Remove this item from your cart?');" style="display:inline;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="action-button remove-button">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="checkout-section">
            <div class="total-price">Total: RM<?= number_format($grandTotal, 2) ?></div>
            <button type="button" class="checkout-button" id="checkout-btn">Proceed to Checkout Selected Items</button>
        </div>
    <?php endif; ?>

    <script>
        // Confirm before proceeding to checkout if no items are selected
        document.getElementById('checkout-form')?.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('#checkout-form input[name="selected_items[]"]:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one item to checkout');
            }
        });

        // Make the entire row clickable to toggle checkbox, except for form elements
        document.querySelectorAll('.selectable-row').forEach(function(row) {
            row.addEventListener('click', function(e) {
                // Ignore clicks on form controls or inside forms (except the main cart form)
                if (
                    e.target.tagName === 'INPUT' ||
                    e.target.tagName === 'BUTTON' ||
                    e.target.tagName === 'SELECT' ||
                    e.target.tagName === 'LABEL' ||
                    (e.target.closest('form') && e.target.closest('form').getAttribute('action') !== 'counter_checkout.php')
                ) {
                    return;
                }
                const checkboxId = row.getAttribute('data-checkbox-id');
                const checkbox = document.getElementById(checkboxId);
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                }
            });
        });

        // Enable editing of options and quantity in cart
        document.querySelectorAll('.edit-form').forEach(function(form) {
            const editBtn = form.querySelector('.edit-btn');
            const saveBtn = form.querySelector('.save-btn');
            const selects = form.querySelectorAll('.option-select');
            const qtyInput = form.querySelector('.quantity-input');

            editBtn.addEventListener('click', function() {
                selects.forEach(s => s.disabled = false);
                qtyInput.readOnly = false;
                editBtn.style.display = 'none';
                saveBtn.style.display = '';
                qtyInput.focus();
            });

            // Optional: After save, disable fields again (handled by page reload)
            // form.addEventListener('submit', function() {
            //     selects.forEach(s => s.disabled = true);
            //     qtyInput.readOnly = true;
            //     editBtn.style.display = '';
            //     saveBtn.style.display = 'none';
            // });
        });

        // Checkout selected items
        document.getElementById('checkout-btn')?.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select at least one item to checkout');
                return;
            }
            // Create a form and submit selected items to checkout.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'counter_checkout.php';
            checkedBoxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = cb.value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        });

        window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="selected_items[]"]:not(:disabled)').forEach(function(cb) {
        cb.checked = true;
    });
});

    </script>
</body>
</html>