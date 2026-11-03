<?php
// Modern Enhancement: one book's lending state for the Vue <BorrowBox> on the book page (assets/js/vue/borrow-box.js).
//   GET  id=N                                       the book's copies and what the viewer may do with it
//   POST action=borrow, id, collection_date, csrf   borrow it (same as the Confirm loan form on borrow.php)
//   POST action=queue|leave, id, csrf               join or leave its queue
//   POST action=save|unsave, id, csrf               add it to or remove it from My Shelf
// Open to visitors for GET (they see the copies and a sign in link). Every POST checks the method, the
// sign in and the CSRF token, in that order, then calls the same borrow_book(), join_queue() and
// leave_queue() as process/loan.php, so the limits (6 loans, overdue books, S$5 in fees, one copy per
// title, queue order, hold dates) are decided in PHP exactly as before, with the same messages.
// The shelf uses the same two statements as process/shelf.php.
require __DIR__ . '/../includes/api.php';

// Builds the book box data for the viewer. my_loans() and my_queue() are cached per request, and
// nothing reads them before an action, so the data sent back after an action is already up to date.
function book_payload(int $bookId): array
{
    $book = db_one("SELECT b.id, b.title, b.author, b.stock, b.sample_type, " . availability_columns() . " FROM books b
                    WHERE b.id = ? AND b.status = 'approved'", [$bookId]);
    if (!$book) {
        api_error(404, 'That book is not in the catalogue.');
    }
    $user = current_user();
    $uid = $user ? (int) $user['id'] : 0;
    $loan = my_loans()[$bookId] ?? null;
    $entry = my_queue()[$bookId] ?? null;
    $offered = $entry && $entry['status'] === 'offered';
    $available = max(0, (int) $book['stock'] - (int) $book['on_loan'] - (int) $book['held']);
    [$text, $class, $note] = availability_label($book);

    // The same collection dates as borrow.php: up to a week ahead, or up to the end of a hold.
    $dates = collection_dates();
    if ($offered) {
        $dates = array_values(array_filter($dates, fn($d) => $d <= hold_until($entry)));
    }
    $today = date('Y-m-d');

    return [
        'book' => ['id' => (int) $book['id'], 'title' => $book['title'], 'stock' => (int) $book['stock'], 'available' => $available, 'sampleWord' => sample_word($book),
            'availability' => ['text' => $text, 'class' => $class, 'note' => $note]],
        'signedIn' => (bool) $user,
        'onShelf' => $uid && (bool) db_value('SELECT 1 FROM shelf WHERE user_id = ? AND book_id = ?', [$uid, $bookId]),
        'openLoans' => count(my_loans()),
        'loan' => $loan ? ['id' => (int) $loan['id'], 'state' => loan_state($loan), 'collectLong' => long_date($loan['collection_date']),
            'dueLong' => long_date($loan['due_date']), 'url' => url('account.php?loan=' . (int) $loan['id'] . '#loans')] : null,
        'queue' => $entry ? ['status' => $entry['status'], 'position' => (int) $entry['position'],
            'holdUntilLong' => $offered ? long_date(hold_until($entry)) : ''] : null,
        'blocks' => $uid && !$loan ? borrow_blocks($uid) : [],
        'dates' => array_map(fn($d) => ['value' => $d, 'label' => ($d === $today ? 'Today, ' : '') . short_date($d),
            'dueShort' => short_date(due_date_for($d)), 'dueLong' => long_date(due_date_for($d))], $dates),
        'rules' => ['loanDays' => LOAN_DAYS, 'holdDays' => QUEUE_HOLD_DAYS, 'maxLoans' => MAX_LOANS,
            'hours' => sprintf('%02d:00 and %02d:00', OPEN_HOUR, CLOSE_HOUR)],
        'links' => ['signin' => url('sign-in.php?return=' . rawurlencode(book_url($bookId))), 'loans' => url('account.php#loans')],
    ];
}

$method = api_method('GET', 'POST');
$bookId = input_int($method === 'POST' ? $_POST : $_GET, 'id');

if ($method === 'GET') {
    api_send(200, book_payload($bookId));
}

$user = api_user('Please sign in again to borrow or save books.');
api_csrf();
$action = input($_POST, 'action', 10);
$title = (string) db_value("SELECT title FROM books WHERE id = ? AND status = 'approved'", [$bookId]);
if ($title === '') {
    api_error(404, 'That book is not in the catalogue.');
}

switch ($action) {
    case 'borrow':
        [$problem, $loanId] = borrow_book($user, $bookId, input($_POST, 'collection_date', 10));
        if ($problem) {
            api_error(422, $problem, book_payload($bookId));
        }
        $loan = db_one('SELECT collection_date, due_date FROM loans WHERE id = ?', [$loanId]);
        $message = 'Borrowed. Collect ' . $title . ' at the front desk from ' . long_date($loan['collection_date'])
            . '. It is due back on ' . long_date($loan['due_date']) . '. A confirmation email is on its way.';
        break;

    case 'queue':
        [$problem, $position] = join_queue($user, $bookId);
        if ($problem) {
            api_error(422, $problem, book_payload($bookId));
        }
        $message = 'You are in the queue: #' . $position . ' in line. We will email you when a copy is yours, and hold it for '
            . QUEUE_HOLD_DAYS . ' days.';
        break;

    case 'leave':
        if ($problem = leave_queue($user, $bookId)) {
            api_error(422, $problem, book_payload($bookId));
        }
        $message = 'You have left the queue.';
        break;

    case 'save':
        db_exec('INSERT IGNORE INTO shelf (user_id, book_id) VALUES (?, ?)', [$user['id'], $bookId]);
        $message = $title . ' is on your shelf. Find it any time in My Account.';
        break;

    case 'unsave':
        db_exec('DELETE FROM shelf WHERE user_id = ? AND book_id = ?', [$user['id'], $bookId]);
        $message = $title . ' was removed from your shelf.';
        break;

    default:
        api_error(400, 'Unknown action.', book_payload($bookId));
}

api_send(200, ['message' => $message, 'action' => $action] + book_payload($bookId));
