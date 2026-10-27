<?php
/**
 * Runs the "let a stopped person through" scenarios against the LOCAL stack and
 * prints what happened as JSON, for verify.sh to assert on. Not a test on its
 * own: it needs the local database, Mailpit, the stand-in verifier on :8083, and
 * a local site on :8080 started with several workers (releasing makes a request
 * from the server to its own site, which a one-worker server cannot answer while
 * it is busy answering the first).
 *
 *   php local-dev/check-release.php
 */
chdir(__DIR__ . '/../public_html');
require 'includes/config.php';
require 'includes/audit.php';
require 'includes/guard-release.php';

$base = 'http://127.0.0.1:8080';
$out  = [];

function mails(string $to): int
{
    $d = json_decode((string) @file_get_contents('http://127.0.0.1:8025/api/v1/messages?limit=500'), true);
    $n = 0;
    foreach (($d['messages'] ?? []) as $m) {
        foreach ($m['To'] as $t) {
            if (strcasecmp($t['Address'], $to) === 0) {
                $n++;
            }
        }
    }

    return $n;
}

$one = static fn (string $sql) => $pdo->query($sql)->fetchColumn();

foreach (['form_quarantine', 'form_throttle'] as $t) { $pdo->exec("DELETE FROM $t"); }
$pdo->exec("DELETE FROM event_registrations WHERE email LIKE '%@release.example'");
$pdo->exec("DELETE FROM webinar_registrations WHERE email LIKE '%@release.example'");
$pdo->exec("DELETE FROM cms_audit_log WHERE summary LIKE '%@release.example%'");
$pdo->exec("REPLACE INTO site_settings (setting_key,setting_value) VALUES ('form_guard_mode','enforce'),('recaptcha_site_key','localsitekeylocalsitekey'),('recaptcha_secret_key','localsecretlocalsecret'),('notify_registration_emails','team@release.example')");
@file_get_contents('http://127.0.0.1:8025/api/v1/messages', false, stream_context_create(['http' => ['method' => 'DELETE']]));

// 1. A real registration stopped by a low score, through the real handler.
$jar = tempnam(sys_get_temp_dir(), 'rel');
$ch  = curl_init($base . '/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
preg_match('/name="csrf_token" value="([a-f0-9]+)"/', (string) curl_exec($ch), $m);
$post = [
    'csrf_token' => $m[1], 'g-recaptcha-response' => 'low:event_registration', 'pm_rc' => '', 'event_id' => 5,
    'event_name' => $one('SELECT title FROM events WHERE id=5'), 'tier' => 'vip', 'first_name' => 'Wanjiru', 'last_name' => 'Kamau',
    'phone' => '+254711222333', 'email' => 'wanjiru@release.example', 'organization' => 'County Treasury', 'country' => 'Kenya',
    'address' => 'Nakuru', 'consent' => 'yes',
    'attendees' => ['first_name' => ['Wanjiru', 'Peter'], 'last_name' => ['Kamau', 'Mwangi'], 'email' => ['wanjiru@release.example', 'peter@release.example']],
];
curl_setopt_array($ch, [CURLOPT_URL => $base . '/process-registration.php', CURLOPT_POSTFIELDS => http_build_query($post)]);
$r = json_decode((string) curl_exec($ch), true);
$out['stopped_refused']      = $r['success'] === false && str_contains((string) $r['message'], 'could not confirm');
$out['stopped_not_stored']   = (int) $one("SELECT COUNT(*) FROM event_registrations WHERE email='wanjiru@release.example'") === 0;
$id = (int) $one('SELECT MAX(id) FROM form_quarantine');
$out['kept_whole_form']      = (int) $one("SELECT payload IS NOT NULL AND JSON_LENGTH(JSON_EXTRACT(payload, '$.attendees.first_name')) = 2 FROM form_quarantine WHERE id=$id");

// 2. The admin lets her through.
$res = pmGuardRelease($pdo, $id, 'Craig');
$out['release_ok']           = $res['ok'];
$out['release_message']      = $res['message'];
$out['release_link']         = $res['link'];
$reg = $pdo->query("SELECT attendee_count, tier, phone, address, total_amount, invoice_number, consent FROM event_registrations WHERE email='wanjiru@release.example'")->fetch(PDO::FETCH_ASSOC) ?: [];
$out['reg'] = $reg;
$out['person_emails']        = mails('wanjiru@release.example');
$out['team_alerts']          = mails('team@release.example');
$row = $pdo->query("SELECT status, resolved_by FROM form_quarantine WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
$out['row_status']           = $row['status'] . '|' . $row['resolved_by'];
$out['second_release']       = pmGuardRelease($pdo, $id, 'Craig')['ok'];
$out['registrations_count']  = (int) $one("SELECT COUNT(*) FROM event_registrations WHERE email='wanjiru@release.example'");
$out['audited']              = (int) $one("SELECT COUNT(*) FROM cms_audit_log WHERE action='blocked_release' AND summary LIKE '%wanjiru@release.example%'");

// 3. Rows stopped before the whole form was kept.
$ins = $pdo->prepare("INSERT INTO form_quarantine (form, severity, reasons, ip_hash, summary) VALUES (?, 'soft', 'rate', 'aaaaaaaaaaaaaaaa', ?)");
$ins->execute(['webinar', json_encode(['email' => 'oldweb@release.example', 'name' => 'Old Webinar Person', 'organization' => 'MoF', 'country' => 'Ghana'])]);
$wid = (int) $pdo->lastInsertId();
$ins->execute(['event', json_encode(['email' => 'oldevent@release.example', 'first_name' => 'Old', 'last_name' => 'EventPerson', 'organization' => 'MoF', 'country' => 'Kenya'])]);
$eid = (int) $pdo->lastInsertId();
$out['old_webinar_can_release'] = pmGuardCanRelease(pmGuardRow($pdo, $wid));
$out['old_event_can_release']   = pmGuardCanRelease(pmGuardRow($pdo, $eid));
$res = pmGuardRelease($pdo, $wid, 'Craig', ['session_id' => 3]);
$out['old_webinar_release']     = $res['ok'];
$out['old_webinar_session']     = (int) $one("SELECT session_id FROM webinar_registrations WHERE email='oldweb@release.example'");
$out['old_event_release']       = pmGuardRelease($pdo, $eid, 'Craig')['ok'];
$out['old_event_still_waiting'] = $one("SELECT status FROM form_quarantine WHERE id=$eid");
$inv = pmGuardInvite($pdo, $eid, 5, 'Craig');
$out['invite_ok']               = $inv['ok'];
$out['invite_emails']           = mails('oldevent@release.example');
$out['invite_no_registration']  = (int) $one("SELECT COUNT(*) FROM event_registrations WHERE email='oldevent@release.example'") === 0;
$out['invite_status']           = $one("SELECT status FROM form_quarantine WHERE id=$eid");
$out['second_invite']           = pmGuardInvite($pdo, $eid, 5, 'Craig')['ok'];
$out['invite_no_school']        = pmGuardInvite($pdo, $wid, 0, 'Craig')['ok'];

// 4. The server cannot reach its own site: the row stays stopped, with a reason.
$ins->execute(['webinar', json_encode(['email' => 'unreachable@release.example', 'name' => 'Unreachable Test'])]);
$uid = (int) $pdo->lastInsertId();
putenv('PM_LOOPBACK_ORIGIN=http://127.0.0.1:9');
$_SERVER['PM_LOOPBACK_ORIGIN'] = 'http://127.0.0.1:9';
$res = pmGuardRelease($pdo, $uid, 'Craig', ['session_id' => 3]);
$out['unreachable_ok']      = $res['ok'];
$out['unreachable_status']  = $one("SELECT status FROM form_quarantine WHERE id=$uid");
$out['unreachable_message'] = $res['message'];

foreach (['form_quarantine', 'form_throttle'] as $t) { $pdo->exec("DELETE FROM $t"); }
$pdo->exec("DELETE FROM event_registrations WHERE email LIKE '%@release.example'");
$pdo->exec("DELETE FROM webinar_registrations WHERE email LIKE '%@release.example'");
$pdo->exec("DELETE FROM site_settings WHERE setting_key IN ('notify_registration_emails','recaptcha_site_key','recaptcha_secret_key','form_guard_mode')");
@unlink($jar);

echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
