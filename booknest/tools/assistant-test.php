<?php
// Asks Paige a list of questions and checks each lands on the expected topic.
// Run:  C:\xampp\php\php.exe tools\assistant-test.php        (add -v to print every answer)
require __DIR__ . '/../includes/bootstrap.php';

$verbose = in_array('-v', $argv, true);
$member = db_one("SELECT id, full_name, email, role, created_at FROM users WHERE email = 'aisha@localhost'");
$ownOrder = (int) db_value('SELECT id FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$member['id']]);
$otherOrder = (int) db_value('SELECT id FROM orders WHERE user_id IS NOT NULL AND user_id <> ? ORDER BY id DESC LIMIT 1', [$member['id']]);

// [question, expected topic, signed in?, text the answer must contain (optional)]
$cases = [
    ['Opening hours', 'hours', false, '10:00 to 21:00'],
    ['what time do you close today', 'hours', false, ''],
    ['Where are you located?', 'hours', false, 'Nanyang'],
    ['Where is my order?', 'order', false, 'order number'],
    ['Where is my order?', 'order', true, 'Order #'],
    ["where is order $ownOrder", 'order', true, "Order #$ownOrder"],
    ["track order #$otherOrder", 'order', true, 'only show an order to the person who placed it'],
    ["what is the status of order $ownOrder", 'order', false, 'only show an order to the person who placed it'],
    ['How do I order a book?', 'checkout', false, 'Buy now'],
    ['how can i pay', 'checkout', false, ''],
    ['my payment failed', 'payment', false, 'not charged'],
    ['my card was declined', 'payment', false, ''],
    ['Can I return a book?', 'refund', false, '14 days'],
    ['Book a study room', 'rooms', false, 'signed in'],
    ['how do I cancel my room booking', 'rooms', true, 'Cancel'],
    ['when is my next room', 'rooms', true, ''],
    ['Delivery fees', 'delivery', false, 'S$3.50'],
    ['do you ship to Jurong', 'delivery', false, ''],
    ['Recommend a book', 'recommend', false, 'best sellers'],
    ['recommend a good thriller', 'recommend', false, 'Mystery and Thriller'],
    ['any books about money?', 'recommend', false, ''],
    ['Do you have Atomic Habits?', 'book-search', false, 'Atomic Habits'],
    ['how much is sapiens', 'book-search', false, 'S$29.90'],
    ['looking for something by Andy Weir', 'book-search', false, 'Andy Weir'],
    ['do you have the lord of the rings', 'book-search', false, 'could not find'],
    ['what is BNT-000123', 'serial', true, 'The Da Vinci Code'],
    ['what is a serial number', 'serial', false, ''],
    ['I forgot my password', 'account', false, 'cannot reset'],
    ['how do I create an account', 'account', false, ''],
    ['can I read a sample first', 'reader', false, 'preview'],
    ['how do I add a book', 'add-book', true, ''],
    ['how do I save a book for later', 'shelf', false, ''],
    ['Talk to a person', 'contact', false, 'front desk'],
    ['are you a robot?', 'about', false, 'automated'],
    ['thanks!', 'thanks', false, ''],
    ['hello', 'greeting', false, ''],
    ['do you sell gift cards', 'book-search', false, 'could not find'],
    ['can I bring my dog', 'unknown', false, 'not sure'],
    ['<script>alert(1)</script>', 'unknown', false, ''],
    ["' OR 1=1 --", 'unknown', false, ''],
];

$pass = 0;
foreach ($cases as [$q, $expect, $signedIn, $needle]) {
    $a = assistant_reply($q, $signedIn ? $member : null);
    $ok = $a['intent'] === $expect && ($needle === '' || stripos($a['text'], $needle) !== false);
    $pass += $ok ? 1 : 0;
    printf("%s  %-45s -> %-12s%s\n", $ok ? 'PASS' : 'FAIL', mb_strimwidth($q . ($signedIn ? ' [member]' : ''), 0, 45), $a['intent'],
        $ok ? '' : "  (expected $expect" . ($needle ? ", containing \"$needle\"" : '') . ')');
    if ($verbose || !$ok) {
        echo '        ', $a['text'], "\n";
    }
}
echo "\n$pass of " . count($cases) . " questions answered as expected.\n";
