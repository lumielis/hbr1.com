<?php

declare(strict_types=1);

namespace App\Tests;

use App\Support\CharsetRepair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The repair rules, with the byte sequences that actually occur in the
 * database as fixtures.
 */
final class CharsetRepairTest extends TestCase
{
    private function repair(): CharsetRepair
    {
        return new CharsetRepair();
    }

    // ------------------------------------------------------------- detection

    public function testPlainAsciiIsNotSuspect(): void
    {
        self::assertFalse($this->repair()->isSuspect('Schwarzfahrt'));
    }

    public function testCleanCp1252IsNotSuspect(): void
    {
        // "München" stored as the single byte FC, which is correct text.
        self::assertFalse($this->repair()->isSuspect("M\xFCnchen"));
    }

    public function testDoubleEncodedIsSuspect(): void
    {
        // "München" stored as C3 BC, the UTF-8 encoding of "ü".
        self::assertTrue($this->repair()->isSuspect("M\xC3\xBCnchen"));
    }

    public function testLoneHighBytesAreNotASequence(): void
    {
        // 0xB4 is a valid cp1252 acute accent and cannot start a UTF-8 run.
        self::assertFalse($this->repair()->isSuspect("DJ\xB4s"));
    }

    // --------------------------------------------------------------- repair

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function realValues(): array
    {
        return [
            // party.title 2530 - "Ä" as C3 84
            'SVEN VÄTH'          => ["WORLD LEAGUE with SVEN V\xC3\x84TH", 'WORLD LEAGUE with SVEN VÄTH'],
            // party.location 2582
            'München'            => ["5 Jahre M\xC3\xBCnchen", '5 Jahre München'],
            // party.title 2584
            'Kröcher'            => ["Felix Kr\xC3\xB6cher", 'Felix Kröcher'],
            // guestbook.content - a curly quote as E2 80 99
            'right single quote' => ["isn\xE2\x80\x99t", 'isn’t'],
            // guestbook.submitter - "Björn" written twice-encoded
            'Björn'              => ["Bj\xC3\xB6rn", 'Björn'],
        ];
    }

    #[DataProvider('realValues')]
    public function testRepairsDoubleEncodedValues(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->repair()->repair($stored));
    }

    public function testLeavesCleanCp1252Alone(): void
    {
        $clean = "Gr\xFC\xDFe aus K\xF6ln \x80";

        self::assertSame($clean, $this->repair()->repair($clean));
    }

    public function testLeavesPlainAsciiAlone(): void
    {
        self::assertSame('plain', $this->repair()->repair('plain'));
    }

    public function testIsIdempotent(): void
    {
        $repair = $this->repair();
        $once   = $repair->repair("M\xC3\xBCnchen");

        self::assertSame($once, $repair->repair($once));
        self::assertSame('München', $once);
    }

    /**
     * 25 values in the database mix both forms in one string. Treating the whole
     * value as either broken or clean gets these wrong; a run-wise scan does not.
     */
    public function testRepairsMixedValuesRunByRun(): void
    {
        // "DJ´s" with a clean cp1252 acute (B4), then "München" double-encoded.
        $stored = "DJ\xB4s: M\xC3\xBCnchen";

        self::assertSame('DJ´s: München', $this->repair()->repair($stored));
    }

    public function testMixedValueKeepsTheCleanPart(): void
    {
        // A lone 0x9A is cp1252 "š" and must survive alongside a repaired "ü".
        $stored = "M\xC3\xBCnchen \x9A";

        self::assertSame('München š', $this->repair()->repair($stored));
    }

    public function testDoesNotDoubleRepairAnAlreadyRepairedValue(): void
    {
        $repair = $this->repair();

        // "AÄ" where the Ä was already a single byte C4.
        $already = "A\xC4";

        self::assertSame($already, $repair->repair($already));
    }

    /**
     * The five cp1252-undefined bytes appear in real rows (one party.location
     * holds 0x9D). They must survive, since MySQL stores and returns them.
     */
    public function testUndefinedCp1252BytesSurvive(): void
    {
        $repair = $this->repair();

        foreach ([0x81, 0x8D, 0x8F, 0x90, 0x9D] as $byte) {
            // "a" + double-encoded "ü" + the raw byte + "b"
            $out = $repair->repair("a\xC3\xBC" . chr($byte) . 'b');

            self::assertSame('aü', mb_substr($out, 0, 2, 'UTF-8'));
            self::assertSame($byte, mb_ord(mb_substr($out, 2, 1, 'UTF-8')), sprintf('byte 0x%02X', $byte));
            self::assertSame('b', mb_substr($out, 3, 1, 'UTF-8'));
        }
    }

    /**
     * A 4-byte sequence must be taken whole. Emoji do occur in the chat table.
     */
    public function testRepairsFourByteSequences(): void
    {
        // "😀" as F0 9F 98 80
        self::assertSame("hi \xF0\x9F\x98\x80", $this->repair()->repair("hi \xF0\x9F\x98\x80"));
    }

    public function testLoneHighByteInASuspectValueFallsBackToCp1252(): void
    {
        // A value that IS double-encoded, but ends with a byte that cannot begin
        // a sequence. It must become cp1252 "Ã" (U+00C3), not U+FFFD.
        $out = $this->repair()->repair("M\xC3\xBCnchen\xC3");

        self::assertSame("München\xC3\x83", $out);
    }

    public function testAnUnpairedHighByteIsLeftAlone(): void
    {
        // No complete sequence means no evidence of double encoding: a single
        // stray cp1252 byte is just a stray byte, and rewriting it would be a
        // guess.
        self::assertSame("\xC3", $this->repair()->repair("\xC3"));
    }

    // ------------------------------------------------------------ detection

    public function testReplacementCharacterIsReportedAsLost(): void
    {
        self::assertTrue($this->repair()->isLost("bad \xEF\xBF\xBD value"));
        self::assertFalse($this->repair()->isLost('good ü value'));
    }

    // ------------------------------------------------------- cp1252 fitness

    public function testCp1252FitnessAcceptsTheGermanRepertoire(): void
    {
        // "Ł" is cp1252 0x9A, "Ø" is 0xD8, "š" is 0x9A - all representable.
        foreach (['München', 'Grüße', '€', '–', '»', 'é', 'Ø', 'Orašje'] as $text) {
            self::assertTrue($this->repair()->fitsCp1252($text), $text . ' should fit');
        }
    }

    public function testCp1252FitnessRejectsCyrillicAndCjk(): void
    {
        foreach (['Электро', '张浩', 'Αθήνα', '😀'] as $text) {
            self::assertFalse($this->repair()->fitsCp1252($text), $text . ' should not fit');
        }
    }

    /**
     * These three are the real deferrals: repairing them in a latin1 column
     * would turn the non-cp1252 characters into '?'.
     */
    public function testTheRealDeferralsAreDetected(): void
    {
        $repair = $this->repair();

        // party_comment_1.info - Cyrillic spam
        self::assertFalse($repair->fitsCp1252('бани из бруса'));
        // party.memo - Japanese in a language list
        self::assertFalse($repair->fitsCp1252('(Português | 日本語)'));
        // chat.name - fullwidth angle brackets
        self::assertFalse($repair->fitsCp1252('acu1112＜s1﹥s2'));
    }
}
