<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use Azera\Http\Response;

/**
 * Placeholder for the administration area, which is W8.
 *
 * `/admin` used to fall through to `htdocs/admin/index.html`, the legacy
 * frameset. Its content frame is `/cgi-bin/admin.cgi`, which the URL mapper
 * sends back to `/admin` - so the page nested itself as deep as the browser
 * allowed, and showed the old admin chrome with no authentication in front of
 * it. Until the real admin is built, say so plainly.
 *
 * Production answers 401 here (an `.htpasswd` gate that this build does not
 * reproduce yet), which is why the legacy links into `/admin` were not
 * obviously broken.
 */
class AdminController extends BaseController
{
    public function indexAction(): string
    {
        return $this->page('web/admin_pending', [
            'title' => 'HBR1 Administration',
        ]);
    }
}
