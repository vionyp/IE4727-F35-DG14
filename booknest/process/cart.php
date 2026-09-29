<?php
// Cart actions: add, buy now (add then go to checkout), update quantity, remove.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$action = input($_POST, 'action', 10);
$bookId = input_int($_POST, 'book_id');
$book = $bookId ? db_one("SELECT id, title, stock FROM books WHERE id = ? AND status = 'approved'", [$bookId]) : null;

if (!$book) {
    flash('error', 'That book is not available.');
    redirect(back_url('catalogue.php'));
}

$inCart = cart()[$bookId] ?? 0;
$limit = min((int) $book['stock'], MAX_QTY_PER_BOOK);

switch ($action) {
    case 'add':
    case 'buy':
        if ($limit <= 0) {
            flash('error', $book['title'] . ' is out of stock at the moment.');
            redirect(back_url('book.php?id=' . $bookId));
        }
        if ($inCart >= $limit) {
            flash('info', 'You already have the most copies of ' . $book['title'] . ' we can sell you (' . $limit . ').');
        } else {
            cart_set($bookId, $inCart + 1);
            if ($action === 'add') {
                flash('success', $book['title'] . ' is in your cart.');
            }
        }
        redirect($action === 'buy' ? 'checkout.php' : back_url('book.php?id=' . $bookId));

    case 'update':
        $qty = input_int($_POST, 'qty');
        if ($qty < 1 || $qty > $limit) {
            flash('error', 'Choose a quantity from 1 to ' . max(1, $limit) . ' for ' . $book['title'] . '.');
        } else {
            cart_set($bookId, $qty);
            flash('success', 'Updated ' . $book['title'] . ' to ' . $qty . ($qty === 1 ? ' copy.' : ' copies.'));
        }
        redirect('checkout.php');

    case 'remove':
        cart_set($bookId, 0);
        flash('success', $book['title'] . ' was removed from your cart.');
        redirect('checkout.php');

    default:
        flash('error', 'Unknown cart action.');
        redirect('checkout.php');
}
