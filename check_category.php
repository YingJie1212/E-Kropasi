<?php
require_once "../classes/Category.php";
session_start();

// Optional: check admin login here

header('Content-Type: application/json');

if (!isset($_GET['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'No category name provided']);
    exit;
}

$name = trim($_GET['name']);
$id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

$category = new Category();

if ($category->existsByName($name, $id)) {
    echo json_encode(['status' => 'exists']);
} else {
    echo json_encode(['status' => 'available']);
}
