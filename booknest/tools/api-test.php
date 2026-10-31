<?php
// Modern Enhancement: tests for the JSON endpoints in api/, run over HTTP like tools/e2e-test.php.
//   C:\xampp\php\php.exe tools\api-test.php
// Needs Apache and MySQL running and the seed data loaded. The loan tests renew and return books,
// so re-import sql/schema.sql and sql/seed.sql afterwards for a clean demo.
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
date_default_timezone_set(APP_TIMEZONE);

const BASE = 'http://localhost/booknest/';
$results = [];

// A small browser: keeps its own cookies, never follows redirects.
final class Client
{
    public string $jar;
    public function __construct(public string $name)
    {
        $this->jar = sys_get_temp_dir() . '/bn_api_' . $name . '_' . getmypid() . '.txt';
        @unlink($this->jar);
    }
    // Sends a request; $post makes it a form POST, $method overrides the verb.
    public function req(string $path, ?array $post = null, string $method = ''): array
    {
        $ch = curl_init(BASE . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if ($method !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        $raw = (string) curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        $body = substr($raw, $size);
        return ['code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body,
            'json' => json_decode($body, true), 'isJson' => (bool) preg_match('#^Content-Type:\s*application/json#mi', $headers)];
    }
    // Returns the session's CSRF token, read from a page that prints a form.
    public function csrf(): string
    {
        foreach (['sign-in.php', 'account.php'] as $page) {
            if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $this->req($page)['body'], $m)) {
                return $m[1];
            }
        }
        return '';
    }
    // Signs in through the normal login form.
    public function login(string $email, string $password): void
    {
        $this->req('process/login.php', ['email' => $email, 'password' => $password, 'csrf' => $this->csrf()]);
    }
}

// Records and prints one result.
function check(string $id, string $name, bool $pass, string $actual): void
{
    global $results;
    $results[] = [$id, $name, $pass, $actual];
    printf("%-5s %-4s %-60s %s\n", $id, $pass ? 'PASS' : 'FAIL', mb_strimwidth($name, 0, 60), mb_strimwidth($actual, 0, 120, '...'));
}

// Returns the text of the availability pill on a catalogue card for a book id, as PHP printed it.
function card_label(string $html, int $bookId): string
{
    $pattern = '#href="/booknest/book\.php\?id=' . $bookId . '".*?<span class="avail ([^"]*)">([^<]*)</span>#s';
    return preg_match($pattern, $html, $m) ? html_entity_decode($m[2], ENT_QUOTES) : '';
}

echo "BookNest api/ tests, " . date('Y-m-d H:i:s') . "\n" . str_repeat('-', 100) . "\n";

// ---- 1. api/books.php (live search) ---------------------------------------------------------------
$v = new Client('visitor');
$approved = (int) db_value("SELECT COUNT(*) FROM books WHERE status = 'approved'");

$r = $v->req('api/books.php?q=alice');
$b = $r['json']['books'][0] ?? [];
check('A01', 'books: "alice" returns Alice with availability', $r['code'] === 200 && $r['isJson'] && ($r['json']['count'] ?? 0) === 1
    && str_contains($b['title'] ?? '', 'Alice') && ($b['availability']['text'] ?? '') !== '' && ($b['category'] ?? '') !== '',
    "HTTP {$r['code']}, " . ($r['json']['count'] ?? '?') . ' book: ' . ($b['title'] ?? '-') . ' / ' . ($b['availability']['text'] ?? '-') . ' / ' . ($b['category'] ?? '-'));

$r = $v->req('api/books.php');
check('A02', 'books: empty query returns every approved book and the categories', $r['code'] === 200 && ($r['json']['count'] ?? 0) === $approved
    && count($r['json']['categories'] ?? []) === (int) db_value('SELECT COUNT(*) FROM categories'),
    ($r['json']['count'] ?? '?') . " books (database: $approved approved), " . count($r['json']['categories'] ?? []) . ' categories');

$pending = (string) db_value("SELECT title FROM books WHERE status = 'pending' ORDER BY id LIMIT 1");
$r = $v->req('api/books.php?q=' . rawurlencode($pending));
check('A03', 'books: a book waiting for review is never returned', $pending !== '' && ($r['json']['count'] ?? -1) === 0, "\"$pending\": " . ($r['json']['count'] ?? '?') . ' results');

$booksBefore = (int) db_value('SELECT COUNT(*) FROM books');
$r = $v->req('api/books.php?q=' . rawurlencode("' OR 1=1 -- "));
$r2 = $v->req('api/books.php?q=' . rawurlencode("%' UNION SELECT email, password_hash FROM users -- "));
check('A04', 'books: SQL injection returns nothing and changes nothing', $r['code'] === 200 && ($r['json']['count'] ?? -1) === 0
    && ($r2['json']['count'] ?? -1) === 0 && !str_contains($r2['body'], '$2y$') && (int) db_value('SELECT COUNT(*) FROM books') === $booksBefore,
    'OR 1=1: ' . ($r['json']['count'] ?? '?') . ', UNION: ' . ($r2['json']['count'] ?? '?') . " results, no hashes, books still $booksBefore");

$r = $v->req('api/books.php?q=' . rawurlencode('<img src=x onerror=alert(1)>'));
$esc = chr(92) . 'u003Cimg';
check('A05', 'books: output is JSON-escaped (no raw < in the body)', $r['isJson'] && !str_contains($r['body'], '<img') && str_contains($r['body'], $esc)
    && preg_match('/^X-Content-Type-Options:\s*nosniff/mi', $r['headers']) === 1, 'query echoed as ' . $esc . '..., nosniff sent');

$r = $v->req('api/books.php', ['q' => 'x']);
$rd = $v->req('api/books.php', null, 'DELETE');
check('A06', 'books: POST and DELETE are refused with 405', $r['code'] === 405 && $rd['code'] === 405 && preg_match('/^Allow:\s*GET/mi', $r['headers']) === 1
    && ($r['json']['error'] ?? '') !== '', "POST {$r['code']}, DELETE {$rd['code']}, \"" . ($r['json']['error'] ?? '') . '"');

$budy = new Client('budy');
$budy->login('budy@gmail.com', 'budy123!');
$deep = (int) db_value("SELECT id FROM books WHERE title = 'Deep Work'");
$apiLabel = '';
foreach ($budy->req('api/books.php?q=Deep%20Work')['json']['books'] ?? [] as $x) {
    if ($x['id'] === $deep) { $apiLabel = $x['availability']['text']; }
}
$pageLabel = card_label($budy->req('catalogue.php?q=Deep%20Work')['body'], $deep);
$visitorLabel = '';
foreach ($v->req('api/books.php?q=Deep%20Work')['json']['books'] ?? [] as $x) {
    if ($x['id'] === $deep) { $visitorLabel = $x['availability']['text']; }
}
check('A07', 'books: member-aware label equals the PHP card label', $apiLabel !== '' && $apiLabel === $pageLabel && $apiLabel !== $visitorLabel,
    "budy api \"$apiLabel\" = page \"$pageLabel\"; visitor sees \"$visitorLabel\"");

$r = $v->req('catalogue.php?q=hobbit');
check('A08', 'Browse page still renders results in PHP and loads the Vue files', $r['code'] === 200 && preg_match_all('/<article class="card"/', $r['body']) >= 1
    && str_contains($r['body'], 'data-filter-grid') && str_contains($r['body'], 'js/vue/boot.js') && str_contains($r['body'], 'js/vue/book-search.js'),
    preg_match_all('/<article class="card"/', $r['body']) . ' card(s) printed by PHP; boot.js and book-search.js included');

// ---- 2. api/loans.php (My Loans panel) -------------------------------------------------------------
// Returns budy's loan id for a title.
$loanOf = fn(string $title) => (int) db_value("SELECT l.id FROM loans l JOIN books b ON b.id = l.book_id
                                               WHERE l.user_id = 82 AND b.title = ? AND l.status <> 'returned'", [$title]);
// Returns one loan row straight from the database.
$loanRow = fn(int $id) => db_one('SELECT due_date, renewals, status FROM loans WHERE id = ?', [$id]);
// Extracts the flash message text from a page, as the Base pages print it.
$flash = function (string $html): string {
    preg_match('/<div class="flash flash-\w+"[^>]*>.*?<p>(.*?)<\/p>/s', $html, $m);
    return html_entity_decode(strip_tags($m[1] ?? ''), ENT_QUOTES);
};
$mailLog = STORAGE_PATH . '/mail.log';

$r = $v->req('api/loans.php');
check('A11', 'loans: not signed in gives 401 with a sign in link', $r['code'] === 401 && $r['isJson'] && ($r['json']['error'] ?? '') !== ''
    && ($r['json']['signin'] ?? '') === '/booknest/sign-in.php', "HTTP {$r['code']}: \"" . ($r['json']['error'] ?? '') . '"');

$r = $budy->req('api/loans.php');
$dbOpen = (int) db_value("SELECT COUNT(*) FROM loans WHERE user_id = 82 AND status <> 'returned'");
$dbOwed = (float) db_value('SELECT COALESCE(SUM(GREATEST(0, DATEDIFF(COALESCE(returned_date, CURDATE()), due_date)) * ' . LATE_FEE_PER_DAY . '), 0)
                            FROM loans WHERE user_id = 82 AND fee_cleared_at IS NULL');
$apiOwed = round(array_sum(array_column($r['json']['loans'] ?? [], 'fee')) + ($r['json']['feesFromReturns'] ?? 0), 2);
check('A12', 'loans: member gets their open loans, fees and block reasons', $r['code'] === 200 && count($r['json']['loans'] ?? []) === $dbOpen
    && abs($apiOwed - $dbOwed) < 0.001 && count($r['json']['blocks'] ?? []) === 3,
    count($r['json']['loans'] ?? []) . " loans (database $dbOpen), fees S\$" . number_format($apiOwed, 2) . ' (database S$' . number_format($dbOwed, 2) . '), '
    . count($r['json']['blocks'] ?? []) . ' block reasons');

$hobbit = $loanOf('The Hobbit');
$before = $loanRow($hobbit);
$r = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $hobbit]);
$r2 = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $hobbit, 'csrf' => str_repeat('0', 64)]);
check('A13', 'loans: missing or wrong CSRF token gives 403, nothing changes', $r['code'] === 403 && $r2['code'] === 403
    && str_contains($r['json']['error'] ?? '', 'session expired') && $loanRow($hobbit) == $before,
    "no token: {$r['code']}, wrong token: {$r2['code']}, \"" . ($r['json']['error'] ?? '') . '"; loan unchanged');

$r = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $hobbit], 'PUT');
check('A14', 'loans: PUT is refused with 405 (GET, POST allowed)', $r['code'] === 405 && preg_match('/^Allow:\s*GET, POST/mi', $r['headers']) === 1, "HTTP {$r['code']}");

$token = $budy->csrf();
$mailBefore = is_file($mailLog) ? filesize($mailLog) : 0;
$r = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $hobbit, 'csrf' => $token]);
$after = $loanRow($hobbit);
$mailNew = is_file($mailLog) ? (string) file_get_contents($mailLog, false, null, $mailBefore) : '';
$newDue = date('Y-m-d', strtotime($before['due_date'] . ' +' . LOAN_DAYS . ' day'));
check('A15', 'loans: renew works like process/loan.php (+14 days, emailed)', $r['code'] === 200 && $after['due_date'] === $newDue && (int) $after['renewals'] === 1
    && ($r['json']['message'] ?? '') === 'Renewed The Hobbit. It is now due back on ' . date('l j F', strtotime($newDue)) . '. A confirmation email is on its way.'
    && str_contains($mailNew, 'Loan renewed: The Hobbit'),
    "due {$before['due_date']} -> {$after['due_date']}, renewals {$after['renewals']}; \"" . ($r['json']['message'] ?? '') . '"');

$r = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $hobbit, 'csrf' => $token]);
check('A16', 'loans: second renewal refused with PHP\'s message', $r['code'] === 422 && ($r['json']['error'] ?? '') === 'Could not renew The Hobbit. It has already been renewed once, the most allowed.'
    && $loanRow($hobbit) == $after && is_array($r['json']['loans'] ?? null), "HTTP {$r['code']}: \"" . ($r['json']['error'] ?? '') . '"; fresh list sent back');

$gone = $loanOf('Gone Girl');
$r = $budy->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $gone, 'csrf' => $token]);
check('A17', 'loans: overdue loan cannot be renewed', $r['code'] === 422 && str_contains($r['json']['error'] ?? '', 'It is overdue, so it cannot be renewed'),
    "HTTP {$r['code']}: \"" . ($r['json']['error'] ?? '') . '"');

$aisha = new Client('aisha');
$aisha->login('aisha@localhost', 'Member123!');
$at = $aisha->csrf();
$educated = $loanOf('Educated');
$eBefore = $loanRow($educated);
$r = $aisha->req('api/loans.php', ['action' => 'renew', 'loan_id' => (string) $educated, 'csrf' => $at]);
$r2 = $aisha->req('api/loans.php', ['action' => 'return', 'loan_id' => (string) $educated, 'csrf' => $at]);
check('A18', 'loans: another member cannot renew or return your loan', $r['code'] === 422 && $r2['code'] === 422 && ($r['json']['error'] ?? '') === 'We could not find that loan.'
    && $loanRow($educated) == $eBefore, "renew {$r['code']} \"" . ($r['json']['error'] ?? '') . "\", return {$r2['code']} \"" . ($r2['json']['error'] ?? '') . '"; loan unchanged');

$r = $budy->req('api/loans.php', ['action' => 'explode', 'loan_id' => (string) $educated, 'csrf' => $token]);
check('A19', 'loans: unknown action gives 400', $r['code'] === 400 && ($r['json']['error'] ?? '') === 'Unknown action.', "HTTP {$r['code']}: \"" . ($r['json']['error'] ?? '') . '"');

$visitorToken = $v->csrf();
$r = $v->req('api/loans.php', ['action' => 'return', 'loan_id' => (string) $educated, 'csrf' => $visitorToken]);
check('A20', 'loans: signed out POST gives 401 even with a valid token', $r['code'] === 401 && $loanRow($educated) == $eBefore, "HTTP {$r['code']}, loan unchanged");

$atomic = $loanOf('Atomic Habits');
$r = $budy->req('api/loans.php', ['action' => 'return', 'loan_id' => (string) $atomic, 'csrf' => $token]);
$sixReason = array_filter($r['json']['blocks'] ?? [], fn($b) => str_contains($b, 'books on loan'));
check('A21', 'loans: return works, list and block reasons are updated', $r['code'] === 200 && ($r['json']['message'] ?? '') === 'Returned Atomic Habits. Thank you for bringing it back on time.'
    && count($r['json']['loans'] ?? []) === $dbOpen - 1 && !$sixReason && $loanRow($atomic)['status'] === 'returned',
    '"' . ($r['json']['message'] ?? '') . '"; ' . count($r['json']['loans'] ?? []) . ' loans left; "6 books" reason gone: ' . ($sixReason ? 'no' : 'yes'));

// The same action through the Base form must give the same words (here: returning Educated on time).
$budy->req('process/loan.php', ['action' => 'return', 'loan_id' => (string) $educated, 'csrf' => $token]);
$baseMsg = $flash($budy->req('account.php')['body']);
check('A22', 'loans: API message matches the Base flash message', $baseMsg === 'Returned Educated. Thank you for bringing it back on time.'
    && str_replace('Atomic Habits', 'Educated', $r['json']['message'] ?? '') === $baseMsg, "Base: \"$baseMsg\"");

$r = $budy->req('account.php');
check('A23', 'My Account still lists loans in PHP and loads the Vue panel', $r['code'] === 200 && preg_match_all('/<li class="loan-item/', $r['body']) >= 4
    && str_contains($r['body'], 'name="action" value="renew"') && str_contains($r['body'], 'js/vue/loans-panel.js'),
    preg_match_all('/<li class="loan-item/', $r['body']) . ' loan rows with Base forms; loans-panel.js included');

// ---- 3. api/rooms.php (study room slot grid) ------------------------------------------------------
$slotCount = (21 - 10) * 2;
$r = $v->req('api/rooms.php');
check('A30', 'rooms: visitor gets today\'s slots for the first open room', $r['code'] === 200 && count($r['json']['slots'] ?? []) === $slotCount
    && ($r['json']['signedIn'] ?? true) === false && ($r['json']['minutesUsed'] ?? -1) === 0 && count($r['json']['dates'] ?? []) === 8
    && ($r['json']['rules']['dailyCap'] ?? 0) === DAILY_CAP_MINUTES,
    "HTTP {$r['code']}, " . count($r['json']['slots'] ?? []) . ' slots in ' . ($r['json']['room']['name'] ?? '?') . ', ' . count($r['json']['dates'] ?? []) . ' dates, cap ' . ($r['json']['rules']['dailyCap'] ?? '?'));

$r = $v->req('api/rooms.php?date=2020-01-01');
$r2 = $v->req('api/rooms.php?room=9999');
$r3 = $v->req('api/rooms.php?date=' . rawurlencode("' OR 1=1 --") . '&room=' . rawurlencode('1 OR 1=1'));
check('A31', 'rooms: bad date 422, unknown room 404, injection rejected', $r['code'] === 422 && ($r['json']['error'] ?? '') === 'Choose a date from today up to 7 days ahead.'
    && $r2['code'] === 404 && ($r2['json']['error'] ?? '') === 'That room does not exist.' && $r3['code'] === 422,
    "old date {$r['code']} \"" . ($r['json']['error'] ?? '') . "\", room 9999 {$r2['code']}, injected date {$r3['code']}");

$r = $v->req('api/rooms.php', ['date' => date('Y-m-d')]);
check('A32', 'rooms: POST refused with 405 (read only)', $r['code'] === 405 && preg_match('/^Allow:\s*GET/mi', $r['headers']) === 1, "HTTP {$r['code']}");

// The grid must agree with the PHP timetable: same statuses for the same room and day.
$busy = db_one("SELECT rb.room_id, rb.booking_date, r.name, r.floor FROM room_bookings rb JOIN study_rooms r ON r.id = rb.room_id
                WHERE rb.status = 'confirmed' AND rb.booking_date > CURDATE() ORDER BY rb.booking_date, rb.room_id LIMIT 1");
$api = array_column($v->req('api/rooms.php?date=' . $busy['booking_date'] . '&room=' . $busy['room_id'])['json']['slots'] ?? [], 'status');
$page = $v->req('rooms.php?date=' . $busy['booking_date'] . '&arc=' . substr($busy['floor'], -1))['body'];
preg_match('#<span class="room-name">' . preg_quote($busy['name'], '#') . '</span>.*?</tr>#s', $page, $rowHtml);
preg_match_all('#class="slot slot-(\w+)#', $rowHtml[0] ?? '', $m);
check('A33', 'rooms: slot statuses equal the PHP timetable row', $api && $api === $m[1] && in_array('taken', $api, true),
    "{$busy['name']} on {$busy['booking_date']}: api " . implode('', array_map(fn($s) => $s[0], $api)) . ' = page ' . implode('', array_map(fn($s) => $s[0], $m[1])));

$ben = new Client('ben');
$ben->login('ben@localhost', 'Member123!');
$benBooking = db_one("SELECT room_id, booking_date FROM room_bookings rb JOIN users u ON u.id = rb.user_id
                      WHERE u.email = 'ben@localhost' AND rb.status = 'confirmed' AND rb.booking_date >= CURDATE() ORDER BY booking_date LIMIT 1");
$used = (int) db_value("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) FROM room_bookings rb JOIN users u ON u.id = rb.user_id
                        WHERE u.email = 'ben@localhost' AND rb.booking_date = ? AND rb.status = 'confirmed'", [$benBooking['booking_date']]);
$r = $ben->req('api/rooms.php?date=' . $benBooking['booking_date'] . '&room=' . $benBooking['room_id']);
$statuses = array_count_values(array_column($r['json']['slots'] ?? [], 'status'));
check('A34', 'rooms: member sees own bookings and minutes used', ($r['json']['signedIn'] ?? false) === true && ($r['json']['minutesUsed'] ?? -1) === $used && $used > 0
    && (($statuses['yours'] ?? 0) + ($statuses['past'] ?? 0)) > 0,
    "ben on {$benBooking['booking_date']}: minutesUsed " . ($r['json']['minutesUsed'] ?? '?') . " (database $used), yours " . ($statuses['yours'] ?? 0) . ', past ' . ($statuses['past'] ?? 0));

// The real limit is still PHP's: posting the Base form over the cap is refused whatever the grid showed.
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$bt = $ben->csrf();
$free = array_values(array_filter($ben->req("api/rooms.php?date=$tomorrow&room=5")['json']['slots'] ?? [], fn($s) => $s['status'] === 'free'));
$ben->req('process/book-room.php', ['room_id' => '5', 'date' => $tomorrow, 'start' => $free[0]['start'], 'duration' => '60', 'csrf' => $bt]);
$ben->req('rooms.php');   // shows (and so clears) the "Booked" message before the next attempt
$before = (int) db_value("SELECT COUNT(*) FROM room_bookings rb JOIN users u ON u.id = rb.user_id WHERE u.email = 'ben@localhost' AND rb.status = 'confirmed'");
$ben->req('process/book-room.php', ['room_id' => '5', 'date' => $tomorrow, 'start' => $free[4]['start'], 'duration' => '30', 'csrf' => $bt]);
$after = (int) db_value("SELECT COUNT(*) FROM room_bookings rb JOIN users u ON u.id = rb.user_id WHERE u.email = 'ben@localhost' AND rb.status = 'confirmed'");
$capFlash = $flash($ben->req('rooms.php?date=' . $tomorrow)['body']);
$r = $ben->req("api/rooms.php?date=$tomorrow&room=5");
check('A35', 'rooms: over the daily cap the server refuses; the API then shows 60 used', $after === $before && str_contains($capFlash, 'The daily limit is 60 minutes')
    && ($r['json']['minutesUsed'] ?? 0) === 60, "\"$capFlash\" | bookings $before -> $after | api minutesUsed " . ($r['json']['minutesUsed'] ?? '?'));

$r = $v->req('rooms.php');
check('A36', 'Study Rooms still prints the PHP timetable and loads the Vue files', $r['code'] === 200 && str_contains($r['body'], 'data-availability')
    && preg_match_all('/class="slot slot-free"/', $r['body']) > 0 && str_contains($r['body'], 'js/vue/slot-cell.js') && str_contains($r['body'], 'js/vue/room-slots.js'),
    preg_match_all('/<a class="slot slot-free"/', $r['body']) . ' free links in the PHP table; slot-cell.js and room-slots.js included');

// ---- Summary --------------------------------------------------------------------------------------
$passed = count(array_filter($results, fn($x) => $x[2]));
echo str_repeat('-', 100) . "\n$passed of " . count($results) . " api checks passed.\n";
exit($passed === count($results) ? 0 : 1);
