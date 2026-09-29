<?php
// Sign in: checks the password hash, starts a fresh session, remembers the email if asked.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$email = strtolower(input($_POST, 'email', 120));
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$return = safe_return(input($_POST, 'return', 300), 'account.php');
$fail = safe_return(input($_POST, 'fail', 300), 'sign-in.php?return=' . rawurlencode($return));

$errors = [];
if ($email === '') {
    $errors['email'] = 'Please enter your email.';
}
if ($password === '') {
    $errors['password'] = 'Please enter your password.';
}

$user = $errors ? null : db_one('SELECT id, full_name, password_hash FROM users WHERE email = ?', [$email]);
if (!$errors && (!$user || !password_verify($password, $user['password_hash']))) {
    app_log('Failed sign in for ' . $email);
    $errors['password'] = 'Email or password is incorrect.';
}

if ($errors) {
    keep_form('login', $_POST, $errors);
    flash('error', 'We could not sign you in. ' . reset($errors));
    redirect($fail);
}

sign_in_user((int) $user['id']);
if (!empty($_POST['remember'])) {
    setcookie(EMAIL_COOKIE, $email, ['expires' => time() + 90 * 86400, 'path' => BASE_URL, 'httponly' => true, 'samesite' => 'Lax']);
} else {
    setcookie(EMAIL_COOKIE, '', ['expires' => time() - 3600, 'path' => BASE_URL]);
}
flash('success', 'Welcome back, ' . explode(' ', $user['full_name'])[0] . '.');
redirect($return);
