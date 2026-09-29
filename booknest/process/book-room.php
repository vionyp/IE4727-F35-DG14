<?php
// Study room booking. PHP is the authority on every rule; the transaction and row locks
// make two people (or one person in two tabs) unable to take the same time or beat the daily cap.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$user = require_login('Please sign in to book a study room.');
verify_csrf();

$roomId = input_int($_POST, 'room_id');
$date = input($_POST, 'date', 10);
$start = input($_POST, 'start', 5);
$duration = input_int($_POST, 'duration');
$purpose = input($_POST, 'purpose', 120);
$back = 'rooms.php?date=' . rawurlencode(in_array($date, bookable_dates(), true) ? $date : date('Y-m-d'));

$errors = validate_booking_input($roomId, $date, $start, $duration);
if ($errors) {
    keep_form('booking', $_POST, $errors);
    flash('error', reset($errors));
    redirect($back . '#book');
}

$end = from_minutes(to_minutes($start) + $duration);
$db = db();
$db->begin_transaction();
try {
    // Lock this member's row (daily cap) and the room's row (overlaps) until we commit.
    db_one('SELECT id FROM users WHERE id = ? FOR UPDATE', [$user['id']]);
    $room = db_one('SELECT id, name, is_active FROM study_rooms WHERE id = ? FOR UPDATE', [$roomId]);

    $problem = '';
    if (!$room) {
        $problem = 'That room does not exist.';
    } elseif (!$room['is_active']) {
        $problem = $room['name'] . ' is closed for maintenance. Please choose another room.';
    } elseif (db_value("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND booking_date = ? AND status = 'confirmed'
                        AND start_time < ? AND end_time > ?", [$roomId, $date, $end, $start]) > 0) {
        $problem = 'Someone has just booked ' . $room['name'] . ' at that time. Please pick another slot.';
    } elseif (db_value("SELECT COUNT(*) FROM room_bookings WHERE user_id = ? AND booking_date = ? AND status = 'confirmed'
                        AND start_time < ? AND end_time > ?", [$user['id'], $date, $end, $start]) > 0) {
        $problem = 'You already have a room booked at that time.';
    } else {
        $used = minutes_used((int) $user['id'], $date);
        if ($used + $duration > DAILY_CAP_MINUTES) {
            $left = max(0, DAILY_CAP_MINUTES - $used);
            $problem = "You already have $used minutes booked on " . short_date($date) . '. The daily limit is '
                . DAILY_CAP_MINUTES . ' minutes' . ($left ? ", so you can book $left more." : '.');
        }
    }

    if ($problem) {
        $db->rollback();
        keep_form('booking', $_POST, ['start' => $problem]);
        flash('error', $problem);
        redirect($back . '#book');
    }

    db_exec("INSERT INTO room_bookings (room_id, user_id, booking_date, start_time, end_time, purpose, status)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmed')", [$roomId, $user['id'], $date, $start, $end, $purpose ?: null]);
    $bookingId = db_insert_id();
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

send_mail($user['email'], 'Study room booked: ' . $room['name'] . ', ' . short_date($date) . ' ' . $start,
    mail_body(first_name($user), "Your study room is booked.\n\n  Room: {$room['name']}\n  Date: " . long_date($date)
        . "\n  Time: $start to $end\n  Booking number: $bookingId\n\nPlease arrive on time; rooms are released after 15 minutes."
        . "\nNeed to cancel? Go to My Account. Cancelling frees your hour for that day."));

flash('success', 'Booked. ' . $room['name'] . ', ' . long_date($date) . ', ' . $start . ' to ' . $end . '. A confirmation email is on its way.');
redirect($back);
