<?php

namespace App\Tests;

use App\AppContext;
use App\Services\GalleryService;
use App\Support\LegacyMarkup;
use PHPUnit\Framework\TestCase;

/**
 * The gallery pages against production.
 *
 * Production is the authority, not the repository's legacy templates: the
 * deployed page says "GALLERY ENTRANCE" while `htdocs/cgi-htm/client/
 * galleries.htm` says "PHOTO GALLERIES". The captures under `tests/fixtures/
 * legacy/` and `temp/prod-gallery*.html` are the record of what the old pages
 * actually served.
 *
 * These are integration tests by design. The gallery schema is the part most
 * likely to be misread during a port, so the expected ids and titles come from
 * the live database rather than from a fixture that could drift.
 *
 * Skipped automatically when the database is unreachable.
 */
class GalleryPageTest extends TestCase
{
    private GalleryService $gallery;
    private array $category;
    private array $galleryRow;
    private array $picture;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->gallery = new GalleryService();

        try {
            $categories = $this->gallery->categories();
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        self::assertNotEmpty($categories, 'the local database must hold public categories');

        $this->category = $categories[0];

        $galleries = $this->gallery->galleriesFor($this->category['id']);

        self::assertNotEmpty($galleries, 'the first category must hold galleries');

        $this->galleryRow = $galleries[0];

        $pictures = $this->gallery->picturesFor($this->galleryRow['id']);

        self::assertNotEmpty($pictures, 'the first gallery must hold pictures');

        $this->picture = $pictures[0];
    }

    public function testEntranceUsesTheLegacyHeadingAndIntro(): void
    {
        $body = $this->page('/gallery');

        self::assertStringContainsString('<title>Galleries on HBR1</title>', $body);
        self::assertStringContainsString('<b>GALLERY ENTRANCE</b>', $body);
        self::assertStringContainsString('Welcome to the progressive gallery on futurenet', $body);
        self::assertStringContainsString('Select one of the styles below', $body);
        self::assertStringContainsString('background="/images/body_bg2.gif"', $body, 'the gallery well background');
    }

    public function testEntranceRendersTheLegacyFramedCategoryTiles(): void
    {
        $body       = $this->page('/gallery');
        $categories = $this->gallery->categories();
        $withThumb  = array_filter($categories, static fn(array $c): bool => $c['thumb'] !== null);

        self::assertSame(count($categories), substr_count($body, 'gallery-frame-category'));

        foreach ($categories as $category) {
            self::assertStringContainsString('href="/gallery/' . $category['id'] . '"', $body);
        }

        self::assertSame(
            count($withThumb),
            substr_count($body, 'width="110" height="82"'),
            'every category thumbnail is the legacy 110x82 size'
        );
        self::assertSame(count($categories), substr_count($body, 'class="copyright gallery-credit"'));
    }

    public function testCategoryPageUsesTheLegacyHeadingBandsAndTruncatedTitles(): void
    {
        $body       = $this->page('/gallery/' . $this->category['id']);
        $galleries  = $this->gallery->galleriesFor($this->category['id']);

        self::assertStringContainsString('<b>GALLERIES ON HBR1</b>', $body);
        self::assertStringContainsString('Browse through the galleries', $body);
        self::assertGreaterThanOrEqual(2, substr_count($body, '>ENTRANCE<'), 'the band appears above and below');
        self::assertStringContainsString('href="/gallery"', $body);
        self::assertSame(count($galleries), substr_count($body, 'gallery-frame-gallery'));
        self::assertStringContainsString('/images/icons/edit_small_dark.gif', $body);

        $expected = LegacyMarkup::cut($this->galleryRow['title'], 20, '..');
        self::assertStringContainsString($expected, $body);
    }

    public function testCategoryPagerUsesTheLegacyIconPager(): void
    {
        // The first category has two galleries, so production shows neither
        // arrow - only the edit icon. The point of the check is that the
        // pager is the icon pager, not the port's old text pager.
        $body = $this->page('/gallery/' . $this->category['id']);

        self::assertStringContainsString('/images/icons/edit_small_dark.gif', $body);
        self::assertStringContainsString('gallery-pager-category', $body);
        self::assertStringNotContainsString('next &raquo;', $body);
        self::assertStringNotContainsString('prev &laquo;', $body);
    }

    public function testGalleryPageUsesTheLegacyHeadingBandsAndTiles(): void
    {
        $body     = $this->page('/gallery/' . $this->category['id'] . '/' . $this->galleryRow['id']);
        $pictures = $this->gallery->picturesFor($this->galleryRow['id']);

        self::assertStringContainsString('<b>GALLERY MAIN</b>', $body);
        self::assertStringContainsString('id="gallery"', $body);
        self::assertGreaterThanOrEqual(2, substr_count($body, '>LEVEL_UP<'), 'the band appears above and below');
        self::assertStringContainsString('href="/gallery/' . $this->category['id'] . '"', $body);
        self::assertSame(count($pictures), substr_count($body, 'gallery-frame-photo'));
        self::assertStringContainsString('/images/icons/edit_small_dark.gif', $body);

        if ($this->gallery->countPicturesFor($this->galleryRow['id']) > GalleryService::PICTURES_PER_PAGE) {
            self::assertStringContainsString('/images/icons/arrow_right_small.gif', $body);
        }
    }

    public function testPicturePageUsesTheLegacyFramingAndMediumArrows(): void
    {
        $body    = $this->page('/gallery/pic/' . $this->picture['id']);
        $picture = $this->gallery->picture($this->picture['id']);

        self::assertStringContainsString('<b>GALLERY PICTURE</b>', $body);
        self::assertStringContainsString('/images/gallery/layout/border_top.gif', $body);
        self::assertStringContainsString('/images/gallery/layout/border_bottom.gif', $body);
        self::assertStringContainsString('/images/gallery/layout/border_left.gif', $body);
        self::assertStringContainsString('/images/gallery/layout/border_right.gif', $body);
        self::assertStringContainsString('width="400" height="300"', $body);
        self::assertStringContainsString(
            'href="/gallery/' . $picture['category'] . '/' . $picture['gallery'] . '"',
            $body,
            'LEVEL_UP returns to the gallery'
        );

        if ($picture['previous'] !== null) {
            self::assertStringContainsString('href="/gallery/pic/' . $picture['previous'] . '"', $body);
        }

        if ($picture['next'] !== null) {
            self::assertStringContainsString('href="/gallery/pic/' . $picture['next'] . '"', $body);
            self::assertStringContainsString('/images/icons/arrow_right_medium.gif', $body);
        }
    }

    public function testGalleryPagesAreWellFormedHtml(): void
    {
        $pages = [
            '/gallery' => $this->page('/gallery'),
            '/gallery/' . $this->category['id'] => $this->page('/gallery/' . $this->category['id']),
            '/gallery/' . $this->category['id'] . '/' . $this->galleryRow['id']
                => $this->page('/gallery/' . $this->category['id'] . '/' . $this->galleryRow['id']),
            '/gallery/pic/' . $this->picture['id'] => $this->page('/gallery/pic/' . $this->picture['id']),
        ];

        foreach ($pages as $label => $html) {
            // The layout carries JavaScript that contains HTML strings; libxml
            // parses script bodies, so strip the executable blocks first. What
            // remains is the document structure the browser actually builds.
            $html = preg_replace('!<script\b[^>]*>.*?</script>!is', '', $html) ?? $html;
            $html = preg_replace('!<style\b[^>]*>.*?</style>!is', '', $html) ?? $html;

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $document = new \DOMDocument();
            $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

            $errors = array_map(
                static fn(\LibXMLError $error): string => trim($error->message) . ' (line ' . $error->line . ')',
                libxml_get_errors()
            );

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            self::assertSame([], $errors, $label . " must be well-formed:\n" . implode("\n", $errors));
        }
    }

    public function testGalleryViewsUseNoObsoletePresentationalMarkup(): void
    {
        $views = glob(dirname(__DIR__) . '/views/web/gallery*.clarity.html');

        self::assertNotEmpty($views);

        $banned = ['<font', ' bgcolor=', ' align=', ' background=', ' cellspacing=', ' cellpadding=', ' border='];

        foreach ($views as $view) {
            // Twig comments explain the rule and never reach the browser, so
            // they are not part of the markup under test.
            $source = (string) preg_replace('!\{#.*?#\}!s', '', (string) file_get_contents($view));
            $source = strtolower($source);

            foreach ($banned as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($view) . ' must not emit obsolete presentational markup: ' . $needle
                );
            }
        }
    }

    public function testGalleryLinksAreCanonicalAndInternal(): void
    {
        $pages = [
            $this->page('/gallery'),
            $this->page('/gallery/' . $this->category['id']),
            $this->page('/gallery/' . $this->category['id'] . '/' . $this->galleryRow['id']),
            $this->page('/gallery/pic/' . $this->picture['id']),
        ];

        foreach ($pages as $body) {
            self::assertStringNotContainsString('main.php', $body);
            self::assertStringNotContainsString('cgi-bin', $body);
            self::assertStringNotContainsString('show=cat', $body);
        }
    }

    public function testGalleryStylesKeepTheLegacyColoursAndFrameAssets(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/htdocs/style/client.css');

        foreach ([
            'category_select_border.gif',
            'gallery_select_border.gif',
            'photo_select_border.gif',
            '#99FF00',
            '#6699FF',
            '#88CCAA',
            '#99DDFF',
            '#88EE00',
        ] as $expected) {
            self::assertStringContainsString($expected, $css);
        }
    }

    /** Render a public gallery route through the real dispatcher. */
    private function page(string $path): string
    {
        $context = AppContext::instance();
        $route   = $context->router()->match($path, 'GET');

        self::assertNotNull($route, "no route matches {$path}");

        return $context->dispatcher()->dispatch($route)->getBody();
    }
}
