<?php

namespace App\Models;

use Azera\Orm\Model;

class Chat extends Model
{
    public int $id;
    public int $timestamp;
    public string $remote_addr;
    public string $name;
    public string $content;
    public string $color;
    public function source(): string { return 'chat'; }
    // Properties will be added automatically by the sync task.
}