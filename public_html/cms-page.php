<?php
require_once __DIR__ . '/includes/layout/page.php';
require_once __DIR__ . '/includes/pages.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/events.php';

$slug    = pmPageSlugify((string) ($_GET['slug'] ?? ''));
$preview = (string) ($_GET['preview'] ?? '');
$page    = null;

if ($preview !== '') {
    $previewId = pmPreviewTokenPageId($pdo, $preview);
    if ($previewId !== null) {
        $page = pmPageFind($pdo, $previewId);
    }
}

if ($page === null) {
    $candidate = pmPageFindBySlug($pdo, $slug);
    // A draft or scheduled page is a 404 to the public. Only a valid preview
    // token above reaches one, and that link expires.
    if ($candidate !== null && pmPageIsLive($candidate)) {
        $page = $candidate;
    }
}

if ($page === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$isPreview = $preview !== '' && !pmPageIsLive($page);
$blocks    = pmPageBlocks($pdo, (int) $page['id']);

pmPageBegin([
    'slug'        => 'cms-' . $page['slug'],
    'nav'         => '',
    'title'       => (string) ($page['seo_title'] ?: $page['title']),
    'description' => (string) $page['seo_description'],
    'canonical'   => '/' . $page['slug'],
    'noindex'     => (bool) $page['noindex'] || $isPreview,
]);
?>

<?php if ($isPreview): ?>
<div style="background:#0d0d0d;color:#fff;padding:10px 20px;font-size:13px;text-align:center">
  You are previewing a page that is not published. This link expires.
</div>
<?php endif; ?>

<?php
if (!$blocks) {
    ?>
<section class="pm-section">
  <div class="pm-container">
    <h1 class="pm-h1"><?php echo pmEsc((string) $page['title']); ?></h1>
    <p class="pm-lede pm-mt-lg">This page has no content yet.</p>
  </div>
</section>
    <?php
} else {
    echo pmRenderBlocks($blocks, $pdo);
}
?>

<?php pmPageEnd(); ?>
