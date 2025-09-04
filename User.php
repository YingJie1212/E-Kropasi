<?php
require_once "DB.php";

class User {
    private $db;

    public function __construct($db) {
        $this->db = $db->getConnection();
    }

    // Register user (with email, chinese_name, gender)
    public function register($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                name, student_id, class_name, phone,
                parent_name, parent_phone, email,
                profile_image, chinese_name, gender, is_admin, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([
            $data['name'],
            $data['student_id'],
            $data['class_name'],
            $data['phone'],
            $data['parent_name'],
            $data['parent_phone'],
            $data['email'],
            $data['profile_image'],
            $data['chinese_name'],
            $data['gender']
        ]);
    }

    // Check if student_id exists
    public function studentIdExists($student_id) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt->execute([$student_id]);
        return $stmt->fetch() !== false;
    }

    // Check if email exists
    public function emailExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }

    // Login function
    public function login($student_id, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE student_id = ? LIMIT 1");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    // Get user by student_id
    public function getByStudentId($student_id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE student_id = ? LIMIT 1");
        $stmt->execute([$student_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user by phone
    public function getByPhone($phone) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all users
    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM users");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Find user by ID
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update password (plain password as input)
    public function updatePassword($student_id, $new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE student_id = ?");
        return $stmt->execute([$hashed, $student_id]);
    }

    // Update user profile (plain password as input)
    public function updateProfile($student_id, $data) {
        $fields = "
            name = ?, class_name = ?, phone = ?,
            parent_name = ?, parent_phone = ?
        ";
        $params = [
            $data['name'],
            $data['class_name'],
            $data['phone'],
            $data['parent_name'],
            $data['parent_phone']
        ];

        if (!empty($data['profile_image'])) {
            $fields .= ", profile_image = ?";
            $params[] = $data['profile_image'];
        }

        if (!empty($data['password'])) {
            $fields .= ", password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $params[] = $student_id;

        $stmt = $this->db->prepare("UPDATE users SET $fields WHERE student_id = ?");
        return $stmt->execute($params);
    }

     // Update only class_name by student_id
    public function updateClassName($student_id, $new_class_name) {
        $stmt = $this->db->prepare("UPDATE users SET class_name = ? WHERE student_id = ?");
        return $stmt->execute([$new_class_name, $student_id]);
    }

    public function updatePasswordByPhone($phone, $new_password) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE phone = ?");
    return $stmt->execute([$hashed, $phone]);
}

}
