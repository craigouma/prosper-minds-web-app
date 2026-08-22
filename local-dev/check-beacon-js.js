// Behaviour checks for the form_started beacon's client-side half.
//
// The IIFE is EXTRACTED from a rendered event-registration.php, not retyped
// here, so this exercises the code that actually ships. It runs against a
// minimal DOM stub to prove the one thing curl cannot: that the listener fires
// exactly once per page load, posts the right payload, and degrades correctly
// when sendBeacon is missing or refuses the payload.
//
// No dependencies beyond node's built-in vm module. Called from
// local-dev/verify.sh, which skips it when node is not installed.
//
//   curl -s http://127.0.0.1:8080/event-registration.php?id=3 > /tmp/page.html
//   node local-dev/check-beacon-js.js /tmp/page.html

const fs = require('fs');
const html = fs.readFileSync(process.argv[2], 'utf8');

// Extract only the funnel IIFE, by its own marker comment.
const start = html.indexOf('// ── Funnel analytics: "form_started"');
if (start < 0) { console.error('FAIL: funnel IIFE marker not found in rendered page'); process.exit(1); }
const tail = html.slice(start);
const end = tail.indexOf('})();');
if (end < 0) { console.error('FAIL: IIFE end not found'); process.exit(1); }
const code = tail.slice(0, end + 5);

function run(scenario) {
  const listeners = {};
  const sent = [];

  const tokenInput = { value: 'TOKEN123', name: 'csrf_token' };
  const form = {
    addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
    removeEventListener(type, fn) {
      listeners[type] = (listeners[type] || []).filter(f => f !== fn);
    },
    querySelector(sel) { return sel.includes('csrf_token') ? tokenInput : null; },
  };

  class FakeFormData {
    constructor() { this.entries = []; }
    append(k, v) { this.entries.push([k, v]); }
  }

  const sandbox = {
    document: { getElementById: id => (id === 'standaloneRegForm' ? form : null) },
    FormData: FakeFormData,
    navigator: scenario.sendBeacon
      ? { sendBeacon: (url, body) => { sent.push({ via: 'sendBeacon', url, body }); return scenario.beaconOk; } }
      : {},
    fetch: (url, opts) => { sent.push({ via: 'fetch', url, opts }); return Promise.resolve(); },
    console,
  };

  const vm = require('vm');
  vm.createContext(sandbox);
  vm.runInContext(code, sandbox);

  const fire = type => (listeners[type] || []).slice().forEach(fn => fn({ type }));

  // A real visitor: focus a field, then type in it, then focus another field.
  fire('focusin');
  fire('input');
  fire('focusin');
  fire('input');

  return { sent, listeners };
}

let failures = 0;
const ok = (label, cond, extra) => {
  console.log((cond ? '  PASS  ' : '  FAIL  ') + label + (extra !== undefined ? '   ' + extra : ''));
  if (!cond) failures++;
};

// Scenario 1: sendBeacon present and accepts the payload.
let r = run({ sendBeacon: true, beaconOk: true });
ok('fires exactly once across 4 events', r.sent.length === 1, 'sent=' + r.sent.length);
ok('uses navigator.sendBeacon', r.sent[0] && r.sent[0].via === 'sendBeacon');
ok('posts to track-funnel-event.php', r.sent[0] && r.sent[0].url === 'track-funnel-event.php');
const entries = r.sent[0] ? Object.fromEntries(r.sent[0].body.entries) : {};
ok('event_type=form_started', entries.event_type === 'form_started');
ok('event_id sent', /^\d+$/.test(entries.event_id || ''), 'event_id=' + entries.event_id);
ok('csrf_token taken from the form', entries.csrf_token === 'TOKEN123');
ok('both listeners removed after firing',
   (r.listeners.focusin || []).length === 0 && (r.listeners.input || []).length === 0);

// Scenario 2: sendBeacon refuses (queue full / payload limit) -> fetch keepalive.
r = run({ sendBeacon: true, beaconOk: false });
ok('falls back to fetch when sendBeacon returns false',
   r.sent.length === 2 && r.sent[1].via === 'fetch');
ok('fallback fetch uses keepalive',
   r.sent[1] && r.sent[1].opts && r.sent[1].opts.keepalive === true);
ok('fallback fetch is a POST', r.sent[1] && r.sent[1].opts.method === 'POST');

// Scenario 3: no sendBeacon at all (older browser).
r = run({ sendBeacon: false });
ok('uses fetch when sendBeacon is absent',
   r.sent.length === 1 && r.sent[0].via === 'fetch');

console.log(failures === 0 ? '\nALL BEACON JS CHECKS PASSED' : '\n' + failures + ' FAILED');
process.exit(failures === 0 ? 0 : 1);
