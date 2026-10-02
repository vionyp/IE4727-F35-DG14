<?php
// Book detail: cover, facts, synopsis, availability with borrow or queue actions, shelf, and similar books.
require __DIR__ . '/includes/bootstrap.php';

$id = input_int($_GET, 'id');
$book = $id ? db_one("SELECT b.*, " . availability_columns() . ", c.name AS category, c.slug AS category_slug FROM books b
                      JOIN categories c ON c.id = b.category_id WHERE b.id = ? AND b.status = 'approved'", [$id]) : null;

if (!$book) {
    http_response_code(404);
    $page_title = 'Book not found';
    $crumbs = [['Home', url()], ['Browse', url('catalogue.php')], ['Not found', null]];
    require __DIR__ . '/includes/header.php';
    echo '<div class="container section">' . empty_state('We could not find that book',
        'It may have been removed, or the link is incomplete. Try searching for it instead.',
        '<a class="btn btn-primary btn-sm" href="' . e(url('catalogue.php')) . '">Browse all books</a>') . '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

remember_recent((int) $book['id']);
$user = current_user();
$onShelf = $user && db_value('SELECT 1 FROM shelf WHERE user_id = ? AND book_id = ?', [$user['id'], $book['id']]);
$loan = my_loans()[$book['id']] ?? null;
$entry = my_queue()[$book['id']] ?? null;
$available = max(0, (int) $book['stock'] - (int) $book['on_loan'] - (int) $book['held']);
$pagesInSample = $book['sample_text'] ? substr_count($book['sample_text'], '---PAGE---') + 1 : 0;
$similar = db_all('SELECT ' . card_columns() . " FROM books b WHERE b.status = 'approved' AND b.category_id = ? AND b.id <> ?
                   ORDER BY b.rating DESC LIMIT 8", [$book['category_id'], $book['id']]);

$page_title = $book['title'];
$page_desc = $book['title'] . ' by ' . $book['author'] . '. ' . $book['hook'];
$body_class = 'page-book';
$scripts = ['rows.js'];
$crumbs = [['Home', url()], [$book['category'], url('catalogue.php?category=' . $book['category_slug'])], [$book['title'], null]];
require __DIR__ . '/includes/header.php';
?>

<article class="container book-detail" style="--cover: url('<?= e(url('assets/' . cover_file($book, true))) ?>')">
  <div class="book-cover-wrap">
    <?= cover_img($book, 'book-cover', false, 360) ?>
  </div>

  <div class="book-info">
    <p class="eyebrow"><?= e($book['category']) ?></p>
    <h1><?= e($book['title']) ?></h1>
    <p class="book-author">by <?= e($book['author']) ?></p>
    <p class="book-facts"><?= rating_html($book['rating']) ?><span><?= e(year_label((int) $book['published_year'])) ?></span><span><?= e($book['format']) ?></span><span><?= (int) $book['pages'] ?> pages</span></p>

    <div class="avail-box">
      <p class="avail-line avail-lg"><?= availability_html($book) ?></p>
      <p class="small muted"><?= $available ?> of <?= (int) $book['stock'] ?> <?= (int) $book['stock'] === 1 ? 'copy' : 'copies' ?> on the shelf · <?= LOAN_DAYS ?> day loans, free for members</p>
    </div>

    <div class="book-actions">
      <?php if (!$user): ?>
      <a class="btn btn-primary" href="<?= e(url('sign-in.php?return=' . rawurlencode(url('borrow.php?id=' . $book['id'])))) ?>"><?= icon('books', 18) ?> Sign in to borrow</a>
      <?php elseif ($loan): ?>
      <a class="btn btn-primary" href="<?= e(url('account.php#loans')) ?>"><?= icon('books', 18) ?> See your loan</a>
      <?php elseif ($entry && $entry['status'] === 'offered'): ?>
      <a class="btn btn-primary" href="<?= e(url('borrow.php?id=' . $book['id'])) ?>"><?= icon('books', 18) ?> Borrow your held copy</a>
      <?php elseif ($entry): ?>
      <form action="<?= e(url('process/loan.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <button class="btn btn-secondary" type="submit" name="action" value="leave">Leave the queue</button>
      </form>
      <?php elseif ($available > 0): ?>
      <a class="btn btn-primary" href="<?= e(url('borrow.php?id=' . $book['id'])) ?>"><?= icon('books', 18) ?> Borrow</a>
      <?php elseif ((int) $book['stock'] > 0): ?>
      <form action="<?= e(url('process/loan.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <button class="btn btn-primary" type="submit" name="action" value="queue"><?= icon('queue', 18) ?> Join the queue</button>
      </form>
      <?php endif; ?>
      <?php if ($pagesInSample): ?>
      <a class="btn btn-ghost" href="<?= e(url('read.php?id=' . $book['id'])) ?>"><?= icon('book', 18) ?> Read a <?= sample_word($book) ?></a>
      <?php endif; ?>
      <?php if ($user): ?>
      <form action="<?= e(url('process/shelf.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <input type="hidden" name="action" value="<?= $onShelf ? 'remove' : 'add' ?>">
        <button class="btn btn-ghost" type="submit" aria-pressed="<?= $onShelf ? 'true' : 'false' ?>"><?= icon($onShelf ? 'check' : 'bookmark', 18) ?> <?= $onShelf ? 'On your shelf' : 'Save to shelf' ?></button>
      </form>
      <?php else: ?>
      <a class="btn btn-ghost" href="<?= e(url('sign-in.php?return=' . rawurlencode(current_path()))) ?>"><?= icon('bookmark', 18) ?> Sign in to save</a>
      <?php endif; ?>
    </div>
    <?php if ($entry && $entry['status'] === 'waiting'): ?>
    <p class="notice"><?= icon('queue', 18) ?> We will email you as soon as a copy is yours, then hold it for <?= QUEUE_HOLD_DAYS ?> days. Meanwhile you can read the <?= sample_word($book) ?>.</p>
    <?php elseif (!$loan && !$entry && $available <= 0 && (int) $book['stock'] > 0): ?>
    <p class="notice">Every copy is out. Join the queue and we will email you when one comes back. You can still read the <?= sample_word($book) ?> now.</p>
    <?php elseif ((int) $book['stock'] <= 0): ?>
    <p class="notice">We have no copies of this book yet. You can still read the <?= sample_word($book) ?>, and save it to your shelf for later.</p>
    <?php endif; ?>

    <section class="synopsis" aria-labelledby="synopsis-title">
      <h2 id="synopsis-title">About the book</h2>
      <p class="book-hook"><?= e($book['hook']) ?></p>
      <p><?= e($book['synopsis']) ?></p>
    </section>

    <section aria-labelledby="details-title">
      <h2 id="details-title" class="visually-hidden">Details</h2>
      <dl class="spec-list">
        <div><dt>Serial number</dt><dd><?= e($book['serial_no']) ?></dd></div>
        <?php if ($book['isbn']): ?><div><dt>ISBN</dt><dd><?= e($book['isbn']) ?></dd></div><?php endif; ?>
        <?php if ($book['publisher']): ?><div><dt>Publisher</dt><dd><?= e($book['publisher']) ?></dd></div><?php endif; ?>
        <div><dt>Format</dt><dd><?= e($book['format']) ?></dd></div>
        <div><dt>Category</dt><dd><a href="<?= e(url('catalogue.php?category=' . $book['category_slug'])) ?>"><?= e($book['category']) ?></a></dd></div>
        <div><dt>First published</dt><dd><?= e(year_label((int) $book['published_year'])) ?></dd></div>
        <div><dt>Length</dt><dd><?= (int) $book['pages'] ?> pages</dd></div>
        <div><dt><?= sample_word($book) === 'preview' ? 'BookNest preview' : 'Free sample' ?></dt><dd><?= $pagesInSample ? $pagesInSample . ' pages' . (sample_word($book) === 'preview' ? ', written by us' : ' from the book') : 'Not available' ?></dd></div>
        <div><dt>Copies</dt><dd><?= (int) $book['stock'] ?> owned by the library</dd></div>
        <div><dt>Loan period</dt><dd><?= LOAN_DAYS ?> days, collected at the front desk</dd></div>
        <div><dt>Late returns</dt><dd><?= money(LATE_FEE_PER_DAY) ?> a day after the due date</dd></div>
      </dl>
    </section>
  </div>
</article>

<div class="container">
  <?= book_row('row-similar', 'You may also like', $similar, url('catalogue.php?category=' . $book['category_slug']), 'More from ' . $book['category'] . '.') ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
