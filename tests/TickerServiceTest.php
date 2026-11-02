<?php

namespace App\Tests;

use App\Services\TickerService;
use PHPUnit\Framework\TestCase;

/**
 * The WebTV ticker, against the live `ticker` table.
 *
 * The old pipeline generated `content*.htm` files on every admin save and
 * chained them with meta-refresh. The port renders the same file names from the
 * database, so the two things worth pinning are the mapping between file name
 * and sequence index, and the fact that the chain is a closed ring: the last
 * running page must point back at the first, or the ticker stops.
 *
 * Skipped automatically when the database is unreachable.
 */
class TickerServiceTest extends TestCase
{
    private TickerService $ticker;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->ticker = new TickerService(
            \App\AppContext::instance()->dbManager()->getDefault()
        );

        try {
            $this->ticker->pages();
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    public function testFileNamesRoundTrip(): void
    {
        self::assertSame('content.htm', TickerService::fileName(0));
        self::assertSame('content1.htm', TickerService::fileName(1));
        self::assertSame('content11.htm', TickerService::fileName(11));

        foreach ([0, 1, 7, 11, 42] as $index) {
            self::assertSame(
                $index,
                TickerService::indexFromFileName(TickerService::fileName($index))
            );
        }
    }

    public function testUnknownFileNamesAreRejected(): void
    {
        foreach (['index.html', 'content.htm.bak', 'contentA.htm', '../content.htm', 'content.htm/'] as $name) {
            self::assertNull(TickerService::indexFromFileName($name), $name);
        }
    }

    /**
     * The generated files must never come back.
     *
     * The port renders `content*.htm` from the `ticker` table, and the page is
     * now a route rather than a file. That only holds while no file of the same
     * name exists in the document root: the web server serves a real file before
     * it consults the front controller, so a resurrected `content.htm` would
     * silently put stale text back in front of visitors - which is precisely the
     * bug the port removed, and the reason the nginx vhost used to need a
     * `try_files /dev/null` override for this one prefix.
     */
    public function testNoGeneratedTickerFileExistsInTheDocumentRoot(): void
    {
        $found = glob(BASE_DIR . '/public/webtv/ticker/content*.htm') ?: [];

        self::assertSame(
            [],
            $found,
            'Generated ticker pages must not exist on disk: '
            . implode(', ', $found)
        );
    }

    public function testOnlyRunningPagesAreListed(): void
    {
        $pages = $this->ticker->pages();

        self::assertNotEmpty($pages);

        foreach ($pages as $page) {
            self::assertGreaterThan(0, $page['id']);
            self::assertGreaterThanOrEqual(1, $page['delay'], 'a zero delay would reload in a tight loop');
        }
    }

    /** Position order, so the admin's re-ordering is what the ticker follows. */
    public function testPagesFollowPositionOrder(): void
    {
        $positions = array_column($this->ticker->pages(), 'id');

        // The same rows read straight from the table must be in the same order.
        $db = \App\AppContext::instance()->dbManager()->getDefault();
        $rows = $db
            ->query('SELECT `id` FROM `hbr1`.`ticker` WHERE `status` = 1 ORDER BY `position`, `id`')
            ->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(array_map('intval', $rows), $positions);
    }

    /**
     * The chain must close. A dangling "next" is how a ticker silently stops.
     */
    public function testTheRefreshChainIsAClosedRing(): void
    {
        $pages = $this->ticker->pages();
        $count = count($pages);

        for ($index = 0; $index < $count; $index++) {
            $page = $this->ticker->page($index);

            self::assertNotNull($page, "page {$index} should exist");
            self::assertSame(
                TickerService::fileName(($index + 1) % $count),
                $page['next'],
                "page {$index} should point at its successor"
            );
        }
    }

    public function testWalkTheRingAndComeBack(): void
    {
        $count = count($this->ticker->pages());
        $index = 0;
        $visited = [];

        for ($step = 0; $step < $count; $step++) {
            $page = $this->ticker->page($index);

            self::assertNotNull($page);
            $visited[] = $index;
            $index = TickerService::indexFromFileName($page['next']);
            self::assertNotNull($index, 'next must parse back to an index');
        }

        self::assertSame(0, $index, 'walking the whole ring must land back on page 0');
        self::assertSame(range(0, $count - 1), $visited, 'every page must be visited once');
    }

    public function testAnIndexPastTheEndIsNull(): void
    {
        self::assertNull($this->ticker->page(count($this->ticker->pages())));
        self::assertNull($this->ticker->page(-1));
    }

    /** The bracket markup from `ssms::system::to_html()`. */
    public function testBracketMarkupIsPorted(): void
    {
        self::assertSame(
            '<a href="http://x/" target="_blank">label</a>',
            $this->ticker->markup('[a=http://x/]label[/a]')
        );

        self::assertSame(
            '<a href="http://x/" target="_blank">http://x/</a>',
            $this->ticker->markup('[a]http://x/[/a]')
        );

        self::assertSame('<b>b</b> <i>i</i> <u>u</u>', $this->ticker->markup('[b]b[/b] [i]i[/i] [u]u[/u]'));

        // Tags the ticker never supported are left as plain text, exactly as
        // the legacy filter did.
        self::assertSame('[s]x[/s]', $this->ticker->markup('[s]x[/s]'));
    }

    /** Colours and font values are interpolated into attributes and CSS. */
    public function testHostileColoursAndFontsFallBack(): void
    {
        $page = $this->ticker->page(0);

        self::assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $page['bgColor']);
        self::assertMatchesRegularExpression('/^[0-9A-Fa-f]{6}$/', $page['anchorColor']);
        self::assertDoesNotMatchRegularExpression('/[<>"]/', $page['contentFont']);
    }
}
