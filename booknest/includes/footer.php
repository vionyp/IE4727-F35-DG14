<?php
// Shared page footer: opening hours with live status, quick links, credits.
$status = library_status();
?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
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
      <p>Questions? Write to <a href="mailto:<?= e(TEAM_EMAIL) ?>"><?= e(TEAM_EMAIL) ?></a>.</p>
      <p>Covers and illustrations are original artwork made for this site.</p>
    </section>
  </div>
  <div class="container footer-base">
    <p>Public domain sample texts courtesy of Project Gutenberg.</p>
    <p>We use two small cookies: one remembers books you viewed, one remembers your email if you ask us to.</p>
    <p>A student project. Not affiliated with the National Library Board.</p>
  </div>
</footer>
</body>
</html>
