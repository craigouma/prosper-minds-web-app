<?php
// Import PHPMailer classes
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Configuration is read from the environment (a real environment variable, or
// a gitignored .env file) instead of being hardcoded here. See .env.example
// for the full list and env.php for the resolution order.
//
// DEPLOYMENT WARNING: there are no fallback database credentials any more. The
// environment variables (or a .env file) MUST exist on the server before this
// code is deployed, or every page stops at "Site configuration is incomplete."
require_once __DIR__ . '/env.php';

// Database Configuration
define('DB_HOST', cpd_env('DB_HOST', 'localhost'));
define('DB_USER', cpd_env_required('DB_USER'));
define('DB_PASS', cpd_env_required('DB_PASS'));
define('DB_NAME', cpd_env_required('DB_NAME'));
define('DB_PORT', cpd_env('DB_PORT', '3306'));

// Email Configuration
define('ADMIN_EMAIL', cpd_env('ADMIN_EMAIL', 'info@prosper-minds.com'));
define('COMPANY_NAME', cpd_env('COMPANY_NAME', 'ProsperMinds'));
define('COMPANY_COLOR', '#02AD2F');

// SMTP Configuration. Host/port/encryption keep their previous literal values
// as defaults because they are not secrets; the username and password have no
// default at all. If either is unset, sendEmail() logs "SMTP is not configured"
// and returns without connecting — registration still completes, which is the
// behaviour this site has always been written for.
define('SMTP_HOST', cpd_env('SMTP_HOST', 'mail.prosper-minds.com'));
define('SMTP_PORT', (int) cpd_env('SMTP_PORT', '587'));
define('SMTP_SECURE', cpd_env('SMTP_SECURE', 'tls'));   // tls | ssl | none
define('SMTP_USER', cpd_env('SMTP_USER', ''));
define('SMTP_PASS', cpd_env('SMTP_PASS', ''));
define('SMTP_FROM_EMAIL', cpd_env('SMTP_FROM_EMAIL', '') ?: ADMIN_EMAIL);

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
    // No credentials configured: log and carry on. Registration must never be
    // blocked by mail configuration.
    if (SMTP_USER === '' || SMTP_PASS === '') {
        error_log("Email not sent to {$to}: SMTP is not configured (SMTP_USER / SMTP_PASS are unset).");
        return true;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings come from the environment — see .env.example
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure  = false;
            $mail->SMTPAutoTLS = false;
        }
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, COMPANY_NAME);
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
