<?php
require_once "../classes/Category.php";
require_once "../classes/OrderManager.php";
session_start();

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

$category = new Category();
$orderManager = new OrderManager();
$message = "";

// Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name) {
        if ($category->existsByName($name)) {
            $message = "<div class='notification notification--error'>
                            <div class='notification__icon'>
                                <svg viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>
                            </div>
                            <div class='notification__content'>
                                <h4>Category Exists</h4>
                                <p>The specified category name already exists in the system.</p>
                            </div>
                        </div>";
        } elseif ($category->add($name)) {
            $message = "<div class='notification notification--success'>
                            <div class='notification__icon'>
                                <svg viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z'/></svg>
                            </div>
                            <div class='notification__content'>
                                <h4>Category Added</h4>
                                <p>The new category has been successfully created.</p>
                            </div>
                        </div>";
        } else {
            $message = "<div class='notification notification--error'>
                            <div class='notification__icon'>
                                <svg viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>
                            </div>
                            <div class='notification__content'>
                                <h4>Operation Failed</h4>
                                <p>Unable to add the category due to a system error.</p>
                            </div>
                        </div>";
        }
    } else {
        $message = "<div class='notification notification--warning'>
                        <div class='notification__icon'>
                            <svg viewBox='0 0 24 24'><path d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z'/></svg>
                        </div>
                        <div class='notification__content'>
                            <h4>Validation Error</h4>
                            <p>Category name is a required field.</p>
                        </div>
                    </div>";
    }
}

$pendingOrdersCount = $orderManager->countOrdersByStatus('Pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category | Admin Console</title>
    <style>
        :root {
            /* Color System - Updated with lighter green */
            --primary: #4CAF50; /* Lighter green */
            --primary-hover: #388E3C; /* Slightly darker for hover */
            --primary-light: #E8F5E9;
            --text-primary: #263238;
            --text-secondary: #455A64;
            --text-tertiary: #78909C;
            --border: #CFD8DC;
            --border-light: #ECEFF1;
            --background: #F5F7FA;
            --surface: #FFFFFF;
            --error: #C62828;
            --warning: #F9A825;
            --success: #4CAF50; /* Matching lighter green */
            
            /* Spacing System */
            --space-xxs: 4px;
            --space-xs: 8px;
            --space-sm: 12px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --space-xxl: 48px;
            
            /* Typography */
            --font-size-sm: 13px;
            --font-size-md: 14px;
            --font-size-lg: 16px;
            --font-size-xl: 20px;
            
            /* Borders */
            --radius-sm: 2px;
            --radius-md: 4px;
            
            /* Elevation */
            --elevation-1: 0 1px 3px rgba(0,0,0,0.05);
            --elevation-2: 0 4px 6px rgba(0,0,0,0.08);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: var(--font-size-md);
            padding: var(--space-xxl);
        }

        .admin-console {
            max-width: 720px;
            margin: 0 auto;
            background-color: var(--surface);
            border-radius: var(--radius-md);
            box-shadow: var(--elevation-2);
            overflow: hidden;
        }

        .console-header {
            padding: var(--space-xl);
            background-color: var(--surface);
            border-bottom: 1px solid var(--border-light);
        }

        .console-title {
            display: flex;
            align-items: center;
            font-size: var(--font-size-xl);
            font-weight: 500;
            color: var(--text-primary);
        }

        .console-title__icon {
            margin-right: var(--space-sm);
            color: var(--primary);
        }

        .console-body {
            padding: var(--space-xl);
        }

        .form-section {
            margin-bottom: var(--space-xxl);
        }

        .form-section__title {
            font-size: var(--font-size-lg);
            font-weight: 500;
            margin-bottom: var(--space-lg);
            color: var(--text-primary);
            padding-bottom: var(--space-sm);
            border-bottom: 1px solid var(--border-light);
        }

        .form-group {
            margin-bottom: var(--space-xl);
        }

        .form-label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 500;
            color: var(--text-secondary);
            font-size: var(--font-size-md);
        }

        .form-control {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-md);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--surface);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1); /* Updated to match new green */
        }

        .form-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-xxl);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-sm) var(--space-lg);
            font-weight: 500;
            font-size: var(--font-size-md);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            border: none;
        }

        .btn--primary {
            background-color: var(--primary);
            color: var(--surface);
        }

        .btn--primary:hover {
            background-color: var(--primary-hover);
        }

        .btn--secondary {
            background-color: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .btn--secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn__icon {
            margin-right: var(--space-xs);
        }

        .notification {
            display: flex;
            padding: var(--space-md);
            margin-bottom: var(--space-xl);
            border-radius: var(--radius-sm);
            border-left: 4px solid transparent;
        }

        .notification--error {
            background-color: #FFEBEE;
            border-left-color: var(--error);
        }

        .notification--success {
            background-color: var(--primary-light);
            border-left-color: var(--success);
        }

        .notification--warning {
            background-color: #FFF8E1;
            border-left-color: var(--warning);
        }

        .notification__icon {
            margin-right: var(--space-md);
            flex-shrink: 0;
        }

        .notification__icon svg {
            width: 24px;
            height: 24px;
        }

        .notification__content h4 {
            font-weight: 500;
            margin-bottom: var(--space-xxs);
        }

        .notification__content p {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }

        .form-hint {
            font-size: var(--font-size-sm);
            color: var(--text-tertiary);
            margin-top: var(--space-xxs);
            font-style: italic;
        }

        @media (max-width: 768px) {
            body {
                padding: var(--space-md);
            }
            
            .admin-console {
                border-radius: 0;
            }
            
            .console-header,
            .console-body {
                padding: var(--space-lg);
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-console">
        <div class="console-header">
            <h1 class="console-title">
                <span class="console-title__icon">
                    <svg viewBox="0 0 24 24" width="24" height="24"><path d='M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z'/></svg>
                </span>
                Create New Category
            </h1>
        </div>
        
        <div class="console-body">
            <?= $message ?>
            
            <div class="form-section">
                <h2 class="form-section__title">Category Information</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required placeholder="Enter a descriptive category name">
                        <p id="availability-message" class="form-hint">This name will be used throughout the system</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn--primary">
                            <span class="btn__icon">
                                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            </span>
                            Create Category
                        </button>
                        
                        <a href="view_categories.php" class="btn btn--secondary">
                            <span class="btn__icon">
                                <svg viewBox="0 0 24 24" width="18" height="18"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                            </span>
                            Cancel
                        </a>
                    </div>
                </form>
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

        setInterval(checkNewOrders, 1000);
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
                messageEl.textContent = 'This name will be used throughout the system';
                messageEl.style.color = 'var(--text-tertiary)';
                submitBtn.disabled = false;
                return;
            }

            let url = 'check_category.php?name=' + encodeURIComponent(name);

            const urlParams = new URLSearchParams(window.location.search);
            const excludeId = urlParams.get('id');
            if (excludeId) {
                url += '&exclude_id=' + excludeId;
            }

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'exists') {
                        messageEl.textContent = 'This category name is already in use';
                        messageEl.style.color = 'var(--error)';
                        submitBtn.disabled = true;
                    } else if (data.status === 'available') {
                        messageEl.textContent = 'This name is available';
                        messageEl.style.color = 'var(--success)';
                        submitBtn.disabled = false;
                    } else {
                        messageEl.textContent = 'This name will be used throughout the system';
                        messageEl.style.color = 'var(--text-tertiary)';
                        submitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    messageEl.textContent = 'This name will be used throughout the system';
                    messageEl.style.color = 'var(--text-tertiary)';
                    submitBtn.disabled = false;
                });
        }

        nameInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(checkNameAvailability, 500);
        });
    });
    </script>
</body>
</html>