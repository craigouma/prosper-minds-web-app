<?php

function buildEventUrl(array $event): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'www.prosper-minds.com';
    $base = (stripos($host, 'localhost') !== false) ? ('http://' . $host) : ('https://' . $host);
    return $base . '/event-registration.php?id=' . (int) ($event['id'] ?? 0);
}

function getAvailableEventsCalendarHtml(array $events): string
{
    $rows = '';

    foreach ($events as $event) {
        $date = htmlspecialchars($event['date_display'] ?? '');
        $title = htmlspecialchars($event['title'] ?? '');
        $location = htmlspecialchars($event['location'] ?? '');
        $url = htmlspecialchars(buildEventUrl($event));

        $rows .= "
            <tr>
                <td style='padding:12px;border:1px solid #d9e2ec;vertical-align:top;'><strong>{$date}</strong></td>
                <td style='padding:12px;border:1px solid #d9e2ec;vertical-align:top;'>{$title}</td>
                <td style='padding:12px;border:1px solid #d9e2ec;vertical-align:top;'><a href='{$url}' style='color:#007A2F;text-decoration:none;font-weight:600;'>{$location}</a></td>
            </tr>
        ";
    }

    return "
        <table style='width:100%;border-collapse:collapse;margin:18px 0 24px;'>
            <thead>
                <tr style='background:#f2f7f3;color:#0f172a;'>
                    <th style='padding:12px;border:1px solid #d9e2ec;text-align:left;'>Dates</th>
                    <th style='padding:12px;border:1px solid #d9e2ec;text-align:left;'>Programme</th>
                    <th style='padding:12px;border:1px solid #d9e2ec;text-align:left;'>Venue</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>
    ";
}

function getWelcomeEmailTemplate($registrationData, $eventDetails, array $availableEvents): string
{
    $fullName = htmlspecialchars(trim(($registrationData['first_name'] ?? '') . ' ' . ($registrationData['last_name'] ?? '')));
    $eventName = htmlspecialchars($eventDetails['name'] ?? '');
    $eventLocation = htmlspecialchars($eventDetails['location'] ?? '');
    $eventDate = htmlspecialchars($eventDetails['date'] ?? '');
    $calendarHtml = getAvailableEventsCalendarHtml($availableEvents);

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #111111; line-height: 1.7; background:#f8fafc; }
            .container { max-width: 700px; margin: 0 auto; background:#ffffff; border: 1px solid #e5e7eb; }
            .header { background: linear-gradient(135deg, #0b5d2a, #00B140); color: #ffffff; padding: 28px 32px; }
            .header img { max-width: 170px; display:block; margin-bottom:16px; }
            .content { padding: 30px 32px; }
            .event-highlight { background:#f3fbf5; border-left:4px solid #00B140; padding:16px 18px; margin:22px 0; }
            .footer { padding: 20px 32px 28px; color:#64748b; font-size:12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://www.prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds Logo'>
                <h2 style='margin:0;'>Your Prosperminds Learning Invitation</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>{$fullName}</strong>,</p>

                <p>I hope this message finds you well.</p>

                <p>Every day, you make decisions that protect public resources, strengthen accountability and improve the lives of countless citizens, often without recognition. Professionals like you are the quiet force behind trusted governments, and it is our privilege to support your continued growth.</p>

                <p>Please find below your personal invitation to the <strong>{$eventName}</strong>, taking place in <strong>{$eventLocation}</strong>, from <strong>{$eventDate}</strong>. We would be honoured to welcome you.</p>

                <div class='event-highlight'>
                    <strong>Selected Programme:</strong><br>
                    {$eventName}<br>
                    {$eventLocation}<br>
                    {$eventDate}
                </div>

                <p>Your invoice for this registration has been sent to you in a separate email.</p>

                <p>To help you plan ahead, below is our remaining <strong>Prosper-Minds Executive Learning Calendar for 2026</strong>:</p>

                {$calendarHtml}

                <p>Whether you join us in Cape Town, Kuala Lumpur, Bali, or Mombasa, you will be among a community of public finance leaders committed to building stronger governments and delivering greater public value.</p>

                <p>If you need any support, please reply to this email or contact us at <a href='mailto:info@prosper-minds.com' style='color:#007A2F;font-weight:700;'>info@prosper-minds.com</a>.</p>

                <p><strong>Register:</strong> <a href='https://www.prosper-minds.com' style='color:#007A2F;font-weight:700;'>www.prosper-minds.com</a></p>

                <p>We look forward to welcoming you to a greatly promising learning and growth experience.</p>

                <p>Best Regards,<br>The Prosperminds Team</p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " Prosperminds. All rights reserved.
            </div>
        </div>
    </body>
    </html>";
}

function getInvoiceEmailTemplate($registrationData, $eventDetails): string
{
    $attendeeItems = '';
    foreach (($registrationData['attendees'] ?? []) as $attendee) {
        $name = trim(($attendee['first_name'] ?? '') . ' ' . ($attendee['last_name'] ?? ''));
        $role = trim($attendee['title'] ?? '');
        $email = trim($attendee['email'] ?? '');

        $meta = array_filter([$role, $email]);
        $attendeeItems .= '<li style="margin-bottom:6px;">' . htmlspecialchars($name ?: 'Delegate') .
            (!empty($meta) ? ' <span style="color:#64748b;">(' . htmlspecialchars(implode(' | ', $meta)) . ')</span>' : '') .
            '</li>';
    }

    $totalDue = ($eventDetails['currency_code'] ?? 'USD') . ' ' . number_format((float) ($eventDetails['total_amount'] ?? 0), 2);

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #111111; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; }
            .header { background-color: #00B140; color: #ffffff; padding: 20px; text-align: center; }
            .header img { max-width: 150px; margin-bottom: 10px; }
            .content { padding: 20px; }
            .event-card { background-color: #F5F5F5; padding: 15px; border-left: 4px solid #00B140; margin: 20px 0; }
            .footer { text-align: center; font-size: 12px; color: #777; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://www.prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds Logo'>
                <h2>Invoice for Your Registration</h2>
            </div>
            <div class='content'>
                <p>Dear " . htmlspecialchars(($registrationData['first_name'] ?? '') . ' ' . ($registrationData['last_name'] ?? '')) . ",</p>
                <p>Thank you for registering. Please find your invoice attached for payment processing.</p>

                <div class='event-card'>
                    <h3 style='color: #007A2F; margin-top: 0;'>" . htmlspecialchars($eventDetails['name']) . "</h3>
                    <p><strong>Date:</strong> " . htmlspecialchars($eventDetails['date']) . "</p>
                    <p><strong>Venue:</strong> " . htmlspecialchars($eventDetails['location']) . "</p>
                    <p><strong>Delegate Fee:</strong> " . htmlspecialchars($eventDetails['price']) . "</p>
                    <p><strong>Delegates Registered:</strong> " . (int) $eventDetails['attendee_count'] . "</p>
                    <p><strong>Total Invoice Amount:</strong> {$totalDue}</p>
                    <p><strong>Invoice Number:</strong> " . htmlspecialchars($eventDetails['invoice_number']) . "</p>
                </div>

                <h4 style='margin-bottom: 8px; color: #111111;'>Registered Delegates</h4>
                <ul style='padding-left: 18px; margin-top: 0;'>{$attendeeItems}</ul>

                <p><strong>Payment Reminder:</strong> Please make payment by the due date shown on the attached invoice.</p>
                <p>If you have any questions, feel free to contact us at <a href='mailto:info@prosper-minds.com'>info@prosper-minds.com</a>.</p>
                <p>Best regards,<br>The Prosperminds Team</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Prosperminds. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
}
?>
