<?php

namespace App\Http;

use Azera\Http\Response;

/**
 * Serves the legacy static tree that still lives outside `public/`.
 *
 * The old document root was `htdocs/` itself, so pages like `/ltop.htm`,
 * `/djs.htm` and `/link_base.htm` were plain files. The new root is `public/`,
 * and the web server only aliases the asset directories (`/images`, `/style`,
 * …). The frameset's own frames are *not* in an aliased directory, so without
 * this they 404 and the front page renders four broken panes.
 *
 * Only files that exist are served, and only with a static extension - the
 * legacy root also contains `.pl`, `.inc` and `.pab.pl` files that used to be
 * readable as plain text, and that is exactly the leak Phase 0 closes.
 */
final class LegacyStatic
{
    /** Extensions that were always static and are safe to serve. */
    private const TYPES = [
        'htm'  => 'text/html',
        'html' => 'text/html',
        'txt'  => 'text/plain',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'xml'  => 'application/xml',
        'gif'  => 'image/gif',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'ico'  => 'image/x-icon',
        'swf'  => 'application/x-shockwave-flash',
        'class' => 'application/java-vm',
        'm3u'  => 'audio/x-mpegurl',
        'asx'  => 'video/x-ms-asf',
        'pls'  => 'audio/x-scpls',
        'xspf' => 'application/xspf+xml',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
    ];

    public function __construct(private string $root)
    {
    }

    /**
     * Resolve a URL path to a file in the legacy tree, or null.
     *
     * Rejects anything that escapes the root, any dotfile segment, and any
     * extension that is not on the static list.
     */
    public function resolve(string $path): ?array
    {
        $path = rawurldecode($path);

        // Reject traversal outright rather than trying to normalise it away.
        if (str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return null;
        }

        // Dotfiles (.htpasswd, .git, editor droppings) are never public.
        if (preg_match('#(^|/)\.#', $path) === 1) {
            return null;
        }

        $relative = ltrim($path, '/');

        if ($relative === '') {
            return null;
        }

        // A directory request resolves to its index file. The legacy tree
        // relies on this: the frameset loads `/webtv/ticker/` and expects
        // `index.html`. Apache's DirectoryIndex did it; this reproduces it.
        if (str_ends_with($relative, '/') || is_dir($this->root . '/' . $relative)) {
            $relative = rtrim($relative, '/') . '/index.html';
        }

        if (!str_contains($relative, '.')) {
            return null;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        if (!isset(self::TYPES[$extension])) {
            return null;
        }

        $file = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_file($file)) {
            return null;
        }

        return [
            'file' => $file,
            'type' => self::TYPES[$extension],
            'mtime' => (int) filemtime($file),
        ];
    }

    public function response(string $path): ?Response
    {
        $found = $this->resolve($path);

        if ($found === null) {
            return null;
        }

        $response = new Response(200, [], (string) file_get_contents($found['file']));
        $response->setHeader('Content-Type', $found['type']);
        $response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $found['mtime']) . ' GMT');

        return $response;
    }
}
