<?php
/**
 * Shared page bootstrap and layout composition for the rebuilt site.
 *
 * HOW A PAGE USES THIS
 * --------------------
 * This file must be the FIRST thing a page requires, before any output at all,
 * because it starts the session (the footer's newsletter form needs a CSRF
 * token) and because includes/config.php may need to send headers.
 *
 *     <?php
 *     require_once __DIR__ . '/includes/layout/page.php';
 *
 *     $content = pmContentAll($pdo, 'about');   // one query, whole page
 *
 *     pmPageBegin([
 *         'slug'        => 'about',
 *         'nav'         => 'about',
 *         'title'       => 'About Prosperminds',
 *         'description' => 'Twenty-five years inside treasuries and audit offices.',
 *         'canonical'   => '/about.php',
 *     ]);
 *     ?>
 *     <section class="pm-section">
 *       <div class="pm-container">
 *         <span class="pm-eyebrow">Track record</span>
 *         <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'about', 'hero_title',
 *               'Twenty-five years in the room'); ?></h1>
 *       </div>
 *     </section>
 *     <?php
 *     pmPageEnd();
 *
 * Every visible string goes through pmContentSafe() (or pmContentAll() plus
 * pmEsc()) with a real inline default. The default is what the page shows if
 * the content table is missing, empty or unreachable, so it should say the same
 * thing the seeded row says — see includes/content.php, safety contract point 3.
 *
 * WHY THE DEFENSIVE LOAD BELOW
 * ----------------------------
 * includes/content.php is new, and the August 2026 outage was one new file
 * arriving incomplete: a truncated PHP file raises ParseError, an Error, which
 * catch (Exception) does not catch. includes/config.php already loads
 * includes/funnel.php this way and substitutes no-ops. The same treatment is
 * applied here to content.php and newsletter.php, so a page that composes this
 * layout renders its inline defaults rather than a white screen if either file
 * arrives broken. Call sites need no guard of their own.
 *
 * config.php itself is loaded normally, not defensively: it opens the database
 * connection the whole site depends on, so if it is broken there is no page to
 * save. That is the same judgement the existing entry points already make.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../csrf.php';

// Before any output. The footer newsletter form carries a CSRF token, and the
// footer renders after the page body, by which time it is far too late to send
// a session cookie.
formCsrfEnsureSession();

// ── Content layer, loaded defensively ───────────────────────────────────────
if (is_file(__DIR__ . '/../content.php')) {
    try {
        require_once __DIR__ . '/../content.php';
    } catch (Throwable $pmContentLoadError) {
        error_log('Page content layer unavailable: ' . $pmContentLoadError->getMessage());
    }
}

if (!function_exists('pmContent')) {
    // Stand-ins with identical signatures. Every one returns the caller's own
    // default, which is exactly what a working content layer returns when a key
    // is not stored — so a page cannot tell the difference and neither can a
    // visitor.
    function ensurePageContentSchema(PDO $pdo): void {}
    function pmContentRows(?PDO $pdo, string $pageSlug): array { return []; }
    function pmContentAll(?PDO $pdo, string $pageSlug): array { return []; }
    function pmContent(?PDO $pdo, string $pageSlug, string $sectionKey, string $default = ''): string { return $default; }
    function pmContentSafe(?PDO $pdo, string $pageSlug, string $sectionKey, string $default = '', bool $defaultIsHtml = false): string {
        return $defaultIsHtml ? $default : htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    function pmContentJson(?PDO $pdo, string $pageSlug, string $sectionKey, array $default = []): array { return $default; }
    function pmContentSet(?PDO $pdo, string $pageSlug, string $sectionKey, string $value, string $contentType = 'text', int $sortOrder = 0): bool { return false; }
}

// ── Event data, loaded defensively ──────────────────────────────────────────
// Same treatment and the same reason as the content layer. The homepage and the
// service pages read the events table through these helpers; if the file is
// missing or truncated they render their empty state, which is a page, rather
// than a fatal, which is not.
if (is_file(__DIR__ . '/../events.php')) {
    try {
        require_once __DIR__ . '/../events.php';
    } catch (Throwable $pmEventsLoadError) {
        error_log('Event helpers unavailable: ' . $pmEventsLoadError->getMessage());
    }
}

if (!function_exists('pmActiveEvents')) {
    function pmActiveEvents(?PDO $pdo): array { return []; }
    function pmAllEvents(?PDO $pdo): array { return []; }
    function pmEventById(?PDO $pdo, int $id): ?array { return null; }
    function pmEventNextEarlyBird(array $event, ?string $today = null): ?array { return null; }
    function pmSoonestEarlyBird(array $events, ?string $today = null): ?array { return null; }
    function pmEventCity(array $event): string { return trim((string) strtok((string) ($event['location'] ?? ''), ',')); }
    function pmEarlyBirdBadge(array $event, string $lapsedLabel, ?string $today = null): string { return $lapsedLabel; }
    function pmEarlyBirdFill(string $template, array $earlyBird, array $event): string { return $template; }
    function pmEventsMatchingTags(array $events, array $tags): array { return $events; }
    function pmEventIsPast(array $event, ?string $today = null): bool { return false; }
    function pmEventIsListable(array $event, ?string $today = null): bool { return false; }
    function pmPartitionEventsByDate(array $events, ?string $today = null): array { return ['upcoming' => [], 'past' => []]; }
    /** The one stand-in that must still DO its job. It is the house no-em-dash
     *  rule, and a no-op here would let the rule fail silently on exactly the
     *  page whose data layer is already broken. It needs no database. */
    function pmEventProse(?string $text): string {
        return (string) preg_replace('/\s*\x{2014}\s*/u', ', ', (string) $text);
    }
    function pmEventLines(?string $text): array { return []; }
    function pmEventAgenda(array $event): array { return []; }
    function pmEventDateBlock(array $event): array { return ['range' => '', 'stamp' => '']; }
    function pmEventDatesLong(array $event): string { return (string) ($event['date_display'] ?? ''); }
    function pmEventLengthLabel(array $event, string $singular = 'day', string $plural = 'days'): string { return ''; }
    function pmEventFocusTags(array $event): array { return []; }
}

// ── Event card markup, loaded defensively ───────────────────────────────────
// Loaded after events.php because the card calls pmEarlyBirdBadge(). Same
// treatment and the same reason as the layers above: five Phase 2 pages print
// an event grid, and a missing or truncated partial must cost those pages
// their grid, not their page.
if (is_file(__DIR__ . '/event-card.php')) {
    try {
        require_once __DIR__ . '/event-card.php';
    } catch (Throwable $pmEventCardLoadError) {
        error_log('Event card partial unavailable: ' . $pmEventCardLoadError->getMessage());
    }
}

if (!function_exists('pmRenderEventGrid')) {
    function pmEventDetailUrl(array $event): string { return '/event.php?id=' . (int) ($event['id'] ?? 0); }
    function pmEventRegisterUrl(array $event): string { return '/event-registration.php?id=' . (int) ($event['id'] ?? 0); }
    function pmEventImageUrl(array $event): string { return ''; }
    function pmEventMonthLabel(array $event): string { return ''; }
    function pmRenderEventCard(array $event, array $labels, string $variant, int $index, int $level): void {}
    /** Falls back to the page's own empty message, which is a sentence rather
     *  than a silent gap where a calendar should be. */
    function pmRenderEventGrid(array $events, array $labels, string $emptyMessage, string $variant = 'full', int $level = 3): void
    {
        echo '<p class="pm-body pm-mt-lg">' . htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') . "</p>\n";
    }
}

// ── Newsletter, loaded defensively ──────────────────────────────────────────
if (is_file(__DIR__ . '/../newsletter.php')) {
    try {
        require_once __DIR__ . '/../newsletter.php';
    } catch (Throwable $pmNewsletterLoadError) {
        error_log('Newsletter helpers unavailable: ' . $pmNewsletterLoadError->getMessage());
    }
}

if (!function_exists('pmNewsletterSubscribe')) {
    function ensureNewsletterSubscriberSchema(PDO $pdo): void {}
    function pmNewsletterNormaliseEmail(string $email): string { return ''; }
    function pmNewsletterSubscribe(?PDO $pdo, string $email, string $source = 'footer'): array {
        error_log('newsletter_subscribers: helpers unavailable, subscription dropped');

        return [
            'status'  => 'error',
            'success' => false,
            'message' => 'We could not save that just now. Please try again shortly.',
        ];
    }
}


/** Canonical origin, used for canonical links and Open Graph URLs. */
const PM_SITE_ORIGIN = 'https://prosper-minds.com';

/** Fallback social sharing image. The existing brand mark, already deployed. */
const PM_SOCIAL_IMAGE = '/assets/images/fisrt-logo.png';


/**
 * htmlspecialchars() with this project's settings, for values that did not come
 * from the content layer (query strings, computed strings, database rows from
 * other tables). Content-layer values should use pmContentSafe() instead, which
 * honours the stored content_type.
 */
function pmEsc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * The site navigation, in order, shared by the header and the footer's Site
 * column so the two can never drift apart.
 *
 * Every item is now a real page. Phase 2 landed about.php, services.php and
 * contact.php; Phase 3 landed events.php, and 'events' stopped being the
 * homepage anchor it had been since launch. Nothing in this navigation points
 * at a fragment any more, which was the whole point of the exercise: Section
 * 4.1 of the design brief names nav links that pretend to be pages as the core
 * problem with the live site.
 *
 * Changing one 'href' here updates both the header and the footer's Site
 * column. sitemap.php lists the same URLs and has to be kept in step by hand.
 *
 * The "Admin" link is deliberately absent. It was removed from the public
 * navbar in commit 66bc766 and must not be reintroduced.
 *
 * @return array<string, array{label: string, href: string}>
 */
function pmNavItems(): array
{
    return [
        'home'        => ['label' => 'Home',        'href' => '/index.php'],
        'events'      => ['label' => 'Events',      'href' => '/events.php'],
        'services'    => ['label' => 'Services',    'href' => '/services.php'],
        'about'       => ['label' => 'About',       'href' => '/about.php'],
        'sponsorship' => ['label' => 'Sponsorship', 'href' => '/sponsorship.php'],
        'contact'     => ['label' => 'Contact',     'href' => '/contact.php'],
    ];
}

/**
 * The three service pillars, as the INLINE DEFAULT for page_content
 * services.pillars.
 *
 * This is not the source of truth. The seeded json row is, and it is what a
 * visitor normally sees; Phase 5's CMS edits that row. This array exists
 * because the content layer's contract (includes/content.php, point 3) is that
 * every call site passes a real default saying the same thing the seeded row
 * says, so that a missing or unreachable page_content table produces the page
 * rather than a blank section.
 *
 * It lives here rather than being repeated because THREE pages render the
 * pillars: the homepage, the About page and the services overview. Three copies
 * of a fallback is three chances for them to disagree about what the pillars
 * are, which is the precise failure this whole content layer exists to avoid.
 *
 * Keep the `key` values in step with pmServiceHref() below.
 *
 * @return array<int, array<string, mixed>>
 */
function pmPillarsDefault(): array
{
    return [
        [
            'key'     => 'pfm',
            'num'     => '01',
            'name'    => 'PFM, IPSAS and IFRS Mastery',
            'promise' => 'Build the technical foundation your finance teams need.',
            'intro'   => 'Accrual accounting, disclosure and audit readiness for institutions that are judged on their financial statements.',
        ],
        [
            'key'     => 'data',
            'num'     => '02',
            'name'    => 'Data Analytics and AI Automation',
            'promise' => 'Transform reporting from burden to strategic advantage.',
            'intro'   => 'Practical analytics and automation for finance functions that still spend most of the month closing the books.',
        ],
        [
            'key'     => 'sustainability',
            'num'     => '03',
            'name'    => 'Sustainability Reporting',
            'promise' => 'Meet global standards while strengthening transparency.',
            'intro'   => 'Climate and sustainability disclosure for public institutions now being asked for it by lenders, auditors and citizens.',
        ],
    ];
}

/**
 * The detail page for one of the three service pillars.
 *
 * The pillar data itself is content (page_content services.pillars, a json
 * row), so the pillar list, its names and its copy are all editable. The URL
 * a pillar maps to is NOT content: it is a route, and a CMS user who could
 * retype it could point a pillar at a page that does not exist. So the mapping
 * lives here, keyed by the stable `key` field in that json row.
 *
 * An unknown key returns the services overview rather than a broken link, so
 * adding a fourth pillar in the CMS before its page exists degrades to a real
 * page instead of a 404.
 */
function pmServiceHref(string $key): string
{
    return match ($key) {
        'pfm'            => '/service-pfm.php',
        'data'           => '/service-data.php',
        'sustainability' => '/service-sustainability.php',
        default          => '/services.php',
    };
}

/**
 * Where the header's primary green button goes.
 *
 * PHASE 4 NOTE: the redesigned multi-step registration flow is the last phase
 * of the rebuild, and there is no generic "register" URL today — the live
 * event-registration.php needs an event id. Until that flow exists, the honest
 * destination is the calendar, where a delegate picks a school first. Phase 3
 * made that a real page, so this no longer has to send them to a fragment.
 */
function pmRegisterHref(): string
{
    return '/events.php';
}

/**
 * The page's configuration, filled out with defaults.
 *
 * Accepted keys:
 *   slug        string  page_content page_slug for this page. Also the default
 *                       nav key. Required in practice; defaults to 'page'.
 *   nav         string  Which pmNavItems() key is the current page.
 *   title       string  <title>, without the site-name suffix.
 *   description string  meta description and og:description.
 *   canonical   string  Root-relative path for the canonical link, e.g. '/about.php'.
 *   body_class  string  Extra classes appended to the required 'pm' class.
 *   og_image    string  Root-relative path to the social image.
 *   noindex     bool    Emit robots noindex. Used by the temporary preview page.
 *   styles      array   Extra root-relative stylesheet paths, rendered in <head>
 *                       after the design system.
 *   scripts     array   Extra root-relative script paths, rendered before
 *                       </body> with defer, in the order given.
 *
 * WHY styles AND scripts EXIST
 * ----------------------------
 * Almost every page needs exactly the design system and nothing else, and that
 * stays the default. One page so far needs more: contact.php self-hosts
 * MapLibre GL JS for the office map. The alternatives were worse. Merging a
 * third-party stylesheet into pm-design-system.css would put dozens of colours
 * outside the brand palette into the file whose whole contract is that it holds
 * none (local-dev/verify.sh asserts exactly that), and loading a 1 MB map
 * library on all nine pages to avoid a config key is not a trade worth making.
 *
 * These take PATHS, not markup, and every path is escaped, so a page cannot
 * inject arbitrary tags into the head through them.
 *
 * @param array<string, mixed> $page
 * @return array<string, mixed>
 */
function pmPageConfig(array $page = []): array
{
    $slug = (string) ($page['slug'] ?? 'page');

    return array_merge([
        'slug'        => $slug,
        'nav'         => $slug,
        'title'       => 'Prosperminds',
        'description' => 'Executive public finance, IPSAS, data analytics and sustainability reporting training for government finance leaders across Africa.',
        'canonical'   => '',
        'body_class'  => '',
        'og_image'    => PM_SOCIAL_IMAGE,
        'noindex'     => false,
        'styles'      => [],
        'scripts'     => [],
    ], $page);
}

/**
 * Open the document: <head>, <body>, the site header, and <main>.
 *
 * Must be called before any output. Everything a page echoes afterwards lands
 * inside <main id="pm-main">, which is the skip link's target.
 *
 * @param array<string, mixed> $page See pmPageConfig().
 */
function pmPageBegin(array $page = []): void
{
    $GLOBALS['pmPage'] = $pmPage = pmPageConfig($page);
    $pdo = $GLOBALS['pdo'] ?? null;

    require __DIR__ . '/head.php';
    require __DIR__ . '/header.php';

    echo '<main id="pm-main">' . "\n";
}

/**
 * Close the document: </main>, the site footer, the layout script, </body>.
 */
function pmPageEnd(): void
{
    $pmPage = $GLOBALS['pmPage'] ?? pmPageConfig();
    $pdo = $GLOBALS['pdo'] ?? null;

    echo "</main>\n";

    require __DIR__ . '/footer.php';
}
