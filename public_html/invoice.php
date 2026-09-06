<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/invoice.php';

/**
 * Signed, expiring delivery for invoice PDFs.
 *
 * assets/invoices/ is denied to the web, so this is the only route to a PDF.
 * A link carries a registration id, an expiry and an HMAC over both, keyed by
 * a server secret. Possession of one link never implies the ability to guess
 * another, which was the whole problem with the sequential filenames.
 */

$id      = (int) ($_GET['r'] ?? 0);
$expires = (int) ($_GET['e'] ?? 0);
$sig     = (string) ($_GET['s'] ?? '');

function pmInvoiceDeny(string $why): void
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo $why;
    exit;
}

if ($id <= 0 || $expires <= 0 || $sig === '') {
    pmInvoiceDeny('This invoice link is not complete. Ask the programme office for a new one.');
}

if ($expires < time()) {
    pmInvoiceDeny('This invoice link has expired. Ask the programme office for a new one.');
}

if (!pmInvoiceSignatureValid($id, $expires, $sig)) {
    pmInvoiceDeny('This invoice link is not valid.');
}

try {
    $stmt = $pdo->prepare('SELECT invoice_number FROM event_registrations WHERE id = ?');
    $stmt->execute([$id]);
    $invoiceNumber = (string) ($stmt->fetchColumn() ?: '');
} catch (Throwable $e) {
    error_log('invoice delivery: lookup failed: ' . $e->getMessage());
    pmInvoiceDeny('That invoice could not be looked up.');
}

if ($invoiceNumber === '') {
    pmInvoiceDeny('That invoice does not exist.');
}

// basename() is the guard that stops a crafted invoice number reading a file
// from anywhere else on disk.
$file = __DIR__ . '/assets/invoices/' . basename($invoiceNumber) . '.pdf';

if (!is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'That invoice file is not on the server. Please contact the programme office.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($file));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
readfile($file);
