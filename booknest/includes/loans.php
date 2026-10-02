<?php
// Lending rules shared by every page and endpoint: availability, loans, the queue and late fees.
// PHP is the authority. Anything that changes how many copies are on the shelf locks the book row
// first (SELECT ... FOR UPDATE), so two members can never take the same last copy.

// Returns the dates a member can choose to collect a book: today up to COLLECT_AHEAD_DAYS ahead.
function collection_dates(): array
{
    $dates = [];
    for ($i = 0; $i <= COLLECT_AHEAD_DAYS; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i day", strtotime('today')));
    }
    return $dates;
}

// Returns the due date of a loan collected on the given date.
function due_date_for(string $collectionDate): string
{
    return date('Y-m-d', strtotime($collectionDate . ' +' . LOAN_DAYS . ' day'));
}

// Returns the number of whole days between two Y-m-d dates (positive when $to is later).
function days_between(string $from, string $to): int
{
    return (int) round((strtotime($to . ' 12:00') - strtotime($from . ' 12:00')) / 86400);
}

// Returns "1 day" or "3 days".
function days_text(int $n): string
{
    return $n . ($n === 1 ? ' day' : ' days');
}

// Returns how many days a loan is (or was) late: counted up to today, or up to the return date.
function loan_days_late(array $loan): int
{
    $end = $loan['returned_date'] ?: date('Y-m-d');
    return max(0, days_between($loan['due_date'], $end));
}

// Returns the late fee of a loan, worked out live from its dates (never stored).
function loan_fee(array $loan): float
{
    return loan_days_late($loan) * LATE_FEE_PER_DAY;
}

// Returns the SQL expression for a loan's late fee, so totals use exactly the same rule as loan_fee().
function loan_fee_sql(string $alias = 'l'): string
{
    return "(GREATEST(0, DATEDIFF(COALESCE($alias.returned_date, CURDATE()), $alias.due_date)) * " . number_format(LATE_FEE_PER_DAY, 2, '.', '') . ')';
}

// Returns the state of a loan right now: reserved, active, overdue or returned.
function loan_state(array $loan): string
{
    $today = date('Y-m-d');
    if ($loan['returned_date']) {
        return 'returned';
    }
    if ($loan['collection_date'] > $today) {
        return 'reserved';
    }
    return $loan['due_date'] < $today ? 'overdue' : 'active';
}

// Returns the total late fees a member owes that have not been cleared at the desk.
function fees_owed(int $userId): float
{
    return (float) db_value('SELECT COALESCE(SUM(' . loan_fee_sql() . '), 0) FROM loans l
                             WHERE l.user_id = ? AND l.fee_cleared_at IS NULL', [$userId]);
}

// Returns the copy counts of one book: owned, on loan, held for the queue, waiting, available, next due date.
function book_counts(int $bookId): array
{
    $row = db_one("SELECT b.stock,
                          (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.status <> 'returned') AS on_loan,
                          (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = b.id AND q.status = 'offered') AS held,
                          (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = b.id AND q.status = 'waiting') AS waiting,
                          (SELECT MIN(l.due_date) FROM loans l WHERE l.book_id = b.id AND l.status <> 'returned') AS next_due
                   FROM books b WHERE b.id = ?", [$bookId]);
    if (!$row) {
        return ['stock' => 0, 'on_loan' => 0, 'held' => 0, 'waiting' => 0, 'available' => 0, 'next_due' => null];
    }
    $row = array_map(fn($v) => is_numeric($v) ? (int) $v : $v, $row);
    $row['available'] = max(0, $row['stock'] - $row['on_loan'] - $row['held']);
    return $row;
}

// Returns the SQL columns that give a book card its live copy counts (used by card_columns()).
function availability_columns(string $alias = 'b'): string
{
    return "(SELECT COUNT(*) FROM loans l WHERE l.book_id = $alias.id AND l.status <> 'returned') AS on_loan,
            (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = $alias.id AND q.status = 'offered') AS held,
            (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = $alias.id AND q.status = 'waiting') AS waiting,
            (SELECT MIN(l.due_date) FROM loans l WHERE l.book_id = $alias.id AND l.status <> 'returned') AS next_due";
}

// Returns the SQL expression for "copies on the shelf now", for sorting the catalogue.
function available_sql(string $alias = 'b'): string
{
    return "($alias.stock - (SELECT COUNT(*) FROM loans l WHERE l.book_id = $alias.id AND l.status <> 'returned')
                         - (SELECT COUNT(*) FROM book_queue q WHERE q.book_id = $alias.id AND q.status = 'offered'))";
}

// Returns the signed in member's open loans, keyed by book id (cached for the request).
function my_loans(): array
{
    static $loans = null;
    if ($loans === null) {
        $loans = [];
        if ($uid = user_id()) {
            foreach (db_all("SELECT * FROM loans WHERE user_id = ? AND status <> 'returned'", [$uid]) as $l) {
                $loans[(int) $l['book_id']] = $l;
            }
        }
    }
    return $loans;
}

// Returns the signed in member's queue places (waiting or offered), keyed by book id, with their position.
function my_queue(): array
{
    static $queue = null;
    if ($queue === null) {
        $queue = [];
        if ($uid = user_id()) {
            foreach (db_all("SELECT * FROM book_queue WHERE user_id = ? AND status IN ('waiting','offered')", [$uid]) as $q) {
                $q['position'] = queue_position($q);
                $queue[(int) $q['book_id']] = $q;
            }
        }
    }
    return $queue;
}

// Returns a member's place in line (1 = next), counting only people still waiting ahead of them.
function queue_position(array $entry): int
{
    if ($entry['status'] === 'offered') {
        return 0;
    }
    return 1 + (int) db_value("SELECT COUNT(*) FROM book_queue WHERE book_id = ? AND status = 'waiting'
                               AND (queued_at < ? OR (queued_at = ? AND id < ?))",
        [$entry['book_id'], $entry['queued_at'], $entry['queued_at'], $entry['id']]);
}

// Returns the last day a queue offer can be taken up (Y-m-d).
function hold_until(array $entry): string
{
    return date('Y-m-d', strtotime($entry['offered_at'] . ' +' . QUEUE_HOLD_DAYS . ' day'));
}

// Describes a book's availability for the person looking at it: [text, css class, extra note].
// $b needs id, stock, on_loan, held, waiting and next_due (see availability_columns()).
function availability_label(array $b): array
{
    $id = (int) $b['id'];
    $waiting = (int) $b['waiting'];
    $queueNote = $waiting > 0 ? $waiting . ($waiting === 1 ? ' person' : ' people') . ' waiting' : '';

    if ($loan = my_loans()[$id] ?? null) {
        $state = loan_state($loan);
        if ($state === 'reserved') {
            return ['Collect from ' . short_date($loan['collection_date']), 'is-mine', ''];
        }
        $days = days_between(date('Y-m-d'), $loan['due_date']);
        if ($days > 0) {
            return ['Due back in ' . $days . ($days === 1 ? ' day' : ' days'), 'is-mine', ''];
        }
        if ($days === 0) {
            return ['Due today', 'is-low', ''];
        }
        return [(-$days) . ((-$days) === 1 ? ' day' : ' days') . ' overdue', 'is-out', ''];
    }
    if ($entry = my_queue()[$id] ?? null) {
        if ($entry['status'] === 'offered') {
            return ['Ready for you until ' . short_date(hold_until($entry)), 'is-in', ''];
        }
        $others = max(0, $waiting - 1);
        return ['You are #' . $entry['position'] . ' in line', 'is-low', $others ? $others . ' other' . ($others === 1 ? ' person' : ' people') . ' waiting' : ''];
    }
    $available = (int) $b['stock'] - (int) $b['on_loan'] - (int) $b['held'];
    if ((int) $b['stock'] <= 0) {
        return ['No copies yet', 'is-out', ''];
    }
    if ($available > 0) {
        return ['Available to borrow', 'is-in', ''];
    }
    if ((int) $b['on_loan'] === 0) {
        return ['On hold for the next reader', 'is-low', $queueNote];
    }
    if ($b['next_due'] < date('Y-m-d')) {
        return ['Borrowed, return overdue', 'is-low', $queueNote];
    }
    return ['Borrowed until ' . short_date($b['next_due']), 'is-low', $queueNote];
}

// Returns the HTML pill (and queue note) for a book's availability.
function availability_html(array $b, string $extraClass = ''): string
{
    [$text, $class, $note] = availability_label($b);
    return '<span class="avail ' . $class . ($extraClass ? ' ' . $extraClass : '') . '">' . e($text) . '</span>'
        . ($note ? '<span class="avail-note">' . e($note) . '</span>' : '');
}

// Gives free copies of a book to the people at the front of its queue. Call it inside a transaction,
// after locking the book row. Returns the offers made, so the emails can be sent after the commit.
function queue_offer_next(int $bookId): array
{
    $offers = [];
    $counts = book_counts($bookId);
    $free = $counts['available'];
    while ($free > 0) {
        $next = db_one("SELECT q.id, q.user_id, u.full_name, u.email, b.title FROM book_queue q
                        JOIN users u ON u.id = q.user_id JOIN books b ON b.id = q.book_id
                        WHERE q.book_id = ? AND q.status = 'waiting' ORDER BY q.queued_at, q.id LIMIT 1", [$bookId]);
        if (!$next) {
            break;
        }
        db_exec("UPDATE book_queue SET status = 'offered', offered_at = NOW() WHERE id = ? AND status = 'waiting'", [$next['id']]);
        $offers[] = $next;
        $free--;
    }
    return $offers;
}

// Emails each member who has just been offered a copy from the queue.
function send_queue_offers(array $offers): void
{
    $until = long_date(date('Y-m-d', strtotime('+' . QUEUE_HOLD_DAYS . ' day')));
    foreach ($offers as $o) {
        send_mail($o['email'], 'Your reserved book is ready: ' . $o['title'],
            mail_body(first_name($o), "Good news. A copy of " . $o['title'] . " is back on the shelf and we are holding it for you.\n\n"
                . "Borrow it on BookNest by $until, then collect it at the front desk on the date you choose."
                . "\nIf you do not borrow it by then, the copy passes to the next person in the queue."));
    }
}

// Keeps stored statuses in step with the calendar and moves the queue along. There is no scheduler
// on XAMPP, so this runs once per request. Fees never depend on it: they are always worked out live.
function loans_sync(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    db_exec("UPDATE loans SET status = 'active' WHERE status = 'reserved' AND collection_date <= CURDATE() AND due_date >= CURDATE()");
    db_exec("UPDATE loans SET status = 'overdue' WHERE status IN ('reserved','active') AND due_date < CURDATE()");

    // Books where a hold has run out, or where a copy is free while people are still waiting.
    $hold = (int) QUEUE_HOLD_DAYS;
    $books = db_all("SELECT DISTINCT q.book_id FROM book_queue q
                     WHERE (q.status = 'offered' AND q.offered_at < NOW() - INTERVAL $hold DAY)
                        OR (q.status = 'waiting' AND " . available_sql_for_id('q.book_id') . " > 0)");
    foreach ($books as $row) {
        $bookId = (int) $row['book_id'];
        $db = db();
        $db->begin_transaction();
        try {
            db_one('SELECT id FROM books WHERE id = ? FOR UPDATE', [$bookId]);
            $expired = db_all("SELECT q.id, u.full_name, u.email, b.title FROM book_queue q JOIN users u ON u.id = q.user_id
                               JOIN books b ON b.id = q.book_id
                               WHERE q.book_id = ? AND q.status = 'offered' AND q.offered_at < NOW() - INTERVAL $hold DAY", [$bookId]);
            foreach ($expired as $x) {
                db_exec("UPDATE book_queue SET status = 'expired' WHERE id = ? AND status = 'offered'", [$x['id']]);
            }
            $offers = queue_offer_next($bookId);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        foreach ($expired as $x) {
            send_mail($x['email'], 'Your hold on ' . $x['title'] . ' has ended',
                mail_body(first_name($x), 'We held a copy of ' . $x['title'] . ' for you for ' . QUEUE_HOLD_DAYS . " days, but it was not borrowed in time,\n"
                    . 'so it has passed to the next person in the queue. You are welcome to join the queue again from the book page.'));
        }
        send_queue_offers($offers);
    }
}

// Returns the "copies on the shelf" SQL for a book id expression (used where no books alias exists).
function available_sql_for_id(string $idExpr): string
{
    return "((SELECT stock FROM books WHERE id = $idExpr)
             - (SELECT COUNT(*) FROM loans l WHERE l.book_id = $idExpr AND l.status <> 'returned')
             - (SELECT COUNT(*) FROM book_queue o WHERE o.book_id = $idExpr AND o.status = 'offered'))";
}

// Borrows a book for a member. Returns [error message or '', loan id]. Safe against two members
// taking the last copy at the same moment: the book row stays locked until the loan is saved.
function borrow_book(array $user, int $bookId, string $collectionDate): array
{
    if (!in_array($collectionDate, collection_dates(), true)) {
        return ['Choose a collection date from today up to ' . COLLECT_AHEAD_DAYS . ' days ahead.', 0];
    }
    $uid = (int) $user['id'];
    $db = db();
    $db->begin_transaction();
    try {
        db_one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$uid]);
        $book = db_one("SELECT id, title FROM books WHERE id = ? AND status = 'approved' FOR UPDATE", [$bookId]);
        $problem = '';
        $offer = null;
        if (!$book) {
            $problem = 'That book is not in the catalogue.';
        } elseif (db_value("SELECT 1 FROM loans WHERE user_id = ? AND book_id = ? AND status <> 'returned'", [$uid, $bookId])) {
            $problem = 'You already have ' . $book['title'] . ' on loan. Return it before borrowing it again.';
        } else {
            $offer = db_one("SELECT * FROM book_queue WHERE user_id = ? AND book_id = ? AND status IN ('waiting','offered')", [$uid, $bookId]);
            $counts = book_counts($bookId);
            if ($offer && $offer['status'] === 'offered') {
                if ($collectionDate > hold_until($offer)) {
                    $problem = 'We are holding this copy for you until ' . long_date(hold_until($offer)) . '. Choose a collection date on or before then.';
                }
            } elseif ($offer) {
                $problem = 'You are #' . queue_position($offer) . ' in the queue for ' . $book['title'] . '. We will email you as soon as a copy is yours.';
            } elseif ($counts['available'] <= 0) {
                $problem = 'Every copy of ' . $book['title'] . ' is out at the moment. Join the queue and we will email you when one comes back.';
            }
        }
        if ($problem) {
            $db->rollback();
            return [$problem, 0];
        }
        $due = due_date_for($collectionDate);
        $status = $collectionDate <= date('Y-m-d') ? 'active' : 'reserved';
        db_exec('INSERT INTO loans (user_id, book_id, collection_date, due_date, status) VALUES (?, ?, ?, ?, ?)',
            [$uid, $bookId, $collectionDate, $due, $status]);
        $loanId = db_insert_id();
        if ($offer) {
            db_exec("UPDATE book_queue SET status = 'borrowed' WHERE id = ?", [$offer['id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    send_mail($user['email'], 'Loan confirmed: ' . $book['title'] . ', due back ' . short_date($due),
        mail_body(first_name($user), 'Your loan is confirmed.' . "\n\n  Book: " . $book['title'] . "\n  Collect from: " . long_date($collectionDate)
            . ' at the front desk' . "\n  Due back: " . long_date($due) . "\n  Loan number: $loanId\n\n"
            . 'Late returns cost ' . money(LATE_FEE_PER_DAY) . ' a day. You can return the book early at any time.'));
    return ['', $loanId];
}

// Puts a member in the queue for a book with no copy on the shelf. Returns [error or '', position].
function join_queue(array $user, int $bookId): array
{
    $uid = (int) $user['id'];
    $db = db();
    $db->begin_transaction();
    try {
        db_one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$uid]);
        $book = db_one("SELECT id, title, stock FROM books WHERE id = ? AND status = 'approved' FOR UPDATE", [$bookId]);
        $problem = '';
        if (!$book) {
            $problem = 'That book is not in the catalogue.';
        } elseif ((int) $book['stock'] <= 0) {
            $problem = 'The library has no copies of ' . $book['title'] . ' yet, so there is nothing to queue for.';
        } elseif (db_value("SELECT 1 FROM loans WHERE user_id = ? AND book_id = ? AND status <> 'returned'", [$uid, $bookId])) {
            $problem = 'You already have ' . $book['title'] . ' on loan, so you cannot queue for it as well.';
        } elseif (db_value("SELECT 1 FROM book_queue WHERE user_id = ? AND book_id = ? AND status IN ('waiting','offered')", [$uid, $bookId])) {
            $problem = 'You are already in the queue for ' . $book['title'] . '.';
        } elseif (book_counts($bookId)['available'] > 0) {
            $problem = 'A copy of ' . $book['title'] . ' is on the shelf right now, so you can borrow it straight away.';
        }
        if ($problem) {
            $db->rollback();
            return [$problem, 0];
        }
        db_exec("INSERT INTO book_queue (book_id, user_id, status) VALUES (?, ?, 'waiting')", [$bookId, $uid]);
        $entry = db_one('SELECT * FROM book_queue WHERE id = ?', [db_insert_id()]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return ['', queue_position($entry)];
}

// Takes a member out of a queue. If a copy was being held for them, it goes to the next person.
function leave_queue(array $user, int $bookId): string
{
    $db = db();
    $db->begin_transaction();
    try {
        db_one('SELECT id FROM books WHERE id = ? FOR UPDATE', [$bookId]);
        $entry = db_one("SELECT q.*, b.title FROM book_queue q JOIN books b ON b.id = q.book_id
                         WHERE q.user_id = ? AND q.book_id = ? AND q.status IN ('waiting','offered')", [$user['id'], $bookId]);
        if (!$entry) {
            $db->rollback();
            return 'You are not in the queue for that book.';
        }
        db_exec("UPDATE book_queue SET status = 'left' WHERE id = ?", [$entry['id']]);
        $offers = queue_offer_next($bookId);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    send_queue_offers($offers);
    return '';
}

// Returns a loan (or cancels a reservation not yet collected), then offers the copy to the queue.
// Returns [error or '', the loan as it was saved].
function return_loan(array $user, int $loanId): array
{
    $loan = db_one("SELECT l.*, b.title FROM loans l JOIN books b ON b.id = l.book_id WHERE l.id = ? AND l.user_id = ?", [$loanId, $user['id']]);
    if (!$loan || $loan['status'] === 'returned') {
        return ['We could not find that loan. It may already be returned.', []];
    }
    $db = db();
    $db->begin_transaction();
    try {
        db_one('SELECT id FROM books WHERE id = ? FOR UPDATE', [$loan['book_id']]);
        $changed = db_exec("UPDATE loans SET returned_date = CURDATE(), status = 'returned' WHERE id = ? AND user_id = ? AND status <> 'returned'",
            [$loanId, $user['id']]);
        if ($changed !== 1) {
            $db->rollback();
            return ['That loan has already been returned.', []];
        }
        $offers = queue_offer_next((int) $loan['book_id']);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    $wasReserved = $loan['collection_date'] > date('Y-m-d');
    $loan['returned_date'] = date('Y-m-d');
    $fee = loan_fee($loan);
    send_mail($user['email'], ($wasReserved ? 'Reservation cancelled: ' : 'Returned: ') . $loan['title'],
        mail_body(first_name($user), ($wasReserved
            ? 'Your reservation for ' . $loan['title'] . ' is cancelled. Nothing is owed.'
            : 'Thank you for returning ' . $loan['title'] . ' on ' . long_date($loan['returned_date']) . '.'
                . ($fee > 0 ? "\n\nIt came back " . days_text(loan_days_late($loan)) . ' late, so a late fee of ' . money($fee)
                    . ' has been added to your account. Please settle it at the front desk.' : "\n\nIt came back on time. No fee is due."))));
    send_queue_offers($offers);
    return ['', $loan];
}
