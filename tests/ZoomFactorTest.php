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
 * There is a second, easier-to-miss half of the same invariant: a frame
 * document inherits *nothing* from the frameset around it, so any frame whose
 * page does not link `client.css` has to declare the zoom itself. Otherwise the
 * track is scaled and the document is not, and the pane renders a quarter too
 * small - which is exactly what happened to the navigation bar and the whole
 * left-hand column. `testFrameDocumentsCarryTheirOwnZoom` is that half.
 */
class ZoomFactorTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    /**
     * Frame documents and where their zoom comes from.
     *
     * `source` is `own` when the document must declare the zoom itself (it is a
     * frame and does not link `client.css`) and `css` when it links the
     * stylesheet and inherits it from there. `none` marks a frame with nothing
     * to scale, which still has to be listed so that a *new* frame cannot slip
     * in unexamined.
     *
     * @var array<string, array{path: string, source: string, why: string}>
     */
    private const FRAME_DOCUMENTS = [
        '/empty.htm' => [
            'path'   => 'public/empty.htm',
            'source' => 'own',
            'why'    => 'the 78 px mesh tile fills the scaled side columns',
        ],
        '/ltop.htm' => [
            'path'   => 'public/ltop.htm',
            'source' => 'own',
            'why'    => '98+96 px of artwork must fill the scaled 258 px track',
        ],
        '/html/lbot.html' => [
            'path'   => 'public/html/lbot.html',
            'source' => 'own',
            'why'    => 'the 194 px music logo must fill the scaled 258 px track',
        ],
        '/navigation' => [
            'path'   => 'views/web/navigation.clarity.html',
            'source' => 'own',
            'why'    => 'the buttons are butted inline images on a 686 px canvas',
        ],
        '/topframe' => [
            'path'   => 'views/web/topframe.clarity.html',
            'source' => 'css',
            'why'    => 'links client.css directly (it is not a layout child)',
        ],
        '/listen' => [
            'path'   => 'views/web/listen.clarity.html',
            'source' => 'layout',
            'why'    => 'extends layouts/page, which declares the zoom',
        ],
        '/webtv/ticker/index_left.html' => [
            'path'   => 'public/webtv/ticker/index_left.html',
            'source' => 'own',
            'why'    => '10 px of camera chrome inside a scaled 13.33 px column',
        ],
        '/webtv/ticker/index_right.html' => [
            'path'   => 'public/webtv/ticker/index_right.html',
            'source' => 'own',
            'why'    => '24 px of camera chrome inside a scaled 31.99 px column',
        ],
        '/webtv/ticker/content.htm' => [
            'path'   => 'views/web/ticker_page.clarity.html',
            'source' => 'own',
            'why'    => 'the ticker text has to fit the scaled 213.28 px column',
        ],
    ];

    /** The factor the CSS declares; everything else is checked against it. */
    public function testClientCssDeclaresThePageZoom(): void
    {
        $css = $this->read('public/style/client.css');

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
        $script = $this->read('public/cgi-htm/jscript/zoom.js');

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

    /**
     * A frame document must render at the same scale as the track holding it.
     *
     * The frameset only scales its *tracks*; each document inside is a separate
     * page and inherits nothing. A page that links `client.css` gets the zoom
     * from `body`, but the frames that do not link it - the navigation bar, the
     * left-hand artwork, the ticker chrome - used to render at 1x inside a track
     * scaled to 1.333, so they came out a quarter too small and stopped filling
     * their frame.
     */
    public function testFrameDocumentsCarryTheirOwnZoom(): void
    {
        foreach (self::FRAME_DOCUMENTS as $url => $doc) {
            $content = $this->stripComments($this->read($doc['path']));

            if ($doc['source'] === 'css') {
                self::assertStringContainsString(
                    'client.css',
                    $content,
                    $url . ' is listed as inheriting the zoom from client.css but does not link it'
                );
                continue;
            }

            if ($doc['source'] === 'layout') {
                self::assertStringContainsString(
                    'extends "layouts/page"',
                    $content,
                    $url . ' is listed as inheriting the zoom from the page layout but does not extend it'
                );
                continue;
            }

            self::assertStringNotContainsString(
                'client.css',
                $content,
                $url . ' must not link client.css; it declares its own zoom'
            );
            self::assertMatchesRegularExpression(
                // `[^}]*` cannot be used here: Clarity placeholders like
                // `{{ page:bgColor }}` contain closing braces, so the match has
                // to run on to the brace that starts its own line instead.
                '/body\s*\{(?:(?!^\s*\}).)*?zoom:\s*1\.333/ms',
                $content,
                $url . ' renders at 1x inside a track scaled to 1.333 (' . $doc['why'] . ')'
            );
        }
    }

    /**
     * A `<body background=>` must be paired with a background on `<html>`.
     *
     * This one is not obvious and cost a round of debugging. When the root
     * element is transparent, CSS propagates the *body's* background to the
     * canvas - and the canvas is painted across the viewport, outside the
     * body's `zoom`. So the background image repeats at its raw size while
     * everything else grows: `lbg.gif` tiled every 194 px instead of 258.6, and
     * the left column stopped lining up with the frames above it.
     *
     * Any non-transparent background-color on `<html>` stops the propagation,
     * and then the body's background scales like any other element's. Verified
     * against the rendered pixels, not against computed style: with the fix the
     * tile pitch measures 258.3 px.
     *
     * Only `background=` on `<body>` is affected. The same attribute on a `<td>`
     * or `<table>` scales normally - measured directly - so the layout's band
     * artwork needs nothing.
     */
    public function testBodyBackgroundIsNotPropagatedToTheCanvas(): void
    {
        $checked = 0;

        foreach (self::FRAME_DOCUMENTS as $url => $doc) {
            $content = $this->stripComments($this->read($doc['path']));

            if (preg_match('/<body\b[^>]*\bbackground\s*=/i', $content) !== 1) {
                continue;
            }
            $checked++;

            self::assertMatchesRegularExpression(
                '/html\s*\{[^}]*background(-color)?\s*:/s',
                $content,
                $url . ' sets a background on <body>; <html> must carry a background too, or the '
                . 'image propagates to the canvas and does not scale'
            );
        }

        self::assertGreaterThan(0, $checked, 'expected at least one frame to use a body background');
    }

    /**
     * Framesets the application actually serves.
     *
     * The tree also contains older framesets (`/admin/`, `/stations/`,
     * `/broadcast/`, `/gallery/`, `/flyers.htm`, `/iphone.html`, those under
     * `/chat/`), but their frames point at CGI entry points that are gone or at
     * pages no route reaches. Editing them for the zoom would be inventing work,
     * so this test governs only what a visitor can load.
     */
    private const LIVE_FRAMESETS = [
        'public/index.html',
        'views/web/index.clarity.html',
        'views/web/ticker.clarity.html',
    ];

    /**
     * Legacy frame srcs that redirect to a canonical route.
     *
     * `public/index.html` is frozen: it is the pre-migration front page, kept
     * so the old entry point still resolves. Its frames name CGI entry points
     * that `LegacyUrlMapper` now 301s to the routes in the table above, so the
     * document that actually renders is the canonical one - already reviewed.
     *
     * @var array<string, string>
     */
    private const REDIRECTED_FRAMES = [
        '/cgi-bin/main.php?module=topframe' => '/topframe',
        '/cgi-bin/main.php?module=listen'   => '/listen',
        '/perl/apax.cgi?run=navigation'     => '/navigation',
    ];

    /**
     * Every frame the live framesets load must be accounted for above.
     *
     * The table above is only useful if it is complete, and a frameset is
     * exactly the kind of markup nobody edits until something is visibly broken.
     */
    public function testEveryLiveFrameIsReviewed(): void
    {
        $missing = [];

        foreach (self::LIVE_FRAMESETS as $path) {
            $html = $this->stripComments($this->read($path));

            if (preg_match_all('/<frame\b[^>]*\bsrc\s*=\s*"([^"]*)"/i', $html, $m) === 0) {
                continue;
            }

            foreach ($m[1] as $src) {
                // Frames whose src is a frameset are covered by their own
                // entries above; only leaf documents need a row.
                if (in_array($src, ['/webtv/ticker/', '/webtv/ticker'], true)) {
                    continue;
                }

                // The frozen legacy front page uses document-relative srcs;
                // resolve them against / so they match the table's keys.
                $key = str_starts_with($src, '/') ? $src : '/' . ltrim($src, './');

                if (isset(self::FRAME_DOCUMENTS[$key])) {
                    continue;
                }
                if (isset(self::REDIRECTED_FRAMES[$key])) {
                    // The redirect target has to be reviewed, not just named.
                    self::assertArrayHasKey(
                        self::REDIRECTED_FRAMES[$key],
                        self::FRAME_DOCUMENTS,
                        $path . ' redirects ' . $src . ' to a document that is not reviewed'
                    );
                    continue;
                }
                $missing[$src] = $path;
            }
        }

        self::assertSame([], $missing, 'these live frame documents are not covered by FRAME_DOCUMENTS');
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
        $dirs   = [$root . '/public', $root . '/views'];
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
        // noticing it stopped covering anything. Five remain: the three that are
        // routes (`/`, the ticker, the chat terminal's grid is not a frameset),
        // plus `flyers.htm` and `iphone.html`. The archived `webtv/index*.html`
        // and `chat/index.html` used to be counted here; they are applet-era
        // pages nothing links, and `chat/index.html` would have shadowed the
        // `/chat/` route.
        self::assertGreaterThanOrEqual(5, $seen, 'expected the legacy framesets to still be found');

        return $found;
    }

    private function read(string $relative): string
    {
        $path = self::ROOT . '/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
