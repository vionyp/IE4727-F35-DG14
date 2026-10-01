<?php
// Book detail: cover, facts, synopsis, buying and shelf actions, and similar books.
require __DIR__ . '/includes/bootstrap.php';

$id = input_int($_GET, 'id');
$book = $id ? db_one("SELECT b.*, c.name AS category, c.slug AS category_slug FROM books b
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
$inCart = cart()[$book['id']] ?? 0;
[$stockText, $stockClass] = stock_label((int) $book['stock']);
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

    <div class="buy-box">
      <p class="price"><?= money($book['price']) ?></p>
      <span class="pill <?= $stockClass ?>"><?= e($stockText) ?></span>
      <?php if ($inCart): ?><span class="pill"><?= icon('cart', 14) ?> <?= (int) $inCart ?> in your cart</span><?php endif; ?>
    </div>

    <div class="book-actions">
      <form action="<?= e(url('process/cart.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="book_id" value="<?= (int) $book['id'] ?>">
        <button class="btn btn-primary" type="submit" name="action" value="buy"<?= $book['stock'] > 0 ? '' : ' disabled' ?>>Buy now</button>
        <button class="btn btn-secondary" type="submit" name="action" value="add"<?= $book['stock'] > 0 ? '' : ' disabled' ?>><?= icon('cart', 18) ?> Add to cart</button>
      </form>
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
    <?php if ($book['stock'] <= 0): ?>
    <p class="notice">This edition is out of stock. You can still read the <?= sample_word($book) ?>, and save it to your shelf for later.</p>
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
        <div><dt>Delivery</dt><dd>Free over <?= money(FREE_DELIVERY_FROM) ?>, or collect at the library</dd></div>
      </dl>
    </section>
  </div>
</article>

<div class="container">
  <?= book_row('row-similar', 'You may also like', $similar, url('catalogue.php?category=' . $book['category_slug']), 'More from ' . $book['category'] . '.') ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
