<?php
/**
 * Database-driven page content for the rebuilt site.
 *
 * WHY THIS EXISTS
 * ---------------
 * REBUILD-PLAN.md section 1 makes the case: if the new pages ship with their
 * copy hardcoded in PHP, then adding the planned CMS (Phase 5) means going back
 * and refactoring every page to read from the database instead. That rework is
 * avoidable by reading content from a table from the very first page, even
 * while there is no admin UI to edit it. Content is seeded by migration today;
 * the CMS later becomes an admin screen over a data layer that already exists
 * and has already been proven in production.
 *
 * THE MODEL
 * ---------
 * One table, page_content, addressed by (page_slug, section_key):
 *
 *     ('home', 'hero_title')     -> "Strong systems start with strong people"
 *     ('home', 'hero_body')      -> "Prosperminds trains senior government..."
 *     ('home', 'stats')          -> '[{"value":"25","label":"Years"}, ...]'
 *
 * content_type is one of text | html | image | json and decides how a value is
 * rendered, not how it is stored. Every value is a string in LONGTEXT; the
 * type is the contract between whoever seeds a row and whoever prints it.
 *
 * SAFETY CONTRACT — read before editing anything below.
 * -----------------------------------------------------
 * Page content is a SECONDARY concern. The primary outcome is that a page
 * renders. In August 2026 this site told 36 delegates their registration had
 * failed because a secondary concern (sending email) was allowed to decide the
 * primary answer; see the Phase 2 comment in process-registration.php and
 * commit 2d05cc1. includes/funnel.php restates the same discipline for
 * analytics. This file is the third instance of it, so:
 *
 *   1. Every public function catches Throwable — not Exception. A truncated
 *      include raises ParseError, which extends Error, which catch (Exception)
 *      does not catch. That is precisely how the August failure escaped.
 *   2. Nothing here ever throws, echoes, or sets an HTTP status. A missing
 *      table, an unreachable database, a malformed JSON value: every one of
 *      them returns the caller's own $default, so the page still renders with
 *      the copy the developer wrote inline as the fallback.
 *   3. Callers are expected to pass a real default for every key. A page whose
 *      fallback is an empty string will render blank if the table disappears,
 *      and that is the caller's bug, not this file's. Seeded values and inline
 *      defaults should say the same thing.
 *   4. The on-demand CREATE TABLE refuses to run inside an open transaction.
 *      CREATE TABLE causes an implicit COMMIT in MySQL, so a schema check in
 *      the middle of a registration could commit a half-written row. Content
 *      is never worth that.
 *   5. One query per page, not per key. pmContentAll() fetches the whole page
 *      and caches it in a static array for the rest of the request, including
 *      caching the failure, so a broken database costs one failed query rather
 *      than one per lookup.
 *
 * This file is loaded defensively by includes/layout/page.php, which substitutes
 * no-op stand-ins with the same signatures if the file is missing or fails to
 * parse. Call sites therefore need no guard of their own.
 */

/** The four accepted content types. Enforced here rather than by a database
 *  ENUM so a fifth can be added without an ALTER TABLE, matching how
 *  FUNNEL_EVENT_TYPES and event_registrations.payment_status are handled. */
const PM_CONTENT_TYPES = ['text', 'html', 'image', 'json'];

/**
 * Create the page_content table if it is missing.
 *
 * Same "ensure schema on demand" convention as ensureFailedNotificationSchema()
 * in includes/config.php and ensureFunnelEventsSchema() in includes/funnel.php,
 * so a deploy does not have to be coordinated with a manual phpMyAdmin step.
 * The equivalent up/down pair is also scripted in
 * database/migrations/2026-08-28-01-create-page-content.*.sql for anyone who
 * would rather apply it explicitly.
 *
 * Note this creates the table EMPTY. An empty table and a missing table behave
 * identically from a caller's point of view: every lookup returns its default.
 * The copy itself arrives via the seed migration
 * (2026-08-28-03-seed-page-content.up.sql), which is deliberately not run from
 * PHP — seeding is content, and content changes should be reviewable.
 */
function ensurePageContentSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    try {
        // Point 4 of the safety contract.
        if ($pdo->inTransaction()) {
            error_log('page_content: skipped schema check, called inside an open transaction');

            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS page_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_slug VARCHAR(64) NOT NULL,
                section_key VARCHAR(96) NOT NULL,
                content_type VARCHAR(16) NOT NULL DEFAULT 'text',
                content_value LONGTEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_page_content_slug_key (page_slug, section_key),
                KEY idx_page_content_slug (page_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    } catch (Throwable $e) {
        error_log('Could not create page_content table: ' . $e->getMessage());
    }
}

/**
 * Every stored row for one page, keyed by section_key, in sort_order.
 *
 * ONE query per page per request. The result — success or failure — is cached
 * in a static array, so a page asking for forty keys issues one query, and a
 * page asking for forty keys against a broken database issues one failed query
 * rather than forty.
 *
 * Each entry is the full row: ['content_type' => ..., 'content_value' => ...,
 * 'sort_order' => ...]. Most callers want pmContentAll() or pmContent()
 * instead; this is the shape the render helpers need in order to honour
 * content_type.
 *
 * @return array<string, array{content_type: string, content_value: string, sort_order: int}>
 */
function pmContentRows(?PDO $pdo, string $pageSlug): array
{
    static $cache = [];

    if (array_key_exists($pageSlug, $cache)) {
        return $cache[$pageSlug];
    }

    // Cache the empty result FIRST. If anything below throws in a way this
    // function somehow fails to catch, a second call still cannot re-run it.
    $cache[$pageSlug] = [];

    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        ensurePageContentSchema($pdo);

        $stmt = $pdo->prepare(
            'SELECT section_key, content_type, content_value, sort_order
               FROM page_content
              WHERE page_slug = ?
              ORDER BY sort_order, section_key'
        );
        $stmt->execute([$pageSlug]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['section_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rows[$key] = [
                'content_type'  => (string) ($row['content_type'] ?? 'text'),
                'content_value' => (string) ($row['content_value'] ?? ''),
                'sort_order'    => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $cache[$pageSlug] = $rows;
    } catch (Throwable $e) {
        // Quiet on purpose. A visitor must never learn that a content layer
        // exists, let alone that it is broken. The page renders from defaults.
        error_log('page_content: could not load page "' . $pageSlug . '" — ' . $e->getMessage());

        return [];
    }
}

/**
 * Every stored value for one page as a plain section_key => value map.
 *
 * Convenience over pmContentRows() for the common case where the caller knows
 * the types it is dealing with. Returns [] when nothing is stored, the table is
 * missing, or the database is unreachable — never null, never an exception.
 *
 * @return array<string, string>
 */
function pmContentAll(?PDO $pdo, string $pageSlug): array
{
    $values = [];

    foreach (pmContentRows($pdo, $pageSlug) as $key => $row) {
        $values[$key] = $row['content_value'];
    }

    return $values;
}

/**
 * One stored value, RAW, or the caller's default.
 *
 * "Raw" means exactly what is in the column. Anything printed into HTML from
 * this must be escaped by the caller, or fetched through pmContentSafe()
 * instead, which does it according to the row's content_type.
 *
 * A row that exists but holds an empty string counts as absent and yields the
 * default: an accidentally-blanked row should show the developer's fallback
 * copy rather than a hole in the page.
 */
function pmContent(?PDO $pdo, string $pageSlug, string $sectionKey, string $default = ''): string
{
    $rows = pmContentRows($pdo, $pageSlug);

    if (!isset($rows[$sectionKey])) {
        return $default;
    }

    $value = $rows[$sectionKey]['content_value'];

    return trim($value) === '' ? $default : $value;
}

/**
 * One stored value, ready to place directly into HTML.
 *
 * The escaping decision comes from the row's own content_type, which is the
 * point of storing it:
 *
 *   text | image  -> htmlspecialchars(), so stored copy can never inject markup
 *   html          -> passed through, because the row is markup by declaration
 *   json          -> escaped, because a JSON blob printed as text is a mistake
 *                    the page should show rather than silently execute
 *
 * The DEFAULT is always escaped as text unless $defaultIsHtml is set, so a
 * fallback string a developer wrote inline is treated the same way as a
 * 'text' row. When the fallback is deliberately markup, pass true.
 */
function pmContentSafe(
    ?PDO $pdo,
    string $pageSlug,
    string $sectionKey,
    string $default = '',
    bool $defaultIsHtml = false
): string {
    $rows = pmContentRows($pdo, $pageSlug);
    $stored = $rows[$sectionKey]['content_value'] ?? '';

    if (trim($stored) === '') {
        return $defaultIsHtml ? $default : htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }

    $type = $rows[$sectionKey]['content_type'] ?? 'text';

    if ($type === 'html') {
        return $stored;
    }

    return htmlspecialchars($stored, ENT_QUOTES, 'UTF-8');
}

/**
 * One stored value decoded from JSON, or the caller's default array.
 *
 * Used for repeated blocks — a list of statistics, a set of testimonials, the
 * bullets under an agenda day — where one row is more honest than fifteen
 * numbered keys. Any decode failure returns the default and logs; malformed
 * JSON in the table must not be able to blank a section.
 */
function pmContentJson(?PDO $pdo, string $pageSlug, string $sectionKey, array $default = []): array
{
    $raw = pmContent($pdo, $pageSlug, $sectionKey, '');

    if (trim($raw) === '') {
        return $default;
    }

    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log(
            'page_content: "' . $pageSlug . '.' . $sectionKey . '" is not valid JSON, using default — '
            . $e->getMessage()
        );

        return $default;
    }

    return is_array($decoded) ? $decoded : $default;
}

/**
 * Insert or update one row. Not used by any public page — it exists so the
 * Phase 5 CMS, and the local test suite, have a single supported write path
 * with the upsert semantics the unique index was designed for.
 *
 * Returns true only on a confirmed write. Unlike the read helpers this one
 * reports its outcome, because a caller saving an edit genuinely needs to know
 * whether the edit was saved — the same distinction process-registration.php
 * draws between the registration itself and the emails about it.
 */
function pmContentSet(
    ?PDO $pdo,
    string $pageSlug,
    string $sectionKey,
    string $value,
    string $contentType = 'text',
    int $sortOrder = 0
): bool {
    if (!$pdo instanceof PDO) {
        return false;
    }

    if (!in_array($contentType, PM_CONTENT_TYPES, true)) {
        error_log('page_content: refused unknown content_type ' . $contentType);

        return false;
    }

    try {
        ensurePageContentSchema($pdo);

        $pdo->prepare(
            'INSERT INTO page_content (page_slug, section_key, content_type, content_value, sort_order)
                  VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                  content_type = VALUES(content_type),
                  content_value = VALUES(content_value),
                  sort_order = VALUES(sort_order)'
        )->execute([$pageSlug, $sectionKey, $contentType, $value, $sortOrder]);

        return true;
    } catch (Throwable $e) {
        error_log('page_content: could not save "' . $pageSlug . '.' . $sectionKey . '" — ' . $e->getMessage());

        return false;
    }
}
