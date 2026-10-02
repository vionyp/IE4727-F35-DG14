<?php
// Members: look up a book by serial number, and add a new book to the library for review.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('Please sign in to add a book or look up a serial number.');

$lookup = strtoupper(input($_GET, 'serial', 10));
$found = null;
$lookupError = '';
if ($lookup !== '') {
    if (!preg_match('/^BNT-\d{6}$/', $lookup)) {
        $lookupError = 'Serial numbers look like BNT-000123: the letters BNT, a hyphen and six digits.';
    } else {
        $found = db_one("SELECT b.*, c.name AS category, u.full_name AS added_by_name FROM books b
                         JOIN categories c ON c.id = b.category_id LEFT JOIN users u ON u.id = b.added_by
                         WHERE b.serial_no = ?", [$lookup]);
        if (!$found) {
            $lookupError = 'No book has the serial number ' . $lookup . '. You can add it below.';
        }
    }
}

$categories = db_all('SELECT id, name FROM categories ORDER BY sort_order');
$nextNumber = (int) db_value("SELECT COALESCE(MAX(CAST(SUBSTRING(serial_no, 5) AS UNSIGNED)), 100) + 1 FROM books");
$suggested = sprintf('BNT-%06d', $nextNumber);
$serialValue = old('addbook', 'serial_no', $lookupError && $lookup !== '' && preg_match('/^BNT-\d{6}$/', $lookup) ? $lookup : $suggested);
$palette = cover_palette((int) substr($serialValue, 4));
$thisYear = (int) date('Y');

$page_title = 'Add a book';
$page_desc = 'Look up a book by serial number, or add a new book to the BookNest library.';
$body_class = 'page-addbook';
$scripts = ['forms.js'];
$crumbs = [['Home', url()], ['My Account', url('account.php')], ['Add a book', null]];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <header class="page-head">
    <h1>Add a book to the library</h1>
    <p class="lede">Check the serial number first. If we do not have the book, tell us about it and a librarian will review it, usually within a day.</p>
  </header>

  <section class="panel lookup" aria-labelledby="lookup-title">
    <h2 id="lookup-title" class="panel-title">Serial number lookup</h2>
    <form class="lookup-form" action="<?= e(url('add-book.php')) ?>" method="get">
      <div class="field">
        <label for="lookup-serial">Serial number</label>
        <input type="text" id="lookup-serial" name="serial" required maxlength="10" pattern="[Bb][Nn][Tt]-\d{6}" placeholder="BNT-000123" value="<?= e($lookup) ?>" data-uppercase autocomplete="off"<?= $lookupError ? ' aria-describedby="lookup-msg"' : '' ?>>
      </div>
      <button class="btn btn-secondary" type="submit"><?= icon('search', 18) ?> Look up</button>
    </form>
    <?php if ($lookupError): ?>
    <p class="notice" id="lookup-msg" role="status"><?= e($lookupError) ?></p>
    <?php elseif ($found): $counts = book_counts((int) $found['id']); ?>
    <div class="serial-panel" role="status">
      <?= cover_img($found, 'serial-cover', false, 120) ?>
      <div>
        <h3><?= e($found['title']) ?></h3>
        <p class="muted"><?= e($found['author']) ?> · <?= e($found['category']) ?></p>
        <dl class="spec-list">
          <div><dt>Serial</dt><dd><?= e($found['serial_no']) ?></dd></div>
          <div><dt>ISBN</dt><dd><?= e($found['isbn'] ?? 'None') ?></dd></div>
          <div><dt>Publisher</dt><dd><?= e($found['publisher'] ?? 'Not given') ?></dd></div>
          <div><dt>Status</dt><dd><span class="pill is-<?= e($found['status']) ?>"><?= e(ucfirst($found['status'])) ?></span></dd></div>
          <div><dt>Copies</dt><dd><?= (int) $found['stock'] ?> owned · <?= $counts['on_loan'] ?> on loan · <?= $counts['available'] ?> on the shelf</dd></div>
          <div><dt>Published</dt><dd><?= e(year_label((int) $found['published_year'])) ?></dd></div>
          <div><dt>Pages</dt><dd><?= (int) $found['pages'] ?></dd></div>
          <div><dt>Added by</dt><dd><?= e($found['added_by_name'] ?? 'Library staff') ?></dd></div>
          <div><dt>Date added</dt><dd><?= e(date('j M Y', strtotime($found['created_at']))) ?></dd></div>
        </dl>
        <?php if ($found['status'] === 'approved'): ?><a class="btn btn-secondary btn-sm" href="<?= e(book_url((int) $found['id'])) ?>">Open book page</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <form class="addbook-layout" id="add" action="<?= e(url('process/add-book.php')) ?>" method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="panel form">
      <h2 class="panel-title">Book details</h2>
      <div class="form-grid cols-2">
        <div class="field">
          <label for="ab-serial">Serial number</label>
          <input type="text" id="ab-serial" name="serial_no" required maxlength="10" pattern="BNT-\d{6}" data-pattern-msg="Use BNT- followed by six digits, for example <?= e($suggested) ?>." data-uppercase value="<?= e($serialValue) ?>"<?= field_attrs('addbook', 'serial_no', 'ab-serial-hint') ?>>
          <p class="hint" id="ab-serial-hint">The next free number is <?= e($suggested) ?>.</p>
          <?= field_error_html('addbook', 'serial_no') ?>
        </div>
        <div class="field">
          <label for="ab-category">Category</label>
          <select id="ab-category" name="category_id" required<?= field_attrs('addbook', 'category_id') ?>>
            <option value="">Choose a category</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= old('addbook', 'category_id') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error_html('addbook', 'category_id') ?>
        </div>
        <div class="field span-2">
          <label for="ab-title">Title</label>
          <input type="text" id="ab-title" name="title" required maxlength="150" value="<?= e(old('addbook', 'title')) ?>"<?= field_attrs('addbook', 'title') ?>>
          <?= field_error_html('addbook', 'title') ?>
        </div>
        <div class="field span-2">
          <label for="ab-author">Author</label>
          <input type="text" id="ab-author" name="author" required minlength="2" maxlength="100" value="<?= e(old('addbook', 'author')) ?>"<?= field_attrs('addbook', 'author') ?>>
          <?= field_error_html('addbook', 'author') ?>
        </div>
        <div class="field">
          <label for="ab-stock">Copies you can give the library</label>
          <input type="number" id="ab-stock" name="stock" required min="0" max="999" step="1" value="<?= e(old('addbook', 'stock', '1')) ?>"<?= field_attrs('addbook', 'stock') ?>>
          <?= field_error_html('addbook', 'stock') ?>
        </div>
        <div class="field">
          <label for="ab-year">Year first published</label>
          <input type="number" id="ab-year" name="published_year" required min="1450" max="<?= $thisYear ?>" step="1" value="<?= e(old('addbook', 'published_year')) ?>"<?= field_attrs('addbook', 'published_year') ?>>
          <?= field_error_html('addbook', 'published_year') ?>
        </div>
        <div class="field">
          <label for="ab-pages">Pages</label>
          <input type="number" id="ab-pages" name="pages" required min="1" max="5000" step="1" value="<?= e(old('addbook', 'pages')) ?>"<?= field_attrs('addbook', 'pages') ?>>
          <?= field_error_html('addbook', 'pages') ?>
        </div>
        <div class="field span-2">
          <label for="ab-hook">One line pitch <span class="optional">(optional)</span></label>
          <input type="text" id="ab-hook" name="hook" maxlength="200" placeholder="Why would someone pick this book up?" value="<?= e(old('addbook', 'hook')) ?>">
        </div>
        <div class="field span-2">
          <label for="ab-synopsis">Synopsis</label>
          <textarea id="ab-synopsis" name="synopsis" rows="5" required minlength="40" maxlength="2000" data-count<?= field_attrs('addbook', 'synopsis') ?>><?= e(old('addbook', 'synopsis')) ?></textarea>
          <?= field_error_html('addbook', 'synopsis') ?>
        </div>
        <div class="field span-2">
          <label for="ab-sample">Sample text <span class="optional">(optional)</span></label>
          <p class="hint" id="ab-sample-hint">Paste the opening pages. Put a line with <code>---PAGE---</code> between pages. Use only public domain or your own writing.</p>
          <textarea id="ab-sample" name="sample_text" rows="8" maxlength="20000" data-count<?= field_attrs('addbook', 'sample_text', 'ab-sample-hint') ?>><?= e(old('addbook', 'sample_text')) ?></textarea>
          <p class="hint" data-pages-for="ab-sample" aria-live="polite"></p>
          <?= field_error_html('addbook', 'sample_text') ?>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Send for review</button>
    </div>

    <aside class="preview-col" aria-label="Cover preview">
      <div class="cover-preview" data-cover-preview style="--pv-bg: <?= e($palette['bg']) ?>; --pv-accent: <?= e($palette['accent']) ?>; --pv-bg2: <?= e($palette['bg2']) ?>">
        <span class="pv-label" data-preview-label>Category</span>
        <span class="pv-title" data-preview-title>Your book title</span>
        <span class="pv-author" data-preview-author>Author name</span>
        <span class="pv-art" aria-hidden="true"></span>
      </div>
      <p class="small muted">We design an original cover for every new book. This preview updates as you type.</p>
    </aside>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
