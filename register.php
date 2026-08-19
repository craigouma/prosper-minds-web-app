<?php
require_once 'config.php';
require_once 'csrf.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reject anything that did not come from a form this session rendered.
    // Checked before any input is read, so a forged cross-site post cannot
    // reach the database or the mailer.
    if (!csrfValidate($_POST['csrf_token'] ?? null)) {
        error_log('CPD registration rejected: missing or invalid CSRF token (ip=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ')');

        $rejected_event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $csrf_message = urlencode('Your session has expired. Please submit the form again.');
        $back = $rejected_event_id > 0
            ? "registration_page.php?event_id=$rejected_event_id&error=$csrf_message"
            : "index.php?error=$csrf_message";

        header("Location: $back");
        exit;
    }

    // Validate and sanitize inputs
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $first_name = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $organization = isset($_POST['organization']) ? sanitizeInput($_POST['organization']) : '';

    // Validate required fields
    $errors = [];
    if (empty($event_id)) $errors[] = 'Event ID is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (empty($email)) $errors[] = 'Email is required';
    // Organization is now optional

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!empty($errors)) {
        // Redirect back with errors
        $error_message = urlencode(implode(', ', $errors));
        header("Location: index.php?error=$error_message");
        exit;
    }

    // Get event details
    $conn = getDBConnection();
    $event_query = $conn->prepare("SELECT title, dates, venue FROM events WHERE id = ?");
    $event_query->bind_param("i", $event_id);
    $event_query->execute();
    $event_result = $event_query->get_result();

    if ($event_result->num_rows === 0) {
        header("Location: index.php?error=Invalid event");
        exit;
    }

    $event = $event_result->fetch_assoc();

    // Save registration to database
    $stmt = $conn->prepare("INSERT INTO registrations (event_id, first_name, last_name, phone, email, organization) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $event_id, $first_name, $last_name, $phone, $email, $organization);

    if ($stmt->execute()) {
        $registration_id = $stmt->insert_id;

        // Send confirmation email to user
        $user_subject = "Your Registration for " . $event['title'] . " is Confirmed";
        $user_message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .header { background: #02AD2F; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .event-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; color: #666; font-size: 0.9rem; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>ProsperMinds</h1>
                </div>
                <div class='content'>
                    <h2>Registration Confirmation</h2>
                    <p>Dear $first_name $last_name,</p>
                    <p>Thank you for registering for our event!</p>

                    <div class='event-details'>
                        <h3>" . htmlspecialchars($event['title']) . "</h3>
                        <p><strong>Dates:</strong> " . htmlspecialchars($event['dates']) . "</p>
                        <p><strong>Venue:</strong> " . htmlspecialchars($event['venue']) . "</p>
                    </div>

                    <p>We look forward to seeing you at the event. If you have any questions, please don't hesitate to contact us.</p>
                    <p>Best regards,<br>The ProsperMinds Team</p>
                </div>
                <div class='footer'>
                    <p>Contact us: <a href='mailto:info@prosper-minds.com'>info@prosper-minds.com</a></p>
                </div>
            </body>
            </html>
        ";

        // Send confirmation email to admin
        $admin_subject = "New Registration for " . $event['title'];
        $admin_message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .header { background: #02AD2F; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .registration-details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
                    .footer { text-align: center; color: #666; font-size: 0.9rem; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>ProsperMinds - New Registration</h1>
                </div>
                <div class='content'>
                    <h2>New Event Registration</h2>
                    <p>A new participant has registered for your event:</p>

                    <div class='registration-details'>
                        <h3>" . htmlspecialchars($event['title']) . "</h3>
                        <p><strong>Event Dates:</strong> " . htmlspecialchars($event['dates']) . "</p>
                        <p><strong>Event Venue:</strong> " . htmlspecialchars($event['venue']) . "</p>

                        <h4>Participant Details:</h4>
                        <p><strong>Name:</strong> $first_name $last_name</p>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Phone:</strong> $phone</p>
                        <p><strong>Organization:</strong> $organization</p>
                    </div>

                    <p>Registration ID: $registration_id</p>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from the ProsperMinds CPD System</p>
                </div>
            </body>
            </html>
        ";

        // Send emails (suppress warnings for local development)
        @sendEmail($email, $user_subject, $user_message);
        @sendEmail(ADMIN_EMAIL, $admin_subject, $admin_message);

        // Redirect to success page
        header("Location: success.php?registration_id=$registration_id&event_id=$event_id");
        exit;
    } else {
        // Database error
        header("Location: index.php?error=Database error: " . urlencode($conn->error));
        exit;
    }

    $conn->close();
} else {
    // Not a POST request, redirect to home
    header("Location: index.php");
    exit;
}
?>
