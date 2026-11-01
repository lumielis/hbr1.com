<?php

namespace App\Support;

/**
 * The two text helpers the gallery pages inherited from Perl.
 *
 * `cgi-bin/include/gallery.php` carried `bbc_to_html()` and `cut_string()`.
 * The credit lines under gallery thumbnails are stored with bracketed markup
 * (`[a=http://...]Photographer[/a]`), and gallery titles are cut to twenty
 * characters in the category listing.
 *
 * One deliberate difference: the legacy renderer wrote `href\="..."`, with
 * literal backslashes, so every credit link on the old pages was broken. This
 * port emits working anchors, as agreed — see `docs/PARITY.md`.
 */
final class LegacyMarkup
{
    /** Schemes a credit link may use. Anything else is rendered as plain text. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Render the legacy BBC subset as HTML.
     *
     * Tags other than the inline ones are stripped first: the source columns
     * hold entities intentionally, so `strip_tags()` (rather than escaping) is
     * what keeps `K&uuml;tral` intact while still refusing a `<script>`.
     */
    public static function bbcToHtml(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $input = strip_tags($input, '<b><i><u><br>');

        $input = (string) preg_replace_callback(
            '!\[a=([^\]]*)\](.*?)\[/a\]!is',
            static function (array $match): string {
                $label = $match[2];

                return self::safeUrl($match[1])
                    ? '<a href="' . self::attr($match[1]) . '" target="_blank" rel="noopener">' . $label . '</a>'
                    : $label;
            },
            $input
        );

        $input = (string) preg_replace_callback(
            '!\[a\]((?:https?|mailto):[^\[]*?)\[/a\]!is',
            static function (array $match): string {
                $url = $match[1];

                return self::safeUrl($url)
                    ? '<a href="' . self::attr($url) . '" target="_blank" rel="noopener">' . self::attr($url) . '</a>'
                    : self::attr($url);
            },
            $input
        );

        foreach (['b', 'i', 'u'] as $tag) {
            $input = (string) preg_replace(
                '!\[' . $tag . '\](.*?)\[/' . $tag . '\]!is',
                '<' . $tag . '>$1</' . $tag . '>',
                $input
            );
        }

        return $input;
    }

    /** The same credit as plain text, for an `alt` attribute. */
    public static function bbcToPlain(string $input): string
    {
        $plain = html_entity_decode(strip_tags(self::bbcToHtml($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('!\s+!u', ' ', $plain));
    }

    /**
     * `cut_string()`: cut at `$cut` characters, backing up to the last space
     * when there is one at or after character four, then append `$add`.
     *
     * The legacy loop walked backwards from `$cut - 1` down to 4, so a space at
     * the very start of the string is not used as a break point.
     */
    public static function cut(string $text, int $cut, string $add = '..'): string
    {
        if ($cut <= 0 || mb_strlen($text) <= $cut) {
            return $text;
        }

        $head  = mb_substr($text, 0, $cut);
        $space = mb_strrpos($head, ' ');

        if ($space !== false && $space >= 4) {
            $head = mb_substr($head, 0, $space);
        }

        return $head . $add;
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
