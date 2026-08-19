# SMTP Configuration Guide for ProsperMinds CPD System

## PHPMailer SMTP Setup Instructions

Your system is now configured to use PHPMailer for email delivery. Here's how to configure your SMTP settings:

### 1. Common SMTP Providers Configuration

#### Gmail SMTP Settings
```php
$mail->Host       = 'mail.prosper-minds.com';
$mail->Username   = 'info@prosper-minds.com';
$mail->Password   = 'info@prosper-minds.com'; // Use App Password, not regular password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
$mail->Port       = 465;
```

#### Outlook/Hotmail SMTP Settings
```php
$mail->Host       = 'smtp.office365.com';
$mail->Username   = 'your@outlook.com';
$mail->Password   = 'your_password';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
$mail->Port       = 587;
```

#### Yahoo Mail SMTP Settings
```php
$mail->Host       = 'smtp.mail.yahoo.com';
$mail->Username   = 'your@yahoo.com';
$mail->Password   = 'your_password';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
$mail->Port       = 587;
```

#### Your Own Domain SMTP (cPanel/WHM)
```php
$mail->Host       = 'mail.prosper-minds.com';
$mail->Username   = 'info@prosper-minds.com';
$mail->Password   = 'info@prosper-minds.com';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
$mail->Port       = 587;
```

### 2. Important Security Notes

#### For Gmail Users:
- You MUST use an **App Password** (not your regular Gmail password)
- Enable 2FA on your Google account first
- Create App Password at: https://myaccount.google.com/apppasswords

#### For Other Providers:
- Check if your provider requires "Less Secure Apps" to be enabled
- Some providers may require SMTP authentication to be enabled in your account settings

### 3. Configuration Steps

1. **Edit `config.php`** and replace the SMTP settings with your provider details
2. **Test with a simple script** to verify SMTP works before using in production
3. **Check spam folders** if emails aren't arriving
4. **Monitor error logs** for any connection issues

### 4. Troubleshooting

#### Common Issues and Solutions:

**Issue: Connection timed out**
- Check your SMTP host name is correct
- Verify your internet connection allows outbound SMTP
- Try different ports (587 for TLS, 465 for SSL)

**Issue: Authentication failed**
- Double-check username and password
- For Gmail, ensure you're using an App Password
- Check if your provider requires special authentication

**Issue: Emails go to spam**
- Set up SPF, DKIM, and DMARC records for your domain
- Use a consistent "From" address
- Avoid spam trigger words in subject/content

**Issue: SSL/TLS errors**
- Try changing encryption type between `ENCRYPTION_STARTTLS` and `ENCRYPTION_SMTPS`
- Some providers require specific SSL versions

### 5. Testing Your Configuration

Create a test file `test_email.php`:
```php
<?php
require_once 'config.php';

$test_email = 'your_test_email@example.com';
$test_subject = 'Test Email from ProsperMinds CPD System';
$test_message = '<h1>Test Email</h1><p>This is a test email to verify SMTP configuration.</p>';

$result = sendEmail($test_email, $test_subject, $test_message);

if ($result) {
    echo "Test email sent successfully!";
} else {
    echo "Failed to send test email. Check error logs.";
}
?>
```

### 6. Production Recommendations

1. **Use a dedicated email service** like SendGrid, Mailgun, or Amazon SES for better deliverability
2. **Set up proper DNS records** (SPF, DKIM, DMARC) for your domain
3. **Monitor email delivery** and handle bounces appropriately
4. **Consider rate limiting** to avoid being flagged as spam

### 7. Security Best Practices

- Store SMTP credentials in environment variables or outside web root
- Use HTTPS for your website to protect form submissions
- Implement CSRF protection for your forms
- Regularly update PHPMailer to latest version

## Need Help?

If you're having trouble with a specific SMTP provider, let me know which one and I can provide detailed setup instructions!
