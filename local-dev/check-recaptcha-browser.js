// Drives the reCAPTCHA integration in a real browser, against the LOCAL stack
// only. verify.sh cannot reach this half: curl runs no script, so it can prove
// what the server does with a token but not that the page gets one, nor that a
// blocked or slow Google never stops a form submitting.
//
// Google's script is replaced in the browser by a fake grecaptcha that returns a
// token the local stand-in (local-dev/recaptcha-stub) understands, and the
// analytics and ads tags are blocked so a test run sends nothing to Google. The
// server must be started with several workers, because releasing a blocked
// person makes a request from the server to its own site:
//
//   php -S 127.0.0.1:8083 -t local-dev/recaptcha-stub &
//   PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public_html &
//   (public_html/.env needs PM_RECAPTCHA_VERIFY_URL=http://127.0.0.1:8083/siteverify)
//   NODE_PATH=$(npm root -g) node local-dev/check-recaptcha-browser.js
//
// It sets and removes its own reCAPTCHA keys and cleans up what it registers.
// Covers: a verified visitor on the webinar form, the newsletter and the
// four-step event registration; Google blocked; a low score; and that the notice
// Google's terms require is beside the form with its badge hidden.
const { chromium } = require('playwright');
const { execSync } = require('child_process');

const BASE = 'http://127.0.0.1:8080';
const SLUG = 'pfm-insight-live-session-03-ai-made-simple-for-finance-teams';
const db = (sql) => execSync(`docker exec pm-db-main mariadb --skip-ssl -upm_local -ppm_local_pw kidsmone_Prosperminds_website -N -e "${sql.replace(/"/g, '\\"')}"`).toString().trim();
const BLOCK = /googletagmanager|google-analytics|analytics\.google|doubleclick|googleadservices|google\.com\/(ccm|rmkt|pagead)/;
const FAKE = (kind) => `window.grecaptcha={ready:function(cb){setTimeout(cb,0)},execute:function(k,o){return Promise.resolve('${kind}:'+o.action)}};`;

let failures = 0;
const ok = (label, cond, extra) => {
  console.log((cond ? '  PASS  ' : '  FAIL  ') + label.padEnd(62) + (extra !== undefined ? String(extra) : ''));
  if (!cond) failures++;
};

(async () => {
  db("REPLACE INTO site_settings (setting_key,setting_value) VALUES ('recaptcha_site_key','localsitekeylocalsitekey'),('recaptcha_secret_key','localsecretlocalsecret'),('form_guard_mode','enforce')");
  db("DELETE FROM form_throttle; DELETE FROM form_quarantine; DELETE FROM webinar_registrations WHERE email LIKE '%@rcbrowser.example'; DELETE FROM newsletter_subscribers WHERE email LIKE '%@rcbrowser.example'; DELETE FROM event_registrations WHERE email LIKE '%@rcbrowser.example'");
  const browser = await chromium.launch();

  async function open(mode, url) {
    const p = await (await browser.newContext({ viewport: { width: 390, height: 844 } })).newPage();
    await p.route(BLOCK, r => r.abort());
    await p.route('**/recaptcha/api.js*', r => (mode === 'blocked' ? r.abort() : r.fulfill({ contentType: 'text/javascript', body: FAKE(mode) })));
    p.errors = []; p.on('pageerror', e => p.errors.push(String(e)));
    await p.goto(url, { waitUntil: 'networkidle' });
    return p;
  }
  const submitWebinar = async (p, who) => {
    await p.fill('#pm-webinar-name', who.name); await p.fill('#pm-webinar-email', who.email); await p.check('#pm-webinar-consent');
    await p.click('form[action="/process-webinar-registration.php"] button[type=submit]');
    await p.waitForURL(/webinar=/, { timeout: 15000 }).catch(() => {});
    return (p.url().match(/webinar=(\w+)/) || [])[1];
  };

  // 1. A verified visitor, submitting at once
  let p = await open('good', `${BASE}/webinar.php?slug=${SLUG}`);
  // Two guarded forms on the page: the sign-up and the footer newsletter.
  ok('the notice Google requires sits beside each of the two forms', await p.locator('.pm-recaptcha-note').count() === 2);
  ok('and its floating badge is hidden', await p.evaluate(() => { const d = document.createElement('div'); d.className = 'grecaptcha-badge'; document.body.appendChild(d); return getComputedStyle(d).visibility === 'hidden'; }));
  ok('the page says reCAPTCHA is not yet used (pm_rc starts as unavailable)', await p.inputValue('input[name=pm_rc]') === 'unavailable');
  ok('a verified visitor is registered with no waiting', await submitWebinar(p, { name: 'Verified Visitor', email: 'verified@rcbrowser.example' }) === 'ok');
  ok('and is on the list', db("SELECT COUNT(*) FROM webinar_registrations WHERE email='verified@rcbrowser.example'") === '1');
  ok('no script errors', p.errors.length === 0, p.errors.join(' | '));

  // 2. Google's script blocked: the form still submits, judged by the fallback
  p = await open('blocked', `${BASE}/webinar.php?slug=${SLUG}`);
  await p.waitForTimeout(3000);
  ok('with Google blocked the visitor is still registered', await submitWebinar(p, { name: 'Blocked Google', email: 'blocked@rcbrowser.example' }) === 'ok');
  ok('and is on the list', db("SELECT COUNT(*) FROM webinar_registrations WHERE email='blocked@rcbrowser.example'") === '1');

  // 3. A low score is told so, plainly, and the whole form is kept
  p = await open('low', `${BASE}/webinar.php?slug=${SLUG}`);
  ok('a low score is refused', await submitWebinar(p, { name: 'Low Score', email: 'low@rcbrowser.example' }) === 'verify');
  ok('with a message that says what to do', /email info@prosper-minds\.com/.test(await p.locator('.pm-notice').first().textContent()));
  ok('nothing was registered', db("SELECT COUNT(*) FROM webinar_registrations WHERE email='low@rcbrowser.example'") === '0');
  ok('and the stopped form was kept with its score', db("SELECT COUNT(*) FROM form_quarantine WHERE reasons='low-score' AND score=0.10 AND payload IS NOT NULL") === '1');

  // 4. The newsletter in the footer of every page
  p = await open('good', `${BASE}/`);
  await p.fill('#pm-newsletter-email', 'newsletter@rcbrowser.example');
  await p.click('footer form button[type=submit]');
  await p.waitForURL(/newsletter=/, { timeout: 15000 }).catch(() => {});
  ok('a verified visitor subscribes', (p.url().match(/newsletter=(\w+)/) || [])[1] === 'ok');
  ok('and is stored', db("SELECT COUNT(*) FROM newsletter_subscribers WHERE email='newsletter@rcbrowser.example'") === '1');

  // 5. The four-step event registration, which submits through its own script
  async function event(mode, email) {
    const q = await open(mode, `${BASE}/event-registration.php?id=5`);
    await q.click('[data-pm-next]');
    await q.fill('#pm-reg-first', 'Amina'); await q.fill('#pm-reg-last', 'Otieno'); await q.fill('#pm-reg-org', 'The National Treasury');
    await q.fill('#pm-reg-email', email); await q.fill('#pm-reg-phone', '+254712345678'); await q.fill('#pm-reg-country', 'Kenya'); await q.fill('#pm-reg-address', 'Nairobi');
    await q.click('[data-pm-next]'); await q.click('[data-pm-next]');
    await q.check('#pm-reg-consent'); await q.click('[data-pm-submit]');
    await q.waitForFunction(() => !document.querySelector('[data-pm-done]').hidden || !document.querySelector('#pm-reg-status').hidden, null, { timeout: 25000 });
    const done = await q.evaluate(() => !document.querySelector('[data-pm-done]').hidden);
    const text = done ? await q.textContent('[data-pm-done-invoice]') : (await q.textContent('#pm-reg-status')).trim();
    await q.context().close();
    return { done, text };
  }
  let e = await event('good', 'good@rcbrowser.example');
  ok('a verified visitor registers for an event and gets an invoice', e.done && /^INV-/.test(e.text), e.text);
  e = await event('low', 'low@rcbrowser.example');
  ok('a low score on the event form is refused with a message', !e.done && /could not confirm/.test(e.text), e.text.slice(0, 60));
  ok('and nothing is registered for them', db("SELECT COUNT(*) FROM event_registrations WHERE email='low@rcbrowser.example'") === '0');

  await browser.close();
  db("DELETE FROM form_throttle; DELETE FROM form_quarantine; DELETE FROM webinar_registrations WHERE email LIKE '%@rcbrowser.example'; DELETE FROM newsletter_subscribers WHERE email LIKE '%@rcbrowser.example'; DELETE FROM event_registrations WHERE email LIKE '%@rcbrowser.example'; DELETE FROM site_settings WHERE setting_key IN ('recaptcha_site_key','recaptcha_secret_key','form_guard_mode')");
  console.log(failures === 0 ? '\nAll checks passed.' : `\n${failures} check(s) failed.`);
  process.exit(failures === 0 ? 0 : 1);
})();
