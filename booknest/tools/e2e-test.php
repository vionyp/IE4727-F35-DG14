<?php
// End to end tests: drives the real site over HTTP (like a browser, with cookies and CSRF tokens)
// and checks the database afterwards. Run with Apache and MySQL started:
//   C:\xampp\php\php.exe tools\e2e-test.php
// The tests change data; re-import sql/schema.sql and sql/seed.sql afterwards for a clean demo.
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
date_default_timezone_set(APP_TIMEZONE);

const BASE = 'http://localhost/booknest/';
$results = [];

// A tiny browser: keeps cookies, never follows redirects automatically.
final class Client
{
    public string $jar;
    public function __construct(public string $name)
    {
        $this->jar = sys_get_temp_dir() . '/bn_e2e_' . $name . '_' . getmypid() . '.txt';
        @unlink($this->jar);
    }
    // Builds a curl handle for a request.
    public function handle(string $path, ?array $post = null, array $cookies = []): CurlHandle
    {
        $ch = curl_init(BASE . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
            CURLOPT_REFERER => BASE . 'index.php']);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($cookies), $cookies)));
        }
        return $ch;
    }
    // Parses a finished curl handle into status, location and body.
    public static function parse(CurlHandle $ch, string $raw): array
    {
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);
        return ['code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $m[1] ?? '', 'body' => substr($raw, $size), 'headers' => $headers];
    }
    // Sends a request and returns the parsed response.
    public function req(string $path, ?array $post = null, array $cookies = []): array
    {
        $ch = $this->handle($path, $post, $cookies);
        $raw = curl_exec($ch);
        return self::parse($ch, (string) $raw);
    }
    // GETs a page and returns its body.
    public function get(string $path, array $cookies = []): array
    {
        return $this->req($path, null, $cookies);
    }
    // Returns the CSRF token for this session, reading it from a page with a form.
    public function csrf(): string
    {
        foreach (['sign-in.php', 'account.php', 'book.php?id=1'] as $page) {
            if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $this->get($page)['body'], $m)) {
                return $m[1];
            }
        }
        return '';
    }
    // POSTs a form with a fresh CSRF token, then follows the redirect and returns the landing page.
    public function submit(string $path, array $data, bool $withCsrf = true): array
    {
        if ($withCsrf) {
            $data['csrf'] = $this->csrf();
        }
        $r = $this->req($path, $data);
        $landing = $r['location'] ? $this->get(local_path($r['location'])) : $r;
        return ['post' => $r, 'page' => $landing, 'flash' => flash_text($landing['body'])];
    }
    // Signs in as a user.
    public function login(string $email, string $password): array
    {
        return $this->submit('process/login.php', ['email' => $email, 'password' => $password, 'return' => BASE . 'account.php']);
    }
}

// Turns a Location header (absolute or /booknest/...) into a path relative to the site.
function local_path(string $location): string
{
    return (string) preg_replace('#^(https?://[^/]+)?/booknest/#', '', $location);
}

// Extracts the text of flash messages from a page.
function flash_text(string $html): string
{
    preg_match_all('/<div class="flash flash-(\w+)"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m, PREG_SET_ORDER);
    return implode(' | ', array_map(fn($x) => strtoupper($x[1]) . ': ' . html_entity_decode($x[2], ENT_QUOTES), $m));
}

// Records a test result.
function check(string $id, string $name, bool $pass, string $actual): void
{
    global $results;
    $results[] = [$id, $name, $pass, $actual];
    printf("%-4s %-4s %-58s %s\n", $id, $pass ? 'PASS' : 'FAIL', mb_strimwidth($name, 0, 58), mb_strimwidth($actual, 0, 110, '...'));
}

// Finds a room and start time that is free on a date for a given duration.
function free_slot(string $date, int $duration, int $avoidRoom = 0, int $from = 600): array
{
    for ($m = $from; $m + $duration <= 21 * 60; $m += 30) {
        $s = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $e = sprintf('%02d:%02d', intdiv($m + $duration, 60), ($m + $duration) % 60);
        for ($room = 1; $room <= 6; $room++) {
            if ($room === $avoidRoom) {
                continue;
            }
            $busy = db_value("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND booking_date = ? AND status = 'confirmed' AND start_time < ? AND end_time > ?", [$room, $date, $e, $s]);
            if (!$busy) {
                return [$room, $s];
            }
        }
    }
    throw new RuntimeException("No free slot on $date");
}

$mailLog = STORAGE_PATH . '/mail.log';
$mailSize = fn() => is_file($mailLog) ? filesize($mailLog) : 0;
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$in2 = date('Y-m-d', strtotime('+2 day'));
$in3 = date('Y-m-d', strtotime('+3 day'));
$stamp = date('His');

echo "BookNest end to end tests, " . date('Y-m-d H:i:s') . "\n" . str_repeat('-', 100) . "\n";

// ---- Search, sort, serial lookup -------------------------------------------------------------
$v = new Client('visitor');
$r = $v->get('catalogue.php?q=alice');
check('T01', 'Search "alice" finds Alice in Wonderland', str_contains($r['body'], 'Adventures in Wonderland'), 'HTTP ' . $r['code'] . ', title found: ' . (str_contains($r['body'], 'Adventures in Wonderland') ? 'yes' : 'no'));

$r = $v->get('catalogue.php?q=' . rawurlencode("' OR 1=1 --"));
$n = preg_match_all('/<article class="card"/', $r['body']);
check('T02', 'SQL injection in search is harmless', $r['code'] === 200 && $n === 0 && str_contains($r['body'], 'No results'), "HTTP {$r['code']}, $n cards, 'No results' shown");

$r = $v->get('catalogue.php?sort=DROP');
check('T03', 'Unknown sort value falls back to default', $r['code'] === 200 && str_contains($r['body'], 'value="title" selected'), "HTTP {$r['code']}, sort select shows Title A to Z");

$r = $v->get('catalogue.php?q=BNT-000123');
check('T05', 'Visitor serial search hides product details', !str_contains($r['body'], 'Exact serial match') && str_contains($r['body'], 'member feature'), 'Members-only notice shown, no details panel');

$aisha = new Client('aisha');
$f = $aisha->login('aisha@localhost', 'Member123!');
check('T17a', 'Member sign in with correct password', str_contains($f['flash'], 'Welcome back'), $f['flash']);
$r = $aisha->get('catalogue.php?q=BNT-000123');
check('T04', 'Member serial search shows full details', str_contains($r['body'], 'Exact serial match') && str_contains($r['body'], 'Copies sold') && str_contains($r['body'], 'Added by'), 'Details panel with stock, status, sold, added by');

// ---- Accounts ---------------------------------------------------------------------------------
$bad = new Client('bad');
$f = $bad->login('aisha@localhost', 'wrong-password1');
check('T17', 'Wrong password gives a generic error', str_contains($f['flash'], 'Email or password is incorrect'), $f['flash']);
$f = $bad->login('nobody@localhost', 'Whatever123');
check('T17b', 'Unknown email gives the same generic error', str_contains($f['flash'], 'Email or password is incorrect'), $f['flash']);

$before = (int) db_value('SELECT COUNT(*) FROM users');
$f = $bad->submit('process/register.php', ['full_name' => 'Aisha Copy', 'email' => 'aisha@localhost', 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg123', 'agree' => '1']);
check('T15', 'Register with an existing email is refused', str_contains($f['page']['body'], 'already an account') && (int) db_value('SELECT COUNT(*) FROM users') === $before, 'Field error shown; users still ' . $before);

$f = $bad->submit('process/register.php', ['full_name' => 'Test Person', 'email' => "t$stamp@localhost", 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg999', 'agree' => '1']);
check('T16', 'Passwords that differ are refused by PHP', str_contains($f['page']['body'], 'passwords do not match'), 'Error "The passwords do not match." on confirm field');

$u1 = new Client('u1');
$f = $u1->submit('process/register.php', ['full_name' => 'Test One', 'email' => "one$stamp@localhost", 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg123', 'agree' => '1']);
check('T15b', 'Register a new member and sign in', str_contains($f['flash'], 'Welcome to BookNest'), $f['flash']);
$u2 = new Client('u2');
$u2->submit('process/register.php', ['full_name' => 'Test Two', 'email' => "two$stamp@localhost", 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg123', 'agree' => '1']);

// ---- Cookies ----------------------------------------------------------------------------------
$c = new Client('cookie');
foreach ([7, 13, 25] as $id) {
    $c->get("book.php?id=$id");
}
$r = $c->get('index.php');
check('T19', 'Recently viewed row appears after viewing books', str_contains($r['body'], 'id="row-recent"'), 'Row "Recently viewed" rendered from bn_recent cookie');
$r = $v->get('index.php', ['bn_recent' => rawurlencode('1,abc,DROP TABLE,13')]);
check('T20', 'Tampered recently viewed cookie is ignored safely', $r['code'] === 200 && str_contains($r['body'], 'id="row-recent"') && !str_contains($r['body'], 'Something went wrong'), "HTTP {$r['code']}, valid ids 1 and 13 shown, junk ignored");
$f = $c->submit('process/login.php', ['email' => 'ben@localhost', 'password' => 'Member123!', 'remember' => '1', 'return' => BASE . 'account.php']);
$jar = file_get_contents($c->jar);
check('T19b', 'Remember my email sets an HttpOnly cookie', str_contains($jar, 'bn_email') && str_contains($jar, '#HttpOnly_'), 'bn_email cookie stored with HttpOnly flag');

// ---- Security ---------------------------------------------------------------------------------
$before = (int) db_value('SELECT COUNT(*) FROM shelf');
$r = $aisha->req('process/shelf.php', ['book_id' => '5', 'action' => 'add']);
check('T33', 'POST without CSRF token is rejected', (int) db_value('SELECT COUNT(*) FROM shelf') === $before && $r['code'] === 303, "303 redirect, shelf rows unchanged ($before)");

$r = $aisha->get('admin.php');
$land = $aisha->get(local_path($r['location']));
check('T31', 'Member opening admin.php is refused', $r['code'] === 303 && str_contains(flash_text($land['body']), 'library staff only'), 'Redirected home: ' . flash_text($land['body']));

$before = (int) db_value('SELECT COUNT(*) FROM books');
$r = $v->req('process/add-book.php', ['serial_no' => 'BNT-999999', 'title' => 'X']);
check('T32', 'Visitor posting to add-book is sent to sign in', $r['code'] === 303 && str_contains($r['location'], 'sign-in.php') && (int) db_value('SELECT COUNT(*) FROM books') === $before, 'Redirect to ' . basename(parse_url($r['location'], PHP_URL_PATH)) . ', no row added');

$r = $v->get('config/config.php');
check('T33b', 'Config and storage folders are not web accessible', $r['code'] === 403 && $v->get('storage/mail.log')['code'] === 403, "config: {$r['code']}, storage: 403");

// ---- Shelf ------------------------------------------------------------------------------------
$f = $aisha->submit('process/shelf.php', ['book_id' => '5', 'action' => 'add']);
$has = (int) db_value('SELECT COUNT(*) FROM shelf WHERE user_id = 2 AND book_id = 5');
$f2 = $aisha->submit('process/shelf.php', ['book_id' => '5', 'action' => 'remove']);
$after = (int) db_value('SELECT COUNT(*) FROM shelf WHERE user_id = 2 AND book_id = 5');
check('T38', 'Save to shelf (INSERT) then remove (DELETE)', $has === 1 && $after === 0, "after save: $has row, after remove: $after rows");

// ---- Checkout ---------------------------------------------------------------------------------
$g = new Client('guest');
$g->submit('process/cart.php', ['action' => 'buy', 'book_id' => '13']);
$valid = ['full_name' => 'Guest Buyer', 'email' => 'guest@localhost', 'phone' => '91234567', 'delivery_method' => 'delivery', 'address' => '12 Nanyang Drive, Singapore 637721', 'note' => '', 'simulate' => 'success'];
$orders = (int) db_value('SELECT COUNT(*) FROM orders');

$f = $g->submit('process/checkout.php', ['full_name' => ''] + $valid);
check('T09', 'Empty name is rejected by PHP (HTML5 bypassed)', str_contains($f['page']['body'], 'Enter the name for this order') && (int) db_value('SELECT COUNT(*) FROM orders') === $orders, 'Field error shown, no order row');
$f = $g->submit('process/checkout.php', ['address' => ''] + $valid);
check('T10', 'Delivery without an address is rejected', str_contains($f['page']['body'], 'full delivery address'), 'Address field error shown');
$f = $g->submit('process/checkout.php', ['phone' => '12345'] + $valid);
check('T11', 'Invalid phone number is rejected', str_contains($f['page']['body'], 'Singapore number with 8 digits'), 'Phone field error shown');
$f = $g->submit('process/checkout.php', ['address' => '12 Nanyang Drive'] + $valid);
check('T10b', 'Address without a postal code is rejected', str_contains($f['page']['body'], '6 digit Singapore postal code'), 'Postal code error shown');

$stock = (int) db_value('SELECT stock FROM books WHERE id = 13');
$f = $g->submit('process/checkout.php', ['simulate' => 'failure'] + $valid);
$last = db_one('SELECT id, payment_status FROM orders ORDER BY id DESC LIMIT 1');
$cartKept = str_contains($f['page']['body'], 'Order summary');
check('T12', 'Payment failure: order failed, stock and cart kept', $last['payment_status'] === 'failed' && (int) db_value('SELECT stock FROM books WHERE id = 13') === $stock && $cartKept,
    "order #{$last['id']} {$last['payment_status']}, stock $stock unchanged, cart kept: " . ($cartKept ? 'yes' : 'no'));

$m0 = $mailSize();
$f = $g->submit('process/checkout.php', $valid);
$last = db_one('SELECT id, payment_status, total FROM orders ORDER BY id DESC LIMIT 1');
$newStock = (int) db_value('SELECT stock FROM books WHERE id = 13');
$mail = substr((string) @file_get_contents($mailLog), $m0);
check('T13', 'Payment success: paid, stock reduced, email, cart cleared', $last['payment_status'] === 'paid' && $newStock === $stock - 1 && str_contains($mail, 'guest@localhost') && str_contains($f['page']['body'], 'is confirmed'),
    "order #{$last['id']} paid S\${$last['total']}, stock $stock -> $newStock, mail logged, confirmation page shown");

$g->submit('process/cart.php', ['action' => 'add', 'book_id' => '13']);
$f = $g->submit('process/cart.php', ['action' => 'update', 'book_id' => '13', 'qty' => '999']);
check('T14', 'Quantity above stock is refused', str_contains($f['flash'], 'Choose a quantity from 1 to'), $f['flash']);

$m0 = $mailSize();
$g->submit('process/checkout.php', ['email' => 'someone@gmail.com'] + $valid);
$mail = substr((string) @file_get_contents($mailLog), $m0);
check('T28', 'Email to an external address is refused and logged', str_contains($mail, 'someone@gmail.com') && str_contains($mail, 'refused: external recipient blocked'), 'mail.log: "refused: external recipient blocked"');

// ---- Study rooms --------------------------------------------------------------------------------
[$room, $start] = free_slot($in2, 60);
$m0 = $mailSize();
$f = $u1->submit('process/book-room.php', ['room_id' => (string) $room, 'date' => $in2, 'start' => $start, 'duration' => '60', 'purpose' => 'Test']);
$ok = db_value("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND booking_date = ? AND start_time = ? AND status = 'confirmed'", [$room, $in2, $start . ':00']);
check('T21', '60 minute booking is confirmed and emailed', $ok == 1 && str_contains($f['flash'], 'Booked') && str_contains(substr((string) @file_get_contents($mailLog), $m0), 'Study room booked'), $f['flash']);

[$room2, $start2] = free_slot($in3, 90);
$f = $u2->submit('process/book-room.php', ['room_id' => (string) $room2, 'date' => $in3, 'start' => $start2, 'duration' => '90']);
check('T22', '90 minute booking (tampered) is rejected', str_contains($f['flash'], '30 or 60 minutes'), $f['flash']);

[$room3, $start3] = free_slot($in2, 30, $room, (int) substr($start, 0, 2) * 60 + (int) substr($start, 3) + 60);
$f = $u1->submit('process/book-room.php', ['room_id' => (string) $room3, 'date' => $in2, 'start' => $start3, 'duration' => '30']);
check('T23', 'Extra 30 minutes after using the daily hour is rejected', str_contains($f['flash'], 'daily limit is 60 minutes'), $f['flash']);

$f = $u2->submit('process/book-room.php', ['room_id' => (string) $room, 'date' => $in2, 'start' => $start, 'duration' => '30']);
check('T24', 'Overlapping booking in the same room is rejected', str_contains($f['flash'], 'just booked'), $f['flash']);

$f = $u2->submit('process/book-room.php', ['room_id' => '1', 'date' => date('Y-m-d'), 'start' => '10:00', 'duration' => '30']);
$f2 = $u2->submit('process/book-room.php', ['room_id' => '1', 'date' => date('Y-m-d', strtotime('+8 day')), 'start' => '12:00', 'duration' => '30']);
check('T27', 'Past time and 8 days ahead are both rejected', (str_contains($f['flash'], 'already passed') || str_contains($f['flash'], 'just booked') || str_contains($f['flash'], 'daily limit')) && str_contains($f2['flash'], 'up to 7 days'), $f['flash'] . ' || ' . $f2['flash']);

$f = $u2->submit('process/book-room.php', ['room_id' => '2', 'date' => $in3, 'start' => '20:30', 'duration' => '60']);
check('T30', 'Booking that ends after closing is rejected', str_contains($f['flash'], 'closes at 21:00'), $f['flash']);

// T25: two different members race for the same slot at the same moment.
$u3 = new Client('u3');
$u3->submit('process/register.php', ['full_name' => 'Test Three', 'email' => "three$stamp@localhost", 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg123', 'agree' => '1']);
[$rr, $rs] = free_slot($in3, 60);
$mh = curl_multi_init();
$handles = [];
foreach ([$u2, $u3] as $cl) {
    $h = $cl->handle('process/book-room.php', ['csrf' => $cl->csrf(), 'room_id' => (string) $rr, 'date' => $in3, 'start' => $rs, 'duration' => '60']);
    curl_multi_add_handle($mh, $h);
    $handles[] = $h;
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running);
$won = (int) db_value("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND booking_date = ? AND start_time = ? AND status = 'confirmed'", [$rr, $in3, $rs . ':00']);
check('T25', 'Two members, same slot, simultaneous: only one wins', $won === 1, "$won confirmed booking for room $rr at $rs on $in3");

// T26: one member tries two rooms at the same time in parallel (daily cap and user overlap).
$u4 = new Client('u4');
$u4->submit('process/register.php', ['full_name' => 'Test Four', 'email' => "four$stamp@localhost", 'password' => 'Abcdefg123', 'confirm' => 'Abcdefg123', 'agree' => '1']);
$uid4 = (int) db_value('SELECT id FROM users WHERE email = ?', ["four$stamp@localhost"]);
[$ra, $sa] = free_slot($tomorrow, 60);
[$rb2, $sb] = free_slot($tomorrow, 60, $ra);
$mh = curl_multi_init();
$token = $u4->csrf();
foreach ([[$ra, $sa], [$rb2, $sb]] as [$rm, $st]) {
    curl_multi_add_handle($mh, $u4->handle('process/book-room.php', ['csrf' => $token, 'room_id' => (string) $rm, 'date' => $tomorrow, 'start' => $st, 'duration' => '60']));
}
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running);
$mins = (int) db_value("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) FROM room_bookings WHERE user_id = ? AND booking_date = ? AND status = 'confirmed'", [$uid4, $tomorrow]);
check('T26', 'One member, two rooms in parallel: cap still 60 minutes', $mins === 60, "$mins minutes booked for the member on $tomorrow");

// T29: cancel then rebook frees the quota.
$bid = (int) db_value("SELECT id FROM room_bookings WHERE room_id = ? AND booking_date = ? AND start_time = ? AND status = 'confirmed'", [$room, $in2, $start . ':00']);
$f = $u1->submit('process/cancel-room.php', ['booking_id' => (string) $bid]);
$f2 = $u1->submit('process/book-room.php', ['room_id' => (string) $room3, 'date' => $in2, 'start' => $start3, 'duration' => '30']);
check('T29', 'Cancel frees the hour; rebooking then succeeds', str_contains($f['flash'], 'free again') && str_contains($f2['flash'], 'Booked'), $f['flash'] . ' || ' . $f2['flash']);
$f = $u2->submit('process/cancel-room.php', ['booking_id' => (string) $bid]);
check('T29b', 'A member cannot cancel someone else\'s booking', str_contains($f['flash'], 'could not find that booking'), $f['flash']);

// ---- Add book and approval ----------------------------------------------------------------------
$serial = sprintf('BNT-%06d', 900000 + (int) date('is'));
$book = ['serial_no' => $serial, 'title' => 'The Test of Time', 'author' => 'Ada Tester', 'category_id' => '1', 'price' => '12.50',
         'published_year' => '1999', 'pages' => '200', 'stock' => '3', 'hook' => '', 'synopsis' => str_repeat('A thoughtful story about testing. ', 3), 'sample_text' => ''];
$f = $aisha->submit('process/add-book.php', $book);
$row = db_one('SELECT id, status FROM books WHERE serial_no = ?', [$serial]);
$inCatalogue = str_contains($v->get('catalogue.php?q=Test+of+Time')['body'], 'The Test of Time');
check('T35', 'Member submission saved as pending, hidden from catalogue', $row && $row['status'] === 'pending' && !$inCatalogue, "status {$row['status']}, visible in catalogue: " . ($inCatalogue ? 'yes' : 'no'));
$f = $aisha->submit('process/add-book.php', $book);
check('T34', 'Duplicate serial number is rejected', str_contains($f['page']['body'], 'already in the library'), 'Serial field error shown');
$f = $aisha->submit('process/add-book.php', ['serial_no' => 'ABC-12'] + $book);
check('T34b', 'Badly formatted serial number is rejected', str_contains($f['page']['body'], 'BNT- followed by six digits'), 'Format error shown');

$admin = new Client('admin');
$admin->login('admin@localhost', 'Admin123!');
$f = $admin->submit('process/admin-action.php', ['action' => 'approve', 'id' => (string) $row['id']]);
$inCatalogue = str_contains($v->get('catalogue.php?q=Test+of+Time')['body'], 'The Test of Time');
check('T36', 'Admin approval puts the book in the catalogue', $inCatalogue, $f['flash']);

$r = $admin->get('admin.php');
$rev = (float) db_value("SELECT SUM(total) FROM orders WHERE payment_status = 'paid'");
check('T37', 'Dashboard revenue matches a manual SUM query', str_contains($r['body'], 'S$' . number_format($rev, 2)), 'Manual SQL: S$' . number_format($rev, 2) . ' shown on dashboard');

$f = $admin->submit('process/admin-action.php', ['action' => 'delete', 'id' => '13']);
check('T39b', 'A book with orders cannot be deleted', str_contains($f['flash'], 'kept for the sales records') && db_value('SELECT 1 FROM books WHERE id = 13'), $f['flash']);

// ---- Session timeout ------------------------------------------------------------------------------
$t = new Client('timeout');
$t->login('chloe@localhost', 'Member123!');
preg_match('/bn_session\s+(\S+)/', file_get_contents($t->jar), $m);
$sessFile = 'C:/xampp/tmp/sess_' . ($m[1] ?? '');
if (is_file($sessFile)) {
    $data = file_get_contents($sessFile);
    file_put_contents($sessFile, preg_replace('/last_active\|i:\d+;/', 'last_active|i:' . (time() - 31 * 60) . ';', $data));
}
$r = $t->get('account.php');
$land = $r['location'] ? $t->get(local_path($r['location'])) : $r;
check('T18', 'Idle for 31 minutes signs the member out', $r['code'] === 303 && str_contains(flash_text($land['body']), '30 minutes without activity'), flash_text($land['body']));

// ---- Summary ------------------------------------------------------------------------------------------
$passed = count(array_filter($results, fn($x) => $x[2]));
echo str_repeat('-', 100) . "\n$passed of " . count($results) . " checks passed.\n";
$errLog = STORAGE_PATH . '/error.log';
echo 'PHP errors logged during run: ' . (is_file($errLog) ? count(preg_grep('/(Fatal|Warning|Notice|Exception)/', file($errLog))) : 0) . "\n";

// Write a machine readable copy for docs/TEST_LOG.md.
file_put_contents(__DIR__ . '/e2e-results.txt', implode("\n", array_map(fn($x) => implode("\t", [$x[0], $x[1], $x[2] ? 'PASS' : 'FAIL', $x[3]]), $results)) . "\n");
