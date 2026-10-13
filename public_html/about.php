<?php
/**
 * About, with Contact and the past cohorts on the same page.
 *
 * Vision and mission are the client's own stored wording (page_content
 * 'about'), the three pillars come from slug 'services' so one definition
 * serves every page that shows them, and the contact details come from
 * pmContact(). Past cohorts are read from the events archive.
 *
 * House style: no em dashes in any user-visible copy. Client instruction.
 */

require_once __DIR__ . '/includes/layout/page.php';

$pmStats = array_slice(pmContentJson($pdo, 'about', 'stats', [
    ['value' => '25',  'label' => 'Years collective experience'],
    ['value' => '875', 'label' => 'Leaders trained'],
]), 0, 2);

$pmPast = pmPartitionEventsByDate(pmAllEvents($pdo))['past'];
$pmContact = pmContact($pdo);
$pmWhatsApp = pmWhatsAppUrl();

pmPageBegin([
    'slug'        => 'about',
    'nav'         => 'about',
    'title'       => pmContent($pdo, 'about', 'meta_title', 'About Prosperminds'),
    'description' => pmContent($pdo, 'about', 'meta_description', 'A training institution for the public sector, working with ministries of finance, audit offices, revenue authorities and state corporations across Africa.'),
    'canonical'   => '/about.php',
]);
?>

<div class="pm-container pm-page-head pm-section--tight">
  <h1 class="pm-h1"><?php echo pmContentSafe($pdo, 'about', 'v2_hero_title', 'About Prosperminds'); ?></h1>
  <p class="pm-lede pm-mt-md"><?php echo pmContentSafe($pdo, 'about', 'v2_hero_body',
    'Prosperminds trains senior public finance officials across Africa, practitioner to practitioner, through five-day residential schools and a free monthly webinar series. We are based in Nairobi and work in partnership with CapaBuil Ltd.'); ?></p>
</div>


<section class="pm-band pm-mt-lg">
  <div class="pm-container pm-vm">
    <div>
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'about', 'vision_eyebrow', 'Vision'); ?></span>
      <p class="pm-vm__vision"><?php echo pmContentSafe($pdo, 'about', 'vision_body',
        'An Africa where every public institution is trusted with its money, led by world-class finance minds.'); ?></p>
    </div>
    <div>
      <span class="pm-label"><?php echo pmContentSafe($pdo, 'about', 'mission_eyebrow', 'Mission'); ?></span>
      <p class="pm-vm__mission"><?php echo pmContentSafe($pdo, 'about', 'mission_body',
        'We prepare Africa\'s senior finance leaders, practitioner to practitioner, to master the standards, put AI to work, and disclose with integrity, so their institutions earn trust at home and respect worldwide.'); ?></p>
    </div>
  </div>
</section>


<div class="pm-container pm-section">

  <section>
    <div class="pm-rule-head pm-rule-head--thin">
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'about', 'v2_teach_title', 'What we teach'); ?></h2>
    </div>
    <dl class="pm-rows">
<?php foreach (pmContentJson($pdo, 'services', 'pillars', pmPillarsDefault()) as $pmPillar): ?>
      <div>
        <dt><?php echo pmEsc((string) ($pmPillar['name'] ?? '')); ?></dt>
        <dd><?php echo pmEsc((string) ($pmPillar['promise'] ?? '')); ?></dd>
      </div>
<?php endforeach; ?>
    </dl>
  </section>

<?php if ($pmStats !== [] || $pmPast !== []): ?>
  <section class="pm-mt-xl">
<?php   if ($pmStats !== []): ?>
    <div class="pm-bigstats pm-bigstats--sm">
<?php     foreach ($pmStats as $pmStat): ?>
      <div>
        <div class="pm-bigstat__value" data-pm-count><?php echo pmEsc((string) ($pmStat['value'] ?? '')); ?></div>
        <div class="pm-bigstat__label"><?php echo pmEsc((string) ($pmStat['label'] ?? '')); ?></div>
      </div>
<?php     endforeach; ?>
    </div>
<?php   endif; ?>
<?php   if ($pmPast !== []): ?>
    <a class="pm-link pm-mt-md" href="#past-cohorts">See past cohorts</a>
<?php   endif; ?>
  </section>
<?php endif; ?>

  <section class="pm-split pm-mt-xl" id="contact">
    <div>
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'about', 'v2_contact_title', 'Contact'); ?></h2>
      <p class="pm-body pm-mt-md"><?php echo $pmContact['address_html']; ?></p>
      <p><a class="pm-link" href="https://maps.google.com/?q=Twiga+Towers+Moi+Avenue+Nairobi">Get directions</a></p>
      <p class="pm-caption"><?php echo pmEsc($pmContact['hours']); ?></p>
    </div>

    <div class="pm-contact-actions">
<?php if ($pmWhatsApp !== ''): ?>
      <a class="pm-btn" href="<?php echo pmEsc($pmWhatsApp); ?>" rel="noopener">WhatsApp</a>
<?php endif; ?>
<?php foreach ($pmContact['phones'] as $pmIndex => $pmPhone): ?>
      <a class="pm-btn<?php echo ($pmWhatsApp !== '' || $pmIndex > 0) ? ' pm-btn--secondary' : ''; ?>"
         href="tel:<?php echo pmEsc($pmPhone['tel']); ?>">Call <?php echo pmEsc($pmPhone['label']); ?></a>
<?php endforeach; ?>
      <a class="pm-btn pm-btn--secondary" href="mailto:<?php echo pmEsc($pmContact['email']); ?>"><?php
        echo pmEsc($pmContact['email']); ?></a>
    </div>
  </section>

<?php if ($pmPast !== []): ?>
  <section class="pm-mt-xl" id="past-cohorts">
    <div class="pm-rule-head pm-rule-head--thin">
      <h2 class="pm-h2 pm-h2--md"><?php echo pmContentSafe($pdo, 'about', 'v2_past_title', 'Past cohorts'); ?></h2>
    </div>
    <ol class="pm-sessions pm-sessions--plain">
<?php   foreach ($pmPast as $pmCohort): ?>
      <li>
        <span class="pm-sessions__date"><?php echo pmEsc(pmEventDatesLong($pmCohort)); ?></span>
        <span class="pm-sessions__title"><?php echo pmEsc(pmEventProse((string) ($pmCohort['title'] ?? ''))); ?></span>
        <span class="pm-caption"><?php echo pmEsc(pmEventPlace($pmCohort)); ?></span>
      </li>
<?php   endforeach; ?>
    </ol>
  </section>
<?php endif; ?>

</div>

<?php pmPageEnd(); ?>
