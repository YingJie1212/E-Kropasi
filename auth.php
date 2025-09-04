<?php
session_start();
require_once "../classes/Admin.php";
$admin = new Admin();

if (!$admin->isLoggedIn()) {
    header("Location: login.php");
    exit;
}
