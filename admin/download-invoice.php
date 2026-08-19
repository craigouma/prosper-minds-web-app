<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
requireAdminAuth();

$registrationId = (int) ($_GET['id'] ?? 0);
if ($registrationId <= 0) {
    http_response_code(400);
    exit('Invalid registration ID.');
}

$stmt = $pdo->prepare("SELECT invoice_number, invoice_path FROM event_registrations WHERE id = ?");
$stmt->execute([$registrationId]);
$reg = $stmt->fetch();

if (!$reg || empty($reg['invoice_path'])) {
    http_response_code(404);
    exit('Invoice not found.');
}

$filePath = realpath(__DIR__ . '/../' . ltrim($reg['invoice_path'], '/\\'));
$invoicesDir = realpath(__DIR__ . '/../assets/invoices');

if (!$filePath || !$invoicesDir || strpos($filePath, $invoicesDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('Invoice file not found.');
}

$downloadName = ($reg['invoice_number'] ?: 'invoice') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
