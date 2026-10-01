<?php
/**
 * PFM Insight Live emails: the confirmation sent the moment someone
 * registers, and the reminder sent before each session. Branded the same way
 * as every other outbound email on the site (dark header, logo, green
 * accent), so a confirmation for a free webinar does not read as less
 * official than an invoice.
 */

/** Wraps the shared chrome around one block of body HTML. */
function pmWebinarEmailShell(string $heading, string $subheading, string $bodyHtml, string $unsubscribeUrl): string
{
    $heading    = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    $subheading = htmlspecialchars($subheading, ENT_QUOTES, 'UTF-8');

    return "
<div style='font-family:Inter,Arial,sans-serif;max-width:620px;margin:0 auto;'>
    <div style='background:#111;padding:36px 30px;border-radius:8px 8px 0 0;'>
        <img src='https://prosper-minds.com/assets/images/fisrt-logo.png' alt='Prosperminds' style='height:40px;filter:brightness(0) invert(1);margin-bottom:20px;display:block;'>
        <h1 style='color:#fff;margin:0 0 8px;font-size:1.5rem;'>$heading</h1>
        <p style='color:#aaa;margin:0;font-size:.95rem;'>$subheading</p>
    </div>
    <div style='background:#fff;padding:32px 30px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
        $bodyHtml
        <div style='background:#111;border-radius:8px;padding:20px 24px;margin-top:24px;'>
            <p style='color:#aaa;font-size:.85rem;margin:0 0 8px;'>Need to reach us directly?</p>
            <p style='color:#00B140;font-size:.9rem;margin:0;'>
                info@prosper-minds.com &nbsp;|&nbsp; +254 740 582302 &nbsp;|&nbsp; +254 722 998105
            </p>
        </div>
        <p style='margin-top:28px;color:#555;font-size:.88rem;'>
            With intelligence, purpose, and anticipation,<br>
            <strong style='color:#111;'>The Prosperminds Team</strong>
        </p>
        <p style='margin-top:20px;color:#999;font-size:.78rem;'>
            You are receiving this because you registered for PFM Insight Live.
            <a href='" . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . "' style='color:#999;'>Unsubscribe</a> at any time.
        </p>
    </div>
</div>";
}

/** One line per session, used by both the series-confirmation and the detail panel. */
function pmWebinarSessionLine(array $session): string
{
    $title = htmlspecialchars((string) $session['title'], ENT_QUOTES, 'UTF-8');
    $date  = htmlspecialchars(pmWebinarDateLong($session), ENT_QUOTES, 'UTF-8');
    $time  = htmlspecialchars((string) $session['time_label'], ENT_QUOTES, 'UTF-8');

    return "<strong>$title</strong><br><span style='color:#666;'>$date, $time</span>";
}

/** @param array<string, mixed> $registration From pmWebinarRegister()'s return, plus name/email. */
function getWebinarConfirmationSubject(array $session, bool $isSeries): string
{
    return $isSeries
        ? 'You are registered for PFM Insight Live'
        : 'You are registered: ' . (string) $session['title'];
}

function getWebinarConfirmationTemplate(
    string $name,
    bool $isSeries,
    ?array $session,
    array $upcoming,
    string $unsubscribeUrl
): string {
    $firstName = htmlspecialchars(trim(explode(' ', $name)[0] ?? $name), ENT_QUOTES, 'UTF-8');

    if ($isSeries) {
        $sessionRows = implode('', array_map(
            static fn (array $s): string => "<tr><td style='padding:10px 14px;border-bottom:1px solid #eee;'>" . pmWebinarSessionLine($s) . '</td></tr>',
            $upcoming
        ));

        $zoomLink = (string) ($upcoming[0]['zoom_link'] ?? '');
        $zoomBlock = $zoomLink !== '' ? "
        <div style='background:#f8fdf8;border:1px solid #d0e8d0;border-radius:8px;padding:20px 24px;margin:24px 0;text-align:center;'>
            <div style='font-weight:700;color:#00B140;font-size:.85rem;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;'>
                Join link (the same one every month)
            </div>
            <a href='" . htmlspecialchars($zoomLink, ENT_QUOTES, 'UTF-8') . "' style='color:#111;font-weight:700;word-break:break-all;'>
                " . htmlspecialchars($zoomLink, ENT_QUOTES, 'UTF-8') . "
            </a>
        </div>" : '';

        $body = "
        <p style='color:#333;line-height:1.8;'>
            Thank you, $firstName. You are registered for every PFM Insight Live session,
            one hour, once a month, free to join. We will send a reminder with this same
            link before each one.
        </p>
        $zoomBlock
        <div style='margin:24px 0;'>
            <div style='font-weight:700;color:#333;margin-bottom:10px;font-size:.9rem;'>UPCOMING SESSIONS</div>
            <table style='width:100%;border-collapse:collapse;font-size:.9rem;'>$sessionRows</table>
        </div>
        <p style='color:#555;font-size:.88rem;'>You can leave the series at any time using the link at the bottom of this email.</p>";

        return pmWebinarEmailShell('Thank You, ' . $firstName . '!', 'You are on the list for PFM Insight Live.', $body, $unsubscribeUrl);
    }

    $session ??= [];
    $title = htmlspecialchars((string) ($session['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date  = htmlspecialchars(pmWebinarDateLong($session), ENT_QUOTES, 'UTF-8');
    $time  = htmlspecialchars((string) ($session['time_label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $zoom  = (string) ($session['zoom_link'] ?? '');

    $zoomBlock = $zoom !== '' ? "
        <div style='background:#f8fdf8;border:1px solid #d0e8d0;border-radius:8px;padding:20px 24px;margin:24px 0;text-align:center;'>
            <div style='font-weight:700;color:#00B140;font-size:.85rem;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;'>Join link</div>
            <a href='" . htmlspecialchars($zoom, ENT_QUOTES, 'UTF-8') . "' style='color:#111;font-weight:700;word-break:break-all;'>" . htmlspecialchars($zoom, ENT_QUOTES, 'UTF-8') . "</a>
        </div>" : '';

    $body = "
        <p style='color:#333;line-height:1.8;'>
            Thank you, $firstName. You are registered for <strong>$title</strong> on <strong>$date</strong> at <strong>$time</strong>.
            We will send a reminder with this link before the session.
        </p>
        $zoomBlock";

    return pmWebinarEmailShell('Thank You, ' . $firstName . '!', 'You are registered for ' . $title . '.', $body, $unsubscribeUrl);
}

function getWebinarReminderSubject(array $session): string
{
    return 'Tomorrow: ' . (string) $session['title'];
}

function getWebinarReminderTemplate(array $registration, array $session, string $unsubscribeUrl): string
{
    $firstName = htmlspecialchars(trim(explode(' ', (string) $registration['name'])[0] ?? ''), ENT_QUOTES, 'UTF-8');
    $title     = htmlspecialchars((string) $session['title'], ENT_QUOTES, 'UTF-8');
    $date      = htmlspecialchars(pmWebinarDateLong($session), ENT_QUOTES, 'UTF-8');
    $time      = htmlspecialchars((string) $session['time_label'], ENT_QUOTES, 'UTF-8');
    $zoom      = (string) $session['zoom_link'];

    $zoomBlock = $zoom !== '' ? "
        <div style='background:#f8fdf8;border:1px solid #d0e8d0;border-radius:8px;padding:20px 24px;margin:24px 0;text-align:center;'>
            <div style='font-weight:700;color:#00B140;font-size:.85rem;letter-spacing:.06em;text-transform:uppercase;margin-bottom:12px;'>Join link</div>
            <a href='" . htmlspecialchars($zoom, ENT_QUOTES, 'UTF-8') . "' style='color:#111;font-weight:700;word-break:break-all;'>" . htmlspecialchars($zoom, ENT_QUOTES, 'UTF-8') . "</a>
        </div>" : '';

    $body = "
        <p style='color:#333;line-height:1.8;'>
            Hi $firstName, a reminder that <strong>$title</strong> runs on <strong>$date</strong> at <strong>$time</strong>.
        </p>
        $zoomBlock";

    return pmWebinarEmailShell('See you soon', $title, $body, $unsubscribeUrl);
}
