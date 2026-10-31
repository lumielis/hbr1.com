<?php

namespace App\Tests;

use App\Services\StationViewService;
use PHPUnit\Framework\TestCase;

/**
 * The display arithmetic behind the /listen and /playlist station panels.
 *
 * These pin behaviours that were previously implicit in
 * `cgi-bin/include/station.php` and `cgi-bin/main.php`, and which the first port
 * got wrong: a 10-second-stale countdown, a lost fractional second, an
 * `hh:mm:ss` that rendered "-1:-1:-1" for negative input, and a caption that
 * could be an empty string.
 */
class StationViewServiceTest extends TestCase
{
    private function service(): StationViewService
    {
        return new StationViewService();
    }

    // ---------------------------------------------------------------- remaining

    /** A track that started 10 minutes ago and runs 30 minutes. */
    public function testRemainingCountsDownToTheEnd(): void
    {
        $start = time() - 600;

        $out = $this->service()->remaining(date('Y-m-d H:i:s', $start), 1800.0);

        self::assertSame(1200, $out['left']);
        self::assertSame('00:20:00', $out['text']);
        self::assertFalse($out['late']);
        self::assertFalse($out['error']);
    }

    /**
     * The legacy pages showed a negative countdown for an overrunning DJ set -
     * production renders "[-01:03:36]" for a 72-minute mix. That is deliberate:
     * it reads as "how far past its stated length this is".
     */
    public function testOverrunIsNegativeAndNotYetLate(): void
    {
        $out = $this->service()->remaining(date('Y-m-d H:i:s', time() - 3600), 3000.0);

        self::assertSame(-600, $out['left']);
        self::assertSame('-00:10:00', $out['text']);
        self::assertFalse($out['late'], 'exactly at the tolerance is still "Running"');
    }

    public function testLateOnlyPastTheTolerance(): void
    {
        $out = $this->service()->remaining(
            date('Y-m-d H:i:s', time() - 3600),
            3000.0,
            time() + 60
        );

        self::assertTrue($out['late']);
    }

    /**
     * Fractional seconds matter: RadioDJ reports 4340.98361 for a 72-minute
     * set, and truncating to int lost a second per track. 0.9 s of remaining
     * time must still truncate to 0 whole seconds, not round up to 1.
     */
    public function testFractionalDurationIsHonoured(): void
    {
        $out = $this->service()->remaining(date('Y-m-d H:i:s', time()), 0.9);

        self::assertSame(0, $out['left']);
    }

    public function testMissingStartIsAnError(): void
    {
        $out = $this->service()->remaining(null, 100.0);

        self::assertTrue($out['error']);
        self::assertSame('', $out['text']);
    }

    public function testNegativeDurationIsNotLate(): void
    {
        $out = $this->service()->remaining(date('Y-m-d H:i:s', time() - 3600), -1.0);

        self::assertFalse($out['error']);
        self::assertFalse($out['late']);
    }

    public function testUnparseableStartIsAnError(): void
    {
        self::assertTrue($this->service()->remaining('not-a-date', 100.0)['error']);
    }

    // ----------------------------------------------------------------- duration

    public function testDurationPadsEveryPart(): void
    {
        self::assertSame('00:00:05', $this->service()->duration(5));
        self::assertSame('00:01:00', $this->service()->duration(60));
        self::assertSame('01:00:00', $this->service()->duration(3600));
        self::assertSame('02:48:10', $this->service()->duration(10090));
    }

    /**
     * The legacy `format_duration()` produced "-1:-1:-1" for negative input:
     * it cast `-90/3600` to int (-0) then subtracted. Splitting the sign first
     * keeps the positive rendering identical and makes negatives legible.
     */
    public function testNegativeDurationKeepsTheSignOnce(): void
    {
        self::assertSame('-00:01:30', $this->service()->duration(-90));
    }

    // ------------------------------------------------------------------ caption

    public function testCaptionPrefersArtistAndTitle(): void
    {
        self::assertSame('A - B', $this->service()->caption('A', 'B'));
    }

    public function testCaptionDegradesToWhicheverIsPresent(): void
    {
        self::assertSame('A', $this->service()->caption('A', ''));
        self::assertSame('B', $this->service()->caption('', 'B'));
        self::assertSame('[no title]', $this->service()->caption('', ''));
    }

    public function testCaptionTrims(): void
    {
        self::assertSame('A - B', $this->service()->caption('  A ', ' B  '));
    }

    // ---------------------------------------------------------------------- fit

    public function testFitLeavesShortStringsAlone(): void
    {
        self::assertSame('short', $this->service()->fit('short', 45));
    }

    public function testFitCutsAtAWordBoundary(): void
    {
        $text = 'Dschinn vs. Trippin Troll - Sensitive Poultice Extra Words Here';

        $out = $this->service()->fit($text, 45);

        self::assertStringEndsWith(' ...', $out);
        self::assertStringStartsNotWith(' ', $out);
        self::assertLessThanOrEqual(49, strlen($out));
    }

    public function testFitAppendsTheEllipsisOnlyWhenShortened(): void
    {
        self::assertStringEndsWith(' ...', $this->service()->fit(str_repeat('x', 50), 45));
        self::assertSame('abcdefghij', $this->service()->fit('abcdefghij', 10));
    }

    public function testFitHandlesAZeroWindow(): void
    {
        self::assertSame('abc', $this->service()->fit('abc', 0));
    }

    // ------------------------------------------------------------ playlistLines

    public function testPlaylistLinesPadsToEightWithLinks(): void
    {
        $lines = $this->service()->playlistLines(
            [['artist' => 'A', 'title' => 'T', 'requested' => false]],
            8,
            'trance'
        );

        self::assertCount(8, $lines);
        self::assertSame('A - T', $lines[0]['text']);
        self::assertFalse($lines[0]['link']);

        for ($i = 1; $i < 8; $i++) {
            self::assertSame('[play another mix]', $lines[$i]['text']);
            self::assertTrue($lines[$i]['link']);
            self::assertSame('/tracklist/trance', $lines[$i]['href']);
        }
    }

    public function testPlaylistLinesPrefersTheCurrentTrackAsLineOne(): void
    {
        $lines = $this->service()->playlistLines(
            [['artist' => 'Next', 'title' => 'Song', 'requested' => false]],
            3,
            'ambient',
            ['artist' => 'Now', 'title' => 'Playing']
        );

        self::assertSame('Now - Playing', $lines[0]['text']);
        self::assertSame('Next - Song', $lines[1]['text']);
        self::assertSame('[play another mix]', $lines[2]['text']);
    }

    public function testPlaylistLinesNeverExceedsTheRequestedLength(): void
    {
        $queue = array_fill(0, 20, ['artist' => 'A', 'title' => 'T', 'requested' => false]);

        self::assertCount(8, $this->service()->playlistLines($queue, 8, 'trance'));
    }

    public function testRequestedQueuedEntriesAreFlagged(): void
    {
        $lines = $this->service()->playlistLines(
            [['artist' => 'A', 'title' => 'T', 'requested' => true]],
            2,
            'trance'
        );

        self::assertTrue($lines[0]['requested']);
    }

    // ---------------------------------------------------------- streamSegments

    /**
     * The rendered row must read exactly like the legacy one:
     *   Tune In: OGG Vorbis [Low], HE-AAC v2 [Low], Html Player
     */
    public function testStreamSegmentsPairsLowVariants(): void
    {
        $segments = $this->service()->streamSegments([
            ['title' => 'OGG Vorbis', 'file' => '/playlist/trance.ogg.m3u'],
            ['title' => 'OGG Vorbis (low)', 'file' => '/playlist/trance-low.ogg.m3u'],
            ['title' => 'HE-AAC v2', 'file' => '/playlist/trance.aac.m3u'],
            ['title' => 'HE-AAC v2 (low)', 'file' => '/playlist/trance-low.aac.m3u'],
        ], '/muses/trance');

        $rendered = '';
        foreach ($segments as $seg) {
            $rendered .= match ($seg['type']) {
                'link'    => $seg['title'],
                'bracket' => $seg['text'],
                default   => $seg['text'],
            };
        }

        self::assertSame('OGG Vorbis [Low], HE-AAC v2 [Low], Html Player', $rendered);
    }

    public function testStreamSegmentsLinksPointAtThePlaylists(): void
    {
        $segments = $this->service()->streamSegments([
            ['title' => 'OGG Vorbis', 'file' => '/playlist/trance.ogg.m3u'],
            ['title' => 'OGG Vorbis (low)', 'file' => '/playlist/trance-low.ogg.m3u'],
        ], '/muses/trance');

        $links = array_values(array_filter($segments, static fn($s) => $s['type'] === 'link'));

        self::assertSame('/playlist/trance.ogg.m3u', $links[0]['href']);
        self::assertSame('/playlist/trance-low.ogg.m3u', $links[1]['href']);
        self::assertSame('/muses/trance', $links[2]['href']);
    }

    public function testStreamSegmentsAlwaysEndsWithTheHtmlPlayer(): void
    {
        $segments = $this->service()->streamSegments([], '/muses/tronic');

        self::assertCount(1, $segments);
        self::assertSame('Html Player', $segments[0]['title']);
        self::assertSame('/muses/tronic', $segments[0]['href']);
    }

    public function testStreamSegmentsKeepsAnUnpairedLow(): void
    {
        $segments = $this->service()->streamSegments([
            ['title' => 'Solo (low)', 'file' => '/playlist/solo-low.m3u'],
        ], '/muses/x');

        $links = array_values(array_filter($segments, static fn($s) => $s['type'] === 'link'));

        self::assertSame('Solo (low)', $links[0]['title']);
    }

    // ---------------------------------------------------------------- page ids

    /**
     * The bottom navigation frame lights the button matching the page's
     * PageID. These values come from the legacy templates.
     */
    public function testPageIdsMatchTheLegacyTemplates(): void
    {
        $service = $this->service();

        self::assertSame('station', $service->pageId('listen'));
        self::assertSame('playlist', $service->pageId('playlist'));
        self::assertSame('inventory', $service->pageId('tracklist'));
        self::assertSame('gallery', $service->pageId('gallery'));
        self::assertSame('chat', $service->pageId('chat'));
    }

    /**
     * `mixes.cgi` renders `client/mix_categories.htm`, `client/mixes.htm` and
     * `client/mix_details.htm`, none of which exists in the PHP tree - the
     * module is served entirely through the ssms templates. All three set
     * `PageID = 'tracklist'`, which is what lights the "Mixes" button:
     * `ImageDef` pairs 15 with "tracklist" and 11 with "playlist".
     */
    public function testMixesPagesLightTheMixesButton(): void
    {
        self::assertSame('tracklist', $this->service()->pageId('mixes'));
    }

    // ----------------------------------------------------------------- promote

    public function testPromoteMovesTheStationToTheFront(): void
    {
        $out = StationViewService::promote([
            ['slug' => 'trance'],
            ['slug' => 'tronic'],
            ['slug' => 'ambient'],
        ], 'ambient');

        self::assertSame('ambient', $out[0]['slug']);
        self::assertSame('trance', $out[1]['slug']);
        self::assertSame('tronic', $out[2]['slug']);
    }

    public function testPromoteLeavesTheListAloneForAnUnknownSlug(): void
    {
        $all = [['slug' => 'trance'], ['slug' => 'tronic']];

        self::assertSame($all, StationViewService::promote($all, 'nope'));
    }
}
