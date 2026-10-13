<?php
/**
 * Sets a real 404 status. A PHP script defaults to 200, which would make this a
 * soft 404 that a crawler cannot tell from a real page.
 */

// First, before any require. See the header comment.
http_response_code(404);

require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/redirects.php';

// Runs only for a request that already found nothing, so a redirect row can
// never shadow a real page. Both calls swallow their own failures: a 404 page
// that fails to render because its logging failed is a worse 404.
$pmPath = pmRequestPath();
pmRedirectMaybe($pdo ?? null, $pmPath);
pmNotFoundRecord($pdo ?? null, $pmPath);

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

<section class="pm-section pm-404">
  <div class="pm-container">
    <span class="pm-caption pm-strong">404</span>
    <h1 class="pm-h1 pm-h1--404 pm-mt-sm"><?php echo pmContentSafe($pdo, 'notfound', 'v2_heading',
      'This page has moved or never existed.'); ?></h1>
    <a class="pm-btn" href="/#schools"><?php echo pmContentSafe($pdo, 'notfound', 'v2_cta', 'See the schools'); ?></a>
  </div>
</section>

<?php pmPageEnd(); ?>
