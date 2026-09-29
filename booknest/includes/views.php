<?php
// Reusable view components: cards, rows, breadcrumbs, empty states and icons.

// Returns an inline SVG icon by name (decorative; the text beside it carries the meaning).
function icon(string $name, int $size = 20): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4.2-4.2"/>',
        'cart' => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'left' => '<path d="m15 5-7 7 7 7"/>',
        'right' => '<path d="m9 5 7 7-7 7"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M8 7h7"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'minus' => '<path d="M6 12h12"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'pin' => '<path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'door' => '<path d="M5 21V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v17"/><path d="M3 21h18"/><circle cx="15" cy="12" r="1"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? '') . '</svg>';
}

// Returns a book cover image tag.
function cover_img(array $book, string $class = '', bool $lazy = true, int $width = 400): string
{
    return '<img class="' . e($class) . '" src="' . e(url('assets/' . $book['cover_path'])) . '" alt="' . e($book['cover_alt'])
        . '" width="' . $width . '" height="' . (int) round($width * 1.5) . '"' . ($lazy ? ' loading="lazy"' : '') . ' decoding="async">';
}

// Returns a cover card for a book, with a quick info overlay on hover and focus.
function book_card(array $b, bool $lazy = true): string
{
    return '<article class="card" data-title="' . e(mb_strtolower($b['title'])) . '" data-author="' . e(mb_strtolower($b['author'])) . '">'
        . '<a class="card-link" href="' . e(book_url((int) $b['id'])) . '">'
        . '<span class="card-cover">' . cover_img($b, '', $lazy) . '<span class="card-overlay" aria-hidden="true">'
        . '<span class="card-hook">' . e($b['hook']) . '</span><span class="card-cta">View details</span></span></span>'
        . '<span class="card-title">' . e($b['title']) . '</span></a>'
        . '<p class="card-author">' . e($b['author']) . '</p>'
        . '<p class="card-meta">' . rating_html($b['rating']) . '<span class="card-price">' . money($b['price']) . '</span></p>'
        . '</article>';
}

// Returns a horizontal, swipeable row of cards with previous and next buttons.
function book_row(string $id, string $title, array $books, ?string $link = null, string $blurb = ''): string
{
    if (!$books) {
        return '';
    }
    $heading = $link ? '<a href="' . e($link) . '">' . e($title) . ' <span class="row-see">See all</span></a>' : e($title);
    $html = '<section class="row" aria-labelledby="' . e($id) . '">'
        . '<div class="row-head"><div><h2 id="' . e($id) . '">' . $heading . '</h2>'
        . ($blurb ? '<p class="row-blurb">' . e($blurb) . '</p>' : '') . '</div>'
        . '<div class="row-controls" hidden>'
        . '<button type="button" class="icon-btn" data-row-prev aria-label="Show previous books in ' . e($title) . '">' . icon('left') . '</button>'
        . '<button type="button" class="icon-btn" data-row-next aria-label="Show more books in ' . e($title) . '">' . icon('right') . '</button>'
        . '</div></div><div class="row-track" data-row-track>';
    foreach ($books as $b) {
        $html .= book_card($b);
    }
    return $html . '</div></section>';
}

// Returns breadcrumb navigation from a list of [label, url or null].
function breadcrumbs(array $items): string
{
    $html = '<nav class="breadcrumbs container" aria-label="Breadcrumb"><ol>';
    $last = count($items) - 1;
    foreach ($items as $i => [$label, $href]) {
        $html .= '<li>' . ($i === $last || !$href
            ? '<span aria-current="page">' . e($label) . '</span>'
            : '<a href="' . e($href) . '">' . e($label) . '</a>') . '</li>';
    }
    return $html . '</ol></nav>';
}

// Returns a friendly empty state with an illustration, message and next step.
function empty_state(string $title, string $text, string $actionsHtml = ''): string
{
    return '<div class="empty-state"><img src="' . e(url('assets/img/empty-shelf.svg')) . '" alt="" width="220" height="140">'
        . '<h2>' . e($title) . '</h2><p>' . e($text) . '</p>'
        . ($actionsHtml ? '<div class="empty-actions">' . $actionsHtml . '</div>' : '') . '</div>';
}

// Returns a year for display, with BC years written out.
function year_label(int $year): string
{
    return $year < 0 ? 'c. ' . abs($year) . ' BC' : (string) $year;
}

// Returns the shared SELECT column list for book cards.
function card_columns(string $alias = 'b'): string
{
    return "$alias.id, $alias.serial_no, $alias.title, $alias.author, $alias.hook, $alias.price, $alias.rating, $alias.stock, $alias.cover_path, $alias.cover_alt, $alias.category_id, $alias.created_at";
}
