<?php

namespace App\Services;

use Azera\Db\Database;

/**
 * The WebTV ticker in the left camera frame.
 *
 * ## How the old pipeline worked
 *
 * The admin module (`legacy/archive/cgi-bin/include/admin/ticker.pl`) stored a row per page in
 * the `ticker` table. Every time an entry was saved, killed or re-ordered it
 * called `write_ticker()`, which rewrote the *whole* sequence as static files:
 *
 *     public/webtv/ticker/content.htm     <- page 0
 *     public/webtv/ticker/content1.htm    <- page 1
 *     public/webtv/ticker/content2.htm    <- page 2
 *     ...
 *
 * Each file carried `<meta http-equiv="refresh" content="<delay>; URL=content<n+1>.htm">`
 * pointing at the next, and the last one pointed back at `content.htm`. The
 * frame `lcam` loads `/webtv/ticker/`, whose frameset puts `content.htm` in the
 * middle. So "create a page in the admin panel" did appear on the site - but
 * only because a Perl subroutine rewrote files on disk, and only if the web
 * server user could write into the document root.
 *
 * ## What this does instead
 *
 * Renders the sequence straight from the `ticker` table on each refresh, using
 * the same file names. Nothing is written to disk, so:
 *
 *   - a page saved in the admin is live on the next refresh, with no publish
 *     step and no write permission on the document root;
 *   - the committed `content*.htm` files (generated artifacts, last written in
 *     2020) are no longer the source of truth and can be deleted;
 *   - the delay comes from the row, not from a value baked into a file.
 *
 * The markup language is `ANT_TO_HTML` from `ssms/system.pl`, ported verbatim:
 * `[a=url]text[/a]`, `[a]url[/a]`, `[b]`, `[i]`, `[u]`.
 */
final class TickerService
{
    private const STATUS_RUNNING = 1;

    /** Only normal-text pages are supported; Flash is retired. */
    private const TYPE_TEXT = 'n';

    /** Uploaded artwork lives here, relative to the legacy document root. */
    public const IMAGE_URL = '/webtv/ticker/images/';

    public function __construct(private Database $db)
    {
    }

    /**
     * Every page that is currently running, in display order.
     *
     * @return list<array<string, mixed>>
     */
    public function pages(): array
    {
        $statement = $this->db->query(
            'SELECT * FROM `hbr1`.`ticker`
              WHERE `status` = :status
              ORDER BY `position`, `id`',
            ['status' => self::STATUS_RUNNING]
        );

        $rows = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->normalise($row);
        }

        return $rows;
    }

    /**
     * Page `$index` of the running sequence, already linked to its successor.
     *
     * Returns null when the sequence is empty or the index is out of range, so
     * the caller can answer 404 rather than replay page 0 forever.
     *
     * @return array<string, mixed>|null
     */
    public function page(int $index): ?array
    {
        $pages = $this->pages();

        if ($pages === [] || $index < 0 || $index >= count($pages)) {
            return null;
        }

        return $pages[$index] + ['next' => self::fileName(($index + 1) % count($pages))];
    }

    /**
     * The legacy file name for a page index.
     *
     * `content.htm`, `content1.htm`, `content2.htm`, … - page 0 has no number.
     * Kept so the routes stay byte-identical to the URLs the meta-refresh chain
     * and any bookmark already use.
     */
    public static function fileName(int $index): string
    {
        return 'content' . ($index === 0 ? '' : $index) . '.htm';
    }

    /**
     * Parse a legacy file name back into a page index, or null.
     */
    public static function indexFromFileName(string $file): ?int
    {
        if ($file === 'content.htm') {
            return 0;
        }

        if (preg_match('/^content(\d{1,4})\.htm$/', $file, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * `ssms::system::to_html()` - the ticker's little bracket markup.
     *
     * Applied to both the title and the content, exactly as `<ANT_TO_HTML>` was.
     * The input is admin-authored and was always trusted as HTML, so this does
     * not escape it; the bracket forms only add links and emphasis.
     */
    public function markup(string $text): string
    {
        $text = (string) preg_replace(
            '!\[a=(.*?)\](.*?)\[/a\]!s',
            '<a href="$1" target="_blank">$2</a>',
            $text
        );

        $text = (string) preg_replace(
            '!\[a\](.*?)\[/a\]!s',
            '<a href="$1" target="_blank">$1</a>',
            $text
        );

        return str_replace(
            ['[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]'],
            ['<b>', '</b>', '<i>', '</i>', '<u>', '</u>'],
            $text
        );
    }

    /**
     * Flatten a database row into the shape the view wants.
     *
     * Colours arrive as bare hex (`100040`, no `#`), so they are passed through
     * as-is and the template adds the `#`. Timestamps are unix seconds.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalise(array $row): array
    {
        $image = (string) ($row['image'] ?? '');

        return [
            'id'        => (int) $row['id'],
            'title'     => $this->markup((string) ($row['title'] ?? '')),
            'content'   => $this->markup((string) ($row['content'] ?? '')),
            'showTitle' => (int) ($row['show_title'] ?? 0) === 1,
            'showImage' => (int) ($row['show_image'] ?? 0) === 1 && $image !== '',
            'imageUrl'  => self::IMAGE_URL . rawurlencode($image),
            'bgColor'   => self::hex((string) ($row['bg_color'] ?? ''), '100040'),
            'titleColor' => self::hex((string) ($row['title_color'] ?? ''), 'FFEEDD'),
            'contentColor' => self::hex((string) ($row['content_color'] ?? ''), 'FFEEDD'),
            'anchorColor' => self::hex((string) ($row['anchor_color'] ?? ''), 'CCFF00'),
            'titleFont' => self::css((string) ($row['title_font_family'] ?? ''), 'Verdana, Arial, mono'),
            'titleSize' => self::css((string) ($row['title_font_size'] ?? ''), '10px'),
            'contentFont' => self::css((string) ($row['content_font_family'] ?? ''), 'Verdana, Arial, mono'),
            'contentSize' => self::css((string) ($row['content_font_size'] ?? ''), '10px'),
            'borderHorizontal' => max(0, (int) ($row['border_horizontal'] ?? 0)),
            'borderVertical'   => max(0, (int) ($row['border_vertical'] ?? 0)),
            // A zero or absent delay would make the browser refresh in a tight
            // loop; the legacy column default is 15.
            'delay' => max(1, (int) ($row['delay'] ?? 0) ?: 15),
            'isFlash' => (string) ($row['object_type'] ?? self::TYPE_TEXT) !== self::TYPE_TEXT,
        ];
    }

    /**
     * A six-digit hex colour, or the fallback.
     *
     * These values are interpolated into a `bgcolor` attribute, so anything that
     * is not plain hex is discarded rather than escaped.
     */
    private static function hex(string $value, string $fallback): string
    {
        return preg_match('/^[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * A CSS font family / size from the column, restricted to a safe alphabet.
     *
     * Same reasoning as `hex()`: the value lands inside a `<style>` block, so it
     * must not be able to close the rule or inject a `</style>`.
     */
    private static function css(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^[A-Za-z0-9 ,.-]+$/', $value) !== 1) {
            return $fallback;
        }

        return $value;
    }
}
