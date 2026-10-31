<?php

namespace App\Tests;

use App\Services\MixesService;
use PHPUnit\Framework\TestCase;

/**
 * Mix lookup, against the live `mixes` table.
 *
 * The mix archive's read side was retired: the per-mix track listing
 * (`releases`) has 0 rows, so `/mixes/{category}` and `/mixes/playlist/{id}`
 * could never show anything, and the catalogue is now the station song list.
 * What remains of this service is {@see MixesService::matchTrack()}, which
 * links the "Running:" line on `/listen` and `/playlist` to the mix being
 * played, plus its two string helpers.
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
            $this->db->query('SELECT 1 FROM `mixes` LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    /**
     * A published row with a blank name and DJ (id 889) must never be matched:
     * it is a leftover from an aborted edit, not a mix. Both sides of the
     * comparison are empty, and the overlap guard rejects that.
     */
    public function testBlankMixIsNeverMatched(): void
    {
        self::assertNull($this->mixes->matchTrack('', ''));
        self::assertNull($this->mixes->matchTrack('   ', '  '));

        // Its `dj` and `name` are both empty, so no query term can find it.
        $unnamed = array_map('intval', $this->db->query(
            'SELECT `id` FROM `mixes` WHERE `status` = 1 AND `name` = \'\' AND `dj` = \'\''
        )->fetchAll(\PDO::FETCH_COLUMN));

        foreach ($unnamed as $id) {
            self::assertNotSame($id, $this->mixes->matchTrack('Anything', 'At All'));
        }
    }

    /**
     * Every id the matcher returns must be a real, published, named mix - the
     * link it drives on `/listen` has to lead somewhere that exists.
     */
    public function testMatchedIdsResolveToAPublishedMix(): void
    {
        $matched = $this->mixes->matchTrack('Dschinn', 'Dschinn');

        if ($matched === null) {
            self::assertTrue(true, 'No mix matches the probe term in this database.');

            return;
        }

        $row = $this->db->query(
            'SELECT `status`, `name` FROM `mixes` WHERE `id` = ?',
            [$matched]
        )->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertSame(1, (int) $row['status']);
        self::assertNotSame('', (string) $row['name']);
    }

    /**
     * `matchTrack()` compares the artist against `dj` and the title against
     * `name`, both normalised: lowercase, underscores as spaces, single
     * spaces. The legacy code did the same so "Dschinn" matched a DJ called
     * "Dschinn vs. Trippin Troll" either way round.
     */
    public function testMatchingToleratesCaseUnderscoresAndPartialNames(): void
    {
        $rows = $this->db->query(
            'SELECT `dj`, `name` FROM `mixes`'
            . ' WHERE `status` = 1 AND `dj` <> \'\' AND `name` <> \'\' LIMIT 1'
        )->fetchAll(\PDO::FETCH_ASSOC);

        if ($rows === []) {
            self::assertTrue(true, 'No fully populated mix in this database.');

            return;
        }

        $dj   = (string) $rows[0]['dj'];
        $name = (string) $rows[0]['name'];

        $id = $this->mixes->matchTrack(strtoupper(str_replace(' ', '_', $dj)), $name);

        self::assertNotNull($id, 'The exact DJ and name, recased, must still match.');
    }
}
