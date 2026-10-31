<?php

namespace App\Models;

use Azera\Orm\Model;

class MediaPlaylist extends Model
{
    public int $id;
    public int $is_def;
    public string $name;
    public string $title;
    public string $file;
    public string $type;
    public function source(): string { return 'media_playlist'; }
    // Properties will be added automatically by the sync task.
}