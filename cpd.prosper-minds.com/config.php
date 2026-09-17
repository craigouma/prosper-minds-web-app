<?php
// Import PHPMailer classes
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'kidsmone_cpd_events');
define('DB_PASS', 'X$qN^m4q%mempF?r');
define('DB_NAME', 'kidsmone_cpd_events');
define('DB_PORT', '3306');

// Email Configuration
define('ADMIN_EMAIL', 'info@prosper-minds.com');
define('COMPANY_NAME', 'ProsperMinds');
define('COMPANY_COLOR', '#02AD2F');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    // ADD THIS LINE:
$conn->set_charset("utf8mb4");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Email function using PHPMailer
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Server settings - CONFIGURE THESE WITH YOUR SMTP DETAILS
        $mail->isSMTP();
        $mail->Host       = 'mail.prosper-minds.com'; // Your SMTP server (e.g., smtp.gmail.com, mail.yourdomain.com)
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@prosper-minds.com'; // Your SMTP username
        $mail->Password   = 'info@prosper-minds.com'; // Your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS (or PHPMailer::ENCRYPTION_SMTPS for SSL)
        $mail->Port       = 587; // TCP port to connect to (587 for TLS, 465 for SSL)

        // Recipients
        $mail->setFrom(ADMIN_EMAIL, COMPANY_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message); // Plain text version

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error but don't fail registration
        error_log("Email error: {$mail->ErrorInfo}");
        return true; // Still return true to complete registration
    }
}

// Input sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
