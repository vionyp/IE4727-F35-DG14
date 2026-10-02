<?php
// Sample reader: server renders every page; reader.js turns them into a book with page turns.
require __DIR__ . '/includes/bootstrap.php';

$id = input_int($_GET, 'id');
$book = $id ? db_one("SELECT b.id, b.title, b.author, b.stock, b.sample_text, b.sample_type, b.cover_path, b.cover_alt, c.name AS category, c.slug AS category_slug
                      FROM books b JOIN categories c ON c.id = b.category_id WHERE b.id = ? AND b.status = 'approved'", [$id]) : null;
if (!$book || trim((string) $book['sample_text']) === '') {
    flash('info', 'There is nothing to read for that book yet.');
    redirect($book ? 'book.php?id=' . $book['id'] : 'catalogue.php');
}

// Splits the stored sample into pages, and each page into headings and paragraphs.
$pages = [];
foreach (preg_split('/\R?---PAGE---\R?/', trim($book['sample_text'])) as $raw) {
    $blocks = [];
    foreach (preg_split('/\R\s*\R/', trim($raw)) as $para) {
        $para = trim($para);
        if ($para === '') {
            continue;
        }
        $blocks[] = str_starts_with($para, '# ')
            ? ['h', substr($para, 2)]
            : ['p', preg_replace('/\s+/', ' ', $para)];
    }
    if ($blocks) {
        $pages[] = $blocks;
    }
}
$total = count($pages) + 2;
$isPreview = $book['sample_type'] === 'preview';
$word = $isPreview ? 'preview' : 'sample';

$page_title = 'Reading ' . $book['title'];
$page_desc = ($isPreview ? 'Read a short BookNest preview of ' : 'Read a free ' . count($pages) . ' page sample of ') . $book['title'] . ' by ' . $book['author'] . '.';
$body_class = 'page-reader';
$scripts = ['reader.js'];
$crumbs = [['Home', url()], [$book['category'], url('catalogue.php?category=' . $book['category_slug'])],
           [$book['title'], book_url((int) $book['id'])], [ucfirst($word), null]];
require __DIR__ . '/includes/header.php';
?>

<div class="reader" data-reader data-book-id="<?= (int) $book['id'] ?>">
  <div class="container reader-bar">
    <a class="btn btn-ghost btn-sm" href="<?= e(book_url((int) $book['id'])) ?>"><?= icon('x', 16) ?> Close</a>
    <p class="reader-title"><strong><?= e($book['title']) ?></strong> <span class="muted">· <?= $isPreview ? 'BookNest preview' : 'Free sample' ?></span></p>
    <p class="reader-count" data-reader-count aria-live="polite"><?= $total ?> pages</p>
  </div>

  <div class="reader-stage" data-reader-stage>
    <div class="reader-book" data-reader-book>
      <section class="page page-title" aria-label="Title page">
        <div class="page-body">
          <?= cover_img($book, 'title-cover', false, 180) ?>
          <h1 class="title-name"><?= e($book['title']) ?></h1>
          <p class="title-author"><?= e($book['author']) ?></p>
          <p class="title-note"><?= $isPreview ? 'A ' . count($pages) . ' page preview written by BookNest, not an excerpt' : 'A free sample of ' . count($pages) . ' pages' ?></p>
        </div>
      </section>

      <?php foreach ($pages as $i => $blocks): ?>
      <section class="page" aria-label="Page <?= $i + 2 ?>">
        <div class="page-body">
          <?php foreach ($blocks as [$type, $text]): ?>
          <?php if ($type === 'h'): ?>
          <h2 class="page-heading"><?= e($text) ?></h2>
          <?php else: ?>
          <p><?= e($text) ?></p>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <p class="page-num" aria-hidden="true"><?= $i + 2 ?></p>
      </section>
      <?php endforeach; ?>

      <section class="page page-end" aria-label="End of <?= $word ?>">
        <div class="page-body">
          <p class="eyebrow">End of <?= $word ?></p>
          <h2 class="title-name"><?= $isPreview ? 'Sounds like your kind of book?' : 'Enjoying it?' ?></h2>
          <p><?= $isPreview ? 'Borrow ' . e($book['title']) . ' free for ' . LOAN_DAYS . ' days and collect it at the front desk.' : 'The rest of ' . e($book['title']) . ' is waiting. Borrow it free for ' . LOAN_DAYS . ' days and collect it at the front desk.' ?></p>
          <a class="btn btn-primary" href="<?= e(url('borrow.php?id=' . $book['id'])) ?>">Borrow this book</a>
          <a class="btn btn-ghost-paper" href="<?= e(book_url((int) $book['id'])) ?>">Back to book</a>
        </div>
      </section>
    </div>
  </div>

  <div class="container reader-controls" data-reader-controls>
    <button type="button" class="btn btn-secondary" data-reader-prev><?= icon('left', 18) ?> Previous</button>
    <div class="reader-progress" aria-hidden="true"><span data-reader-progress></span></div>
    <button type="button" class="btn btn-secondary" data-reader-next>Next <?= icon('right', 18) ?></button>
  </div>
  <p class="container reader-help small muted" data-reader-help>Swipe or drag the page, use the arrow keys, or click the edge of a page to turn it.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
