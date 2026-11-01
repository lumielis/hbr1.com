<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The page zoom factor is declared in four places and they must agree.
 *
 * The site scales itself to PAGE_ZOOM with CSS `zoom` on `body`, and every
 * legacy `<frameset>` scales its fixed tracks by the same factor (otherwise the
 * frameset keeps its 794 px column while the pages inside it grow, and the
 * content overflows its frame). Nothing in the language links those numbers, so
 * a change to one and not the others silently reintroduces the overflow. This
 * test is the link.
 *
 * The two panes that do not link `client.css` (`ltop.htm`, `lbot.html`) and the
 * chat terminal's bare `left.htm`/`right.htm` chrome each carry their own copy
 * for the same reason.
 */
class ZoomFactorTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    /** The factor the CSS declares; everything else is checked against it. */
    public function testClientCssDeclaresThePageZoom(): void
    {
        $css = $this->read('htdocs/style/client.css');

        self::assertSame(
            1,
            preg_match('/body\s*\{[^}]*zoom:\s*([\d.]+)/s', $css, $m),
            'client.css must declare a zoom on body'
        );

        self::assertSame(1.333, (float) $m[1]);
    }

    public function testPageLayoutDeclaresTheSameZoom(): void
    {
        $layout = $this->read('views/layouts/page.clarity.html');

        self::assertSame(
            1,
            preg_match('/body\s*\{[^}]*zoom:\s*([\d.]+)/s', $layout, $m),
            'the page layout must declare the same zoom as client.css'
        );

        self::assertSame(1.333, (float) $m[1]);
    }

    public function testFramesetScriptUsesTheSameZoom(): void
    {
        $script = $this->read('htdocs/cgi-htm/jscript/zoom.js');

        self::assertSame(
            1,
            preg_match('/PAGE_ZOOM\s*=\s*([\d.]+)/', $script, $m)
        );

        self::assertSame(1.333, (float) $m[1]);
    }

    /**
     * Every document that still is a frameset must load the track scaler before
     * the frameset tag: the tracks are applied as they parse, so a script
     * placed afterwards cannot correct them.
     */
    public function testEveryFramesetLoadsTheScalerFirst(): void
    {
        foreach ($this->framesetDocuments() as $path) {
            // Strip comments and script blocks first: several comments quote the
            // tag name, and `chat.html` builds its frameset with document.write.
            // Neither is static markup this test can govern.
            $html     = $this->stripComments($this->read($path));
            $scriptAt = strpos($html, 'zoom.js');
            $setAt    = stripos($html, '<frameset');

            if (!$this->hasFixedTrack($html, $setAt)) {
                continue;
            }

            self::assertNotFalse($scriptAt, $path . ' is a frameset but does not load zoom.js');
            self::assertLessThan(
                $setAt,
                $scriptAt,
                $path . ' loads zoom.js after the frameset tag; the tracks are already parsed'
            );
        }
    }

    /**
     * Does the frameset at $offset declare a fixed pixel track?
     *
     * `*` and `%` tracks are already relative and scale on their own, so a
     * frameset made only of those needs no scaler - `iphone.html` is
     * `cols="100%"`.
     */
    private function hasFixedTrack(string $html, int|false $offset): bool
    {
        if ($offset === false) {
            return false;
        }

        $tag = substr($html, $offset, 200);

        foreach (['cols', 'rows'] as $attribute) {
            if (!preg_match('~' . $attribute . '="([^"]*)"~i', $tag, $m)) {
                continue;
            }
            foreach (explode(',', $m[1]) as $track) {
                $track = trim($track);
                if ($track !== '' && !str_contains($track, '%') && !str_contains($track, '*')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function testTheChatTerminalDoesNotUseAFrameset(): void
    {
        // It is a CSS grid so that it scales with the zoom without any of this.
        $view = $this->stripComments($this->read('views/web/chat_terminal.clarity.html'));

        self::assertStringNotContainsString('<frameset', $view);
        self::assertStringContainsString('class="terminal"', $view);
    }

    /** Remove comments and script bodies so prose and generated markup are ignored. */
    private function stripComments(string $content): string
    {
        $content = (string) preg_replace('~\{#.*?#\}~s', '', $content);
        $content = (string) preg_replace('~<!\-\-.*?\-\->~s', '', $content);
        $content = (string) preg_replace('~/\*.*?\*/~s', '', $content);

        // Strip inline script bodies, but keep <script src="..."> so a loader of
        // zoom.js is still visible; only generated markup needs suppressing.
        return (string) preg_replace('~<script\b(?![^>]*\bsrc=)[^>]*>.*?</script>~is', '', $content);
    }

    /** @return list<string> */
    private function framesetDocuments(): array
    {
        $found  = [];
        $root   = realpath(self::ROOT);
        $dirs   = [$root . '/htdocs', $root . '/views'];
        $seen   = 0;

        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (!preg_match('/\.(html?|clarity\.html)$/', $file->getFilename())) {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                if (!str_contains($this->stripComments($contents), '<frameset')) {
                    continue;
                }
                $found[] = substr($file->getPathname(), strlen($root) + 1);
                $seen++;
            }
        }

        sort($found);

        // The tree must not silently lose its framesets without this test
        // noticing it stopped covering anything.
        self::assertGreaterThan(5, $seen, 'expected the legacy framesets to still be found');

        return $found;
    }

    private function read(string $relative): string
    {
        $path = self::ROOT . '/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
