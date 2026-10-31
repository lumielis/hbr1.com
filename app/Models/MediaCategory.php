<?php

namespace App\Models;

use Azera\Orm\Model;

class MediaCategory extends Model
{
    public int $id;
    public string $name;
    public string $path;
    public int $fixed;
    public int $status;
    public int $flags;
    public function source(): string { return 'media_category'; }
    // Properties will be added automatically by the sync task.
}