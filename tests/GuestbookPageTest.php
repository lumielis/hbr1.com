<?php

namespace App\Tests;

use App\AppContext;
use App\Controllers\Web\GuestbookController;
use App\Services\GuestbookService;
use Azera\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * The guestbook against production.
 *
 * The live page is `apax.cgi?run=gbms&sid=…`; it is unreachable without the
 * legacy `sid` handshake, so `temp/prod-guestbook.html` and
 * `temp/prod-guestbook-submit.html` were captured with a freshly minted sid.
 * They are the reference for the markup below.
 *
 * Skipped automatically when the database is unreachable.
 */
class GuestbookPageTest extends TestCase
{
    private GuestbookService $guestbook;
    private array $entry;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->guestbook = AppContext::instance()->get(GuestbookService::class);

        try {
            $page = $this->guestbook->page(0, 1);
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        self::assertNotEmpty($page['entries'], 'the local database must hold guestbook entries');

        $this->entry = $page['entries'][0];
    }

    public function testListingUsesTheLegacyHeadingAndIntro(): void
    {
        $body = $this->page('/guestbook');

        self::assertStringContainsString('<b>GUESTBOOK</b>', $body);
        self::assertStringContainsString('Welcome to our Guestbook', $body);
        self::assertStringContainsString(
            'Compliments and Criticism. Pleasures and Pain. The Guestbook is open for your comments.',
            $body
        );
        self::assertStringContainsString('Submit Your Comment', $body);
        self::assertStringContainsString('href="/guestbook/submit"', $body);
        self::assertStringContainsString('2001-2005', $body, 'the guestbook carried its own signature');
        self::assertStringContainsString('guestbook-well', $body, 'the legacy well was left-aligned');
    }

    public function testEntriesUseTheLegacyLayout(): void
    {
        $body = $this->page('/guestbook');

        self::assertStringContainsString('class="splitter"', $body);
        self::assertStringContainsString($this->entry['submitter'], $body);
        self::assertStringContainsString(' wrote at ', $body);
        self::assertStringContainsString(' (utc) following comment:', $body);
        self::assertStringContainsString('<blockquote class="Text"', $body);

        // Production formats with localtime (Europe/Berlin) but still labels
        // the value "(utc)". Reproduce the output, not the label's claim.
        $expected = (new \DateTimeImmutable('@' . $this->entry['timestamp']))
            ->setTimezone(new \DateTimeZone('Europe/Berlin'))
            ->format('D, d M Y H:i');

        self::assertStringContainsString($expected, $body);
    }

    public function testEntriesWithEmailUseTheObfuscatedMailtoLink(): void
    {
        $body = $this->page('/guestbook');

        self::assertStringContainsString('/jscript/crypt.js', $body);
        self::assertStringContainsString('function decryptEmail', $body);
        self::assertMatchesRegularExpression(
            '/<a href="mailto:[^"]*"[\s\S]*?onclick="return decryptEmail\(this,\'[0-9a-f]+\',' .
            '\'subject=your%20comment%20on%20the%20guestbook%20of%20hbr1\'\)"/',
            $body
        );
    }

    public function testPagerUsesTheLegacyNavMarkup(): void
    {
        $body = $this->page('/guestbook');

        self::assertStringContainsString('class="Nav_Current"', $body);
        self::assertStringContainsString('class="Nav_Page"', $body);
        self::assertStringContainsString('Older entries &gt;', $body);
        self::assertStringContainsString('href="/guestbook?offset=33"', $body);
        self::assertStringNotContainsString('Newer entries', $body, 'page 1 has nothing newer');
        self::assertStringContainsString('-THE END-', $body);
    }

    public function testSubmitPageUsesTheLegacyForm(): void
    {
        $body = $this->page('/guestbook/submit');

        self::assertStringContainsString('<b>GUESTBOOK</b>', $body);
        self::assertStringContainsString('Welcome to our Guestbook', $body);
        self::assertStringContainsString('Spam-Filter: Your E-Mail address will be protected', $body);
        self::assertStringContainsString('href="/guestbook"', $body);
        self::assertStringContainsString('Back to the Entries', $body);
        self::assertStringContainsString('Name:', $body);
        self::assertStringContainsString('E-Mail:', $body);
        self::assertStringContainsString('Comment:', $body);
        self::assertStringContainsString('name="submitter"', $body);
        self::assertStringContainsString('name="email"', $body);
        self::assertStringContainsString('name="content"', $body);
        self::assertStringContainsString('rows="8"', $body);
        self::assertStringContainsString('value="Submit"', $body);
    }

    public function testValidationErrorsRenderTheSubmitForm(): void
    {
        $post = ['submitter' => '', 'email' => 'not-an-email', 'content' => ''];
        $context = AppContext::instance();
        $context->set(Request::class, static fn() => new Request($_SERVER, [], $post));

        $response = (new GuestbookController())->submitAction();
        $body     = $response->getBody();

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString('Please enter your name.', $body);
        self::assertStringContainsString('This e-mail address does not look valid.', $body);
        self::assertStringContainsString('Please enter a message.', $body);
        self::assertStringContainsString('name="submitter"', $body);
        self::assertStringContainsString('value="not-an-email"', $body);
    }

    public function testGuestbookPagesAreWellFormedHtml(): void
    {
        foreach (['/guestbook', '/guestbook/submit'] as $path) {
            $this->assertWellFormed($path, $this->page($path));
        }
    }

    public function testGuestbookViewsUseNoObsoletePresentationalMarkup(): void
    {
        foreach (glob(dirname(__DIR__) . '/views/web/guestbook*.clarity.html') as $view) {
            $source = (string) preg_replace('!\{#.*?#\}!s', '', (string) file_get_contents($view));
            $source = strtolower($source);

            foreach (['<font', ' bgcolor=', ' align=', ' background=', ' cellspacing=', ' cellpadding=', ' border='] as $needle) {
                self::assertStringNotContainsString($needle, $source, basename($view) . ' still uses ' . $needle);
            }
        }
    }

    public function testLinksAreCanonical(): void
    {
        foreach (['/guestbook', '/guestbook/submit'] as $path) {
            $body = $this->page($path);

            self::assertStringNotContainsString('apax.cgi', $body);
            self::assertStringNotContainsString('cgi-bin', $body);
            self::assertStringNotContainsString('sid=', $body);
        }
    }

    private function page(string $path): string
    {
        $context = AppContext::instance();
        $route   = $context->router()->match($path, 'GET');

        self::assertNotNull($route, "no route matches {$path}");

        return $context->dispatcher()->dispatch($route)->getBody();
    }

    private function assertWellFormed(string $label, string $html): void
    {
        $html = preg_replace('!<script\b[^>]*>.*?</script>!is', '', $html) ?? $html;
        $html = preg_replace('!<style\b[^>]*>.*?</style>!is', '', $html) ?? $html;

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

        $errors = array_map(
            static fn(\LibXMLError $error): string => trim($error->message) . ' (line ' . $error->line . ')',
            libxml_get_errors()
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertSame([], $errors, $label . " must be well-formed:\n" . implode("\n", $errors));
    }
}
