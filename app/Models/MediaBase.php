<?php

namespace App\Models;

use Azera\Orm\Model;

class MediaBase extends Model
{
    public string $Id;
    public string $Title;
    public string $Info;
    public string $File;
    public string $Author;
    public string $Copyright;
    public string $Type;
    public function source(): string { return 'media_base'; }
    // Properties will be added automatically by the sync task.
}