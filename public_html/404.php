<?php
/**
 * Not found.
 *
 * Rebuilt on the design system. The page it replaces was standalone: it fetched
 * Inter from Google Fonts and Font Awesome from a CDN, used a purple-to-green
 * gradient header, 12px rounded corners and a drop shadow, and pulled its
 * assets with RELATIVE paths. That last one is the functional bug: Apache
 * serves this file through ErrorDocument for a request to any path, so a 404 at
 * /some/deep/path asked for /some/deep/assets/css/style.css and got nothing.
 * Every asset here is root-relative.
 *
 * WHAT THIS PAGE GAVE UP, AND WHY THAT IS ACCEPTABLE
 * --------------------------------------------------
 * The old file's header comment said "a 404 page must never fail itself, so
 * this does not require config.php or touch the database". That was a real
 * property and it is now gone: this page composes the shared layout, which
 * opens a database connection, and includes/config.php die()s if that fails.
 *
 * The trade is worth making and is mitigated:
 *
 *   * http_response_code(404) is set on the FIRST line, before anything is
 *     required. If config.php does die, the visitor still gets a 404 with a
 *     plain sentence rather than a 200 with the wrong content, which is the
 *     failure that actually matters to a crawler.
 *   * The content layer is already proof against its own failure: a missing,
 *     broken or unreachable page_content table renders the inline defaults
 *     below, and local-dev/verify.sh proves it against this page.
 *   * If the database is down, every other page on the site is down too. A
 *     branded 404 in that state is not the problem worth solving.
 *
 * The alternative, a second hand-written copy of the header and footer that
 * needs no database, is a copy that silently drifts from the real one. That
 * is precisely the duplication this rebuild exists to remove.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

// First, before any require. See the header comment.
http_response_code(404);

require_once __DIR__ . '/includes/layout/page.php';

pmPageBegin([
    'slug'        => 'notfound',
    'nav'         => '',
    'title'       => pmContent($pdo, 'notfound', 'meta_title', 'Page not found'),
    'description' => pmContent($pdo, 'notfound', 'meta_description', 'The page you asked for is not on this site. These are the routes most visitors are looking for.'),
    // No canonical: this page has no single URL, because it answers for every
    // URL that does not exist. Pointing one at itself would invite indexing.
    'noindex'     => true,
]);
?>

<section class="pm-section pm-relative pm-clip">
  <?php include __DIR__ . '/includes/layout/motif.php'; ?>
  <div class="pm-container pm-relative">

    <span class="pm-eyebrow"><?php echo pmContentSafe($pdo, 'notfound', 'eyebrow', 'Not found'); ?></span>

    <?php // The status code, set at display size. A <p> rather than a heading:
          // it is a label for the page's state, and the sentence below it is
          // the actual heading. ?>
    <p class="pm-display"><?php echo pmContentSafe($pdo, 'notfound', 'code', '404'); ?></p>

    <h1 class="pm-h1 pm-mt-md"><?php echo pmContentSafe($pdo, 'notfound', 'title',
      'This page is not on the site'); ?></h1>

    <p class="pm-lede pm-mt-lg"><?php echo pmContentSafe($pdo, 'notfound', 'body',
      'The address may have changed, or the course it referred to has already run. These four routes cover most of what people arrive looking for.'); ?></p>

    <?php // A nav rather than a headed section: four links are a navigation
          // aid, and labelling the landmark says so without inventing a
          // heading nobody needs to read. ?>
    <nav class="pm-grid pm-grid--ruled pm-grid--4 pm-mt-lg" aria-label="Suggested pages">
<?php foreach (pmContentJson($pdo, 'notfound', 'routes', [
        ['eyebrow' => 'Calendar',    'label' => 'The 2026 schools',     'href' => '/index.php#events'],
        ['eyebrow' => 'Services',    'label' => 'The three pillars',    'href' => '/services.php'],
        ['eyebrow' => 'Sponsorship', 'label' => 'Partner with a school', 'href' => '/sponsorship.php'],
        ['eyebrow' => 'Contact',     'label' => 'Programme office',     'href' => '/contact.php'],
      ]) as $route): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmEsc((string) ($route['eyebrow'] ?? '')); ?></span>
        <p class="pm-body">
          <a href="<?php echo pmEsc((string) ($route['href'] ?? '/')); ?>"><?php
            echo pmEsc((string) ($route['label'] ?? '')); ?></a>
        </p>
      </div>
<?php endforeach; ?>
    </nav>

  </div>
</section>

<?php pmPageEnd(); ?>
