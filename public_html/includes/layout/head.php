<?php
/** @var array<string, mixed> $pmPage */
/** @var PDO|null $pdo */

$pmTitle = trim((string) ($pmPage['title'] ?? ''));
// One suffix, applied here, so no page has to remember it. A title that already
// names the brand is left alone rather than repeating it.
if ($pmTitle === '') {
    $pmTitle = 'Prosperminds';
} elseif (stripos($pmTitle, 'prosperminds') === false) {
    $pmTitle .= ' | Prosperminds';
}

$pmDescription = (string) ($pmPage['description'] ?? '');
$pmCanonicalPath = (string) ($pmPage['canonical'] ?? '');
$pmCanonical = $pmCanonicalPath !== '' ? PM_SITE_ORIGIN . $pmCanonicalPath : '';
$pmOgImage = PM_SITE_ORIGIN . (string) ($pmPage['og_image'] ?? PM_SOCIAL_IMAGE);
$pmBodyClass = trim('pm ' . (string) ($pmPage['body_class'] ?? ''));
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo pmEsc($pmTitle); ?></title>
    <meta name="description" content="<?php echo pmEsc($pmDescription); ?>">
<?php if (!empty($pmPage['noindex'])): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($pmCanonical !== ''): ?>
    <link rel="canonical" href="<?php echo pmEsc($pmCanonical); ?>">
<?php endif; ?>

    <!-- Open Graph / Twitter. Same values as the page's own title and
         description, so the two can never drift apart. -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Prosperminds">
    <meta property="og:title" content="<?php echo pmEsc($pmTitle); ?>">
    <meta property="og:description" content="<?php echo pmEsc($pmDescription); ?>">
    <meta property="og:image" content="<?php echo pmEsc($pmOgImage); ?>">
<?php if ($pmCanonical !== ''): ?>
    <meta property="og:url" content="<?php echo pmEsc($pmCanonical); ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo pmEsc($pmTitle); ?>">
    <meta name="twitter:description" content="<?php echo pmEsc($pmDescription); ?>">
    <meta name="twitter:image" content="<?php echo pmEsc($pmOgImage); ?>">

    <meta name="theme-color" content="#000000">
    <!-- Favicons cropped from the real logo file (the shield and neural head,
         without the wordmark), not redrawn. The full lock-up is 713x183 and is
         unreadable squashed into a square, which is the only reason a separate
         asset exists at all. -->
    <link rel="icon" href="/assets/images/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/assets/images/favicon-512.png" sizes="512x512" type="image/png">
    <link rel="apple-touch-icon" href="/assets/images/favicon-180.png">

    <!-- Maharlika is self-hosted and is the only typeface. Preloaded because it
         is used for every character on the page including the first heading;
         crossorigin is required even same-origin for font fetches. -->
    <link rel="preload" href="/assets/fonts/Maharlika-Regular.ttf" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="/assets/css/pm-design-system.css">
<?php // Per-page stylesheets, after the design system so a page can override
      // and never the other way round. Paths only, escaped: see pmPageConfig().
      foreach ((array) ($pmPage['styles'] ?? []) as $pmStyle): ?>
    <link rel="stylesheet" href="<?php echo pmEsc((string) $pmStyle); ?>">
<?php endforeach; ?>

    <!-- Marks the document as script-capable before first paint, so the mobile
         menu's collapsed state is never applied to a browser that could not
         then open it. Progressive enhancement: with JS off, the nav stays in
         normal flow and every link remains reachable. -->
    <script>document.documentElement.setAttribute('data-pm-js', 'on');</script>

    <?php include __DIR__ . '/../google-tag.php'; ?>
</head>
<body class="<?php echo pmEsc($pmBodyClass); ?>">
<a class="pm-skip-link" href="#pm-main">Skip to content</a>
