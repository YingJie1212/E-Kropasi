<?php
require_once "../classes/Category.php";
session_start();

if (!isset($_SESSION['admin_id'])) {
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $category = new Category();
    $result = $category->delete($_POST['id']);
    if (isset($_POST['ajax'])) {
        echo json_encode(['success' => $result]);
        exit;
    }
    if ($result) {
        header("Location: view_categories.php?success=Category deleted");
    } else {
        header("Location: view_categories.php?error=Delete failed");
    }
    exit;
}
?>
