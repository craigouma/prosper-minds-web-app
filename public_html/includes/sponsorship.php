<?php

const PM_SPONSORSHIP_MESSAGE_MAX = 5000;

function ensureSponsorshipEnquirySchema(PDO $pdo): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // CREATE TABLE implicitly commits in MySQL, so a call inside someone
        // else's transaction would commit their half-written work early.
        if ($pdo->inTransaction()) {
            error_log('sponsorship_enquiries: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `sponsorship_enquiries` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `first_name` VARCHAR(120) NOT NULL,
              `last_name` VARCHAR(120) NOT NULL,
              `organisation` VARCHAR(200) NOT NULL,
              `email` VARCHAR(190) NOT NULL,
              `phone` VARCHAR(60) DEFAULT NULL,
              `country` VARCHAR(120) DEFAULT NULL,
              `tier` VARCHAR(64) DEFAULT NULL,
              `events` TEXT DEFAULT NULL,
              `message` TEXT DEFAULT NULL,
              `status` VARCHAR(16) NOT NULL DEFAULT \'new\',
              `notified` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_sponsorship_created` (`created_at`),
              KEY `idx_sponsorship_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    } catch (Throwable $e) {
        error_log('sponsorship_enquiries: schema check failed: ' . $e->getMessage());
    }
}

/**
 * @param array<int, string> $events
 * @return int The new row id, or 0 if nothing was stored.
 */
function pmSponsorshipStore(?PDO $pdo, array $values, array $events): int
{
    if (!$pdo instanceof PDO) {
        error_log('sponsorship_enquiries: no database handle available, enquiry NOT stored');

        return 0;
    }

    try {
        ensureSponsorshipEnquirySchema($pdo);

        $clean = [];
        foreach ($events as $event) {
            $event = trim((string) $event);
            if ($event !== '') {
                $clean[] = mb_substr($event, 0, 200);
            }
        }

        $pdo->prepare(
            'INSERT INTO sponsorship_enquiries
                (first_name, last_name, organisation, email, phone, country, tier, events, message)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($values['first_name'] ?? '', 0, 120),
            mb_substr($values['last_name'] ?? '', 0, 120),
            mb_substr($values['organisation'] ?? '', 0, 200),
            mb_substr($values['email'] ?? '', 0, 190),
            ($values['phone'] ?? '') !== '' ? mb_substr($values['phone'], 0, 60) : null,
            ($values['country'] ?? '') !== '' ? mb_substr($values['country'], 0, 120) : null,
            ($values['tier'] ?? '') !== '' ? mb_substr($values['tier'], 0, 64) : null,
            $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            mb_substr($values['message'] ?? '', 0, PM_SPONSORSHIP_MESSAGE_MAX),
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('sponsorship_enquiries: could not store an enquiry: ' . $e->getMessage());

        return 0;
    }
}

function pmSponsorshipMarkNotified(?PDO $pdo, int $enquiryId): void
{
    if (!$pdo instanceof PDO || $enquiryId <= 0) {
        return;
    }

    try {
        $pdo->prepare('UPDATE sponsorship_enquiries SET notified = 1 WHERE id = ?')->execute([$enquiryId]);
    } catch (Throwable $e) {
        error_log('sponsorship_enquiries: could not flag enquiry ' . $enquiryId . ': ' . $e->getMessage());
    }
}

/** @return array<int, string> */
function pmSponsorshipEvents(?string $stored): array
{
    if ($stored === null || trim($stored) === '') {
        return [];
    }

    $decoded = json_decode($stored, true);

    return is_array($decoded) ? array_map('strval', $decoded) : [$stored];
}
