<?php
require_once "../classes/AdminController.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
    
    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        echo "⚠️ Invalid phone format.";
        exit;
    }

    $controller = new AdminController();

    if (method_exists($controller, 'isPhoneTaken') && $controller->isPhoneTaken($phone)) {
        echo "⚠️ Phone number already registered.";
    } else {
        echo "✓ Phone number available.";
    }
} else {
    echo "⚠️ Invalid request.";
}
