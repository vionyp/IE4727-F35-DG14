<?php
// My Account: loans (due dates, late fees, return), queue places, room bookings, My Shelf and submissions.
require __DIR__ . '/includes/bootstrap.php';
$user = require_login('Please sign in to see your account.');
$uid = (int) $user['id'];

$bookings = db_all("SELECT rb.id, rb.booking_date, rb.start_time, rb.end_time, rb.purpose, r.name, r.capacity, r.floor
                    FROM room_bookings rb JOIN study_rooms r ON r.id = rb.room_id
                    WHERE rb.user_id = ? AND rb.status = 'confirmed'
                      AND (rb.booking_date > CURDATE() OR (rb.booking_date = CURDATE() AND rb.start_time > CURTIME()))
                    ORDER BY rb.booking_date, rb.start_time", [$uid]);
$loans = db_all("SELECT l.*, b.title, b.author, b.cover_path, b.cover_alt FROM loans l JOIN books b ON b.id = l.book_id
                 WHERE l.user_id = ? AND l.status <> 'returned' ORDER BY l.due_date, l.id", [$uid]);
$history = db_all("SELECT l.*, b.title FROM loans l JOIN books b ON b.id = l.book_id
                   WHERE l.user_id = ? AND l.status = 'returned' ORDER BY l.returned_date DESC, l.id DESC LIMIT 10", [$uid]);
$queue = db_all("SELECT q.*, b.title, b.author, b.cover_path, b.cover_alt FROM book_queue q JOIN books b ON b.id = q.book_id
                 WHERE q.user_id = ? AND q.status IN ('waiting','offered') ORDER BY q.status = 'offered' DESC, q.queued_at", [$uid]);
$shelf = db_all('SELECT ' . card_columns() . ", s.added_at FROM shelf s JOIN books b ON b.id = s.book_id
                 WHERE s.user_id = ? AND b.status = 'approved' ORDER BY s.added_at DESC", [$uid]);
$submissions = db_all('SELECT id, serial_no, title, status, created_at FROM books WHERE added_by = ? ORDER BY created_at DESC', [$uid]);
$minutesToday = minutes_used($uid, date('Y-m-d'));
$owed = fees_owed($uid);
$highlight = input_int($_GET, 'loan');

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
    <div class="tile"><p class="tile-label">Books on loan</p><p class="tile-value"><?= count($loans) ?></p></div>
    <div class="tile<?= $owed > 0 ? ' tile-alert' : '' ?>"><p class="tile-label">Late fees owed</p><p class="tile-value"><?= money($owed) ?></p><?php if ($owed > 0): ?><p class="tile-note">Settle at the front desk</p><?php endif; ?></div>
    <div class="tile"><p class="tile-label">Waiting for</p><p class="tile-value"><?= count($queue) ?><small> <?= count($queue) === 1 ? 'book' : 'books' ?></small></p></div>
    <div class="tile"><p class="tile-label">Study time left today</p><p class="tile-value"><?= max(0, DAILY_CAP_MINUTES - $minutesToday) ?><small> min</small></p></div>
    <div class="tile"><p class="tile-label">Books on your shelf</p><p class="tile-value"><?= count($shelf) ?></p></div>
  </div>

  <section id="loans" class="account-section" aria-labelledby="loans-title" tabindex="-1">
    <div class="section-head">
      <h2 id="loans-title">Your loans</h2>
      <a class="btn btn-secondary btn-sm" href="<?= e(url('catalogue.php?sort=available')) ?>">Find a book</a>
    </div>
    <?php if ($loans): ?>
    <ul class="loan-list" role="list">
      <?php foreach ($loans as $l): $state = loan_state($l); $late = loan_days_late($l); $daysLeft = days_between(date('Y-m-d'), $l['due_date']); ?>
      <li class="loan-item<?= $state === 'overdue' ? ' is-overdue' : '' ?><?= $highlight === (int) $l['id'] ? ' is-highlight' : '' ?>">
        <a href="<?= e(book_url((int) $l['book_id'])) ?>" tabindex="-1" aria-hidden="true"><?= cover_img($l, 'line-cover', true, 64) ?></a>
        <div class="loan-body">
          <h3><a href="<?= e(book_url((int) $l['book_id'])) ?>"><?= e($l['title']) ?></a> <span class="muted small">· <?= e($l['author']) ?></span></h3>
          <?php if ($state === 'reserved'): ?>
          <p><span class="avail is-mine">Reserved</span> Collect at the front desk from <strong><?= e(long_date($l['collection_date'])) ?></strong>. Due back <?= e(long_date($l['due_date'])) ?>.</p>
          <?php elseif ($state === 'overdue'): ?>
          <p><span class="avail is-out"><?= $late ?> <?= $late === 1 ? 'day' : 'days' ?> overdue, <?= money(loan_fee($l)) ?> owed so far</span> It was due back on <?= e(long_date($l['due_date'])) ?>. The fee grows by <?= money(LATE_FEE_PER_DAY) ?> a day until you return it.</p>
          <?php else: ?>
          <p><span class="avail <?= $daysLeft === 0 ? 'is-low' : 'is-mine' ?>"><?= $daysLeft === 0 ? 'Due today' : 'Due back in ' . $daysLeft . ($daysLeft === 1 ? ' day' : ' days') ?></span> Collected <?= e(short_date($l['collection_date'])) ?>, due <?= e(long_date($l['due_date'])) ?>.</p>
          <?php endif; ?>
        </div>
        <form class="loan-action" action="<?= e(url('process/loan.php')) ?>" method="post" data-confirm>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="return">
          <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
          <button type="button" class="btn btn-ghost btn-sm" data-confirm-trigger hidden><?= $state === 'reserved' ? 'Cancel reservation' : 'Return' ?></button>
          <div class="confirm-panel" data-confirm-panel>
            <span class="small"><?= $state === 'reserved' ? 'Cancel this reservation?' : 'Return ' . e($l['title']) . ' today?' ?></span>
            <button type="submit" class="btn btn-primary btn-sm"><?= $state === 'reserved' ? 'Yes, cancel' : 'Yes, return it' ?></button>
            <button type="button" class="btn btn-ghost btn-sm" data-confirm-dismiss hidden>Not yet</button>
          </div>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="notice">No books on loan. Borrow up to one copy of any title, free for <?= LOAN_DAYS ?> days. <a href="<?= e(url('catalogue.php?sort=available')) ?>">See what is on the shelf</a></p>
    <?php endif; ?>

    <?php if ($queue): ?>
    <h3 class="subsection-title" id="queue">Waiting for</h3>
    <ul class="loan-list" role="list">
      <?php foreach ($queue as $q): ?>
      <li class="loan-item">
        <a href="<?= e(book_url((int) $q['book_id'])) ?>" tabindex="-1" aria-hidden="true"><?= cover_img($q, 'line-cover', true, 64) ?></a>
        <div class="loan-body">
          <h3><a href="<?= e(book_url((int) $q['book_id'])) ?>"><?= e($q['title']) ?></a> <span class="muted small">· <?= e($q['author']) ?></span></h3>
          <?php if ($q['status'] === 'offered'): ?>
          <p><span class="avail is-in">Ready for you</span> A copy is held for you until <?= e(long_date(hold_until($q))) ?>.</p>
          <?php else: ?>
          <p><span class="avail is-low">You are #<?= queue_position($q) ?> in line</span> Joined <?= e(short_date($q['queued_at'])) ?>. We will email you when a copy is yours.</p>
          <?php endif; ?>
        </div>
        <div class="loan-action cluster">
          <?php if ($q['status'] === 'offered'): ?>
          <a class="btn btn-primary btn-sm" href="<?= e(url('borrow.php?id=' . $q['book_id'])) ?>">Borrow now</a>
          <?php endif; ?>
          <form action="<?= e(url('process/loan.php')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="book_id" value="<?= (int) $q['book_id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit" name="action" value="leave" aria-label="Leave the queue for <?= e($q['title']) ?>">Leave queue</button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($history): ?>
    <div class="table-wrap history">
      <table class="data-table">
        <caption>Your 10 most recent returns</caption>
        <thead><tr><th scope="col">Book</th><th scope="col">Collected</th><th scope="col">Due</th><th scope="col">Returned</th><th scope="col" class="num">Late fee</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): $fee = loan_fee($h); ?>
          <tr>
            <th scope="row"><a href="<?= e(book_url((int) $h['book_id'])) ?>"><?= e($h['title']) ?></a></th>
            <td><?= e(date('j M', strtotime($h['collection_date']))) ?></td>
            <td><?= e(date('j M', strtotime($h['due_date']))) ?></td>
            <td><?= e(date('j M', strtotime($h['returned_date']))) ?><?= loan_days_late($h) ? ' <span class="pill is-out">' . days_text(loan_days_late($h)) . ' late</span>' : '' ?></td>
            <td class="num"><?= $fee > 0 ? money($fee) . ($h['fee_cleared_at'] ? ' <span class="muted small">paid</span>' : '') : 'None' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

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
