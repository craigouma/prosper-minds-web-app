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
 *   repeats      the same person submitting the same thing minutes apart
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

        $now = time();
        $sig = substr(hash_hmac('sha256', $form . '|' . $now, pmGuardSecret($pdo)), 0, 24);
        $id  = 'pm-hp-' . $form;

        return '<input type="hidden" name="pm_t" value="' . $now . '.' . $sig . '">'
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
function pmGuardBlock(PDO $pdo, string $form, string $severity, array $reasons, array $summary, string $message): array
{
    try {
        pmGuardEnsureSchema($pdo);

        $clean = [];
        foreach ($summary as $key => $value) {
            $clean[$key] = mb_substr((string) $value, 0, 300);
        }

        $pdo->prepare(
            'INSERT INTO form_quarantine (form, severity, reasons, ip_hash, summary) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $form,
            $severity,
            mb_substr(implode(', ', $reasons), 0, 255),
            pmGuardIpHash($pdo),
            json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $pdo->exec("DELETE FROM form_quarantine WHERE created_at < (NOW() - INTERVAL 45 DAY)");
    } catch (Throwable $e) {
        error_log('formguard: could not keep a blocked submission: ' . $e->getMessage());
    }

    return ['ok' => false, 'severity' => $severity, 'reasons' => $reasons, 'message' => $message];
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

        // 1. The honeypot. No message: a bot is told nothing.
        foreach (['pm_hp', 'company'] as $trap) {
            if (trim((string) ($post[$trap] ?? '')) !== '') {
                return pmGuardBlock($pdo, $form, 'bot', ['honeypot'], $summary, '');
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
                'There have been a lot of submissions from your connection. Please wait a little and try again, or email info@prosper-minds.com.');
        }

        // 3. Timing: the render stamp has to be ours, and old enough.
        $stamp = (string) ($post['pm_t'] ?? '');
        $valid = false;
        $age   = 0;

        if (preg_match('/^(\d{9,11})\.([a-f0-9]{24})$/', $stamp, $m) === 1) {
            $expected = substr(hash_hmac('sha256', $form . '|' . $m[1], pmGuardSecret($pdo)), 0, 24);
            $valid    = hash_equals($expected, $m[2]);
            $age      = time() - (int) $m[1];
        }

        if (!$valid) {
            return pmGuardBlock($pdo, $form, 'soft', ['no-stamp'], $summary,
                'This form has expired. Please reload the page and try again.');
        }

        if ($age < $rules['min']) {
            return pmGuardBlock($pdo, $form, 'soft', ['too-fast'], $summary,
                'That was very quick. Please check your details and submit again.');
        }

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
            return pmGuardBlock($pdo, $form, 'bot', ['random-text'], $summary, '');
        }

        if ($links) {
            return pmGuardBlock($pdo, $form, 'bot', ['link-in-name'], $summary, '');
        }

        // 5. The same person, the same thing, minutes apart.
        if ($who !== '') {
            $print = substr(hash_hmac('sha256', strtolower($who) . '|' . $extra, pmGuardSecret($pdo)), 0, 16);

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM form_throttle
                  WHERE form = ? AND fingerprint = ? AND created_at >= (NOW() - INTERVAL ' . PM_GUARD_DUPLICATE_WINDOW . ' SECOND)'
            );
            $stmt->execute([$form, $print]);

            if ((int) $stmt->fetchColumn() > 0) {
                return pmGuardBlock($pdo, $form, 'soft', ['repeat'], $summary,
                    'We already have this. If you do not see a confirmation email soon, write to info@prosper-minds.com.');
            }

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
