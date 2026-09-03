<?php

// Errors are logged, never printed. A notice printed into the response would
// corrupt the JSON the registration handler returns, break any header()
// redirect issued after it, and disclose the absolute server path.
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', (getenv('PM_DISPLAY_ERRORS') === '1') ? '1' : '0');

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

    $mail = null;

    try {
        // Instantiated INSIDE the try on purpose. `new PHPMailer(true)` is what
        // makes Composer's autoloader require vendor/phpmailer/.../PHPMailer.php
        // for the first time. When that file was left truncated by an
        // incomplete upload on the main site in August 2026, the require threw
        // a ParseError — and because the equivalent line there also sat outside
        // any try, it escaped as an uncaught fatal. On this site that would kill
        // register.php before its header("Location: success.php") redirect, so a
        // delegate whose row had already been inserted would see a blank fatal
        // instead of the confirmation page.
        //
        // CPD's own vendor files are intact today; this is preventative.
        $mail = new PHPMailer(true);

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
    } catch (Throwable $e) {
        // Throwable, not Exception: a corrupted autoloaded vendor file raises
        // ParseError, which extends Error, which catch (Exception) does NOT
        // catch. That distinction is the whole point of this hardening.
        //
        // $mail can still be null here if the failure happened during
        // construction, so fall back to the throwable's own message.
        $errorInfo = ($mail instanceof PHPMailer && $mail->ErrorInfo !== '')
            ? $mail->ErrorInfo
            : $e->getMessage();
        error_log("Email error sending to {$to}: {$errorInfo}");

        // Still return true: a mail problem must never block a registration
        // that has already been written to the database.
        return true;
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
