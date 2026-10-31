<?php

namespace App\Models;

use Azera\Orm\Model;

class Nowplaying extends Model
{
    public string $Id;
    public string $Station;
    public string $Artist;
    public string $Title;
    public string $Track;
    public string $Filename;
    public string $Genre;
    public string $Length;
    public string $Quality;
    public string $Year;
    public string $Album;
    public string $Comment;
    public string $Time;
    public function source(): string { return 'nowplaying'; }
    // Properties will be added automatically by the sync task.
}