<?php

namespace App\Services;

/**
 * The station catalogue for a mix.
 *
 * The legacy "Tracklist" button opened `mixes.cgi`, a DJ-set archive with a
 * per-mix track listing (`releases`) that nobody ever filled in: the live
 * database has **0 `releases` rows**, so the detail page was always empty, and
 * it was retired rather than ported. What stays behind is the only part that
 * ever held data and is still used - {@see matchTrack()}, which links the
 * "Running:" line on `/listen` and `/playlist` to the mix being played.
 *
 * `mixes`, `releases`, `labels` and `media_category` stay in the database
 * until W8 decides their fate; nothing public reads the categories any more.
 */
class MixesService
{
    public function __construct(private \Azera\Db\Database $db)
    {
    }

    /**
     * The id of a mix whose DJ and name match the playing track, if any.
     *
     * Port of `find_mix()` from `cgi-bin/include/station.php`. The legacy
     * implementation ran two `MATCH … AGAINST` queries and then compared
     * lowercased, underscore-stripped strings with `strpos` in both directions -
     * so "Dschinn" matched a DJ called "Dschinn vs. Trippin Troll" either way
     * round. It linked the "Running:" line to the mix's tracklist.
     *
     * Only the FULLTEXT indexes on `dj` and `name` exist (no others), so the
     * candidate set stays tiny.
     *
     * A `mixes` row with both `dj` and `name` blank (there is exactly one, id
     * 889, from an aborted edit) would match nothing under `overlaps()` - both
     * sides are empty, and the guard rejects that - which is the right answer:
     * it is not a real mix.
     */
    public function matchTrack(string $artist, string $title): ?int
    {
        $artist = trim($artist);
        $title  = trim($title);

        if ($artist === '' || $title === '') {
            return null;
        }

        $rows = $this->db->query(
            'SELECT `id`, `dj`, `name` FROM `mixes`'
            . ' WHERE MATCH(`dj`) AGAINST (?) OR MATCH(`name`) AGAINST (?)'
            . ' LIMIT 50',
            [$artist, $title]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $needleDj   = $this->normalise($artist);
        $needleName = $this->normalise($title);

        foreach ($rows as $row) {
            $dj   = $this->normalise((string) $row['dj']);
            $name = $this->normalise((string) $row['name']);

            if ($this->overlaps($needleDj, $dj) && $this->overlaps($needleName, $name)) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /** Lowercase, underscores to spaces, collapsed whitespace. */
    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', strtolower($value))));
    }

    /**
     * Either string containing the other, in either direction.
     *
     * The legacy code guarded each side against being empty, because an empty
     * needle matches everything under `strpos`. Emptiness is checked up front
     * in {@see matchTrack()} instead; `overlaps()` therefore expects non-empty
     * input and `normalise()` above already guarantees a string.
     */
    private function overlaps(string $a, string $b): bool
    {
        return $a !== '' && $b !== ''
            && (str_contains($a, $b) || str_contains($b, $a));
    }
}
