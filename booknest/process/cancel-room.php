<?php
// Cancel a study room booking (UPDATE to cancelled). Only the owner, and only before it starts.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$user = require_login();
verify_csrf();

$bookingId = input_int($_POST, 'booking_id');
$booking = $bookingId ? db_one("SELECT rb.*, r.name FROM room_bookings rb JOIN study_rooms r ON r.id = rb.room_id
                                WHERE rb.id = ? AND rb.user_id = ?", [$bookingId, $user['id']]) : null;

if (!$booking || $booking['status'] !== 'confirmed') {
    flash('error', 'We could not find that booking. It may already be cancelled.');
    redirect('account.php#bookings');
}
if (strtotime($booking['booking_date'] . ' ' . $booking['start_time']) <= time()) {
    flash('error', 'That booking has already started, so it can no longer be cancelled.');
    redirect('account.php#bookings');
}

db_exec("UPDATE room_bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?", [$bookingId, $user['id']]);

send_mail($user['email'], 'Study room booking cancelled',
    mail_body(first_name($user), "Your booking has been cancelled.\n\n  Room: {$booking['name']}\n  Date: " . long_date($booking['booking_date'])
        . "\n  Time: " . hm($booking['start_time']) . ' to ' . hm($booking['end_time']) . "\n\nYour study hour for that day is free again."));

flash('success', 'Cancelled ' . $booking['name'] . ' on ' . short_date($booking['booking_date']) . ' at ' . hm($booking['start_time'])
    . '. Your hour for that day is free again.');
redirect('account.php#bookings');
