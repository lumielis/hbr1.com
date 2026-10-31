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
        $router->add('GET', '/topframe', 'Web\\TopframeController::indexAction');

        // Stream playlist files. A single path segment carries both the
        // station slug and the format ("trance.m3u"), so the parameter is
        // declared as a regex rather than two typed parameters.
        $router->add(
            'GET',
            '/stream/{file:regex([a-z]+\.(m3u|asx|pls))}',
            'Stream\\StreamController::playlistAction'
        );

        // Tracklist (W4).
        $router->add('GET', '/tracklist', 'Web\\TracklistController::indexAction');
        $router->add('GET', '/tracklist/{station:alpha}', 'Web\\TracklistController::stationAction');

        // Song requests.
        $router->add('GET', '/request/{station:alpha}/{song:int}', 'Web\\RequestController::formAction');
        $router->add('POST', '/request/{station:alpha}', 'Web\\RequestController::submitAction');
        // Gallery (W5).
        $router->add('GET', '/gallery', 'Web\\GalleryController::indexAction');
        $router->add('GET', '/gallery/pic/{id:int}', 'Web\\GalleryController::pictureAction');
        $router->add('GET', '/gallery/{category:int}', 'Web\\GalleryController::categoryAction');
        $router->add('GET', '/gallery/{category:int}/{gallery:int}', 'Web\\GalleryController::galleryAction');

        // Navigation frame (W6). The frameset's bottom bar.
        $router->add('GET', '/navigation', 'Web\\NavigationController::indexAction');

        // Community (W6).
        $router->add('GET', '/guestbook', 'Web\\GuestbookController::indexAction');
        $router->add('POST', '/guestbook', 'Web\\GuestbookController::submitAction');

        $router->add('GET', '/party', 'Web\\PartyController::indexAction');
        $router->add('GET', '/party/{id:int}', 'Web\\PartyController::detailAction');

        $router->add('GET', '/mixes', 'Web\\MixesController::indexAction');
        $router->add('GET', '/mixes/playlist/{id:int}', 'Web\\MixesController::playlistAction');
        $router->add('GET', '/mixes/{category:int}', 'Web\\MixesController::categoryAction');

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
