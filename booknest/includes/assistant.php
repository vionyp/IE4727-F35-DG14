<?php
// Paige, the BookNest virtual assistant. She is a scripted helper, not a language model:
// she matches the visitor's words to a topic, then answers from the site's own rules and
// from live data (orders, room bookings, stock, opening hours). Everything runs in PHP on
// our server; there is no outside service.

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

// Lists up to three books as one sentence each, with links to their pages.
function assistant_book_lines(array $books): array
{
    $lines = [];
    $links = [];
    foreach ($books as $b) {
        [$stock] = stock_label((int) $b['stock']);
        $lines[] = $b['title'] . ' by ' . $b['author'] . ', ' . money($b['price']) . ' (' . strtolower($stock) . ').';
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
    return db_all("SELECT id, title, author, price, stock FROM books
                   WHERE status = 'approved' AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)
                   ORDER BY rating DESC LIMIT " . (int) $limit, [$like, $like, $like]);
}

// Answers a question about an order. An order is only ever shown to the person who placed it.
function assistant_order(string $text, ?array $user): array
{
    $mine = fn(array $o) => ($user && (int) $o['user_id'] === (int) $user['id'])
        || (int) ($_SESSION['last_order'] ?? 0) === (int) $o['id'];
    $order = null;
    if (preg_match('/#?\b(\d{1,6})\b/', $text, $m)) {
        $order = db_one('SELECT * FROM orders WHERE id = ?', [(int) $m[1]]);
        if (!$order || !$mine($order)) {
            return assistant_answer('order', 'I can only show an order to the person who placed it, and I could not match order #'
                . (int) $m[1] . ' to you. If it is yours, sign in with the account you ordered with and ask me again.',
                [['Sign in', 'sign-in.php']]);
        }
    } elseif ($user) {
        $order = db_one('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$user['id']]);
        if (!$order) {
            return assistant_answer('order', 'You have not placed an order with this account yet. When you do, it appears in My Account straight away.',
                [['Browse books', 'catalogue.php']]);
        }
    } else {
        return assistant_answer('order', 'Tell me your order number (it is in your confirmation email, for example "order 52"). '
            . 'If you ordered while signed in, sign in and I can look it up for you.', [['Sign in', 'sign-in.php']]);
    }

    $items = (int) db_value('SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = ?', [$order['id']]);
    $placed = date('j M', strtotime($order['created_at']));
    $state = match ($order['payment_status']) {
        'paid' => $order['delivery_method'] === 'delivery'
            ? 'It is paid and on its way: delivery takes up to 3 working days from ' . $placed . '.'
            : 'It is paid and ready to collect at the front desk, any day from ' . sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR) . '.',
        'failed' => 'The payment did not go through, so nothing was charged and nothing will be sent. Your cart is kept if you want to try again.',
        default => 'The payment is still being confirmed. Please check again in a few minutes.',
    };
    return assistant_answer('order', 'Order #' . $order['id'] . ' was placed on ' . $placed . ' for ' . $items
        . ($items === 1 ? ' book' : ' books') . ', total ' . money($order['total']) . '. ' . $state,
        $user ? [['My orders', 'account.php#orders']] : []);
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

// Suggests books, from a category if the visitor named one, otherwise the best sellers.
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
    foreach ($topics as $slug => $pattern) {
        if (preg_match($pattern, $text)) {
            $cat = db_one('SELECT id, name FROM categories WHERE slug = ?', [$slug]);
            if ($cat) {
                $books = db_all("SELECT id, title, author, price, stock FROM books WHERE status = 'approved' AND category_id = ?
                                 ORDER BY rating DESC LIMIT 3", [$cat['id']]);
                [$line, $links] = assistant_book_lines($books);
                $links[] = ['All ' . $cat['name'], 'catalogue.php?category=' . $slug];
                return assistant_answer('recommend', 'In ' . $cat['name'] . ', readers rate these highest: ' . $line, $links);
            }
        }
    }
    $books = db_all("SELECT b.id, b.title, b.author, b.price, b.stock FROM books b
                     JOIN order_items oi ON oi.book_id = b.id JOIN orders o ON o.id = oi.order_id AND o.payment_status = 'paid'
                     WHERE b.status = 'approved' GROUP BY b.id ORDER BY SUM(oi.qty) DESC LIMIT 3");
    [$line, $links] = assistant_book_lines($books);
    return assistant_answer('recommend', 'Our best sellers right now: ' . $line
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
            . 'I answer from the library\'s help guide and, when you are signed in, from your own orders and bookings. '
            . 'If I cannot help, I will point you to our staff.');
    }
    if ($has('/\b(thank|thanks|thx|cheers|great|perfect)\b/') && mb_strlen($text) < 40) {
        return assistant_answer('thanks', 'You are welcome. Ask me anything else, any time.');
    }
    $howToBuy = $has('/how (do|can|to) (i |we )?(buy|order|purchase|pay|check ?out)/');
    if (!$howToBuy && $has('/\border(s|ed)?\b|track|where is my|parcel|my purchase/')) {
        return assistant_order($text, $user);
    }
    if ($has('/refund|return (a|my|the)|exchange|money back/')) {
        return assistant_answer('refund', 'Unread books in good condition can be returned or exchanged within 14 days. Bring the book and your '
            . 'order number to the front desk, or write to us and we will arrange it. Failed payments are never charged, so they need no refund.',
            [['Email the team', 'mailto:' . TEAM_EMAIL]]);
    }
    if (!$howToBuy && $has('/payment|\bpay\b|paying|\bcard\b|declin|charged|failed|not go through/')) {
        return assistant_answer('payment', 'If a payment fails, you are not charged and your cart is kept, so you can simply press "Place order" again '
            . 'or use another card. Payments are handled by our payment partner; BookNest never sees your card number. '
            . 'If you were charged but have no confirmation, tell our staff your name and the time of the order.',
            [['Go to checkout', 'checkout.php'], ['Email the team', 'mailto:' . TEAM_EMAIL]]);
    }
    if ($has('/\broom|study|\bslot|reserve|booking|book a (room|space|table)/')) {
        return assistant_rooms($text, $user);
    }
    if ($has('/\bopen|clos(e|ed|ing)|hours|what time|address|where are you|location|directions/')) {
        $status = library_status();
        return assistant_answer('hours', 'We are open every day, ' . $hours . '. ' . $status['label'] . ' at the moment ('
            . lcfirst($status['detail']) . '). You will find us at ' . BRANCH_ADDRESS . '. Study rooms keep the same hours.');
    }
    if ($has('/serial|bnt-\d|isbn/')) {
        if ($user && preg_match('/bnt-\d{6}/', $text, $m)) {
            $book = db_one('SELECT id, title, author, price, stock, status FROM books WHERE serial_no = ?', [strtoupper($m[0])]);
            if ($book) {
                return assistant_answer('serial', strtoupper($m[0]) . ' is ' . $book['title'] . ' by ' . $book['author'] . ': '
                    . money($book['price']) . ', ' . (int) $book['stock'] . ' in stock, status ' . $book['status'] . '.',
                    $book['status'] === 'approved' ? [[$book['title'], 'book.php?id=' . $book['id']]] : []);
            }
            return assistant_answer('serial', 'No book has the serial number ' . strtoupper($m[0]) . '. Members can add it to the library.',
                [['Add a book', 'add-book.php']]);
        }
        return assistant_answer('serial', 'Every book has a BookNest serial number like BNT-000123, and most have an ISBN. Type either into the '
            . 'search box at the top of any page. Members who search a serial number also see stock, status and who added the book.'
            . ($user ? ' You can also give me a serial number here.' : ''), [['Browse books', 'catalogue.php']]);
    }
    if ($has('/password|sign.?in|log.?in|login|register|sign.?up|account|forgot|locked out/')) {
        if ($has('/forgot|reset|locked|lost|remember/')) {
            return assistant_answer('account', 'I cannot reset passwords myself, for your security. Please email our team from the address on your '
                . 'account, or visit the front desk with your student card, and staff will set a new one.', [['Email the team', 'mailto:' . TEAM_EMAIL]]);
        }
        return assistant_answer('account', 'A free member account lets you book study rooms, keep a shelf, look up serial numbers and add books. '
            . 'Creating one takes a minute: name, email and a password of at least 8 characters with a letter and a number. '
            . 'You do not need an account to buy books.', [['Sign in or join', 'sign-in.php'], ['My Account', 'account.php']]);
    }
    if ($has('/sample|preview|excerpt|read (it|a bit|first|before)|look inside/')) {
        return assistant_answer('reader', 'Every book page has a "Read a sample" or "Read a preview" button. Classics that are out of copyright show '
            . 'their real opening pages. Newer books are still in copyright, so instead of their text you get a short preview written by our '
            . 'librarians: what the book is about, three ideas from it and who it suits.', [['Browse books', 'catalogue.php']]);
    }
    if ($has('/add a book|suggest a|submit|donat|my submission|approv/')) {
        return assistant_answer('add-book', 'Members can add a book to the library: give its serial number, title, author, category, price and a '
            . 'synopsis. A librarian reviews each submission, usually within a day, and you can follow its status in My Account.',
            [['Add a book', 'add-book.php'], ['My submissions', 'account.php#submissions']]);
    }
    if ($has('/shelf|wish.?list|save (it |a book |books )?for later|favourite|favorite/')) {
        return assistant_answer('shelf', 'On any book page, press "Save to shelf" to keep it for later. Your shelf lives in My Account, where you can '
            . 'also remove books. You need to be signed in.', [['My Shelf', 'account.php#shelf']]);
    }
    if ($has('/recommend|suggest|what should i read|something to read|good book|best.?sell|popular|similar to|books? (about|on)\b|something (about|on)\b/')) {
        return assistant_recommend($text);
    }
    if ($has('/deliver|shipping|postage|\bship\b|pick.?up|collect/')) {
        return assistant_answer('delivery', 'Delivery within Singapore costs ' . money(DELIVERY_FEE) . ' and is free for orders of '
            . money(FREE_DELIVERY_FROM) . ' or more; it takes up to 3 working days. Library pickup is always free and ready the next day '
            . 'at the front desk. You choose at checkout.', [['Go to checkout', 'checkout.php']]);
    }
    if ($howToBuy || $has('/\bcart\b|checkout|check out|guest/')) {
        return assistant_answer('checkout', 'Press "Buy now" on a book to go straight to checkout, or "Add to cart" to keep browsing. '
            . 'At checkout, enter your name, email and mobile number, choose delivery or pickup, and place the order. No account is needed. '
            . 'Your cart currently holds ' . cart_count() . (cart_count() === 1 ? ' item.' : ' items.'), [['Go to checkout', 'checkout.php']]);
    }
    if ($has('/human|person|staff|agent|librarian|talk to|speak (to|with)|contact|e.?mail|phone|complain|manager/')) {
        return assistant_answer('contact', 'Of course. Our staff are at the front desk every day, ' . $hours . ', at ' . BRANCH_ADDRESS
            . '. You can also email the team and someone will reply within one working day. It helps to include your order or booking number.',
            [['Email the team', 'mailto:' . TEAM_EMAIL]]);
    }

    // A question about a particular book: strip the asking words and search for what is left.
    $term = trim(preg_replace('/\b(do you (have|sell|stock)|have you got|i am looking for|i\'m looking for|looking for|can i (buy|get)|find|search( for)?|'
        . 'price of|how much (is|does|for)|is|are|there|cost|in stock|stock|available|the book|book|by|a copy of|any|please|you|have|got)\b|[?!.,"]/', ' ', $text));
    $term = preg_replace('/\s+/', ' ', $term);
    if ($books = assistant_find_books($term)) {
        [$line, $links] = assistant_book_lines($books);
        return assistant_answer('book-search', (count($books) === 1 ? 'Yes, we have it: ' : 'Here is what I found: ') . $line, $links);
    }
    if ($has('/do you (have|sell|stock)|looking for|price of|how much|in stock|\bfind\b|search/')) {
        return assistant_answer('book-search', 'I could not find a book matching "' . $term . '". Check the spelling, try the author\'s surname, '
            . 'or search by ISBN. If we really do not have it, members can suggest it.',
            [['Search the catalogue', 'catalogue.php'], ['Suggest a book', 'add-book.php']], false);
    }
    if ($has('/\b(hi|hello|hey|hiya|good (morning|afternoon|evening))\b/')) {
        return assistant_answer('greeting', 'Hello' . ($user ? ', ' . first_name($user) : '') . '. I can help with orders, study rooms, opening hours, '
            . 'delivery, your account, or finding a book. What do you need?');
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
    $quick = ['Opening hours', 'Where is my order?', 'Book a study room', 'Delivery fees', 'Recommend a book', 'Talk to a person'];

    $html = '<details class="assistant" id="assistant"' . ($open ? ' open' : '') . ' data-assistant>'
        . '<summary class="assistant-launcher"><span class="assistant-avatar" aria-hidden="true">P</span>'
        . '<span class="assistant-launcher-text">Ask ' . ASSISTANT_NAME . '</span><span class="visually-hidden">, the help assistant</span></summary>'
        . '<section class="assistant-panel" aria-label="' . ASSISTANT_NAME . ', help assistant">'
        . '<div class="assistant-head"><span class="assistant-avatar" aria-hidden="true">P</span><div><h2>' . ASSISTANT_NAME
        . '</h2><p>Virtual assistant · answers instantly</p></div></div>'
        . '<div class="assistant-log" role="log" aria-live="polite" tabindex="0" aria-label="Conversation with ' . ASSISTANT_NAME . '" data-assistant-log>';

    $html .= '<div class="msg msg-bot"><p>Hello, I am ' . ASSISTANT_NAME . ', BookNest\'s virtual assistant. I am automated, not a person. '
        . 'Ask me about orders, study rooms, opening hours, delivery or finding a book.</p></div>';
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
