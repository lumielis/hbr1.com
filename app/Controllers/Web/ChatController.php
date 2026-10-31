<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\ChatService;
use Azera\Http\Response;

/**
 * Chat room (replaces `cgi-bin/chat.cgi`).
 *
 * The legacy page wrote a rendered copy of the room to
 * `htdocs/html/chat.html` on every post and let a meta-refresh poll it. That
 * file is still served as a static asset, so the ported page does the same
 * thing: it renders live from the database but keeps the file in step, which
 * is what the frameset and any bookmark of `/chat/chat.html` expect.
 */
class ChatController extends BaseController
{
    public function indexAction(): string
    {
        /** @var ChatService $service */
        $service = $this->service(ChatService::class);

        return $this->page('web/chat', [
            'title'    => 'Chat - HBR1',
            'messages' => $service->recent(50),
            'errors'   => ['name' => '', 'content' => ''],
            'values'   => ['name' => $this->savedName()],
            'posted'   => false,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function submitAction(): Response|string
    {
        /** @var ChatService $service */
        $service = $this->service(ChatService::class);

        $request = $this->context()->request();
        $input   = [
            'name'    => (string) ($request->post('name') ?? ''),
            'content' => (string) ($request->post('content') ?? ''),
        ];

        $errors = $service->validate($input);

        if ($errors !== []) {
            return Response::html($this->page('web/chat', [
                'title'    => 'Chat - HBR1',
                'messages' => $service->recent(50),
                'errors'   => ['name' => (string) ($errors['name'] ?? ''), 'content' => (string) ($errors['content'] ?? '')],
                'values'   => $input,
                'posted'   => false,
                'csrf'     => $this->csrfToken(),
            ]), 400);
        }

        $service->post($input, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        // The legacy page saved the nickname in a cookie so regulars do not
        // retype it. Same name, same lifetime, same path.
        setcookie('chat_name', trim($input['name']), [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        return Response::redirect('/chat');
    }

    private function savedName(): string
    {
        $name = $_COOKIE['chat_name'] ?? '';

        return is_string($name) && $name !== '' ? $name : 'Nickname';
    }
}
