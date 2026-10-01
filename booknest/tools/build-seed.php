<?php
// Builds sql/seed.sql, the book covers and the room plans.
// Run from the project root:  C:\xampp\php\php.exe tools\build-seed.php
// Classics get real sample pages from Project Gutenberg (downloaded once into tools/cache);
// modern, in copyright books get a short preview written by us (see tools/catalogue.php).
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/covers.php';

const WORDS_PER_PAGE = 125;
const SAMPLE_PAGES = 10;

// Categories, modern books, classics and pending member submissions.
require __DIR__ . '/catalogue.php';

$users = [
    ['Grace Lim', 'admin@localhost', 'Admin123!', 'admin', 400],
    ['Aisha Rahman', 'aisha@localhost', 'Member123!', 'member', 210],
    ['Ben Tan', 'ben@localhost', 'Member123!', 'member', 180],
    ['Chloe Wong', 'chloe@localhost', 'Member123!', 'member', 150],
    ['Daniel Ng', 'daniel@localhost', 'Member123!', 'member', 90],
    ['Elena Koh', 'elena@localhost', 'Member123!', 'member', 60],
    ['Farah Ismail', 'farah@localhost', 'Member123!', 'member', 40],
    ['Gavin Lee', 'gavin@localhost', 'Member123!', 'member', 21],
    ['Hana Sato', 'hana@localhost', 'Member123!', 'member', 7],
];

$rooms = [
    ['Folio', 2, 'Level 2', 'whiteboard, power'],
    ['Quill', 2, 'Level 2', 'power'],
    ['Atlas', 4, 'Level 3', 'whiteboard, power, screen'],
    ['Sonnet', 4, 'Level 3', 'whiteboard, power'],
    ['Vellum', 6, 'Level 4', 'whiteboard, power, screen'],
    ['Colophon', 6, 'Level 4', 'power, screen'],
];

// Downloads (once) and returns the plain text of a Project Gutenberg book.
function gutenberg_text(int $id): string
{
    $cache = __DIR__ . "/cache/pg$id.txt";
    if (!is_file($cache)) {
        @mkdir(__DIR__ . '/cache', 0777, true);
        $text = @file_get_contents("https://www.gutenberg.org/cache/epub/$id/pg$id.txt");
        if ($text === false || strlen($text) < 1000) {
            throw new RuntimeException("Could not download Gutenberg book $id");
        }
        file_put_contents($cache, $text);
    }
    return file_get_contents($cache);
}

// Cuts the licence header and footer and normalises line endings and quotes.
function strip_gutenberg(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/\*\*\*\s*START OF (THE|THIS) PROJECT GUTENBERG[^\n]*\n/i', $text, $m, PREG_OFFSET_CAPTURE)) {
        $text = substr($text, $m[0][1] + strlen($m[0][0]));
    }
    if (preg_match('/\*\*\*\s*END OF (THE|THIS) PROJECT GUTENBERG/i', $text, $m, PREG_OFFSET_CAPTURE)) {
        $text = substr($text, 0, $m[0][1]);
    }
    return $text;
}

// Returns the paragraphs of the opening of a book, starting at the paragraph with a marker.
function opening_paragraphs(string $text, array $markers): array
{
    $pos = false;
    foreach ($markers as $marker) {
        $pos = strpos($text, $marker);
        if ($pos === false) {
            $pos = stripos($text, $marker);
        }
        if ($pos !== false) {
            break;
        }
    }
    if ($pos === false) {
        throw new RuntimeException('Marker not found: ' . $markers[0]);
    }
    $start = strrpos(substr($text, 0, $pos), "\n\n");
    $start = $start === false ? 0 : $start;

    // Include one short heading paragraph just before the start (for example "CHAPTER I.").
    $before = rtrim(substr($text, 0, $start));
    $prevBreak = strrpos($before, "\n\n");
    $prev = trim(substr($before, $prevBreak === false ? 0 : $prevBreak));
    $prev = preg_replace('/\s+/', ' ', $prev);
    $heading = (mb_strlen($prev) > 2 && mb_strlen($prev) < 70 && !preg_match('/[a-z]\.$/', $prev)) ? $prev : '';

    $chunk = substr($text, $start, 60000);
    $paras = [];
    if ($heading !== '') {
        $paras[] = '# ' . clean_text($heading);
    }
    foreach (preg_split('/\n\s*\n/', $chunk) as $p) {
        $p = clean_text(preg_replace('/\s+/', ' ', trim($p)));
        if ($p === '' || preg_match('/^\[(Illustration|Footnote)/i', $p)) {
            continue;
        }
        $isHeading = mb_strlen($p) < 70 && (preg_match('/^(CHAPTER|Chapter|BOOK|LETTER|Letter|STAVE|PART|PROLOGUE|[IVXLC]+\.?\s*$)/', $p)
            || (mb_strtoupper($p) === $p && preg_match('/[A-Z]/', $p)));
        $paras[] = $isHeading ? '# ' . $p : $p;
    }
    return $paras;
}

// Removes Gutenberg markup such as _italics_ and [Illustration] notes.
function clean_text(string $p): string
{
    $p = preg_replace('/\[Illustration[^\]]*\]/i', '', $p);
    $p = preg_replace('/^\[|\]$/', '', trim($p));
    $p = preg_replace('/\[\d+\]/', '', $p);
    $p = preg_replace('/(?<![A-Za-z0-9])_([^_]+)_(?![A-Za-z0-9])/', '$1', $p);
    $p = str_replace('_', '', $p);
    return trim($p);
}

// Packs paragraphs into pages of roughly WORDS_PER_PAGE words.
function paginate(array $paras): array
{
    $limit = WORDS_PER_PAGE;
    $pages = [];
    $page = [];
    $count = 0;
    $hasText = false;
    $queue = $paras;
    while ($queue && count($pages) < SAMPLE_PAGES) {
        $p = array_shift($queue);
        if (str_starts_with($p, '# ')) {
            // A heading never sits at the bottom of a page.
            if ($count > $limit * 0.8) {
                $pages[] = $page;
                [$page, $count, $hasText] = [[], 0, false];
            }
            $page[] = $p;
            $count += 4;
            continue;
        }
        $words = str_word_count($p);
        if ($count + $words <= $limit * 1.1) {
            $page[] = $p;
            $count += $words;
            $hasText = true;
            continue;
        }
        // Fill the rest of this page with whole sentences and carry the remainder over.
        [$first, $rest] = split_sentences($p, $limit - $count, !$hasText);
        if ($first !== '') {
            $page[] = $first;
        }
        if ($rest !== '') {
            array_unshift($queue, $rest);
        }
        $pages[] = $page;
        [$page, $count, $hasText] = [[], 0, false];
    }
    if ($hasText && count($pages) < SAMPLE_PAGES) {
        $pages[] = $page;
    }
    return array_map(fn($pg) => implode("\n\n", $pg), array_slice($pages, 0, SAMPLE_PAGES));
}

// Splits a paragraph into the sentences that fit in $room words and the rest.
function split_sentences(string $p, int $room, bool $force): array
{
    $sentences = preg_split('/(?<=[.!?;:]|[.!?;:]["\'”’])\s+/u', $p);
    $first = [];
    $taken = 0;
    while ($sentences && $taken + str_word_count($sentences[0]) <= $room) {
        $taken += str_word_count($sentences[0]);
        $first[] = array_shift($sentences);
    }
    if (!$first && $force && $sentences) {
        $first[] = array_shift($sentences);
    }
    return [implode(' ', $first), implode(' ', $sentences)];
}

// Quotes a value for SQL.
function q(mixed $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $v) . "'";
}

// ---------------------------------------------------------------------------------------------
$sql = [];
$sql[] = "-- BookNest seed data. Generated by tools/build-seed.php on " . date('Y-m-d H:i');
$sql[] = "-- Dates are relative to the moment of import, so the demo always looks current.";
$sql[] = "-- Sample texts: public domain works courtesy of Project Gutenberg (www.gutenberg.org).";
$sql[] = "USE booknest;\nSET NAMES utf8mb4;\nSET time_zone = '+08:00';\n";

// Categories
$catIds = [];
$rows = [];
foreach ($categories as $i => [$name, $slug, $blurb]) {
    $catIds[$slug] = $i + 1;
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ', ' . q($slug) . ', ' . q($blurb) . ', ' . ($i + 1) . ')';
}
$sql[] = "INSERT INTO categories (id, name, slug, blurb, sort_order) VALUES\n  " . implode(",\n  ", $rows) . ";\n";
$catNames = array_combine(array_column($categories, 1), array_column($categories, 0));

// Users
$userIds = [];
$rows = [];
foreach ($users as $i => [$name, $email, $pw, $role, $daysAgo]) {
    $userIds[$email] = $i + 1;
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ', ' . q($email) . ', ' . q(password_hash($pw, PASSWORD_DEFAULT))
        . ', ' . q($role) . ", NOW() - INTERVAL $daysAgo DAY)";
}
$sql[] = "INSERT INTO users (id, full_name, email, password_hash, role, created_at) VALUES\n  " . implode(",\n  ", $rows) . ";\n";

// Books: modern titles first (written previews), then classics and pending books (real excerpts).
$all = [];
foreach ($modern as $b) {
    $all[] = $b + ['status' => 'approved', 'by' => null, 'gid' => null, 'markers' => []];
}
foreach ($classics as $b) {
    $all[] = ['gid' => $b[0], 'markers' => $b[1], 'title' => $b[2], 'author' => $b[3], 'cat' => 'classics', 'year' => $b[4],
              'pages' => $b[5], 'price' => $b[6], 'rating' => $b[7], 'stock' => $b[8], 'staff' => $b[9], 'featured' => 0,
              'days' => $b[10], 'hook' => $b[11], 'synopsis' => $b[12], 'status' => 'approved', 'by' => null,
              'isbn' => null, 'publisher' => 'BookNest Classics', 'format' => 'Paperback'];
}
foreach ($pending as $b) {
    $all[] = ['gid' => $b[0], 'markers' => $b[1], 'title' => $b[2], 'author' => $b[3], 'cat' => $b[4], 'year' => $b[5],
              'pages' => $b[6], 'price' => $b[7], 'rating' => 0.0, 'stock' => $b[8], 'staff' => 0, 'featured' => 0,
              'days' => 1, 'hook' => $b[10], 'synopsis' => $b[11], 'status' => 'pending', 'by' => $userIds[$b[9]],
              'isbn' => null, 'publisher' => null, 'format' => 'Paperback'];
}

// Writes the preview pages for a book that is still in copyright. Every word is ours, not the author's.
function preview_pages(array $b): array
{
    return [
        "# About this preview\n\n" . $b['title'] . ' is still in copyright, so BookNest does not show pages from the book itself.'
            . " This short preview was written by our librarians to help you decide whether it is for you.\n\n" . $b['hook'],
        "# What it is about\n\n" . $b['synopsis'],
        "# Three ideas you will meet\n\n" . implode("\n\n", $b['ideas']),
        "# Is it for you?\n\n" . $b['audience'] . "\n\n" . $b['format'] . ', ' . $b['pages'] . ' pages. Published by '
            . $b['publisher'] . '. First published in ' . $b['year'] . '. ISBN ' . $b['isbn'] . '.',
    ];
}

$rows = [];
$report = [];
foreach ($all as $i => $b) {
    $id = $i + 1;
    $serial = sprintf('BNT-%06d', 100 + $id);
    $isExcerpt = $b['gid'] !== null;
    $pages = $isExcerpt
        ? paginate(opening_paragraphs(strip_gutenberg(gutenberg_text($b['gid'])), $b['markers']))
        : preview_pages($b);
    $sample = implode("\n---PAGE---\n", $pages);
    // A generated cover is always written; a real cover in assets/covers/real/ replaces it when present.
    $cover = write_cover($serial, $b['title'], $b['author'], $id, $catNames[$b['cat']]);
    $alt = 'Cover of ' . $b['title'] . ' by ' . $b['author'];
    $report[] = sprintf('%2d %s %-44s %s pages=%d', $id, $serial, mb_substr($b['title'], 0, 44), $isExcerpt ? 'excerpt' : 'preview', count($pages));
    $rows[] = '(' . implode(', ', [$id, q($serial), q($b['isbn']), q($b['title']), q($b['author']), q($b['publisher']), q($b['format']),
        $catIds[$b['cat']], q($b['hook']), q($b['synopsis']), q($sample), q($isExcerpt ? 'excerpt' : 'preview'),
        $b['price'], $b['rating'], $b['year'], $b['pages'], $b['stock'], q($cover), q($alt),
        $b['featured'], $b['staff'], q($b['status']), $b['by'] ?? 'NULL', "NOW() - INTERVAL {$b['days']} DAY - INTERVAL " . ($id * 37 % 600) . ' MINUTE']) . ')';
}
$sql[] = "INSERT INTO books (id, serial_no, isbn, title, author, publisher, format, category_id, hook, synopsis, sample_text, sample_type, price, rating, published_year, pages, stock, cover_path, cover_alt, is_featured, is_staff_pick, status, added_by, created_at) VALUES\n  "
    . implode(",\n  ", $rows) . ";\n";

// Rooms and their floor plans
$rows = [];
@mkdir(APP_ROOT . '/assets/img/rooms', 0777, true);
foreach ($rooms as $i => [$name, $cap, $floor, $features]) {
    $path = 'img/rooms/' . strtolower($name) . '.svg';
    file_put_contents(APP_ROOT . '/assets/' . $path, room_svg($name, $cap, $features, $i + 1));
    $rows[] = '(' . ($i + 1) . ', ' . q($name) . ", $cap, " . q($floor) . ', ' . q($features) . ', ' . q($path) . ', 1)';
}
$sql[] = "INSERT INTO study_rooms (id, name, capacity, floor, features, image_path, is_active) VALUES\n  " . implode(",\n  ", $rows) . ";\n";

// Orders over the last 21 days (deterministic, so the seed is reproducible)
mt_srand(4727);
$approved = array_values(array_filter(array_keys($all), fn($k) => $all[$k]['status'] === 'approved'));
$members = array_slice(array_values($userIds), 1);
$guests = [['Priya Nair', 'priya@localhost'], ['Marcus Chen', 'marcus@localhost'], ['Siti Aminah', 'siti@localhost'], ['Tom Reyes', 'tom@localhost']];
$orderRows = [];
$itemRows = [];
$orderId = 0;
for ($d = 20; $d >= 0; $d--) {
    $n = mt_rand(0, 3) + ($d < 14 ? 1 : 0);
    for ($k = 0; $k < $n; $k++) {
        $orderId++;
        $isGuest = mt_rand(1, 4) === 1;
        if ($isGuest) {
            [$name, $email] = $guests[mt_rand(0, count($guests) - 1)];
            $uid = null;
        } else {
            $uid = $members[mt_rand(0, count($members) - 1)];
            [$name, $email] = [$users[$uid - 1][0], $users[$uid - 1][1]];
        }
        $lines = mt_rand(1, 3);
        $picked = [];
        $subtotal = 0;
        for ($l = 0; $l < $lines; $l++) {
            // Weighted toward the first books in each category so "top 5" has clear winners.
            $bk = $approved[min(count($approved) - 1, (int) floor((mt_rand(0, 1000) / 1000) ** 1.6 * count($approved)))];
            if (isset($picked[$bk])) {
                continue;
            }
            $qty = mt_rand(1, 4) === 1 ? 2 : 1;
            $picked[$bk] = $qty;
            $subtotal += $all[$bk]['price'] * $qty;
            $itemRows[] = "($orderId, " . ($bk + 1) . ", $qty, {$all[$bk]['price']})";
        }
        $method = mt_rand(0, 1) ? 'delivery' : 'pickup';
        $fee = ($method === 'delivery' && $subtotal < FREE_DELIVERY_FROM) ? DELIVERY_FEE : 0;
        $status = mt_rand(1, 9) === 1 ? 'failed' : 'paid';
        $orderRows[] = '(' . implode(', ', [$orderId, $uid ?? 'NULL', q($email), q($name), q('9' . mt_rand(1000000, 8999999)),
            q($method), $method === 'delivery' ? q(mt_rand(1, 99) . ' Jurong West Street ' . mt_rand(11, 99) . ', Singapore 6' . mt_rand(40000, 49999)) : 'NULL',
            'NULL', number_format($subtotal, 2, '.', ''), number_format($fee, 2, '.', ''), number_format($subtotal + $fee, 2, '.', ''),
            q($status), "NOW() - INTERVAL $d DAY - INTERVAL " . mt_rand(30, 600) . ' MINUTE']) . ')';
    }
}
$sql[] = "INSERT INTO orders (id, user_id, email, full_name, phone, delivery_method, address, note, subtotal, delivery_fee, total, payment_status, created_at) VALUES\n  "
    . implode(",\n  ", $orderRows) . ";\n";
$sql[] = "INSERT INTO order_items (order_id, book_id, qty, unit_price) VALUES\n  " . implode(",\n  ", $itemRows) . ";\n";

// Room bookings from 14 days ago to 3 days ahead, respecting every room rule.
$bookRows = [];
$aisha = $userIds['aisha@localhost'];
$ben = $userIds['ben@localhost'];
for ($d = -14; $d <= 3; $d++) {
    $roomBusy = [];
    $userMinutes = [];
    $userBusy = [];
    $wanted = mt_rand(7, 12);
    if ($d === 0) {
        // Ben has used his hour today, so the daily cap can be demonstrated with his account.
        $bookRows[] = "(1, $ben, CURDATE(), '10:00:00', '11:00:00', 'Group project', 'confirmed', NOW() - INTERVAL 1 DAY)";
        $roomBusy[1][] = [600, 660];
        $userMinutes[$ben] = 60;
        $userBusy[$ben][] = [600, 660];
    }
    if ($d === 1) {
        // Aisha's upcoming booking tomorrow, shown as "Yours" and cancellable in My Account.
        $bookRows[] = "(1, $aisha, CURDATE() + INTERVAL 1 DAY, '14:00:00', '15:00:00', 'Revision for finals', 'confirmed', NOW())";
        $roomBusy[1][] = [840, 900];
        $userMinutes[$aisha] = 60;
        $userBusy[$aisha][] = [840, 900];
    }
    for ($t = 0; $t < $wanted * 3 && $wanted > 0; $t++) {
        $room = mt_rand(1, 6);
        $uid = $members[mt_rand(0, count($members) - 1)];
        if ($d === 0 && $uid === $aisha) {
            continue; // keep Aisha's quota free today for the live demo
        }
        $hourWeights = [10, 11, 12, 13, 14, 14, 15, 15, 15, 16, 16, 17, 18, 19, 20];
        $startMin = $hourWeights[mt_rand(0, count($hourWeights) - 1)] * 60 + (mt_rand(0, 1) ? 30 : 0);
        $dur = mt_rand(0, 2) ? 60 : 30;
        $end = $startMin + $dur;
        if ($end > CLOSE_HOUR * 60 || ($userMinutes[$uid] ?? 0) + $dur > DAILY_CAP_MINUTES) {
            continue;
        }
        $clash = false;
        foreach ($roomBusy[$room] ?? [] as [$s, $e]) {
            $clash = $clash || ($s < $end && $e > $startMin);
        }
        foreach ($userBusy[$uid] ?? [] as [$s, $e]) {
            $clash = $clash || ($s < $end && $e > $startMin);
        }
        if ($clash) {
            continue;
        }
        $status = mt_rand(1, 10) === 1 ? 'cancelled' : 'confirmed';
        if ($status === 'confirmed') {
            $roomBusy[$room][] = [$startMin, $end];
            $userBusy[$uid][] = [$startMin, $end];
            $userMinutes[$uid] = ($userMinutes[$uid] ?? 0) + $dur;
        }
        $date = $d === 0 ? 'CURDATE()' : ('CURDATE() ' . ($d < 0 ? '- INTERVAL ' . (-$d) : '+ INTERVAL ' . $d) . ' DAY');
        $purposes = ['Group project', 'Quiet study', 'Interview practice', 'Book club', 'Tutoring', 'Presentation rehearsal', null];
        $bookRows[] = "($room, $uid, $date, '" . from_min($startMin) . ":00', '" . from_min($end) . ":00', "
            . q($purposes[mt_rand(0, count($purposes) - 1)]) . ", '$status', NOW() - INTERVAL " . (15 - $d) . ' DAY)';
        if (--$wanted <= 0) {
            break;
        }
    }
}
$sql[] = "INSERT INTO room_bookings (room_id, user_id, booking_date, start_time, end_time, purpose, status, created_at) VALUES\n  "
    . implode(",\n  ", $bookRows) . ";\n";

// Shelves
$shelf = [[$aisha, 1], [$aisha, 13], [$aisha, 25], [$aisha, 32], [$ben, 7], [$ben, 14], [$userIds['chloe@localhost'], 2]];
$sql[] = "INSERT INTO shelf (user_id, book_id, added_at) VALUES\n  "
    . implode(",\n  ", array_map(fn($s) => "({$s[0]}, {$s[1]}, NOW() - INTERVAL " . ($s[1] % 9) . ' DAY)', $shelf)) . ";\n";

// Questions asked to Paige over the last two weeks, so the admin dashboard has something to show.
$asked = [
    ['What time do you close today?', 'hours', 1], ['Opening hours', 'hours', 1], ['Where is my order?', 'order', 1],
    ['where is order 31', 'order', 1], ['Book a study room', 'rooms', 1], ['how do i cancel my room booking', 'rooms', 1],
    ['Delivery fees', 'delivery', 1], ['do you deliver to Jurong', 'delivery', 1], ['Do you have Atomic Habits?', 'book-search', 1],
    ['price of sapiens', 'book-search', 1], ['Recommend a book', 'recommend', 1], ['recommend a thriller', 'recommend', 1],
    ['my payment failed', 'payment', 1], ['I forgot my password', 'account', 1], ['Talk to a person', 'contact', 1],
    ['can i return a book', 'refund', 1], ['Do you sell gift cards?', 'unknown', 0], ['is there parking nearby', 'unknown', 0],
    ['can I bring coffee into the study rooms', 'rooms', 1], ['do you buy second hand books', 'unknown', 0],
    ['are you a robot', 'about', 1], ['can I print here', 'unknown', 0],
];
$rows = [];
foreach ($asked as $i => [$question, $intent, $answered]) {
    $times = $answered ? mt_rand(1, 4) : 1;
    for ($k = 0; $k < $times; $k++) {
        $uid = mt_rand(0, 2) ? $members[mt_rand(0, count($members) - 1)] : 'NULL';
        $rows[] = "($uid, " . q($question) . ', ' . q($intent) . ", $answered, NOW() - INTERVAL " . mt_rand(0, 13) . ' DAY - INTERVAL ' . mt_rand(10, 600) . ' MINUTE)';
    }
}
$sql[] = "INSERT INTO assistant_log (user_id, question, intent, answered, created_at) VALUES\n  " . implode(",\n  ", $rows) . ";\n";

file_put_contents(APP_ROOT . '/sql/seed.sql', implode("\n", $sql));

// Converts minutes after midnight to "HH:MM".
function from_min(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

echo implode("\n", $report), "\n\nWrote sql/seed.sql (", count($all), " books, $orderId orders, ", count($bookRows), " bookings)\n";
