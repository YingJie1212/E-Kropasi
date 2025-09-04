<?php
require_once "../classes/Product.php";
require_once "../classes/ProductOptionGroup.php";
require_once "../classes/ProductOptionValue.php";
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

// Access control
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$product = new Product();
$groupObj = new ProductOptionGroup();
$valueObj = new ProductOptionValue();
$category = new Category();
$orderManager = new OrderManager();

$categories = $category->getAll();
$message = "";
$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if (empty($name) || empty($price) || empty($category_id)) {
        $message = "<div class='alert alert-danger'>Please fill in all required fields.</div>";
    } else {
        $imageNames = [];
        $pdfNames = [];
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $originalName = $_FILES['images']['name'][$key];
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $newName = time() . "_" . basename($originalName);
                    $targetPath = "../uploads/" . $newName;
                    if (move_uploaded_file($tmp_name, $targetPath)) {
                        if ($ext === 'pdf') {
                            $pdfNames[] = $newName;
                        } else {
                            $imageNames[] = $newName;
                        }
                    }
                }
            }
        }

        $mainImage = $imageNames[0] ?? null;
        $newId = $product->add($name, $description, $price, $mainImage, $category_id, $quantity);

        // Add option groups + values
        if (!empty($_POST['option_group_name'])) {
            foreach ($_POST['option_group_name'] as $index => $groupName) {
                $groupName = trim($groupName);
                if ($groupName !== "") {
                    $groupId = $groupObj->add($newId, $groupName);
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

        // Save images
        if (!empty($imageNames)) {
            foreach ($imageNames as $img) {
                $product->addProductImage($newId, $img);
            }
        }

        // Save PDFs
        if (!empty($pdfNames)) {
            foreach ($pdfNames as $pdf) {
                $product->addProductPdf($newId, $pdf);
            }
        }

        header("Location: view_products.php?added=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #66BB6A; /* Light green */
            --primary-dark: #57A85A;
            --primary-light: #E8F5E9;
            --secondary: #81C784; /* Complementary light green */
            --success: #66BB6A;
            --danger: #EF5350;
            --warning: #FFA726;
            --info: #42A5F5;
            --light: #F5F5F5;
            --dark: #263238;
            --gray: #90A4AE;
            --gray-light: #CFD8DC;
            --white: #FFFFFF;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F1F8E9;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(102, 187, 106, 0.3);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-dark);
            position: relative;
            padding-left: 1rem;
        }

        .header h1::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: var(--primary);
            border-radius: 4px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .breadcrumb a {
            color: var(--primary-dark);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .breadcrumb a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .breadcrumb i {
            margin: 0 0.5rem;
            font-size: 0.7rem;
            color: var(--gray);
        }

        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            border: 1px solid rgba(102, 187, 106, 0.2);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 6px 16px rgba(102, 187, 106, 0.15);
        }

        .card-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--primary-light);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }

        .alert {
            padding: 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            box-shadow: var(--box-shadow);
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .alert-danger {
            background-color: #FFEBEE;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background-color: #E8F5E9;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .form-label.required::after {
            content: " *";
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1.25rem;
            font-size: 0.95rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            transition: var(--transition);
            background-color: var(--light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 187, 106, 0.25);
            background-color: var(--white);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .file-upload-wrapper {
            margin-bottom: 1.5rem;
        }

        .file-upload-input {
            display: none;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: var(--primary-light);
            border: 2px dashed var(--primary);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            justify-content: center;
            flex-direction: column;
            text-align: center;
            min-height: 120px;
        }

        .file-upload-label:hover {
            background-color: rgba(102, 187, 106, 0.1);
            border-color: var(--primary-dark);
        }

        .file-upload-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 0.5rem;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .image-preview-item {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--gray-light);
            height: 100px;
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview-item-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 1.5rem;
            height: 1.5rem;
            background-color: var(--danger);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            cursor: pointer;
            opacity: 0;
            transition: var(--transition);
        }

        .image-preview-item:hover .image-preview-item-remove {
            opacity: 1;
        }

        .options-container {
            margin-top: 2.5rem;
        }

        .option-group {
            background-color: var(--primary-light);
            border-radius: var(--border-radius);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .option-values-container {
            margin-top: 1.5rem;
        }

        .option-value-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .option-value-input {
            flex: 1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.9rem 1.75rem;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn i {
            margin-right: 0.75rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 187, 106, 0.3);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: #6BB46E;
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #E53935;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.6rem 1.25rem;
            font-size: 0.85rem;
        }

        .btn-icon {
            width: 2.25rem;
            height: 2.25rem;
            padding: 0;
            border-radius: 50%;
        }

        .form-actions {
            display: flex;
            gap: 1.25rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2390A4AE' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }

        /* PDF-specific styling */
        .pdf-preview {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            padding: 1rem;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            width: 100%;
        }
        
        .pdf-preview i {
            font-size: 2rem;
            color: #e74c3c;
            margin-right: 1rem;
        }
        
        .pdf-preview-info {
            flex: 1;
        }
        
        .pdf-preview-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            word-break: break-word;
        }
        
        .pdf-preview-size {
            font-size: 0.8rem;
            color: var(--gray);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .container {
                padding: 1.75rem;
            }
            
            .card {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .form-actions, .button-group {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .card {
                padding: 1.75rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1.25rem;
                padding-top: 1.5rem;
                padding-bottom: 3rem;
                max-width: 100%;
                width: 100%;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .option-value-row {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plus-circle"></i> Add New Product</h1>
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="view_products.php">Products</a>
                <i class="fas fa-chevron-right"></i>
                <span>Add Product</span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'error') !== false ? 'alert-danger' : 'alert-success' ?>">
                <i class="fas <?= strpos($message, 'error') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-info-circle"></i> Basic Information</h2>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name" class="form-label required">Product Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id" class="form-label required">Category</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="price" class="form-label required">Price</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity" class="form-label required">Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="0" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control"></textarea>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-images"></i> Product Images & PDFs</h2>
                </div>
                
                <div id="imageInputs">
                    <div class="file-upload-wrapper">
                        <input type="file" name="images[]" id="productImage_0" class="file-upload-input" accept="image/*,.pdf" multiple>
                        <label for="productImage_0" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Drag & drop your images or PDFs here or click to browse</span>
                            <span class="file-name" id="fileName_0">No files selected</span>
                        </label>
                        <div class="image-preview-container" id="imagePreview_0"></div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" id="addMoreImageBtn">
                    <i class="fas fa-plus"></i> Add More Files
                </button>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-cogs"></i> Product Options</h2>
                </div>
                
                <div id="optionGroups">
                    <div class="option-group">
                        <div class="form-group">
                            <label class="form-label">Option Group</label>
                            <select name="option_group_name[]" class="form-control">
                                <option value="">-- Select Option Group --</option>
                                <option value="Size">Size</option>
                                <option value="Color">Color</option>
                                <option value="Material">Material</option>
                                <option value="Style">Style</option>
                            </select>
                        </div>
                        
                        <div class="option-values-container">
                            <label class="form-label">Option Values</label>
                            <div class="option-value-row">
                                <div class="option-value-input">
                                    <input type="text" name="option_group_value_0[]" class="form-control" placeholder="Value (e.g. Small)">
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addValue(this)">
                            <i class="fas fa-plus"></i> Add Value
                        </button>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="addOptionGroup()">
                        <i class="fas fa-plus"></i> Add Option Group
                    </button>
                    <a href="view_products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    let groupCount = 1;
    let imageInputCount = 1;

    // Add new option group
    function addOptionGroup() {
        const groupHTML = `
            <div class="option-group">
                <div class="form-group">
                    <label class="form-label">Option Group</label>
                    <select name="option_group_name[]" class="form-control">
                        <option value="">-- Select Option Group --</option>
                        <option value="Size">Size</option>
                        <option value="Color">Color</option>
                        <option value="Material">Material</option>
                        <option value="Style">Style</option>
                    </select>
                </div>
                
                <div class="option-values-container">
                    <label class="form-label">Option Values</label>
                    <div class="option-value-row">
                        <div class="option-value-input">
                            <input type="text" name="option_group_value_${groupCount}[]" class="form-control" placeholder="Value (e.g. Small)">
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary btn-sm" onclick="addValue(this)">
                    <i class="fas fa-plus"></i> Add Value
                </button>
            </div>`;
        document.getElementById("optionGroups").insertAdjacentHTML("beforeend", groupHTML);
        groupCount++;
    }
    
    // Add new value to option group
    function addValue(btn) {
        const valuesContainer = btn.closest('.option-group').querySelector('.option-values-container');
        const name = valuesContainer.querySelector('input').getAttribute('name');
        const match = name.match(/option_group_value_(\d+)\[]/);
        if (match) {
            const groupIndex = match[1];
            const valueHTML = `
                <div class="option-value-row">
                    <div class="option-value-input">
                        <input type="text" name="option_group_value_${groupIndex}[]" class="form-control" placeholder="Value (e.g. Medium)">
                    </div>
                </div>`;
            valuesContainer.insertAdjacentHTML("beforeend", valueHTML);
        }
    }

    // Add more image inputs
    document.getElementById('addMoreImageBtn').addEventListener('click', function() {
        const container = document.getElementById('imageInputs');
        const inputId = 'productImage_' + imageInputCount;
        
        const fileUploadHTML = `
            <div class="file-upload-wrapper">
                <input type="file" name="images[]" id="${inputId}" class="file-upload-input" accept="image/*,.pdf" multiple>
                <label for="${inputId}" class="file-upload-label">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Drag & drop your images or PDFs here or click to browse</span>
                    <span class="file-name" id="fileName_${imageInputCount}">No files selected</span>
                </label>
                <div class="image-preview-container" id="imagePreview_${imageInputCount}"></div>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeImageInput(this)">
                    <i class="fas fa-trash"></i> Remove This Upload
                </button>
            </div>`;
        
        container.insertAdjacentHTML('beforeend', fileUploadHTML);
        setupFileInput(inputId);
        imageInputCount++;
    });
    
    // Remove image input
    function removeImageInput(btn) {
        btn.closest('.file-upload-wrapper').remove();
    }
    
    // Helper function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Setup file input change event
    function setupFileInput(inputId) {
        const fileInput = document.getElementById(inputId);
        const fileNameSpan = document.getElementById('fileName_' + inputId.split('_')[1]);
        const previewDiv = document.getElementById('imagePreview_' + inputId.split('_')[1]);
        
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length) {
                const fileNames = Array.from(fileInput.files).map(file => file.name).join(', ');
                fileNameSpan.textContent = fileNames;
                previewDiv.innerHTML = '';
                
                Array.from(fileInput.files).forEach(file => {
                    if (file.type.startsWith('image/')) {
                        // Handle image previews
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'image-preview-item';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            
                            const removeBtn = document.createElement('div');
                            removeBtn.className = 'image-preview-item-remove';
                            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                            removeBtn.addEventListener('click', function() {
                                previewItem.remove();
                            });
                            
                            previewItem.appendChild(img);
                            previewItem.appendChild(removeBtn);
                            previewDiv.appendChild(previewItem);
                        };
                        reader.readAsDataURL(file);
                    } else if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                        // Handle PDF previews
                        const previewItem = document.createElement('div');
                        previewItem.className = 'pdf-preview';
                        
                        const pdfIcon = document.createElement('i');
                        pdfIcon.className = 'fas fa-file-pdf';
                        
                        const pdfInfo = document.createElement('div');
                        pdfInfo.className = 'pdf-preview-info';
                        
                        const pdfName = document.createElement('div');
                        pdfName.className = 'pdf-preview-name';
                        pdfName.textContent = file.name;
                        
                        const pdfSize = document.createElement('div');
                        pdfSize.className = 'pdf-preview-size';
                        pdfSize.textContent = formatFileSize(file.size);
                        
                        const removeBtn = document.createElement('div');
                        removeBtn.className = 'image-preview-item-remove';
                        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                        removeBtn.addEventListener('click', function() {
                            previewItem.remove();
                        });
                        
                        pdfInfo.appendChild(pdfName);
                        pdfInfo.appendChild(pdfSize);
                        previewItem.appendChild(pdfIcon);
                        previewItem.appendChild(pdfInfo);
                        previewItem.appendChild(removeBtn);
                        previewDiv.appendChild(previewItem);
                    }
                });
            } else {
                fileNameSpan.textContent = 'No files selected';
                previewDiv.innerHTML = '';
            }
        });
    }
    
    // Initialize first file input
    document.addEventListener('DOMContentLoaded', function() {
        setupFileInput('productImage_0');
    });
    </script>
</body>
</html>