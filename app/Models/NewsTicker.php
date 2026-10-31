<?php

namespace App\Models;

use Azera\Orm\Model;

class NewsTicker extends Model
{
    public int $Id;
    public string $Time;
    public string $Message;
    public int $Status;
    public function source(): string { return 'news_ticker'; }
    // Properties will be added automatically by the sync task.
}