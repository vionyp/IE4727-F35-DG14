<?php
// My Shelf: save a book for later (INSERT) or remove it (DELETE).
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$user = require_login('Please sign in to keep a shelf.');
verify_csrf();

$bookId = input_int($_POST, 'book_id');
$book = $bookId ? db_one("SELECT id, title FROM books WHERE id = ? AND status = 'approved'", [$bookId]) : null;
if (!$book) {
    flash('error', 'That book is not available.');
    redirect(back_url('account.php'));
}

if (input($_POST, 'action', 10) === 'remove') {
    db_exec('DELETE FROM shelf WHERE user_id = ? AND book_id = ?', [$user['id'], $bookId]);
    flash('success', $book['title'] . ' was removed from your shelf.');
} else {
    db_exec('INSERT IGNORE INTO shelf (user_id, book_id) VALUES (?, ?)', [$user['id'], $bookId]);
    flash('success', $book['title'] . ' is on your shelf. Find it any time in My Account.');
}
redirect(back_url('account.php'));
