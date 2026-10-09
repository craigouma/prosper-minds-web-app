<?php
/** @var array<string, mixed> $pmPage */
/** @var PDO|null $pdo */

$pmNavActive = (string) ($pmPage['nav'] ?? '');
$pmContact = pmContact($pdo ?? null);
?>
<header class="pm-header">
  <div class="pm-header__bar pm-container">

    <a class="pm-brand" href="/" aria-label="Prosperminds home">
      <img class="pm-brand__logo" src="/assets/images/fisrt-logo.png"
           alt="Prosperminds" width="713" height="183">
    </a>

    <span class="pm-header__spacer"></span>

    <nav class="pm-nav" id="pm-nav" aria-label="Primary">

      <div class="pm-nav__panel-head">
        <img class="pm-brand__logo" src="/assets/images/fisrt-logo.png"
             alt="Prosperminds" width="713" height="183">
        <button type="button" class="pm-nav__close" id="pm-nav-close" aria-label="Close menu">
          <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M1 1l14 14M15 1 1 15" stroke="#000" stroke-width="1.8" fill="none"></path>
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

      <a class="pm-btn pm-nav__cta" href="<?php echo pmEsc(pmRegisterHref()); ?>">Register</a>

      <div class="pm-nav__panel-contact">
<?php foreach ($pmContact['phones'] as $pmPhone): ?>
        <a href="tel:<?php echo pmEsc($pmPhone['tel']); ?>"><?php echo pmEsc($pmPhone['label']); ?></a>
<?php endforeach; ?>
        <a href="mailto:<?php echo pmEsc($pmContact['email']); ?>"><?php echo pmEsc($pmContact['email']); ?></a>
        <span><?php echo pmEsc($pmContact['hours']); ?></span>
      </div>
    </nav>

<?php if (empty($pmPage['hide_cta'])): ?>
    <a class="pm-btn pm-header__cta" href="<?php echo pmEsc(pmRegisterHref()); ?>">Register</a>
<?php endif; ?>

    <button
      type="button"
      class="pm-nav-toggle"
      id="pm-nav-toggle"
      aria-controls="pm-nav"
      aria-expanded="false"
      aria-label="Menu"
    >
      <svg width="20" height="14" viewBox="0 0 20 14" aria-hidden="true">
        <path d="M0 1h20M0 7h20M0 13h20" stroke="currentColor" stroke-width="1.8" fill="none"></path>
      </svg>
    </button>

  </div>
</header>
