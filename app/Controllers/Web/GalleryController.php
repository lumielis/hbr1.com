<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\GalleryService;
use Azera\Http\Response;

/**
 * Gallery (replaces `main.php?module=gallery` and `gallery.cgi`).
 *
 * Reads the `GAL_/GAP_/MCG_` schema — the live one; the legacy
 * `gallery/gallery_picture/media_category` tables are a byte-identical
 * duplicate, see deploy/GALLERY-SCHEMA.md.
 */
class GalleryController extends BaseController
{
    /** Entrance: the public categories. */
    public function indexAction(): string
    {
        $categories = $this->service(GalleryService::class)->categories();

        return $this->page('web/gallery', [
            'title'      => 'Galleries on HBR1',
            'categories' => $categories,
            // The legacy grid is three columns with a 13 px gap between rows.
            // Grouping here keeps the view free of index arithmetic.
            'rows'       => array_chunk($categories, 3),
        ]);
    }

    /** One category: its galleries. */
    public function categoryAction(int $category): Response
    {
        /** @var GalleryService $service */
        $service = $this->service(GalleryService::class);
        $data    = $service->category($category);

        if ($data === null) {
            return Response::status(404);
        }

        $offset    = $this->offset();
        $galleries = $service->galleriesFor($category, $offset);

        return Response::html($this->page('web/gallery_category', [
            'title'     => 'Galleries on HBR1',
            'category'  => $data,
            'galleries' => $galleries,
            'rows'      => array_chunk($galleries, 3),
            'pager'     => $this->pager($service->countGalleriesFor($category), $offset),
            // Both bands return to the entrance, as the legacy page did.
            'bands'     => [['href' => '/gallery', 'label' => 'ENTRANCE', 'colour' => '#77AA00']],
        ]));
    }

    /** One gallery: its pictures. */
    public function galleryAction(int $category, int $gallery): Response
    {
        /** @var GalleryService $service */
        $service = $this->service(GalleryService::class);
        $data    = $service->gallery($gallery);

        // A gallery must belong to the category named in the URL, so a
        // hand-edited path cannot mix two categories up.
        if ($data === null || $data['category'] !== $category) {
            return Response::status(404);
        }

        $offset   = $this->offset();
        $pictures = $service->picturesFor($gallery, $offset);

        return Response::html($this->page('web/gallery_gallery', [
            'title'    => 'Galleries on HBR1',
            'category' => $service->category($category),
            'gallery'  => $data,
            'pictures' => $pictures,
            'rows'     => array_chunk($pictures, 3),
            'pager'    => $this->pager($service->countPicturesFor($gallery), $offset),
            'bands'    => [['href' => '/gallery/' . $category, 'label' => 'LEVEL_UP', 'colour' => '#77AA00']],
        ]));
    }

    /** One picture, with previous/next navigation. */
    public function pictureAction(int $id): Response
    {
        $picture = $this->service(GalleryService::class)->picture($id);

        if ($picture === null) {
            return Response::status(404);
        }

        // The picture page has no category in its URL, so the band goes back to
        // the gallery it came from.
        $bands = [[
            'href'   => '/gallery/' . $picture['category'] . '/' . $picture['gallery'],
            'label'  => 'LEVEL_UP',
            'colour' => '#77AA00',
        ]];

        return Response::html($this->page('web/gallery_picture', [
            'title'   => 'Galleries on HBR1',
            'picture' => $picture,
            'bands'   => $bands,
        ]));
    }

    private function offset(): int
    {
        $offset = $this->context()->request()->query('offset');

        return is_numeric($offset) ? max(0, (int) $offset) : 0;
    }

    private function pager(int $total, int $offset): array
    {
        $limit   = GalleryService::PICTURES_PER_PAGE;
        $current = intdiv($offset, $limit) + 1;
        $last    = max(1, (int) ceil($total / $limit));

        return [
            'current' => $current,
            'last'    => $last,
            'total'   => $total,
            'hasPrev' => $current > 1,
            'hasNext' => $current < $last,
            'prevOff' => max(0, $offset - $limit),
            'nextOff' => $offset + $limit,
        ];
    }
}
