<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
require_once '../includes/invoice.php';
require_once '../includes/mail-template-user.php';
requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$registrationId = (int) ($_POST['registration_id'] ?? 0);
if ($registrationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'No registration ID provided.']);
    exit;
}

// Fetch the registration
$stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE id = ?");
$stmt->execute([$registrationId]);
$reg = $stmt->fetch();

if (!$reg) {
    echo json_encode(['success' => false, 'message' => 'Registration not found.']);
    exit;
}

// Fetch event record
$evStmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$evStmt->execute([$reg['event_id']]);
$eventRecord = $evStmt->fetch();

// If event_id lookup fails, try by title
if (!$eventRecord && !empty($reg['event_name'])) {
    $evStmt2 = $pdo->prepare("SELECT * FROM events WHERE title = ? LIMIT 1");
    // Strip the (location date) suffix if present
    $titleOnly = preg_replace('/\s*\([^)]*\)\s*$/', '', $reg['event_name']);
    $evStmt2->execute([trim($titleOnly)]);
    $eventRecord = $evStmt2->fetch();
}

// Build attendees array
$attendees = json_decode($reg['attendee_details'] ?? '[]', true);
if (!is_array($attendees)) {
    $attendees = [];
}

$registrationData = [
    'first_name'     => $reg['first_name'],
    'last_name'      => $reg['last_name'],
    'phone'          => $reg['phone'],
    'email'          => $reg['email'],
    'organization'   => $reg['organization'],
    'country'        => $reg['country'] ?? '',
    'address'        => $reg['address'] ?? '',
    'gender'         => $reg['gender'] ?? '',
    'meal_preference'=> $reg['meal_preference'] ?? '',
    'future_topics'  => $reg['future_topics'] ?? '',
    'event_name'     => $reg['event_name'],
    'attendee_count' => (int) ($reg['attendee_count'] ?? 1),
    'attendees'      => $attendees,
    'invoice_number' => $reg['invoice_number'] ?? '',
    'currency_code'  => $reg['currency_code'] ?? 'USD',
    'unit_price_amount' => (float) ($reg['unit_price_amount'] ?? 0),
    'total_amount'   => (float) ($reg['total_amount'] ?? 0),
];

$eventDetails = [
    'name'          => $eventRecord['title'] ?? preg_replace('/\s*\([^)]*\)\s*$/', '', $reg['event_name']),
    'date'          => $eventRecord['date_display'] ?? '',
    'location'      => $eventRecord['location'] ?? '',
    'price'         => $eventRecord['price'] ?? '',
    'invoice_number'=> $reg['invoice_number'] ?? '',
    'attendee_count'=> (int) ($reg['attendee_count'] ?? 1),
    'total_amount'  => (float) ($reg['total_amount'] ?? 0),
    'currency_code' => $reg['currency_code'] ?? 'USD',
];

// Find the invoice PDF
$invoiceAbsolutePath = null;
$invoiceFilename     = null;

if (!empty($reg['invoice_path'])) {
    $candidate = __DIR__ . '/../' . ltrim($reg['invoice_path'], '/\\');
    if (is_file($candidate)) {
        $invoiceAbsolutePath = realpath($candidate);
        $invoiceFilename     = basename($invoiceAbsolutePath);
    }
}

// If the PDF is missing, regenerate it
if (!$invoiceAbsolutePath || !is_file($invoiceAbsolutePath)) {
    $invoiceDir = __DIR__ . '/../assets/invoices/';
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
    }

    $invoiceNumber   = $reg['invoice_number'] ?? generateInvoiceNumber($registrationId);
    $invoiceFilename = $invoiceNumber . '.pdf';
    $targetPath      = $invoiceDir . $invoiceFilename;

    if ($eventRecord) {
        $invoiceResult = generateInvoicePdf(
            buildInvoicePayload($reg, $eventRecord),
            $targetPath
        );
        if ($invoiceResult['success'] && is_file($targetPath)) {
            $invoiceAbsolutePath = $targetPath;
        }
    }
}

// Get available events for the calendar
$availableEventsStmt = $pdo->query(
    "SELECT id, title, date_display, location FROM events WHERE is_active = 1 ORDER BY event_start_date, id"
);
$availableEvents = $availableEventsStmt ? $availableEventsStmt->fetchAll() : [];

$welcomeBody = getWelcomeEmailTemplate($registrationData, $eventDetails, $availableEvents);
$invoiceBody = getInvoiceEmailTemplate($registrationData, $eventDetails);

$attachments = [];
if ($invoiceAbsolutePath && is_file($invoiceAbsolutePath)) {
    $attachments[] = ['path' => $invoiceAbsolutePath, 'name' => $invoiceFilename];
}

$eventNameShort = $eventRecord['title'] ?? preg_replace('/\s*\([^)]*\)\s*$/', '', $reg['event_name']);
$mailResults = sendEmailMessages([
    [
        'to'          => $reg['email'],
        'subject'     => 'Your Invitation - ' . $eventNameShort,
        'message'     => $welcomeBody,
        'attachments' => [],
    ],
    [
        'to'          => $reg['email'],
        'subject'     => 'Your Invoice - ' . $eventNameShort,
        'message'     => $invoiceBody,
        'attachments' => $attachments,
    ],
]);

$invitationSent = (bool) ($mailResults[0]['success'] ?? false);
$invoiceSent = (bool) ($mailResults[1]['success'] ?? false);

if ($invitationSent && $invoiceSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Invitation and invoice emails successfully resent to ' . $reg['email'],
    ]);
} else {
    $err = $mailResults[1]['error'] ?? ($mailResults[0]['error'] ?? 'Unknown error');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend one or more emails to ' . $reg['email'] . '. Error: ' . $err,
    ]);
}
