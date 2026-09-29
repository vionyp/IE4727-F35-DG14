<?php
// My Account: upcoming room bookings, orders, My Shelf and book submissions.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('Please sign in to see your account.');
$uid = (int) $user['id'];

$bookings = db_all("SELECT rb.id, rb.booking_date, rb.start_time, rb.end_time, rb.purpose, r.name, r.capacity, r.floor
                    FROM room_bookings rb JOIN study_rooms r ON r.id = rb.room_id
                    WHERE rb.user_id = ? AND rb.status = 'confirmed'
                      AND (rb.booking_date > CURDATE() OR (rb.booking_date = CURDATE() AND rb.start_time > CURTIME()))
                    ORDER BY rb.booking_date, rb.start_time", [$uid]);
$orders = db_all("SELECT o.id, o.total, o.payment_status, o.delivery_method, o.created_at,
                         COALESCE(SUM(oi.qty), 0) AS items, GROUP_CONCAT(b.title ORDER BY b.title SEPARATOR ', ') AS titles
                  FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id LEFT JOIN books b ON b.id = oi.book_id
                  WHERE o.user_id = ? GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10", [$uid]);
$shelf = db_all('SELECT ' . card_columns() . ", s.added_at FROM shelf s JOIN books b ON b.id = s.book_id
                 WHERE s.user_id = ? AND b.status = 'approved' ORDER BY s.added_at DESC", [$uid]);
$submissions = db_all('SELECT id, serial_no, title, status, created_at FROM books WHERE added_by = ? ORDER BY created_at DESC', [$uid]);
$minutesToday = minutes_used($uid, date('Y-m-d'));
$spent = (float) db_value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = ? AND payment_status = 'paid'", [$uid]);
$highlight = input_int($_GET, 'order');

$page_title = 'My Account';
$body_class = 'page-account';
$scripts = ['forms.js', 'rows.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <header class="page-head account-head">
    <div>
      <p class="eyebrow"><?= $user['role'] === 'admin' ? 'Library staff' : 'Member since ' . e(date('F Y', strtotime($user['created_at']))) ?></p>
      <h1>Hello, <?= e(first_name($user)) ?></h1>
      <p class="muted"><?= e($user['email']) ?></p>
    </div>
    <div class="cluster">
      <a class="btn btn-primary" href="<?= e(url('add-book.php')) ?>"><?= icon('plus', 18) ?> Add a book</a>
      <?php if ($user['role'] === 'admin'): ?>
      <a class="btn btn-secondary" href="<?= e(url('admin.php')) ?>"><?= icon('chart', 18) ?> Admin dashboard</a>
      <?php endif; ?>
      <form action="<?= e(url('process/logout.php')) ?>" method="post">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit">Sign out</button>
      </form>
    </div>
  </header>

  <div class="tiles">
    <div class="tile"><p class="tile-label">Study time left today</p><p class="tile-value"><?= max(0, DAILY_CAP_MINUTES - $minutesToday) ?><small> min</small></p></div>
    <div class="tile"><p class="tile-label">Upcoming bookings</p><p class="tile-value"><?= count($bookings) ?></p></div>
    <div class="tile"><p class="tile-label">Books on your shelf</p><p class="tile-value"><?= count($shelf) ?></p></div>
    <div class="tile"><p class="tile-label">Spent on books</p><p class="tile-value"><?= money($spent) ?></p></div>
  </div>

  <section id="bookings" class="account-section" aria-labelledby="bookings-title" tabindex="-1">
    <div class="section-head">
      <h2 id="bookings-title">Upcoming study rooms</h2>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('rooms.php')) ?>">Book a room</a>
    </div>
    <?php if ($bookings): ?>
    <ul class="booking-list" role="list">
      <?php foreach ($bookings as $b): ?>
      <li class="booking-item">
        <div class="booking-date"><span><?= e(date('D', strtotime($b['booking_date']))) ?></span><strong><?= e(date('j', strtotime($b['booking_date']))) ?></strong><span><?= e(date('M', strtotime($b['booking_date']))) ?></span></div>
        <div class="booking-body">
          <h3><?= e($b['name']) ?> <span class="muted small">· <?= (int) $b['capacity'] ?> seats, <?= e($b['floor']) ?></span></h3>
          <p><?= e(hm($b['start_time'])) ?> to <?= e(hm($b['end_time'])) ?><?= $b['purpose'] ? ' · ' . e($b['purpose']) : '' ?></p>
        </div>
        <form class="booking-cancel" action="<?= e(url('process/cancel-room.php')) ?>" method="post" data-confirm>
          <?= csrf_field() ?>
          <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
          <button type="button" class="btn btn-ghost btn-sm" data-confirm-trigger hidden>Cancel</button>
          <div class="confirm-panel" data-confirm-panel>
            <span class="small">Cancel this booking?</span>
            <button type="submit" class="btn btn-danger btn-sm">Yes, cancel</button>
            <button type="button" class="btn btn-ghost btn-sm" data-confirm-dismiss hidden>Keep it</button>
          </div>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="notice">No upcoming bookings. You have <?= max(0, DAILY_CAP_MINUTES - $minutesToday) ?> minutes of study room time left today. <a href="<?= e(url('rooms.php')) ?>">See free rooms</a></p>
    <?php endif; ?>
  </section>

  <section id="orders" class="account-section" aria-labelledby="orders-title" tabindex="-1">
    <div class="section-head"><h2 id="orders-title">Your orders</h2></div>
    <?php if ($orders): ?>
    <div class="table-wrap">
      <table class="data-table">
        <caption>Your 10 most recent orders</caption>
        <thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Books</th><th scope="col">Method</th><th scope="col" class="num">Total</th><th scope="col">Status</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr<?= $highlight === (int) $o['id'] ? ' class="is-highlight"' : '' ?>>
            <th scope="row">#<?= (int) $o['id'] ?></th>
            <td><?= e(date('j M Y', strtotime($o['created_at']))) ?></td>
            <td class="titles-cell"><?= (int) $o['items'] ?> · <span class="muted"><?= e($o['titles'] ?? 'No items') ?></span></td>
            <td><?= $o['delivery_method'] === 'delivery' ? 'Delivery' : 'Pickup' ?></td>
            <td class="num"><?= money($o['total']) ?></td>
            <td><span class="pill is-<?= e($o['payment_status']) ?>"><?= e(ucfirst($o['payment_status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="notice">No orders yet. <a href="<?= e(url('catalogue.php')) ?>">Browse the collection</a></p>
    <?php endif; ?>
  </section>

  <section id="shelf" class="account-section" aria-labelledby="shelf-title" tabindex="-1">
    <div class="section-head">
      <h2 id="shelf-title">My Shelf</h2>
      <p class="muted small">Books you saved for later.</p>
    </div>
    <?php if ($shelf): ?>
    <div class="card-grid shelf-grid">
      <?php foreach ($shelf as $b): ?>
      <div class="shelf-item">
        <?= book_card($b) ?>
        <form action="<?= e(url('process/shelf.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="book_id" value="<?= (int) $b['id'] ?>">
          <input type="hidden" name="action" value="remove">
          <button class="btn btn-ghost btn-sm btn-block" type="submit" aria-label="Remove <?= e($b['title']) ?> from your shelf">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <?= empty_state('Your shelf is empty', 'Press "Save to shelf" on any book page to keep it here for later.',
        '<a class="btn btn-primary btn-sm" href="' . e(url('catalogue.php')) . '">Find a book</a>') ?>
    <?php endif; ?>
  </section>

  <section id="submissions" class="account-section" aria-labelledby="subs-title" tabindex="-1">
    <div class="section-head">
      <h2 id="subs-title">Books you added</h2>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('add-book.php')) ?>">Add a book</a>
    </div>
    <?php if ($submissions): ?>
    <div class="table-wrap">
      <table class="data-table">
        <caption>Your submissions and their review status</caption>
        <thead><tr><th scope="col">Serial</th><th scope="col">Title</th><th scope="col">Sent</th><th scope="col">Status</th></tr></thead>
        <tbody>
          <?php foreach ($submissions as $s): ?>
          <tr>
            <th scope="row"><?= e($s['serial_no']) ?></th>
            <td><?= $s['status'] === 'approved' ? '<a href="' . e(book_url((int) $s['id'])) . '">' . e($s['title']) . '</a>' : e($s['title']) ?></td>
            <td><?= e(date('j M Y', strtotime($s['created_at']))) ?></td>
            <td><span class="pill is-<?= e($s['status']) ?>"><?= e($s['status'] === 'pending' ? 'Waiting for review' : ucfirst($s['status'])) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p class="notice">Know a book we should have? <a href="<?= e(url('add-book.php')) ?>">Add it to the library</a>. A librarian reviews every submission.</p>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
