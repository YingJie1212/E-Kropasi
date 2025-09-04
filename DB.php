<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

class DB {
    protected $conn;

    public function __construct() {
        $host = 'sv99.ifastnet.com';
        $dbname = 'lpktechn_phor_tay_e_koperasi';
        $username = 'lpktechn_hengee';
        $password = '123qwe123!@#'; // Make sure to avoid copying any extra invisible characters like non-breaking space

        try {
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("DB Connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>
