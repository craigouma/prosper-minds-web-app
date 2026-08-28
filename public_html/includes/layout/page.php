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
    function pmEventNextEarlyBird(array $event, ?string $today = null): ?array { return null; }
    function pmSoonestEarlyBird(array $events, ?string $today = null): ?array { return null; }
    function pmEventCity(array $event): string { return trim((string) strtok((string) ($event['location'] ?? ''), ',')); }
    function pmEarlyBirdBadge(array $event, string $lapsedLabel, ?string $today = null): string { return $lapsedLabel; }
    function pmEarlyBirdFill(string $template, array $earlyBird, array $event): string { return $template; }
    function pmEventsMatchingTags(array $events, array $tags): array { return $events; }
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
 * PHASE 3 NOTE — read before changing a URL here.
 * -----------------------------------------------
 * Phase 2 landed about.php, services.php and contact.php as real pages, so
 * those three are no longer homepage anchors. 'events' is still an anchor on
 * purpose: the standalone CPD calendar (events.php) and the redesigned
 * event.php are Phase 3, and until events.php exists the homepage's own events
 * grid is the only calendar there is. Pointing at a page that does not exist
 * would put a 404 in the header of every page on the site.
 *
 * When events.php lands, change the one 'href' here and both the header and
 * the footer follow. sitemap.php needs the new URL too.
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
        'events'      => ['label' => 'Events',      'href' => '/index.php#events'],
        'services'    => ['label' => 'Services',    'href' => '/services.php'],
        'about'       => ['label' => 'About',       'href' => '/about.php'],
        'sponsorship' => ['label' => 'Sponsorship', 'href' => '/sponsorship.php'],
        'contact'     => ['label' => 'Contact',     'href' => '/contact.php'],
    ];
}

/**
 * Where the header's primary green button goes.
 *
 * PHASE 4 NOTE: the redesigned multi-step registration flow is the last phase
 * of the rebuild, and there is no generic "register" URL today — the live
 * event-registration.php needs an event id. Until that flow exists, the honest
 * destination is the events list, where a delegate picks an event first.
 */
function pmRegisterHref(): string
{
    return '/index.php#events';
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
