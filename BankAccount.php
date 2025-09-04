<?php
class BankAccount {
    private $conn;
    private $table = "bank_accounts";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Get bank account by ID
     * @param int $id Bank account ID
     * @return array Bank account data or default values if not found
     */
    public function getById($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: $this->getDefaultBankData();
        } catch (PDOException $e) {
            $this->logError("getById", $e);
            return $this->getDefaultBankData();
        }
    }

    /**
     * Update bank account information (supports partial updates)
     * @param int $id Bank account ID
     * @param array $data Associative array of fields to update
     * @return bool True on success, false on failure
     */
    public function update($id, $data) {
        try {
            // Remove null or empty string values (but keep false/0 values)
            $data = array_filter($data, function($value) {
                return $value !== null && $value !== '';
            });

            // Validate required fields if they're being updated
            $required = ['bank_name', 'account_number', 'account_holder', 'school_email'];
            foreach ($required as $field) {
                if (array_key_exists($field, $data) && empty($data[$field])) {
                    throw new Exception("Required field '{$field}' cannot be empty");
                }
            }

            // Prepare update fields
            $fields = [];
            $params = [':id' => (int)$id];
            
            // Whitelist of allowed fields
            $allowedFields = ['bank_name', 'account_number', 'account_holder', 
                             'school_email', 'phone_number', 'gmail_app_password'];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $fields[] = "{$key} = :{$key}";
                    $params[":{$key}"] = $value;
                }
            }

            if (empty($fields)) {
                throw new Exception("No valid fields to update");
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt->execute($params)) {
                $error = $stmt->errorInfo();
                throw new Exception("Database error: " . $error[2]);
            }

            return true;
        } catch (PDOException $e) {
            $this->logError("update", $e);
            return false;
        } catch (Exception $e) {
            $this->logError("update", $e);
            return false;
        }
    }

    /**
     * Get the most recent bank account
     * @return array Latest bank account or default values if none found
     */
    public function getLatest() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultBankData();
        } catch (PDOException $e) {
            $this->logError("getLatest", $e);
            return $this->getDefaultBankData();
        }
    }

    /**
     * Create initial bank account if none exists
     * @return bool True if created or already exists, false on failure
     */
    public function initialize() {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table}");
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                $defaultData = $this->getDefaultBankData();
                $stmt = $this->conn->prepare("
                    INSERT INTO {$this->table} 
                    (bank_name, account_number, account_holder, school_email, phone_number, gmail_app_password)
                    VALUES (:bank_name, :account_number, :account_holder, :school_email, :phone_number, :gmail_app_password)
                ");
                
                return $stmt->execute([
                    ':bank_name' => $defaultData['bank_name'],
                    ':account_number' => $defaultData['account_number'],
                    ':account_holder' => $defaultData['account_holder'],
                    ':school_email' => $defaultData['school_email'],
                    ':phone_number' => $defaultData['phone_number'],
                    ':gmail_app_password' => $defaultData['gmail_app_password']
                ]);
            }
            
            return true;
        } catch (PDOException $e) {
            $this->logError("initialize", $e);
            return false;
        }
    }

    /**
     * Get default bank account data
     * @return array Default bank account values
     */
    private function getDefaultBankData() {
        return [
            'id' => 0,
            'bank_name' => 'Default Bank',
            'account_number' => '0000000000',
            'account_holder' => 'School Name',
            'school_email' => 'school@example.com',
            'phone_number' => '',
            'gmail_app_password' => '',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    private function logError($method, $e) {
        error_log("[BankAccount::$method] " . $e->getMessage());
    }
}
?>