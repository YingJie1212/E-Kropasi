<?php
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$category = new Category();
$orderManager = new OrderManager();
$message = "";

// Validate ID
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    die("Invalid category ID.");
}

$data = $category->getById($id);
if (!$data) {
    die("Category not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name) {
        // Check if category name already exists (exclude current ID)
        if ($category->existsByName($name, $id)) {
            $message = "<div class='alert alert-error'>❌ Another category with that name already exists.</div>";
        } elseif ($category->update($id, $name)) {
            $message = "<div class='alert alert-success'>✅ Category updated successfully.</div>";
            $data = $category->getById($id); // refresh data
        } else {
            $message = "<div class='alert alert-error'>❌ Failed to update category. Try a different name.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>⚠️ Category name is required.</div>";
    }
}

$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
    <style>
        :root {
            --primary-color: #66BB6A;
            --primary-light: #E8F5E9;
            --primary-dark: #43A047;
            --secondary-color: #26A69A;
            --text-color: #333333;
            --text-light: #757575;
            --border-color: #E0E0E0;
            --background-color: #F5F5F5;
            --card-bg: #FFFFFF;
            --error-color: #EF5350;
            --success-color: #66BB6A;
            --warning-color: #FFA726;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .header {
            padding: 24px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 500;
        }

        .breadcrumb {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .content {
            padding: 24px;
        }

        .form-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(102, 187, 106, 0.2);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn svg {
            margin-right: 8px;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary-color);
            text-decoration: none;
            margin-top: 24px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .back-link svg {
            margin-right: 8px;
        }

        .alert {
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            font-size: 15px;
        }

        .alert-error {
            background-color: rgba(239, 83, 80, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert-success {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-warning {
            background-color: rgba(255, 167, 38, 0.1);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        .availability-message {
            font-size: 14px;
            margin-top: 8px;
            padding: 4px 0;
        }

        .text-success {
            color: var(--success-color);
        }

        .text-error {
            color: var(--error-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding: 16px;
            }

            .content {
                padding: 16px;
            }

            .form-container {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                border-radius: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Category</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> &raquo; <a href="view_categories.php">Categories</a> &raquo; Edit
            </div>
        </div>

        <div class="content">
            <?= $message ?>

            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?= htmlspecialchars($data['name']) ?>" 
                               required 
                               placeholder="Enter category name">
                        <div id="availability-message" class="availability-message"></div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-block">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                            Update Category
                        </button>
                    </div>
                </form>

                <a href="view_categories.php" class="back-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Back to Categories
                </a>
            </div>
        </div>
    </div>

    <audio id="orderSound" src="../sound/notification.mp3" preload="auto"></audio>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastPendingCount = <?= $pendingOrdersCount ?> || 0;
        const audio = document.getElementById('orderSound');

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

        setInterval(checkNewOrders, 10000); // Check every 10 seconds instead of 1 to reduce load
    });
    </script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const nameInput = document.getElementById('name');
        const submitBtn = document.querySelector('button[type="submit"]');
        const messageEl = document.getElementById('availability-message');
        
        let timeout = null;

        function checkNameAvailability() {
            const name = nameInput.value.trim();
            if (!name) {
                messageEl.textContent = '';
                messageEl.className = 'availability-message';
                submitBtn.disabled = false;
                return;
            }

            let url = 'check_category.php?name=' + encodeURIComponent(name) + '&exclude_id=<?= $id ?>';

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'exists') {
                        messageEl.textContent = '⚠️ This category name already exists.';
                        messageEl.className = 'availability-message text-error';
                        submitBtn.disabled = true;
                    } else if (data.status === 'available') {
                        messageEl.textContent = '✓ This name is available.';
                        messageEl.className = 'availability-message text-success';
                        submitBtn.disabled = false;
                    } else {
                        messageEl.textContent = '';
                        messageEl.className = 'availability-message';
                        submitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    messageEl.textContent = '';
                    messageEl.className = 'availability-message';
                    submitBtn.disabled = false;
                });
        }

        nameInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(checkNameAvailability, 500);
        });

        // Check on initial load if there's a value
        if (nameInput.value.trim()) {
            checkNameAvailability();
        }
    });
    </script>
</body>
</html>