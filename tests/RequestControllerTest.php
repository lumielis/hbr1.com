<?php

namespace App\Tests;

use App\Controllers\Web\RequestController;
use App\Services\CaptchaService;
use App\Services\RequestService;
use App\Services\SongService;
use App\Services\StationService;
use Azera\Http\Session;
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
     * The station is in the rendered page at `{{ station:slug }}`. Before the
     * guard, a station-less rejection rendered the layout with a null track and
     * PHP emitted "trying to access array offset on null" into the response.
     */
    public function testStationWithoutRequestSupportRendersWithoutWarnings(): void
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            ['id' => 'Legacy', 'slug' => 'legacy', 'title' => 'Legacy', 'radiodj_db' => null],
        ]);

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        $ctx->set(CaptchaService::class, static fn() => new CaptchaService(self::fontPath()));

        $warnings = [];
        set_error_handler(static function (int $no, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $body = (new RequestController())->submitAction('legacy')->getBody();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertStringContainsString('Requests are not available for this station.', $body);
        self::assertStringNotContainsString('name="code"', $body, 'no form when there is no track to request');
    }

    public function testFormRendersTheSecurityImage(): void
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'Trance', 'radiodj_db' => 'radiodj_trance'],
        ]);

        $track = ['id' => 15, 'artist' => 'Akira Indika', 'title' => 'FuerMichiMix', 'duration' => 60, 'durationLabel' => '00:01:00'];

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        $ctx->set(SongService::class, static fn() => new class ($track) extends SongService {
            public function __construct(private array $track)
            {
                parent::__construct(new StationService([]));
            }

            public function find(array $station, int $id): ?array
            {
                return $this->track;
            }
        });
        $ctx->set(CaptchaService::class, static fn() => new CaptchaService(self::fontPath()));

        $body = (new RequestController())->formAction('trance', 15)->getBody();

        self::assertStringContainsString('REQUEST RESULT', $body);
        self::assertStringContainsString('GO_BACK', $body);
        self::assertStringContainsString('#AAAA22', $body, 'the legacy GO_BACK band colour');
        self::assertStringContainsString('src="/request/code.png?t=', $body);
        self::assertStringContainsString('To verify your input please enter the code schown above', $body);
        self::assertStringContainsString('name="code"', $body);
        self::assertStringContainsString('href="/mixes/trance"', $body);
    }

    public function testImageActionServesAPngAndBindsTheCodeToTheSession(): void
    {
        \App\Bootstrap::bootstrap();

        $captcha = new CaptchaService(self::fontPath());

        $store = [];
        $session = new Session($store);

        $ctx = \App\AppContext::instance();
        $ctx->set(CaptchaService::class, static fn() => $captcha);
        $ctx->setSession($session);

        $original = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $response = (new RequestController())->imageAction();
        } finally {
            $_SESSION = $original;
        }

        self::assertSame(200, $response->getStatus());
        self::assertSame('image/png', $response->getHeader('Content-Type'));
        self::assertSame('no-store, no-cache, must-revalidate', $response->getHeader('Cache-Control'));

        $info = getimagesizefromstring($response->getBody());

        self::assertIsArray($info);
        self::assertSame(100, $info[0]);
        self::assertSame(30, $info[1]);

        self::assertMatchesRegularExpression('/^[0-9]{5}$/', (string) $captcha->peek($session));
    }

    /**
     * The catalogue picks a random page when `?offset=` is absent, so GO_BACK
     * has to carry the offset the listener left. Without it the round-trip
     * silently lands on a different slice of the catalogue every time.
     */
    public function testBackLinkCarriesTheCatalogueOffset(): void
    {
        $body = $this->renderForm('?from=160');

        self::assertStringContainsString('href="/mixes/trance?offset=160"', $body);
        self::assertStringNotContainsString('href="/mixes/trance"', $body);
        self::assertStringContainsString('name="from" value="160"', $body);
    }

    public function testBackLinkWithoutAPageHasNoOffset(): void
    {
        $body = $this->renderForm('');

        self::assertStringContainsString('href="/mixes/trance"', $body);
        self::assertStringNotContainsString('offset=', $body);
        self::assertStringNotContainsString('name="from"', $body);
    }

    /**
     * The legacy page navigated to whatever `backlink` held. Only a plain
     * integer is accepted here, and the path is rebuilt from the station slug,
     * so neither a traversal nor an off-site URL can be introduced.
     */
    public function testMalformedPageOffsetIsIgnored(): void
    {
        foreach (['?from=../etc/passwd', '?from=http://evil.example/', '?from=-1', '?from=abc', '?from='] as $query) {
            $body = $this->renderForm($query);

            self::assertStringNotContainsString('evil.example', $body, $query);
            self::assertStringNotContainsString('offset=', $body, $query);
            self::assertStringContainsString('href="/mixes/trance"', $body, $query);
        }
    }

    /** Render `GET /request/trance/15` with a synthetic query string. */
    private function renderForm(string $query): string
    {
        \App\Bootstrap::bootstrap();

        $stations = new StationService([
            ['id' => 'Trance', 'slug' => 'trance', 'title' => 'Trance', 'radiodj_db' => 'radiodj_trance'],
        ]);

        $track = ['id' => 15, 'artist' => 'Akira Indika', 'title' => 'FuerMichiMix', 'duration' => 60, 'durationLabel' => '00:01:00'];

        parse_str(ltrim($query, '?'), $get);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/request/trance/15' . $query;
        $_SERVER['QUERY_STRING']   = ltrim($query, '?');
        $_GET                      = $get;

        $ctx = \App\AppContext::instance();
        $ctx->set(StationService::class, static fn() => $stations);
        $ctx->set(CaptchaService::class, static fn() => new CaptchaService(self::fontPath()));
        $ctx->set(\Azera\Http\Request::class, static fn() => new \Azera\Http\Request($_SERVER, $get));
        $ctx->set(SongService::class, static fn() => new class ($track) extends SongService {
            public function __construct(private array $track)
            {
                parent::__construct(new StationService([]));
            }

            public function find(array $station, int $id): ?array
            {
                return $this->track;
            }
        });

        return (new RequestController())->formAction('trance', 15)->getBody();
    }

    private static function fontPath(): string
    {
        return dirname(__DIR__) . '/data/security.ttf';
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
