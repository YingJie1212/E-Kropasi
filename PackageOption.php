<?php
require_once "DB.php";

class PackageOption extends DB {
    public function getOptionsByPackageId($package_id) {
        $stmt = $this->conn->prepare("SELECT * FROM package_options WHERE package_id = ?");
        $stmt->execute([$package_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
