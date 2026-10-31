<?php

namespace App\Models;

use Azera\Orm\Model;

class GalleryPicture extends Model
{
    public int $id;
    public int $gallery_id;
    public string $title;
    public string $file;
    public string $copyright;
    public function source(): string { return 'gallery_picture'; }
    // Properties will be added automatically by the sync task.
}