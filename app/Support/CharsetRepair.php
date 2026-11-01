<?php

namespace App\Support;

/**
 * Repairs the character damage left by the latin1 era.
 *
 * ## What the damage is
 *
 * The legacy site ran entirely on `latin1` columns. Accented characters usually
 * survived: `ü` was stored as the single cp1252 byte `FC`. But sometimes a
 * UTF-8 byte string was written into the column byte-for-byte - the writer had
 * already encoded it, and the column stored the bytes as given. `München` then
 * became `MÃ¼nchen`, held as `4D C3 BC 6E 63 68 65 6E`.
 *
 * The second case is recoverable precisely because those bytes *are* the
 * correct UTF-8 encoding of the intended text. Reading them as UTF-8 instead of
 * cp1252 recovers the characters.
 *
 * ## Why the scan is run-wise, not a regex
 *
 * 25 of the 186 affected values are mixed: part double-encoded, part plain
 * cp1252. Treating a value as wholly broken or wholly clean gets those wrong.
 * This walks the byte string instead - a well-formed UTF-8 sequence is taken as
 * text, and any byte that cannot begin one is read as cp1252.
 *
 * The legacy code used a regex over the whole string, which rewrote the bytes
 * in exactly these mixed cases. A byte scan leaves them intact.
 *
 * ## Why some values must be deferred
 *
 * In a `latin1` column the repair has to write the result back as cp1252. When
 * the recovered text contains something cp1252 cannot hold - the Cyrillic spam
 * in `party_comment_1`, one emoji in `chat.name`, one Japanese `party.memo` -
 * writing it back would turn that character into `?`, which is *worse* than the
 * mojibake. Those are reported as deferred and need `utf8mb4` first.
 */
final class CharsetRepair
{
    /**
     * The five byte values cp1252 leaves undefined. Real latin1 text does not
     * contain them, so they are decoded as ISO-8859-1 rather than as U+FFFD.
     */
    private const UNDEFINED_CP1252 = [0x81, 0x8D, 0x8F, 0x90, 0x9D];

    /** Matches a well-formed 2-, 3- or 4-byte UTF-8 sequence. */
    private const SEQUENCE = '/[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3}/';

    /** @var array<int, string> byte => UTF-8 character */
    private array $decoded = [];

    /** @var array<string, true>|null characters cp1252 can represent */
    private ?array $encodable = null;

    /**
     * The text this value was always meant to contain.
     *
     * Idempotent: the result carries no double-encoded runs, so calling it a
     * second time returns the value unchanged.
     */
    public function repair(string $latin1Bytes): string
    {
        if (!$this->isSuspect($latin1Bytes)) {
            return $latin1Bytes;
        }

        $out    = '';
        $offset = 0;
        $length = strlen($latin1Bytes);

        while ($offset < $length) {
            $byte = ord($latin1Bytes[$offset]);

            if ($byte < 0x80) {
                $out .= $latin1Bytes[$offset];
                $offset++;
                continue;
            }

            $width = $this->sequenceWidth($byte);

            if ($width > 1 && $offset + $width <= $length) {
                $candidate = substr($latin1Bytes, $offset, $width);

                // One well-formed character, not an overlong form or a surrogate.
                if (preg_match('//u', $candidate) === 1 && mb_strlen($candidate, 'UTF-8') === 1) {
                    $out .= $candidate;
                    $offset += $width;
                    continue;
                }
            }

            $out .= $this->cp1252Character($byte);
            $offset++;
        }

        return $out;
    }

    /** Does this value look double-encoded? */
    public function isSuspect(string $latin1Bytes): bool
    {
        return preg_match(self::SEQUENCE, $latin1Bytes) === 1;
    }

    /** Has this value already lost a character to `?` or U+FFFD? */
    public function isLost(string $latin1Bytes): bool
    {
        return str_contains($latin1Bytes, "\xEF\xBF\xBD");
    }

    /**
     * Can a `latin1` column hold this text without losing a character?
     *
     * False means repairing now would trade mojibake for `?`; the caller should
     * defer the value until the columns are `utf8mb4`.
     */
    public function fitsCp1252(string $utf8): bool
    {
        $this->encodable ??= $this->encodableCharacters();

        foreach (preg_split('//u', $utf8, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (!isset($this->encodable[$character])) {
                return false;
            }
        }

        return true;
    }

    /** How many bytes a UTF-8 sequence starting with this byte would use. */
    private function sequenceWidth(int $byte): int
    {
        return match (true) {
            $byte >= 0xF0 && $byte <= 0xF4 => 4,
            $byte >= 0xE0 && $byte <= 0xEF => 3,
            $byte >= 0xC2 && $byte <= 0xDF => 2,
            default                        => 1,
        };
    }

    /** One cp1252 byte read as a UTF-8 character. */
    private function cp1252Character(int $byte): string
    {
        return $this->decoded[$byte] ??= mb_convert_encoding(
            chr($byte),
            'UTF-8',
            in_array($byte, self::UNDEFINED_CP1252, true) ? 'ISO-8859-1' : 'WINDOWS-1252'
        );
    }

    /**
     * Every character cp1252 (and the latin1 column) can represent.
     *
     * The five bytes cp1252 leaves undefined are included deliberately: MySQL
     * stores them, and this application reads them back as U+0081 and friends.
     * Writing such a character returns the same byte, verified. Treating them
     * as unrepresentable would defer values that actually round-trip.
     *
     * @return array<string, true>
     */
    private function encodableCharacters(): array
    {
        $characters = [];

        for ($byte = 0x00; $byte <= 0x7F; $byte++) {
            $characters[chr($byte)] = true;
        }

        for ($byte = 0x80; $byte <= 0xFF; $byte++) {
            $characters[mb_convert_encoding(chr($byte), 'UTF-8', 'ISO-8859-1')] = true;
            $characters[mb_convert_encoding(chr($byte), 'UTF-8', 'WINDOWS-1252')] = true;
        }

        return $characters;
    }
}
