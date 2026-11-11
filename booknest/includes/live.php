<?php
// Live updates: PHP tells the Node.js + Express server in live/ that something changed, and that server
// pushes it to the pages people have open (assets/js/live.js then updates those pages in place).
// Nothing here can break a request: if the live server is not running, the notes are simply dropped.

// True when assets/js/live.js is re-reading the page to update it, not a person opening it.
function is_live_refresh(): bool
{
    return ($_SERVER['HTTP_X_BOOKNEST_LIVE'] ?? '') === '1';
}

// A signed pass that lets this page listen for live updates: who is reading, whether they are staff,
// and until when. The live server checks the signature with the shared LIVE_SECRET.
function live_token(): string
{
    $user = current_user();
    $payload = rtrim(strtr(base64_encode(json_encode([
        'uid' => $user ? (int) $user['id'] : 0,
        'admin' => $user && $user['role'] === 'admin',
        'exp' => time() + LIVE_TOKEN_SECONDS,
    ])), '+/', '-_'), '=');
    return $payload . '.' . hash_hmac('sha256', $payload, LIVE_SECRET);
}

// Queues one live note. $to is a member id, 'admins' or 'all'. $topic says what changed ('me' for that
// member's own account, 'admin', 'book:12', 'rooms'). $message is shown to that member as a small notice.
// Notes are sent once the request has finished, so they always describe saved data.
function live_notify(int|string $to, string $topic, string $message = ''): void
{
    static $registered = false;
    if (is_int($to) && $to <= 0) {
        return;
    }
    $GLOBALS['live_queue'][] = ['to' => $to, 'topic' => $topic, 'message' => $message];
    if (!$registered) {
        $registered = true;
        register_shutdown_function('live_flush');
    }
}

// Sends the queued notes to the live server in one short request. A server that is not running answers
// at once with "connection refused", so a page never waits more than a moment.
function live_flush(): void
{
    $events = $GLOBALS['live_queue'] ?? [];
    $GLOBALS['live_queue'] = [];
    if (!$events) {
        return;
    }
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Live-Secret: " . LIVE_SECRET . "\r\n",
        'content' => json_encode(['events' => $events]),
        'timeout' => 0.5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents(LIVE_NOTIFY_URL, false, $context);
}
