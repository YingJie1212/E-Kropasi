<?php
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/Product.php";
require_once "../classes/Package.php";
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

$product = new Product();
$package = new Package();
$categoryObj = new Category();
$orderManager = new OrderManager();

if (!isset($_GET['id'])) {
    header("Location: view_package.php");
    exit;
}

$id = $_GET['id'];
$pkg = $package->getById($id);
if (!$pkg) {
    echo "Package not found.";
    exit;
}

// Modified to only get active products
$allProducts = $product->getActiveProductsWithFirstImage();
$categories = $categoryObj->getAll();
$items = $package->getProductsInPackage($id);

$selectedProducts = [];
$quantities = [];

foreach ($items as $item) {
    $selectedProducts[] = $item['product_id'];
    $quantities[$item['product_id']] = $item['quantity'];
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $visible_to = $_POST['visible_to'] ?? 'both';
    $selectedProducts = $_POST['products'] ?? [];
    $quantitiesInput = $_POST['quantities'] ?? [];

    // Collect category IDs from selected products
    $categoryIds = [];
    foreach ($selectedProducts as $productId) {
        foreach ($allProducts as $p) {
            if ($p['id'] == $productId && !empty($p['category_id'])) {
                $categoryIds[] = $p['category_id'];
            }
        }
    }
    $categoryIds = array_unique($categoryIds);
    $category_id = $categoryIds[0] ?? null;

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

        $qty = (int)$quantitiesInput[$index];
        if ($productDetails && $qty > 0) {
            $price += $productDetails['price'] * $qty;
        }
    }

    // Image handling
    $imageName = $pkg['image'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "../uploads/";
        $newImageName = time() . "_" . basename($_FILES['image']['name']);
        $uploadPath = $uploadDir . $newImageName;

        // Handle image upload
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Delete old image if exists
            if (!empty($pkg['image']) && file_exists($uploadDir . $pkg['image'])) {
                unlink($uploadDir . $pkg['image']);
            }

            // Save the new image name
            $imageName = $newImageName;
        } else {
            echo "Failed to upload image.";
        }
    }

    // Update package
    $gender = $_POST['gender'] ?? null;
    if ($gender === '') {
        $gender = null;
    }

    if ($name && !empty($selectedProducts)) {
        $package->update($id, $name, $description, $price, $imageName, $category_id, $visible_to, $gender);
        $package->removeAllCategoriesFromPackage($id);
        foreach ($categoryIds as $catId) {
            $package->addCategory($id, $catId);
        }

        // Update package items
        $package->removeAllItemsFromPackage($id);
        foreach ($selectedProducts as $index => $productId) {
            $qty = (int)$quantitiesInput[$index];
            if ($qty > 0) {
                $package->addItemToPackage($id, $productId, $qty);
            }
        }

        header("Location: view_package.php?package_updated=1");
        exit;
    } else {
        $message = "Please fill all required fields.";
    }
}

// Prepare price map for JS
$priceMap = [];
foreach ($allProducts as $p) {
    $priceMap[$p['id']] = (float)$p['price'];
}

// Count pending orders
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product Package</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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

        /* Secondary Color Palette - Teal/Blue */
        --secondary-50: #ecfdf8;
        --secondary-100: #d1faf0;
        --secondary-200: #a7f3e4;
        --secondary-300: #6ee7d4;
        --secondary-400: #36d1bf;
        --secondary-500: #0ea5a5;
        --secondary-600: #088484;
        --secondary-700: #0f6868;
        --secondary-800: #115555;
        --secondary-900: #134747;

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
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #3b82f6;

        /* Additional accent colors */
        --accent-purple: #8b5cf6;
        --accent-pink: #ec4899;
        
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
        -webkit-text-size-adjust: 100%;
        padding: var(--space-md);
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: var(--white);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        padding: var(--space-lg);
        border: 1px solid var(--gray-200);
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-lg);
        padding-bottom: var(--space-md);
        border-bottom: 1px solid var(--gray-200);
    }

    .breadcrumb {
        font-size: 0.875rem;
        color: var(--gray-600);
    }

    .breadcrumb a {
        color: var(--primary-600);
        text-decoration: none;
        transition: var(--transition);
    }

    .breadcrumb a:hover {
        color: var(--primary-700);
        text-decoration: underline;
    }

    h2 {
        color: var(--primary-700);
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .alert {
        padding: var(--space-sm);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-md);
        background-color: #fef2f2;
        color: var(--danger);
        border-left: 4px solid var(--danger);
    }

    .form-group {
        margin-bottom: var(--space-md);
    }

    label {
        display: block;
        margin-bottom: var(--space-xs);
        font-weight: 500;
        color: var(--gray-700);
    }

    .required:after {
        content: " *";
        color: var(--danger);
    }

    input[type="text"],
    textarea,
    select {
        width: 100%;
        padding: var(--space-sm);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        font-size: 0.9375rem;
        transition: var(--transition);
        font-family: inherit;
    }

    textarea {
        min-height: 100px;
        resize: vertical;
    }

    input:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: var(--primary-300);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: var(--space-md);
        margin-top: var(--space-md);
    }

    .product-card {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        padding: var(--space-md);
        background: var(--white);
        transition: var(--transition);
    }

    .product-card:hover {
        border-color: var(--primary-300);
        box-shadow: var(--shadow-sm);
    }

    .product-header {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        margin-bottom: var(--space-sm);
    }

    .product-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: var(--radius-sm);
        border: 1px solid var(--gray-200);
    }

    .product-info {
        flex: 1;
    }

    .product-name {
        font-weight: 600;
        margin-bottom: var(--space-xs);
        color: var(--gray-900);
    }

    .product-price {
        font-weight: 700;
        color: var(--primary-700);
    }

    .product-options {
        font-size: 0.8125rem;
        color: var(--gray-600);
        margin: var(--space-xs) 0;
    }

    .product-quantity {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        margin-top: var(--space-sm);
    }

    .product-quantity input[type="number"] {
        width: 60px;
        padding: var(--space-xs) var(--space-sm);
    }

    .total-price {
        margin: var(--space-lg) 0;
        padding: var(--space-md);
        background-color: var(--primary-50);
        border-radius: var(--radius-md);
        font-size: 1.125rem;
        font-weight: 700;
        text-align: right;
        color: var(--primary-700);
        border: 1px solid var(--primary-100);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        padding: var(--space-sm) var(--space-md);
        background-color: var(--primary-600);
        color: var(--white);
        border: none;
        border-radius: var(--radius-md);
        cursor: pointer;
        font-weight: 500;
        transition: var(--transition);
        text-decoration: none;
        font-size: 0.9375rem;
        box-shadow: var(--shadow-sm);
    }

    .btn:hover {
        background-color: var(--primary-700);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        margin-top: var(--space-md);
        color: var(--primary-600);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
    }

    .back-link:hover {
        color: var(--primary-700);
        text-decoration: underline;
    }

    .current-image {
        margin-bottom: var(--space-sm);
    }

    .current-image img {
        max-width: 120px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--gray-200);
    }

    /* Checkbox styling */
    input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary-500);
    }

    /* Media Queries for Responsive Design */
    @media (max-width: 992px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
        
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-sm);
        }
        
        .breadcrumb {
            font-size: 0.8125rem;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: var(--space-md);
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: var(--space-sm);
        }
        
        .product-card {
            padding: var(--space-sm);
        }
        
        .product-header {
            flex-direction: row;
            align-items: flex-start;
            gap: var(--space-sm);
        }
        
        /* Smaller product images on mobile */
        .product-image {
            width: 60px;
            height: 60px;
        }
        
        .product-name {
            font-size: 0.9rem;
        }
        
        .product-price {
            font-size: 0.9rem;
        }
        
        .product-options {
            font-size: 0.75rem;
        }
        
        .product-quantity {
            flex-direction: row;
            align-items: center;
            gap: var(--space-xs);
            margin-top: var(--space-sm);
        }
        
        .total-price {
            font-size: 1rem;
            padding: var(--space-sm);
        }
    }

    @media (max-width: 576px) {
        body {
            padding: var(--space-sm);
        }
        
        .product-grid {
            grid-template-columns: 1fr;
        }
        
        h2 {
            font-size: 1.25rem;
        }
        
        .btn, .back-link {
            width: 100%;
            justify-content: center;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: var(--space-sm);
        }
        
        input[type="text"],
        textarea,
        select {
            padding: var(--space-xs) var(--space-sm);
        }
        
        /* Even smaller product boxes on mobile */
        .product-card {
            padding: 0.75rem;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
        }
        
        .product-header {
            gap: 0.5rem;
        }
        
        .product-name {
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        
        .product-price {
            font-size: 0.85rem;
        }
        
        .product-options {
            font-size: 0.7rem;
            margin: 0.1rem 0;
        }
        
        .product-quantity {
            margin-top: 0.5rem;
        }
        
        .product-quantity input[type="number"] {
            width: 50px;
            padding: 0.2rem;
        }
    }

    @media (max-width: 400px) {
        .product-card {
            padding: 0.6rem;
        }
        
        .product-quantity input[type="number"] {
            width: 45px;
        }
        
        .breadcrumb {
            font-size: 0.75rem;
        }
        
        /* Smallest product boxes on extra small screens */
        .product-image {
            width: 45px;
            height: 45px;
        }
        
        .product-name {
            font-size: 0.8rem;
        }
        
        .product-price {
            font-size: 0.8rem;
        }
        
        .product-options {
            font-size: 0.65rem;
        }
        
        .product-quantity label {
            font-size: 0.8rem;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> / <a href="view_package.php">Packages</a> / Edit Package
            </div>
            <h2>Edit Product Package</h2>
        </div>
        

        <?php if (!empty($message)): ?>
            <div class="alert"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="required">Package Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($pkg['name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?= htmlspecialchars($pkg['description']) ?></textarea>
            </div>


            <div class="form-group">
                <label>Package Image</label>
                <?php if (!empty($pkg['image'])): ?>
                    <div class="current-image">
                        <img src="../uploads/<?= htmlspecialchars($pkg['image']) ?>" alt="Current package image">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" id="imageInput">
                <div id="imagePreview" style="margin-top:10px;"></div>
                <script>
                document.getElementById('imageInput').addEventListener('change', function(event) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = '';
                    const file = event.target.files[0];
                    if (file) {
                        const img = document.createElement('img');
                        img.style.maxWidth = '120px';
                        img.style.borderRadius = '6px';
                        img.style.border = '1px solid var(--gray-200)';
                        img.src = URL.createObjectURL(file);
                        preview.appendChild(img);
                    }
                });
                </script>
            </div>

            <div class="form-group">
                <label class="required">Visible To</label>
                <select name="visible_to" required>
                    <option value="both" <?= ($pkg['visible_to'] ?? 'both') === 'both' ? 'selected' : '' ?>>Both Student & Guest</option>
                    <option value="student" <?= ($pkg['visible_to'] ?? '') === 'student' ? 'selected' : '' ?>>Student Only</option>
                    <option value="guest" <?= ($pkg['visible_to'] ?? '') === 'guest' ? 'selected' : '' ?>>Guest Only</option>
                </select>
            </div>

            <div class="form-group">
                <label>Gender (optional)</label>
                <select name="gender">
                    <option value="" <?= empty($pkg['gender']) ? 'selected' : '' ?>>Any</option>
                    <option value="male" <?= ($pkg['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male Only</option>
                    <option value="female" <?= ($pkg['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female Only</option>
                </select>
            </div>

            <h3>Include Products</h3>
            <div class="product-grid">
                <?php
                foreach ($allProducts as $p):
                    $options = $product->getOptionsByProductId($p['id']);
                    $checked = in_array($p['id'], $selectedProducts);
                    $qtyValue = $checked ? $quantities[$p['id']] : 1;
                ?>
                    <div class="product-card">
                        <div class="product-header">
                            <?php if (!empty($p['first_image'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($p['first_image']) ?>" class="product-image" alt="<?= htmlspecialchars($p['name']) ?>">
                            <?php else: ?>
                                <div class="product-image" style="background:var(--gray-100);display:flex;align-items:center;justify-content:center;width:80px;height:80px;border-radius:4px;color:var(--gray-400);">
                                    No Image
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="product-price">RM<?= number_format($p['price'], 2) ?></div>
                                <?php if (!empty($options)): ?>
                                    <div class="product-options">
                                        <?php foreach ($options as $optionGroup): ?>
                                            <div>
                                                <?= htmlspecialchars($optionGroup['group_name']) ?>: 
                                                <?= htmlspecialchars(implode(", ", $optionGroup['values'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="product-quantity">
                            <input type="checkbox" name="products[]" value="<?= $p['id'] ?>" id="prod_<?= $p['id'] ?>" <?= $checked ? 'checked' : '' ?> onchange="updateTotal()">
                            <label for="prod_<?= $p['id'] ?>">Include</label>
                            <input type="number" name="quantities[]" value="<?= $qtyValue ?>" min="1" oninput="updateTotal()" <?= $checked ? '' : 'disabled' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="total-price">
                Total Package Price: RM<span id="totalPrice">0.00</span>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i>
                Save Changes
            </button>

            <a href="view_package.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Packages
            </a>
        </form>
    </div>

    <script id="prices-data" type="application/json"><?= json_encode($priceMap) ?></script>
    <script>
        function updateTotal() {
            const checkboxes = document.querySelectorAll('input[name="products[]"]');
            const quantities = document.querySelectorAll('input[name="quantities[]"]');
            const prices = JSON.parse(document.getElementById("prices-data").textContent);
            let total = 0;

            checkboxes.forEach((box, i) => {
                const qtyInput = quantities[i];
                if (box.checked) {
                    qtyInput.disabled = false;
                    const qty = parseFloat(qtyInput.value || 0);
                    const price = parseFloat(prices[box.value]) || 0;
                    total += qty * price;
                } else {
                    qtyInput.disabled = true;
                }
            });

            document.getElementById("totalPrice").textContent = total.toFixed(2);
        }
        document.addEventListener('DOMContentLoaded', function() {
            updateTotal();
            // Also update total when checkboxes are toggled
            document.querySelectorAll('input[name="products[]"]').forEach(function(box) {
                box.addEventListener('change', updateTotal);
            });

            // 整块区域点击切换 checkbox
            document.querySelectorAll('.product-quantity').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    // 如果点的是输入框或label就不处理
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') return;
                    const checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
        });
    </script>
</body>
</html>