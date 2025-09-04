<?php
require_once "Admin.php";

class AdminController {
    private Admin $admin;

    public function __construct() {
        $this->admin = new Admin();
    }

    public function isLoggedIn(): bool {
        return $this->admin->isLoggedIn();
    }

    public function register(array $data): string|bool {
        $name     = trim($data["name"] ?? "");
        $email    = trim($data["email"] ?? "");
        $password = $data["password"] ?? "";
        $confirm  = $data["confirm"] ?? "";
        $phone    = trim($data["phone"] ?? "");

        // Validation
        if (!$name || !$email || !$password || !$confirm || !$phone) {
            return "All fields are required.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format.";
        }
        if (strlen($password) < 6) {
            return "Password must be at least 6 characters.";
        }
        if ($password !== $confirm) {
            return "Passwords do not match.";
        }
        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            return "Phone number must be 10-15 digits.";
        }

        // Call Admin register method with phone parameter
        $result = $this->admin->register($name, $email, $phone, $password);

        if ($result === true) {
            return true;
        }

        return $result;
    }
}
