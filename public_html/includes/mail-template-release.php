<?php
/**
 * The email that invites a person whose registration was stopped by mistake to
 * register again. Short and plain: it says what happened in the first lines,
 * owns it, and offers the way back. No marketing.
 */

function getRegistrationInviteTemplate(string $firstName, array $event, string $registerUrl): string
{
    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $greeting = $firstName === '' ? 'Hello,' : 'Dear ' . $e($firstName) . ',';
    $title    = $e((string) ($event['title'] ?? ''));
    $where    = $e(trim((string) ($event['location'] ?? '')));
    $when     = $e(trim((string) ($event['date_display'] ?? '')));
    $detail   = $where === '' && $when === '' ? '' : '<br>' . $where . ($where !== '' && $when !== '' ? '<br>' : '') . $when;

    return "
<div style='font-family:Inter,Arial,sans-serif;max-width:620px;margin:0 auto;'>
    <div style='background:#111;padding:28px 30px;border-radius:8px 8px 0 0;'>
        <img src='https://prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds' style='height:40px;filter:brightness(0) invert(1);margin-bottom:16px;display:block;'>
        <h1 style='color:#fff;margin:0;font-size:1.4rem;'>Please register again</h1>
    </div>
    <div style='background:#fff;padding:28px 30px;border:1px solid #eee;border-radius:0 0 8px 8px;color:#111;line-height:1.65;'>
        <p>{$greeting}</p>
        <p>You recently tried to register for the school below on our website. A problem with our security check stopped it before it reached us, so we have no registration for you and no invoice has been issued. We are sorry.</p>
        <p style='background:#f3fbf5;border-left:4px solid #00B140;padding:14px 18px;'><strong>{$title}</strong>{$detail}</p>
        <p>Please register again here. It takes a few minutes.</p>
        <p><a href='" . $e($registerUrl) . "' style='display:inline-block;background:#00B140;color:#111;text-decoration:none;font-weight:700;padding:13px 26px;border-radius:4px;'>Register for this school</a></p>
        <p style='color:#555;font-size:.92rem;'>If it does not work for you, reply to this email or write to info@prosper-minds.com and we will register you directly.</p>
        <p style='margin-top:26px;'>With best regards,<br><strong>The Prosperminds Team</strong></p>
    </div>
</div>";
}
