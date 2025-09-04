<?php
session_start();
require_once "../classes/CounterCart.php";
require_once "../classes/DB.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$cart = new Cart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_items'])) {
    echo "<p>No items selected. <a href='counter_cart.php'>Return to cart</a>.</p>";
    exit;
}

$selectedItemIds = array_map('intval', $_POST['selected_items']);
$allItems = $cart->getItemsByUser($admin_id);

$orderItems = array_filter($allItems, function ($item) use ($selectedItemIds) {
    return in_array($item['id'], $selectedItemIds);
});

if (empty($orderItems)) {
    echo "<p>No valid items found. <a href='counter_cart.php'>Return to cart</a>.</p>";
    exit;
}

$totalAmount = 0;
foreach ($orderItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

$shipping_address = "School Pickup";

try {
    $pdo = $cart->getConnection();
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("SELECT name, class_name FROM users WHERE id = ?");
    $stmtUser->execute([$admin_id]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        throw new Exception("User not found.");
    }

    // Upload bank slip
    $bank_slip_filename = null;
    if (
        isset($_FILES['bank_slip']) &&
        $_FILES['bank_slip']['error'] === UPLOAD_ERR_OK &&
        ($_POST['payment_method'] ?? '') === 'bank-in'
    ) {
        $ext = strtolower(pathinfo($_FILES['bank_slip']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed)) {
            $bank_slip_filename = 'slip_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $upload_dir = '../uploads/slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            move_uploaded_file($_FILES['bank_slip']['tmp_name'], $upload_dir . $bank_slip_filename);
        }
    }

    // Upload e-wallet slip
    $ewallet_slip_filename = null;
    if (
        isset($_FILES['ewallet_slip']) &&
        $_FILES['ewallet_slip']['error'] === UPLOAD_ERR_OK &&
        ($_POST['payment_method'] ?? '') === 'ewallet'
    ) {
        $ext = strtolower(pathinfo($_FILES['ewallet_slip']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed)) {
            $ewallet_slip_filename = 'ewallet_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $upload_dir = '../uploads/slips/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            move_uploaded_file($_FILES['ewallet_slip']['tmp_name'], $upload_dir . $ewallet_slip_filename);
        }
    }

    // Get payment method from shared table
    $method = $_POST['payment_method'] ?? 'bank-in';
    $stmt = $pdo->prepare("SELECT id, fee FROM counter_payment_methods WHERE method_name = ?");
    $stmt->execute([$method]);
    $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paymentData) {
        throw new Exception("Invalid payment method.");
    }

    $payment_method_id = $paymentData['id'];
    $payment_fee = floatval($paymentData['fee']);

    // Get delivery option (hardcoded to pickup)
    $stmt = $pdo->prepare("SELECT id, fee FROM counter_delivery_options WHERE option_name = ?");
    $stmt->execute(['pickup']);
    $deliveryData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deliveryData) {
        throw new Exception("Delivery option 'pickup' not found.");
    }

    $delivery_option_id = $deliveryData['id'];
    $delivery_fee = floatval($deliveryData['fee']);

    $totalAmount += $payment_fee + $delivery_fee;

    // ✅ Enforce single user type logic
    $user_id = null;
    $guest_id = null;

    if (($admin_id && !$user_id && !$guest_id) === false) {
        throw new Exception("Only one of admin_id, user_id, or guest_id must be set.");
    }

    // Get coupon from POST (if any)
    $coupon = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : null;

    // Insert order
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (
            admin_id, user_id, guest_id, total_amount, status, payment_method_id, delivery_option_id,
            bank_slip, ewallet_slip, shipping_address, created_at,
            student_name, class_name, order_number, coupon
        ) VALUES (?, NULL, NULL, ?, 'Completed', ?, ?, ?, ?, ?, NOW(), ?, ?, NULL, ?)
    ");

    $stmtOrder->execute([
        $admin_id,
        $totalAmount,
        $payment_method_id,
        $delivery_option_id,
        $bank_slip_filename,
        $ewallet_slip_filename,
        $shipping_address,
        $userInfo['name'],
        $userInfo['class_name'],
        $coupon // <-- add this
    ]);

    $order_id = $pdo->lastInsertId();

    // Generate order number
    $yearMonth = date('Ym');
    $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE_FORMAT(created_at, '%Y%m') = ?");
    $stmtSeq->execute([$yearMonth]);
    $monthlyCount = $stmtSeq->fetchColumn();
    $sequence = $monthlyCount;

    $order_number = $yearMonth . str_pad($sequence, 3, '0', STR_PAD_LEFT);

    $stmtUpdateOrderNumber = $pdo->prepare("UPDATE orders SET order_number = ? WHERE id = ?");
    $stmtUpdateOrderNumber->execute([$order_number, $order_id]);

    // Insert order items
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, package_id, quantity, price, selected_options)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($orderItems as $item) {
        $product_id = $item['item_type'] === 'product' ? $item['item_id'] : null;
        $package_id = $item['item_type'] === 'package' ? $item['item_id'] : null;
        $quantity = $item['quantity'];
        $price = $item['price'];
        $selected_options = json_encode($item['selected_options'] ?? []);

        $stmtItem->execute([
            $order_id,
            $product_id,
            $package_id,
            $quantity,
            $price,
            $selected_options
        ]);

        $cart->removeItem($item['id'], $admin_id);
    }

    $pdo->commit();
    header("Location: counter_receipt.php?order_id=" . $order_id);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
    echo "<p>Order could not be completed. Error: " . htmlspecialchars($error) . "</p>";
    echo "<p><a href='counter_cart.php'>Return to cart</a>.</p>";
    exit;
}
