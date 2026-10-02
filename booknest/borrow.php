<?php
// Borrow: the member picks a collection date for one book and confirms. Replaces the old cart and checkout.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('Please sign in to borrow a book. Membership is free.');

$id = input_int($_GET, 'id');
$book = $id ? db_one('SELECT ' . card_columns() . ", b.format, b.pages, c.name AS category, c.slug AS category_slug
                      FROM books b JOIN categories c ON c.id = b.category_id WHERE b.id = ? AND b.status = 'approved'", [$id]) : null;
if (!$book) {
    flash('info', 'Choose a book to borrow first.');
    redirect('catalogue.php');
}

$loan = my_loans()[$id] ?? null;
$entry = my_queue()[$id] ?? null;
$available = (int) $book['stock'] - (int) $book['on_loan'] - (int) $book['held'];
$offered = $entry && $entry['status'] === 'offered';
$canBorrow = !$loan && ($offered || (!$entry && $available > 0));

// Offered members must borrow within their hold; everyone else may choose up to a week ahead.
$dates = collection_dates();
if ($offered) {
    $dates = array_values(array_filter($dates, fn($d) => $d <= hold_until($entry)));
}
$chosen = old('borrow', 'collection_date', $dates[0] ?? '');

$page_title = 'Borrow ' . $book['title'];
$page_desc = 'Choose when to collect ' . $book['title'] . ' and confirm your loan.';
$body_class = 'page-borrow';
$crumbs = [['Home', url()], [$book['category'], url('catalogue.php?category=' . $book['category_slug'])],
           [$book['title'], book_url((int) $book['id'])], ['Borrow', null]];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <header class="page-head">
    <h1>Borrow this book</h1>
    <p class="lede">Choose the day you will collect it at the front desk. Your <?= LOAN_DAYS ?> day loan starts on that day.</p>
  </header>

  <div class="borrow-grid">
    <aside class="panel borrow-book" aria-labelledby="borrow-book-title">
      <?= cover_img($book, 'borrow-cover', false, 160) ?>
      <div>
        <h2 id="borrow-book-title" class="panel-title"><?= e($book['title']) ?></h2>
        <p class="muted"><?= e($book['author']) ?> · <?= e($book['format']) ?>, <?= (int) $book['pages'] ?> pages</p>
        <p class="avail-line"><?= availability_html($book) ?></p>
        <p class="small muted"><?= max(0, $available) ?> of <?= (int) $book['stock'] ?> <?= (int) $book['stock'] === 1 ? 'copy' : 'copies' ?> on the shelf now.</p>
      </div>
    </aside>

    <?php if ($loan): ?>
    <section class="panel" aria-labelledby="have-title">
      <h2 id="have-title" class="panel-title">You already have this book</h2>
      <p>It is due back on <strong><?= e(long_date($loan['due_date'])) ?></strong>. You can borrow one copy of a title at a time.</p>
      <a class="btn btn-primary" href="<?= e(url('account.php#loans')) ?>">See your loans</a>
    </section>

    <?php elseif ($entry && !$offered): ?>
    <section class="panel" aria-labelledby="queue-title">
      <h2 id="queue-title" class="panel-title">You are #<?= (int) $entry['position'] ?> in line</h2>
      <p>Every copy is out at the moment. We will email you as soon as one is yours, then hold it for you for <?= QUEUE_HOLD_DAYS ?> days.</p>
      <div class="cluster">
        <a class="btn btn-secondary" href="<?= e(book_url((int) $book['id'])) ?>">Back to the book</a>
        <form action="<?= e(url('process/loan.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
          <button class="btn btn-ghost" type="submit" name="action" value="leave">Leave the queue</button>
        </form>
      </div>
    </section>

    <?php elseif (!$canBorrow): ?>
    <section class="panel" aria-labelledby="out-title">
      <h2 id="out-title" class="panel-title"><?= (int) $book['stock'] > 0 ? 'Every copy is out right now' : 'We have no copies yet' ?></h2>
      <?php if ((int) $book['stock'] > 0): ?>
      <p>Join the queue and we will email you when a copy comes back. It will be held for you for <?= QUEUE_HOLD_DAYS ?> days.</p>
      <form action="<?= e(url('process/loan.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <button class="btn btn-primary" type="submit" name="action" value="queue"><?= icon('queue', 18) ?> Join the queue</button>
      </form>
      <?php else: ?>
      <p>Save it to your shelf and check back soon.</p>
      <a class="btn btn-secondary" href="<?= e(book_url((int) $book['id'])) ?>">Back to the book</a>
      <?php endif; ?>
    </section>

    <?php else: ?>
    <form class="panel form borrow-form" action="<?= e(url('process/loan.php')) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="borrow">
      <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
      <h2 class="panel-title"><?= $offered ? 'Your copy is waiting' : 'When will you collect it?' ?></h2>
      <?php if ($offered): ?>
      <p class="notice"><?= icon('check', 18) ?> We are holding a copy for you until <?= e(long_date(hold_until($entry))) ?>.</p>
      <?php endif; ?>
      <div class="field">
        <label for="collection-date">Collection date</label>
        <select id="collection-date" name="collection_date" required<?= field_attrs('borrow', 'collection_date', 'collection-hint') ?>>
          <?php foreach ($dates as $i => $d): ?>
          <option value="<?= e($d) ?>"<?= $d === $chosen ? ' selected' : '' ?>><?= $d === date('Y-m-d') ? 'Today, ' : '' ?><?= e(short_date($d)) ?> · due back <?= e(short_date(due_date_for($d))) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint" id="collection-hint">Collect it at the front desk on that day, between <?= sprintf('%02d:00 and %02d:00', OPEN_HOUR, CLOSE_HOUR) ?>.</p>
        <?= field_error_html('borrow', 'collection_date') ?>
      </div>
      <ul class="rules-list" role="list">
        <li><?= icon('clock', 18) ?> The loan lasts <?= LOAN_DAYS ?> days from the day you collect.</li>
        <li><?= icon('return', 18) ?> Return it early whenever you like, from My Account or at the desk.</li>
        <li><?= icon('lock', 18) ?> Late returns cost <?= money(LATE_FEE_PER_DAY) ?> for every day after the due date.</li>
      </ul>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Confirm loan</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
