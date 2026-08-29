<?php
/**
 * Sets a real 404 status. A PHP script defaults to 200, which would make this a
 * soft 404 that a crawler cannot tell from a real page.
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
        ['eyebrow' => 'Calendar',    'label' => 'The 2026 schools',     'href' => '/events.php'],
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
