<?php

namespace App;

use App\Middleware\LegacyRedirectMiddleware;
use Azera\Boot\BootstrapProvider;
use Azera\Core\Controller;
use Azera\Core\Engines\ClarityEngine;
use Azera\Core\ViewEngine;
use Azera\Db\Database;
use Azera\Http\SessionMiddleware;

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
    }

    private function configureDispatcher(AppContext $ctx): void
    {
        $dispatcher = $ctx->dispatcher();
        $dispatcher->setBaseNamespace('\\App\\Controllers');
        $dispatcher->setDefaultController('Web\\IndexController');
        $dispatcher->setDefaultAction('indexAction');
        $dispatcher->addMiddleware(new SessionMiddleware());
        $dispatcher->addMiddleware(new LegacyRedirectMiddleware());
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

        // Gallery (W5).
        $router->add('GET', '/gallery', 'Web\\GalleryController::indexAction');
        $router->add('GET', '/gallery/pic/{id:int}', 'Web\\GalleryController::pictureAction');
        $router->add('GET', '/gallery/{category:int}', 'Web\\GalleryController::categoryAction');
        $router->add('GET', '/gallery/{category:int}/{gallery:int}', 'Web\\GalleryController::galleryAction');
    }
}
