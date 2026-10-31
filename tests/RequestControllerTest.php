<?php

namespace App\Tests;

use App\Controllers\Web\RequestController;
use App\Services\RequestService;
use App\Services\SongService;
use App\Services\StationService;
use PHPUnit\Framework\TestCase;

/**
 * The request endpoint's reject paths, without any network or database.
 *
 * The accept path cannot be exercised end to end: a successful request calls
 * RadioDJ's LoadTrackToTop command, which queues a *real* track on a live
 * station. That was verified once by hand and then undone, so acceptance is
 * covered through RequestServiceTest::evaluate() instead.
 *
 * The cases here all reject before the first query, so they are safe and
 * deterministic.
 */
class RequestControllerTest extends TestCase
{
    public function testUnknownStationIsNotFound(): void
    {
        $controller = $this->controller();

        self::assertSame(404, $controller->formAction('rock', 1)->getStatus());
        self::assertSame(404, $controller->submitAction('rock')->getStatus());
    }

    public function testStationWithoutRequestSupportIsRejected(): void
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            ['id' => 'Legacy', 'slug' => 'legacy', 'title' => 'Legacy', 'radiodj_db' => null],
        ]);

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);

        self::assertSame(400, (new RequestController())->submitAction('legacy')->getStatus());
    }

    /**
     * CSRF is global middleware. If it went missing, every POST endpoint in the
     * application would silently become forgeable, so it is worth pinning.
     */
    public function testExpectedMiddlewareIsRegisteredGlobally(): void
    {
        \App\Bootstrap::bootstrap();

        $dispatcher = \App\AppContext::instance()->dispatcher();
        $reflection = new \ReflectionClass($dispatcher);
        $property   = $reflection->getProperty('globalMiddleware');
        $property->setAccessible(true);

        $classes = array_map(
            static fn($entry) => is_object($entry) ? $entry::class : (is_string($entry) ? $entry : ''),
            $property->getValue($dispatcher)
        );

        self::assertContains(\Azera\Security\CsrfMiddleware::class, $classes);
        self::assertContains(\Azera\Http\SessionMiddleware::class, $classes);
        self::assertContains(\App\Middleware\LegacyRedirectMiddleware::class, $classes);
    }

    private function controller(): RequestController
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'Trance', 'radiodj_db' => 'radiodj_trance'],
        ]);

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        $ctx->set(SongService::class, static fn() => new SongService($stations));
        $ctx->set(RequestService::class, static fn() => new RequestService($stations));

        return new RequestController();
    }
}
