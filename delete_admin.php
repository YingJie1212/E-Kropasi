<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../classes/Admin.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin = new Admin();

$adminIdToDelete = $_GET['id'] ?? null;

if (!$adminIdToDelete || !is_numeric($adminIdToDelete)) {
    die("Invalid request.");
}

// Debugging (optional, remove once confirmed working)
// var_dump($adminIdToDelete, $_SESSION['admin_id']); exit;

$result = $admin->deleteAdmin((int)$adminIdToDelete, (int)$_SESSION['admin_id']);

if ($result === true) {
    header("Location: view_admin.php?deleted=1");
    exit;
} else {
    die($result);
}
