<?php
// Shared page footer: opening hours with live status, quick links, credits.
$status = library_status();
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <p class="footer-logo"><img src="<?= e(url('assets/img/favicon.svg')) ?>" alt="" width="28" height="28"><span><?= e(SITE_NAME) ?></span></p>
      <p>An online library for students and readers in Singapore: borrow books free, read before you borrow, and book a quiet room.</p>
    </div>
    <section aria-labelledby="footer-visit">
      <h2 id="footer-visit">Visit us</h2>
      <p class="status-pill <?= $status['open'] ? 'is-open' : 'is-closed' ?>"><span class="dot" aria-hidden="true"></span><?= e($status['label']) ?> <span class="status-detail">· <?= e($status['detail']) ?></span></p>
      <p><?= e(BRANCH_NAME) ?><br><?= e(BRANCH_ADDRESS) ?></p>
      <p>Open every day, <?= sprintf('%02d:00 to %02d:00', OPEN_HOUR, CLOSE_HOUR) ?>.<br>Study rooms follow the same hours.</p>
    </section>
    <section aria-labelledby="footer-explore">
      <h2 id="footer-explore">Explore</h2>
      <ul class="footer-links">
        <li><a href="<?= e(url('catalogue.php')) ?>">Browse all books</a></li>
        <li><a href="<?= e(url('catalogue.php?sort=newest')) ?>">New arrivals</a></li>
        <li><a href="<?= e(url('rooms.php')) ?>">Book a study room</a></li>
        <li><a href="<?= e(url('add-book.php')) ?>">Suggest a book</a></li>
        <li><a href="<?= e(url(current_user() ? 'account.php' : 'sign-in.php')) ?>"><?= current_user() ? 'My Account' : 'Sign in or join' ?></a></li>
      </ul>
    </section>
    <section aria-labelledby="footer-about">
      <h2 id="footer-about">About this project</h2>
      <p>Built by <?= e(TEAM_NAMES) ?> for IE4727 Web Application Design.</p>
      <p>Questions? Write to <a href="mailto:<?= e(TEAM_EMAIL) ?>"><?= e(TEAM_EMAIL) ?></a> or <a href="mailto:<?= e(TEAM_EMAIL_2) ?>"><?= e(TEAM_EMAIL_2) ?></a>.</p>
      <p>The logo, illustrations and room plans are original artwork made for this site.</p>
    </section>
  </div>
  <div class="container footer-base">
    <p>Samples of classics are public domain texts courtesy of Project Gutenberg. Previews of other books are written by BookNest and are not excerpts.</p>
    <p>Book cover images courtesy of Open Library; covers belong to their publishers.</p>
    <p>We use two small cookies: one remembers books you viewed, one remembers your email if you ask us to.</p>
    <p>A student project. Not affiliated with the National Library Board.</p>
  </div>
</footer>

<?php
  // Phone tab bar: the four main places, always one tap away at the bottom of small screens.
  $tabs = [['index.php', '', 'home', 'Home'], ['catalogue.php', 'catalogue.php', 'compass', 'Browse'], ['rooms.php', 'rooms.php', 'door', 'Rooms']];
?>
<nav class="tabbar" aria-label="Quick navigation">
  <?php foreach ($tabs as [$file, $href, $tabIcon, $label]): ?>
  <a href="<?= e(url($href)) ?>"<?= is_page($file) ? ' aria-current="page"' : '' ?>><?= icon($tabIcon, 22) ?><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
  <a href="<?= e(url(current_user() ? 'account.php' : 'sign-in.php')) ?>"<?= (is_page('account.php') || is_page('sign-in.php')) ? ' aria-current="page"' : '' ?>><?= icon('user', 22) ?><span><?= current_user() ? 'Account' : 'Sign in' ?></span></a>
</nav>
</body>
</html>
