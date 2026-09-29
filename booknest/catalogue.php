<?php
// Browse: server side search, category filter and sort, instant refine, and member serial lookup.
require __DIR__ . '/includes/bootstrap.php';

$q = input($_GET, 'q', 100);
$categories = db_all('SELECT id, name, slug FROM categories ORDER BY sort_order');
$slugs = array_column($categories, null, 'slug');
$catSlug = input($_GET, 'category', 60);
$category = $slugs[$catSlug] ?? null;

// Sort options are a whitelist; the user's value never reaches the SQL text.
$sorts = [
    'title' => ['Title A to Z', 'b.title ASC'],
    'newest' => ['Newest first', 'b.created_at DESC'],
    'rating' => ['Highest rated', 'b.rating DESC, b.title ASC'],
    'price_asc' => ['Price, low to high', 'b.price ASC, b.title ASC'],
    'price_desc' => ['Price, high to low', 'b.price DESC, b.title ASC'],
];
$sort = input($_GET, 'sort', 20);
if (!isset($sorts[$sort])) {
    $sort = $q !== '' ? 'rating' : 'title';
}

$where = ["b.status = 'approved'"];
$params = [];
if ($category) {
    $where[] = 'b.category_id = ?';
    $params[] = $category['id'];
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.serial_no LIKE ?)';
    array_push($params, $like, $like, $like);
}
$books = db_all('SELECT ' . card_columns() . ' FROM books b WHERE ' . implode(' AND ', $where)
    . ' ORDER BY ' . $sorts[$sort][1], $params);

// Members searching an exact serial number see full product details, whatever the status.
$isSerial = (bool) preg_match('/^BNT-\d{6}$/i', $q);
$serialBook = null;
if ($isSerial && current_user()) {
    $serialBook = db_one("SELECT b.*, c.name AS category, u.full_name AS added_by_name,
                                 (SELECT COALESCE(SUM(oi.qty), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                                  WHERE oi.book_id = b.id AND o.payment_status = 'paid') AS sold
                          FROM books b JOIN categories c ON c.id = b.category_id
                          LEFT JOIN users u ON u.id = b.added_by WHERE b.serial_no = ?", [strtoupper($q)]);
}

// Builds a catalogue link that keeps the current search while changing one parameter.
function catalogue_link(array $change): string
{
    global $q, $catSlug, $sort;
    $params = array_filter(array_merge(['q' => $q, 'category' => $catSlug, 'sort' => $sort], $change), fn($v) => $v !== '' && $v !== null);
    return url('catalogue.php' . ($params ? '?' . http_build_query($params) : ''));
}

$heading = $q !== '' ? 'Results for "' . $q . '"' : ($category ? $category['name'] : 'Browse the collection');
$page_title = $q !== '' ? 'Search: ' . $q : ($category ? $category['name'] : 'Browse');
$page_desc = 'Browse and search the BookNest collection by title, author, category or serial number.';
$body_class = 'page-catalogue';
$scripts = ['search-filter.js'];
$crumbs = [['Home', url()], ['Browse', $category || $q !== '' ? url('catalogue.php') : null]];
if ($category) {
    $crumbs[] = [$category['name'], null];
}
if ($q !== '') {
    $crumbs[] = ['Search', null];
}
require __DIR__ . '/includes/header.php';
$n = count($books);
?>

<div class="container">
  <header class="page-head">
    <h1><?= e($heading) ?></h1>
    <p class="lede" id="result-count" aria-live="polite" data-total="<?= $n ?>">
      <?= $n ?> <?= $n === 1 ? 'book' : 'books' ?><?= $q !== '' ? ' match "' . e($q) . '"' : '' ?><?= $category ? ' in ' . e($category['name']) : '' ?>.
    </p>
  </header>

  <nav class="chips" aria-label="Categories">
    <a class="chip" href="<?= e(catalogue_link(['category' => ''])) ?>"<?= $category ? '' : ' aria-current="page"' ?>>All</a>
    <?php foreach ($categories as $c): ?>
    <a class="chip" href="<?= e(catalogue_link(['category' => $c['slug']])) ?>"<?= $category && $category['id'] === $c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="toolbar">
    <div class="field refine" data-refine hidden>
      <label for="refine">Refine these results</label>
      <input type="search" id="refine" placeholder="Type to filter by title or author" maxlength="60" autocomplete="off">
    </div>
    <form class="sort-form" action="<?= e(url('catalogue.php')) ?>" method="get">
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($category): ?><input type="hidden" name="category" value="<?= e($category['slug']) ?>"><?php endif; ?>
      <div class="field">
        <label for="sort">Sort by</label>
        <select id="sort" name="sort" data-autosubmit>
          <?php foreach ($sorts as $key => [$label]): ?>
          <option value="<?= e($key) ?>"<?= $key === $sort ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-secondary" type="submit" data-autosubmit-btn>Sort</button>
    </form>
  </div>

  <?php if ($serialBook): [$stockText, $stockClass] = stock_label((int) $serialBook['stock']); ?>
  <section class="serial-panel panel" aria-labelledby="serial-title">
    <?= cover_img($serialBook, 'serial-cover', false, 160) ?>
    <div>
      <p class="eyebrow">Exact serial match · members only</p>
      <h2 id="serial-title"><?= e($serialBook['title']) ?></h2>
      <p class="muted"><?= e($serialBook['author']) ?></p>
      <dl class="spec-list">
        <div><dt>Serial number</dt><dd><?= e($serialBook['serial_no']) ?></dd></div>
        <div><dt>Status</dt><dd><span class="pill is-<?= e($serialBook['status']) ?>"><?= e(ucfirst($serialBook['status'])) ?></span></dd></div>
        <div><dt>Stock</dt><dd><span class="pill <?= $stockClass ?>"><?= (int) $serialBook['stock'] ?> copies · <?= e($stockText) ?></span></dd></div>
        <div><dt>Copies sold</dt><dd><?= (int) $serialBook['sold'] ?></dd></div>
        <div><dt>Price</dt><dd><?= money($serialBook['price']) ?></dd></div>
        <div><dt>Category</dt><dd><?= e($serialBook['category']) ?></dd></div>
        <div><dt>Published</dt><dd><?= e(year_label((int) $serialBook['published_year'])) ?></dd></div>
        <div><dt>Pages</dt><dd><?= (int) $serialBook['pages'] ?></dd></div>
        <div><dt>Added by</dt><dd><?= e($serialBook['added_by_name'] ?? 'Library staff') ?></dd></div>
        <div><dt>Date added</dt><dd><?= e(date('j M Y', strtotime($serialBook['created_at']))) ?></dd></div>
      </dl>
      <?php if ($serialBook['status'] === 'approved'): ?>
      <a class="btn btn-secondary btn-sm" href="<?= e(book_url((int) $serialBook['id'])) ?>">Open book page</a>
      <?php endif; ?>
    </div>
  </section>
  <?php elseif ($isSerial && !current_user()): ?>
  <p class="notice"><?= icon('lock', 18) ?> Serial number lookup with stock and product details is a member feature. <a href="<?= e(url('sign-in.php?return=' . rawurlencode(current_path()))) ?>">Sign in</a> to see it.</p>
  <?php elseif ($isSerial): ?>
  <p class="notice">No book has the serial number <?= e(strtoupper($q)) ?>. Members can <a href="<?= e(url('add-book.php')) ?>">add it to the library</a>.</p>
  <?php endif; ?>

  <?php if ($books): ?>
  <div class="card-grid" data-filter-grid>
    <?php foreach ($books as $i => $b): ?>
    <?= book_card($b, $i > 11) ?>
    <?php endforeach; ?>
  </div>
  <div data-filter-empty hidden>
    <?= empty_state('Nothing matches that filter', 'Try fewer letters, or clear the refine box to see all results again.') ?>
  </div>
  <?php else: ?>
  <?= empty_state(
      $q !== '' ? 'No results for "' . $q . '"' : 'No books here yet',
      'Check the spelling, try an author\'s surname, or browse a category instead. Can\'t find a book we should have? Suggest it.',
      '<a class="btn btn-secondary btn-sm" href="' . e(url('catalogue.php?category=classics')) . '">Browse Classics</a>'
      . '<a class="btn btn-secondary btn-sm" href="' . e(url('catalogue.php?category=mystery')) . '">Browse Mystery</a>'
      . '<a class="btn btn-primary btn-sm" href="' . e(url('add-book.php')) . '">Suggest a book</a>'
  ) ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
