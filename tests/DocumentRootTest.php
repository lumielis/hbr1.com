<?php

namespace App\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The document root has to be the only web-reachable tree.
 *
 * `public/` is what the vhost serves. Every asset a page asks for has to be
 * there, at the URL the page already emits, or the request falls through to the
 * front controller and 404s. The vhost may not paper over a missing file with
 * an alias: an alias shadows the router, which is what broke `/playlist/{station}`
 * on the dev box and forced the `try_files /dev/null` override for the ticker.
 *
 * These are the checks that keep that property from silently rotting. They
 * encode the *outcome* of the cleanup, so they fail while the move is in
 * progress and pass only once it is finished.
 */
class DocumentRootTest extends TestCase
{
    private const PUBLIC = BASE_DIR . '/public';

    /** The retired trees must not exist at the project root any more. */
    public function testTheLegacyTreesAreGone(): void
    {
        foreach (['htdocs', 'cgi-bin', 'template'] as $dir) {
            self::assertDirectoryDoesNotExist(
                BASE_DIR . '/' . $dir,
                "{$dir}/ still exists at the project root; it belongs in legacy/archive/."
            );
        }
    }

    /**
     * Every asset tree a live page or the application names.
     *
     * `gp/` is the gallery's DB-derived artwork (`/gp/tn/…`, `/gp/pv/…`) and the
     * ticker's `images/` holds uploaded pages; both are referenced from row
     * data, so a missing directory shows up as a field of broken images rather
     * than as a failing link.
     */
    public static function servedTrees(): array
    {
        return [
            'images'        => ['images'],
            'style'         => ['style'],
            'jscript'       => ['jscript'],
            'gallery art'   => ['gp/tn'],
            'gallery prev'  => ['gp/pv'],
            'playlists'     => ['playlist'],
            'chat chrome'   => ['chat'],
            'ticker chrome' => ['webtv/ticker'],
            'ticker art'    => ['webtv/ticker/images'],
            'legacy js'     => ['cgi-htm/jscript'],
        ];
    }

    #[DataProvider('servedTrees')]
    public function testTheDocumentRootHoldsTheServedTree(string $relative): void
    {
        self::assertDirectoryExists(
            self::PUBLIC . '/' . $relative,
            "/{$relative}/ must be inside public/, or the URL it backs will 404."
        );
    }

    /**
     * The files, not just the directories.
     *
     * Each of these is named literally by a view, a controller or the CSS, so
     * the URL is fixed and only the containing directory could change.
     */
    public static function servedFiles(): array
    {
        return [
            // The page frame every content page inherits.
            'client css'   => ['style/client.css'],
            'spacer'       => ['images/spacer.gif'],
            'site header'  => ['images/header_left.gif'],
            'nav button'   => ['images/navigation/mbhome.gif'],
            'station art'  => ['images/station/station_left.gif'],
            'player strip' => ['images/player/station_player.png'],
            'flyer dir'    => ['images/party/flyer'],
            // JavaScript the views load by absolute URL.
            'zoom script'  => ['cgi-htm/jscript/zoom.js'],
            'color script' => ['cgi-htm/jscript/color.js'],
            'crypt script' => ['jscript/crypt.js'],
            // The front page's own frames.
            'frameset'     => ['index.html'],
            'left top'     => ['ltop.htm'],
            'left bottom'  => ['lbot.htm'],
            'filler'       => ['empty.htm'],
            // Navigation targets that are still plain files.
            'djs'          => ['djs.htm'],
            'artists'      => ['artists.html'],
            'links'        => ['link_base.htm'],
            'contact'      => ['contact.htm'],
            'events'       => ['events.html'],
        ];
    }

    #[DataProvider('servedFiles')]
    public function testTheDocumentRootHoldsTheServedFile(string $relative): void
    {
        $path = self::PUBLIC . '/' . $relative;

        self::assertTrue(
            is_file($path) || is_dir($path),
            "/{$relative} must be inside public/, or the URL it backs will 404."
        );
    }

    /**
     * No vhost alias may be needed for a served path.
     *
     * Both configs are checked, because they are meant to stay semantically
     * identical and a divergence is exactly how the dev box stops predicting
     * production. `/cgi-bin/`, `/run/` and `/perl/` are excluded: those are
     * legacy entry points the application answers with a 301, not static trees.
     */
    public static function vhosts(): array
    {
        return [
            'nginx (dev)'    => ['deploy/nginx/hbr1.evo.conf'],
            'apache (prod)'  => ['deploy/apache/www.hbr1.com.conf'],
        ];
    }

    #[DataProvider('vhosts')]
    public function testTheVhostDoesNotAliasAStaticTree(string $config): void
    {
        $source = (string) file_get_contents(BASE_DIR . '/' . $config);

        $aliased = [];

        // nginx: `location ^~ /images/ { root … }`
        if (preg_match_all('#location\s+\^~\s+/([a-z0-9_-]+)/#i', $source, $m) > 0) {
            $aliased = array_merge($aliased, $m[1]);
        }

        // Apache: `Alias /images /home/…/htdocs/images`
        if (preg_match_all('#^\s*Alias\s+/([a-z0-9_-]+)#im', $source, $m) > 0) {
            $aliased = array_merge($aliased, $m[1]);
        }

        $aliased = array_values(array_unique(array_diff($aliased, ['cgi-bin', 'run', 'perl', 'cgi-perl', 'cgi-pab'])));

        self::assertSame(
            [],
            $aliased,
            "{$config} still aliases static trees into the document root: " . implode(', ', $aliased)
        );
    }

    /**
     * A directory URL a page emits must not shadow a route.
     *
     * This is the trap the cleanup had to avoid: `/playlist/` and `/webtv/ticker/`
     * are directories *and* prefixes of application routes, so if the directory
     * existed while the route did not, or the other way round, one of them would
     * silently win. Anything listed here must be one or the other, never both.
     */
    public static function routePrefixes(): array
    {
        return [
            'playlist'      => ['playlist'],
            'gallery'       => ['gallery'],
            'mixes'         => ['mixes'],
            'muses'         => ['muses'],
            'party'         => ['party'],
            'chat'          => ['chat'],
            'ticker'        => ['webtv/ticker'],
            'request'       => ['request'],
            'admin'         => ['admin'],
        ];
    }

    #[DataProvider('routePrefixes')]
    public function testAStaticDirectoryDoesNotShadowARoute(string $relative): void
    {
        // The ticker is the documented exception: `webtv/ticker/` is a directory
        // of chrome *and* the frameset route. Its `index.html` was deliberately
        // removed so `/webtv/ticker/` reaches the controller, and the regression
        // is pinned separately in TickerServiceTest.
        if ($relative === 'webtv/ticker') {
            self::assertFileDoesNotExist(self::PUBLIC . '/webtv/ticker/index.html');
            return;
        }

        // A directory is only a problem if it has an index the web server would
        // serve ahead of the route; an empty or index-less directory falls
        // through to the front controller.
        self::assertFileDoesNotExist(
            self::PUBLIC . '/' . $relative . '/index.html',
            "/{$relative}/index.html would be served instead of the /{$relative} route."
        );
        self::assertFileDoesNotExist(
            self::PUBLIC . '/' . $relative . '/index.php',
            "/{$relative}/index.php would be executed instead of the /{$relative} route."
        );
    }

    /**
     * Everything a page still links must actually resolve.
     *
     * The archived trees are checked by hand rather than by walking links: this
     * covers the one class of mistake that is invisible in a test suite - a link
     * to a directory that was archived while the page that points at it stayed.
     */
    public static function linkedDirectories(): array
    {
        return [
            // Linked from the legacy nav bar and the `lbot` camera strip.
            'shop'   => ['shop'],
            'upload' => ['upload'],
            'live'   => ['live'],
        ];
    }

    #[DataProvider('linkedDirectories')]
    public function testALinkedDirectoryIsStillInTheDocumentRoot(string $relative): void
    {
        self::assertDirectoryExists(
            self::PUBLIC . '/' . $relative,
            "/{$relative}/ is linked from a live page but is not in public/."
        );
    }

    /**
     * The nginx `try_files` list must not try a directory.
     *
     * `try_files $uri $uri/ …` matches a directory and hands it to nginx, which
     * with `autoindex off` answers 403 - the front controller is never reached.
     * `/webtv/ticker/` is a directory and a route, and it is the front page's
     * `lcam` frame, so that single extra token would blank a pane of the site
     * while every unit test still passed. `/chat/` is in the same position.
     *
     * Apache has no equivalent hazard: `FallbackResource` fires because the
     * directory has no index, which is exactly the behaviour wanted.
     */
    public function testTheNginxFallbackDoesNotTryDirectories(): void
    {
        $source = (string) file_get_contents(BASE_DIR . '/deploy/nginx/hbr1.evo.conf');

        // Strip comments so the explanation above cannot satisfy the assertion.
        $source = (string) preg_replace('/^\s*#.*$/m', '', $source);

        self::assertDoesNotMatchRegularExpression(
            '/try_files\s+[^;]*\$uri\/\s/',
            $source,
            'try_files must not contain `$uri/`: a directory match is a 403, not a route.'
        );
    }
}
