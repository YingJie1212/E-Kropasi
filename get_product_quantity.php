<?php
// filepath: c:\xampp\htdocs\school_project\admin\get_product_quantity.php
require_once "../classes/Product.php";
header('Content-Type: application/json');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = new Product();
$data = $product->getById($id);
echo json_encode(['quantity' => $data ? (int)$data['quantity'] : 0]);