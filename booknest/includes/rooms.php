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
