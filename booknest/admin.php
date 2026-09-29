<?php
// Admin dashboard: analytics built with GROUP BY and aggregates, approvals, book and room management.
require __DIR__ . '/includes/bootstrap.php';
require_admin();

// Headline numbers.
$kpi = db_one("SELECT
    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total END), 0) AS revenue,
    SUM(payment_status = 'paid') AS paid_orders,
    COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN total END), 0) AS avg_order,
    SUM(payment_status = 'failed') AS failed_orders
  FROM orders");
$attempts = (int) $kpi['paid_orders'] + (int) $kpi['failed_orders'];
$successRate = $attempts ? round($kpi['paid_orders'] / $attempts * 100) : 0;
$members = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'member'");
$newMembers = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'member' AND created_at >= NOW() - INTERVAL 30 DAY");

// Revenue by category.
$byCategory = db_all("SELECT c.name, SUM(oi.qty * oi.unit_price) AS revenue, SUM(oi.qty) AS units
                      FROM order_items oi JOIN orders o ON o.id = oi.order_id AND o.payment_status = 'paid'
                      JOIN books b ON b.id = oi.book_id JOIN categories c ON c.id = b.category_id
                      GROUP BY c.id, c.name ORDER BY revenue DESC");
// Top five books by copies sold.
$topBooks = db_all("SELECT b.title, SUM(oi.qty) AS units, SUM(oi.qty * oi.unit_price) AS revenue
                    FROM order_items oi JOIN orders o ON o.id = oi.order_id AND o.payment_status = 'paid'
                    JOIN books b ON b.id = oi.book_id
                    GROUP BY b.id, b.title ORDER BY units DESC, revenue DESC LIMIT 5");
// Paid orders per day over the last 14 days (missing days filled with zero).
$perDayRows = db_all("SELECT DATE(created_at) AS day, COUNT(*) AS orders FROM orders
                      WHERE payment_status = 'paid' AND created_at >= CURDATE() - INTERVAL 13 DAY
                      GROUP BY DATE(created_at)");
$perDayMap = array_column($perDayRows, 'orders', 'day');
$perDay = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day", strtotime('today')));
    $perDay[$d] = (int) ($perDayMap[$d] ?? 0);
}
// Room use over the last 30 days and the next 7.
$byRoom = db_all("SELECT r.name, COUNT(rb.id) AS bookings, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, rb.start_time, rb.end_time)), 0) AS minutes
                  FROM study_rooms r LEFT JOIN room_bookings rb ON rb.room_id = r.id AND rb.status = 'confirmed'
                    AND rb.booking_date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE() + INTERVAL 7 DAY
                  GROUP BY r.id, r.name ORDER BY bookings DESC");
$byHour = db_all("SELECT HOUR(start_time) AS hour, COUNT(*) AS bookings FROM room_bookings
                  WHERE status = 'confirmed' GROUP BY HOUR(start_time) ORDER BY hour");
$cancelRate = (int) round((float) db_value("SELECT COALESCE(AVG(status = 'cancelled'), 0) * 100 FROM room_bookings"));

$pending = db_all("SELECT b.*, c.name AS category, u.full_name AS added_by_name FROM books b
                   JOIN categories c ON c.id = b.category_id LEFT JOIN users u ON u.id = b.added_by
                   WHERE b.status = 'pending' ORDER BY b.created_at");
$books = db_all("SELECT b.id, b.serial_no, b.title, b.author, b.price, b.stock, b.is_featured, b.is_staff_pick, c.name AS category,
                        (SELECT COUNT(*) FROM order_items oi WHERE oi.book_id = b.id) AS times_ordered
                 FROM books b JOIN categories c ON c.id = b.category_id WHERE b.status = 'approved' ORDER BY b.title");
$rooms = db_all('SELECT * FROM study_rooms ORDER BY id');

// Prints one horizontal bar chart as an accessible table.
function bar_table(string $caption, array $rows, string $labelKey, string $valueKey, callable $format, string $class = ''): string
{
    $max = max(array_map(fn($r) => (float) $r[$valueKey], $rows) ?: [1]) ?: 1;
    $html = '<table class="bar-table ' . $class . '"><caption>' . e($caption) . '</caption><tbody>';
    foreach ($rows as $r) {
        $pct = round((float) $r[$valueKey] / $max * 100, 1);
        $html .= '<tr><th scope="row" title="' . e($r[$labelKey]) . '">' . e($r[$labelKey]) . '</th><td><span class="bar">'
            . '<span class="bar-fill" style="--value: ' . $pct . '%"></span><span class="bar-value">' . e($format($r)) . '</span></span></td></tr>';
    }
    return $html . '</tbody></table>';
}

$page_title = 'Library dashboard';
$body_class = 'page-admin';
$scripts = ['forms.js'];
$crumbs = [['Home', url()], ['Admin', null]];
require __DIR__ . '/includes/header.php';
$maxDay = max($perDay) ?: 1;
?>

<div class="container">
  <header class="page-head">
    <p class="eyebrow">Staff only</p>
    <h1>Library dashboard</h1>
    <p class="lede">Sales, study room use and everything waiting for a decision.</p>
  </header>

  <div class="tiles">
    <div class="tile"><p class="tile-label">Revenue (paid orders)</p><p class="tile-value"><?= money($kpi['revenue']) ?></p></div>
    <div class="tile"><p class="tile-label">Paid orders</p><p class="tile-value"><?= (int) $kpi['paid_orders'] ?></p><p class="tile-note"><?= (int) $kpi['failed_orders'] ?> payments failed</p></div>
    <div class="tile"><p class="tile-label">Average order</p><p class="tile-value"><?= money($kpi['avg_order']) ?></p></div>
    <div class="tile"><p class="tile-label">Payment success</p><p class="tile-value"><?= $successRate ?>%</p></div>
    <div class="tile"><p class="tile-label">Members</p><p class="tile-value"><?= $members ?></p><p class="tile-note"><?= $newMembers ?> joined in 30 days</p></div>
  </div>

  <section class="analytics" aria-labelledby="sales-title">
    <h2 id="sales-title" class="section-title">Sales</h2>
    <div class="analytics-grid">
      <div class="panel"><?= bar_table('Revenue by category', $byCategory, 'name', 'revenue', fn($r) => money($r['revenue']) . ' · ' . $r['units'] . ' sold') ?></div>
      <div class="panel"><?= bar_table('Top 5 books by copies sold', $topBooks, 'title', 'units', fn($r) => $r['units'] . ' copies') ?></div>
      <div class="panel span-2">
        <h3 class="chart-title">Paid orders per day, last 14 days</h3>
        <div class="columns-chart" style="--count: 14" role="img" aria-label="Paid orders per day for the last 14 days: <?= e(implode(', ', array_map(fn($d, $n) => date('j M', strtotime($d)) . ' ' . $n, array_keys($perDay), $perDay))) ?>">
          <?php foreach ($perDay as $d => $n): ?>
          <div class="col"><span class="col-num"><?= $n ?></span><span class="col-fill" style="--value: <?= round($n / $maxDay * 100) ?>%"></span></div>
          <?php endforeach; ?>
        </div>
        <div class="col-labels" style="--count: 14" aria-hidden="true">
          <?php foreach (array_keys($perDay) as $d): ?><span><?= e(date('j/n', strtotime($d))) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="analytics" aria-labelledby="rooms-an-title">
    <h2 id="rooms-an-title" class="section-title">Study rooms</h2>
    <div class="analytics-grid">
      <div class="panel"><?= bar_table('Bookings per room (30 days back, 7 ahead)', $byRoom, 'name', 'bookings', fn($r) => $r['bookings'] . ' · ' . round($r['minutes'] / 60, 1) . ' h', 'bar-teal') ?></div>
      <div class="panel"><?= bar_table('Busiest start times (all bookings)', array_map(fn($r) => ['label' => sprintf('%02d:00', $r['hour']), 'bookings' => $r['bookings']], $byHour), 'label', 'bookings', fn($r) => $r['bookings'] . ' bookings', 'bar-teal') ?>
        <p class="small muted"><?= $cancelRate ?>% of bookings are cancelled in advance.</p>
      </div>
    </div>
  </section>

  <section id="pending" class="account-section" aria-labelledby="pending-title" tabindex="-1">
    <div class="section-head"><h2 id="pending-title">Waiting for review <span class="pill is-pending"><?= count($pending) ?></span></h2></div>
    <?php if ($pending): ?>
    <ul class="pending-list" role="list">
      <?php foreach ($pending as $p): ?>
      <li class="pending-item panel">
        <?= cover_img($p, 'pending-cover', true, 100) ?>
        <div class="pending-body">
          <h3><?= e($p['title']) ?></h3>
          <p class="muted"><?= e($p['author']) ?> · <?= e($p['category']) ?> · <?= e(year_label((int) $p['published_year'])) ?> · <?= money($p['price']) ?> · <?= (int) $p['stock'] ?> copies</p>
          <p class="small"><?= e(mb_strimwidth($p['synopsis'], 0, 220, '...')) ?></p>
          <p class="small muted"><?= e($p['serial_no']) ?> · sent by <?= e($p['added_by_name'] ?? 'unknown') ?> on <?= e(date('j M Y', strtotime($p['created_at']))) ?> · <?= $p['sample_text'] ? 'includes a sample' : 'no sample' ?></p>
        </div>
        <form class="pending-actions" action="<?= e(url('process/admin-action.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn btn-teal btn-sm" type="submit" name="action" value="approve">Approve</button>
          <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject">Reject</button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="notice">Nothing waiting. New member submissions will appear here.</p>
    <?php endif; ?>
  </section>

  <section id="books" class="account-section" aria-labelledby="books-title" tabindex="-1">
    <div class="section-head"><h2 id="books-title">Books in the catalogue</h2><p class="muted small"><?= count($books) ?> approved titles</p></div>
    <div class="table-wrap">
      <table class="data-table admin-books">
        <caption>Edit price, stock and home page placement. Books that have been ordered cannot be deleted.</caption>
        <thead><tr><th scope="col">Serial</th><th scope="col">Title</th><th scope="col">Price (S$)</th><th scope="col">Stock</th><th scope="col">Staff pick</th><th scope="col">Featured</th><th scope="col">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($books as $b): $f = 'edit-' . (int) $b['id']; ?>
          <tr>
            <td class="mono"><?= e($b['serial_no']) ?></td>
            <th scope="row"><a href="<?= e(book_url((int) $b['id'])) ?>"><?= e($b['title']) ?></a><span class="muted small block"><?= e($b['author']) ?> · <?= e($b['category']) ?></span></th>
            <td><label class="visually-hidden" for="<?= $f ?>-price">Price of <?= e($b['title']) ?></label><input class="input input-sm" form="<?= $f ?>" id="<?= $f ?>-price" type="number" name="price" min="0.5" max="500" step="0.01" required value="<?= e($b['price']) ?>"></td>
            <td><label class="visually-hidden" for="<?= $f ?>-stock">Stock of <?= e($b['title']) ?></label><input class="input input-sm" form="<?= $f ?>" id="<?= $f ?>-stock" type="number" name="stock" min="0" max="999" step="1" required value="<?= (int) $b['stock'] ?>"></td>
            <td><input form="<?= $f ?>" type="checkbox" name="is_staff_pick" value="1" aria-label="Staff pick: <?= e($b['title']) ?>"<?= $b['is_staff_pick'] ? ' checked' : '' ?>></td>
            <td><input form="<?= $f ?>" type="radio" name="is_featured" value="1" aria-label="Feature <?= e($b['title']) ?> on the home page"<?= $b['is_featured'] ? ' checked' : '' ?>></td>
            <td>
              <form id="<?= $f ?>" class="row-actions" action="<?= e(url('process/admin-action.php')) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-secondary btn-sm" type="submit" name="action" value="update">Save</button>
                <?php if (!$b['times_ordered']): ?>
                <button class="btn btn-danger btn-sm" type="submit" name="action" value="delete" formnovalidate aria-label="Delete <?= e($b['title']) ?>">Delete</button>
                <?php endif; ?>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section id="rooms" class="account-section" aria-labelledby="rooms-admin-title" tabindex="-1">
    <div class="section-head"><h2 id="rooms-admin-title">Study rooms</h2></div>
    <ul class="room-admin" role="list">
      <?php foreach ($rooms as $r): ?>
      <li class="panel room-admin-item">
        <img src="<?= e(url('assets/' . $r['image_path'])) ?>" alt="" width="120" height="75" loading="lazy">
        <div><h3><?= e($r['name']) ?></h3><p class="small muted"><?= (int) $r['capacity'] ?> seats · <?= e($r['floor']) ?></p></div>
        <span class="pill <?= $r['is_active'] ? 'is-in' : 'is-out' ?>"><?= $r['is_active'] ? 'Open' : 'Closed' ?></span>
        <form action="<?= e(url('process/admin-action.php')) ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn btn-ghost btn-sm" type="submit" name="action" value="room"><?= $r['is_active'] ? 'Close room' : 'Open room' ?></button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
