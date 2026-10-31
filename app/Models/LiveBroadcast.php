<?php

namespace App\Models;

use Azera\Orm\Model;

class LiveBroadcast extends Model
{
    public string $Id;
    public string $Artist;
    public string $Title;
    public string $Html_LBot;
    public int $Status;
    public string $Station;
    public int $Fixed;
    public function source(): string { return 'live_broadcast'; }
    // Properties will be added automatically by the sync task.
}