<?php
session_start();
require_once "../classes/GuestCart.php";
require_once "../classes/DB.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$guest_id = $_SESSION['guest_id'] ?? null;
if (!$guest_id) {
    echo "<p>Session expired. Please <a href='new_student_dashboard.php'>start shopping again</a>.</p>";
    exit;
}

$guestCart = new GuestCart();
$pdo = $guestCart->getPDO();
// 获取 guest_delivery_options 选项
$deliveryOptions = array();
$stmtDeliveryOpt = $pdo->query("SELECT option_name, fee FROM guest_delivery_options");
while ($row = $stmtDeliveryOpt->fetch(PDO::FETCH_ASSOC)) {
    $deliveryOptions[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['selected_items'])) {
    echo "<p>No items selected. <a href='new_student_cart.php'>Return to cart</a>.</p>";
    exit;
}

$selectedItemIds = array_map('intval', $_POST['selected_items']);
$allItems = $guestCart->getItemsByGuest($guest_id);

$orderItems = array_filter($allItems, function ($item) use ($selectedItemIds) {
    return in_array($item['id'], $selectedItemIds);
});

if (empty($orderItems)) {
    echo "<p>No valid items found. <a href='new_student_cart.php'>Return to cart</a>.</p>";
    exit;
}

$totalAmount = 0;
foreach ($orderItems as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}

// 生成 checkout 表单（只展示核心部分，实际可根据你的页面结构调整）
echo '<form method="POST" enctype="multipart/form-data">';
echo '<h3>Delivery Option</h3>';
foreach ($deliveryOptions as $opt) {
    $checked = ($opt['option_name'] === ($_POST['delivery_option'] ?? 'pickup')) ? 'checked' : '';
    echo '<label><input type="radio" name="delivery_option" value="'.htmlspecialchars($opt['option_name']).'" '.$checked.'> '.htmlspecialchars($opt['option_name']).' (Fee: RM'.number_format($opt['fee'],2).')</label><br>';
}
// ...existing code for other form fields...
echo '<button type="submit">Place Order</button>';
echo '</form>';

// Get payment method ID and fee
$payment_method = $_POST['payment_method'] ?? 'bank-in';
$stmt = $pdo->prepare("SELECT id, fee FROM payment_methods WHERE method_name = ?");
$stmt->execute([$payment_method]);
$paymentMethodRow = $stmt->fetch(PDO::FETCH_ASSOC);
$payment_method_id = $paymentMethodRow['id'] ?? null;
$payment_fee = (float)($paymentMethodRow['fee'] ?? 0);

if (!$payment_method_id) {
    throw new Exception("Invalid payment method.");
}

// Get delivery option ID and fee

$delivery_option = $_POST['delivery_option'] ?? 'pickup';
$stmtDelivery = $pdo->prepare("SELECT id, fee FROM guest_delivery_options WHERE option_name = ?");
$stmtDelivery->execute([$delivery_option]);
$deliveryOptionRow = $stmtDelivery->fetch(PDO::FETCH_ASSOC);
$delivery_option_id = $deliveryOptionRow['id'] ?? null;
$delivery_fee = (float)($deliveryOptionRow['fee'] ?? 0);

if (!$delivery_option_id) {
    throw new Exception("Invalid delivery option.");
}

// Add fees to total amount
$totalAmount += $payment_fee + $delivery_fee;

$shipping_address = "School Pickup";

// Guest info (ask for name/class on checkout form, or use placeholders)
$guest_name = $_POST['guest_name'] ?? 'Guest';
$guest_class = $_POST['guest_class'] ?? 'Unknown';

try {
    $pdo->beginTransaction();

    $bank_slip_filename = '';
    $ewallet_slip_filename = '';

    if ($payment_method === 'bank-in' && isset($_FILES['bank_slip']) && $_FILES['bank_slip']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['bank_slip']['name'], PATHINFO_EXTENSION);
        $bank_slip_filename = 'bank_slip_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        move_uploaded_file($_FILES['bank_slip']['tmp_name'], "../uploads/$bank_slip_filename");
    }

    if ($payment_method === 'ewallet' && isset($_FILES['ewallet_slip']) && $_FILES['ewallet_slip']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['ewallet_slip']['name'], PATHINFO_EXTENSION);
        $ewallet_slip_filename = 'ewallet_slip_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        move_uploaded_file($_FILES['ewallet_slip']['tmp_name'], "../uploads/$ewallet_slip_filename");
    }

    // Insert order (user_id is NULL, guest_id is set)
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (guest_id, total_amount, status, created_at, payment_method_id, delivery_option_id, bank_slip, ewallet_slip, order_number, student_name, class_name)
        VALUES (?, ?, 'Pending', NOW(), ?, ?, ?, ?, '', ?, ?)
    ");
    $stmtOrder->execute([
        $guest_id,
        $totalAmount,
        $payment_method_id,
        $delivery_option_id,
        $bank_slip_filename,
        $ewallet_slip_filename,
        $guest_name,
        $guest_class
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

        $guestCart->removeItem($item['id'], $guest_id);
    }

    $pdo->commit();
    $success = true;

    header("Location: new_student_receipt.php?order_id=" . $order_id);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
    echo "<p>Order failed: " . htmlspecialchars($error) . "</p>";
    echo "<p><a href='new_student_cart.php'>Return to cart</a></p>";
}
?>