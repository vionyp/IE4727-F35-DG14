<?php
// Paige, the BookNest virtual assistant. She is a scripted helper, not a language model:
// she matches the visitor's words to a topic, then answers from the site's own rules and
// from live data (loans, queues, late fees, room bookings, copies, opening hours). Everything
// runs in PHP on our server; there is no outside service.

const ASSISTANT_NAME = 'Paige';
const ASSISTANT_MAX_MESSAGES = 24;

// Returns the conversation kept in the session, oldest first.
function assistant_messages(): array
{
    return is_array($_SESSION['assistant'] ?? null) ? $_SESSION['assistant'] : [];
}

// Adds one message to the conversation and keeps only the most recent ones.
function assistant_push(string $from, string $text, array $links = []): void
{
    $messages = assistant_messages();
    $messages[] = ['from' => $from, 'text' => $text, 'links' => $links, 'time' => date('H:i')];
    $_SESSION['assistant'] = array_slice($messages, -ASSISTANT_MAX_MESSAGES);
}

// Builds an answer array.
function assistant_answer(string $intent, string $text, array $links = [], bool $answered = true): array
{
    return ['intent' => $intent, 'text' => $text, 'links' => $links, 'answered' => $answered];
}

// Lists up to three books as one sentence each, with their availability and links to their pages.
function assistant_book_lines(array $books): array
{
    $lines = [];
    $links = [];
    foreach ($books as $b) {
        [$avail] = availability_label($b);
        $lines[] = $b['title'] . ' by ' . $b['author'] . ' (' . lcfirst($avail) . ').';
        $links[] = [$b['title'], 'book.php?id=' . $b['id']];
    }
    return [implode(' ', $lines), $links];
}

// Searches approved books by title or author and returns the best matches.
function assistant_find_books(string $term, int $limit = 3): array
{
    $term = trim($term);
    if (mb_strlen($term) < 3) {
        return [];
    }
    $like = '%' . addcslashes($term, '%_\\') . '%';
    return db_all("SELECT b.id, b.title, b.author, b.stock, " . availability_columns() . " FROM books b
                   WHERE b.status = 'approved' AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)
                   ORDER BY b.rating DESC LIMIT " . (int) $limit, [$like, $like, $like]);
}

// Answers "what do I have on loan". Loans are only ever shown to the member they belong to.
function assistant_loans(?array $user): array
{
    if (!$user) {
        return assistant_answer('loans', 'I can only show loans to the member they belong to. Sign in and ask me again, '
            . 'or open My Account to see every book you have, with its due date.', [['Sign in', 'sign-in.php']]);
    }
    $loans = db_all("SELECT l.*, b.title FROM loans l JOIN books b ON b.id = l.book_id
                     WHERE l.user_id = ? AND l.status <> 'returned' ORDER BY l.due_date", [$user['id']]);
    if (!$loans) {
        return assistant_answer('loans', 'You have nothing on loan right now. Find a book, press Borrow and pick a collection day.',
            [['See what is on the shelf', 'catalogue.php?sort=available']]);
    }
    $parts = [];
    foreach ($loans as $l) {
        $state = loan_state($l);
        $parts[] = $l['title'] . match ($state) {
            'reserved' => ', ready to collect from ' . short_date($l['collection_date']),
            'overdue' => ', ' . days_text(loan_days_late($l)) . ' overdue (' . money(loan_fee($l)) . ' so far)',
            default => ', due back ' . short_date($l['due_date']),
        };
    }
    return assistant_answer('loans', 'You have ' . count($loans) . (count($loans) === 1 ? ' book' : ' books') . ' on loan: '
        . implode('; ', $parts) . '.', [['My loans', 'account.php#loans']]);
}

// Answers questions about the queue, including the member's own places in line.
function assistant_queue(?array $user): array
{
    $rule = 'When every copy of a book is out, press "Join the queue" on its page. When a copy comes back, the first person '
        . 'in line gets an email and we hold it for them for ' . QUEUE_HOLD_DAYS . ' days; after that it passes to the next person.';
    if ($user) {
        $mine = db_all("SELECT q.*, b.title FROM book_queue q JOIN books b ON b.id = q.book_id
                        WHERE q.user_id = ? AND q.status IN ('waiting','offered') ORDER BY q.queued_at", [$user['id']]);
        if ($mine) {
            $parts = array_map(fn($q) => $q['title'] . ($q['status'] === 'offered'
                ? ' (a copy is held for you until ' . short_date(hold_until($q)) . ')'
                : ' (you are #' . queue_position($q) . ' in line)'), $mine);
            return assistant_answer('queue', 'You are waiting for ' . implode('; ', $parts) . '. ' . $rule, [['My loans', 'account.php#loans']]);
        }
    }
    return assistant_answer('queue', $rule, [['Browse books', 'catalogue.php']]);
}

// Answers questions about late fees, with the member's own total when signed in.
function assistant_fees(?array $user): array
{
    $rule = 'A loan lasts ' . LOAN_DAYS . ' days. Returning late costs ' . money(LATE_FEE_PER_DAY) . ' for every day after the due date, '
        . 'and the fee stops growing the day you return the book. Fees are settled at the front desk.';
    if ($user) {
        $owed = fees_owed((int) $user['id']);
        return assistant_answer('fees', ($owed > 0 ? 'You owe ' . money($owed) . ' in late fees at the moment. ' : 'You owe nothing at the moment. ') . $rule,
            [['My loans', 'account.php#loans']]);
    }
    return assistant_answer('fees', $rule);
}

// Answers questions about study rooms, using live availability and the member's own bookings.
function assistant_rooms(string $text, ?array $user): array
{
    if (preg_match('/cancel/', $text)) {
        return assistant_answer('rooms', 'To cancel a room, open My Account, find the booking under "Upcoming study rooms" and press Cancel. '
            . 'You can cancel any time before it starts, and your hour for that day is free again.', [['My Account', 'account.php#bookings']]);
    }
    if ($user && preg_match('/\b(my|mine|next|upcoming)\b/', $text)) {
        $next = db_one("SELECT rb.booking_date, rb.start_time, rb.end_time, r.name FROM room_bookings rb
                        JOIN study_rooms r ON r.id = rb.room_id
                        WHERE rb.user_id = ? AND rb.status = 'confirmed'
                          AND (rb.booking_date > CURDATE() OR (rb.booking_date = CURDATE() AND rb.end_time > CURTIME()))
                        ORDER BY rb.booking_date, rb.start_time LIMIT 1", [$user['id']]);
        return $next
            ? assistant_answer('rooms', 'Your next study room is ' . $next['name'] . ' on ' . long_date($next['booking_date']) . ', '
                . hm($next['start_time']) . ' to ' . hm($next['end_time']) . '.', [['My bookings', 'account.php#bookings']])
            : assistant_answer('rooms', 'You have no upcoming study room. You have '
                . max(0, DAILY_CAP_MINUTES - minutes_used((int) $user['id'], date('Y-m-d'))) . ' minutes left today.', [['See free rooms', 'rooms.php']]);
    }
    $rooms = db_all('SELECT id, name, is_active FROM study_rooms ORDER BY id');
    $free = 0;
    foreach (availability(date('Y-m-d'), $rooms, (int) ($user['id'] ?? 0)) as $slots) {
        $free += count(array_filter($slots, fn($s) => $s === 'free'));
    }
    return assistant_answer('rooms', 'We have ' . count($rooms) . ' study rooms for 2 to 6 people. Members can book up to '
        . DAILY_CAP_MINUTES . ' minutes a day, today or up to ' . ADVANCE_DAYS . ' days ahead. Right now ' . $free
        . ' half hour slots are still free today. Pick a free slot in the table, choose how long, and confirm.'
        . ($user ? '' : ' You need to be signed in to book.'), [['See free rooms', 'rooms.php']]);
}

// Suggests books, from a category if the visitor named one, otherwise the most borrowed.
function assistant_recommend(string $text): array
{
    $topics = [
        'self-improvement' => '/habit|productiv|self.?(help|improve)|motivat|focus/',
        'business-money' => '/money|financ|invest|business|startup|rich|econom/',
        'mystery-thriller' => '/myster|thrill|crime|detective|suspense|murder/',
        'sci-fi-fantasy' => '/sci.?fi|science fiction|fantasy|space|dragon|magic/',
        'non-fiction' => '/non.?fiction|history|science|memoir|biograph|true story/',
        'classics' => '/classic|old|public domain/',
        'fiction' => '/fiction|novel|story|romance/',
    ];
    $cols = 'b.id, b.title, b.author, b.stock, ' . availability_columns();
    foreach ($topics as $slug => $pattern) {
        if (preg_match($pattern, $text)) {
            $cat = db_one('SELECT id, name FROM categories WHERE slug = ?', [$slug]);
            if ($cat) {
                $books = db_all("SELECT $cols FROM books b WHERE b.status = 'approved' AND b.category_id = ?
                                 ORDER BY b.rating DESC LIMIT 3", [$cat['id']]);
                [$line, $links] = assistant_book_lines($books);
                $links[] = ['All ' . $cat['name'], 'catalogue.php?category=' . $slug];
                return assistant_answer('recommend', 'In ' . $cat['name'] . ', readers rate these highest: ' . $line, $links);
            }
        }
    }
    $books = db_all("SELECT $cols FROM books b JOIN loans l ON l.book_id = b.id
                     WHERE b.status = 'approved' GROUP BY b.id ORDER BY COUNT(l.id) DESC, b.title LIMIT 3");
    [$line, $links] = assistant_book_lines($books);
    return assistant_answer('recommend', 'Our most borrowed books right now: ' . $line
        . ' Tell me a genre, such as mystery, money or fantasy, and I will narrow it down.', $links);
}

// Works out what the visitor is asking and returns the answer.
function assistant_reply(string $message, ?array $user): array
{
    $text = ' ' . preg_replace('/\s+/', ' ', mb_strtolower(trim($message))) . ' ';
    $has = fn(string $pattern) => (bool) preg_match($pattern, $text);
    $hours = sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR);

    if ($has('/who are you|your name|are you (a |an )?(bot|robot|human|real|person|ai)\b|what are you/')) {
        return assistant_answer('about', 'I am ' . ASSISTANT_NAME . ', BookNest\'s virtual assistant. I am an automated helper, not a person: '
            . 'I answer from the library\'s help guide and, when you are signed in, from your own loans and bookings. '
            . 'If I cannot help, I will point you to our staff.');
    }
    if ($has('/\b(thank|thanks|thx|cheers|great|perfect)\b/') && mb_strlen($text) < 40) {
        return assistant_answer('thanks', 'You are welcome. Ask me anything else, any time.');
    }
    $howToBorrow = $has('/how (do|can|to|does) (i |we )?(borrow|loan|get a book|check ?out)|how does borrowing work|can i borrow/');
    if ($has('/\bfees?\b|\bfines?\b|\blate\b|overdue|\bowe\b|penalt/')) {
        return assistant_fees($user);
    }
    if (!$howToBorrow && $has('/\bmy (loans?|books)\b|on loan|due (date|back)|when is (it|my book) due|what (have|did) i borrow|books? i borrowed/')) {
        return assistant_loans($user);
    }
    if ($has('/queue|waiting list|wait.?list|in line|on hold|\bhold (a|it|the|my)\b/')) {
        return assistant_queue($user);
    }
    if ($has('/\breturn|bring (it|the book|a book) back|give (it )?back/')) {
        return assistant_answer('return', 'To return a book, bring it to the front desk, or press Return next to it in My Account if you have '
            . 'already dropped it in the returns box. Returning early is always fine. The copy then goes to the next person in the queue.',
            [['My loans', 'account.php#loans']]);
    }
    if ($has('/\broom|study|\bslot|reserve (a )?(room|space)|booking|book a (room|space|table)/')) {
        return assistant_rooms($text, $user);
    }
    if ($has('/\bopen|clos(e|ed|ing)|hours|what time|address|where are you|location|directions/')) {
        $status = library_status();
        return assistant_answer('hours', 'We are open every day, ' . $hours . '. ' . $status['label'] . ' at the moment ('
            . lcfirst($status['detail']) . '). You will find us at ' . BRANCH_ADDRESS . '. Study rooms keep the same hours.');
    }
    if ($has('/serial|bnt-\d|isbn/')) {
        if ($user && preg_match('/bnt-\d{6}/', $text, $m)) {
            $book = db_one('SELECT b.id, b.title, b.author, b.stock, b.status, ' . availability_columns() . ' FROM books b WHERE b.serial_no = ?', [strtoupper($m[0])]);
            if ($book) {
                [$avail] = availability_label($book);
                return assistant_answer('serial', strtoupper($m[0]) . ' is ' . $book['title'] . ' by ' . $book['author'] . ': '
                    . (int) $book['stock'] . ' copies owned, ' . (int) $book['on_loan'] . ' on loan, ' . lcfirst($avail) . ', status ' . $book['status'] . '.',
                    $book['status'] === 'approved' ? [[$book['title'], 'book.php?id=' . $book['id']]] : []);
            }
            return assistant_answer('serial', 'No book has the serial number ' . strtoupper($m[0]) . '. Members can add it to the library.',
                [['Add a book', 'add-book.php']]);
        }
        return assistant_answer('serial', 'Every book has a BookNest serial number like BNT-000123, and most have an ISBN. Type either into the '
            . 'search box at the top of any page. Members who search a serial number also see copies, loans and who added the book.'
            . ($user ? ' You can also give me a serial number here.' : ''), [['Browse books', 'catalogue.php']]);
    }
    if ($has('/password|sign.?in|log.?in|login|register|sign.?up|account|forgot|locked out/')) {
        if ($has('/forgot|reset|locked|lost|remember/')) {
            return assistant_answer('account', 'I cannot reset passwords myself, for your security. Please email our team from the address on your '
                . 'account, or visit the front desk with your student card, and staff will set a new one.', [['Email the team', 'mailto:' . TEAM_EMAIL]]);
        }
        return assistant_answer('account', 'A free member account lets you borrow books, book study rooms, keep a shelf, look up serial numbers and add books. '
            . 'Creating one takes a minute: name, email and a password of at least 8 characters with a letter and a number.',
            [['Sign in or join', 'sign-in.php'], ['My Account', 'account.php']]);
    }
    if ($has('/sample|preview|excerpt|read (it|a bit|first|before)|look inside/')) {
        return assistant_answer('reader', 'Every book page has a "Read a sample" or "Read a preview" button. Classics that are out of copyright show '
            . 'their real opening pages. Newer books are still in copyright, so instead of their text you get a short preview written by our '
            . 'librarians: what the book is about, three ideas from it and who it suits.', [['Browse books', 'catalogue.php']]);
    }
    if ($has('/add a book|suggest a|submit|donat|my submission|approv/')) {
        return assistant_answer('add-book', 'Members can add a book to the library: give its serial number, title, author, category, copies and a '
            . 'synopsis. A librarian reviews each submission, usually within a day, and you can follow its status in My Account.',
            [['Add a book', 'add-book.php'], ['My submissions', 'account.php#submissions']]);
    }
    if ($has('/shelf|wish.?list|save (it |a book |books )?for later|favourite|favorite/')) {
        return assistant_answer('shelf', 'On any book page, press "Save to shelf" to keep it for later. Your shelf lives in My Account, where you can '
            . 'also remove books. You need to be signed in.', [['My Shelf', 'account.php#shelf']]);
    }
    if ($has('/recommend|suggest|what should i read|something to read|good book|most borrowed|popular|similar to|books? (about|on)\b|something (about|on)\b/')) {
        return assistant_recommend($text);
    }
    if ($has('/deliver|shipping|postage|\bship\b|pick.?up|collect/')) {
        return assistant_answer('collect', 'We do not post books. When you borrow, you choose a collection day (today or up to '
            . COLLECT_AHEAD_DAYS . ' days ahead) and pick the book up at the front desk, ' . $hours . '. The ' . LOAN_DAYS
            . ' day loan starts on that day.', [['My loans', 'account.php#loans']]);
    }
    if ($howToBorrow || $has('/\bborrow|\bloans?\b|\blend|check ?out|\bbuy\b|purchase|how long can i keep|keep (a|the) book|loan period/')) {
        return assistant_answer('borrow', 'Borrowing is free for members. Open a book, press Borrow, choose the day you will collect it and confirm. '
            . 'You keep it for ' . LOAN_DAYS . ' days and can hold one copy of each title at a time. If every copy is out, join the queue. '
            . 'We do not sell books.', $user ? [['See what is on the shelf', 'catalogue.php?sort=available']] : [['Sign in or join', 'sign-in.php']]);
    }
    if ($has('/human|person|staff|agent|librarian|talk to|speak (to|with)|contact|e.?mail|phone|complain|manager/')) {
        return assistant_answer('contact', 'Of course. Our staff are at the front desk every day, ' . $hours . ', at ' . BRANCH_ADDRESS
            . '. You can also email the team and someone will reply within one working day. It helps to include your loan or booking number.',
            [['Email the team', 'mailto:' . TEAM_EMAIL]]);
    }

    // A question about a particular book: strip the asking words and search for what is left.
    $term = trim(preg_replace('/\b(do you (have|stock)|have you got|i am looking for|i\'m looking for|looking for|can i (get|read)|find|search( for)?|'
        . 'is|are|there|in stock|stock|available|the book|book|by|a copy of|any|please|you|have|got)\b|[?!.,"]/', ' ', $text));
    $term = preg_replace('/\s+/', ' ', $term);
    if ($books = assistant_find_books($term)) {
        [$line, $links] = assistant_book_lines($books);
        return assistant_answer('book-search', (count($books) === 1 ? 'Yes, we have it: ' : 'Here is what I found: ') . $line, $links);
    }
    if ($has('/do you (have|stock|sell)|looking for|in stock|available|\bfind\b|search/')) {
        return assistant_answer('book-search', 'I could not find a book matching "' . $term . '". Check the spelling, try the author\'s surname, '
            . 'or search by ISBN. If we really do not have it, members can suggest it.',
            [['Search the catalogue', 'catalogue.php'], ['Suggest a book', 'add-book.php']], false);
    }
    if ($has('/\b(hi|hello|hey|hiya|good (morning|afternoon|evening))\b/')) {
        return assistant_answer('greeting', 'Hello' . ($user ? ', ' . first_name($user) : '') . '. I can help with borrowing, your loans, '
            . 'late fees, queues, study rooms, opening hours, your account, or finding a book. What do you need?');
    }

    // Nothing matched: say so honestly and offer the human route. The question is logged for staff.
    return assistant_answer('unknown', 'I am not sure about that one, and I would rather not guess. You could try asking it another way, '
        . 'or our staff will be glad to help: they are at the front desk ' . $hours . ' and answer email within a working day.',
        [['Email the team', 'mailto:' . TEAM_EMAIL]], false);
}

// Prints the assistant: a launcher button that opens the chat panel, on every page.
function assistant_widget(): string
{
    $messages = assistant_messages();
    $open = !empty($_SESSION['assistant_open']);
    unset($_SESSION['assistant_open']);
    $quick = ['Opening hours', 'What do I have on loan?', 'How do I borrow?', 'Late fees', 'Book a study room', 'Recommend a book', 'Talk to a person'];

    $html = '<details class="assistant" id="assistant"' . ($open ? ' open' : '') . ' data-assistant>'
        . '<summary class="assistant-launcher"><span class="assistant-avatar" aria-hidden="true">P</span>'
        . '<span class="assistant-launcher-text">Ask ' . ASSISTANT_NAME . '</span><span class="visually-hidden">, the help assistant</span></summary>'
        . '<section class="assistant-panel" aria-label="' . ASSISTANT_NAME . ', help assistant">'
        . '<div class="assistant-head"><span class="assistant-avatar" aria-hidden="true">P</span><div><h2>' . ASSISTANT_NAME
        . '</h2><p>Virtual assistant · answers instantly</p></div></div>'
        . '<div class="assistant-log" role="log" aria-live="polite" tabindex="0" aria-label="Conversation with ' . ASSISTANT_NAME . '" data-assistant-log>';

    $html .= '<div class="msg msg-bot"><p>Hello, I am ' . ASSISTANT_NAME . ', BookNest\'s virtual assistant. I am automated, not a person. '
        . 'Ask me about borrowing, your loans, late fees, queues, study rooms, opening hours or finding a book.</p></div>';
    foreach ($messages as $m) {
        $html .= '<div class="msg msg-' . ($m['from'] === 'user' ? 'user' : 'bot') . '"><span class="visually-hidden">'
            . ($m['from'] === 'user' ? 'You said: ' : ASSISTANT_NAME . ' said: ') . '</span><p>' . e($m['text']) . '</p>';
        if (!empty($m['links'])) {
            $html .= '<ul class="msg-links" role="list">';
            foreach ($m['links'] as [$label, $path]) {
                $href = str_starts_with($path, 'mailto:') ? $path : url($path);
                $html .= '<li><a href="' . e($href) . '">' . e($label) . '</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '<span class="msg-time">' . e($m['time']) . '</span></div>';
    }
    $html .= '</div>'
        . '<form class="assistant-form" action="' . e(url('process/assistant.php')) . '" method="post" data-assistant-form>'
        . csrf_field()
        . '<div class="assistant-quick">';
    foreach ($quick as $q) {
        $html .= '<button type="submit" name="quick" value="' . e($q) . '" class="chip chip-sm" formnovalidate>' . e($q) . '</button>';
    }
    $html .= '</div><div class="assistant-entry"><label class="visually-hidden" for="assistant-message">Your question for ' . ASSISTANT_NAME . '</label>'
        . '<input type="text" id="assistant-message" name="message" maxlength="300" placeholder="Type your question" autocomplete="off" required>'
        . '<button type="submit" class="btn btn-primary btn-sm">Send</button></div>'
        . ($messages ? '<button type="submit" name="clear" value="1" class="btn-link assistant-clear" formnovalidate>Clear this conversation</button>' : '')
        . '</form></section></details>';
    return $html;
}
