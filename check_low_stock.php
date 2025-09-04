<?php
require_once "../classes/Product.php";
$product = new Product();
echo json_encode(['count' => $product->countLowStock(10)]); // 10 is your threshold