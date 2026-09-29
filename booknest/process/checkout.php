<?php
// Checkout: validates the order, records it, then applies the (simulated) payment outcome.
// Success: stock is reduced and the order marked paid inside one transaction. Failure: nothing changes.
require __DIR__ . '/../includes/bootstrap.php';
require_post();
verify_csrf();

$cart = cart_lines();
if (!$cart['lines']) {
    flash('info', 'Your cart is empty, so there is nothing to pay for yet.');
    redirect('checkout.php');
}

$name = preg_replace('/\s+/', ' ', input($_POST, 'full_name', 80));
$email = strtolower(input($_POST, 'email', 120));
$phone = preg_replace('/\s+/', '', input($_POST, 'phone', 20));
$method = input($_POST, 'delivery_method', 10);
$address = input($_POST, 'address', 200);
$note = input($_POST, 'note', 300);
$outcome = PAYMENT_SIMULATOR ? input($_POST, 'simulate', 10) : 'success';

$errors = [];
if (mb_strlen($name) < 2) {
    $errors['full_name'] = 'Enter the name for this order.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-z0-9._%+-]+@localhost$/', $email)) {
    $errors['email'] = 'Enter an email address like name@example.com.';
}
if (!preg_match('/^(\+65)?[689]\d{7}$/', $phone)) {
    $errors['phone'] = 'Enter a Singapore number with 8 digits, starting with 6, 8 or 9.';
}
if (!in_array($method, ['delivery', 'pickup'], true)) {
    $errors['delivery_method'] = 'Choose delivery or library pickup.';
}
if ($method === 'delivery') {
    if (mb_strlen($address) < 10) {
        $errors['address'] = 'Enter the full delivery address, including the postal code.';
    } elseif (!preg_match('/\b\d{6}\b/', $address)) {
        $errors['address'] = 'Add the 6 digit Singapore postal code to the address.';
    }
} else {
    $address = '';
}
if (!in_array($outcome, ['success', 'failure'], true)) {
    $errors['simulate'] = 'Choose a payment outcome.';
}
foreach ($cart['lines'] as $line) {
    if ($line['qty'] > $line['stock']) {
        $errors['cart'] = 'Only ' . $line['stock'] . ' copies of ' . $line['title'] . ' are left. Please lower the quantity.';
    }
}

if ($errors) {
    keep_form('checkout', $_POST, $errors);
    flash('error', $errors['cart'] ?? 'Please check the highlighted details and try again.');
    redirect('checkout.php');
}

$fee = $method === 'delivery' ? $cart['delivery_fee'] : 0.0;
$total = $cart['subtotal'] + $fee;
$uid = user_id() ?: null;

// 1. Record the order as pending before asking the payment provider.
db_exec('INSERT INTO orders (user_id, email, full_name, phone, delivery_method, address, note, subtotal, delivery_fee, total, payment_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')',
    [$uid, $email, $name, $phone, $method, $address ?: null, $note ?: null, $cart['subtotal'], $fee, $total]);
$orderId = db_insert_id();

// 2. The provider answers. On failure nothing else changes and the cart is kept.
if ($outcome === 'failure') {
    db_exec("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$orderId]);
    app_log("Payment failed for order $orderId");
    keep_form('checkout', $_POST, []);
    flash('error', 'Your payment was not successful, so we have not charged you. Your cart is saved. Please try again or use another card.');
    redirect('checkout.php');
}

// 3. Payment succeeded: save items and reduce stock atomically.
$db = db();
$db->begin_transaction();
try {
    foreach ($cart['lines'] as $line) {
        $updated = db_exec('UPDATE books SET stock = stock - ? WHERE id = ? AND stock >= ?', [$line['qty'], $line['id'], $line['qty']]);
        if ($updated !== 1) {
            throw new RuntimeException('Not enough stock for ' . $line['title']);
        }
        db_exec('INSERT INTO order_items (order_id, book_id, qty, unit_price) VALUES (?, ?, ?, ?)',
            [$orderId, $line['id'], $line['qty'], $line['price']]);
    }
    db_exec("UPDATE orders SET payment_status = 'paid' WHERE id = ?", [$orderId]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    db_exec("UPDATE orders SET payment_status = 'failed' WHERE id = ?", [$orderId]);
    app_log("Order $orderId rolled back: " . $e->getMessage());
    flash('error', 'Someone bought the last copy while you were checking out. Nothing was charged. Please review your cart.');
    redirect('checkout.php');
}

// 4. Confirm by email (local mail server only) and clear the cart.
$list = '';
foreach ($cart['lines'] as $line) {
    $list .= sprintf("  %d x %s, %s\n", $line['qty'], $line['title'], money($line['line_total']));
}
$how = $method === 'delivery'
    ? "We will deliver to:\n  " . $address . "\nwithin 3 working days."
    : "Your books will be ready to collect at the front desk in 1 working day.";
send_mail($email, 'Your ' . SITE_NAME . ' order #' . $orderId . ' is confirmed',
    mail_body($name, "Thank you for your order #$orderId.\n\n$list\nDelivery: " . money($fee) . "\nTotal paid: " . money($total) . "\n\n$how"));

cart_clear();
$_SESSION['last_order'] = $orderId;
flash('success', 'Payment successful. Order #' . $orderId . ' is confirmed and a confirmation email is on its way.');
redirect($uid ? 'account.php?order=' . $orderId . '#orders' : 'checkout.php?done=' . $orderId);
