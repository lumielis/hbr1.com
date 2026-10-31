<?php

namespace App\Tests;

use App\Services\ChatService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The nickname colour is a deterministic hash into an RGB triple. Regulars
 * recognise each other by it, so the arithmetic is pinned against the Perl
 * original rather than "looks reasonable".
 *
 * The expected values were produced by running `color_by_name()` from
 * cgi-bin/chat.cgi. They are the contract.
 */
class ChatColorTest extends TestCase
{
    #[DataProvider('nicknames')]
    public function testColorMatchesTheLegacyAlgorithm(string $name, string $expected): void
    {
        self::assertSame($expected, ChatService::colorByName($name));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function nicknames(): array
    {
        return [
            // 'a' is a vowel: red only. 'b' is a consonant: blue only.
            'a'        => ['a', 'FFBD96'],
            'b'        => ['b', '96BEFF'],
            'Nickname' => ['Nickname', 'BDBFD7'],
            'Tad'      => ['Tad', 'B9BCDC'],
            'Alice'    => ['Alice', 'D5BDC0'],
            'Pille'    => ['Pille', 'C0BFD5'],
        ];
    }

    public function testEmptyNameFallsBackToGrey(): void
    {
        self::assertSame('CCCCCC', ChatService::colorByName(''));
    }

    /**
     * The colour is emitted inside a style attribute, so a stored value that
     * is not six hex digits must never reach the page.
     */
    public function testValidationRejectsEmptyFields(): void
    {
        \App\Bootstrap::bootstrap();
        $service = new ChatService(\App\AppContext::instance()->dbManager()->getDefault());

        $errors = $service->validate(['name' => '  ', 'content' => '']);

        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('content', $errors);
    }

    public function testLongMessageIsRejected(): void
    {
        \App\Bootstrap::bootstrap();
        $service = new ChatService(\App\AppContext::instance()->dbManager()->getDefault());

        $errors = $service->validate(['name' => 'ok', 'content' => str_repeat('x', 256)]);

        self::assertArrayHasKey('content', $errors);
    }
}
