<?php
/**
 * Address, image and card helpers for the school pages.
 *
 * A school is drawn in exactly one shape, the card below, on the home page.
 * The poster is shown WHOLE in a fixed 4:5 box with letterboxing, so square
 * and portrait posters both appear uncropped.
 */

/**
 * The public detail page for one event.
 *
 * An event with a slug gets the clean /school/{slug} address; one without
 * (an older row from before the slug column existed) keeps working on
 * /event.php?id=N. Both render the same page; the slug one is canonical.
 */
function pmEventDetailUrl(array $event): string
{
    $slug = trim((string) ($event['slug'] ?? ''));

    return $slug !== '' ? '/school/' . rawurlencode($slug) : '/event.php?id=' . (int) ($event['id'] ?? 0);
}

/** The registration entry point for one event. Same slug-or-id rule as above. */
function pmEventRegisterUrl(array $event): string
{
    $slug = trim((string) ($event['slug'] ?? ''));

    return $slug !== ''
        ? '/school/' . rawurlencode($slug) . '/register'
        : '/event-registration.php?id=' . (int) ($event['id'] ?? 0);
}

/**
 * Root-relative URL for an event's designed banner, or '' when there is none.
 *
 * events.image_path is stored without a leading slash ("assets/images/x.jpg")
 * and several of the real filenames contain spaces, so each path segment is
 * encoded. A row pointing at nothing returns '' and the caller draws a
 * monogram in the same slot rather than a broken image icon.
 */
function pmEventImageUrl(array $event): string
{
    $path = trim((string) ($event['image_path'] ?? ''));

    if ($path === '') {
        return '';
    }

    $segments = array_map('rawurlencode', explode('/', ltrim($path, '/')));

    return '/' . implode('/', $segments);
}

/**
 * Two initials standing in for a missing banner, for example "FF" from
 * "Foundations of Foresight".
 *
 * The seven 2026 CPD cohorts were listed as text and never had a designed
 * banner made for them. Omitting the tile entirely left those cards visibly
 * shorter than the ones beside them, which read as a fault rather than as an
 * archive, so the slot is filled typographically instead.
 *
 * Joining words are skipped so the initials come from the words that carry the
 * name. A single-word title uses its own first two letters, and a title with
 * nothing alphabetic in it returns '' so the caller can fall back again.
 */
function pmEventMonogram(array $event): string
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', (string) ($event['title'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $words = array_values(array_filter($words, static function (string $word): bool {
        return !in_array(mb_strtolower($word), ['of', 'the', 'and', 'for', 'in', 'a', 'an', 'to', 'on', 'with'], true);
    }));

    if ($words === []) {
        return '';
    }

    $initials = count($words) === 1
        ? mb_substr($words[0], 0, 2)
        : mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);

    return mb_strtoupper($initials);
}

/**
 * The real pixel size of a poster, or null.
 *
 * Without width and height on the image the page reflows as each poster
 * arrives. Read once per file per request, and a file that cannot be measured
 * simply goes without.
 *
 * @return array{0: int, 1: int}|null
 */
function pmEventImageSize(string $rootRelative): ?array
{
    static $cache = [];

    if (array_key_exists($rootRelative, $cache)) {
        return $cache[$rootRelative];
    }

    $cache[$rootRelative] = null;
    $path = __DIR__ . '/../../' . ltrim($rootRelative, '/');

    if (!is_file($path)) {
        return null;
    }

    $size = @getimagesize($path);

    if (!is_array($size) || (int) ($size[0] ?? 0) <= 0 || (int) ($size[1] ?? 0) <= 0) {
        return null;
    }

    return $cache[$rootRelative] = [(int) $size[0], (int) $size[1]];
}

/**
 * One school card for the home page: poster, when and where, title, price,
 * the live early-bird line while it is open, and the two actions.
 *
 * @param array<string, mixed> $event A row from pmActiveEvents().
 */
function pmRenderSchoolCard(array $event, int $index = 0): void
{
    $title   = pmEventProse((string) ($event['title'] ?? ''));
    $image   = pmEventImageUrl($event);
    $size    = $image !== '' ? pmEventImageSize((string) ($event['image_path'] ?? '')) : null;
    $when    = trim(pmEventDatesLong($event) . ', ' . pmEventPlace($event), ', ');
    $from    = pmEventFromPrice($event);
    $early   = pmEventNextEarlyBird($event);
    $monogram = pmEventMonogram($event);
    ?>
        <article class="pm-school">
          <div class="pm-poster pm-poster--card<?php echo $image === '' ? ' pm-poster--empty' : ''; ?>">
<?php if ($image !== ''): ?>
            <img src="<?php echo pmEsc($image); ?>"
                 alt="Poster for <?php echo pmEsc($title); ?>"
<?php if ($size !== null): ?>
                 width="<?php echo $size[0]; ?>" height="<?php echo $size[1]; ?>"
<?php endif; ?>
                 loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>" decoding="async">
<?php else: ?>
            <span aria-hidden="true"><?php echo pmEsc($monogram); ?></span>
<?php endif; ?>
          </div>

          <div class="pm-school__meta">
            <span class="pm-school__when"><?php echo pmEsc($when); ?></span>
            <h3 class="pm-h3"><?php echo pmEsc($title); ?></h3>
<?php if ($from !== ''): ?>
            <span class="pm-school__from">From <strong><?php echo pmEsc($from); ?></strong> per delegate</span>
<?php endif; ?>
<?php if ($early !== null): ?>
            <span class="pm-live"><?php echo pmEsc($early['pct']); ?>% early-bird discount until <?php
              echo pmEsc($early['date_display']); ?></span>
<?php endif; ?>
          </div>

          <div class="pm-btn-row">
            <a class="pm-btn" href="<?php echo pmEsc(pmEventRegisterUrl($event)); ?>">Register<span class="pm-sr-only"> for <?php
              echo pmEsc($title); ?></span></a>
            <a class="pm-btn pm-btn--secondary" href="<?php echo pmEsc(pmEventDetailUrl($event)); ?>">Details<span class="pm-sr-only"> of <?php
              echo pmEsc($title); ?></span></a>
          </div>
        </article>
<?php
}
