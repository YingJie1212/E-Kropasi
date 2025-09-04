<?php
require_once "../classes/Product.php";
require_once "../classes/Package.php";
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

$product = new Product();
$package = new Package();
$categoryObj = new Category();
$orderManager = new OrderManager();

// Only get active products
$allProducts = $product->getActiveProductsWithFirstImage();
$categories = $categoryObj->getAll();
$message = "";
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $visible_to = $_POST['visible_to'] ?? 'both';
    $selectedProducts = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];

    foreach ($selectedProducts as $productId) {
        $qty = (int)($quantities[$productId] ?? 0);
        if ($qty > 0) {
            // Use $productId and $qty as needed
            // e.g. $package->addItemToPackage($packageId, $productId, $qty);
        }
    }

    $category_id = null;
    $gender = $_POST['gender'] ?? null;
    if ($gender === '') {
        $gender = null;
    }
    if (!empty($selectedProducts)) {
        $firstProductId = $selectedProducts[0];
        foreach ($allProducts as $p) {
            if ($p['id'] == $firstProductId) {
                $category_id = $p['category_id'];
                break;
            }
        }
    }

    // Calculate total price
    $price = 0;
    foreach ($selectedProducts as $index => $productId) {
        $productDetails = null;
        foreach ($allProducts as $p) {
            if ($p['id'] == $productId) {
                $productDetails = $p;
                break;
            }
        }
        $qty = (int)$quantities[$index];
        if ($productDetails && $qty > 0) {
            $price += $productDetails['price'] * $qty;
        }
    }

    // Use manual price if provided
    if (isset($_POST['manual_price']) && $_POST['manual_price'] !== '') {
        $manualPrice = floatval($_POST['manual_price']);
        if ($manualPrice >= 0) {
            $price = $manualPrice;
        }
    }

    // Image upload
    $imageName = "";
    if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageName = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $imageName);
    } elseif (!empty($selectedProducts)) {
        $firstProductId = $selectedProducts[0];
        $firstProduct = $product->find($firstProductId);
        if ($firstProduct && !empty($firstProduct['image'])) {
            $imageName = $firstProduct['image'];
        }
    }
    
    if ($name && !empty($selectedProducts)) {
        $packageId = $package->add($name, $description, $price, $imageName, $category_id, $visible_to, $gender);
        foreach ($selectedProducts as $index => $productId) {
            $qty = (int)$quantities[$index];
            if ($qty > 0) {
                $package->addItemToPackage($packageId, $productId, $qty);
                $prod = $product->find($productId);
                if ($prod && !empty($prod['image'])) {
                    $package->addImage($packageId, $prod['image']);
                }
                // Automatically include all options for this product
                $options = $product->getOptionsByProductId($productId);
                foreach ($options as $opt) {
                    // Assuming $opt['group_name'] and $opt['values'] (array)
                    foreach ($opt['values'] as $value) {
                        $package->addOption($packageId, $productId, $opt['group_name'], $value);
                    }
                }
            }
        }
        
        // Collect unique category IDs from selected products
        $categoryIds = [];
        foreach ($selectedProducts as $productId) {
            foreach ($allProducts as $p) {
                if ($p['id'] == $productId && !empty($p['category_id'])) {
                    $categoryIds[] = $p['category_id'];
                }
            }
        }
        $categoryIds = array_unique($categoryIds);

        if (!empty($categoryIds)) {
            foreach ($categoryIds as $catId) {
                $package->addCategory($packageId, $catId);
            }
        }
        
        header("Location: view_package.php?package_added=1");
        exit;
    } else {
        $message = "Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Product Package</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-50: #e8f5e9;
            --primary-100: #c8e6c9;
            --primary-200: #a5d6a7;
            --primary-300: #81c784;
            --primary-400: #66bb6a;
            --primary-500: #4caf50;
            --primary-600: #43a047;
            --primary-700: #388e3c;
            --primary-800: #2e7d32;
            --primary-900: #1b5e20;
            
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
            
            --red-500: #ef4444;
            --green-500: #10b981;
            --blue-500: #3b82f6;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            --radius-sm: 0.125rem;
            --radius: 0.25rem;
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
            --radius-xl: 0.75rem;
            --radius-full: 9999px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.5;
            color: var(--gray-800);
            background-color: var(--gray-50);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-700);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .breadcrumb a {
            color: var(--primary-600);
            text-decoration: none;
            transition: color 0.15s ease;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-800);
            text-decoration: underline;
        }
        
        .breadcrumb-separator {
            margin: 0 0.5rem;
            color: var(--gray-400);
        }
        
        /* Alert */
        .alert {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            background-color: #fef2f2;
            color: var(--red-500);
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        
        .alert-icon {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1.5rem;
        }
        
        .form-section {
            grid-column: span 8 / span 8;
        }
        
        .form-sidebar {
            grid-column: span 4 / span 4;
        }
        
        .form-card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .form-card-title {
            margin-bottom: 1rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-label.required::after {
            content: " *";
            color: var(--red-500);
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: var(--gray-700);
            background-color: white;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-300);
            box-shadow: 0 0 0 3px rgba(66, 153, 66, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-text {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-top: 1rem;
        }
        
        .product-card {
            padding: 1.25rem;
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        }
        
        .product-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .product-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            background-color: var(--primary-50);
        }
        
        .product-info {
            flex: 1;
            min-width: 0;
        }
        
        .product-name {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-price {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--primary-600);
            margin-bottom: 0.5rem;
        }
        
        .product-options {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .product-option-group {
            margin-bottom: 0.25rem;
        }
        
        .product-option-name {
            font-weight: 500;
        }
        
        .product-quantity {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }
        
        .product-quantity-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .product-quantity-input {
            width: 80px;
            padding: 0.375rem 0.5rem;
            font-size: 0.875rem;
            text-align: center;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
        }
        
        /* Checkbox */
        .form-check {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 1.5rem;
        }
        
        .form-check-input {
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
            appearance: none;
            background-color: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            transition: background-color 0.15s, border-color 0.15s, box-shadow 0.15s;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-500);
            border-color: var(--primary-500);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }
        
        /* Image Preview */
        .image-preview {
            margin-top: 0.5rem;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }
        
        /* Summary Card */
        .summary-card {
            position: sticky;
            top: 1.5rem;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .summary-value {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .summary-total {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary-700);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25rem;
            border-radius: var(--radius);
            transition: all 0.15s ease;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
        }
        
        .btn-primary {
            color: white;
            background-color: var(--primary-600);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-700);
        }
        
        .btn-block {
            display: flex;
            width: 100%;
        }
        
        .btn-icon {
            margin-right: 0.5rem;
            font-size: 0.875rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary-600);
            text-decoration: none;
            transition: color 0.15s ease;
        }
        
        .back-link:hover {
            color: var(--primary-800);
            text-decoration: underline;
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .container {
                padding: 1.25rem;
            }
            
            .form-section {
                grid-column: span 7 / span 7;
            }
            
            .form-sidebar {
                grid-column: span 5 / span 5;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .form-section {
                grid-column: span 12 / span 12;
            }
            
            .form-sidebar {
                grid-column: span 12 / span 12;
            }
            
            .summary-card {
                position: static;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .header-title {
                font-size: 1.25rem;
            }
            
            .form-card {
                padding: 1.25rem;
            }
            
            .product-card {
                padding: 1rem;
            }
            
            .product-header {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .product-image {
                width: 100%;
                height: auto;
                aspect-ratio: 1/1;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 1rem;
            }
            
            .product-quantity {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .product-quantity-input {
                width: 100%;
            }
            
            .breadcrumb {
                flex-wrap: wrap;
                row-gap: 0.25rem;
            }
        }
        
        @media (max-width: 400px) {
            .container {
                padding: 0.75rem;
            }
            
            .form-card {
                padding: 0.75rem;
            }
            
            .form-card-title {
                font-size: 1rem;
            }
            
            .form-control {
                padding: 0.5rem 0.75rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.8125rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <a href="view_package.php">Packages</a>
                <span class="breadcrumb-separator">/</span>
                <span>Create Package</span>
            </div>
            <h1 class="header-title">Create Product Package</h1>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <div class="form-section">
                <div class="form-card">
                    <h2 class="form-card-title">Package Details</h2>
                    
                    <div class="form-group">
                        <label for="name" class="form-label required">Package Name</label>
                        <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Starter Kit, Complete Bundle">
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Describe what's included in this package"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="image" class="form-label">Package Image</label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*">
                        <div id="imagePreview" class="image-preview"></div>
                        <p class="form-text">If no image is selected, the first product's image will be used.</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="visible_to" class="form-label required">Visibility</label>
                        <select id="visible_to" name="visible_to" class="form-control" required>
                            <option value="both">Visible to Everyone</option>
                            <option value="student">Students Only</option>
                            <option value="guest">Guests Only</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender Specific</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="">Not Gender Specific</option>
                            <option value="male">Male Only</option>
                            <option value="female">Female Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-card">
                    <h2 class="form-card-title">Select Products</h2>
                    
                    <div class="products-grid" id="productsContainer">
                        <?php foreach ($allProducts as $p): 
                            $options = $product->getOptionsByProductId($p['id']);
                        ?>
                            <div class="product-card" data-product-id="<?= $p['id'] ?>">
                                <div class="product-header">
                                    <?php if (!empty($p['first_image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($p['first_image']) ?>" class="product-image" alt="<?= htmlspecialchars($p['name']) ?>">
                                    <?php elseif (!empty($p['image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" class="product-image" alt="<?= htmlspecialchars($p['name']) ?>">
                                    <?php else: ?>
                                        <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: var(--gray-400);">
                                            <i class="fas fa-image" style="font-size: 1.5rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-info">
                                        <h3 class="product-name"><?= htmlspecialchars($p['name']) ?></h3>
                                        <div class="product-price">RM<?= number_format($p['price'], 2) ?></div>
                                        
                                        <?php if (!empty($options)): ?>
                                            <div class="product-options">
                                                <?php foreach ($options as $optionGroup): ?>
                                                    <div class="product-option-group">
                                                        <span class="product-option-name"><?= htmlspecialchars($optionGroup['group_name']) ?>:</span>
                                                        <span><?= htmlspecialchars(implode(", ", $optionGroup['values'])) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="product-quantity">
                                    <label class="product-quantity-label">
                                        <input type="checkbox" 
                                               class="form-check-input product-checkbox" 
                                               name="products[<?= $p['id'] ?>]" 
                                               value="<?= $p['id'] ?>"
                                               onchange="updateTotal()">
                                        Include
                                    </label>
                                    <input type="number" 
                                           name="quantities[<?= $p['id'] ?>]" 
                                           class="product-quantity-input" 
                                           value="1" 
                                           min="1" 
                                           oninput="updateTotal()">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-sidebar">
                <div class="form-card summary-card">
                    <h2 class="form-card-title">Package Summary</h2>
                    
                    <div class="summary-item">
                        <span class="summary-label">Selected Products</span>
                        <span class="summary-value" id="selectedCount">0</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label">Total Price</span>
                        <span class="summary-value" id="totalPrice">RM0.00</span>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label for="manual_price" class="form-label">Override Price</label>
                        <input type="number" id="manual_price" name="manual_price" class="form-control" placeholder="Enter custom price" step="0.01" min="0" oninput="updateTotal()">
                        <p class="form-text">Leave blank to use calculated price</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                        <i class="fas fa-save btn-icon"></i>
                        Create Package
                    </button>
                    
                    <a href="view_package.php" class="back-link">
                        <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                        Back to Packages
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script id="prices-data" type="application/json"><?= json_encode(array_column($allProducts, 'price', 'id')) ?></script>
    <script>
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                preview.appendChild(img);
            }
        });
        
        // Calculate total price
        function updateTotal() {
            const prices = JSON.parse(document.getElementById('prices-data').textContent);
            let total = 0;
            let selectedCount = 0;
            
            document.querySelectorAll('.product-card').forEach(card => {
                const productId = card.dataset.productId;
                const checkbox = card.querySelector('.product-checkbox');
                const quantityInput = card.querySelector('.product-quantity-input');
                
                if (checkbox && checkbox.checked && quantityInput) {
                    const quantity = parseInt(quantityInput.value) || 0;
                    const price = parseFloat(prices[productId]) || 0;
                    total += quantity * price;
                    selectedCount++;
                }
            });
            
            // Check for manual price override
            const manualPriceInput = document.getElementById('manual_price');
            if (manualPriceInput && manualPriceInput.value) {
                total = parseFloat(manualPriceInput.value) || 0;
            }
            
            // Update UI
            document.getElementById('selectedCount').textContent = selectedCount;
            document.getElementById('totalPrice').textContent = 'RM' + total.toFixed(2);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTotal();
            
            // Check for pending orders
            let lastPendingCount = <?= (int)$pendingOrdersCount ?>;
            
            function checkNewOrders() {
                fetch('check_pending_orders.php')
                    .then(res => res.json())
                    .then(data => {
                        if (typeof data.count !== 'undefined' && data.count > lastPendingCount) {
                            // Play notification sound
                            const audio = new Audio('../assets/notification.mp3');
                            audio.play().catch(e => console.log('Audio play failed:', e));
                        }
                        lastPendingCount = data.count;
                    })
                    .catch(console.error);
            }
            
            // Check every 30 seconds
            setInterval(checkNewOrders, 30000);
        });
    </script>
</body>
</html>