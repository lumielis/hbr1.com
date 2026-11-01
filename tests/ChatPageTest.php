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

    public function testFramesetMatchesProduction(): void
    {
        foreach (['/chat', '/chat/'] as $path) {
            $body = $this->page($path);

            self::assertStringContainsString('<frameset rows="26,*,26,29"', $body, $path);
            self::assertStringContainsString('<frameset cols="64,*,100,64"', $body, $path);
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

    public function testUsersFrameIsTheLegacyEmptyShell(): void
    {
        $body = $this->page('/chat/users');

        self::assertStringContainsString('<title>HBR1 Chat Users</title>', $body);
        self::assertStringContainsString('<meta http-equiv="refresh" content="9">', $body);
        self::assertStringContainsString('background-color: #100040', $body);
        self::assertStringContainsString('<table', $body);
    }

    public function testSubmitFrameHasTheLegacyForm(): void
    {
        $body = $this->page('/chat/submit');

        self::assertStringContainsString('<form action="/chat" method="post"', $body);
        self::assertStringContainsString('name="_csrf_token"', $body);
        self::assertStringContainsString('name="name"', $body);
        self::assertStringContainsString('name="content"', $body);
        self::assertStringContainsString('value="OK"', $body);
        self::assertStringContainsString('size="10"', $body);
        self::assertStringContainsString('size="40"', $body);
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

        self::assertStringContainsString('<form action="/chat"', $body);
        self::assertStringContainsString('value="Tester"', $body);
        self::assertStringNotContainsString('<frameset', $body);
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
        self::assertStringContainsString('<form action="/chat"', $response->getBody());
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
