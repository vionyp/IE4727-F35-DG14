<?php
// Admin actions: approve or reject submissions (and email the member), settle late fees, edit or delete books, open or close rooms.
// Changing the number of copies locks the book row, so it cannot race a member borrowing the last copy.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
require_admin();
verify_csrf();

$action = input($_POST, 'action', 20);
$id = input_int($_POST, 'id');

switch ($action) {
    case 'approve':
    case 'reject':
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $sub = db_one("SELECT b.title, b.serial_no, u.full_name, u.email FROM books b LEFT JOIN users u ON u.id = b.added_by
                       WHERE b.id = ? AND b.status = 'pending'", [$id]);
        if ($sub && db_exec("UPDATE books SET status = ?, created_at = IF(? = 'approved', NOW(), created_at) WHERE id = ? AND status = 'pending'", [$status, $status, $id])) {
            // Let the member who sent the book know the decision.
            if ($sub['email']) {
                send_mail($sub['email'], ($status === 'approved' ? 'Your book is in the library: ' : 'Your book suggestion: ') . $sub['title'],
                    mail_body(first_name($sub), $status === 'approved'
                        ? 'Good news. ' . $sub['title'] . ' (' . $sub['serial_no'] . "), the book you added, has been approved by a librarian.\n\n"
                            . "It is now in the catalogue, so you and every other member can find it, read the sample and borrow it.\nThank you for helping the library grow."
                        : 'Thank you for suggesting ' . $sub['title'] . ' (' . $sub['serial_no'] . ").\n\n"
                            . "A librarian has reviewed it and it will not be added to the catalogue this time.\n"
                            . 'You are welcome to suggest other books from Add a book on BookNest.'));
            }
            flash('success', $sub['title'] . ($status === 'approved' ? ' is approved and now in the catalogue.' : ' was rejected.')
                . ($sub['email'] ? ' ' . first_name($sub) . ' has been emailed.' : ''));
        } else {
            flash('error', 'That submission was already handled.');
        }
        redirect('admin.php#pending');

    case 'paid':
        // Settles the late fee of a returned loan at the front desk. A loan still out has a fee that keeps
        // growing, so it can only be settled once the book is back.
        $loan = db_one('SELECT l.*, b.title, u.full_name, u.email FROM loans l JOIN books b ON b.id = l.book_id
                        JOIN users u ON u.id = l.user_id WHERE l.id = ?', [$id]);
        $fee = $loan ? loan_fee($loan) : 0.0;
        if (!$loan) {
            flash('error', 'That loan no longer exists.');
        } elseif ($loan['status'] !== 'returned') {
            flash('error', $loan['title'] . ' is still out, so its fee is still growing. Settle it once the book is returned.');
        } elseif ($fee <= 0) {
            flash('error', $loan['title'] . ' came back on time, so there is no fee to settle.');
        } elseif (db_exec('UPDATE loans SET fee_cleared_at = NOW() WHERE id = ? AND fee_cleared_at IS NULL', [$id]) !== 1) {
            flash('error', 'That fee was already marked as paid.');
        } else {
            send_mail($loan['email'], 'Late fee paid: ' . $loan['title'],
                mail_body(first_name($loan), 'Thank you. We have received your late fee of ' . money($fee) . ' for ' . $loan['title']
                    . ', which came back ' . days_text(loan_days_late($loan)) . " late.\n\nThis is your receipt. Nothing more is owed for this loan."));
            flash('success', 'Marked ' . money($fee) . ' as paid for ' . $loan['full_name'] . ' (' . $loan['title'] . '). A receipt has been emailed.');
        }
        redirect('admin.php#fees');

    case 'update':
        $book = db_one('SELECT id, title FROM books WHERE id = ?', [$id]);
        $price = input($_POST, 'price', 10);
        $stock = input($_POST, 'stock', 4);
        if (!$book) {
            flash('error', 'That book no longer exists.');
        } elseif (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $price) || (float) $price > 500) {
            flash('error', 'Replacement value for ' . $book['title'] . ' must be from 0.00 to 500.00.');
        } elseif (!ctype_digit($stock) || (int) $stock > 999) {
            flash('error', 'Copies of ' . $book['title'] . ' must be a whole number from 0 to 999.');
        } else {
            $db = db();
            $db->begin_transaction();
            try {
                db_one('SELECT id FROM books WHERE id = ? FOR UPDATE', [$id]);
                $counts = book_counts($id);
                $out = $counts['on_loan'] + $counts['held'];
                if ((int) $stock < $out) {
                    $db->rollback();
                    flash('error', $book['title'] . ' has ' . $out . ($out === 1 ? ' copy' : ' copies') . ' out on loan or held for the queue, so the library must keep at least that many.');
                    redirect('admin.php#books');
                }
                $staff = empty($_POST['is_staff_pick']) ? 0 : 1;
                db_exec('UPDATE books SET price = ?, stock = ?, is_staff_pick = ? WHERE id = ?', [$price, (int) $stock, $staff, $id]);
                if (!empty($_POST['is_featured'])) {
                    db_exec('UPDATE books SET is_featured = (id = ?)', [$id]);
                }
                // New copies go straight to the people waiting for this book.
                $offers = queue_offer_next($id);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollback();
                throw $e;
            }
            send_queue_offers($offers);
            flash('success', 'Saved changes to ' . $book['title'] . '.' . ($offers ? ' ' . count($offers) . ' new '
                . (count($offers) === 1 ? 'copy was' : 'copies were') . ' offered to the queue.' : ''));
        }
        redirect('admin.php#books');

    case 'delete':
        $book = db_one('SELECT id, title FROM books WHERE id = ?', [$id]);
        if (!$book) {
            flash('error', 'That book no longer exists.');
        } elseif (db_value('SELECT COUNT(*) FROM loans WHERE book_id = ?', [$id]) > 0) {
            flash('error', $book['title'] . ' has been borrowed before, so it is kept for the loan records. Set its copies to 0 instead.');
        } else {
            db_exec('DELETE FROM books WHERE id = ?', [$id]);
            flash('success', $book['title'] . ' was deleted.');
        }
        redirect('admin.php#books');

    case 'room':
        $room = db_one('SELECT id, name, is_active FROM study_rooms WHERE id = ?', [$id]);
        if ($room) {
            db_exec('UPDATE study_rooms SET is_active = ? WHERE id = ?', [$room['is_active'] ? 0 : 1, $id]);
            flash('success', $room['name'] . ($room['is_active'] ? ' is now closed to new bookings.' : ' is open for bookings again.'));
        }
        redirect('admin.php#rooms');

    default:
        flash('error', 'Unknown action.');
        redirect('admin.php');
}
