<?php
/**
 * Google reCAPTCHA v3, used by the form guard (includes/formguard.php).
 *
 * v3 never asks a visitor to do anything. The page asks Google for a token when
 * the form is submitted, and this file asks Google how likely it is that the
 * token came from a person: a score from 0.0 (a bot) to 1.0 (a person).
 *
 * The keys are settings (recaptcha_site_key, recaptcha_secret_key), edited under
 * Settings, never code. With either one missing the whole thing is off and the
 * guard behaves as it did before reCAPTCHA existed.
 *
 * A reCAPTCHA failure must never turn a person away because of OUR problem: if
 * Google cannot be reached, or the secret key is wrong, the verdict says so and
 * the guard lets the submission through on its other checks instead of blocking
 * everybody. Only a token that Google itself says is invalid or scores low is a
 * reason to stop a submission.
 */

const PM_RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

/** The action name each form asks Google to score, and the guard checks it came back. */
const PM_RECAPTCHA_ACTIONS = [
    'event'       => 'event_registration',
    'webinar'     => 'webinar_signup',
    'sponsorship' => 'sponsorship_enquiry',
    'newsletter'  => 'newsletter_signup',
    'contact'     => 'contact_enquiry',
];

function pmRecaptchaSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : trim((string) $value);
    } catch (Throwable $e) {
        return $default;
    }
}

function pmRecaptchaSiteKey(PDO $pdo): string
{
    return pmRecaptchaSetting($pdo, 'recaptcha_site_key');
}

function pmRecaptchaEnabled(PDO $pdo): bool
{
    return pmRecaptchaSiteKey($pdo) !== '' && pmRecaptchaSetting($pdo, 'recaptcha_secret_key') !== '';
}

/** The lowest score still treated as a person. Google suggests 0.5; 0.3 turns fewer real people away. */
function pmRecaptchaMinScore(PDO $pdo): float
{
    $value = (float) pmRecaptchaSetting($pdo, 'recaptcha_min_score', '0.3');

    return max(0.05, min(0.95, $value > 0 ? $value : 0.3));
}

/** Overridable from the environment so the local test suite can point it at a stand-in. Never from the admin panel. */
function pmRecaptchaVerifyUrl(): string
{
    $override = function_exists('pm_env') ? (string) pm_env('PM_RECAPTCHA_VERIFY_URL', '') : '';

    return $override !== '' ? $override : PM_RECAPTCHA_VERIFY_URL;
}

/**
 * Ask Google about one token.
 *
 * @return array{reached: bool, success: bool, score: ?float, action: string, hostname: string, errors: array<int, string>}
 */
function pmRecaptchaVerify(string $secret, string $token, string $ip = ''): array
{
    $out = ['reached' => false, 'success' => false, 'score' => null, 'action' => '', 'hostname' => '', 'errors' => []];

    $fields = http_build_query(array_filter(['secret' => $secret, 'response' => $token, 'remoteip' => $ip], static fn ($v) => $v !== ''));
    $body   = false;

    if (function_exists('curl_init')) {
        $ch = curl_init(pmRecaptchaVerifyUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $body = curl_exec($ch);
    } else {
        $body = @file_get_contents(pmRecaptchaVerifyUrl(), false, stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $fields,
            'timeout' => 5,
        ]]));
    }

    $data = is_string($body) ? json_decode($body, true) : null;

    if (!is_array($data) || !array_key_exists('success', $data)) {
        return $out;
    }

    $out['reached']  = true;
    $out['success']  = $data['success'] === true;
    $out['score']    = isset($data['score']) ? (float) $data['score'] : null;
    $out['action']   = (string) ($data['action'] ?? '');
    $out['hostname'] = strtolower((string) ($data['hostname'] ?? ''));
    $out['errors']   = array_values(array_map('strval', (array) ($data['error-codes'] ?? [])));

    return $out;
}

/** Is this the site's own address? A token minted on some other site must not count here. */
function pmRecaptchaHostOk(string $hostname): bool
{
    if ($hostname === '') {
        return true;
    }

    $allowed = ['prosper-minds.com'];
    $extra   = function_exists('pm_env') ? (string) pm_env('PM_RECAPTCHA_ALLOW_HOST', '') : '';

    foreach (array_filter(array_map('trim', explode(',', $extra))) as $host) {
        $allowed[] = strtolower($host);
    }

    foreach ($allowed as $host) {
        if ($hostname === $host || str_ends_with($hostname, '.' . $host)) {
            return true;
        }
    }

    return false;
}

/**
 * One verdict for one submission.
 *
 *   off          reCAPTCHA is not set up; nothing to judge
 *   unavailable  the page said its script could not run (blocked, offline)
 *   missing      no token and no such claim: a script that never loaded the form
 *   unreachable  Google did not answer: our problem, not the visitor's
 *   misconfigured the secret key is wrong: our problem, not the visitor's
 *   invalid      Google says the token is expired, used before, or for another site or form
 *   low          a real token with a score under the minimum
 *   ok           a real token with a good score
 *
 * @param array<string, mixed> $post
 * @return array{state: string, score: ?float, detail: string}
 */
function pmRecaptchaAssess(PDO $pdo, string $form, array $post, string $ip = ''): array
{
    $verdict = static fn (string $state, ?float $score = null, string $detail = ''): array
        => ['state' => $state, 'score' => $score, 'detail' => $detail];

    if (!pmRecaptchaEnabled($pdo)) {
        return $verdict('off');
    }

    $token = trim((string) ($post['g-recaptcha-response'] ?? ''));

    if ($token === '') {
        return (string) ($post['pm_rc'] ?? '') === 'unavailable' ? $verdict('unavailable') : $verdict('missing');
    }

    $r = pmRecaptchaVerify(pmRecaptchaSetting($pdo, 'recaptcha_secret_key'), mb_substr($token, 0, 4096), $ip);

    if (!$r['reached']) {
        return $verdict('unreachable');
    }

    if (array_intersect($r['errors'], ['invalid-input-secret', 'missing-input-secret', 'bad-request'])) {
        return $verdict('misconfigured', null, implode(',', $r['errors']));
    }

    if (!$r['success']) {
        return $verdict('invalid', null, implode(',', $r['errors']));
    }

    if (!pmRecaptchaHostOk($r['hostname'])) {
        return $verdict('invalid', $r['score'], 'wrong-host:' . $r['hostname']);
    }

    $expected = PM_RECAPTCHA_ACTIONS[$form] ?? '';
    if ($expected !== '' && $r['action'] !== '' && $r['action'] !== $expected) {
        return $verdict('invalid', $r['score'], 'wrong-action:' . $r['action']);
    }

    // A success with no score is a v2 key, or Google's test keys. It cannot be
    // scored, and a verified token is still the strongest signal there is.
    if ($r['score'] === null) {
        return $verdict('ok', null, 'no-score');
    }

    return $r['score'] >= pmRecaptchaMinScore($pdo) ? $verdict('ok', $r['score']) : $verdict('low', $r['score']);
}

/**
 * Ask Google whether it accepts the saved secret key, by sending it a token that
 * is certain to be invalid: a good secret answers "invalid response", a wrong one
 * answers "invalid secret". Used by Settings right after the keys are saved.
 *
 * @return array{ok: bool, message: string}
 */
function pmRecaptchaCheckKeys(PDO $pdo): array
{
    $r = pmRecaptchaVerify(pmRecaptchaSetting($pdo, 'recaptcha_secret_key'), 'pm-settings-check');

    if (!$r['reached']) {
        return ['ok' => false, 'message' => 'The keys are saved, but Google could not be reached to check them just now. The forms keep working either way.'];
    }

    if (array_intersect($r['errors'], ['invalid-input-secret', 'missing-input-secret'])) {
        return ['ok' => false, 'message' => 'The keys are saved, but Google does not accept the secret key. Check it was copied whole. Until it is right the forms use the older checks and nobody is turned away.'];
    }

    return ['ok' => true, 'message' => 'Google accepted the secret key. The site key is checked in the browser the first time a form is sent; if it is wrong the forms use the older checks.'];
}
