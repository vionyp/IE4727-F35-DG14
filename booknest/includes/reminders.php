<?php
// Loan reminder emails. Four kinds, each sent once (the loan_reminders table remembers what went out):
//   collect    the collection day of a book reserved in advance has come
//   due_soon   due back within DUE_SOON_DAYS days (sent again after a renewal, for the new date)
//   due_today  due back today
//   overdue    late: the fee so far and how it grows, then again every REMIND_OVERDUE_DAYS days
// They are sent by tools/send-reminders.php (Windows Task Scheduler runs it every morning) or by the
// "Send reminders now" button on the admin dashboard. Books handed in and waiting for a librarian get none.

// Returns the reminders that are due now, one per loan: each loan row with 'kind' added.
function reminders_due(): array
{
    $soon = (int) DUE_SOON_DAYS;
    $rows = db_all("SELECT l.*, b.title, b.author, u.full_name, u.email FROM loans l
                    JOIN books b ON b.id = l.book_id JOIN users u ON u.id = l.user_id
                    WHERE l.status <> 'returned' AND l.return_requested_at IS NULL
                      AND ((l.collection_date = CURDATE() AND DATE(l.created_at) < CURDATE() AND l.collected_at IS NULL)
                        OR (l.collection_date <= CURDATE() AND l.due_date <= CURDATE() + INTERVAL $soon DAY))
                    ORDER BY u.full_name, l.due_date, l.id");
    $today = date('Y-m-d');
    $due = [];
    foreach ($rows as $l) {
        if ($l['due_date'] < $today) {
            $kind = 'overdue';
            $sent = db_value("SELECT 1 FROM loan_reminders WHERE loan_id = ? AND kind = 'overdue'
                              AND sent_at > NOW() - INTERVAL " . (int) REMIND_OVERDUE_DAYS . ' DAY LIMIT 1', [$l['id']]);
        } else {
            $kind = $l['due_date'] === $today ? 'due_today'
                : ($l['due_date'] <= date('Y-m-d', strtotime("+$soon day")) && $l['collection_date'] < $today ? 'due_soon' : 'collect');
            $sent = db_value('SELECT 1 FROM loan_reminders WHERE loan_id = ? AND kind = ? AND due_date = ? LIMIT 1',
                [$l['id'], $kind, $l['due_date']]);
        }
        if (!$sent) {
            $due[] = $l + ['kind' => $kind];
        }
    }
    return $due;
}

// Writes one reminder email. Returns [subject, body, short note for the member's open pages].
function reminder_message(array $l): array
{
    $title = $l['title'];
    $code = desk_code('L', (int) $l['id']);
    $account = 'Your loans on BookNest: ' . app_url('account.php#loans');
    $renewLine = '';
    if (in_array($l['kind'], ['due_soon', 'due_today'], true)) {
        $why = renew_problem($l);
        $renewLine = $why === ''
            ? "\nNeed longer? You can renew it once, to " . long_date(due_date_for($l['due_date'])) . ', from My Account.'
            : "\nIt cannot be renewed: " . lcfirst($why);
    }
    switch ($l['kind']) {
        case 'collect':
            return ['Ready to collect today: ' . $title,
                mail_body(first_name($l), $title . ' by ' . $l['author'] . ' is waiting for you at the front desk today.'
                    . "\n\nTo collect it, open My Account, press \"Show QR code to collect\" and show the QR code at the desk."
                    . "\nYour collection code is $code. It is due back on " . long_date($l['due_date']) . ".\n\n" . $account),
                $title . ' is ready to collect today. Show the QR code on My Account at the desk.'];
        case 'due_soon':
            $when = due_soon_text($l);
            return ['Reminder: ' . $title . ' is ' . $when,
                mail_body(first_name($l), 'A friendly reminder: ' . $title . ' is ' . $when . ', on ' . long_date($l['due_date']) . '.'
                    . $renewLine . "\n\nAfter the due date the late fee is " . money(LATE_FEE_PER_DAY) . " a day.\n\n" . $account),
                'Reminder: ' . $title . ' is ' . $when . '.'];
        case 'due_today':
            return ['Due back today: ' . $title,
                mail_body(first_name($l), $title . ' is due back today, ' . long_date($l['due_date']) . '.' . $renewLine
                    . "\n\nBring it to the front desk before " . sprintf('%02d:00', CLOSE_HOUR) . ', or press Return on My Account and hand it in.'
                    . "\nFrom tomorrow the late fee is " . money(LATE_FEE_PER_DAY) . " a day.\n\n" . $account),
                $title . ' is due back today.'];
        default:   // overdue
            $days = loan_days_late($l);
            $fee = loan_fee($l);
            return ['Overdue: ' . $title . ' (' . money($fee) . ' so far)',
                mail_body(first_name($l), $title . ' was due back on ' . long_date($l['due_date']) . ' and is now ' . days_text($days) . ' late.'
                    . "\n\nThe late fee so far is " . money($fee) . ' and grows by ' . money(LATE_FEE_PER_DAY) . ' a day until it is back.'
                    . ' New loans are paused while a book is overdue' . ($fee >= FEE_BLOCK_LIMIT ? ' and while you owe ' . money(FEE_BLOCK_LIMIT) . ' or more' : '') . '.'
                    . "\n\nPlease bring it back to the front desk, or press Return on My Account and hand it in.\n\n" . $account),
                $title . ' is ' . days_text($days) . ' overdue: ' . money($fee) . ' so far.'];
    }
}

// Sends every reminder that is due. Returns the counts per kind, plus 'failed' for emails the mail
// server did not take (those are tried again next time, because they are not recorded as sent).
function send_loan_reminders(): array
{
    $counts = ['collect' => 0, 'due_soon' => 0, 'due_today' => 0, 'overdue' => 0, 'failed' => 0];
    foreach (reminders_due() as $l) {
        [$subject, $body, $note] = reminder_message($l);
        if (!send_mail($l['email'], $subject, $body)) {
            $counts['failed']++;
            continue;
        }
        db_exec('INSERT INTO loan_reminders (loan_id, kind, due_date) VALUES (?, ?, ?)', [$l['id'], $l['kind'], $l['due_date']]);
        live_notify((int) $l['user_id'], 'me', $note);
        $counts[$l['kind']]++;
    }
    return $counts;
}

// "3 reminders (1 due today, 2 overdue)" for the admin dashboard.
function reminder_summary(array $counts): string
{
    $labels = ['collect' => 'to collect', 'due_soon' => 'due soon', 'due_today' => 'due today', 'overdue' => 'overdue'];
    $parts = [];
    foreach ($labels as $k => $label) {
        if (!empty($counts[$k])) {
            $parts[] = $counts[$k] . ' ' . $label;
        }
    }
    $total = array_sum(array_intersect_key($counts, $labels));
    return $total . ' ' . ($total === 1 ? 'reminder' : 'reminders') . ($parts ? ' (' . implode(', ', $parts) . ')' : '');
}
