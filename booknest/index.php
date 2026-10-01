<?php
// Home: featured billboard, sign in or personal welcome, book rows, study room teaser, opening hours.
require __DIR__ . '/includes/bootstrap.php';

$cols = card_columns();
$featured = db_one("SELECT b.*, c.name AS category, c.slug AS category_slug FROM books b JOIN categories c ON c.id = b.category_id
                    WHERE b.status = 'approved' AND b.is_featured = 1 ORDER BY b.id LIMIT 1")
    ?? db_one("SELECT b.*, c.name AS category, c.slug AS category_slug FROM books b JOIN categories c ON c.id = b.category_id
               WHERE b.status = 'approved' ORDER BY b.rating DESC LIMIT 1");

$newArrivals = db_all("SELECT $cols FROM books b WHERE b.status = 'approved' ORDER BY b.created_at DESC LIMIT 12");
$staffPicks = db_all("SELECT $cols FROM books b WHERE b.status = 'approved' AND b.is_staff_pick = 1 ORDER BY b.rating DESC");
$categories = db_all('SELECT id, name, slug, blurb FROM categories ORDER BY sort_order');
$byCategory = [];
foreach (db_all("SELECT $cols FROM books b WHERE b.status = 'approved' ORDER BY b.rating DESC, b.title") as $b) {
    $byCategory[$b['category_id']][] = $b;
}

// Recently viewed, from the cookie, kept in the order the reader saw them.
$recent = [];
if ($ids = recent_ids()) {
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows = db_all("SELECT $cols FROM books b WHERE b.status = 'approved' AND b.id IN ($marks)", $ids);
    $byId = array_column($rows, null, 'id');
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $recent[] = $byId[$id];
        }
    }
}

$user = current_user();
$nextBooking = null;
$shelfCount = 0;
if ($user) {
    $nextBooking = db_one("SELECT rb.booking_date, rb.start_time, rb.end_time, r.name FROM room_bookings rb
                           JOIN study_rooms r ON r.id = rb.room_id
                           WHERE rb.user_id = ? AND rb.status = 'confirmed'
                             AND (rb.booking_date > CURDATE() OR (rb.booking_date = CURDATE() AND rb.end_time > CURTIME()))
                           ORDER BY rb.booking_date, rb.start_time LIMIT 1", [$user['id']]);
    $shelfCount = (int) db_value('SELECT COUNT(*) FROM shelf WHERE user_id = ?', [$user['id']]);
}

$rooms = db_all('SELECT id, name, is_active FROM study_rooms ORDER BY id');
$freeToday = 0;
foreach (availability(date('Y-m-d'), $rooms, user_id()) as $slots) {
    $freeToday += count(array_filter($slots, fn($s) => $s === 'free'));
}
$status = library_status();

$page_title = 'Browse, sample and buy books';
$body_class = 'page-home';
$scripts = ['rows.js', 'forms.js'];
require __DIR__ . '/includes/header.php';
?>

<?php if ($featured): ?>
<section class="billboard" aria-labelledby="billboard-title" style="--cover: url('<?= e(url('assets/' . cover_file($featured, true))) ?>')">
  <div class="billboard-bg" aria-hidden="true"></div>
  <div class="container billboard-inner">
    <div class="billboard-copy">
      <p class="eyebrow">Featured this week · <?= e($featured['category']) ?></p>
      <h1 id="billboard-title"><?= e($featured['title']) ?></h1>
      <p class="billboard-meta"><?= e($featured['author']) ?> · <?= e(year_label((int) $featured['published_year'])) ?> · <?= rating_html($featured['rating']) ?></p>
      <p class="billboard-hook"><?= e($featured['hook']) ?></p>
      <div class="cluster billboard-actions">
        <form action="<?= e(url('process/cart.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="buy">
          <input type="hidden" name="book_id" value="<?= (int) $featured['id'] ?>">
          <button class="btn btn-primary" type="submit"<?= $featured['stock'] > 0 ? '' : ' disabled' ?>>Buy now · <?= money($featured['price']) ?></button>
        </form>
        <a class="btn btn-secondary" href="<?= e(url('read.php?id=' . $featured['id'])) ?>"><?= icon('book', 18) ?> Read a <?= sample_word($featured) ?></a>
        <a class="btn-link" href="<?= e(book_url((int) $featured['id'])) ?>">About this book</a>
      </div>
    </div>
    <a class="billboard-cover" href="<?= e(book_url((int) $featured['id'])) ?>" tabindex="-1" aria-hidden="true">
      <?= cover_img($featured, '', false, 320) ?>
    </a>
  </div>
</section>
<?php endif; ?>

<div class="container">
  <?php if ($user): ?>
  <section class="welcome panel" aria-labelledby="welcome-title">
    <div>
      <p class="eyebrow">Your library</p>
      <h2 id="welcome-title">Welcome back, <?= e(first_name($user)) ?>.</h2>
      <?php if ($nextBooking): ?>
      <p class="welcome-note"><?= icon('clock', 18) ?> Next study room: <strong><?= e($nextBooking['name']) ?></strong>, <?= e(short_date($nextBooking['booking_date'])) ?>, <?= e(hm($nextBooking['start_time'])) ?> to <?= e(hm($nextBooking['end_time'])) ?>.</p>
      <?php else: ?>
      <p class="welcome-note">No study room booked. <?= $freeToday ?> free slots are left today.</p>
      <?php endif; ?>
    </div>
    <ul class="welcome-links" role="list">
      <li><a class="btn btn-secondary" href="<?= e(url('account.php#shelf')) ?>"><?= icon('bookmark', 18) ?> My Shelf (<?= $shelfCount ?>)</a></li>
      <li><a class="btn btn-secondary" href="<?= e(url('rooms.php')) ?>"><?= icon('door', 18) ?> Study rooms</a></li>
      <li><a class="btn btn-secondary" href="<?= e(url('add-book.php')) ?>"><?= icon('plus', 18) ?> Add a book</a></li>
    </ul>
  </section>
  <?php else: ?>
  <section class="intro-grid" aria-label="Why BookNest">
    <div class="intro-points">
      <h2>Everything you need, three clicks away.</h2>
      <ol class="points" role="list">
        <li><span class="point-num">1</span><div><strong>Find it</strong><p>Search by title, author or serial number from any page.</p></div></li>
        <li><span class="point-num">2</span><div><strong>Try it</strong><p>Read a sample or a short preview of any book before you decide.</p></div></li>
        <li><span class="point-num">3</span><div><strong>Keep it</strong><p>Buy without an account, or join to book study rooms and keep a shelf.</p></div></li>
      </ol>
    </div>
    <div class="panel sign-in-card">
      <h2 class="panel-title">Member sign in</h2>
      <form class="form" action="<?= e(url('process/login.php')) ?>" method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e(url()) ?>">
        <div class="field">
          <label for="home-email">Email</label>
          <input type="email" id="home-email" name="email" required maxlength="120" autocomplete="email" value="<?= e(old('login', 'email', (string) ($_COOKIE[EMAIL_COOKIE] ?? ''))) ?>"<?= field_attrs('login', 'email') ?>>
          <?= field_error_html('login', 'email') ?>
        </div>
        <div class="field">
          <label for="home-password">Password</label>
          <input type="password" id="home-password" name="password" required autocomplete="current-password"<?= field_attrs('login', 'password') ?>>
          <?= field_error_html('login', 'password') ?>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"<?= isset($_COOKIE[EMAIL_COOKIE]) ? ' checked' : '' ?>> Remember my email on this device</label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
        <p class="small muted">New here? <a href="<?= e(url('sign-in.php#register')) ?>">Create a free account</a></p>
      </form>
    </div>
  </section>
  <?php endif; ?>

  <?= book_row('row-recent', 'Recently viewed', $recent) ?>
  <?= book_row('row-new', 'New arrivals', $newArrivals, url('catalogue.php?sort=newest'), 'Just added to the shelves.') ?>
  <?= book_row('row-staff', 'Staff picks', $staffPicks, null, 'Chosen by the people who look after the collection.') ?>
  <?php foreach ($categories as $cat): ?>
  <?= book_row('row-' . $cat['slug'], $cat['name'], $byCategory[$cat['id']] ?? [], url('catalogue.php?category=' . $cat['slug']), $cat['blurb']) ?>
  <?php endforeach; ?>

  <section class="teaser" aria-labelledby="teaser-title">
    <img src="<?= e(url('assets/img/rooms/atlas.svg')) ?>" alt="Floor plan of the Atlas study room with four seats and a screen" width="480" height="300" loading="lazy">
    <div>
      <p class="eyebrow">Study rooms</p>
      <h2 id="teaser-title">A quiet room when you need one.</h2>
      <p>Six rooms for two to six people, bookable up to a week ahead. Every member gets up to an hour a day, so there is always a room for someone.</p>
      <p class="teaser-stat"><strong><?= $freeToday ?></strong> free half hour slots left today.</p>
      <a class="btn btn-primary" href="<?= e(url('rooms.php')) ?>">See free rooms</a>
    </div>
  </section>

  <section class="visit" aria-labelledby="visit-title">
    <div>
      <p class="eyebrow">Visit us</p>
      <h2 id="visit-title"><?= e(BRANCH_NAME) ?></h2>
      <p class="status-pill <?= $status['open'] ? 'is-open' : 'is-closed' ?>"><span class="dot" aria-hidden="true"></span><?= e($status['label']) ?> <span class="status-detail">· <?= e($status['detail']) ?></span></p>
      <p class="muted"><?= icon('pin', 18) ?> <?= e(BRANCH_ADDRESS) ?></p>
    </div>
    <table class="hours-table">
      <caption class="visually-hidden">Opening hours this week</caption>
      <tbody>
        <?php for ($i = 0; $i < 7; $i++): $d = strtotime("+$i day", strtotime('today')); ?>
        <tr<?= $i === 0 ? ' class="is-today"' : '' ?>>
          <th scope="row"><?= $i === 0 ? 'Today' : date('l', $d) ?></th>
          <td><?= sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR) ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
