<?php
// Lending actions for members: borrow a book, join or leave a queue, return a loan.
// The rules live in includes/loans.php; this endpoint validates the request, calls them and redirects.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$user = require_login('Please sign in to borrow books.');
verify_csrf();

$action = input($_POST, 'action', 10);
$bookId = input_int($_POST, 'book_id');
$bookPage = 'book.php?id=' . $bookId;

switch ($action) {
    case 'borrow':
        $date = input($_POST, 'collection_date', 10);
        [$problem, $loanId] = borrow_book($user, $bookId, $date);
        if ($problem) {
            keep_form('borrow', $_POST, ['collection_date' => $problem]);
            flash('error', $problem);
            redirect('borrow.php?id=' . $bookId);
        }
        $loan = db_one('SELECT l.collection_date, l.due_date, b.title FROM loans l JOIN books b ON b.id = l.book_id WHERE l.id = ?', [$loanId]);
        flash('success', 'Borrowed. Collect ' . $loan['title'] . ' at the front desk from ' . long_date($loan['collection_date'])
            . '. It is due back on ' . long_date($loan['due_date']) . '. A confirmation email is on its way.');
        redirect('account.php?loan=' . $loanId . '#loans');

    case 'queue':
        [$problem, $position] = join_queue($user, $bookId);
        if ($problem) {
            flash('error', $problem);
        } else {
            flash('success', 'You are in the queue: #' . $position . ' in line. We will email you when a copy is yours, and hold it for '
                . QUEUE_HOLD_DAYS . ' days.');
        }
        redirect($bookPage);

    case 'leave':
        $problem = leave_queue($user, $bookId);
        flash($problem ? 'error' : 'success', $problem ?: 'You have left the queue.');
        redirect(back_url($bookPage));

    case 'return':
        [$problem, $loan] = return_loan($user, input_int($_POST, 'loan_id'));
        if ($problem) {
            flash('error', $problem);
        } elseif ($loan['collection_date'] > date('Y-m-d')) {
            flash('success', 'Your reservation for ' . $loan['title'] . ' is cancelled.');
        } else {
            $fee = loan_fee($loan);
            flash('success', 'Returned ' . $loan['title'] . '. ' . ($fee > 0
                ? 'It was ' . days_text(loan_days_late($loan)) . ' late, so a late fee of ' . money($fee) . ' was added to your account.'
                : 'Thank you for bringing it back on time.'));
        }
        redirect('account.php#loans');

    default:
        flash('error', 'Unknown action.');
        redirect($bookId ? $bookPage : 'account.php');
}
