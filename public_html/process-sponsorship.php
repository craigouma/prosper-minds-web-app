<?php
require_once 'includes/config.php';
require_once 'includes/sponsorship.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$firstName    = trim($_POST['first_name']    ?? '');
$lastName     = trim($_POST['last_name']     ?? '');
$organisation = trim($_POST['organisation']  ?? '');
$email        = trim($_POST['email']         ?? '');
$phone        = trim($_POST['phone']         ?? '');
$country      = trim($_POST['country']       ?? '');
$tier         = trim($_POST['tier']          ?? 'Not specified');
$message      = trim($_POST['message']       ?? '');
$events       = $_POST['events'] ?? [];

// Validation
if (!$firstName || !$lastName || !$organisation || !$email) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}
if (empty($events)) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one event.']);
    exit;
}

$eventsList = implode(', ', array_map('htmlspecialchars', $events));
$fullName   = "$firstName $lastName";

$enquiryId = pmSponsorshipStore($pdo, [
    'first_name'   => $firstName,
    'last_name'    => $lastName,
    'organisation' => $organisation,
    'email'        => $email,
    'phone'        => $phone,
    'country'      => $country,
    'tier'         => $tier,
    'message'      => $message,
], is_array($events) ? $events : [$events]);

if ($enquiryId === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'We could not record your enquiry. Please email info@prosper-minds.com and we will pick it up from there.',
    ]);
    exit;
}

// ── Email to admin ──────────────────────────────────────────
$adminBody = "
<div style='font-family:Inter,Arial,sans-serif;max-width:620px;margin:0 auto;'>
    <div style='background:#111;padding:30px;border-radius:8px 8px 0 0;'>
        <h1 style='color:#fff;margin:0;font-size:1.4rem;'>
            New Sponsorship Appeal
            <span style='color:#00B140;'>Received</span>
        </h1>
        <p style='color:#aaa;margin:8px 0 0;font-size:.9rem;'>
            " . date('j F Y, H:i') . "
        </p>
    </div>
    <div style='background:#fff;padding:30px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
        <table style='width:100%;border-collapse:collapse;font-size:.93rem;'>
            <tr style='background:#f8f8f8;'>
                <td style='padding:10px 14px;font-weight:700;color:#555;width:35%;'>Contact</td>
                <td style='padding:10px 14px;'>$fullName</td>
            </tr>
            <tr>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Organisation</td>
                <td style='padding:10px 14px;'>$organisation</td>
            </tr>
            <tr style='background:#f8f8f8;'>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Email</td>
                <td style='padding:10px 14px;'><a href='mailto:$email' style='color:#00B140;'>$email</a></td>
            </tr>
            <tr>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Phone</td>
                <td style='padding:10px 14px;'>" . ($phone ?: '—') . "</td>
            </tr>
            <tr style='background:#f8f8f8;'>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Country</td>
                <td style='padding:10px 14px;'>" . ($country ?: '—') . "</td>
            </tr>
            <tr>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Events Interested</td>
                <td style='padding:10px 14px;color:#00B140;font-weight:600;'>$eventsList</td>
            </tr>
            <tr style='background:#f8f8f8;'>
                <td style='padding:10px 14px;font-weight:700;color:#555;'>Sponsorship Tier</td>
                <td style='padding:10px 14px;font-weight:600;'>" . htmlspecialchars($tier) . "</td>
            </tr>
        </table>
        " . ($message ? "
        <div style='margin-top:20px;padding:16px;background:#f8fdf8;border-left:4px solid #00B140;border-radius:4px;'>
            <div style='font-weight:700;color:#333;margin-bottom:8px;font-size:.9rem;'>MESSAGE / GOALS</div>
            <p style='color:#444;line-height:1.7;margin:0;'>" . nl2br(htmlspecialchars($message)) . "</p>
        </div>" : "") . "
        <div style='margin-top:24px;padding:14px;background:#111;border-radius:6px;text-align:center;'>
            <a href='mailto:$email' style='color:#00B140;font-weight:700;text-decoration:none;font-size:.95rem;'>
                Reply to $fullName →
            </a>
        </div>
    </div>
</div>";

$adminSubject = "New Sponsorship Appeal \u{2013} $organisation ($tier)";
try {
    // sendEmail() reports failure by returning false, so the return value has
    // to be checked as well as caught: a ParseError in the mailer is an Error.
    $sent = sendEmailMessages([
        ['to' => ADMIN_EMAIL, 'subject' => $adminSubject, 'message' => $adminBody],
    ]);
    if (!empty($sent[0]['success'])) {
        pmSponsorshipMarkNotified($pdo, $enquiryId);
    } else {
        recordFailedNotification($pdo, null, ADMIN_EMAIL, $adminSubject,
            (string) ($sent[0]['error'] ?? 'send reported failure'));
    }
} catch (Throwable $e) {
    recordFailedNotification($pdo, null, ADMIN_EMAIL, $adminSubject, $e->getMessage());
}

// ── Confirmation email to sponsor ──────────────────────────
$confirmBody = "
<div style='font-family:Inter,Arial,sans-serif;max-width:620px;margin:0 auto;'>
    <div style='background:#111;padding:36px 30px;border-radius:8px 8px 0 0;'>
        <img src='https://prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds' style='height:40px;filter:brightness(0) invert(1);margin-bottom:20px;display:block;'>
        <h1 style='color:#fff;margin:0 0 8px;font-size:1.5rem;'>
            Thank You, $firstName!
        </h1>
        <p style='color:#aaa;margin:0;font-size:.95rem;'>Your sponsorship appeal has been received.</p>
    </div>
    <div style='background:#fff;padding:32px 30px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
        <p style='color:#333;line-height:1.8;'>
            Warm greetings from the Prosperminds team. We have received your sponsorship enquiry for
            <strong>$eventsList</strong> and are delighted by your interest in co-authoring Africa's Public Finance Future.
        </p>
        <p style='color:#333;line-height:1.8;'>
            Our team will review your appeal and reach out within <strong>48 hours</strong> to schedule a short discovery call
            to explore exactly how we can build a partnership aligned to your goals.
        </p>
        <div style='background:#f8fdf8;border:1px solid #d0e8d0;border-radius:8px;padding:20px 24px;margin:24px 0;'>
            <div style='font-weight:700;color:#00B140;font-size:.85rem;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;'>
                Your Enquiry Summary
            </div>
            <div style='font-size:.9rem;color:#444;line-height:2;'>
                <strong>Organisation:</strong> $organisation<br>
                <strong>Events:</strong> $eventsList<br>
                <strong>Tier of Interest:</strong> " . htmlspecialchars($tier) . "
            </div>
        </div>
        <div style='background:#111;border-radius:8px;padding:20px 24px;margin-top:24px;'>
            <p style='color:#aaa;font-size:.85rem;margin:0 0 8px;'>Need to reach us directly?</p>
            <p style='color:#00B140;font-size:.9rem;margin:0;'>
                info@prosper-minds.com &nbsp;|&nbsp; +254 740 582302 &nbsp;|&nbsp; +254 741 174909
            </p>
        </div>
        <p style='margin-top:28px;color:#555;font-size:.88rem;'>
            With intelligence, purpose, and anticipation,<br>
            <strong style='color:#111;'>The Prosperminds Team</strong>
        </p>
    </div>
</div>";

$confirmSubject = "Sponsorship Appeal Received \u{2013} Prosperminds 2026 Events";
try {
    $sent = sendEmailMessages([
        ['to' => $email, 'subject' => $confirmSubject, 'message' => $confirmBody],
    ]);
    if (empty($sent[0]['success'])) {
        recordFailedNotification($pdo, null, $email, $confirmSubject,
            (string) ($sent[0]['error'] ?? 'send reported failure'));
    }
} catch (Throwable $e) {
    recordFailedNotification($pdo, null, $email, $confirmSubject, $e->getMessage());
}

echo json_encode([
    'success'    => true,
    'message'    => 'Thank you! We will be in touch within 48 hours.',
    'enquiry_id' => $enquiryId,
]);
