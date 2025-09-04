<?php
require_once "DB.php";

class LoginLog extends DB {
    public function record($user_id) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $stmt = $this->conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $ip, $agent]);
    }

    public function getAll() {
        $stmt = $this->conn->query("
            SELECT login_logs.*, users.name, users.email
            FROM login_logs
            JOIN users ON users.id = login_logs.user_id
            ORDER BY login_time DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
