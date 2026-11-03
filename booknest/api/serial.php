<?php
// Modern Enhancement: serial number lookup for the Vue screens on Add a book (assets/js/vue/serial-lookup.js).
//   GET serial=BNT-000123    the book with that serial number (any status), or "free" when there is none
// Members only, like the lookup on add-book.php and the serial panel on Browse. Read only: adding a book
// still goes through the Base form to process/add-book.php, which checks the format and that the serial
// is unused again, in PHP, before saving anything. Same messages as add-book.php.
require __DIR__ . '/../includes/api.php';
api_method('GET');
api_user('Please sign in again to look up a serial number.');

$serial = strtoupper(input($_GET, 'serial', 10));
$nextNumber = (int) db_value("SELECT COALESCE(MAX(CAST(SUBSTRING(serial_no, 5) AS UNSIGNED)), 100) + 1 FROM books");
$suggested = sprintf('BNT-%06d', $nextNumber);

if (!preg_match('/^BNT-\d{6}$/', $serial)) {
    api_error(422, 'Serial numbers look like BNT-000123: the letters BNT, a hyphen and six digits.', ['suggested' => $suggested]);
}

$found = db_one("SELECT b.*, c.name AS category, u.full_name AS added_by_name FROM books b
                 JOIN categories c ON c.id = b.category_id LEFT JOIN users u ON u.id = b.added_by
                 WHERE b.serial_no = ?", [$serial]);
if (!$found) {
    api_send(200, ['serial' => $serial, 'free' => true, 'suggested' => $suggested,
        'message' => 'No book has the serial number ' . $serial . '. You can add it below.']);
}

$counts = book_counts((int) $found['id']);
api_send(200, [
    'serial' => $serial,
    'free' => false,
    'suggested' => $suggested,
    'book' => [
        'title' => $found['title'],
        'author' => $found['author'],
        'category' => $found['category'],
        'isbn' => $found['isbn'] ?? 'None',
        'publisher' => $found['publisher'] ?? 'Not given',
        'status' => $found['status'],
        'statusLabel' => ucfirst($found['status']),
        'copies' => $found['stock'] . ' owned · ' . $counts['on_loan'] . ' on loan · ' . $counts['available'] . ' on the shelf',
        'published' => year_label((int) $found['published_year']),
        'pages' => (int) $found['pages'],
        'addedBy' => $found['added_by_name'] ?? 'Library staff',
        'added' => date('j M Y', strtotime($found['created_at'])),
        'cover' => url('assets/' . cover_file($found, true)),
        'coverAlt' => $found['cover_alt'],
        'url' => $found['status'] === 'approved' ? book_url((int) $found['id']) : '',
    ],
]);
