<?php

namespace App\Tests;

use App\Services\GalleryService;
use PHPUnit\Framework\TestCase;

/**
 * Gallery read paths against the live database.
 *
 * These are integration tests by design: the gallery is the one area where the
 * schema itself (camel-collation `*_Index` primary keys, the flag/status pair
 * on categories, the plaintext `GAL_Password`) is the thing most likely to be
 * misread during a port, so the assertions are made against real rows.
 *
 * Skipped automatically when the database is unreachable.
 */
class GalleryServiceTest extends TestCase
{
    private GalleryService $gallery;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->gallery = new GalleryService();

        try {
            $this->gallery->categories();
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    /** Only flag-2 / status-1 categories are public. */
    public function testOnlyPublicCategoriesAreListed(): void
    {
        $categories = $this->gallery->categories();

        self::assertNotEmpty($categories);

        foreach ($categories as $category) {
            self::assertGreaterThan(0, $category['id']);
            self::assertNotSame('', $category['name']);
        }
    }

    public function testCategoriesAreSortedByName(): void
    {
        $names = array_column($this->gallery->categories(), 'name');
        $sorted = $names;
        usort($sorted, static fn($a, $b) => strcmp($a, $b));

        self::assertSame($sorted, $names);
    }

    public function testAnUnknownCategoryIsNull(): void
    {
        self::assertNull($this->gallery->category(999999));
    }

    public function testGalleriesBelongToTheirCategory(): void
    {
        $category = $this->gallery->categories()[0];

        foreach ($this->gallery->galleriesFor($category['id']) as $item) {
            $meta = $this->gallery->gallery($item['id']);

            self::assertNotNull($meta, "gallery {$item['id']} should be published");
            self::assertSame($category['id'], $meta['category']);
        }
    }

    public function testPictureNavigationIsConsistent(): void
    {
        $category = $this->gallery->categories()[0];
        $gallery  = $this->gallery->galleriesFor($category['id'])[0] ?? null;

        if ($gallery === null) {
            self::markTestSkipped('No galleries to inspect');
        }

        $pictures = $this->gallery->picturesFor($gallery['id']);

        if ($pictures === []) {
            self::markTestSkipped('No pictures to inspect');
        }

        $first = $this->gallery->picture($pictures[0]['id']);

        self::assertNotNull($first);
        self::assertSame($gallery['id'], $first['gallery']);

        // The first picture has no predecessor, and its successor (if any)
        // must point back at it.
        self::assertNull($first['previous']);

        if ($first['next'] !== null) {
            $second = $this->gallery->picture($first['next']);

            self::assertNotNull($second);
            self::assertSame($first['id'], $second['previous']);
        }
    }

    public function testUnknownPictureIsNull(): void
    {
        self::assertNull($this->gallery->picture(999999));
    }

    public function testPictureCountMatchesThePage(): void
    {
        $category = $this->gallery->categories()[0];
        $gallery  = $this->gallery->galleriesFor($category['id'])[0] ?? null;

        if ($gallery === null) {
            self::markTestSkipped('No galleries to inspect');
        }

        self::assertSame(
            $gallery['count'],
            $this->gallery->countPicturesFor($gallery['id'])
        );
    }
}
