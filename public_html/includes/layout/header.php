<?php
/**
 * Site header and primary navigation for every rebuilt page.
 *
 * Included by pmPageBegin() in includes/layout/page.php, which is the only
 * supported way to reach it — it expects $pmPage to already be in scope.
 *
 * Structure, taken from the approved prototype's header:
 *   left   shield-and-mind mark + PROSPERMINDS wordmark, linking home
 *   right  nav links in uppercase letter-spaced small caps
 *   far right  the green primary action
 *
 * The mark is inline SVG rather than an <img> so it inherits nothing, costs no
 * request, and stays crisp. It is the brand's secondary/digital logo: the
 * shield outline in green with the neural head inside in black. Per the brand
 * rules it is never recoloured, stretched, shadowed or rotated.
 *
 * MOBILE MENU — progressive enhancement, deliberately
 * ---------------------------------------------------
 * The nav is a plain list in the document. Below 900px, and ONLY when the
 * inline script in head.php has confirmed JavaScript runs (html[data-pm-js]),
 * CSS collapses it into a full-screen panel and reveals the toggle button.
 * With JavaScript off, no toggle is shown and every link stays in normal flow
 * and reachable. Nothing about navigating this site requires script.
 *
 * The toggle's state lives in one place: data-pm-nav-open on <html>. CSS reads
 * it, assets/js/pm-layout.js writes it, and aria-expanded is kept in step so
 * the control announces correctly.
 */

/** @var array<string, mixed> $pmPage */

$pmNavActive = (string) ($pmPage['nav'] ?? '');
?>
<header class="pm-header">
  <div class="pm-header__bar pm-container">

    <a class="pm-brand" href="/index.php">
      <!-- The real logo file, the same one the site has always used. It is the
           brand's own artwork, so nothing here redraws or approximates it: the
           brand guide forbids altering the mark, and an invented lookalike is a
           worse violation than a scaling one. -->
      <img class="pm-brand__logo" src="/assets/images/fisrt-logo.png"
           alt="Prosperminds" width="713" height="183">
    </a>

    <button
      type="button"
      class="pm-nav-toggle"
      id="pm-nav-toggle"
      aria-controls="pm-nav"
      aria-expanded="false"
    >Menu</button>

    <nav class="pm-nav" id="pm-nav" aria-label="Primary">

      <?php // Only ever visible inside the open mobile panel, which covers the
            // header bar. Repeats the lock-up so the panel is still identifiably
            // the Prosperminds site, exactly as the prototype's panel does. ?>
      <div class="pm-nav__panel-head">
        <img class="pm-brand__logo" src="/assets/images/fisrt-logo.png"
             alt="Prosperminds" width="713" height="183">
        <button type="button" class="pm-nav__close" id="pm-nav-close" aria-label="Close menu">
          <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
            <path d="M2 2 16 16M16 2 2 16" stroke="#000" stroke-width="1.6" stroke-linecap="round"></path>
          </svg>
        </button>
      </div>

<?php foreach (pmNavItems() as $pmNavKey => $pmNavItem): ?>
      <a
        class="pm-nav__link"
        href="<?php echo pmEsc($pmNavItem['href']); ?>"
        <?php echo $pmNavKey === $pmNavActive ? 'aria-current="page"' : ''; ?>
      ><?php echo pmEsc($pmNavItem['label']); ?></a>
<?php endforeach; ?>

      <a class="pm-btn pm-nav__cta" href="<?php echo pmEsc(pmRegisterHref()); ?>">Register a delegate</a>

      <div class="pm-nav__panel-contact">
        info@prosper-minds.com<br>
        +254 740 582302<br>
        Mon to Fri, 8am to 5pm EAT
      </div>
    </nav>

  </div>
</header>
