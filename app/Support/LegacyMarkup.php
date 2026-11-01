<?php

namespace App\Support;

/**
 * The markup helpers the legacy pages inherited from Perl.
 *
 * `cgi-bin/include/gallery.php` carried `bbc_to_html()` and `cut_string()`;
 * `PAB::Format::formatHyperlinks()` turned bare URLs and addresses in
 * guestbook entries into links; `PAB::Crypt::Xor` encrypted the guestbook
 * e-mail addresses that were decrypted in the browser.
 *
 * Two deliberate differences from production:
 *
 *  - the legacy renderer wrote `href\="..."`, with literal backslashes, so
 *    every gallery credit link was broken. This port emits working anchors.
 *  - the legacy pages printed stored markup verbatim. Here the allowed tags
 *    are rebuilt from scratch, so attributes (and therefore event handlers)
 *    cannot survive. See `.claude/reviews/gallery-parity-review.md`.
 */
final class LegacyMarkup
{
    /** Schemes a link may use. Anything else is rendered as plain text. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * The key `gbms.pab.pl` embedded in the guestbook page. It is an
     * obfuscation key, not a credential: production prints it in every page
     * that carries an e-mail link.
     */
    public const EMAIL_XOR_KEY = 'DFSE3-KX427-A2RRG-BL5XM-CB336';

    /** Inline tags the legacy BBC subset allowed. */
    private const INLINE_TAGS = ['b', 'i', 'u', 'br'];

    /**
     * Render the legacy BBC subset as HTML.
     *
     * Tags other than the inline ones are stripped first: the source columns
     * hold entities intentionally, so the DOM pass (rather than escaping) is
     * what keeps `K&uuml;tral` intact while still refusing a `<script>`.
     */
    public static function bbcToHtml(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $input = self::sanitizeInline($input, false);

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

        foreach (self::INLINE_TAGS as $tag) {
            if ($tag === 'br') {
                continue;
            }

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
     * Guestbook entry content: keep the legacy block/inline markup, then turn
     * bare URLs and addresses into links exactly where the legacy
     * `formatHyperlinks()` did.
     */
    public static function formatGuestbookContent(string $content): string
    {
        if ($content === '') {
            return '';
        }

        return self::autolink(self::sanitizeInline($content, true));
    }

    /**
     * `cut_string()`: cut at `$cut` characters, backing up to the last space
     * when there is one at or after character four, then append `$add`.
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

    /** `PAB::Crypt::Xor::encryptHex()` for the guestbook e-mail links. */
    public static function xorEncryptHex(string $plain): string
    {
        return bin2hex(self::xorTransform($plain, true));
    }

    /** `PAB::Crypt::Xor::decryptHex()`, used by the tests. */
    public static function xorDecryptHex(string $hex): string
    {
        if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            return '';
        }

        $raw = hex2bin($hex);

        return $raw === false ? '' : self::xorTransform($raw, false);
    }

    /**
     * Rebuild an HTML fragment from an allowlist.
     *
     * Disallowed elements are unwrapped (their text survives), scripts and
     * styles are removed outright, and every attribute is dropped. Anchors
     * keep only a safe `href`, plus `target`/`rel`.
     */
    private static function sanitizeInline(string $html, bool $allowAnchors): string
    {
        $document = self::loadFragment($html);

        if ($document === null) {
            return '';
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        self::cleanChildren($root, $allowAnchors);

        return self::serializeChildren($root);
    }

    private static function cleanChildren(\DOMNode $parent, bool $allowAnchors): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'script' || $tag === 'style') {
                $parent->removeChild($child);
                continue;
            }

            $allowed = in_array($tag, self::INLINE_TAGS, true)
                || ($allowAnchors && $tag === 'a');

            $href = $tag === 'a' ? $child->getAttribute('href') : null;

            if (!$allowed || ($tag === 'a' && !self::safeUrl((string) $href))) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);

                // The moved children may themselves contain disallowed tags.
                self::cleanChildren($parent, $allowAnchors);

                return;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttributeNode($attribute);
            }

            if ($tag === 'a') {
                $child->setAttribute('href', (string) $href);
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener');
            }

            self::cleanChildren($child, $allowAnchors);
        }
    }

    /** The legacy `formatHyperlinks()` behaviour, on text nodes only. */
    private static function autolink(string $html): string
    {
        $document = self::loadFragment($html);

        if ($document === null) {
            return '';
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return '';
        }

        self::linkTextNodes($root);

        return self::serializeChildren($root);
    }

    private static function linkTextNodes(\DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                $parentTag = $child->parentNode instanceof \DOMElement
                    ? strtolower($child->parentNode->tagName)
                    : '';

                if (!in_array($parentTag, ['a', 'script', 'style'], true)) {
                    self::replaceTextWithLinks($child);
                }

                continue;
            }

            if ($child instanceof \DOMElement) {
                self::linkTextNodes($child);
            }
        }
    }

    private static function replaceTextWithLinks(\DOMText $node): void
    {
        $text = $node->nodeValue ?? '';

        $pattern = '~((?:https?|ftp)://[0-9A-Za-z$_.\-/=&%?+,():#]+'
            . '|[0-9A-Za-z$_.\-]+@[0-9A-Za-z\-_]+\.[0-9A-Za-z$_.\-]+'
            . '|[0-9A-Za-z\-_]{3,}\.[A-Za-z]{2,}[0-9A-Za-z\-_./=&%?+,():#]*)~u';

        $count = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($count === false || $count === 0) {
            return;
        }

        $document = $node->ownerDocument;

        if ($document === null) {
            return;
        }

        $fragment = $document->createDocumentFragment();
        $offset   = 0;

        foreach ($matches[0] as [$match, $position]) {
            if ($position > $offset) {
                $fragment->appendChild($document->createTextNode(substr($text, $offset, $position - $offset)));
            }

            $anchor = $document->createElement('a');

            if (str_starts_with($match, 'http://') || str_starts_with($match, 'https://') || str_starts_with($match, 'ftp://')) {
                $anchor->setAttribute('href', $match);
                $anchor->setAttribute('target', '_blank');
                $anchor->setAttribute('rel', 'noopener');
            } elseif (str_contains($match, '@')) {
                $anchor->setAttribute('href', 'mailto:' . $match);
            } else {
                $anchor->setAttribute('href', 'http://' . $match);
                $anchor->setAttribute('target', '_blank');
                $anchor->setAttribute('rel', 'noopener');
            }

            $anchor->appendChild($document->createTextNode($match));
            $fragment->appendChild($anchor);

            $offset = $position + strlen($match);
        }

        if ($offset < strlen($text)) {
            $fragment->appendChild($document->createTextNode(substr($text, $offset)));
        }

        $node->parentNode?->replaceChild($fragment, $node);
    }

    private static function loadFragment(string $html): ?\DOMDocument
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $document : null;
    }

    private static function serializeChildren(\DOMNode $root): string
    {
        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $root->ownerDocument?->saveHTML($child) ?? '';
        }

        return $out;
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_contains($url, '\\')) {
            return false;
        }

        // A single leading slash is a local path; `//host` is protocol-relative
        // and leaves the site.
        if (preg_match('#^/(?!/)#', $url) === 1) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::SAFE_SCHEMES, true);
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function xorTransform(string $input, bool $encrypt): string
    {
        $key    = self::EMAIL_XOR_KEY;
        $keyLen = strlen($key);
        $out    = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $k = ord($key[$i % $keyLen]);
            $c = ord($input[$i]);

            $out .= chr($encrypt ? ($c + $k) % 256 : ($c - $k + 256) % 256);
        }

        return $out;
    }
}
