<?php
session_start();
require_once "../classes/Cart.php";

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = new Cart();
    $user_id = $_SESSION['student_id'];
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    // Assume options are sent as array in POST['options']
    $selected_options = $_POST['options'] ?? [];

    $success = $cart->addItem($user_id, $product_id, $quantity, $selected_options);

    if ($success) {
        header("Location: cart.php?added=1");
    } else {
        echo "Failed to add to cart.";
    }
}
