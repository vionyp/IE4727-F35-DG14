<?php
// Paige, the help assistant: receives a question, stores the answer in the session conversation,
// logs the question for staff, and returns to the page the visitor was on.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$back = back_url();
$back .= (str_contains($back, '#') ? '' : '#assistant');

if (!empty($_POST['clear'])) {
    unset($_SESSION['assistant']);
    $_SESSION['assistant_open'] = true;
    redirect($back);
}

// A quick question button sends its own text; otherwise use what was typed.
$message = input($_POST, 'quick', 60) ?: input($_POST, 'message', 300);
$message = preg_replace('/\s+/', ' ', $message);
if ($message === '') {
    $_SESSION['assistant_open'] = true;
    redirect($back);
}

$user = current_user();
$answer = assistant_reply($message, $user);

assistant_push('user', $message);
assistant_push('bot', $answer['text'], $answer['links']);
db_exec('INSERT INTO assistant_log (user_id, question, intent, answered) VALUES (?, ?, ?, ?)',
    [$user['id'] ?? null, $message, $answer['intent'], $answer['answered'] ? 1 : 0]);

$_SESSION['assistant_open'] = true;
redirect($back);
