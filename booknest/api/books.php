<?php
// Modern Enhancement: books for the Vue live search on the Browse page (assets/js/vue/book-search.js).
// GET only, read only. Same matching rules as the server search on catalogue.php (title, author,
// serial number or ISBN; approved books only) and the same member-aware availability labels.
// Open to visitors, like the Browse page itself.
require __DIR__ . '/../includes/api.php';
api_method('GET');

const SEARCH_LIMIT = 100;   // the whole catalogue fits; the cap only protects the server

$q = input($_GET, 'q', 100);
$where = ["b.status = 'approved'"];
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.serial_no LIKE ? OR b.isbn LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
$rows = db_all('SELECT ' . card_columns() . ', c.slug AS category_slug, c.name AS category_name
                FROM books b JOIN categories c ON c.id = b.category_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY b.title LIMIT ' . SEARCH_LIMIT, $params);

$books = [];
foreach ($rows as $b) {
    [$text, $class, $note] = availability_label($b);
    $books[] = [
        'id' => (int) $b['id'],
        'title' => $b['title'],
        'author' => $b['author'],
        'hook' => $b['hook'],
        'rating' => (float) $b['rating'],
        'added' => $b['created_at'],
        'category' => $b['category_slug'],
        'categoryName' => $b['category_name'],
        'available' => max(0, (int) $b['stock'] - (int) $b['on_loan'] - (int) $b['held']),
        'availability' => ['text' => $text, 'class' => $class, 'note' => $note],
        'url' => book_url((int) $b['id']),
        'cover' => url('assets/' . cover_file($b, true)),
        'coverLarge' => url('assets/' . cover_file($b)),
        'coverAlt' => $b['cover_alt'],
    ];
}

$categories = array_map(fn($c) => ['slug' => $c['slug'], 'name' => $c['name']],
    db_all('SELECT slug, name FROM categories ORDER BY sort_order'));

api_send(200, ['query' => $q, 'count' => count($books), 'books' => $books, 'categories' => $categories]);
