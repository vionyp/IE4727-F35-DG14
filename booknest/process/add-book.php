<?php
// Add a book (members): validates every field, checks the serial is unique, generates a cover,
// and saves the book as pending until a librarian approves it.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$user = require_login('Please sign in to add a book.');
verify_csrf();

$f = [
    'serial_no' => strtoupper(input($_POST, 'serial_no', 10)),
    'title' => preg_replace('/\s+/', ' ', input($_POST, 'title', 150)),
    'author' => preg_replace('/\s+/', ' ', input($_POST, 'author', 100)),
    'category_id' => input_int($_POST, 'category_id'),
    'published_year' => input($_POST, 'published_year', 5),
    'pages' => input($_POST, 'pages', 5),
    'stock' => input($_POST, 'stock', 4),
    'hook' => input($_POST, 'hook', 200),
    'synopsis' => input($_POST, 'synopsis', 2000),
    'sample_text' => str_replace(["\r\n", "\r"], "\n", input($_POST, 'sample_text', 20000)),
];
$thisYear = (int) date('Y');

$errors = [];
if (!preg_match('/^BNT-\d{6}$/', $f['serial_no'])) {
    $errors['serial_no'] = 'Use the format BNT- followed by six digits, for example BNT-000245.';
} elseif (db_value('SELECT 1 FROM books WHERE serial_no = ?', [$f['serial_no']])) {
    $errors['serial_no'] = 'Serial ' . $f['serial_no'] . ' is already in the library. Please check the number.';
}
if (mb_strlen($f['title']) < 1) {
    $errors['title'] = 'Enter the book title.';
}
if (mb_strlen($f['author']) < 2) {
    $errors['author'] = 'Enter the author\'s name.';
}
if (!db_value('SELECT 1 FROM categories WHERE id = ?', [$f['category_id']])) {
    $errors['category_id'] = 'Choose a category.';
}
if (!ctype_digit($f['published_year']) || (int) $f['published_year'] < 1450 || (int) $f['published_year'] > $thisYear) {
    $errors['published_year'] = "Enter a year from 1450 to $thisYear.";
}
if (!ctype_digit($f['pages']) || (int) $f['pages'] < 1 || (int) $f['pages'] > 5000) {
    $errors['pages'] = 'Enter the number of pages, from 1 to 5000.';
}
if (!ctype_digit($f['stock']) || (int) $f['stock'] > 999) {
    $errors['stock'] = 'Enter how many copies you can provide, from 0 to 999.';
}
if (mb_strlen($f['synopsis']) < 40) {
    $errors['synopsis'] = 'Write a synopsis of at least 40 characters so readers know what the book is about.';
}
if ($f['sample_text'] !== '' && mb_strlen($f['sample_text']) < 200) {
    $errors['sample_text'] = 'A sample should be at least 200 characters, or leave it empty.';
}

if ($errors) {
    keep_form('addbook', $_POST, $errors);
    flash('error', 'Please fix the highlighted fields. Nothing has been saved yet.');
    redirect('add-book.php#add');
}

$hook = $f['hook'] !== '' ? $f['hook'] : mb_substr($f['synopsis'], 0, 120) . (mb_strlen($f['synopsis']) > 120 ? '...' : '');
$category = db_value('SELECT name FROM categories WHERE id = ?', [$f['category_id']]);
$seed = (int) substr($f['serial_no'], 4);
$cover = write_cover($f['serial_no'], $f['title'], $f['author'], $seed, (string) $category);

db_exec("INSERT INTO books (serial_no, title, author, category_id, hook, synopsis, sample_text, rating, published_year,
                            pages, stock, cover_path, cover_alt, status, added_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0.0, ?, ?, ?, ?, ?, 'pending', ?)",
    [$f['serial_no'], $f['title'], $f['author'], $f['category_id'], $hook, $f['synopsis'], $f['sample_text'] ?: null,
     (int) $f['published_year'], (int) $f['pages'], (int) $f['stock'], $cover,
     'Cover of ' . $f['title'] . ' by ' . $f['author'] . ': title lettering on a geometric design', $user['id']]);

flash('success', 'Thank you. ' . $f['title'] . ' (' . $f['serial_no'] . ') has been sent to our librarians. It will appear in the catalogue once approved.');
redirect('account.php#submissions');
