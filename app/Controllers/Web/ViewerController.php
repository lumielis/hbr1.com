<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Http\LegacyStatic;
use Azera\Http\Response;

/**
 * The `vimg.cgi` image viewer ("IView").
 *
 * Every DJ portrait, artist photo and party flyer on the legacy pages is a
 * link through this script: about 1,300 of them across `/djs.htm`,
 * `/artists.html` and the front page. The port had no route for it, so all of
 * those links were 404s.
 *
 * The legacy script printed the query string straight into an `<img src>`
 * attribute, which is why it could also be pointed at any URL on the internet.
 * Here the target is resolved through `LegacyStatic`, so only files that
 * actually exist in the document root are ever shown, and only with a static
 * extension.
 *
 * The page itself is a bare fragment in its own window: no site frame, black
 * background, click the image to go back.
 */
class ViewerController extends BaseController
{
    public function imageAction(string ...$path): string|Response
    {
        $target = implode('/', $path);

        $root = $this->config()['paths']['legacy_docs'] ?? \dirname(__DIR__, 3) . '/public';

        $file = (new LegacyStatic($root))->resolve('/' . $target);

        if ($file === null) {
            return Response::status(404);
        }

        return $this->fragment('web/viewer', [
            'title' => $this->label($target) . ' - IView v1.0.3 - (c) HBR1 2003',
            'name'  => $this->label($target),
            'href'  => '/' . ltrim($target, '/'),
        ]);
    }

    /**
     * The legacy caption: the file name without the directory, and without a
     * leading `NN_` ordering prefix (`05_maya.jpg` -> `maya.jpg`).
     */
    private function label(string $path): string
    {
        $name = basename($path);

        return preg_replace('/^\d+_/', '', $name) ?? $name;
    }
}
