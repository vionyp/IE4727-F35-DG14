// Modern Enhancement: browser tests for the Vue screens, run in headless Microsoft Edge.
//   node tools/vue-browser-test.mjs            (all groups)
//   node tools/vue-browser-test.mjs search     (one group: search, loans or rooms)
// Needs Node 22+ (built-in fetch and WebSocket), Edge, Apache and MySQL with the seed data.
// It drives the real pages through the Chrome DevTools Protocol: types with real key events,
// counts requests to api/, blocks the Vue file or an endpoint to test the fallbacks, turns
// JavaScript off, and fails any test that logs a console error. No packages to install.
// The loan tests renew and return books: re-import the seed afterwards for a clean demo.
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync, existsSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const SITE = 'http://localhost/booknest/';
const PORT = 9341;
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
const only = process.argv[2] || '';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const results = [];

// Records and prints one result.
function check(id, name, pass, actual) {
  results.push([id, name, !!pass, actual]);
  console.log(`${id.padEnd(5)} ${pass ? 'PASS' : 'FAIL'} ${name.slice(0, 60).padEnd(60)} ${String(actual).slice(0, 130)}`);
}

// One browser tab in its own cookie jar (a fresh browser context), driven over CDP.
class Tab {
  static async open(browserWs) {
    const ctx = await browserWs.send('Target.createBrowserContext', { disposeOnDetach: true });
    const { targetId } = await browserWs.send('Target.createTarget', { url: 'about:blank', browserContextId: ctx.browserContextId });
    const tab = new Tab(await Cdp.connect(`ws://127.0.0.1:${PORT}/devtools/page/${targetId}`));
    await tab.cdp.send('Runtime.enable');
    await tab.cdp.send('Page.enable');
    await tab.cdp.send('Network.enable');
    await tab.cdp.send('Log.enable');
    return tab;
  }
  constructor(cdp) {
    this.cdp = cdp;
    this.errors = [];          // JavaScript exceptions and console.error calls
    this.refusals = [];        // the browser's own network log lines for 4xx/5xx answers
    this.requests = [];
    cdp.on('Runtime.exceptionThrown', (p) => this.errors.push(p.exceptionDetails.exception?.description || p.exceptionDetails.text));
    cdp.on('Runtime.consoleAPICalled', (p) => { if (p.type === 'error') this.errors.push(p.args.map((a) => a.value ?? a.description).join(' ')); });
    cdp.on('Log.entryAdded', (p) => {
      if (p.entry.level !== 'error' || /ERR_BLOCKED_BY_CLIENT/.test(p.entry.text)) return;   // blocked on purpose
      // "Failed to load resource: 422" is Chrome reporting an HTTP refusal, not a script error; keep it apart.
      (p.entry.source === 'network' ? this.refusals : this.errors).push(p.entry.text + ' ' + (p.entry.url || ''));
    });
    cdp.on('Network.requestWillBeSent', (p) => this.requests.push({ url: p.request.url, method: p.request.method, at: Date.now() }));
  }
  // Opens a page; options: js (default true), block (URL patterns that fail to load).
  async go(path, { js = true, block = [] } = {}) {
    await this.cdp.send('Network.setBlockedURLs', { urls: block });
    await this.cdp.send('Emulation.setScriptExecutionDisabled', { value: !js });
    await this.cdp.send('Page.navigate', { url: SITE + path });
    await this.waitFor('document.readyState === "complete"', 15000, !js);
    await sleep(300);
  }
  // Evaluates an expression in the page and returns its value.
  async eval(expression) {
    const r = await this.cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  }
  // Waits until an expression is truthy (polling), or gives up after the timeout.
  async waitFor(expression, timeout = 5000, quiet = false) {
    const end = Date.now() + timeout;
    while (Date.now() < end) {
      try { if (await this.eval(expression)) return true; } catch (e) { if (!quiet) { /* page still loading */ } }
      await sleep(80);
    }
    return false;
  }
  // Reads the page HTML through the DOM domain (works with JavaScript turned off).
  async html() {
    const doc = await this.cdp.send('DOM.getDocument', { depth: -1 });
    return (await this.cdp.send('DOM.getOuterHTML', { nodeId: doc.root.nodeId })).outerHTML;
  }
  // Focuses an element and types text with real key events, one character every delay ms.
  async type(selector, text, delay = 60) {
    await this.eval(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.focus(); el.select && el.select(); })()`);
    for (const ch of text) {
      await this.cdp.send('Input.insertText', { text: ch });
      await sleep(delay);
    }
  }
  // Presses one key (Enter, Escape, Tab...).
  async key(key) {
    const codes = { Enter: 13, Escape: 27, Tab: 9, Backspace: 8 };
    for (const type of ['keyDown', 'keyUp']) {
      await this.cdp.send('Input.dispatchKeyEvent', { type, key, code: key, windowsVirtualKeyCode: codes[key] || 0, text: key === 'Enter' ? '\r' : undefined });
    }
  }
  // Clicks the centre of the first element matching the selector, with a real mouse event.
  async click(selector) {
    return this.clickEl(`document.querySelector(${JSON.stringify(selector)})`);
  }
  // Clicks the element that a JavaScript expression returns, with a real mouse event.
  async clickEl(expression) {
    // The site scrolls smoothly (and focus changes scroll too), so jump instantly, wait two frames and
    // only click once the element really is the one under the pointer; retry while the page settles.
    let box = null;
    for (let i = 0; i < 20 && !box; i++) {
      box = await this.eval(`new Promise((done) => { const el = ${expression}; if (!el) { done(null); return; }
        el.scrollIntoView({ block: 'center', behavior: 'instant' });
        requestAnimationFrame(() => requestAnimationFrame(() => { const r = el.getBoundingClientRect();
          const x = r.left + r.width / 2, y = r.top + r.height / 2, hit = document.elementFromPoint(x, y);
          done(hit && (hit === el || el.contains(hit)) ? { x, y } : null); })); })`);
      if (!box) await sleep(100);
    }
    if (!box) throw new Error('Nothing to click: ' + expression);
    for (const type of ['mousePressed', 'mouseReleased']) {
      await this.cdp.send('Input.dispatchMouseEvent', { type, x: box.x, y: box.y, button: 'left', clickCount: 1 });
    }
  }
  // Signs in through the real sign in form.
  async login(email, password) {
    await this.go('sign-in.php');
    await this.eval(`(() => { document.getElementById('li-email').value = ${JSON.stringify(email)};
      document.getElementById('li-password').value = ${JSON.stringify(password)}; document.getElementById('login').submit(); })()`);
    await sleep(1500);
    return this.waitFor('!!document.querySelector(".account-link") && !location.pathname.endsWith("sign-in.php")', 8000);
  }
  // Counts the requests made to an api/ path since a given time.
  apiCalls(path, since = 0) {
    return this.requests.filter((r) => r.url.includes('/api/' + path) && r.at >= since);
  }
  // Saves a screenshot when the SHOTS environment variable names a folder (for checking the layout by eye).
  async shot(name, width = 1280) {
    if (!process.env.SHOTS) return;
    await this.cdp.send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 600 });
    await sleep(300);
    const img = await this.cdp.send('Page.captureScreenshot', { format: 'png' });
    writeFileSync(join(process.env.SHOTS, name + '.png'), Buffer.from(img.data, 'base64'));
    await this.cdp.send('Emulation.clearDeviceMetricsOverride');
  }
  async close() { this.cdp.close(); }
}

// A minimal Chrome DevTools Protocol client over the built-in WebSocket.
class Cdp {
  static async connect(url) {
    const ws = new WebSocket(url);
    await new Promise((resolve, reject) => { ws.addEventListener('open', resolve); ws.addEventListener('error', reject); });
    return new Cdp(ws);
  }
  constructor(ws) {
    this.ws = ws; this.id = 0; this.waiting = new Map(); this.handlers = {};
    ws.addEventListener('message', (m) => {
      const d = JSON.parse(m.data);
      if (d.id && this.waiting.has(d.id)) {
        const { resolve, reject } = this.waiting.get(d.id);
        this.waiting.delete(d.id);
        d.error ? reject(new Error(d.error.message)) : resolve(d.result);
      } else if (d.method && this.handlers[d.method]) {
        this.handlers[d.method].forEach((h) => h(d.params));
      }
    });
  }
  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = ++this.id;
      this.waiting.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
  on(method, fn) { (this.handlers[method] ||= []).push(fn); }
  close() { this.ws.close(); }
}

// ---- 1. Live search on Browse ------------------------------------------------------------------------
async function searchTests(browser) {
  const t = await Tab.open(browser);
  await t.go('catalogue.php');
  const mounted = await t.waitFor('!!document.querySelector(".vs .card")', 8000);
  const state = await t.eval(`({ cards: document.querySelectorAll('.vs .card').length,
    baseHidden: document.querySelector('[data-filter-grid]').hidden && document.querySelector('.toolbar').hidden,
    vue: window.Vue && Vue.version, count: document.querySelector('.vs-count').textContent })`);
  await t.shot('search-desktop');
  await t.shot('search-phone', 390);
  check('B01', 'Search: Vue mounts and replaces the Base grid', mounted && state.cards === 42 && state.baseHidden,
    `Vue ${state.vue}, ${state.cards} cards, Base grid hidden: ${state.baseHidden}, "${state.count}"`);

  let since = Date.now();
  await t.type('#vs-q', 'hob', 60);
  await sleep(150);
  const during = t.apiCalls('books.php', since).length;
  await t.waitFor('document.querySelectorAll(".vs .card").length === 1', 3000);
  const after = t.apiCalls('books.php', since).length;
  const titles = await t.eval('[...document.querySelectorAll(".vs .card-title")].map(x => x.textContent)');
  check('B02', 'Search: 300 ms debounce, one request for "hob"', during === 0 && after === 1 && titles[0] === 'The Hobbit',
    `requests while typing: ${during}, after the pause: ${after}; result: ${titles.join(', ')}`);

  const url = await t.eval('location.search');
  const h1 = await t.eval('document.querySelector(".page-head h1").textContent');
  check('B03', 'Search: address and heading follow the search', url === '?q=hob' && h1 === 'Results for "hob"', `address "${url}", heading "${h1}"`);

  await t.eval(`(() => { const i = document.getElementById('vs-q'); i.select(); })()`);
  await t.key('Backspace');
  await t.waitFor('document.querySelectorAll(".vs .card").length === 42', 3000);
  since = Date.now();
  await t.click('.vs .chips button:nth-child(2)');
  await sleep(400);
  const cat = await t.eval(`({ chip: document.querySelector('.vs .chips .is-active').textContent, cards: document.querySelectorAll('.vs .card').length })`);
  const catCount = await (await fetch(SITE + 'api/books.php')).json().then((d) => d.books.filter((b) => b.categoryName === cat.chip).length);
  check('B04', 'Search: category filter is computed, no new request', cat.cards === catCount && cat.cards < 42 && t.apiCalls('books.php', since).length === 0,
    `"${cat.chip}": ${cat.cards} cards (expected ${catCount}), requests: ${t.apiCalls('books.php', since).length}`);

  await t.click('.vs .chips button:nth-child(1)');
  await t.click('.vs-avail input');
  await sleep(300);
  const avail = await t.eval(`[...document.querySelectorAll('.vs .card .card-avail')].map(x => x.textContent)`);
  check('B05', 'Search: "On the shelf now only" keeps only available books', avail.length > 0 && avail.length < 42 && avail.every((x) => x === 'Available to borrow'),
    `${avail.length} cards, all "Available to borrow": ${avail.every((x) => x === 'Available to borrow')}`);
  await t.click('.vs-avail input');

  await t.type('#vs-q', 'zzqx', 30);
  await t.waitFor('!!document.querySelector(".vs .empty-state")', 3000);
  const empty = await t.eval(`({ text: document.querySelector('.vs .empty-state h2')?.textContent, count: document.querySelector('.vs-count').textContent })`);
  await t.click('.vs .empty-state button');
  const cleared = await t.waitFor('document.querySelectorAll(".vs .card").length === 42 && document.getElementById("vs-q").value === ""', 3000);
  check('B06', 'Search: empty state, then "Clear" brings every book back', empty.text === 'Nothing matches' && cleared, `"${empty.text}", "${empty.count}", cleared: ${cleared}`);

  since = Date.now();
  await t.type('#vs-q', 'dune', 0);
  await t.key('Enter');
  await sleep(120);
  const enterCalls = t.apiCalls('books.php', since).length;
  await t.waitFor('document.querySelectorAll(".vs .card").length === 1', 3000);
  check('B07', 'Search: Enter searches at once without waiting', enterCalls >= 1, `${enterCalls} request(s) within 120 ms of Enter`);
  check('B08', 'Search: no console errors', t.errors.length === 0, t.errors.length ? t.errors.join(' | ') : 'none');
  await t.close();

  const off = await Tab.open(browser);
  await off.go('catalogue.php?q=hobbit', { js: false });
  const html = await off.html();
  check('B09', 'Search: JavaScript off, the PHP search still works', /<article class="card"/.test(html) && html.includes('The Hobbit') && !html.includes('class="vs"'),
    `${(html.match(/<article class="card"/g) || []).length} PHP card(s), Vue markup: ${html.includes('class="vs"')}`);
  await off.close();

  const noVue = await Tab.open(browser);
  await noVue.go('catalogue.php', { block: ['*vue.global.prod.js*'] });
  await sleep(800);
  const nv = await noVue.eval(`({ vs: !!document.querySelector('.vs'), grid: !document.querySelector('[data-filter-grid]').hidden,
    cards: document.querySelectorAll('[data-filter-grid] .card').length, refine: !document.querySelector('[data-refine]').hidden })`);
  check('B10', 'Search: Vue blocked, the Base page stays (refine box works)', !nv.vs && nv.grid && nv.cards === 42 && nv.refine,
    `Vue app: ${nv.vs}, Base grid shown: ${nv.grid} (${nv.cards} cards), refine box shown: ${nv.refine}`);
  await noVue.close();

  const noApi = await Tab.open(browser);
  await noApi.go('catalogue.php', { block: ['*api/books.php*'] });
  await sleep(1200);
  const na = await noApi.eval(`({ vs: !!document.querySelector('.vs'), grid: !document.querySelector('[data-filter-grid]').hidden })`);
  check('B11', 'Search: api/ unreachable, Vue steps back and the Base page stays', !na.vs && na.grid, `Vue app: ${na.vs}, Base grid shown: ${na.grid}`);
  await noApi.close();
}

// ---- 2. My Loans panel on My Account --------------------------------------------------------------
// Returns a JS expression for budy's loan row with this title in the Vue panel.
const row = (title) => `[...document.querySelectorAll('.vl .loan-item')].find(li => li.querySelector('h3 a').textContent === ${JSON.stringify(title)})`;
// Reads the three totals, the header badge and the "Books on loan" tile.
const totals = `({ items: document.querySelectorAll('.vl .loan-item').length,
  summary: [...document.querySelectorAll('.vl-summary li')].map(li => li.textContent.trim().replace(/\\s+/g, ' ')),
  badge: document.querySelector('.loans-link .badge').textContent,
  tile: [...document.querySelectorAll('.tile')].find(t => t.querySelector('.tile-label').textContent === 'Books on loan').querySelector('.tile-value').textContent,
  feeTile: [...document.querySelectorAll('.tile')].find(t => t.querySelector('.tile-label').textContent === 'Late fees owed').querySelector('.tile-value').textContent,
  blocks: document.querySelector('.vl .notice-alert')?.textContent || '' })`;

async function loansTests(browser) {
  // A second tab, signed in as the same member, opened first so that it goes stale.
  const stale = await Tab.open(browser);
  await stale.login('budy@gmail.com', 'budy123!');
  await stale.go('account.php');
  await stale.waitFor('!!document.querySelector(".vl .loan-item")', 8000);

  const t = await Tab.open(browser);
  const signedIn = await t.login('budy@gmail.com', 'budy123!');
  await t.go('account.php');
  const mounted = await t.waitFor('!!document.querySelector(".vl .loan-item")', 8000);
  await t.eval(`document.getElementById('loans').scrollIntoView({ behavior: 'instant' })`);
  await t.shot('loans-desktop');
  await t.shot('loans-phone', 390);
  let s = await t.eval(totals);
  const baseHidden = await t.eval(`[...document.querySelectorAll('#loans > [data-vue-replaced]')].every(el => el.hidden) && document.querySelectorAll('#loans > [data-vue-replaced]').length > 0`);
  check('C01', 'Loans: panel mounts with computed totals', signedIn && mounted && baseHidden && s.items === 6 && s.summary[0] === '6 of 6 loans'
    && s.summary[1] === 'S$8.50 late fees owed' && s.summary[2] === '1 due soon',
    `${s.items} loans; ${s.summary.join(' | ')}; Base list hidden: ${baseHidden}`);

  await t.eval('window.__samePage = "yes"');
  let since = Date.now();
  await t.clickEl(`${row('The Hobbit')}.querySelector('button.btn-secondary')`);
  await t.waitFor(`(document.querySelector('.vl-message')?.textContent || '').startsWith('Renewed The Hobbit')`, 5000);
  const hob = await t.eval(`({ text: ${row('The Hobbit')}.querySelector('.loan-body p').textContent.replace(/\\s+/g, ' ').trim(),
    renewBtn: !!${row('The Hobbit')}.querySelector('button.btn-secondary'), msg: document.querySelector('.vl-message').textContent.trim(),
    focus: document.activeElement === document.querySelector('.vl-message'), same: window.__samePage })`);
  check('C02', 'Loans: Renew updates the row in place, no reload', hob.same === 'yes' && hob.text.includes('Renewed once.') && !hob.renewBtn
    && t.apiCalls('loans.php', since).filter((r) => r.method === 'POST').length === 1 && hob.focus,
    `"${hob.msg}" | row: "${hob.text.slice(0, 70)}..." | Renew button gone: ${!hob.renewBtn}, focus on message: ${hob.focus}`);

  await t.clickEl(`[...${row('Atomic Habits')}.querySelectorAll('button')].find(b => b.textContent.trim() === 'Return')`);
  const yesFocused = await t.waitFor(`(document.activeElement.id || '').startsWith('vl-yes-')`, 2000);
  await t.clickEl(`${row('Atomic Habits')}.querySelector('[id^="vl-yes-"]')`);
  const leaving = await t.waitFor(`!!document.querySelector('.vl .loan-item.loan-leave-active')`, 3000);
  await t.waitFor(`!${row('Atomic Habits')}`, 3000);
  s = await t.eval(totals);
  const same = await t.eval('window.__samePage');
  const msg = await t.eval(`document.querySelector('.vl-message').textContent.trim()`);
  check('C03', 'Loans: Return asks first, row leaves with <transition-group>', yesFocused && leaving && same === 'yes' && s.items === 5
    && msg === 'Returned Atomic Habits. Thank you for bringing it back on time.',
    `confirm focused: ${yesFocused}, leave transition seen: ${leaving}, rows now ${s.items}, "${msg}"`);
  check('C04', 'Loans: totals, header badge, tiles and pause reasons follow', s.summary[0] === '5 of 6 loans' && s.badge === '5' && s.tile === '5'
    && s.feeTile === 'S$8.50' && !s.blocks.includes('books on loan'),
    `${s.summary.join(' | ')}; badge ${s.badge}, tile ${s.tile}, fee tile ${s.feeTile}; "6 books" reason gone: ${!s.blocks.includes('books on loan')}`);

  await stale.clickEl(`${row('The Hobbit')}.querySelector('button.btn-secondary')`);
  await stale.waitFor(`!!document.querySelector('.vl-message')`, 5000);
  await stale.waitFor(`!${row('Atomic Habits')}`, 3000);   // its leave transition takes 0.35 s
  const st = await stale.eval(`({ msg: document.querySelector('.vl-message').textContent.trim(), cls: document.querySelector('.vl-message').className,
    renew: !!${row('The Hobbit')}.querySelector('button.btn-secondary'), atomic: !!${row('Atomic Habits')} })`);
  check('C05', 'Loans: server refusal shown as sent (stale second tab)', st.msg === 'Could not renew The Hobbit. It has already been renewed once, the most allowed.'
    && st.cls.includes('notice-alert') && !st.renew && !st.atomic, `"${st.msg}"; list refreshed: Hobbit renew gone ${!st.renew}, Atomic Habits gone ${!st.atomic}`);
  check('C06', 'Loans: no JavaScript errors (both tabs)', t.errors.length === 0 && stale.errors.length === 0, [...t.errors, ...stale.errors].join(' | ') || 'none');
  await stale.close();

  await t.eval(`document.querySelectorAll('input[name="csrf"]').forEach(i => { i.value = 'expired'; })`);
  const eduBefore = await t.eval(`${row('Educated')}.querySelector('.loan-body p').textContent`);
  await t.clickEl(`${row('Educated')}.querySelector('button.btn-secondary')`);
  await t.waitFor(`(document.querySelector('.vl-message')?.textContent || '').includes('session expired')`, 5000);
  const ex = await t.eval(`({ msg: document.querySelector('.vl-message').textContent.trim(), p: ${row('Educated')}.querySelector('.loan-body p').textContent,
    renew: !!${row('Educated')}.querySelector('button.btn-secondary') })`);
  check('C07', 'Loans: expired CSRF token, PHP refuses and nothing changes', ex.msg.startsWith('Your session expired before the form was sent') && ex.p === eduBefore && ex.renew,
    `"${ex.msg}"; Educated unchanged: ${ex.p === eduBefore}`);

  await t.cdp.send('Network.deleteCookies', { name: 'bn_session', url: SITE });
  await t.clickEl(`[...${row('Educated')}.querySelectorAll('button')].find(b => b.textContent.trim() === 'Return')`);
  await t.clickEl(`${row('Educated')}.querySelector('[id^="vl-yes-"]')`);
  await t.waitFor(`(document.querySelector('.vl-message')?.textContent || '').includes('sign in')`, 5000);
  const out = await t.eval(`({ msg: document.querySelector('.vl-message').textContent.trim(), link: document.querySelector('.vl-message a')?.getAttribute('href'),
    still: !!${row('Educated')} })`);
  check('C08', 'Loans: signed out meanwhile, PHP answers 401 and the row stays', out.msg.startsWith('Please sign in again') && out.link === '/booknest/sign-in.php' && out.still,
    `"${out.msg}", link ${out.link}, Educated still listed: ${out.still}`);
  check('C09', 'Loans: no JavaScript errors after the refusals', t.errors.length === 0, t.errors.join(' | ') || 'none');
  const refused = [...t.refusals].map((x) => (x.match(/status of (\d+)/) || [])[1] + ' ' + (x.includes('/api/loans.php') ? 'api/loans.php' : x));
  check('C09b', 'Loans: only the expected refusals reached the network log', refused.length === 2 && refused[0] === '403 api/loans.php' && refused[1] === '401 api/loans.php',
    refused.join(', ') || 'none');
  await t.close();

  const off = await Tab.open(browser);
  await off.login('budy@gmail.com', 'budy123!');
  await off.go('account.php', { js: false });
  const html = await off.html();
  check('C10', 'Loans: JavaScript off, the PHP list and forms work', !html.includes('class="vl"') && (html.match(/<li class="loan-item/g) || []).length >= 4
    && html.includes('value="renew"') && html.includes('value="return"'), `${(html.match(/<li class="loan-item/g) || []).length} PHP rows with renew and return forms, no Vue markup`);
  await off.close();

  const noVue = await Tab.open(browser);
  await noVue.login('budy@gmail.com', 'budy123!');
  await noVue.go('account.php', { block: ['*vue.global.prod.js*'] });
  await sleep(600);
  const nv = await noVue.eval(`({ vl: !!document.querySelector('.vl'), rows: document.querySelectorAll('#loans .loan-item').length })`);
  await noVue.clickEl(`[...document.querySelectorAll('#loans .loan-item')].find(li => li.querySelector('h3 a').textContent === 'Deep Work').querySelector('button[type="submit"]')`);
  await noVue.waitFor(`!!document.querySelector('.flash')`, 6000);
  const flash = await noVue.eval(`document.querySelector('.flash p')?.textContent || ''`);
  check('C11', 'Loans: Vue blocked, the Base Renew form still works', !nv.vl && nv.rows >= 4 && flash.startsWith('Renewed Deep Work'), `Vue app: ${nv.vl}, ${nv.rows} PHP rows; after the Base form: "${flash.slice(0, 60)}..."`);
  await noVue.close();
}

// ---- 3. Study room slot grid ----------------------------------------------------------------------
const quota = `(document.querySelector('.vr-quota')?.textContent || '').replace(/\\s+/g, ' ').trim()`;

async function roomsTests(browser) {
  const v = await Tab.open(browser);
  await v.go('rooms.php');
  const mounted = await v.waitFor('document.querySelectorAll(".vr-grid li").length === 22', 8000);
  const vs = await v.eval(`({ cells: document.querySelectorAll('.vr-grid li').length, baseHidden: document.getElementById('timetable').hidden
    && document.querySelector('.availability-wrap').hidden, quota: ${quota}, title: document.getElementById('vr-title').textContent })`);
  await v.eval(`document.querySelector('.vr').scrollIntoView({ behavior: 'instant' })`);
  await v.shot('rooms-desktop');
  await v.shot('rooms-phone', 390);
  check('D01', 'Rooms: grid mounts in place of the arc timetable', mounted && vs.cells === 22 && vs.baseHidden && vs.quota.startsWith('Viewing is open to everyone'),
    `${vs.cells} cells, Base timetable hidden: ${vs.baseHidden}, "${vs.title}", "${vs.quota.slice(0, 40)}..."`);
  await v.clickEl(`document.querySelector('.vr-grid a.slot-free')`);
  await v.waitFor('location.search.includes("start=")', 5000);
  const vurl = await v.eval('location.pathname + location.search');
  check('D02', 'Rooms: visitor clicking a free cell follows the Base link', /rooms\.php\?date=.*&room=\d+&start=\d\d%3A\d\d/.test(vurl), vurl);
  check('D03', 'Rooms: no JavaScript errors (visitor)', v.errors.length === 0, v.errors.join(' | ') || 'none');
  await v.close();

  const t = await Tab.open(browser);
  await t.login('aisha@localhost', 'Member123!');
  await t.go('rooms.php');
  await t.waitFor('document.querySelectorAll(".vr-grid li").length === 22', 8000);
  // Pick a day (not today) where aisha has nothing booked, asking the same api the grid uses.
  const day = await t.eval(`(async () => { for (const opt of [...document.querySelectorAll('#vr-date option')].slice(1)) {
      const r = await fetch('/booknest/api/rooms.php?date=' + opt.value, { credentials: 'same-origin' }).then(x => x.json());
      if (r.minutesUsed === 0) return opt.value; } return ''; })()`);
  // Changing a v-model select: set the value and fire 'change', exactly what a person's choice does.
  const choose = (id, value) => t.eval(`(() => { const s = document.getElementById(${JSON.stringify(id)}); s.value = ${JSON.stringify(String(value))};
    s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  let since = Date.now();
  await choose('vr-date', day);
  await t.waitFor(`document.getElementById('vr-date').value === ${JSON.stringify(day)} && !document.querySelector('.vr-grid.is-loading')`, 5000);
  await sleep(300);
  const dateCalls = t.apiCalls('rooms.php', since).map((r) => new URL(r.url).searchParams.get('date'));
  since = Date.now();
  await choose('vr-room', 12);
  await t.waitFor(`document.getElementById('vr-title').textContent.startsWith('Timetable: A12')`, 5000);
  await sleep(300);
  const roomCalls = t.apiCalls('rooms.php', since).map((r) => new URL(r.url).searchParams.get('room'));
  check('D04', 'Rooms: watch refetches once when the date or room changes', dateCalls.length === 1 && dateCalls[0] === day && roomCalls.length === 1 && roomCalls[0] === '12',
    `date change -> ${dateCalls.length} request (date=${dateCalls[0]}), room change -> ${roomCalls.length} request (room=${roomCalls[0]})`);

  const q0 = await t.eval(quota);
  await t.clickEl(`document.querySelectorAll('.vr-grid a.slot-free')[2]`);
  await sleep(400);
  const picked = await t.eval(`({ quota: ${quota}, chosen: document.querySelector('.vr-grid .is-selected')?.textContent.trim(),
    current: document.querySelector('.vr-grid .is-selected')?.getAttribute('aria-current'),
    form: [document.getElementById('bk-room').value, document.getElementById('bk-date').value, document.getElementById('bk-start').value, document.getElementById('bk-duration').value],
    summary: document.querySelector('[data-booking-summary]').textContent, focus: document.activeElement.id })`);
  check('D05', 'Rooms: <slot-cell> emits pick; the Base form is filled', picked.chosen?.endsWith('Chosen') && picked.current === 'true' && picked.form[0] === '12'
    && picked.form[1] === day && /^\d\d:\d\d$/.test(picked.form[2]) && picked.summary.includes('A12') && picked.focus === 'bk-duration',
    `cell "${picked.chosen}", form = room ${picked.form[0]}, ${picked.form[1]}, ${picked.form[2]}, ${picked.form[3]} min; rooms.js summary: "${picked.summary.slice(0, 50)}..."`);
  await choose('vr-duration', 30);
  await sleep(200);
  const q30 = await t.eval(`({ quota: ${quota}, form: document.getElementById('bk-duration').value })`);
  check('D06', 'Rooms: computed minutes left follow the choice and duration', q0.startsWith('60 of 60 minutes left') && picked.quota.includes('leaves 0 minutes')
    && q30.quota.includes('leaves 30 minutes') && q30.form === '30', `before: "${q0.slice(0, 30)}" | 60 min: "...${picked.quota.slice(-26)}" | 30 min: "...${q30.quota.slice(-27)}", form duration ${q30.form}`);
  await choose('vr-duration', 60);

  await t.clickEl(`document.querySelector('#booking button[type="submit"]')`);
  await t.waitFor(`!!document.querySelector('.flash')`, 8000);
  const booked = await t.eval(`document.querySelector('.flash p')?.textContent || ''`);
  await t.waitFor('document.querySelectorAll(".vr-grid li").length === 22', 8000);
  const afterBook = await t.eval(`({ yours: document.querySelectorAll('.vr-grid .slot-yours').length, quota: ${quota}, room: document.getElementById('vr-room').value, date: document.getElementById('vr-date').value })`);
  check('D07', 'Rooms: booking through the Base form, grid then shows Yours', booked.startsWith('Booked. A12') && afterBook.yours === 2 && afterBook.quota.startsWith('0 of 60 minutes'),
    `"${booked.slice(0, 50)}..." | grid: ${afterBook.yours} Yours cells, "${afterBook.quota.slice(0, 30)}"`);

  await t.clickEl(`[...document.querySelectorAll('.vr-grid a.slot-free')].slice(-3)[0]`);
  await sleep(300);
  const over = await t.eval(`({ quota: ${quota}, over: document.querySelector('.vr-quota').classList.contains('is-over') })`);
  await t.eval(`document.getElementById('booking').submit()`);   // skips the browser checks on purpose, like a tampered request
  await t.waitFor(`(document.querySelector('.flash p')?.textContent || '').includes('daily limit')`, 8000);
  const refused = await t.eval(`document.querySelector('.flash p')?.textContent || ''`);
  check('D08', 'Rooms: grid warns over the cap; the server still refuses', over.over && over.quota.includes('would be refused') && refused.includes('The daily limit is 60 minutes'),
    `grid: "...${over.quota.slice(-70)}" | server: "${refused}"`);
  check('D09', 'Rooms: no JavaScript errors (member)', t.errors.length === 0, t.errors.join(' | ') || 'none');
  await t.close();

  const off = await Tab.open(browser);
  await off.go('rooms.php', { js: false });
  const html = await off.html();
  check('D10', 'Rooms: JavaScript off, the PHP timetable is shown', !html.includes('class="vr"') && html.includes('data-availability') && (html.match(/class="slot slot-free"/g) || []).length > 0,
    `${(html.match(/<a class="slot slot-free"/g) || []).length} free links in the PHP table, no Vue markup`);
  await off.close();

  const noVue = await Tab.open(browser);
  await noVue.login('aisha@localhost', 'Member123!');
  await noVue.go('rooms.php', { block: ['*vue.global.prod.js*'] });
  await sleep(600);
  await noVue.clickEl(`document.querySelector('[data-availability] a.slot-free')`);
  await sleep(300);
  const nv = await noVue.eval(`({ vr: !!document.querySelector('.vr'), table: !document.querySelector('.availability-wrap').hidden,
    start: document.getElementById('bk-start').value, room: document.getElementById('bk-room').value })`);
  check('D11', 'Rooms: Vue blocked, the Base table and rooms.js still work', !nv.vr && nv.table && nv.start !== '' && nv.room !== '',
    `Vue app: ${nv.vr}, PHP table shown: ${nv.table}; Base click filled room ${nv.room} at ${nv.start}`);
  await noVue.close();
}

const groups = { search: searchTests, loans: loansTests, rooms: roomsTests };

// Starts Edge, runs the chosen groups, prints a summary and exits with 0 only if all passed.
async function main() {
  if (!EDGE) { console.log('Microsoft Edge not found; browser tests not run.'); process.exit(2); }
  const profile = mkdtempSync(join(tmpdir(), 'bn-vue-'));
  const edge = spawn(EDGE, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--no-first-run',
    '--no-default-browser-check', '--window-size=1280,900', 'about:blank']);
  let version;
  for (let i = 0; i < 60 && !version; i++) {
    try { version = await (await fetch(`http://127.0.0.1:${PORT}/json/version`)).json(); } catch { await sleep(250); }
  }
  const browser = await Cdp.connect(version.webSocketDebuggerUrl);
  console.log(`BookNest Vue browser tests, ${new Date().toISOString()}, ${version.Browser}\n${'-'.repeat(100)}`);
  try {
    for (const [name, run] of Object.entries(groups)) {
      if (!only || only === name) await run(browser);
    }
  } catch (e) {
    check('ERR', 'Test run stopped', false, e.message);
  }
  browser.close();
  edge.kill();
  await sleep(500);
  try { rmSync(profile, { recursive: true, force: true }); } catch { /* Edge may still hold a file */ }
  const passed = results.filter((r) => r[2]).length;
  console.log(`${'-'.repeat(100)}\n${passed} of ${results.length} browser checks passed.`);
  process.exit(passed === results.length ? 0 : 1);
}

main();
