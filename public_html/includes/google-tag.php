<!-- Google tag (gtag.js) -->
<!--
  One gtag.js load configures both destinations -- doesn't matter which ID is
  used as the loader's own id= param, per Google's own multi-product pattern.

  G-H030354F23 is the GA4 property ("Prosper-Minds"). AW-18352784550 is the
  Ads account.

  CONVERSIONS. The registration confirmation fires two hits (see
  assets/js/pm-register.js): a GA4 `purchase` event, and the Google Ads
  conversion action named below. The Ads hit exists because a GA4-imported
  conversion is not a live Ads conversion: it arrives on Google's import
  schedule rather than at the moment of sale, and Tag Assistant cannot verify
  it, which is why testing the tag looked broken when it was not.

  ONLY ONE OF THE TWO MAY COUNT. If the Ads account still imports the GA4
  `purchase` key event as a conversion action AND the label below is set, every
  registration is counted twice and Smart Bidding is fed a number that is
  double the truth. Set the GA4-imported action to Secondary in Google Ads, or
  delete the line below. Deleting it is the whole switch: pm-register.js sends
  the Ads hit only when this is defined.
-->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-H030354F23"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-H030354F23');
  gtag('config', 'AW-18352784550');

  window.pmAdsPurchaseConversion = 'AW-18352784550/upCfCLTBgt0cEKaJpa9E';
</script>
