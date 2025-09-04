<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../classes/DB.php";
require_once "../classes/BankAccount.php";
require_once "../classes/OrderManager.php";

// Initialize DB and BankAccount
$db = new DB();
$conn = $db->getConnection();
$bankAccount = new BankAccount($conn);
$orderManager = new OrderManager();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    try {
        // Update bank information
        if (isset($_POST['update_bank'])) {
            $id = 1;
            $bank = $bankAccount->getById($id);
            
            $phone_number = trim($_POST['phone_number'] ?? '');
            $gmail_app_password = trim($_POST['gmail_app_password'] ?? '');

            $updateData = [
                'bank_name' => trim($_POST['bank_name'] ?? ''),
                'account_number' => trim($_POST['account_number'] ?? ''),
                'account_holder' => trim($_POST['account_holder'] ?? ''),
                'school_email' => trim($_POST['school_email'] ?? ''),
                'phone_number' => $phone_number
            ];

            if (!empty($gmail_app_password)) {
                $updateData['gmail_app_password'] = $gmail_app_password;
            }

            if ($bankAccount->update($id, $updateData)) {
                $response = ['success' => true, 'message' => 'Bank information updated successfully!'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update bank information.'];
            }
        }
        // Generic handler for method updates (payment, guest payment, counter payment)
        elseif (isset($_POST['update_methods'])) {
            $type = $_POST['method_type'] ?? '';
            $tableMap = [
                'payment' => ['table' => 'payment_methods', 'prefix' => 'payment_method'],
                'guest_payment' => ['table' => 'guest_payment_methods', 'prefix' => 'guest_payment_method'],
                'counter_payment' => ['table' => 'counter_payment_methods', 'prefix' => 'counter_payment_method'],
                'delivery' => ['table' => 'delivery_options', 'prefix' => 'delivery_option'],
                'guest_delivery' => ['table' => 'guest_delivery_options', 'prefix' => 'guest_delivery_option'],
                'counter_delivery' => ['table' => 'counter_delivery_options', 'prefix' => 'counter_delivery_option']
            ];
            
            if (!array_key_exists($type, $tableMap)) {
                $response = ['success' => false, 'message' => 'Invalid method type'];
                echo json_encode($response);
                exit;
            }

            $config = $tableMap[$type];
            $table = $config['table'];
            $prefix = $config['prefix'];
            $ids = $_POST['method_id'] ?? [];
            // Use correct input name for delivery types
            if ($type === 'payment' || $type === 'guest_payment' || $type === 'counter_payment') {
                $names = $_POST['method_name'] ?? [];
            } else {
                $names = $_POST['option_name'] ?? [];
            }
            $fees = $_POST['fee'] ?? [];
            $updated = 0;

            foreach ($ids as $i => $id) {
                $id = intval($id);
                $name = trim($names[$i] ?? '');
                $fee = isset($fees[$i]) ? floatval($fees[$i]) : 0.00;
                
                // Check for duplicate name in active records (excluding current ID)
                $nameColumn = ($type === 'payment' || $type === 'guest_payment' || $type === 'counter_payment') ? 'method_name' : 'option_name';
                $stmt = $conn->prepare("SELECT id FROM $table WHERE $nameColumn = :name AND id != :id AND is_active = 1");
                $stmt->execute([':name' => $name, ':id' => $id]);
                $duplicate = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($duplicate) {
                    $response = ['success' => false, 'message' => "The name '$name' is already in use by another active method."];
                    echo json_encode($response);
                    exit;
                }
                
                // Handle image upload for payment methods
                $image = null;
                if (strpos($type, 'payment') !== false) {
                    $imgField = 'method_image_' . $id;
                    if (!empty($_FILES[$imgField]['name']) && $_FILES[$imgField]['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES[$imgField]['name'], PATHINFO_EXTENSION);
                        $filename = $prefix . '_' . $id . '_' . time() . '.' . $ext;
                        $target = "../uploads/$filename";
                        
                        if (move_uploaded_file($_FILES[$imgField]['tmp_name'], $target)) {
                            $image = $filename;
                        }
                    }
                }

                if (strpos($type, 'payment') !== false) {
                    if ($image) {
                        $stmt = $conn->prepare("UPDATE $table SET method_name=?, fee=?, image=? WHERE id=?");
                        $ok = $stmt->execute([$name, $fee, $image, $id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE $table SET method_name=?, fee=? WHERE id=?");
                        $ok = $stmt->execute([$name, $fee, $id]);
                    }
                } else {
                    // For delivery options
                    $stmt = $conn->prepare("UPDATE $table SET option_name=?, fee=? WHERE id=?");
                    $ok = $stmt->execute([$name, $fee, $id]);
                }

                if ($ok) $updated++;
            }

            $response = ['success' => true, 'message' => "Updated $updated $type method(s)."];
        }
        // Generic handler for adding methods
        elseif (isset($_POST['add_method'])) {
            $type = $_POST['method_type'] ?? '';
            $tableMap = [
                'payment' => ['table' => 'payment_methods', 'prefix' => 'payment_method', 'has_image' => true, 'name_column' => 'method_name'],
                'guest_payment' => ['table' => 'guest_payment_methods', 'prefix' => 'guest_payment_method', 'has_image' => true, 'name_column' => 'method_name'],
                'counter_payment' => ['table' => 'counter_payment_methods', 'prefix' => 'counter_payment_method', 'has_image' => true, 'name_column' => 'method_name'],
                'delivery' => ['table' => 'delivery_options', 'prefix' => 'delivery_option', 'has_image' => false, 'name_column' => 'option_name'],
                'guest_delivery' => ['table' => 'guest_delivery_options', 'prefix' => 'guest_delivery_option', 'has_image' => false, 'name_column' => 'option_name'],
                'counter_delivery' => ['table' => 'counter_delivery_options', 'prefix' => 'counter_delivery_option', 'has_image' => false, 'name_column' => 'option_name']
            ];
            
            if (!array_key_exists($type, $tableMap)) {
                $response = ['success' => false, 'message' => 'Invalid method type'];
                echo json_encode($response);
                exit;
            }

            $config = $tableMap[$type];
            $table = $config['table'];
            $prefix = $config['prefix'];
            $hasImage = $config['has_image'];
            $nameColumn = $config['name_column'];
            
            $name = trim($_POST['new_method_name'] ?? '');
            $fee = isset($_POST['new_fee']) ? floatval($_POST['new_fee']) : 0.00;
            $image = null;

            // Check if name already exists (active or inactive)
            $stmt = $conn->prepare("SELECT id, is_active FROM $table WHERE $nameColumn = :name");
            $stmt->execute([':name' => $name]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['is_active'] == 0) {
                    // Reactivate the existing inactive record
                    if ($hasImage && !empty($_FILES['new_method_image']['name']) && $_FILES['new_method_image']['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES['new_method_image']['name'], PATHINFO_EXTENSION);
                        $filename = $prefix . '_new_' . time() . '.' . $ext;
                        $target = "../uploads/$filename";
                        
                        if (move_uploaded_file($_FILES['new_method_image']['tmp_name'], $target)) {
                            $image = $filename;
                        }
                    }

                    if ($hasImage) {
                        $stmt = $conn->prepare("UPDATE $table SET fee = ?, image = ?, is_active = 1 WHERE id = ?");
                        $result = $stmt->execute([$fee, $image, $existing['id']]);
                    } else {
                        $stmt = $conn->prepare("UPDATE $table SET fee = ?, is_active = 1 WHERE id = ?");
                        $result = $stmt->execute([$fee, $existing['id']]);
                    }
                    
                    if ($result) {
                        $response = [
                            'success' => true,
                            'message' => 'Reactivated existing method with this name.',
                            'method' => [
                                'id' => $existing['id'],
                                'name' => $name,
                                'fee' => $fee,
                                'image' => $image
                            ]
                        ];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to reactivate existing method.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Sorry, this name is already taken by an active method.'];
                }
                echo json_encode($response);
                exit;
            }

            // No existing record found, create new one
            if ($name) {
                if ($hasImage && !empty($_FILES['new_method_image']['name']) && $_FILES['new_method_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['new_method_image']['name'], PATHINFO_EXTENSION);
                    $filename = $prefix . '_new_' . time() . '.' . $ext;
                    $target = "../uploads/$filename";
                    
                    if (move_uploaded_file($_FILES['new_method_image']['tmp_name'], $target)) {
                        $image = $filename;
                    }
                }

                if ($hasImage) {
                    $stmt = $conn->prepare("INSERT INTO $table (method_name, fee, image, is_active) VALUES (?, ?, ?, 1)");
                    $result = $stmt->execute([$name, $fee, $image]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO $table (option_name, fee, is_active) VALUES (?, ?, 1)");
                    $result = $stmt->execute([$name, $fee]);
                }
                
                if ($result) {
                    $id = $conn->lastInsertId();
                    $response = [
                        'success' => true,
                        'message' => ucfirst(str_replace('_', ' ', $type)) . ' method added.',
                        'method' => [
                            'id' => $id,
                            'name' => $name,
                            'fee' => $fee,
                            'image' => $image
                        ]
                    ];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to add method.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Method name required.'];
            }
        }
        // Generic handler for deleting methods
        elseif (isset($_POST['delete_method'])) {
            $type = $_POST['method_type'] ?? '';
            $tableMap = [
                'payment' => 'payment_methods',
                'guest_payment' => 'guest_payment_methods',
                'counter_payment' => 'counter_payment_methods',
                'delivery' => 'delivery_options',
                'guest_delivery' => 'guest_delivery_options',
                'counter_delivery' => 'counter_delivery_options'
            ];
            
            if (!array_key_exists($type, $tableMap)) {
                $response = ['success' => false, 'message' => 'Invalid method type'];
                echo json_encode($response);
                exit;
            }

            $table = $tableMap[$type];
            $id = intval($_POST['method_id']);
            $stmt = $conn->prepare("UPDATE $table SET is_active = 0 WHERE id = ?");
            if ($stmt->execute([$id])) {
                $response = ['success' => true, 'message' => ucfirst(str_replace('_', ' ', $type)) . " method deactivated!"];
            } else {
                $response = ['success' => false, 'message' => "Failed to deactivate method."];
            }
        }

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
}

// Fetch bank information (default ID is 1)
$id = 1;
$bank = $bankAccount->getById($id);

// Generic function to fetch active methods
function fetchActiveMethods($conn, $table, $isPayment = false) {
    $columns = $isPayment ? '*' : 'id, option_name as method_name, fee, is_active';
    $stmt = $conn->query("SELECT $columns FROM $table WHERE is_active = 1");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Fetch all active methods
$paymentMethods = fetchActiveMethods($conn, 'payment_methods', true);
$deliveryOptions = fetchActiveMethods($conn, 'delivery_options');
$guestDeliveryOptions = fetchActiveMethods($conn, 'guest_delivery_options');
$guestPaymentMethods = fetchActiveMethods($conn, 'guest_payment_methods', true);
$counterDeliveryOptions = fetchActiveMethods($conn, 'counter_delivery_options');
$counterPaymentMethods = fetchActiveMethods($conn, 'counter_payment_methods', true);

// Get pending orders count for notification
$pendingOrdersCount = 0;
$stmt = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
if ($stmt) {
    $pendingOrdersCount = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Information Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f8f9e8;
            --secondary-color: #f5f7e8;
            --accent-color: #d8eacc;
            --dark-accent: #a8c8a0;
            --text-color: #4a5c42;
            --light-text: #6c7c65;
            --border-color: #e0e6d6;
            --shadow-color: rgba(168, 200, 160, 0.1);
            --success-color: #8bc34a;
            --error-color: #f44336;
            --warning-color: #ff9800;
            --border-radius: 6px;
            --box-shadow: 0 4px 12px var(--shadow-color);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 15px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--text-color);
        }

        .header h1 i {
            margin-right: 10px;
            color: var(--dark-accent);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 18px;
            background-color: var(--accent-color);
            color: var(--text-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--border-color);
        }

        .back-btn:hover {
            background-color: var(--dark-accent);
            color: white;
            transform: translateY(-2px);
        }

        .back-btn i {
            margin-right: 8px;
        }

        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 6px 16px rgba(168, 200, 160, 0.15);
        }

        .card-header {
            padding: 18px 25px;
            background-color: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 500;
            color: var(--text-color);
            margin: 0;
        }

        .card-header h2 i {
            margin-right: 12px;
            color: var(--dark-accent);
        }

        .card-header .toggle-icon {
            transition: transform 0.3s ease;
        }

        .card.closed .card-header .toggle-icon {
            transform: rotate(-90deg);
        }

        .card-content {
            padding: 25px;
            transition: all 0.3s ease;
            max-height: 2000px;
            opacity: 1;
        }

        .card.closed .card-content {
            max-height: 0;
            opacity: 0;
            padding: 0;
            overflow: hidden;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="file"],
        input[type="password"],
        select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: white;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: var(--transition);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--dark-accent);
            box-shadow: 0 0 0 3px rgba(168, 200, 160, 0.2);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--dark-accent);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
            font-size: 14px;
        }

        .btn:hover {
            background-color: #95b88d;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(168, 200, 160, 0.2);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background-color: var(--dark-accent);
        }

        .btn-danger {
            background-color: var(--error-color);
        }

        .btn-warning {
            background-color: var(--warning-color);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .table-responsive {
            overflow-x: auto;
            margin: 25px 0;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th {
            background-color: var(--dark-accent);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background-color: var(--secondary-color);
        }

        .preview-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            object-fit: cover;
        }

        .method-actions {
            display: flex;
            gap: 8px;
        }

        .delete-btn {
            padding: 8px 12px;
            background-color: var(--error-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .delete-btn:hover {
            background-color: #e53935;
        }

        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 320px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification.success {
            border-left: 4px solid var(--success-color);
        }

        .notification.error {
            border-left: 4px solid var(--error-color);
        }

        .notification i {
            margin-right: 12px;
            font-size: 18px;
        }

        .notification.success i {
            color: var(--success-color);
        }

        .notification.error i {
            color: var(--error-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: var(--light-text);
        }

        /* Media Queries */
        @media (max-width: 992px) {
            .container {
                padding: 25px 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .card-header h2 {
                font-size: 18px;
            }
            
            .btn {
                padding: 10px 20px;
            }
            
            .form-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
            
            .notification-container {
                width: 90%;
                right: 5%;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .preview-image {
                max-width: 80px;
                max-height: 80px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 20px 10px;
            }
            
            .card-header {
                padding: 15px;
            }
            
            .card-content {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 22px;
            }
            
            .card-header h2 {
                font-size: 16px;
            }
            
            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="file"],
            input[type="password"],
            select, textarea {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            label {
                font-size: 13px;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .preview-image {
                max-width: 60px;
                max-height: 60px;
            }
            
            .method-actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .delete-btn {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 400px) {
            .container {
                padding: 15px 8px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .card-header h2 {
                font-size: 15px;
            }
            
            .card-header h2 i {
                margin-right: 8px;
            }
            
            th, td {
                padding: 10px 6px;
                font-size: 13px;
            }
            
            .preview-image {
                max-width: 50px;
                max-height: 50px;
            }
        }
    </style>
</head>
<body>
    <!-- Notification Container -->
    <div class="notification-container" id="notification-container"></div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-school"></i> School Information Management</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Bank Account Information Section -->
        <div class="card closed" id="bank-card">
            <div class="card-header" onclick="toggleCard('bank-card')">
                <h2><i class="fas fa-university"></i> Bank Account Information</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="bank-form" method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_bank" value="1">
                    <div class="form-group">
                        <label>Bank Name:</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($bank['bank_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Number:</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($bank['account_number']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Holder:</label>
                        <input type="text" name="account_holder" value="<?= htmlspecialchars($bank['account_holder']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>School Email:</label>
                        <input type="email" name="school_email" value="<?= htmlspecialchars($bank['school_email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number:</label>
                        <input type="text" name="phone_number" value="<?= htmlspecialchars($bank['phone_number'] ?? '') ?>">
                    </div>
                    
                    
                    <div class="form-group">
                        <label>Gmail App Password:</label>
                        <input type="password" name="gmail_app_password" value="" autocomplete="new-password" placeholder="Enter new Gmail App Password">
                        <small>Leave blank to keep the current password</small>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_bank">
                            <i class="fas fa-save"></i> Save Bank Information
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Payment Methods Section -->
        <div class="card closed" id="payment-card">
            <div class="card-header" onclick="toggleCard('payment-card')">
                <h2><i class="fas fa-credit-card"></i> Payment Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="payment-methods-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="payment">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Method Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Image</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payment-methods-body">
                                <?php foreach ($paymentMethods as $i => $method): ?>
                                <tr id="method-row-<?= $method['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $method['id'] ?>">
                                        <input type="text" name="method_name[]" value="<?= htmlspecialchars($method['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($method['fee'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <?php if (!empty($method['image'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($method['image']) ?>" alt="Method Image" class="preview-image">
                                        <?php endif; ?>
                                        <input type="file" name="method_image_<?= $method['id'] ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('payment', <?= $method['id'] ?>, '#method-row-<?= $method['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_payment_methods">
                            <i class="fas fa-sync-alt"></i> Update Payment Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Payment Method</h3>
                <form id="add-payment-method-form" method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="payment">
                    <div class="form-group">
                        <label>Method Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="form-group">
                        <label>Image:</label>
                        <input type="file" name="new_method_image" accept="image/*">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_payment_method">
                            <i class="fas fa-plus"></i> Add Payment Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Delivery Methods Section -->
        <div class="card closed" id="delivery-card">
            <div class="card-header" onclick="toggleCard('delivery-card')">
                <h2><i class="fas fa-truck"></i> Delivery Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="delivery-methods-form" method="post">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="delivery">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Option Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="delivery-methods-body">
                                <?php foreach ($deliveryOptions as $i => $option): ?>
                                <tr id="delivery-row-<?= $option['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $option['id'] ?>">
                                        <input type="text" name="option_name[]" value="<?= htmlspecialchars($option['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($option['fee'] ?? '') ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('delivery', <?= $option['id'] ?>, '#delivery-row-<?= $option['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_delivery_methods">
                            <i class="fas fa-sync-alt"></i> Update Delivery Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Delivery Method</h3>
                <form id="add-delivery-method-form" method="post" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="delivery">
                    <div class="form-group">
                        <label>Option Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_delivery_method">
                            <i class="fas fa-plus"></i> Add Delivery Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Guest Delivery Methods Section -->
        <div class="card closed" id="guest-delivery-card">
            <div class="card-header" onclick="toggleCard('guest-delivery-card')">
                <h2><i class="fas fa-user"></i> Guest Delivery Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="guest-delivery-methods-form" method="post">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="guest_delivery">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Option Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="guest-delivery-methods-body">
                                <?php foreach ($guestDeliveryOptions as $i => $option): ?>
                                <tr id="guest-delivery-row-<?= $option['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $option['id'] ?>">
                                        <input type="text" name="option_name[]" value="<?= htmlspecialchars($option['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($option['fee'] ?? '') ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('guest_delivery', <?= $option['id'] ?>, '#guest-delivery-row-<?= $option['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_guest_delivery_methods">
                            <i class="fas fa-sync-alt"></i> Update Guest Delivery Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Guest Delivery Method</h3>
                <form id="add-guest-delivery-method-form" method="post" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="guest_delivery">
                    <div class="form-group">
                        <label>Option Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_guest_delivery_method">
                            <i class="fas fa-plus"></i> Add Guest Delivery Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Guest Payment Methods Section -->
        <div class="card closed" id="guest-payment-card">
            <div class="card-header" onclick="toggleCard('guest-payment-card')">
                <h2><i class="fas fa-money-bill-wave"></i> Guest Payment Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="guest-payment-methods-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="guest_payment">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Method Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Image</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="guest-payment-methods-body">
                                <?php foreach ($guestPaymentMethods as $i => $method): ?>
                                <tr id="guest-method-row-<?= $method['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $method['id'] ?>">
                                        <input type="text" name="method_name[]" value="<?= htmlspecialchars($method['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($method['fee'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <?php if (!empty($method['image'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($method['image']) ?>" alt="Method Image" class="preview-image">
                                        <?php endif; ?>
                                        <input type="file" name="method_image_<?= $method['id'] ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('guest_payment', <?= $method['id'] ?>, '#guest-method-row-<?= $method['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_guest_payment_methods">
                            <i class="fas fa-sync-alt"></i> Update Guest Payment Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Guest Payment Method</h3>
                <form id="add-guest-payment-method-form" method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="guest_payment">
                    <div class="form-group">
                        <label>Method Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="form-group">
                        <label>Image:</label>
                        <input type="file" name="new_method_image" accept="image/*">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_guest_payment_method">
                            <i class="fas fa-plus"></i> Add Guest Payment Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Counter Delivery Methods Section -->
        <div class="card closed" id="counter-delivery-card">
            <div class="card-header" onclick="toggleCard('counter-delivery-card')">
                <h2><i class="fas fa-store"></i> Counter Delivery Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="counter-delivery-methods-form" method="post">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="counter_delivery">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Option Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="counter-delivery-methods-body">
                                <?php foreach ($counterDeliveryOptions as $i => $option): ?>
                                <tr id="counter-delivery-row-<?= $option['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $option['id'] ?>">
                                        <input type="text" name="option_name[]" value="<?= htmlspecialchars($option['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($option['fee'] ?? '') ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('counter_delivery', <?= $option['id'] ?>, '#counter-delivery-row-<?= $option['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_counter_delivery_methods">
                            <i class="fas fa-sync-alt"></i> Update Counter Delivery Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Counter Delivery Method</h3>
                <form id="add-counter-delivery-method-form" method="post" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="counter_delivery">
                    <div class="form-group">
                        <label>Option Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_counter_delivery_method">
                            <i class="fas fa-plus"></i> Add Counter Delivery Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Counter Payment Methods Section -->
        <div class="card closed" id="counter-payment-card">
            <div class="card-header" onclick="toggleCard('counter-payment-card')">
                <h2><i class="fas fa-money-check-alt"></i> Counter Payment Methods</h2>
                <i class="fas fa-chevron-down toggle-icon"></i>
            </div>
            <div class="card-content">
                <form id="counter-payment-methods-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="update_methods" value="1">
                    <input type="hidden" name="method_type" value="counter_payment">
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Method Name</th>
                                    <th>Fee (RM)</th>
                                    <th>Image</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="counter-payment-methods-body">
                                <?php foreach ($counterPaymentMethods as $i => $method): ?>
                                <tr id="counter-method-row-<?= $method['id'] ?>">
                                    <td>
                                        <input type="hidden" name="method_id[]" value="<?= $method['id'] ?>">
                                        <input type="text" name="method_name[]" value="<?= htmlspecialchars($method['method_name']) ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="fee[]" value="<?= htmlspecialchars($method['fee'] ?? '') ?>">
                                    </td>
                                    <td>
                                        <?php if (!empty($method['image'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($method['image']) ?>" alt="Method Image" class="preview-image">
                                        <?php endif; ?>
                                        <input type="file" name="method_image_<?= $method['id'] ?>">
                                    </td>
                                    <td class="method-actions">
                                        <button type="button" onclick="deleteMethod('counter_payment', <?= $method['id'] ?>, '#counter-method-row-<?= $method['id'] ?>')" class="delete-btn" title="Deactivate">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="update_counter_payment_methods">
                            <i class="fas fa-sync-alt"></i> Update Counter Payment Methods
                        </button>
                    </div>
                </form>
                
                <h3 style="margin-top: 30px;"><i class="fas fa-plus-circle"></i> Add New Counter Payment Method</h3>
                <form id="add-counter-payment-method-form" method="post" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="ajax" value="1">
                    <input type="hidden" name="add_method" value="1">
                    <input type="hidden" name="method_type" value="counter_payment">
                    <div class="form-group">
                        <label>Method Name:</label>
                        <input type="text" name="new_method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Fee (RM):</label>
                        <input type="number" step="0.01" name="new_fee">
                    </div>
                    
                    <div class="form-group">
                        <label>Image:</label>
                        <input type="file" name="new_method_image" accept="image/*">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" name="add_counter_payment_method">
                            <i class="fas fa-plus"></i> Add Counter Payment Method
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle card sections
        function toggleCard(cardId) {
            const card = document.getElementById(cardId);
            card.classList.toggle('closed');
        }

        // Preview QR code image
        function previewQR(input) {
            const preview = document.getElementById('ewallet-qr-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Confirm deactivation
        function confirmDeactivate() {
            return confirm('Are you sure you want to deactivate this item? It will no longer be available for selection.');
        }

        // Show notification message
        function showNotification(type, message) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            notification.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
            
            container.appendChild(notification);
            
            // Trigger the animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Remove the notification after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }

        // Generic AJAX form handler
        function handleFormSubmit(formId, successCallback) {
            $(formId).on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showNotification('success', response.message);
                            if (typeof successCallback === 'function') {
                                successCallback(response);
                            }
                        } else {
                            showNotification('error', response.message);
                        }
                    },
                    error: function() {
                        showNotification('error', 'An error occurred. Please try again.');
                    }
                });
            });
        }

        // Generic method deletion function
        function deleteMethod(methodType, id, rowSelector) {
            if (!confirmDeactivate()) return;
            
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    ajax: 1,
                    delete_method: 1,
                    method_type: methodType,
                    method_id: id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showNotification('success', response.message);
                        $(rowSelector).remove();
                    } else {
                        showNotification('error', response.message);
                    }
                },
                error: function() {
                    showNotification('error', 'An error occurred. Please try again.');
                }
            });
        }

        // Initialize all form handlers
        $(document).ready(function() {
            // Bank form
            handleFormSubmit('#bank-form', function(response) {
                if (response.data && response.data.ewallet_qr) {
                    $('#ewallet-qr-preview').attr('src', '../uploads/' + response.data.ewallet_qr).show();
                }
            });

            // Payment methods
            handleFormSubmit('#payment-methods-form');
            handleFormSubmit('#add-payment-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="method-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="method_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td>
                                ${response.method.image ? `<img src="../uploads/${response.method.image}" alt="Method Image" class="preview-image">` : ''}
                                <input type="file" name="method_image_${response.method.id}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('payment', ${response.method.id}, '#method-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#payment-methods-body').append(newRow);
                    $('#add-payment-method-form')[0].reset();
                }
            });

            // Delivery methods
            handleFormSubmit('#delivery-methods-form');
            handleFormSubmit('#add-delivery-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="delivery-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="option_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('delivery', ${response.method.id}, '#delivery-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#delivery-methods-body').append(newRow);
                    $('#add-delivery-method-form')[0].reset();
                }
            });

            // Guest delivery methods
            handleFormSubmit('#guest-delivery-methods-form');
            handleFormSubmit('#add-guest-delivery-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="guest-delivery-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="option_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('guest_delivery', ${response.method.id}, '#guest-delivery-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#guest-delivery-methods-body').append(newRow);
                    $('#add-guest-delivery-method-form')[0].reset();
                }
            });

            // Guest payment methods
            handleFormSubmit('#guest-payment-methods-form');
            handleFormSubmit('#add-guest-payment-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="guest-method-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="method_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td>
                                ${response.method.image ? `<img src="../uploads/${response.method.image}" alt="Method Image" class="preview-image">` : ''}
                                <input type="file" name="method_image_${response.method.id}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('guest_payment', ${response.method.id}, '#guest-method-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#guest-payment-methods-body').append(newRow);
                    $('#add-guest-payment-method-form')[0].reset();
                }
            });

            // Counter delivery methods
            handleFormSubmit('#counter-delivery-methods-form');
            handleFormSubmit('#add-counter-delivery-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="counter-delivery-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="option_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('counter_delivery', ${response.method.id}, '#counter-delivery-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#counter-delivery-methods-body').append(newRow);
                    $('#add-counter-delivery-method-form')[0].reset();
                }
            });

            // Counter payment methods
            handleFormSubmit('#counter-payment-methods-form');
            handleFormSubmit('#add-counter-payment-method-form', function(response) {
                if (response.method) {
                    const newRow = `
                        <tr id="counter-method-row-${response.method.id}">
                            <td>
                                <input type="hidden" name="method_id[]" value="${response.method.id}">
                                <input type="text" name="method_name[]" value="${response.method.name}" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="fee[]" value="${response.method.fee || ''}">
                            </td>
                            <td>
                                ${response.method.image ? `<img src="../uploads/${response.method.image}" alt="Method Image" class="preview-image">` : ''}
                                <input type="file" name="method_image_${response.method.id}">
                            </td>
                            <td class="method-actions">
                                <button type="button" onclick="deleteMethod('counter_payment', ${response.method.id}, '#counter-method-row-${response.method.id}')" class="delete-btn" title="Deactivate">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#counter-payment-methods-body').append(newRow);
                    $('#add-counter-payment-method-form')[0].reset();
                }
            });

            // Check for new orders
            let lastPendingCount = <?= $pendingOrdersCount ?? 0 ?>;
            const audio = new Audio('../sounds/notification.mp3');
            audio.volume = 0.3;

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
                                showNotification('success', `New order received! (Total pending: ${data.count})`);
                            }
                            lastPendingCount = data.count;
                        }
                    })
                    .catch(() => {});
            }

            setInterval(checkNewOrders, 10000);
        });
    </script>
</body>
</html>