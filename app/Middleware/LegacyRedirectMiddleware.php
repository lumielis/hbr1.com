<?php

namespace App\Middleware;

use App\Http\LegacyUrlMapper;
use Azera\AppContext;
use Azera\Core\MiddlewareInterface;
use Azera\Http\Response;

/**
 * Legacy URL compatibility, as pipeline middleware.
 *
 * The front controller already resolves legacy URLs before routing (so the
 * redirect happens even though no route would match). This middleware covers
 * the case where a legacy path does reach the pipeline, and keeps the
 * behaviour available to any other entry point.
 */
class LegacyRedirectMiddleware implements MiddlewareInterface
{
    public function process(AppContext $context, callable $next): ?Response
    {
        $request = $context->request();
        $query   = $request->query();
        $target  = LegacyUrlMapper::resolve(
            $request->path(),
            is_array($query) ? $query : []
        );

        if ($target === null) {
            return $next($context);
        }

        return Response::redirect($target, 301);
    }
}
