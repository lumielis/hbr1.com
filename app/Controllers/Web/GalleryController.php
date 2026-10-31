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
        return $this->page('web/gallery', [
            'title'      => 'Gallery - HBR1',
            'categories' => $this->service(GalleryService::class)->categories(),
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

        $offset = $this->offset();

        return Response::html($this->page('web/gallery_category', [
            'title'     => $data['name'] . ' - Gallery - HBR1',
            'category'  => $data,
            'galleries' => $service->galleriesFor($category, $offset),
            'pager'     => $this->pager($service->countGalleriesFor($category), $offset),
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

        $offset = $this->offset();

        return Response::html($this->page('web/gallery_gallery', [
            'title'    => $data['title'] . ' - Gallery - HBR1',
            'category' => $service->category($category),
            'gallery'  => $data,
            'pictures' => $service->picturesFor($gallery, $offset),
            'pager'    => $this->pager($service->countPicturesFor($gallery), $offset),
        ]));
    }

    /** One picture, with previous/next navigation. */
    public function pictureAction(int $id): Response
    {
        $picture = $this->service(GalleryService::class)->picture($id);

        if ($picture === null) {
            return Response::status(404);
        }

        return Response::html($this->page('web/gallery_picture', [
            'title'   => ($picture['title'] !== '' ? $picture['title'] : 'Picture') . ' - Gallery - HBR1',
            'picture' => $picture,
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
