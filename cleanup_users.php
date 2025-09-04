<?php
// cleanup_users.php
require_once "../classes/DB.php";
$pdo = (new DB())->getConnection();
$pdo->prepare("DELETE FROM users WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 YEAR)")->execute();