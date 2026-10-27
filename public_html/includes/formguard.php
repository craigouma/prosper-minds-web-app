<?php
/**
 * Bot protection for the public forms.
 *
 * WHAT IT IS UP AGAINST
 * ---------------------
 * The September wave sent real-looking corporate email addresses with random
 * letters in every free-text field (names of twenty random letters, a country
 * called "Medguivbfe"), submitted twice about five seconds apart, to the
 * registration and sponsorship forms alike. Each one made this site email an
 * invoice or a confirmation to a stranger's address. A hidden field alone does
 * not stop that, so the checks are layered, and none of them needs a third
 * party: no captcha service, no cross-border request.
 *
 *   honeypot     a field no person sees; any value is a bot
 *   timing       a signed render time; a form posted in a couple of seconds, or
 *                with no stamp at all, was not filled in by a person
 *   gibberish    random-looking text in two or more free-text fields
 *   links        a web address typed into a name
 *   rate         attempts per address, per hour and per day
 *   repeats      the same person submitting the same thing minutes apart; and a
 *                stamp is good for one successful submission, so it cannot be replayed
 *
 * Whatever is stopped is kept in form_quarantine with the reasons, for 45 days,
 * so a person wrongly stopped can be found and let through by hand.
 *
 * THE SAFETY CONTRACT
 * -------------------
 * Same as every secondary concern in this codebase: a failure inside this file
 * must never cost a genuine registration. Every entry point catches Throwable
 * and lets the submission through, and the whole guard can be switched off
 * with the form_guard_mode setting ('off') without a deploy.
 */

/** Per form: seconds a person needs at least, and attempts allowed per address. */
const PM_GUARD_FORMS = [
    'event'       => ['min' => 8, 'hour' => 6,  'day' => 15],
    'webinar'     => ['min' => 3, 'hour' => 10, 'day' => 40],
    'sponsorship' => ['min' => 6, 'hour' => 3,  'day' => 8],
    'newsletter'  => ['min' => 2, 'hour' => 6,  'day' => 20],
    'contact'     => ['min' => 6, 'hour' => 4,  'day' => 10],
];

const PM_GUARD_DUPLICATE_WINDOW = 600;

function pmGuardEnsureSchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // CREATE TABLE commits implicitly, so never inside someone's transaction.
        if ($pdo->inTransaction()) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `form_throttle` (
              `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
              `form` VARCHAR(24) NOT NULL,
              `ip_hash` CHAR(16) NOT NULL,
              `fingerprint` CHAR(16) DEFAULT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_throttle_ip` (`form`, `ip_hash`, `created_at`),
              KEY `idx_throttle_fp` (`form`, `fingerprint`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `form_quarantine` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `form` VARCHAR(24) NOT NULL,
              `severity` VARCHAR(8) NOT NULL,
              `reasons` VARCHAR(255) NOT NULL,
              `ip_hash` CHAR(16) NOT NULL,
              `summary` TEXT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_quarantine_when` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        // What is needed to let a stopped submission through later: the whole
        // form as it was sent, where it stands, and what the reCAPTCHA score was.
        // Added column by column, in one place, so an older table upgrades itself.
        $have = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_quarantine'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ([
            'status'      => "VARCHAR(12) NOT NULL DEFAULT 'blocked'",
            'payload'     => 'MEDIUMTEXT NULL',
            'score'       => 'DECIMAL(3,2) NULL',
            'resolved_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'resolved_by' => 'VARCHAR(64) NULL',
            'resolution'  => 'VARCHAR(255) NULL',
        ] as $column => $definition) {
            if (!in_array($column, $have, true)) {
                $pdo->exec('ALTER TABLE `form_quarantine` ADD COLUMN `' . $column . '` ' . $definition);
            }
        }
    } catch (Throwable $e) {
        error_log('formguard: schema check failed: ' . $e->getMessage());
    }
}

function pmGuardSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false || $value === null ? $default : (string) $value;
    } catch (Throwable $e) {
        return $default;
    }
}

/** 'enforce' unless an admin has switched the guard off. */
function pmGuardMode(PDO $pdo): string
{
    return strtolower(trim(pmGuardSetting($pdo, 'form_guard_mode', 'enforce'))) === 'off' ? 'off' : 'enforce';
}

/**
 * The secret that signs render stamps and hashes addresses. Made once and kept
 * in site_settings; if that cannot be written, a value derived from this
 * server is used, which is weaker but still stops a replayed stamp.
 */
function pmGuardSecret(PDO $pdo): string
{
    static $secret = null;

    if ($secret !== null) {
        return $secret;
    }

    $stored = pmGuardSetting($pdo, 'form_guard_secret');

    if (strlen($stored) >= 32) {
        return $secret = $stored;
    }

    try {
        $fresh = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = IF(CHAR_LENGTH(setting_value) >= 32, setting_value, VALUES(setting_value))'
        )->execute(['form_guard_secret', $fresh]);

        $stored = pmGuardSetting($pdo, 'form_guard_secret');
        if (strlen($stored) >= 32) {
            return $secret = $stored;
        }
    } catch (Throwable $e) {
        error_log('formguard: could not store a secret: ' . $e->getMessage());
    }

    return $secret = hash('sha256', __DIR__ . '|' . php_uname('n'));
}

/** The visitor's address, hashed. Raw addresses are not stored. */
function pmGuardIpHash(PDO $pdo): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return substr(hash_hmac('sha256', $ip, pmGuardSecret($pdo)), 0, 16);
}

/**
 * The two hidden fields every guarded form carries: the signed render time and
 * the honeypot. The honeypot is positioned off screen with inline style so it
 * stays hidden whatever stylesheet loads, and is named so browser autofill has
 * nothing to match it to.
 */
function pmGuardFields(string $form): string
{
    try {
        global $pdo;
        if (!$pdo instanceof PDO) {
            return '';
        }

        // The random part makes every stamp unique. Without it two visitors who
        // load a form in the same second would share one, and a stamp is good for
        // one successful submission, so the second would be told it had expired.
        $now   = time();
        $nonce = bin2hex(random_bytes(4));
        $sig   = substr(hash_hmac('sha256', $form . '|' . $now . '|' . $nonce, pmGuardSecret($pdo)), 0, 24);
        $id    = 'pm-hp-' . $form;

        return '<input type="hidden" name="pm_t" value="' . $now . '.' . $nonce . '.' . $sig . '">'
            . '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
            . '<label for="' . $id . '">Leave this field empty</label>'
            . '<input type="text" id="' . $id . '" name="pm_hp" value="" tabindex="-1" autocomplete="off">'
            . '</div>';
    } catch (Throwable $e) {
        error_log('formguard: could not draw the hidden fields: ' . $e->getMessage());

        return '';
    }
}

/**
 * Does this text look like random keystrokes rather than something a person
 * wrote? Structural tests only. A statistical "does this look like English"
 * test would flag real African and Asian names, which is the one mistake this
 * must not make, so each test below is something no real name or sentence does.
 */
function pmGuardLooksRandom(string $text): bool
{
    $words = preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($words as $word) {
        $length = mb_strlen($word);

        if ($length < 9) {
            continue;
        }

        // One unbroken run of sixteen or more letters: no first name is that long.
        if ($length >= 16) {
            return true;
        }

        // Capitals inside a word, three times or more: "riIHudMjNoluVWTQdE".
        // McDonald and DeShawn have one.
        if (preg_match_all('/\p{Ll}\p{Lu}/u', $word) >= 3) {
            return true;
        }

        // Six consonants in a row, counting y as a vowel: "mnvbdv".
        if (preg_match('/[^aeiouyàáâãäåèéêëìíîïòóôõöùúûü\P{Latin}]{6,}/iu', $word) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * Stop a submission, keep a copy for review, and say whether to tell the
 * sender. 'bot' is silent (they learn nothing), 'soft' gets a polite message a
 * real person can act on.
 *
 * @param array<int, string> $reasons
 * @return array{ok: false, severity: string, reasons: array<int, string>, message: string}
 */
function pmGuardBlock(
    PDO $pdo,
    string $form,
    string $severity,
    array $reasons,
    array $summary,
    string $message,
    array $payload = [],
    ?float $score = null
): array {
    try {
        pmGuardEnsureSchema($pdo);

        $clean = [];
        foreach ($summary as $key => $value) {
            $clean[$key] = mb_substr((string) $value, 0, 300);
        }

        $pdo->prepare(
            'INSERT INTO form_quarantine (form, severity, reasons, ip_hash, summary, payload, score) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $form,
            $severity,
            mb_substr(implode(', ', $reasons), 0, 255),
            pmGuardIpHash($pdo),
            json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $score,
        ]);

        $pdo->exec("DELETE FROM form_quarantine WHERE created_at < (NOW() - INTERVAL 45 DAY)");
    } catch (Throwable $e) {
        error_log('formguard: could not keep a blocked submission: ' . $e->getMessage());
    }

    return ['ok' => false, 'severity' => $severity, 'reasons' => $reasons, 'message' => $message];
}

/**
 * The form as it was sent, without the fields that only exist to be checked, so
 * an admin can later let it through. Kept as long as the rest of the record.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function pmGuardPayload(array $post): array
{
    $drop = [
        'csrf_token', 'pm_t', 'pm_hp', 'company', 'website', 'g-recaptcha-response', 'pm_rc',
        'pm_release', 'pm_release_id', 'attendee_first_name', 'attendee_last_name',
    ];
    $payload = array_diff_key($post, array_flip($drop));
    $json    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // A form is a few hundred bytes. Anything bigger is not a form.
    return $json !== false && strlen($json) <= 30000 ? $payload : [];
}

/**
 * The page status each guard reason maps to, for the handlers that answer with a
 * redirect and a status in the query string (webinar and newsletter).
 *
 * @param array<int, string> $reasons
 */
function pmGuardStatusFor(array $reasons): string
{
    return [
        'too-fast'         => 'fast',
        'no-stamp'         => 'stale',
        'rate'             => 'rate',
        'low-score'        => 'verify',
        'recaptcha-failed' => 'verify',
        'no-token'         => 'verify',
    ][$reasons[0] ?? ''] ?? 'invalid';
}

/** The token that proves a replayed submission came from an admin pressing "Let through". */
function pmGuardReleaseToken(PDO $pdo, int $id, string $email): string
{
    return substr(hash_hmac('sha256', 'release|' . $id . '|' . strtolower(trim($email)), pmGuardSecret($pdo)), 0, 32);
}

/**
 * Is this post an admin's replay of a stopped submission? Three things must all
 * hold: the token matches (so it was made with the site's secret), the row is
 * currently being released (not blocked, not already let through), and the
 * email is the one the row was stopped with.
 *
 * @param array<string, mixed> $post
 */
function pmGuardReleaseValid(PDO $pdo, array $post, string $who): bool
{
    $id    = (int) ($post['pm_release_id'] ?? 0);
    $token = (string) ($post['pm_release'] ?? '');

    if ($id <= 0 || $token === '' || $who === '') {
        return false;
    }

    if (!hash_equals(pmGuardReleaseToken($pdo, $id, $who), $token)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT summary FROM form_quarantine WHERE id = ? AND status = 'releasing' LIMIT 1");
    $stmt->execute([$id]);
    $summary = json_decode((string) $stmt->fetchColumn(), true);

    return is_array($summary) && strtolower(trim((string) ($summary['email'] ?? ''))) === strtolower(trim($who));
}

/**
 * Run every check on one submission.
 *
 * @param string               $form   a key of PM_GUARD_FORMS
 * @param array<string, mixed> $post   the submitted fields
 * @param array<int, string>   $text   names of the free-text fields to read
 * @param string               $who    the email, used to spot a repeat
 * @param string               $extra  what else makes two submissions "the same" (the event id)
 * @return array{ok: bool, severity: string, reasons: array<int, string>, message: string}
 */
function pmGuardCheck(PDO $pdo, string $form, array $post, array $text = [], string $who = '', string $extra = ''): array
{
    $pass = ['ok' => true, 'severity' => '', 'reasons' => [], 'message' => ''];

    try {
        $rules = PM_GUARD_FORMS[$form] ?? null;

        if ($rules === null || pmGuardMode($pdo) === 'off') {
            return $pass;
        }

        pmGuardEnsureSchema($pdo);

        $summary = ['email' => $who];
        foreach ($text as $field) {
            $summary[$field] = (string) ($post[$field] ?? '');
        }

        $payload = pmGuardPayload($post);

        // An admin has let this exact stopped submission through. See
        // pmGuardRelease(): the row is in the 'releasing' state only for the
        // few seconds the replay takes, and the token cannot be made without the
        // site's secret.
        if (pmGuardReleaseValid($pdo, $post, $who)) {
            return $pass;
        }

        // 1. The honeypot. No message: a bot is told nothing.
        foreach (['pm_hp', 'company'] as $trap) {
            if (trim((string) ($post[$trap] ?? '')) !== '') {
                return pmGuardBlock($pdo, $form, 'bot', ['honeypot'], $summary, '', $payload);
            }
        }

        $ip = pmGuardIpHash($pdo);

        // 2. How often this address has been here. Counted before the other
        // checks so a bot that fails them is still counted.
        $hour = $day = 0;
        $stmt = $pdo->prepare(
            'SELECT SUM(created_at >= (NOW() - INTERVAL 1 HOUR)), COUNT(*)
               FROM form_throttle
              WHERE form = ? AND ip_hash = ? AND fingerprint IS NULL AND created_at >= (NOW() - INTERVAL 1 DAY)'
        );
        $stmt->execute([$form, $ip]);
        [$hour, $day] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0]);

        $pdo->prepare('INSERT INTO form_throttle (form, ip_hash) VALUES (?, ?)')->execute([$form, $ip]);

        if (random_int(1, 50) === 1) {
            $pdo->exec('DELETE FROM form_throttle WHERE created_at < (NOW() - INTERVAL 2 DAY)');
        }

        if ($hour >= $rules['hour'] || $day >= $rules['day']) {
            return pmGuardBlock($pdo, $form, 'soft', ['rate'], $summary,
                'There have been a lot of submissions from your connection. Please wait a little and try again, or email info@prosper-minds.com.', $payload);
        }

        // 3. Timing: the render stamp has to be ours, and old enough.
        $stamp = (string) ($post['pm_t'] ?? '');
        $valid = false;
        $age   = 0;

        if (preg_match('/^(\d{9,11})\.([a-f0-9]{8})\.([a-f0-9]{24})$/', $stamp, $m) === 1) {
            $expected = substr(hash_hmac('sha256', $form . '|' . $m[1] . '|' . $m[2], pmGuardSecret($pdo)), 0, 24);
            $valid    = hash_equals($expected, $m[3]);
            $age      = time() - (int) $m[1];
        }

        if (!$valid) {
            return pmGuardBlock($pdo, $form, 'soft', ['no-stamp'], $summary,
                'This form has expired. Please reload the page and try again.', $payload);
        }

        if ($age < $rules['min']) {
            return pmGuardBlock($pdo, $form, 'soft', ['too-fast'], $summary,
                'That was very quick. Please check your details and submit again.', $payload);
        }

        // A stamp is good for one successful submission. Without this a bot
        // could keep one stamp and replay it for as long as it likes. A failed
        // attempt does not use the stamp up (see the end of this function), so a
        // person who has to fix a typo and send again is not caught by it.
        $stampPrint = substr(hash('sha256', $stamp), 0, 16);
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM form_throttle WHERE form = 'stamp' AND fingerprint = ? AND created_at >= (NOW() - INTERVAL 2 DAY)"
        );
        $stmt->execute([$stampPrint]);
        $stampUsed = (int) $stmt->fetchColumn() > 0;

        // 4. What was typed.
        $random = 0;
        $links  = false;
        foreach ($text as $field) {
            $value = (string) ($post[$field] ?? '');

            if ($value !== '' && pmGuardLooksRandom($value)) {
                $random++;
            }

            if (in_array($field, ['name', 'first_name', 'last_name', 'organization', 'organisation', 'country'], true)
                && preg_match('~https?://|www\.|\.(com|net|org|ru|xyz)/~i', $value) === 1) {
                $links = true;
            }
        }

        if ($random >= 2) {
            return pmGuardBlock($pdo, $form, 'bot', ['random-text'], $summary, '', $payload);
        }

        if ($links) {
            return pmGuardBlock($pdo, $form, 'bot', ['link-in-name'], $summary, '', $payload);
        }

        // 5. The same person, the same thing, minutes apart. Checked before the
        // used-stamp rule takes effect, so a double click that registered the
        // first time is told "we already have this", not "the form expired".
        $print = $who === '' ? '' : substr(hash_hmac('sha256', strtolower($who) . '|' . $extra, pmGuardSecret($pdo)), 0, 16);

        if ($print !== '') {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM form_throttle
                  WHERE form = ? AND fingerprint = ? AND created_at >= (NOW() - INTERVAL ' . PM_GUARD_DUPLICATE_WINDOW . ' SECOND)'
            );
            $stmt->execute([$form, $print]);

            if ((int) $stmt->fetchColumn() > 0) {
                return pmGuardBlock($pdo, $form, 'soft', ['repeat'], $summary,
                    'We already have this. If you do not see a confirmation email soon, write to info@prosper-minds.com.', $payload);
            }
        }

        if ($stampUsed) {
            return pmGuardBlock($pdo, $form, 'soft', ['no-stamp'], $summary,
                'This form has expired. Please reload the page and try again.', $payload);
        }

        // Accepted: only now is the stamp used up and the repeat window opened.
        $pdo->prepare("INSERT INTO form_throttle (form, ip_hash, fingerprint) VALUES ('stamp', ?, ?)")
            ->execute([$ip, $stampPrint]);

        if ($print !== '') {
            $pdo->prepare('INSERT INTO form_throttle (form, ip_hash, fingerprint) VALUES (?, ?, ?)')
                ->execute([$form, $ip, $print]);
        }

        return $pass;
    } catch (Throwable $e) {
        // Fail open: a broken guard must never turn away a genuine registration.
        error_log('formguard: check failed, letting the submission through: ' . $e->getMessage());

        return $pass;
    }
}
