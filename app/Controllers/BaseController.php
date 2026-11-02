<?php

namespace App\Controllers;

use App\AppContext;
use Azera\Core\Controller;
use Azera\Db\Paginator;
use Azera\Security\CsrfMiddleware;

/**
 * Shared helpers for application controllers.
 *
 * The rule from the migration plan lives here: controllers never run SQL.
 * They call a service, receive plain arrays/entities and hand them to a view.
 */
abstract class BaseController extends Controller
{
    protected function config(): array
    {
        /** @var AppContext $ctx */
        $ctx = $this->context();

        return $ctx->Config;
    }

    protected function service(string $class): object
    {
        return $this->context()->get($class);
    }

    /**
     * Render a view inside the shared layout.
     */
    protected function page(string $view, array $vars = []): string
    {
        $config = $this->config();
        $root   = $config['paths']['legacy_docs'] ?? (BASE_DIR . '/public');

        // Static assets are served straight from the legacy tree by the web
        // server, so a cached copy survives a stylesheet change. The legacy
        // templates pinned `client.css?1`; deriving the token from the mtime
        // does the same job without anyone having to remember to bump it.
        $css = $root . '/style/client.css';

        return $this->view()->render($view, $vars + [
            'clientCssVersion' => is_file($css) ? (string) filemtime($css) : null,
        ]);
    }

    /**
     * Render a bare fragment, without the layout (frames, playlist files,
     * protocol responses).
     */
    protected function fragment(string $view, array $vars = []): string
    {
        return $this->view()->renderPartial($view, $vars);
    }

    /**
     * Paginator metadata in the shape the legacy templates expected, so the
     * ported views can be compared against the original output.
     */
    protected function paginationMeta(Paginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage'    => $paginator->lastPage(),
            'totalItems'  => $paginator->totalItems(),
            'pageSize'    => $paginator->pageSize(),
            'firstItem'   => $paginator->firstItem(),
            'lastItem'    => $paginator->lastItem(),
            'hasPrevious' => $paginator->hasPrevious(),
            'hasNext'     => $paginator->hasNext(),
            'previous'    => $paginator->previousPage(),
            'next'        => $paginator->nextPage(),
        ];
    }

    /**
     * The CSRF token for a form in the current session.
     *
     * CsrfMiddleware verifies every POST globally, so a form that does not
     * echo this will be rejected. Empty string when there is no session, which
     * only happens outside a web request.
     */
    protected function csrfToken(): string
    {
        $session = $this->context()->session();

        return $session === null ? '' : (new CsrfMiddleware())->ensureToken($session);
    }
}
