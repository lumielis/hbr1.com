<?php

namespace App\Tests;

use App\AppContext;
use App\Services\ChatService;
use Azera\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * The chat terminal against production.
 *
 * Production reference: `https://hbr1.com/chat/`, a frameset with a 26 px
 * header, a 64+*+100+64 column split, a 26 px footer and a 29 px submit bar.
 * Its dynamic frames are `apax.cgi?Part=Chat&Action=…`; the port points them at
 * canonical routes and renders the messages from the `chat` table instead of
 * the stale generated `/html/chat_content.html`.
 *
 * Skipped automatically when the database is unreachable.
 */
class ChatPageTest extends TestCase
{
    private ChatService $chat;
    private array $message;

    protected function setUp(): void
    {
        \App\Bootstrap::bootstrap();

        $this->chat = AppContext::instance()->get(ChatService::class);

        try {
            $recent = $this->chat->recent(1);
        } catch (\Throwable $e) {
            self::markTestSkipped('Database unavailable: ' . $e->getMessage());
        }

        self::assertNotEmpty($recent, 'the local database must hold chat messages');

        $this->message = $recent[0];
    }

    public function testTerminalKeepsProductionGeometryAsAGrid(): void
    {
        foreach (['/chat', '/chat/'] as $path) {
            $body = $this->page($path);

            self::assertStringNotContainsString('<frameset', $body, $path);
            self::assertStringContainsString('class="terminal"', $body, $path);
            self::assertStringContainsString('grid-template-rows: auto 1fr auto auto', $body, $path);
            self::assertStringContainsString('grid-template-columns: 77px 1fr 100px 77px', $body, $path);
            self::assertStringContainsString('src="/chat/top.htm?1"', $body, $path);
            self::assertStringContainsString('src="/chat/left.htm"', $body, $path);
            self::assertStringContainsString('src="/chat/right.htm"', $body, $path);
            self::assertStringContainsString('src="/chat/bottom.htm?1"', $body, $path);
            self::assertStringContainsString('name="chat_main" src="/chat/content"', $body, $path);
            self::assertStringContainsString('name="chat_users" src="/chat/users"', $body, $path);
            self::assertStringContainsString('name="chat_submit" src="/chat/submit"', $body, $path);
            self::assertStringNotContainsString('apax.cgi', $body, $path);
            self::assertStringNotContainsString('/html/chat_content.html', $body, $path);
        }
    }

    /**
     * A frameset's row heights are frameset units that page CSS cannot reach,
     * so once the site scaled itself the frames grew inside rows that stayed at
     * 26/29 raw pixels and clipped the submit bar. The grid avoids that: the
     * rows are sized by the zoomed content, and the tracks are pre-multiplied
     * so the chrome panes still fill them.
     */
    public function testTerminalTracksAreScaledAndTheShellIsNot(): void
    {
        $body = $this->page('/chat');

        // The shell must not inherit client.css's body zoom, or the tracks
        // would be multiplied twice.
        self::assertStringNotContainsString('/style/client.css', $body);

        // 64 * 1.2 = 76.8, 100 * 1.2 = 120. Whole pixels are fine here because
        // the column is filled, not measured.
        self::assertStringContainsString('grid-template-columns: 77px 1fr 100px 77px', $body);

        // left.htm and right.htm have no stylesheet, so they are scaled here.
        self::assertStringContainsString('.pane-left, .pane-right {', $body);
        self::assertMatchesRegularExpression(
            '~\.pane-left,\s*\.pane-right\s*\{[^}]*zoom:\s*1\.2~s',
            $body
        );
    }

    public function testContentFrameUsesTheLegacyLineFormat(): void
    {
        $body = $this->page('/chat/content');

        self::assertStringContainsString('<title>HBR1 Chat</title>', $body);
        self::assertStringContainsString('<meta http-equiv="refresh" content="9">', $body);
        self::assertStringContainsString('background-color: #100040', $body);
        self::assertStringContainsString('class="chat-time"', $body);
        self::assertStringContainsString('class="chat-name"', $body);
        self::assertStringContainsString('color: #' . $this->message['color'], $body);
        self::assertStringContainsString($this->message['name'] . ': ', $body);
        self::assertStringContainsString($this->message['content'], $body);

        $expected = (new \DateTimeImmutable('@' . $this->message['timestamp']))
            ->setTimezone(new \DateTimeZone('Europe/Berlin'))
            ->format('m/d H:i');

        self::assertStringContainsString($expected, $body);
    }

    public function testUsersFrameListsWhoIsInTheRoom(): void
    {
        $body = $this->page('/chat/users');

        self::assertStringContainsString('<title>HBR1 Chat Users</title>', $body);
        self::assertStringContainsString('<meta http-equiv="refresh" content="9">', $body);
        self::assertStringContainsString('background-color: #100040', $body);
        self::assertStringContainsString('<table class="chat-users"', $body);

        // The pane is no longer the empty shell production served; the rows
        // themselves are rendered from ChatService::online(), which is pinned
        // against a real table in ChatOnlineTest. Here the guarantee is that
        // the frame is filled from that list rather than hard-coded.
        foreach ($this->chat->online() as $user) {
            self::assertStringContainsString('style="color: #' . $user['color'] . '"', $body);
            self::assertStringContainsString('data-nickname="' . $user['name'] . '"', $body);
        }

        self::assertStringContainsString('function chatUserInfo(', $body);
        self::assertStringNotContainsString('<tr><td></td></tr>', $body);
    }

    public function testSubmitFramePinsTheBarToTheBottomOfTheViewport(): void
    {
        // The bar is the bottom row of a full-height table. Production got that
        // for free because quirks mode propagates `height: 100%` from <body>
        // through the <form> wrapping the rows. Standards mode does not
        // propagate through a form, so the chain has to be explicit or the
        // table collapses to its 29 px content height and sits at the top.
        $body = $this->page('/chat/submit');

        self::assertStringContainsString('html, body {', $body);
        self::assertMatchesRegularExpression(
            '/html,\s*body\s*\{[^}]*height:\s*100%/s',
            $body,
            'html/body must be able to resolve a percentage height'
        );
        self::assertStringContainsString('<form action="/chat/submit" method="post" class="chat-form">', $body);
        self::assertMatchesRegularExpression(
            '/\.chat-form\s*\{[^}]*height:\s*100%/s',
            $body,
            'the form is the link that quirks mode gave for free'
        );
        self::assertMatchesRegularExpression(
            '/\.chat-submit\s*\{[^}]*height:\s*100%/s',
            $body,
            'the table still asks for the full height'
        );
    }

    public function testSubmitFrameAlignsTheChromeTabsToTheTop(): void
    {
        // The four chrome gifs are not uniformly aligned: inpl/inpr are 29 px
        // and fill the bar, inptl/inptr are 9 px tabs that hug the top. A
        // blanket `vertical-align: bottom` on every cell pushed the tabs to
        // y=20 and broke the tab silhouette.
        $body = $this->page('/chat/submit');

        self::assertSame(
            2,
            preg_match_all('/class="chat-tab"/', $body),
            'both 9 px tabs are marked'
        );
        self::assertSame(
            2,
            preg_match_all('/class="chat-corner"/', $body),
            'both 29 px end pieces are marked'
        );
        self::assertMatchesRegularExpression(
            '/td\.chat-tab\s*\{[^}]*vertical-align:\s*top/s',
            $body
        );
        self::assertMatchesRegularExpression(
            '/td\.chat-corner\s*\{[^}]*vertical-align:\s*baseline/s',
            $body,
            'the end pieces reset the inherited bottom, since vertical-align does not inherit'
        );
    }

    public function testSubmitFrameHasTheLegacyForm(): void
    {
        $body = $this->page('/chat/submit');

        self::assertStringContainsString('<form action="/chat/submit" method="post"', $body);
        self::assertStringContainsString('name="_csrf_token"', $body);
        self::assertStringContainsString('name="name"', $body);
        self::assertStringContainsString('name="content"', $body);
        self::assertStringContainsString('value="OK"', $body);
        self::assertStringContainsString('size="10"', $body);
        self::assertStringContainsString('size="39"', $body);
        self::assertStringContainsString('maxlength="255"', $body);
    }

    public function testSuccessfulPostReturnsTheSubmitBarNotTheFrameset(): void
    {
        $context = AppContext::instance();
        $real    = $context->get(ChatService::class);
        $fake    = new class ($context->dbManager()->getDefault()) extends ChatService {
            public function post(array $input, string $remoteAddr): int
            {
                return 1;
            }
        };

        $context->set(ChatService::class, static fn() => $fake);
        $context->set(Request::class, static fn() => new Request($_SERVER, [], [
            'name'    => 'Tester',
            'content' => 'Hello',
        ]));

        try {
            $response = (new \App\Controllers\Web\ChatController())->submitAction();
        } finally {
            $context->set(ChatService::class, static fn() => $real);
        }

        $body = is_string($response) ? $response : $response->getBody();

        self::assertStringContainsString('<form action="/chat/submit"', $body);
        self::assertStringContainsString('value="Tester"', $body);
        self::assertStringNotContainsString('<frameset', $body);

        // Production's submit frame reloaded the message pane and the user
        // list after a post, so the new line and the poster's own nickname
        // appear at once instead of at the next 9 s refresh.
        self::assertStringContainsString("window.parent.frames['chat_main']", $body);
        self::assertStringContainsString("window.parent.frames['chat_users']", $body);
    }

    public function testEmptyPostRendersTheSubmitBarWithAnError(): void
    {
        $context = AppContext::instance();
        $context->set(Request::class, static fn() => new Request($_SERVER, [], [
            'name'    => '',
            'content' => '',
        ]));

        $response = (new \App\Controllers\Web\ChatController())->submitAction();

        self::assertSame(400, $response->getStatus());
        self::assertStringContainsString('Please enter a nickname.', $response->getBody());
        self::assertStringContainsString('<form action="/chat/submit"', $response->getBody());
        self::assertStringNotContainsString('window.parent.frames', $response->getBody());
    }

    public function testContentAndSubmitFramesAreWellFormed(): void
    {
        foreach (['/chat/content', '/chat/users', '/chat/submit'] as $path) {
            $html = $this->page($path);
            $html = preg_replace('!<script\b[^>]*>.*?</script>!is', '', $html) ?? $html;
            $html = preg_replace('!<style\b[^>]*>.*?</style>!is', '', $html) ?? $html;

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $document = new \DOMDocument();
            $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);

            $errors = array_map(
                static fn(\LibXMLError $e): string => trim($e->message) . ' (line ' . $e->line . ')',
                libxml_get_errors()
            );

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            self::assertSame([], $errors, $path . " must be well-formed:\n" . implode("\n", $errors));
        }
    }

    public function testChromeFramesStillResolve(): void
    {
        $static = new \App\Http\LegacyStatic(dirname(__DIR__) . '/htdocs');

        foreach (['/chat/top.htm', '/chat/left.htm', '/chat/right.htm', '/chat/bottom.htm'] as $path) {
            self::assertNotNull($static->resolve($path), $path . ' must still be served');
        }
    }

    private function page(string $path): string
    {
        $context = AppContext::instance();
        $route   = $context->router()->match($path, 'GET');

        self::assertNotNull($route, "no route matches {$path}");

        return $context->dispatcher()->dispatch($route)->getBody();
    }
}
