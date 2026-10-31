<?php

namespace App\Tests;

use App\Controllers\Web\MixesController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The legacy pager, `page_navigation()` in `cgi-bin/main.php`.
 *
 * The window is deliberately asymmetric, and the port must not "improve" it:
 * the current page sits at the left edge with seven pages after it, and the
 * window only slides back once it would overrun the last page. Production
 * therefore shows 1..15 on page 1 of 18 and 2..16 on page 9 - not seven pages
 * either side of centre.
 *
 * The numbers here are the ones production actually rendered, captured from
 * `main.php?module=tracklist&station=Trance&offset=N` with 357 mixes.
 */
class MixesPagerTest extends TestCase
{
    public static function productionWindows(): array
    {
        return [
            // offset, pages
            'page 1'  => [0,   1],
            'page 2'  => [20,  2],
            'page 4'  => [60,  4],
            'page 8'  => [140, 8],
            'page 9'  => [169, 9],
            'page 16' => [300, 16],
            'page 18' => [340, 18],
        ];
    }

    #[DataProvider('productionWindows')]
    public function testWindowMatchesTheLegacyPageNavigation(int $offset, int $current): void
    {
        [$actualCurrent, $start, $end] = $this->window($offset, 357);

        [$expectedCurrent, $expectedStart, $expectedEnd] = $this->expected($offset);

        self::assertSame($expectedCurrent, $actualCurrent, 'current page');
        self::assertSame($expectedStart, $start, 'window start');
        self::assertSame($expectedEnd, $end, 'window end');
    }

    public function testFirstPageHasNoPreviousAndLastPageHasNoNext(): void
    {
        $pager = $this->pager(0, 357);
        self::assertFalse($pager['hasPrev']);
        self::assertTrue($pager['hasNext']);
        self::assertFalse($pager['leadEllipsis'], 'page 1 is already in the window');
        self::assertTrue($pager['trailEllipsis'], 'the window stops at 15 of 18');

        $pager = $this->pager(340, 357);
        self::assertTrue($pager['hasPrev']);
        self::assertFalse($pager['hasNext']);
        self::assertTrue($pager['leadEllipsis']);
        self::assertFalse($pager['trailEllipsis']);
    }

    /**
     * A catalogue that fits on one page must not render a pager at all - the
     * legacy template guarded it with `$Record_Max > $Record_Count`.
     */
    public function testSinglePageCatalogueHasNoPager(): void
    {
        $pager = $this->pager(0, 5);

        self::assertSame(1, $pager['last']);
        self::assertFalse($pager['hasPrev']);
        self::assertFalse($pager['hasNext']);
    }

    /**
     * Every page in the window carries the offset that page starts at, so a
     * pager link cannot point at the wrong slice.
     */
    public function testEachPageCarriesItsOwnOffset(): void
    {
        $pager = $this->pager(169, 357);

        foreach ($pager['pages'] as $entry) {
            self::assertSame(
                ($entry['number'] - 1) * 20,
                $entry['offset'],
                'page ' . $entry['number']
            );
        }

        self::assertSame(9, $pager['current']);
        self::assertSame(169, $pager['offset']);
    }

    /** @return array{0:int,1:int,2:int} current, start, end */
    private function window(int $offset, int $total): array
    {
        $pager = $this->pager($offset, $total);

        return [$pager['current'], $pager['start'], $pager['end']];
    }

    /** The same expected values, written out longhand. */
    private function expected(int $offset): array
    {
        return match ($offset) {
            0       => [1, 1, 15],
            20      => [2, 1, 15],
            60      => [4, 1, 15],
            140     => [8, 1, 15],
            169     => [9, 2, 16],
            300     => [16, 4, 18],
            340     => [18, 4, 18],
            default => throw new \InvalidArgumentException('Unpinned offset ' . $offset),
        };
    }

    private function pager(int $offset, int $total): array
    {
        \App\Bootstrap::bootstrap();

        $controller = new MixesController();
        $method     = (new \ReflectionClass($controller))->getMethod('pager');
        $method->setAccessible(true);

        return $method->invoke($controller, [
            'rows'   => [],
            'total'  => $total,
            'offset' => $offset,
            'limit'  => 20,
        ]);
    }
}
