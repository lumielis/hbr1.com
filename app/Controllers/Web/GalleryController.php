<?php

namespace App\Controllers\Web;

use App\Controllers\BaseController;
use App\Services\StationService;

/**
 * Gallery (replaces `main.php?module=gallery`).
 *
 * Data side lands in workstream W5; routing exists so redirects resolve.
 */
class GalleryController extends BaseController
{
    public function indexAction(): string
    {
        return $this->page('web/gallery', [
            'title' => 'Gallery - HBR1',
        ]);
    }

    public function categoryAction(int $category): string
    {
        return $this->page('web/gallery', [
            'title'    => 'Gallery - HBR1',
            'category' => $category,
        ]);
    }

    public function galleryAction(int $category, int $gallery): string
    {
        return $this->page('web/gallery', [
            'title'    => 'Gallery - HBR1',
            'category' => $category,
            'gallery'  => $gallery,
        ]);
    }

    public function pictureAction(int $id): string
    {
        return $this->page('web/gallery', [
            'title' => 'Gallery - HBR1',
            'picture' => $id,
        ]);
    }
}
