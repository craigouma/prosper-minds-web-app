<!-- Google tag (gtag.js) -->
<!--
  One gtag.js load configures both destinations -- doesn't matter which ID is
  used as the loader's own id= param, per Google's own multi-product pattern.

  G-H030354F23 is the GA4 property ("Prosper-Minds"). AW-18352784550 is the
  existing Ads account tag. The Ads "Purchase" conversion action
  (conversion type ID 7720563828) is sourced FROM this GA4 property's own
  `purchase` event -- Google Ads imports it automatically -- so firing a GA4
  purchase event (see process-registration.php's success handler) is what
  satisfies that conversion action. No separate Ads-only conversion snippet
  needed for this one.
-->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-H030354F23"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-H030354F23');
  gtag('config', 'AW-18352784550');
</script>
