<?php
require_once '../includes/auth.php';
startAdminSession();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
