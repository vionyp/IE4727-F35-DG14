// Takes the report screenshots in headless Microsoft Edge, with the same CDP setup as tools/vue-browser-test.mjs.
//   node tools/take-screenshots.mjs            (screenshots, then the three test suites into screenshots/tests.txt)
//   node tools/take-screenshots.mjs --no-tests (screenshots only)
// Needs Node 22+, Edge, Apache and MySQL. No app code is changed.
// Database: the script dumps the current booknest database first, imports a fresh seed for the shots
// (and again before each test suite, as docs/MODERN.md asks), and at the end loads the dump back,
// so the database ends exactly as it was before the run.
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, rmSync, existsSync, writeFileSync, readFileSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const OUT = join(ROOT, 'screenshots');
const SITE = 'http://localhost/booknest/';
const PORT = 9342;   // not the test port, so the two never clash
const W = 1440, H = 900;
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
const MYSQL = 'C:/xampp/mysql/bin/mysql.exe', MYSQLDUMP = 'C:/xampp/mysql/bin/mysqldump.exe', PHP = 'C:/xampp/php/php.exe';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const saved = [];

// ---- Database helpers ---------------------------------------------------------------------------------
function mysql(sql, args = []) {
  const r = spawnSync(MYSQL, ['-uroot', '--default-character-set=utf8mb4', ...args], { input: sql, encoding: 'utf8', maxBuffer: 1 << 28 });
  if (r.status !== 0) throw new Error('mysql: ' + r.stderr);
  return r.stdout;
}
const query = (sql) => mysql(sql, ['-N', '-B', 'booknest']).trim();
function freshSeed() {
  mysql(readFileSync(join(ROOT, 'sql/schema.sql'), 'utf8'));
  mysql(readFileSync(join(ROOT, 'sql/seed.sql'), 'utf8'));
}

// ---- A minimal Chrome DevTools Protocol client (same as the browser tests) ---------------------------
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
      } else if (d.method && this.handlers[d.method]) this.handlers[d.method].forEach((h) => h(d.params));
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

// One tab in its own cookie jar, at 1440x900.
class Tab {
  static async open(browserWs) {
    const ctx = await browserWs.send('Target.createBrowserContext', { disposeOnDetach: true });
    const { targetId } = await browserWs.send('Target.createTarget', { url: 'about:blank', browserContextId: ctx.browserContextId });
    const tab = new Tab(await Cdp.connect(`ws://127.0.0.1:${PORT}/devtools/page/${targetId}`));
    await tab.cdp.send('Runtime.enable');
    await tab.cdp.send('Page.enable');
    await tab.cdp.send('Network.enable');
    await tab.cdp.send('Emulation.setDeviceMetricsOverride', { width: W, height: H, deviceScaleFactor: 1, mobile: false });
    // Skip the onboarding tour so it never covers a shot (a cookie, not an app change).
    await tab.cdp.send('Network.setCookie', { name: 'bn_tour', value: 'done', url: SITE });
    return tab;
  }
  constructor(cdp) { this.cdp = cdp; }
  async go(path, { js = true, block = [] } = {}) {
    await this.cdp.send('Network.setBlockedURLs', { urls: block });
    await this.cdp.send('Emulation.setScriptExecutionDisabled', { value: !js });
    await this.cdp.send('Page.navigate', { url: SITE + path });
    await this.waitFor('document.readyState === "complete"', 15000);
    await sleep(400);
  }
  async eval(expression) {
    const r = await this.cdp.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  }
  async waitFor(expression, timeout = 5000) {
    const end = Date.now() + timeout;
    while (Date.now() < end) {
      try { if (await this.eval(expression)) return true; } catch { /* still loading */ }
      await sleep(50);
    }
    return false;
  }
  async type(selector, text, delay = 40) {
    await this.eval(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.focus(); el.select && el.select(); })()`);
    for (const ch of text) { await this.cdp.send('Input.insertText', { text: ch }); await sleep(delay); }
  }
  async clickEl(expression) {
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
  async login(email, password) {
    await this.go('sign-in.php');
    await this.eval(`(() => { document.getElementById('li-email').value = ${JSON.stringify(email)};
      document.getElementById('li-password').value = ${JSON.stringify(password)}; document.getElementById('login').submit(); })()`);
    await sleep(1200);
    if (!await this.waitFor('!!document.querySelector(".account-link") && !location.pathname.endsWith("sign-in.php")', 8000)) throw new Error('Sign in failed: ' + email);
  }
  // Saves a PNG of the area around the given elements (union of their boxes, plus padding), in page coordinates.
  // `viewport: true` crops inside the visible screen instead (for fixed things like the toast).
  async shot(name, selectors, { pad = 24, maxH = 1400, viewport = false, minW = 0 } = {}) {
    // Page shots are taken from the top of the page, so the sticky site header never lands on the crop.
    if (!viewport) { await this.eval('window.scrollTo({ top: 0, behavior: "instant" })'); await sleep(250); }
    const scroll = await this.eval('({ x: scrollX, y: scrollY })');
    const rect = await this.eval(`(() => { const sx = ${viewport} ? 0 : scrollX, sy = ${viewport} ? 0 : scrollY; let r = null;
      for (const s of ${JSON.stringify([].concat(selectors))}) for (const el of document.querySelectorAll(s)) {
        const b = el.getBoundingClientRect(); if (!b.width || !b.height) continue;
        const x = { l: b.left + sx, t: b.top + sy, r: b.right + sx, b: b.bottom + sy };
        r = r ? { l: Math.min(r.l, x.l), t: Math.min(r.t, x.t), r: Math.max(r.r, x.r), b: Math.max(r.b, x.b) } : x; }
      return r; })()`);
    if (!rect) throw new Error(`${name}: nothing found for ${selectors}`);
    let x = Math.max(0, rect.l - pad), y = Math.max(0, rect.t - pad);
    let w = Math.min(W - x, rect.r - rect.l + 2 * pad), h = Math.min(maxH, rect.b - rect.t + 2 * pad);
    if (w < minW) { x = Math.max(0, x - (minW - w)); w = Math.min(W - x, minW); }
    if (viewport) { w = Math.min(w, W - x); h = Math.min(h, H - y); }
    // The clip is in page coordinates; a screen crop is moved by the current scroll.
    const img = await this.cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: !viewport,
      clip: { x: viewport ? x + scroll.x : x, y: viewport ? y + scroll.y : y, width: w, height: h, scale: 1 } });
    const file = join(OUT, name);
    writeFileSync(file, Buffer.from(img.data, 'base64'));
    saved.push(file);
    console.log(`saved ${file} (${Math.round(w)}x${Math.round(h)})`);
  }
  async close() { this.cdp.close(); }
}

const BTN = (scope, text) => `[...document.querySelectorAll('${scope} button')].find(b => b.textContent.trim().replace(/\\s+/g, ' ') === ${JSON.stringify(text)})`;
const top = () => 'window.scrollTo({ top: 0, behavior: "instant" })';

// ---- The shots ------------------------------------------------------------------------------------------
async function shots(browser) {
  // 1. Browse: the same word in the Base page (Vue blocked, refine box) and the Vue page (filter chips).
  const base = await Tab.open(browser);
  await base.go('catalogue.php', { block: ['*vue.global.prod.js*'] });
  await base.waitFor('!document.querySelector("[data-refine]").hidden', 5000);
  await base.type('#refine', 'atomic', 60);
  await sleep(700);
  await base.eval(top());
  await base.shot('browse-base.png', ['main .page-head', 'main nav.chips', 'main .toolbar', '[data-filter-grid] .card:not([hidden])'], { maxH: 860 });
  await base.close();

  const vue = await Tab.open(browser);
  await vue.go('catalogue.php');
  await vue.waitFor('!!document.querySelector(".vs .card")', 8000);
  await vue.type('#vs-q', 'atomic', 60);
  await vue.waitFor('document.querySelectorAll(".vs .card").length < 42 && !!document.querySelector(".vs .card")', 4000);
  await sleep(500);
  await vue.eval(top());
  await vue.shot('browse-vue.png', ['main .page-head', '.vs'], { maxH: 860 });
  await vue.close();

  // 2. Paused: make one of Chloe's loans overdue for a moment, take the shot, put it back.
  const chloeLoan = query(`SELECT l.id, l.due_date, l.status FROM loans l JOIN users u ON u.id = l.user_id
    WHERE u.email = 'chloe@localhost' AND l.status IN ('reserved','active') ORDER BY l.id LIMIT 1`).split('\t');
  const [loanId, oldDue, oldStatus] = chloeLoan;
  console.log(`paused.png: loan ${loanId} due ${oldDue} (${oldStatus}) set to yesterday for the shot`);
  query(`UPDATE loans SET due_date = CURDATE() - INTERVAL 1 DAY WHERE id = ${Number(loanId)}`);
  try {
    const p = await Tab.open(browser);
    await p.login('chloe@localhost', 'Member123!');
    await p.go('borrow.php?id=29');
    const ok = await p.waitFor(`document.getElementById('paused-title')?.textContent === 'New loans are paused for now'`, 5000);
    if (!ok) throw new Error('paused.png: the paused panel did not show');
    await p.eval(top());
    await p.shot('paused.png', ['main .page-head', '.borrow-grid']);
    await p.close();
  } finally {
    query(`UPDATE loans SET due_date = '${oldDue}', status = '${oldStatus}' WHERE id = ${Number(loanId)}`);
    console.log(`paused.png: loan ${loanId} put back to due ${query(`SELECT due_date, status FROM loans WHERE id = ${Number(loanId)}`).replace('\t', ' (')})`);
  }

  // 5 and 6. Borrow box with the date picker open, then the toast right after Confirm (Chloe, The Hobbit).
  const t = await Tab.open(browser);
  await t.login('chloe@localhost', 'Member123!');
  await t.go('book.php?id=29');
  await t.waitFor('!!document.querySelector(".vb .book-actions")', 8000);
  await t.clickEl(BTN('.vb .book-actions', 'Borrow'));
  await t.waitFor('!!document.getElementById("vb-date") && !!document.getElementById("vb-due")', 3000);
  await t.eval(`(() => { const s = document.getElementById('vb-date'); s.selectedIndex = 2; s.dispatchEvent(new Event('change')); })()`);
  await sleep(400);
  await t.eval(`document.querySelector('.vb').scrollIntoView({ block: 'center', behavior: 'instant' })`);
  await sleep(200);
  await t.shot('borrow-box.png', ['.book-cover, .book-hero img, main .book-info h1', '.vb'], { maxH: 900 });

  await t.clickEl(BTN('.vb-picker', 'Confirm loan'));
  await t.waitFor(`!!document.querySelector('.vt-item')`, 6000);
  await sleep(250);   // let the slide in finish; the toast stays about 5 s
  await t.shot('toast.png', ['.vt-item'], { viewport: true, pad: 220, minW: 720 });

  // 3. My Account right after Return (Chloe returns Fourth Wing in the Vue loans panel).
  await t.go('account.php');
  await t.waitFor('!!document.querySelector(".vl .loan-item")', 8000);
  const row = `[...document.querySelectorAll('.vl .loan-item')].find(li => li.querySelector('h3 a').textContent === 'Fourth Wing')`;
  await t.clickEl(`[...${row}.querySelectorAll('button')].find(b => b.textContent.trim() === 'Return')`);
  await t.clickEl(`${row}.querySelector('[id^="vl-yes-"]')`);
  await t.waitFor(`!${row} && !!document.querySelector('.vl-message')`, 5000);
  await t.waitFor(`document.querySelectorAll('.vt-item').length === 0`, 7000);   // the "Returned" toast fades after ~5 s
  await sleep(300);
  await t.shot('account-after-return.png', ['.tiles, .tile', '#loans .section-head, #loans h2', '.vl-summary', '.vl-message', '.vl .loan-item'], { maxH: 1100 });
  await t.close();

  // 4. Study rooms: a day where Budy already has a booking, one cell chosen, 1 hour -> over the 60 minute cap.
  const r = await Tab.open(browser);
  await r.login('budy@localhost', 'Member123!');
  await r.go('rooms.php');
  await r.waitFor('document.querySelectorAll(".vr-grid li").length > 0', 8000);
  const day = await r.eval(`(async () => { for (const opt of [...document.querySelectorAll('#vr-date option')]) {
      const d = await fetch('/booknest/api/rooms.php?date=' + opt.value, { credentials: 'same-origin' }).then(x => x.json());
      if (d.minutesUsed > 0 && d.slots.some(s => s.status === 'free')) return opt.value; } return ''; })()`);
  if (day) {
    await r.eval(`(() => { const s = document.getElementById('vr-date'); s.value = ${JSON.stringify(day)}; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
    await r.waitFor(`document.getElementById('vr-date').value === ${JSON.stringify(day)} && !document.querySelector('.vr-grid.is-loading')`, 5000);
    await sleep(400);
  } else {
    // No booking in the seed for Budy: book one hour through the Base form first (the dump restore undoes it).
    await r.clickEl(`document.querySelectorAll('.vr-grid a.slot-free')[2]`);
    await sleep(300);
    await r.clickEl(`document.querySelector('#booking button[type="submit"]')`);
    await r.waitFor(`!!document.querySelector('.flash')`, 8000);
    await r.waitFor('document.querySelectorAll(".vr-grid li").length > 0', 8000);
  }
  await r.eval(`(() => { const s = document.getElementById('vr-duration'); s.value = '60'; s.dispatchEvent(new Event('change', { bubbles: true })); })()`);
  await r.clickEl(`[...document.querySelectorAll('.vr-grid a.slot-free')].slice(-4)[0]`);
  const over = await r.waitFor(`document.querySelector('.vr-quota').classList.contains('is-over') && !!document.querySelector('.vr-grid .is-selected')`, 3000);
  if (!over) console.log('rooms-slotgrid.png: warning: the cap warning did not appear');
  await r.eval(`document.querySelector('.vr').scrollIntoView({ behavior: 'instant' })`);
  await r.eval('window.scrollBy(0, -20)');
  await sleep(300);
  await r.shot('rooms-slotgrid.png', ['.vr'], { maxH: 1100 });
  await r.close();

  // 7. Add a book: the serial field's live check, free and already in the library.
  const a = await Tab.open(browser);
  await a.login('budy@localhost', 'Member123!');
  await a.go('add-book.php');
  await a.waitFor('/is free to use/.test(document.getElementById("ab-serial-status")?.textContent || "")', 8000);
  const field = `[...document.querySelectorAll('#add .field')].find(f => f.contains(document.getElementById('ab-serial')))`;
  const framing = async (name) => {
    await a.eval(`${field}.scrollIntoView({ block: 'center', behavior: 'instant' })`);
    await sleep(200);
    await a.eval(`document.getElementById('ab-serial').setAttribute('data-shot', ''); ${field}.setAttribute('data-shot', '');
      ${field}.closest('.panel').querySelector('.panel-title').setAttribute('data-shot', '')`);
    await a.shot(name, ['[data-shot]'], { maxH: 600 });
  };
  await framing('serial-free.png');
  await a.type('#ab-serial', 'BNT-000101', 40);
  await a.waitFor(`/already in the library/.test(document.getElementById('ab-serial-status').textContent)`, 4000);
  await sleep(300);
  await framing('serial-taken.png');
  await a.close();
}

// ---- 8. The three test suites, each on a fresh seed --------------------------------------------------
function runTests() {
  const suites = [
    ['Base end to end (tools/e2e-test.php)', PHP, ['tools/e2e-test.php']],
    ['API (tools/api-test.php)', PHP, ['tools/api-test.php']],
    ['Browser (tools/vue-browser-test.mjs)', process.execPath, ['tools/vue-browser-test.mjs']],
  ];
  let out = `BookNest test results, ${new Date().toString()}\nEach suite ran on a fresh import of sql/schema.sql and sql/seed.sql.\n`;
  for (const [label, cmd, args] of suites) {
    freshSeed();
    console.log(`running ${label} ...`);
    const r = spawnSync(cmd, args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 1 << 26 });
    const lines = (r.stdout + r.stderr).trimEnd().split(/\r?\n/);
    const tail = lines.slice(-3).join('\n');
    console.log(tail);
    out += `\n== ${label} (exit code ${r.status}) ==\n${tail}\n`;
    writeFileSync(join(OUT, 'full-' + args[0].split('/').pop().replace(/\.\w+$/, '') + '.log'), lines.join('\n') + '\n');
  }
  const file = join(OUT, 'tests.txt');
  writeFileSync(file, out);
  saved.push(file);
}

async function main() {
  if (!EDGE) { console.log('Microsoft Edge not found.'); process.exit(2); }
  mkdirSync(OUT, { recursive: true });
  const coversBefore = new Set(readdirSync(join(ROOT, 'assets/covers')));

  // Keep the current database to put it back at the end.
  const dumpFile = join(tmpdir(), `booknest-before-screenshots-${Date.now()}.sql`);
  const dump = spawnSync(MYSQLDUMP, ['-uroot', '--default-character-set=utf8mb4', '--routines', '--databases', 'booknest'], { encoding: 'utf8', maxBuffer: 1 << 28 });
  if (dump.status !== 0) throw new Error('mysqldump: ' + dump.stderr);
  writeFileSync(dumpFile, dump.stdout);
  console.log(`database saved to ${dumpFile}`);

  let failed = null;
  try {
    freshSeed();
    const profile = mkdtempSync(join(tmpdir(), 'bn-shots-'));
    const edge = spawn(EDGE, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--no-first-run',
      '--no-default-browser-check', '--hide-scrollbars', `--window-size=${W},${H}`, 'about:blank']);
    let version;
    for (let i = 0; i < 60 && !version; i++) {
      try { version = await (await fetch(`http://127.0.0.1:${PORT}/json/version`)).json(); } catch { await sleep(250); }
    }
    const browser = await Cdp.connect(version.webSocketDebuggerUrl);
    console.log(`BookNest screenshots, ${version.Browser}, ${W}x${H}`);
    try { await shots(browser); } catch (e) { failed = e; }
    browser.close();
    edge.kill();
    await sleep(500);
    try { rmSync(profile, { recursive: true, force: true }); } catch { /* Edge may still hold a file */ }
    if (!failed && !process.argv.includes('--no-tests')) runTests();
  } catch (e) { failed = failed || e; }
  finally {
    mysql(readFileSync(dumpFile, 'utf8'));
    console.log(`database put back from ${dumpFile}`);
    const added = readdirSync(join(ROOT, 'assets/covers')).filter((f) => !coversBefore.has(f));
    if (added.length) console.log(`note: the tests added these cover files: ${added.map((f) => 'assets/covers/' + f).join(', ')}`);
  }
  if (failed) { console.log('STOPPED: ' + failed.message); process.exit(1); }
  console.log('\nFiles:');
  saved.forEach((f) => console.log('  ' + f));
}

main();
