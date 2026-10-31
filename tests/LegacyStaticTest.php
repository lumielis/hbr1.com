<?php

namespace App\Tests;

use App\Http\LegacyStatic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The legacy document root is still served for static files, because the
 * frameset loads `.htm` pages that predate the application.
 *
 * That makes this the one place where a path-traversal bug would expose the
 * whole checkout - `htdocs` sits next to `cgi-bin/`, which still contains the
 * Perl configuration with database credentials.
 */
class LegacyStaticTest extends TestCase
{
    private function handler(): LegacyStatic
    {
        return new LegacyStatic(dirname(__DIR__) . '/htdocs');
    }

    public function testServesAKnownLegacyPage(): void
    {
        $hit = $this->handler()->resolve('/ltop.htm');

        self::assertNotNull($hit);
        self::assertSame('text/html', $hit['type']);
    }

    public function testDirectoryResolvesToItsIndex(): void
    {
        // The frameset still loads static directory pages like /chat/, and
        // /broadcast/ is a plain file page. The ticker used to be the example
        // here, but its index.html is gone: /webtv/ticker/ is now a route.
        $hit = $this->handler()->resolve('/broadcast/');

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
