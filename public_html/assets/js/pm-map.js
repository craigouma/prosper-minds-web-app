/**
 * The office location map on contact.php.
 *
 * WHY OPENFREEMAP AND NOT GOOGLE MAPS
 * -----------------------------------
 * The live site embeds a Google Maps iframe. This site already has to justify
 * Google Analytics and Google Ads as third-party data flows under the Kenya
 * Data Protection Act 2019 and, for EU delegates, GDPR. A third Google embed
 * would be a third one to justify, on the one page whose entire job is a form.
 * OpenFreeMap needs no API key, no signup, and does not track visitors.
 *
 * The style is 'positron', deliberately. OpenFreeMap also serves 'bright' and
 * 'liberty'; both are full-colour and would put half a dozen hues that are not
 * green, black or white onto a page in a brand whose palette is exactly those
 * three. Positron is the restrained light grey style and is the only one that
 * belongs here.
 *
 * MapLibre GL JS itself is self-hosted in assets/js/, for the same reason
 * Maharlika is self-hosted rather than loaded from Google Fonts: it removes a
 * cross-border third-party request for no benefit. The vector tiles still come
 * from tiles.openfreemap.org, which is unavoidable for any map at all, but that
 * is one third-party host rather than a script host plus a tile host.
 *
 * SAFETY CONTRACT
 * ---------------
 * The map is decoration around information that is already on the page. The
 * address, the email address and both phone numbers are real text in the HTML,
 * above this element, and none of them is inside the map. So:
 *
 *   1. Everything is inside try/catch. A map failure costs the page its map,
 *      never its contact form. Same discipline as assets/js/pm-layout.js and
 *      the rest of this codebase.
 *   2. If MapLibre did not load at all, or the browser cannot do WebGL, this
 *      file returns quietly and the container keeps the fallback line that is
 *      already in the markup.
 *   3. Nothing here is required for the page to be usable. With JavaScript off
 *      the visitor sees the fallback line, and the link to open the location in
 *      their own map app sits outside the map, in the text beside it, where no
 *      script can remove it.
 *   4. Scroll zoom is off. A map that swallows the page scroll when a visitor
 *      is on their way to the form below it is hostile on a phone. Zoom buttons
 *      and the keyboard still work.
 */
(function () {
  'use strict';

  function initMap(el) {
    var lat = parseFloat(el.getAttribute('data-lat'));
    var lng = parseFloat(el.getAttribute('data-lng'));
    var zoom = parseFloat(el.getAttribute('data-zoom'));
    var style = el.getAttribute('data-style');

    // A bad coordinate must not produce a map of the wrong place, and must not
    // produce a broken one either. The server side validates these too; this is
    // the second gate, because the value reaches here as a string attribute.
    if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
      return;
    }

    if (!isFinite(zoom) || zoom < 1 || zoom > 20) {
      zoom = 16;
    }

    var map = new maplibregl.Map({
      container: el,
      style: style,
      center: [lng, lat],
      zoom: zoom,
      // Attribution is required by OpenStreetMap's licence and is carried by
      // the style itself. It stays on.
      attributionControl: { compact: true },
      // See safety contract point 4.
      scrollZoom: false,
      // The map is a location, not a globe to spin.
      pitchWithRotate: false,
      dragRotate: false,
      touchZoomRotate: true
    });

    // Keyboard-operable zoom, so the control is not mouse-only.
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    new maplibregl.Marker({ color: '#00BF63' }).setLngLat([lng, lat]).addTo(map);

    // Only now hide the fallback line. If the style request fails the map stays
    // blank, and a blank grey box with no explanation is worse than the
    // sentence it replaced.
    map.on('load', function () {
      try {
        el.setAttribute('data-pm-map-ready', 'true');
      } catch (e) { /* nothing to do */ }
    });

    map.on('error', function () {
      // Tiles or style unreachable. Put the fallback line back rather than
      // leaving an empty frame.
      try {
        el.removeAttribute('data-pm-map-ready');
      } catch (e) { /* nothing to do */ }
    });
  }

  try {
    // Feature-detect the constructor, not a maplibregl.supported() helper.
    // That helper existed in MapLibre 2 and 3 and was REMOVED in 4; checking
    // for it here would be false on the version actually shipped in
    // assets/js/maplibre-gl.js and would silently disable the map on every
    // browser. The library raises its own error if WebGL is unavailable, and
    // the try/catch below turns that into the fallback line.
    if (typeof maplibregl === 'undefined' || typeof maplibregl.Map !== 'function') {
      return;
    }

    var nodes = document.querySelectorAll('[data-pm-map]');

    for (var i = 0; i < nodes.length; i++) {
      try {
        initMap(nodes[i]);
      } catch (e) {
        // One map failing must not stop another, and must not throw out of
        // this file into whatever runs next.
      }
    }
  } catch (e) {
    /* The page keeps its address, its directions and its form. */
  }
})();
