<?php
session_start();
require_once "../classes/Cart.php";
require_once "../classes/DB.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart = new Cart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_items'])) {
    echo "<p>No items selected. <a href='cart.php'>Return to cart</a>.</p>";
    exit;
}

$selectedItemIds = array_map('intval', $_POST['selected_items']);
$allItems = $cart->getItemsByUser($user_id);

$orderItems = array_filter($allItems, function ($item) use ($selectedItemIds) {
    return in_array($item['id'], $selectedItemIds);
});

if (empty($orderItems)) {
    echo "<p>No valid items found. <a href='cart.php'>Return to cart</a>.</p>";
    exit;
}

$totalAmount = 0;
foreach ($orderItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

$shipping_address = "School Pickup";

try {
    $pdo = $cart->getConnection(); // This method should return $this->conn from DB class
    $pdo->beginTransaction();

    // Get user info (student name and class)
    $stmtUser = $pdo->prepare("SELECT name, class_name FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) {
        throw new Exception("User not found.");
    }

    // Handle bank slip upload if payment method is bank-in
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

    // Handle e-wallet slip upload if payment method is ewallet
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

    // Get payment method ID from the new table
    $method = $_POST['payment_method'] ?? 'bank-in';
    $stmt = $pdo->prepare("SELECT id FROM payment_methods WHERE method_name = ?");
    $stmt->execute([$method]);
    $payment_method_id = $stmt->fetchColumn();

    if (!$payment_method_id) {
        throw new Exception("Invalid payment method.");
    }

    // Get delivery option ID from the new table
    $delivery_option = $_POST['delivery_option'] ?? 'pickup';
    $stmt = $pdo->prepare("SELECT id FROM delivery_options WHERE option_name = ?");
    $stmt->execute([$delivery_option]);
    $delivery_option_id = $stmt->fetchColumn();

    if (!$delivery_option_id) {
        throw new Exception("Invalid delivery option.");
    }

    // Get payment and delivery fees
    $payment_fee = 0;
    $delivery_fee = 0;

    // Get payment method fee
    if ($payment_method_id) {
        $stmt = $pdo->prepare("SELECT fee FROM payment_methods WHERE id = ?");
        $stmt->execute([$payment_method_id]);
        $payment_fee = floatval($stmt->fetchColumn());
    }

    // Get delivery option fee
    if ($delivery_option_id) {
        $stmt = $pdo->prepare("SELECT fee FROM delivery_options WHERE id = ?");
        $stmt->execute([$delivery_option_id]);
        $delivery_fee = floatval($stmt->fetchColumn());
    }

    // Add fees to total
    $totalAmount += $payment_fee + $delivery_fee;

    // Now insert $totalAmount (which includes all fees) into the database
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, status, payment_method_id, delivery_option_id, bank_slip, ewallet_slip, shipping_address, created_at, student_name, class_name, order_number)
        VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?, NOW(), ?, ?, NULL)
    ");
    $stmtOrder->execute([
        $user_id,
        $totalAmount,
        $payment_method_id,
        $delivery_option_id,
        $bank_slip_filename,
        $ewallet_slip_filename,
        $shipping_address,
        $userInfo['name'],
        $userInfo['class_name']
    ]);

    $order_id = $pdo->lastInsertId();

    // Generate order_number as year/month/sequence (restart every month)
    $yearMonth = date('Ym');
    $stmtSeq = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE_FORMAT(created_at, '%Y%m') = ?");
    $stmtSeq->execute([$yearMonth]);
    $monthlyCount = $stmtSeq->fetchColumn();
    $sequence = $monthlyCount; // This order is already inserted, so count is correct

    $order_number = $yearMonth . str_pad($sequence, 3, '0', STR_PAD_LEFT);

    // Update the order with the generated order_number
    $stmtUpdateOrderNumber = $pdo->prepare("UPDATE orders SET order_number = ? WHERE id = ?");
    $stmtUpdateOrderNumber->execute([$order_number, $order_id]);

    // Insert items
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

        $cart->removeItem($item['id'], $user_id);
    }

    $pdo->commit();
    $success = true;

    header("Location: receipt.php?order_id=" . $order_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}

// Fetch the order details for the receipt
$stmtReceipt = $pdo->prepare("
    SELECT o.*, pm.method_name AS payment_method
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
    WHERE o.id = ?
");
$stmtReceipt->execute([$order_id]);
$orderDetails = $stmtReceipt->fetch(PDO::FETCH_ASSOC);

if (!$orderDetails) {
    die("Order not found.");
}

// Fetch payment and delivery fees
$payment_fee = 0;
$delivery_fee = 0;

if ($orderDetails['payment_method_id']) {
    $stmt = $pdo->prepare("SELECT fee FROM payment_methods WHERE id = ?");
    $stmt->execute([$orderDetails['payment_method_id']]);
    $payment_fee = floatval($stmt->fetchColumn());
}
if ($orderDetails['delivery_option_id']) {
    $stmt = $pdo->prepare("SELECT fee FROM delivery_options WHERE id = ?");
    $stmt->execute([$orderDetails['delivery_option_id']]);
    $delivery_fee = floatval($stmt->fetchColumn());
}

$grand_total = floatval($orderDetails['total_amount']) + $payment_fee + $delivery_fee;

// Display order details
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Order Receipt</h1>
        <p>Thank you for your order, <?= htmlspecialchars($userInfo['name']) ?>!</p>
        <p>Order Number: <?= htmlspecialchars($orderDetails['order_number']) ?></p>
        <p>Date: <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></p>

        <h2>Order Details</h2>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= htmlspecialchars($item['quantity']) ?></td>
                    <td><?= htmlspecialchars($item['price']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>Total Amount: <?= htmlspecialchars(number_format($grand_total, 2)) ?></p>
        <p>Payment Method: <?= htmlspecialchars($orderDetails['payment_method']) ?></p>
        <p>Shipping Address: <?= htmlspecialchars($orderDetails['shipping_address']) ?></p>

        <h2>Payment Slip</h2>
        <?php if ($orderDetails['bank_slip']): ?>
            <p>Bank Slip:</p>
            <img src="../uploads/slips/<?= htmlspecialchars($orderDetails['bank_slip']) ?>" alt="Bank Slip" class="slip-image">
        <?php endif; ?>
        <?php if ($orderDetails['ewallet_slip']): ?>
            <p>E-Wallet Slip:</p>
            <img src="../uploads/slips/<?= htmlspecialchars($orderDetails['ewallet_slip']) ?>" alt="E-Wallet Slip" class="slip-image">
        <?php endif; ?>

        <p><a href="order_history.php" class="btn">Back to Order History</a></p>
    </div>
</body>
</html>
