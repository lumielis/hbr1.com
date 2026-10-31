<?php

namespace App\Tests;

use App\Services\StationService;
use PHPUnit\Framework\TestCase;

class StationServiceTest extends TestCase
{
    private function service(): StationService
    {
        return new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'I.D.M. Tranceponder'],
            ['id' => 'Tronic', 'slug' => 'tronic', 'title' => 'Tronic Lounge'],
        ]);
    }

    public function testFindBySlugIsCaseInsensitive(): void
    {
        self::assertSame('Trance', $this->service()->find('trance')['id']);
        self::assertSame('Trance', $this->service()->find('TRANCE')['id']);
    }

    /** Legacy links and the config use the canonical Id, so both must work. */
    public function testFindByCanonicalId(): void
    {
        self::assertSame('tronic', $this->service()->find('Tronic')['slug']);
    }

    public function testUnknownStationReturnsNull(): void
    {
        self::assertNull($this->service()->find('rock'));
    }

    public function testFindOrFailThrowsForUnknownStation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->findOrFail('rock');
    }

    public function testAllReturnsEveryStation(): void
    {
        self::assertCount(2, $this->service()->all());
    }

    /**
     * The legacy `muses.php` looped over `$Stations` without resetting the
     * variable, so a request without `?station=` fell through to the *last*
     * configured station. Production serves Dream Factory (Ambient) from the
     * bare `/cgi-bin/main.php?module=muses` for exactly that reason.
     */
    public function testFallbackIsTheLastConfiguredStation(): void
    {
        self::assertSame('tronic', $this->service()->fallback()['slug']);
    }

    public function testFallbackThrowsWhenNothingIsConfigured(): void
    {
        $this->expectException(\RuntimeException::class);
        (new StationService([]))->fallback();
    }
}
