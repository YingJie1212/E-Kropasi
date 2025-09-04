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

    $cart_id = $_POST['cart_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($cart_id && $action) {
        if ($action === 'update') {
            $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
            $cart->updateQuantity($cart_id, $user_id, $quantity);
        } elseif ($action === 'remove') {
            $cart->removeItem($cart_id, $user_id);
        }
    }
}

header("Location: cart.php");
exit;
