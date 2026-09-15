<?php

/**
 * The newsletter's branded shell.
 *
 * Deliberately built from the same parts as the invoice email in
 * mail-template-user.php: the green header band, the logo, a white content
 * area, a quiet footer. A delegate who has had an invoice from Prosperminds
 * should recognise the newsletter as coming from the same people.
 *
 * Inline styles and a table layout on purpose. Outlook ignores most of a
 * <style> block, and a newsletter that only holds together in Gmail is not
 * finished.
 */

function getNewsletterEmailTemplate(string $subject, string $bodyHtml, string $unsubscribeUrl): string
{
    $heading = htmlspecialchars($subject);
    $optOut   = htmlspecialchars($unsubscribeUrl);
    $year     = date('Y');

    return '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>' . $heading . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f5f5;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #e0e0e0;">

          <tr>
            <td align="center" style="background-color:#00B140;padding:24px 20px;">
              <img src="https://www.prosper-minds.com/assets/images/fisrt-logo.png"
                   alt="Prosperminds" width="150"
                   style="max-width:150px;margin-bottom:10px;display:block;border:0;">
              <h2 style="margin:0;color:#ffffff;font-family:Arial,sans-serif;font-size:20px;line-height:1.3;">'
                . $heading . '</h2>
            </td>
          </tr>

          <tr>
            <td style="padding:24px 24px 8px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111111;">'
              . $bodyHtml . '
            </td>
          </tr>

          <tr>
            <td style="padding:8px 24px 24px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#111111;">
              <p style="margin:0 0 4px;">Best regards,</p>
              <p style="margin:0;">The Prosperminds Team</p>
              <p style="margin:16px 0 0;">
                <a href="mailto:info@prosper-minds.com" style="color:#007A2F;">info@prosper-minds.com</a>
                &nbsp;|&nbsp; +254 740 582302
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 24px 24px;border-top:1px solid #e0e0e0;
                       font-family:Arial,sans-serif;font-size:12px;line-height:1.6;color:#777777;">
              You are receiving this because you subscribed at prosper-minds.com.
              <a href="' . $optOut . '" style="color:#777777;">Unsubscribe</a>.
              <br>Prosperminds, Twiga Towers, Moi Avenue, Nairobi, Kenya.
              <br>&copy; ' . $year . ' Prosperminds. All rights reserved.
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}
