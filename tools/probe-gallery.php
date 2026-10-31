<?php

/** Probe GalleryService against the live database. Read-only. */

require __DIR__ . '/../env.php';
require __DIR__ . '/../vendor/autoload.php';

App\Bootstrap::bootstrap();

$gallery = new App\Services\GalleryService();

echo "=== categories ===\n";
$categories = $gallery->categories();
printf("  %d public categories\n", count($categories));
foreach (array_slice($categories, 0, 4) as $category) {
    printf("    #%-3d %-28s thumb=%s\n", $category['id'], $category['name'], $category['thumb']['file'] ?? '-');
}

if ($categories === []) {
    exit(0);
}

$categoryId = $categories[0]['id'];

echo "\n=== galleries in category {$categoryId} ===\n";
$galleries = $gallery->galleriesFor($categoryId);
printf("  %d of %d\n", count($galleries), $gallery->countGalleriesFor($categoryId));
foreach (array_slice($galleries, 0, 4) as $item) {
    printf("    #%-3d %-30s pics=%-4d thumb=%s\n", $item['id'], $item['title'], $item['count'], $item['thumb']['file'] ?? '-');
}

if ($galleries === []) {
    exit(0);
}

$galleryId = $galleries[0]['id'];

echo "\n=== gallery {$galleryId} ===\n";
$meta = $gallery->gallery($galleryId);
printf("  title=%s locked=%s\n", $meta['title'], $meta['locked'] ? 'yes' : 'no');

$pictures = $gallery->picturesFor($galleryId);
printf("  %d pictures on page 1 of %d\n", count($pictures), $gallery->countPicturesFor($galleryId));

if ($pictures === []) {
    exit(0);
}

$pictureId = $pictures[0]['id'];
printf("  first picture: #%d %s\n", $pictureId, $pictures[0]['file']);

echo "\n=== picture {$pictureId} ===\n";
$picture = $gallery->picture($pictureId);
printf("  file=%s\n  prev=%s next=%s\n", $picture['file'], var_export($picture['previous'], true), var_export($picture['next'], true));
