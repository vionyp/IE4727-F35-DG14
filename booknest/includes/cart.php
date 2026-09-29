<?php
// Shopping cart stored in the session as book id => quantity.

// Returns the raw cart array.
function cart(): array
{
    return is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
}

// Returns the number of items in the cart, for the header badge.
function cart_count(): int
{
    return array_sum(cart());
}

// Sets the quantity of a book in the cart (0 removes it).
function cart_set(int $bookId, int $qty): void
{
    $cart = cart();
    if ($qty <= 0) {
        unset($cart[$bookId]);
    } else {
        $cart[$bookId] = $qty;
    }
    $_SESSION['cart'] = $cart;
}

// Empties the cart.
function cart_clear(): void
{
    unset($_SESSION['cart']);
}

// Loads cart lines with current book data and totals; drops books no longer on sale.
function cart_lines(): array
{
    $cart = cart();
    $lines = [];
    $subtotal = 0.0;
    if ($cart) {
        $ids = array_keys($cart);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_all("SELECT id, serial_no, title, author, price, stock, cover_path, cover_alt
                        FROM books WHERE status = 'approved' AND id IN ($marks)", $ids);
        foreach ($rows as $b) {
            $qty = (int) $cart[$b['id']];
            $line = (float) $b['price'] * $qty;
            $subtotal += $line;
            $lines[] = $b + ['qty' => $qty, 'line_total' => $line];
        }
    }
    $delivery = DELIVERY_FEE;
    $freeDelivery = $subtotal >= FREE_DELIVERY_FROM;
    return ['lines' => $lines, 'subtotal' => $subtotal, 'delivery_fee' => $freeDelivery ? 0.0 : $delivery,
            'free_delivery' => $freeDelivery];
}
