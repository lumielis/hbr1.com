<?php

namespace App\Tests;

use App\Controllers\Web\ViewerController;
use App\Services\StationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `vimg.cgi` image viewer.
 *
 * The legacy script printed `$ENV{'QUERY_STRING'}` straight into an `<img
 * src>` attribute, so it would happily render any URL it was given - and the
 * route had no equivalent in the port, which left ~1,300 links dead.
 *
 * These pin the two things that matter: a real file renders, and anything
 * that is not a real static file inside the document root is a 404.
 */
class ViewerControllerTest extends TestCase
{
    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => new StationService([]));
    }

    public function testRendersAKnownImage(): void
    {
        $html = (new ViewerController())->imageAction('images', 'djs', 'maya.jpg');

        self::assertStringContainsString('src="/images/djs/maya.jpg"', $html);
        self::assertStringContainsString('maya.jpg - IView v1.0.3 - (c) HBR1 2003', $html);
    }

    public static function rejectedPaths(): array
    {
        return [
            'missing file'        => [['images', 'djs', 'nope.jpg']],
            // LegacyStatic only serves known static extensions, so the Perl
            // configuration cannot be read through the viewer either.
            'non-static extension' => [['../cgi-bin/include/config.php']],
            'traversal'           => [['..', 'cgi-bin', 'settings.inc']],
            'dotfile'             => [['.git', 'config']],
            'absolute URL'        => [['http://evil.example/x.jpg']],
            'empty'               => [[]],
        ];
    }

    #[DataProvider('rejectedPaths')]
    public function testEverythingElseIsNotFound(array $path): void
    {
        self::assertSame(404, (new ViewerController())->imageAction(...$path)->getStatus());
    }

    /**
     * The caption drops the `NN_` ordering prefix the gallery upload added.
     *
     * There is no such file in the tree today, so the label is exercised
     * directly - the lookup is what would reject it first.
     */
    public function testLabelStripsTheNumericPrefix(): void
    {
        $label = new \ReflectionMethod(ViewerController::class, 'label');

        self::assertSame('maya.jpg', $label->invoke(new ViewerController(), 'images/djs/05_maya.jpg'));
        self::assertSame('maya.jpg', $label->invoke(new ViewerController(), 'images/djs/maya.jpg'));
    }
}
