<?php

/**
 * Google Tag Manager container.
 *
 * The ID is declared once here and used by both halves, because the two
 * snippets Google gives you carry it separately and a mismatched pair fails
 * silently: the head half loads a container and the body half points a noscript
 * iframe at a different one.
 *
 * DO NOT ALSO CONFIGURE GA4 OR GOOGLE ADS INSIDE THIS CONTAINER.
 * includes/google-tag.php already configures both on the page itself:
 *
 *   G-H030354F23    the GA4 property
 *   AW-18352784550  the Ads account, including the Purchase conversion
 *
 * Adding a GA4 Configuration tag or a Google Ads Conversion Tracking tag in GTM
 * for either of those IDs double-tags the site. Every page view is counted
 * twice and every registration reports as two conversions, which is the exact
 * failure Google's own setup guide warns about. This container is for tags that
 * are NOT already on the page.
 */

const PM_GTM_ID = 'GTM-TS7N8L9D';

/** The loader. Google asks for this as high in the head as possible. */
function pmGtmHead(): void
{
    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo PM_GTM_ID; ?>');</script>
    <!-- End Google Tag Manager -->
<?php
}

/** The no-JavaScript fallback, which belongs immediately after <body>. */
function pmGtmBody(): void
{
    ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo PM_GTM_ID; ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php
}
