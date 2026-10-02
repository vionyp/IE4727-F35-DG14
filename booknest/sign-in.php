<?php
// Sign in and register: two forms in accessible tabs (both visible without JavaScript).
require __DIR__ . '/includes/bootstrap.php';

$return = safe_return(input($_GET, 'return', 300), 'account.php');
if (current_user()) {
    redirect($return);
}
$registerFirst = form_state()['name'] === 'register';
$rememberedEmail = is_string($_COOKIE[EMAIL_COOKIE] ?? null) ? $_COOKIE[EMAIL_COOKIE] : '';

$page_title = 'Sign in or join';
$page_desc = 'Sign in to borrow books, book study rooms, keep a shelf and add books, or create a free account.';
$body_class = 'page-signin';
$scripts = ['forms.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container signin-layout">
  <section class="signin-art" aria-labelledby="signin-why">
    <img src="<?= e(url('assets/img/reading-lamp.svg')) ?>" alt="Illustration of a desk lamp lighting an open book at night" width="520" height="420">
    <h1 id="signin-why">Your corner of the library.</h1>
    <ul class="perks" role="list">
      <li><?= icon('books', 20) ?><span><strong>Borrow books</strong> free for <?= LOAN_DAYS ?> days, or queue for popular ones.</span></li>
      <li><?= icon('door', 20) ?><span><strong>Book study rooms</strong> for up to an hour a day.</span></li>
      <li><?= icon('bookmark', 20) ?><span><strong>Keep a shelf</strong> of books to read next.</span></li>
      <li><?= icon('search', 20) ?><span><strong>Look up serial numbers</strong> with copies and loan details.</span></li>
      <li><?= icon('plus', 20) ?><span><strong>Add books</strong> you think we should stock.</span></li>
    </ul>
  </section>

  <div class="panel tabs signin-panel" data-tabs>
    <div role="tablist" aria-label="Sign in or create an account">
      <button type="button" role="tab" id="tab-signin" aria-controls="signin" aria-selected="<?= $registerFirst ? 'false' : 'true' ?>">Sign in</button>
      <button type="button" role="tab" id="tab-register" aria-controls="register" aria-selected="<?= $registerFirst ? 'true' : 'false' ?>">Create account</button>
    </div>

    <section id="signin" role="tabpanel" aria-labelledby="tab-signin">
      <h2 class="panel-title">Welcome back</h2>
      <form class="form" id="login" action="<?= e(url('process/login.php')) ?>" method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <div class="field">
          <label for="li-email">Email</label>
          <input type="email" id="li-email" name="email" required maxlength="120" autocomplete="email" value="<?= e(old('login', 'email', $rememberedEmail)) ?>"<?= field_attrs('login', 'email') ?>>
          <?= field_error_html('login', 'email') ?>
        </div>
        <div class="field">
          <label for="li-password">Password</label>
          <input type="password" id="li-password" name="password" required autocomplete="current-password"<?= field_attrs('login', 'password') ?>>
          <?= field_error_html('login', 'password') ?>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"<?= $rememberedEmail !== '' ? ' checked' : '' ?>> Remember my email on this device</label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      </form>
      <details class="demo-accounts">
        <summary>Demo accounts for testing</summary>
        <p class="small">Member: <code>aisha@localhost</code> / <code>Member123!</code><br>Admin: <code>admin@localhost</code> / <code>Admin123!</code></p>
      </details>
    </section>

    <section id="register" role="tabpanel" aria-labelledby="tab-register">
      <h2 class="panel-title">Join for free</h2>
      <form class="form" id="reg" action="<?= e(url('process/register.php')) ?>" method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <div class="field">
          <label for="rg-name">Full name</label>
          <input type="text" id="rg-name" name="full_name" required minlength="2" maxlength="80" autocomplete="name" data-name-rule value="<?= e(old('register', 'full_name')) ?>"<?= field_attrs('register', 'full_name') ?>>
          <?= field_error_html('register', 'full_name') ?>
        </div>
        <div class="field">
          <label for="rg-email">Email</label>
          <input type="email" id="rg-email" name="email" required maxlength="120" autocomplete="email" value="<?= e(old('register', 'email')) ?>"<?= field_attrs('register', 'email') ?>>
          <?= field_error_html('register', 'email') ?>
        </div>
        <div class="form-grid cols-2">
          <div class="field">
            <label for="rg-password">Password</label>
            <input type="password" id="rg-password" name="password" required minlength="8" autocomplete="new-password" data-strength="rg-strength"<?= field_attrs('register', 'password', 'rg-strength') ?>>
            <p class="hint" id="rg-strength">At least 8 characters, with a letter and a number.</p>
            <?= field_error_html('register', 'password') ?>
          </div>
          <div class="field">
            <label for="rg-confirm">Confirm password</label>
            <input type="password" id="rg-confirm" name="confirm" required autocomplete="new-password" data-match="password"<?= field_attrs('register', 'confirm') ?>>
            <?= field_error_html('register', 'confirm') ?>
          </div>
        </div>
        <div class="field">
          <label class="check"><input type="checkbox" name="agree" value="1" required<?= old('register', 'agree') ? ' checked' : '' ?><?= field_attrs('register', 'agree') ?>> I agree to use study rooms fairly: at most one hour a day, and I will cancel if I cannot come.</label>
          <?= field_error_html('register', 'agree') ?>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create my account</button>
      </form>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
