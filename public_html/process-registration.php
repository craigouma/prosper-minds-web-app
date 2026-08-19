<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/invoice.php';
require_once 'includes/mail-template-user.php';
require_once 'includes/mail-template-admin.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$firstName      = trim($_POST['first_name'] ?? '');
$lastName       = trim($_POST['last_name'] ?? '');
$phone          = trim($_POST['phone'] ?? '');
$email          = trim($_POST['email'] ?? '');
$organization   = trim($_POST['organization'] ?? '');
$country        = trim($_POST['country'] ?? '');
$address        = trim($_POST['address'] ?? '');
$gender         = trim($_POST['gender'] ?? '');
$mealPreference = trim($_POST['meal_preference'] ?? '');
$futureTopics   = trim($_POST['future_topics'] ?? '');
$consent        = isset($_POST['consent']) ? 1 : 0;
$eventId        = (int) ($_POST['event_id'] ?? 0);
$eventName      = trim($_POST['event_name'] ?? '');

$attendeeFirstNames = $_POST['attendees']['first_name'] ?? [];
$attendeeLastNames  = $_POST['attendees']['last_name'] ?? [];
$attendeeEmails     = $_POST['attendees']['email'] ?? [];
$attendeeTitles     = $_POST['attendees']['title'] ?? [];

$attendees = [];
foreach ($attendeeFirstNames as $idx => $rawFirstName) {
    $attendeeFirstName = trim((string) $rawFirstName);
    $attendeeLastName  = trim((string) ($attendeeLastNames[$idx] ?? ''));
    $attendeeEmail     = trim((string) ($attendeeEmails[$idx] ?? ''));
    $attendeeTitle     = trim((string) ($attendeeTitles[$idx] ?? ''));

    if ($attendeeFirstName === '' && $attendeeLastName === '' && $attendeeEmail === '' && $attendeeTitle === '') {
        continue;
    }

    if ($attendeeFirstName === '' || $attendeeLastName === '') {
        echo json_encode(['success' => false, 'message' => 'Each attendee must have a first and last name.']);
        exit;
    }

    if ($attendeeEmail !== '' && !filter_var($attendeeEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'One or more attendee email addresses are invalid.']);
        exit;
    }

    $attendees[] = [
        'first_name' => $attendeeFirstName,
        'last_name' => $attendeeLastName,
        'email' => $attendeeEmail,
        'title' => $attendeeTitle,
    ];
}

if (
    $firstName === '' || $lastName === '' || $phone === '' || $email === '' ||
    $organization === '' || $country === '' || $address === '' || $eventName === ''
) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (!preg_match('/^[\d\+\-\s\(\)]{8,20}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
    exit;
}

if (count($attendees) === 0) {
    echo json_encode(['success' => false, 'message' => 'Please add at least one attendee.']);
    exit;
}

ensureRegistrationInvoiceSchema($pdo);

$evStmt = $eventId > 0
    ? $pdo->prepare("SELECT * FROM events WHERE id = ? AND is_active = 1 LIMIT 1")
    : $pdo->prepare("SELECT * FROM events WHERE title = ? AND is_active = 1 LIMIT 1");
$evStmt->execute([$eventId > 0 ? $eventId : $eventName]);
$eventRecord = $evStmt->fetch();

if (!$eventRecord) {
    echo json_encode(['success' => false, 'message' => 'Selected event is no longer available.']);
    exit;
}

$eventName = $eventRecord['title'];
$fullEventName = $eventName . ' (' . $eventRecord['location'] . ' ' . $eventRecord['date_display'] . ')';
[$currencyCode, $unitPriceAmount] = parseEventPrice($eventRecord['price'] ?? '');
$attendeeCount = count($attendees);
$totalAmount = $unitPriceAmount * $attendeeCount;

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        "INSERT INTO event_registrations
         (first_name, last_name, phone, email, organization, country, address, attendee_count, attendee_details,
          gender, meal_preference, future_topics, consent, event_name, event_id, currency_code, unit_price_amount, total_amount)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([
        $firstName,
        $lastName,
        $phone,
        $email,
        $organization,
        $country,
        $address,
        $attendeeCount,
        json_encode($attendees, JSON_UNESCAPED_UNICODE),
        $gender,
        $mealPreference,
        $futureTopics,
        $consent,
        $fullEventName,
        $eventRecord['id'],
        $currencyCode,
        $unitPriceAmount,
        $totalAmount,
    ]);

    $registrationId = (int) $pdo->lastInsertId();
    $invoiceNumber = generateInvoiceNumber($registrationId);

    $invoiceDir = __DIR__ . '/assets/invoices/';
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
    }

    $invoiceFilename = $invoiceNumber . '.pdf';
    $invoiceAbsolutePath = $invoiceDir . $invoiceFilename;
    $invoiceRelativePath = 'assets/invoices/' . $invoiceFilename;

    $pdo->prepare(
        "UPDATE event_registrations SET invoice_number = ?, invoice_path = ? WHERE id = ?"
    )->execute([$invoiceNumber, $invoiceRelativePath, $registrationId]);

    $registrationRow = [
        'id' => $registrationId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'email' => $email,
        'organization' => $organization,
        'country' => $country,
        'address' => $address,
        'attendee_count' => $attendeeCount,
        'attendee_details' => json_encode($attendees, JSON_UNESCAPED_UNICODE),
        'invoice_number' => $invoiceNumber,
        'currency_code' => $currencyCode,
        'unit_price_amount' => $unitPriceAmount,
        'total_amount' => $totalAmount,
    ];

    $invoiceResult = generateInvoicePdf(
        buildInvoicePayload($registrationRow, $eventRecord),
        $invoiceAbsolutePath
    );

    if (!$invoiceResult['success']) {
        throw new RuntimeException('Invoice generation failed: ' . $invoiceResult['output']);
    }

    $pdo->commit();

    $registrationData = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'email' => $email,
        'organization' => $organization,
        'country' => $country,
        'address' => $address,
        'gender' => $gender,
        'meal_preference' => $mealPreference,
        'future_topics' => $futureTopics,
        'event_name' => $fullEventName,
        'attendee_count' => $attendeeCount,
        'attendees' => $attendees,
        'invoice_number' => $invoiceNumber,
        'currency_code' => $currencyCode,
        'unit_price_amount' => $unitPriceAmount,
        'total_amount' => $totalAmount,
    ];

    $availableEventsStmt = $pdo->query(
        "SELECT id, title, date_display, location FROM events WHERE is_active = 1 ORDER BY event_start_date, id"
    );
    $availableEvents = $availableEventsStmt ? $availableEventsStmt->fetchAll() : [];

    $eventDetails = [
        'name' => $eventRecord['title'],
        'date' => $eventRecord['date_display'],
        'location' => $eventRecord['location'],
        'price' => $eventRecord['price'],
        'invoice_number' => $invoiceNumber,
        'attendee_count' => $attendeeCount,
        'total_amount' => $totalAmount,
        'currency_code' => $currencyCode,
    ];

    $adminBody = getAdminEmailTemplate($registrationData);
    $welcomeBody = getWelcomeEmailTemplate($registrationData, $eventDetails, $availableEvents);
    $invoiceBody = getInvoiceEmailTemplate($registrationData, $eventDetails);
    $invoiceAttachment = [
        ['path' => $invoiceAbsolutePath, 'name' => $invoiceFilename],
    ];
    $mailResults = sendEmailMessages([
        [
            'to' => $email,
            'subject' => 'Your Invitation - ' . $eventRecord['title'],
            'message' => $welcomeBody,
            'attachments' => [],
        ],
        [
            'to' => $email,
            'subject' => 'Your Invoice - ' . $eventRecord['title'],
            'message' => $invoiceBody,
            'attachments' => $invoiceAttachment,
        ],
        [
            'to' => ADMIN_EMAIL,
            'subject' => 'New Event Registration - ' . $eventName,
            'message' => $adminBody,
            'attachments' => [],
        ],
        [
            'to' => ADMIN_EMAIL,
            'subject' => '[Copy] Your Invitation - ' . $eventRecord['title'],
            'message' => $welcomeBody,
            'attachments' => [],
        ],
        [
            'to' => ADMIN_EMAIL,
            'subject' => '[Copy] Your Invoice - ' . $eventRecord['title'],
            'message' => $invoiceBody,
            'attachments' => $invoiceAttachment,
        ],
    ]);
    $invitationEmailSent = (bool) ($mailResults[0]['success'] ?? false);
    $invoiceEmailSent = (bool) ($mailResults[1]['success'] ?? false);
    $adminEmailSent = (bool) ($mailResults[2]['success'] ?? false);
    $adminInvitationCopySent = (bool) ($mailResults[3]['success'] ?? false);
    $adminInvoiceCopySent = (bool) ($mailResults[4]['success'] ?? false);

    if ($adminEmailSent && $invitationEmailSent && $invoiceEmailSent && $adminInvitationCopySent && $adminInvoiceCopySent) {
        echo json_encode(['success' => true, 'message' => 'Registration successful! Your invitation and invoice have been emailed to you.']);
    } else {
        $mailIssue = 'Registration was saved, but one or more emails could not be sent.';
        if (!$adminEmailSent) {
            $mailIssue .= ' Admin notification failed.';
        }
        if (!$invitationEmailSent) {
            $mailIssue .= ' Invitation email failed for ' . $email . '.';
        }
        if (!$invoiceEmailSent) {
            $mailIssue .= ' Invoice email failed for ' . $email . '.';
        }
        if (!$adminInvitationCopySent) {
            $mailIssue .= ' Admin invitation copy failed.';
        }
        if (!$adminInvoiceCopySent) {
            $mailIssue .= ' Admin invoice copy failed.';
        }
        echo json_encode(['success' => false, 'message' => $mailIssue]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'We could not complete the registration right now. Please try again shortly.']);
}
