<?php
/**
 * Privacy policy.
 *
 * Written against what this codebase ACTUALLY does, not a generic template.
 * Every claim here was checked against the code before it was written:
 *
 *   * The registration fields listed are the columns process-registration.php
 *     really inserts into event_registrations.
 *   * The analytics cookie section describes includes/funnel.php as built:
 *     one first-party cookie (pm_funnel_sid), a random UUID, 24 hours, and
 *     deliberately no IP address and no user agent stored.
 *   * The newsletter section describes newsletter_subscribers, which stores an
 *     email address and a timestamp and nothing else.
 *
 * If any of those change, this page has to change with them. That is the point
 * of writing it from the code rather than from a boilerplate.
 *
 * House style: no em dashes anywhere in the copy. Client instruction.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout/page.php';

pmPageBegin([
    'slug'        => 'privacy',
    'nav'         => '',
    'title'       => 'Privacy Policy',
    'description' => 'How Prosperminds collects, uses, stores and protects personal data, and the rights you have over your information.',
    'canonical'   => '/privacy-policy.php',
]);
?>

<section class="pm-section pm-section--tight">
  <div class="pm-container pm-measure">

    <span class="pm-eyebrow">Legal</span>
    <h1 class="pm-h1">Privacy Policy</h1>
    <p class="pm-lede pm-mt-lg">
      This policy explains what personal data Prosperminds collects through this
      website, why we collect it, how long we keep it, and what rights you have
      over it.
    </p>
    <p class="pm-muted pm-mt-sm">Last updated: 28 August 2026</p>

    <h2 class="pm-h3 pm-mt-xl">Who we are</h2>
    <p>
      Prosperminds delivers professional training in public financial management,
      IPSAS and IFRS reporting, data analytics, automation and sustainability
      disclosure for the public sector. We are the data controller for the
      information described in this policy.
    </p>
    <p>
      Our office is at Twiga Towers, Moi Avenue, Nairobi, Kenya. You can reach us
      at <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a>,
      or on +254 740 582302 or +254 741 174909, Monday to Friday, 8am to 5pm
      East Africa Time.
    </p>

    <h2 class="pm-h3 pm-mt-xl">The law we work under</h2>
    <p>
      We handle personal data in line with the Kenya Data Protection Act 2019.
      Where a delegate or an enquirer is in the European Union or the United
      Kingdom, we also handle their data in line with the General Data Protection
      Regulation. Where the two differ, we apply the stricter standard.
    </p>

    <h2 class="pm-h3 pm-mt-xl">What we collect, and why</h2>

    <h3 class="pm-h4 pm-mt-lg">When you register for a course</h3>
    <p>
      To reserve a place, raise an invoice and run the course, we collect the
      billing contact name, phone number, email address, organisation, country
      and billing address. For each delegate being registered we collect their
      name, email address and job title where you give it.
    </p>
    <p>
      We also collect optional details that help us run the event properly:
      gender, meal preference, and any future topics you tell us you are
      interested in. You can leave these blank and still register.
    </p>
    <p>
      Our lawful basis is the performance of a contract, because we cannot
      deliver a paid course or issue a valid invoice without this information.
      We ask for your explicit consent at the point of registration before we
      use your details for anything beyond running the course you booked.
    </p>

    <h3 class="pm-h4 pm-mt-lg">When you enquire about sponsorship</h3>
    <p>
      We collect your name, organisation, email address, phone number, country,
      which events interest you, the sponsorship tier you are considering and
      whatever you write in the message field. We use it only to respond to your
      enquiry and to discuss a possible partnership. Our lawful basis is our
      legitimate interest in replying to a business enquiry you initiated.
    </p>

    <h3 class="pm-h4 pm-mt-lg">When you subscribe to the newsletter</h3>
    <p>
      We store your email address and the date you subscribed. Nothing else. We
      use it to send course dates and early bird deadlines when they are
      confirmed. Our lawful basis is your consent, which you give by subscribing
      and can withdraw at any time using the unsubscribe link in any newsletter,
      or by emailing us.
    </p>

    <h3 class="pm-h4 pm-mt-lg">When you contact us</h3>
    <p>
      If you use a contact form or email us, we keep your message and contact
      details so we can answer you and follow up if needed.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Cookies and analytics</h2>
    <p>
      We use one first party cookie of our own, named
      <code>pm_funnel_sid</code>. It holds a randomly generated identifier, it
      lasts 24 hours, and its only job is to let us count how many people who
      open a registration page go on to complete it. We deliberately do not
      store your IP address or your browser user agent alongside it, and it
      carries no meaning outside our own conversion counts. It cannot be used to
      identify you.
    </p>
    <p>
      We also use Google Analytics and Google Ads to understand how people find
      and use the site and to measure the results of our advertising. These set
      their own cookies and send data to Google, which acts as a separate data
      controller for that information. You can read how Google handles it in the
      <a href="https://policies.google.com/privacy" rel="noopener noreferrer" target="_blank">Google Privacy Policy</a>.
    </p>
    <p>
      Most browsers let you block or delete cookies in their settings. Blocking
      the analytics cookies does not stop you registering for a course or using
      any part of this site.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Who we share data with</h2>
    <p>
      We do not sell personal data, and we do not share it for anyone else's
      marketing. We share it only with service providers who help us run the
      business, and only to the extent they need it. These are our web hosting
      provider, our email delivery provider, our newsletter provider, and Google
      for the analytics and advertising described above. Each of them is bound to
      protect the data and to use it only on our instructions.
    </p>
    <p>
      We may also disclose information where the law requires it, for example in
      response to a valid legal order.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Where data is stored</h2>
    <p>
      Our website and database are hosted on servers operated by our hosting
      provider. Some of our service providers, including Google, process data
      outside Kenya and outside the European Economic Area. Where that happens we
      rely on the safeguards those providers put in place for international
      transfers, such as standard contractual clauses.
    </p>

    <h2 class="pm-h3 pm-mt-xl">How long we keep it</h2>
    <p>
      Registration and invoice records are kept for seven years, because tax and
      accounting rules require us to retain financial records for that period.
    </p>
    <p>
      Sponsorship and general enquiries are kept for two years from our last
      contact with you, then deleted.
    </p>
    <p>
      Newsletter subscriptions are kept until you unsubscribe. Once you
      unsubscribe we remove your address from the mailing list.
    </p>
    <p>
      The analytics cookie described above expires after 24 hours. The
      conversion counts it produces are aggregate figures that do not identify
      anyone.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Your rights</h2>
    <p>You have the right to:</p>
    <ul class="pm-list">
      <li>ask what personal data we hold about you, and get a copy of it</li>
      <li>have inaccurate data corrected</li>
      <li>ask us to delete your data, where we are not required to keep it</li>
      <li>object to or ask us to restrict how we use it</li>
      <li>withdraw consent at any time, where we relied on consent</li>
      <li>ask us to transfer your data to another provider in a portable format</li>
      <li>complain to the Office of the Data Protection Commissioner in Kenya, or to your local supervisory authority if you are in the EU or UK</li>
    </ul>
    <p>
      To use any of these rights, email
      <a href="mailto:info@prosper-minds.com">info@prosper-minds.com</a>.
      We will respond within the time the law allows, and we will not charge you
      for a reasonable request.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Keeping data secure</h2>
    <p>
      Access to registration records is limited to staff who need it, and is
      protected by individual accounts and passwords. The site is served over an
      encrypted HTTPS connection, and database credentials are held outside the
      published code. No system is perfectly secure, but we take these measures
      seriously and review them as the site changes.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Children</h2>
    <p>
      Our courses are for working professionals. This site is not directed at
      children, and we do not knowingly collect data about anyone under 18. If
      you believe we have, please contact us and we will delete it.
    </p>

    <h2 class="pm-h3 pm-mt-xl">Changes to this policy</h2>
    <p>
      If we change how we handle personal data, we will update this page and
      change the date at the top. Where a change is significant, we will say so
      clearly rather than rely on you noticing.
    </p>

  </div>
</section>

<?php pmPageEnd(); ?>
