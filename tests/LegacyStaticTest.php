<?php

namespace App\Tests;

use App\Http\LegacyStatic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The legacy document root is now `public/`, and the `.htm` pages the frameset
 * loads still live there as plain files.
 *
 * That makes this the one place where a path-traversal bug would expose the
 * whole checkout - a few directories above `public/` sit `legacy/archive/` (the
 * Perl configuration, credentials included) and the application itself.
 */
class LegacyStaticTest extends TestCase
{
    private function handler(): LegacyStatic
    {
        return new LegacyStatic(dirname(__DIR__) . '/public');
    }

    public function testServesAKnownLegacyPage(): void
    {
        $hit = $this->handler()->resolve('/ltop.htm');

        self::assertNotNull($hit);
        self::assertSame('text/html', $hit['type']);
    }

    public function testDirectoryResolvesToItsIndex(): void
    {
        // Requests for a directory resolve to its `index.html`, the way Apache's
        // DirectoryIndex did. `/shop/` is the surviving example. The chat and
        // ticker framesets used to be the example, but their `index.html` is
        // gone: both `/chat/` and `/webtv/ticker/` are routes now, and a file of
        // that name would be served ahead of the route.
        $hit = $this->handler()->resolve('/shop/');

        self::assertNotNull($hit);
        self::assertStringEndsWith('index.html', str_replace('\\', '/', $hit['file']));
    }

    #[DataProvider('dangerousPaths')]
    public function testRejectsDangerousPaths(string $path): void
    {
        self::assertNull($this->handler()->resolve($path), $path);
    }

    /** @return array<string, array{0: string}> */
    public static function dangerousPaths(): array
    {
        return [
            'parent traversal'      => ['/../env.php'],
            'nested traversal'      => ['/images/../../env.php'],
            'encoded traversal'     => ['/%2e%2e/env.php'],
            'encoded slash'         => ['/images%2f..%2f..%2fenv.php'],
            'dotfile'              => ['/.htpasswd'],
            'nested dotfile'       => ['/admin/.htpasswd'],
            'git directory'         => ['/.git/config'],
            // The Perl configuration is still in the tree and holds
            // credentials; it must never be served.
            'perl config'           => ['/../cgi-bin/include/settings.inc'],
            'perl module'           => ['/../cgi-bin/main.php'],
            'php source'            => ['/../app/Bootstrap.php'],
            'null byte'             => ["/ltop.htm\0.png"],
            // Extension is not on the static list.
            'no extension'          => ['/images/spacer'],
        ];
    }

    public function testDoesNotServePhpOrPerlExtensions(): void
    {
        $handler = $this->handler();

        foreach (['/index.php', '/cgi-bin/main.php', '/cgi-bin/asx.cgi'] as $path) {
            self::assertNull($handler->resolve($path), $path);
        }
    }
}
