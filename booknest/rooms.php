<?php
// Study rooms: the availability table (rooms by half hour slots) and the booking form.
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$dates = bookable_dates();
$date = input($_GET, 'date', 10);
if (!in_array($date, $dates, true)) {
    $date = old('booking', 'date', $dates[0]);
    $date = in_array($date, $dates, true) ? $date : $dates[0];
}

$rooms = db_all('SELECT * FROM study_rooms ORDER BY id');
$grid = availability($date, $rooms, user_id());
$slots = room_slots();

// Pre-fill from a clicked free cell (GET) or from a failed attempt (kept form).
$preRoom = input_int($_GET, 'room') ?: (int) old('booking', 'room_id', '0');
$preStart = input($_GET, 'start', 5) ?: old('booking', 'start');
$preStart = in_array($preStart, $slots, true) ? $preStart : '';
$preDuration = old('booking', 'duration', '60');
$prePurpose = old('booking', 'purpose');

$used = $user ? minutes_used((int) $user['id'], $date) : 0;
$quota = [];
if ($user) {
    foreach ($dates as $d) {
        $quota[] = $d . ':' . minutes_used((int) $user['id'], $d);
    }
}
$freeCount = 0;
foreach ($grid as $cells) {
    $freeCount += count(array_filter($cells, fn($s) => $s === 'free'));
}
$labels = ['free' => 'Free', 'taken' => 'Taken', 'yours' => 'Yours', 'past' => 'Past', 'closed' => 'Closed'];
$symbols = ['free' => '○', 'taken' => '●', 'yours' => '★', 'past' => '–', 'closed' => '×'];
$returnHere = url('rooms.php?date=' . $date . ($preRoom ? '&room=' . $preRoom : '') . ($preStart ? '&start=' . $preStart : '') . '#book');

$page_title = 'Study Rooms';
$page_desc = 'See which study rooms are free and book one for up to an hour a day.';
$body_class = 'page-rooms';
$scripts = ['forms.js', 'rooms.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <header class="page-head rooms-head">
    <div>
      <h1>Study rooms</h1>
      <p class="lede">Six quiet rooms for two to six people. Pick a free slot in the table, choose how long, and confirm.</p>
    </div>
    <ul class="rules" role="list" aria-label="Booking rules">
      <li><?= icon('clock', 18) ?> Up to <?= MAX_BOOKING_MINUTES ?> minutes per member per day</li>
      <li><?= icon('door', 18) ?> Book today or up to <?= ADVANCE_DAYS ?> days ahead</li>
      <li><?= icon('check', 18) ?> Open <?= sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR) ?> daily</li>
      <?php if ($user): ?>
      <li class="quota"><strong><?= max(0, DAILY_CAP_MINUTES - $used) ?> minutes</strong> left for you on <?= e(short_date($date)) ?></li>
      <?php endif; ?>
    </ul>
  </header>

  <nav class="date-picker" aria-label="Choose a date">
    <div class="chips">
      <?php foreach ($dates as $i => $d): ?>
      <a class="chip date-chip" href="<?= e(url('rooms.php?date=' . $d)) ?>"<?= $d === $date ? ' aria-current="page"' : '' ?>>
        <span><?= $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : e(date('D', strtotime($d)))) ?></span>
        <small><?= e(date('j M', strtotime($d))) ?></small>
      </a>
      <?php endforeach; ?>
    </div>
    <form class="date-form" action="<?= e(url('rooms.php')) ?>" method="get">
      <label for="date-jump" class="visually-hidden">Date</label>
      <input class="input" type="date" id="date-jump" name="date" value="<?= e($date) ?>" min="<?= e($dates[0]) ?>" max="<?= e(end($dates)) ?>" required>
      <button class="btn btn-secondary btn-sm" type="submit">Show</button>
    </form>
  </nav>

  <div class="legend" aria-hidden="true">
    <?php foreach ($labels as $key => $label): ?>
    <span class="legend-item"><span class="slot slot-<?= $key ?>"><?= $symbols[$key] ?></span> <?= e($label) ?></span>
    <?php endforeach; ?>
    <span class="legend-count"><?= $freeCount ?> free slots on <?= e(short_date($date)) ?></span>
  </div>

  <div class="table-wrap availability-wrap" tabindex="0" role="region" aria-labelledby="avail-caption">
    <table class="availability" data-availability>
      <caption id="avail-caption">Study room availability for <?= e(long_date($date)) ?>. Each column is a 30 minute slot starting at the time shown. Select a free slot to book it.</caption>
      <thead>
        <tr>
          <th scope="col" class="room-col">Room</th>
          <?php foreach ($slots as $s): ?>
          <th scope="col"><?= e($s) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $room): ?>
        <tr>
          <th scope="row" class="room-col"><span class="room-name"><?= e($room['name']) ?></span><span class="room-cap"><?= (int) $room['capacity'] ?> seats</span></th>
          <?php foreach ($slots as $s): $st = $grid[$room['id']][$s]; ?>
          <td>
            <?php if ($st === 'free'): ?>
            <a class="slot slot-free" href="<?= e(url('rooms.php?date=' . $date . '&room=' . $room['id'] . '&start=' . $s . '#book')) ?>" data-room="<?= (int) $room['id'] ?>" data-start="<?= e($s) ?>" aria-label="Free, book <?= e($room['name']) ?> at <?= e($s) ?>"><span aria-hidden="true"><?= $symbols['free'] ?></span> Free</a>
            <?php else: ?>
            <span class="slot slot-<?= $st ?>"><span aria-hidden="true"><?= $symbols[$st] ?></span> <?= e($labels[$st]) ?></span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="small muted table-note">On a phone, swipe the table sideways to see later times.</p>

  <section id="book" class="book-panel panel" aria-labelledby="book-title" tabindex="-1">
    <h2 id="book-title" class="panel-title">Book a room</h2>
    <?php if ($user): ?>
    <form class="form" id="booking" action="<?= e(url('process/book-room.php')) ?>" method="post" data-validate data-booking
          data-quota="<?= e(implode(',', $quota)) ?>" data-cap="<?= DAILY_CAP_MINUTES ?>" data-close="<?= CLOSE_HOUR * 60 ?>"
          data-today="<?= e($dates[0]) ?>" data-now="<?= (int) date('G') * 60 + (int) date('i') ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid booking-grid">
        <div class="field">
          <label for="bk-room">Room</label>
          <select id="bk-room" name="room_id" required<?= field_attrs('booking', 'room') ?>>
            <option value="">Choose a room</option>
            <?php foreach ($rooms as $room): ?>
            <option value="<?= (int) $room['id'] ?>"<?= $preRoom === (int) $room['id'] ? ' selected' : '' ?><?= $room['is_active'] ? '' : ' disabled' ?>><?= e($room['name']) ?> · <?= (int) $room['capacity'] ?> seats<?= $room['is_active'] ? '' : ' (closed)' ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error_html('booking', 'room') ?>
        </div>
        <div class="field">
          <label for="bk-date">Date</label>
          <input type="date" id="bk-date" name="date" required min="<?= e($dates[0]) ?>" max="<?= e(end($dates)) ?>" value="<?= e($date) ?>"<?= field_attrs('booking', 'date') ?>>
          <?= field_error_html('booking', 'date') ?>
        </div>
        <div class="field">
          <label for="bk-start">Start time</label>
          <select id="bk-start" name="start" required<?= field_attrs('booking', 'start') ?>>
            <option value="">Choose a time</option>
            <?php foreach ($slots as $s): ?>
            <option value="<?= e($s) ?>"<?= $preStart === $s ? ' selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error_html('booking', 'start') ?>
        </div>
        <div class="field">
          <label for="bk-duration">How long</label>
          <select id="bk-duration" name="duration" required<?= field_attrs('booking', 'duration') ?>>
            <option value="60"<?= $preDuration !== '30' ? ' selected' : '' ?>>1 hour</option>
            <option value="30"<?= $preDuration === '30' ? ' selected' : '' ?>>30 minutes</option>
          </select>
          <?= field_error_html('booking', 'duration') ?>
        </div>
        <div class="field span-all">
          <label for="bk-purpose">What is it for? <span class="optional">(optional)</span></label>
          <input type="text" id="bk-purpose" name="purpose" maxlength="120" placeholder="Group project, quiet study, interview practice" value="<?= e($prePurpose) ?>">
        </div>
      </div>
      <p class="booking-summary" data-booking-summary aria-live="polite"></p>
      <button class="btn btn-primary" type="submit">Confirm booking</button>
    </form>
    <?php else: ?>
    <div class="signin-inline">
      <div>
        <p>Viewing is open to everyone. To book, sign in with your member account and we will bring you straight back to <?= $preStart ? 'the slot you picked' : 'this page' ?>.</p>
        <p class="small muted">Not a member yet? <a href="<?= e(url('sign-in.php?return=' . rawurlencode($returnHere) . '#register')) ?>">Join for free</a> in under a minute.</p>
      </div>
      <form class="form" id="rooms-login" action="<?= e(url('process/login.php')) ?>" method="post" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($returnHere) ?>">
        <input type="hidden" name="fail" value="<?= e($returnHere) ?>">
        <div class="form-grid cols-2">
          <div class="field">
            <label for="rl-email">Email</label>
            <input type="email" id="rl-email" name="email" required maxlength="120" autocomplete="email" value="<?= e(old('login', 'email', (string) ($_COOKIE[EMAIL_COOKIE] ?? ''))) ?>"<?= field_attrs('login', 'email') ?>>
            <?= field_error_html('login', 'email') ?>
          </div>
          <div class="field">
            <label for="rl-password">Password</label>
            <input type="password" id="rl-password" name="password" required autocomplete="current-password"<?= field_attrs('login', 'password') ?>>
            <?= field_error_html('login', 'password') ?>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Sign in and continue</button>
      </form>
    </div>
    <?php endif; ?>
  </section>

  <section class="section-tight" aria-labelledby="rooms-title">
    <h2 id="rooms-title" class="section-title">The rooms</h2>
    <ul class="room-cards" role="list">
      <?php foreach ($rooms as $room): ?>
      <li class="room-card">
        <img src="<?= e(url('assets/' . $room['image_path'])) ?>" alt="Floor plan of <?= e($room['name']) ?>: a table with <?= (int) $room['capacity'] ?> chairs" width="480" height="300" loading="lazy">
        <div class="room-card-body">
          <h3><?= e($room['name']) ?> <span class="pill <?= $room['is_active'] ? 'is-in' : 'is-out' ?>"><?= $room['is_active'] ? 'Open' : 'Closed' ?></span></h3>
          <p class="muted"><?= (int) $room['capacity'] ?> seats · <?= e($room['floor']) ?></p>
          <p class="small"><?= e(ucfirst($room['features'])) ?></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
