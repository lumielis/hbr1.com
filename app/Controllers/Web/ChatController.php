<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\ChatService;
use Azera\Http\Response;

/**
 * The chat terminal (replaces `cgi-bin/chat.cgi` and the `/chat/` frameset).
 *
 * Production is a four-band frameset: a 26 px header, a column split of
 * 64 px / flexible / 100 px / 64 px holding the chrome, the message pane and
 * the user list, a 26 px footer, and a 29 px submit bar. The chrome frames are
 * static files under
 * `/chat/`; the three dynamic frames used to be
 * `apax.cgi?Part=Chat&Action=…`. They are canonical routes here, and the
 * message pane renders the `chat` table live instead of the stale generated
 * `htdocs/html/chat_content.html`.
 *
 * The terminal itself is a CSS grid rather than a frameset. A frameset's row
 * heights are frameset units that page CSS cannot reach, so once the site
 * scaled itself to 1.333 the frames inside grew while their rows stayed at
 * 26/29 raw pixels and clipped. The grid keeps production's geometry but
 * scales as one document; see `views/web/chat_terminal.clarity.html`.
 */
class ChatController extends BaseController
{
    /** The terminal. */
    public function indexAction(): string
    {
        return $this->fragment('web/chat_terminal');
    }

    /** The message pane (`chat_main`), refreshed by the frame's meta tag. */
    public function contentAction(): string
    {
        /** @var ChatService $service */
        $service = $this->service(ChatService::class);

        return $this->fragment('web/chat_content', [
            'messages' => $service->recent(50),
        ]);
    }

    /** The user list (`chat_users`), refreshed by the frame's meta tag. */
    public function usersAction(): string
    {
        /** @var ChatService $service */
        $service = $this->service(ChatService::class);

        return $this->fragment('web/chat_users', [
            'users' => $service->online(),
        ]);
    }

    /** The 29 px submit bar (`chat_submit`). */
    public function formAction(): string
    {
        return $this->fragment('web/chat_submit', [
            'values' => ['name' => $this->savedName(), 'content' => ''],
            'error'  => '',
            'posted' => false,
            'csrf'   => $this->csrfToken(),
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
            return Response::html($this->fragment('web/chat_submit', [
                'values' => $input,
                'error'  => (string) reset($errors),
                'posted' => false,
                'csrf'   => $this->csrfToken(),
            ]), 400);
        }

        $service->post($input, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        // The legacy form saved the nickname in a cookie so regulars do not
        // retype it. Same name, same lifetime, same path.
        setcookie('chat_name', trim($input['name']), [
            'expires'  => time() + 86400 * 365,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        // The form is the 29 px bottom frame; redirecting to /chat would load
        // the frameset *inside* that frame. Production re-served the submit
        // bar and told the other two frames to reload; `posted` is what makes
        // the bar emit that script.
        return $this->fragment('web/chat_submit', [
            'values' => ['name' => trim($input['name']), 'content' => ''],
            'error'  => '',
            'posted' => true,
            'csrf'   => $this->csrfToken(),
        ]);
    }

    private function savedName(): string
    {
        $name = $_COOKIE['chat_name'] ?? '';

        return is_string($name) && $name !== '' ? $name : 'Nickname';
    }
}
