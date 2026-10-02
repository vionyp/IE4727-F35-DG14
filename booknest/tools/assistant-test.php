<?php
// Asks Paige a list of questions and checks each lands on the expected topic.
// Run:  C:\xampp\php\php.exe tools\assistant-test.php        (add -v to print every answer)
require __DIR__ . '/../includes/bootstrap.php';

$verbose = in_array('-v', $argv, true);
$member = db_one("SELECT id, full_name, email, role, created_at FROM users WHERE email = 'aisha@localhost'");
// [question, expected topic, signed in?, text the answer must contain (optional)]
$cases = [
    ['Opening hours', 'hours', false, '10:00 to 21:00'],
    ['what time do you close today', 'hours', false, ''],
    ['Where are you located?', 'hours', false, 'Nanyang'],
    ['What do I have on loan?', 'loans', false, 'only show loans to the member'],
    ['What do I have on loan?', 'loans', true, 'Atomic Habits, due back'],
    ['when is my book due', 'loans', true, 'Atomic Habits'],
    ['How do I borrow?', 'borrow', false, 'press Borrow'],
    ['can i buy this book', 'borrow', false, 'We do not sell books'],
    ['how long can I keep a book', 'borrow', false, '14 days'],
    ['Late fees', 'fees', false, 'S$0.50'],
    ['how much do I owe', 'fees', true, 'You owe S$2.00'],
    ['my book is overdue', 'fees', true, ''],
    ['how does the queue work', 'queue', false, 'Join the queue'],
    ['am I on the waiting list', 'queue', true, 'Fourth Wing'],
    ['Can I return a book early?', 'return', false, 'front desk'],
    ['Book a study room', 'rooms', false, 'signed in'],
    ['how do I cancel my room booking', 'rooms', true, 'Cancel'],
    ['when is my next room', 'rooms', true, ''],
    ['do you deliver books', 'collect', false, 'collection day'],
    ['where do I pick up my book', 'collect', false, 'front desk'],
    ['Recommend a book', 'recommend', false, 'most borrowed'],
    ['recommend a good thriller', 'recommend', false, 'Mystery and Thriller'],
    ['any books about money?', 'recommend', false, ''],
    ['Do you have Atomic Habits?', 'book-search', false, 'Atomic Habits'],
    ['is sapiens available', 'book-search', false, 'Sapiens'],
    ['looking for something by Andy Weir', 'book-search', false, 'Andy Weir'],
    ['do you have the lord of the rings', 'book-search', false, 'could not find'],
    ['what is BNT-000123', 'serial', true, 'The Da Vinci Code'],
    ['what is a serial number', 'serial', false, ''],
    ['I forgot my password', 'account', false, 'cannot reset'],
    ['how do I create an account', 'account', false, 'borrow books'],
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
