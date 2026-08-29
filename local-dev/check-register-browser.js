// Drives the rebuilt registration flow in a real browser, against the LOCAL
// stack only. This is the half local-dev/verify.sh cannot reach: curl runs no
// script, so every assertion there is either about the undriven document or a
// line-order check on the source.
//
// NOT wired into verify.sh, deliberately. verify.sh depends on nothing but the
// shell, curl, php and (optionally) node, and adding Playwright to it would make
// the assertion count depend on what is installed. Run this by hand:
//
//   npx playwright install chromium          # once
//   NODE_PATH=$(npm root -g) node local-dev/check-register-browser.js
//
// Covers: the five-step flow, the delegate stepper and the live invoice total,
// per-step validation, the confirmation and the GA4 purchase payload, the
// no-JavaScript fallback, and what happens when each script is missing or throws.
const { chromium } = require('playwright');

const BASE = 'http://127.0.0.1:8080';
const EVENT = process.env.EVENT_ID || '5';
const EMAIL = process.env.EMAIL || 'p4-browser@example.test';

let failures = 0;
const ok = (label, cond, extra) => {
  console.log((cond ? '  PASS  ' : '  FAIL  ') + label.padEnd(58) + (extra !== undefined ? String(extra) : ''));
  if (!cond) failures++;
};

(async () => {
  const browser = await chromium.launch();

  // ── 1. JavaScript ON: the driven five-step flow ─────────────────────────
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(String(e)));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text()); });

  await page.goto(`${BASE}/event-registration.php?id=${EVENT}`, { waitUntil: 'networkidle' });

  ok('no page error on load', errors.length === 0, errors.join(' | '));
  ok('flow took over (data-pm-steps set)',
     await page.getAttribute('html', 'data-pm-steps') === 'on');

  const visible = async sel => page.locator(sel).isVisible();
  ok('step 1 visible', await visible('[data-pm-step="1"]'));
  ok('step 2 hidden', !(await visible('[data-pm-step="2"]')));
  ok('step 4 hidden', !(await visible('[data-pm-step="4"]')));
  ok('confirmation hidden', !(await visible('[data-pm-done]')));
  ok('stepper visible', await visible('[data-pm-stepper]'));
  ok('step counter reads step 1',
     (await page.textContent('[data-pm-stepcount]')).trim() === 'Step 1 of 5');

  const totalText = () => page.textContent('[data-pm-invoice-total]');
  const totalAttr = () => page.getAttribute('[data-pm-invoice-total]', 'data-pm-total-amount');
  const unit = parseFloat(await page.getAttribute('[data-pm-register]', 'data-pm-unit-amount'));

  ok('total starts at unit price', (await totalAttr()) === unit.toFixed(2), await totalText());

  // The stepper, and the live total.
  await page.click('[data-pm-step-up]');
  await page.click('[data-pm-step-up]');
  ok('stepper reads 3', (await page.textContent('[data-pm-count-value]')).trim() === '3');
  ok('total is unit x 3', (await totalAttr()) === (unit * 3).toFixed(2), await totalText());
  ok('summary line count reads 3', (await page.textContent('[data-pm-line-count]')).trim() === '3');

  // Rows above the count must be DISABLED, not just hidden, or they post.
  const rowState = await page.$$eval('[data-pm-delegate]', rows => rows.map(r => ({
    hidden: r.hidden,
    disabled: Array.from(r.querySelectorAll('input')).every(i => i.disabled),
    enabled: Array.from(r.querySelectorAll('input')).every(i => !i.disabled),
  })));
  ok('rows 1 to 3 enabled', rowState.slice(0, 3).every(r => r.enabled && !r.hidden));
  ok('rows 4 to 5 hidden AND disabled', rowState.slice(3).every(r => r.hidden && r.disabled));

  await page.click('[data-pm-step-down]');
  ok('stepping down goes back to 2', (await page.textContent('[data-pm-count-value]')).trim() === '2');
  ok('total follows back down', (await totalAttr()) === (unit * 2).toFixed(2), await totalText());

  // Continue must refuse to advance past an invalid step.
  await page.click('[data-pm-next]');
  ok('advanced to step 2', await visible('[data-pm-step="2"]'));
  ok('step counter reads step 2',
     (await page.textContent('[data-pm-stepcount]')).trim() === 'Step 2 of 5');

  await page.click('[data-pm-next]');
  ok('empty required fields block Continue', await visible('[data-pm-step="2"]'));
  ok('a message says why', await visible('[data-pm-status]'));

  await page.fill('#pm-reg-first', 'Browser');
  await page.fill('#pm-reg-last', 'Delegate');
  await page.fill('#pm-reg-org', 'Local Test Ministry of Finance');
  await page.fill('#pm-reg-email', EMAIL);
  await page.fill('#pm-reg-phone', 'not-a-phone');
  await page.fill('#pm-reg-country', 'Kenya');
  await page.fill('#pm-reg-address', '1 Test Street, Nairobi');

  await page.click('[data-pm-next]');
  ok('a bad phone number blocks Continue', await visible('[data-pm-step="2"]'));

  await page.fill('#pm-reg-phone', '+254700000002');
  await page.click('[data-pm-next]');
  ok('valid step 2 advances to step 3', await visible('[data-pm-step="3"]'));

  const d0first = await page.inputValue('#pm-reg-d0-first');
  ok('delegate 1 prefilled from the billing contact', d0first === 'Browser', d0first);

  await page.fill('#pm-reg-d1-first', 'Second');
  await page.fill('#pm-reg-d1-last', 'Delegate');
  await page.click('[data-pm-next]');
  ok('valid step 3 advances to step 4', await visible('[data-pm-step="4"]'));
  ok('review row shows 2 delegates',
     (await page.textContent('[data-pm-review-count]')).trim() === '2');
  ok('review total equals the summary total',
     (await page.textContent('[data-pm-review-total]')).trim() === (await totalText()).trim());
  ok('Continue is gone on the last step', !(await visible('[data-pm-next]')));

  // Back navigation.
  await page.click('[data-pm-back]');
  ok('Back returns to step 3', await visible('[data-pm-step="3"]'));
  await page.click('[data-pm-progress="4"]');
  ok('the progress rule navigates', await visible('[data-pm-step="4"]'));

  await page.evaluate(() => {
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.__pmGtag = (window.__pmGtag || []); window.__pmGtag.push(Array.from(arguments)); };
  });

  // Routed rather than read off the Response: Chromium will not hand back a body
  // the page itself already consumed.
  let raw = null;
  await page.route('**/process-registration.php', async route => {
    const resp = await route.fetch();
    raw = await resp.text();
    await route.fulfill({ response: resp, body: raw });
  });

  await page.check('#pm-reg-consent');
  await page.click('[data-pm-submit]');
  await page.waitForFunction(() => !document.querySelector('[data-pm-done]').hidden, null, { timeout: 10000 });
  const json = JSON.parse(raw);
  ok('handler returned success', json.success === true, JSON.stringify(json).slice(0, 120));
  ok('the page did not navigate away', page.url().includes('event-registration.php'), page.url());

  ok('confirmation panel shown', await visible('[data-pm-done]'));
  ok('the form is gone', !(await visible('[data-pm-register]')));
  ok('confirmation shows the real invoice number',
     (await page.textContent('[data-pm-done-invoice]')).trim() === json.invoice_number,
     await page.textContent('[data-pm-done-invoice]'));
  ok('confirmation amount equals the stored total',
     (await page.textContent('[data-pm-done-total]')).trim() ===
       json.currency_code + ' ' + Number(json.total_amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/, ','),
     await page.textContent('[data-pm-done-total]'));
  ok('confirmation delegate count is 2',
     (await page.textContent('[data-pm-done-count]')).trim() === '2');

  const gtagCalls = await page.evaluate(() => window.__pmGtag || []);
  const purchase = gtagCalls.find(c => c[0] === 'event' && c[1] === 'purchase');
  ok('GA4 purchase fired once', gtagCalls.filter(c => c[1] === 'purchase').length === 1);
  ok('purchase transaction_id is the invoice number',
     purchase && purchase[2].transaction_id === json.invoice_number);
  ok('purchase value is the stored total',
     purchase && Number(purchase[2].value) === Number(json.total_amount),
     purchase && purchase[2].value);
  ok('purchase currency', purchase && purchase[2].currency === json.currency_code);
  ok('purchase items carry item_id and price',
     purchase && purchase[2].items[0].item_id && Number(purchase[2].items[0].price) > 0);
  ok('purchase quantity is the delegate count', purchase && purchase[2].items[0].quantity === 2);

  ok('no page error across the whole flow', errors.length === 0, errors.join(' | '));

  // ── 2. JavaScript OFF ────────────────────────────────────────────────────
  const noJs = await browser.newContext({ javaScriptEnabled: false });
  const p2 = await noJs.newPage();
  await p2.goto(`${BASE}/event-registration.php?id=${EVENT}`);

  ok('no JS: all four steps visible',
     (await p2.locator('.pm-reg__panel:visible').count()) === 4);
  ok('no JS: five delegate rows visible',
     (await p2.locator('[data-pm-delegate]:visible').count()) === 5);
  ok('no JS: the submit button is visible', await p2.locator('[data-pm-submit]').isVisible());
  ok('no JS: the stepper is not shown', !(await p2.locator('[data-pm-stepper]').isVisible()));
  ok('no JS: Continue is not shown', !(await p2.locator('[data-pm-next]').isVisible()));
  ok('no JS: the confirmation is not shown', !(await p2.locator('[data-pm-done]').isVisible()));
  ok('no JS: the invoice total still shows a real figure',
     (await p2.textContent('[data-pm-invoice-total]')).trim().length > 3,
     await p2.textContent('[data-pm-invoice-total]'));

  // Counters, with JS off, are the numbers themselves.
  await p2.goto(`${BASE}/index.php`);
  const idxStats = await p2.$$eval('[data-pm-count]', els => els.map(e => e.textContent.trim()));
  ok('no JS: homepage counters show real figures, never 0',
     idxStats.length === 8 && idxStats.includes('875') && idxStats.includes('25') && !idxStats.includes('0'),
     idxStats.join(','));

  await p2.goto(`${BASE}/about.php`);
  const abtStats = await p2.$$eval('[data-pm-count]', els => els.map(e => e.textContent.trim()));
  ok('no JS: about counters show real figures, never 0',
     abtStats.length === 4 && abtStats.includes('875') && !abtStats.includes('0'),
     abtStats.join(','));

  // ── 3. Counters WITH JavaScript, and with reduced motion ────────────────
  const p3 = await browser.newPage();
  await p3.goto(`${BASE}/about.php`);
  await p3.locator('[data-pm-count]').first().scrollIntoViewIfNeeded();
  await p3.waitForTimeout(1800);
  const settled = await p3.$$eval('[data-pm-count]', els => els.map(e => e.textContent.trim()));
  ok('counters settle on the real figures', settled.includes('875') && settled.includes('25'), settled.join(','));

  const reduced = await browser.newContext({ reducedMotion: 'reduce' });
  const p4 = await reduced.newPage();
  await p4.goto(`${BASE}/about.php`);
  const immediate = await p4.$$eval('[data-pm-count]', els => els.map(e => e.textContent.trim()));
  ok('reduced motion shows the final values immediately',
     immediate.includes('875') && !immediate.includes('0'), immediate.join(','));

  // A thrown counter script must not cost the page its figures.
  const p5 = await browser.newPage();
  await p5.route('**/assets/js/pm-layout.js', r => r.fulfill({ status: 200, contentType: 'application/javascript', body: 'throw new Error("boom");' }));
  await p5.goto(`${BASE}/about.php`);
  const broken = await p5.$$eval('[data-pm-count]', els => els.map(e => e.textContent.trim()));
  ok('a broken counter script leaves the real figures', broken.includes('875'), broken.join(','));

  // A missing flow script must leave a usable, postable form.
  const p6 = await browser.newPage();
  await p6.route('**/assets/js/pm-register.js', r => r.fulfill({ status: 404, body: '' }));
  await p6.goto(`${BASE}/event-registration.php?id=${EVENT}`, { waitUntil: 'networkidle' });
  ok('missing flow script: no data-pm-steps',
     (await p6.getAttribute('html', 'data-pm-steps')) === null);
  ok('missing flow script: all four steps still visible',
     (await p6.locator('.pm-reg__panel:visible').count()) === 4);
  ok('missing flow script: the submit button is still there',
     await p6.locator('[data-pm-submit]').isVisible());
  ok('missing flow script: the confirmation stays hidden',
     !(await p6.locator('[data-pm-done]').isVisible()));

  await browser.close();
  console.log(failures === 0 ? '\nALL BROWSER CHECKS PASSED' : `\n${failures} FAILED`);
  process.exit(failures === 0 ? 0 : 1);
})().catch(e => { console.error('HARNESS ERROR:', e); process.exit(2); });
