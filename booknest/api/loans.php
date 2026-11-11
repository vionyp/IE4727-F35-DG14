<?php
// Modern Enhancement: the member's loans for the Vue panel on My Account (assets/js/vue/loans-panel.js).
//   GET                                   the open loans, books handed in, fees owed, limits and the reasons borrowing is paused
//   POST action=renew|return, loan_id, csrf   renew or return one loan, then the same data again
// The POST calls renew_loan() and return_loan() from includes/loans.php, exactly like process/loan.php,
// in the same order of checks (method, signed in, CSRF), and answers with the same messages. Every rule
// (renew once, not when overdue or when someone waits, only your own loans) is enforced there, in PHP.
require __DIR__ . '/../includes/api.php';

// Builds the panel data for a member: open loans with their dates and fees, books handed in that a
// librarian has not confirmed yet, plus the totals' inputs.
function loans_payload(array $user): array
{
    $uid = (int) $user['id'];
    $rows = db_all("SELECT l.*, b.title, b.author, b.cover_path, b.cover_alt FROM loans l JOIN books b ON b.id = l.book_id
                    WHERE l.user_id = ? AND l.status <> 'returned' ORDER BY l.due_date, l.id", [$uid]);
    $today = date('Y-m-d');
    $loans = [];
    $handedIn = [];
    $openFees = 0.0;
    foreach ($rows as $l) {
        if (!empty($l['return_requested_at'])) {
            $handedIn[] = [
                'id' => (int) $l['id'],
                'title' => $l['title'],
                'author' => $l['author'],
                'url' => book_url((int) $l['book_id']),
                'cover' => url('assets/' . cover_file($l, true)),
                'coverAlt' => $l['cover_alt'],
                'handedInLong' => long_date(loan_end_date($l)),
                'daysLate' => loan_days_late($l),
                'fee' => loan_fee($l),
            ];
            continue;
        }
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
        ] + desk_payload($l);
    }
    return [
        'loans' => $loans,
        'handedIn' => $handedIn,
        'feesFromReturns' => round(max(0, fees_owed($uid) - $openFees), 2),   // unpaid fees on books handed in or already back
        'blocks' => borrow_blocks($uid),
        'rules' => ['maxLoans' => MAX_LOANS, 'maxRenewals' => MAX_RENEWALS, 'dueSoonDays' => DUE_SOON_DAYS,
            'lateFeePerDay' => LATE_FEE_PER_DAY, 'loanDays' => LOAN_DAYS],
    ];
}

// The QR code to collect a loan that is waiting at the desk, for the panel: the code, what to do with it
// and the QR code as SVG markup (built by qr_svg() from rectangles only, so the panel can insert it with
// v-html). A book already collected has no code to show.
function desk_payload(array $l): array
{
    if (!loan_to_collect($l)) {
        return ['toCollect' => false, 'deskCode' => '', 'deskHint' => '', 'deskQr' => ''];
    }
    $code = desk_code('L', (int) $l['id']);
    return ['toCollect' => true, 'deskCode' => $code, 'deskHint' => desk_hint($l), 'deskQr' => qr_svg(desk_url($code), 'QR code for ' . $l['title'])];
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
        $message = return_message($loan);
    } else {
        api_error(400, 'Unknown action.', loans_payload($user));
    }
    api_send(200, ['message' => $message, 'action' => $action, 'loanId' => $loanId] + loans_payload($user));
}

api_send(200, loans_payload($user));
