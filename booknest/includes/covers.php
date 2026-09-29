<?php
// Original generated artwork: typographic book covers and study room plans, as SVG.

// Converts HSL values (degrees, percent, percent) to a hex colour.
function hsl_hex(float $h, float $s, float $l): string
{
    $h = fmod(fmod($h, 360) + 360, 360) / 360;
    $s /= 100;
    $l /= 100;
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $conv = function (float $t) use ($p, $q): int {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) $v = $p + ($q - $p) * 6 * $t;
        elseif ($t < 1 / 2) $v = $q;
        elseif ($t < 2 / 3) $v = $p + ($q - $p) * (2 / 3 - $t) * 6;
        else $v = $p;
        return (int) round($v * 255);
    };
    return sprintf('#%02X%02X%02X', $conv($h + 1 / 3), $conv($h), $conv($h - 1 / 3));
}

// Returns the colour trio (background, accent, ink) for a seed number.
function cover_palette(int $seed): array
{
    $hue = fmod($seed * 137.508, 360);
    return [
        'bg' => hsl_hex($hue, 42, 20),
        'bg2' => hsl_hex($hue + 12, 38, 28),
        'accent' => hsl_hex($hue + 38, 58, 62),
        'ink' => '#F6F0E4',
    ];
}

// Splits a title into lines that fit the cover width.
function wrap_words(string $text, int $maxChars, int $maxLines = 4): array
{
    $lines = [];
    $line = '';
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        $try = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($try) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.') . '...';
    }
    return $lines;
}

// Builds a 400 x 600 SVG cover from title, author and a seed that picks colours and pattern.
function cover_svg(string $title, string $author, int $seed, string $label = ''): string
{
    $c = cover_palette($seed);
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $len = mb_strlen($title);
    [$size, $chars] = $len <= 10 ? [58, 10] : ($len <= 22 ? [46, 13] : [36, 17]);
    $lines = wrap_words($title, $chars);
    $style = $seed % 5;

    // Decorative layer, one of five geometric motifs.
    $art = match ($style) {
        0 => '<circle cx="300" cy="470" r="150" fill="' . $c['accent'] . '" opacity=".9"/>'
            . '<circle cx="300" cy="470" r="95" fill="' . $c['bg'] . '" opacity=".55"/>',
        1 => '<g stroke="' . $c['accent'] . '" stroke-width="14" opacity=".55">'
            . implode('', array_map(fn($i) => '<line x1="' . (-200 + $i * 60) . '" y1="600" x2="' . (100 + $i * 60) . '" y2="300"/>', range(0, 11)))
            . '</g><rect x="0" y="300" width="400" height="6" fill="' . $c['accent'] . '"/>',
        2 => '<g fill="' . $c['accent'] . '" opacity=".7">'
            . implode('', array_map(fn($i) => '<circle cx="' . (50 + ($i % 7) * 50) . '" cy="' . (340 + intdiv($i, 7) * 50) . '" r="' . (4 + ($i * 7 % 9)) . '"/>', range(0, 34)))
            . '</g>',
        3 => '<path d="M110 600V430a90 90 0 0 1 180 0V600z" fill="' . $c['accent'] . '"/>'
            . '<path d="M140 600V440a60 60 0 0 1 120 0V600z" fill="' . $c['bg2'] . '"/>',
        default => '<rect x="0" y="330" width="400" height="270" fill="' . $c['bg2'] . '"/>'
            . '<rect x="40" y="370" width="320" height="4" fill="' . $c['accent'] . '"/>'
            . '<rect x="40" y="560" width="320" height="4" fill="' . $c['accent'] . '"/>'
            . '<polygon points="200,395 250,465 200,535 150,465" fill="' . $c['accent'] . '"/>',
    };

    $tspans = '';
    foreach ($lines as $i => $l) {
        $tspans .= '<tspan x="40" dy="' . ($i === 0 ? 0 : round($size * 1.08)) . '">' . $x($l) . '</tspan>';
    }
    $labelSvg = $label !== '' ? '<text x="40" y="58" font-family="Segoe UI, Arial, sans-serif" font-size="15" letter-spacing="3" fill="' . $c['accent'] . '">' . $x(strtoupper($label)) . '</text>' : '';

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 600" width="400" height="600" role="img" aria-label="' . $x($title . ' by ' . $author) . '">'
        . '<rect width="400" height="600" fill="' . $c['bg'] . '"/>'
        . $art
        . '<rect x="0" y="0" width="14" height="600" fill="#000" opacity=".22"/>'
        . $labelSvg
        . '<text x="40" y="' . (110 + $size) . '" font-family="Georgia, \'Palatino Linotype\', serif" font-size="' . $size . '" font-weight="700" fill="' . $c['ink'] . '">' . $tspans . '</text>'
        . '<text x="40" y="' . (130 + $size + count($lines) * round($size * 1.08)) . '" font-family="Segoe UI, Arial, sans-serif" font-size="20" fill="' . $c['ink'] . '" opacity=".85">' . $x($author) . '</text>'
        . '</svg>';
}

// Builds a top down plan of a study room with one chair per seat and its equipment.
function room_svg(string $name, int $capacity, string $features, int $seed): string
{
    $c = cover_palette($seed + 3);
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $tableW = 60 + $capacity * 28;
    $tx = (int) (240 - $tableW / 2);
    $chairs = '';
    $perSide = (int) ceil($capacity / 2);
    for ($i = 0; $i < $capacity; $i++) {
        $side = $i < $perSide ? 0 : 1;
        $n = $side ? $capacity - $perSide : $perSide;
        $k = $side ? $i - $perSide : $i;
        $cx = $tx + ($k + 1) * $tableW / ($n + 1);
        $cy = $side ? 205 : 95;
        $chairs .= '<rect x="' . round($cx - 16) . '" y="' . ($cy - 14) . '" width="32" height="28" rx="8" fill="' . $c['accent'] . '"/>';
    }
    $extras = '';
    if (str_contains($features, 'whiteboard')) {
        $extras .= '<rect x="40" y="36" width="130" height="8" rx="4" fill="#F6F0E4"/>';
    }
    if (str_contains($features, 'screen')) {
        $extras .= '<rect x="424" y="110" width="10" height="80" rx="3" fill="#7DB7FF"/>';
    }
    if (str_contains($features, 'power')) {
        $extras .= '<circle cx="240" cy="150" r="7" fill="' . $c['bg'] . '" stroke="#F6F0E4" stroke-width="2"/>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 300" width="480" height="300" role="img" aria-label="' . $x('Floor plan of ' . $name . ', ' . $capacity . ' seats') . '">'
        . '<rect width="480" height="300" rx="18" fill="' . $c['bg'] . '"/>'
        . '<rect x="22" y="22" width="436" height="256" rx="10" fill="none" stroke="' . $c['bg2'] . '" stroke-width="6"/>'
        . '<rect x="330" y="270" width="80" height="10" fill="' . $c['bg'] . '"/>'
        . $extras . $chairs
        . '<rect x="' . $tx . '" y="115" width="' . $tableW . '" height="70" rx="12" fill="' . $c['bg2'] . '" stroke="#F6F0E4" stroke-opacity=".35"/>'
        . '<text x="44" y="258" font-family="Georgia, serif" font-size="30" font-weight="700" fill="#F6F0E4">' . $x($name) . '</text>'
        . '</svg>';
}

// Writes a generated cover for a book to assets/covers and returns its web path.
function write_cover(string $serial, string $title, string $author, int $seed, string $label = ''): string
{
    $file = 'covers/' . strtolower($serial) . '.svg';
    file_put_contents(APP_ROOT . '/assets/' . $file, cover_svg($title, $author, $seed, $label));
    return $file;
}
