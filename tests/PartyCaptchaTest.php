<?php

namespace App\Tests;

use App\AppContext;
use App\Controllers\Web\PartyController;
use App\Services\CaptchaService;
use Azera\Http\Request;
use Azera\Http\Session;
use PHPUnit\Framework\TestCase;

/**
 * The security image on the party submission form.
 *
 * The legacy form had none, which is how 19 blank drafts accumulated in the
 * table: `?part=edit&party_id=new` inserted a row before anything was typed,
 * and nothing stood between a script and that page view. The ported form is a
 * plain GET, so the row cannot be created without a POST - and the POST carries
 * the same picture the song request form uses.
 *
 * Two things are pinned here that the request form does not need:
 *
 *  - the party form and the song request form do not share a session key. Both
 *    images are fetched by the browser, and one key would mean whichever is
 *    opened last invalidates the other's code.
 *  - a wrong code re-renders the form with the values still in the controls. The
 *    page is a dozen fields, two dates and a file input; losing them is worse
 *    than the failed submission.
 *
 * Database-backed cases skip themselves when the server is unreachable.
 */
class PartyCaptchaTest extends TestCase
{
    private array $store;
    private Session $session;
    private CaptchaService $captcha;
    private ?Session $previousSession = null;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->captcha = new CaptchaService(self::fontPath());
        $this->store   = [];
        $this->session = new Session($this->store);

        $ctx = AppContext::instance();

        // The dispatcher's SessionMiddleware overwrites the context session with
        // one over `$_SESSION` on every dispatch, so the tests that go through it
        // read the real superglobal. Clearing it gives each case a clean jar.
        $_SESSION = [];
        $this->previousSession = $ctx->session();

        $ctx->setSession($this->session);
        $ctx->set(CaptchaService::class, fn() => $this->captcha);

        try {
            $ctx->dbManager()->getDefault()->query('SELECT 1 FROM `party` LIMIT 1');
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        AppContext::instance()->setSession($this->previousSession ?? new Session($this->store));

        $_SESSION = [];
        $_POST    = [];
    }

    public function testTheFormRendersTheSecurityImage(): void
    {
        $body = $this->dispatch('GET', '/party/new')->getBody();

        self::assertStringContainsString('name="code"', $body);
        self::assertMatchesRegularExpression('!src="/party/code\.png\?t=\d+"!', $body);
        self::assertStringContainsString('To verify your input please enter the code shown above', $body);
        self::assertStringContainsString('maxlength="5"', $body, 'the legacy image held five digits');
        self::assertStringContainsString('autocomplete="off"', $body);
    }

    /** The form emits only an `<img>`; the code is written when it is fetched. */
    public function testTheImageEndpointBindsTheCodeToThePartyKey(): void
    {
        $response = $this->dispatch('GET', '/party/code.png');

        self::assertSame(200, $response->getStatus());
        self::assertSame('image/png', $response->getHeader('Content-Type'));
        self::assertSame('no-store, no-cache, must-revalidate', $response->getHeader('Cache-Control'));

        self::assertArrayNotHasKey(
            CaptchaService::SESSION_KEY,
            $_SESSION,
            'the request form key must stay untouched'
        );

        $stored = $_SESSION[CaptchaService::PARTY_SESSION_KEY] ?? null;

        self::assertIsArray($stored);
        self::assertMatchesRegularExpression('/^[0-9]{5}$/', (string) $stored['code']);

        $info = getimagesizefromstring($response->getBody());

        self::assertIsArray($info);
        self::assertSame(100, $info[0]);
        self::assertSame(30, $info[1]);

        self::assertSame(
            CaptchaService::PARTY_EXPIRY_SECONDS,
            (int) $stored['expires'] - time(),
            'the party form gets an hour, not securimage fifteen minutes'
        );
    }

    public function testAWrongCodeRejectsTheSubmissionAndKeepsTheValues(): void
    {
        $this->captcha->issue(
            $this->session,
            CaptchaService::PARTY_SESSION_KEY,
            CaptchaService::PARTY_EXPIRY_SECONDS
        );

        $response = $this->post($this->valid() + ['code' => '00000']);
        $body     = $response->getBody();

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString('The code you entered did not match the image.', $body);
        self::assertStringContainsString('value="Port Test Party"', $body);
        self::assertStringContainsString('value="Munich"', $body);
        self::assertStringContainsString('value="Port Test"', $body);
        self::assertMatchesRegularExpression(
            '!src="/party/code\.png\?t=\d+"!',
            $body,
            'a rejected submission has to draw a fresh image'
        );
    }

    /**
     * A code issued for the song request form must not unlock the party form.
     * The two keys exist precisely so one form cannot consume the other's code.
     */
    public function testTheRequestFormsCodeDoesNotUnlockThePartyForm(): void
    {
        $code = $this->captcha->issue($this->session);

        $response = $this->post($this->valid() + ['code' => $code]);

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString('did not match the image', $response->getBody());
    }

    public function testAMissingCodeIsRejected(): void
    {
        $this->captcha->issue(
            $this->session,
            CaptchaService::PARTY_SESSION_KEY,
            CaptchaService::PARTY_EXPIRY_SECONDS
        );

        $response = $this->post($this->valid());

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString('did not match the image', $response->getBody());
    }

    /**
     * The field checks come first: a visitor who mistyped a date is not sent
     * back to retype the code over a typo. The page marks the offending label
     * red rather than printing the message, so the absence of the code message
     * is the observable part.
     */
    public function testFieldErrorsAreReportedBeforeTheCode(): void
    {
        $this->captcha->issue(
            $this->session,
            CaptchaService::PARTY_SESSION_KEY,
            CaptchaService::PARTY_EXPIRY_SECONDS
        );

        $response = $this->post(array_replace($this->valid(), ['title' => 'ab', 'code' => '00000']));
        $body     = $response->getBody();

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString(
            'There are some errors. Please look at the red labeled fields.',
            $body
        );
        self::assertStringNotContainsString(
            'did not match the image',
            $body,
            'the code is only checked once the fields are valid'
        );
    }

    /**
     * The code is checked *before* the credentials. Otherwise an edit could be
     * used to test a password without ever solving an image, and the captcha
     * would protect nothing on the one branch that overwrites a row.
     */
    public function testTheCodeIsCheckedBeforeTheCredentials(): void
    {
        $db = AppContext::instance()->dbManager()->getDefault();
        $id = 0;

        try {
            $id = $this->createParty($db);

            $response = $this->post(
                array_replace($this->valid(), ['password' => 'wrong-password', 'code' => '00000']),
                '/party/' . $id . '/edit'
            );

            self::assertSame(400, $response->getStatus());
            self::assertStringContainsString('did not match the image', $response->getBody());
            self::assertStringNotContainsString(
                'Your personal data is not correct.',
                $response->getBody(),
                'a wrong password must not be distinguishable until the code is solved'
            );
        } finally {
            if ($id !== 0) {
                $db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
            }
        }
    }

    public function testACorrectCodeCreatesThePartyAndConsumesTheCode(): void
    {
        $code = $this->captcha->issue(
            $this->session,
            CaptchaService::PARTY_SESSION_KEY,
            CaptchaService::PARTY_EXPIRY_SECONDS
        );

        $db = AppContext::instance()->dbManager()->getDefault();
        $id = 0;

        try {
            $response = $this->post($this->valid() + ['code' => $code]);

            self::assertSame(303, $response->getStatus());
            self::assertMatchesRegularExpression('!^/party/\d+$!', (string) $response->getHeader('Location'));

            $id = (int) basename((string) $response->getHeader('Location'));

            self::assertSame('Port Test Party', $db->query(
                'SELECT `title` FROM `party` WHERE `id` = ?',
                [$id]
            )->fetchColumn());

            self::assertNull(
                $this->captcha->peek($this->session, CaptchaService::PARTY_SESSION_KEY),
                'a delivered code is single use'
            );
        } finally {
            if ($id !== 0) {
                $db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
            }
        }
    }

    /**
     * The edit form carries the same gate. Without it the one branch that can
     * overwrite an existing row would be the unprotected one.
     */
    public function testTheEditFormAlsoAsksForTheCode(): void
    {
        $db = AppContext::instance()->dbManager()->getDefault();
        $id = 0;

        try {
            $id = $this->createParty($db);

            $response = $this->dispatch('GET', '/party/' . $id . '/edit');

            self::assertSame(200, $response->getStatus());
            self::assertStringContainsString('name="code"', $response->getBody());

            $this->captcha->issue(
                $this->session,
                CaptchaService::PARTY_SESSION_KEY,
                CaptchaService::PARTY_EXPIRY_SECONDS
            );

            $rejected = $this->post(
                $this->valid() + ['code' => '00000'],
                '/party/' . $id . '/edit'
            );

            self::assertSame(400, $rejected->getStatus());
            self::assertStringContainsString('did not match the image', $rejected->getBody());
        } finally {
            if ($id !== 0) {
                $db->query('DELETE FROM `party` WHERE `id` = ?', [$id]);
            }
        }
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function dispatch(string $method, string $path): \Azera\Http\Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $path;

        // The context memoises Request, so a previous case's POST would
        // otherwise still be the request this GET is dispatched with - and the
        // CSRF middleware would answer 419 for a page that was only read.
        AppContext::instance()->set(Request::class, static fn() => new Request($_SERVER, [], []));

        $route = AppContext::instance()->router()->match($path, $method);

        self::assertNotNull($route, "no route matches {$method} {$path}");

        return AppContext::instance()->dispatcher()->dispatch($route);
    }

    /** POST the form as a browser would, to `$action` or the create route. */
    private function post(array $post, string $action = '/party/new'): \Azera\Http\Response
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = $action;

        AppContext::instance()->set(Request::class, static fn() => new Request($_SERVER, [], $post));

        $controller = new PartyController();

        return $action === '/party/new'
            ? $controller->createAction()
            : $controller->updateAction((int) basename(dirname($action)));
    }

    private function createParty(\Azera\Db\Database $db): int
    {
        $service = new \App\Services\PartyService(
            $db,
            'Europe/Berlin',
            sys_get_temp_dir() . '/hbr1-party-flyer-test'
        );

        return $service->create($this->valid());
    }

    /** @return array<string, string> */
    private function valid(): array
    {
        $start = new \DateTimeImmutable('+60 days', new \DateTimeZone('Europe/Berlin'));

        return [
            'title'                => 'Port Test Party',
            'type'                 => 'Club',
            'country'              => 'DE',
            'city'                 => 'Munich',
            'location'             => 'Some club',
            'djs'                  => 'DJ One',
            'live'                 => '',
            'chillout'             => '',
            'deco'                 => '',
            'memo'                 => 'See you there',
            'organizer'            => 'Org',
            'email'                => 'org@example.com',
            'url'                  => 'http://example.com',
            'hotline'              => '+49 89 123',
            'submitter'            => 'Port Test',
            'password'             => 'secret123',
            'password2'            => 'secret123',
            'start_timestamp_date' => $start->format('n/j/y'),
            'start_timestamp_time' => '22:00',
            'end_timestamp_date'   => $start->modify('+1 day')->format('n/j/y'),
            'end_timestamp_time'   => '06:00',
        ];
    }

    private static function fontPath(): string
    {
        return dirname(__DIR__) . '/data/security.ttf';
    }
}
