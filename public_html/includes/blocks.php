<?php

/**
 * A constrained subset. The whole value of the design system is that a page
 * cannot be made ugly by accident, and an editor that accepts arbitrary markup
 * gives that away on the first paste from Word.
 */
function pmBlockSafeHtml(?string $html): string
{
    return strip_tags((string) $html, '<p><br><strong><em><b><i><a><ul><ol><li><h2><h3><blockquote>');
}

function pmBlockLines(?string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text) ?: [];

    return array_values(array_filter(array_map('trim', $lines), static fn ($l) => $l !== ''));
}

function pmBlockField(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

/**
 * Render one block. Unknown types render nothing rather than throwing: a block
 * type removed in a later version must not take a published page down with it.
 */
function pmRenderBlock(array $block, ?PDO $pdo = null): string
{
    $type = (string) $block['block_type'];
    $dark = ($block['appearance'] ?? 'light') === 'dark';
    $data = $block['data'] ?? [];
    $mod  = $dark ? ' pm-section--dark' : '';

    ob_start();

    switch ($type) {
        case 'hero':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'eyebrow') !== ''): ?>
    <span class="pm-eyebrow"><?php echo pmEsc(pmBlockField($data, 'eyebrow')); ?></span>
<?php endif; ?>
    <h1 class="pm-h1"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h1>
<?php if (pmBlockField($data, 'body') !== ''): ?>
    <p class="pm-lede pm-mt-lg"><?php echo pmEsc(pmBlockField($data, 'body')); ?></p>
<?php endif; ?>
<?php if (pmBlockField($data, 'cta_label') !== '' && pmBlockField($data, 'cta_href') !== ''): ?>
    <p class="pm-mt-lg"><a class="pm-btn" href="<?php echo pmEsc(pmBlockField($data, 'cta_href')); ?>"><?php
      echo pmEsc(pmBlockField($data, 'cta_label')); ?></a></p>
<?php endif; ?>
  </div>
</section>
            <?php
            break;

        case 'richtext':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <div class="pm-prose pm-mt-lg"><?php echo pmBlockSafeHtml(pmBlockField($data, 'body')); ?></div>
  </div>
</section>
            <?php
            break;

        case 'image':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'src') !== ''): ?>
    <figure class="pm-figure">
      <img src="<?php echo pmEsc(pmBlockField($data, 'src')); ?>"
           alt="<?php echo pmEsc(pmBlockField($data, 'alt')); ?>" loading="lazy">
<?php if (pmBlockField($data, 'caption') !== ''): ?>
      <figcaption class="pm-label pm-mt-md"><?php echo pmEsc(pmBlockField($data, 'caption')); ?></figcaption>
<?php endif; ?>
    </figure>
<?php endif; ?>
  </div>
</section>
            <?php
            break;

        case 'imagetext':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container pm-grid pm-grid--2 pm-grid--ruled">
    <div class="pm-cell">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
      <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
      <div class="pm-prose pm-mt-md"><?php echo pmBlockSafeHtml(pmBlockField($data, 'body')); ?></div>
    </div>
    <div class="pm-cell">
<?php if (pmBlockField($data, 'src') !== ''): ?>
      <img src="<?php echo pmEsc(pmBlockField($data, 'src')); ?>"
           alt="<?php echo pmEsc(pmBlockField($data, 'alt')); ?>" loading="lazy" style="width:100%;display:block">
<?php endif; ?>
    </div>
  </div>
</section>
            <?php
            break;

        case 'stats':
            $stats = pmBlockLines(pmBlockField($data, 'items'));
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <div class="pm-grid pm-grid--ruled pm-grid--<?php echo max(2, min(4, count($stats))); ?> pm-mt-lg">
<?php foreach ($stats as $stat):
        [$figure, $label] = array_pad(explode('|', $stat, 2), 2, '');
?>
      <div class="pm-cell">
        <p class="pm-display" data-count-to="<?php echo pmEsc(trim($figure)); ?>"><?php echo pmEsc(trim($figure)); ?></p>
        <span class="pm-label"><?php echo pmEsc(trim($label)); ?></span>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>
            <?php
            break;

        case 'cards':
            $cards = pmBlockLines(pmBlockField($data, 'items'));
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <div class="pm-grid pm-grid--ruled pm-grid--<?php echo max(2, min(4, count($cards))); ?> pm-mt-lg">
<?php foreach ($cards as $card):
        [$title, $body] = array_pad(explode('|', $card, 2), 2, '');
?>
      <div class="pm-cell">
        <h3 class="pm-h3"><?php echo pmEsc(trim($title)); ?></h3>
        <p class="pm-body pm-mt-md"><?php echo pmEsc(trim($body)); ?></p>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>
            <?php
            break;

        case 'testimonials':
            $quotes = pmBlockLines(pmBlockField($data, 'items'));
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <div class="pm-grid pm-grid--ruled pm-grid--<?php echo max(2, min(3, count($quotes))); ?> pm-mt-lg">
<?php foreach ($quotes as $quote):
        [$text, $who] = array_pad(explode('|', $quote, 2), 2, '');
?>
      <figure class="pm-cell" style="margin:0">
        <blockquote class="pm-body"><?php echo pmEsc(trim($text)); ?></blockquote>
        <figcaption class="pm-label pm-mt-md"><?php echo pmEsc(trim($who)); ?></figcaption>
      </figure>
<?php endforeach; ?>
    </div>
  </div>
</section>
            <?php
            break;

        case 'agenda':
            $rows = pmBlockLines(pmBlockField($data, 'items'));
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <div class="pm-mt-lg">
<?php foreach ($rows as $i => $row):
        [$title, $body] = array_pad(explode('|', $row, 2), 2, '');
?>
      <details class="pm-accordion"<?php echo $i === 0 ? ' open' : ''; ?>>
        <summary class="pm-accordion__summary"><?php echo pmEsc(trim($title)); ?></summary>
        <p class="pm-body pm-mt-md"><?php echo pmEsc(trim($body)); ?></p>
      </details>
<?php endforeach; ?>
    </div>
  </div>
</section>
            <?php
            break;

        case 'cta':
            ?>
<section class="pm-section pm-section--accent">
  <div class="pm-container">
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php if (pmBlockField($data, 'body') !== ''): ?>
    <p class="pm-lede pm-mt-md"><?php echo pmEsc(pmBlockField($data, 'body')); ?></p>
<?php endif; ?>
<?php if (pmBlockField($data, 'cta_label') !== '' && pmBlockField($data, 'cta_href') !== ''): ?>
    <p class="pm-mt-lg"><a class="pm-btn pm-btn--invert" href="<?php echo pmEsc(pmBlockField($data, 'cta_href')); ?>"><?php
      echo pmEsc(pmBlockField($data, 'cta_label')); ?></a></p>
<?php endif; ?>
  </div>
</section>
            <?php
            break;

        case 'eventlist':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
<?php
    // Reads the live event records rather than a copy, so a course that is
    // retired stops appearing here without anyone editing this page.
    $events = function_exists('pmActiveEvents') && $pdo instanceof PDO ? pmActiveEvents($pdo) : [];
    if ($events):
?>
    <div class="pm-grid pm-grid--ruled pm-grid--2 pm-mt-lg">
<?php   foreach ($events as $event): ?>
      <div class="pm-cell">
        <span class="pm-label"><?php echo pmEsc((string) ($event['date_display'] ?? '')); ?></span>
        <h3 class="pm-h3 pm-mt-md"><a href="/event.php?id=<?php echo (int) $event['id']; ?>"><?php
          echo pmEsc((string) ($event['title'] ?? '')); ?></a></h3>
        <p class="pm-body pm-mt-md"><?php echo pmEsc((string) ($event['location'] ?? '')); ?></p>
      </div>
<?php   endforeach; ?>
    </div>
<?php else: ?>
    <p class="pm-body pm-mt-lg">The next calendar is being confirmed.</p>
<?php endif; ?>
  </div>
</section>
            <?php
            break;

        case 'contact':
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php if (pmBlockField($data, 'heading') !== ''): ?>
    <h2 class="pm-h2"><?php echo pmEsc(pmBlockField($data, 'heading')); ?></h2>
<?php endif; ?>
    <p class="pm-lede pm-mt-md"><?php echo pmEsc(pmBlockField($data, 'body')); ?></p>
    <p class="pm-mt-lg"><a class="pm-btn" href="/contact.php">Contact the programme office</a></p>
  </div>
</section>
            <?php
            break;

        case 'embed':
            $url = trim(pmBlockField($data, 'url'));
            ?>
<section class="pm-section<?php echo $mod; ?>">
  <div class="pm-container">
<?php
    // Only https iframes, and only from hosts chosen here. An embed block that
    // accepts any URL is a way to put someone else's JavaScript on this site.
    $host    = parse_url($url, PHP_URL_HOST) ?: '';
    $allowed = ['www.youtube.com', 'youtube.com', 'youtu.be', 'player.vimeo.com', 'www.google.com'];
    if (str_starts_with($url, 'https://') && in_array($host, $allowed, true)):
?>
    <div class="pm-embed">
      <iframe src="<?php echo pmEsc($url); ?>" title="<?php echo pmEsc(pmBlockField($data, 'title', 'Embedded media')); ?>"
              loading="lazy" allowfullscreen referrerpolicy="no-referrer"></iframe>
    </div>
<?php else: ?>
    <p class="pm-body">This embed is not from a permitted source and has not been shown.</p>
<?php endif; ?>
  </div>
</section>
            <?php
            break;
    }

    return (string) ob_get_clean();
}

function pmRenderBlocks(array $blocks, ?PDO $pdo = null): string
{
    $html = '';

    foreach ($blocks as $block) {
        try {
            $html .= pmRenderBlock($block, $pdo);
        } catch (Throwable $e) {
            // One malformed block must not take the whole page down.
            error_log('block render failed (' . ($block['block_type'] ?? '?') . '): ' . $e->getMessage());
        }
    }

    return $html;
}
