<?php
// Cart and checkout: order summary with quantity controls, the checkout form, and the guest confirmation.
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$doneId = input_int($_GET, 'done');
$order = null;
if ($doneId && ($_SESSION['last_order'] ?? 0) === $doneId) {
    $order = db_one('SELECT * FROM orders WHERE id = ?', [$doneId]);
    $order['items'] = db_all('SELECT oi.qty, oi.unit_price, b.id, b.title, b.author, b.cover_path, b.cover_alt
                              FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ?', [$doneId]);
}

$cart = cart_lines();
$totalWith = $cart['subtotal'] + $cart['delivery_fee'];
$method = old('checkout', 'delivery_method', 'delivery');
$simulate = old('checkout', 'simulate', 'success');

$page_title = $order ? 'Order confirmed' : 'Checkout';
$page_desc = 'Review your cart and place your order.';
$body_class = 'page-checkout';
$scripts = ['forms.js'];
$crumbs = [['Home', url()], ['Browse', url('catalogue.php')], [$order ? 'Order confirmed' : 'Checkout', null]];
require __DIR__ . '/includes/header.php';
?>

<div class="container">
<?php if ($order): ?>
  <section class="confirmation panel" aria-labelledby="done-title">
    <p class="eyebrow">Payment successful</p>
    <h1 id="done-title">Thank you, <?= e(explode(' ', $order['full_name'])[0]) ?>. Order #<?= (int) $order['id'] ?> is confirmed.</h1>
    <p class="lede">A confirmation has been sent to <?= e($order['email']) ?>. <?= $order['delivery_method'] === 'delivery' ? 'We will deliver within 3 working days.' : 'Collect your books at the front desk from tomorrow.' ?></p>
    <ul class="order-lines" role="list">
      <?php foreach ($order['items'] as $it): ?>
      <li><?= cover_img($it, 'line-cover', false, 64) ?><div><strong><?= e($it['title']) ?></strong><span class="muted"><?= e($it['author']) ?> · <?= (int) $it['qty'] ?> × <?= money($it['unit_price']) ?></span></div></li>
      <?php endforeach; ?>
    </ul>
    <p class="order-total">Total paid <strong><?= money($order['total']) ?></strong></p>
    <div class="cluster">
      <a class="btn btn-primary" href="<?= e(url('catalogue.php')) ?>">Keep browsing</a>
      <a class="btn btn-ghost" href="<?= e(url('sign-in.php#register')) ?>">Create an account to track orders</a>
    </div>
  </section>

<?php elseif (!$cart['lines']): ?>
  <header class="page-head"><h1>Your cart</h1></header>
  <?= empty_state('Your cart is empty', 'Find something good to read, then come back here to check out. No account needed.',
      '<a class="btn btn-primary btn-sm" href="' . e(url('catalogue.php')) . '">Browse books</a>'
      . '<a class="btn btn-secondary btn-sm" href="' . e(url('catalogue.php?sort=newest')) . '">See new arrivals</a>') ?>

<?php else: ?>
  <header class="page-head">
    <h1>Checkout</h1>
    <p class="lede">No account needed. Your details are only used for this order.</p>
  </header>

  <div class="checkout-grid">
    <aside class="summary panel" aria-labelledby="summary-title">
      <h2 id="summary-title" class="panel-title">Order summary</h2>
      <ul class="cart-lines" role="list">
        <?php foreach ($cart['lines'] as $line): $max = min((int) $line['stock'], MAX_QTY_PER_BOOK); ?>
        <li class="cart-line">
          <a href="<?= e(book_url((int) $line['id'])) ?>" tabindex="-1" aria-hidden="true"><?= cover_img($line, 'line-cover', false, 64) ?></a>
          <div class="cart-line-body">
            <a class="cart-line-title" href="<?= e(book_url((int) $line['id'])) ?>"><?= e($line['title']) ?></a>
            <span class="muted small"><?= e($line['author']) ?> · <?= money($line['price']) ?> each</span>
            <div class="cart-line-actions">
              <form class="qty-form" action="<?= e(url('process/cart.php')) ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="book_id" value="<?= (int) $line['id'] ?>">
                <label class="visually-hidden" for="qty-<?= (int) $line['id'] ?>">Quantity of <?= e($line['title']) ?></label>
                <input class="input qty-input" type="number" id="qty-<?= (int) $line['id'] ?>" name="qty" value="<?= (int) $line['qty'] ?>" min="1" max="<?= $max ?>" step="1" required>
                <button class="btn btn-ghost btn-sm" type="submit" name="action" value="update">Update</button>
                <button class="btn btn-danger btn-sm" type="submit" name="action" value="remove" formnovalidate aria-label="Remove <?= e($line['title']) ?>">Remove</button>
              </form>
            </div>
          </div>
          <span class="cart-line-total"><?= money($line['line_total']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <dl class="totals">
        <div><dt>Subtotal</dt><dd><?= money($cart['subtotal']) ?></dd></div>
        <div data-delivery-only><dt>Delivery</dt><dd><?= $cart['free_delivery'] ? 'Free' : money($cart['delivery_fee']) ?></dd></div>
        <div class="grand"><dt>Total</dt><dd data-total data-with-delivery="<?= e(money($totalWith)) ?>" data-without-delivery="<?= e(money($cart['subtotal'])) ?>"><?= money($method === 'pickup' ? $cart['subtotal'] : $totalWith) ?></dd></div>
      </dl>
      <?php if (!$cart['free_delivery']): ?>
      <p class="small muted">Free delivery on orders over <?= money(FREE_DELIVERY_FROM) ?>. Library pickup is always free.</p>
      <?php endif; ?>
    </aside>

    <form class="panel form checkout-form" id="checkout" action="<?= e(url('process/checkout.php')) ?>" method="post" data-validate data-delivery novalidate>
      <?= csrf_field() ?>
      <h2 class="panel-title">Your details</h2>
      <div class="form-grid cols-2">
        <div class="field span-2">
          <label for="co-name">Full name</label>
          <input type="text" id="co-name" name="full_name" required minlength="2" maxlength="80" autocomplete="name" value="<?= e(old('checkout', 'full_name', $user['full_name'] ?? '')) ?>"<?= field_attrs('checkout', 'full_name') ?>>
          <?= field_error_html('checkout', 'full_name') ?>
        </div>
        <div class="field">
          <label for="co-email">Email</label>
          <input type="email" id="co-email" name="email" required maxlength="120" autocomplete="email" value="<?= e(old('checkout', 'email', $user['email'] ?? '')) ?>"<?= field_attrs('checkout', 'email', 'co-email-hint') ?>>
          <p class="hint" id="co-email-hint">For your receipt. Demo emails reach local @localhost accounts only.</p>
          <?= field_error_html('checkout', 'email') ?>
        </div>
        <div class="field">
          <label for="co-phone">Mobile number</label>
          <input type="tel" id="co-phone" name="phone" required maxlength="20" autocomplete="tel" placeholder="9123 4567" pattern="(\+65)?\s?[689]\d{3}\s?\d{4}" data-pattern-msg="Enter a Singapore number with 8 digits, starting with 6, 8 or 9." value="<?= e(old('checkout', 'phone')) ?>"<?= field_attrs('checkout', 'phone') ?>>
          <?= field_error_html('checkout', 'phone') ?>
        </div>
      </div>

      <fieldset class="field">
        <legend>How would you like your books?</legend>
        <div class="choice-group cols-2">
          <label class="choice"><input type="radio" name="delivery_method" value="delivery" required<?= $method !== 'pickup' ? ' checked' : '' ?>><span><strong><?= icon('truck', 18) ?> Delivery</strong><span><?= $cart['free_delivery'] ? 'Free' : money(DELIVERY_FEE) ?>, within 3 working days</span></span></label>
          <label class="choice"><input type="radio" name="delivery_method" value="pickup"<?= $method === 'pickup' ? ' checked' : '' ?>><span><strong><?= icon('pin', 18) ?> Library pickup</strong><span>Free, ready tomorrow at the front desk</span></span></label>
        </div>
        <?= field_error_html('checkout', 'delivery_method') ?>
      </fieldset>

      <div class="field" data-address-block>
        <label for="co-address">Delivery address</label>
        <textarea id="co-address" name="address" rows="3" maxlength="200" autocomplete="street-address" placeholder="Block, street, unit number and 6 digit postal code"<?= field_attrs('checkout', 'address') ?>><?= e(old('checkout', 'address')) ?></textarea>
        <?= field_error_html('checkout', 'address') ?>
      </div>

      <div class="field">
        <label for="co-note">Note for us <span class="optional">(optional)</span></label>
        <textarea id="co-note" name="note" rows="2" maxlength="300" data-count placeholder="A gift message, or where to leave the parcel"><?= e(old('checkout', 'note')) ?></textarea>
      </div>

      <?php if (PAYMENT_SIMULATOR): ?>
      <fieldset class="field simulator">
        <legend><?= icon('lock', 16) ?> Payment simulator <span class="optional">(demo only)</span></legend>
        <p class="hint">Payment is handled by a third party. Choose the answer the provider should give.</p>
        <div class="choice-group cols-2">
          <label class="choice"><input type="radio" name="simulate" value="success"<?= $simulate !== 'failure' ? ' checked' : '' ?>><span><strong>Payment succeeds</strong><span>Order is confirmed</span></span></label>
          <label class="choice"><input type="radio" name="simulate" value="failure"<?= $simulate === 'failure' ? ' checked' : '' ?>><span><strong>Payment fails</strong><span>Card is declined</span></span></label>
        </div>
      </fieldset>
      <?php endif; ?>

      <button class="btn btn-primary btn-block btn-lg" type="submit">Place order · <span data-total data-with-delivery="<?= e(money($totalWith)) ?>" data-without-delivery="<?= e(money($cart['subtotal'])) ?>"><?= money($method === 'pickup' ? $cart['subtotal'] : $totalWith) ?></span></button>
      <p class="small muted center">You will get an email confirmation straight away.</p>
    </form>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
