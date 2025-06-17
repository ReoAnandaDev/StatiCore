<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$db = new Database();
$auth = new Auth($db->getConnection());

// Destroy session and redirect to login
$auth->logout();
header("Location: /StatiCore/login.php");
exit;