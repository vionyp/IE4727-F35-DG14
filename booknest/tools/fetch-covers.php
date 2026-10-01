<?php
// Downloads the real cover image of every book that has an ISBN, from Open Library
// (covers.openlibrary.org), into assets/covers/real/, in two sizes: large (bnt-000101.jpg) for the
// book page and small (bnt-000101-m.jpg) for cards and thumbnails.
// Run once after importing the database:
//   C:\xampp\php\php.exe tools\fetch-covers.php
// The images are copyrighted by their publishers, so the folder is ignored by Git and each
// teammate downloads their own copy. Without them the site shows our generated covers instead.
declare(strict_types=1);

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';

$dir = APP_ROOT . '/assets/covers/real';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$done = 0;
$skipped = 0;
$failed = [];
foreach (db_all('SELECT serial_no, isbn, title FROM books WHERE isbn IS NOT NULL ORDER BY id') as $book) {
    $ok = true;
    $fetched = false;
    foreach (['L' => '.jpg', 'M' => '-m.jpg'] as $size => $suffix) {
        $file = $dir . '/' . strtolower($book['serial_no']) . $suffix;
        if (is_file($file)) {
            continue;
        }
        // "default=false" makes Open Library answer 404 instead of sending a blank placeholder.
        $image = @file_get_contents('https://covers.openlibrary.org/b/isbn/' . $book['isbn'] . '-' . $size . '.jpg?default=false');
        // Keep the file only if it really is a JPEG of a sensible size.
        if ($image !== false && strlen($image) > 1500 && str_starts_with($image, "\xFF\xD8")) {
            file_put_contents($file, $image);
            $fetched = true;
        } else {
            $ok = false;
        }
        usleep(250000); // be polite to the free service
    }
    if (!$ok) {
        $failed[] = $book['title'];
        echo 'MISSING ' . $book['title'] . "\n";
    } elseif ($fetched) {
        $done++;
        echo 'ok      ' . $book['title'] . "\n";
    } else {
        $skipped++;
    }
}

echo "\nDownloaded $done, already had $skipped, missing " . count($failed) . ".\n";
if ($failed) {
    echo "Books without a real cover keep their generated BookNest cover.\n";
}
