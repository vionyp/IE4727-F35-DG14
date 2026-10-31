<?php
// Modern Enhancement: tests for the JSON endpoints in api/, run over HTTP like tools/e2e-test.php.
//   C:\xampp\php\php.exe tools\api-test.php
// Needs Apache and MySQL running and the seed data loaded. The loan tests renew and return books,
// so re-import sql/schema.sql and sql/seed.sql afterwards for a clean demo.
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
date_default_timezone_set(APP_TIMEZONE);

const BASE = 'http://localhost/booknest/';
$results = [];

// A small browser: keeps its own cookies, never follows redirects.
final class Client
{
    public string $jar;
    public function __construct(public string $name)
    {
        $this->jar = sys_get_temp_dir() . '/bn_api_' . $name . '_' . getmypid() . '.txt';
        @unlink($this->jar);
    }
    // Sends a request; $post makes it a form POST, $method overrides the verb.
    public function req(string $path, ?array $post = null, string $method = ''): array
    {
        $ch = curl_init(BASE . ltrim($path, '/'));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if ($method !== '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        $raw = (string) curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($raw, 0, $size);
        $body = substr($raw, $size);
        return ['code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body,
            'json' => json_decode($body, true), 'isJson' => (bool) preg_match('#^Content-Type:\s*application/json#mi', $headers)];
    }
    // Returns the session's CSRF token, read from a page that prints a form.
    public function csrf(): string
    {
        foreach (['sign-in.php', 'account.php'] as $page) {
            if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $this->req($page)['body'], $m)) {
                return $m[1];
            }
        }
        return '';
    }
    // Signs in through the normal login form.
    public function login(string $email, string $password): void
    {
        $this->req('process/login.php', ['email' => $email, 'password' => $password, 'csrf' => $this->csrf()]);
    }
}

// Records and prints one result.
function check(string $id, string $name, bool $pass, string $actual): void
{
    global $results;
    $results[] = [$id, $name, $pass, $actual];
    printf("%-5s %-4s %-60s %s\n", $id, $pass ? 'PASS' : 'FAIL', mb_strimwidth($name, 0, 60), mb_strimwidth($actual, 0, 120, '...'));
}

// Returns the text of the availability pill on a catalogue card for a book id, as PHP printed it.
function card_label(string $html, int $bookId): string
{
    $pattern = '#href="/booknest/book\.php\?id=' . $bookId . '".*?<span class="avail ([^"]*)">([^<]*)</span>#s';
    return preg_match($pattern, $html, $m) ? html_entity_decode($m[2], ENT_QUOTES) : '';
}

echo "BookNest api/ tests, " . date('Y-m-d H:i:s') . "\n" . str_repeat('-', 100) . "\n";

// ---- 1. api/books.php (live search) ---------------------------------------------------------------
$v = new Client('visitor');
$approved = (int) db_value("SELECT COUNT(*) FROM books WHERE status = 'approved'");

$r = $v->req('api/books.php?q=alice');
$b = $r['json']['books'][0] ?? [];
check('A01', 'books: "alice" returns Alice with availability', $r['code'] === 200 && $r['isJson'] && ($r['json']['count'] ?? 0) === 1
    && str_contains($b['title'] ?? '', 'Alice') && ($b['availability']['text'] ?? '') !== '' && ($b['category'] ?? '') !== '',
    "HTTP {$r['code']}, " . ($r['json']['count'] ?? '?') . ' book: ' . ($b['title'] ?? '-') . ' / ' . ($b['availability']['text'] ?? '-') . ' / ' . ($b['category'] ?? '-'));

$r = $v->req('api/books.php');
check('A02', 'books: empty query returns every approved book and the categories', $r['code'] === 200 && ($r['json']['count'] ?? 0) === $approved
    && count($r['json']['categories'] ?? []) === (int) db_value('SELECT COUNT(*) FROM categories'),
    ($r['json']['count'] ?? '?') . " books (database: $approved approved), " . count($r['json']['categories'] ?? []) . ' categories');

$pending = (string) db_value("SELECT title FROM books WHERE status = 'pending' ORDER BY id LIMIT 1");
$r = $v->req('api/books.php?q=' . rawurlencode($pending));
check('A03', 'books: a book waiting for review is never returned', $pending !== '' && ($r['json']['count'] ?? -1) === 0, "\"$pending\": " . ($r['json']['count'] ?? '?') . ' results');

$booksBefore = (int) db_value('SELECT COUNT(*) FROM books');
$r = $v->req('api/books.php?q=' . rawurlencode("' OR 1=1 -- "));
$r2 = $v->req('api/books.php?q=' . rawurlencode("%' UNION SELECT email, password_hash FROM users -- "));
check('A04', 'books: SQL injection returns nothing and changes nothing', $r['code'] === 200 && ($r['json']['count'] ?? -1) === 0
    && ($r2['json']['count'] ?? -1) === 0 && !str_contains($r2['body'], '$2y$') && (int) db_value('SELECT COUNT(*) FROM books') === $booksBefore,
    'OR 1=1: ' . ($r['json']['count'] ?? '?') . ', UNION: ' . ($r2['json']['count'] ?? '?') . " results, no hashes, books still $booksBefore");

$r = $v->req('api/books.php?q=' . rawurlencode('<img src=x onerror=alert(1)>'));
$esc = chr(92) . 'u003Cimg';
check('A05', 'books: output is JSON-escaped (no raw < in the body)', $r['isJson'] && !str_contains($r['body'], '<img') && str_contains($r['body'], $esc)
    && preg_match('/^X-Content-Type-Options:\s*nosniff/mi', $r['headers']) === 1, 'query echoed as ' . $esc . '..., nosniff sent');

$r = $v->req('api/books.php', ['q' => 'x']);
$rd = $v->req('api/books.php', null, 'DELETE');
check('A06', 'books: POST and DELETE are refused with 405', $r['code'] === 405 && $rd['code'] === 405 && preg_match('/^Allow:\s*GET/mi', $r['headers']) === 1
    && ($r['json']['error'] ?? '') !== '', "POST {$r['code']}, DELETE {$rd['code']}, \"" . ($r['json']['error'] ?? '') . '"');

$budy = new Client('budy');
$budy->login('budy@gmail.com', 'budy123!');
$deep = (int) db_value("SELECT id FROM books WHERE title = 'Deep Work'");
$apiLabel = '';
foreach ($budy->req('api/books.php?q=Deep%20Work')['json']['books'] ?? [] as $x) {
    if ($x['id'] === $deep) { $apiLabel = $x['availability']['text']; }
}
$pageLabel = card_label($budy->req('catalogue.php?q=Deep%20Work')['body'], $deep);
$visitorLabel = '';
foreach ($v->req('api/books.php?q=Deep%20Work')['json']['books'] ?? [] as $x) {
    if ($x['id'] === $deep) { $visitorLabel = $x['availability']['text']; }
}
check('A07', 'books: member-aware label equals the PHP card label', $apiLabel !== '' && $apiLabel === $pageLabel && $apiLabel !== $visitorLabel,
    "budy api \"$apiLabel\" = page \"$pageLabel\"; visitor sees \"$visitorLabel\"");

$r = $v->req('catalogue.php?q=hobbit');
check('A08', 'Browse page still renders results in PHP and loads the Vue files', $r['code'] === 200 && preg_match_all('/<article class="card"/', $r['body']) >= 1
    && str_contains($r['body'], 'data-filter-grid') && str_contains($r['body'], 'js/vue/boot.js') && str_contains($r['body'], 'js/vue/book-search.js'),
    preg_match_all('/<article class="card"/', $r['body']) . ' card(s) printed by PHP; boot.js and book-search.js included');

// ---- Summary --------------------------------------------------------------------------------------
$passed = count(array_filter($results, fn($x) => $x[2]));
echo str_repeat('-', 100) . "\n$passed of " . count($results) . " api checks passed.\n";
exit($passed === count($results) ? 0 : 1);
