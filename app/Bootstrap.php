<?php

namespace App;

use App\Middleware\LegacyRedirectMiddleware;
use Azera\Boot\BootstrapProvider;
use Azera\Core\Controller;
use Azera\Core\Engines\ClarityEngine;
use Azera\Core\ViewEngine;
use Azera\Db\Database;
use Azera\Http\SessionMiddleware;
use Azera\Security\CsrfMiddleware;

/**
 * Application bootstrap.
 *
 * Wires configuration, the database connections, the Clarity view engine,
 * the dispatcher and the route table. Mirrors the layout used by the
 * sailantis-homepage reference application so the two stay comparable.
 *
 * Deliberately does NOT touch the view engine eagerly: Clarity compiles
 * templates on the first render, and forcing it during boot costs a full
 * class stack load on every request.
 */
class Bootstrap implements BootstrapProvider
{
    /**
     * Boot once. Repeated calls are ignored, which keeps the CLI and the
     * web entry point interchangeable.
     */
    public static function bootstrap(): void
    {
        $instance = new static();
        $instance->boot();
    }

    public function boot(): void
    {
        $config = require CONFIG_PATH;

        $ctx = AppContext::instance();

        $ctx->Config = $config;

        $this->registerDatabase($ctx, $config);
        $this->registerViewEngine($ctx, $config);
        $this->registerServices($ctx);
        $this->configureDispatcher($ctx);
        $this->registerRoutes($ctx->router());
    }

    private function registerDatabase(AppContext $ctx, array $config): void
    {
        // Lazily built so a request that never touches the database (static
        // passthrough, redirects, health checks) never opens a connection.
        foreach ($config['db'] as $role => $params) {
            $ctx->dbManager()->set(
                $role,
                fn() => new Database($params['dsn'], $params['user'], $params['pass'])
            );
        }
    }

    private function registerViewEngine(AppContext $ctx, array $config): void
    {
        $viewsPath = $config['paths']['views'];
        $cachePath = $config['paths']['cache'];

        $ctx->set(
            ViewEngine::class,
            function () use ($viewsPath, $cachePath, $config) {
                $engine = (new ClarityEngine())
                    ->setViewPath($viewsPath)
                    ->setCachePath($cachePath . '/clarity')
                    ->setVars([
                        'appName' => $config['app']['name'],
                        'baseUrl' => $config['app']['base_url'],
                        'debug'   => $config['app']['debug'],
                    ]);

                return $engine;
            }
        );
    }

    private function registerServices(AppContext $ctx): void
    {
        $ctx->set(
            \App\Services\StationService::class,
            fn() => new \App\Services\StationService(AppContext::instance()->Config['stations'])
        );

        $ctx->set(
            \App\Services\TrackService::class,
            fn() => new \App\Services\TrackService(
                AppContext::instance()->get(\App\Services\StationService::class)
            )
        );

        $ctx->set(
            \App\Services\SongService::class,
            fn() => new \App\Services\SongService(
                AppContext::instance()->get(\App\Services\StationService::class)
            )
        );

        $ctx->set(
            \App\Services\StationViewService::class,
            fn() => new \App\Services\StationViewService()
        );

        $ctx->set(
            \App\Services\RequestService::class,
            fn() => new \App\Services\RequestService(
                AppContext::instance()->get(\App\Services\StationService::class)
            )
        );

        $ctx->set(
            \App\Services\GalleryService::class,
            fn() => new \App\Services\GalleryService()
        );

        $ctx->set(
            \App\Services\GuestbookService::class,
            fn() => new \App\Services\GuestbookService(
                AppContext::instance()->dbManager()->getDefault()
            )
        );

        $ctx->set(
            \App\Services\PartyService::class,
            fn() => new \App\Services\PartyService(
                AppContext::instance()->dbManager()->getDefault(),
                AppContext::instance()->Config['app']['timezone'] ?? 'Europe/Berlin'
            )
        );

        $ctx->set(
            \App\Services\MixesService::class,
            fn() => new \App\Services\MixesService(
                AppContext::instance()->dbManager()->getDefault()
            )
        );

        $ctx->set(
            \App\Services\ChatService::class,
            fn() => new \App\Services\ChatService(
                AppContext::instance()->dbManager()->getDefault()
            )
        );

        // Stateless: the header clock is pure computation, no connection.
        $ctx->set(
            \App\Services\ClockService::class,
            fn() => new \App\Services\ClockService()
        );

        // The request form's security image. It needs the legacy TTF, which is
        // the one asset the ported site reads from `data/`.
        $ctx->set(
            \App\Services\CaptchaService::class,
            fn() => new \App\Services\CaptchaService(
                AppContext::instance()->Config['paths']['font_security']
            )
        );

        $ctx->set(
            \App\Services\TickerService::class,
            fn() => new \App\Services\TickerService(
                AppContext::instance()->dbManager()->getDefault()
            )
        );
    }

    private function configureDispatcher(AppContext $ctx): void
    {
        $dispatcher = $ctx->dispatcher();
        $dispatcher->setBaseNamespace('\\App\\Controllers');
        $dispatcher->setDefaultController('Web\\IndexController');
        $dispatcher->setDefaultAction('indexAction');
        $dispatcher->addMiddleware(new SessionMiddleware());
        $dispatcher->addMiddleware(new LegacyRedirectMiddleware());
        $dispatcher->addMiddleware(new CsrfMiddleware());
    }

    private function registerRoutes(\Azera\Core\Router $router): void
    {
        $router->add('GET', '/', 'Web\\IndexController::indexAction');

        // Station-centric public pages (W3).
        $router->add('GET', '/listen', 'Web\\ListenController::indexAction');
        $router->add('GET', '/listen/{station:alpha}', 'Web\\ListenController::stationAction');
        $router->add('GET', '/playlist', 'Web\\PlaylistController::indexAction');
        $router->add('GET', '/playlist/{station:alpha}', 'Web\\PlaylistController::stationAction');
        $router->add('GET', '/muses', 'Web\\MusesController::indexAction');
        $router->add('GET', '/muses/{station:alpha}', 'Web\\MusesController::stationAction');
        $router->add('GET', '/topframe', 'Web\\TopframeController::indexAction');

        // Stream playlist files. A single path segment carries both the
        // station slug and the format ("trance.m3u"), so the parameter is
        // declared as a regex rather than two typed parameters.
        $router->add(
            'GET',
            '/stream/{file:regex([a-z]+\.(m3u|asx|pls))}',
            'Stream\\StreamController::playlistAction'
        );

        // The station song catalogue (W4). It lives at /mixes because the
        // catalogue *is* the mix collection: HBR1 plays DJ sets only, so the
        // RadioDJ "songs" rows are the mixes themselves. `/tracklist` was the
        // legacy name for the same thing and is kept as a redirect (see
        // TracklistController).
        $router->add('GET', '/mixes', 'Web\\MixesController::indexAction');
        $router->add('GET', '/mixes/{station:alpha}', 'Web\\MixesController::stationAction');

        // The retired `/tracklist` name. Both nav buttons now point at /mixes.
        $router->add('GET', '/tracklist', 'Web\\TracklistController::indexAction');
        $router->add('GET', '/tracklist/{station:alpha}', 'Web\\TracklistController::stationAction');

        // The `vimg.cgi` image viewer. About 1,300 legacy links point at it
        // with the image path as a bare query string (`?../images/djs/x.jpg`),
        // so the parameter is a multi-segment path, not a slug.
        $router->add('GET', '/viewer/{path:*}', 'Web\\ViewerController::imageAction');

        // Song requests.
        $router->add('GET', '/request/{station:alpha}/{song:int}', 'Web\\RequestController::formAction');
        $router->add('POST', '/request/{station:alpha}', 'Web\\RequestController::submitAction');

        // The security image on the request form. The legacy URL
        // (`?module=tracklist&show=secimg`) is a cache-buster only; this path
        // is the canonical one. It is a static segment rather than a typed
        // parameter so it cannot be shadowed by `{song:int}` above.
        $router->add('GET', '/request/code.png', 'Web\\RequestController::imageAction');

        // The iPhone chooser (`apax.cgi?run=iphone`). Oversized buttons, one
        // per station; the URL mapper sent this to /iphone but no such route
        // existed, so the legacy entry point landed on a 404.
        $router->add('GET', '/iphone', 'Web\\IphoneController::indexAction');
        // Gallery (W5).
        $router->add('GET', '/gallery', 'Web\\GalleryController::indexAction');
        $router->add('GET', '/gallery/pic/{id:int}', 'Web\\GalleryController::pictureAction');
        $router->add('GET', '/gallery/{category:int}', 'Web\\GalleryController::categoryAction');
        $router->add('GET', '/gallery/{category:int}/{gallery:int}', 'Web\\GalleryController::galleryAction');

        // Navigation frame (W6). The frameset's bottom bar.
        $router->add('GET', '/navigation', 'Web\\NavigationController::indexAction');

        // The administration area is W8. Until it exists, `/admin` must be
        // ours - otherwise it falls through to the legacy frameset in
        // `htdocs/admin/`, whose content frame redirects back here.
        $router->add('GET', '/admin', 'Web\\AdminController::indexAction');

        // Community (W6).
        $router->add('GET', '/guestbook', 'Web\\GuestbookController::indexAction');
        $router->add('POST', '/guestbook', 'Web\\GuestbookController::submitAction');

        $router->add('GET', '/party', 'Web\\PartyController::indexAction');
        $router->add('GET', '/party/{id:int}', 'Web\\PartyController::detailAction');

        $router->add('GET', '/chat', 'Web\\ChatController::indexAction');
        $router->add('POST', '/chat', 'Web\\ChatController::submitAction');

        // WebTV ticker (W7). The left camera frame loads /webtv/ticker/, whose
        // frameset asks for content.htm and then follows the meta-refresh ring.
        // These must win over the /webtv/ static alias, which is why the alias
        // uses try_files and these are real routes.
        $router->add(
            'GET',
            '/webtv/ticker/{file:regex(content[0-9]*\\.htm)}',
            'Web\\TickerController::pageAction'
        );
        $router->add('GET', '/webtv/ticker', 'Web\\TickerController::startAction');
    }
}
