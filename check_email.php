<?php
require_once "../classes/DB.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"])) {
    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "⚠️ Invalid email format.";
        exit;
    }

    $db = new DB();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        echo "⚠️ Email already taken.";
    } else {
        echo "✓ Email is available.";
    }
}
