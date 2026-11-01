<?php

namespace App\Services;

/**
 * Display data for the station panels on /listen and /playlist.
 *
 * Both legacy pages (`template/listen.phtml`, `template/playlist.phtml`) built
 * the same block per station: a gradient header with the title and genre, a
 * "Running: [-hh:mm:ss] artist - title" line, a "Tune In:" row of playlist
 * links, and - on the playlist page - an eight-line ordered list.
 *
 * That shaping used to live in `cgi-bin/include/station.php`
 * (`setup_stations()`), which mixed three concerns: reading the playout
 * database, computing the remaining time, and looking up a matching mix. It is
 * split here so the arithmetic is testable without a database:
 *
 *   - {@see remaining()}      start/duration/now -> countdown text
 *   - {@see caption()}        artist/title -> display caption
 *   - {@see fit()}            the legacy `fit_string()` truncation
 *   - {@see duration()}       the legacy `format_duration()`
 *
 * The legacy pages printed a *negative* countdown ("[-00:13:18]") because they
 * computed `end - now` where `end` was already in the past for a long DJ set.
 * That is kept: it reads as "how far past its stated length this set is", which
 * is exactly what the operator wants to see on a continuous mix.
 */
class StationViewService
{
    /**
     * How long after the stated end a track is still shown as "Running".
     *
     * `station.php` had two thresholds: more than 600 s late swapped the label
     * to "Last Played", while a negative track duration was tolerated up to
     * four hours. Only the first is a display decision, so it lives here.
     */
    public const OVERRUN_TOLERANCE = 600;

    /**
     * `PageID` per route, as the legacy templates set it.
     *
     * The bottom navigation frame reads its sibling's `PageID` to decide which
     * button to light (`navigation.htm` + `updatePage()`). The ported pages had
     * dropped it, so the wrong button stayed lit. Values are the ones the
     * legacy templates used.
     *
     * `mixes` and `tracklist` are the same page now and light both buttons:
     * "Tracklist" (`ImageDef` 15) and "Inventory" (`ImageDef` 6). The catalogue
     * sets `PageIDs` to carry both; this map is the single-button fallback.
     */
    public const PAGE_IDS = [
        'listen'    => 'station',
        'playlist'  => 'playlist',
        'tracklist' => 'inventory',
        'gallery'   => 'gallery',
        'mixes'     => 'tracklist',
        'party'     => 'party',
        'guestbook' => 'guestbook',
        'chat'      => 'chat',
        'request'   => 'inventory',
    ];

    public function pageId(string $route): string
    {
        return self::PAGE_IDS[$route] ?? $route;
    }

    /**
     * Move one station to the front of the list, preserving the rest.
     *
     * `/listen/{station}` and `/playlist/{station}` render all three panels;
     * the requested one leads so an old link lands somewhere meaningful.
     *
     * @param array<int, array> $all
     * @return array<int, array>
     */
    public static function promote(array $all, string $slug): array
    {
        usort(
            $all,
            static fn(array $a, array $b): int
                => ((int) ($b['slug'] === $slug)) <=> ((int) ($a['slug'] === $slug))
        );

        return $all;
    }

    /**
     * The countdown for a track that started at $startedAt and runs $seconds.
     *
     * @param string|null $startedAt `date_played`, a UTC datetime string
     * @param float       $seconds   duration as the playout system reports it
     * @param int|null    $now       unix timestamp; null means "now"
     *
     * @return array{left: int, text: string, late: bool, error: bool}
     *   `left` is seconds remaining (negative once the track overruns),
     *   `text` is the legacy `[-hh:mm:ss]` string, `late` is true once the
     *   overrun exceeds {@see OVERRUN_TOLERANCE}.
     */
    public function remaining(?string $startedAt, float $seconds, ?int $now = null): array
    {
        $now = $now ?? time();

        if ($startedAt === null || $startedAt === '') {
            return ['left' => 0, 'text' => '', 'late' => true, 'error' => true];
        }

        $start = strtotime($startedAt);

        if ($start === false) {
            return ['left' => 0, 'text' => '', 'late' => true, 'error' => true];
        }

        // A negative duration means the playout system did not know the length.
        if ($seconds < 0) {
            return ['left' => 0, 'text' => '', 'late' => false, 'error' => false];
        }

        $left = (int) ($start + $seconds) - $now;

        return [
            'left'  => $left,
            'text'  => $this->duration($left),
            'late'  => $left < -self::OVERRUN_TOLERANCE,
            'error' => false,
        ];
    }

    /**
     * The display caption, preferring "artist - title" and degrading gracefully.
     *
     * Legacy collapsed underscores because the SAM schema stored names that way;
     * RadioDJ does not, so this only trims. The literal `[no title]` fallback is
     * the legacy one.
     */
    public function caption(string $artist, string $title): string
    {
        $artist = trim($artist);
        $title  = trim($title);

        return match (true) {
            $artist !== '' && $title !== '' => $artist . ' - ' . $title,
            $artist !== ''                  => $artist,
            $title !== ''                   => $title,
            default                         => '[no title]',
        };
    }

    /**
     * `hh:mm:ss` with a leading minus for negative values.
     *
     * This is `format_duration()` from `cgi-bin/main.php` with one correction:
     * the original used `(int) ($sec / 3600)` and then subtracted, so a negative
     * input produced "-1:-1:-1". Splitting off the sign first keeps the legacy
     * rendering for positive values and makes negative ones legible.
     */
    public function duration(int $seconds): string
    {
        $sign    = $seconds < 0 ? '-' : '';
        $seconds = abs($seconds);

        return sprintf(
            '%s%02d:%02d:%02d',
            $sign,
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    /**
     * `fit_string()` from `cgi-bin/main.php`, with one deliberate difference.
     *
     * The legacy version measured in bytes, which was the same thing as
     * characters back when every string was cp1252. The port renders UTF-8, so
     * a byte cut can land inside a multi-byte sequence and emit an invalid
     * byte sequence - the caption then shows a replacement glyph. This counts
     * characters instead, so the window and the word-boundary rule keep the
     * legacy meaning and the result is always valid UTF-8.
     *
     * Truncates to $size characters, preferring to cut at the last space when
     * that does not lose more than 20% of the window, and appends $cut.
     */
    public function fit(string $string, int $size, string $cut = ' ...'): string
    {
        $len = mb_strlen($string, 'UTF-8');

        if ($string === '' || $size <= 0 || $size > $len) {
            return $string;
        }

        $diff  = (int) ($size * 0.2);
        $tmp   = mb_substr($string, 0, $size, 'UTF-8');
        $found = mb_strrpos($tmp, ' ', 0, 'UTF-8');

        if ($found !== false && $size - $found < $diff) {
            $tmp = mb_substr($tmp, 0, $found, 'UTF-8');
        }

        return $tmp . (mb_strlen($tmp, 'UTF-8') < $len ? $cut : '');
    }

    /**
     * The eight-line playlist block for one station.
     *
     * The legacy `get_playlist()` walked the station's full playlist with a
     * persistent offset and padded the tail with "play another mix" links. With
     * a station-scoped page there is one station per request, so the offset is
     * always zero - but the padding is kept, including the exact wording, so the
     * page still reads the same.
     *
     * `$lead` is the currently playing track, rendered as line 1 with the
     * `#current` highlight. The legacy page built that line by prepending the
     * `history` row, which is why production's line 1 repeats the "Running:"
     * text above it. That is not a bug to fix: line 1 is "now", the rest is
     * "soon", and on an overrunning DJ set "now" legitimately lasts an hour.
     *
     * @param array<int, array{artist: string, title: string, requested: bool}> $queue
     * @param array{artist: string, title: string}|null $lead
     *
     * @return array<int, array{text: string, requested: bool, link: bool, href: string}>
     */
    public function playlistLines(
        array $queue,
        int $lines = 8,
        string $stationSlug = '',
        ?array $lead = null,
    ): array {
        $out    = [];
        $target = $stationSlug === '' ? '/mixes' : '/mixes/' . $stationSlug;

        $items = $lead === null
            ? $queue
            : [['artist' => $lead['artist'], 'title' => $lead['title'], 'requested' => false], ...$queue];

        foreach (array_slice($items, 0, $lines) as $item) {
            $out[] = [
                'text'      => $this->caption($item['artist'] ?? '', $item['title'] ?? ''),
                'requested' => (bool) ($item['requested'] ?? false),
                'link'      => false,
                'href'      => $target,
            ];
        }

        for ($i = count($out); $i < $lines; $i++) {
            $out[] = [
                'text'      => '[play another mix]',
                'requested' => false,
                'link'      => true,
                'href'      => $target,
            ];
        }

        return $out;
    }

    /**
     * The "Tune In:" row, flattened into ordered segments for the template.
     *
     * The legacy `$Stations[...]['Stream']` array mixed links with literal
     * presentational markup (`<font color="FFFFAA">Tune In: </font>`, green
     * brackets, bare commas) and the template simply printed each entry. That
     * markup is rebuilt as segments so the view stays free of colour literals,
     * while the rendered row is character-for-character the same:
     *
     *   Tune In: OGG Vorbis [Low], HE-AAC v2 [Low], Html Player
     *
     * `OGG Vorbis (low)` in the config is paired with the `OGG Vorbis` that
     * precedes it and rendered as the bracketed `Low` variant.
     *
     * @param array<int, array{title: string, file: string}> $playlists
     * @param string $musesHref   the in-page player, already resolved to a route
     *
     * @return array<int, array{type: string, title?: string, href?: string, text?: string}>
     */
    public function streamSegments(array $playlists, string $musesHref): array
    {
        $segments = [];
        $pairs    = $this->pairPlaylists($playlists);

        foreach ($pairs as $index => $pair) {
            if ($index > 0) {
                $segments[] = ['type' => 'text', 'text' => ', '];
            }

            $segments[] = ['type' => 'link', 'title' => $pair['title'], 'href' => $pair['file']];

            if ($pair['low'] !== null) {
                $segments[] = ['type' => 'text', 'text' => ' '];
                $segments[] = ['type' => 'bracket', 'text' => '['];
                $segments[] = ['type' => 'link', 'title' => 'Low', 'href' => $pair['low']];
                $segments[] = ['type' => 'bracket', 'text' => ']'];
            }
        }

        if ($segments !== []) {
            $segments[] = ['type' => 'text', 'text' => ', '];
        }

        $segments[] = ['type' => 'link', 'title' => 'Html Player', 'href' => $musesHref];

        return $segments;
    }

    /**
     * Fold `X (low)` entries into the `X` entry they belong to.
     *
     * @param array<int, array{title: string, file: string}> $playlists
     *
     * @return array<int, array{title: string, file: string, low: ?string}>
     */
    private function pairPlaylists(array $playlists): array
    {
        $out = [];

        foreach ($playlists as $entry) {
            $title = (string) ($entry['title'] ?? '');
            $file  = (string) ($entry['file'] ?? '');

            if ($title === '' || $file === '') {
                continue;
            }

            if (preg_match('/^(.*?)\s*\(low\)$/i', $title, $m) === 1) {
                $base = $m[1];

                // Attach to the most recent entry with that base title.
                for ($i = count($out) - 1; $i >= 0; $i--) {
                    if ($out[$i]['title'] === $base && $out[$i]['low'] === null) {
                        $out[$i]['low'] = $file;
                        continue 2;
                    }
                }

                // No base to attach to: show it standalone rather than lose it.
                $out[] = ['title' => $title, 'file' => $file, 'low' => null];
                continue;
            }

            $out[] = ['title' => $title, 'file' => $file, 'low' => null];
        }

        return $out;
    }
}
