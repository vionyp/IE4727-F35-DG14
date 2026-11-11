// BookNest live server (Modern Enhancement): Node.js + Express.
// Start it next to XAMPP:  cd live  then  npm install  (once)  then  npm start
//
// What it does
// - GET /events?t=<pass>: a page opens one long-lived connection here (Server-Sent Events). The pass
//   is signed by PHP (live_token() in includes/live.php), so this server knows who is reading without
//   touching the database or the PHP session.
// - POST /notify: PHP calls this after it saves a change (a loan, a return, a fee, a booking). The
//   note says who should hear about it ('all', 'admins' or a member id) and what changed. The server
//   passes it on to the matching open pages, and assets/js/live.js updates them in place.
// - Every 30 seconds it checks the opening hours, and tells every page when the library opens or closes.
// PHP stays the source of truth: this server stores nothing and only says "something changed".
// If it is not running, BookNest works exactly as before; pages just need a reload to show changes.
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const express = require('express');

// Settings come from the same config/config.php that PHP uses, so there is one place to change them.
const configText = fs.readFileSync(path.join(__dirname, '..', 'config', 'config.php'), 'utf8');
function setting(name) {
  const m = configText.match(new RegExp("define\\('" + name + "',\\s*'?([^')]+)'?\\)"));
  if (!m) { throw new Error('config.php has no ' + name); }
  return m[1].trim();
}
const PORT = Number(setting('LIVE_PORT'));
const SECRET = setting('LIVE_SECRET');
const OPEN_HOUR = Number(setting('OPEN_HOUR'));
const CLOSE_HOUR = Number(setting('CLOSE_HOUR'));
const TIMEZONE = setting('APP_TIMEZONE');

const app = express();
app.disable('x-powered-by');

// Every open page: { res, uid, admin }.
const clients = new Set();

// Checks a pass made by live_token(): "<base64url JSON>.<HMAC-SHA256 hex>". Returns its contents or null.
function readPass(pass) {
  if (typeof pass !== 'string' || pass.length > 600) { return null; }
  const dot = pass.indexOf('.');
  if (dot < 1) { return null; }
  const payload = pass.slice(0, dot);
  const given = Buffer.from(pass.slice(dot + 1), 'utf8');
  const wanted = Buffer.from(crypto.createHmac('sha256', SECRET).update(payload).digest('hex'), 'utf8');
  if (given.length !== wanted.length || !crypto.timingSafeEqual(given, wanted)) { return null; }
  let data;
  try {
    data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
  if (!data || typeof data.exp !== 'number' || data.exp * 1000 < Date.now()) { return null; }
  return { uid: Number(data.uid) || 0, admin: data.admin === true };
}

// Sends one event to one page.
function send(client, event, data) {
  client.res.write('event: ' + event + '\ndata: ' + JSON.stringify(data) + '\n\n');
}

// Passes a note to every page it is meant for.
function deliver(note) {
  let count = 0;
  for (const c of clients) {
    const match = note.to === 'all' || (note.to === 'admins' && c.admin) || (Number.isInteger(note.to) && note.to === c.uid);
    if (match) {
      send(c, 'update', { topic: note.topic, message: note.message || '' });
      count++;
    }
  }
  return count;
}

// A page starts listening. The browser's EventSource reconnects by itself if the line drops.
app.get('/events', (req, res) => {
  const who = readPass(req.query.t);
  res.set('Access-Control-Allow-Origin', '*');   // the pass in the URL is what grants access, not cookies
  if (!who) {
    res.status(401).type('text').send('This live pass is missing or has expired. Reload the page.');
    return;
  }
  res.set({ 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-store', Connection: 'keep-alive', 'X-Accel-Buffering': 'no' });
  res.flushHeaders();
  res.write('retry: 3000\n\n');   // wait 3 seconds before reconnecting after a drop
  const client = { res, uid: who.uid, admin: who.admin };
  clients.add(client);
  send(client, 'hello', { open: isOpen() });
  req.on('close', () => clients.delete(client));
});

// PHP reports changes here. Only PHP on this computer knows the secret, and only local calls are taken.
app.post('/notify', express.json({ limit: '32kb' }), (req, res) => {
  const local = ['127.0.0.1', '::1', '::ffff:127.0.0.1'].includes(req.socket.remoteAddress);
  const given = Buffer.from(String(req.get('X-Live-Secret') || ''), 'utf8');
  const wanted = Buffer.from(SECRET, 'utf8');
  if (!local || given.length !== wanted.length || !crypto.timingSafeEqual(given, wanted)) {
    res.status(403).json({ error: 'Not allowed.' });
    return;
  }
  const events = Array.isArray(req.body && req.body.events) ? req.body.events.slice(0, 50) : [];
  let delivered = 0;
  for (const e of events) {
    const to = e.to === 'all' || e.to === 'admins' ? e.to : Number.parseInt(e.to, 10);
    const topic = String(e.topic || '');
    if ((to !== 'all' && to !== 'admins' && !(to > 0)) || !/^[a-z][a-z0-9:-]{0,40}$/.test(topic)) { continue; }
    delivered += deliver({ to, topic, message: String(e.message || '').slice(0, 300) });
  }
  console.log(new Date().toLocaleTimeString('en-SG', { timeZone: TIMEZONE }) + '  ' + events.map((e) => e.to + ' ' + e.topic).join(', ')
    + '  -> ' + delivered + ' page' + (delivered === 1 ? '' : 's'));
  res.json({ delivered });
});

// A quick health check: open http://localhost:3000/ to see it is running.
app.get('/', (req, res) => {
  res.json({ name: 'BookNest live server', pagesListening: clients.size, libraryOpen: isOpen() });
});

// Opening hours, worked out in Singapore time like library_status() in PHP.
function isOpen() {
  const hour = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hourCycle: 'h23', timeZone: TIMEZONE }).format(new Date()));
  return hour >= OPEN_HOUR && hour < CLOSE_HOUR;
}
let wasOpen = isOpen();
setInterval(() => {
  const open = isOpen();
  if (open !== wasOpen) {
    wasOpen = open;
    deliver({ to: 'all', topic: 'hours', message: '' });
  }
}, 30 * 1000);

// A comment line every 25 seconds keeps idle connections from being closed along the way.
setInterval(() => { for (const c of clients) { c.res.write(': still here\n\n'); } }, 25 * 1000);

app.listen(PORT, () => {
  console.log('BookNest live server on http://localhost:' + PORT + '  (library ' + (wasOpen ? 'open' : 'closed') + ' now)');
  console.log('Leave this window open during the demo. Press Ctrl+C to stop.');
});
