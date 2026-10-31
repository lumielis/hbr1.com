<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;

/**
 * The bottom navigation bar (replaces `/perl/apax.cgi?run=navigation`).
 *
 * The legacy template shipped nineteen image-map hotspots pointing at CGI
 * entry points. Two things about it needed care:
 *
 *  - Every destination is translated to its canonical route here, so the
 *    frame never emits a `/cgi-bin/` URL that would immediately redirect.
 *  - `PageID` is still set, because `client.navigation.htm` drives the
 *    "active" button through `parent.frames['bottom'].updatePage()`, and the
 *    ported pages still call it.
 *
 * Two buttons share one destination: "Tracklist" (`mb15`) and "Inventory"
 * (`mb6`) both open the station catalogue at `/mixes`, because HBR1 plays
 * mixes only and the two legacy pages listed the same data. The catalogue sets
 * `PageIDs` so the bar lights both.
 *
 * The image rollovers are kept as-is; they are static GIFs in the legacy
 * `images/navigation/` tree.
 */
class NavigationController extends BaseController
{
    /**
     * Rows of buttons, exactly as `template/client/navigation.htm` lays them
     * out: the first row is eight buttons, the second is eight plus the
     * `mbot.gif` filler that squares the bar off.
     *
     * The `width`/`height` values are the ones the legacy template declared,
     * which are *not* always the GIF's intrinsic size - `mb4n.gif` is 93 px
     * wide but was always shown at 95, `mb14n.gif` is 72x32 but was always
     * shown at 71x30. Reproducing the declared geometry is what keeps the bar
     * pixel-identical, so the numbers stay as they were.
     */
    private const ROWS = [
        [
            'height'  => 32,
            'valign'  => 'bottom',
            'filler'  => false,
            'buttons' => [
                ['key' => 'home', 'image' => 'mbhome', 'href' => '/', 'width' => 71, 'alt' => 'Home'],
                ['key' => 'guestbook', 'image' => 'mb1', 'href' => '/guestbook', 'width' => 68, 'alt' => 'Guestbook'],
                ['key' => 'events', 'image' => 'mb2', 'href' => '/events.html', 'width' => 83, 'alt' => 'Events'],
                ['key' => 'party', 'image' => 'mb3', 'href' => '/party', 'width' => 75, 'alt' => 'Party'],
                ['key' => 'listen', 'image' => 'mb4', 'href' => '/listen', 'width' => 95, 'alt' => 'Listen'],
                ['key' => 'upload', 'image' => 'mb5', 'href' => '/upload/', 'width' => 97, 'alt' => 'Upload'],
                ['key' => 'mixes', 'image' => 'mb15', 'href' => '/mixes', 'width' => 86, 'alt' => 'Tracklist'],
                ['key' => 'inventory', 'image' => 'mb6', 'href' => '/mixes', 'width' => 111, 'alt' => 'Inventory'],
            ],
        ],
        [
            'height'  => 30,
            'valign'  => 'top',
            'filler'  => true,
            'buttons' => [
                ['key' => 'djs', 'image' => 'mb7', 'href' => '/djs.htm', 'width' => 63, 'alt' => 'DJs'],
                ['key' => 'artists', 'image' => 'mb8', 'href' => '/artists.html', 'width' => 73, 'alt' => 'Artists'],
                ['key' => 'chat', 'image' => 'mb14', 'href' => '/chat', 'width' => 71, 'alt' => 'Chat'],
                ['key' => 'gallery', 'image' => 'mb10', 'href' => '/gallery', 'width' => 81, 'alt' => 'Gallery'],
                ['key' => 'playlist', 'image' => 'mb11', 'href' => '/playlist', 'width' => 94, 'alt' => 'Playlist'],
                ['key' => 'links', 'image' => 'mb16', 'href' => '/link_base.htm', 'width' => 86, 'alt' => 'Links'],
                ['key' => 'contact', 'image' => 'mb12', 'href' => '/contact.htm', 'width' => 66, 'alt' => 'Contact'],
                ['key' => 'shop', 'image' => 'mb13', 'href' => '/shop/', 'width' => 65, 'alt' => 'Shop'],
            ],
        ],
    ];

    public function indexAction(): string
    {
        return $this->fragment('web/navigation', [
            'title' => 'Navigation',
            'rows'  => self::ROWS,
        ]);
    }
}
