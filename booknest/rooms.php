<?php
// Study rooms: the Crescent map, the availability table (rooms by half hour slots) and the booking form.
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

// ---- The Crescent map ---------------------------------------------------------------------
// The map shows every room at ONE time of day, chosen with ?at=HH:MM. Without it we use the
// start time the visitor already picked, or today's next slot, or 14:00 on a future day.
$at = input($_GET, 'at', 5) ?: $preStart;
if (!in_array($at, $slots, true)) {
    $at = '14:00';
    if ($date === $dates[0]) {
        $nowMin = (int) date('G') * 60 + (int) date('i');
        $later = array_filter($slots, fn($slot) => to_minutes($slot) > $nowMin);
        $at = $later ? reset($later) : end($slots);
    }
}

// Group the rooms by arc. The "floor" column of a room holds its arc, for example "Arc A".
$arcs = map_arcs();
$byArc = [];
foreach ($rooms as $room) {
    $byArc[$room['floor']][] = $room;
}

// How many rooms are in each state at the chosen time, for example ['free' => 31, 'taken' => 17].
$atCounts = array_count_values(array_map(fn($room) => $grid[$room['id']][$at], $rooms));

// The timetable shows one arc at a time: the one asked for, or the picked room's, or the first.
$arc = 'Arc ' . strtoupper(input($_GET, 'arc', 1));
if (!isset($byArc[$arc])) {
    $arc = array_key_first($byArc);
    foreach ($rooms as $room) {
        if ((int) $room['id'] === $preRoom) {
            $arc = $room['floor'];
        }
    }
}
$tableRooms = $byArc[$arc] ?? [];
$arcFree = 0;
foreach ($tableRooms as $room) {
    $arcFree += count(array_keys($grid[$room['id']], 'free'));
}

// Builds a link to this page that keeps the chosen date, time and arc, with some values changed.
$link = fn(array $change = [], string $hash = '') => url('rooms.php?'
    . http_build_query(array_merge(['date' => $date, 'at' => $at, 'arc' => substr($arc, -1)], $change)) . $hash);

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
      <p class="lede"><?= count($rooms) ?> quiet rooms in four arcs around the Moon Garden. Pick a time, choose a free room on the map, and confirm.</p>
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
      <a class="chip date-chip" href="<?= e($link(['date' => $d])) ?>"<?= $d === $date ? ' aria-current="page"' : '' ?>>
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

  <section class="moon" aria-labelledby="map-title">
    <div class="moon-map">
      <svg viewBox="0 0 660 620" role="group" aria-labelledby="map-title map-desc">
        <desc id="map-desc">Plan of the library, a crescent shaped building. <?= count($rooms) ?> study rooms sit on four arcs around a garden. Free rooms are links.</desc>
        <defs>
          <radialGradient id="moon-fill" cx="30%" cy="35%" r="80%">
            <stop offset="0" stop-color="#232A36"/><stop offset="1" stop-color="#12161D"/>
          </radialGradient>
          <linearGradient id="moon-edge" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#E5A93D"/><stop offset="1" stop-color="#3FB6A8"/>
          </linearGradient>
        </defs>

        <!-- a few stars, then the crescent: a big circle with a smaller circle bitten out of it -->
        <g class="moon-stars" aria-hidden="true">
          <circle cx="560" cy="60" r="2"/><circle cx="612" cy="150" r="1.5"/><circle cx="590" cy="470" r="2"/>
          <circle cx="636" cy="330" r="1.5"/><circle cx="540" cy="585" r="1.5"/><circle cx="30" cy="40" r="1.5"/><circle cx="24" cy="580" r="2"/>
        </g>
        <path class="moon-body" d="M500 75.1 A290 290 0 1 0 500 544.9 A236 236 0 1 1 500 75.1 Z"/>
        <g class="moon-garden" aria-hidden="true">
          <circle cx="455" cy="310" r="96"/>
          <text x="455" y="304">Moon</text><text x="455" y="322">Garden</text>
          <text class="moon-entrance" x="520" y="566">Entrance</text>
        </g>

        <?php foreach ($arcs as $arcName => $a): $list = $byArc[$arcName] ?? []; ?>
        <?php
          // The arc's letter, drawn just past its first room (half a tile plus a small gap further round).
          $gap = rad2deg(($a['w'] / 2 + 13) / $a['radius']);
          $tag = map_position(['radius' => $a['radius'], 'half_span' => $a['half_span'] + $gap], 0, 2);
        ?>
        <text class="moon-arc-letter" x="<?= $tag['x'] ?>" y="<?= $tag['y'] ?>" aria-hidden="true"><?= e(substr($arcName, -1)) ?></text>
        <?php foreach ($list as $i => $r): ?>
        <?php
          $pos = map_position($a, $i, count($list));     // where this room sits on its arc
          $state = $grid[$r['id']][$at];                 // free, taken, yours, past or closed
          $number = substr($r['name'], 1);               // "A07" is shown as "07"
          $mark = ['yours' => '★', 'closed' => '×'][$state] ?? $number;
          $about = $r['name'] . ', ' . (int) $r['capacity'] . ' seats, ' . strtolower($labels[$state]) . ' at ' . $at;
          $class = 'mroom is-' . $state . ($preRoom === (int) $r['id'] ? ' is-selected' : '');
          // The tile is drawn flat, centred on the spot, then turned to follow the curve.
          $rect = '<rect x="' . ($pos['x'] - $a['w'] / 2) . '" y="' . ($pos['y'] - $a['h'] / 2) . '" width="' . $a['w'] . '" height="' . $a['h']
              . '" rx="5" transform="rotate(' . ($pos['angle'] + 90) . ' ' . $pos['x'] . ' ' . $pos['y'] . ')"/>';
        ?>
        <?php if ($state === 'free'): ?>
        <a class="<?= $class ?>" href="<?= e($link(['room' => $r['id'], 'start' => $at, 'arc' => substr($arcName, -1)], '#book')) ?>" aria-label="<?= e($about) ?>. Book it.">
          <title><?= e($about) ?>. Select to book.</title><?= $rect ?><text x="<?= $pos['x'] ?>" y="<?= $pos['y'] ?>"><?= e($mark) ?></text>
        </a>
        <?php else: ?>
        <g class="<?= $class ?>" role="img" aria-label="<?= e($about) ?>">
          <title><?= e($about) ?></title><?= $rect ?><text x="<?= $pos['x'] ?>" y="<?= $pos['y'] ?>"><?= e($mark) ?></text>
        </g>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </svg>
    </div>

    <aside class="moon-panel panel">
      <p class="eyebrow">The Crescent</p>
      <h2 id="map-title">Library map</h2>
      <p class="muted"><?= e(long_date($date)) ?></p>

      <form class="moon-time" action="<?= e(url('rooms.php')) ?>" method="get">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <input type="hidden" name="arc" value="<?= e(substr($arc, -1)) ?>">
        <div class="field">
          <label for="map-at">Show the rooms at</label>
          <select id="map-at" name="at" data-autosubmit>
            <?php foreach ($slots as $slot): ?>
            <option value="<?= e($slot) ?>"<?= $slot === $at ? ' selected' : '' ?>><?= e($slot) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-secondary" type="submit" data-autosubmit-btn>Show</button>
      </form>

      <p class="moon-stat"><strong><?= (int) ($atCounts['free'] ?? 0) ?></strong> of <?= count($rooms) ?> rooms are free at <?= e($at) ?></p>
      <?php if (($atCounts['past'] ?? 0) > 0): ?>
      <p class="notice"><?= e($at) ?> has already passed today. Choose a later time above, or another day.</p>
      <?php endif; ?>

      <ul class="moon-legend" role="list" aria-label="Map key">
        <?php foreach ($labels as $key => $label): ?>
        <li><span class="moon-swatch is-<?= $key ?>" aria-hidden="true"><?= ['yours' => '★', 'closed' => '×'][$key] ?? '' ?></span><?= e($label) ?> <span class="muted">(<?= (int) ($atCounts[$key] ?? 0) ?>)</span></li>
        <?php endforeach; ?>
      </ul>

      <ul class="moon-arcs" role="list" aria-label="The four arcs">
        <?php foreach ($arcs as $arcName => $a): $list = $byArc[$arcName] ?? []; ?>
        <li><strong><?= e(substr($arcName, -1)) ?></strong> <?= e($a['title']) ?> <span class="muted">· <?= count($list) ?> rooms · <?= (int) ($list[0]['capacity'] ?? 0) ?> seats</span></li>
        <?php endforeach; ?>
      </ul>
      <p class="small muted">Select a free room to book it from <?= e($at) ?>. Hover over a room to see its name and size.</p>
    </aside>
  </section>

  <div class="section-head" id="timetable">
    <h2>Timetable: <?= e($arc) ?>, <?= e($arcs[$arc]['title'] ?? '') ?></h2>
    <nav class="chips" aria-label="Choose an arc for the timetable">
      <?php foreach ($arcs as $arcName => $a): ?>
      <a class="chip chip-sm<?= $arcName === $arc ? ' is-active' : '' ?>" href="<?= e($link(['arc' => substr($arcName, -1)], '#timetable')) ?>"<?= $arcName === $arc ? ' aria-current="true"' : '' ?>><?= e($arcName) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="legend" aria-hidden="true">
    <?php foreach ($labels as $key => $label): ?>
    <span class="legend-item"><span class="slot slot-<?= $key ?>"><?= $symbols[$key] ?></span> <?= e($label) ?></span>
    <?php endforeach; ?>
    <span class="legend-count"><?= $arcFree ?> free slots in <?= e($arc) ?> on <?= e(short_date($date)) ?></span>
  </div>

  <div class="table-wrap availability-wrap" tabindex="0" role="region" aria-labelledby="avail-caption">
    <table class="availability" data-availability>
      <caption id="avail-caption">Availability of the rooms in <?= e($arc) ?> for <?= e(long_date($date)) ?>. Each column is a 30 minute slot starting at the time shown. Select a free slot to book it.</caption>
      <thead>
        <tr>
          <th scope="col" class="room-col">Room</th>
          <?php foreach ($slots as $s): ?>
          <th scope="col"><?= e($s) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tableRooms as $room): ?>
        <tr>
          <th scope="row" class="room-col"><span class="room-name"><?= e($room['name']) ?></span><span class="room-cap"><?= (int) $room['capacity'] ?> seats</span></th>
          <?php foreach ($slots as $s): $st = $grid[$room['id']][$s]; ?>
          <td>
            <?php if ($st === 'free'): ?>
            <a class="slot slot-free" href="<?= e($link(['room' => $room['id'], 'start' => $s, 'at' => $s], '#book')) ?>" data-room="<?= (int) $room['id'] ?>" data-start="<?= e($s) ?>" aria-label="Free, book <?= e($room['name']) ?> at <?= e($s) ?>"><span aria-hidden="true"><?= $symbols['free'] ?></span> Free</a>
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
            <?php foreach ($byArc as $arcName => $list): ?>
            <optgroup label="<?= e($arcName . ', ' . ($arcs[$arcName]['title'] ?? '')) ?>">
              <?php foreach ($list as $room): ?>
              <option value="<?= (int) $room['id'] ?>"<?= $preRoom === (int) $room['id'] ? ' selected' : '' ?><?= $room['is_active'] ? '' : ' disabled' ?>><?= e($room['name']) ?> · <?= (int) $room['capacity'] ?> seats<?= $room['is_active'] ? '' : ' (closed)' ?></option>
              <?php endforeach; ?>
            </optgroup>
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
    <h2 id="rooms-title" class="section-title">The four arcs</h2>
    <ul class="room-cards" role="list">
      <?php foreach ($arcs as $arcName => $a): $list = $byArc[$arcName] ?? []; if (!$list) { continue; } ?>
      <?php $freeNow = count(array_filter($list, fn($room) => $grid[$room['id']][$at] === 'free')); ?>
      <li class="room-card">
        <img src="<?= e(url('assets/' . $list[0]['image_path'])) ?>" alt="Floor plan of a room in <?= e($arcName) ?>: a table with <?= (int) $list[0]['capacity'] ?> chairs" width="480" height="300" loading="lazy">
        <div class="room-card-body">
          <h3><?= e($arcName) ?> <span class="pill is-in"><?= $freeNow ?> free at <?= e($at) ?></span></h3>
          <p class="muted"><?= e($a['title']) ?> · <?= count($list) ?> rooms · <?= (int) $list[0]['capacity'] ?> seats each</p>
          <p class="small"><?= e(ucfirst($list[0]['features'])) ?></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
