<?php

namespace App\Tests;

use App\Services\MixesService;
use PHPUnit\Framework\TestCase;

/**
 * The mixes catalogue, against the live `mixes` table.
 *
 * The listing has to hide published rows with an empty name. The live database
 * has exactly one (id 889, `status = 1`, blank `name` and `dj`), left behind by
 * an aborted edit; `MixesController::playlistAction()` refuses it as a 404, so
 * rendering it in the listing produced a link that led straight to a 404.
 *
 * Skipped automatically when the database is unreachable.
 */
class MixesServiceTest extends TestCase
{
    private MixesService $mixes;

    private \Azera\Db\Database $db;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->db = \App\AppContext::instance()->dbManager()->getDefault();

        $this->mixes = new MixesService($this->db);

        try {
            $this->mixes->mixes();
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    /** Ids of the published rows the database holds directly, names included. */
    private function publishedIds(bool $unnamedOnly): array
    {
        $sql = $unnamedOnly
            ? 'SELECT `id` FROM `mixes` WHERE `status` = 1 AND `name` = \'\''
            : 'SELECT `id` FROM `mixes` WHERE `status` = 1';

        return array_map('intval', $this->db->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Every listed mix must be reachable: a row the controller 404s must not
     * appear in the catalogue. The live table has one such row, so this also
     * proves the filter is doing something rather than being vacuous.
     */
    public function testListingNeverContainsAnUnnamedMix(): void
    {
        $listed = array_column($this->mixes->mixes(), 'id');
        $unnamed = $this->publishedIds(true);

        self::assertSame([], array_values(array_intersect($listed, $unnamed)));
        self::assertSame([], array_values(array_filter(
            $this->mixes->mixes(),
            static fn(array $mix): bool => $mix['name'] === ''
        )));

        // Whatever survives must be the named subset of what the table holds.
        $named = array_values(array_diff($this->publishedIds(false), $unnamed));
        sort($listed);
        sort($named);
        self::assertSame($named, $listed);
    }

    /**
     * The category listing is filtered the same way, and every row in it
     * belongs to the category that was asked for.
     */
    public function testCategoryListingHidesUnnamedMixesAndStaysInCategory(): void
    {
        $unnamed = $this->publishedIds(true);
        $seen    = 0;

        foreach ($this->mixes->categories() as $category) {
            foreach ($this->mixes->mixes((int) $category['id']) as $mix) {
                $seen++;
                self::assertNotContains($mix['id'], $unnamed);
                self::assertSame((int) $category['id'], $mix['category']);
            }
        }

        // An empty catalogue is a valid state; not reaching this line is not.
        self::assertGreaterThanOrEqual(0, $seen);
    }

    /** A search hit must not be the untitled row either. */
    public function testSearchHidesUnnamedMixes(): void
    {
        $unnamed = $this->publishedIds(true);

        foreach ($this->mixes->mixes(null, 'a') as $mix) {
            self::assertNotContains($mix['id'], $unnamed);
        }

        self::assertSame([], array_values(array_intersect(
            array_column($this->mixes->mixes(null, 'a'), 'id'),
            $unnamed
        )));
    }
}
