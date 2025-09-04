<?php
require_once "../classes/Product.php";
require_once "../classes/Category.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/ProductPackage.php";
require_once "../classes/Package.php";
require_once "../classes/OrderManager.php";
session_start();

// Access control
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view_products.php?error=invalid_id");
    exit;
}

$id = (int)$_GET['id'];

$product = new Product();
$category = new Category();
$groupObj = new ProductOptionGroup();
$valueObj = new ProductOptionValue();
$productPackageObj = new ProductPackage();
$packageObj = new Package();

// Add this line to get the PDO connection
$pdo = (new DB())->getConnection();

$data = $product->getById($id);
$categories = $category->getAll();
$groups = $groupObj->getByProduct($id);
$productImages = $product->getImagesByProductId($id); // returns array of image info

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $desc = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $cat = $_POST['category_id'] ?? '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $image = $_FILES['image'] ?? null;

    if (empty($name) || empty($price) || empty($cat)) {
        $message = "Please fill in required fields.";
    } else {
        // Handle image
        $imageName = $data['image'];
        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            if ($imageName && file_exists("../uploads/" . $imageName)) {
                unlink("../uploads/" . $imageName); // delete old
            }
            $imageName = time() . "_" . basename($image['name']);
            move_uploaded_file($image['tmp_name'], "../uploads/" . $imageName);
        }

        // Update product with quantity
        $product->update($id, $name, $desc, $price, $imageName, $cat, $quantity);

        // ðŸ‘‡ Handle multiple image uploads
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $key => $imgName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['images']['tmp_name'][$key];
                    $newName = time() . '_' . basename($imgName);
                    move_uploaded_file($tmpName, "../uploads/" . $newName);

                    // Insert into product_images table
                    $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
                    $stmt->execute([$id, $newName]);
                }
            }
        }

        // Handle main image replacement
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
            // Get the first image record
            $firstImage = $productImages[0] ?? null;
            if ($firstImage && file_exists("../uploads/" . $firstImage['image'])) {
                unlink("../uploads/" . $firstImage['image']);
            }
            $mainImageName = time() . "_" . basename($_FILES['main_image']['name']);
            move_uploaded_file($_FILES['main_image']['tmp_name'], "../uploads/" . $mainImageName);

            // Update the first image in product_images table
            if ($firstImage) {
                $stmt = $pdo->prepare("UPDATE product_images SET image = ? WHERE id = ?");
                $stmt->execute([$mainImageName, $firstImage['id']]);
            } else {
                // If no image exists, insert as first image
                $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
                $stmt->execute([$id, $mainImageName]);
            }
        }

        // Clear old groups/values
        foreach ($groups as $g) {
            $valueObj->deleteByGroup($g['id']);
            $groupObj->delete($g['id']);
        }

        // Add new groups/values
        if (!empty($_POST['option_group_name'])) {
            foreach ($_POST['option_group_name'] as $index => $groupName) {
                $groupName = trim($groupName);
                if ($groupName !== "") {
                    $groupId = $groupObj->add($id, $groupName);
                    $groupValues = $_POST["option_group_value_$index"] ?? [];
                    foreach ($groupValues as $val) {
                        $val = trim($val);
                        if ($val !== "") {
                            $valueObj->add($groupId, $val);
                        }
                    }
                }
            }
        }

        // Update packages that include this product
        $packageIds = $productPackageObj->getPackageIdsByProductId($id);
        foreach ($packageIds as $pkgId) {
            // Check if the new category already exists for this package
            $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM package_categories WHERE package_id = ? AND category_id = ?");
            $existsStmt->execute([$pkgId, $cat]);
            $exists = $existsStmt->fetchColumn();

            if (!$exists) {
                // Add the new category to the package
                $insertStmt = $pdo->prepare("INSERT INTO package_categories (package_id, category_id) VALUES (?, ?)");
                $insertStmt->execute([$pkgId, $cat]);
            }
        }

        // After uploading product images, sync to packages that include this product
        $productImages = $product->getImagesByProductId($id); // get latest images
        $packageIds = $productPackageObj->getPackageIdsByProductId($id);

        foreach ($packageIds as $pkgId) {
            // Remove only images in package_images that match any of this product's images
            foreach ($productImages as $img) {
                $stmt = $pdo->prepare("DELETE FROM package_images WHERE package_id = ? AND image_path = ?");
                $stmt->execute([$pkgId, $img['image']]);
            }
            // Add all current product images to the package (if not already present)
            foreach ($productImages as $img) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM package_images WHERE package_id = ? AND image_path = ?");
                $stmt->execute([$pkgId, $img['image']]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO package_images (package_id, image_path) VALUES (?, ?)");
                    $stmt->execute([$pkgId, $img['image']]);
                }
            }
        }

        header("Location: view_products.php?updated=1");
        exit;
    }
}

$orderManager = new OrderManager();
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-light: #f8fff8;
            --secondary-light: #f0fff0;
            --accent-yellow: #fffacd;
            --text-color: #2e4e36;
            --border-color: #c8e0c8;
            --button-color: #4caf50;
            --button-hover: #3e8e41;
            --remove-color: #e67e22;
            --remove-hover: #d35400;
            --success-color: #2e8b57;
            --header-bg: #f0fff0;
            --row-even: #f8fff8;
            --row-odd: #ffffff;
            --highlight-color: #e0ffe0;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--primary-light);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
        }
        
        .page-container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        h1 {
            font-weight: 500;
            color: var(--text-color);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-link {
            color: var(--button-color);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: var(--button-hover);
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-error {
            background-color: #fdecea;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--button-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .image-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            margin-top: 10px;
        }
        
        .option-groups {
            grid-column: 1 / -1;
            margin-top: 20px;
        }
        
        .option-group-title {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .group-block {
            background-color: var(--row-even);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .group-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .group-select {
            flex: 1;
            min-width: 200px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .remove-group-btn {
            background-color: var(--remove-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .remove-group-btn:hover {
            background-color: var(--remove-hover);
        }
        
        .values-container {
            margin-top: 10px;
        }
        
        .value-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .value-input {
            flex: 1;
            min-width: 200px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .remove-value-btn {
            background-color: #e0e0e0;
            color: #333;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .remove-value-btn:hover {
            background-color: #bdbdbd;
        }
        
        .add-value-btn {
            background-color: var(--accent-yellow);
            color: var(--text-color);
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 5px;
            transition: background-color 0.3s;
        }
        
        .add-value-btn:hover {
            background-color: #ffe699;
        }
        
        #add-group-btn {
            background-color: var(--button-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        #add-group-btn:hover {
            background-color: var(--button-hover);
        }
        
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .submit-btn {
            background-color: var(--success-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #2e7d32;
        }

        /* Image upload styles */
        #imageInputsWrapper {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }

        #existingImages {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .image-thumb {
            position: relative;
        }

        .remove-image-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #e74c3c;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #newImagePreview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .page-container {
                padding: 15px;
            }

            .group-select, .value-input {
                min-width: 100%;
            }

            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .submit-btn {
                width: 100%;
            }

            .back-link {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .group-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .remove-group-btn {
                width: 100%;
            }

            .value-row {
                flex-direction: column;
                align-items: stretch;
            }

            .remove-value-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <h1>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit Product
            </h1>
            <a href="view_products.php" class="back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Back to Product List
            </a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="form-grid">
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($data['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $data['category_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Price (RM)</label>
                <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($data['price']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" min="0" value="<?= isset($data['quantity']) ? (int)$data['quantity'] : 0 ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Images</label>
                <input type="file" name="main_image" class="form-control" id="mainImageInput" accept="image/*">
                <div id="imageInputsWrapper"></div>
                <button type="button" id="addMoreImageBtn" style="margin-top:8px;background:var(--accent-yellow);border:none;padding:8px 14px;border-radius:4px;cursor:pointer;">Add More Image</button>
                <div id="existingImages">
                    <?php if (!empty($productImages)): ?>
                        <?php foreach ($productImages as $idx => $img): ?>
                            <?php if ($img['image'] && file_exists("../uploads/" . $img['image'])): ?>
                                <div class="image-thumb" data-image-id="<?= $img['id'] ?>">
                                    <img src="../uploads/<?= htmlspecialchars($img['image']) ?>" class="image-preview" style="width:80px;height:80px;">
                                    <?php if ($idx > 0): ?>
                                        <button type="button" class="remove-image-btn" data-image-id="<?= $img['id'] ?>">&times;</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="newImagePreview"></div>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control"><?= htmlspecialchars($data['description']) ?></textarea>
            </div>
            
            <div class="option-groups">
                <div class="option-group-title">Product Options</div>
                <div id="option-groups">
                    <?php foreach ($groups as $i => $g): ?>
                    <div class="group-block" data-index="<?= $i ?>">
                        <div class="group-header">
                            <select name="option_group_name[]" class="group-select" required>
                                <option value="">-- Select Option Group --</option>
                                <option value="Size" <?= $g['name'] === 'Size' ? 'selected' : '' ?>>Size</option>
                                <option value="Color" <?= $g['name'] === 'Color' ? 'selected' : '' ?>>Color</option>
                                <option value="Material" <?= $g['name'] === 'Material' ? 'selected' : '' ?>>Material</option>
                            </select>
                            <button type="button" class="remove-group-btn">Remove Group</button>
                        </div>
                        <div>
                            <div class="values-container">
                                <?php
                                $values = $valueObj->getByGroup($g['id']);
                                foreach ($values as $vi => $v):
                                ?>
                                <div class="value-row">
                                    <input type="text" name="option_group_value_<?= $i ?>[]" class="value-input" value="<?= htmlspecialchars($v['value']) ?>" required>
                                    <button type="button" class="remove-value-btn">Remove</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="add-value-btn">Add Value</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-group-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Option Group
                </button>
            </div>
            
            <div class="form-actions">
                <a href="view_products.php" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Cancel
                </a>
                <button type="submit" class="submit-btn">Update Product</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Start with the current count from the server (optional fallback to 0)
        let lastPendingCount = window.__pendingOrdersCount || 0;
        const audio = document.getElementById('orderSound');

        // Unlock audio on first user interaction
        function unlockAudio() {
            audio.play().then(() => {
                audio.pause();
                audio.currentTime = 0;
            }).catch(()=>{});
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        }
        window.addEventListener('click', unlockAudio);
        window.addEventListener('touchstart', unlockAudio);

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

        setInterval(checkNewOrders, 1000); // Check every 1 second
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        let groupIndex = <?= count($groups) ?>;

        const optionGroupsContainer = document.getElementById('option-groups');
        const addGroupBtn = document.getElementById('add-group-btn');

        // Function to create a new option group block
        function createGroupBlock(index) {
            const div = document.createElement('div');
            div.className = 'group-block';
            div.setAttribute('data-index', index);

            div.innerHTML = `
                <div class="group-header">
                    <select name="option_group_name[]" class="group-select" required>
                        <option value="">-- Select Option Group --</option>
                        <option value="Size">Size</option>
                        <option value="Color">Color</option>
                        <option value="Material">Material</option>
                    </select>
                    <button type="button" class="remove-group-btn">Remove Group</button>
                </div>
                <div>
                    <div class="values-container">
                        <div class="value-row">
                            <input type="text" name="option_group_value_${index}[]" class="value-input" required>
                            <button type="button" class="remove-value-btn">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="add-value-btn">Add Value</button>
                </div>
            `;
            return div;
        }

        // Add new group
        addGroupBtn.addEventListener('click', () => {
            const newGroup = createGroupBlock(groupIndex);
            optionGroupsContainer.appendChild(newGroup);
            groupIndex++;
        });

        // Delegate clicks inside optionGroupsContainer for dynamic buttons
        optionGroupsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-group-btn')) {
                // Remove the entire group block
                e.target.closest('.group-block').remove();
            }

            if (e.target.classList.contains('add-value-btn')) {
                // Add a new value input inside this group's values container
                const groupBlock = e.target.closest('.group-block');
                const index = groupBlock.getAttribute('data-index');
                const valuesContainer = groupBlock.querySelector('.values-container');

                const valueRow = document.createElement('div');
                valueRow.className = 'value-row';
                valueRow.innerHTML = `
                    <input type="text" name="option_group_value_${index}[]" class="value-input" required>
                    <button type="button" class="remove-value-btn">Remove</button>
                `;
                valuesContainer.appendChild(valueRow);
            }

            if (e.target.classList.contains('remove-value-btn')) {
                // Remove this value input row
                const valueRow = e.target.closest('.value-row');
                const valuesContainer = valueRow.parentElement;
                
                // Don't remove the last value input
                if (valuesContainer.querySelectorAll('.value-row').length > 1) {
                    valueRow.remove();
                }
            }
        });

        // Image handling
        document.getElementById('mainImageInput').addEventListener('change', function(event) {
            const preview = document.getElementById('existingImages');
            const file = event.target.files[0];
            if (file) {
                // Only update the first image preview
                const firstThumb = preview.querySelector('.image-thumb');
                if (firstThumb) {
                    const img = firstThumb.querySelector('img');
                    img.src = URL.createObjectURL(file);
                    // Optionally, add a hidden input to mark this image for replacement
                    firstThumb.setAttribute('data-replace', '1');
                }
            }
        });

        // Remove existing image via AJAX
        document.getElementById('existingImages').addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-image-btn')) {
                const imageId = e.target.getAttribute('data-image-id');
                if (confirm('Remove this image?')) {
                    fetch('remove_product_image.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'image_id=' + encodeURIComponent(imageId)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            e.target.closest('.image-thumb').remove();
                        } else {
                            alert('Failed to remove image.');
                        }
                    });
                }
            }
        });

        // Add more image inputs
        document.getElementById('addMoreImageBtn').addEventListener('click', function() {
            const wrapper = document.getElementById('imageInputsWrapper');
            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'images[]';
            input.className = 'form-control image-input';
            input.style.marginTop = '8px';
            wrapper.appendChild(input);

            input.addEventListener('change', function(event) {
                const preview = document.getElementById('newImagePreview');
                for (let file of event.target.files) {
                    // Create a container for image and remove button
                    const imgWrapper = document.createElement('div');
                    imgWrapper.style.display = 'inline-block';
                    imgWrapper.style.position = 'relative';
                    imgWrapper.style.marginRight = '8px';
                    imgWrapper.style.marginBottom = '8px';

                    // Create the image preview
                    const img = document.createElement('img');
                    img.style.maxWidth = '80px';
                    img.style.maxHeight = '80px';
                    img.style.borderRadius = '4px';
                    img.style.border = '1px solid #e0e0e0';
                    img.src = URL.createObjectURL(file);

                    // Create the remove button
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.textContent = '×';
                    removeBtn.style.position = 'absolute';
                    removeBtn.style.top = '2px';
                    removeBtn.style.right = '2px';
                    removeBtn.style.background = '#e74c3c';
                    removeBtn.style.color = '#fff';
                    removeBtn.style.border = 'none';
                    removeBtn.style.borderRadius = '50%';
                    removeBtn.style.width = '22px';
                    removeBtn.style.height = '22px';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.style.fontSize = '16px';
                    removeBtn.style.lineHeight = '1';

                    // Remove both preview and input when clicked
                    removeBtn.addEventListener('click', function() {
                        imgWrapper.remove();
                        input.remove();
                    });

                    imgWrapper.appendChild(img);
                    imgWrapper.appendChild(removeBtn);
                    preview.appendChild(imgWrapper);
                }
            });
        });
    });
    </script>
</body>
</html>