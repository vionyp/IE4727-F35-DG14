<?php
// Modern Enhancement: one room's half hour slots for the Vue slot grid on Study Rooms (assets/js/vue/room-slots.js).
//   GET date=YYYY-MM-DD&room=ID    the slots of that room on that day, plus the member's minutes already booked
// Read only. Open to visitors like rooms.php itself; when signed in, the member's own bookings show as
// "yours" and minutesUsed is filled in. It uses availability(), bookable_dates() and minutes_used() from
// includes/rooms.php, the same functions as the page. Booking still goes through the Base form to
// process/book-room.php, which checks every rule (daily cap, overlaps, closing time) again in PHP.
require __DIR__ . '/../includes/api.php';
api_method('GET');

$dates = bookable_dates();
$date = input($_GET, 'date', 10) ?: $dates[0];
if (!in_array($date, $dates, true)) {
    api_error(422, 'Choose a date from today up to ' . ADVANCE_DAYS . ' days ahead.');
}

$rooms = db_all('SELECT id, name, capacity, floor, is_active FROM study_rooms ORDER BY id');
$roomId = input_int($_GET, 'room');
$room = null;
foreach ($rooms as $r) {
    if ($roomId ? (int) $r['id'] === $roomId : (bool) $r['is_active']) {
        $room = $r;
        break;
    }
}
if (!$room) {
    api_error(404, 'That room does not exist.');
}

$user = current_user();
$grid = availability($date, [$room], user_id());
$arc = substr($room['floor'], -1);
$slots = [];
foreach ($grid[$room['id']] as $start => $status) {
    $slots[] = [
        'start' => $start,
        'status' => $status,   // free, taken, yours, past or closed
        'href' => url('rooms.php?' . http_build_query(['date' => $date, 'room' => $room['id'], 'start' => $start, 'at' => $start, 'arc' => $arc]) . '#book'),
    ];
}

api_send(200, [
    'date' => $date,
    'dateLong' => long_date($date),
    'dates' => array_map(fn($d, $i) => ['value' => $d, 'label' => ($i === 0 ? 'Today, ' : ($i === 1 ? 'Tomorrow, ' : '')) . short_date($d)],
        $dates, array_keys($dates)),
    'room' => ['id' => (int) $room['id'], 'name' => $room['name'], 'capacity' => (int) $room['capacity'], 'arc' => $room['floor'], 'isActive' => (bool) $room['is_active']],
    'rooms' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'capacity' => (int) $r['capacity'], 'arc' => $r['floor'], 'isActive' => (bool) $r['is_active']], $rooms),
    'slots' => $slots,
    'signedIn' => (bool) $user,
    'minutesUsed' => $user ? minutes_used((int) $user['id'], $date) : 0,
    'rules' => ['dailyCap' => DAILY_CAP_MINUTES, 'maxBooking' => MAX_BOOKING_MINUTES, 'slotMinutes' => SLOT_MINUTES, 'closeMinutes' => CLOSE_HOUR * 60],
]);
