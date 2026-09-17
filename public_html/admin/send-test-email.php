<?php
require_once '../includes/auth.php';
startAdminSession();
require_once '../includes/config.php';
requireAdminAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$to = ADMIN_EMAIL;

$body = "
<html>
<body style='font-family:Arial,sans-serif;color:#111;'>
  <div style='max-width:560px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;'>
    <div style='background:#00B140;padding:22px 28px;'>
      <h2 style='color:#fff;margin:0;'>ProsperMinds – SMTP Test Email</h2>
    </div>
    <div style='padding:24px 28px;'>
      <p>This is a test email sent from the <strong>ProsperMinds Admin Panel</strong>.</p>
      <p>If you received this message, your SMTP settings are configured correctly and emails will be delivered to registrants.</p>
      <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
      <p style='color:#64748b;font-size:13px;'>Sent at: " . date('Y-m-d H:i:s') . "<br>
      From: " . htmlspecialchars(COMPANY_NAME) . " &lt;" . htmlspecialchars(getSetting('smtp_user', ADMIN_EMAIL)) . "&gt;<br>
      To: " . htmlspecialchars($to) . "</p>
    </div>
  </div>
</body>
</html>";

$sent = sendEmail(
    $to,
    'ProsperMinds – SMTP Test Email (' . date('H:i:s') . ')',
    $body
);

if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Test email successfully sent to ' . $to . '. Please check that inbox (and the spam/junk folder).',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send test email. Please check your SMTP settings and try again. Check storage/logs/email-delivery.log for details.',
    ]);
}
