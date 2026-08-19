<?php
function getAdminEmailTemplate($registrationData) {
    $date = date('Y-m-d H:i:s');
    $attendees = $registrationData['attendees'] ?? [];
    $attendeeRows = '';

    foreach ($attendees as $index => $attendee) {
        $fullName = trim(($attendee['first_name'] ?? '') . ' ' . ($attendee['last_name'] ?? ''));
        $details = array_filter([
            trim($attendee['title'] ?? ''),
            trim($attendee['email'] ?? ''),
        ]);

        $attendeeRows .= '<tr><th>Delegate ' . ($index + 1) . '</th><td>' .
            htmlspecialchars($fullName ?: 'Unnamed delegate') .
            (!empty($details) ? '<br><span style="color:#64748b;">' . htmlspecialchars(implode(' | ', $details)) . '</span>' : '') .
            '</td></tr>';
    }
    
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #111111; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; }
            .header { background-color: #00B140; color: #ffffff; padding: 15px; text-align: center; }
            .content { padding: 20px; background-color: #F5F5F5; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
            th { width: 40%; color: #007A2F; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Event Registration</h2>
            </div>
            <div class='content'>
                <p>A new registration has been received for <strong>{$registrationData['event_name']}</strong>.</p>
                <table>
                    <tr><th>First Name</th><td>{$registrationData['first_name']}</td></tr>
                    <tr><th>Last Name</th><td>{$registrationData['last_name']}</td></tr>
                    <tr><th>Phone Number</th><td>{$registrationData['phone']}</td></tr>
                    <tr><th>Email Address</th><td>{$registrationData['email']}</td></tr>
                    <tr><th>Organization</th><td>{$registrationData['organization']}</td></tr>
                    <tr><th>Country</th><td>{$registrationData['country']}</td></tr>
                    <tr><th>Billing Address</th><td>{$registrationData['address']}</td></tr>
                    <tr><th>Gender</th><td>{$registrationData['gender']}</td></tr>
                    <tr><th>Meal Preference</th><td>{$registrationData['meal_preference']}</td></tr>
                    <tr><th>Attendee Count</th><td>{$registrationData['attendee_count']}</td></tr>
                    <tr><th>Invoice Number</th><td>{$registrationData['invoice_number']}</td></tr>
                    <tr><th>Invoice Total</th><td>{$registrationData['currency_code']} " . number_format((float) ($registrationData['total_amount'] ?? 0), 2) . "</td></tr>
                    <tr><th>Future Topics</th><td>{$registrationData['future_topics']}</td></tr>
                    <tr><th>Timestamp</th><td>{$date}</td></tr>
                    {$attendeeRows}
                </table>
            </div>
        </div>
    </body>
    </html>";
    return $html;
}
?>
