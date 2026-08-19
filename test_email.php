<?php
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>SMTP Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: 0 auto; }
        .container { background: #f8f9fa; padding: 20px; border-radius: 8px; }
        .success { color: #02AD2F; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .instructions { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; }
        form { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #02AD2F; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #088A29; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>SMTP Email Configuration Test</h1>

        <div class='instructions'>
            <h3>How to Use This Test</h3>
            <ol>
                <li>Configure your SMTP settings in <strong>config.php</strong></li>
                <li>Enter a test email address below</li>
                <li>Click 'Send Test Email'</li>
                <li>Check if the email arrives (check spam folder too)</li>
            </ol>
        </div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    $test_subject = 'ProsperMinds CPD System - Test Email';
    $test_message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #02AD2F; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { text-align: center; color: #666; font-size: 0.9rem; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ProsperMinds CPD System</h1>
            </div>
            <div class='content'>
                <h2>Test Email Successful!</h2>
                <p>This email confirms that your SMTP configuration is working correctly.</p>
                <p>You can now receive registration confirmations and notifications.</p>
            </div>
            <div class='footer'>
                <p>ProsperMinds CPD Event Management System</p>
            </div>
        </body>
        </html>
    ";

    $result = sendEmail($test_email, $test_subject, $test_message);

    if ($result) {
        echo "<div class='success'>✅ Test email sent successfully! Check your inbox (and spam folder).</div>";
    } else {
        echo "<div class='error'>❌ Failed to send test email. Check your SMTP configuration and error logs.</div>";
    }
}

echo "<form method='POST'>
    <div class='form-group'>
        <label for='test_email'>Enter Test Email Address:</label>
        <input type='email' id='test_email' name='test_email' required placeholder='your@email.com'>
    </div>

    <button type='submit'>Send Test Email</button>
</form>

<div class='instructions' style='margin-top: 30px;'>
    <h3>Common SMTP Providers</h3>
    <p><strong>Gmail:</strong> smtp.gmail.com (Port 465 SSL or 587 TLS)</p>
    <p><strong>Outlook:</strong> smtp.office365.com (Port 587 TLS)</p>
    <p><strong>Yahoo:</strong> smtp.mail.yahoo.com (Port 465 SSL or 587 TLS)</p>
    <p><strong>Your Domain:</strong> mail.yourdomain.com (Port 587 TLS)</p>
</div>

<div class='instructions' style='margin-top: 20px;'>
    <h3>Need Help?</h3>
    <p>Check the <strong>SMTP_CONFIGURATION.md</strong> file for detailed setup instructions for various providers.</p>
    <p>For Gmail users: Remember to use an <strong>App Password</strong>, not your regular password.</p>
</div>
</div>
</body>
</html>";
