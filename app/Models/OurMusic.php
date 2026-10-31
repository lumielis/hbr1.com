<?php

namespace App\Models;

use Azera\Orm\Model;

class OurMusic extends Model
{
    public int $id;
    public int $time;
    public int $length;
    public string $file;
    public int $quality;
    public string $title;
    public string $info;
    public string $mixedby;
    public string $style;
    public function source(): string { return 'our_music'; }
    // Properties will be added automatically by the sync task.
}