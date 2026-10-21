<?php
// Study room rules shared by the rooms page and the booking endpoint.

// Returns every slot start time of the day as "HH:MM".
function room_slots(): array
{
    $slots = [];
    for ($m = OPEN_HOUR * 60; $m < CLOSE_HOUR * 60; $m += SLOT_MINUTES) {
        $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    return $slots;
}

// Converts "HH:MM" or "HH:MM:SS" to minutes after midnight.
function to_minutes(string $time): int
{
    [$h, $m] = array_map('intval', explode(':', $time));
    return $h * 60 + $m;
}

// Converts minutes after midnight to "HH:MM".
function from_minutes(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

// Returns the list of bookable dates: today up to ADVANCE_DAYS ahead.
function bookable_dates(): array
{
    $dates = [];
    for ($i = 0; $i <= ADVANCE_DAYS; $i++) {
        $dates[] = date('Y-m-d', strtotime("+$i day", strtotime('today')));
    }
    return $dates;
}

// Returns minutes already booked by a user on a date (confirmed bookings only).
function minutes_used(int $userId, string $date): int
{
    return (int) db_value("SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0)
                           FROM room_bookings WHERE user_id = ? AND booking_date = ? AND status = 'confirmed'",
                          [$userId, $date]);
}

// Checks the parts of a booking request that do not need the database. Returns field errors.
function validate_booking_input(int $roomId, string $date, string $start, int $duration): array
{
    $errors = [];
    if ($roomId <= 0) {
        $errors['room'] = 'Choose a room.';
    }
    if (!in_array($date, bookable_dates(), true)) {
        $errors['date'] = 'Choose a date from today up to ' . ADVANCE_DAYS . ' days ahead.';
    }
    if (!in_array($start, room_slots(), true)) {
        $errors['start'] = 'Choose a start time on the half hour between '
            . sprintf('%02d:00 and %02d:00.', OPEN_HOUR, CLOSE_HOUR);
    }
    if (!in_array($duration, [30, 60], true) || $duration > MAX_BOOKING_MINUTES) {
        $errors['duration'] = 'A booking can be 30 or 60 minutes long.';
    }
    if (!$errors) {
        $end = to_minutes($start) + $duration;
        if ($end > CLOSE_HOUR * 60) {
            $errors['duration'] = sprintf('The library closes at %02d:00. Choose an earlier start or a shorter booking.', CLOSE_HOUR);
        }
        if (strtotime($date . ' ' . $start) <= time()) {
            $errors['start'] = 'That time has already passed. Choose a later slot.';
        }
    }
    return $errors;
}

// Returns a map of room id => slot => ['status' => free|taken|yours|past|closed].
function availability(string $date, array $rooms, int $userId): array
{
    $bookings = db_all("SELECT room_id, user_id, start_time, end_time FROM room_bookings
                        WHERE booking_date = ? AND status = 'confirmed'", [$date]);
    $isToday = $date === date('Y-m-d');
    $nowMin = (int) date('G') * 60 + (int) date('i');
    $grid = [];
    foreach ($rooms as $room) {
        foreach (room_slots() as $slot) {
            $s = to_minutes($slot);
            $status = 'free';
            if (!$room['is_active']) {
                $status = 'closed';
            } elseif ($isToday && $s <= $nowMin) {
                $status = 'past';
            }
            foreach ($bookings as $b) {
                if ((int) $b['room_id'] === (int) $room['id']
                    && to_minutes($b['start_time']) <= $s && to_minutes($b['end_time']) > $s) {
                    $status = (int) $b['user_id'] === $userId ? 'yours' : ($status === 'past' ? 'past' : 'taken');
                }
            }
            $grid[$room['id']][$slot] = $status;
        }
    }
    return $grid;
}

// ---- The Crescent: the library map --------------------------------------------------------
// The building is a crescent moon. Study rooms sit on four arcs (curved rows) that share one
// centre point. The map is an SVG 660 units wide and 620 high; these are that centre's x and y.
const MAP_CX = 330;
const MAP_CY = 310;

// Describes each arc: its name for visitors, how far it is from the centre (radius), how far it
// opens above and below the west point in degrees (half_span), and the size of one room tile.
// Inner arcs are shorter, which is what gives the building its tapering moon shape.
function map_arcs(): array
{
    return [
        'Arc A' => ['title' => 'Focus pods',    'radius' => 262, 'half_span' => 100, 'w' => 34, 'h' => 24],
        'Arc B' => ['title' => 'Study rooms',   'radius' => 224, 'half_span' => 88,  'w' => 36, 'h' => 26],
        'Arc C' => ['title' => 'Media rooms',   'radius' => 186, 'half_span' => 72,  'w' => 38, 'h' => 28],
        'Arc D' => ['title' => 'Project rooms', 'radius' => 148, 'half_span' => 48,  'w' => 46, 'h' => 30],
    ];
}

// Returns where room number $index (0 = first) of $count sits on an arc: its x and y on the map
// and the angle of that spot. Rooms are spread evenly from the top end of the arc to the bottom.
// 180 degrees is due west (the middle of the crescent); cos and sin turn an angle into x and y.
function map_position(array $arc, int $index, int $count): array
{
    $step = $count > 1 ? (2 * $arc['half_span']) / ($count - 1) : 0;   // degrees between two rooms
    $angle = 180 + $arc['half_span'] - $index * $step;
    return [
        'x' => round(MAP_CX + $arc['radius'] * cos(deg2rad($angle)), 1),
        'y' => round(MAP_CY + $arc['radius'] * sin(deg2rad($angle)), 1),
        'angle' => round($angle, 1),
    ];
}
