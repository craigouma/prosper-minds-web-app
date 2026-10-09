<?php
/**
 * Page bootstrap: pmPageBegin() opens the document and chrome, pmPageEnd()
 * closes it. Pages should not include head.php or footer.php directly.
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

if (is_file(__DIR__ . '/../menus.php')) {
    try {
        require_once __DIR__ . '/../menus.php';
    } catch (Throwable $pmMenuLoadError) {
        error_log('Menu layer unavailable: ' . $pmMenuLoadError->getMessage());
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
    function pmEventPlace(array $event): string { return pmEventCity($event); }
    function pmEventParsePrice(string $priceText): array { return ['USD', 0.0]; }
    function pmEventMoney(string $currency, float $amount): string { return $currency . ' ' . $amount; }
    function pmEventFromPrice(array $event): string { return ''; }
    function pmNumberWord(int $n): string { return (string) $n; }
    function pmEarlyBirdFill(string $template, array $earlyBird, array $event): string { return $template; }
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
// Loaded after events.php because the card calls its price and early-bird
// helpers. A missing or truncated partial must cost the page its cards, not
// the page.
if (is_file(__DIR__ . '/event-card.php')) {
    try {
        require_once __DIR__ . '/event-card.php';
    } catch (Throwable $pmEventCardLoadError) {
        error_log('Event card partial unavailable: ' . $pmEventCardLoadError->getMessage());
    }
}

if (!function_exists('pmRenderSchoolCard')) {
    function pmEventDetailUrl(array $event): string { return '/event.php?id=' . (int) ($event['id'] ?? 0); }
    function pmEventRegisterUrl(array $event): string { return '/event-registration.php?id=' . (int) ($event['id'] ?? 0); }
    function pmEventImageUrl(array $event): string { return ''; }
    function pmEventImageSize(string $rootRelative): ?array { return null; }
    function pmRenderSchoolCard(array $event, int $index = 0): void {}
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
 * The header navigation, shared by the header and the mobile panel.
 *
 * A stored header menu (cms_menu_items) replaces this list outright, so the
 * keys are re-derived from each href: pages mark their own tab through
 * pmPageBegin's 'nav' option and a row id would never match it.
 *
 * @return array<string, array{label: string, href: string}>
 */
function pmNavItems(): array
{
    $default = [
        'schools'     => ['label' => 'Schools',         'href' => '/#schools'],
        'webinars'    => ['label' => 'Webinars',        'href' => '/webinars.php'],
        'sponsorship' => ['label' => 'Partner with us', 'href' => '/sponsorship.php'],
        'about'       => ['label' => 'About',           'href' => '/about.php'],
    ];

    if (!function_exists('pmMenu')) {
        return $default;
    }

    global $pdo;
    $items = pmMenu($pdo ?? null, 'header', $default);

    $keyed = [];
    foreach ($items as $key => $item) {
        $keyed[pmNavKeyFor($key, $item['href'])] = $item;
    }

    return $keyed ?: $default;
}

function pmNavKeyFor(string $fallback, string $href): string
{
    if (parse_url($href, PHP_URL_FRAGMENT) === 'schools') {
        return 'schools';
    }

    $path = parse_url($href, PHP_URL_PATH) ?? '';
    $base = strtolower(pathinfo($path, PATHINFO_FILENAME));

    if ($base === '' || $base === 'index') {
        return $base === 'index' ? 'home' : $fallback;
    }

    return $base;
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
 * Where a "Register" button with no school behind it goes. The page itself
 * offers the choice of school, or skips it when only one is open.
 */
function pmRegisterHref(): string
{
    return '/register';
}

/** The typefaces the site can be set in, each with the stack it resolves to. */
const PM_TYPEFACES = [
    'Manrope' => "'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif",
    'Inter'   => "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif",
    'Roboto'  => "'Roboto', system-ui, -apple-system, 'Segoe UI', sans-serif",
    'Calibri' => "Calibri, 'Carlito', 'Segoe UI', system-ui, sans-serif",
];

/** The font file worth preloading for each typeface. Calibri has none to preload. */
const PM_TYPEFACE_FILES = [
    'Manrope' => '/assets/fonts/Manrope-Variable.ttf',
    'Inter'   => '/assets/fonts/Inter-Variable.ttf',
    'Roboto'  => '/assets/fonts/Roboto-Variable.ttf',
    'Calibri' => '/assets/fonts/Carlito-Regular.ttf',
];

function pmTypeface(): string
{
    $name = trim(getSetting('site_typeface', 'Manrope'));

    return isset(PM_TYPEFACES[$name]) ? $name : 'Manrope';
}

/** 'white' or 'dark'. Only the home page reads it. */
function pmHomeTreatment(): string
{
    return strtolower(trim(getSetting('home_treatment', 'white'))) === 'dark' ? 'dark' : 'white';
}

/**
 * The WhatsApp chat link, or '' while no number is set. Every WhatsApp button
 * is drawn only when this is non-empty, so none can point at nothing.
 */
function pmWhatsAppUrl(string $prefill = ''): string
{
    $digits = preg_replace('/\D+/', '', getSetting('whatsapp_number', '')) ?? '';

    if (strlen($digits) < 8) {
        return '';
    }

    return 'https://wa.me/' . $digits . ($prefill !== '' ? '?text=' . rawurlencode($prefill) : '');
}

/**
 * Phones, email, hours and address as one structure, so the header panel, the
 * footer, the About page and the confirmation page never disagree.
 *
 * @return array{email: string, phones: array<int, array{label: string, tel: string}>, hours: string, address_html: string}
 */
function pmContact(?PDO $pdo): array
{
    $phones = [];
    foreach ([
        pmContent($pdo, 'global', 'phone_primary', '+254 740 582302'),
        pmContent($pdo, 'global', 'phone_secondary', '+254 722 998105'),
    ] as $label) {
        $label = trim($label);
        $digits = preg_replace('/\D+/', '', $label) ?? '';

        if ($digits !== '') {
            $phones[] = ['label' => $label, 'tel' => '+' . $digits];
        }
    }

    return [
        'email'        => trim(pmContent($pdo, 'global', 'email', 'info@prosper-minds.com')),
        'phones'       => $phones,
        'hours'        => trim(pmContent($pdo, 'global', 'office_hours', 'Monday to Friday, 8am to 5pm EAT')),
        'address_html' => pmContent($pdo, 'global', 'v2_address_html', 'Twiga Towers, Moi Avenue<br>Nairobi, Kenya'),
    ];
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
 *   hide_cta    bool    Leave the header's Register button out, on pages where
 *                       the whole page is already a registration.
 *   treatment   string  'dark' sets the dark colour tokens on <body>.
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
        'hide_cta'    => false,
        'treatment'   => '',
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
