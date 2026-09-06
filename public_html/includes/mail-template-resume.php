<?php

/**
 * The one reminder an unfinished registration earns.
 *
 * Deliberately short and deliberately plain. This reaches somebody who did not
 * ask to hear from us, so it says what it is in the first line, offers the way
 * back, and offers the way out. No calendar of other courses, no marketing.
 */

require_once __DIR__ . '/events.php';

function getResumeReminderSubject(array $event): string
{
    $title = trim((string) ($event['title'] ?? 'your course'));

    return 'You did not finish registering for ' . $title;
}

function getResumeReminderTemplate(array $row, array $event, string $resumeUrl, string $optOutUrl): string
{
    $name     = trim((string) ($row['first_name'] ?? ''));
    $greeting = $name === '' ? 'Hello,' : 'Dear ' . htmlspecialchars($name) . ',';

    $title    = htmlspecialchars((string) ($event['title'] ?? ''));
    $location = htmlspecialchars((string) ($event['location'] ?? ''));
    $dates    = htmlspecialchars((string) ($event['date_display'] ?? ''));

    $resume   = htmlspecialchars($resumeUrl);
    $optOut   = htmlspecialchars($optOutUrl);

    $detail = $location === '' && $dates === ''
        ? ''
        : "<div class='detail'><strong>{$title}</strong><br>{$location}<br>{$dates}</div>";

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color:#111111; line-height:1.7; background:#f8fafc; }
            .container { max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; }
            .header { background:#0b5d2a; color:#ffffff; padding:24px 32px; }
            .header img { max-width:150px; display:block; margin-bottom:14px; }
            .content { padding:28px 32px; }
            .detail { background:#f3fbf5; border-left:4px solid #00B140; padding:16px 18px; margin:22px 0; }
            .cta { display:inline-block; background:#00B140; color:#ffffff; text-decoration:none;
                   padding:13px 26px; font-weight:bold; margin:8px 0 4px; }
            .footer { padding:18px 32px 26px; color:#64748b; font-size:12px; border-top:1px solid #e5e7eb; }
            .footer a { color:#64748b; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <img src='https://www.prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds'>
                <h2 style='margin:0;'>Your registration is not finished</h2>
            </div>
            <div class='content'>
                <p>{$greeting}</p>

                <p>You started registering for one of our courses and did not get to the end. Nothing has been booked and no invoice has been raised.</p>

                {$detail}

                <p>If you would still like a place, this link takes you back to the form with your details already filled in:</p>

                <p><a class='cta' href='{$resume}'>Finish my registration</a></p>

                <p>The link works for the next seven days. If you have changed your mind, you can ignore this message and we will not write again about it.</p>

                <p>If you would like help instead, reply to this email or call +254 740 582302 and somebody will take the details over the phone.</p>
            </div>
            <div class='footer'>
                You are receiving this once because this address was entered into our
                registration form. <a href='{$optOut}'>Do not remind me</a> and we will
                not send this again.
                <br><br>
                Prosperminds, Twiga Towers, Moi Avenue, Nairobi, Kenya.
            </div>
        </div>
    </body>
    </html>";
}
