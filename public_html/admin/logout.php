<?php
require_once '../includes/auth.php';
startAdminSession();

if (!empty($_SESSION['admin_logged_in'])) {
    try {
        require_once '../includes/config.php';
        require_once '../includes/audit.php';
        require_once '../includes/adminsession.php';
        pmAudit($pdo, 'logout', 'Signed out of the admin panel');
        pmRememberForget($pdo);
    } catch (Throwable $e) {
        error_log('logout audit failed: ' . $e->getMessage());
    }
}

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
