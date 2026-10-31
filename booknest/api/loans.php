<?php
// Modern Enhancement: the member's loans for the Vue panel on My Account (assets/js/vue/loans-panel.js).
//   GET                                   the open loans, fees owed, limits and the reasons borrowing is paused
//   POST action=renew|return, loan_id, csrf   renew or return one loan, then the same data again
// The POST calls renew_loan() and return_loan() from includes/loans.php, exactly like process/loan.php,
// in the same order of checks (method, signed in, CSRF), and answers with the same messages. Every rule
// (renew once, not when overdue or when someone waits, only your own loans) is enforced there, in PHP.
require __DIR__ . '/../includes/api.php';

// Builds the panel data for a member: open loans with their dates and fees, plus the totals' inputs.
function loans_payload(array $user): array
{
    $uid = (int) $user['id'];
    $rows = db_all("SELECT l.*, b.title, b.author, b.cover_path, b.cover_alt FROM loans l JOIN books b ON b.id = l.book_id
                    WHERE l.user_id = ? AND l.status <> 'returned' ORDER BY l.due_date, l.id", [$uid]);
    $today = date('Y-m-d');
    $loans = [];
    $openFees = 0.0;
    foreach ($rows as $l) {
        $why = renew_problem($l);
        $fee = loan_fee($l);
        $openFees += $fee;
        $loans[] = [
            'id' => (int) $l['id'],
            'bookId' => (int) $l['book_id'],
            'title' => $l['title'],
            'author' => $l['author'],
            'url' => book_url((int) $l['book_id']),
            'cover' => url('assets/' . cover_file($l, true)),
            'coverAlt' => $l['cover_alt'],
            'state' => loan_state($l),
            'collectedShort' => short_date($l['collection_date']),
            'collectedLong' => long_date($l['collection_date']),
            'dueLong' => long_date($l['due_date']),
            'daysLeft' => days_between($today, $l['due_date']),
            'daysLate' => loan_days_late($l),
            'fee' => $fee,
            'renewals' => (int) $l['renewals'],
            'canRenew' => $why === '',
            'renewWhy' => $why,
            'renewToShort' => short_date(due_date_for($l['due_date'])),
            'renewToLong' => long_date(due_date_for($l['due_date'])),
        ];
    }
    return [
        'loans' => $loans,
        'feesFromReturns' => round(max(0, fees_owed($uid) - $openFees), 2),   // unpaid fees on books already back
        'blocks' => borrow_blocks($uid),
        'rules' => ['maxLoans' => MAX_LOANS, 'maxRenewals' => MAX_RENEWALS, 'dueSoonDays' => DUE_SOON_DAYS,
            'lateFeePerDay' => LATE_FEE_PER_DAY, 'loanDays' => LOAN_DAYS],
    ];
}

$method = api_method('GET', 'POST');
$user = api_user('Please sign in again to see and change your loans.');

if ($method === 'POST') {
    api_csrf();
    $action = input($_POST, 'action', 10);
    $loanId = input_int($_POST, 'loan_id');

    if ($action === 'renew') {
        [$problem, $loan] = renew_loan($user, $loanId);
        if ($problem) {
            api_error(422, $problem, loans_payload($user));
        }
        $message = 'Renewed ' . $loan['title'] . '. It is now due back on ' . long_date($loan['due_date']) . '. A confirmation email is on its way.';
    } elseif ($action === 'return') {
        [$problem, $loan] = return_loan($user, $loanId);
        if ($problem) {
            api_error(422, $problem, loans_payload($user));
        }
        if ($loan['collection_date'] > date('Y-m-d')) {
            $message = 'Your reservation for ' . $loan['title'] . ' is cancelled.';
        } else {
            $fee = loan_fee($loan);
            $message = 'Returned ' . $loan['title'] . '. ' . ($fee > 0
                ? 'It was ' . days_text(loan_days_late($loan)) . ' late, so a late fee of ' . money($fee) . ' was added to your account.'
                : 'Thank you for bringing it back on time.');
        }
    } else {
        api_error(400, 'Unknown action.', loans_payload($user));
    }
    api_send(200, ['message' => $message, 'action' => $action, 'loanId' => $loanId] + loans_payload($user));
}

api_send(200, loans_payload($user));
