<?php
require_once "DB.php";
require_once "LoginLog.php";

class Admin extends DB {

    // Register a new admin (with phone validation)
    public function register($name, $email, $phone, $password) {
        // Check if email exists
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            return "Email already exists.";
        }

        // Check if phone exists
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->rowCount() > 0) {
            return "Phone number already in use.";
        }

        // Validate phone format
        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            return "Invalid phone number format.";
        }

        // Hash password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Insert admin
        $stmt = $this->conn->prepare("INSERT INTO users (name, email, phone, password, is_admin) VALUES (?, ?, ?, ?, 1)");
        return $stmt->execute([$name, $email, $phone, $hashed]) ? true : "Failed to register admin.";
    }

    // Delete an admin (with protection against self-deletion and super-admin deletion)
    public function deleteAdmin($adminId, $currentAdminId) {
        if ($adminId == $currentAdminId) {
            return "⚠️ You cannot delete your own account.";
        }

        $stmt = $this->conn->prepare("SELECT is_admin FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return "Admin not found.";
        }

        if ($admin['is_admin'] == 2) {
            return "⚠️ Cannot delete a super admin.";
        }

        $deleteStmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        return $deleteStmt->execute([$adminId]) ? true : "Failed to delete admin.";
    }

    // Login with session regeneration and admin level tracking
    public function login($email, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ? AND (is_admin = 1 OR is_admin = 2)");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_level'] = $user['is_admin']; // Store admin level (1 or 2)

            // 🔒 Security: Regenerate session ID to prevent fixation
            session_regenerate_id(true);

            // Log the login attempt
            $log = new LoginLog();
            $log->record($user['id']);

            return $user['is_admin']; // Return admin level (1 = admin, 2 = super admin)
        }

        return false;
    }

    // Secure logout (destroys entire session)
    public function logout() {
        $_SESSION = []; // Clear all session data
        session_destroy(); // Destroy the session
        return true;
    }

    // Check if admin is logged in (with level verification)
    public function isLoggedIn() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['admin_level']);
    }

    // Get admin details (without password)
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT id, name, email, phone, is_admin, password FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update admin profile (name, email, phone)
    public function update($id, $name, $email, $phone) {
        // Check if email is already taken by another admin
        if ($this->existsByEmail($email, $id)) {
            return "Email already in use by another admin.";
        }

        $stmt = $this->conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        return $stmt->execute([$name, $email, $phone, $id]) ? true : "Failed to update profile.";
    }

    // Update password (with old password verification)
    public function updatePassword($id, $oldPassword, $newPassword) {
        $user = $this->getById($id);
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return "Old password is incorrect.";
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed, $id]) ? true : "Failed to update password.";
    }

    // Check if email exists (excluding current admin)
    public function existsByEmail(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    // Get all users (for admin dashboard)
    public function getAllUsers() {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE student_id IS NOT NULL AND student_id != '' ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get single user by ID
    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}