<?php
// Register: validates, checks the email is unused, stores a password hash, signs the member in.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$name = preg_replace('/\s+/', ' ', input($_POST, 'full_name', 80));
$email = strtolower(input($_POST, 'email', 120));
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
$return = safe_return(input($_POST, 'return', 300), 'account.php');

$errors = [];
if (mb_strlen($name) < 2 || !preg_match("/^[\p{L} .'-]+$/u", $name)) {
    $errors['full_name'] = 'Enter your full name using letters, spaces, apostrophes or hyphens.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-z0-9._%+-]+@localhost$/', $email)) {
    $errors['email'] = 'Enter an email address like name@example.com.';
} elseif (db_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
    $errors['email'] = 'There is already an account with this email. Try signing in instead.';
}
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    $errors['password'] = 'Use at least 8 characters, with at least one letter and one number.';
}
if ($password !== $confirm) {
    $errors['confirm'] = 'The passwords do not match.';
}
if (empty($_POST['agree'])) {
    $errors['agree'] = 'Please agree to the study room and library rules.';
}

if ($errors) {
    keep_form('register', $_POST, $errors);
    flash('error', 'Please fix the highlighted fields to create your account.');
    redirect('sign-in.php?return=' . rawurlencode($return) . '#register');
}

db_exec("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'member')",
    [$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
sign_in_user(db_insert_id());
flash('success', 'Welcome to ' . SITE_NAME . ', ' . explode(' ', $name)[0] . '. You can now book study rooms and keep a shelf.');
redirect($return);
