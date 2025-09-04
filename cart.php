<?php
session_start();
require_once "../classes/Cart.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart = new Cart();

// Handle removals and updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cart_id = (int) ($_POST['cart_id'] ?? 0);

    if ($action === 'remove') {
        $cart->removeItem($cart_id, $user_id);
        header("Location: cart.php");
        exit;
    } elseif ($action === 'update') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        if ($action === 'update' && $cart_id) {
            // Fetch the cart item to get the product id
            $cartItem = $cart->getItemById($cart_id, $user_id);
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
            $cart->updateItem($cart_id, $user_id, $selected_options, $quantity);
            header("Location: cart.php");
            exit;
        }
    }
}

$items = $cart->getItemsByUser($user_id);
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
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="cart-header">
            <h1 class="cart-title">Your Shopping Cart</h1>
            <a href="products.php" class="continue-shopping">
                <i class="fas fa-arrow-left"></i>
                Continue Shopping
            </a>
        </header>

        <?php if (empty($items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="empty-cart-title">Your cart is empty</h2>
                <p class="empty-cart-text">Looks like you haven't added any items to your cart yet.</p>
                <a href="products.php" class="browse-btn">
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
                    foreach ($items as $index => $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
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
                                       class="select-checkbox" <?= $isOutOfStock ? 'disabled' : '' ?>>
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
                                <form method="POST" action="cart.php" class="edit-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                    <div class="options-container">
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
                                <form method="POST" action="cart.php" onsubmit="return confirm('Are you sure you want to remove this item?');">
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
                    Selected Total: <span id="selected-total">RM0.00</span>
                </div>
                <button type="button" class="checkout-btn" id="checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
            </div>
        <?php endif; ?>
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
        document.addEventListener('DOMContentLoaded', updateSelectedTotal);

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

            // Create a form to submit to checkout.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout.php';
            
            checkedItems.forEach(itemId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = itemId;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        });

        // Show save button when quantity is changed directly (without clicking edit)
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (!this.readOnly) {
                    const form = this.closest('.edit-form');
                    const saveBtn = form.querySelector('.save-btn');
                    saveBtn.style.display = 'inline-flex';
                }
            });
        });
    </script>
</body>
</html>