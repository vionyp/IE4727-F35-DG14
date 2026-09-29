<?php
// Shared page header. Set $page_title, and optionally $page_desc, $body_class, $crumbs and $scripts, before including.
$page_title ??= 'Home';
$page_desc ??= 'BookNest is an online library where you can browse, sample and buy books, and book a quiet study room.';
$body_class ??= '';
$crumbs ??= [];
$scripts ??= [];
$user = current_user();
$count = cart_count();
$nav = [['index.php', 'Home'], ['catalogue.php', 'Browse'], ['rooms.php', 'Study Rooms']];
if (is_admin()) {
    $nav[] = ['admin.php', 'Admin'];
}
$searchValue = is_page('catalogue.php') ? input($_GET, 'q', 100) : '';
?>
<!doctype html>
<html lang="en-SG" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($page_desc) ?>">
<meta name="theme-color" content="#0E1116">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/pages.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/print.css')) ?>" media="print">
<script>document.documentElement.className = 'js';</script>
<script src="<?= e(asset('js/nav.js')) ?>" defer></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= e(asset('js/' . $s)) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="<?= e($body_class) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e(SITE_NAME) ?> home">
      <svg class="brand-mark" viewBox="0 0 40 40" width="34" height="34" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="10" fill="#E5A93D"/>
        <path d="M11 9.5c3-1.2 6-.9 9 1v11c-3-1.9-6-2.2-9-1z" fill="#1A1204"/>
        <path d="M29 9.5c-3-1.2-6-.9-9 1v11c3-1.9 6-2.2 9-1z" fill="#1A1204" opacity=".7"/>
        <path d="M23 13.8h3.8M23 16.8h3.8M23 19.6h2.4" stroke="#E5A93D" stroke-width="1.3" stroke-linecap="round"/>
        <path d="M6.5 23Q20 38 33.5 27.6" fill="none" stroke="#1A1204" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M6.5 27.6Q20 38 33.5 23" fill="none" stroke="#1A1204" stroke-width="1.8" stroke-linecap="round"/>
        <path d="M10.5 26.4Q20 34.2 29.5 26.4" fill="none" stroke="#1A1204" stroke-width="1.6" stroke-linecap="round" opacity=".7"/>
      </svg>
      <span class="brand-name"><?= e(SITE_NAME) ?></span>
    </a>

    <button type="button" class="nav-toggle icon-btn" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
      <?= icon('menu') ?><span class="visually-hidden">Menu</span>
    </button>

    <nav id="site-nav" class="site-nav" aria-label="Main">
      <ul>
        <?php foreach ($nav as [$file, $label]): ?>
        <li><a href="<?= e(url($file === 'index.php' ? '' : $file)) ?>"<?= is_page($file) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <form class="site-search" role="search" action="<?= e(url('catalogue.php')) ?>" method="get">
      <label for="site-q" class="visually-hidden">Search by title, author or serial number</label>
      <input type="search" id="site-q" name="q" value="<?= e($searchValue) ?>" placeholder="Search titles, authors, serials" maxlength="100" autocomplete="off">
      <button type="submit" class="search-btn"><?= icon('search', 18) ?><span class="visually-hidden">Search</span></button>
    </form>

    <div class="header-actions">
      <a class="cart-link<?= is_page('checkout.php') ? ' is-active' : '' ?>" href="<?= e(url('checkout.php')) ?>"<?= is_page('checkout.php') ? ' aria-current="page"' : '' ?>>
        <?= icon('cart') ?><span class="visually-hidden">Cart,</span>
        <span class="badge<?= $count ? '' : ' is-empty' ?>"><?= $count ?></span><span class="visually-hidden"> <?= $count === 1 ? 'item' : 'items' ?></span>
      </a>
      <?php if ($user): ?>
      <a class="account-link" href="<?= e(url('account.php')) ?>"<?= is_page('account.php') ? ' aria-current="page"' : '' ?>><?= icon('user', 18) ?><span>My Account</span></a>
      <?php else: ?>
      <a class="account-link" href="<?= e(url('sign-in.php')) ?>"<?= is_page('sign-in.php') ? ' aria-current="page"' : '' ?>><?= icon('user', 18) ?><span>Sign in</span></a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?= flash_render() ?>
<main id="main" tabindex="-1">
<?= $crumbs ? breadcrumbs($crumbs) : '' ?>
